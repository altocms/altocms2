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
 * Блок настройки списка пользователей в ленте
 *
 * @package widgets
 * @since   1.0
 */
class WidgetUserfeedUsers extends Widget
{
    /**
     * Запуск обработки
     */
    public function exec() {
        /**
         * Пользователь авторизован?
         */
        if ($oUserCurrent = \E::User()) {
            /**
             * Получаем необходимые переменные и передаем в шаблон
             */
            $aUserSubscribes = \E::Module('Userfeed')->getUserSubscribes($oUserCurrent->getId());
            $aFriends = \E::Module('User')->getUsersFriend($oUserCurrent->getId());
            \E::Module('Viewer')->assign('aUserfeedSubscribedUsers', $aUserSubscribes['users']);
            \E::Module('Viewer')->assign('aUserfeedFriends', $aFriends['collection']);
        }
    }

}

// EOF