<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use DateTimeInterface;

class InvoiceService
{
    /**
     * إنشاء فاتورة من طلب.
     * - لو $lines == null: يضيف تلقائيًا كل البنود المتبقية غير المفوترة.
     * - كل line = ['order_item_id' => int, 'qty' => int, 'discount' => 0, 'tax' => 0]
     */
    public function createFromOrder(
        Order $order,
        ?array $lines = null,
        string $status = Invoice::STATUS_ISSUED,
        ?string $notes = null,
        float $shippingTotal = 0.0,
        DateTimeInterface|string|null $dueAt = null
    ): Invoice {
        return DB::transaction(function () use ($order, $lines, $status, $notes, $shippingTotal, $dueAt) {
            $invoice = new Invoice([
                'order_id'       => $order->id,
                'invoice_no'     => $this->generateInvoiceNumber(),
                'status'         => $status,
                'currency'       => $order->currency,
                'shipping_total' => $shippingTotal,
                'notes'          => $notes,
                'due_at'         => $dueAt,
                'issued_at'      => $status === Invoice::STATUS_ISSUED ? now() : null,
            ]);
            $invoice->save();

            if (is_array($lines) && count($lines)) {
                $this->addItems($invoice, $lines);
            } else {
                // فوترة تلقائية لما تبقى من كل بند
                $order->loadMissing('items:id,order_id,qty,price,name');
                foreach ($order->items as $oi) {
                    $qty = $this->remainingInvoiceableQty($oi);
                    if ($qty > 0) {
                        InvoiceItem::createFromOrderItem($invoice->id, $oi, $qty);
                    }
                }
            }

            $invoice->updateTotals();

            return $invoice;
        });
    }

    /**
     * إضافة بنود (جزئية) لفاتورة موجودة.
     * كل عنصر: ['order_item_id' => int, 'qty' => int, 'discount' => 0, 'tax' => 0]
     */
    public function addItems(Invoice $invoice, array $lines): Invoice
    {
        return DB::transaction(function () use ($invoice, $lines) {

            $orderItemIds = collect($lines)->pluck('order_item_id')->filter()->unique()->values()->all();
            /** @var \Illuminate\Database\Eloquent\Collection<int, OrderItem> $orderItems */
            $orderItems = OrderItem::whereIn('id', $orderItemIds)->get()->keyBy('id');

            foreach ($lines as $line) {
                $oiId     = (int) ($line['order_item_id'] ?? 0);
                $qty      = (int) ($line['qty'] ?? 0);
                $discount = (float) ($line['discount'] ?? 0);
                $tax      = (float) ($line['tax'] ?? 0);

                if ($qty < 1 || !$orderItems->has($oiId)) {
                    continue;
                }

                $oi = $orderItems[$oiId];

                // حماية: لازم الـ order_item لنفس الطلب
                if ((int) $oi->order_id !== (int) $invoice->order_id) {
                    throw new \RuntimeException("Order item #{$oi->id} does not belong to invoice.order_id");
                }

                $item = InvoiceItem::createFromOrderItem($invoice->id, $oi, $qty);

                if ($discount > 0) {
                    $item->applyDiscount($discount);
                }
                if ($tax > 0) {
                    $item->applyTax($tax);
                }
            }

            $invoice->updateTotals();

            return $invoice;
        });
    }

    /** حساب الكمية المتبقية للفوترة لبند طلب واحد */
    public function remainingInvoiceableQty(OrderItem $orderItem): int
    {
        $already = (int) InvoiceItem::where('order_item_id', $orderItem->id)->sum('qty');
        return max(0, (int) $orderItem->qty - $already);
    }

