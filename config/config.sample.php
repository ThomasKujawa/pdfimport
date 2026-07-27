<?php

return [
    'accounts' => [
        [
            'name' => '',
            'imap' => [
                'host' => '{deine.domain.de:993/imap/ssl}',
                'user' => '',
                'pass' => '',
            ],
            'cloud' => [
                'url'  => 'https://www.deinedomain.de/remote.php/dav/files/deinuserpfad/',
                'user' => '',
                'pass' => 'app-passwort',
                'pfad' => '',
            ],
        ],
    ],

    'target_folder' => 'PDFImport',

    'senders' => [
        'email@eins.de',

    ],

    'epson_sender' => 'irgendwas@print.epsonconnect.com',
];
