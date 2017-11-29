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

F::IncludeFile(__DIR__ . '/LangArray.class.php');

/**
 * Модуль поддержки языковых файлов
 *
 * @package engine.modules
 * @since   1.0
 */
class ModuleLang extends Module {

    const LANG_PATTERN = '%%lang%%';

    /**
     * Текущий язык ресурса
     *
     * @var string
     */
    protected $sCurrentLang;

    /**
     * Язык ресурса, используемый по умолчанию
     *
     * @var string
     */
    protected $sDefaultLang;

    /**
     * Путь к языковым файлам
     *
     * @var string
     */
    protected $aLangPaths;

    /**
     * Список языковых текстовок
     *
     * @var array
     */
    protected $aLangMsg = [];

    /**
     * Список текстовок для JS
     *
     * @var array
     */
    protected $aLangMsgJs = [];

    protected $bDeleteUndefinedVars;

    /**
     * Инициализация модуля
     *
     */
    public function init() 
    {
        \HookManager::run('lang_init_start');

        $this->sDefaultLang = \C::get('lang.default');
        $this->aLangPaths =  \F::File_NormPath(C::get('lang.paths'));
        $this->bDeleteUndefinedVars = \C::get('module.lang.delete_undefined');

        // Allowed languages
        $aLangsAllow = (array)C::get('lang.allow');

        // Проверку на языки делаем, только если сайт мультиязычный
        if (C::get('lang.multilang')) {
            // Время хранение языка в куках
            $iSavePeriod =  \F::ToSeconds(C::get('lang.save'));
            $sLangKey = (is_string(C::get('lang.in_get')) ? \C::get('lang.in_get') : 'lang');

            // Получаем язык, если он был задан в URL
            $this->sCurrentLang = R::GetLang();

            // Проверка куки, если требуется
            if (!$this->sCurrentLang && $iSavePeriod) {
                $sLang = (string)E::Module('Session')->getCookie($sLangKey);
                if ($sLang) {
                    $this->sCurrentLang = $sLang;
                }
            }
            if (!$this->sCurrentLang) {
                $this->sCurrentLang = \C::get('lang.current');
            }
        } else {
            $this->sCurrentLang = \C::get('lang.current');
            $iSavePeriod = 0;
            $sLangKey = null;
        }
        // Current languages must be in allowed languages
        if (!in_array($this->sCurrentLang, $aLangsAllow)) {
            $this->sCurrentLang = reset($aLangsAllow);
        }

        // Проверяем на случай старого обозначения языков
        $this->sDefaultLang = $this->_checkLang($this->sDefaultLang);
        $this->sCurrentLang = $this->_checkLang($this->sCurrentLang);

        if ($this->sCurrentLang && \C::get('lang.multilang') && $iSavePeriod) {
            // Пишем в куки, если требуется
            \E::Module('Session')->setCookie($sLangKey, $this->sCurrentLang, $iSavePeriod);
        }

        $this->initLang();
    }

    /**
     * @param string $sLang
     *
     * @return int|string
     */
    protected function _checkLang($sLang)
    {
        if (!UserLocale::getLocale($sLang)) {
            $aLangs = (array)UserLocale::getAvailableLanguages();
            if (!isset($aLangs[$sLang])) {
                // Возможно в $sLang полное название языка, поэтому проверяем
                foreach($aLangs as $sLangCode=>$aLangInfo) {
                    if (strtolower($sLang) === strtolower($aLangInfo['name'])) {
                        return $sLangCode;
                    }
                }
            }
        }
        return $sLang;
    }

