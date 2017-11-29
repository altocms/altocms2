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
 * Обрабатывает виджет облака тегов для избранного
 *
 * @package widgets
 * @since   1.0
 */
class WidgetTagsFavouriteTopic extends Widget
{
    /**
     * Запуск обработки
     */
    public function exec()
    {
        // * Пользователь авторизован?
        if ($oUserCurrent = \E::User()) {
            if (!($oUser = $this->getParam('user'))) {
                $oUser = $oUserCurrent;
            }

            // * Получаем список тегов
            $aTags = \E::Module('Favourite')->getGroupTags($oUser->getId(), 'topic', false, 70);

            // * Расчитываем логарифмическое облако тегов
            \F::MakeCloud($aTags);

            // * Устанавливаем шаблон вывода
            \E::Module('Viewer')->assign('aFavouriteTopicTags', $aTags);

            // * Получаем список тегов пользователя
            $aTags = \E::Module('Favourite')->getGroupTags($oUser->getId(), 'topic', true, 70);

            // * Расчитываем логарифмическое облако тегов
            \F::MakeCloud($aTags);

            // * Устанавливаем шаблон вывода
            \E::Module('Viewer')->assign('aFavouriteTopicUserTags', $aTags);
            \E::Module('Viewer')->assign('oFavouriteUser', $oUser);
        }
    }
}

// EOF