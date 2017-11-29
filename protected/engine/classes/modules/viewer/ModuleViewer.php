<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 * Based on
 *   LiveStreet Engine Social Networking by Mzhelskiy Maxim
 *   Site: www.livestreet.ru
 *   E-mail: rus.engine@gmail.com
 *----------------------------------------------------------------------------
 */

// For Smarty
define('DS', '/');

/**
 * Модуль обработки шаблонов используя шаблонизатор Smarty
 *
 * @package engine.modules
 * @since 1.0
 */
class ModuleViewer extends Module
{
    /** @var bool Устанавливаем признак предзагрузки (влияет на порядок шатдауна) */
    protected $bPreloaded = true;

    /**
     * Объект Smarty
     *
     * @var Smarty
     */
    protected $oTemplator;

    /**
     * Коллекция (массив) виджетов
     *
     * @var array
     */
    protected $aWidgets = [];

    /**
     * Массив дополнительных (добавленных) виджетов
     *
     * @var array
     */
    protected $aWidgetsAppend = [];

    /**
     * Признак сортировки виджетов
     *
     * @var bool
     */
    protected $bWidgetsSorted = false;

    /**
     * Каталог для кешировния js,css файлов
     *
     * @var string
     */
    protected $sCacheDir = '';

    /**
     * Заголовки HTML страницы (объединяются через сепаратор перед выдачей)
     *
     * @var array
     */
    protected $aHtmlTitles = '';

    /**
     * Максимальное число заголовков, из которых строится общий заголовок HTML страницы (тег title)
     *
     * @var int
     */
    protected $iHtmlTitlesMax = 0;

    /**
     * Разделитель заголовка HTML страницы
     *
     * @var string
     */
    protected $sHtmlTitleSeparator = ' / ';

    /**
     * SEO ключевые слова страницы
     *
     * @var array
     */
    protected $aHtmlKeywords;

    /**
     * SEO описание страницы
     *
     * @var string
     */
    protected $sHtmlDescription;

    /**
     * Альтернативный адрес страницы по RSS
     *
     * @var array
     */
    protected $aHtmlRssAlternate;

    /**
     * Указание поисковику основного URL страницы, для борьбы с дублями
     *
     * @var string
     */
    protected $sHtmlCanonical;

    /**
     * Html код для подключения js,css
     *
     * @var array
     */
    protected $aHtmlHeaScripts = [];

    /**
     * Html-теги, добавляемые в HEAD страницы
     *
     * @var array
     */
    protected $aHtmlHeadTags = [];

    protected $aSpecMetaTagsAttr = ['http-equiv', 'name', 'property', 'itemprop'];

    /**
     * Переменные для передачи в шаблон
     *
     * @var array
     */
    protected $aVarsTemplate = [];

    /**
     * Переменные для отдачи при ajax запросе
     *
     * @var array
     */
    protected $aVarsAjax = [];

    /**
     * Определяет тип ответа при ajax запросе
     *
     * @var string
     */
    protected $sResponseAjax;

    /**
     * Отправляет специфичный для ответа header
     *
     * @var bool
     */
    protected $bResponseSpecificHeader = true;

    /**
     * Список меню для рендеринга
     *
     * @var array
     */
    protected $aMenu = [];

    /**
     * Скомпилированные меню
     *
     * @var array
     */
    protected $aMenuFetch = [];

    /** @var bool Local viewer */
    protected $bLocal = false;

    /** @var  string Current skin */
    protected $sViewSkin;

    /** @var  string Current theme */
    protected $sViewTheme;

    protected $sForcedSkin;

    protected $sForcedTheme;

    protected $nMuteErrorsCnt = 0;

    protected $aResponseHeaders = [];

    /** @var bool To prevent double initialization  */
    protected $bInitRender = false;

    static protected $aTemplatePaths = [];

    static protected $_renderCount = 0;
    static protected $_renderTime = 0;
    static protected $_renderStart = 0;
    static protected $_preprocessTime = 0;
    static protected $_inRender = 0;

    static protected $_renderOptionsStack = [];

    /**
     * @return int
     */
    public static function getRenderCount()
    {
        return self::$_renderCount;
    }

    /**
     * @return int
     */
    public static function getRenderTime()
    {
        return self::$_renderTime + (self::$_renderStart ? microtime(true) - self::$_renderStart : 0);
    }

    /**
     * @return int
     */
    public static function getPreProcessingTime()
    {
        return self::$_preprocessTime + self::getRenderTime();
    }

    /**
     * @return int
     */
    public static function getTotalTime()
    {
        return self::getPreProcessingTime() + self::getRenderTime();
    }

    /**
     * Инициализация модуля
     *
     * @param bool $bLocal
     */
    public function init($bLocal = false)
    {
        \HookManager::run('viewer_init_start', compact('bLocal'));

        $this->bLocal = (bool)$bLocal;

        if (($iTitleMax = \C::get('view.html.title_max')) && ($iTitleMax > 0)) {
            $this->iHtmlTitlesMax = \C::get('view.html.title_max');
        }
        $this->sHtmlTitleSeparator = \C::get('view.html.title_sep');

        // * Заголовок HTML страницы
        $this->setHtmlTitle(\C::get('view.name'));

        // * SEO ключевые слова страницы
        $sValue = (\C::get('view.keywords') ? \C::get('view.keywords') : \C::get('view.html.keywords'));
        $this->setHtmlKeywords($sValue);

        // * SEO описание страницы
        $sValue = (\C::get('view.description') ? \C::get('view.description') : \C::get('view.html.description'));
        $this->setHtmlDescription($sValue);

        // * Пустой вызов только для того, чтоб модуль Message инициализировался, если еще не
        \E::Module('Message')->IsInit();

        $this->sCacheDir = \C::get('path.runtime.dir');

        $this->responseSetHeader('X-Powered-By', 'Alto CMS v.2');
        $this->responseSetHeader('Content-Type', 'text/html; charset=utf-8');

        $this->_initSkin();
    }

    /**
     * Возвращает версию Smarty
     *
     * @return string|null
     */
    public function getSmartyVersion()
    {
        $sSmartyVersion = null;
        if (defined('Smarty::SMARTY_VERSION')) {
            $sSmartyVersion = Smarty::SMARTY_VERSION;
        }
        return $sSmartyVersion;
    }

    /**
     * Создает и возвращает объект Smarty
     *
     * @return Smarty
     */
    public function createTemplator()
    {
        return new Smarty();
    }

    /**
     * Get templator Smarty
     *
     * @param array|null $aVariables
     *
     * @return Smarty
     */
    public function getTemplator($aVariables = null)
    {
        $this->_initTemplator();
        $oSmarty = $this->oTemplator;
        $aTemplateVars = $this->getTemplateVars();
        if (is_array($aTemplateVars) && !empty($aTemplateVars)) {
            $oSmarty->assign($aTemplateVars);
        }

        if (is_array($aVariables)) {
            $oSmarty->assign($aVariables);
        }
        return $oSmarty;
    }

    /**
     *
     */
    protected function _initTemplator()
    {
        if (!$this->oTemplator) {
            $this->_tplInit();
        }
    }

    /**
     * Возвращает локальную копию модуля
     *
     * @return ModuleViewer
     */
    public function getLocalViewer()
    {
        $sClass = \E::PluginManager()->getDelegate('module', __CLASS__);

        /** @var ModuleViewer $oViewerLocal */
        $oViewerLocal = new $sClass();
        $oViewerLocal->init(true);
        $oViewerLocal->_initRender();
        $oViewerLocal->varAssign();

        $oSmarty = $oViewerLocal->getTemplator();
        $oSmarty->assign($oViewerLocal->getTemplateVars());

        return $oViewerLocal;
    }

    /**
     * @return string
     */
    public function getTemplatorName()
    {
        return 'Smarty';
    }

    /**
     * @return null|string
     */
    public function getTemplatorVersion()
    {
        return $this->getSmartyVersion();
    }

