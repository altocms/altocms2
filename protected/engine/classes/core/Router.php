<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */
namespace alto\engine\core;

use alto\engine\generic\Singleton;
use alto\engine\generic\Controller;

/**
 * Класс роутинга
 * Инициализирует ядро, определяет какой экшен запустить согласно URL'у и запускает его
 *
 * @package engine
 * @since 1.0
 */
class Router extends Singleton
{
    /**
     * Конфигурация роутинга, получается из конфига
     *
     * @var array
     */
    protected $aConfigRoute = [];

    protected $oHttpRequest;

    protected $oHttpResponse;

    /**
     * Текущий контроллер
     *
     * @var string|null
     */
    static protected $sController = null;

    /**
     * Текущий евент
     *
     * @var string|null
     */
    static protected $sControllerAction = null;

    /**
     * Имя текущего евента
     *
     * @var string|null
     */
    static protected $sControllerActionName = null;

    /**
     * Класс текущего контроллера
     *
     * @var string|null
     */
    static protected $sControllerClass = null;

    /**
     * Текущий полный URL
     *
     * @var string|null
     */
    static protected $sCurrentFullUrl = null;

    /**
     * Текущий обрабатываемый путь контроллера
     *
     * @var string|null
     */
    static protected $sControllerPath = null;

    /**
     * Текущий язык
     *
     * @var string|null
     */
    static protected $sLang = null;

    /**
     * Список параметров ЧПУ URL
     * <pre>/controller/action/param0/param1/../paramN/</pre>
     *
     * @var array
     */
    static protected $aParams = [];

    static protected $aRequestURI = [];

    static protected $aControllerPaths = [];

    protected $aCurrentUrl = [];

    protected $aDefinedClasses = [];

    /**
     * Объект текущего экшена
     *
     * @var \alto\engine\generic\Controller|\alto\engine\generic\Action $oAction
     */
    protected $oController = null;

    /**
     * Объект ядра
     *
     * @var \alto\engine\core\Engine
     */
    protected $oEngine = null;

    /**
     * Маска фомирования URL топика
     *
     * @var string
     */
    static protected $sTopicUrlMask = null;

    /**
     * Маска фомирования URL профиля пользователя
     *
     * @var string
     */
    static protected $sUserUrlMask = null;

    /**
     * Call ModuleViewer()->Display() when shutdown
     *
     * @var bool
     */
    static protected $bAutoDisplay = true;

    /**
     * Загрузка конфига роутинга при создании объекта
     */
    public function __construct()
    {
        parent::__construct();
        $this->_loadConfig();
    }

    /**
     * @return HttpRequest
     */
    public function getHttpRequest()
    {
        if (null === $this->oHttpRequest) {
            $this->oHttpRequest = HttpRequest::create();
        }
        return $this->oHttpRequest;
    }

    /**
     * @return HttpResponse
     */
    public function getHttpResponse()
    {
        if (null === $this->oHttpResponse) {
            $this->oHttpResponse = HttpResponse::create();
        }
        return $this->oHttpResponse;
    }

    /**
     * Запускает весь процесс :)
     *
     * @throws \RuntimeException
     */
    public function exec()
    {
        $oRequest = $this->getHttpRequest();
        $oResponse = $this->getHttpResponse();

        $this->_parseUrl();
        $this->_defineControllerClass(); // Для возможности ДО инициализации модулей определить какой action/event запрошен
        $this->oEngine = \E::getInstance();

        $oResultRequest = $this->oEngine->init($oRequest);
        if (is_object($oResultRequest)) {
            $oRequest = $oResultRequest;
        }

        $oResultResponse = $this->execController($oRequest, $oResponse);
        if (is_object($oResultResponse)) {
            $oResponse = $oResultResponse;
        }

        $oResultResponse = $this->oEngine->shutdown($oResponse);
        if (is_object($oResultResponse)) {
            $oResponse = $oResultResponse;
        }

        $this->shutdown($oResponse);
    }

    /**
     * Завершение работы роутинга
     *
     * @param HttpResponse $oResponse
     */
    public function shutdown($oResponse)
    {
        $this->_assignVars();
        $this->oEngine->shutdown($oResponse);
        \E::Module('Viewer')->display($this->oController->getTemplate());
    }

