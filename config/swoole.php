<?php
return [
    'listern' => [
        'uri'  => '0.0.0.0',
        'port' => '8081',
        'type' => SWOOLE_SOCK_TCP,
    ],
    'memory' => [
        'fd' => 'int',
        'appid' => 'string',
        'status' => 'int',
    ],
    'socket' => [
        'port' => 8000,
        'worker_num'  => 8,
        'package_max_length' => 40 * 1024 * 1024,
        'open_eof_check' => true,
    ],
    'third_uri' => [
        'token_uri' => '192.168.2.177:23131',
        'discern_uri' => '192.168.2.177:7002',
    ],
    'original_name' => true,
    'daemonize' => false,
];