    /**
     * Инициализация шаблонизатора
     *
     */
    protected function _tplInit()
    {
        if ($this->oTemplator) {
            return;
        }

        // * Создаём объект Smarty
        $this->oTemplator = $this->createTemplator();

        // * Устанавливаем необходимые параметры для Smarty
        $this->oTemplator->compile_check = (bool)\C::get('smarty.options.compile_check');
        $this->oTemplator->force_compile = (bool)\C::get('smarty.options.force_compile');
        $this->oTemplator->merge_compiled_includes = (bool)\C::get('smarty.options.merge_compiled_includes');

        // * Подавляем NOTICE ошибки - в этом вся прелесть смарти )
        $this->oTemplator->error_reporting = error_reporting() & ~E_NOTICE;

        // * Папки расположения шаблонов по умолчанию
        $aDirs = \F::File_NormPath(\F::Str2Array(\C::get('smarty.path.template')));
        if (count($aDirs) === 1) {
            $sDir = $aDirs[0];
            $aDirs['themes'] = \F::File_NormPath($sDir . '/themes');
            $aDirs['tpls'] = \F::File_NormPath($sDir . '/tpls');
        }
        $this->oTemplator->setTemplateDir($aDirs);
        if (\C::get('smarty.dir.templates')) {
            $this->oTemplator->addTemplateDir(\F::File_NormPath(\F::Str2Array(\C::get('smarty.dir.templates'))));
        }

        // * Для каждого скина устанавливаем свою директорию компиляции шаблонов
        $sCompilePath = \F::File_NormPath(\C::get('smarty.path.compiled'));
        \F::File_CheckDir($sCompilePath);
        $this->oTemplator->setCompileDir($sCompilePath);
        $this->oTemplator->setCacheDir(\C::get('smarty.path.cache'));

        // * Папки расположения пдагинов Smarty
        $this->oTemplator->addPluginsDir([\C::get('smarty.path.plug'), 'plugins']);
        if (\C::get('smarty.dir.plugins')) {
            $this->oTemplator->addPluginsDir(\F::File_NormPath(\F::Str2Array(\C::get('smarty.dir.plugins'))));
        }

        $this->oTemplator->default_template_handler_func = [$this, 'SmartyDefaultTemplateHandler'];

        // * Параметры кеширования, если заданы
        if (\C::get('smarty.options.cache_lifetime')) {
            $this->oTemplator->caching = Smarty::CACHING_LIFETIME_SAVED;
            $this->oTemplator->cache_lifetime = \F::ToSeconds(\C::get('smarty.options.cache_lifetime'));
        }

        // Settings for Smarty 3.1.16 and more
        $this->oTemplator->inheritance_merge_compiled_includes = false;

        \F::includeFile('./plugs/resource.file.php');
        $this->oTemplator->registerResource('file', new Smarty_Resource_File());

        // Mutes expected Smarty minor errors
        Smarty::muteExpectedErrors();
    }

    /**
     * @param string $sTemplate
     * @param bool $bException
     *
     * @return bool|string
     * @throws Exception
     */
    protected function _tplTemplateExists($sTemplate, $bException = false)
    {
        if (!$this->oTemplator) {
            $this->_tplInit();
        }

        $bResult = $this->oTemplator->templateExists($sTemplate);

        $sSkin = $this->getConfigSkin();
        if (!$bResult && $bException) {
            $sMessage = 'Can not find the template "' . $sTemplate . '" in skin "' . $sSkin . '"';
            $sMessage .= '.';

            // записываем доп. информацию - пути к шаблонам Smarty
            $sErrorInfo = 'Template Dirs: ' . implode('; ', $this->oTemplator->getTemplateDir());
            $this->_error($sMessage, $sErrorInfo);
            return false;
        }
        if ($bResult && ($sResult = $this->_getTemplatePath($sSkin, $sTemplate))) {
            $sTemplate = $sResult;
        }

        return $bResult ? $sTemplate : $bResult;
    }

    /**
     * @param string $sTemplate
     * @param array  $aVariables
     *
     * @return object
     */
    protected function _tplCreateTemplate($sTemplate, $aVariables = null)
    {
        $oSmarty = $this->getTemplator();
        $oTemplate = $oSmarty->createTemplate($sTemplate, null, null, $oSmarty, false);
        if ($aVariables && is_array($aVariables)) {
            $oTemplate->assign($aVariables);
        }
        return $oTemplate;
    }

    /**
     * Set templator options
     *
     * @param array $aOptions
     */
    protected function _tplSetOptions($aOptions)
    {
        self::$_renderOptionsStack[] = [
            'caching'        => $this->oTemplator->caching,
            'cache_lifetime' => $this->oTemplator->cache_lifetime,
        ];
        if (isset($aOptions['cache'])) {
            $this->oTemplator->caching = Smarty::CACHING_LIFETIME_SAVED;
            if ($aOptions['cache'] === false) {
                // Отключаем кеширование
                $this->oTemplator->cache_lifetime = 0;
            } elseif (isset($aOptions['cache']['time'])) {
                if ($aOptions['cache']['time'] == -1) {
                    // Задаем бессрочное кеширование
                    $this->oTemplator->cache_lifetime = -1;
                } elseif ($aOptions['cache']['time']) {
                    // Задаем время кеширования
                    $this->oTemplator->cache_lifetime = \F::ToSeconds($aOptions['cache']['time']);
                } else {
                    // Отключаем кеширование
                    $this->oTemplator->cache_lifetime = 0;
                }
            }
        }

    }

    /**
     * Restore templator options
     */
    protected function _tplRestoreOptions()
    {
        if (self::$_renderOptionsStack) {
            $aOptions = array_pop(self::$_renderOptionsStack);
            if (isset($aOptions['caching'])) {
                $this->oTemplator->caching = $aOptions['caching'];
            }
            if (isset($aOptions['cache_lifetime'])) {
                $this->oTemplator->cache_lifetime = $aOptions['cache_lifetime'];
            }
        }
    }

    /**
     * Загружает переменную в шаблон
     *
     * @param string $sName  - Имя переменной в шаблоне
     * @param mixed  $xValue - Значение переменной
     */
    protected function _tplAssign($sName, $xValue)
    {
        $this->oTemplator->assign($sName, $xValue);
    }


    /**
     * Initialization of skin
     *
     */
    protected function _initSkin()
    {
        $this->sViewSkin = $this->getConfigSkin();

        // Load skin's config
        $aConfig = [];
       \C::resetLevel(\C::LEVEL_SKIN);

        $aSkinConfigPaths = [];
        $aSkinConfigPaths['sSkinConfigCommonPath'] = \C::get('smarty.path.template') . '/settings/config/';
        $aSkinConfigPaths['sSkinConfigAppPath']    = \C::get('path.dir.app')
            . \F::File_LocalPath(
                $aSkinConfigPaths['sSkinConfigCommonPath'],
               \C::get('path.dir.common')
            )
        ;
        // Load configs from paths
        foreach ($aSkinConfigPaths as $sPath) {
            if (is_dir($sPath)) {
                \Loader::loadConfigFiles($sPath, \C::LEVEL_SKIN);
            }
        }

        // ** Old skin version compatibility
        $oSkin = \E::Module('Skin')->GetSkin($this->sViewSkin);
        if (!$oSkin || !$oSkin->GetCompatible() || $oSkin->SkinCompatible('1.1', '<')) {
            // 'head.default' may be used in skin config
           \C::load(['head.default' => false]);
        }

       \C::resetLevel(Config::LEVEL_SKIN_CUSTOM);

        // Checks skin's config from users settings
        $sUserConfigKey = 'skin.' . $this->sViewSkin . '.config';
        $aUserConfig = \C::get($sUserConfigKey);
        if ($aUserConfig) {
            if (!$aConfig) {
                $aConfig = $aUserConfig;
            } else {
                $aConfig = \F::Array_MergeCombo($aConfig, $aUserConfig);
            }
        }

        if ($aConfig) {
           \C::load($aConfig, null, $sUserConfigKey);
        }

        // Check skin theme and set one in config if it was changed
        if ($this->getConfigTheme() !== \C::get('view.theme')) {
           \C::set('view.theme', $this->getConfigTheme());
        }

        // Load lang files for skin
        \E::Module('Lang')->loadLangFileTemplate(\E::Module('Lang')->getLang());

        // Load template variables from config
        if (($aVars = \C::get('view.assign')) && is_array($aVars)) {
            $this->assign($aVars);
        }
    }

