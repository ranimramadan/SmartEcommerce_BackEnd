<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\PaymentProvider;

class PaymentProvidersController extends Controller
{
    public function index(Request $req)
    {
        $q = PaymentProvider::query();

        if ($s = $req->get('q')) {
            $q->where(function($w) use ($s){
                $w->where('name','like',"%{$s}%")
                  ->orWhere('code','like',"%{$s}%");
            });
        }
        if ($req->filled('type'))      $q->where('type', $req->get('type'));          // online|offline
        if ($req->filled('is_active')) $q->where('is_active', $req->boolean('is_active'));

        $providers = $q->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn($p) => $this->mask($p));

        return response()->json($providers);
    }

    public function show(PaymentProvider $paymentProvider)
    {
        return response()->json($this->mask($paymentProvider));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        // نظّف مفاتيح config (سماح بقائمة محدودة)
        $data['config'] = $this->cleanConfig($data['config'] ?? []);

        $pp = PaymentProvider::create($data + ['is_active' => $data['is_active'] ?? true]);

        return response()->json($this->mask($pp), 201);
    }

    public function update(Request $request, PaymentProvider $paymentProvider)
    {
        $data = $this->validateData($request, $paymentProvider);

        $data['config'] = $this->cleanConfig($data['config'] ?? []);

        $paymentProvider->update($data);

        return response()->json($this->mask($paymentProvider));
    }

    public function destroy(PaymentProvider $paymentProvider)
    {
        $paymentProvider->delete();
        return response()->json(null, 204);
    }

    public function activate(PaymentProvider $paymentProvider)
    {
        $paymentProvider->update(['is_active' => true]);
        return response()->json($this->mask($paymentProvider));
    }

    public function deactivate(PaymentProvider $paymentProvider)
    {
        $paymentProvider->update(['is_active' => false]);
        return response()->json($this->mask($paymentProvider));
    }

    // ======================== Helpers ========================

    private function validateData(Request $request, ?PaymentProvider $existing = null): array
    {
        $codeUnique = Rule::unique('payment_providers','code');
        if ($existing) $codeUnique = $codeUnique->ignore($existing->id);

        $rules = [
            'name'      => $existing ? 'sometimes|string|max:100' : 'required|string|max:100',
            'code'      => $existing ? ['sometimes','string','max:50', $codeUnique] : ['required','string','max:50', $codeUnique],
            'type'      => $existing ? ['sometimes', Rule::in(['online','offline'])] : ['required', Rule::in(['online','offline'])],
            'is_active' => 'boolean',
            'sort_order'=> 'nullable|integer|min:0',
            'config'    => ['nullable','array', function($attr, $val, $fail) use ($request, $existing) {
                $type = $request->input('type', $existing?->type ?? 'online');
                if ($type === 'online' && empty(data_get($val, 'secret_key'))) {
                    $fail('config.secret_key is required for online providers.');
                }
                $code = $request->input('code', $existing?->code);
                if ($code === 'stripe' && empty(data_get($val, 'webhook_secret'))) {
                    $fail('config.webhook_secret is required for Stripe webhooks.');
                }
            }],
        ];

        return $request->validate($rules);
    }

    /** إخفاء المفاتيح الحساسة في الاستجابة */
    private function mask(PaymentProvider $p): array
    {
        $arr = $p->toArray();
        $cfg = $arr['config'] ?? [];

        foreach (['secret','secret_key','api_key','webhook_secret'] as $k) {
            if (isset($cfg[$k]) && is_string($cfg[$k])) {
                $cfg[$k] = $this->maskSecret($cfg[$k]);
            }
        }
        $arr['config'] = $cfg;

        return $arr;
    }

    private function maskSecret(string $v): string
    {
        if (strlen($v) <= 8) return '********';
        return substr($v, 0, 4) . str_repeat('*', max(0, strlen($v) - 8)) . substr($v, -4);
    }

    /** نسمح فقط بمفاتيح معروفة داخل config لتجنّب تخزين أي شيء عشوائي */
    private function cleanConfig(array $cfg): array
    {
        $allowed = [
            'secret_key','publishable_key','webhook_secret',
            'mode','account_id','api_version'
        ];
        return array_intersect_key($cfg, array_flip($allowed));
    }
}
