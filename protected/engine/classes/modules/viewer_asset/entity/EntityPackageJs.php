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
 * @package engine.modules
 * @since   1.0
 */
class ModuleViewerAsset_EntityPackageJs extends ModuleViewerAsset_EntityPackage
{
    protected $sOutType = 'js';

    /**
     * Init package
     */
    public function init()
    {
        $this->aHtmlLinkParams = [
            'tag'  => 'script',
            'attr' => [
                'type' => 'text/javascript',
                'src'  => '@link',
            ],
            'pair' => true,
        ];
    }

    /**
     * Init compressor/minifier
     *
     * @return bool
     */
    protected function initCompressor()
    {
        if ($this->bCompress) {
            return true;
        }
        return false;
    }

    /**
     * Compress/Minification
     *
     * @param string $sContents
     *
     * @return bool|string
     * @throws Exception
     */
    public function compress($sContents)
    {
        if (strpos($sContents, $this->sMarker)) {
            $sContents = preg_replace_callback(
                '|\/\*\[' . preg_quote($this->sMarker) . '\s(?P<file>[\w\-\.\/]+)\sbegin\]\*\/(?P<content>.+)\/\*\[' . preg_quote($this->sMarker) . '\send\]\*\/\s*|sU',
                function($aMatches){
                    if (substr($aMatches['file'], -7) != '.min.js') {
                        $sResult = \JShrink\Minifier::minify($aMatches['content']);
                    } else {
                        $sResult = $aMatches['content'];
                    }
                    return $sResult;
                },
                $sContents
            );
        } else {
            $sContents = \JShrink\Minifier::minify($sContents);
        }

        return $sContents;
    }

    /**
     * Prepare js-file
     *
     * @param string $sFile
     * @param string $sDestination
     *
     * @return null|string
     */
    public function prepareFile($sFile, $sDestination)
    {
        $sContents =  \F::File_GetContents($sFile);
        if ($sContents !== false) {
            $sContents = $this->prepareContents($sContents, $sFile);
            if (\F::File_PutContents($sDestination, $sContents, LOCK_EX, true) !== false) {
                return $sDestination;
            }
        }
        return false;
    }

    /**
     * Pre process stage
     *
     * @return bool
     */
    public function preProcess()
    {
        if ($this->aFiles) {
            $this->initCompressor();
        }
        return parent::preProcess();
    }

    /**
     * @param array  $aFileParams
     * @param string $sAssetName
     *
     * @return string
     */
    protected function _defineAssetName($aFileParams, $sAssetName)
    {
        $sAssetName = parent::_defineAssetName($aFileParams, $sAssetName);
        if (!empty($aFileParams['defer'])) {
            $sAssetName = '@defer|' . $sAssetName;
        } elseif (!empty($aFileParams['async'])) {
            $sAssetName = '@async|' . $sAssetName;
        }
        return $sAssetName;
    }

    /**
     * @param string $sDestination
     *
     * @return bool
     */
    public function checkDestination($sDestination)
    {
        if ($this->_cfgTypeOption('force')) {
            return false;
        }
        return parent::checkDestination($sDestination);
    }

    /**
     * @param string $sOutType
     * @param string $sLink
     * @param array  $aParams
     */
    public function addLink($sOutType, $sLink, $aParams = array())
    {
        if (!empty($aParams['asset'])) {
            if (!isset($aParams['attr'])) {
                $aParams['attr'] = [];
            }
            if (strpos($aParams['asset'], '@defer|') === 0) {
                $aParams['attr'][] = 'defer';
            } elseif (strpos($aParams['asset'], '@async|') === 0) {
                $aParams['attr'][] = 'async';
            }
        }
        parent::addLink($sOutType, $sLink, $aParams);
    }

    /**
     * @param array $aLink
     *
     * @return string
     */
    public function buildHtmlTag($aLink)
    {
        if (empty($aLink['throw']) && !empty($aLink['compress']) && $this->_cfgTypeOption('gzip')) {
            $aLink['link'] = $aLink['link'] . ((isset($_SERVER['HTTP_ACCEPT_ENCODING']) && stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'GZIP') !== FALSE) ? '.gz.js' : '');
        }
        return parent::buildHtmlTag($aLink);
    }

    /**
     * @return bool
     */
    public function process()
    {
        $bResult = true;
        foreach ($this->aLinks as $nIdx => $aLinkData) {
            if (empty($aLinkData['throw']) && !empty($aLinkData['compress'])) {
                $sAssetFile = $aLinkData['asset_file'];
                $sExtension = 'min.' .  \F::File_GetExtension($sAssetFile);
                $sCompressedFile =  \F::File_SetExtension($sAssetFile, $sExtension);
                if (!$this->checkDestination($sCompressedFile)) {
                    if (($sContents =  \F::File_GetContents($sAssetFile))) {
                        $sContents = $this->compress($sContents);
                        if (\F::File_PutContents($sCompressedFile, $sContents, LOCK_EX, true)) {
                             \F::File_Delete($sAssetFile);
                            $this->aLinks[$nIdx]['link'] =  \F::File_SetExtension($this->aLinks[$nIdx]['link'], $sExtension);
                        }
                        if ($this->_cfgTypeOption('gzip')) {
                            // Сохраним gzip
                            $sCompressedContent = gzencode($sContents, 9);
                             \F::File_PutContents($sCompressedFile . '.gz.js', $sCompressedContent, LOCK_EX, true);
                        }
                    }
                } else {
                    $this->aLinks[$nIdx]['link'] =  \F::File_SetExtension($this->aLinks[$nIdx]['link'], $sExtension);
                }
            }
        }
        return $bResult;
    }

    /**
     * Обработка контента
     *
     * @param string $sContents
     * @param string $sSource
     *
     * @return string
     */
    public function prepareContents($sContents, $sSource)
    {
        if ($this->bCompress) {
            $sFile =  \F::File_LocalDir($sSource);
            $sContents = '/*[' . $this->sMarker . ' ' . $sFile . ' begin]*/' . PHP_EOL
                . $sContents
                . PHP_EOL . '/*[' . $this->sMarker . ' end]*/' . PHP_EOL;
        }

        return $sContents;
    }

}

// EOF