    /**
     * Initialization of render before fetch() or display()
     *
     * @param bool $bForce
     */
    protected function _initRender($bForce = false) 
    {
        if ($this->bInitRender && !$bForce) {
            return;
        }

        $nTimer = microtime(true);

        \HookManager::run('render_init_start', ['bLocal' => $this->bLocal]);

        // If skin not initialized (or it was changed) then init one
        if ($this->sViewSkin !== $this->getConfigSkin()) {
            $this->_initSkin();
        } else {
            // Level could be changed after skin initialization
           \C::setLevel(Config::LEVEL_SKIN_CUSTOM);
        }

        // init templator if not yet
        $this->_initTemplator();

        // Loads localized texts
        $aLang = \E::Module('Lang')->getLangMsg();
        // Old skin compatibility
        $aLang['registration_password_notice'] = \E::Module('Lang')->get('registration_password_notice', ['min' => \C::val('module.security.password_len', 3)]);
        $this->assign('aLang', $aLang);
        //$this->Assign('oLang', \E::Module('Lang')->Dictionary());

        //if (!$this->bLocal && !$this->getResponseAjax()) {
        //    // Initialization of assets (JS-, CSS-files)
        //    $this->initAssetFiles();
        //}

        $oSkin = \E::Module('Skin')->GetSkin($this->sViewSkin);
        if (!$oSkin || !$oSkin->GetCompatible() || $oSkin->SkinCompatible('1.1', '<')) {
            // Для старых скинов загружаем объект доступа к конфигурации
            $this->assign('oConfig', \C::getInstance());

        }

        \HookManager::run('render_init_done', ['bLocal' => $this->bLocal]);

        $this->bInitRender = true;
        self::$_preprocessTime += microtime(true) - $nTimer;
    }

    // -- RESPONSE -- //

    /**
     * Add HTTP header
     *
     * @param string $sHeader
     */
    public function responseAddHeader($sHeader)
    {
        if (preg_match('/^([^:]+)\s+:(.+)$/', $sHeader, $aM)) {
            $sHeader = $aM[1] . ': ' . $aM[2];
        }
        $this->aResponseHeaders[] = $sHeader;
    }

    /**
     * Sets HTTP header as "<key>: <value>"
     *
     * @param string $sHeaderKey
     * @param string $sHeaderValue
     */
    public function responseSetHeader($sHeaderKey, $sHeaderValue)
    {
        $sSeekKey = strtolower($sHeaderKey) . ':';
        foreach($this->aResponseHeaders as $iIndex => $sHeader) {
            if (strpos(strtolower($sHeader), $sSeekKey) === 0) {
                unset($this->aResponseHeaders[$iIndex]);
                break;
            }
        }
        $this->responseAddHeader($sHeaderKey . ': ' . $sHeaderValue);
    }

    /**
     * Clears HTTP Headers
     */
    public function responseClearHeaders()
    {
        $this->aResponseHeaders[] = [];
    }

    /**
     * Returns HTTP headers
     *
     * @return array
     */
    public function responseGetHeaders()
    {
        return $this->aResponseHeaders;
    }

    /**
     * Send HTTP headers
     *
     * @return bool
     */
    public function responseSendHeaders()
    {
        if (headers_sent()) {
            return false;
        }
        foreach($this->aResponseHeaders as $iIndex => $sHeader) {
            header($sHeader);
            unset($this->aResponseHeaders[$iIndex]);
        }
        return true;
    }

    /**
     * Return content type from HTTP headers
     *
     * @param bool $bSystemHeadersOnly
     *
     * @return string
     */
    public function responseGetContentType($bSystemHeadersOnly = false)
    {
        $aHeaders = headers_list();
        if (!$bSystemHeadersOnly) {
            if ($aHeaders) {
                $aHeaders = array_merge($aHeaders, $this->aResponseHeaders);
            } else {
                $aHeaders = $this->aResponseHeaders;
            }
        }
        $sResult = null;
        if ($aHeaders) {
            foreach ($aHeaders as $sHeader) {
                if (preg_match('/content-type:\s*([a-z0-9\-\/]+)/i', $sHeader, $aM)) {
                    $sResult = $aM[1];
                }
            }
        }
        return $sResult;
    }

    /**
     * Выполняет загрузку необходимых (возможно даже системных :)) переменных в шаблонизатор
     */
    public function varAssign()
    {
        // * Загружаем весь $_REQUEST, предварительно обработав его функцией \F::HtmlSpecialChars()
        $aRequest = $_REQUEST;
        \F::HtmlSpecialChars($aRequest);
        $this->assign('_aRequest', $aRequest);

        // * Загружаем роутинг с учетом правил rewrite
        /*
        $aRouter = [];
        $aPages = \C::get('router.controllers');

        if (!$aPages || !is_array($aPages)) {
            throw new Exception('Router rules is underfined.');
        }
        foreach ($aPages as $sPage => $aAction) {
            $aRouter[$sPage] = R::getLink($sPage);
        }
        $this->assign('aRouter', $aRouter);
        */

        // * Загружаем виджеты
        $this->assign('aWidgets', $this->getWidgets());

        // * Загружаем HTML заголовки
        $this->assign('sHtmlTitle', $this->getHtmlTitle());
        $this->assign('sHtmlKeywords', $this->getHtmlKeywords());
        $this->assign('sHtmlDescription', $this->getHtmlDescription());

        $this->assign('aHtmlHeaScripts', $this->aHtmlHeaScripts);
        $this->assign('aHtmlRssAlternate', $this->aHtmlRssAlternate);
        $this->assign('sHtmlCanonical', $this->sHtmlCanonical);
        $this->assign('aHtmlHeadTags', $this->aHtmlHeadTags);

        $this->assign('aJsAssets', \E::Module('ViewerAsset')->getPreparedAssetLinks());

        // * Загружаем список активных плагинов
        $aPlugins = \E::getActivePlugins();
        $this->assign('aPluginActive', array_fill_keys(array_keys($aPlugins), true));

        // * Загружаем пути до шаблонов плагинов
        $aPluginsTemplateUrl = [];
        $aPluginsTemplateDir = [];

        /** @var Plugin $oPlugin */
        foreach ($aPlugins as $sPlugin => $oPlugin) {
            $sDir = PluginManager::getTemplateDir(get_class($oPlugin));
            if ($sDir) {
                $this->oTemplator->addTemplateDir([$sDir . 'tpls/', $sDir], $oPlugin->getName(false));
                $aPluginsTemplateDir[$sPlugin] = $sDir;
                $aPluginsTemplateUrl[$sPlugin] = PluginManager::getTemplateUrl(get_class($oPlugin));
            }
        }

        $sSkinTheme = $this->getConfigTheme();
        if (!$sSkinTheme) {
            $sSkinTheme = 'default';
        }
        // Проверка существования темы
        if ($this->checkTheme($sSkinTheme)) {
            $this->oTemplator->compile_id = $sSkinTheme;
        }
        $this->assign('sSkinTheme', $sSkinTheme);
    }

    /**
     * Загружаем содержимое menu-контейнеров
     */
    protected function _menuVarAssign()
    {
        $this->assign('aMenuFetch', $this->aMenuFetch);
        $this->assign('aMenuContainers', array_keys($this->aMenu));
    }

    /**
     * Выводит на экран (в браузер) обработанный шаблон
     *
     * @param string $sTemplate - Шаблон для вывода
     */
    public function display($sTemplate)
    {
        // Проверка существования папки с текущим скином
        $this->checkSkin();

        if ($this->sResponseAjax) {
            $this->displayAjax($this->sResponseAjax);
        }

        $this->responseSendHeaders();
        /*
         * Если шаблон найден то выводим, иначе - ошибка
         * Но предварительно проверяем наличие делегата
         */
        if ($sTemplate) {
            $this->_initRender();

            $sTemplate = \E::PluginManager()->getDelegate('template', $sTemplate);
            if ($sTemplatePath = $this->templateExists($sTemplate, true)) {
                // Установка нового secret key непосредственно перед рендерингом
                \E::Module('Security')->SetSecurityKey();

                $oTpl = $this->_tplCreateTemplate($sTemplatePath);

                ++self::$_renderCount;
                self::$_renderStart = microtime(true);
                ++self::$_inRender;

                $oTpl->display();

                --self::$_inRender;
                self::$_renderTime += (microtime(true) - self::$_renderStart);
                self::$_renderStart = 0;
            }
        }
    }

