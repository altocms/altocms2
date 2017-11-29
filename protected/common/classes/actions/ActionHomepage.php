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
 * @package actions
 * @since   1.0
 */
class ActionHomepage extends Action {

    /**
     * Инициализация
     *
     */
    public function init() {

        $this->setDefaultEvent('default');
    }

    /**
     * Регистрация евентов
     *
     */
    protected function registerEvent() {

        $this->addEvent('default', 'eventDefault');
    }

    /**
     * Default homepage
     *
     * @return string
     */
    public function eventDefault() {

        \E::Module('Viewer')->assign('sMenuHeadItemSelect', 'homepage');
        $sHomepage = \C::get('router.config.homepage');
        if ($sHomepage) {
            $sHomepageSelect = \C::get('router.config.homepage_select');
            if ($sHomepageSelect == 'page') {
                // if page not active or deleted then this homepage is off
                $oPage = \E::Module('Page')->getPageByUrlFull($sHomepage, 1);
                if ($oPage) {
                    $sHomepage = $oPage->getLink();
                } else {
                    $sHomepage = '';
                }
            } else {
                if ($sHomepageSelect == 'category_homepage') {
                    $sHomepageSelect = 'plugin-categories-homepage';
                }
                $aHomePageSelect = explode('-', $sHomepageSelect);
                // if homepage was from plugin and plugin is not active then this homepage is off
                if ($aHomePageSelect[0] == 'plugin' && isset($aHomePageSelect[1])) {
                    if (!\E::activePlugin($aHomePageSelect[1])) {
                        $sHomepage = '';
                    }
                }
            }
            if ($sHomepage == 'home') {
                if (\E::Module('Viewer')->templateExists('actions/homepage/action.homepage.index.tpl')) {
                    $this->setTemplateAction('index');
                    return;
                }
            } elseif ($sHomepage) {
                return R::redirect($sHomepage);
            }
        }
        return R::redirect('index');
    }

}
// EOF