<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

namespace alto\engine\generic;

/**
 * Абстрактный класс экшена.
 *
 * От этого класса наследуются все экшены в движке.
 * Предоставляет базовые метода для работы с параметрами и шаблоном при запросе страницы в браузере.
 *
 * @package engine
 * @since   1.0
 */
abstract class Action extends Component
{
    const MATCH_TYPE_STR = 0;
    const MATCH_TYPE_REG = 1;

    /**
     * Список зарегистрированных евентов
     *
     * @var array
     */
    protected $aRegisterEvent = [];

    /**
     * Index of selected event for execution
     *
     * @var int
     */
    protected $iRegisterEventIndex;

    /**
     * Список параметров из URL
     * <pre>/action/event/param0/param1/../paramN/</pre>
     *
     * @var array
     */
    protected $aParams = [];

    /**
     * Список совпадений по регулярному выражению для евента
     *
     * @var array
     */
    protected $aParamsEventMatch = ['event' => [], 'params' => []];

    /**
     * Объект ядра
     *
     * @var Engine|null
     */
    protected $oEngine = null;

    /**
     * Шаблон экшена
     *
     * @see setTemplate
     * @see setTemplateAction
     *
     * @var string|null
     */
    protected $sActionTemplate = null;

    /**
     * Дефолтный евент
     *
     * @see setDefaultEvent
     *
     * @var string|null
     */
    protected $sDefaultEvent = 'index';

    /**
     * Текущий евент
     *
     * @var string|null
     */
    protected $sCurrentEvent = null;

    /**
     * Current event name
     * Позволяет именовать экшены на основе регулярных выражений
     *
     * @var string|null
     */
    protected $sCurrentEventName = null;

    /**
     * @var array|bool
     */
    protected $aCurrentEventHandler = null;

    /**
     * Текущий экшен
     *
     * @var null|string
     */
    protected $sCurrentAction = null;

    /**
     * Current request method
     *
     * @var string
     */
    protected $sRequestMethod = null;

    /**
     * Request data - POST, GET and other params
     *
     * @var array
     */
    protected $aRequestData = [];

    protected static $bPost = null;

    /**
     * Конструктор
     *
     * @param string $sAction Название экшена
     */
    public function __construct($sAction = null)
    {
        parent::__construct();
        $this->_prepareRequestData();

        //Engine::getInstance();
        $this->sCurrentAction = $sAction;
        $this->aParams = \R::getParams();
        $this->registerEvent();

        //Config::ResetLevel(Config::LEVEL_ACTION);
        // Get current config level
        $iConfigLevel = \C::getLevel();
        // load action's config if exists
        if ($sFile = \F::File_Exists('/config/actions/' . $sAction . '.php', \C::get('path.root.seek'))) {
            // Дополняем текущий конфиг конфигом экшена
            if ($aConfig = \F::File_IncludeFile($sFile, true, true)) {
                // Текущий уровень конфига может быть как меньше LEVEL_ACTION,
                // так и больше. Нужно обновить все уровни
                if ($iConfigLevel <= \C::LEVEL_ACTION) {
                    $iMinLevel = $iConfigLevel;
                    $iMaxLevel = \C::LEVEL_ACTION;
                } else {
                    $iMinLevel = \C::LEVEL_ACTION;
                    $iMaxLevel = $iConfigLevel;
                }
                for ($iLevel = $iMinLevel; $iLevel <= $iMaxLevel; $iLevel++) {
                    if ($iLevel > $iConfigLevel) {
                        \C::resetLevel(Config::LEVEL_ACTION);
                    }
                    \C::load($aConfig, $iLevel, $sFile);
                }
            }
        } elseif ($iConfigLevel < \C::LEVEL_ACTION) {
            \C::resetLevel(\C::LEVEL_ACTION);
        }
    }

    /**
     * @param string $sType
     * @param string $sKey
     * @param mixed  $xValue
     */
    protected function _setRequestData($sType, $sKey, $xValue = null)
    {
        if (is_array($sKey) && is_null($xValue)) {
            foreach((array)$sKey as $sDataKey => $xDataValue) {
                $this->aRequestData[$sType][strtolower($sDataKey)] = $xDataValue;
            }
        } else {
            $this->aRequestData[$sType][strtolower($sKey)] = $xValue;
        }
    }

