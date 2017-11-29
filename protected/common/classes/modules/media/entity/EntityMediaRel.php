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
 * Class ModuleMedia_EntityMediaRel
 *
 * @method setType($xParam)
 * @method setUserId($iUserId)
 *
 * @method int getMediaId()
 * @method string getTargetType()
 *
 */
class ModuleMedia_EntityMediaRel extends ModuleMedia_EntityMedia
{
    /**
     * @return int
     */
    public function getId()
    {
        return (int)$this->getProp('id');
    }

    /**
     * @param null $xSize
     *
     * @return string|null
     */
    public function getImageUrl($xSize = null) {

        $sUrl = $this->getPathUrl();
        if ($sUrl) {
            $sUrl = \E::Module('Uploader')->CompleteUrl($sUrl);
            if (!$xSize) {
                return $sUrl;
            }

            return \E::Module('Uploader')->ResizeTargetImage($sUrl, $xSize);
        }
        return null;
    }

    /**
     * @return bool|ModuleImg_EntityImage|null
     */
    protected function _getImageObject() {

        if ($this->isImage()) {
            $oImg = $this->getProp('__image');
            if ($oImg === null) {
                if (!($sFile = $this->GetFile()) || !($oImg = \E::Module('Img')->Read($sFile))) {
                    $oImg = false;
                }
            }
            return $oImg;
        }
        return null;
    }

    /**
     * @return int|null
     */
    public function getSizeWidth() {

        $oImg = $this->_getImageObject();
        if ($oImg) {
            return $oImg->GetWidth();
        }
        return null;
    }

    /**
     * @return int|null
     */
    public function getSizeHeight() {

        $oImg = $this->_getImageObject();
        if ($oImg) {
            return $oImg->GetHeight();
        }
        return null;
    }

}

// EOF