    /**
     * Возвращает отрендеренный шаблон
     *
     * @param string $sTemplate - Шаблон для рендеринга
     * @param array  $aVars     - Переменные для локального рендеринга
     * @param array  $aOptions  - Опции рендеринга
     *
     * @return  string
     */
    public function fetch($sTemplate, $aVars = [], $aOptions = [])
    {
        $this->_initRender();

        // * Проверяем наличие делегата
        $sTemplate = \E::PluginManager()->getDelegate('template', $sTemplate);
        if ($sTemplatePath = $this->templateExists($sTemplate, true)) {
            // Если задаются локальные параметры кеширования, то сохраняем общие
            $this->_tplSetOptions($aOptions);

            $oTpl = $this->_tplCreateTemplate($sTemplatePath);
            if ($aVars) {
                $oTpl->assign($aVars);
            }

            ++self::$_renderCount;
            self::$_renderStart = microtime(true);
            ++self::$_inRender;

            $sContent = $oTpl->fetch();

            --self::$_inRender;
            self::$_renderTime += (microtime(true) - self::$_renderStart);
            self::$_renderStart = 0;

            $this->_tplRestoreOptions();

            return $sContent;
        }
        return null;
    }

    /**
     * Возвращает отрендеренный шаблон виджета
     *
     * @param string $sTemplate - Шаблон для рендеринга
     * @param array  $aVars     - Переменные для локального рендеринга
     * @param array  $aOptions  - Опции рендеринга
     *
     * @return string
     */
    public function fetchWidget($sTemplate, $aVars = [], $aOptions = [])
    {
        // * Проверяем наличие делегата
        $sDelegateTemplate = \E::PluginManager()->getDelegate('template', $sTemplate);
        $sRenderTemplate = '';
        if ($sDelegateTemplate === $sTemplate && !$this->templateExists($sTemplate)) {
            $sWidgetTemplate = 'widgets/widget.' . $sTemplate;
            $sWidgetTemplate = \E::PluginManager()->getDelegate('template', $sWidgetTemplate);
            if ($sTemplatePath = $this->templateExists($sWidgetTemplate)) {
                $sRenderTemplate = $sTemplatePath;
            }

            if (!$sRenderTemplate) {
                $sRenderTemplate = $sWidgetTemplate;
            }
        }
        if (!$sRenderTemplate) {
            $sRenderTemplate = $sDelegateTemplate;
        }

        return $this->fetch($sRenderTemplate, $aVars, $aOptions);
    }

    /**
     * Ответ на ajax запрос
     *
     * @param string $sType - Варианты: json, jsonIframe, jsonp
     */
    public function displayAjax($sType = 'json')
    {
        $sOutput = '';

        // * Загружаем статус ответа и сообщение
        $bStateError = false;
        $sMsgTitle = '';
        $sMsg = '';
        $aMsgError = \E::Module('Message')->GetError();
        $aMsgNotice = \E::Module('Message')->GetNotice();
        if (count($aMsgError) > 0) {
            $bStateError = true;
            $sMsgTitle = $aMsgError[0]['title'];
            $sMsg = $aMsgError[0]['msg'];
        } elseif (count($aMsgNotice) > 0) {
            $sMsgTitle = $aMsgNotice[0]['title'];
            $sMsg = $aMsgNotice[0]['msg'];
        }
        $this->assignAjax('sMsgTitle', $sMsgTitle);
        $this->assignAjax('sMsg', $sMsg);
        $this->assignAjax('bStateError', $bStateError);
        if ($sType === 'json') {
            $this->responseSetHeader('Content-type', 'application/json; charset=utf-8');
            $sOutput = \F::jsonEncode($this->aVarsAjax);
        } elseif ($sType === 'jsonIframe') {
            // Оборачивает json в тег <textarea>, это не дает браузеру выполнить HTML, который вернул iframe
            $this->responseSetHeader('Content-type', 'application/json; charset=utf-8');

            // * Избавляемся от бага, когда в возвращаемом тексте есть &quot;
            $sOutput = '<textarea>' . htmlspecialchars(\F::jsonEncode($this->aVarsAjax)) . '</textarea>';
        } elseif ($sType === 'jsonp') {
            $this->responseSetHeader('Content-type', 'application/json; charset=utf-8');
            $sOutput = \F::getRequest('jsonpCallback', 'callback') . '(' . \F::jsonEncode($this->aVarsAjax) . ');';
        }

        $this->flush($sOutput);

        exit();
    }

    /**
     * Flush output string to client
     *
     * @param string $sOutput
     */
    public function flush($sOutput)
    {
        $this->responseSendHeaders();

        echo $sOutput;
    }

    /**
     * Sets forced skin
     *
     * @param string $sSkin
     */
    public function setViewSkin($sSkin)
    {
        $this->sForcedSkin = $sSkin;
    }

    /**
     * Sets forced theme
     *
     * @param string $sTheme
     */
    public function setViewTheme($sTheme)
    {
        $this->sForcedTheme = $sTheme;
    }

    /**
     * Returns theme of current skin from forced settings or config
     *
     * @param  bool $bSiteSkin - if true then returns skin for site (ignore LEVEL_ACTION)
     *
     * @return string
     */
    public function getViewSkin($bSiteSkin = false)
    {
        if ($this->sForcedSkin && !$bSiteSkin) {
            return $this->sForcedSkin;
        }
        return $this->getConfigSkin($bSiteSkin);
    }

    /**
     * Returns theme of current theme from forced settings or config
     *
     * @param  bool $bSiteSkin - if true then returns theme for site (ignore LEVEL_ACTION)
     *
     * @return string
     */
    public function getViewTheme($bSiteSkin = false) {

        if ($this->sForcedTheme && !$bSiteSkin) {
            return $this->sForcedTheme;
        }
        return $this->getConfigTheme($bSiteSkin);
    }

    /**
     * Возвращает скин
     *
     * @param bool $bSiteSkin - если задано, то возвращает скин, установленный для сайта (игнорирует скин экшена)
     *
     * @return  string
     */
    public function getConfigSkin($bSiteSkin = false)
    {
        if ($bSiteSkin) {
            return \C::get('view.skin', \C::LEVEL_CUSTOM);
        } else {
            return \C::get('view.skin');
        }
    }

    /**
     * Returns theme of current skin from config
     *
     * @param  bool $bSiteSkin - if true then returns theme for site (ignore LEVEL_ACTION)
     *
     * @return string
     */
    public function getConfigTheme($bSiteSkin = false)
    {
        if ($bSiteSkin) {
            return \C::get('view.theme', \C::LEVEL_CUSTOM);
        } else {
            return \C::get('view.theme');
        }
    }

    /**
     * Возвращает путь к папке скина
     *
     * @param bool $bSiteSkin - Если задано, то возвращает скин, установленный для сайта (игнорирует скин экшена)
     *
     * @return  string
     */
    public function getTemplateDir($bSiteSkin = true)
    {
        if ($bSiteSkin) {
            return \F::File_NormPath(\C::get('path.skins.dir') . '/' . $this->getConfigSkin($bSiteSkin));
        } else {
            return \C::get('smarty.path.template');
        }
    }

    /**
     * Возвращает тип отдачи контекта
     *
     * @return string
     */
    public function getResponseAjax()
    {
        return $this->sResponseAjax;
    }

    /**
     * Устанавливает тип отдачи при ajax запросе, если null то выполняется обычный вывод шаблона в браузер
     *
     * @param string $sResponseAjax    Тип ответа
     * @param bool $bResponseSpecificHeader    Установливать специфичные тиру заголовки через header()
     * @param bool $bValidate    Производить или нет валидацию формы через {@link Security::ValidateSendForm}
     */
    public function setResponseAjax($sResponseAjax = 'json', $bResponseSpecificHeader = true, $bValidate = true)
    {
        // Для возможности кросс-доменных запросов
        if ($sResponseAjax !== 'jsonp' && $bValidate) {
            \E::Module('Security')->validateSendForm();
        }
        $this->sResponseAjax = $sResponseAjax;
        $_REQUEST['ALTO_AJAX'] = $sResponseAjax;
        $this->bResponseSpecificHeader = $bResponseSpecificHeader;
    }

    /**
     * Загружаем переменную в ajax ответ
     *
     * @param string $sName    Имя переменной в шаблоне
     * @param mixed $xValue    Значение переменной
     */
    public function assignAjax($sName, $xValue)
    {
        $this->aVarsAjax[$sName] = $xValue;
    }