    /**
     * @param string $sBodyData
     *
     * @return array
     */
    protected function _prepareRequestBody($sBodyData)
    {
        $aResult = [];
        if ($sBodyData) {
            if ($this->_getRequestData('HEADER', 'Content-Type') === 'application/json') {
                $aResult = json_decode($sBodyData, true);
            } else {
                $aExplodedData = explode('&', $sBodyData);
                foreach ($aExplodedData as $aPair) {
                    $item = explode('=', $aPair);
                    if (count($item) == 2) {
                        $aResult[urldecode($item[0])] = urldecode($item[1]);
                    }
                }
            }
        }
        return $aResult;
    }

    /**
     * Preparation of request data
     */
    protected function _prepareRequestData()
    {
        $this->sRequestMethod = strtoupper(\F::GetRequestMethod());
        $this->_setRequestData('HEADER', \F::GetRequestHeaders());

        if (isset($_GET) && is_array($_GET)) {
            $this->_setRequestData('GET', $_GET);
        }

        if (isset($_POST) && is_array($_POST)) {
            $this->_setRequestData('POST', $_POST);
        }

        if (isset($_FILES) && is_array($_FILES)) {
            $this->_setRequestData('FILES', $_FILES);
        }

        $sBodyData = \F::GetRequestBody();
        if ($sBodyData && $aExplodedData = $this->_prepareRequestBody($sBodyData)) {
            foreach ($aExplodedData as $sKey => $xVal) {
                $this->_setRequestData('BODY', $sKey, $xVal);
            }
        }
    }

    /**
     * Return current request method
     *
     * @return string
     */
    protected function _getRequestMethod()
    {
        return $this->sRequestMethod;
    }

    /**
     * Return required request data
     *
     * @param string      $sType
     * @param string|null $sName
     *
     * @return mixed
     */
    protected function _getRequestData($sType, $sName = null)
    {
        $sType = strtoupper($sType);
        if (in_array($sType, ['HEADER', 'GET', 'POST', 'BODY', 'FILES'])) {
            if (is_null($sName) && isset($this->aRequestData[$sType])) {
                return $this->aRequestData[$sType];
            } elseif (!is_null($sName) && isset($this->aRequestData[$sType][strtolower($sName)])) {
                return $this->aRequestData[$sType][strtolower($sName)];
            } else {
                return null;
            }
        }
        // $sType is request method
        if ($this->_getRequestMethod() === $sType) {
            if (is_null($sName)) {
                return $this->aRequestData['BODY'];
            } elseif (isset($this->aRequestData['BODY'][strtolower($sName)])) {
                return $this->aRequestData['BODY'][strtolower($sName)];
            } else {
                return null;
            }
        }
        return null;
    }

    /**
     * Add event handler
     *
     * @param array $aArgs
     * @param int   $iType
     *
     * @throws \RuntimeException
     */
    protected function _addEventHandler($aArgs, $iType)
    {
        $iCountArgs = count($aArgs);
        if ($iCountArgs < 2) {
            throw new \RuntimeException('Incorrect number of arguments when adding events');
        }
        $aEvent = [];
        /**
         * Последний параметр может быть массивом - содержать имя метода и имя евента(именованный евент)
         * Если указан только метод, то имя будет равным названию метода
         */
        $aNames = (array)$aArgs[--$iCountArgs];
        $aEvent['method'] = $aNames[0];
        if (isset($aNames[1])) {
            $aEvent['name'] = $aNames[1];
        } else {
            $aEvent['name'] = $aEvent['method'];
        }
        if (!$this->_eventExists($aEvent['method'])) {
            throw new \RuntimeException('Method of the event not found: ' . $aEvent['method']);
        }
        $aEvent['type'] = $iType;
        $aEvent['uri_event'] = $aArgs[0];
        $aEvent['uri_params'] = [];
        for ($i = 1; $i < $iCountArgs; $i++) {
            $aEvent['uri_params'][] = $aArgs[$i];
        }
        $this->aRegisterEvent[] = $aEvent;
    }