    /**
     * Инициализирует языковой файл
     *
     * @param null $sLang
     */
    protected function initLang($sLang = null)
    {
        if (!$sLang) {
            $sLang = $this->sCurrentLang;
        }

        UserLocale::setLocale(
            \C::get('lang.current'),
            ['locale' => \C::get('i18n.locale'), 'timezone' => \C::get('i18n.timezone')]
        );

        $this->aLangMsg[$sLang] = [];

        // * Если используется кеширование через memcaсhed, то сохраняем данные языкового файла в кеш
        if (C::get('sys.cache.type') === 'memory' && \C::get('sys.cache.use')) {
            $sCacheKey = 'lang_' . $sLang . '_' . \C::get('view.skin');
            if (false === ($this->aLangMsg[$sLang] = \E::Module('Cache')->Get($sCacheKey))) {
                // if false then empty array
                $this->aLangMsg[$sLang] = [];
                $this->loadLangFiles($this->sDefaultLang, $sLang);
                if ($sLang !== $this->sDefaultLang) {
                    $this->loadLangFiles($sLang, $sLang);
                }
                \E::Module('Cache')->Set($this->aLangMsg[$sLang], $sCacheKey, array(), 60 * 60);
            }
        } else {
            $this->loadLangFiles($this->sDefaultLang, $sLang);
            if ($sLang !== $this->sDefaultLang) {
                $this->loadLangFiles($sLang, $sLang);
            }
        }
        if ($sLang !== \C::get('lang.current')) {
            //C::Set('lang.current', $sLang);
        }
        $this->loadLangJs();
    }

    /**
     * Загружает из конфига текстовки для JS
     *
     */
    protected function loadLangJs()
    {
        $aMsg = \C::get('lang.load_to_js');
        if (is_array($aMsg) && count($aMsg)) {
            $this->aLangMsgJs = $aMsg;
        }
    }

    /**
     * Прогружает в шаблон текстовки в виде js
     *
     */
    protected function assignToJs()
    {
        $aLangMsg = [];
        foreach ($this->aLangMsgJs as $sName) {
            $aLangMsg[$sName] = $this->get($sName, [], false);
        }
        \E::Module('Viewer')->assign('aLangJs', $aLangMsg);
    }

    /**
     * Добавляет текстовку к js
     *
     * @param array $aKeys    Список текстовок
     */
    public function addLangJs($aKeys)
    {
        if (!is_array($aKeys)) {
            $aKeys = [$aKeys];
        }
        $this->aLangMsgJs = array_merge($this->aLangMsgJs, $aKeys);
    }

    /**
     * Make file list for loading
     *
     * @param      $aPaths
     * @param      $sPattern
     * @param      $sLang
     * @param bool $bExactMatch
     * @param bool $bCheckAliases
     *
     * @return array
     */
    public function _makeFileList($aPaths, $sPattern, $sLang, $bExactMatch = true, $bCheckAliases = true)
    {
        if (!is_array($aPaths)) {
            $aPaths = [(string)$aPaths];
        }

        $aResult = [];
        foreach ($aPaths as $sPath) {
            $sPathPattern = $sPath . '/' . $sPattern;
            $sLangFile = str_replace(static::LANG_PATTERN, $sLang, $sPathPattern);

            if ($bExactMatch) {
                if (\F::File_Exists($sLangFile)) {
                    $aResult[] = $sLangFile;
                }
            } else {
                if ($aFiles = glob($sLangFile)) {
                    $aResult = array_merge($aResult, $aFiles);
                }
            }
            if (!$aResult && $bCheckAliases && ($aAliases = (array)F::Str2Array(C::get('lang.aliases.' . $sLang)))) {
                //If the languages file is not found, then check its aliases
                foreach ($aAliases as $sLangAlias) {
                    $aSubResult = $this->_makeFileList($aPaths, $sPattern, $sLangAlias, $bExactMatch, false);
                    if ($aSubResult) {
                        $aResult = array_merge($aResult, $aSubResult);
                        break;
                    }
                }
            }
        }
        return $aResult;
    }

    /**
     * Loads languages files from path
     *
     * @param string|array $xPath
     * @param string       $sLang
     * @param array        $aParams
     * @param string       $sLangFor
     */
    protected function _loadFiles($xPath, $sLang, $aParams = null, $sLangFor = null)
    {
        $aFiles = $this->_makeFileList($xPath, static::LANG_PATTERN . '.php', $sLang);
        foreach ($aFiles as $sLangFile) {
            $aTexts =  \F::File_IncludeFile($sLangFile, true, true);
            if ($aTexts) {
                $this->addMessages($aTexts, $aParams, $sLangFor);
            }
        }
    }