    /**
     * Sets value(s) to template variable(s)
     *
     * @param string|array $xParam Name of template variable or associate array
     * @param mixed|null   $xValue Value of variable if $xParam is string
     */
    public function assign($xParam, $xValue = null)
    {
        if (is_array($xParam) && (null === $xValue)) {
            foreach((array)$xParam as $sName => $xVal) {
                $this->assign($sName, $xVal);
            }
        } else {
            $this->aVarsTemplate[$xParam] = $xValue;
            if (self::$_inRender || $this->bLocal) {
                $this->_tplAssign($xParam, $xValue);
            }
        }
    }

    /**
     * Returns template variable(s)
     *
     * @param string|null $sVarName
     *
     * @return mixed
     */
    public function getTemplateVars($sVarName = null)
    {
        $xResult = null;
        if ($sVarName) {
            if (isset($this->aVarsTemplate[$sVarName])) {
                $xResult = $this->aVarsTemplate[$sVarName];
            }
        } else {
            $xResult = $this->aVarsTemplate;
        }
        return $xResult;
    }

    /**
     * @param null      $sVarName
     * @param bool|true $bJsonEncode
     *
     * @return mixed
     */
    public function getAjaxVars($sVarName = null, $bJsonEncode = true)
    {
        $xResult = $this->aVarsAjax;
        if (is_string($sVarName)) {
            if (isset($xResult[$sVarName])) {
                $xResult = $xResult[$sVarName];
            } else {
                $xResult = null;
            }
        }
        if ($bJsonEncode) {
            $xResult = \F::jsonEncode($xResult);
        }

        return $xResult;
    }

    /**
     *
     */
    protected function _muteErrors()
    {
        if ($this->nMuteErrorsCnt <= 0) {
            Smarty::muteExpectedErrors();
            $this->nMuteErrorsCnt++;
        }
    }

    /**
     *
     */
    protected function _unmuteErrors()
    {
        if ($this->nMuteErrorsCnt > 0) {
            Smarty::unmuteExpectedErrors();
            $this->nMuteErrorsCnt--;
        }
    }

    /**
     * @param string      $sSkin
     * @param string      $sTemplate
     * @param string|bool $sTemplatePath
     */
    protected function _setTemplatePath($sSkin, $sTemplate, $sTemplatePath)
    {
        static::$aTemplatePaths[$sSkin][$sTemplate] = $sTemplatePath;
    }

    /**
     * @param string $sSkin
     * @param string $sTemplate
     *
     * @return string|bool|null
     */
    protected function _getTemplatePath($sSkin, $sTemplate)
    {
        if (isset(static::$aTemplatePaths[$sSkin][$sTemplate])) {
            if (static::$aTemplatePaths[$sSkin][$sTemplate] === true) {
                return $sTemplate;
            }
            return static::$aTemplatePaths[$sSkin][$sTemplate];
        }
        return null;
    }

    /**
     * Проверяет существование шаблона
     *
     * @param   string $sTemplate   Шаблон
     * @param   bool   $bException  Нужно ли генерить ошибку, если шаблон не найден
     *
     * @return  bool
     */
    public function templateExists($sTemplate, $bException = false)
    {
        $sSkin = $this->getConfigSkin();

        $sResult = $this->_getTemplatePath($sSkin, $sTemplate);

        if (is_null($sResult)) {
            $sResult = $this->_tplTemplateExists($sTemplate, $bException);
            if ($sResult) {
                $this->_setTemplatePath($sSkin, $sTemplate, $sResult);
            }
        }

        return $sResult;
    }

    /**
     * Проверяет существование скина/темы
     *
     * @param   string $sSkin      Скин (или скин/тема)
     * @param   bool   $bException Нужно ли генерить ошибку, если скин не найден
     *
     * @return  bool
     */
    public function skinExists($sSkin, $bException = false)
    {
        if (strpos($sSkin, '/') !== false) {
            // Разделяем сам скин и тему
            list($sSkin, $sTheme) = explode('/', $sSkin, 2);
            if (!$sSkin) {
                $sSkin = $this->getConfigSkin();
            }
        } else {
            $sTheme = null;
        }
        $sCheckDir = \C::get('path.skin.dir');
        // Если проверяется не текущий скин, то корректируем путь
        if ($sSkin !== $this->getConfigSkin()) {
            $sCheckDir = str_replace('/' . $this->getConfigSkin() . '/', '/' . $sSkin . '/', $sCheckDir);
        }
        // Проверяем только скин или тему скина
        if ($sTheme) {
            $sCheckDir .= 'themes/' . $sTheme . '/';
        }
        $bResult = is_dir($sCheckDir);
        if (!$bResult && $bException) {
            if ($sTheme) {
                $sMessage = 'Can not find the theme "' . $sTheme . '" of skin "' . $sSkin . '"';
                // записываем доп. информацию - пути к шаблонам Smarty
                $sErrorInfo = 'Theme dir: ' . $sCheckDir;
            } else {
                $sMessage = 'Can not find the skin "' . $sSkin . '"';
                // записываем доп. информацию - пути к шаблонам Smarty
                $sErrorInfo = 'Skin dir: ' . $sCheckDir;
            }
            $this->_error($sMessage, $sErrorInfo);
            return false;
        }
        return $bResult;
    }

    /**
     * Проверяет существование папки текущего скина
     *
     * @return  bool
     */
    public function checkSkin()
    {
        if (!$this->skinExists($this->getConfigSkin(), true)) {
            die('Please check skin folder');
        }
        return true;
    }

    /**
     * Проверяет существование папки конкретной темы текущего скина
     *
     * @param   string $sTheme
     *
     * @return  bool
     */
    public function checkTheme($sTheme)
    {
        return $this->skinExists($this->getConfigSkin() . '/' . $sTheme, false);
    }

    /**
     * Генерация ошибки "Шаблон не найден"
     *
     * @param   string $sMessage   - сообщение об ошибке
     * @param   string $sErrorInfo - доп. информация для записи в лог
     *
     * @throws  Exception
     */
    protected function _error($sMessage, $sErrorInfo = null)
    {
        $oException = new Exception($sMessage);
        $oException->sAdditionalInfo = $sErrorInfo;
        throw $oException;
    }

    /**
     * Добавляет виджет для отображения
     *
     * @param   string $sGroup Группа виджетов
     * @param   string $sName  Название виджета - Можно передать название виджета,
     *                         тогда для обработки данных будет вызван обработчик из /classes/widgets/,
     *                         либо передать путь до шаблона, тогда будет выполнено
     *                         обычное подключение шаблона
     * @param   array $aParams Параметры виджета, которые будут переданы обработчику
     * @param   int $iPriority Приоритет, согласно которому сортируются виджеты
     *
     * @return  bool
     */
    public function addWidget($sGroup, $sName, $aParams = [], $iPriority = null)
    {
        if (null === $iPriority) {
            $iPriority = (isset($aParams['priority']) ? $aParams['priority'] : 0);
        }

        $aWidgetData = [
            'wgroup'   => $sGroup,
            'name'     => $sName,
            'priority' => $iPriority,
            'params'   => $aParams,
        ];
        if (isset($aWidgetData['params']['id'])) {
            $aWidgetData['id'] = $aWidgetData['params']['id'];
            unset($aWidgetData['params']['id']);
        }

        $oWidget = \E::Module('Widget')->makeWidget($aWidgetData);

        // Если тип виджета определен, то добавляем его
        if (!$oWidget->getType()) {
            return false;
        }

        // Добавляем виджет в массив дополнительных
        $this->aWidgetsAppend[$oWidget->getId()] = $oWidget;

        // Сбрасываем флаг сортировки
        $this->bWidgetsSorted = false;

        return true;
    }

    /**
     * Добавляет список виджетов
     *
     * @param   string $sGroup          - Группа виджетов
     * @param   array  $aWidgets        - Список добавляемых виджетов
     * @param   bool   $ClearWidgets    - Очищать или нет список виджетов, добавленных до этого, в данной группе
     * <pre>
     * \E::Module('Viewer')->addWidgets('right', array('tags', array('widget'=>'stream', 'priority'=>100)));
     * </pre>
     */
    public function addWidgets($sGroup, $aWidgets, $ClearWidgets = true)
    {
        // * Удаляем ранее добавленые виджеты
        if ($ClearWidgets) {
            $this->clearWidgets($sGroup);
        }
        foreach ($aWidgets as $sWidget) {
            if (is_array($sWidget)) {
                $this->addWidget(
                    $sGroup,
                    $sWidget['widget'],
                    isset($sWidget['params']) ? $sWidget['params'] : [],
                    isset($sWidget['priority']) ? $sWidget['priority'] : 0
                );
            } else {
                $this->addWidget($sGroup, $sWidget);
            }
        }
    }