    /**
     * Парсим URL
     * Пример: http://site.ru/controller/action/param1/param2/  на выходе получим:
     *    static::$sController = 'controller';
     *    static::$sActionEvent='action';
     *    static::$aParams=array('param1','param2');
     *
     */
    protected function _parseUrl()
    {
        $sReq = $this->_getRequestUri();
        $aRequestUrl = $this->_getRequestArray($sReq);

        // Список доступных языков, которые могут быть указаны в URL
        $aLangs = [];
        // Только для мультиязычных сайтов
        if (\C::get('lang.multilang')) {
            // Получаем список доступных языков
            $aLangs = (array)\C::get('lang.allow');

            // Проверка языка в URL
            if ($aRequestUrl && $aLangs && \C::get('lang.in_url')) {
                if (count($aLangs) && count($aRequestUrl) && in_array($aRequestUrl[0], $aLangs)) {
                    static::$sLang = array_shift($aRequestUrl);
                }
            }
        }

        static::$aRequestURI = $aRequestUrl = $this->_rewriteRequest($aRequestUrl);

        if (!empty($aRequestUrl)) {
            static::$sController = array_shift($aRequestUrl);
            static::$sControllerAction = array_shift($aRequestUrl);
        } else {
            static::$sController = null;
            static::$sControllerAction = null;
        }
        static::$aParams = $aRequestUrl;

        // Только для мультиязычных сайтов
        if (\C::get('lang.multilang')) {
            // Проверка языка в GET-параметрах
            if ($aLangs && \C::get('lang.in_get')) {
                $sLangParam = (is_string(\C::get('lang.in_get')) ? \C::get('lang.in_get') : 'lang');
                $sLang = \F::getRequestStr($sLangParam, null, 'get');
                if ($sLang) {
                    static::$sLang = $sLang;
                }
            }
        }

        $this->aCurrentUrl = parse_url(static::$sCurrentFullUrl);
        $this->aCurrentUrl['protocol'] = \F::urlScheme();
        if (!isset($this->aCurrentUrl['scheme']) && $this->aCurrentUrl['protocol']) {
            $this->aCurrentUrl['scheme'] = $this->aCurrentUrl['protocol'];
        }

        $iPathOffset = (int)\C::get('path.offset_request_url');
        $aUrlParts = \F::parseUrl();
        $sBase = !empty($aUrlParts['base']) ? $aUrlParts['base'] : null;
        if ($sBase && $iPathOffset) {
            $aPath = explode('/', trim($aUrlParts['path'], '/'));
            $iPathOffset = min($iPathOffset, count($aPath));
            for($i = 0; $i < $iPathOffset; $i++) {
                $sBase .= '/' . $aPath[$i];
            }
        }

        $this->aCurrentUrl['root'] = \F::File_RootUrl();
        $this->aCurrentUrl['base'] = $sBase . '/';
        $this->aCurrentUrl['lang'] = static::$sLang;
        $this->aCurrentUrl['controller'] = static::$sController;
        $this->aCurrentUrl['action'] = static::$sControllerAction;
        $this->aCurrentUrl['params'] = implode('/', static::$aParams);
    }

    /**
     * Метод выполняет первичную обработку $_SERVER['REQUEST_URI']
     *
     * @return string
     */
    protected function _getRequestUri()
    {
        $sReq = preg_replace('/\/+/', '/', $_SERVER['REQUEST_URI']);
        if (substr($sReq, -1) === '/') {
            $sLastChar = '/';
        } else {
            $sLastChar = '';
        }
        $sReq = preg_replace('/^\/(.*)\/?$/U', '$1', $sReq);
        $sReq = preg_replace('/^(.*)\?.*$/U', '$1', $sReq);

        // * Формируем $sCurrentFullUrl ДО применения реврайтов
        if (!empty($this->aConfigRoute['domains']['forward'])) {
            // маппинг доменов
            static::$sCurrentFullUrl = strtolower(\F::UrlBase() . '/' . implode('/', $this->_getRequestArray($sReq)));
        } else {
            static::$sCurrentFullUrl = strtolower(\F::File_RootUrl() . implode('/', $this->_getRequestArray($sReq)));
        }

        $this->_checkRedirectionRules();

        return $sReq . $sLastChar;
    }

    /**
     * Checks redirection rules and redirects if there is compliance
     */
    protected function _checkRedirectionRules()
    {
        if (isset($this->aConfigRoute['redirect']) && is_array($this->aConfigRoute['redirect'])) {
            $sUrl = static::$sCurrentFullUrl;

            $iHttpResponse = 301;
            foreach((array)$this->aConfigRoute['redirect'] as $sRule => $xTarget) {
                if ($xTarget) {
                    if (!is_array($xTarget)) {
                        $sTarget = $xTarget;
                        $iCode = 301;
                    } elseif (count($xTarget) === 1) {
                        $sTarget = reset($xTarget);
                        $iCode = 301;
                    } else {
                        $sTarget = reset($xTarget);
                        $iCode = (int)next($xTarget);
                    }
                    if (($sRule[0] === '[') && (substr($sRule, -1) === ']')) {
                        $sPattern = substr($sRule, 1, -2);
                        if (preg_match($sPattern, $sUrl)) {
                            $sUrl = preg_replace($sPattern, $sTarget, $sUrl);
                            $iHttpResponse = $iCode;
                        }
                    } else {
                        $sPattern = \F::strMatch($sRule, $sUrl, true, $aMatches);
                        if ($sPattern && isset($aMatches[1])) {
                            $sUrl = str_replace('*', $aMatches[1], $sTarget);
                            $iHttpResponse = $iCode;
                        }
                    }
                }
            }
            if ($sUrl && ($sUrl !== static::$sCurrentFullUrl)) {
                \F::httpHeader($iHttpResponse, null, $sUrl);
                exit;
            }
        }
    }

    /**
     * Возвращает массив реквеста
     *
     * @param string $sReq    Строка реквеста
     * @return array
     */
    protected function _getRequestArray($sReq)
    {
        $aRequestUrl = ($sReq === '' || $sReq === '/') ? [] : explode('/', trim($sReq, '/'));
        for ($i = 0; $i < \C::get('path.offset_request_url'); $i++) {
            array_shift($aRequestUrl);
        }
        $aRequestUrl = array_map('urldecode', $aRequestUrl);
        return $aRequestUrl;
    }

    /**
     * Returns router URI rules
     *
     * @return array
     */
    protected function _getRouterUriRules()
    {
        return (array)\C::get('router.uri');
    }

