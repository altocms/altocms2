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

class ModuleViewerAsset_EntityPackage extends Entity
{
    /** @var string  */
    protected $sOutType = '*';

    /** @var string  */
    protected $sAssetType = '';

    /** @var bool  */
    protected $bMerge = false;

    /** @var bool  */
    protected $bCompress = false;

    protected $aFiles = [];

    protected $aAssets = [];

    protected $aAssetNames = [];

    /** @var integer  */
    protected $iAssetNum = 0;

    protected $aLinks = [];

    protected $aHtmlLinkParams = [];

    /** @var  csstidy */
    protected $oCompressor;

    protected $aMapDir = [];

    protected $sMarker;

    protected $sAssetDir;

    protected $sAssetUrl;

    /**
     * ModuleViewerAsset_EntityPackage constructor.
     *
     * @param array $aParams
     */
    public function __construct($aParams = [])
    {
        parent::__construct($aParams);
        if (isset($aParams['out_type'])) {
            $this->sOutType = $aParams['out_type'];
        }
        if (isset($aParams['asset_type'])) {
            $this->sAssetType = $aParams['asset_type'];
        }
        if ($this->sOutType) {
            $this->bMerge = (bool)$this->_cfgTypeOption('merge');
            $this->bCompress = (bool)$this->_cfgTypeOption('compress');
        }
        $this->sMarker = uniqid('alto-asset-marker-', true);
        $this->sAssetDir =  \F::File_GetAssetDir();
        $this->sAssetUrl =  \F::File_GetAssetUrl();
    }

    /**
     * @param string $sOption
     *
     * @return mixed
     */
    protected function _cfgTypeOption($sOption)
    {
        return \C::get('assets.' . $this->sOutType . '.' . $sOption);
    }

    /**
     * @param string $sDir
     *
     * @return string
     */
    protected function _makeSubdir($sDir)
    {
        if (!isset($this->aMapDir[$sDir])) {
            if (!substr($sDir, -1) !== '/') {
                $sDir .= '/';
            }
            $this->aMapDir[$sDir] = \E::Module('ViewerAsset')->assetFileHashDir($sDir);
        }
        return $this->aMapDir[$sDir];
    }

    /**
     * @param array  $aFileParams
     * @param string $sAssetName
     *
     * @return string
     */
    protected function _defineAssetName($aFileParams, $sAssetName)
    {
        if ($this->bMerge && $aFileParams['merge']) {
            // Определяем имя набора
            if (!$sAssetName) {
                if (isset($aFileParams['asset'])) {
                    $sAssetName = $aFileParams['asset'];
                } elseif (isset($aFileParams['block'])) {
                    // LS compatible
                    $sAssetName = $aFileParams['block'];
                } else {
                    $sAssetName = '__default';
                }
            }
            if (strpos($sAssetName, '__default') === 0) {
                $sAssetName .= (string)$this->iAssetNum;
            }
        } else {
            // Если слияние отключено, то каждый набор - это отдельный файл,
            // но надо нормализовать имя набора
            if ($aFileParams['name'] && $aFileParams['name'][0] === '?') {
                $sAssetName =  \F::File_NormPath(substr($aFileParams['name'], 1));
            } else {
                $sAssetName =  \F::File_NormPath($aFileParams['name']);
            }
            $aFileParams['merge'] = false;
        }
        return $sAssetName;
    }

