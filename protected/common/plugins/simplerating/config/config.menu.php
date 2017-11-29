<?php

$config['$root$']['menu']['data']['topics']['list']['top'] = [
    'text'    => '{{blog_menu_all_top}}',
    'link'    => '___path.root.url___/index/top/',
    'active'  => ['topic_kind' => ['top']],
    'submenu' => 'top',
];

/**
 *  Подменю топовых
 */
$config['$root$']['menu']['data']['top'] = [
    'init' => [
        'fill' => [
            'list' => ['*'],
        ],
    ],
    'list' => [
        '24h' => [
            'text'   => '{{blog_menu_top_period_24h}}',
            'link'   => '___path.root.url___/index/top/?period=1',
            'active' => ['compare_get_param' => ['period', 1]],
        ],
        '7d'  => [
            'text'   => '{{blog_menu_top_period_7d}}',
            'link'   => '___path.root.url___/index/top/?period=7',
            'active' => ['compare_get_param' => ['period', 7]],
        ],
        '30d' => [
            'text'   => '{{blog_menu_top_period_30d}}',
            'link'   => '___path.root.url___/index/top/?period=30',
            'active' => ['compare_get_param' => ['period', 30]],
        ],
        'all' => [
            'text'   => '{{blog_menu_top_period_all}}',
            'link'   => '___path.root.url___/index/top/?period=all',
            'active' => ['compare_get_param' => ['period', 'all']],
        ],

    ]
];

// EOF