    /**
     * Load several files by pattern
     *
     * @param string|array $xPath
     * @param string       $sMask
     * @param string       $sLang
     * @param string       $sPrefix
     * @param string       $sLangFor
     */
    protected function _loadFileByMask($xPath, $sMask, $sLang, $sPrefix, $sLangFor = null)
    {
        $aFiles = $this->_makeFileList($xPath, $sMask, $sLang, false);
        if ($aFiles) {
            foreach ($aFiles as $sLangFile) {
                $sDirModule = basename(dirname($sLangFile));
                $aTexts =  \F::File_IncludeFile($sLangFile, true, true);
                if ($aTexts) {
                    $this->addMessages($aTexts, ['category' => $sPrefix, 'name' => $sDirModule], $sLangFor);
                }
            }
        }
    }

    /**
     * Загружает текстовки из языковых файлов
     *
     * @param $sLangName - Язык для загрузки
     * @param $sLangFor  - Для какого языка выполняется загрузка
     */
    protected function loadLangFiles($sLangName, $sLangFor = null)
    {
        if (!$sLangFor) {
            $sLangFor = $this->sCurrentLang;
        }

        // Подключаем основной языковой файл
        $this->_loadFiles($this->aLangPaths, $sLangName, null, $sLangFor);

        // * Ищем языковые файлы модулей и объединяем их с текущим
        $sMask = '/modules/*/' . static::LANG_PATTERN . '.php';
        $this->_loadFileByMask($this->aLangPaths, $sMask, $sLangName, 'module', $sLangFor);

        // * Ищет языковые файлы экшенов и объединяет их с текущим
        $sMask = '/actions/*/' . static::LANG_PATTERN . '.php';
        $this->_loadFileByMask($this->aLangPaths, $sMask, $sLangName, 'action', $sLangFor);

        // * Ищем языковые файлы активированных плагинов
        if ($aPluginList =  \F::getPluginsList()) {
            foreach ($aPluginList as $sPluginName) {
                $aDirs = PluginManager::getDirLang($sPluginName);
                foreach($aDirs as $sDir) {
                    $aParams = array('name' => $sPluginName, 'category' => 'plugin');
                    $this->_loadFiles($sDir, $sLangName, $aParams, $sLangFor);
                }
            }

        }
        // * Ищет языковой файл текущего шаблона
        $this->loadLangFileTemplate($sLangName, $sLangFor);
    }

    /**
     * Загружает языковой файл текущего шаблона
     *
     * @param string $sLangName    Язык для загрузки
     * @param string $sLangFor
     */
    public function loadLangFileTemplate($sLangName = null, $sLangFor = null)
    {
        $aLangPaths = [
            \C::get('smarty.path.template') . '/settings/languages/',
        ];
        $aLangPaths[] = \C::get('path.dir.app') .  \F::File_LocalPath($aLangPaths[0], \C::get('path.dir.common'));

        $this->_loadFiles($aLangPaths, $sLangName, null, $sLangFor);
    }

    /**
     * Установить текущий язык
     *
     * @param string $sLang    Название языка
     */
    public function setLang($sLang)
    {
        $this->sCurrentLang = $sLang;
        $this->initLang();
    }

    /**
     * Получить текущий язык
     *
     * @return string
     */
    public function getLang()
    {
        return $this->sCurrentLang;
    }

    /**
     * Получить алиасы текущего языка
     *
     * @param bool $bIncludeCurrentLang
     *
     * @return array
     */
    public function getLangAliases($bIncludeCurrentLang = false)
    {
        $aResult =  \F::Str2Array(C::get('lang.aliases.' . $this->getLang()));
        if ($bIncludeCurrentLang) {
            array_unshift($aResult, $this->getLang());
        }
        return $aResult;
    }

    /**
     * Получить язык по умолчанию
     *
     * @return string
     */
    public function getDefaultLang()
    {
        return $this->sDefaultLang;
    }

    /**
     * Получить алиасы языка по умолчанию
     *
     * @return array
     */
    public function getDefaultLangAliases()
    {
        return  \F::Str2Array(C::get('lang.aliases.' . $this->getDefaultLang()));
    }

