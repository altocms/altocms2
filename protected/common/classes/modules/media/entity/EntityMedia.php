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
 * Class ModuleMedia_EntityMedia
 *
 * @method setMediaId(int $iParam)
 * @method setUserId(int $iParam)
 * @method setTargetId(int $iParam)
 * @method setTargetType(string $sParam)
 * @method setTargetsCount(int $iParam)
 * @method setUser(object $oParam)
 * @method setLink(string $sParam)
 * @method setHashFile(string $sParam)
 * @method setHashUrl(string $sParam)
 * @method setPathFile(string $sParam)
 * @method setPathUrl(string $sParam)
 * @method setType(string $sParam)
 * @method setStorage(string $sParam)
 * @method setIncount(int $iParam)
 *
 * @method int      getMediaId()
 * @method int      getUserId()
 * @method string   getTargetType()
 * @method int      getTargetsCount()
 * @method object   getUser()
 * @method int      getTargetId()
 * @method string   getLink()
 * @method string   getHashFile()
 * @method string   getHashUrl()
 * @method string   getPathFile()
 * @method string   getPathUrl()
 * @method int      getType()
 * @method string   getStorage()
 * @method int      getIncount()
 */
class ModuleMedia_EntityMedia extends Entity
{
    /**
     * Массив параметров ресурса
     *
     * @var array
     */
    protected $aParams = null;

    /**
     * ModuleMedia_EntityMedia constructor.
     *
     * @param null|array $aParam
     */
    public function __construct($aParam = null)
    {
        if ($aParam && $aParam instanceOf ModuleUploader_EntityItem) {
            $oUploaderItem = $aParam;
            $aParam = $oUploaderItem->getAllProps();
        } else {
            $oUploaderItem = null;
        }
        parent::__construct($aParam);
        if ($oUploaderItem) {
            $this->setUrl($oUploaderItem->getUrl());
            if ($oUploaderItem->getFile()) {
                $this->SetFile($oUploaderItem->getFile());
            }
            $this->setType($oUploaderItem->getProp('is_image') ? ModuleMedia::TYPE_IMAGE : 0);
        }
    }

    /**
     * Checks if resource is external link
     *
     * @return bool
     */
    public function isLink()
    {
        return (bool)$this->getLink();
    }

    /**
     * Checks if resource is local file
     *
     * @return bool
     */
    public function isFile()
    {
        return !$this->IsLink() && $this->getHashFile();
    }

    /**
     * @param $nMask
     *
     * @return int
     */
    public function isType($nMask)
    {
        return $this->getPropMask('type', $nMask);
    }

    /**
     * Checks if resource is image
     *
     * @return bool
     */
    public function isImage()
    {
        return $this->isType(ModuleMedia::TYPE_IMAGE | ModuleMedia::TYPE_PHOTO | ModuleMedia::TYPE_PHOTO_PRIMARY);
    }

    /**
     * Checks if resource is image
     *
     * @return bool
     */
    public function isGraphicFile()
    {
        return $this->isType(ModuleMedia::TYPE_IMAGE | ModuleMedia::TYPE_PHOTO | ModuleMedia::TYPE_PHOTO_PRIMARY);
    }

    /**
     * Checks if resource can be deleted
     *
     * @return bool
     */
    public function canDelete()
    {
        return (bool)$this->getProp('candelete');
    }

    /**
     * Sets full url of resource
     *
     * @param $sUrl
     */
    public function setUrl($sUrl)
    {
        if ($sUrl[0] === '@') {
            $sPathUrl = substr($sUrl, 1);
            $sUrl = F::File_RootUrl() . $sPathUrl;
        } else {
            $sPathUrl = F::File_LocalUrl($sUrl);
        }
        if ($sPathUrl) {
            // Сохраняем относительный путь
            $this->SetPathUrl('@' . trim($sPathUrl, '/'));
            if (!$this->getPathFile()) {
                $this->SetFile(\F::File_Url2Dir($sUrl));
            }
        } else {
            // Сохраняем абсолютный путь
            $this->SetPathUrl($sUrl);
        }
        if (null === $this->getPathFile()) {
            if (null === $this->getLink()) {
                $this->setLink(true);
            }
            if (null === $this->getType()) {
                $this->setType(ModuleMedia::TYPE_HREF);
            }
        }
        $this->recalcHash();
    }

