<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 */

/**
 * Модуль для работы с сессиями
 * Выступает в качестве надстроки для стандартного механизма сессий
 *
 * @package engine.modules
 * @since 1.0
 */
class ModuleSession extends Module
{
    /**
     * ID  сессии
     *
     * @var null|string
     */
    protected $sId = null;

    /**
     * Данные сессии
     *
     * @var array
     */
    protected $aData = [];

    /**
     * Список user-agent'ов для флеш плеера
     * Используется для передачи ID сессии при обращениии к сайту через flash, например, загрузка файлов через flash
     *
     * @var array
     */
    protected $aFlashUserAgent = [
        'Shockwave Flash'
    ];

    /**
     * Использовать или нет стандартный механизм сессий
     * ВНИМАНИЕ! Не рекомендуется ставить false - т.к. этот режим до конца не протестирован
     *
     * @var bool
     */
    protected $bUseStandardSession = true;

    /**
     * Инициализация модуля
     *
     */
    public function init()
    {
        $this->bUseStandardSession = \C::get('sys.session.standart');

        // * Стартуем сессию
        $this->start();

        if (!$this->getCookie('visitor_id')) {
            $this->setCookie('visitor_id',  \F::randomStr());
        }

        session_register_shutdown();
    }

    /**
     * Старт сессии
     *
     */
    protected function start()
    {
        if ($this->bUseStandardSession) {
            $sSysSessionName = \C::get('sys.session.name');
            session_name($sSysSessionName);
            session_set_cookie_params(
                \C::get('sys.session.timeout'),
                \C::get('sys.session.path'),
                \C::get('sys.session.host')
            );
            if (!session_id()) {

                // * Попытка подменить идентификатор имени сессии через куку
                if (isset($_COOKIE[$sSysSessionName])) {
                    if (!is_string($_COOKIE[$sSysSessionName])) {
                        $this->delCookie($sSysSessionName . '[]');
                        $this->delCookie($sSysSessionName);
                    } elseif (!preg_match('/^[\-\,a-zA-Z0-9]{1,128}$/', $_COOKIE[$sSysSessionName])) {
                        $this->delCookie($sSysSessionName);
                    }
                }

                // * Попытка подменить идентификатор имени сессии в реквесте
                $aRequest = array_merge($_GET, $_POST); // Исключаем попадаение $_COOKIE в реквест
                if (@ini_get('session.use_only_cookies') === '0' && isset($aRequest[$sSysSessionName]) && !is_string($aRequest[$sSysSessionName])) {
                    session_name($this->generateId());
                }

                // * Даем возможность флешу задавать id сессии
                $sSSID =  \F::getRequestStr('SSID');
                if ($sSSID && $this->_validFlashAgent() && preg_match('/^[\w]{5,40}$/', $sSSID)) {
                    session_id($sSSID);
                    session_start();
                } else {
                    session_start();
                    if ($sSSID) {
                        // wrong session ID, regenerates it
                        session_regenerate_id(true);
                    }
                }
            }
        } else {
            $this->setId();
            $this->readData();
        }
    }

    /**
     * @return bool
     */
    protected function _validFlashAgent()
    {
        $sUserAgent = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : null;
        if ($sUserAgent && (in_array($sUserAgent, $this->aFlashUserAgent) || strpos($sUserAgent, 'Adobe Flash Player') === 0)) {
            return true;
        }
        return false;
    }

    /**
     * Устанавливает уникальный идентификатор сессии
     *
     */
    protected function setId()
    {
        // * Если идентификатор есть в куках то берем его
        if (isset($_COOKIE[\C::get('sys.session.name')])) {
            $this->sId = $_COOKIE[\C::get('sys.session.name')];
        } else {
            // * Иначе создаём новый и записываем его в куку
            $this->sId = $this->generateId();
            setcookie(
                \C::get('sys.session.name'),
                $this->sId,
                time() + \C::get('sys.session.timeout'),
                \C::get('sys.session.path'),
                \C::get('sys.session.host')
            );
        }
    }

    /**
     * Получает идентификатор текущей сессии
     *
     */
    public function getId()
    {
        if ($this->bUseStandardSession) {
            return session_id();
        }
        return $this->sId;
    }

    /**
     * Returns hash-key of current session
     */
    public function getKey()
    {
        return \E::Module('Security')->getSaltedHash($this->getId(), 'sess');
    }

    /**
     * Гинерирует уникальный идентификатор
     *
     * @return string
     */
    protected function generateId()
    {
        return md5(\F::RandomStr() . time());
    }

