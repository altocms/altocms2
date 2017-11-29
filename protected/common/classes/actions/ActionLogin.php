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
 * Authorization and password reovery
 *
 * @package actions
 * @since   1.0
 */
class ActionLogin extends Action
{
    /**
     * Инициализация
     *
     */
    public function init()
    {
        $this->setDefaultEvent('default');

    }

    /**
     * Регистрируем евенты
     *
     */
    protected function registerEvent()
    {
        $this->addEvent('default', 'eventLogin');
        $this->addEvent('exit', 'eventExit');
        $this->addEvent('reminder', 'eventReminder');
        $this->addEvent('reactivation', 'eventReactivation');

        $this->addEvent('ajax-login', 'eventAjaxLogin');
        $this->addEvent('ajax-reminder', 'eventAjaxReminder');
        $this->addEvent('ajax-reactivation', 'eventAjaxReactivation');
    }

    /**
     * Ajax авторизация
     */
    public function eventAjaxLogin()
    {
        // Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // Проверяем передачу логина пароля через POST
        $sUserLogin = trim($this->getPost('login'));
        $sUserPassword = $this->getPost('password');
        $bRemember = $this->getPost('remember', false) ? true : false;
        $sUrlRedirect = F::getRequestStr('return-path');

        if (!$sUserLogin || !trim($sUserPassword)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('user_login_bad'));
            return;
        }

        $iError = null;
        // Seek user by mail or by login
        $aUserAuthData = [
            'login' => $sUserLogin,
            'email' => $sUserLogin,
            'password' => $sUserPassword,
            'error' => &$iError,
        ];
        /** @var ModuleUser_EntityUser $oUser */
        $oUser = \E::Module('User')->getUserAuthorization($aUserAuthData);
        if ($oUser) {
            if ($iError) {
                switch($iError) {
                    case ModuleUser::USER_AUTH_ERR_NOT_ACTIVATED:
                        $sErrorMessage = \E::Module('Lang')->get(
                            'user_not_activated',
                            ['reactivation_path' => R::getLink('login') . 'reactivation']
                        );
                        break;
                    case ModuleUser::USER_AUTH_ERR_IP_BANNED:
                        $sErrorMessage = \E::Module('Lang')->get('user_ip_banned');
                        break;
                    case ModuleUser::USER_AUTH_ERR_BANNED_DATE:
                        $sErrorMessage = \E::Module('Lang')->get('user_banned_before', array('date' => $oUser->GetBanLine()));
                        break;
                    case ModuleUser::USER_AUTH_ERR_BANNED_UNLIM:
                        $sErrorMessage = \E::Module('Lang')->get('user_banned_unlim');
                        break;
                    default:
                        $sErrorMessage = \E::Module('Lang')->get('user_login_bad');
                }
                \E::Module('Message')->addErrorSingle($sErrorMessage);
                return;
            }

            // Авторизуем
            \E::Module('User')->authorization($oUser, $bRemember);

            // Определяем редирект
            //$sUrl = \C::Get('module.user.redirect_after_login');
            if (!$sUrlRedirect) {
                $sUrlRedirect = C::get('path.root.url');
            }

            \E::Module('Viewer')->assignAjax('sUrlRedirect', $sUrlRedirect);
            return;
        }

