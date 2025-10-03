<?php

return [
    'auto_approve_verified_only' => true,  // الهجين: الموثّق approved تلقائيًا
    'auto_approve'               => false, // لو true: الكل approved فورًا
    'bad_words' => ['fuck','shit','bitch','وسخ','قذر','حمار','تافه'],
    'report_auto_pending_threshold' => 3,  // عند هذا العدد يتحوّل review إلى pending
];
