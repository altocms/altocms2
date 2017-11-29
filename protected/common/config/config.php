<?php
/*-------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *-------------------------------------------------------
 */

/********************************************************
 * ATTENTION! Don't touch this file!
 *
 * All changes of settings you need to do in application
 * configuration file, which usually placed here:
 * /app/config/config.local.php
 ********************************************************/

/********************************************************
 * ВНИМАНИЕ! Не вносите изменения в этот файл!
 *
 * Все изменения настроек нужно выполнять в файле
 * конфигурации приложения, который обычно находится здесь:
 * /app/config/config.local.php
 ********************************************************/

/*
 * Базовые настройки внешнего вида
 */
$config['view']['skin']             = 'experience-simple';          // скин
$config['view']['theme']            = 'default';                    // тема
$config['view']['name']             = 'Your Site Name';             // название сайта
$config['view']['wysiwyg']          = false;    // использовать или нет визуальный редактор
$config['view']['noindex']          = true;     // "прятать" или нет ссылки от поисковиков, оборачивая их в тег <noindex> и добавляя rel="nofollow"

$config['view']['html']['description'] = 'Description of your site';   // meta tag description
$config['view']['html']['keywords']    = 'site, google, internet';     // meta tag keywords

$config['view']['html']['description_max_words'] = 20; // количество слов из топика для вывода в метатег description

//$config['view']['html']['title']       = '___view.name___';  // строка, которая всегда добавляется в конец тега <title>
$config['view']['html']['title_max']   = 0;       // максимальное число частей, из которых состоит тег <title>
$config['view']['html']['title_sep']   = ' / ';   // разделитель для формирования тега <title>

$config['view']['skill_length'] = 2;// Длинна представления силы пользователя 0, 1, 2 или 3 знака после запятой. Округление идёт в большую сторону.
$config['view']['rating_length'] = 2;// Длинна представления рейтинга пользователя 0, 1, или 2 знака после запятой. Округление идёт в большую сторону.

$config['view']['set_editors'] = [
    'default' => 'markitup',    // default simple editor
    'wysiwyg' => 'tinymce',     // wysiwyg editor
];
/**
 * Настройка пагинации
 */
$config['pagination']['pages']['count'] = 9;                  // количество ссылок на другие страницы в пагинации


/* ----------------------------------------------------------------------------
 * Настройка путей
 *
 * Как правило полный путь до папки или файла содержит в названии 'dir'
 * URL-путь содержит в названии 'url'
 */

$config['path']['root']['url'] = F::UrlBase() . '/';
$config['path']['root']['dir'] = ALTO_DIR_ROOT . '/';

//$config['path']['offset_request_url']   = 0;        // иногда помогает если сервер использует внутренние реврайты
$config['path']['root']['subdir']       = '';         // Директория относительно корня домена

/**
 * Параметры сервера для статики. По умолчанию совпадают с основным сервером
 */
$config['path']['static']['url']        = '___path.root.url___';               // Полный URL до static-сервера
$config['path']['static']['dir']        = '___path.root.dir___';               // Полный путь до static-сервера в файловой системе

$config['path']['uploads']['root']      = '/uploads';                          // папка для загрузки файлов
$config['path']['uploads']['images']    = '___path.uploads.root___/images/';
$config['path']['uploads']['files']     = '___path.uploads.root___/files/';

$config['path']['tmp']['dir']           = ALTO_DIR_MAIN . '/_tmp/';         // путь к папке для временных файлов
$config['path']['runtime']['dir']       = ALTO_DIR_ROOT . '/_run/';         // путь к папке для runtime-файлов
$config['path']['runtime']['url']       = '___path.root.subdir___/_run/';         // URL для runtime-файлов

$config['path']['frontend']['dir']      = '___path.dir.common___/frontend/';
$config['path']['templates']['dir']      = '___path.dir.common___/frontend/skins/';
//$config['path']['frontend']['url']      = '___path.root.url___/common/frontend/frontend/';

$config['path']['skins']['dir']         = '___path.templates.dir___/';             // путь к папке для скинов
$config['path']['skin']['dir']          = '___path.skins.dir___/___view.skin___/';      // путь к папке текущего скина
//$config['path']['skin']['url']          = '___path.root.url___common/frontend/skin/___view.skin___/';    // URL-путь к папке текущего скина
//$config['path']['skin']['assets']['url']= '___path.runtime.url___assets/skin/___view.skin___/';
$config['path']['skin']['assets']['dir']= '___path.runtime.dir___assets/skin/___view.skin___/';

/**
 * Настройки шаблонизатора Smarty
 */
$config['smarty']['path']['template'] = '___path.skins.dir___/___view.skin___/';
$config['smarty']['path']['compiled'] = '___path.tmp.dir___/templates/___view.skin___-___view.theme___/compiled/';
$config['smarty']['path']['cache']    = '___path.tmp.dir___/templates/___view.skin___-___view.theme___/cache/';
$config['smarty']['path']['plug']     = '___path.dir.engine___/classes/modules/viewer/plugs/';