    /**
     * @param string $sFileName
     * @param array  $aFileParams
     * @param string $sAssetName
     *
     * @return array
     */
    protected function _prepareParams($sFileName, $aFileParams, $sAssetName)
    {
        // Проверка набора параметров файла
        if (!is_array($aFileParams)) {
            $aFileParams = [];
        }
        if ($sFileName[0] === '?') {
            $sFileName = substr($sFileName, 1);
            $aFileParams['file'] =  \F::File_NormPath($sFileName);
            $aFileParams['if_exists'] = true;
        }
        if (!isset($aFileParams['file'])) {
            $aFileParams['file'] =  \F::File_NormPath($sFileName);
        }
        $aFileParams['info'] =  \F::File_PathInfo($aFileParams['file']);

        // Ссылка или локальный файл
        if (isset($aFileParams['info']['scheme']) && $aFileParams['info']['scheme']) {
            $aFileParams['link'] = true;
        } else {
            $aFileParams['link'] = false;
        }
        // Ссылки пропускаются без обработки
        $aFileParams['throw'] = $aFileParams['link'];

        // По умолчанию файл сливается с остальными,
        // но хаки (с параметром 'browser') и внешние файлы (ссылки) не сливаются
        if (isset($aFileParams['browser']) || $aFileParams['throw']) {
            $aFileParams['merge'] = false;
        }
        if (!isset($aFileParams['merge'])) {
            $aFileParams['merge'] = true;
        }
        if (!isset($aFileParams['compress'])) {
            // Don't need to minify already minified files
            if (substr($aFileParams['info']['filename'], -4) === '.min') {
                //$aFileParams['compress'] = false;
            } else {
                $aFileParams['compress'] = $this->bCompress;
            }
        }

        if (!$this->bMerge) {
            $aFileParams['merge'] = false;
        }
        if (!isset($aFileParams['name'])) {
            $aFileParams['name'] = $sFileName;
        }
        if (!isset($aFileParams['browser'])) {
            $aFileParams['browser'] = null;
        }
        $aAvailPlaces = (array)$this->getProp('avail_places');
        if ($aAvailPlaces) {
            if (!isset($aFileParams['place']) || !in_array($aFileParams['place'], $aAvailPlaces)) {
                $aFileParams['place'] = reset($aAvailPlaces);
            }
        } else {
            $aFileParams['place'] = '';
        }
        $aFileParams['prepare'] = isset($aFileParams['prepare'])? (bool)isset($aFileParams['prepare']) : false;
        $aFileParams['name'] =  \F::File_NormPath($aFileParams['name']);
        $aFileParams['asset'] = $this->_defineAssetName($aFileParams, $sAssetName);

        /*
         * Если среди файлов дефолтных наборов встречается ссылка,
         * то может быть нарушен порядок добавления файлов, а он иногда важен.
         * Для того, чтобы этого избежать, мы разбиваем дефолтные наборы на поднаборы,
         * если среди них есть ссылки. Для этого и используется $this->iAssetNum
         */
        // Это необходимо, чтобы файлы из дефолтных наборов шли в том же порядке, как заданы в конфиге
        if ($aFileParams['link']) {
            $this->iAssetNum += 1;
        }

        return $aFileParams;
    }

    /**
     * @param array  $aFileParams
     * @param bool   $bPrepend
     *
     * @return int
     */
    protected function _add($aFileParams, $bPrepend = false)
    {
        $sName = $aFileParams['name'];
        $sAssetName = $aFileParams['asset'];
        // If this asset does not exist then add it into stack
        if (!isset($this->aFiles[$sAssetName])) {
            $this->aFiles[$sAssetName] = ['_append_' => [], '_prepend_' => []];
            if ($bPrepend) {
                array_unshift($this->aAssetNames, $sAssetName);
            } else {
                $this->aAssetNames[] = $sAssetName;
            }
        }
        if (isset($this->aFiles[$sAssetName]['_append_'][$sName])) {
            if (!empty($aFileParams['replace'])) {
                unset($this->aFiles[$sAssetName]['_append_'][$sName]);
            } else {
                return 0;
            }
        } elseif (isset($this->aFiles[$sAssetName]['_prepend_'][$sName])) {
            if (!empty($aFileParams['replace'])) {
                unset($this->aFiles[$sAssetName]['_prepend_'][$sName]);
            } else {
                return 0;
            }
        }
        $this->aFiles[$sAssetName][$bPrepend ? '_prepend_' : '_append_'][$sName] = $aFileParams;

        return 1;
    }

    /**
     * Initialization
     */
    public function init()
    {
        $this->aHtmlLinkParams = [];
    }

    /**
     * @return string
     */
    public function getHash()
    {
        return $this->sAssetType . '-' . md5(serialize($this->aFiles));
    }

    /**
     * @return string
     */
    public function getOutType()
    {
        return $this->sOutType;
    }

    /**
     * Добавляет ссылку в набор
     *
     * @param string $sOutType
     * @param string $sLink
     * @param array  $aParams
     */
    public function addLink($sOutType, $sLink, $aParams = [])
    {
        if ($sOutType !== $this->sOutType) {
            \E::Module('ViewerAsset')->addLinksToAssets('*', [$sLink => $aParams]);
        } else {
            $this->aLinks[] = array_merge($aParams,['link' => $sLink]);
        }
    }

    /**
     * Сжатие контента
     *
     * @param string $sContents
     *
     * @return string
     */
    public function compress($sContents)
    {
        return $sContents;
    }

    /**
     * Обработка файла
     *
     * @param string $sFile
     * @param string $sDestination
     *
     * @return string
     */
    public function prepareFile($sFile, $sDestination)
    {
        return  \F::File_Copy($sFile, $sDestination);
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
        return $sContents;
    }

