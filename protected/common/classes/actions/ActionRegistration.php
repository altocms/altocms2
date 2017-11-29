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
 * Экшен обработки регистрации
 *
 * @package actions
 */
class ActionRegistration extends Action
{
    /**
     * Инициализация
     *
     */
    public function init()
    {
        //  Проверяем аторизован ли юзер
        if (\E::isAuth()) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('registration_is_authorization'), \E::Module('Lang')->get('attention')
            );
            return R::redirect('error');
        }

        //  Если включены инвайты то перенаправляем на страницу регистрации по инвайтам
        if (!\E::isAuth() && \C::get('general.reg.invite')
            && !in_array(R::getControllerAction(), ['invite', 'activate', 'confirm'], true) && !$this->CheckInviteRegister()) {
            return R::redirect('registration', 'invite');
        }

        $this->setDefaultEvent('index');
        //  Устанавливаем title страницы
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('registration'));
    }

    /**
     * Регистрируем евенты
     *
     */
    protected function registerEvent()
    {
        $this->addEvent('default', 'eventDefault');
        $this->addEvent('confirm', 'eventConfirm');
        $this->addEvent('activate', 'eventActivate');
        $this->addEvent('invite', 'eventInvite');

        $this->addEvent('ajax-validate-fields', 'eventAjaxValidateFields');
        $this->addEvent('ajax-registration', 'eventAjaxRegistration');
    }


    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    /**
     * Ajax валидация формы регистрации
     */
    public function eventAjaxValidateFields() {

        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // * Создаем объект пользователя и устанавливаем сценарий валидации
        /** @var ModuleUser_EntityUser $oUser */
        $oUser = \E::getEntity('ModuleUser_EntityUser');
        $oUser->_setValidateScenario('registration');

        //  Пробегаем по переданным полям/значениям и валидируем их каждое в отдельности
        $aFields = F::getRequest('fields');
        if (is_array($aFields)) {
            foreach ($aFields as $aField) {
                if (isset($aField['field'], $aField['value'])) {
                    \HookManager::run('registration_validate_field', ['aField' => &$aField, 'oUser' => &$oUser]);

                    $sField = $aField['field'];
                    $sValue = $aField['value'];
                    //  Список полей для валидации
                    if ($sField === 'login') {
                        $oUser->setLogin($sValue);
                    } elseif ($sField === 'mail') {
                        $oUser->setMail($sValue);
                    } elseif ($sField === 'captcha') {
                        $oUser->setCaptcha($sValue);
                    } elseif ($sField === 'password') {
                        $oUser->setPassword($sValue);
                        if (isset($aField['params']['login'])) {
                            $oUser->setLogin($aField['params']['login']);
                        }
                    } elseif ($sField === 'password_confirm') {
                        $oUser->setPasswordConfirm($sValue);
                        $oUser->setPassword(
                            isset($aField['params']['password']) ? $aField['params']['password'] : null
                        );
                    } else {
                        continue;
                    }

                    //  Валидируем поле
                    $oUser->_validate([$sField], false);
                }
            }
        }
        //  Возникли ошибки?
        if ($oUser->_hasValidateErrors()) {
            //  Получаем ошибки
            \E::Module('Viewer')->assignAjax('aErrors', $oUser->_getValidateErrors());
        }
    }

    /**
     * Обработка Ajax регистрации
     */
    public function eventAjaxRegistration() {

        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        \E::Module('Security')->validateSendForm();

        // * Создаем объект пользователя и устанавливаем сценарий валидации
        /** @var ModuleUser_EntityUser $oUser */
        $oUser = \E::getEntity('ModuleUser_EntityUser');
        $oUser->_setValidateScenario('registration');

        // * Заполняем поля (данные)
        $oUser->setLogin($this->getPost('login'));
        $oUser->setMail($this->getPost('mail'));
        $oUser->setPassword($this->getPost('password'));
        $oUser->setPasswordConfirm($this->getPost('password_confirm'));
        $oUser->setCaptcha($this->getPost('captcha'));
        $oUser->setDateRegister(\F::Now());
        $oUser->setIpRegister(\F::GetUserIp());

        // * Если используется активация, то генерим код активации
        if (\C::get('general.reg.activation')) {
            $oUser->setActivate(0);
            $oUser->setActivationKey(\F::RandomStr());
        } else {
            $oUser->setActivate(1);
            $oUser->setActivationKey(null);
        }
        \HookManager::run('registration_validate_before', array('oUser' => $oUser));

        // * Запускаем валидацию
        if ($oUser->_validate()) {
            // Сбросим капчу // issue#342.
            \E::Module('Session')->drop(\E::Module('Captcha')->getKeyName());

            \HookManager::run('registration_validate_after', array('oUser' => $oUser));
            $oUser->setPassword($oUser->getPassword(), true);
            if ($this->_addUser($oUser)) {
                \HookManager::run('registration_after', array('oUser' => $oUser));

                // * Подписываем пользователя на дефолтные события в ленте активности
                \E::Module('Stream')->SwitchUserEventDefaultTypes($oUser->getId());

                // * Если юзер зарегистрировался по приглашению то обновляем инвайт
                if (\C::get('general.reg.invite') && ($oInvite = \E::Module('User')->getInviteByCode($this->GetInviteRegister()))) {
                    $oInvite->setUserToId($oUser->getId());
                    $oInvite->setDateUsed(\F::Now());
                    $oInvite->setUsed(1);
                    \E::Module('User')->UpdateInvite($oInvite);
                }

                // * Если стоит регистрация с активацией то проводим её
                if (\C::get('general.reg.activation')) {
                    // * Отправляем на мыло письмо о подтверждении регистрации
                    \E::Module('Notify')->sendRegistrationActivate($oUser, F::getRequestStr('password'));
                    \E::Module('Viewer')->assignAjax('sUrlRedirect', R::getLink('registration') . 'confirm/');
                } else {
                    \E::Module('Notify')->sendRegistration($oUser, F::getRequestStr('password'));
                    $oUser = \E::Module('User')->getUserById($oUser->getId());

                    // * Сразу авторизуем
                    \E::Module('User')->Authorization($oUser, false);
                    $this->DropInviteRegister();

                    // * Определяем URL для редиректа после авторизации
                    $sUrl = \C::get('module.user.redirect_after_registration');
                    if (\F::getRequestStr('return-path')) {
                        $sUrl = F::getRequestStr('return-path');
                    }
                    \E::Module('Viewer')->assignAjax('sUrlRedirect', $sUrl ? $sUrl : \C::get('path.root.url'));
                    \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('registration_ok'));
                }
            } else {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));
                return;
            }
        } else {
            // * Получаем ошибки
            \E::Module('Viewer')->assignAjax('aErrors', $oUser->_getValidateErrors());
        }
    }

    /**
     * Add new user
     *
     * @param ModuleUser_EntityUser $oUser
     *
     * @return bool|ModuleUser_EntityUser
     */
    protected function _addUser($oUser)
    {
        return \E::Module('User')->add($oUser);
    }

    /**
     * Показывает страничку регистрации
     * Просто вывод шаблона
     */
    public function eventDefault()
    {

    }

    /**
     * Обрабатывает активацию аккаунта
     */
    public function eventActivate() {

        $bError = false;

        // * Проверяет передан ли код активации
        $sActivateKey = $this->getParam(0);
        if (!F::CheckVal($sActivateKey, 'md5')) {
            $bError = true;
        }

        // * Проверяет верный ли код активации
        if (!($oUser = \E::Module('User')->getUserByActivationKey($sActivateKey))) {
            $bError = true;
        }

        // * User is already activated
        if ($oUser && $oUser->isActivated()) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('registration_activate_error_reactivate'), \E::Module('Lang')->get('error')
            );
            return R::redirect('error');
        }

        // * Если что то не то
        if ($bError) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('registration_activate_error_code'), \E::Module('Lang')->get('error')
            );
            return R::redirect('error');
        }

        // * Активируем
        if ($this->_activateUser($oUser)) {
            $this->DropInviteRegister();
            \E::Module('Viewer')->assign('bRefreshToHome', true);
            \E::Module('User')->Authorization($oUser, false);
            return;
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));
            return R::redirect('error');
        }
    }

    /**
     * Activate user
     *
     * @param ModuleUser_EntityUser $oUser
     *
     * @return bool
     */
    protected function _activateUser($oUser) {

        return \E::Module('User')->Activate($oUser);
    }

    /**
     * Обработка кода приглашения при включеном режиме инвайтов
     *
     */
    public function eventInvite() {

        if (!Config::get('general.reg.invite')) {
            return parent::eventNotFound();
        }
        //  Обработка отправки формы с кодом приглашения
        if (\F::isPost('submit_invite')) {
            //  проверяем код приглашения на валидность
            if ($this->CheckInviteRegister()) {
                $sInviteCode = $this->GetInviteRegister();
            } else {
                $sInviteCode = trim(\F::getRequestStr('invite_code'));
            }
            $oInvite = \E::Module('User')->getInviteByCode($sInviteCode);
            if ($oInvite) {
                if (!$this->CheckInviteRegister()) {
                    \E::Module('Session')->set('invite_code', $oInvite->getCode());
                }
                return R::redirect('registration');
            } else {
                \E::Module('Message')->addError(\E::Module('Lang')->get('registration_invite_code_error'), \E::Module('Lang')->get('error'));
            }
        }
    }

    /**
     * Пытается ли юзер зарегистрироваться с помощью кода приглашения
     *
     * @return bool
     */
    protected function CheckInviteRegister() {

        if (\E::Module('Session')->get('invite_code')) {
            return true;
        }
        return false;
    }

    /**
     * Вожвращает код приглашения из сессии
     *
     * @return string
     */
    protected function GetInviteRegister() {

        return \E::Module('Session')->get('invite_code');
    }

    /**
     * Удаляет код приглашения из сессии
     */
    protected function DropInviteRegister() {

        if (\C::get('general.reg.invite')) {
            \E::Module('Session')->drop('invite_code');
        }
    }

    /**
     * Просто выводит шаблон для подтверждения регистрации
     *
     */
    public function eventConfirm() {
    }

}

// EOF