    /**
     * Удаляет виджеты заданной группы
     *
     * @param   string $sGroup
     */
    public function clearWidgets($sGroup)
    {
        $this->aWidgets[$sGroup] = [];
    }

    /**
     * Удаляет виджеты из всех групп
     *
     */
    public function clearAllWidgets()
    {
        $this->aWidgets = [];
    }

    /**
     * Возвращает список виджетов
     *
     * @param bool $bSort Выполнять или нет сортировку виджетов
     *
     * @return array
     */
    public function getWidgets($bSort = true)
    {
        if ($this->aWidgetsAppend) {
            $this->_addWidgetsToList($this->aWidgetsAppend);
            $this->aWidgetsAppend = [];
        }

        if ($bSort && !$this->bWidgetsSorted) {
            $this->_sortWidgets();
        }

        return $this->aWidgets;
    }

    /**
     * Вспомагательная функция для сортировки виджетов:
     *  - первыми идут с приоритетом 'top'
     *  - потом те, у кого выше приоритет
     *  - потом те, которые были раньше добавлены
     *
     * @param  ModuleWidget_EntityWidget $oW1
     * @param  ModuleWidget_EntityWidget $oW2
     *
     * @return int
     */
    public function _sortWidgetsCompare($oW1, $oW2)
    {
        if ($oW1->getPriority() === $oW2->getPriority()) {
            return $oW1->getOrder() - $oW2->getOrder();
        } elseif ($oW1->isTop()) {
            return 1;
        } elseif ($oW2->isTop()) {
            return -1;
        }
        if ($oW1->getPriority() < $oW2->getPriority()) {
            return -1;
        }
        return 1;
    }

    /**
     * Сортируем виджеты по приоритетам
     *
     */
    protected function _sortWidgets()
    {
        if ($this->aWidgets)
            foreach ($this->aWidgets as $sGroup => $aWidgets) {
                if (count($aWidgets)) {
                    uasort($aWidgets, [$this, '_sortWidgetsCompare']);
                    $this->aWidgets[$sGroup] = array_reverse($aWidgets);
                }
            }
        $this->bWidgetsSorted = true;
    }

    /**
     * Make lists of widgets (separated by groups)
     */
    protected function _makeWidgetsLists()
    {
        // Load widgets from config files
        $aWidgets = \E::Module('Widget')->getWidgets();
        $iCount = $this->_addWidgetsToList($aWidgets);

        // Check widgets added from actions
        if ($this->aWidgetsAppend) {
            $iCount += $this->_addWidgetsToList($this->aWidgetsAppend);
            $this->aWidgetsAppend = [];
        }

        if ($iCount) {
            $this->_sortWidgets();
        }
    }

    /**
     * Adds widgets from array to current lists
     *
     * @param array $aWidgets
     *
     * @return int
     */
    protected function _addWidgetsToList($aWidgets)
    {
        $iCount = 0;
        /** @var ModuleWidget_EntityWidget $oWidget */
        foreach ($aWidgets as $oWidget) {
            $sGroup = $oWidget->getGroup();
            if (!$sGroup) {
                // group not defined
                $sGroup = '-';
            }
            // Свойство "order" потребуется для сортировки по порядку добавления, если не задан приоритет
            if (!$oWidget->getOrder()) {
                $oWidget->setOrder(isset($this->aWidgets[$sGroup]) ? count($this->aWidgets[$sGroup]) : 0);
            }

            // if widget must be displayed then we add it to arrays
            if ($oWidget->isDisplay()) {
                // Список всех виджетов, в т.ч. и без группы
                $this->aWidgets['_all_'][$oWidget->GetId()] = $oWidget;
                // Список виджетов с разбивкой по группам (чтоб не дублировать, сохраняем ссылку на элемент в общем списке)
                $this->aWidgets[$sGroup][$oWidget->GetId()] = & $this->aWidgets['_all_'][$oWidget->GetId()];
                $iCount += 1;
            }
        }
        return $iCount;
    }

    /**
     * Добавляет js-файл в конец списка
     *
     * @param string $sFile    js-файл
     * @param array  $aParams  Параметры, например, можно указать параметр 'name'=>'jquery.plugin.foo'
     *                         для исключения повторного добавления файла с таким именем
     * @param bool   $bReplace заменять ли файл с таким же именем, если он уже есть
     */
    public function appendScript($sFile, $aParams = [], $bReplace = null)
    {
        \E::Module('ViewerAsset')->appendJs($sFile, $aParams, $bReplace || !empty($aParams['replace']));
    }

    /**
     * Добавляет js-файл в начало списка
     *
     * @param string $sFile    js-файл
     * @param array  $aParams  Параметры, например, можно указать параметр 'name'=>'jquery.plugin.foo'
     *                         для исключения повторного добавления файла с таким именем
     * @param bool   $bReplace заменять ли файл с таким же именем, если он уже есть
     */
    public function prependScript($sFile, $aParams = [], $bReplace = null)
    {
        \E::Module('ViewerAsset')->prependJs($sFile, $aParams, $bReplace || !empty($aParams['replace']));
    }

    /**
     * Добавляет css-файл в конец списка
     *
     * @param string $sFile    css-файл стилей
     * @param array  $aParams  Параметры, например, можно указать параметр 'name'=>'blueprint'
     *                         для исключения повторного добавления файла с таким именем
     * @param bool   $bReplace заменять ли файл с таким же именем, если он уже есть
     */
    public function appendStyle($sFile, $aParams = [], $bReplace = null)
    {
        \E::Module('ViewerAsset')->appendCss($sFile, $aParams, $bReplace || !empty($aParams['replace']));
    }

    /**
     * Добавляет css-файл в начало списка
     *
     * @param string $sFile      css-файл стилей
     * @param array  $aParams    Параметры, например, можно указать параметр 'name'=>'blueprint'
     *                           для исключения повторного добавления файла с таким именем
     * @param bool   $bReplace   заменять ли файл с таким же именем, если он уже есть
     */
    public function prependStyle($sFile, $aParams = [], $bReplace = null)
    {
        \E::Module('ViewerAsset')->prependCss($sFile, $aParams, $bReplace || !empty($aParams['replace']));
    }

    /**
     * Готовит для подключения js-файл
     *
     * @param string $sFile
     * @param array  $aParams
     * @param bool   $bReplace
     */
    public function prepareScript($sFile, $aParams = [], $bReplace = null)
    {
        if (is_array($aParams)) {
            $aParams['prepare'] = true;
        } else {
            $aParams = ['prepare' => true];
        }
        return \E::Module('ViewerAsset')->appendJs($sFile, $aParams, $bReplace || !empty($aParams['replace']));
    }

    /**
     * Готовит для подключения css-файл
     *
     * @param string $sFile
     * @param array  $aParams
     * @param bool   $bReplace
     */
    public function prepareStyle($sFile, $aParams = [], $bReplace = null)
    {
        if (is_array($aParams)) {
            $aParams['prepare'] = true;
        } else {
            $aParams = ['prepare' => true];
        }
        return \E::Module('ViewerAsset')->appendCss($sFile, $aParams, $bReplace || !empty($aParams['replace']));
    }

    /**
     * Prepend asset file (.js or .css)
     *
     * @param string $sFile
     * @param array  $aParams
     *
     * @return bool
     */
    public function prependAsset($sFile, $aParams = [])
    {
        if (substr($sFile, -3) === '.js') {
            return $this->prependScript($sFile, $aParams);
        }
        if (substr($sFile, -4) === '.css') {
            return $this->prependStyle($sFile, $aParams);
        }
        return false;
    }

    /**
     * Append asset file (.js or .css)
     *
     * @param string $sFile
     * @param array  $aParams
     *
     * @return bool
     */
    public function appendAsset($sFile, $aParams = [])
    {
        if (substr($sFile, -3) === '.js') {
            return $this->appendScript($sFile, $aParams);
        }
        if (substr($sFile, -4) === '.css') {
            return $this->appendStyle($sFile, $aParams);
        }
        return false;
    }