    /**
     * Return event handler
     *
     * @return array|bool
     */
    protected function _getEventHandler()
    {
        foreach ($this->aRegisterEvent as $iEventKey => $aEvent) {
            $bFound = false;
            if ($aEvent['type'] == self::MATCH_TYPE_STR && $aEvent['uri_event'] == $this->sCurrentEvent) {
                $this->aParamsEventMatch['key'] = $iEventKey;
                $this->aParamsEventMatch['event'] = [$this->sCurrentEvent, $this->sCurrentEvent];
                $this->aParamsEventMatch['params'] = [];
                $bFound = true;
                foreach ($aEvent['uri_params'] as $iKey => $sUriParam) {
                    $sParam = $this->getParam($iKey, '');
                    if ($sUriParam == $sParam) {
                        $this->aParamsEventMatch['params'][$iKey] = [$sParam, $sParam];
                    } else {
                        $bFound = false;
                        break;
                    }
                }
            } elseif ($aEvent['type'] == self::MATCH_TYPE_REG && preg_match($aEvent['uri_event'], $this->sCurrentEvent, $aMatch)) {
                $this->aParamsEventMatch['key'] = $iEventKey;
                $this->aParamsEventMatch['event'] = $aMatch;
                $this->aParamsEventMatch['params'] = [];
                $bFound = true;
                foreach ($aEvent['uri_params'] as $iKey => $sUriParam) {
                    if (preg_match($sUriParam, $this->getParam($iKey, ''), $aMatch)) {
                        $this->aParamsEventMatch['params'][$iKey] = $aMatch;
                    } else {
                        $bFound = false;
                        break;
                    }
                }
            }
            if ($bFound) {
                return $aEvent;
            }
        }
        return false;
    }

    /**
     * Добавляет евент в экшен
     * По сути является оберткой для addEventPreg(), оставлен для простоты и совместимости с прошлыми версиями ядра
     *
     * @see addEventPreg
     *
     * @param string $sEventName     Название евента
     * @param string|array $sEventFunction Какой метод ему соответствует
     */
    protected function addEvent($sEventName, $sEventFunction)
    {
        $this->_addEventHandler(func_get_args(), self::MATCH_TYPE_STR);
    }

    /**
     * Добавляет евент в экшен, используя регулярное выражение для евента и параметров
     *
     */
    protected function addEventPreg()
    {
        $this->_addEventHandler(func_get_args(), self::MATCH_TYPE_REG);
    }

    /**
     * @param string $sEvent
     *
     * @return bool
     */
    protected function _eventExists($sEvent)
    {
        return method_exists($this, $sEvent);
    }

    /**
     * Запускает евент на выполнение
     * Если текущий евент не определен то  запускается тот которые определен по умолчанию (default event)
     *
     * @return mixed
     */
    public function execEvent()
    {
        if ($this->getDefaultEvent() === 'index' && method_exists($this, 'EventIndex')) {
            $this->addEvent('index', 'EventIndex');
        }
        $this->sCurrentEvent = \R::getControllerAction();
        if ($this->sCurrentEvent == null) {
            $this->sCurrentEvent = $this->getDefaultEvent();
            \R::setControllerAction($this->sCurrentEvent);
        }
        $this->aCurrentEventHandler = $this->_getEventHandler();
        if ($this->aCurrentEventHandler !== false) {
            $this->sCurrentEventName = $this->aCurrentEventHandler['name'];

            if ($this->access(\R::getControllerAction())) {
                $sMethod = $this->aCurrentEventHandler['method'];
                $sHook = 'action_event_' . strtolower($this->sCurrentAction);

                \HookManager::run($sHook . '_before', ['event' => $this->sCurrentEvent, 'params' => $this->getParams()]);
                $xResult = $this->$sMethod();
                \HookManager::run($sHook . '_after', ['event' => $this->sCurrentEvent, 'params' => $this->getParams()]);

                return $xResult;
            } else {
                return $this->accessDenied(\R::getControllerAction());
                //return null;
            }
        }

        return $this->eventNotFound();
    }

    /**
     * Устанавливает евент по умолчанию
     *
     * @param string $sEvent Имя евента
     */
    public function setDefaultEvent($sEvent)
    {
        $this->sDefaultEvent = $sEvent;
    }

    /**
     * Получает евент по умолчанию
     *
     * @return string
     */
    public function getDefaultEvent()
    {
        return $this->sDefaultEvent;
    }

    /**
     * Возвращает элементы совпадения по регулярному выражению для евента
     *
     * @param int|null $iItem    Номер совпадения
     *
     * @return string|null
     */
    protected function getEventMatch($iItem = null)
    {
        if ($iItem) {
            if (isset($this->aParamsEventMatch['event'][$iItem])) {
                return $this->aParamsEventMatch['event'][$iItem];
            } else {
                return null;
            }
        } else {
            return $this->aParamsEventMatch['event'];
        }
    }

    /**
     * Возвращает элементы совпадения по регулярному выражению для параметров евента
     *
     * @param int      $iParamNum    Номер параметра, начинается с нуля
     * @param int|null $iItem        Номер совпадения, начинается с нуля
     *
     * @return string|null
     */
    protected function getParamEventMatch($iParamNum, $iItem = null)
    {
        if (!is_null($iItem)) {
            if (isset($this->aParamsEventMatch['params'][$iParamNum][$iItem])) {
                return $this->aParamsEventMatch['params'][$iParamNum][$iItem];
            } else {
                return null;
            }
        } else {
            if (isset($this->aParamsEventMatch['event'][$iParamNum])) {
                return $this->aParamsEventMatch['event'][$iParamNum];
            } else {
                return null;
            }
        }
    }

