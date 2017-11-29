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
class ModuleViewerAsset extends Module 
{
    const TMP_TIME      = 60;
    const SLEEP_TIME    = 5;
    const SLEEP_COUNT   = 4;

    const ADD_PREPEND   = -1;
    const ADD_DEFAULT   = 0;
    const ADD_APPEND    = 1;

    const PLACE_HEAD    = 1;
    const PLACE_BODY    = 2;
    const PLACE_END     = 3;
    const PLACE_DEFAULT = 1;

    protected $aAssetTypes = ['less', 'js', 'css'];

    /**
     * @var array
     */
    protected $aAssets = [];

    protected $aFiles = [];

    protected $aHtmlTags = [];

    protected $bPrepared = false;

    protected $sAssetDir;

    protected $sAssetUrl;

    protected $aMapDir = [];

    /**
     * Module initialization
     */
    public function init()
    {
        // Load default assets
        $sAssetConfigName = \C::val('assets.select', 'default');
        // Compatibility with old style skins
        if (!($aAssetsConfig = \C::get('head.default')) && !($aAssetsConfig = \C::get('assets.default'))) {
            $aAssetsConfig = \C::get('assets.data.' . $sAssetConfigName);
        }
        if ($aAssetsConfig) {
            $this->addAssetFiles($aAssetsConfig);
        }

        // Load editor's assets
        if ($aEditors = \C::get('view.set_editors')) {
            if  (\C::get('view.wysiwyg')) {
                $sEditor = isset($aEditors['wysiwyg']) ? $aEditors['wysiwyg'] : 'tinymce';
            } else {
                if (isset($aEditors['default'])) {
                    $sEditor = $aEditors['default'];
                } else {
                    $sEditor = 'markitup';
                }
            }
            $aEditorAssets = \C::get('assets.editor.' . $sEditor);
            if ($aEditorAssets) {
                $this->addAssetFiles($aEditorAssets);
            }
        }
        $this->sAssetDir =  \F::File_GetAssetDir();
        $this->sAssetUrl =  \F::File_GetAssetUrl();
    }

    /**
     * @return array
     */
    protected function _getAvailablePlacements()
    {
        return ['head', 'body', 'bottom'];
    }

    /**
     * Returns set of asset files
     *
     * @param  bool $bReorder
     *
     * @return array
     */
    public function getFiles($bReorder = false)
    {
        $aResult = [];
        /** @var array $aTypeSets */
        foreach($this->aFiles as $sType => $aTypeSets) {
            $aPrepend['files'] = isset($aTypeSets['files'][self::ADD_PREPEND]) ? $aTypeSets['files'][self::ADD_PREPEND] : [];
            $aPrepend['links'] = isset($aTypeSets['links'][self::ADD_PREPEND]) ? $aTypeSets['links'][self::ADD_PREPEND] : [];
            if ($bReorder) {
                if ($aPrepend['files']) {
                    $aPrepend['files'] = array_reverse($aPrepend['files'], true);
                }
                if ($aPrepend['links']) {
                    $aPrepend['links'] = array_reverse($aPrepend['links'], true);
                }
            }
            $aResult[$sType]['files'] = array_merge(
                $aPrepend['files'],
                isset($aTypeSets['files'][self::ADD_DEFAULT]) ? $aTypeSets['files'][self::ADD_DEFAULT] : [],
                isset($aTypeSets['files'][self::ADD_APPEND]) ? $aTypeSets['files'][self::ADD_APPEND] : []
            );
            $aResult[$sType]['links'] = array_merge(
                $aPrepend['links'],
                isset($aTypeSets['links'][self::ADD_DEFAULT]) ? $aTypeSets['links'][self::ADD_DEFAULT] : [],
                isset($aTypeSets['links'][self::ADD_APPEND]) ? $aTypeSets['links'][self::ADD_APPEND] : []
            );
        }
        return $aResult;
    }

    /**
     * Calculate hash of file's dirname
     *
     * @param  string $sFile
     *
     * @return string
     */
    public function assetFileHashDir($sFile) 
    {
        if (substr($sFile, -1) === '/') {
            $sDir = $sFile;
        } else {
            $sDir = dirname($sFile);
        }
        $sResult = \F::Crc32($sDir, true);
        if (ALTO_DEBUG) {
            //$sResult = str_replace(['\\', '/', ':'], '-',  \F::File_LocalDir($sDir)) . '-' . $sResult;
        }
        return $sResult;
    }

