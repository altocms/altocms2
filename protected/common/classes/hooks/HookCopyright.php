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
 * Регистрация хука для вывода ссылки копирайта
 *
 * @package hooks
 * @since 1.0
 */
class HookCopyright extends Hook
{
    /**
     * Регистрируем хуки
     */
    public function registerHook()
    {
        $this->AddHook('template_copyright', 'CopyrightLink', __CLASS__, -100);
    }

    /**
     * Обработка хука копирайта
     *
     * @return string
     */
    public function copyrightLink()
    {
        // * Выводим везде, кроме страницы списка блогов и списка всех комментов
        return '&copy; Powered by <a href="http://altocms.ru">Alto CMS</a>';
    }
}

// EOF