        \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('user_login_bad'));
    }

    /**
     * Повторный запрос активации
     */
    public function eventReactivation() {

        if (\E::User()) {
            R::Location(\C::get('path.root.url') . '/');
        }

        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('reactivation'));
    }

    /**
     *  Ajax повторной активации
     */
    public function eventAjaxReactivation()
    {
        \E::Module('Viewer')->setResponseAjax('json');

        /** @var ModuleUser_EntityUser $oUser */
        if ((\F::CheckVal(\F::getRequestStr('mail'), 'mail') && $oUser = \E::Module('User')->getUserByMail(\F::getRequestStr('mail')))) {
            if ($oUser->getActivate()) {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('registration_activate_error_reactivate'));
                return;
            } else {
                $oUser->setActivationKey(\F::RandomStr());
                if (\E::Module('User')->Update($oUser)) {
                    \E::Module('Message')->addNotice(\E::Module('Lang')->get('reactivation_send_link'));
                    \E::Module('Notify')->sendReactivationCode($oUser);
                    return;
                }
            }
        }

        \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('password_reminder_bad_email'));
    }

    /**
     * Обрабатываем процесс залогинивания
     * По факту только отображение шаблона, дальше вступает в дело Ajax
     *
     */
    public function eventLogin()
    {
        // Если уже авторизирован
        if (\E::User()) {
            R::Location(\C::get('path.root.url') . '/');
        }
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('login'));
    }

    /**
     * Обрабатываем процесс разлогинивания
     *
     */
    public function eventExit()
    {
        \E::Module('Security')->validateSendForm();
        \E::Module('User')->Logout();

        $iShowTime = \C::val('module.user.logout.show_exit', 3);
        $sRedirect = \C::get('module.user.logout.redirect');
        if (!$sRedirect) {
            if (isset($_SERVER['HTTP_REFERER']) && F::File_IsLocalUrl($_SERVER['HTTP_REFERER'])) {
                $sRedirect = $_SERVER['HTTP_REFERER'];
            }
        }

        /**
         * issue #104, {@see https://github.com/altocms/altocms/issues/104}
         * Установим в lgp (last_good_page) хэш имени страницы с постфиксом "logout". Такая
         * кука будет означать, что на этой странице пользователь вышел с сайта. Время 60 -
         * заранее достаточное время, что бы произошел редирект на страницу HTTP_REFERER. Если
         * же эта страница выпадет в 404 то в экшене ActionError уйдем на главную, поскольку
         * эта страница недоступна стала после выхода с сайта, а до этого была вполне ничего.
         */

        if ($iShowTime) {
            $sUrl = \F::realUrl($sRedirect);
            $sReferrer = \C::get('path.root.web'). \R::getAction() . "/" . \R::getControllerAction() .'/?security_key=' . \F::getRequest('security_key', '');
            \E::Module('Session')->setCookie('lgp', md5($sReferrer . 'logout'), 60);
            \E::Module('Viewer')->setHtmlHeadTag('meta', array('http-equiv' => 'Refresh', 'Content' => $iShowTime . '; url=' . $sUrl));
        } elseif ($sRedirect) {
            // Если установлена пользовтаельская страница выхода, то считаем,
            // что она без ошибки и смело не нее редиректим, в других случаях
            // возможна 404
            if (!\C::get('module.user.logout.redirect')) {
                \E::Module('Session')->setCookie('lgp', md5(\F::RealUrl($sRedirect) . 'logout'), 60);
            }
            R::Location($sRedirect);
            exit;
        } else {
            // \E::Module('Viewer')->Assign('bRefreshToHome', true);
            // Время показа страницы выхода не задано, поэтому просто редирект
            R::Location(\C::get('path.root.web'));
            exit;
        }
    }

    /**
     * Ajax запрос на восстановление пароля
     */
    public function eventAjaxReminder()
    {
        // Устанвливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        $this->_eventRecovery(true);
    }

    /**
     * Обработка напоминания пароля, подтверждение смены пароля
     *
     * @return string|null
     */
    public function eventReminder() {

        if (\E::isUser()) {
            // Для авторизованного юзера восстанавливать нечего
            \R::Location('/');
        } else {
            // Устанавливаем title страницы
            \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('password_reminder'));

            $this->_eventRecovery(false);
        }
    }

    protected function _eventRecovery($bAjax = false) {

        if ($this->isPost()) {
            // Was POST request
            $sEmail = F::getRequestStr('mail');

            // Пользователь с таким емайлом существует?
            if ($sEmail && (\F::CheckVal($sEmail, 'mail'))) {
                if ($this->_eventRecoveryRequest($sEmail)) {
                    if (!$bAjax) {
                        \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('password_reminder_send_link'));
                    }
                    return;
                }
            }
            \E::Module('Message')->addError(\E::Module('Lang')->get('password_reminder_bad_email'), \E::Module('Lang')->get('error'));
        } elseif ($sRecoveryCode = $this->getParam(0)) {
            // Was recovery code in GET
            if (\F::CheckVal($sRecoveryCode, 'md5')) {

                // Проверка кода подтверждения
                if ($this->_eventRecoverySend($sRecoveryCode)) {
                    return null;
                }
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('password_reminder_bad_code_txt'), \E::Module('Lang')->get('password_reminder_bad_code'));
                if (!$bAjax) {
                    return R::redirect('error');
                }
                return;
            }
        }
    }

    protected function _eventRecoveryRequest($sMail)
    {
        if ($oUser = \E::Module('User')->getUserByMail($sMail)) {

            // Формируем и отправляем ссылку на смену пароля
            /** @var ModuleUser_EntityReminder $oReminder */
            $oReminder = \E::getEntity('User_Reminder');
            $oReminder->setCode(\F::RandomStr(32));
            $oReminder->setDateAdd(\F::Now());
            $oReminder->setDateExpire(date('Y-m-d H:i:s', time() + \C::val('module.user.pass_recovery_delay', 60 * 60 * 24 * 7)));
            $oReminder->setDateUsed(null);
            $oReminder->setIsUsed(0);
            $oReminder->setUserId($oUser->getId());
            if (\E::Module('User')->addReminder($oReminder)) {
                \E::Module('Notify')->sendReminderCode($oUser, $oReminder);
                \E::Module('Message')->addNotice(\E::Module('Lang')->get('password_reminder_send_link'));
                return true;
            }
        }
        return false;
    }

    protected function _eventRecoverySend($sRecoveryCode) {

        /** @var ModuleUser_EntityReminder $oReminder */
        if ($oReminder = \E::Module('User')->getReminderByCode($sRecoveryCode)) {
            /** @var ModuleUser_EntityUser $oUser */
            if ($oReminder->IsValid() && $oUser = \E::Module('User')->getUserById($oReminder->getUserId())) {
                $sNewPassword = F::RandomStr(7);
                $oUser->setPassword($sNewPassword, true);
                if (\E::Module('User')->Update($oUser)) {

                    // Do logout of current user
                    \E::Module('User')->Logout();

                    // Close all sessions of this user
                    \E::Module('User')->CloseAllSessions($oUser);

                    $oReminder->setDateUsed(\F::Now());
                    $oReminder->setIsUsed(1);
                    \E::Module('User')->UpdateReminder($oReminder);
                    \E::Module('Notify')->sendReminderPassword($oUser, $sNewPassword);
                    $this->setTemplateAction('reminder_confirm');

                    if (($sUrl = F::GetPost('return_url')) || ($sUrl = F::GetPost('return-path'))) {
                        \E::Module('Viewer')->assign('return-path', $sUrl);
                    }
                    return true;
                }
            }
        }
        return false;
    }

}

// EOF