    /**
     * Applies config rewrite rules to request URI array, uses \C::Get('router.uri')
     *
     * @param array $aRequestUrl Request URI array
     *
     * @return array
     */
    protected function _rewriteRequest($aRequestUrl)
    {
        if (!$aRequestUrl) {
            return $aRequestUrl;
        }

        // STAGE 1: Rewrite rules for domains
        if (!empty($this->aConfigRoute['domains']['forward']) && !\F::ajaxRequest()) {
            // если в запросе есть контроллер и он есть в списке страниц, то доменный маппинг не выполняется
            if (empty($aRequestUrl[0]) || empty($this->aConfigRoute['controller'][$aRequestUrl[0]])) {
                $sHost = parse_url(self::$sCurrentFullUrl, PHP_URL_HOST);
                if (isset($this->aConfigRoute['domains']['forward'][$sHost])) {
                    $aRequestUrl = array_merge(explode('/', $this->aConfigRoute['domains']['forward'][$sHost]), $aRequestUrl);
                } else {
                    $aMatches = [];
                    $sPattern = \F::strMatch($this->aConfigRoute['domains']['forward_keys'], $sHost, true, $aMatches);
                    if ($sPattern) {
                        $sNewUrl = $this->aConfigRoute['domains']['forward'][$sPattern];
                        if (!empty($aMatches[1])) {
                            $sNewUrl = str_replace('*', $aMatches[1], $sNewUrl);
                            $aRequestUrl = array_merge(explode('/', $sNewUrl), $aRequestUrl);
                        }
                    }
                }
            }
        }

        // STAGE 2: Rewrite rules for REQUEST_URI
        $sRequest = implode('/', $aRequestUrl);

        $aRouterUriRules = $this->_getRouterUriRules();
        if ($aRouterUriRules) {
            foreach ($aRouterUriRules as $sPattern => $sReplace) {
                if ($sPattern[0] === '[' && substr($sPattern, -1) === ']') {
                    $sRegExp = substr($sPattern, 1, -1);
                } elseif ((strlen($sPattern) > 3) && ($sPattern[1] === '^') && (substr_count($sPattern, $sPattern[0]) === 2)) {
                    $sRegExp = $sPattern;
                } else {
                    $sRegExp = null;
                }
                if ($sRegExp && preg_match($sRegExp, $sRequest)) {
                    // regex pattern
                    $sRequest = preg_replace($sRegExp, $sReplace, $sRequest);
                    break;
                }
                if ($sRegExp && preg_match($sRegExp, $sRequest . '/')) {
                    // regex pattern
                    $sRequest = preg_replace($sRegExp, $sReplace, $sRequest . '/');
                    break;
                }

                if (substr($sPattern, -2) === '/*') {
                    $bFoundPattern = \F::strMatch([substr($sPattern, 0, -2), $sPattern], $sRequest, true, $aMatches);
                } else {
                    $bFoundPattern = \F::strMatch($sPattern, $sRequest, true, $aMatches);
                }
                if ($bFoundPattern) {
                    $sRequest = $sReplace;
                    break;
                }
            }

            if ($sRequest[0] === '@') {
                $this->_specialAction($sRequest);
            }
        }

        // STAGE 3: Internal rewriting (topic URLs etc.)
        $aRequestUrl = (trim($sRequest, '/') === '') ? [] : explode('/', $sRequest);
        if ($aRequestUrl) {
            $aRequestUrl = $this->_rewriteInternal($aRequestUrl);
        }

        // STAGE 4: Rules for actions rewriting
        if (isset($aRequestUrl[0])) {
            $sRequestAction = $aRequestUrl[0];
            if (isset($this->aConfigRoute['rewrite'][$sRequestAction])) {
                $sRequestAction = $this->aConfigRoute['rewrite'][$sRequestAction];
                $aRequestUrl[0] = $sRequestAction;
            }
        }
        return $aRequestUrl;
    }

    /**
     * Applies internal rewrite rules to request URI array, uses topics' and profiles' patterns
     *
     * @param array $aRequestUrl Request URI array
     *
     * @return array
     */
    protected function _rewriteInternal($aRequestUrl) 
    {
        $aRewrite = [];
        if ($sTopicUrlPattern = static::GetTopicUrlPattern()) {
            $aRewrite = array_merge($aRewrite, array($sTopicUrlPattern => 'blog/$1.html'));
        }
        if ($sUserUrlPattern = static::GetUserUrlPattern()) {
            if (strpos(static::GetUserUrlMask(), '%user_id%')) {
                $aRewrite = array_merge($aRewrite, array($sUserUrlPattern => 'profile/id-$1'));
            } elseif (strpos(static::GetUserUrlMask(), '%login%')) {
                $aRewrite = array_merge($aRewrite, array($sUserUrlPattern => 'profile/login-$1'));
            }
        }
        // * Internal rewrite rules for REQUEST_URI
        if ($aRewrite) {
            $sReq = implode('/', $aRequestUrl);
            foreach($aRewrite as $sPattern => $sReplace) {
                if (preg_match($sPattern, $sReq)) {
                    $sReq = preg_replace($sPattern, $sReplace, $sReq);
                    break;
                }
            }
            return (trim($sReq, '/') === '') ? [] : explode('/', $sReq);
        }
        return $aRequestUrl;
    }

    /**
     * Специальное действие по REQUEST_URI
     *
     * @param string $sReq
     */
    protected function _specialAction($sReq) 
    {
        if (0 === strpos($sReq, '@404')) {
            \F::httpHeader('404 Not Found');
            exit;
        } elseif (preg_match('~@die(.*)~i', $sReq, $aMatches)) {
            if (isset($aMatches[1]) && $aMatches[1]) {
                $sMsg = trim($aMatches[1]);
                if ($sMsg[0] === '(' && substr($sMsg, -1) === ')') {
                    $sMsg = trim($sMsg, '()');
                }
                if ($sMsg[0] === '"' && substr($sMsg, -1) === '"') {
                    $sMsg = trim($sMsg, '"');
                }
                if ($sMsg[0] === '\'' && substr($sMsg, -1) === '\'') {
                    $sMsg = trim($sMsg, '\'');
                }
                die($sMsg);
            }
        } else {
            exit;
        }
    }