    /**
     * Читает данные сессии в data
     *
     */
    protected function readData()
    {
        $this->aData = \E::Module('Cache')->get($this->sId);
    }

    /**
     * Сохраняет данные сессии
     *
     */
    protected function save()
    {
        \E::Module('Cache')->set($this->aData, $this->sId, [], \C::get('sys.session.timeout'));
    }

    /**
     * Получает значение из сессии
     *
     * @param   string      $sName    Имя параметра
     * @param   string|null $sDefault Значение по умолчанию
     * @return  mixed|null
     */
    public function get($sName = null, $sDefault = null)
    {
        if (null === $sName) {
            return $this->getData();
        }
        if ($this->bUseStandardSession) {
            return isset($_SESSION[$sName]) ? $_SESSION[$sName] : $sDefault;
        }
        return isset($this->aData[$sName]) ? $this->aData[$sName] : $sDefault;
    }

    /**
     * Get session data and drop it from current session
     *
     * @param string|null $sName
     * @param mixed|null  $sDefault
     *
     * @return mixed|null
     */
    public function getClear($sName = null, $sDefault = null)
    {
        $xResult = $this->get($sName, $sDefault);
        $this->drop($sName);

        return $xResult;
    }

    /**
     * Записывает значение в сессию
     *
     * @param string $sName  Имя параметра
     * @param mixed  $data   Данные
     */
    public function set($sName, $data)
    {
        if ($this->bUseStandardSession) {
            $_SESSION[$sName] = $data;
        } else {
            $this->aData[$sName] = $data;
            $this->save();
        }
    }

    /**
     * Удаляет значение из сессии
     *
     * @param string $sName    Имя параметра
     */
    public function drop($sName)
    {
        if (isset($_SESSION[$sName])) {
            unset($_SESSION[$sName]);
        }
        if (!$this->bUseStandardSession) {
            if (isset($this->aData[$sName])) {
                unset($this->aData[$sName]);
                $this->save();
            }
        }
    }

    /**
     * Получает разом все данные сессии
     *
     * @return array
     */
    public function getData()
    {
        if ($this->bUseStandardSession) {
            return $_SESSION;
        }
        return $this->aData;
    }

    /**
     * Завершает сессию, дропая все данные
     *
     */
    public function dropSession()
    {
        if ($this->bUseStandardSession && session_id()) {
            session_unset();
            $this->delCookie(Config::get('sys.session.name'));
            session_destroy();
        } else {
            unset($this->sId, $this->aData);
            $this->delCookie(Config::get('sys.session.name'));
        }
    }

    /**
     * Sets cookie
     *
     * @param   string          $sName
     * @param   mixed           $xValue
     * @param   int|string|null $xPeriod - period in seconds or in string like 'P<..>'
     * @param   bool            $bHttpOnly
     * @param   bool            $bSecure
     *
     * @return bool
     */
    public function setCookie($sName, $xValue, $xPeriod = null, $bHttpOnly = true, $bSecure = false)
    {
        if ($xPeriod) {
            $nTime = time() +  \F::ToSeconds($xPeriod);
        } else {
            $nTime = 0;
        }
        // setting a cookie with a value of FALSE will try to delete the cookie
        if (is_bool($xValue)) {
            $xValue = ($xValue ? 1 : 0);
        }
        $bResult = setcookie($sName, (string)$xValue, $nTime, \C::get('sys.cookie.path'), \C::get('sys.cookie.host'), $bSecure, $bHttpOnly);
        if (ALTO_DEBUG) {
            if (!$bResult) {
                if (headers_sent($sFilename, $iLine)) {
                     \F::sysWarning('Cannot set cookie "' . $sName . '" - header was sent in file ' . $sFilename . '(' . $iLine . ')');
                } else {
                     \F::sysWarning('Cannot set cookie "' . $sName . '"');
                }
            }
        }
        return $bResult;
    }

    /**
     * Gets cookie
     *
     * @param   string  $sName
     * @return  string|null
     */
    public function getCookie($sName)
    {
        if (isset($_COOKIE[$sName])) {
            return $_COOKIE[$sName];
        }
        return null;
    }

    /**
     * Deletes cookie
     *
     * @param   string  $sName
     */
    public function delCookie($sName)
    {
        if (isset($_COOKIE[$sName])) {
            unset($_COOKIE[$sName]);
        }
        setcookie($sName, '', time() - 3600, \C::get('sys.cookie.path'), \C::get('sys.cookie.host'));
    }

}

// EOF