    /**
     * Make path of asset file
     *
     * @param  string $sLocalFile
     * @param  string $sParentDir
     *
     * @return string
     */
    public function assetFilePath($sLocalFile, $sParentDir = null) 
    {
        $sResult = $this->assetFileHashDir($sLocalFile) . '/' . basename($sLocalFile);
        if ($sParentDir) {
            if (substr($sParentDir, -1) !== '/') {
                $sParentDir .= '/';
            }
            $sResult = $sParentDir . $sResult;
        }
        return $sResult;
    }

    /**
     * Converts file path (with filename) into path to asset-resource
     *
     * @param  string $sLocalFile
     * @param  string $sParentDir
     *
     * @return string
     */
    public function assetFileDir($sLocalFile, $sParentDir = null) 
    {
        return $this->sAssetDir . $this->assetFilePath($sLocalFile, $sParentDir);
    }

    /**
     * Convert file path (with filename) into URL to asset-resource
     *
     * @param  string $sLocalFile
     * @param  string $sParentDir
     *
     * @return string
     */
    public function assetFileUrl($sLocalFile, $sParentDir = null) 
    {
        return $this->sAssetUrl . $this->assetFilePath($sLocalFile, $sParentDir);
    }

    /**
     * Converts file path (without filename - path to file only) into path to asset-resource
     *
     * @param  string $sLocalFile
     * @param  string $sParentDir
     *
     * @return string
     */
    public function assetFilePathDir($sLocalFile, $sParentDir = null)
    {
        $sResult = $this->assetFileDir($sLocalFile, $sParentDir);
        return dirname($sResult);
    }

    /**
     * Convert file path (without filename - path to file only) into URL to asset-resource
     *
     * @param  string $sLocalFile
     * @param  string $sParentDir
     *
     * @return string
     */
    public function assetFilePathUrl($sLocalFile, $sParentDir = null)
    {
        $sResult = $this->assetFileUrl($sLocalFile, $sParentDir);
        return dirname($sResult);
    }

    /**
     * @param string $sAssetFile
     *
     * @return string
     */
    public function assetFileDir2Url($sAssetFile) 
    {
        $sFilePath =  \F::File_LocalPath($sAssetFile, $this->sAssetDir);
        return $this->sAssetUrl . $sFilePath;
    }

    /**
     * @param string $sAssetFile
     *
     * @return string
     */
    public function assetFileUrl2Dir($sAssetFile) 
    {
        $sFilePath =  \F::File_LocalPathUrl($sAssetFile, $this->sAssetUrl);
        return $this->sAssetDir . $sFilePath;
    }

    /**
     * @param  string $sLocalFile
     * @param  string $sParentDir
     *
     * @return bool|string
     */
    public function file2Link($sLocalFile, $sParentDir = null) 
    {
        $sAssetFile = $this->assetFileDir($sLocalFile, $sParentDir);
        if (\F::File_Exists($sAssetFile) ||  \F::File_Copy($sLocalFile, $sAssetFile)) {
            return $this->assetFileUrl($sLocalFile, $sParentDir);
        }
        return false;
    }

    /**
     * @param string $sType
     *
     * @return ModuleViewerAsset_EntityPackage
     */
    protected function _getAssetPackage($sType) 
    {
        $oResult = null;
        if (!isset($this->aAssets[$sType])) {
            if (in_array($sType, $this->aAssetTypes)) {
                $aParams = ['asset_type' => $sType];
                $aPlaces = $this->_getAvailablePlacements();
                if ($sType === 'js') {
                    $aParams['avail_places'] = $aPlaces;
                } else {
                    $aParams['avail_places'] = [reset($aPlaces)];
                }
                $this->aAssets[$sType] = \E::getEntity('ViewerAsset_Package' . ucfirst($sType), $aParams);
                $oResult = $this->aAssets[$sType];
            } else {
                if (!isset($this->aAssets['*'])) {
                    $this->aAssets['*'] = \E::getEntity('ViewerAsset_Package');
                }
                $oResult = $this->aAssets['*'];
            }
        } else {
            $oResult = $this->aAssets[$sType];
        }
        return $oResult;
    }