$config['smarty']['options']['compile_check']          = true;   // Проверять или нет файлы шаблона на изменения перед компиляцией, false может значительно увеличить быстродействие, но потребует ручного удаления кеша при изменения шаблона
$config['smarty']['options']['force_compile']          = false;  // Принудительно компилировать шаблоны (отменяет действие 'compile_check')
$config['smarty']['options']['merge_compiled_includes']= false;  // Слияние скомпилированных шаблонов (увеличивает скорость рендеринга при большом числе подшаблонов)
$config['smarty']['options']['cache_lifetime']         = false;  // Кеширование отрендеренных шаблонов

/**
 * Настройки плагинов
 */
$config['sys']['plugins']['activation_dir'] = '___path.dir.app___plugins/'; // файл со списком активных плагинов в каталоге /plugins/
$config['sys']['plugins']['activation_file'] = 'plugins.dat'; // файл со списком активных плагинов в каталоге /plugins/

/**
 * Настройки куков
 */
$config['sys']['cookie']['host'] = null;                    // хост для установки куков
$config['sys']['cookie']['path'] = '/';                     // путь для установки куков
$config['sys']['cookie']['time'] = 60 * 60 * 24 * 3;        // время жизни куки когда пользователь остается залогиненым на сайте, 3 дня

/**
 * Настройки сессий
 */
$config['sys']['session']['standart'] = true;                               // Использовать или нет стандартный механизм сессий
$config['sys']['session']['name']     = 'PHPSESSID';                        // название сессии
$config['sys']['session']['timeout']  = null;                               // Тайм-аут сессии в секундах
$config['sys']['session']['host']     = '___sys.cookie.host___';            // хост сессии в куках
$config['sys']['session']['path']     = '___sys.cookie.path___';            // путь сессии в куках
/**
 * Настройки почтовых уведомлений
 */
$config['sys']['mail']['type']             = 'mail';                        // Какой тип отправки использовать
$config['sys']['mail']['from_email']       = 'admin@admin.adm';             // Мыло с которого отправляются все уведомления
$config['sys']['mail']['from_name']        = 'Почтовик ___view.name___';    // Имя с которого отправляются все уведомления
$config['sys']['mail']['charset']          = 'UTF-8';                // Какую кодировку использовать в письмах
$config['sys']['mail']['encoding']         = 'quoted-printable';     // Какое кодирование использовать в письмах: 8bit, 7bit, binary, base64, quoted-printable
$config['sys']['mail']['smtp']['host']     = 'localhost';            // Настройки SMTP - хост
$config['sys']['mail']['smtp']['port']     = 25;                     // Настройки SMTP - порт
$config['sys']['mail']['smtp']['user']     = '';                     // Настройки SMTP - пользователь
$config['sys']['mail']['smtp']['password'] = '';                     // Настройки SMTP - пароль
$config['sys']['mail']['smtp']['secure']   = '';                     // Настройки SMTP - протокол шифрования: tls, ssl
$config['sys']['mail']['smtp']['auth']     = true;                   // Использовать авторизацию при отправке
$config['sys']['mail']['include_comment']  = true;                   // Включает в уведомление о новых комментах текст коммента
$config['sys']['mail']['include_talk']     = true;                   // Включает в уведомление о новых личных сообщениях текст сообщения

/**
 * Настройки кеширования
 */
/*
 * Режим автокеширования
 *
 * Если true и задан тип кеширования, то кеширование работает всегда
 * Если false и задан тип кеширования, то кеширование работает только по запросу
 */
$config['sys']['cache']['use']    = false;
/*
 * Доступные типы (виды) кеширования
 */
$config['sys']['cache']['backends'] = [
    'file'   => 'File',         // файловое кеширование
    'memory' => 'Memcached',    // используется Memcached
    'xcache' => 'Xcache',       // используется XCache
    'tmp'    => 'Tmp',          // используется временное хранилище в памяти
];

/*
 * Разрешить принудительное кеширование в модулях. Если разрешено, то в модулях можно программно задать
 * принудительное кеширование, даже если тип кеширования задан false
 *
 * Возможные значения:
 *  - [...]       - разрешено принудительное кеширование заданных видов
 *  - true        - разрешено принудительное кеширование любого вида
 *  - false       - запрещено принудительное кеширование
 */
$config['sys']['cache']['force']    = ['file', 'tmp'];

/*
 * Тип кеширования:
 *      file    - файловое
 *      memory  - используется Memcached
 *      xcache  - используется XCache
 */
$config['sys']['cache']['type']   = 'file';                         // тип кеширования по умолчанию
$config['sys']['cache']['dir']    = '___path.tmp.dir___/cache/';    // каталог для файлового кеша
$config['sys']['cache']['prefix'] = 'alto_cache';                   // префикс кеширования, чтоб можно было на одной машине держать несколько сайтов с общим кешевым хранилищем
$config['sys']['cache']['directory_level'] = 1;         // уровень вложенности директорий файлового кеша
$config['sys']['cache']['solid']  = true;               // Настройка использования раздельного и монолитного кеша для отдельных операций
/*
 * Задержка "протухания" кеша при конкурирующих запросах (сек)
 *
 * Если установлено в 0 или false, то поддержка конкурирующих запросов отключена
 */
$config['sys']['cache']['concurrent_delay']    = 0; //60;

/**
 * Настройки логирования
 */