    /**
     * Выполняет загрузку конфигов роутинга
     *
     */
    protected function _loadConfig() 
    {
        //Конфиг роутинга, содержит соответствия URL и классов экшенов
        $this->aConfigRoute = \C::get('router');

        // Переписываем конфиг согласно правилу rewrite
        if (!empty($this->aConfigRoute['rewrite'])) {
            foreach ((array)$this->aConfigRoute['rewrite'] as $sRequest => $sTarget) {
                if (isset($this->aConfigRoute['controller'][$sTarget])) {
                    $this->aConfigRoute['controller'][$sRequest] = $this->aConfigRoute['controller'][$sTarget];
                    unset($this->aConfigRoute['controller'][$sTarget]);
                }
            }
        }

        if (!empty($this->aConfigRoute['domain'])) {
            $aDomains = $this->aConfigRoute['domain'];
            $this->aConfigRoute['domains']['forward'] = $aDomains;
            $this->aConfigRoute['domains']['forward_keys'] = array_keys($aDomains);
            $this->aConfigRoute['domains']['backward'] = array_flip($aDomains);
            $this->aConfigRoute['domains']['backward_keys'] = array_keys($this->aConfigRoute['domains']['backward']);
        }
    }

    /**
     * Загружает в шаблонизатор необходимые переменные
     *
     */
    protected function _assignVars()
    {
        $aTable = get_html_translation_table();
        unset($aTable['&']);
        $sPathWebCurrent = strtr(static::$sCurrentFullUrl, $aTable);
        \E::Module('Viewer')->assign('PATH_WEB_CURRENT', $sPathWebCurrent);

        \E::Module('Viewer')->assign('sAction', static::$sController);
        \E::Module('Viewer')->assign('sEvent', static::$sControllerAction);
        \E::Module('Viewer')->assign('aParams', static::$aParams);
    }

    /**
     * Запускает на выполнение контроллер
     * Может запускаться рекурсивно если в одном экшене стоит переадресация на другой
     *
     * @param HttpRequest $oRequest
     * @param HttpResponse $oResponse
     *
     * @throws \RuntimeException
     *
     * @return HttpResponse
     */
    public function execController($oRequest, $oResponse)
    {
        $this->_defineControllerClass();

        // Hook before action
        \HookManager::run('action_before');

        // * Определяем наличие делегата экшена
        $aChain = \E::PluginManager()->GetDelegationChain('action', static::$sControllerClass);
        if (!empty($aChain)) {
            static::$sControllerClass = $aChain[0];
        }

        if (!class_exists(static::$sControllerClass)) {
            throw new \RuntimeException('Cannot load class "' . static::$sControllerClass . '"');
        }
        $this->oController = new static::$sControllerClass(static::$sController);

        if ($this->oController instanceof Controller) {
            $oResponse = $this->_execNewController($this->oController, $oRequest, $oResponse);
        } else {
            $oResponse = $this->_execOldAction($this->oController, $oRequest, $oResponse);
        }

        // Hook after action
        \HookManager::run('action_after');

        return $oResponse;
    }

    /**
     * @param Controller $oController
     * @param HttpRequest $oRequest
     * @param HttpResponse $oResponse
     *
     * @return HttpResponse
     */
    protected function _execNewController($oController, $oRequest, $oResponse)
    {
        $oRequest = $oController->init($oRequest);
        if ($oRequest !== false) {
            $oResponse = $oController->execAction($oRequest, $oResponse);

            $oResponse = $oController->shutdown($oResponse);
        }
        return $oResponse;
    }

    /**
     * @param \alto\engine\generic\Action $oAction
     *
     * @throws \RuntimeException
     */
    protected function _execOldAction($oAction, $oRequest, $oResponse)
    {
        // * Инициализируем экшен
        $xInitResult = $oAction->init($oRequest);
        if (is_object($xInitResult)) {
            $oRequest = $xInitResult;
            $sInitResult = null;
        } else {
            $sInitResult = (string)$xInitResult;
        }

        if ($sInitResult === 'next') {
            $this->execController($oRequest, $oResponse);
        } else {
            // Если инициализация контроллера прошла успешно,
            // то запускаем запрошенный экшен на исполнение.
            if ($sInitResult !== false) {
                $xEventResult = $oAction->execEvent();

                static::$sControllerActionName = $oAction->getCurrentEventName();
                $oAction->eventShutdown();

                if ($xEventResult === 'next') {
                    $this->execController($oRequest, $oResponse);
                }
            }
        }
        return $oResponse;
    }

    /**
     * Tries to define action class in config and plugins
     *
     * @return null|string
     */
    protected function _findControllerClass()
    {
        if (!static::$sController) {
            if (empty($this->aConfigRoute['config']['action_default'])) {
                $sControllerClass = $this->_determineClass('homepage', static::$sControllerAction);
                if ($sControllerClass) {
                    static::$sController = 'homepage';
                }
            } else {
                $aDefaultAction = explode('/', $this->aConfigRoute['config']['action_default']);
                if (count($aDefaultAction) > 1) {
                    $sControllerClass = $this->_determineClass($aDefaultAction[0], $aDefaultAction[1]);
                } else {
                    $sControllerClass = $this->_determineClass($aDefaultAction[0]);
                }
                if ($sControllerClass) {
                    static::$sController = $aDefaultAction[0];
                    static::$sControllerAction = (!empty($aDefaultAction[1]) ? $aDefaultAction[1] : null);
                }
            }
        } else {
            $sControllerClass = $this->_determineClass(static::$sController, static::$sControllerAction);
        }
        return $sControllerClass;
    }

