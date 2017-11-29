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
 * @package modules.rss
 * @since   1.1
 */
class ModuleRss_EntityRssChannel extends Entity {

    /**
     * @param ModuleRss_EntityRssItem $oItem
     *
     * @return ModuleRss_EntityRssChannel
     */
    public function addItem($oItem) {

        $this->appendProp('items', $oItem);

        return $this;
    }

    public function getItems() {

        return (array)$this->getProp('items');
    }
}

// EOF