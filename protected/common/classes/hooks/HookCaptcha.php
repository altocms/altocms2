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
 * Регистрация хука для вывода каптчи
 *
 * @package hooks
 * @since 1.0
 */
class HookCaptcha extends Hook
{
    /**
     * Регистрируем хуки
     */
    public function registerHook()
    {
        $this->AddHookTemplate('registration_captcha', 'hookTemplateCaptcha');
    }

    /**
     * Обработка хука
     *
     * @param array $aData
     *
     * @return string
     */
    public function hookTemplateCaptcha($aData)
    {
        $sType = isset($aData['type']) ? $aData['type'] : 'registration';

        return \E::Module('Viewer')->fetch("tpls/commons/common.captcha.$sType.tpl");
    }

}

// EOF