    /**
     * Определяет какой класс соответствует текущему контроллеру
     *
     * @return string
     */
    protected function _defineControllerClass()
    {
        if (isset($this->aDefinedClasses[static::$sController][static::$sControllerAction])) {
            static::$sControllerClass = $this->aDefinedClasses[static::$sController][static::$sControllerAction];
        } else {
            $sControllerClass = $this->_findControllerClass();
            if (!$sControllerClass) {
                //Если не находим нужного класса, то определяем класс экшена-обработчика ошибки
                static::$sController = $this->aConfigRoute['config']['action_not_found'];
                static::$sControllerAction = '404';
                $sControllerClass = $this->_determineClass(static::$sController, static::$sControllerAction);
            }
            if ($sControllerClass) {
                static::$sControllerClass = $sControllerClass;
            } elseif (!$sControllerClass && static::$sController && isset($this->aConfigRoute['controller'][static::$sController])) {
                static::$sControllerClass = $this->aConfigRoute['controller'][static::$sController];
            }

            // Если класс экшена так и не определен, то аварийное завершение
            if (!static::$sControllerClass) {
                die('Controller class does not define');
            }
            $this->aDefinedClasses[static::$sController][static::$sControllerAction] = static::$sControllerClass;
        }

        return static::$sControllerClass;
    }

    /**
     * Determines action class by action (and optionally by event)
     *
     * @param string $sController
     * @param string $sAction
     *
     * @return null|string
     */
    protected function _determineClass($sController, $sAction = null)
    {
        $sControllerClass = null;

        if ($sController) {
            // Сначала ищем экшен по таблице роутинга
            if (isset($this->aConfigRoute['controller'][$sController])) {
                $sControllerClass = $this->aConfigRoute['controller'][$sController];
            }
        }

        // Если в таблице нет и включено автоопределение роутинга, то ищем по путям и файлам
        if (!$sControllerClass && \C::get('router.config.autodefine')) {
            $sControllerClass = Loader::seekActionClass($sController, $sAction);
        }
        return $sControllerClass;
    }

    /**
     * Set AutoDisplay value
     *
     * @param bool $bValue
     */
    public static function setAutoDisplay($bValue)
    {
        self::$bAutoDisplay = (bool)$bValue;
    }

    /**
     * Функция переадресации на другой экшен
     * Если ею завершить евент в экшене то запустится новый экшен
     * Примеры:
     * <pre>
     * return R::Action('error');
     * return R::Action('error', '404');
     * return R::Action('error/404');
     * </pre>
     *
     * @param string $sController    Экшен
     * @param string $sControllerAction    Евент
     * @param array $aParams    Список параметров
     * @return string
     */
    public static function redirect($sController, $sControllerAction = null, $aParams = null)
    {
        $sController = trim($sController, '/');
        // если в $sAction передан путь вида action/event/param..., то обрабатываем его
        if (!$sControllerAction && !$aParams && ($n = substr_count($sController, '/'))) {
            if ($n > 2) {
                list($sController, $sControllerAction, $aParams) = explode('/', $sController, 3);
                if ($aParams) $aParams = explode('/', $aParams);
            } else {
                list($sController, $sControllerAction) = explode('/', $sController);
                $aParams = [];
            }
        }
        static::$sController = static::getInstance()->rewritePath($sController);
        static::$sControllerAction = $sControllerAction;
        if (is_array($aParams)) {
            static::$aParams = $aParams;
        }
        return 'next';
    }

    /**
     * Returns real URL (or path of URL) without rewrites
     *
     * @param bool $bPathOnly
     *
     * @return null|string
     */
    public static function realUrl($bPathOnly = false)
    {
        $sResult = static::$sCurrentFullUrl;
        if ($bPathOnly) {
            $sResult = \F::File_LocalUrl($sResult);
        }
        return $sResult;
    }

    /**
     * Returns current languages
     *
     * @return string
     */
    public static function getLang()
    {
        return static::$sLang;
    }

    /**
     * Sets languages
     *
     * @param string $sLang
     */
    public static function setLang($sLang)
    {
        static::$sLang = $sLang;
    }

    /**
     * Returns current action
     *
     * @return string
     */
    public static function getController()
    {
        return static::$sController;
    }

    /**
     * Returns class name of current action
     *
     * @return string
     */
    public static function getControllerClass()
    {
        return static::$sControllerClass;
    }

    /**
     * Returns current action's event
     *
     * @return string
     */
    public static function getControllerAction()
    {
        return static::$sControllerAction;
    }

    /**
     * Sets event
     *
     * @param string $sControllerAction
     */
    public static function setControllerAction($sControllerAction)
    {
        static::$sControllerAction = $sControllerAction;
    }

    /**
     * Returns current event name
     *
     * @return string
     */
    public static function getControllerActionName()
    {
        return static::$sControllerActionName;
    }

    /**
     * Возвращает параметры(те которые передаются в URL)
     *
     * @return array
     */
    public static function getParams()
    {
        return static::$aParams;
    }

    /**
     * Возвращает параметр по номеру, если его нет то возвращается null
     * Нумерация параметров начинается нуля
     *
     * @param int $iOffset
     * @param string $sDefault
     *
     * @return string
     */
    public static function getParam($iOffset, $sDefault = null)
    {
        $iOffset = (int)$iOffset;
        return isset(static::$aParams[$iOffset]) ? static::$aParams[$iOffset] : $sDefault;
    }

    /**
     * Возвращает текущий обрабатывемый путь контроллера
     *
     * @return string
     */
    public static function getControllerPath()
    {
        if (null === static::$sControllerPath) {
            static::$sControllerPath = static::getController() . '/';
            if (static::getControllerAction()) static::$sControllerPath .= static::getControllerAction() . '/';
            if (static::getParams()) static::$sControllerPath .= implode('/', static::getParams()) . '/';
        }
        return static::$sControllerPath;
    }