    /**
     * Получить дефолтный язык
     *
     * @return string
     */
    public function getLangDefault()
    {
        return $this->getDefaultLang();
    }

    /**
     * Получить список текстовок
     *
     * @param  string $sLang
     *
     * @return array
     */
    public function getLangMsg($sLang = null)
    {
        if (!$sLang) {
            $sLang = $this->sCurrentLang;
        }
        if (isset($this->aLangMsg[$sLang])) {
            return $this->aLangMsg[$sLang];
        }
        return [];
    }

    /**
     * @return LangArray
     */
    public function getLangArray()
    {
        return new LangArray();
    }

    /**
     * Получает текстовку по её имени
     *
     * @param string $sName    - Имя текстовки
     * @param array  $aReplace - Список параметром для замены в текстовке
     * @param bool   $bDelete  - Удалять или нет параметры, которые не были заменены
     * @param bool   $bEmptyResult
     *
     * @return string
     */
    public function get($sName, $aReplace = [], $bDelete = true, $bEmptyResult = false)
    {
        if (empty($sName)) {
            return 'EMPTY_LANG_TEXT';
        }
        if ($sName[0] === '[') {
            if ($sName[1] === ']') {
                $sLang = $this->sCurrentLang;
                $sName = substr($sName, 2);
            } else {
                $sLang = substr($sName, 1, 2);
                $sName = substr($sName, 4);
            }
        } else {
            $sLang = $this->sCurrentLang;
        }
        // Если нет нужного языка, то подгружаем его
        if (!isset($this->aLangMsg[$sLang])) {
            $this->initLang($sLang);
        }

        if (strpos($sName, '.')) {
            $aLangMsg = $this->aLangMsg[$sLang];
            $aKeys = explode('.', $sName);
            foreach ($aKeys as $k) {
                if (isset($aLangMsg[$k])) {
                    $aLangMsg = $aLangMsg[$k];
                } else {
                    //return  'NOT_FOUND_LANG_TEXT';
                    return $bEmptyResult ? null : strtoupper($sName);
                }
            }
            $sText = (string)$aLangMsg;
        } else {
            if (isset($this->aLangMsg[$sLang][$sName])) {
                $sText = $this->aLangMsg[$sLang][$sName];
            } else {
                //return 'NOT_FOUND_LANG_TEXT';
                return $bEmptyResult ? null : strtoupper($sName);
            }
        }

        if (!empty($aReplace) && is_string($sLang)) {
            $aReplacePairs = [];
            foreach ($aReplace as $sFrom => $sTo) {
                $aReplacePairs["%%{$sFrom}%%"] = $sTo;
            }
            $sText = strtr($sText, $aReplacePairs);
        }

        if ($this->bDeleteUndefinedVars && $bDelete && is_string($sText)) {
            $sText = preg_replace('|\%\%[\S]+\%\%|U', '', $sText);
        }
        return $sText;
    }

    /**
     * Return text by text key
     * If text key in brackets '{{..}}' then it will return text from brackets when text key not found
     *
     * @param string $sText    String as a simple key or in brackets as '{{..}}'
     * @param array  $aReplace List params for replacement
     * @param bool   $bDelete  Delete params from text if they cuold not replace
     * @param string $sLang    Language
     *
     * @return string
     */
    public function text($sText, $aReplace = [], $bDelete = true, $sLang = '')
    {
        if (is_string($bDelete)) {
            $sLang = $bDelete;
            $bDelete = true;
        } elseif (is_string($aReplace)) {
            $sLang = $aReplace;
            $bDelete = true;
            $aReplace = [];
        }
        if (substr($sText, 0, 2) === '{{' && substr($sText, -2) === '}}') {
            $sTextKey = mb_substr($sText, 2, -2);
            $sResult = $this->get('[' . $sLang . ']' . $sTextKey, $aReplace, $bDelete, true);
            if (null === $sResult) {
                $sResult = $sTextKey;
            }
        } else {
            $sResult = $this->get('[' . $sLang . ']' . $sText, $aReplace, $bDelete);
        }

        return $sResult;
    }

