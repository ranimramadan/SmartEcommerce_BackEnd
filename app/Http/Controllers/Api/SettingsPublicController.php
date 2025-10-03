<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\PaymentProvider;

class SettingsPublicController extends Controller
{
    public function index(Request $request)
    {
        $public = Setting::publicMap();

        // مزودات دفع فعّالة (اختياري)
        $providers = PaymentProvider::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id','name','code','type']);

        return response()->json([
            'success' => true,
            'data'    => [
                'settings'  => $public,
                'providers' => $providers,
            ],
        ]);
    }
}