    /**
     * @param string       $sType
     * @param array|string $aFiles
     * @param array        $aOptions
     *
     * @return int
     */
    protected function _add($sType, $aFiles, $aOptions = []) 
    {
        if ($oAssetPackage = $this->_getAssetPackage($sType)) {
            $aAddFiles = [];
            foreach ((array)$aFiles as $sFileName => $aFileParams) {
                // extract & normalize full file path
                if (isset($aFileParams['file'])) {
                    $sFilePath =  \F::File_NormPath($aFileParams['file']);
                } else {
                    $sFilePath =  \F::File_NormPath((string)$sFileName);
                }
                // if file path defined
                if ($sFilePath) {
                    if (!is_array($aFileParams)) {
                        $aFileParams =['file' => $sFilePath];
                    } else {
                        $aFileParams['file'] = $sFilePath;
                    }
                    if (!isset($aFileParams['name'])) {
                        $aFileParams['name'] = $aFileParams['file'];
                    }
                    $aAddFiles[$aFileParams['name']] = $aFileParams;
                } else {
                     \F::sysWarning('Can not define asset file path "' . $sFilePath . '"');
                }
            }
            if ($aAddFiles) {
                return $oAssetPackage->addFiles(
                    $aAddFiles, null,
                    isset($aOptions['prepend']) ? $aOptions['prepend'] : false,
                    isset($aOptions['replace']) ? $aOptions['replace'] : null
                );
            }
        }
        return 0;
    }

    /**
     * @param $aFiles
     */
    public function addAssetFiles($aFiles) 
    {
        $this->aAssets = [];

        if (isset($aFiles['js'])) {
            $this->addJsFiles($aFiles['js']);
        }
        if (isset($aFiles['css'])) {
            $this->addCssFiles($aFiles['css']);
        }
        if (isset($aFiles['less'])) {
            $this->addLessFiles($aFiles['less']);
        }
    }

    /**
     * @param string $sType
     * @param array  $aFiles
     */
    public function addFiles($sType, $aFiles)
    {
        if (!is_array($aFiles)) {
            $aFiles = [
                ['file' => (string)$aFiles],
            ];
        }
        $aAssetFiles = [];
        foreach ($aFiles as $sFileName => $aFileParams) {
            // extract file path
            if (is_numeric($sFileName)) {
                // single file name or array of options
                if (!is_array($aFileParams)) {
                    $sName = $sFile = (string)$aFileParams;
                } elseif(isset($aFileParams['files'])) {
                    // group of files with common options
                    $aOptions = isset($aFileParams['options']) ? (array)$aFileParams['options']: [];
                    $aFileGroup = [];
                    foreach((array)$aFileParams['files'] as $sGroupFile => $aGroupFileParams) {
                        if (!is_array($aGroupFileParams)) {
                            $aGroupFileParams = ['file' => (string)$aGroupFileParams];
                        }
                        $aFileGroup[$sGroupFile] = array_merge($aGroupFileParams, $aOptions);
                    }
                    $this->addFiles($sType, $aFileGroup);
                    continue;
                } else {
                    $sFile = isset($aFileParams['file']) ? $aFileParams['file'] : null;
                    $sName = isset($aFileParams['name']) ? $aFileParams['name'] : $sFile;
                }
            } else {
                // filename => array of options
                if (isset($aFileParams['file'])) {
                    $sFile = $aFileParams['file'];
                } else {
                    $sFile = (string)$sFileName;
                }
                $sName = isset($aFileParams['name']) ? $aFileParams['name'] : $sFile;
            }
            if (!is_array($aFileParams)) {
                $aFileParams = [];
            }
            $sName =  \F::File_NormPath($sName);
            $aFileParams['file'] = $sFile;
            $aFileParams['name'] = $sName;
            $aAssetFiles[$sName] = $aFileParams;
        }
        $aAvailPlaces = $this->_getAvailablePlacements();
        $sDefaultPlace = reset($aAvailPlaces);
        // Appends files for future preparation
        foreach ($aAssetFiles as $sName => $aFileParams) {
            if (empty($aFileParams['place']) || !in_array($aFileParams['place'], $aAvailPlaces, true)) {
                $aFileParams['place'] = $sDefaultPlace;
            }
            if (!empty($aFileParams['prepend'])) {
                if (empty($aFileParams['asset'])) {
                    $aFileParams['asset'] = '__prepend_' . $aFileParams['place'];
                }
                $this->aFiles[$sType]['files'][self::ADD_PREPEND][$sName] = $aFileParams;
                if (isset($this->aFiles[$sType]['files'][self::ADD_APPEND][$sName])) {
                    unset($this->aFiles[$sType]['files'][self::ADD_APPEND][$sName]);
                }
            } else {
                if (empty($aFileParams['asset'])) {
                    $aFileParams['asset'] = '__append_' . $aFileParams['place'];
                }
                $this->aFiles[$sType]['files'][self::ADD_APPEND][$sName] = $aFileParams;
                if (isset($this->aFiles[$sType]['files'][self::ADD_PREPEND][$sName])) {
                    unset($this->aFiles[$sType]['files'][self::ADD_PREPEND][$sName]);
                }
            }
        }
        $this->bPrepared = false;
    }

