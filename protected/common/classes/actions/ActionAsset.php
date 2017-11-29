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
 * @since 1.0
 */
class ActionAsset extends Action {

    public function init() {

    }

    protected function registerEvent() {

        $this->addEvent('skin', 'eventSkin');
    }

    public function eventSkin()
    {
        $aParams = $this->getParams();
        $sSkinName = array_shift($aParams);
        $sRelPath = implode('/', $aParams);

        $sOriginalFile = \C::get('path.skins.dir') . $sSkinName . '/' . $sRelPath;
        if (\F::File_Exists($sOriginalFile)) {
            $sAssetFile = F::File_GetAssetDir() . 'skin/' . $sSkinName . '/' . $sRelPath;
            if (\F::File_Copy($sOriginalFile, $sAssetFile)) {
                if (headers_sent($sFile, $nLine)) {
                    $sUrl = F::File_GetAssetUrl() . 'skin/' . $sSkinName . '/' . $sRelPath;
                    if (strpos($sUrl, '?')) {
                        $sUrl .= '&' . uniqid('', true);
                    } else {
                        $sUrl .= '?' . uniqid('', true);
                    }
                    R::Location($sUrl);
                } else {
                    header_remove();
                    if ($sMimeType = F::File_MimeType($sAssetFile)) {
                        header('Content-Type: ' . $sMimeType);
                    }
                    echo file_get_contents($sAssetFile);
                    exit;
                }
            }
        }
        F::httpHeader('404 Not Found');
        exit;
    }

}

// EOF