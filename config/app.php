<?php

return [
    'id'         => 'api',
    'env'        => 'dev',
    'debug'      => true,
    'version'    => '1.1.1',
    'timezone'   => 'PRC',
    'params'     => [],
    'aliases'    => [],
    'components' => [
        'httpServer' => ['port' => 9501, 'max_request' => 1000000],
        'logger'     => ['level' => 'info'],
    ],
    'services'   => [],
    'plugins'    => [
        'debugger',
        'cors',
    ],
];