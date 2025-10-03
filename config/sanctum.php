<?php

use Laravel\Sanctum\Sanctum;

return [
    // الدومينات/الأصول التي تُعامل كـ "stateful" (جلسات كوكيز للـ SPA)
    'stateful' => [
        Sanctum::currentApplicationUrlWithPort(), // يقرأ من APP_URL + البورت
        Sanctum::currentRequestHost(),            // يلتقط نفس الهوست أثناء الطلب
        'localhost:3000',
        '127.0.0.1:3000',
    ],

    // حارس التوثيق الافتراضي للجلسات
    'guard' => ['web'],

    // تخص التوكنات (غير مؤثرة على جلسات SPA)
    'expiration' => null,
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

  
];