    /**
     * Создание ресурса из одиночного файла
     *
     * @param string $sAsset
     * @param array  $aFileParams
     *
     * @return bool
     */
    public function makeSingle($sAsset, $aFileParams)
    {
        $sFile = $aFileParams['file'];
        if (isset($aFileParams['dir_from'])) {
            $sLocalPath =  \F::File_LocalPath(dirname($sFile), $aFileParams['dir_from']);
            $sDir = $aFileParams['dir_from'];
        } else {
            $sLocalPath = '';
            $sDir = dirname($sFile);
        }
        if ($aFileParams['merge']) {
            $sSubdir = $this->_makeSubdir($sAsset . $sDir);
        } else {
            $sSubdir = $this->_makeSubdir($sDir);
        }
        if ($sLocalPath) {
            $sSubdir .= '/' . $sLocalPath;
        }
        $sDestination = $this->sAssetDir . $sSubdir . '/' . basename($sFile);
        $aFileParams['asset_file'] = $sDestination;
        if (!$this->checkDestination($sDestination)) {
            // conditional file processing - if the file not exists then just skip one
            if (!empty($aFileParams['if_exists']) && !F::File_Exists($sFile)) {
                return true;
            }
            $sDestination = $this->prepareFile($sFile, $sDestination);
            if ($sDestination === false) {
                 \F::sysWarning('Can not prepare asset file "' . $sFile . '"');
                return false;
            }
        }
        $this->addLink($aFileParams['info']['extension'], \E::Module('ViewerAsset')->assetFileDir2Url($sDestination), $aFileParams);

        return true;
    }

    /**
     * Создание ресурса из множества файлов
     *
     * @param string $sAsset
     * @param array  $aFiles
     *
     * @return bool
     */
    public function makeMerge($sAsset, $aFiles)
    {
        $sDestination = $this->sAssetDir . md5($sAsset . serialize($aFiles)) . '.' . $this->sOutType;
        if (!$this->checkDestination($sDestination)) {
            $sContents = '';
            $bCompress = $this->bCompress;
            $bPrepare = null;
            foreach ($aFiles as $aFileParams) {
                $sFileContents = trim(\F::File_GetContents($aFileParams['file']));
                $sContents .= $this->prepareContents($sFileContents, $aFileParams['file']) . PHP_EOL;
                if (isset($aFileParams['compress'])) {
                    $bCompress = $bCompress && (bool)$aFileParams['compress'];
                }
                // Если хотя бы один файл из набора нужно выводить, то весь набор выводится
                if (((null === $bPrepare) || $bPrepare === true) && isset($aFileParams['prepare']) && !$aFileParams['prepare']) {
                    $bPrepare = false;
                }
            }
            if (\F::File_PutContents($sDestination, $sContents, LOCK_EX, true)) {
                $aParams = [
                    'file' => $sDestination,
                    'asset' => $sAsset,
                    'asset_file' => $sDestination,
                    'compress' => $bCompress,
                    'prepare' => (null === $bPrepare) ? false : $bPrepare,
                ];
                $this->addLink($this->sOutType, \E::Module('ViewerAsset')->assetFileDir2Url($sDestination), $aParams);
            } else {
                return false;
            }
        } else {
            $aParams =[
                'file' => $sDestination,
                'asset' => $sAsset,
                'asset_file' => $sDestination,
                'compress' => $this->bCompress,
                'prepare' => false,
            ];
            $this->addLink($this->sOutType, \E::Module('ViewerAsset')->assetFileDir2Url($sDestination), $aParams);
        }
        return true;
    }

    /**
     * Проверка итогового файла назначения
     *
     * @param string $sDestination
     *
     * @return bool
     */
    public function checkDestination($sDestination)
    {
        // Проверка минифицированного файла
        if (substr($sDestination, -strlen($this->sOutType) - 5) === '.min.' . $this->sOutType) {
            return  \F::File_Exists($sDestination);
        }
        $sDestinationMin =  \F::File_SetExtension($sDestination, 'min.' . $this->sOutType);
        if ($this->bCompress) {
            return  \F::File_Exists($sDestinationMin) ||  \F::File_Exists($sDestination);
        }
        return  \F::File_Exists($sDestination);
    }