$config['sys']['logs']['dir']            = '___path.tmp.dir___/logs/';       // папка для логов
$config['sys']['logs']['file']           = 'log.log';       // файл общего лога
$config['sys']['logs']['sql_query']      = false;            // логировать или нет SQL запросы
$config['sys']['logs']['sql_query_file'] = 'sql_query.log'; // файл лога SQL запросов
$config['sys']['logs']['sql_error']      = true;            // логировать или нет ошибки SQl
$config['sys']['logs']['sql_error_file'] = 'sql_error.log'; // файл лога ошибок SQL
$config['sys']['logs']['cron']           = true;            // логировать или нет cron скрипты
$config['sys']['logs']['cron_file']      = 'cron.log';      // файл лога запуска крон-процессов

$config['sys']['logs']['error_file']        = 'error.log';  // файл лога ошибок
$config['sys']['logs']['error_extinfo']     = false;        // выводить ли дополнительную информацию в лог ошибок
$config['sys']['logs']['error_callstack']   = false;        // выводить стек вызовов в лог ошибок
$config['sys']['logs']['error_norepeat']    = true;         // не повторять вывод одинаковых ошибок

$config['sys']['logs']['size_for_rotate'] = 1000000;        // максимальный размер для ротации логов (если 0 - без ротации)
$config['sys']['logs']['count_for_rotate'] = 99;            // максимальное число файлов в ротации (если 0 - без ограничений)

/*
 * Параметры для определения IP-адрес посетителя:
 *   'trusted'          - ключи переменной $_SERVER, где нужно искать IP-адрес
 *   'non_trusted'      - ключи переменной $_SERVER, где НЕ нужно искать IP-адрес
 *   'multi_backward'   - если в $_SERVER[<key>] несколько адресов, то выбираем с конца
 *   'exclude'          - список исключаемых IP-адресов
 *   'exclude_server'   - IP-адрес посетителя не может совпадать с IP-адресом сервера
 *   'exclude_private'  - исключать IP частных сетей
 *   'default'          - если IP так и не определен
 */
$config['sys']['ip']['trusted']         = ['REMOTE_ADDR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_VIA'];
$config['sys']['ip']['non_trusted']     = [];
$config['sys']['ip']['backward']        = true;
$config['sys']['ip']['exclude']         = ['127.0.0.1', 'fe80::1', '::1'];
$config['sys']['ip']['exclude_server']  = true;
$config['sys']['ip']['exclude_private'] = true;
$config['sys']['ip']['default']         = '127.0.0.1';

$config['sys']['include']['check_file'] = false; // Проверка подключаемых файлов на "UTF-8 without BOM"

/**
 * Настройка memcache
 */
$config['memcache']['servers'][0]['host'] = '127.0.0.1';
$config['memcache']['servers'][0]['port'] = '11211';
$config['memcache']['servers'][0]['persistent'] = true;
$config['memcache']['compression'] = true;


/**
 * Общие настройки
 */
$config['general']['close']['mode']     = false; // использовать закрытый режим работы сайта, сайт будет доступен только авторизованным пользователям
$config['general']['close']['actions']  = ['login', 'registration', 'captcha']; // enabled actions in closed mode
$config['general']['rss_editor_mail']   = '___sys.mail.from_email___'; // мыло редактора РСС
$config['general']['reg']['invite']     = false; // использовать режим регистрации по приглашению или нет. Если использовать, то регистрация будет доступна ТОЛЬКО по приглашениям!
$config['general']['reg']['activation'] = false; // использовать активацию при регистрации или нет

$config['general']['show']['stats'] = [1]; // Показывать статистику: false - никому, true - всем, [1] - список ID юзеров, крму показывать

/**
 * Настройки ACL(Access Control List — список контроля доступа)
 */
$config['acl']['create']['blog']['rating']                =  1;     // порог рейтинга при котором юзер может создать коллективный блог
$config['acl']['create']['comment']['rating']             = -10;    // порог рейтинга при котором юзер может добавлять комментарии
$config['acl']['create']['comment']['limit_time']         =  10;    // время в секундах между постингом комментариев, если 0 то ограничение по времени не будет работать
$config['acl']['create']['comment']['limit_time_rating']  = -1;     // рейтинг, выше которого перестаёт действовать ограничение по времени на постинг комментов. Не имеет смысла при $config['acl']['create']['comment']['limit_time']=0
$config['acl']['create']['topic']['limit_time']           =  240;   // время в секундах между созданием записей, если 0 то ограничение по времени не будет работать
$config['acl']['create']['topic']['limit_time_rating']    =  5;     // рейтинг, выше которого перестаёт действовать ограничение по времени на создание записей
$config['acl']['create']['topic']['limit_rating']         =  -20;   // порог рейтинга при котором юзер может создавать топики (учитываются любые блоги, включая персональные), как дополнительная защита от спама/троллинга
$config['acl']['create']['talk']['limit_time']          =  300;     // время в секундах между отправкой инбоксов, если 0 то ограничение по времени не будет работать
$config['acl']['create']['talk']['limit_time_rating']   =  1;       // рейтинг, выше которого перестаёт действовать ограничение по времени на отправку инбоксов
$config['acl']['create']['talk_comment']['limit_time']        =  10;// время в секундах между отправкой инбоксов, если 0 то ограничение по времени не будет работать
$config['acl']['create']['talk_comment']['limit_time_rating'] =  5; // рейтинг, выше которого перестаёт действовать ограничение по времени на отправку инбоксов
$config['acl']['create']['wall']['limit_time']          = 20;   // рейтинг, выше которого перестаёт действовать ограничение по времени на отправку сообщений на стену
$config['acl']['create']['wall']['limit_time_rating']   = 0;    // рейтинг, выше которого перестаёт действовать ограничение по времени на отправку сообщений на стену
$config['acl']['vote']['comment']['rating']             = -3;   // порог рейтинга при котором юзер может голосовать за комментарии
$config['acl']['vote']['blog']['rating']                = -5;   // порог рейтинга при котором юзер может голосовать за блог
$config['acl']['vote']['topic']['rating']               = -7;   // порог рейтинга при котором юзер может голосовать за топик
$config['acl']['vote']['user']['rating']                = -1;   // порог рейтинга при котором юзер может голосовать за пользователя
$config['acl']['vote']['topic']['limit_time']           = 60*60*24*20; // ограничение времени голосования за топик
$config['acl']['vote']['comment']['limit_time']         = 60*60*24*5;  // ограничение времени голосования за комментарий

