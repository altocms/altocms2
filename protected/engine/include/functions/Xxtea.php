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
 * Encryption/Decryption functions for Alto CMS
 */
class AltoFunc_Xxtea {

    /**
     * Ключ криптования по умолчанию
     *
     * @return mixed|null
     */
    protected static function _defaultKey() {

        return F::_getConfig('security.salt_auth', __DIR__);
    }

    /**
     * Шифрование строки
     *
     * @param string          $sData
     * @param string|null     $sKey
     *
     * @return string
     */
    public static function Encrypt($sData, $sKey = null) {

        if (!$sKey) {
            $sKey = static::_defaultKey();
        }
        return xxtea_encrypt($sData, $sKey);
    }

    /**
     * Дешифрование строки
     *
     * @param string          $sData
     * @param string|null     $sKey
     *
     * @return string
     */
    public static function Decrypt($sData, $sKey = null) {

        if (!$sKey) {
            $sKey = static::_defaultKey();
        }
        return xxtea_decrypt($sData, $sKey);
    }

    /**
     * Шифрование и "безопасное" кодирование для передачи в URL (RFC 3986)
     *
     * @param string          $sData
     * @param string|null     $sKey
     *
     * @return string
     */
    public static function Encode($sData, $sKey = null) {

        return rawurlencode(base64_encode(static::Encrypt($sData, $sKey)));
    }

    /**
     * Декодирование и дешифрование строки
     *
     * @param string          $sData
     * @param string|null     $sKey
     *
     * @return string
     */
    public static function Decode($sData, $sKey = null) {

        return static::Decrypt(base64_decode(rawurldecode($sData)), $sKey);
    }

}

// EOF