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
 * @package actions
 * @since 0.9
 */
class ActionCaptcha extends Action {

    /**
     * Инициализация
     *
     */
    public function init() {

        $this->setDefaultEvent('registration');
    }

    protected function registerEvent() {

        $this->addEvent('registration', 'eventRegistration');
    }

    public function eventRegistration() {

        /** @var ModuleCaptcha_EntityCaptcha $oCaptcha */
        $oCaptcha = \E::Module('Captcha')->getCaptcha();
        $oCaptcha->Display();
        exit;
    }

}

// EOF