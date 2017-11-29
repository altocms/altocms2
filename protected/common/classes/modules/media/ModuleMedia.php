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
 * @package modules.media
 * @since   1.0
 */
class ModuleMedia extends Module {

    const TYPE_IMAGE    = 1;
    const TYPE_VIDEO    = 2;
    const TYPE_AUDIO    = 4;
    const TYPE_FLASH    = 8;
    const TYPE_PHOTO    = 16; // Элемент фотосета
    const TYPE_HREF     = 32;
    const TYPE_PHOTO_PRIMARY  = 64; // Обложка фотосета
    const TYPE_OTHERS  = 1024;      // Other types

    /** @var  ModuleMedia_MapperMedia */
    protected $oMapper;

    /**
     * Инициализация
     *
     */
    public function init()
    {
        $this->oMapper = \E::getMapper(__CLASS__);
    }

    /**
     * Создание сущности медиа ресурса ссылки
     *
     * @param string $sLink
     *
     * @return ModuleMedia_EntityMedia
     */
    public function buildMediaLink($sLink)
    {
        /** @var ModuleMedia_EntityMedia $oMresource */
        $oMresource = \E::getEntity('Media');
        $oMresource->setUrl($this->normalizeUrl($sLink));

        return $oMresource;
    }

    /**
     * Создание хеш-списка ресурсов, где индексом является хеш
     *
     * @param ModuleMedia_EntityMedia[] $aMedias
     *
     * @return array
     */
    public function buildMediaHashList($aMedias)
    {
        if ($this->isHashList($aMedias)) {
            return $aMedias;
        }
        /** @var ModuleMedia_EntityMedia[] $aHashList */
        $aHashList = [];
        foreach ($aMedias as $oMedia) {
            $sHash = $oMedia->getHashUrl();
            if (isset($aHash[$sHash])) {
                $aHashList[$sHash]->setIncount(1 + $aHashList[$sHash]->getIncount());
            } else {
                $aHashList[$sHash] = $oMedia;
            }
        }
        return $aHashList;
    }

