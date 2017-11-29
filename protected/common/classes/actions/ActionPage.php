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
 * @since   0.9
 */
class ActionPage extends Action {

    protected $oCurrentPage;

    public function init() {
    }

    /**
     * Регистрируем евенты
     *
     */
    protected function registerEvent() {

        $this->addEventPreg('/^[\w\-\_]*$/i', 'eventShowPage');
    }


    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    /**
     * Returns page by requested URL
     *
     * @return ModulePage_EntityPage
     */
    protected function _getPageFromUrl() {

        // * Составляем полный URL страницы для поиска по нему в БД
        $sUrlFull = join('/', $this->getParams());
        if ($sUrlFull != '') {
            $sUrlFull = $this->sCurrentEvent . '/' . $sUrlFull;
        } else {
            $sUrlFull = $this->sCurrentEvent;
        }

        // * Ищем страницу в БД
        $oPage = \E::Module('Page')->getPageByUrlFull($sUrlFull, 1);

        return $oPage;
    }

    /**
     * Отображение страницы
     *
     * @return mixed
     */
    public function eventShowPage() {

        if (!$this->sCurrentEvent) {
            // * Показывает дефолтную страницу (а это какая страница?)
        }

        $this->oCurrentPage = $this->_getPageFromUrl();

        if (!$this->oCurrentPage) {
            return $this->eventNotFound();
        }

        // * Заполняем HTML теги и SEO
        \E::Module('Viewer')->addHtmlTitle($this->oCurrentPage->getTitle());
        if ($this->oCurrentPage->getSeoKeywords()) {
            \E::Module('Viewer')->setHtmlKeywords($this->oCurrentPage->getSeoKeywords());
        }
        if ($this->oCurrentPage->getSeoDescription()) {
            \E::Module('Viewer')->setHtmlDescription($this->oCurrentPage->getSeoDescription());
        }

        \E::Module('Viewer')->assign('oPage', $this->oCurrentPage);

        // * Устанавливаем шаблон для вывода
        $this->setTemplateAction('show');
    }


}

// EOF