/**
 * Настройки модулей
 */
// Модуль Blog
$config['module']['blog']['per_page']        = 20;                  // Число блогов на страницу
$config['module']['blog']['users_per_page']  = 20;                  // Число пользователей блога на страницу
$config['module']['blog']['personal_good']   = -5;                  // Рейтинг топика в персональном блоге ниже которого он считается плохим
$config['module']['blog']['collective_good'] = -3;                  // рейтинг топика в коллективных блогах ниже которого он считается плохим
$config['module']['blog']['index_good']      =  8;                  // Рейтинг топика выше которого(включительно) он попадает на главную
$config['module']['blog']['encrypt']         = 'alto';              // Ключ XXTEA шифрования идентификаторов в ссылках приглашения в блоги
$config['module']['blog']['avatar_size'] = [100,64,48,24,0];   // Список размеров аватаров у блога. 0 - исходный размер ** Old frontend compatibility

// Модуль Topic
$config['module']['topic']['new_time']   = 60*60*24*1;              // Время в секундах в течении которого топик считается новым
$config['module']['topic']['per_page']   = 10;                      // Число топиков на одну страницу
$config['module']['topic']['images_per_page']   = 12;               // Число картинок на одну страницу
$config['module']['topic']['group_images_per_page']   = 6;          // Число картинок группы на одну страницу
$config['module']['topic']['max_length'] = 15000;                   // Максимальное количество символов в одном топике
$config['module']['topic']['link_max_length'] = 500;                // Максимальное количество символов в одном топике-ссылке
$config['module']['topic']['question_max_length'] = 500;            // Максимальное количество символов в одном топике-опросе
$config['module']['topic']['allow_empty_tags'] = true;              // Разрешать или нет не заполнять теги
$config['module']['topic']['draft_link'] = false;                   // разрешить показывать черновик по прямой ссылке
$config['module']['topic']['on_duplicate_url'] = 1;                 // 0 - игнорировать; 1 - добавлять порядковый номер;

// Модуль Uploader

// Default options for file uploading
$config['module']['uploader']['files']['default'] = [
    'file_maxsize'    => '5Mb', // максимальный размер загружаемого файла
    'file_extensions' => [ //расширения файлов, которые можно прикреплять к топикам
        'zip','rar','gz','mp3',
        'doc', 'docx', 'xls', 'xlsx', 'pdf','djv','djvu',
        'gif', 'png', 'jpg', 'jpeg',
    ],
    'upload'          => [ // параметры сохранения при загрузке
        'return_url' => true,   // возвращает URL загруженного файла
    ],
];

$config['module']['uploader']['images']['default'] = array(
    '$extends$' => '___module.uploader.files.default___',
    'image_extensions' => ['gif', 'png', 'jpg', 'jpeg'],
    'max_width'  => 8000, // максимальная ширина загружаемых изображений в пикселях
    'max_height' => 6000, // максимальная высота загружаемых изображений в пикселях
    'url_maxsize' => '2Mb', // максимальный размер изображения для загрузки по URL
    'original' => [
        'save' => false,            // надо ли сохрагять оригинальное изображение
        'suffix' => '-original',    // суффикс оригинального изображения
    ],
    // параметры сохранения при загрузке
    'transform' => array(
        'max_width'  => 800,        // максимальная ширина сохраняемого изображения
        'max_height' => 600,        // максимальная высота сохраняемого изображения
        'bg_color'  => '#ffffff',   // цвет фона при преобразовании изображений
        'watermark' => [
            'enable' => false,
            'image' => [
                'path' => '___path.static.dir___/___path.uploads.root___',
                'file' => 'altocms.png',
                'topleft' => false,
                'position' => '0,0', // вместо пикселей можно указать center для одной или обоих координат
            ],
        ],
        '@mime(jpeg)' => [
            'quality' => 80,
        ],
        '@mime(gif)'  => [
            'animation' => false,
        ],
        '@mime(png)'  => [
            //'save_as' => 'jpg',
        ],
    ),
);

