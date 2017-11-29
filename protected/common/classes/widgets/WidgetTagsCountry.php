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
 * Обрабатывает виджет облака тегов стран юзеров
 *
 * @package widgets
 * @since   1.0
 */
class WidgetTagsCountry extends Widget
{
    public function exec()
    {
        // * Получаем страны
        $aCountries = \E::Module('Geo')->getGroupCountriesByTargetType('user', 20);

        // * Формируем облако тегов
        $aCountries = \F::MakeCloud($aCountries);

        // * Выводим в шаблон
        \E::Module('Viewer')->assign('aCountryList', $aCountries);
    }
}

// EOF