    /**
     * @param string $sType
     * @param array  $aFiles
     * @param array  $aOptions
     *
     * @return int
     */
    public function addFilesToAssets($sType, $aFiles, $aOptions = []) 
    {
        if (!is_array($aFiles)) {
            $aFiles = [
                ['file' => (string)$aFiles],
            ];
        }
        $aAssetFiles = [];
        $aFileList = [];

        // seek wildcards - if name hase '*' then add files by pattern
        foreach ($aFiles as $sFileName => $aFileParams) {
            if (strpos($sFileName, '*')) {
                unset($aFiles[$sFileName]);
                $aFoundFiles = (array)F::File_ReadFileList($sFileName, 0, true);
                foreach($aFoundFiles as $sAddFile) {
                    $sAddType =  \F::File_GetExtension($sAddFile, true);
                    $aFileParams['name'] = $sAddFile;
                    $aFileParams['file'] = $sAddFile;
                    if ($sAddType === $sType) {
                        $aFileList[$sAddFile] = $aFileParams;
                    } else {
                        $this->addFilesToAssets($sAddType,[$sAddFile => $aFileParams], $aOptions);
                    }
                }
            } else {
                $aFileList[$sFileName] = $aFileParams;
            }
        }

        $aSubDirs = [];
        foreach ($aFileList as $sFileName => $aFileParams) {
            // extract & normalize full file path
            if (isset($aFileParams['file'])) {
                $sFile =  \F::File_NormPath($aFileParams['file']);
            } else {
                $sFile =  \F::File_NormPath((string)$sFileName);
            }
            $sName = isset($aFileParams['name']) ? $aFileParams['name'] : $sFile;
            if (!is_array($aFileParams)) {
                $aFileParams = [];
            }
            $aFileParams['file'] =  \F::File_NormPath($sFile);
            $aFileParams['name'] =  \F::File_NormPath($sName);
            $aAssetFiles[$sName] = $aFileParams;

            // Optional preparation of subdirs
            if (!empty($aFileParams['prepare_subdirs'])) {
                if ($aFileParams['prepare_subdirs'] === true) {
                    $aFileParams['prepare_subdirs'] = '*';
                }
                $sDirPattern = dirname($aFileParams['file']) . '/' . $aFileParams['prepare_subdirs'];
                $aSubDirs[$sDirPattern] = $aFileParams;
            }
        }
        $iResult = $this->_add($sType, $aAssetFiles, $aOptions);
        if (!empty($aSubDirs)) {
            foreach($aSubDirs as $sDirPattern => $aFileParams) {
                $iResult += $this->_addDirsToAssets($sDirPattern, $aFileParams);
            }
        }
        return $iResult;
    }

    /**
     * @param string $sDirPattern
     * @param array  $aFileParams
     *
     * @return int
     */
    protected function _addDirsToAssets($sDirPattern, $aFileParams)
    {
        $iFlag = GLOB_ONLYDIR;
        $sBaseDir = dirname($sDirPattern);
        $aNames =  \F::Str2Array(basename($sDirPattern));
        $iResult = 0;
        $aFileParams['prepare'] = true;
        $aFileParams['merge'] = true;
        $aFileParams['name'] = null;
        $aFileParams['asset'] = null;
        foreach($aNames as $sName) {
            $aDirs = (array)glob($sBaseDir . '/' . $sName, $iFlag);
            foreach($aDirs as $sDir) {
                $aFiles = [
                    $sDir . '/*' => $aFileParams,
                ];
                $this->addFilesToAssets('*', $aFiles);
            }
        }
        return $iResult;
    }

