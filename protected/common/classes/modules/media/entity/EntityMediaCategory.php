<?php

/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

class ModuleMedia_EntityMediaCategory extends Entity {

    public function getId() {
        return $this->getProp('id');
    }

    public function getLabel() {
        return $this->getProp('label');
    }

    public function getCount() {
        return $this->getProp('count');
    }
}

// EOF