$config['module']['uploader']['images']['profile_avatar'] = array(
    '$extends$' => '___module.uploader.images.default___',
    'size' => ['large' => '96x96crop', 'medium' => '64x64crop', 'small' => '32x32crop'],
    'transform' => array(
        'max_width'  => 250,        // максимальная ширина сохраняемой аватары
        'max_height' => 250,        // максимальная высота сохраняемой аватары
        'aspect_ratio' => '1',      // соотношение ширины и высоты
        'watermark' => [
            'enable' => false,
        ],
        '@mime(gif)'  => [
            'animation' => true,
        ],
    ),
);

$config['module']['uploader']['images']['profile_photo'] = [
    '$extends$' => '___module.uploader.images.default___',
    'transform' => [
        'aspect_ratio' => '1',      // соотношение ширины и высоты
    ]
];

$config['module']['uploader']['images']['topic'] = [
    '$extends$' => '___module.uploader.images.default___',
    'transform' => [
        'watermark' => [
            'enable' => false,
        ],
    ],
];

$config['module']['uploader']['images']['photoset'] = [
    '$extends$' => '___module.uploader.images.default___',
    'transform' => [
        'watermark' => [
            'enable' => true,
        ],
    ],
];

$config['module']['uploader']['images']['video'] = [
    '$extends$' => '___module.uploader.images.default___',
    'transform' => [
        'max_width'  => 640,        // максимальная ширина фрейма
        'max_height' => 360,        // максимальная высота фрейма
        'aspect_ratio' => '16:9',   // соотношение ширины и высоты фрейма
        'watermark' => [
            'enable' => false,
        ],
    ],
];


$config['module']['uploader']['drives'] = [
    'local' => [
        'dir' => '___path.root.dir___',
        'url' => '___path.root.url___',
    ],
];

// Модуль Image
$config['module']['image']['autoresize'] = true;

$config['module']['image']['libs'] = 'Gmagick,Imagick,GD'; // 'GD', 'Imagick' or 'Gmagick', or several libs separated by comma

// Модуль Menu
$config['module']['menu']['default_length'] = 20;
$config['module']['menu']['blog_logo_size'] = '24x24crop';

/*
 * Настройка ЧПУ топика
 * Допустимые параметры:
 *      %year%       - год топика
 *      %month%      - месяц
 *      %day%        - день
 *      %hour%       - час
 *      %minute%     - минуты
 *      %second%     - секунды (54)
 *      %login%      - логин автора топика (admin)
 *      %blog_url%   - url коллективного блога (для личных блогов будет заменен на логин автора)
 *      %topic_type% - тип топика
 *      %topic_id%   - id топика
 *      %topic_url%  - относительный URL топика
 *
 * В шаблоне обязательно должен быть %topic_id% или %topic_url%
 */
$config['module']['topic']['url'] = '%topic_id%.html';          // постоянная ссылка на топик (permalink)

// Модуль User
$config['module']['user']['per_page']    = 15;                  // Число юзеров на страницу на странице статистики и в профиле пользователя
$config['module']['user']['friend_on_profile']    = 15;         // Ограничение на вывод числа друзей пользователя на странице его профиля
$config['module']['user']['friend_notice']['delete'] = false;   // Отправить talk-сообщение в случае удаления пользователя из друзей
$config['module']['user']['friend_notice']['accept'] = false;   // Отправить talk-сообщение в случае одобрения заявки на добавление в друзья
$config['module']['user']['friend_notice']['reject'] = false;   // Отправить talk-сообщение в случае отклонения заявки на добавление в друзья

$config['module']['user']['login']['min_size'] = 3;             // Минимальное количество символов в логине
$config['module']['user']['login']['max_size'] = 30;            // Максимальное количество символов в логине
$config['module']['user']['login']['charset'] = '0-9a-z_\-';    // Допустимые в логине пользователя символы
$config['module']['user']['login']['disabled'] = ['admin', 'administrator', 'moderator', 'new', 'guest', '@admin', '@guest'];  // недопустимые имена логинов

$config['module']['user']['display_name'] = '%%login%%';        // Допустимые подстановки - %%login%%, %%profilename%%

$config['module']['user']['profile_url'] = 'profile/%login%';   // ссылка на профиль пользователя
$config['module']['user']['profile_photo_size'] = '240x340';    // размер фотопрофиля по умолчанию
$config['module']['user']['profile_avatar_size'] = 100;         // размер аватара по умолчанию

$config['module']['user']['time_active'] = 60*60*24*7;          // Число секунд с момента последнего посещения пользователем сайта, в течение которых он считается активным
$config['module']['user']['usernote_text_max'] = 250;           // Максимальный размер заметки о пользователе
$config['module']['user']['usernote_per_page'] = 20;            // Число заметок на одну страницу
$config['module']['user']['userfield_max_identical'] = 2;       // Максимальное число контактов одного типа
$config['module']['user']['profile_photo_width'] = 250;         // ширина квадрата фотографии в профиле, px
$config['module']['user']['name_max'] = 30;                     // максимальная длинна имени в профиле пользователя
$config['module']['user']['captcha_use_registration'] = true;   // проверять поле капчи при регистрации пользователя
$config['module']['user']['max_session_history'] = 50;          // число хранимых сессий пользователя, если 0, то хранятся все сессии

$config['module']['user']['pass_recovery_delay'] = 60 * 60 * 24 * 7;  // Время, в течение которого действует ссылка на восстановление пароля

