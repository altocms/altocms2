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
 * Экшен обработки подписок пользователей
 *
 * @package actions
 * @since   1.0
 */
class ActionSubscribe extends Action {
    /**
     * Текущий пользователь
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserCurrent = null;

    /**
     * Инициализация
     *
     */
    public function init() {
        $this->oUserCurrent = \E::User();
    }

    /**
     * Регистрация евентов
     *
     */
    protected function registerEvent() {
        $this->addEventPreg('/^unsubscribe$/i', '/^\w{32}$/i', 'eventUnsubscribe');
        $this->addEvent('ajax-subscribe-toggle', 'eventAjaxSubscribeToggle');
        $this->addEvent('ajax-track-toggle', 'eventAjaxTrackToggle');
    }


    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */


    /**
     * Отписка от подписки
     */
    public function eventUnsubscribe() {
        /**
         * Получаем подписку по ключу
         */
        $oSubscribe = \E::Module('Subscribe')->getSubscribeByKey($this->getParam(0));
        if ($oSubscribe && $oSubscribe->getStatus() == 1) {
            /**
             * Отписываем пользователя
             */
            $oSubscribe->setStatus(0);
            $oSubscribe->setDateRemove(\F::Now());
            \E::Module('Subscribe')->UpdateSubscribe($oSubscribe);

            \E::Module('Message')->addNotice(\E::Module('Lang')->get('subscribe_change_ok'), null, true);
        }
        /**
         * Получаем URL для редиректа
         */
        if ((!$sUrl = \E::Module('Subscribe')->getUrlTarget($oSubscribe->getTargetType(), $oSubscribe->getTargetId()))) {
            $sUrl = R::getLink('index');
        }
        R::Location($sUrl);
    }

    /**
     * Изменение состояния подписки
     */
    public function eventAjaxSubscribeToggle() {
        /**
         * Устанавливаем формат Ajax ответа
         */
        \E::Module('Viewer')->setResponseAjax('json');
        /**
         * Получаем емайл подписки и проверяем его на валидность
         */
        $sMail = F::getRequestStr('mail');
        if ($this->oUserCurrent) {
            $sMail = $this->oUserCurrent->getMail();
        }
        if (!F::CheckVal($sMail, 'mail')) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('registration_mail_error'), \E::Module('Lang')->get('error'));
            return;
        }
        /**
         * Получаем тип объекта подписки
         */
        $sTargetType = F::getRequestStr('target_type');
        if (!\E::Module('Subscribe')->IsAllowTargetType($sTargetType)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        $sTargetId = F::getRequestStr('target_id') ? F::getRequestStr('target_id') : null;
        $iValue = F::getRequest('value') ? 1 : 0;

        $oSubscribe = null;
        /**
         * Есть ли доступ к подписке гостям?
         */
        if (!$this->oUserCurrent && !\E::Module('Subscribe')->IsAllowTargetForGuest($sTargetType)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        /**
         * Проверка объекта подписки
         */
        if (!\E::Module('Subscribe')->CheckTarget($sTargetType, $sTargetId, $iValue)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        /**
         * Если подписка еще не существовала, то создаем её
         */
        if ($oSubscribe = \E::Module('Subscribe')->AddSubscribeSimple(
            $sTargetType, $sTargetId, $sMail, $this->oUserCurrent ? $this->oUserCurrent->getId() : null
        )
        ) {
            $oSubscribe->setStatus($iValue);
            \E::Module('Subscribe')->UpdateSubscribe($oSubscribe);
            \E::Module('Message')->addNotice(\E::Module('Lang')->get('subscribe_change_ok'), \E::Module('Lang')->get('attention'));
            return;
        }
        \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
        return;
    }

    /**
     * Изменение состояния подписки
     */
    public function eventAjaxTrackToggle() {
        /**
         * Устанавливаем формат Ajax ответа
         */
        \E::Module('Viewer')->setResponseAjax('json');

        if (!$this->oUserCurrent) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        /**
         * Получаем тип объекта подписки
         */
        $sTargetType = F::getRequestStr('target_type');
        if (!\E::Module('Subscribe')->IsAllowTargetType($sTargetType)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        $sTargetId = F::getRequestStr('target_id') ? F::getRequestStr('target_id') : null;
        $iValue = F::getRequest('value') ? 1 : 0;

        $oTrack = null;
        /**
         * Проверка объекта подписки
         */
        if (!\E::Module('Subscribe')->CheckTarget($sTargetType, $sTargetId, $iValue)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        /**
         * Если подписка еще не существовала, то создаем её
         */
        if ($oTrack = \E::Module('Subscribe')->AddTrackSimple($sTargetType, $sTargetId, $this->oUserCurrent->getId())) {
            $oTrack->setStatus($iValue);
            \E::Module('Subscribe')->UpdateTrack($oTrack);
            \E::Module('Message')->addNotice(\E::Module('Lang')->get('subscribe_change_ok'), \E::Module('Lang')->get('attention'));
            return;
        }
        \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
        return;
    }
}

// EOF