    /**
     * Устанавливает значение параметра
     *
     * @param int $iOffset Номер параметра, по идее может быть не только числом
     * @param string $sValue
     */
    public static function setParam($iOffset, $sValue)
    {
        static::$aParams[$iOffset] = $sValue;
    }

    /**
     * Возвращает правильную адресацию (URL) по переданому названию страницы (экшену)
     *
     * @param  string $sController Экшен
     *
     * @return string
     */
    public static function getLink($sController)
    {
        if (empty(static::$aControllerPaths[$sController])) {
            static::$aControllerPaths[$sController] = static::getInstance()->_getLink(trim($sController, '/'));
        }
        return static::$aControllerPaths[$sController];
    }

    /**
     * @param string $sAction
     *
     * @return string
     */
    public function _getLink($sAction)
    {
        // Если пользователь запросил action по умолчанию
        $sPage = (($sAction === 'default') ? $this->aConfigRoute['config']['action_default'] : $sAction);

        // Смотрим, есть ли правило rewrite
        $sPage = static::getInstance()->restorePath($sPage);
        // Маппинг доменов
        if (!empty($this->aConfigRoute['domains']['backward'])) {
            if (isset($this->aConfigRoute['domains']['backward'][$sPage])) {
                $sResult = $this->aConfigRoute['domains']['backward'][$sPage];
                if ($sResult[1] !== '/') {
                    $sResult = '//' . $sResult;
                    if (substr($sResult, -1) !== '/') {
                        $sResult .= '/';
                    }
                }
                // Кешируем
                $this->aConfigRoute['domains']['backward'][$sPage] = $sResult;
                return $sResult;
            }
            $sPattern = \F::strMatch($this->aConfigRoute['domains']['backward_keys'], $sPage, true, $aMatches);
            if ($sPattern) {
                $sResult = '//' . $this->aConfigRoute['domains']['backward'][$sPattern];
                if (!empty($aMatches[1])) {
                    $sResult = str_replace('*', $aMatches[1], $sResult);
                }
                if (substr($sResult, -1) !== '/') {
                    $sResult .= '/';
                }
                // Кешируем
                $this->aConfigRoute['domains']['backward'][$sPage] = $sResult;
                return $sResult;
            }
        }
        return rtrim(\F::File_RootUrl(true), '/') . ($sPage ? "/$sPage/" : '/');
    }

    /**
     * Returns rewrite rule for "from" or for "to" or for both
     *
     * @param string $sFrom
     * @param string $sTo
     *
     * @return array
     */
    protected function _getRewriteRule($sFrom, $sTo)
    {
        if ($this->aConfigRoute['rewrite']) {
            if ($sFrom) {
                if (isset($this->aConfigRoute['rewrite'][$sFrom])) {
                    if ($sTo) {
                        if ($this->aConfigRoute['rewrite'][$sFrom] === $sTo) {
                            return array($sFrom, $sTo);
                        }
                    } else {
                        return array($sFrom, $this->aConfigRoute['rewrite'][$sFrom]);
                    }
                }
            } elseif ($sTo) {
                $sFrom = array_search($sTo, $this->aConfigRoute['rewrite'], true);
                if ($sFrom) {
                    return array($sFrom , $sTo);
                }
            }
        }
        return array($sFrom, $sTo);
    }

    /**
     * Try to find rewrite rule for the path
     * On success returns right controller, otherwise returns given param
     *
     * @param  string $sPath
     *
     * @return string
     */
    public function rewritePath($sPath)
    {
        list ($sFrom, $sTo) = $this->_getRewriteRule($sPath, null);
        return $sTo ? $sTo : $sPath;
    }

    /**
     * Стандартизирует определение внутренних ресурсов.
     *
     * Пытается по переданому экшену найти rewrite rule и
     * вернуть стандартное название ресусрса.
     *
     * @param  string $sPath
     *
     * @return string
     */
    public function restorePath($sPath)
    {
        if (strpos($sPath, '/')) {
            list($sAction, $sOthers) = explode('/', $sPath, 2);
            list ($sFrom, $sTo) = $this->_getRewriteRule(null, $sAction);
            $sResult = ($sFrom ? $sFrom . '/' . $sOthers : $sPath);
        } else {
            list ($sFrom, $sTo) = $this->_getRewriteRule(null, $sPath);
            $sResult = ($sFrom ? $sFrom : $sPath);
        }
        return $sResult;
    }

    /**
     * Выполняет редирект, предварительно завершая работу Engine
     *
     * URL для редиректа:
     *      - полный:           http://ya.ru
     *      - относительный:    /path/to/go/
     *      - виртуальный:      action/event/params/
     *
     * @param string $sLocation    URL для редиректа
     */
    public static function Location($sLocation)
    {
        static::getInstance()->oEngine->shutdown();
        if ($sLocation[0] !== '/') {
            // Проверка на "виртуальный" путь
            $sRelLocation = trim($sLocation, '/');
            if (preg_match('|^[a-z][\w\-]+$|', $sRelLocation)) {
                // задан action
                $sLocation = static::getLink($sRelLocation);
            } elseif (preg_match('|^([a-z][\w\-]+)(\/.+)$|', $sRelLocation)) {
                // задан action/event/...
                list($sAction, $sRest) = explode('/', $sLocation, 2);
                $sLocation = static::getLink($sAction) . '/' . $sRest;
            }
        }
        \F::HttpLocation($sLocation);
    }

