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
 * Регистрация хука для вывода меню страниц
 *
 */
class HookPage extends Hook {
    public function registerHook() {
        $this->AddHook('template_main_menu_item', 'Menu');
    }

    public function Menu() {
        $aPages = \E::Module('Page')->getPages(array('pid' => null, 'main' => 1, 'active' => 1));
        \E::Module('Viewer')->assign('aPagesMain', $aPages);
        return \E::Module('Viewer')->fetch('menus/menu.main_pages.tpl');
    }
}

// EOF