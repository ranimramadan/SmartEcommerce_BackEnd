<?php
return [
    'emit_shipment_events' => true,
    'channels' => [
  'webhook' => env('NOTIFY_WEBHOOK', false),
  'admin'   => env('NOTIFY_ADMIN',   false),
  'user'    => env('NOTIFY_USER',    false),
],

];