    /**
     * Препроцессинг
     */
    public function preProcess()
    {
        $bResult = true;

        // Создаем окончательные наборы, сливая prepend и append
        $this->aAssets = [];
        if ($this->aFiles) {
            foreach ($this->aAssetNames as $sAsset) {
                $aFileStacks = $this->aFiles[$sAsset];
                if (!empty($aFileStacks['_prepend_']) || !empty($aFileStacks['_append_'])) {
                    if (!empty($aFileStacks['_prepend_']) && !empty($aFileStacks['_append_'])) {
                        // both prepend and append
                        $this->aAssets[$sAsset] = array_merge(array_reverse($aFileStacks['_prepend_']), $aFileStacks['_append_']);
                    } elseif ($aFileStacks['_append_']) {
                        // append only
                        $this->aAssets[$sAsset] = $aFileStacks['_append_'];
                    } else {
                        // prepend only
                        $this->aAssets[$sAsset] = array_reverse($aFileStacks['_prepend_']);
                    }
                }
            }
        }

        // Обрабатываем наборы
        foreach ($this->aAssets as $sAsset => $aFiles) {
            if (count($aFiles) === 1) {
                // Одиночный файл
                $aFileParams = array_shift($aFiles);
                if ($aFileParams['throw']) {
                    // Throws without prepare (e.c. external links)
                    $this->addLink($this->sOutType, $aFileParams['file'], $aFileParams);
                } else {
                    // Prepares single file
                    $this->makeSingle($sAsset, $aFileParams);
                }
            } else {
                // Prepares set of several files
                $this->makeMerge($sAsset, $aFiles);
            }
        }

        return $bResult;
    }

    /**
     * Processing of asset package
     *
     * @return bool
     */
    public function process()
    {
        return true;
    }

    /**
     * Postprocessing of asset package
     *
     * @return bool
     */
    public function postProcess()
    {
        return true;
    }

    /**
     * @param array  $aFiles
     * @param string $sAssetName
     * @param bool   $bPrepend
     * @param bool   $bReplace
     *
     * @return int
     */
    public function addFiles($aFiles, $sAssetName = null, $bPrepend = false, $bReplace = null)
    {
        $iCount = 0;
        foreach ($aFiles as $sFileName => $aFileParams) {
            if (null === $bReplace) {
                $aFileParams['replace'] = (isset($aFileParams['replace']) ? (bool)$aFileParams['replace'] : false);
            }
            $aFileParams = $this->_prepareParams($sFileName, $aFileParams, $sAssetName);
            $iCount += $this->_add($aFileParams, $bPrepend);
        }

        return $iCount;
    }

    /**
     * @param string $sAssetName
     */
    public function clear($sAssetName = null)
    {
        if ($sAssetName) {
            if (isset($this->aFiles[$sAssetName])) {
                unset($this->aFiles[$sAssetName]);
            }
        } else {
            $this->aFiles = [];
        }
    }

    /**
     * @param array  $aFiles
     * @param string $sAssetName
     */
    public function exclude($aFiles, $sAssetName = null)
    {
        foreach ($aFiles as $sFileName => $aFileParams) {
            $aFileParams = $this->_prepareParams($sFileName, $aFileParams, $sAssetName);
            $sName = $aFileParams['name'];
            if (!isset($this->aFiles[$sAssetName])) {
                $this->aFiles[$sAssetName] = ['_append_' => [], '_prepend_' => []];
            }
            if (isset($this->aFiles[$sAssetName]['_append_'][$sName])) {
                unset($this->aFiles[$sAssetName]['_append_'][$sName]);
            } elseif (isset($this->aFiles[$sAssetName]['_prepend_'][$sName])) {
                unset($this->aFiles[$sAssetName]['_prepend_'][$sName]);
            }
        }
    }

    /**
     * @param int $nStage
     *
     * @return bool
     */
    protected function _stageBegin($nStage)
    {
        $sFile = $this->sAssetDir . '_check/' . $this->getHash();

        if ($aCheckFiles = glob($sFile . '.{1,2,3}.begin.tmp', GLOB_BRACE)) {
            $sCheckFile = reset($aCheckFiles);
            // check time of tmp file
            $nTime = filectime($sCheckFile);
            if (!$nTime) {
                $nTime =  \F::File_GetContents($sCheckFile);
            }
            if (time() < $nTime + ModuleViewerAsset::TMP_TIME) {
                return false;
            }
        }

        if (($nStage == 2) && ($aCheckFiles = glob($sFile . '.{2,3}.end.tmp', GLOB_BRACE))) {
            return false;
        } elseif (($nStage == 3) &&  \F::File_Exists($sFile . '.3.end.tmp')) {
            return false;
        }
        return  \F::File_PutContents($sFile . '.' . $nStage . '.begin.tmp', time(), LOCK_EX, true);
    }