    /**
     * Проверка, является ли массив хеш-списком ресурсов
     *
     * @param array $aMedia
     *
     * @return bool
     */
    public function isHashList($aMedia)
    {
        if (is_array($aMedia)) {
            // first element of array
            $aData = reset($aMedia);
            if (($aData['value'] instanceof ModuleMedia_EntityMedia) && ($aData['value']->getHash() === $aData['key'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Нормализация URL
     *
     * @param string|array $xUrl
     * @param string       $sReplace
     * @param string       $sAdditional
     *
     * @return array|string
     */
    public function normalizeUrl($xUrl, $sReplace = '@', $sAdditional = '')
    {
        if (is_array($xUrl)) {
            foreach ((array)$xUrl as $nI => $sUrl) {
                $xUrl[$nI] = $this->normalizeUrl((string)$sUrl, $sReplace, $sAdditional);
            }
            return $xUrl;
        }
        $sUrl = str_replace(array('http://@' . $sAdditional, 'https://@' . $sAdditional, 'ftp://@' . $sAdditional), $sReplace, $xUrl);

        return F::File_NormPath($sUrl);
    }

    /**
     * Добавление ресурса
     *
     * @param array|ModuleMedia_EntityMedia $oMediaResource
     *
     * @return bool
     */
    public function add($oMediaResource)
    {
        if (!$oMediaResource) {
            return null;
        }
        $iNewId = 0;
        if (is_array($oMediaResource)) {
            $aResources = $oMediaResource;
            // Групповое добавление
            foreach ($aResources as $nIdx => $oResource) {
                if ($iNewId = $this->oMapper->add($oResource)) {
                    $aResources[$nIdx] = $this->getMresourceById($iNewId);
                }
            }
        } else {
            if ($iNewId = $this->oMapper->add($oMediaResource)) {
                $oMediaResource = $this->getMresourceById($iNewId);
            }
        }
        if ($iNewId) {
            //чистим зависимые кеши
            \E::Module('Cache')->cleanByTags(array('media_update'));
        }
        return $iNewId;
    }

    /**
     * Add relations between mresources and target
     *
     * @param ModuleMedia_EntityMedia[] $aMresources
     * @param string                            $sTargetType
     * @param int                               $iTargetId
     *
     * @return bool
     */
    public function addTargetRel($aMresources, $sTargetType, $iTargetId) {

        if (!is_array($aMresources)) {
            $aMresources = array($aMresources);
        }
        $aMresources = $this->buildMediaHashList($aMresources);
        $aNewMresources = $aMresources;

        // Проверяем, есть ли эти ресурсы в базе
        $aMresourcesDb = $this->oMapper->getMresourcesByHashUrl(array_keys($aMresources));
        if ($aMresourcesDb) {
            /** @var ModuleMedia_EntityMedia $oMresourceDb */
            foreach($aMresourcesDb as $oMresourceDb) {
                if (isset($aMresources[$oMresourceDb->GetHash()])) {
                    // Такой ресурс есть, удаляем из списка на добавление
                    $aMresources[$oMresourceDb->GetHash()]->setMediaId($oMresourceDb->GetId());
                    unset($aNewMresources[$oMresourceDb->GetHash()]);
                }
            }
        }

        // Добавляем новые ресурсы, если есть
        if ($aNewMresources) {
            /** @var ModuleMedia_EntityMedia $oNewMresource */
            foreach ($aNewMresources as $oNewMresource) {
                $oSavedMresource = $this->getMresourcesByUuid($oNewMresource->GetStorageUuid());
                // Если ресурс в базе есть, но файла нет (если удален извне), то удаляем ресус из базы
                if ($oSavedMresource && !$oSavedMresource->isLink() && !$oSavedMresource->Exists()) {
                    $this->deleteMedia($oSavedMresource, false);
                    $oSavedMresource = null;
                }
                if (!$oSavedMresource) {
                    // Если ресурса нет, то добавляем
                    $nId = $this->oMapper->add($oNewMresource);
                } else {
                    // Если ресурс есть, то просто его ID берем
                    $nId = $oSavedMresource->getId();
                }
                if ($nId && isset($aMresources[$oNewMresource->GetHash()])) {
                    // Такой ресурс есть, удаляем из списка на добавление
                    $aMresources[$oNewMresource->GetHash()]->setMediaId($nId);
                }
            }
        }

        // Добавляем связь ресурса с сущностью
        if ($aMresources) {
            /** @var ModuleMedia_EntityMedia $oMresource */
            foreach($aMresources as $oMresource) {
                if (!$oMresource->GetTargetType()) {
                    $oMresource->SetTargetType($sTargetType);
                }
                if (!$oMresource->GetTargetid()) {
                    $oMresource->SetTargetId($iTargetId);
                }
                $this->oMapper->addTargetRel($oMresource);
            }
        }
        \E::Module('Cache')->cleanByTags(array('media_rel_update'));

        return true;
    }

    /**
     * @param int $iId
     *
     * @return ModuleMedia_EntityMedia|null
     */
    public function getMresourceById($iId) {

        $aData = $this->oMapper->getMediaById(array($iId));
        if (isset($aData[$iId])) {
            return $aData[$iId];
        }
        return null;
    }

    /**
     * @param $xUuid
     *
     * @return array|ModuleMedia_EntityMedia
     */
    public function getMresourcesByUuid($xUuid) {

        $bSingleRec = !is_array($xUuid);
        $aData = $this->oMapper->getMresourcesByUuid($xUuid);
        if ($aData) {
            if ($bSingleRec) {
                return reset($aData);
            } else {
                return $aData;
            }
        }
        return $bSingleRec ? null : [];
    }

    /**
     * @param array $aCriteria
     *
     * @return array
     */
    public function getMresourcesByCriteria($aCriteria) {

        $aData = $this->oMapper->getMediaByCriteria($aCriteria);
        if ($aData['data']) {
            $aCollection = \E::getEntityRows('Media', $aData['data']);
            if (isset($aCriteria['with'])) {
                if (!is_array($aCriteria['with'])) {
                    $aCriteria['with'] = array($aCriteria['with']);
                }
                foreach($aCriteria['with'] as $sRelEntity) {
                    if ($sRelEntity === 'user') {
                        $aUserId = array_values(array_unique(\F::Array_Column($aData['data'], 'user_id')));
                        $aUsers = \E::Module('User')->getUsersByArrayId($aUserId);

                        /** @var ModuleMedia_EntityMedia $oMresource */
                        foreach ($aCollection as $oMresource) {
                            if (isset($aUsers[$oMresource->getUserId()])) {
                                $oMresource->setUser($aUsers[$oMresource->getUserId()]);
                            }
                        }
                        $aUsers = null;
                    }
                }
            }
        } else {
            $aCollection = [];
        }
        return array('collection' => $aCollection, 'count' => 0);
    }

    /**
     * @param array $aFilter
     * @param int   $iPage
     * @param int   $iPerPage
     *
     * @return array
     */
    public function getMediaByFilter($aFilter, $iPage, $iPerPage)
    {
        $aData = $this->oMapper->getMediaByFilter($aFilter, $iPage, $iPerPage);

        return array('collection' => $aData['data'], 'count' => 0);
    }

    /**
     * @param string         $sTargetType
     * @param int|array|null $xTargetId
     *
     * @return ModuleMedia_EntityMediaRel[]
     */
    public function getMediaRelByTarget($sTargetType, $xTargetId = null)
    {
        return $this->getMediaRelByTargetAndUser($sTargetType, $xTargetId, null);
    }

    /**
     * @param string         $sTargetType
     * @param int|array|null $xTargetId
     * @param int|array|null $xUserId
     *
     * @return ModuleMedia_EntityMediaRel[]
     */
    public function getMediaRelByTargetAndUser($sTargetType, $xTargetId = null, $xUserId = null) {

        $sCacheKey = 'media_rel_' . serialize(array($sTargetType, $xTargetId, $xUserId));
        if (false === ($aData = \E::Module('Cache')->get($sCacheKey))) {
            $aData = $this->oMapper->getMediaRelByTargetAndUser($sTargetType, $xTargetId, $xUserId);
            \E::Module('Cache')->set($aData, $sCacheKey, array('media_rel_update'), 'P1D');
        }

        return $aData;
    }

    /**
     * Deletes media resources by ID
     *
     * @param $aMedias
     * @param $bDeleteFiles
     * @param $bNoCheckTargets
     *
     * @return bool
     */
    public function deleteMedia($aMedias, $bDeleteFiles = true, $bNoCheckTargets = false)
    {
        $aId = $this->_entitiesId($aMedias);
        $bResult = true;

        if ($aId) {
            if ($bDeleteFiles) {
                $aMedias = $this->oMapper->getMediaById($aId);
                if (!$bNoCheckTargets && $aMedias) {
                    /** @var ModuleMedia_EntityMedia $oMresource */
                    foreach ($aMedias as $oMresource) {
                        // Если число ссылок > 0, то не удаляем
                        if ($oMresource->getTargetsCount() > 0) {
                            $iIdx = array_search($oMresource->getId(), $aId);
                            if ($iIdx !== false) {
                                unset($aId[$iIdx]);
                            }
                        }
                    }
                }
            }

            $bResult = $this->oMapper->deleteMedia($aId);

            if ($bDeleteFiles) {
                if ($bResult && $aMedias && $aId) {
                    // Удаляем файлы
                    foreach ($aId as $nId) {
                        if (isset($aMedias[$nId]) && $aMedias[$nId]->isFile() && $aMedias[$nId]->canDelete()) {
                            if ($aMedias[$nId]->isImage()) {
                                \E::Module('Img')->delete($aMedias[$nId]->GetFile());
                            } else {
                                F::File_Delete($aMedias[$nId]->GetFile());
                            }
                        }
                    }
                }
            }
        }
        \E::Module('Cache')->cleanByTags(array('media_update', 'media_rel_update'));

        return $bResult;
    }

    /**
     * @param ModuleMedia_EntityMediaRel[] $aMediaRel
     *
     * @return bool
     */
    protected function _deleteMediaRel($aMediaRel)
    {
        $aMresId = [];
        if ($aMediaRel) {
            if (!is_array($aMediaRel)) {
                $aMediaRel = array($aMediaRel);
            }
            /** @var ModuleMedia_EntityMediaRel $oResourceRel */
            foreach($aMediaRel as $oResourceRel) {
                $aMresId[] = $oResourceRel->getMediaId();
            }
            $aMresId = array_unique($aMresId);
        }
        $bResult = $this->oMapper->deleteMediaRel(array_keys($aMediaRel));
        if ($bResult && $aMresId) {
            // TODO: Delete files or not - need to add config options
            //  $this->deleteMresources($aMresId);
            $this->deleteMedia($aMresId, false);
        }
        \E::Module('Cache')->cleanByTags(['media_update', 'media_rel_update']);

        return true;
    }

    /**
     * Deletes media resources' relations by rel ID
     *
     * @param int[] $aId
     *
     * @return bool
     */
    public function deleteMediaRel($aId)
    {
        if ($aId) {
            $aMresourceRel = $this->oMapper->getMediaRelById($aId);
            if ($aMresourceRel) {
                return $this->_deleteMediaRel($aMresourceRel);
            }
        }
        return true;
    }

    /**
     * Deletes mresources' relations by target type & id
     *
     * @param string    $sTargetType
     * @param int|array $xTargetId
     *
     * @return bool
     */
    public function deleteMediaRelByTarget($sTargetType, $xTargetId)
    {
        $aMresourceRel = $this->oMapper->getMediaRelByTarget($sTargetType, $xTargetId);
        if ($aMresourceRel) {
            $aMresId = [];
            if ($this->oMapper->deleteTargetRel($sTargetType, $xTargetId)) {

                /** @var ModuleMedia_EntityMediaRel $oResourceRel */
                foreach ($aMresourceRel as $oResourceRel) {
                    $aMresId[] = $oResourceRel->getMediaId();
                }
                $aMresId = array_unique($aMresId);
            }
            if ($aMresId) {
                return $this->deleteMedia($aMresId);
            }
        }
        \E::Module('Cache')->cleanByTags(array('media_rel_update'));

        return true;
    }

    /**
     * @param string    $sTargetType
     * @param int|array $xTargetId
     * @param int       $iUserId
     *
     * @return bool
     */
    public function deleteMediaRelByTargetAndUser($sTargetType, $xTargetId, $iUserId)
    {
        $aMresourceRel = $this->oMapper->getMediaRelByTargetAndUser($sTargetType, $xTargetId, $iUserId);
        if ($aMresourceRel) {
            $aMresId = [];
            if ($this->oMapper->deleteTargetRel($sTargetType, $xTargetId)) {

                /** @var ModuleMedia_EntityMediaRel $oResourceRel */
                foreach ($aMresourceRel as $oResourceRel) {
                    $aMresId[] = $oResourceRel->getMediaId();
                }
                $aMresId = array_unique($aMresId);
            }
            if ($aMresId) {
                return $this->deleteMedia($aMresId);
            }
        }
        \E::Module('Cache')->cleanByTags(array('media_rel_update'));

        return true;
    }

    /**
     * Calc hash of URL for seeking & comparation
     *
     * @param string $sUrl
     *
     * @return string
     */
    public function calcUrlHash($sUrl)
    {
        if ($sUrl[0] !== '@') {
            $sPathUrl = F::File_LocalUrl($sUrl);
            if ($sPathUrl) {
                $sUrl = '@' . trim($sPathUrl, '/');
            }
        }
        return md5($sUrl);
    }

    /**
     * @param string $sStorage
     * @param string $sFileName
     * @param string $sFileHash
     * @param int    $iUserId
     *
     * @return string
     */
    public static function createUuid($sStorage, $sFileName, $sFileHash, $iUserId)
    {
        $sUuid = '0u' . F::Crc32($iUserId . ':' . $sFileHash, true)
            . '-' . F::Crc32($sStorage . ':' . $sFileName . ':' . $iUserId, true)
            . '-' . F::Crc32($sStorage . ':' . $sFileHash . ':' . $sFileName, true);
        return $sUuid;
    }

    /**
     * Удаление изображения
     *
     * @param string $sTargetType
     * @param int    $iTargetId
     * @param int    $iUserId
     */
    public function unlinkFile($sTargetType, $iTargetId, $iUserId)
    {
        // Получим и удалим все ресурсы
        $aMresourceRel = $this->getMediaRelByTargetAndUser($sTargetType, $iTargetId, $iUserId);
        if ($aMresourceRel) {
            $aMresId = [];
            /** @var ModuleMedia_EntityMediaRel $oResourceRel */
            foreach ($aMresourceRel as $oResourceRel) {
                $aMresId[] = $oResourceRel->getMediaId();
            }
            if ($aMresId) {
                $this->deleteMedia($aMresId, TRUE);
            }
        }

        // И связи
        $this->deleteMediaRelByTargetAndUser($sTargetType, $iTargetId, E::userId());
    }

    /**
     * @return string[]
     */
    public function getTargetTypes() 
    {
        return $this->oMapper->getTargetTypes();
    }

    /**
     * @param $sTargetType
     *
     * @return int
     */
    public function getMediaCountByTarget($sTargetType)
    {
        return $this->oMapper->getMediaCountByTarget($sTargetType);
    }

    /**
     * @param $sTargetType
     * @param $iUserId
     *
     * @return int
     */
    public function getMediaCountByTargetAndUserId($sTargetType, $iUserId)
    {
        return $this->oMapper->getMediaCountByTargetAndUserId($sTargetType, $iUserId);
    }

    /**
     * @param $sTargetType
     * @param $sTargetId
     * @param $iUserId
     *
     * @return int
     */
    public function getMediaCountByTargetIdAndUserId($sTargetType, $sTargetId, $iUserId)
    {
        return $this->oMapper->getMediaCountByTargetIdAndUserId($sTargetType, $sTargetId, $iUserId);
    }

    /**
     * Проверяет картинки комментариев
     * \E::Module('Media')->CheckTargetTextForImages($sTarget, $sTargetId, $sTargetText);
     *
     * @param string $sTargetType
     * @param int    $sTargetId
     * @param string $sTargetText
     *
     * @return bool
     */
    public function checkTargetTextForImages($sTargetType, $sTargetId, $sTargetText)
    {
        // 1. Получим uuid рисунков из текста топика и создадим связь с объектом
        // если ее ещё нет.
        if (preg_match_all('~0u\w{8}-\w{8}-\w{8}~', $sTargetText, $aUuid) && isset($aUuid[0])) {

            // Получим uuid ресурсов
            $aUuid = array_unique($aUuid[0]);

            // Найдем ресурсы
            /** @var ModuleMedia_EntityMedia[] $aResult */
            $aResult = $this->getMresourcesByUuid($aUuid);
            if (!$aResult) {
                return FALSE;
            }

            // Новым рисункам добавим таргет
            $aNewResources = [];
            foreach ($aResult as $sId => $oResource) {
                if ($oResource->getTargetsCount() != 0) {
                    continue;
                }

                // Текущий ресурс новый
                $aNewResources[] = $oResource;
            }

            // Добавим связи, если нужно
            if ($aNewResources) {
                $this->AddTargetRel($aNewResources, $sTargetType, $sTargetId);
            }


            // 2. Пробежимся по ресурсам комментария и если ресурса нет в новых, тогда
            // удалим этот ресурс.
            // Читаем список ресурсов из базы
            $aMresources = $this->getMediaRelByTarget($sTargetType, $sTargetId);

            // Строим список ID ресурсов для удаления
            $aDeleteResources = [];
            foreach ($aMresources as $oMresource) {
                if (!isset($aResult[$oMresource->getMediaId()])) {
                    // Если ресурса нет в хеш-таблице, то это прентендент на удаление
                    $aDeleteResources[$oMresource->GetId()] = $oMresource->getMediaId();
                }
            }
            if ($aDeleteResources) {
                $this->deleteMedia(array_values($aDeleteResources));
                $this->deleteMediaRel(array_keys($aDeleteResources));
            }
        }

        return TRUE;
    }

    /**
     * Прикрепляет временный ресурс к вновь созданному объекту
     *
     * @param string $sTargetType
     * @param string $sTargetId
     * @param $sTargetTmp
     *
     * @return bool|ModuleMedia_EntityMedia
     */
    public function LinkTempResource($sTargetType, $sTargetId, $sTargetTmp)
    {
        if ($sTargetTmp && E::isUser()) {

            $sNewPath = \E::Module('Uploader')->getUserImageDir(\E::userId(), true, false);
            $aMresourceRel = $this->getMediaRelByTargetAndUser($sTargetType, 0, E::userId());

            if ($aMresourceRel) {
                $oResource = array_shift($aMresourceRel);
                $sOldPath = $oResource->GetFile();

                $oStoredFile = \E::Module('Uploader')->Store($sOldPath, $sNewPath);
                /** @var ModuleMedia_EntityMedia $oResource */
                $oResource = $this->getMresourcesByUuid($oStoredFile->getUuid());
                if ($oResource) {
                    $oResource->setUrl($this->normalizeUrl(\E::Module('Uploader')->getTargetUrl($sTargetType, $sTargetId)));
                    $oResource->setType($sTargetType);
                    $oResource->setUserId(\E::userId());

                    // 4. В свойство поля записать адрес картинки
                    $this->UnlinkFile($sTargetType, 0, E::userId());
                    $this->AddTargetRel($oResource, $sTargetType, $sTargetId);

                    return $oResource;

                }
            }
        }

        return false;
    }

    /**
     * Обновляет параметры ресурса
     *
     * @param ModuleMedia_EntityMedia $oResource
     *
     * @return bool
     */
    public function updateExtraData($oResource)
    {
        return $this->oMapper->updateExtraData($oResource);
    }

    /**
     * Обновляет url ресурса
     *
     * @param ModuleMedia_EntityMedia $oResource
     *
     * @return bool
     */
    public function updateMediaUrl($oResource)
    {
        return $this->oMapper->updateMresouceUrl($oResource);
    }

    /**
     * Обновляет тип ресурса
     *
     * @param ModuleMedia_EntityMedia $oResource
     *
     * @return bool
     */
    public function updateType($oResource){

        return $this->oMapper->updateType($oResource);
    }

    /**
     * Устанавливает главный рисунок фотосета
     *
     * @param ModuleMedia_EntityMedia $oResource
     * @param $sTargetType
     * @param $sTargetId
     *
     * @return bool
     */
    public function updatePrimary($oResource, $sTargetType, $sTargetId)
    {
        return $this->oMapper->updatePrimary($oResource, $sTargetType, $sTargetId);
    }

    /**
     * Устанавливает новый порядок сортировки изображений
     *
     * @param $aOrder
     * @param $sTargetType
     * @param $sTargetId
     *
     * @return mixed
     */
    public function updateSort($aOrder, $sTargetType, $sTargetId)
    {
        return $this->oMapper->updateOrder($aOrder, $sTargetType, $sTargetId);
    }

    /**
     * Возвращает информацию о количестве и обложке фотосета
     *
     * @param $sTargetType
     * @param $sTargetId
     *
     * @return array
     */
    public function getPhotosetData($sTargetType, $sTargetId)
    {
        $aMedia = $this->getMediaRelByTarget($sTargetType, $sTargetId);

        $aResult = array(
            'count' => 0,
            'cover' => FALSE,
        );

        if ($aMedia) {
            $aResult['count'] = count($aMedia);

            foreach ($aMedia as $oResource) {
                if ($oResource->IsCover()) {
                    $aResult['cover'] = $oResource->getMediaId();
                    break;
                }

            }

        }
        return $aResult;
    }

    /**
     * Возвращает категории изображения для пользователя
     *
     * @param $iUserId
     * @param bool $sTopicId
     *
     * @return array
     */
    public function getImageCategoriesByUserId($iUserId, $sTopicId = FALSE)
    {
        $aRows = $this->oMapper->getImageCategoriesByUserId($iUserId, $sTopicId);
        $aResult = [];
        if ($aRows) {
            foreach ($aRows as $aRow) {
                $aResult[] = \E::getEntity('Media_MediaCategory', array(
                    'id' => $aRow['ttype'],
                    'count' => $aRow['count'],
                    'label' => \E::Module('Lang')->get('aim_target_type_' . $aRow['ttype']),
                ));
            }
        }
        return $aResult;
    }

    /**
     * @param $iUserId
     * @param $sTopicId
     *
     * @return mixed
     */
    public function getCurrentTopicResourcesId($iUserId, $sTopicId)
    {
        return $this->oMapper->getCurrentTopicResourcesId($iUserId, $sTopicId);
    }

    /**
     * @param int $iUserId
     * @param bool $sTopicId
     *
     * @return bool|Entity
     */
    public function getCurrentTopicImageCategory($iUserId, $sTopicId = FALSE)
    {
        $aResourcesId = $this->oMapper->getCurrentTopicResourcesId($iUserId, $sTopicId);
       if ($aResourcesId) {
           if ($sTopicId) {
               return E::getEntity('Media_MediaCategory', array(
                   'id' => 'current',
                   'count' => count($aResourcesId),
                   'label' => \E::Module('Lang')->get('aim_target_type_current'),
               ));
           }

           return E::getEntity('Media_MediaCategory', array(
               'id' => 'tmp',
               'count' => count($aResourcesId),
               'label' => \E::Module('Lang')->get('target_type_tmp'),
           ));
        }
        return FALSE;
    }

    /**
     * Получает топики пользователя с картинками
     *
     * @param int $iUserId
     * @param int $iPage
     * @param int $iPerPage
     *
     * @return array
     */
    public function getTopicsPage($iUserId, $iPage, $iPerPage)
    {
        $iCount = 0;
        $aFilter = array(
            'user_id' => $iUserId,
            'media_type' => self::TYPE_IMAGE | self::TYPE_PHOTO | self::TYPE_PHOTO_PRIMARY,
            'target_type' => array('photoset', 'topic'),
        );
        if (\E::isUser() && E::User() !== $iUserId) {
            // Если текущий юзер не совпадает с запрашиваемым, то получаем список доступных блогов
            $aFilter['blogs_id'] = \E::Module('Blog')->getAccessibleBlogsByUser(\E::User());
            // И топики должны быть опубликованы
            $aFilter['topic_publish'] = 1;
        }
        if (!\E::isUser()) {
            // Если юзер не авторизован, то считаем все доступные для индексации топики
            $aFilter['topic_index_ignore'] = 0;
        }

        $aTopicInfo = $this->oMapper->getTopicInfo($aFilter, $iCount, $iPage, $iPerPage);
        if ($aTopicInfo) {

            $aTopics = \E::Module('Topic')->getTopicsAdditionalData(array_keys($aTopicInfo));
            if ($aTopics) {
                foreach ($aTopics as $sTopicId => $oTopic) {
                    $oTopic->setImagesCount($aTopicInfo[$sTopicId]);
                    $aTopics[$sTopicId] = $oTopic;
                }
            }

            $aResult = array(
                'collection' => $aTopics,
                'count' => $iCount,
            );
        } else {
            $aResult = array(
                'collection' => array(),
                'count' => 0
            );
        }

        return $aResult;
    }

    /**
     * @param int $iUserId
     *
     * @return bool|ModuleMedia_EntityMediaCategory
     */
    public function getTalksImageCategory($iUserId) {

        $aTalkInfo = $this->oMapper->getTalkInfo($iUserId, $iCount, 1, 100000);
        if ($aTalkInfo) {
            return E::getEntity('Media_MediaCategory', array(
                'id' => 'talks',
                'count' => count($aTalkInfo),
                'label' => \E::Module('Lang')->get('aim_target_type_talks'),
            ));
        }

        return FALSE;
    }

    /**
     * Получает топики пользователя с картинками
     *
     * @param int $iUserId
     * @param int $iPage
     * @param int $iPerPage
     *
     * @return array
     */
    public function getTalksPage($iUserId, $iPage, $iPerPage)  {

        $iCount = 0;
        $aResult = array(
            'collection' => array(),
            'count' => 0
        );

        $aTalkInfo = $this->oMapper->getTalkInfo($iUserId, $iCount, $iPage, $iPerPage);
        if ($aTalkInfo) {

            $aTalks = \E::Module('Talk')->getTalksAdditionalData(array_keys($aTalkInfo));
            if ($aTalks) {
                foreach ($aTalks as $sTopicId => $oTopic) {
                    $oTopic->setImagesCount($aTalkInfo[$sTopicId]);
                    $aTalks[$sTopicId] = $oTopic;
                }
            }

            $aResult['collection'] = $aTalks;
            $aResult['count'] = $iCount;
        }

        return $aResult;
    }

    /**
     * @param int $iUserId
     *
     * @return bool|Entity
     */
    public function getCommentsImageCategory($iUserId) {

        $aImagesInCommentsCount = $this->getMediaCountByTargetAndUserId(array(
            'talk_comment',
            'topic_comment'
        ), $iUserId);
        if ($aImagesInCommentsCount) {
            return E::getEntity('Media_MediaCategory', array(
                'id' => 'comments',
                'count' => $aImagesInCommentsCount,
                'label' => \E::Module('Lang')->get('aim_target_type_comments'),
            ));
        }

        return FALSE;
    }
    /**
     * Возвращает категории изображения для пользователя
     * @param int $iUserId
     * @return array
     */
    public function getAllImageCategoriesByUserId($iUserId){

        $aRows = $this->oMapper->getAllImageCategoriesByUserId($iUserId);
        $aResult = [];
        if ($aRows) {
            foreach ($aRows as $aRow) {
                $aResult[] = \E::getEntity('Media_MediaCategory', array(
                    'id' => $aRow['ttype'],
                    'count' => $aRow['count'],
                    'label' => \E::Module('Lang')->get('aim_target_type_' . $aRow['ttype']),
                ));
            }
        }
        return $aResult;
    }

    /**
     * Возвращает информацию о категориях изображений пользователя
     * с разбивкой по типу контента
     *
     * @param int $iUserId
     *
     * @return array
     */
    public function getTopicsImageCategory($iUserId) {

        $aFilter = array(
            'user_id' => $iUserId,
            'media_type' => self::TYPE_IMAGE | self::TYPE_PHOTO | self::TYPE_PHOTO_PRIMARY,
            'target_type' => array('photoset', 'topic'),
        );
        if (\E::isUser() && E::User() !== $iUserId) {
            // Если текущий юзер не совпадает с запрашиваемым, то получаем список доступных блогов
            $aFilter['blogs_id'] = \E::Module('Blog')->getAccessibleBlogsByUser(\E::User());
            // И топики должны быть опубликованы
            $aFilter['topic_publish'] = 1;
        }
        if (!\E::isUser()) {
            // Если юзер не авторизован, то считаем все доступные для индексации топики
            $aFilter['topic_index_ignore'] = 0;
        }
        $aData = $this->oMapper->getCountImagesByTopicType($aFilter);
        if ($aData) {
            foreach ($aData as $xIndex => $aRow) {
                $sLabelKey = 'target_type_' . $aRow['id'];
                if (($sLabel = \E::Module('Lang')->get($sLabelKey)) === mb_strtoupper($sLabelKey)) {
                    /** @var ModuleTopic_EntityContentType $oContentType */
                    $oContentType = \E::Module('Topic')->getContentTypeByUrl($aRow['id']);
                    if ($oContentType) {
                        $sLabel = $oContentType->getContentTitleDecl();
                    }
                }
                $aData[$xIndex]['label'] = \E::Module('Lang')->get($sLabel);
            }
            $aResult = \E::getEntityRows('Media_MediaCategory', $aData);
        } else {
            $aResult = [];
        }
        return $aResult;
    }

    /**
     * Получает топики пользователя с картинками
     *
     * @param int    $iUserId
     * @param string $sType
     * @param int    $iPage
     * @param int    $iPerPage
     *
     * @return array
     */
    public function getTopicsPageByType($iUserId, $sType, $iPage, $iPerPage)
    {
        $iCount = 0;
        $aFilter = array(
            'user_id' => $iUserId,
            'media_type' => self::TYPE_IMAGE | self::TYPE_PHOTO | self::TYPE_PHOTO_PRIMARY,
            'target_type' => ['photoset', 'topic'],
        );
        if (\E::isUser() && E::User() !== $iUserId) {
            // Если текущий юзер не совпадает с запрашиваемым, то получаем список доступных блогов
            $aFilter['blogs_id'] = \E::Module('Blog')->getAccessibleBlogsByUser(\E::User());
            // И топики должны быть опубликованы
            $aFilter['topic_publish'] = 1;
        }
        if (!\E::isUser()) {
            // Если юзер не авторизован, то считаем все доступные для индексации топики
            $aFilter['topic_index_ignore'] = 0;
        }
        $aFilter['topic_type'] = $sType;

        $aTopicInfo = $this->oMapper->getTopicInfo($aFilter, $iCount, $iPage, $iPerPage);
        if ($aTopicInfo) {

            $aFilter = array(
                'topic_id' => array_keys($aTopicInfo),
                'topic_type' => $sType
            );
            // Результат в формате array('collection'=>..., 'count'=>...)
            $aResult = \E::Module('Topic')->getTopicsByFilter($aFilter, 1, count($aTopicInfo));

            if ($aResult) {
                /** @var ModuleTopic_EntityTopic $oTopic */
                foreach ($aResult['collection'] as $sTopicId => $oTopic) {
                    $oTopic->setImagesCount($aTopicInfo[$sTopicId]);
                    $aResult['collection'][$sTopicId] = $oTopic;
                }
                $aResult['count'] = $iCount; // total number of topics with images
            }

            return $aResult;
        }

        return array(
            'collection' => array(),
            'count' => 0
        );
    }

    /**
     * Возвращает категории изображения для пользователя
     * @param $iUserId
     *
     * @return mixed
     */
    public function getCountImagesByUserId($iUserId)
    {
        return $this->oMapper->getCountImagesByUserId($iUserId);

    }

}

// EOF
