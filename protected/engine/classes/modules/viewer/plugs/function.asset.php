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
 * Plugin for Smarty
 * Returns URL for skin asset file
 *
 * @param   array $aParams
 * @param   Smarty_Internal_Template $oSmartyTemplate
 *
 * @return  string
 */
function smarty_function_asset($aParams, $oSmartyTemplate)
{
    if (empty($aParams['skin']) && empty($aParams['file'])) {
        trigger_error('Asset: missing "file" parametr', E_USER_WARNING);
        return '';
    }

    if (isset($aParams['file'])) {
        if ((stripos($aParams['file'], 'http://') === 0)
            || (stripos($aParams['file'], 'https://') === 0)
            || (strpos($aParams['file'], '//') === 0)) {
            $sUrl = $aParams['file'];
        } else {
            $sSkin = (!empty($aParams['skin']) ? $aParams['skin'] : \E::Module('Viewer')->getConfigSkin());
            // File name has full local path
            if (\F::File_LocalDir($aParams['file'])) {
                $sFile = $aParams['file'];
            } else {
                // Need URL to asset file
                if (isset($aParams['theme'])) {
                    if (is_bool($aParams['theme'])) {
                        $sTheme = \E::Module('Viewer')->getConfigTheme();
                    } else {
                        $sTheme = $aParams['theme'];
                    }
                } else {
                    $sTheme = '';
                }
                if ($sTheme) {
                    $sTheme = 'themes/' . $sTheme . '/';
                }
                if (isset($aParams['plugin'])) {
                    $sFile = PluginManager::getTemplateFile($aParams['plugin'], $aParams['file']);
                } else {
                    $sFile = \C::get('path.skins.dir') . '/' . $sSkin . '/' . $sTheme . $aParams['file'];
                }
            }
            if (isset($aParams['prepare'])) {
                $sAssetName = (empty($aParams['asset']) ? $sFile : $aParams['asset']);
                // Грязноватый хак, но иначе нам не получить ссылку
                $aFileData = [
                    $sFile => [
                        'name' => md5($sFile),
                        'prepare' => true,
                    ],
                ];

                /** @var ModuleViewerAsset $oLocalViewerAsset */
                $oLocalViewerAsset = new ModuleViewerAsset();
                $oLocalViewerAsset->addFiles(\F::File_GetExtension($sFile, true), $aFileData, $sAssetName);
                $oLocalViewerAsset->prepare();
                //$sUrl = $oLocalViewerAsset->AssetFileUrl(\F::File_NormPath($sFile));
                $aLinks = $oLocalViewerAsset->getPreparedAssetLinks();
                $sUrl = reset($aLinks);
            } else {
                $sUrl = \E::Module('ViewerAsset')->file2Link($sFile, 'skin/' . $sSkin . '/');
            }
        }
    } else {
        // Need URL to asset dir
        $sUrl = \F::File_GetAssetUrl() . 'skin/' . $aParams['skin'] . '/';
    }

    return $sUrl;
}

// EOF