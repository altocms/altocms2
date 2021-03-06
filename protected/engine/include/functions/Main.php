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
 * Common functions for Alto CMS
 */
class AltoFunc_Main 
{
    static protected $sRandChars = '0123456789_ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    static protected $sHexChars = '0123456789abcdef';
    static protected $sMemSizeUnits = 'BKMGTP';

    /**
     * @param string $sStr
     *
     * @return string
     */
    public static function strUnderscore($sStr) 
    {
        return strtolower(preg_replace('/([^A-Z])([A-Z])/', "$1_$2", $sStr));
    }

    /**
     * @param string $sStr
     * 
     * @return string
     */
    public static function strCamelize($sStr) 
    {
        $sStr = str_replace('-', '_', $sStr);
        $aParts = explode('_', $sStr);
        $sCamelized = '';
        foreach ($aParts as $sPart) {
            $sCamelized .= ucfirst($sPart);
        }
        return $sCamelized;
    }

    /**
     * @param string $sStr
     * @param string $sSeparator
     * @param bool   $bSkipEmpty
     *
     * @return array
     */
    public static function Str2Array($sStr, $sSeparator = ',', $bSkipEmpty = false) 
    {
        return F::Array_Str2Array($sStr, $sSeparator, $bSkipEmpty);
    }

    /**
     * @param string $sStr
     * @param string $sSeparator
     * @param bool   $bUnique
     *
     * @return array
     */
    public static function Str2ArrayInt($sStr, $sSeparator = ',', $bUnique = true) {

        return F::Array_Str2ArrayInt($sStr, $sSeparator, $bUnique);
    }

    /**
     * @param mixed  $xVal
     * @param string $sSeparator
     * @param bool   $bSkipEmpty
     *
     * @return array
     */
    public static function Val2Array($xVal, $sSeparator = ',', $bSkipEmpty = false)
    {
        return F::Array_Val2Array($xVal, $sSeparator, $bSkipEmpty);
    }

    /**
     * Возвращает строку со случайным набором символов
     *
     * @param int         $nLen   - длина строка
     * @param bool|string $sChars - только шестнадцатиричные символы [0-9a-f], либо заданный набор символов
     *
     * @return  string
     */
    public static function randomStr($nLen = 32, $sChars = true)
    {
        $sResult = '';
        if ($sChars === true) {
            while (strlen($sResult) < $nLen) {
                $sResult .= md5(uniqid(md5(mt_rand()), true));
            }
            if (strlen($sResult) > $nLen) {
                $sResult = substr($sResult, 0, $nLen);
            }
        } else {
            if (!is_string($sChars)) {
                $sChars = self::$sRandChars;
            }
            $nMax = strlen($sChars) - 1;
            while (strlen($sResult) < $nLen) {
                $sResult .= $sChars[mt_rand(0, $nMax)];
            }
        }
        return $sResult;
    }

    /**
     * Cryptographically secure pseudo-random number generator (CSPRNG)
     * @link http://stackoverflow.com/questions/1846202/php-how-to-generate-a-random-unique-alphanumeric-string/13733588#13733588
     *
     * @param $iMin
     * @param $iMax
     *
     * @return int
     */
    public static function CryptoRandom($iMin, $iMax) {

        $iRange = $iMax - $iMin;
        if ($iRange < 0) {
            return $iMin; // not so random...
        }
        $nLog = log($iRange, 2);
        $iBytes = (int)($nLog / 8) + 1; // length in bytes
        $iBits = (int)$nLog + 1; // length in bits
        $iFilter = (int)(1 << $iBits) - 1; // set all lower bits to 1
        do {
            $iRnd = hexdec(bin2hex(openssl_random_pseudo_bytes($iBytes)));
            $iRnd = $iRnd & $iFilter; // discard irrelevant bits
        } while ($iRnd >= $iRange);
        return $iMin + $iRnd;
    }

    /**
     * Random string using CSPRNG
     *
     * @param int $iLen
     * @param bool|string $sChars
     *
     * @return string
     */
    public static function CryptoRandomStr($iLen = 32, $sChars = true) {

        $sResult = '';
        if ($sChars === true) {
            $sChars = self::$sHexChars;
        } elseif (!is_string($sChars)) {
            $sChars = self::$sRandChars;
        }

        for ($i = 0; $i < $iLen; $i++) {
            $sResult .= $sChars[self::CryptoRandom(0, strlen($sChars))];
        }
        return $sResult;
    }