    /**
     * Строит массив для подключения css и js,
     * преобразовывает их в строку для HTML
     *
     */
    protected function _buildHeadFiles()
    {
        $aCfg = [
            'url' => [
                'root' => \C::get('path.root.url'), // реальный рут сайта
                'ajax' => R::url('base'), // адрес для ajax-запросов
            ],
            'assets' => \E::Module('ViewerAsset')->getPreparedAssetLinks(),
            'lang' => \C::get('lang.current'),
            'wysiwyg' => \C::get('view.wysiwyg') ? true : false,
        ];

        $sScript = 'var alto = alto || { };' . PHP_EOL;
        $sScript .= 'alto.cfg = ' . \F::JsonEncode($aCfg) . ';' . PHP_EOL;
        $sScript = '<script>' . $sScript . '</script>' . PHP_EOL;

        $this->setHtmlHeadScripts([$sScript]);
    }

    /**
     * Аналог realpath + обработка URL
     *
     * @param string $sPath
     * 
     * @return string
     */
    protected function _getRealpath($sPath)
    {
        if (preg_match('@^(http|https):@', $sPath)) {
            $aUrl = parse_url($sPath);
            $sPath = $aUrl['path'];

            $aParts = [];
            $sPath = preg_replace('~/\./~', '/', $sPath);
            foreach (explode('/', preg_replace('~/+~', '/', $sPath)) as $sPart) {
                if ($sPart === '..') {
                    array_pop($aParts);
                } elseif (!empty($sPart)) {
                    $aParts[] = $sPart;
                }
            }
            return (array_key_exists('scheme', $aUrl) ? $aUrl['scheme'] . '://' . $aUrl['host'] : '') . '/' . implode('/', $aParts);
        }
        return realpath($sPath);
    }

    /**
     * Устанавливает список скриптов для вывода в хидере страницы
     *
     * @param array $aScripts Список файлов
     */
    public function setHtmlHeadScripts($aScripts)
    {
        $this->aHtmlHeaScripts = $aScripts;
    }

    /**
     * Добавляет тег для вывода в <HEAD>
     *
     * @param string $sTag
     */
    public function addHtmlHeadTag($sTag)
    {
        if ($sTag[0] === '<') {
            $sTag = (string)substr($sTag, 1);
        }
        if (substr($sTag, -2) === '/>') {
            $sTag = (string)substr($sTag, 0, - 2);
        } elseif (substr($sTag, -1) === '>') {
            $sTag = (string)substr($sTag, 0, - 1);
        }

        if (strpos($sTag, ' ')) {
            list($sTagName, $sAttributes) = explode(' ', $sTag, 2);
        } else {
            $sTagName = $sTag;
            $sAttributes = [];
        }
        $this->setHtmlHeadTag($sTagName, $sAttributes);
    }

