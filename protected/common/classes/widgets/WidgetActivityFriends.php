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
 * Виджет настройки ленты активности (друзья)
 *
 * @package widgets
 * @since   1.0
 */
class WidgetActivityFriends extends Widget
{
    /**
     * Запуск обработки
     */
    public function exec()
    {
        // * пользователь авторизован?
        if ($oUserCurrent = \E::User()) {
            // * Получаем и прогружаем необходимые переменные в шаблон
            $aFriends = \E::Module('User')->getUsersFriend($oUserCurrent->getId());
            if ($aFriends) {
                \E::Module('Viewer')->assign('aStreamFriends', $aFriends['collection']);
            }
        }
    }
}

// EOF