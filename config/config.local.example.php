<?php
// Copy this file to config.local.php and change what differs on your machine or server.
// config.local.php is never overwritten by updates.
return [
    // true only while testing on your own computer. Must be false on the live system.
    'debug' => false,

    // Live on the internet with an SSL certificate: send everyone to https://
    // 'force_https' => true,

    'db' => [
        'name'     => 'nisdas_sms',
        'user'     => 'root',      // on a hosting account: the database user created in cPanel
        'password' => '',          // and its password
    ],
];
