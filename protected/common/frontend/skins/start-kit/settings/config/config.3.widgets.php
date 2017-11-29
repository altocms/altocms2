<?php
/*-------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *-------------------------------------------------------
 *
 * Настройка Виджетов
 * Widgets settings
 */

/*

// Объявление виджета,
// здесь <id> - это идентификатор виджета и он должен быть уникальным
$config['widgets'][<id>] = [
    // ...
];

// пример
$config['widgets']['stream'] = [
    'name'     => 'stream', // имя виджета
    'wgroup'   => 'right',  // имя группы виджетов в шаблоне, куда виджет будет добавлен
    'priority' => 100,      // приоритет - чем выше приоритет, тем раньше в группе выводится виджет
                            // виджеты с приоритетом 'top' выводятся раньше других в группе
    'on' => ['index', 'blog'], // где показывать виджет
    'off' => ['admin/*', 'settings/*', 'profile/*', 'talk/*', 'people/*'], // где НЕ показывать виджет
    'action' => [
        'blog' => ['{topics}', '{topic}', '{blog}'], // для совместимости с LiveStreet
    ],
    'display' => true,  // true - выводить, false - не выводить,
                        // ['date_from'=>'2011-10-10', 'date_upto'=>'2011-10-20'] - выводить с... по...
];

*/
// Прямой эфир
$config['widgets']['stream'] = array(
    'name'      => 'stream', // исполняемый виджет Stream (class WidgetStream)
    'type'      => 'exec',   // тип - exec - исполняемый (если не задавать, то будет определяться автоматически)
    'wgroup'    => 'right',  // группа, куда нужно добавить виджет
    'priority'  => 100,      // приоритет
    'action'    => [
        'index',
        'community',
        'filter',
        'blogs',
        'blog' => ['{topics}', '{topic}', '{blog}'],
        'tag',
    ],
    'params' => [
        'items' => [
            'comments' => ['text' => '{{widget_stream_comments}}', 'type'=>'comment'],
            'topics' => ['text' => '{{widget_stream_topics}}', 'type'=>'topic'],
        ],
        'limit' => 20, // max items for display
    ],
);

$config['widgets']['blogInfo.tpl'] = [
    'name'      => 'blogInfo.tpl',
    'type'      => 'template',   // шаблонный виджет
    'template'  => 'widgets/widget.blogInfo.tpl',
    'wgroup'    => 'right',
    'action'    => [
        'content' => ['{add}', '{edit}'],
    ],
];

$config['widgets']['blogAvatar.tpl'] = [
    'name'      => 'blogAvatar.tpl',
    'type'      => 'template',   // шаблонный виджет
    'template'  => 'widgets/widget.blogAvatar.tpl',
    'wgroup'    => 'right',
    'priority'  => 999,
    'on' => [
        'blog/add', 'blog/edit',
    ],
];

// Теги
$config['widgets']['tags'] = [
    'name'      => 'tags',
    'type'      => 'exec',
    'wgroup'    => 'right',
    'priority'  => 50,
    'action'    => [
        'index',
        'community',
        'filter',
        'comments',
        'blog' => ['{topics}', '{topic}', '{blog}'],
        'tag',
    ],
    'params' => [
        'limit' => 70, // max items for display
    ],
];

// Блоги
$config['widgets']['blogs'] = [
    'name'      => 'blogs',
    'type'      => 'exec',
    'wgroup'    => 'right',
    'priority'  => 1,
    'action' => [
        'index',
        'community',
        'filter',
        'comments',
        'blog' => ['{topics}', '{topic}', '{blog}'],
    ],
    'params' => [
        'limit' => 10, // max items for display
    ],
];

$config['widgets']['profile.sidebar.tpl'] = [
    'name'      => 'profile.sidebar.tpl',
    'type'      => 'template',   // шаблонный виджет
    'template'  => 'actions/profile/action.profile.sidebar.tpl',
    'wgroup' => 'right',
    'priority' => 150,
    'on' => 'profile, talk, settings',
];

$config['widgets']['people.sidebar.tpl'] = [
    'name'      => 'people.sidebar.tpl',
    'type'      => 'template',   // шаблонный виджет
    'template'  => 'actions/people/action.people.sidebar.tpl',
    'wgroup'    => 'right',
    'on'        => 'people, search',
];

$config['widgets']['userfeedBlogs'] = [
    'name'      => 'userfeedBlogs',
    'type'      => 'exec',
    'wgroup'    => 'right',
    'action'    => [
        'feed' => ['{index}'],
    ],
];

$config['widgets']['userfeedUsers'] = [
    'name'      => 'userfeedUsers',
    'type'      => 'exec',
    'wgroup'    => 'right',
    'action'    => [
        'feed' => ['{index}'],
    ],
];

$config['widgets']['blog.tpl'] = [
    'name' => 'widgets/widget.blog.tpl',
    'wgroup' => 'right',
    'priority' => 300,
    'action' => [
        'blog' => ['{topic}']
    ],
];

$config['widgets']['topbanner_image'] = [
    'name'      => 'topbanner_image.tpl',
    'type'      => 'template',   // шаблонный виджет
    'template'  => 'widgets/widget.topbanner_image.tpl',
    'wgroup' => 'topbanner',
    'params' => [
        'image' => '___path.skin.dir___/assets/images/header-banner.jpg',
        'style' => '',
        'title' => '___view.name___',
    ],
    'display' => true,
];

$config['widgets']['topbanner_slider'] = [
    'name'      => 'topbanner_slider.tpl',
    'type'      => 'template',   // шаблонный виджет
    'template'  => 'widgets/widget.topbanner_slider.tpl',
    'wgroup' => 'topbanner',
    'params' => [
        'images' => [
            [
                'image' => '___path.skin.dir___/assets/images/header-banner1.jpg',
                'title' => 'Picture 1',
            ],
            [
                'image' => '___path.skin.dir___/assets/images/header-banner2.jpg',
                'title' => 'Picture 2',
            ],
            [
                'image' => '___path.skin.dir___/assets/images/header-banner3.jpg',
                'title' => '<a href="#">Picture 3</a>',
            ],
        ],
    ],
    'display' => false,
];

$config['widgets']['toolbar_admin'] = [
    'name'      => 'toolbar_admin.tpl',
    'type'      => 'template',   // шаблонный виджет
    'template'  => 'widgets/widget.toolbar_admin.tpl',
    'wgroup'    => 'toolbar',
    'priority'  => 'top',
];

$config['widgets']['toolbar_scrollup'] = [
    'name'      => 'toolbar_scrollup.tpl',
    'type'      => 'template',   // шаблонный виджет
    'template'  => 'widgets/widget.toolbar_scrollup.tpl',
    'wgroup'    => 'toolbar',
    'priority'  => -100,
];

// EOF