    /**
     * Получает параметр из URL по его номеру, если его нет то null
     *
     * @param   int    $iOffset    Номер параметра, начинается с нуля
     * @param   string $sDefault   Значение по умолчанию
     *
     * @return  string
     */
    public function getParam($iOffset, $sDefault = null)
    {
        $iOffset = (int)$iOffset;
        return isset($this->aParams[$iOffset]) ? $this->aParams[$iOffset] : $sDefault;
    }

    /**
     * Получает последний парамет из URL
     *
     * @param string $sDefault
     *
     * @return string|null
     */
    protected function getLastParam($sDefault = null)
    {
        $nNumParams = count($this->getParams());
        if ($nNumParams > 0) {
            $iOffset = $nNumParams - 1;
            return $this->getParam($iOffset, $sDefault);
        }
        return null;
    }

    /**
     * Получает список параметров из URL
     *
     * @return array
     */
    public function getParams()
    {
        return $this->aParams;
    }

    /**
     * Установить значение параметра(эмуляция параметра в URL).
     * После установки занова считывает параметры из роутера - для корректной работы
     *
     * @param int    $iOffset Номер параметра, но по идеи может быть не только числом
     * @param string $sValue
     */
    public function setParam($iOffset, $sValue)
    {
        \R::setParam($iOffset, $sValue);
        $this->aParams = \R::getParams();
    }

    /**
     * Устанавливает какой шаблон выводить
     *
     * @param string $sTemplate Путь до шаблона относительно общего каталога шаблонов
     */
    protected function setTemplate($sTemplate)
    {
        $this->sActionTemplate = $sTemplate;
    }

    /**
     * Устанавливает какой шаблон выводить
     *
     * @param string $sTemplate Путь до шаблона относительно каталога шаблонов экшена
     */
    protected function setTemplateAction($sTemplate)
    {
        if (substr($sTemplate, -4) !== '.tpl') {
            $sTemplate .= '.tpl';
        }
        $sActionTemplatePath = $sTemplate;

        if (!\F::File_IsLocalDir($sActionTemplatePath)) {
            // If not absolute path then defines real path of template
            $aDelegates = \E::PluginManager()->GetDelegationChain('action', $this->getActionClass());
            foreach ($aDelegates as $sAction) {
                if (preg_match('/^(Plugin([\w]+)_)?Action([\w]+)$/i', $sAction, $aMatches)) {
                    // for LS-compatibility
                    $sActionNameOriginal = $aMatches[3];
                    // New-style action frontend
                    $sActionName = strtolower($sActionNameOriginal);
                    $sTemplatePath = \E::PluginManager()->getDelegate('template', 'actions/' . $sActionName . '/action.' . $sActionName . '.' . $sTemplate);
                    $sActionTemplatePath = $sTemplatePath;
                    if (!empty($aMatches[1])) {
                        $aPluginTemplateDirs = [\PluginManager::getTemplateDir($sAction)];
                        if (basename($aPluginTemplateDirs[0]) !== 'default') {
                            $aPluginTemplateDirs[] = dirname($aPluginTemplateDirs[0]) . '/default/';
                        }

                        if ($sTemplatePath = \F::File_Exists('tpls/' . $sTemplatePath, $aPluginTemplateDirs)) {
                            $sActionTemplatePath = $sTemplatePath;
                            break;
                        }
                        if ($sTemplatePath = \F::File_Exists($sTemplatePath, $aPluginTemplateDirs)) {
                            $sActionTemplatePath = $sTemplatePath;
                            break;
                        }

                    }
                }
            }
        }

        $this->sActionTemplate = $sActionTemplatePath;
    }

    /**
     * Получить шаблон
     * Если шаблон не определен то возвращаем дефолтный шаблон евента: action/{Action}.{event}.tpl
     *
     * @return string
     */
    public function getTemplate()
    {
        if (is_null($this->sActionTemplate)) {
            $this->setTemplateAction($this->sCurrentEvent);
        }
        return $this->sActionTemplate;
    }

    /**
     * @see Router::getControllerClass
     *
     * @return string
     */
    public function getActionClass()
    {
        return \R::getControllerClass();
    }

    /**
     * Возвращает имя евента
     *
     * @return null|string
     */
    public function getCurrentEventName()
    {
        return $this->sCurrentEventName;
    }