    /**
     * @param   float $nValue
     * @param   int   $nDecimal
     *
     * @return  string
     */
    public static function MemSizeFormat($nValue, $nDecimal = 0) {

        $aUnits = self::$sMemSizeUnits;
        $nIndex = 0;
        $nResult = (int)$nValue;
        while ($nResult >= 1024) {
            $nIndex += 1;
            $nResult = $nResult / 1024;
        }
        if (isset($aUnits[$nIndex])) {
            return number_format($nResult, $nDecimal, '.', '\'') . '&nbsp;' . $aUnits[$nIndex];
        }
        return $nValue;
    }

    /**
     * Converts string as memory size into number
     *      '256'   => 256 - just number
     *      '2K'    => 2 * 1024 = 2048 - in KB
     *      '4 KB'  => 4 * 1024 = 4096 - in KB
     *      '1.5M'  => 1.5 * 1024 * 1024 = 1572864 - in MB
     *      '187X'  => 187 - invalid unit
     *
     * @param $sNum
     *
     * @return int|number
     */
    public static function MemSize2Int($sNum)
    {
        $nValue = (float)$sNum;
        if (!is_numeric($sNum)) {
            // string has a non-numeric suffix
            $sSuffix = strpbrk(strtoupper($sNum), self::$sMemSizeUnits);
            if ($sSuffix && ($nIdx = strpos(self::$sMemSizeUnits, $sSuffix[0]))) {
                $nValue *= 1024 ** $nIdx;
            }
        }
        return (int)$nValue;
    }

