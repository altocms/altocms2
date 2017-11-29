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
 * @package actions
 * @since   1.0
 */
class ActionBlogs extends Action {
    /**
     * Главное меню
     *
     * @var string
     */
    protected $sMenuHeadItemSelect = 'blogs';

    /**
     * Инициализация
     */
    public function init()
    {
        // * Загружаем в шаблон JS текстовки
        \E::Module('Lang')->addLangJs(
            array(
                 'blog_join', 'blog_leave'
            )
        );
    }

    /**
     * Регистрируем евенты
     */
    protected function registerEvent()
    {
        $this->addEventPreg('/^personal$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventShowBlogsPersonal');
        $this->addEventPreg('/^(page([1-9]\d{0,5}))?$/i', 'eventShowBlogs');
        $this->addEventPreg('/^ajax-search$/i', 'eventAjaxSearch');
    }


    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    /**
     * Поиск блогов по названию
     */
    public function eventAjaxSearch()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // * Получаем из реквеста первые буквы блога
        if ($sTitle = F::getRequestStr('blog_title')) {
            $sTitle = str_replace('%', '', $sTitle);
        }
        if (!$sTitle) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));
            return;
        }

        // * Ищем блоги
        if (\F::getRequestStr('blog_type') === 'personal') {
            $aFilter = array('include_type' => 'personal');
        } else {
            $aFilter = array(
                'include_type' => \E::Module('Blog')->getAllowBlogTypes(\E::User(), 'list', true),
            );
            $aFilter['exclude_type'] = 'personal';
        }
        $aFilter['title'] = "%{$sTitle}%";
        $aFilter['order'] = array('blog_title' => 'asc');

        $aResult = \E::Module('Blog')->getBlogsByFilter($aFilter, 1, 100);

        // * Формируем и возвращаем ответ
        $aVars = array(
            'aBlogs'          => $aResult['collection'],
            'oUserCurrent'    => E::User(),
            'sBlogsEmptyList' => \E::Module('Lang')->get('blogs_search_empty'),
        );
        \E::Module('Viewer')->assignAjax('sText', \E::Module('Viewer')->fetch('commons/common.blog_list.tpl', $aVars));
    }

    public function eventIndex()
    {
        $this->eventShowBlogs();
    }

    /**
     * Отображение списка блогов
     */
    public function eventShowBlogs()
    {
        // * По какому полю сортировать
        $sOrder = F::getRequestStr('order', 'blog_rating');

        // * В каком направлении сортировать
        $sOrderWay = F::getRequestStr('order_way', 'desc');

        $aAllowBlogTypes = \E::Module('Blog')->getAllowBlogTypes(\E::User(), 'list', true);

        // * Фильтр выборки блогов
        $aFilter = [];
        if ($sIncludeType = F::getRequestStr('include_type')) {
            $aFilter['include_type'] = array_intersect(array_merge($aAllowBlogTypes, ['personal']), F::Array_Str2Array($sIncludeType));
        }
        if ($sExcludeType = F::getRequestStr('exclude_type')) {
            $aFilter['exclude_type'] = F::Array_Str2Array($sExcludeType);
        }

        if (!$aFilter) {
            $aFilter = array(
                'include_type' => $aAllowBlogTypes,
            );
        }

        if ($sOrder == 'blog_title') {
            $aFilter['order'] = array('blog_title' => $sOrderWay);
        } else {
            $aFilter['order'] = array($sOrder => $sOrderWay, 'blog_title' => 'asc');
        }

        // * Передан ли номер страницы
        $iPage = preg_match('/^\d+$/i', $this->getEventMatch(2)) ? $this->getEventMatch(2) : 1;

        // * Получаем список блогов
        $aResult = \E::Module('Blog')->getBlogsByFilter(
            $aFilter,
            $iPage, \C::get('module.blog.per_page')
        );
        $aBlogs = $aResult['collection'];

        // * Формируем постраничность
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.blog.per_page'), \C::get('pagination.pages.count'),
            R::getLink('blogs'), ['order' => $sOrder, 'order_way' => $sOrderWay]
        );

        //  * Загружаем переменные в шаблон
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aBlogs', $aBlogs);
        \E::Module('Viewer')->assign('sBlogOrder', htmlspecialchars($sOrder));
        \E::Module('Viewer')->assign('sBlogOrderWay', htmlspecialchars($sOrderWay));
        \E::Module('Viewer')->assign('sBlogOrderWayNext', ($sOrderWay == 'desc' ? 'asc' : 'desc'));
        \E::Module('Viewer')->assign('sShow', 'collective');
        \E::Module('Viewer')->assign('sBlogsRootPage', R::getLink('blogs'));

        // * Устанавливаем title страницы
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('blog_menu_all_list'));

        // * Устанавливаем шаблон вывода
        $this->setTemplateAction('index');
    }

    /**
     * Отображение списка персональных блогов
     */
    public function eventShowBlogsPersonal()
    {
        // * По какому полю сортировать
        $sOrder = F::getRequestStr('order', 'blog_title');

        // * В каком направлении сортировать
        $sOrderWay = F::getRequestStr('order_way', 'desc');

        // * Фильтр поиска блогов
        $aFilter = [
            'include_type' => 'personal'
        ];

        // * Передан ли номер страницы
        $iPage = preg_match('/^\d+$/i', $this->getParamEventMatch(0, 2)) ? $this->getParamEventMatch(0, 2) : 1;

        // * Получаем список блогов
        $aResult = \E::Module('Blog')->getBlogsByFilter($aFilter, [$sOrder => $sOrderWay], $iPage, \C::get('module.blog.per_page'));
        $aBlogs = $aResult['collection'];

        // * Формируем постраничность
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.blog.per_page'), \C::get('pagination.pages.count'),
            R::getLink('blogs') . 'personal/', ['order' => $sOrder, 'order_way' => $sOrderWay]
        );

        // * Загружаем переменные в шаблон
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aBlogs', $aBlogs);
        \E::Module('Viewer')->assign('sBlogOrder', htmlspecialchars($sOrder));
        \E::Module('Viewer')->assign('sBlogOrderWay', htmlspecialchars($sOrderWay));
        \E::Module('Viewer')->assign('sBlogOrderWayNext', ($sOrderWay == 'desc' ? 'asc' : 'desc'));
        \E::Module('Viewer')->assign('sShow', 'personal');
        \E::Module('Viewer')->assign('sBlogsRootPage', R::getLink('blogs') . 'personal/');

        // * Устанавливаем title страницы
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('blog_menu_all_list'));

        // * Устанавливаем шаблон вывода
        $this->setTemplateAction('index');
    }

    public function eventShutdown()
    {
        \E::Module('Viewer')->assign('sMenuHeadItemSelect', $this->sMenuHeadItemSelect);
    }
}

// EOF