    /**
     * Вызывается в том случаи если не найден евент который запросили через URL
     * По дефолту происходит перекидывание на страницу ошибки, это можно переопределить в наследнике
     *
     * @see Router::redirect
     *
     * @return string
     */
    public function eventNotFound()
    {
        return \R::redirect('error', '404');
    }

    /**
     * Выполняется при завершение экшена, после вызова основного евента
     *
     */
    public function eventShutdown()
    {

    }

    /**
     * Метод инициализации экшена
     * @return bool|string
     */
    public function init()
    {
        return null;
    }

    /**
     * Метод регистрации евентов.
     * В нём необходимо вызывать метод addEvent($sEventName, $sEventFunction)
     *
     */
    protected function registerEvent()
    {

    }

    /**
     * Были ли ли переданы POST-параметры (или конкретный POST-параметр)
     *
     * @param   string|null $sName
     *
     * @return  bool
     */
    protected function isPost($sName = null)
    {
        $aPostData = $this->_getRequestData('POST');
        if (is_null(self::$bPost)) {
            if (\E::Module('Security')->validateSendForm(false)
                && ($this->_getRequestMethod() === 'POST')
                && !is_null($aPostData)
            ) {
                self::$bPost = true;
            } else {
                self::$bPost = false;
            }
        }
        if (self::$bPost) {
            if ($sName) {
                return array_key_exists(strtolower($sName), $aPostData);
            } else {
                return is_array($aPostData);
            }
        }
        return false;
    }

    /**
     * Получает POST-параметры с валидацией формы
     *
     * @param string $sName
     * @param string $sDefault
     *
     * @return  mixed
     */
    protected function getPost($sName = null, $sDefault = null)
    {
        if ($this->isPost($sName)) {
            $aPostData = $this->_getRequestData('POST');
            if ($sName) {
                $sName = strtolower($sName);
                return isset($aPostData[$sName]) ? $aPostData[$sName] : $sDefault;
            } else {
                return $aPostData;
            }
        }
        return null;
    }

    /**
     * @param string|null $sName
     *
     * @return array
     */
    protected function getUploadedFileData($sName = null)
    {
        $aFileData = [];
        $aFiles = $this->_getRequestData('FILES');
        if (!empty($aFiles)) {
            if (null === $sName) {
                $aFileData = reset($aFiles);
            } elseif (!empty($aFiles) && is_array($aFiles)) {
                foreach($aFiles as $sKey => $aData) {
                    if (strtolower($sKey) === strtolower($sName)) {
                        $aFileData = $aData;
                        break;
                    }
                }
            }
        }
        return $aFileData;
    }

    /**
     * Returns information about the uploaded file with form validation
     * If a field name is omitted it returns the first of uploaded files
     *
     * @param   string|null $sName
     *
     * @return  array|bool
     */
    protected function getUploadedFile($sName = null)
    {
        if (\E::Module('Security')->validateSendForm(false)) {
            $aFileData = $this->getUploadedFileData($sName);
            if ($aFileData && isset($aFileData['tmp_name']) && is_uploaded_file($aFileData['tmp_name'])) {
                return $aFileData;
            }
        }

        return false;
    }

    /**
     * @param string|null $sName
     *
     * @return string
     */
    protected function getUploadedFileError($sName = null)
    {
        $sResult = null;
        $aFileData = $this->getUploadedFileData($sName);
        if ($aFileData && !empty($aFileData['error'])) {
            $iErrorCode = $aFileData['error'];
            $sError = \E::Module('Lang')->get('error_upload_file_' . $iErrorCode);
            if ($sError) {
                $sResult = $sError;
            } else {
                $sResult = \E::Module('Lang')->get('error_upload_file_unknown');
            }
        }
        return $sResult;
    }


    /**
     * Метод проверки прав доступа пользователя к конкретному ивенту
     * @param string $sEvent Наименование ивента
     *
     * @return bool
     */
    public function access($sEvent)
    {
//        $sAccessMethodName = 'Access' . $sEvent;
//
//        if (method_exists($this, 'Access' . $sEvent)) {
//            return call_user_func_array(array($this, $sAccessMethodName), []);
//        }

        return true;
    }


    /**
     * Метод запрета доступа к ивенту
     * @param string $sEvent Наименование ивента
     *
     * @return bool
     */
    public function accessDenied($sEvent = null)
    {
        if (!\F::AjaxRequest()) {
            return $this->eventNotFound();
        }
        echo 'Access denied';

        return null;
    }

}

// EOF