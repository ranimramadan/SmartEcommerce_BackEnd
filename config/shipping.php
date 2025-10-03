<?php
// config/shipping.php
return [
    'tracking_urls' => [
        'dhl'           => 'https://www.dhl.com/track?tracking-id={tracking}',
        'aramex'        => 'https://www.aramex.com/track/shipments/{tracking}',
        'internal_fleet'=> null, // بدون تتبع خارجي
    ],
];