    /**
     * Add link to current asset pack
     *
     * @param string $sType
     * @param array  $aLinks
     */
    public function addLinksToAssets($sType, $aLinks) 
    {
        foreach ($aLinks as $sLink => $aParams) {
            // Add links to assets
            if ($oAssetPackage = $this->_getAssetPackage($sType)) {
                $oAssetPackage->addLink($sType, $sLink, $aParams);
            }
        }
    }


    /**
     * @param array  $aFiles
     */
    public function addJsFiles($aFiles)
    {
        return $this->addFiles('js', $aFiles);
    }

    /**
     * @param array  $aFiles
     */
    public function addCssFiles($aFiles)
    {
        return $this->addFiles('css', $aFiles);
    }

    /**
     * @param array  $aFiles
     */
    public function addLessFiles($aFiles)
    {
        return $this->addFiles('less', $aFiles);
    }

    /**
     * @param string $sFile
     * @param array $aParams
     * @param bool  $bReplace
     */
    public function appendJs($sFile, $aParams = [], $bReplace = false) 
    {
        $aParams['prepend'] = false;
        $aParams['replace'] = (bool)$bReplace;

        return $this->addFiles('js', [$sFile => $aParams]);
    }

    /**
     * @param string $sFile
     * @param array $aParams
     * @param bool  $bReplace
     */
    public function prependJs($sFile, $aParams = [], $bReplace = false) 
    {
        $aParams['prepend'] = true;
        $aParams['replace'] = (bool)$bReplace;

        return $this->addFiles('js', [$sFile => $aParams]);
    }

    /**
     * @param string $sFile
     * @param array  $aParams
     * @param bool   $bReplace
     */
    public function prepareJs($sFile, $aParams = [], $bReplace = false) 
    {
        $aParams['prepare'] = true;
        $aParams['replace'] = (bool)$bReplace;

        return $this->addFiles('js', [$sFile => $aParams]);
    }

    /**
     * @param string $sFile
     * @param array $aParams
     * @param bool  $bReplace
     */
    public function appendCss($sFile, $aParams = [], $bReplace = false) 
    {
        $aParams['prepend'] = false;
        $aParams['replace'] = (bool)$bReplace;

        return $this->addFiles('css', [$sFile => $aParams]);
    }

    /**
     * @param string $sFile
     * @param array $aParams
     * @param bool  $bReplace
     */
    public function prependCss($sFile, $aParams = [], $bReplace = false) 
    {
        $aParams['prepend'] = true;
        $aParams['replace'] = (bool)$bReplace;

        return $this->addFiles('css', [$sFile => $aParams]);
    }

    /**
     * @param string $sFile
     * @param array $aParams
     * @param bool  $bReplace
     */
    public function prepareCss($sFile, $aParams = [], $bReplace = false) 
    {
        $aParams['prepare'] = true;
        $aParams['replace'] = (bool)$bReplace;

        return $this->addFiles('css', [$sFile => $aParams]);
    }

    /**
     * Clear file set of requested type
     *
     * @param string $sType
     */
    public function clear($sType) 
    {
        $this->aFiles[$sType] = [];
        $this->bPrepared = false;
    }

    /**
     * Clear js-file set
     */
    public function clearJs() 
    {
        $this->clear('js');
    }

    /**
     * Clear css-file set
     */
    public function clearCss() 
    {
        $this->clear('css');
    }

    /**
     * @param string $sType
     * @param array $aFiles
     */
    public function exclude($sType, $aFiles) 
    {
        foreach ($aFiles as $aFileParams) {
            if (is_array($aFileParams)) {
                if (isset($aFileParams['name'])) {
                    $sName = $aFileParams['name'];
                } else {
                    $sName = $aFileParams['file'];
                }
            } else {
                $sName = (string)$aFileParams;
            }
            if (isset($this->aFiles[$sType]['files'][self::ADD_PREPEND][$sName])) {
                unset($this->aFiles[$sType]['files'][self::ADD_PREPEND][$sName]);
                $this->bPrepared = false;
            }
            if (isset($this->aFiles[$sType]['files'][self::ADD_APPEND][$sName])) {
                unset($this->aFiles[$sType]['files'][self::ADD_APPEND][$sName]);
                $this->bPrepared = false;
            }
            if (isset($this->aFiles[$sType]['links'][self::ADD_PREPEND][$sName])) {
                unset($this->aFiles[$sType]['links'][self::ADD_PREPEND][$sName]);
                $this->bPrepared = false;
            }
            if (isset($this->aFiles[$sType]['links'][self::ADD_APPEND][$sName])) {
                unset($this->aFiles[$sType]['links'][self::ADD_APPEND][$sName]);
                $this->bPrepared = false;
            }
        }
    }

