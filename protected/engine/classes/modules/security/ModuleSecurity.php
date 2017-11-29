<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

/**
 * Модуль безопасности
 * Необходимо использовать перед обработкой отправленной формы:
 * <pre>
 * if (\F::GetRequest('submit_add')) {
 *    \E::Module('Security')->ValidateSendForm();
 *    // далее код обработки формы
 *  ......
 * }
 * </pre>
 *
 * @package engine.modules
 * @since   1.0
 */
class ModuleSecurity extends Module
{
    protected $sSecurityKeyName;
    protected $sSecurityKeyLen;

    /**
     * Initializes the module
     *
     */
    public function init()
    {
        $this->sSecurityKeyName = 'ALTO_SECURITY_KEY';
        $this->sSecurityKeyLen = 32;
    }

    /**
     * Производит валидацию отправки формы/запроса от пользователя, позволяет избежать атаки CSRF
     *
     * @param   bool $bBreak - немедленно прекратить работу
     *
     * @return  bool
     */
    public function validateSendForm($bBreak = true)
    {
        if (!$this->validateSecurityKey()) {
            if ($bBreak) {
                die('Hacking attempt!');
            }
            return false;
        }
        return true;
    }

    /**
     * Проверка на соотвествие реферала
     *
     * @return bool
     */
    public function validateReferal()
    {
        if (isset($_SERVER['HTTP_REFERER'])) {
            $aUrl = parse_url($_SERVER['HTTP_REFERER']);
            if (strcasecmp($aUrl['host'], $_SERVER['HTTP_HOST']) == 0) {
                return true;
            }
            if (preg_match("/\." . quotemeta($_SERVER['HTTP_HOST']) . "$/i", $aUrl['host'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifies security key from argument or from request
     *
     * @param   string|null $sKey  - Security key for verifying. If it is ommited then it extracts from request
     *
     * @return  bool
     */
    public function validateSecurityKey($sKey = null)
    {
        if (!$sKey) {
            if (isset($_SERVER['HTTP_X_ALTO_AJAX_KEY'])) {
                $sKey = (string)$_SERVER['HTTP_X_ALTO_AJAX_KEY'];
            } else {
                $sKey =  \F::getRequestStr('security_key');
            }
        }
        return ($sKey === $this->getSecurityKey());
    }

    /**
     * Returns security key from the session
     *
     * @return string
     */
    public function getSecurityKey()
    {
        $sSecurityKey = \E::Module('Session')->get($this->sSecurityKeyName);
        if (empty($sSecurityKey)) {
            $sSecurityKey = $this->_generateSecurityKey();
        }
        return $sSecurityKey;
    }

    /**
     * Set security key in the session
     *
     * @return string
     */
    public function setSecurityKey()
    {
        $sSecurityKey = $this->_generateSecurityKey();

        \E::Module('Session')->set($this->sSecurityKeyName, $sSecurityKey);
        \E::Module('Viewer')->assign($this->sSecurityKeyName, $sSecurityKey);

        return $sSecurityKey;
    }

    /**
     * Generates security key for the current session
     *
     * @return string
     */
    protected function _generateSecurityKey()
    {
        // Сохраняем текущий ключ для ajax-запросов
        if (\F::ajaxRequest() && ($sKey = \E::Module('Session')->get($this->sSecurityKeyName))) {
            return $sKey;
        }

        if (Config::get('module.security.randomkey')) {
            return  \F::RandomStr($this->sSecurityKeyLen);
        }

        return md5($this->getUniqueKey() . $this->getClientHash() . \C::get('module.security.hash'));
    }

    /**
     * @param string $sPassword
     *
     * @return string
     */
    public function getPasswordHash($sPassword)
    {
        $sSalt = \C::get('security.salt_pass');
        return password_hash($sPassword . '::' . $sSalt, PASSWORD_DEFAULT);
    }

    /**
     * @param string $sPassword
     * @param string $sHash
     *
     * @return bool
     */
    public function verifyPasswordHash($sPassword, $sHash)
    {
        if (!$sHash) {
            return false;
        }

        if ($sHash[0] === '$') {
            $sSalt = \C::get('security.salt_pass');
            return password_verify($sPassword . '::' . $sSalt, $sHash);
        }
        return $this->checkSaltedHash($sPassword, $sHash, 'pass');
    }

    /**
     * Returns hash of salted string
     *
     * @param   string      $sData
     * @param   string|null $sType
     *
     * @return  string
     */
    public function getSaltedHash($sData, $sType = null)
    {
        $sSalt = \C::get('security.salt_' . $sType);
        if ($sSalt !== false && !$sSalt) {
            $sSalt = $sType;
        }
        return  \F::DoSalt($sData, $sSalt);
    }

    /**
     * Checks salted hash and original string
     *
     * @param   string $sSalted    - "соленый" хеш
     * @param   string $sData      - проверяемые данные
     * @param   string $sType      - тип "соли"
     *
     * @return  bool
     */
    public function checkSaltedHash($sSalted, $sData, $sType = null)
    {
        if (!$sSalted) {
            return false;
        }

        if (0 === strpos($sSalted, '0x:')) {
            return $sSalted === $this->getSaltedHash($sData, $sType);
        }
        if ($sType === 'pass') {
            if ($sSalted[0] === '$') {
                return password_verify($sData, $sSalted);
            }
            // Compatibility with Joomla
            if (0 === strpos($sSalted, 'Jx:')) {
                list($sHash, $sSalt) = explode(':', substr($sSalted, 3), 2);
                if ($sHash && $sSalt && is_string($sData)) {
                    return $sHash === md5($sData . $sSalt);
                }
                return false;
            }
        }
        return $sSalted === md5($sData);
    }

    /**
     * Calcs hash of user agent
     *
     * @return string
     */
    public function getUserAgentHash()
    {
        $sUserAgent = (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
        return $this->getSaltedHash($sUserAgent, 'auth');
    }

    /**
     * Calcs hash of client
     *
     * @return string
     */
    public function getClientHash()
    {
        $sClientHash = \F::urlScheme() . '::' . $this->getUserAgentHash();
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $sClientHash .= $_SERVER['REMOTE_ADDR'];
        }
        //if ($sVizId = \E::Module('Session')->getCookie('visitor_id')) $sClientHash .= $sVizId;

        return $this->getSaltedHash($sClientHash, 'auth');
    }

    /**
     * Generates depersonalized unique key of the site
     *
     * @return string
     */
    public function generateUniqueKey()
    {
        $sData = serialize(Config::get('path.root'));
        if (isset($_SERVER['SERVER_ADDR'])) {
            $sData .= $_SERVER['SERVER_ADDR'];
        }
        return $this->getSaltedHash(md5($sData), 'auth');
    }

    /**
     * Returns depersonalized unique key of the site
     *
     * @return string
     */
    public function getUniqueKey()
    {
        $sUniqueKey = \C::get(Config::ALTO_UNIQUE_KEY);
        if (!$sUniqueKey) {
            $sUniqueKey = $this->generateUniqueKey();
            \C::set(Config::ALTO_UNIQUE_KEY, $sUniqueKey);

            // +++ Old version compatibility
            if (Config::readCustomConfig('alto.uniq_key')) {
                \C::resetCustomConfig('alto.uniq_key');
            }
            // ---

            \C::writeEngineConfig(array(Config::ALTO_UNIQUE_KEY => $sUniqueKey));
        }
        return $sUniqueKey;
    }

    /**
     * Shutdowns the module
     */
    public function shutdown()
    {
        // nothing
    }

}

// EOF