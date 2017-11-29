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
class ModuleViewerAsset_EntityPackageCss extends ModuleViewerAsset_EntityPackage
{
    protected $sOutType = 'css';

    public function init()
    {
        $this->aHtmlLinkParams = [
            'tag'  => 'link',
            'attr' => [
                'type' => 'text/css',
                'rel'  => 'stylesheet',
                'href' => '@link',
            ],
            'pair' => false,
        ];
    }

    /**
     * Создает css-компрессор и инициализирует его конфигурацию
     *
     * @return bool
     */
    protected function initCompressor()
    {
        if ($this->bCompress) {
            $this->oCompressor = new csstidy();

            if ($this->oCompressor) {
                // * Получаем параметры из конфигурации
                $aParams = (array)$this->_cfgTypeOption('options.csstidy');
                // * Устанавливаем параметры
                foreach ($aParams as $sKey => $sVal) {
                    if ($sKey === 'template') {
                        $this->oCompressor->load_template($sVal);
                    } else {
                        $this->oCompressor->set_cfg('case_properties', $sVal);
                    }
                }
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $sContents
     *
     * @return mixed|string
     */
    public function compress($sContents)
    {
        $nErrorReporting =  \F::errorIgnored(E_NOTICE, true);
        if (strpos($sContents, $this->sMarker)) {
            $oCompressor = $this->oCompressor;
            $sContents = preg_replace_callback(
                '|\/\*\[' . preg_quote($this->sMarker) . '\s(?P<file>[\w\-\.\/]+)\sbegin\]\*\/(?P<content>.+)\/\*\[' . preg_quote($this->sMarker) . '\send\]\*\/\s*|sU',
                function($aMatches) use($oCompressor) {
                    if (substr($aMatches['file'], -8) !== '.min.css') {
                        $oCompressor->parse($aMatches['content']);
                        $sResult = $oCompressor->print->plain();
                    } else {
                        $sResult = $aMatches['content'];
                    }
                    return $sResult;
                },
                $sContents
            );
        } else {
            $this->oCompressor->parse($sContents);
            $sContents = $this->oCompressor->print->plain();
        }
         \F::errorReporting($nErrorReporting);

        return $sContents;
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
     * @param array $aLink
     *
     * @return string
     */
    public function buildHtmlTag($aLink)
    {
        if (empty($aLink['throw']) && !empty($aLink['compress']) && $this->_cfgTypeOption('gzip')) {
            $aLink['link'] .= ((isset($_SERVER['HTTP_ACCEPT_ENCODING']) && stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'GZIP') !== FALSE) ? '.gz.js' : '');
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
                    if ($sContents =  \F::File_GetContents($sAssetFile)) {
                        $sContents = $this->compress($sContents);
                        if (\F::File_PutContents($sCompressedFile, $sContents, LOCK_EX, true)) {
                             \F::File_Delete($sAssetFile);
                            $this->aLinks[$nIdx]['link'] =  \F::File_SetExtension($this->aLinks[$nIdx]['link'], $sExtension);
                            if ($this->_cfgTypeOption('gzip')) {
                                // Сохраним gzip
                                $sCompressedContent = gzencode($sContents, 9);
                                 \F::File_PutContents($sCompressedFile . '.gz.css', $sCompressedContent, LOCK_EX, true);
                            }
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
     * @param string $sContents
     * @param string $sSource
     *
     * @return mixed|string
     */
    public function prepareContents($sContents, $sSource)
    {
        if ($sContents) {
            $sContents = $this->_convertUrlsInCss($sContents, dirname($sSource) . '/');
            if ($this->bCompress) {
                $sFile =  \F::File_LocalDir($sSource);
                $sContents = '/*[' . $this->sMarker . ' ' . $sFile . ' begin]*/' . PHP_EOL
                    . $sContents
                    . PHP_EOL . '/*[' . $this->sMarker . ' end]*/' . PHP_EOL;
            }
        }
        return $sContents;
    }

    /**
     * @param string $sContent
     * @param $sSourceDir
     *
     * @return mixed
     */
    protected function _convertUrlsInCss($sContent, $sSourceDir)
    {
        // Есть ли в файле URLs
        if (!preg_match_all('/(?P<src>src:)?url\((?P<url>.*?)\)/is', $sContent, $aMatchedUrl, PREG_OFFSET_CAPTURE)) {
            return $sContent;
        }

        // * Обрабатываем список URLs
        $aUrls = [];
        foreach ($aMatchedUrl['url'] as $nIdx => $aPart) {
            $sPath = $aPart[0];
            //$nPos = $aPart[1];

            // * Don't touch data URIs
            if (false !== strpos($sPath, 'data:')) {
                continue;
            }
            $sPath = str_replace(['\'', '"'], '', $sPath);

            // * Если путь является абсолютным, то не обрабатываем
            if ($sPath[0] === '/' || 0 === strpos($sPath, 'http:') || 0 === strpos($sPath, 'https:')) {
                continue;
            }

            if (($n = strpos($sPath, '?')) || ($n = strpos($sPath, '#'))) {
                $sPath = substr($sPath, 0, $n);
                $sFileParam = substr($sPath, $n);
            } else {
                $sFileParam = '';
            }
            if (!isset($aUrls[$sPath])) {
                // if url didn't prepare...
                $sRealPath = realpath($sSourceDir . $sPath);
                if ($sRealPath) {
                    $sDestination =  \F::File_GetAssetDir() . $this->_makeSubdir(dirname($sRealPath)) . '/' . basename($sRealPath);
                    $aUrls[$sPath] = [
                        'source'      => $sRealPath,
                        'destination' => $sDestination,
                        'url'         => \E::Module('ViewerAsset')->assetFileDir2Url($sDestination) . $sFileParam,
                    ];
                     \F::File_Copy($sRealPath, $sDestination);
                }
            }
        }
        if ($aUrls) {
            $sContent = str_replace(array_keys($aUrls),  \F::Array_Column($aUrls, 'url'), $sContent);
        }
        return $sContent;
    }

}

// EOF