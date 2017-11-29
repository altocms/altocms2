<?php
$config['view']['theme'] = 'default';

$config['smarty']['dir']['templates'] = [
    'themes' => '___path.skins.dir___/___view.skin___/themes/',
    'tpls'   => '___path.skins.dir___/___view.skin___/tpls/',
];

$config['assets']['data']['default']['css'] = [
    '___path.frontend.dir___/vendor/markitup/skins/default/style.css',
    '___path.frontend.dir___/vendor/markitup/sets/default/style.css',
    '___path.frontend.dir___/vendor/jcrop/jquery.Jcrop.css',
    '___path.frontend.dir___/vendor/prettify/prettify.css',
    '___path.frontend.dir___/vendor/syslabel/syslabel.css',
    '___path.skin.dir___/assets/css/jquery-ui.css',
    '___path.skin.dir___/assets/css/jquery-notifier.css',

    '___path.skin.dir___/assets/css/bootstrap.min.css',
    '___path.skin.dir___/assets/css/simpleline/simple-line-icons.css',
    '___path.skin.dir___/assets/css/datepicker.css',
    '___path.skin.dir___/assets/css/fullcalendar.css',
    '___path.skin.dir___/assets/css/main-modals.css',
    '___path.skin.dir___/assets/css/main-admin.css',

    '___path.skin.dir___/assets/css/main.css',
    '___path.skin.dir___/assets/css/main-forms.css',
    '___path.skin.dir___/themes/___view.theme___/theme.css',
];

$config['assets']['data']['default']['js'] = [
    '___path.frontend.dir___/vendor/jquery-1.12.4.min.js' => ['name' => 'jquery', 'asset' => 'mini'],
    '___path.frontend.dir___/vendor/jquery-migrate-1.4.1.min.js' => ['asset' => 'mini'],
    '___path.frontend.dir___/vendor/jquery-ui/js/jquery-ui-1.10.2.custom.min.js' => ['name' => 'jquery-ui', 'asset' => 'mini'],
    '___path.frontend.dir___/vendor/jquery-ui/js/localization/jquery-ui-datepicker-ru.js',
    '___path.frontend.dir___/vendor/jquery-ui/js/jquery.ui.autocomplete.html.js',
    '___path.frontend.dir___/vendor/markitup/jquery.markitup.js' => ['name' => 'markitup'],
    '___path.frontend.dir___/vendor/autosize/jquery.autosize.min.js' => ['asset' => 'mini'],
    '___path.frontend.dir___/vendor/tinymce_4/tinymce.min.js'       => [
        'dir_from' => '___path.frontend.dir___/vendor/tinymce_4/',
        'name'     => 'tinymce_4',
        'compress' => false,
        'merge'    => false
    ],
    '___path.frontend.dir___/vendor/tinymce_4/plugins/*'       => [
        'dir_from'  => '___path.frontend.dir___/vendor/tinymce_4/',
        'prepare'   => true,
        'compress'  => false,
        'merge'     => false
    ],
    '___path.frontend.dir___/vendor/tinymce_4/langs/*'       => [
        'dir_from' => '___path.frontend.dir___/vendor/tinymce_4/',
        'prepare'  => true,
        'compress' => false,
        'merge'    => false
    ],
    '___path.frontend.dir___/vendor/tinymce_4/skins/*'       => [
        'dir_from' => '___path.frontend.dir___/vendor/tinymce_4/',
        'prepare'  => true,
        'compress' => false,
        'merge'    => false
    ],
    '___path.frontend.dir___/vendor/tinymce_4/themes/*'       => [
        'dir_from' => '___path.frontend.dir___/vendor/tinymce_4/',
        'prepare'  => true,
        'compress' => false,
        'merge'    => false
    ],

    '___path.frontend.dir___/bootstrap-3/js/bootstrap.min.js' => ['name' => 'bootstrap'],

    '___path.skin.dir___/assets/js/excanvas.min.js' => ['asset' => 'mini'],
    '___path.skin.dir___/assets/js/jquery.flot.min.js' => ['asset' => 'mini'],
    '___path.skin.dir___/assets/js/jquery.flot.resize.min.js' => ['asset' => 'mini'],
    '___path.skin.dir___/assets/js/jquery.peity.min.js' => ['asset' => 'mini'],
    '___path.skin.dir___/assets/js/fullcalendar.min.js' => ['asset' => 'mini'],
    '___path.skin.dir___/assets/js/midnight.js',
    //'___path.skin.dir___/assets/js/midnight.dashboard.js',
    '___path.frontend.dir___/vendor/notifier/jquery.notifier.js',
    '___path.frontend.dir___/vendor/jquery.scrollto.js',
    '___path.frontend.dir___/vendor/jquery.rich-array.min.js',

    '___path.frontend.dir___/vendor/jquery.form.js',
    '___path.frontend.dir___/vendor/jquery.cookie.js',
    '___path.frontend.dir___/vendor/jquery.serializejson.js',
    '___path.frontend.dir___/vendor/jquery.file.js',
    '___path.frontend.dir___/vendor/jcrop/jquery.Jcrop.js',
    '___path.frontend.dir___/vendor/jquery.placeholder.min.js',
    '___path.frontend.dir___/vendor/jquery.charcount.js',
    '___path.frontend.dir___/vendor/prettify/prettify.js',
    '___path.frontend.dir___/vendor/syslabel/syslabel.js',
    '___path.frontend.dir___/vendor/bootbox/bootbox.min.js' => ['asset' => 'mini'],

    '___path.frontend.dir___/libs/js/core/main.js',
    '___path.frontend.dir___/libs/js/core/hook.js',
    '___path.frontend.dir___/libs/js/core/modal.js',
    '___path.frontend.dir___/assets/engine/js/utils/favourite.js',
    '___path.frontend.dir___/assets/engine/js/utils/vote.js',
    '___path.frontend.dir___/assets/engine/js/utils/poll.js',
    '___path.frontend.dir___/assets/engine/js/utils/subscribe.js',
    '___path.frontend.dir___/assets/engine/js/utils/geo.js',
    '___path.frontend.dir___/assets/engine/js/utils/usernote.js',
    '___path.frontend.dir___/assets/engine/js/utils/comments.js',
    '___path.frontend.dir___/assets/engine/js/utils/blog.js',
    '___path.frontend.dir___/assets/engine/js/utils/user.js',
    '___path.frontend.dir___/assets/engine/js/utils/userfeed.js',
    '___path.frontend.dir___/assets/engine/js/utils/admin-userfield.js',
    '___path.frontend.dir___/assets/engine/js/utils/settings.js',
    '___path.frontend.dir___/assets/engine/js/utils/topic.js',
    '___path.frontend.dir___/assets/engine/js/utils/altoImageManager.js',
    '___path.skin.dir___/assets/js/admin.js',
    '___path.skin.dir___/assets/js/admin.user.js',
    '___path.skin.dir___/assets/js/jquery.formstyler.min.js',
];

$config['assets']['footer']['js'] = false;

$config['path']['skin']['img']['dir'] = '___path.skin.dir___/assets/img/'; // папка с изображениями скина
$config['path']['skin']['img']['url'] = '___path.skin.url___/assets/img/'; // URL с изображениями скина

//$config['compress']['css']['merge'] = false; // указывает на необходимость слияния файлов по указанным блокам.
//$config['compress']['css']['use'] = false; // указывает на необходимость компрессии файлов. Компрессия используется только в активированном

return $config;

// EOF