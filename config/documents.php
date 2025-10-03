<?php

return [
    'default' => [
        'enabled' => env('DOCS_ENABLED', true),
        'disk'    => env('DOCS_DISK', 'public'),
        'paper'   => env('DOCS_PAPER', 'a4'),
    ],

    'invoices' => [
        'enabled'  => env('DOCS_INVOICES_ENABLED', true),
        'disk'     => env('INVOICES_DISK', null),   // يرث من default لو null
        'paper'    => env('INVOICES_PAPER', null),  // يرث من default لو null
        // اختياري:
        'path'     => env('INVOICES_PATH', 'invoices'),
        'filename' => env('INVOICE_FILENAME', null), // لو null بنستعمل {$invoice->invoice_no}.pdf
    ],
];