    /**
     * Sets full dir path of resource
     *
     * @param $sFile
     */
    public function setFile($sFile)
    {
        if ($sFile) {
            if ($sPathDir = F::File_LocalDir($sFile)) {
                // Сохраняем относительный путь
                $this->setPathFile('@' . $sPathDir);
                if (!$this->getPathUrl()) {
                    $this->setUrl(\F::File_Dir2Url($sFile));
                }
            } else {
                // Сохраняем абсолютный путь
                $this->setPathFile($sFile);
            }
            $this->setLink(false);
            if (!$this->GetStorage()) {
                $this->SetStorage('file');
            }
        } else {
            $this->SetPathFile(null);
        }
        $this->RecalcHash();
    }

    /**
     * Returns ID of media resource
     *
     * @return int
     */
    public function getId()
    {
        return $this->getProp('media_id');
    }

    /**
     * Returns full url to media resource
     *
     * @return string
     */
    public function getUrl()
    {
        $sPathUrl = $this->getPathUrl();
        $sUrl = \E::Module('Uploader')->completeUrl($sPathUrl);

        return $sUrl;
    }

    /**
     * Returns full dir path to media resource
     *
     * @return string
     */
    public function getFile()
    {
        $sPathFile = $this->getPathFile();
        $sFile = \E::Module('Uploader')->completeDir($sPathFile);

        return $sFile;
    }

    /**
     * Returns uniq ID of media
     *
     * @return string
     */
    public function getUuid()
    {
        $sResult = $this->getProp('uuid');
        if (!$sResult) {
            if ($this->getStorage() === 'file') {
                $sResult = ModuleMedia::createUuid($this->getStorage(), $this->getPathFile(), $this->getHashFile(), $this->getUserId());
            } elseif (!$this->getStorage()) {
                $sResult = $this->getHashUrl();
            }
            $this->setProp('uuid', $sResult);
        }
        return $sResult;
    }

    /**
     * Returns storage name and uniq ID of media
     *
     * @return string
     */
    public function getStorageUuid()
    {
        return '[' . $this->getStorage() . ']' . $this->getUuid();
    }

    /**
     * Recalc both hashs (url & dir)
     */
    public function recalcHash()
    {
        if (($sFile = $this->getFile()) && F::File_Exists($sFile)) {
            $sHashFile = md5_file($sFile);
        } else {
            $sHashFile = null;
        }
        if ($sPathUrl = $this->getPathUrl()) {
            $sHashUrl = \E::Module('Media')->calcUrlHash($sPathUrl);
        } else {
            $sHashUrl = null;
        }
        $this->setHashUrl($sHashUrl);
        $this->setHashFile($sHashFile);
    }

    /**
     * Returns hash of mresoutce
     *
     * @return string
     */
    public function getHash()
    {
        return $this->GetHashUrl();
    }

    /**
     * Checks if media local image and its derived from another image
     *
     * @return bool
     */
    public function isDerivedImage()
    {
        return $this->getHash() !== $this->getOriginalHash();
    }

    /**
     * Returns original image path (if mresoutce is local image)
     *
     * @return string
     */
    public function getOriginalPathUrl()
    {
        $sPropKey = '-original-url';
        if (!$this->isProp($sPropKey)) {
            $sUrl = $this->getPathUrl();
            if (!$this->isLink() && $this->isImage() && $sUrl) {
                $aOptions = [];
                $sOriginal = \E::Module('Img')->originalFile($sUrl, $aOptions);
                if ($sOriginal !== $sUrl) {
                    $sUrl = $sOriginal;
                }
            }
            $this->setProp($sPropKey, $sUrl);
        }
        return $this->getProp($sPropKey);
    }

    /**
     * Returns hash of original local image
     * If media isn't a local image then returns ordinary hash
     *
     * @return string
     */
    public function getOriginalHash()
    {
        $sPropKey = '-original-hash';
        if (!$this->isProp($sPropKey)) {
            $sHash = $this->getHash();
            if (($sPathUrl = $this->getPathUrl()) && ($sOriginalUrl = $this->getOriginalPathUrl())) {
                if ($sOriginalUrl !== $sPathUrl) {
                    $sHash = \E::Module('Media')->calcUrlHash($sOriginalUrl);
                }
            }
            $this->setProp($sPropKey, $sHash);
        }
        return $this->getProp($sPropKey);
    }

