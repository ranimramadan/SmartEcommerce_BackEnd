<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Payment\GatewayManager;

class WebhooksController extends Controller
{
    /** POST /api/webhooks/{provider}  مثال: provider = stripe | cod | paypal... */
    public function handle(Request $request, string $provider, GatewayManager $gm)
    {
        $gateway = $gm->gateway($provider); // رح يرمي استثناء لو غير معرّف
        // كل Gateway عندك فيه canHandleWebhook/handleWebhook
        if (method_exists($gateway, 'canHandleWebhook') && !$gateway->canHandleWebhook($request)) {
            // ما نرفض 2xx عشان Stripe ما يعيد باستمرار، نرد 200 ونلوج
            \Log::warning("Webhook ignored by {$provider}: signature/header missing");
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        try {
            $gateway->handleWebhook($request);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            \Log::error("Webhook {$provider} error: ".$e->getMessage(), ['trace'=>$e->getTraceAsString()]);
            // ردّ 200 حتى لا يسبّب إعادة بلا نهاية في بيئات الاختبار — أو 400 لو بدك.
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 200);
        }
    }
}