    /**
     * @param   array $aData
     * @param   string $sPart  One of values: 'url', 'link', 'root', 'path', 'action', 'event', 'params'
     * @return  string
     */
    protected function _getUrlPart($aData, $sPart)
    {
        $sResult = '';
        if ($sPart === 'url') {
            $sResult = $this->_getUrlPart($aData, 'link');
            if (isset($aData['query'])) {
                $sResult .= '?' . $aData['query'];
            }
            if (isset($aData['fragment'])) {
                $sResult .= '#' . $aData['fragment'];
            }
        } elseif ($sPart === 'link') {
            if (isset($aData['root'])) {
                $sResult = trim($aData['root'], '/');
            }
            if (isset($aData['action'])) {
                $sResult .= $this->_getUrlPart($aData, 'path');
            }
        } elseif ($sPart === 'path') {
            if (isset($aData['path'])) {
                $sResult = $aData['path'];
            } else {
                if (isset($aData['action'])) {
                    $sResult = '/' . $aData['action'];
                }
                if (isset($aData['event'])) {
                    $sResult .= '/' . $aData['event'];
                }
                if (isset($aData['params'])) {
                    $sResult .= '/' . $aData['params'];
                }
            }
        } elseif (isset($aData[$sPart])) {
            $sResult = $aData[$sPart];
        }
        return $sResult;
    }

    /**
     * @param string|null $sPart
     *
     * @return array|string
     */
    public function getCurrentUrlInfo($sPart = null)
    {
        if (!$sPart) {
            return $this->aCurrentUrl;
        }
        return $this->_getUrlPart($this->aCurrentUrl, $sPart);
    }

    /**
     * Данные о текущем URL
     *
     * @param   string|null $sPart
     * @return  array|string
     */
    public static function url($sPart = null)
    {
        return static::getInstance()->getCurrentUrlInfo($sPart);
    }

    /**
     * Возврат к предыдущему URL
     * В отличие от GotoBack() анализирует переданные POST-параметры
     *
     * @param   bool $bSecurity  - защита от CSRF
     */
    public static function returnBack($bSecurity = null)
    {
        if (!$bSecurity || \E::Module('Security')->validateSendForm(false)) {
            if (($sUrl = \F::GetPost('return_url')) || ($sUrl = \F::GetPost('return-path'))) {
                static::Location($sUrl);
            }
        }
    }

    /**
     * Возвращает маску формирования URL топика
     *
     * @param  bool     $bEmptyIfWrong
     * @return string
     */
    public static function getTopicUrlMask($bEmptyIfWrong = true) {

        if (null === static::$sTopicUrlMask) {
            $sUrlMask = \C::get('module.topic.url');
            if ($sUrlMask) {
                // WP compatible
                $sUrlMask = str_replace('%post_id%', '%topic_id%', $sUrlMask);
                $sUrlMask = str_replace('%postname%', '%topic_url%', $sUrlMask);
                $sUrlMask = str_replace('%author%', '%login%', $sUrlMask);

                // NuceURL compatible
                $sUrlMask = str_replace('%id%', '%topic_id%', $sUrlMask);
                $sUrlMask = str_replace('%blog%', '%blog_url%', $sUrlMask);
                $sUrlMask = str_replace('%title%', '%topic_url%', $sUrlMask);

                // В маске может быть только одно входение '%topic_id%' и '%topic_url%'
                if (substr_count($sUrlMask, '%topic_id%') > 1) {
                    $aParts = explode('%topic_id%', $sUrlMask, 2);
                    $sUrlMask = $aParts[0] . '%topic_id%' . str_replace('%topic_id%', '', $aParts[1]);
                }
                if (substr_count($sUrlMask, '%topic_url%') > 1) {
                    $aParts = explode('%topic_url%', $sUrlMask, 2);
                    $sUrlMask = $aParts[0] . '%topic_url%' . str_replace('%topic_url%', '', $aParts[1]);
                }
                $sUrlMask = preg_replace('#\/+#', '/', $sUrlMask);
            }
            static::$sTopicUrlMask = $sUrlMask;
        } else {
            $sUrlMask = static::$sTopicUrlMask;
        }

        if ($bEmptyIfWrong && (strpos($sUrlMask, '%topic_id%') === false) && (strpos($sUrlMask, '%topic_url%') === false)) {
            // В маске обязательно должны быть либо '%topic_id%', либо '%topic_url%'
            $sUrlMask = '';
        }
        return $sUrlMask;
    }

    /**
     * Returns pattern for topics' URL
     *
     * @return string
     */
    public static function getTopicUrlPattern() {

        $sUrlPattern = static::GetTopicUrlMask();
        if ($sUrlPattern) {
            $sUrlPattern = preg_quote($sUrlPattern);
            $aReplace = array(
                '%year%'       => '\d{4}',
                '%month%'      => '\d{2}',
                '%day%'        => '\d{2}',
                '%hour%'       => '\d{2}',
                '%minute%'     => '\d{2}',
                '%second%'     => '\d{2}',
                '%login%'      => '[\w_\-]+',
                '%blog_url%'   => '[\w_\-]+',
                '%topic_type%' => '[\w_\-]+',
                '%topic_id%'   => '(\d+)',
                '%topic_url%'  => '([\w\-]+)',
            );
            // brackets in the pattern may be only once
            if (strpos($sUrlPattern, '%topic_id%') !== false && strpos($sUrlPattern, '%topic_url%') !== false) {
                // if both of masks are present then %topic_id% is main
                $aReplace['%topic_url%'] = '[\w\-]+';
            }
            // Если последним символом в шаблоне идет слеш, то надо его сделать опциональным
            if (substr($sUrlPattern, -1) === '/') {
                $sUrlPattern .= '?';
            }
            $sUrlPattern = '#^' . strtr($sUrlPattern, $aReplace) . '$#i';
        }
        return $sUrlPattern;
    }