    /**
     * @param string       $sTagName
     * @param array|string $xAttributes
     * @param string|bool  $xContent
     */
    public function setHtmlHeadTag($sTagName, $xAttributes, $xContent = false)
    {
        $sTagName = strtolower($sTagName);

        $sKey = '';
        $sTag = '<' . $sTagName;
        if (is_string($xAttributes)) {
            $sTag .= ' ' . $xAttributes;
        } elseif (is_array($xAttributes) && count($xAttributes)) {
            $aAttrs = [];
            foreach ((array)$xAttributes as $sName => $sValue) {
                if (is_string($sName)) {
                    $aAttrs[strtolower($sName)] = $sValue;
                } else {
                    $aAttrs[] = $sValue;
                }
            }

            if ($sTagName === 'meta') {
                if (!empty($aAttrs['property']) && strpos($aAttrs['property'], 'og:image') === 0) {
                    $sKey = $sTagName . ' property=' . $aAttrs['property'] . '-' . uniqid(count($this->aHtmlHeadTags), true);
                } else {
                    foreach ($this->aSpecMetaTagsAttr as $sName) {
                        if (isset($aAttrs[$sName])) {
                            // defines special key for meta tags
                            $sKey = $sTagName . ' ' . $sName . '=' . $aAttrs[$sName];
                            break;
                        }
                    }
                }
            }

            if ($aAttrs) {
                $sAttr = '';
                foreach ($aAttrs as $sName => $sValue) {
                    if (is_string($sName)) {
                        $sAttr .= ' ' . $sName . '="' . str_replace('"', '&quot;', $sValue) . '"';
                    } else {
                        $sAttr .= ' ' . htmlspecialchars($sValue, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
                    }
                }
                if ($sAttr) {
                    $sTag .= $sAttr;
                }
            }
        }
        $sTag .= '>';
        // adds content and closing tag
        if ($xContent !== false) {
            $sTag .= htmlspecialchars($xContent);
            $sTag .= '</' . $sTagName . '>';
        }
        // prevents duplication
        if ($sKey) {
            $this->aHtmlHeadTags[$sKey] = $sTag;
        } else {
            $this->aHtmlHeadTags[$sTag] = $sTag;
        }
    }

    /**
     * Additional tags for <head>
     *
     * @param array $aParams
     */
    public function setHtmlHeadTags($aParams)
    {
        foreach($aParams as $aTag) {
            if (is_string($aTag)) {
                $this->addHtmlHeadTag($aTag);
            } elseif (is_array($aTag)) {
                $this->setHtmlHeadTag($aTag[0], isset($aTag[1]) ? $aTag[1] : null, isset($aTag[2]) ? $aTag[2] : false);
            }
        }
    }

    /**
     * Returns all additional tags for <head>
     *
     * @return array
     */
    public function getHtmlHeadTags()
    {
        return $this->aHtmlHeadTags;
    }

    /**
     * Clears all additional tags for <head>
     *
     */
    public function clearHtmlHeadTags()
    {
        $this->aHtmlHeadTags = [];
    }

    /**
     * Устанавливаем заголовок страницы (тег title)
     *
     * @param string $sText    Заголовок
     */
    public function setHtmlTitle($sText)
    {
        $this->aHtmlTitles = [$sText];
    }

    /**
     * Добавляет часть заголовка страницы через разделитель
     *
     * @param string $sText    Заголовок
     */
    public function addHtmlTitle($sText)
    {
        $this->aHtmlTitles[] = $sText;
    }

    /**
     * Возвращает текущий заголовок страницы
     *
     * @param bool $bHtmlEncode    Convert special characters of title to HTML entities
     *
     * @return string
     */
    public function getHtmlTitle($bHtmlEncode = true)
    {
        $aTitles = array_reverse($this->aHtmlTitles);
        if ($this->iHtmlTitlesMax && count($aTitles) > $this->iHtmlTitlesMax) {
            $aTitles = array_splice($aTitles, 0, $this->iHtmlTitlesMax);
        }
        // required part of the tag <title>
        if (\C::get('view.html.title') && count($aTitles) && (end($aTitles) !== \C::get('view.html.title'))) {
            $aTitles[] = \C::get('view.html.title');
        }
        $sHtmlTitle = implode($this->sHtmlTitleSeparator, $aTitles);
        if ($bHtmlEncode) {
            $sHtmlTitle = htmlspecialchars($sHtmlTitle);
        }
        return $sHtmlTitle;
    }

    /**
     * Устанавливает ключевые слова для мета-тега keywords
     *
     * @param string $sText    ключевые слова
     */
    public function setHtmlKeywords($sText)
    {
        $aKeywords = array_map('trim', explode(',', $sText));
        $this->aHtmlKeywords = $aKeywords;
    }

    /**
     * Returns string with keywirds
     *
     * @param bool $bHtmlEncode
     *
     * @return string
     */
    public function getHtmlKeywords($bHtmlEncode = true)
    {
        $aKeywords = $this->aHtmlKeywords;
        if ($bHtmlEncode) {
            $aKeywords = array_map('htmlspecialchars', $aKeywords);
        }
        return implode(', ', $aKeywords);
    }

    /**
     * Устанавливает описание страницы для мета-тега desciption
     *
     * @param string $sText    Описание
     */
    public function setHtmlDescription($sText)
    {
        $this->sHtmlDescription = $sText;
    }

    /**
     * Returns description for HTML
     *
     * @param bool $bHtmlEncode
     *
     * @return string
     */
    public function getHtmlDescription($bHtmlEncode = true)
    {
        if ($bHtmlEncode) {
            return htmlspecialchars($this->sHtmlDescription);
        }
        return $this->sHtmlDescription;
    }

    /**
     * Устанавливает основной адрес страницы
     *
     * @param string $sUrl    URL страницы
     * @param bool $bRewrite  Перезаписывать URL, если он уже установлен
     */
    public function setHtmlCanonical($sUrl, $bRewrite = false)
    {
        if (!$this->sHtmlCanonical || $bRewrite) {
            $this->sHtmlCanonical = $sUrl;
        }
    }

    /**
     * Устанавливает альтернативный адрес страницы по RSS
     *
     * @param string $sUrl   URL
     * @param string $sTitle Заголовок
     */
    public function setHtmlRssAlternate($sUrl, $sTitle)
    {
        $this->aHtmlRssAlternate['title'] = htmlspecialchars($sTitle);
        $this->aHtmlRssAlternate['url'] = htmlspecialchars($sUrl);
    }

    /**
     * Формирует постраничный вывод
     *
     * @param int    $iCount         Общее количество элементов
     * @param int    $iCurrentPage   Текущая страница
     * @param int    $iCountPerPage  Количество элементов на одну страницу
     * @param int    $iCountPageLine Количество ссылок на другие страницы
     * @param string $sBaseUrl       Базовый URL, к нему будет добавлять постфикс /pageN/ и GET параметры
     * @param array  $aGetParamsList Список GET параметров, которые необходимо передавать при постраничном переходе
     *
     * @return array|bool
     */
    public function makePaging($iCount, $iCurrentPage, $iCountPerPage, $iCountPageLine, $sBaseUrl, $aGetParamsList = [])
    {
        if (empty($iCount)) {
            return false;
        }

        $iCountPage = (int)ceil($iCount / $iCountPerPage);
        if (!preg_match('/^[1-9]\d*$/i', $iCurrentPage)) {
            $iCurrentPage = 1;
        }
        if ($iCurrentPage > $iCountPage) {
            $iCurrentPage = $iCountPage;
        }

        $iMin = $iCurrentPage - floor($iCountPageLine / 2);
        if ($iMin < 1) {
            $iMin = 1;
        }
        $iMax = $iMin + $iCountPageLine - 1;
        if ($iMax > $iCountPage) {
            $iMax = $iCountPage;
        }
        if ($iMax - $iMin < $iCountPageLine) {
            $iMin = $iMax - $iCountPageLine + 1;
            if ($iMin < 1) {
                $iMin = 1;
            }
        }

        $aPagesLeft = [];
        $aPagesRight = [];
        for ($i = $iMin; $i <= $iMax; $i++) {
            if ($i < $iCurrentPage) {
                $aPagesLeft[] = $i;
            } elseif ($i > $iCurrentPage) {
                $aPagesRight[] = $i;
            }
        }

        $iNextPage = $iCurrentPage < $iCountPage ? $iCurrentPage + 1 : false;
        $iPrevPage = $iCurrentPage > 1 ? $iCurrentPage - 1 : false;

        $sGetParams = '';
        if (is_string($aGetParamsList) || count($aGetParamsList)) {
            $sGetParams = '?' . (is_array($aGetParamsList) ? http_build_query($aGetParamsList, '', '&') : $aGetParamsList);
        }
        $aPaging = [
            'aPagesLeft'    => $aPagesLeft,
            'aPagesRight'   => $aPagesRight,
            'iCount'        => $iCount,
            'iCountPage'    => $iCountPage,
            'iCurrentPage'  => $iCurrentPage,
            'iNextPage'     => $iNextPage,
            'iPrevPage'     => $iPrevPage,
            'sBaseUrl'      => rtrim($sBaseUrl, '/'),
            'sGetParams'    => $sGetParams,
        ];
        /**
         * Избавляемся от дублирования страниц с page=1
         */
        if ($aPaging['iCurrentPage'] == 1) {
            $this->setHtmlCanonical($aPaging['sBaseUrl'] . '/' . $aPaging['sGetParams']);
        }
        return $aPaging;
    }

    /**
     * Добавить меню в контейнер
     *
     * @param string $sContainer
     * @param string $sTemplate
     */
    public function addMenu($sContainer, $sTemplate)
    {
        $this->aMenu[strtolower($sContainer)] = $sTemplate;
    }

    /**
     * Компилирует меню по контейнерам
     *
     */
    protected function _buildMenu()
    {
        foreach ($this->aMenu as $sContainer => $sTemplate) {
            $this->aMenuFetch[$sContainer] = $this->fetch($sTemplate);
        }
    }

    /**
     * Обработка поиска файла шаблона, если его не смог найти шаблонизатор Smarty
     *
     * @param string $sType       Тип шаблона/ресурса
     * @param string $sName       Имя шаблона - имя файла
     * @param string $sContent    Возврат содержания шаблона при return true;
     * @param int    $iTimestamp  Возврат даты модификации шаблона при return true;
     * @param Smarty $oSmarty     Объект Smarty
     *
     * @return string|bool
     */
    public function smartyDefaultTemplateHandler($sType, $sName, &$sContent, &$iTimestamp, $oSmarty)
    {
        /*
         * Название шаблона может содержать, как полный путь до файла шаблона,
         * так и относительный любого из каталога в $oSmarty->getTemplateDir()
         * По дефолту каталоги такие: /frontend/skin/[name]/ и /plugins/
         */
        /**
         * Задача: если это файл плагина для текущего шаблона, то смотрим этот же файл шаблона плагина в /default/
         */
        $sSkin = $this->getConfigSkin();

        $sResult = $this->_getTemplatePath($sSkin, $sName);
        if ($sResult) {
            return $sResult;
        }

        if ($sSkin !== 'default') {
            $sSkinSeek = preg_quote($sSkin, '@');
            if (preg_match('@^/plugins/([\w\-_]+)/frontend/skin/' . $sSkinSeek . '/(.+)$/@i', $sName, $aMatch)) {
                // => /root/plugins/[plugin name]/frontend/skin/[skin name]/dir/test.tpl
                $sPluginDir = PluginManager::getDir($aMatch[1]);
                $sTemplateFile = $aMatch[2];

            } elseif (preg_match('@^([\w\-_]+)/frontend/skin/' . $sSkinSeek . '/(.+)$/@i', $sName, $aMatch)) {
                // => [plugin name]/frontend/skin/[skin name]/dir/test.tpl
                $sPluginDir = PluginManager::getDir($aMatch[1]);
                $sTemplateFile = $aMatch[2];

            } else {
                $sPluginDir = '';
                $sTemplateFile = '';
            }
            if ($sPluginDir && $sTemplateFile) {
                $sFile = $sPluginDir . '/frontend/skin/default/' . $sTemplateFile;
                if ($this->templateExists($sFile)) {
                    $sResult = $sFile;
                }
            }
        }

        if ($sResult) {
            $this->_setTemplatePath($sSkin, $sName, $sResult);
        }

        return $sResult;
    }

    /**
     * Clear all viewer's temporary & cache files
     */
    public function clearAll()
    {
        $this->clearSmartyFiles();
        $this->clearAssetsFiles();
    }

    /**
     * Clear all cached and compiled files of Smarty
     */
    public function clearSmartyFiles()
    {
        \F::File_ClearDir(\C::get('smarty.path.compiled'));
        \F::File_ClearDir(\C::get('smarty.path.cache'));
    }

    /**
     * Remove asset files and asset cache
     */
    public function clearAssetsFiles()
    {
        $sDir = \F::File_GetAssetDir();
        \F::File_RemoveDir($sDir);
        \E::Module('ViewerAsset')->clearAssetsCache();
    }

    /**
     * Загружаем переменные в шаблон при завершении модуля
     *
     */
    public function shutdown()
    {
        // Calculation of preprocess time is inside this method
        $this->_initRender();

        $nTimer = microtime(true);

        // * Создаются списки виджетов для вывода
        $this->_makeWidgetsLists();

        // * Добавляем JS и CSS по предписанным правилам
        $this->_buildHeadFiles();

        // * Передача переменных в шаблон
        $this->varAssign();

        // * Рендерим меню для шаблонов и передаем контейнеры в шаблон
        $this->_buildMenu();
        $this->_menuVarAssign();

        self::$_preprocessTime += microtime(true) - $nTimer;
    }

}

// EOF
