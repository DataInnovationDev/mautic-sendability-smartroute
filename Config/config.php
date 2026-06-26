<?php

declare(strict_types=1);

return [
    'name'        => 'Sendability SmartRoute',
    'description' => 'Routes emails through different SMTP servers based on contact fields or recipient domain. By Sendability.com.',
    'version'     => '1.0.0',
    'author'      => 'Ziyad Shoeky — Sendability.com',
    'routes'      => [
        'main'   => [],
        'public' => [],
        'api'    => [],
    ],
    'services'   => [],
    'parameters' => [
        'smartroute_enabled'        => false,
        'smartroute_secondary_dsn'  => 'smtp://localhost:587',
        'smartroute_mode'           => 'domain',
        'smartroute_custom_field'   => '',
        'smartroute_field_value'    => '',
        'smartroute_domain_list'    => '',
        'smartroute_from_email'             => '',
        'smartroute_from_name'              => '',
        'smartroute_secondary_percentage'   => 100,
    ],
];