    /**
     * @param   mixed $xData
     *
     * @return  string
     */
    public static function JsonEncode($xData)
    {
        if (function_exists('json_encode')) {
            $iOptions = 0;
            if (defined('JSON_NUMERIC_CHECK')) {
                $iOptions |= JSON_NUMERIC_CHECK;
            }
            if (defined('JSON_UNESCAPED_UNICODE')) {
                $iOptions |= JSON_UNESCAPED_UNICODE;
            }
            return json_encode($xData, $iOptions);
        }
        if (null === $xData) {
            return 'null';
        }
        if ($xData === false) {
            return 'false';
        }
        if ($xData === true) {
            return 'true';
        }
        if (is_scalar($xData)) {
            if (is_float($xData)) {
                // Always use "." for floats.
                return (float)str_replace(',', '.', (string)$xData);
            }

            if (is_string($xData)) {
                static $jsonReplaces
                = [["\\", "/", "\n", "\t", "\r", "\b", "\f", '"'],
                        ['\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"']];
                return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $xData) . '"';
            }
            return $xData;
        }
        $bList = true;
        $nLen = count($xData);
        for ($i = 0, reset($xData); $i < $nLen; $i++, next($xData)) {
            if (key($xData) !== $i) {
                $bList = false;
                break;
            }
        }
        $aResult = array();
        if ($bList) {
            foreach ($xData as $sVal) {
                $aResult[] = self::jsonEncode($sVal);
            }
            return '[' . implode(',', $aResult) . ']';
        }
        foreach ($xData as $sKey => $sVal) {
            $aResult[] = self::jsonEncode($sKey) . ':' . self::jsonEncode($sVal);
        }
        return '{' . implode(',', $aResult) . '}';
    }

    static protected $_aUserIp = array();

    /**
     * Returns all IP of current user
     *
     * @param   array|string|null   $aTrusted
     * @param   array|string|null   $aNonTrusted
     *
     * @return  array
     */
    public static function GetAllUserIp($aTrusted = null, $aNonTrusted = null)
    {
        if (!$aTrusted) {
            if (class_exists('Config', false)) {
                $aTrusted = (array)Config::get('sys.ip.trusted');
            }
            if (!$aTrusted)
                $aTrusted = [
                    'REMOTE_ADDR',
                    'HTTP_X_REAL_IP',
                    'HTTP_CLIENT_IP',
                    'HTTP_X_FORWARDED_FOR',
                    'HTTP_VIA',
                ];
        } else {
            $aTrusted = (array)$aTrusted;
        }

        if (!$aNonTrusted) {
            $aNonTrusted = F::_getConfig('sys.ip.non_trusted', array());
        } else {
            $aNonTrusted = (array)$aNonTrusted;
        }

        $aIp = array();
        foreach ($aTrusted as $sParam) {
            if (isset($_SERVER[$sParam]) && (!$aNonTrusted || !in_array($sParam, $aNonTrusted))) {
                // sometimes IPs separated by space
                $sIp = str_replace(' ', ',', trim($_SERVER[$sParam]));
                if (strpos($sIp, ',') === false) {
                    // several IPs
                    $aData = explode(',', $sIp);
                    $aIp[$sParam] = '';
                    foreach ($aData as $sData) {
                        if ($sData && filter_var($sData, FILTER_VALIDATE_IP)) {
                            if ($aIp[$sParam]) {
                                $aIp[$sParam] .= ',';
                            }
                            $aIp[$sParam] .= $sData;
                        }
                    }
                    if (!$aIp[$sParam]) {
                        unset($aIp[$sParam]);
                    }
                } else {
                    // single IP
                    if ($sIp && filter_var($sIp, FILTER_VALIDATE_IP)) {
                        $aIp[$sParam] = $sIp;
                    }
                }
            }
        }
        if (!$aIp) {
            $sIp = F::_getConfig('sys.ip.default');
            if (!$sIp || !filter_var($sIp, FILTER_VALIDATE_IP)) {
                $sIp = '127.0.0.1';
            }
            $aIp['FAKE_ADDR'] = $sIp;
        }
        return $aIp;
    }

    /**
     * Returns user's IP
     *
     * @param   array|string|null   $aTrusted
     * @param   array|string|null   $aNonTrusted
     *
     * @return  string
     */
    public static function GetUserIp($aTrusted = null, $aNonTrusted = null)
    {
        $sKey = md5(serialize(array($aTrusted, $aNonTrusted)));
        if (isset(self::$_aUserIp[$sKey])) {
            $sIp = self::$_aUserIp[$sKey];
        } else {
            $aIpParams = self::GetAllUserIp($aTrusted, $aNonTrusted);
            $aExcludeIp = (array)F::_getConfig('sys.ip.exclude', ['127.0.0.1', 'fe80::1', '::1']);
            if (\F::_getConfig('sys.ip.exclude_server', true) && isset($_SERVER['SERVER_ADDR'])) {
                $aExcludeIp[] = $_SERVER['SERVER_ADDR'];
            }

            $bSeekBackward = F::_getConfig('sys.ip.backward', true);
            $bExcludePrivate = F::_getConfig('sys.ip.exclude_private', true);
            // collect all ip
            $aIp = array();
            foreach ($aIpParams as $sIp) {
                if (strpos($sIp, ',')) {
                    $aSeveralIps = explode(',', $sIp);
                    if ($bSeekBackward) {
                        $aSeveralIps = array_reverse($aSeveralIps);
                    }
                    $aIp = array_merge($aIp, $aSeveralIps);
                } else {
                    $aIp[] = $sIp;
                }
            }
            foreach ($aIp as $sIp) {
                if (!in_array($sIp, $aExcludeIp) && (!$bExcludePrivate || filter_var($sIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
                    return $sIp;
                }
            }
            $sIp = array_shift($aIp);
            self::$_aUserIp[$sKey] = $sIp;
        }
        return $sIp;
    }

    /**
     * @param string $sValue
     * @param string $sParam
     * @param int    $nMin
     * @param int    $nMax
     *
     * @return bool
     */
    public static function CheckVal($sValue, $sParam, $nMin = 1, $nMax = 100)
    {
        if (!is_scalar($sValue)) {
            return false;
        }
        switch (strtolower($sParam)) {
            case 'id':
                if (preg_match('/^\d{' . $nMin . ',' . $nMax . '}$/', $sValue)) {
                    return true;
                }
                break;
            case 'float':
                if (preg_match('/^[\-]?\d+[\.]?\d*$/', $sValue)) {
                    return true;
                }
                break;
            case 'email':
            case 'mail':
                return filter_var($sValue, FILTER_VALIDATE_EMAIL) !== false;
                break;
            case 'url':
                // ф-ция неверно понимает URL без протокола
                if ((filter_var($sValue, FILTER_VALIDATE_URL) !== false) || (filter_var('http:' . $sValue, FILTER_VALIDATE_URL) !== false)) {
                    return true;
                }
                break;
            case 'login':
                if (preg_match('/^[\da-z\_\-]{' . $nMin . ',' . $nMax . '}$/i', $sValue)) {
                    return true;
                }
                break;
            case 'md5':
                if (preg_match('/^[\da-z]{32}$/i', $sValue)) {
                    return true;
                }
                break;
            case 'password':
                if (mb_strlen($sValue, 'UTF-8') >= $nMin) {
                    return true;
                }
                break;
            case 'text':
                if ((mb_strlen($sValue, 'UTF-8') >= $nMin) && ($nMax == 0 || mb_strlen($sValue, 'UTF-8') <= $nMax)) {
                    return true;
                }
                break;
            default:
                return false;
        }
        return false;
    }

    /**
     * Вовзвращает "соленый" хеш
     *
     * @param   mixed  $xData  - хешируемая переменная
     * @param   string $sSalt  - "соль"
     *
     * @return  string
     */
    public static function DoSalt($xData, $sSalt)
    {
        if (!is_string($xData)) {
            $sData = serialize($xData);
        }
        else {
            $sData = (string)$xData;
        }
        return '0x:' . \F::DoHashe($sData . '::' . $sSalt);
    }

    /**
     * Вовзвращает "чистый" хеш
     *
     * @param   mixed $xData  - хешируемая переменная
     *
     * @return  string
     */
    public static function DoHashe($xData) {

        if (!is_string($xData)) {
            $sData = serialize($xData);
        }
        else {
            $sData = (string)$xData;
        }
        return (md5(sha1($sData)));
    }

    /**
     * Calculates crc32 for any vartype, returns the same result for 32-bit and 64-bit systems
     *
     * @param mixed $xData
     * @param bool  $bHex
     *
     * @return int|string
     */
    public static function Crc32($xData, $bHex = false)
    {
        if (null === $xData) {
            $nCrc = 0;
        } else {
            if (!is_scalar($xData)) {
                $sData = serialize($xData);
            } else {
                $sData = (string)$xData;
            }
            $nCrc = abs(crc32($sData));
            if( $nCrc & 0x80000000){
                $nCrc ^= 0xffffffff;
                $nCrc += 1;
            }
        }
        if ($bHex) {
            return str_pad(dechex($nCrc), 8, STR_PAD_LEFT);
        }
        return $nCrc;
    }

    /**
     * Возвращает текст, обрезанный по заданное число символов
     *
     * @param   string  $sText
     * @param   int     $nLen
     * @param   string  $sPostfix
     * @param   boolean $bWordWrap
     *
     * @return  string
     */
    public static function TruncateText($sText, $nLen, $sPostfix = '', $bWordWrap = false)
    {
        if (mb_strlen($sText, 'UTF-8') > $nLen) {
            if (!$bWordWrap) {
                $sText = mb_substr($sText, 0, $nLen - mb_strlen($sPostfix, 'UTF-8'), 'UTF-8') . $sPostfix;
            } else {
                $sText = mb_substr($sText, 0, $nLen - mb_strlen($sPostfix, 'UTF-8'), 'UTF-8');
                $nLength = mb_strlen($sText, 'UTF-8');
                if (preg_match('/[^\s\.\!\?\,\:\;\]\)\}]+$/siU', $sText, $aM)) {
                    $sText = trim(mb_substr($sText, 0, $nLength - mb_strlen($aM[0], 'UTF-8'), 'UTF-8'));
                }
                $sText .= $sPostfix;
            }
        }
        return $sText;
    }

    /**
     * Возвращает текст, обрезанный по заданное число слов
     *
     * @param   string $sText
     * @param   int    $nCountWords
     *
     * @return  string
     */
    public static function CutText($sText, $nCountWords)
    {
        $aWords = preg_split('#[\s\r\n]+#um', $sText);
        if ($nCountWords < count($aWords)) {
            $aWords = array_slice($aWords, 0, $nCountWords);
        }
        return implode(' ', $aWords);
    }

    /**
     * Аналог serialize() с контролем CRC32
     *
     * @param mixed $xData
     * @param bool  $bSafeMode
     *
     * @return string
     */
    public static function Serialize($xData, $bSafeMode = false)
    {
        $sData = serialize($xData);
        if ($bSafeMode) {
            $sData = base64_encode($sData);
        }
        $sResult = static::Crc32($sData, true) . '|' . $sData;
        return $bSafeMode ? 'x' . $sResult : $sResult;

    }

    /**
     * Аналог unserialize() с контролем CRC32
     *
     * @param string $sData
     * @param mixed  $xDefaultOnError
     *
     * @return mixed
     */
    public static function Unserialize($sData, $xDefaultOnError = null)
    {
        if (is_string($sData) && $sData) {
            if (strpos($sData, '|')) {
                if ($sData[0] === 'x') {
                    $bBase64 = true;
                    list($sCrc32, $sData) = explode('|', substr($sData, 1), 2);
                } else {
                    $bBase64 = false;
                    list($sCrc32, $sData) = explode('|', $sData, 2);
                }
                if ($sCrc32 && $sData && ($sCrc32 == static::Crc32($sData, true))) {
                    if ($bBase64) {
                        $sData = base64_decode($sData);
                    }
                    $xData = @unserialize($sData);
                    return $xData;
                }
            } else {
                $xData = @unserialize($sData);
                return $xData;
            }
        }
        return $xDefaultOnError;
    }

    /**
     * Returns IP-rang by mask (e.c '173.194' => array('173.194.0.0', '173.194.255.255')
     *
     * @param string $sIp
     *
     * @return array
     */
    public static function IpRange($sIp)
    {
        $aIp = explode('.', $sIp) + [0, 0, 0, 0];
        $aIp = array_map('intval', $aIp);

        if ($aIp[0] < 1 || $aIp[0] > 254) {
            // error - first part cannot be empty
            return ['0.0.0.0', '255.255.255.255'];
        }

        $aIp1 = [];
        $aIp2 = [];
        foreach ($aIp as $nPart) {
            if ($nPart < 0 || $nPart >= 255) {
                $aIp1[] = 0;
            } else {
                $aIp1[] = $nPart;
            }
        }
        foreach ($aIp as $nPart) {
            if ($nPart <= 0 || $nPart > 255) {
                $aIp2[] = 255;
            } else {
                $aIp2[] = $nPart;
            }
        }
        return [implode('.', $aIp1), implode('.', $aIp2)];
    }

    /**
     * Преобразует интервал в число секунд
     *
     * @param   string  $sInterval  - значение интервала по спецификации ISO 8601 или в человекочитаемом виде
     *
     * @return  int|null
     */
    public static function ToSeconds($sInterval)
    {
        if (is_numeric($sInterval)) {
            return (int)$sInterval;
        }
        if (!is_string($sInterval)) {
            return null;
        }
        return \avadim\Chrono\Chrono::totalSeconds($sInterval);
    }

    /**
     * Add interval to defined (or current) datetime
     *
     * @param string $sDate
     * @param string $sInterval
     *
     * @return string
     */
    public static function DateTimeAdd($sDate, $sInterval = null)
    {
        if (func_num_args() == 1) {
            $sInterval = $sDate;
            $sDate = 'now';
        } elseif (null === $sDate) {
            $sDate = 'now';
        }
        return \avadim\Chrono\Chrono::dateAddFormat($sDate, $sInterval);
    }

    /**
     * Sub interval from defined (or current) datetime
     *
     * @param string $sDate
     * @param string $sInterval
     *
     * @return string
     */
    public static function DateTimeSub($sDate, $sInterval = null)
    {
        if (null === $sDate) {
            $sDate = 'now';
        } elseif (func_num_args() == 1) {
            $sInterval = $sDate;
            $sDate = 'now';
        }
        return \avadim\Chrono\Chrono::dateSubFormat($sDate, $sInterval);
    }

    /**
     * @param string $sDate1
     * @param string $sDate2
     *
     * @return int
     */
    public static function DateDiffSeconds($sDate1, $sDate2)
    {
        $oDatetime1 = date_create($sDate1);
        $oDatetime2 = date_create($sDate2);
        $nDiff = $oDatetime2->getTimestamp() - $oDatetime1->getTimestamp();
        return (int)$nDiff;
    }

    /**
     * @return string
     */
    public static function Now()
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Транслитерация строки
     *
     * @param   string      $sText
     * @param   string|bool $xLang  - true - использовать установки текущего языка/локали
     *                              - false - использовать системную локаль
     *                              - <string> - использовать установки заданного языка/локали
     *
     * @return  string
     */
    public static function Translit($sText, $xLang = true)
    {
        if ($xLang === true) {
            if (class_exists('ModuleLang', false)) {
                $xLang = \E::Module('Lang')->getLang();
            } elseif (class_exists('Config', false)) {
                $xLang = Config::get('lang.current');
            } else {
                $xLang = false;
            }
        }

        $sText = mb_strtolower($sText, 'UTF-8');
        if ($xLang !== false) {
            $aLangCharsTranslit = UserLocale::getLocale($xLang, 'translit');
            if ($aLangCharsTranslit) {
                $sText = str_replace(array_keys($aLangCharsTranslit), array_values($aLangCharsTranslit), $sText);
            }
        }
        $aConfigCharsTranslit = F::_getConfig('module.text.translit');
        if ($aConfigCharsTranslit) {
            $sText = str_replace(array_keys($aConfigCharsTranslit), array_values($aConfigCharsTranslit), $sText);
        }
        $sText = preg_replace('/[\-]{2,}/u', '-', $sText);
        $sResult = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $sText);
        // В некоторых случаях может возвращаться пустая строка
        if (!$sResult) {
            $sResult = $sText;
        }
        return $sResult;
    }

    /**
     * @param string $sText
     * @param bool   $xLang
     *
     * @return string
     */
    public static function TranslitUrl($sText, $xLang = true)
    {
        $aSymbols = [
            "_" => "-", "'" => "", "`" => "", "^" => "", " " => "-", '.' => '', ',' => '', ':' => '', '"' => '',
            '<' => '', '>' => '', '«' => '', '»' => '', '(' => '-', ')' => '-'
        ];
        $sText = mb_strtolower(static::Translit($sText, $xLang), 'utf-8');
        $sText = str_replace(array_keys($aSymbols), array_values($aSymbols), $sText);
        $sText = preg_replace('/[^a-z0-9\-]/i', '-', $sText);
        $sText = preg_replace('/\s/', '-', $sText);
        $sText = preg_replace('/[\-]{2,}/', '-', $sText);
        $sText = trim($sText, '-');

        return $sText;
    }

    /**
     * URL encoding with double encoding for slashes
     *
     * @param string $sStr
     * @param bool   $bExtra
     *
     * @return string
     */
    public static function UrlEncode($sStr, $bExtra = false)
    {
        if (!empty($sStr)) {
            if ($bExtra) {
                return str_replace(['%2F', '%5C', '-', '.', '_'], ['%252F', '%255C', '%2D', '%2E', '%5F'], urlencode($sStr));
            }
            return str_replace(['%2F', '%5C'], ['%252F', '%255C'], urlencode($sStr));
        }
        return '';
    }

    /**
     * URL encoding with double encoding for slashes
     *
     * @param string $sStr
     * @param bool   $bExtra
     *
     * @return string
     */
    public static function UrlDecode($sStr, $bExtra = false)
    {
        if (!empty($sStr)) {
            if ($bExtra) {
                return urldecode(str_replace(['%252F', '%255C', '%2D', '%2E', '%5F'], ['%2F', '%5C', '-', '.', '_'], $sStr));
            }
            return urldecode(str_replace(['%252F', '%255C'], ['%2F', '%5C'], $sStr));
        }
        return '';
    }

    /**
     * Compare string with simple pattern - symbol, '?', '*'
     *
     * @param string|array $xCheckPatterns
     * @param string       $sString
     * @param bool         $bCaseInsensitive
     * @param array        $aMatches
     *
     * @return string|bool
     */
    public static function strMatch($xCheckPatterns, $sString, $bCaseInsensitive = false, &$aMatches = [])
    {
        if ($xCheckPatterns) {
            if (is_array($xCheckPatterns)) {
                foreach($xCheckPatterns as $sPattern) {
                    $xResult = static::strMatch($sPattern, $sString, $bCaseInsensitive, $aMatches);
                    if ($xResult) {
                        return $xResult;
                    }
                }
            } else {
                $sCheckPattern = (string)$xCheckPatterns;
                if ($sCheckPattern === $sString) {
                    return $sCheckPattern;
                }
                $sPattern = $sCheckPattern;
                if (strpos($sPattern, '?') !== false) {
                    $sCharQ = '_' . uniqid() . '_';
                } else {
                    $sCharQ = '';
                }
                if (strpos($sPattern, '*') !== false) {
                    $sCharW = '_' . uniqid() . '_';
                } else {
                    $sCharW = '';
                }
                if (!$sCharQ && !$sCharW) {
                    if ($bCaseInsensitive) {
                        return (strcasecmp($sPattern, $sString) === 0) ? $sCheckPattern : false;
                    } else {
                        return (strcmp($sPattern, $sString) === 0) ? $sCheckPattern : false;
                    }
                }
                if ($sCharQ) {
                    $sPattern = str_replace('?', $sCharQ, $sPattern);
                }
                if ($sCharW) {
                    $sPattern = str_replace('*', $sCharW, $sPattern);
                }
                $sPattern = preg_quote($sPattern, '/');
                if ($sCharQ) {
                    $sPattern = str_replace($sCharQ, '(.)', $sPattern);
                }
                if ($sCharW) {
                    $sPattern = str_replace($sCharW, '(.*)', $sPattern);
                }
                $sPattern = '/^' . $sPattern . '$/' . ($bCaseInsensitive ? 'i' : '');
                if (preg_match($sPattern, $sString, $aMatches)) {
                    return $sCheckPattern;
                }
            }
        }
        return false;
    }

    /**
     * Return character by code (unicode included)
     *
     * @param $iDec
     *
     * @return string
     */
    public static function Chr($iDec)
    {
        return mb_convert_encoding('&#x' . dechex($iDec) . ';', 'UTF-8', 'HTML-ENTITIES');
    }

    /**
     * Remove "emoji" from text
     *
     * @param string $sText
     * @param string $sReplace
     *
     * @return mixed
     */
    public static function RemoveEmoji($sText, $sReplace = '')
    {
        if ($sReplace !== '') {
            $sC = self::Chr(0x1F600);
        } else {
            $sC = $sReplace;
        }

        // Match Emoticons
        $sRegexEmoticons = '/[\x{1F601}-\x{1F64F}]/u';
        $sResult = preg_replace($sRegexEmoticons, $sC, $sText);

        // Match Miscellaneous Symbols and Pictographs
        $sRegexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
        $sResult = preg_replace($sRegexSymbols, $sC, $sResult);

        // Match Transport And Map Symbols
        $sRegexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
        $sResult = preg_replace($sRegexTransport, $sC, $sResult);

        // Match Miscellaneous Symbols
        $sRegexMisc = '/[\x{2600}-\x{26FF}]/u';
        $sResult = preg_replace($sRegexMisc, $sC, $sResult);

        // Match Dingbats
        $sRegexDingbats = '/[\x{2700}-\x{27BF}]/u';
        $sResult = preg_replace($sRegexDingbats, $sC, $sResult);

        if ($sC !== $sReplace) {
            // Final replace
            $sRegexEmoticons = '/[\x{1F600}]/u';
            $sResult = preg_replace($sRegexEmoticons, $sReplace, $sResult);
        }

        return $sResult;
    }

    /**
     * Строит логарифмическое облако - расчитывает значение size в зависимости от count
     *
     * @param \alto\engine\generic\Entity[] $aCollection   Список тегов
     * @param int    $iMinSize    Минимальный размер
     * @param int    $iMaxSize    Максимальный размер
     * @param string $sCheckProp  Проверяемое свойство
     * @param string $sSetProp    Задаваемое свойство
     *
     * @return \alto\engine\generic\Entity[]
     */
    public static function MakeCloud($aCollection, $iMinSize = 1, $iMaxSize = 10, $sCheckProp = 'count', $sSetProp = 'size')
    {
        if (count($aCollection)) {
            $iSizeRange = $iMaxSize - $iMinSize;

            $nMin = null;
            $nMax = null;
            foreach ($aCollection as $oEntity) {
                $nVal = (float)$oEntity->getProp($sCheckProp);
                if (null === $nMax || $nMax < $nVal) {
                    $nMax = $nVal;
                }
                if (null === $nMin || $nMin > $nVal) {
                    $nMin = $nVal;
                }
            }
            $nMinCount = log($nMin + 1);
            $nMaxCount = log($nMax + 1);
            if ($nMaxCount === $nMinCount) {
                $nCountRange = 1;
            } else {
                $nCountRange = $nMaxCount - $nMinCount;
            }
            foreach ($aCollection as $oEntity) {
                $iTagSize = $iMinSize + (log($oEntity->getProp($sCheckProp) + 1) - $nMinCount) * ($iSizeRange / $nCountRange);
                $oEntity->setProp($sSetProp, round($iTagSize));
            }
        }
        return $aCollection;
    }

}

// EOF