$config['module']['user']['logout']['show_exit'] = 0;           // Время, в течение которого показывается страница выхода (0 - не показывается)
//$config['module']['user']['logout']['redirect'] = '/';        // Безусловный редирект после выхода

$config['module']['user']['online_time'] = 60 * 10;           // Время, в течение которого пользователь считается в онлайне, если 0 - учитывается только явный выход

// Модуль Comment
$config['module']['comment']['per_page'] = 20;          // Число комментариев на одну страницу(это касается только полного списка комментариев прямого эфира)
$config['module']['comment']['bad']      = -5;          // Рейтинг комментария, начиная с которого он будет скрыт
$config['module']['comment']['max_tree'] = 7;           // Максимальная вложенность комментов при отображении
$config['module']['comment']['use_nested'] = false;     // Использовать или нет nested set при выборке комментов, увеличивает производительность при большом числе комментариев + позволяет делать постраничное разбиение комментов
$config['module']['comment']['nested_per_page'] = 0;    // Число комментов на одну страницу в топике, актуально только при use_nested = true
$config['module']['comment']['nested_page_reverse'] = true;     // Определяет порядок вывода страниц. true - последние комментарии на первой странице, false - последние комментарии на последней странице
$config['module']['comment']['favourite_target_allow'] = ['topic']; // Список типов комментов, которые разрешено добавлять в избранное
$config['module']['comment']['edit']['enable'] = '500 minutes';   // В течение какого времени можно редактировать комментарии (true - бессрочно)
$config['module']['comment']['edit']['rest_time'] = true;       // Показывать ли оставшееся время для редактирования комментария
$config['module']['comment']['min_length'] = 2;             // Min length of comments
$config['module']['comment']['max_length'] = 16000;         // Max length of comments (0 - no limit)

// Модуль Talk
$config['module']['talk']['per_page']   = 30;       // Число приватных сообщений на одну страницу
$config['module']['talk']['encrypt']    = 'alto';   // Ключ XXTEA шифрования идентификаторов в ссылках
$config['module']['talk']['max_users']  = 15;       // Максимальное число адресатов в одном личном сообщении
$config['module']['talk']['min_length']  = 2;       // Min length of message
$config['module']['talk']['max_length']  = 4000;    // Max length of message (0 - no limit)

// Модуль Lang
$config['module']['lang']['delete_undefined'] = true;   // Если установлена true, то модуль будет автоматически удалять из языковых конструкций переменные вида %%var%%, по которым не была произведена замена

// Модуль Notify
$config['module']['notify']['delayed']      = false;    // Указывает на необходимость использовать режим отложенной рассылки сообщений на email
$config['module']['notify']['insert_single']= false;    // Если опция установлена в true, систему будет собирать записи заданий удаленной публикации, для вставки их в базу единым INSERT
$config['module']['notify']['per_process']  = 10;       // Количество отложенных заданий, обрабатываемых одним крон-процессом
$config['module']['notify']['dir']          = 'emails'; // Относительный (относительно папки скина) путь до папки с шаблонами писем
$config['module']['notify']['prefix']       = 'email.'; // Префикс шаблонов емэйлов

// Модуль Security
$config['module']['security']['hash']  = 'alto_security_key'; // "примесь" к строке, хешируемой в качестве security-кода
$config['module']['security']['randomkey']  = false;    // генерация случайных ключей во время одной сессии
$config['module']['security']['password_len']  = 6;     // длина пароля

$config['module']['userfeed']['count_default'] = 10;    // Число топиков в ленте по умолчанию

$config['module']['stream']['count_default'] = 20;      // Число топиков в ленте по умолчанию
$config['module']['stream']['disable_vote_events'] = false;

// Модуль Wall - стена
$config['module']['wall']['count_last_reply'] = 3;      // Число последних ответов на сообщени на стене для отображения в ленте
$config['module']['wall']['per_page'] = 10;             // Число сообщений на стене на одну страницу
$config['module']['wall']['text_max'] = 250;            // Ограничение на максимальное количество символов в одном сообщении на стене
$config['module']['wall']['text_min'] = 1;              // Ограничение на минимальное количество символов в одном сообщении на стене

// Модуль Rating
$config['module']['rating']['blog']['topic_rating_sum'] =0.18;  //Коэффициент суммы рейтинга топиков в блоге для расчета рейтинга блога
$config['module']['rating']['blog']['count_users']      =0.2;   //Коэффициент количества подписчиков в блоге для расчета рейтинга блога
$config['module']['rating']['blog']['topic_count']      =0.15;  //Коэффициент количества топиков в блоге для расчета рейтинга блога

/**
 * Настройка фотосета топика
 */
//$config['module']['image']['photoset']['jpg_quality'] = 100;        // настройка модуля Image, качество обработки фото
$config['module']['topic']['photoset']['photo_max_size'] = 6*1024;  // максимально допустимый размер фото, Kb
$config['module']['topic']['photoset']['count_photos_min'] = 2;     // минимальное количество фоток
$config['module']['topic']['photoset']['count_photos_max'] = 30;    // максимальное количество фоток (если 0, то без ограничений)
$config['module']['topic']['photoset']['per_page'] = 20;            // число фоток для одновременной загрузки

/**
 * Настройки модуля API
 */
$config['module']['api']['ajax'] = true;        // Не авторизованный аякс запрос клиента сайта
$config['module']['api']['get'] = false;        // Сторонний get-запрос на получение данных
$config['module']['api']['post'] = false;       // Сторонний post-зпрос на изменение данных