    /**
     * Добавить к текстовкам массив сообщений
     *
     * @param array      $aMessages - Список текстовок для добавления
     * @param array|null $aParams   - Параметры, позволяют хранить текстовки в структурированном виде,
     *                                например, тестовки плагина "test" получать как Get('plugin.name.test')
     * @param string     $sLang     - Язык
     */
    public function addMessages($aMessages, $aParams = null, $sLang = null)
    {
        if (!$sLang) {
            $sLang = $this->sCurrentLang;
        }
        if (!isset($this->aLangMsg[$sLang]) || !is_array($this->aLangMsg[$sLang])) {
            $this->aLangMsg[$sLang] = [];
        }
        if (is_array($aMessages)) {
            if (isset($aParams['name'])) {
                $aNewMessages = $aMessages;
                if (isset($aParams['category'])) {
                    if (isset($this->aLangMsg[$sLang][$aParams['category']][$aParams['name']])) {
                        $aNewMessages = array_merge($this->aLangMsg[$sLang][$aParams['category']][$aParams['name']], $aNewMessages);
                    }
                    $this->aLangMsg[$sLang][$aParams['category']][$aParams['name']] = $aNewMessages;
                } else {
                    if (isset($this->aLangMsg[$sLang][$aParams['name']])) {
                        $aNewMessages = array_merge($this->aLangMsg[$sLang][$aParams['name']], $aNewMessages);
                    }
                    $this->aLangMsg[$sLang][$aParams['name']] = $aNewMessages;
                }
            } else {
                $this->aLangMsg[$sLang] = array_merge($this->aLangMsg[$sLang], $aMessages);
            }
        }
    }

    /**
     * Добавить к текстовкам отдельное сообщение
     *
     * @param string $sKey     - Имя текстовки
     * @param string $sMessage - Значение текстовки
     * @param string $sLang    - Язык
     */
    public function addMessage($sKey, $sMessage, $sLang = null)
    {
        if (!$sLang) {
            $sLang = $this->sCurrentLang;
        }
        $this->aLangMsg[$sLang][$sKey] = $sMessage;
    }

    /**
     * @param string $sLang
     *
     * @return $this
     */
    public function dictionary($sLang = null)
    {
        if ($sLang && $sLang !== $this->sCurrentLang) {
            $this->initLang($sLang);
        }
        return $this;
    }

    /**
     * Возвращает список языков сайта
     *
     * @return array
     */
    public function getLangList()
    {
        $aLangList = (array)C::get('lang.allow');
        if (!$aLangList) {
            $aLangList = [C::get('lang.current')];
        }
        if (!$aLangList) {
            $aLangList = [C::get('lang.default')];
        }
        if (!$aLangList) {
            $aLangList = ['ru'];
        }
        return $aLangList;
    }

    /**
     * Возвращает список доступных языков
     *
     * @return array
     */
    public function getAvailableLanguages()
    {
        $aLanguages = (array)UserLocale::getAvailableLanguages(true);
        foreach ($aLanguages as $sLang=>$aLang) {
            if (!isset($aLang['aliases']) && isset($aLang['name'])) {
                $aLanguages[$sLang]['aliases'] = strtolower($aLang['name']);
            }
        }
        return $aLanguages;
    }

    /**
     * Завершаем работу модуля
     *
     */
    public function shutdown()
    {
        // * Делаем выгрузку необходимых текстовок в шаблон в виде js
        $this->assignToJs();
        if (C::get('lang.multilang')) {
            \E::Module('Viewer')->addHtmlHeadTag(
                '<link rel="alternate" hreflang="x-default" href="' . R::url('link') . '">'
            );
            $aLangs = (array)C::get('lang.allow');
            foreach ($aLangs as $sLang) {
                \E::Module('Viewer')->addHtmlHeadTag(
                    '<link rel="alternate" hreflang="' . $sLang . '" href="' . trim(\F::File_RootUrl($sLang), '/')
                        . R::url('path') . '">'
                );
            }
        }
    }

}

// EOF