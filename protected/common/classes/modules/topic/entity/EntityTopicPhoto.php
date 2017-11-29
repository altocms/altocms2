<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 * Based on
 *   LiveStreet Engine Social Networking by Mzhelskiy Maxim
 *   Site: www.livestreet.ru
 *   E-mail: rus.engine@gmail.com
 *----------------------------------------------------------------------------
 */

/**
 * Объект сущности аттача
 *
 * @method setId($iParam)
 *
 * @package modules.topic
 * @since   1.0
 */
class ModuleTopic_EntityTopicPhoto extends Entity
{
    /**
     * Возвращает ID объекта
     *
     * @return int
     */
    public function getId()
    {
        return $this->getProp('id');
    }

    /**
     * Возвращает ID топика
     *
     * @return int|null
     */
    public function getTopicId() {

        return $this->getProp('topic_id');
    }

    /**
     * Возвращает ключ временного владельца
     *
     * @return string|null
     */
    public function getTargetTmp() {

        return $this->getProp('target_tmp');
    }

    /**
     * Возвращает описание фото
     *
     * @return string|null
     */
    public function getDescription() {

        return $this->getProp('description');
    }

    /**
     * Вовзращает полный веб путь до файла
     *
     * @return string|null
     */
    public function getPath() {

        return $this->getProp('path');
    }

    /**
     * Возвращает ссылку фото определенного размера
     *
     * @param string|null $xSize    Размер фото, например, '100' или '150crop' или '150x100' или 'x100'
     *
     * @return null|string
     */
    public function getLink($xSize = null) {

        if ($sUrl = $this->getPath()) {
            if ($xSize) {
                $sResizedUrl = $this->getProp('_size-' . $xSize . '-url');
                if ($sResizedUrl) {
                    return $sResizedUrl;
                }
                $aPathInfo = pathinfo($sUrl);

                if (!$sResizedUrl) {
                    $sModSuffix = F::File_ImgModSuffix($xSize, $aPathInfo['extension']);
                    if ($sModSuffix) {
                        $sResizedUrl = $sUrl . $sModSuffix;
                        if (\C::get('module.image.autoresize')) {
                            $sFile = \E::Module('Uploader')->Url2Dir($sResizedUrl);
                            $this->setProp('_size-' . $xSize . '-file', $sFile);
                            if (!F::File_Exists($sFile)) {
                                \E::Module('Img')->Duplicate($sFile);
                            }
                        }
                    }
                }
                if ($sResizedUrl) {
                    $sUrl = F::File_NormPath($sResizedUrl);
                }
                $this->setProp('_size-' . $xSize . '-url', $sUrl);
            }
        }
        return $sUrl;
    }

    /**
     * Alias for getLink()
     *
     * @param null $xSize
     *
     * @return null|string
     */
    public function getUrl($xSize = null) {

        return $this->getLink($xSize);
    }

    public function getImgSize($sSize = null) {

        $aSize = $this->getProp('_size-' . $sSize . '-imgsize');
        if (!$aSize) {
            $sFile = $this->getProp('_size-' . $sSize . '-file');
            if (!$sFile) {
                $sUrl = $this->getLink($sSize);
                $sFile = \E::Module('Uploader')->Url2Dir($sUrl);
                $this->setProp('_size-' . $sSize . '-file', $sFile);
            }
            if ($sFile && F::File_Exists($sFile)) {
                $aSize = getimagesize($sFile);
                $this->setProp('_size-' . $sSize . '-imgsize', $aSize);
            }
        }
        return $aSize;
    }

    public function getImgWidth($sSize = null) {

        $aSize = $this->getImgSize($sSize);
        if (isset($aSize[0])) {
            return $aSize[0];
        }
    }

    public function getImgHeight($sSize = null) {

        $aSize = $this->getImgSize($sSize);
        if (isset($aSize[1])) {
            return $aSize[1];
        }
    }

    public function getImgSizeAttr($sSize = null) {

        $aSize = $this->getImgSize($sSize);
        if (isset($aSize[3])) {
            return $aSize[3];
        }
    }

    /**
     * Устанавливает ID топика
     *
     * @param int $iTopicId
     */
    public function setTopicId($iTopicId) {

        $this->setProp('topic_id', $iTopicId);
    }

    /**
     * Устанавливает ключ временного владельца
     *
     * @param string $sTargetTmp
     */
    public function setTargetTmp($sTargetTmp) {

        $this->setProp('target_tmp', $sTargetTmp);
    }

    /**
     * Устанавливает описание фото
     *
     * @param string $sDescription
     */
    public function setDescription($sDescription) {

        $this->setProp('description', $sDescription);
    }

    public function buildMresource() {

        $oMresource = \E::getEntity('Media_MediaRel');
        $oMresource->SetLink(false);
        $oMresource->SetType(ModuleMedia::TYPE_IMAGE | ModuleMedia::TYPE_PHOTO);
        $oMresource->SetUrl(\E::Module('Media')->normalizeUrl($this->GetPath()));
        return $oMresource;
    }

}

// EOF
