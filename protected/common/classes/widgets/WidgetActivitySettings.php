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
 * Виджет настройки ленты активности (события)
 *
 * @package blocks
 * @since   1.0
 */
class WidgetActivitySettings extends Widget
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
            \E::Module('Viewer')->assign('aStreamTypesList', $aTypesList ? $aTypesList : array());
        }
    }
}

// EOF