    /**
     * @param string $sType
     * @param string $sLink
     * @param array  $aParams
     */
    public function addLink($sType, $sLink, $aParams = [])
    {
        $this->aFiles[$sType]['links'][self::ADD_APPEND][$sLink] = $aParams;
        $this->bPrepared = false;
    }

    /**
     * Returns hash for current asset pack
     *
     * @return string
     */
    public function getHash() 
    {
        $aData = [$this->aFiles, \C::get('compress'), \C::get('assets.version')];

        return  \F::Array_Hash($aData);
    }

    /**
     * Returns file name for cache of current asset pack
     *
     * @return string
     */
    public function getAssetsCacheName() 
    {
        return \C::get('sys.cache.dir') . 'data/assets/' . $this->getHash() . '.assets.dat';
    }

    /**
     * Returns name for check-file of current asset pack
     *
     * @return string
     */
    public function getAssetsCheckName() 
    {
        return $this->sAssetDir . '_check/' . $this->getHash() . '.assets.chk';
    }

    /**
     * @return bool
     */
    public function clearAssetsCache() 
    {
        $sDir = \C::get('sys.cache.dir') . 'data/assets/';
        return  \F::File_RemoveDir($sDir);
    }

    /**
     * Checks cache for current asset pack
     * If cache is present then returns one
     *
     * @return int|array
     */
    protected function _checkAssets() 
    {
        $xResult = 0;
        $sFile = $this->getAssetsCacheName();
        $sTmpFile = $sFile . '.tmp';

        if (is_file($sTmpFile)) {
            // tmp file cannot live more than 1 minutes
            $nTime = filectime($sTmpFile);
            if (!$nTime) {
                $nTime =  \F::File_GetContents($sTmpFile);
            }
            if (time() < $nTime + self::TMP_TIME) {
                $xResult = 1;
            }
        } elseif (is_file($sFile)) {
            if ($xData =  \F::File_GetContents($sFile)) {
                $xResult =  \F::Unserialize($xData);
            }
        }
        return $xResult;
    }

    protected function _resetAssets() 
    {
        $sFile = $this->getAssetsCacheName();
         \F::File_PutContents($sFile . '.tmp', time(), LOCK_EX, true);
         \F::File_Delete($sFile);
         \F::File_Delete($this->getAssetsCheckName());

        $this->aAssets = [];
        $this->aHtmlTags = [];
        $this->bPrepared = true;
    }

    /**
     * Save cache and check-file of current asset pack
     */
    protected function _saveAssets() 
    {
        $sCheckFileName = $this->getAssetsCheckName();
         \F::File_PutContents($sCheckFileName, time(), LOCK_EX, true);
        $sCacheFileName = $this->getAssetsCacheName();
         \F::File_PutContents($sCacheFileName,  \F::Serialize($this->aAssets), LOCK_EX, true);
         \F::File_Delete($sCacheFileName . '.tmp');
    }

