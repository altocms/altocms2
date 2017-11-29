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
 * @since   1.1.5
 */
class WidgetPeopleStats extends Widget {
    /**
     * Запуск обработки
     */
    public function exec() {

        // Статистика кто, где и т.п.
        $aPeopleStats = \E::Module('User')->getStatUsers();

        // Загружаем переменные в шаблон
        \E::Module('Viewer')->assign('aPeopleStats', $aPeopleStats);
    }
}

// EOF