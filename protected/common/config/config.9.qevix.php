<?php
/*-------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *-------------------------------------------------------
 */

/**
 * qevix.php
 *
 * Qevix configuration
 */

return [
    'default' => [
        // Разрешённые теги
        'cfgAllowTags' => [
            // вызов метода с параметрами
            [
                ['p', 'cut', 'a', 'img', 'i', 'b', 'u', 's', 'small', 'video', 'em', 'strong', 'nobr', 'li',
                      'ol', 'ul', 'sup', 'abbr', 'sub', 'acronym', 'h4', 'h5', 'h6', 'br', 'hr', 'pre', 'code',
                      'codeline', 'object', 'param', 'embed', 'blockquote', 'iframe', 'table', 'tbody', 'thead', 'th',
                      'tr', 'td', 'div', 'alto'],
            ],
        ],
        // Коротие теги типа
        'cfgSetTagShort' => [
            [
                ['br', 'img', 'hr', 'cut',],
            ],
        ],
        // Преформатированные теги
        'cfgSetTagPreformatted'     => [
            [
                ['pre', 'code', 'codeline', 'video']
            ],
        ],
        // Разрешённые параметры тегов
        'cfgAllowTagParams' => [
            // вызов метода
            [
                'img',
                ['src',
                      'title',
                      'data-rel',
                      'data-src',
                      'alt'    => '#text',
                      'align'  => ['right', 'left', 'center', 'middle'],
                      'width'  => '#regexp(\d+(%|px)?)',
                      'height' => '#int',
                      'hspace' => '#int',
                      'vspace' => '#int',
                      'class'  => '#text',
                ]
            ],
            // следующий вызов метода
            [
                'a',
                ['title',
                      'href',
                      'rel' => '#text',
                      'name' => '#text',
                      'target' => ['_blank'],
                      'class' => ['ls-user', 'topic-photoset-item'],
                      'data-alto-role' => '#text',
                      'data-api' => '#text',
                ]
            ],
            // и т.д.
            [
                'cut',
                ['name']
            ],
            [
                'object',
                ['width' => '#int', 'height' => '#int', 'type'  => '#text',
                      'data'  => ['#domain' => [
                          'youtube.com', 'www.youtube.com', 'rutube.ru', 'vimeo.com', 'player.vimeo.com',
                      ]],
                ]
            ],
            [
                'param',
                ['name' => '#text', 'value' => '#text']
            ],
            [
                'embed',
                ['src' => ['#domain' => [
                    'youtube.com', 'www.youtube.com', 'rutube.ru', 'vimeo.com', 'player.vimeo.com', 'static.googleusercontent.com',
                ]],
                      'type'              => '#text',
                      'allowscriptaccess' => '#text',
                      'allowfullscreen'   => '#text',
                      'width'             => '#int',
                      'height'            => '#int',
                      'flashvars'         => '#text',
                      'wmode'             => '#text',
                ]
            ],
            [
                'acronym',
                ['title']
            ],
            [
                'abbr',
                ['title']
            ],
            [
                'iframe',
                ['width' => '#int',
                      'height' => '#int',
                      'src'   => ['#domain' => [
                          'youtube.com', 'www.youtube.com', 'rutube.ru', 'vimeo.com', 'player.vimeo.com', 'vk.com', 'vkontakte.ru',
                          'slideshare.net', 'mixcloud.com', 'soundcloud.com', 'maps.google.ru', 'issuu.com',
                      ]]]
            ],
            [
                'th',
                ['colspan' => '#int', 'rowspan' => '#int', 'height'  => '#int', 'width' => '#int',
                      'align' => ['right', 'left', 'center', 'justify'],
                ]
            ],
            [
                'td',
                ['colspan' => '#int', 'rowspan' => '#int', 'height'  => '#int', 'width' => '#int',
                      'align' => ['right', 'left', 'center', 'justify'],
                ]
            ],
            [
                'table',
                ['border' => '#int', 'cellpadding' => '#int', 'cellspacing' => '#int', 'height' => '#int', 'width' => '#int',
                      'align'  => ['right', 'left', 'center'],
                ]
            ],
            [
                'div',
                [
                    'class'=> ['alto-photoset', 'alto-photoset js-topic-photoset-list', 'spoiler', 'spoiler-title', 'spoiler-slider', 'spoiler-text'],
                    'data-width',
                    'data-alto-tag-name',
                    'data-alto-tag-attr',
                ],
            ],
            [
                'alto',
                [
                    'name',
                    'data-alto-tag-name',
                    'data-alto-tag-attr',
                ],
            ],
        ],
        // Параметры тегов являющиеся обязательными
        'cfgSetTagParamsRequired' => [
            [
                'img',
                'src'
            ],
        ],
        // Теги которые необходимо вырезать из текста вместе с контентом
        'cfgSetTagCutWithContent' => [
            [
                ['script', 'style']
            ],
        ],
        // Вложенные теги
        'cfgSetTagChilds' => [
            [
                'ul',
                ['li'],
                false,
                true
            ],
            [
                'ol',
                ['li'],
                false,
                true
            ],
            [
                'object',
                'param',
                false,
                true
            ],
            [
                'object',
                'embed',
                false,
                false
            ],
            [
                'table',
                ['tr', 'tbody', 'thead'],
                false,
                true
            ],
            [
                'tbody',
                ['tr', 'td'],
                false,
                true
            ],
            [
                'thead',
                ['tr', 'th'],
                false,
                true
            ],
            [
                'tr',
                ['td', 'th'],
                false,
                true
            ],
        ],
        // Если нужно оставлять пустые не короткие теги
        'cfgSetTagIsEmpty' => [
            [
                ['param', 'embed', 'a', 'iframe', 'alto']
            ],
        ],
        // Не нужна авто-расстановка <br>
        'cfgSetTagNoAutoBr' => [
            [
                ['ul', 'ol', 'object', 'table', 'tr', 'tbody', 'thead']
            ]
        ],
        // Теги с обязательными параметрами
        'cfgSetTagParamDefault' => [
            [
                'embed',
                'wmode',
                'opaque',
                true
            ],
        ],
        // Отключение авто-добавления <br>
        'cfgSetAutoBrMode' => [
            [
                true
            ]
        ],
        // Автозамена
        'cfgSetAutoReplace' => [
            ['+/-', '(c)', '(с)', '(C)', '(С)', '(r)', '(R)'],
            ['±', '©', '©', '©', '©', '®', '®'],
        ],
        // Список допустимых протоколов для ссылок
        'cfgSetLinkProtocolAllow' => [
            [
                ['http', 'https', 'ftp']
            ]
        ],
        'cfgSetTagNoTypography' => [
            [
                ['code', 'video', 'object']
            ],
        ],
        // Теги, после которых необходимо пропускать одну пробельную строку
        'cfgSetTagBlockType' => [
            [
                ['h4', 'h5', 'h6', 'ol', 'ul', 'blockquote', 'pre', 'table', 'iframe', 'p']
            ]
        ],
    ],

    // настройки для обработки текста в результатах поиска
    'search'  => [
        // Разрешённые теги
        'cfgAllowTags' => [
            // вызов метода с параметрами
            [
                ['span'],
            ],
        ],
        // Разрешённые параметры тегов
        'cfgAllowTagParams' => [
            [
                'span',
                ['class' => '#text']
            ],
        ],
    ],
];

// EOF