    /**
     * Checks whether a set of files empty
     *
     * @return bool
     */
    protected function _isEmpty() 
    {
        $aFiles = $this->getFiles();
        if (!empty($aFiles) && is_array($aFiles)) {
            foreach($aFiles as $sType => $aFileSet) {
                if (!empty($aFileSet)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Prepare current asset pack
     */
    public function prepare() 
    {
        if ($this->_isEmpty()) {
            return;
        }

        $bForcePreparation = \C::get('assets.css.force') || \C::get('assets.js.force');
        $xData = $this->_checkAssets();
        if ($xData) {
            if (is_array($xData)) {
                if (\F::File_GetContents($this->getAssetsCheckName())) {
                    // loads assets from cache
                    $this->aAssets = (array)$xData;
                    if (!$bForcePreparation) {
                        return;
                    }
                }
            } else {
                // assets are making right now
                // may be need to wait?
                for ($i=0; $i<self::SLEEP_COUNT; $i++) {
                    sleep(self::SLEEP_TIME);
                    $xData = $this->_checkAssets();
                    if (is_array($xData)) {
                        $this->aAssets = (array)$xData;
                        return;
                    }
                }
                // something wrong
                return;
            }
        }
        // May be assets are not complete
        if (!$this->aAssets && $this->aFiles && !$bForcePreparation) {
            $bForcePreparation = true;
        }

        if ($bForcePreparation || !F::File_GetContents($this->getAssetsCheckName())) {

            // reset assets here
            $this->_resetAssets();

            // Add files & links to assets
            foreach ($this->getFiles(true) as $sType => $aData) {
                if (isset($aData['files'])) {
                    $this->addFilesToAssets($sType, $aData['files']);
                }
                if (isset($aData['links'])) {
                    $this->addLinksToAssets($sType, $aData['links']);
                }
            }

            $nStage = 0;
            $bDone = true;
            // PreProcess
            foreach($this->aAssets as $oAssetPackage) {
                if ($oAssetPackage->preProcessBegin()) {
                    $bDone = ($bDone && $oAssetPackage->preProcess());
                    $oAssetPackage->preProcessEnd();
                }
            }
            if ($bDone) {
                $nStage += 1;
            }
            // Process
            foreach($this->aAssets as $oAssetPackage) {
                if ($oAssetPackage->processBegin()) {
                    $bDone = ($bDone && $oAssetPackage->process());
                    $oAssetPackage->processEnd();
                }
            }
            if ($bDone) {
                $nStage += 1;
            }
            // PostProcess
            foreach($this->aAssets as $oAssetPackage) {
                if ($oAssetPackage->postProcessBegin()) {
                    $bDone = ($bDone && $oAssetPackage->postProcess());
                    $oAssetPackage->postProcessEnd();
                }
            }
            if ($bDone) {
                $nStage += 1;
            }
        } else {
            $nStage = 3;
        }

        if ($nStage === 3) {
            $this->_saveAssets();
        }
    }

    /**
     * @return array
     */
    public function getPreparedAssetLinks() 
    {
        $aResult = [];
        foreach($this->aAssets as $oAssetPackage) {
            if ($aLinks = $oAssetPackage->getLinksArray(true, true)) {
                $aResult =  \F::Array_Merge($aResult, reset($aLinks));
            }
        }
        return $aResult;
    }

    /**
     * @param string $sPlace
     * @param string $sType
     *
     * @return array
     */
    public function getHtmlTags($sPlace = null, $sType = null)
    {
        if (!$this->bPrepared) {
            $this->prepare();
        }

        if (!$this->aHtmlTags) {
            foreach($this->_getAvailablePlacements() as $sAvailPlace) {
                /** @var ModuleViewerAsset_EntityPackage $oAssetPackage */
                foreach($this->aAssets as $oAssetPackage) {
                    $aTags = $oAssetPackage->getHtmlTags($sAvailPlace);
                    if ($aTags) {
                        $this->aHtmlTags[$sAvailPlace][$oAssetPackage->getOutType()] = $aTags;
                    }
                }
            }
        }
        $aResult = [];
        if (!$sPlace && !$sType) {
            array_walk_recursive($this->aHtmlTags, function($sTag) use (&$aResult) { $aResult[] = $sTag; });
        } elseif ($sPlace) {
            if (!empty($this->aHtmlTags[$sPlace])) {
                array_walk_recursive($this->aHtmlTags[$sPlace], function($sTag) use (&$aResult) { $aResult[] = $sTag; });
            }
        } elseif(!empty($this->aHtmlTags[$sPlace][$sType])) {
            return $this->aHtmlTags[$sPlace][$sType];
        }
        return $aResult;
    }

    /**
     * @return string
     */
    public function hookAssetsHead()
    {
        $aHtmlLinks = $this->getHtmlTags('head');
        return implode("\n", $aHtmlLinks);
    }

    /**
     * @return string
     */
    public function hookAssetsBody()
    {
        $aHtmlLinks = $this->getHtmlTags('body');
        return implode("\n", $aHtmlLinks);
    }

    /**
     * @return string
     */
    public function hookAssetsBottom()
    {
        $aHtmlLinks = $this->getHtmlTags('bottom');
        return implode("\n", $aHtmlLinks);
    }

    /**
     * Shutdown
     */
    public function shutdown()
    {
        \E::HookManager()->addHandler('template_layout_head_assets', [$this, 'hookAssetsHead']);
        \E::HookManager()->addHandler('template_layout_body_begin', [$this, 'hookAssetsBody']);
        \E::HookManager()->addHandler('template_layout_body_end', [$this, 'hookAssetsBottom']);

        parent::shutdown();
    }

}

// EOF