    /**
     * Returns image URL with requested size
     *
     * @param string|int $xSize
     *
     * @return string
     */
    public function getImgUrl($xSize = null)
    {
        $sUrl = $this->getUrl();
        if (!$xSize) {
            return $sUrl;
        }

        $sModSuffix = F::File_ImgModSuffix($xSize, pathinfo($sUrl, PATHINFO_EXTENSION));

        $sPropKey = '_img-url-' . ($sModSuffix ? $sModSuffix : $xSize);
        $sResultUrl = $this->getProp($sPropKey);
        if ($sResultUrl) {
            return $sResultUrl;
        }

        if (!$this->isLink() && $this->isType(ModuleMedia::TYPE_IMAGE | ModuleMedia::TYPE_PHOTO)) {
            if ($sModSuffix && \F::File_IsLocalUrl($sUrl)) {
                $sUrl .= $sModSuffix;
                if (\C::get('module.image.autoresize')) {
                    $sFile = \E::Module('Uploader')->url2Dir($sUrl);
                    if (!F::File_Exists($sFile)) {
                        \E::Module('Img')->Duplicate($sFile);
                    }
                }
            }
        }
        $this->setProp($sPropKey, $sUrl);

        return $sUrl;
    }

    /**
     * Check if current media exists in storage
     *
     * @return bool
     */
    public function Exists()
    {
        if ($this->getStorage() === 'file') {
            $sCheckUuid = '[file]' . $this->getFile();
        } else {
            $sCheckUuid = $this->getStorageUuid();
        }
        return \E::Module('Uploader')->exists($sCheckUuid);
    }

    /**
     * @param bool $xSize
     *
     * @return string
     */
    public function getWebPath($xSize=FALSE)
    {
        $sUrl = \E::Module('Uploader')->completeUrl($this->getPathUrl());
        if (!$xSize) {
            return $sUrl;
        }

        return \E::Module('Uploader')->resizeTargetImage($sUrl, $xSize);

    }

    /**
     * Возвращает сериализованную строку дополнительных данных ресурса
     *
     * @return string
     */
    public function getParams()
    {
        $sResult = $this->getProp('params');
        return (null !== $sResult) ? $sResult : serialize('');
    }

    /**
     * Устанавливает сериализованную строчку дополнительных данных
     *
     * @param string|array $data
     */
    public function setParams($data)
    {
        $this->setProp('params', serialize($data));
    }

    /**
     * Получает описание ресурса
     *
     * @return mixed|null
     */
    public function getDescription()
    {
        return $this->getParamValue('description');
    }

    /**
     * Устанавливает описание ресурса
     *
     * @param $sValue
     */
    public function setDescription($sValue)
    {
        $this->setParamValue('description', $sValue);
    }


    public function isCover()
    {
        return $this->getType() === ModuleMedia::TYPE_PHOTO_PRIMARY;
    }
    /* ****************************************************************************************************************
 * методы расширения типов топика
 * ****************************************************************************************************************
 */

    /**
     * Извлекает сериализованные данные топика
     */
    protected function extractParams()
    {
        if (null === $this->aParams) {
            $this->aParams = @unserialize($this->getParams());
        }
    }

    /**
     * Устанавливает значение нужного параметра
     *
     * @param string $sName    Название параметра/данных
     * @param mixed  $data     Данные
     *
     * @return $this
     */
    protected function setParamValue($sName, $data)
    {
        $this->extractParams();
        $this->aParams[$sName] = $data;
        $this->setParams($this->aParams);

        return $this;
    }

    /**
     * Извлекает значение параметра
     *
     * @param string $sName    Название параметра
     *
     * @return null|mixed
     */
    public function getParamValue($sName)
    {
        $this->extractParams();
        if (isset($this->aParams[$sName])) {
            return $this->aParams[$sName];
        }
        return null;
    }
}

// EOF