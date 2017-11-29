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
 * Class ModuleImg_EntityConfig
 *
 * @since 1.1
 */
class ModuleImg_EntityConfig extends Component {

    public function get($sProp) {

        if (substr($sProp, -7) == '.driver') {
            $sDriver = \E::Module('Img')->GetDriver();
            if (!class_exists('\PHPixie\Image\\' . $sDriver, false)) {
                 \F::IncludeLib('PHPixie/Image/' . $sDriver . '.php');
            }
            return $sDriver;
        }
        return null;
    }
}

// EOF