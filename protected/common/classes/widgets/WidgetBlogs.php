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
 * Обработка виджета с рейтингом блогов
 *
 * @package widgets
 * @since   1.0
 */
class WidgetBlogs extends Widget {
    /**
     * Запуск обработки
     */
    public function exec() {

        // * Получаем список блогов
        if ($aResult = \E::Module('Blog')->getBlogsRating(1, \C::get('widgets.blogs.params.limit'))) {
            $aVars = array('aBlogs' => $aResult['collection']);

            // * Формируем результат в виде шаблона и возвращаем
            $sTextResult = \E::Module('Viewer')->fetchWidget('blogs_top.tpl', $aVars);
            \E::Module('Viewer')->assign('sBlogsTop', $sTextResult);
        }
    }
}

// EOF