/**
 * Настройки модуля Text
 */
$config['module']['text']['parser'] = 'Qevix';  // Text parser class: Jevix or Qevix
$config['module']['text']['char']['@'] = true;  // Convert @user into link to profile

// All cyrillic symbols
$config['module']['text']['translit'] = [
    'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'ѓ' => 'g', 'ґ' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
    'є' => 'ye', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'j', 'j' => 'j', 'i' => 'i', 'ї' => 'yi',
    'к' => 'k', 'ќ' => 'k', 'л' => 'l', 'љ' => 'lj', 'м' => 'm', 'н' => 'n', 'њ' => 'nj', 'о' => 'o', 'п' => 'p',
    'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ў' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'cz', 'ч' => 'ch',
    'џ' => 'dh', 'ш' => 'sh', 'щ' => 'shh', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    'ѣ' => 'ye', 'ѳ' => 'fh', 'ѵ' => 'yh', 'ѫ' => 'о',
    'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Ѓ' => 'G', 'Ґ' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'YO',
    'Є' => 'YE', 'Ж' => 'ZH', 'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'J' => 'J', 'I' => 'I', 'Ї' => 'YI',
    'К' => 'K', 'Ќ' => 'K', 'Л' => 'L', 'Љ' => 'LJ', 'М' => 'M', 'Н' => 'N', 'Њ' => 'NJ', 'О' => 'O', 'П' => 'P',
    'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ў' => 'U', 'Ф' => 'F', 'Х' => 'KH', 'Ц' => 'CZ', 'Ч' => 'CH',
    'Џ' => 'DH', 'Ш' => 'SH', 'Щ' => 'SHH', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'YU', 'Я' => 'YA',
    'Ѣ' => 'YE', 'Ѳ' => 'FH', 'Ѵ' => 'YH', 'Ѫ' => 'О',
];

// Какие модули должны быть загружены на старте
$config['engine']['autoload'] = ['Cache','Security','Session','Database','User'];

/**
 * Настройка базы данных
 */
$config['db'][0]['host']   = 'localhost';
$config['db'][0]['port']   = '3306';
$config['db'][0]['user']   = 'root';
$config['db'][0]['pass']   = '';
$config['db'][0]['type']   = 'mysqli';    // mysql, mypdo, postgresql, mssql
$config['db'][0]['dbname'] = 'alto';
$config['db'][0]['charset'] = 'utf8mb4';     // utf8, utf8mb4;

$config['db'][0]['lazy']    = true; // "ленивое" подключение к базе
$config['db'][0]['engine']  = 'InnoDB';  // InnoDB или MyISAM
$config['db'][0]['init_sql'] = [
    "set character_set_client='%%charset%%', character_set_results='%%charset%%', collation_connection='utf8_bin' ",
];

// Префикс таблиц
$config['db'][0]['table_prefix'] = 'prefix_';

/*
 * Можно не объявлять таблицы, если их названия совпадают с именами в SQL-запросах,
 * тогда в запросах достаточно подставлять в качестве имени таблицы ?_table_name
 * Либо можно явно определить имя таблицы и использовать так: Config::Get('db.table.user)
 *
$config['db'][0]['table']['user']                  = '___db.0.table_prefix___user';
*/

/**
 * Настройки роутинга
 */
// Redirection
$config['router']['redirect'] = array(
    //'http://*' => 'https://*', // simple matching, redirect from HTTP to HTTPS
    //'http://site.com/perm-path/*.html' => ['http://site.com/temp-path/*.html', 302], // redirect with code 302
    //'[~(.+/)blabla/(\d+).html$~]' => '$1$2.html', // regular expression in brackets
);

// Domain mapping
$config['router']['domain'] = [
    //'*.site.com' => 'blog/*',
    //'public.site.com' => 'blog/public',
];

// Rewrite rules
$config['router']['rewrite'] = [
    //'secret-admin' => 'admin',
];

// Правила реврайта для REQUEST_URI
// Регулярные выражения необходимо заключать в квадратные скобки
$config['router']['uri'] = array(
    '[~^_run/assets/([\w\-\.]+/.+)$~i]' => 'asset/$1',
    // запрет обработки статичных файлов с заданными расширениями
    /* допустимые значения:
     *  - @ignore   - запрос игнорируется и его обработка прекращается
     *  - @die(msg) - обработка запроса прекращается с выдачей сообщения msg
     *  - @404      - обработка прекращается с выдачей кода 404
     */
    '[~.+\.(js|css|png|jpg|gif|swf|ico|pdf|mov|fla|zip|rar)\/?$~i]' => '@404',
);

