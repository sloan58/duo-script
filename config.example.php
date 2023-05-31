<?php

// config.php
return [
    'ldap' => [
        'host' => '',
        'bind_dn' => '',
        'password' => '',
        'search_base' => ''
    ],
    'duo' => [
        'client_id' => '',
        'client_secret' => '',
        'api_hostname' => '',
        'redirect_uri' => 'https://<your_host>/duo-callback.php',
    ]
];