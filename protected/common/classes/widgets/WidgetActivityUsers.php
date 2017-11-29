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
 * Виджет выбора пользователей для чтения в ленте активности
 *
 * @package blocks
 * @since   1.0
 */
class WidgetActivityUsers extends Widget
{
    /**
     * Запуск обработки
     */
    public function exec() {

        // * пользователь авторизован?
        if ($oUserCurrent = \E::User()) {
            // * Получаем и прогружаем необходимые переменные в шаблон
            $aUserSubscribes = \E::Module('Stream')->getUserSubscribes($oUserCurrent->getId());
            \E::Module('Viewer')->assign('aStreamSubscribedUsers', $aUserSubscribes ? $aUserSubscribes : array());

            // issue#449, список друзей пользователя не передавался в шаблон
            $aStreamFriends = \E::Module('User')->getUsersFriend($oUserCurrent->getId());
            \E::Module('Viewer')->assign('aStreamFriends', $aStreamFriends['collection']);
        }

    }
}

// EOF