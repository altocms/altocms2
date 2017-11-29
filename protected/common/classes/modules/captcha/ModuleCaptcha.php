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
 * Модуль для статических страниц
 *
 * @package modules.captcha
 * @since   1.1
 */
class ModuleCaptcha extends Module {

    const ERR_KEYSTRING_EMPTY = 1;
    const ERR_KEYSTRING_NOT_STR = 2;
    const ERR_KEYSTRING_NOT_DEFINED = 3;
    const ERR_KEYSTRING_NOT_VALID = 4;
    const ERR_KEYSTRING_UNKNOWN = 9;

    protected $sKeyName = 'captcha_keystring';

    public function init() {

    }

    /**
     * @return string
     */
    public function getKeyName() {

        return $this->sKeyName;
    }

    /**
     * @param string $sKeyname
     */
    public function setKeyName($sKeyname) {

        $this->sKeyName = $sKeyname;
    }

    /**
     * @param string $sKeyName
     *
     * @return ModuleCaptcha_EntityCaptcha
     */
    public function getCaptcha($sKeyName = null) {

        /** @var ModuleCaptcha_EntityCaptcha $oCaptcha */
        $oCaptcha = \E::getEntity('Captcha_Captcha');
        if (!$sKeyName) {
            $sKeyName = $this->sKeyName;
        }
        \E::Module('Session')->set($sKeyName, $oCaptcha->getKeyString());

        return $oCaptcha;
    }

    /**
     * @param string $sKeyString
     * @param string $sKeyName
     *
     * @return int
     */
    public function Verify($sKeyString, $sKeyName = null) {

        $iResult = 0;
        if (empty($sKeyString)) {
            $iResult = static::ERR_KEYSTRING_EMPTY;
        } elseif (!is_string($sKeyString)) {
            $iResult = static::ERR_KEYSTRING_NOT_STR;
        } else {
            if (!$sKeyName) {
                $sKeyName = $this->sKeyName;
            }
            $sSavedString = \E::Module('Session')->get($sKeyName);

            // issue#342. При регистрации метод вызывается несколько раз в том
            // числе и при проверки формы аяксом при первой проверке значение
            // капчи сбрасывается и в дальнейшем проверка не проходит. Сброс капчи
            // теперь происходит только после успешной регистрации
            // \E::Module('Session')->drop($sKeyName);

            if (empty($sSavedString) || !is_string($sSavedString)) {
                $iResult = static::ERR_KEYSTRING_NOT_DEFINED;
            } elseif ($sSavedString != $sKeyString) {
                $iResult = static::ERR_KEYSTRING_NOT_VALID;
            }
        }
        return $iResult;
    }
}

// EOF