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
 * Обрабатывает виджет облака тегов
 *
 * @package widgets
 * @since   1.0
 */
class WidgetTags extends Widget
{
    /**
     * Запуск обработки
     */
    public function exec()
    {
        $iLimit = C::val('widgets.tags.params.limit', 70);
        // * Получаем список тегов
        $aTags = \E::Module('Tag')->getTags([], $iLimit);

        // * Расчитываем логарифмическое облако тегов
        if ($aTags) {
            $aTags = \F::MakeCloud($aTags);

            // * Устанавливаем шаблон вывода
            \E::Module('Viewer')->assign('aTags', $aTags);
        }

        // * Теги пользователя
        if ($oUserCurrent = \E::User()) {
            $aTags = \E::Module('Topic')->getOpenTopicTags($iLimit, $oUserCurrent->getId());

            // * Расчитываем логарифмическое облако тегов
            if ($aTags) {
                $aTags = \F::MakeCloud($aTags);

                // * Устанавливаем шаблон вывода
                \E::Module('Viewer')->assign('aTagsUser', $aTags);
            }
        }
    }
}

// EOF