<?php

/**
 * Подключаемые ресурсы - js- и css-файлы
 * <local_path|URL>
 * <local_path|URL> => <parameters_array>
 *
 * Параметры:
 *      'asset'   - указывает на один набор при слиянии файлов
 *      'name'    - "каноническое" имя файла
 *      'prepare' - файл готовится, но не включается в HTML
 *      'defer'   - добавляет атрибут defer (только для js)
 *      'async'   - добавляет атрибут async (только для js)
 *      'place'   - один из вариантов - 'head', 'body', 'bottom', место размещения (только для js)
 */
$config['assets']['data']['default']['js'] = [
    '___path.frontend.dir___/assets/libs/html5shiv.min.js' => ['browser' => 'lt IE 9'],
    '___path.frontend.dir___/assets/libs/jquery-1.12.4.min.js' => ['name' => 'jquery', 'asset' => 'mini'],
    '___path.frontend.dir___/assets/libs/jquery-migrate-1.4.1.min.js' => ['asset' => 'mini'],
    '___path.frontend.dir___/assets/libs/jquery-ui/js/jquery-ui-1.10.2.custom.min.js' => ['name' => 'jquery-ui', 'asset' => 'mini'],
    '___path.frontend.dir___/assets/libs/jquery-ui/js/localization/jquery-ui-datepicker-ru.js',
    '___path.frontend.dir___/assets/libs/jquery-ui/js/jquery.ui.autocomplete.html.js',

    /* Vendor libs */
    '___path.frontend.dir___/assets/libs/jquery.browser.js',
    '___path.frontend.dir___/assets/libs/jquery.scrollto.js',
    '___path.frontend.dir___/assets/libs/jquery.rich-array.min.js',
    '___path.frontend.dir___/assets/libs/jquery.form.js',
    '___path.frontend.dir___/assets/libs/jquery.cookie.js',
    '___path.frontend.dir___/assets/libs/jquery.serializejson.js',
    '___path.frontend.dir___/assets/libs/jquery.file.js',
    '___path.frontend.dir___/assets/libs/jquery.placeholder.min.js',
    '___path.frontend.dir___/assets/libs/jquery.charcount.js',
    '___path.frontend.dir___/assets/libs/jquery.imagesloaded.js',
    '___path.frontend.dir___/assets/libs/jquery.montage.min.js',
    '___path.frontend.dir___/assets/libs/jcrop/jquery.Jcrop.js',
    '___path.frontend.dir___/assets/libs/notifier/jquery.notifier.js',
    '___path.frontend.dir___/assets/libs/prettify/prettify.js',
    '___path.frontend.dir___/assets/libs/nprogress/nprogress.js',
    '___path.frontend.dir___/assets/libs/syslabel/syslabel.js' => ['place' => 'bottom'],
    '___path.frontend.dir___/assets/libs/prettyphoto/js/jquery.prettyphoto.js',
    '___path.frontend.dir___/assets/libs/rowgrid/jquery.row-grid.min.js' => ['asset' => 'mini'],
    '___path.frontend.dir___/assets/libs/jquery.pulse/jquery.pulse.min.js' => ['asset' => 'mini', 'async' => true],

    '___path.frontend.dir___/assets/libs/parsley/parsley.js',
    '___path.frontend.dir___/assets/libs/parsley/i18n/messages.ru.js',
    '___path.frontend.dir___/assets/libs/bootbox/bootbox.js',

    '___path.frontend.dir___/assets/libs/bootstrap-3/js/bootstrap.min.js' => ['name' => 'bootstrap'],
    '___path.frontend.dir___/assets/libs/jquery.fileapi/FileAPI/*'       => [
        'dir_from'  => '___path.frontend.dir___/assets/libs/jquery.fileapi/FileAPI/',
        'prepare'   => true,
        'merge'     => false,
    ],
    '___path.frontend.dir___/assets/libs/jquery.fileapi/FileAPI/FileAPI.min.js',
    '___path.frontend.dir___/assets/libs/jquery.fileapi/jquery.fileapi.js',

    /* Core */
    '___path.frontend.dir___/assets/engine/js/core/alto.js',
    '___path.frontend.dir___/assets/engine/js/core/alto.lang.js',
    '___path.frontend.dir___/assets/engine/js/core/alto.modal.js',
    '___path.frontend.dir___/assets/engine/js/core/init.js',

    [
        'options'   => ['place' => 'bottom'],
        'files'     => [

            /* Engine */
            '___path.frontend.dir___/assets/engine/js/utils/autocomplete.js',
            '___path.frontend.dir___/assets/engine/js/utils/favourite.js',
            '___path.frontend.dir___/assets/engine/js/utils/widgets.js',
            '___path.frontend.dir___/assets/engine/js/utils/pagination.js',
            '___path.frontend.dir___/assets/engine/js/utils/editor.js',
            '___path.frontend.dir___/assets/engine/js/utils/talk.js',
            '___path.frontend.dir___/assets/engine/js/utils/vote.js',
            '___path.frontend.dir___/assets/engine/js/utils/poll.js',
            '___path.frontend.dir___/assets/engine/js/utils/subscribe.js',
            '___path.frontend.dir___/assets/engine/js/utils/geo.js',
            '___path.frontend.dir___/assets/engine/js/utils/wall.js',
            '___path.frontend.dir___/assets/engine/js/utils/usernote.js',
            '___path.frontend.dir___/assets/engine/js/utils/comments.js',
            '___path.frontend.dir___/assets/engine/js/utils/blog.js',
            '___path.frontend.dir___/assets/engine/js/utils/user.js',
            '___path.frontend.dir___/assets/engine/js/utils/userfeed.js',
            '___path.frontend.dir___/assets/engine/js/utils/stream.js',
            //'___path.frontend.dir___/assets/engine/js/utils/swfuploader.js',
            '___path.frontend.dir___/assets/engine/js/utils/photoset.js',
            '___path.frontend.dir___/assets/engine/js/utils/toolbar.js',
            '___path.frontend.dir___/assets/engine/js/utils/settings.js',
            '___path.frontend.dir___/assets/engine/js/utils/topic.js',
            '___path.frontend.dir___/assets/engine/js/utils/userfield.js',
            '___path.frontend.dir___/assets/engine/js/utils/altoUploader.js',

            '___path.frontend.dir___/assets/engine/js/utils/altoMultiUploader.js',
            '___path.frontend.dir___/assets/engine/js/utils/altoImageManager.js',
            '___path.frontend.dir___/assets/engine/js/utils/altoPopover.js',
            '___path.frontend.dir___/assets/libs/masonry.pkgd.js',
            '___path.frontend.dir___/assets/libs/imagesloaded.pkgd.js',

            /* Template */
            '___path.skin.dir___/assets/js/template.js',
        ],
    ],
];