// Распределение action
$config['router']['controller']['admin']         = 'ActionAdmin';
$config['router']['controller']['ajax']          = 'ActionAjax';
$config['router']['controller']['error']         = 'ActionError';
$config['router']['controller']['registration']  = 'ActionRegistration';
$config['router']['controller']['profile']       = 'ActionProfile';
$config['router']['controller']['my']            = 'ActionMy';
$config['router']['controller']['blog']          = 'ActionBlog';
$config['router']['controller']['page']          = 'ActionPage';
$config['router']['controller']['index']         = 'ActionIndex';
$config['router']['controller']['content']       = 'ActionTopic';
$config['router']['controller']['filter']        = 'ActionFilter';
$config['router']['controller']['download']      = 'ActionDownload';
$config['router']['controller']['login']         = 'ActionLogin';
$config['router']['controller']['people']        = 'ActionPeople';
$config['router']['controller']['settings']      = 'ActionSettings';
$config['router']['controller']['tag']           = 'ActionTag';
$config['router']['controller']['talk']          = 'ActionTalk';
$config['router']['controller']['rss']           = 'ActionRss';
$config['router']['controller']['blogs']         = 'ActionBlogs';
$config['router']['controller']['search']        = 'ActionSearch';
$config['router']['controller']['feed']          = 'ActionUserfeed';
$config['router']['controller']['stream']        = 'ActionStream';
$config['router']['controller']['subscribe']     = 'ActionSubscribe';
$config['router']['controller']['img']           = 'ActionImg';
$config['router']['controller']['homepage']      = 'ActionHomepage';
$config['router']['controller']['captcha']       = 'ActionCaptcha';

// Глобальные настройки роутинга
$config['router']['config']['action_default']   = 'homepage';
$config['router']['config']['action_not_found'] = 'error';

$config['router']['config']['homepage']   = 'index';

// Автоопределение роутинга экшенов
$config['router']['config']['autodefine'] = true;

// Параметры обработки css-файлов
$config['assets']['css']['merge']       = true;      // указывает на необходимость слияния файлов по указанным блокам.
$config['assets']['css']['gzip']        = false;     // указывает на необходимость отдачи css в виде gzip
$config['assets']['css']['compress']    = false;     // указывает на необходимость минификации файлов
$config['assets']['css']['force']       = false;     // если заданно, то обработка css-файлов выполняется, даже если результирующие файлы есть

$config['assets']['css']['options']['csstidy'] = [
    'case_properties'       => 1,
    'merge_selectors'       => 0,
    'optimise_shorthands'   => 1,
    'remove_last_;'         => true,
    'css_level'             => 'CSS2.1',
    'template'              => 'highest_compression',
];

// Параметры обработки js-файлов
$config['assets']['js']['merge']        = false;     // указывает на необходимость слияния файлов по указанным блокам.
$config['assets']['js']['gzip']         = false;     // указывает на необходимость отдачи js в виде gzip
$config['assets']['js']['compress']     = false;     // указывает на необходимость минификации файлов
$config['assets']['js']['force']        = false;     // если заданно, то обработка js-файлов выполняется, даже если результирующие файлы есть

/**
 * "Примеси" ("соли") для повышения безопасности хешируемых данных
 */
$config['security']['salt_sess']  = '123456789012345678901234567890';
$config['security']['salt_pass']  = 'qwertyuiopqwertyuiopqwertyuiop';
$config['security']['salt_auth']  = '1234567890qwertyuiopasdfghjkl0';

$config['security']['user_session_key']  = 'user_key';

/**
 * Локализация
 */
// Языковые настройки
// Какие языки доступны на сайте
// Если не задано или задан только один язык, то настройки мультиязычности игнорируются
$config['lang']['allow'] = 'ru';
//$config['lang']['allow'] = ['ru', 'en'];
/*
$config['lang']['aliases'] = [              // набор алиасов для совместимости LS
    'ru' => 'russian',
    'en' => 'english',
];
*/

// Настройки мультиязычного сайта
$config['lang']['in_url'] = true;                                           // проверка языка в URL
$config['lang']['in_get'] = true;                                           // проверка языка в GET-параметре: 'lang=ru'
//$config['lang']['in_get'] = 'languages';                                   // то же, но задает параметр: 'languages=ru'
//$config['lang']['save'] = '1 year';                                         // сохранение языка в куки, задает время хранения; если 0 (или false), то не сохраняется

$config['lang']['default'] = 'ru';                                          // язык, который будет использоваться на сайте по умолчанию, если не наййдены тексты для текущего языка
$config['lang']['current'] = 'ru';                                          // основной язык сайта

// Массив текстовок, которые необходимо прогружать на страницу в виде JS хеша, позволяет использовать текстовки внутри js
$config['lang']['load_to_js'] = [
    'text_yes',
    'text_no',
    'text_confirm',
    'text_cancel',
    'topic_delete_confirm_title',
    'topic_delete_confirm_text',
];

// пути до языковых файлов
$config['lang']['paths']    = [
    '___path.dir.common___/frontend/languages',
    '___path.dir.app___/frontend/languages',
];

/**
 * Установка локали и временной зоны
 */
//$config['i18n']['locale'] = 'ru_RU.UTF-8';                                // Задается локаль, если не задана здесь, то берется из описания языка
//$config['i18n']['timezone'] = 'Europe/Moscow';                            // Задается временная зона, если не задана здесь, то берется из описания языка

/**
 * Настройки автокомплита пользователей
 */
$config['autocomplete']['user']['show_avatar'] = true;  // Добавлять аватар?
$config['autocomplete']['user']['avatar_size'] = 24;    // Размер аватара

/**
 * Доступна ли стстема рейтинга. Эту настройку менять НЕ нужно,
 * она устанавливается в true только плагинами рейтинга
 */
$config['rating']['enabled'] = false;

return $config;

// EOF
