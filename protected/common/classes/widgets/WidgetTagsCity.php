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
 * Обрабатывает виджет облака тегов городов юзеров
 *
 * @package widgets
 * @since   1.0
 */
class WidgetTagsCity extends Widget
{
    /**
     * Запуск обработки
     */
    public function exec()
    {
        // * Получаем города
        $aCities = \E::Module('Geo')->getGroupCitiesByTargetType('user', 20);

        // * Формируем облако тегов
        $aCities = \F::MakeCloud($aCities);

        // * Выводим в шаблон
        \E::Module('Viewer')->assign('aCityList', $aCities);
    }
}

// EOF