//потенциально проблемные файлы выводим в футере
$config['assets']['footer']['js'] = [
    '//yandex.st/share/share.js',
];

/* *** Editor markitUp *** */
$config['assets']['editor']['markitup']['js'] = [
    '___path.frontend.dir___/assets/libs/markitup/jquery.markitup.js'       => [
        'dir_from' => '___path.frontend.dir___/assets/libs/markitup/',
        'name'     => 'markitup',
    ],
];

$config['assets']['editor']['tinymce']['js'] = [
    '___path.frontend.dir___/assets/libs/tinymce/tinymce.min.js'       => [
        'name'              => 'tinymce',
        'dir_from'          => '___path.frontend.dir___/assets/libs/tinymce/',
        'prepare_subdirs'   => true, //
        'compress'          => false,
        'merge'             => false,
    ],
];

$config['assets']['data']['default']['css'] = [
    /* Bootstrap */
    '___path.frontend.dir___/assets/libs/bootstrap-3/css/bootstrap.min.css',

    /* Structure */
    '___path.skin.dir___/assets/css/base.css',
    '___path.frontend.dir___/assets/libs/markitup/skins/default/style.css',
    '___path.frontend.dir___/assets/libs/markitup/sets/default/style.css',
    '___path.frontend.dir___/assets/libs/jcrop/jquery.Jcrop.css',
    '___path.frontend.dir___/assets/libs/prettify/prettify.css',
    '___path.frontend.dir___/assets/libs/nprogress/nprogress.css',
    '___path.frontend.dir___/assets/libs/syslabel/syslabel.css',
    '___path.frontend.dir___/assets/libs/prettyphoto/css/prettyphoto.css',
    '___path.skin.dir___/assets/css/smoothness/jquery-ui.css',
    '___path.skin.dir___/assets/css/responsive.css',
    '___path.skin.dir___/assets/css/default.css',

    /* Theme */
    '?___path.skin.dir___/themes/___view.theme___/css/theme-style.css',
    /* Themer Icons */
    '___path.skin.dir___/assets/icons/css/fontello.css',

    /* tinyMCE */
    '___path.skin.dir___/assets/css/tinymce.css'       => [
        'name'      => 'template-tinymce.css',
        'prepare'   => true,
        'merge'     => false,
    ],
];

return $config;

// EOF