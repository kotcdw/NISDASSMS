<?php
/**
 * Default configuration for a standard XAMPP install.
 * Do NOT edit passwords here — copy config.local.example.php to config.local.php
 * and override only what differs on your machine/server.
 */
$config = [
    'app_name'    => 'NISDAS SMS',
    'school_name' => 'Naylor International S.D.A School',
    'timezone'    => 'Africa/Accra',
    'debug'       => false,

    // Go-live on the internet: true sends every visitor to https:// (needs an SSL certificate).
    'force_https' => false,
    // true only when the host puts the site behind a proxy/load balancer that sets X-Forwarded-Proto
    'trust_proxy' => false,

    // Practice (training) copy at /demo with made-up records. Set false to switch it off.
    'practice_copy' => true,

    // Set false if Apache mod_rewrite is not available (URLs then use index.php/route)
    'pretty_urls' => true,

    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'nisdas_sms',
        'user'     => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    'uploads' => [
        'max_bytes' => 5 * 1024 * 1024, // 5 MB per file
        'allowed'   => [
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
        ],
    ],

    'session_name'     => 'nisdas_sess',
    'login_max_tries'  => 5,   // failed attempts…
    'login_lock_mins'  => 15,  // …within this many minutes locks the account/IP
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        $config = array_replace_recursive($config, $override);
    }
}

return $config;