    /**
     * Возвращает маску формирования URL профиля пользователя
     *
     * @param  bool     $bEmptyIfWrong
     * @return string
     */
    public static function getUserUrlMask($bEmptyIfWrong = true) {

        $sUrlMask = ''; //C::Get('module.user.profile_url');
        if ($bEmptyIfWrong && (strpos($sUrlMask, '%user_id%') === false) && (strpos($sUrlMask, '%login%') === false)) {
            // В маске обязательно должны быть либо '%user_id%', либо '%login%'
            $sUrlMask = '';
        }
        return $sUrlMask;
    }

    /**
     * Returns pattern for user's profile URL
     *
     * @return string
     */
    public static function getUserUrlPattern()
    {
        $sUrlPattern = static::getUserUrlMask();
        if ($sUrlPattern) {
            $sUrlPattern = preg_quote($sUrlPattern);
            $aReplace = array(
                '%login%' => '([\w_\-]+)',
                '%user_id%' => '(\d+)',
            );
            // Если последним символом в шаблоне идет слеш, то надо его сделать опциональным
            if (substr($sUrlPattern, -1) === '/') {
                $sUrlPattern .= '?';
            }
            $sUrlPattern = '#^' . strtr($sUrlPattern, $aReplace) . '$#i';
        }
        return $sUrlPattern;
    }

    /**
     * Alias of CmpControllerPath()
     * @param      $aPaths
     * @param null $bDefault
     *
     * @return string
     */
    public static function compareWithLocalPath($aPaths, $bDefault = null)
    {
        return static::cmpControllerPath($aPaths, $bDefault);
    }
    
    /**
     * Compare each item of array with controller path
     *
     * @see getControllerPath
     *
     * @param string|array $aPaths   - array of compared paths
     * @param bool         $bDefault - default value if $aPaths is empty
     *
     * @return string
     */
    public static function cmpControllerPath($aPaths, $bDefault = null)
    {
        if ($aPaths) {
            $sControllerPath = static::getControllerPath();
            $aPaths = \F::Val2Array($aPaths);
            if ($aPaths) {
                foreach($aPaths as $nKey => $sPath) {
                    if ($sPath === '*') {
                        $aPaths[$nKey] = \C::get('router.config.action_default') . '/*';
                    } elseif($sPath === '/') {
                        $aPaths[$nKey] = \C::get('router.config.action_default') . '/';
                    } elseif (!in_array(substr($sPath, -1), ['/', '*'], true)) {
                        $aPaths[$nKey] = $sPath . '/*';
                    }
                }
                return \F::File_InPath($sControllerPath, $aPaths);
            }
        }
        return $bDefault;
    }

    /**
     * Compare each item of array with request path
     * 
     * @param string|array $aPaths
     * @param bool         $bDefault
     *
     * @return string
     */
    public static function CmpRequestPath($aPaths, $bDefault = null)
    {
        if ($aPaths) {
            $sComparePath = trim(static::url('path'), '/');
            $aPaths = \F::Val2Array($aPaths);
            if ($aPaths) {
                foreach($aPaths as $nKey => $sPath) {
                    if ($sPath === '*') {
                        return $sPath;
                    }
                    if(strpos($sPath, '*') === false && trim($sPath, '/') === $sComparePath) {
                        return $sPath;
                    }
                }
                return \F::File_InPath($sComparePath, $aPaths);
            }
        }
        return $bDefault;
    }

    /**
     * Alias of AllowControllerPath()
     *
     * @param $aAllowPaths
     * @param $aDisallowPaths
     *
     * @return bool
     */
    public static function allowLocalPath($aAllowPaths, $aDisallowPaths)
    {
        return static::allowControllerPath($aAllowPaths, $aDisallowPaths);
    }
    
    /**
     * Check the local path by allow/disallow rules
     *
     * @param string|array|null $aAllowPaths
     * @param string|array|null $aDisallowPaths
     *
     * @return bool
     */
    public static function allowControllerPath($aAllowPaths, $aDisallowPaths)
    {
        if (static::cmpControllerPath($aAllowPaths, true) && !static::cmpControllerPath($aDisallowPaths, false)) {
            return true;
        }
        return false;
    }

    /**
     * Check the current action and event by rules
     *
     * @param $aControllers
     *
     * @return bool
     */
    public static function allowControllers($aControllers)
    {
        $bResult = false;
        if ($aControllers) {
            $aControllers = \F::Val2Array($aControllers);

            $sCurrentController = strtolower(static::getController());
            $sCurrentAction = strtolower(static::getControllerAction());
            $sCurrentActionName = strtolower(static::getControllerActionName());

            foreach ($aControllers as $sController => $aControllerActions) {
                // приводим к виду action=>array(events)
                if (is_int($sController) && !is_array($aControllerActions)) {
                    $sController = (string)$aControllerActions;
                    if (strpos($sController, '/')) {
                        list($sController, $sEvent) = explode('/', $sController);
                        $aControllerActions = array($sEvent);
                    } else {
                        $aControllerActions = [];
                    }
                }
                if ($sController === $sCurrentController) {
                    if (!$aControllerActions) {
                        return true;
                    }
                    $aControllerActions = (array)$aControllerActions;
                    foreach ($aControllerActions as $sActionPreg) {
                        if (($sCurrentActionName && $sActionPreg === $sCurrentActionName) || $sActionPreg === $sCurrentAction) {
                            // * Это название event`a
                            return true;
                        }
                        if ((strpos($sActionPreg, '[') === 0) && (substr($sActionPreg, -1) === ']') && preg_match(substr($sActionPreg, 1, -1), $sCurrentAction)) {
                            // * Это регулярное выражение
                            return true;
                        }
                    }
                }
            }
        }

        return $bResult;
    }

}

// EOF