<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
        'proxy' => env('PROXY'),  // Optional, will be used for all requests
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],
    'bpm' => [
        'url' => env('BPM_API_URL', 'https://unicobpm.hassisto.com'), // Il secondo parametro è un fallback
    ],

    /*
    |--------------------------------------------------------------------------
    | Applicativi esterni interrogabili/scrivibili da UnicoBPM
    |--------------------------------------------------------------------------
    |
    | UnicoLoan e UnicoOAM sono due deploy dello stesso codebase che condividono
    | gli stessi database (proforma, unicooam): entrambi espongono le stesse API
    | generiche (/api/pratiche/{id}, /api/models/{model}/fields, /api/models/{model}/{id}).
    | La configurazione di un Process (completion_write_app) o di un
    | ProcessTaskItem 'blacklist_check' (config['app']) indica quale dei due
    | interrogare/scrivere; il default applicato nel codice se non specificato
    | è 'unicoloan'.
    |
    */
    'apps' => [
        'unicoloan' => [
            'url' => env('UNICOLOAN_API_URL', 'https://unicoloan.hassisto.com'),
            'label' => 'UnicoLoan',
        ],
        'unicooam' => [
            'url' => env('UNICOOAM_API_URL', 'https://unicooam.hassisto.com'),
            'label' => 'UnicoOAM',
        ],
        'proforma' => [
            'url' => env('PROFORMA_API_URL', 'https://proforma.hassisto.com'),  
            'label' => 'Proforma',
        ],
           'unicogdpr' => [
            'url' => env('UNICOGDPR_API_URL', 'https://unicogdpr.hassisto.com'),
            'label' => 'UnicoGDPR',
        ],
        'daishboard' => [
            'url' => env('DAISHBOARD_API_URL', 'https://daishboard.hassisto.com'),
            'label' => 'Dashboard',
        ],
           'whistle' => [
            'url' => env('WHISTLE_API_URL', 'https://whistle.unicocompilance.it'),
            'label' => 'WhistleBlowing',
        ],
                 'unicoaiact' => [
            'url' => env('UNICOAIACT_API_URL', 'https://unicoaiact.unicocompilance.it'),
            'label' => 'AI ACT',
        ],
    ],

];