    /** هل فُوِّر الطلب بالكامل؟ */
    public function isFullyInvoiced(Order $order): bool
    {
        $order->loadMissing('items:id,order_id,qty');
        foreach ($order->items as $oi) {
            if ($this->remainingInvoiceableQty($oi) > 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * تعليم الفاتورة مدفوعة (يُستدعى من Webhook الدفع أو PaymentService).
     * ممكن نربط دفعة بالفاتورة (payment->invoice_id) إن مررتِ Payment.
     */
    public function markAsPaid(Invoice $invoice, ?Payment $payment = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $payment) {
            if ($invoice->status !== Invoice::STATUS_PAID) {
                $invoice->markAsPaid();
            }

            if ($payment && is_null($payment->invoice_id)) {
                $payment->invoice_id = $invoice->id;
                $payment->save();
            }

            return $invoice->refresh();
        });
    }

    /**
     * توليد PDF للفواتير.
     *
     * - يحترم الملفات القديمة (ما بيبدّل مسارها).
     * - الجديدة تتبع documents.invoices.* أولاً ثم invoices.* كـ fallback.
     *
     * @param string|null $locale           'ar' أو 'en' (لو null بياخد لغة التطبيق)
     * @param bool        $forceRegenerate  true = أعِد توليد الملف حتى لو موجود
     * @param bool        $respectExisting  true = احترم المسار القديم إن موجود
     */
    public function generatePdf(
        Invoice $invoice,
        ?string $locale = null,
        bool $forceRegenerate = false,
        bool $respectExisting = true
    ): string {
        // enabled: documents.invoices.enabled → documents.default.enabled → invoices.generate_pdf
        $enabled = (bool) ($this->cfg('enabled', config('documents.default.enabled', true)));
        if (! $enabled) {
            return $invoice->pdf_path ?? '';
        }

        // علاقات + مجاميع
        $invoice->loadMissing(['order.user', 'items.orderItem']);
        $invoice->updateTotals();

        // إعدادات القرص والورق
        $disk  = $this->cfg('disk',  config('documents.default.disk',  'public'));
        $paper = $this->cfg('paper', config('documents.default.paper', 'a4'));

        // احترم الملف القديم إذا موجود وما بدنا نعيد توليد
        if ($respectExisting && $invoice->pdf_path) {
            if (! $forceRegenerate && Storage::disk($disk)->exists($invoice->pdf_path)) {
                return $invoice->pdf_path;
            }
            $targetPath = $invoice->pdf_path; // إعادة توليد بنفس المسار القديم
        } else {
            // فاتورة جديدة → استعملي path/filename الحاليين من الإعداد
            $dir       = trim((string) $this->cfg('path', 'invoices'), '/');
            $filename  = (string) ($this->cfg('filename') ?: "{$invoice->invoice_no}.pdf");
            $targetPath = $dir . '/' . ltrim($filename, '/');
        }

        // HTML داخلي (بدون Blade)
        $html = $this->buildInvoiceHtml($invoice, $locale);

        // DomPDF
        $pdf = PDF::loadHTML($html)
            ->setPaper($paper)
            ->setOptions([
                'isRemoteEnabled'      => true,
                'isHtml5ParserEnabled' => true,
                'defaultFont'          => 'DejaVu Sans',
            ]);

        Storage::disk($disk)->put($targetPath, $pdf->output());

        // خزّن المسار النهائي
        $invoice->forceFill(['pdf_path' => $targetPath])->save();

        return $targetPath;
    }

    /**
     * يبني HTML الفاتورة داخليًا (بدون Blade) مع دعم العربية/الإنجليزية.
     */
    public function buildInvoiceHtml(Invoice $invoice, ?string $locale = null): string
    {
        $invoice->loadMissing(['order.user', 'items.orderItem']);

        $locale = $locale ?: (app()->getLocale() ?: 'en');
        $isRtl  = ($locale === 'ar');

        $tEn = [
            'invoice'     => 'Invoice',
            'store'       => 'Store',
            'date'        => 'Date',
            'currency'    => 'Currency',
            'no'          => '#',
            'product'     => 'Product',
            'price'       => 'Price',
            'qty'         => 'Qty',
            'discount'    => 'Discount',
            'tax'         => 'Tax',
            'total'       => 'Total',
            'subtotal'    => 'Subtotal',
            'shipping'    => 'Shipping',
            'grand_total' => 'Grand Total',
            'footer'      => 'This invoice was generated electronically and does not require a signature.',
        ];

        $tAr = [
            'invoice'     => 'فاتورة',
            'store'       => 'المتجر',
            'date'        => 'التاريخ',
            'currency'    => 'العملة',
            'no'          => '#',
            'product'     => 'المنتج',
            'price'       => 'السعر',
            'qty'         => 'الكمية',
            'discount'    => 'الخصم',
            'tax'         => 'الضريبة',
            'total'       => 'الإجمالي',
            'subtotal'    => 'الإجمالي قبل الخصم',
            'shipping'    => 'الشحن',
            'grand_total' => 'الإجمالي النهائي',
            'footer'      => 'تم توليد هذه الفاتورة إلكترونيًا ولا تحتاج توقيعًا.',
        ];

        $t           = $isRtl ? $tAr : $tEn;
        $company     = config('app.name', 'My Shop');
        $dir         = $isRtl ? 'rtl'   : 'ltr';
        $align       = $isRtl ? 'right' : 'left';
        $cur         = $invoice->currency;
        $totalsFloat = $isRtl ? 'left'  : 'right'; // أحسبها خارج الـheredoc

        $css = <<<CSS
<style>
    @page { margin: 20mm; }
    body { font-family: DejaVu Sans, Arial, sans-serif; direction: {$dir}; text-align: {$align}; font-size: 13px; color:#222; }
    .header { display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .title { font-size: 18px; font-weight: bold; }
    .meta  { font-size: 12px; color: #555; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
    th { background: #f5f5f5; }
    .totals { width: 40%; float: {$totalsFloat}; margin-top: 12px; }
    .totals td { border: none; padding: 4px 0; }
    .footer { clear: both; margin-top: 30px; font-size: 11px; color: #777; }
</style>
CSS;

        $issuedAt = $invoice->issued_at?->format('Y-m-d') ?? '';

        $header = <<<HTML
<div class="header">
    <div class="title">{$t['invoice']} #{$invoice->invoice_no}</div>
    <div class="meta">
        <div>{$t['store']}: {$company}</div>
        <div>{$t['date']}: {$issuedAt}</div>
        <div>{$t['currency']}: {$cur}</div>
    </div>
</div>
HTML;

        $rows = '';
        foreach ($invoice->items as $i => $item) {
            $n     = $i + 1;
            $name  = e($item->product_name);
            $price = number_format((float) $item->unit_price, 2) . " {$cur}";
            $qty   = (int) $item->qty;
            $disc  = number_format((float) $item->discount_amount, 2) . " {$cur}";
            $tax   = number_format((float) $item->tax_amount, 2) . " {$cur}";
            $line  = number_format((float) $item->line_total, 2) . " {$cur}";

            $rows .= "<tr>
                <td>{$n}</td>
                <td>{$name}</td>
                <td>{$price}</td>
                <td>{$qty}</td>
                <td>{$disc}</td>
                <td>{$tax}</td>
                <td>{$line}</td>
            </tr>";
        }

        $table = <<<HTML
<table>
    <thead>
        <tr>
            <th>{$t['no']}</th>
            <th>{$t['product']}</th>
            <th>{$t['price']}</th>
            <th>{$t['qty']}</th>
            <th>{$t['discount']}</th>
            <th>{$t['tax']}</th>
            <th>{$t['total']}</th>
        </tr>
    </thead>
    <tbody>{$rows}</tbody>
</table>
HTML;

        $subtotal = number_format((float) $invoice->subtotal, 2) . " {$cur}";
        $discount = number_format((float) $invoice->discount_total, 2) . " {$cur}";
        $tax      = number_format((float) $invoice->tax_total, 2) . " {$cur}";
        $ship     = number_format((float) $invoice->shipping_total, 2) . " {$cur}";
        $grand    = number_format((float) $invoice->grand_total, 2) . " {$cur}";

        $totals = <<<HTML
<table class="totals">
    <tr><td>{$t['subtotal']}:</td><td>{$subtotal}</td></tr>
    <tr><td>{$t['discount']}:</td><td>- {$discount}</td></tr>
    <tr><td>{$t['tax']}:</td><td>{$tax}</td></tr>
    <tr><td>{$t['shipping']}:</td><td>{$ship}</td></tr>
    <tr><td><strong>{$t['grand_total']}:</strong></td><td><strong>{$grand}</strong></td></tr>
</table>
HTML;

        $footer = "<div class=\"footer\">{$t['footer']}</div>";

        return "<!doctype html><html lang=\"{$locale}\"><head><meta charset=\"utf-8\">{$css}</head><body>{$header}{$table}{$totals}{$footer}</body></html>";
    }

    /** مولّد رقم فاتورة بسيط وآمن ضد التكرار (fallback داخل السيرفيس) */
    private function generateInvoiceNumber(): string
    {
        do {
            $candidate = 'INV-' . date('Ymd') . '-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Invoice::where('invoice_no', $candidate)->exists());

        return $candidate;
    }

   
    private function cfg(string $key, $default = null)
    {
        $v = config("documents.invoices.$key");
        if ($v !== null) return $v;

        $d = config("documents.default.$key");
        if ($d !== null) return $d;

        // توافق خلفي لو عندك config/invoices.php قديم
        return config("invoices.$key", $default);
    }

    /** URL عام للملف (مفيد للفرونت) */
    public function publicUrl(Invoice $invoice): ?string
    {
        if (! $invoice->pdf_path) return null;
        $disk = $this->cfg('disk', 'public');
        return Storage::disk($disk)->url($invoice->pdf_path);
    }
}
