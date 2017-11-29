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
 * Блок настройки ленты активности (LS compatibility)
 *
 * @package blocks
 * @since   1.0
 */
class WidgetStreamConfig extends Widget
{
    /**
     * Запуск обработки
     */
    public function exec()
    {
        // * пользователь авторизован?
        if ($oUserCurrent = \E::User()) {

            // * Получаем и прогружаем необходимые переменные в шаблон
            $aTypesList = \E::Module('Stream')->getTypesList($oUserCurrent->getId());
            $aUserSubscribes = \E::Module('Stream')->getUserSubscribes($oUserCurrent->getId());
            $aFriends = \E::Module('User')->getUsersFriend($oUserCurrent->getId());

            \E::Module('Viewer')->assign('aStreamTypesList', $aTypesList);
            \E::Module('Viewer')->assign('aStreamSubscribedUsers', $aUserSubscribes);
            \E::Module('Viewer')->assign('aStreamFriends', $aFriends['collection']);
        }
    }
}

// EOF