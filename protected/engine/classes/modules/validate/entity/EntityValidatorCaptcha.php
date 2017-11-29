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

/**
 * Валидатор каптчи (число с картинки)
 *
 * @package engine.modules.validate
 * @since   1.0
 */
class ModuleValidate_EntityValidatorCaptcha extends ModuleValidate_EntityValidator {
    /**
     * Допускать или нет пустое значение
     *
     * @var bool
     */
    public $allowEmpty = false;

    /**
     * Запуск валидации
     *
     * @param mixed $sValue    Данные для валидации
     *
     * @return bool|string
     */
    public function validate($sValue) {

        if ($this->allowEmpty && $this->isEmpty($sValue)) {
            return true;
        }
        if (\E::Module('Captcha')->Verify(mb_strtolower($sValue)) !== 0) {
            return $this->getMessage(\E::Module('Lang')->get('validate_captcha_not_valid', null, false), 'msg');
        }
        return \E::Module('Captcha')->Verify(mb_strtolower($sValue)) === 0;
    }
}

// EOF