    /**
     * @param int  $nStage
     * @param bool $bFinal
     */
    protected function _stageEnd($nStage, $bFinal = false)
    {
        $sFile = $this->sAssetDir . '_check/' . $this->getHash();
         \F::File_PutContents($sFile . '.' . $nStage . '.end.tmp', time(), LOCK_EX, true);
        for ($n = 1; $n <= $nStage; $n++) {
             \F::File_Delete($sFile . '.' . $n . '.begin.tmp');
            if ($n < $nStage || $bFinal) {
                 \F::File_Delete($sFile . '.' . $n . '.end.tmp');
            }
        }
    }

    /**
     * @return bool
     */
    public function preProcessBegin()
    {
        return $this->_stageBegin('1');
    }

    /**
     *
     */
    public function preProcessEnd()
    {
        return $this->_stageEnd('1');
    }

    /**
     * @return bool
     */
    public function processBegin()
    {
        return $this->_stageBegin('2');
    }

    /**
     *
     */
    public function processEnd()
    {
        return $this->_stageEnd('2');
    }

    /**
     * @return bool
     */
    public function postProcessBegin()
    {
        return $this->_stageBegin('3');
    }

    /**
     *
     */
    public function postProcessEnd()
    {
        return $this->_stageEnd('3', true);
    }

    /**
     *
     */
    public function prepare()
    {
        if ($this->preProcessBegin()) {
            $this->preProcess();
            $this->preProcessEnd();
        }
        if ($this->processBegin()) {
            $this->process();
            $this->processEnd();
        }
        if ($this->postProcessBegin()) {
            $this->postProcess();
            $this->postProcessEnd();
        }
    }

    /**
     * @param string $bPreparedOnly
     * @param bool   $bSkipWithoutName
     *
     * @return array
     */
    public function getLinks($bPreparedOnly = null, $bSkipWithoutName = false)
    {
        if (is_null($bPreparedOnly)) {
            return $this->aLinks;
        } else {
            $aResult = [];
            foreach ($this->aLinks as $sIdx => $aLinkData) {
                if (($aLinkData['prepare'] == (bool)$bPreparedOnly) && (!$bSkipWithoutName || $aLinkData['file'] !== $aLinkData['name'])) {
                    $aResult[$sIdx] = $aLinkData;
                }
            }
            return $aResult;
        }
    }

    /**
     * @return array
     */
//    public function getBrowserLinks() {
//
//        return $this->aBrowserLinks;
//    }

    /**
     * @param array $aLink
     *
     * @return string
     */
    public function buildHtmlTag($aLink)
    {
        $sResult = '<' . $this->aHtmlLinkParams['tag'] . ' ';
        foreach ((array)$this->aHtmlLinkParams['attr'] as $sName => $sVal) {
            if ($sVal === '@link') {
                $sResult .= $sName . '="' . $aLink['link'] . '" ';
            } else {
                $sResult .= $sName . '="' . $sVal . '" ';
            }
        }
        if (isset($aLink['attr'])) {
            foreach ($aLink['attr'] as $sAttr) {
                $sResult .= $sAttr . ' ';
            }
        }
        if ($this->aHtmlLinkParams['pair']) {
            $sResult .= '></' . $this->aHtmlLinkParams['tag'] . '>';
        } else {
            $sResult .= '/>';
        }
        if (isset($aLink['browser'])) {
            return "<!--[if {$aLink['browser']}]>$sResult<![endif]-->";
        }
        return $sResult;
    }

    /**
     * @return array
     */
    public function getHtmlTagsArray()
    {
        $aResult = [];
        foreach ($this->aLinks as $aLinkData) {
            if (!$aLinkData['prepare']) {
                if (!isset($aLinkData['html'])) {
                    $aLinkData['html'] = $this->buildHtmlTag($aLinkData);
                }
                $aResult[$aLinkData['place']][] = $aLinkData['html'];
            }
        }
        return $aResult;
    }

    /**
     * @param string $sPlace
     *
     * @return array
     */
    public function getHtmlTags($sPlace = null)
    {
        $aResult = [];
        if ($this->sOutType !== '*') {
            $aHtmlTags = $this->getHtmlTagsArray();
            if ($sPlace) {
                return isset($aHtmlTags[$sPlace]) ? $aHtmlTags[$sPlace] : [];
            }
            foreach ($aHtmlTags as $aPlaceTags) {
                $aResult = array_merge($aResult, $aPlaceTags);
            }
        }
        return $aResult;
    }

}

// EOF