<?php

/**
 * HookAdmin
 * Файл хука плагина Rating
 *
 * @author      Андрей Воронов <andreyv@gladcode.ru>
 *              Является частью плагина Rating
 * @version     0.0.1 от 30.01.2015 22:45
 */
class PluginRating_HookAdmin extends Hook {
    /**
     * Регистрация хуков
     */
    public function registerHook() {
        if (\E::isAdmin()) {
            $this->AddHook('template_admin_menu_settings', 'AdminMenuInject');
        }
    }

    public function AdminMenuInject() {

        return E::Module('Viewer')->fetch(Plugin::GetTemplatePath('rating') . '/tpls/inject.admin.menu.tpl');
    }

}
