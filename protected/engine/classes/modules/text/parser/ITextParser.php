<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */


interface ITextParser {

    public function loadConfig($sType = 'default', $bClear = true);

    public function tagBuilder($sTag, $xCallBack);

    public function parse($sText, &$aErrors);
}

// EOF