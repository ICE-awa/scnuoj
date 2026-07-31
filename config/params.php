<?php
return [
    // judge 数据所在目录
    'judgeProblemDataPath' => dirname(__FILE__) . '/../judge/data/',

    // polygon 数据所在目录
    'polygonProblemDataPath' => dirname(__FILE__) . '/../polygon/data/',

    'components.formatter' => [
        'class' => app\components\Formatter::class,
        'defaultTimeZone' => 'Asia/Shanghai',
        'locale' => 'zh-CN',
        'dateFormat' => 'yyyy年MM月dd日',
        'datetimeFormat' => 'yyyy/MM/dd HH:mm:ss',
        'thousandSeparator' => '&thinsp;',
    ],
    'components.setting' => [
        'class' => app\components\Setting::class,
    ],
    // Configure trusted reverse proxies here before enabling exam IP restrictions.
    // Only trust headers from the Docker/Caddy hop, never directly from clients.
    'trustedHosts' => [
        '172.16.0.0/12' => [
            'X-Real-IP',
            'X-Forwarded-For',
            'X-Forwarded-Host',
            'X-Forwarded-Proto',
            'X-Forwarded-Port',
        ],
    ],
    'secureHeaders' => [
        'X-Real-IP',
        'X-Forwarded-For',
        'X-Forwarded-Host',
        'X-Forwarded-Proto',
        'X-Forwarded-Port',
        'Front-End-Https',
        'X-Rewrite-Url',
        'X-Original-Host',
    ],
    'ipHeaders' => [
        'X-Real-IP',
        'X-Forwarded-For',
    ],
    'examAllowedCidrs' => [
        '10.191.0.0/16',
    ],
    'examLabCidr' => '10.191.0.0/16',
];
