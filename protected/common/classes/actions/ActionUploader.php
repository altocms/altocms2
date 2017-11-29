<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: http://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

/**
 * ActionUploader.php
 * Файл экшена загрузчика файлов
 *
 * @package actions
 * @since   1.1
 */
class ActionUploader extends Action 
{
    const PREVIEW_RESIZE = 222;

    const OK = 200;
    const ERROR = 500;

    /**
     * Абстрактный метод регистрации евентов.
     * В нём необходимо вызывать метод AddEvent($sEventName,$sEventFunction)
     * Например:
     *      $this->AddEvent('index', 'eventIndex');
     *      $this->AddEventPreg('/^admin$/i', '/^\d+$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventAdminBlog');
     */
    protected function registerEvent() 
    {
        $this->addEventPreg('/^upload-image/i', '/^$/i', 'eventUploadImage'); // Загрузка изображения на сервер
        $this->addEventPreg('/^resize-image/i', '/^$/i', 'eventResizeImage'); // Ресайз изображения
        $this->addEventPreg('/^remove-image-by-id/i', '/^$/i', 'eventRemoveImageById'); // Удаление изображения по его идентификатору
        $this->addEventPreg('/^remove-image/i', '/^$/i', 'eventRemoveImage'); // Удаление изображения
        $this->addEventPreg('/^cancel-image/i', '/^$/i', 'eventCancelImage'); // Отмена ресайза в окне, закрытие окна ресайза
        $this->addEventPreg('/^direct-image/i', '/^$/i', 'eventDirectImage'); // Прямая загрузка изображения без открытия окна ресайза
        $this->addEventPreg('/^multi-image/i', '/^$/i', 'eventMultiUpload'); // Прямая загрузка нескольких изображений
        $this->addEvent('description', 'eventDescription'); // Установка описания ресурса
        $this->addEvent('cover', 'eventCover'); // Установка обложки фотосета
        $this->addEvent('sort', 'eventSort'); // Меняет сортировку элементов фотосета
    }

    /**
     * Получение размеров изображения после ресайза
     *
     * @param $sParam
     * 
     * @return array|mixed
     */
    protected function _getImageSize($sParam) 
    {
        $aSize = (array)F::getRequest($sParam);
        if (isset($aSize['x'], $aSize['y'], $aSize['x2'], $aSize['y2']) && is_numeric($aSize['x']) && is_numeric($aSize['y']) && is_numeric($aSize['x2']) && is_numeric($aSize['y2'])) {
            foreach ($aSize as $sKey => $sVal) {
                $aSize[$sKey] = (int)$sVal;
            }
            if ($aSize['x'] < $aSize['x2']) {
                $aSize['x1'] = $aSize['x'];
            } else {
                $aSize['x1'] = $aSize['x2'];
                $aSize['x2'] = $aSize['x'];
            }
            $aSize['w'] = $aSize['x2'] - $aSize['x1'];
            unset($aSize['x']);
            if ($aSize['y'] < $aSize['y2']) {
                $aSize['y1'] = $aSize['y'];
            } else {
                $aSize['y1'] = $aSize['y2'];
                $aSize['y2'] = $aSize['y'];
            }
            $aSize['h'] = $aSize['y2'] - $aSize['y1'];
            unset($aSize['y']);

            return $aSize;
        }

        return [];
    }

    /**
     * Добавляет связь между объектом и ресурсом
     *
     * @param $xStoredFile
     * @param $sTargetType
     * @param $sTargetId
     * @param bool $bMulti
     *
     * @return bool
     */
    public function addUploadedFileRelationInfo($xStoredFile, $sTargetType, $sTargetId, $bMulti = FALSE)
    {
        /** @var ModuleMedia_EntityMedia $oResource */
        $oResource = \E::Module('Media')->getMresourcesByUuid($xStoredFile->getUuid());
        if ($oResource) {
            return \E::Module('Uploader')->AddRelationResourceTarget($oResource, $sTargetType, $sTargetId, $bMulti);
        }

        return FALSE;
    }

    /**
     * Прямая загрузка изображения без открытия окна ресайза
     *
     * @return bool
     */
    public function eventDirectImage()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // * Достаем из сессии временный файл
        $sTarget = \E::Module('Session')->get('sTarget');
        $iTargetId = (int)E::Module('Session')->get('sTargetId');
        $sTmpFile = \E::Module('Session')->get("sTmp-{$sTarget}-{$iTargetId}");
        $sPreviewFile = \E::Module('Session')->get("sPreview-{$sTarget}-{$iTargetId}");

        if ($iTargetId === 0) {
            if (!\E::Module('Session')->getCookie(ModuleUploader::COOKIE_TARGET_TMP)) {
                return FALSE;
            }
        }

        if (!F::File_Exists($sTmpFile)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));

            return false;
        }

        // Проверяем, целевой объект и права на его редактирование
        if (!$oTarget = \E::Module('Uploader')->CheckAccessAndGetTarget($sTarget, $iTargetId)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

            return false;
        }

        \E::Module('Media')->UnlinkFile($sTarget, $iTargetId, E::userId());

        $oImg = \E::Module('Img')->Read($sTmpFile);

        $sExtension = strtolower(pathinfo($sTmpFile, PATHINFO_EXTENSION));

        // Сохраняем фото во временный файл
        if ($sTmpFile = $oImg->Save(\F::File_UploadUniqname($sExtension))) {

            // Окончательная запись файла только через модуль Uploader
            if ($oStoredFile = \E::Module('Uploader')->StoreImage($sTmpFile, $sTarget, $iTargetId)) {
                $sFile = $oStoredFile->GetUrl();

                $sFilePreview = $sFile;
                if ($sSize = F::getRequest('crop_size', FALSE)) {
                    $sFilePreview = \E::Module('Uploader')->ResizeTargetImage($sFile, $sSize);
                }

                // Запускаем хук на действия после загрузки картинки
                \HookManager::run('uploader_upload_image_after', array(
                    'sFile'        => $sFile,
                    'sFilePreview' => $sFilePreview,
                    'sTargetId'    => $iTargetId,
                    'sTarget'      => $sTarget,
                    'oTarget'      => $oTarget,
                ));

                \E::Module('Viewer')->assignAjax('sFile', $sFile);
                \E::Module('Viewer')->assignAjax('sFilePreview', $sFilePreview);

                // Чистим
                $sTmpFile = \E::Module('Session')->get("sTmp-{$sTarget}-{$iTargetId}");
                $sPreviewFile = \E::Module('Session')->get("sPreview-{$sTarget}-{$iTargetId}");
                \E::Module('Img')->delete($sTmpFile);
                \E::Module('Img')->delete($sPreviewFile);

                // * Удаляем из сессии
                \E::Module('Session')->drop('sTarget');
                \E::Module('Session')->drop('sTargetId');
                \E::Module('Session')->drop("sTmp-{$sTarget}-{$iTargetId}");
                \E::Module('Session')->drop("sPreview-{$sTarget}-{$iTargetId}");

                return true;
            }
        }

        // * В случае ошибки, возвращаем false
        \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));

        return false;
    }

    /**
     * Загрузка изображения после его ресайза
     *
     * @param  string $sFile - Серверный путь до временной фотографии
     * @param  string $sTarget - Тип целевого объекта
     * @param  string $iTargetId - ID целевого объекта
     * @param  array $aSize - Размер области из которой нужно вырезать картинку - array('x1'=>0,'y1'=>0,'x2'=>100,'y2'=>100)
     *
     * @return ModuleMedia_EntityMedia|bool
     */
    public function uploadImageAfterResize($sFile, $sTarget, $iTargetId, $aSize = [])
    {
        if ((int)$iTargetId === 0) {
            if (!\E::Module('Session')->getCookie(ModuleUploader::COOKIE_TARGET_TMP)) {
                return FALSE;
            }
        }

        if (!F::File_Exists($sFile)) {
            return FALSE;
        }
        $oStoredFile = \E::Module('Uploader')->StoreImage($sFile, $sTarget, $iTargetId, $aSize);
        if ($oStoredFile) {
            return $oStoredFile;
        } else {
            $sError = \E::Module('Uploader')->getErrorMsg();
            if ($sError) {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get($sError));
                return false;
            }
        }

        // * В случае ошибки, возвращаем false
        \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));

        return FALSE;
    }

    /**
     * Path to temporary preview image
     *
     * @param string $sFileName
     *
     * @return string
     */
    protected function _getTmpPreviewName($sFileName)
    {
        $sFileName = basename($sFileName) . '-preview.' . pathinfo($sFileName, PATHINFO_EXTENSION);
        $sFileName = F::File_RootDir() . \C::get('path.uploads.images') . 'tmp/' . $sFileName;

        return F::File_NormPath($sFileName);
    }

    /**
     * Загружаем картинку
     */
    public function eventUploadImage()
    {
        // Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json', FALSE);

        \E::Module('Security')->validateSendForm();

        // Проверяем, загружен ли файл
        if (!($aUploadedFile = $this->getUploadedFile('uploader-upload-image'))) {
            $sError = $this->getUploadedFileError('uploader-upload-image');
            if ($sError) {
                $sError = \E::Module('Lang')->get('error_upload_image') . ' (' . $sError . ')';
            } else {
                $sError = \E::Module('Lang')->get('error_upload_image') . ' (Error #3001)';
            }
            \E::Module('Message')->addError($sError, \E::Module('Lang')->get('error'));

            return;
        }

        $sTarget = F::getRequest('target', FALSE);
        $sTargetId = F::getRequest('target_id', FALSE);

        // Проверяем, целевой объект и права на его редактирование
        if (!$oTarget = \E::Module('Uploader')->CheckAccessAndGetTarget($sTarget, $sTargetId)) {
            // Здесь два варианта, либо редактировать нельзя, либо можно, но топика еще нет
            if ($oTarget === TRUE) {
                // Будем делать временную картинку

            } else {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

                return;
            }
        }

        // Ошибок пока нет
        $sError = '';

        // Загружаем временный файл
        $sTmpFile = \E::Module('Uploader')->UploadLocal($aUploadedFile, 'images.' . $sTarget);

        // Вызовем хук перед началом загрузки картинки
        \HookManager::run('uploader_upload_before', array('oTarget' => $oTarget, 'sTmpFile' => $sTmpFile, 'sTarget' => $sTarget, 'sTargetId' => $sTargetId));

        // Если все ок, и по миме проходит, то
        if ($sTmpFile) {
            if (\E::Module('Img')->mimeType($sTmpFile)) {
                // Ресайзим и сохраняем уменьшенную копию
                // Храним две копии - мелкую для показа пользователю и крупную в качестве исходной для ресайза
                //$sPreviewFile = \E::Module('Uploader')->getUserImageDir(\E::UserId(), true, false) . '_preview.' . F::File_GetExtension($sTmpFile);
                // We need to create special preview file because we can show it only from upload dir (not from common tmp dir)
                $sPreviewFile = $this->_getTmpPreviewName($sTmpFile);

                if ($sPreviewFile = \E::Module('Img')->Copy($sTmpFile, $sPreviewFile, self::PREVIEW_RESIZE, self::PREVIEW_RESIZE)) {

                    // * Сохраняем в сессии временный файл с изображением
                    \E::Module('Session')->set('sTarget', $sTarget);
                    \E::Module('Session')->set('sTargetId', $sTargetId);
                    \E::Module('Session')->set("sTmp-{$sTarget}-{$sTargetId}", $sTmpFile);
                    \E::Module('Session')->set("sPreview-{$sTarget}-{$sTargetId}", $sPreviewFile);
                    \E::Module('Viewer')->assignAjax('sPreview', \E::Module('Uploader')->Dir2Url($sPreviewFile));

                    if (getRequest('direct', FALSE)) {
                        $this->eventDirectImage();
                    }

                    return;
                }
            } else {
                $sError = \E::Module('Lang')->get('error_upload_wrong_image_type');
            }

            // If anything wrong then deletes temp file
            F::File_Delete($sTmpFile);
        } else {

            // Ошибки загрузки картинки
            $sError = \E::Module('Uploader')->getErrorMsg();
            if (!$sError) {
                $sError = \E::Module('Lang')->get('error_upload_image') . ' (Error #3002)';
            }
        }

        // Выведем ошибки пользователю
        \E::Module('Message')->addError($sError, \E::Module('Lang')->get('error'));

        // Удалим ранее загруженый файл
        F::File_Delete($sTmpFile);
    }

    /**
     * Обработка обрезки изображения
     */
    public function eventResizeImage()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // * Достаем из сессии временный файл
        $sTarget = \E::Module('Session')->get('sTarget');
        $sTargetId = \E::Module('Session')->get('sTargetId');
        $sTmpFile = \E::Module('Session')->get("sTmp-{$sTarget}-{$sTargetId}");
        $sPreviewFile = \E::Module('Session')->get("sPreview-{$sTarget}-{$sTargetId}");

        if (!F::File_Exists($sTmpFile)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));

            return;
        }

        // Проверяем, целевой объект и права на его редактирование
        if (!$oTarget = \E::Module('Uploader')->CheckAccessAndGetTarget($sTarget, $sTargetId)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

            return;
        }

        // * Определяем размер большого фото для подсчета множителя пропорции
        $fRation = 1;
        if (($aSizeFile = getimagesize($sTmpFile)) && isset($aSizeFile[0])) {
            // в self::PREVIEW_RESIZE задана максимальная сторона
            $fRation = max($aSizeFile[0], $aSizeFile[1]) / self::PREVIEW_RESIZE; // 222 - размер превью по которой пользователь определяет область для ресайза
            if ($fRation < 1) {
                $fRation = 1;
            }
        }

        // * Получаем размер области из параметров
        $aSize = $this->_getImageSize('size');
        if ($aSize) {
            $aSize = array(
                'x1' => floor($fRation * $aSize['x1']), 'y1' => floor($fRation * $aSize['y1']),
                'x2' => ceil($fRation * $aSize['x2']), 'y2' => ceil($fRation * $aSize['y2'])
            );
            $iD = max($aSize['x2'] - $aSize['x1'] - $aSizeFile[0], $aSize['y2'] - $aSize['y1'] - $aSizeFile[1]);
            if ($iD > 0) {
                $aSize['x2'] -= $iD;
                $aSize['y2'] -= $iD;
            }
        }

        // * Вырезаем и сохраняем фото
        if ($oFileWeb = $this->uploadImageAfterResize($sTmpFile, $sTarget, $sTargetId, $aSize)) {

            $sFileWebPreview = $oFileWeb->getUrl();
            if ($sSize = F::getRequest('crop_size', FALSE)) {
                $sFileWebPreview = \E::Module('Uploader')->ResizeTargetImage($oFileWeb->getUrl(), $sSize);
            }

            // Запускаем хук на действия после загрузки картинки
            \HookManager::run('uploader_upload_image_after', array(
                'sFile'        => $oFileWeb,
                'sFilePreview' => $sFileWebPreview,
                'sTargetId'    => $sTargetId,
                'sTarget'      => $sTarget,
                'oTarget'      => $oTarget,
            ));

            \E::Module('Img')->delete($sTmpFile);
            \E::Module('Img')->delete($sPreviewFile);

            // * Удаляем из сессии
            \E::Module('Session')->drop('sTarget');
            \E::Module('Session')->drop('sTargetId');
            \E::Module('Session')->drop("sTmp-{$sTarget}-{$sTargetId}");
            \E::Module('Session')->drop("sPreview-{$sTarget}-{$sTargetId}");

            \E::Module('Viewer')->assignAjax('sFile', $oFileWeb->getUrl());
            \E::Module('Viewer')->assignAjax('sFilePreview', $sFileWebPreview);
            \E::Module('Viewer')->assignAjax('sTitleUpload', \E::Module('Lang')->get('uploader_upload_success'));
        } else {
            \E::Module('Message')->addError(\E::Module('Lang')->get('error_upload_image') . ' (Error #3021)', \E::Module('Lang')->get('error'));
        }
    }

    /**
     * Удаление картинки
     */
    public function eventRemoveImage()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // Проверяем, целевой объект и права на его редактирование
        $oTarget = \E::Module('Uploader')->CheckAccessAndGetTarget($sTargetType = F::getRequest('target', FALSE), $sTargetId = F::getRequest('target_id', FALSE));
        if (!$oTarget) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

            return;
        }

        $bResult = \HookManager::run('uploader_remove_image_before', [
            'sTargetId' => $sTargetId,
            'sTarget'   => $sTargetType,
            'oTarget'   => $oTarget,
        ]);

        if ($bResult !== false) {
            // * Удаляем картинку
            \E::Module('Uploader')->DeleteImage($sTargetType, $sTargetId, E::User());

            // Запускаем хук на действия после удаления картинки
            \HookManager::run('uploader_remove_image_after', [
                'sTargetId' => $sTargetId,
                'sTarget'   => $sTargetType,
                'oTarget'   => $oTarget,
            ]);
        }

        // * Возвращает сообщение
        \E::Module('Viewer')->assignAjax('sTitleUpload', \E::Module('Lang')->get('uploader_upload_success'));
    }

    /**
     * Отмена загрузки в окне ресайза
     */
    public function eventCancelImage()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // Проверяем, целевой объект и права на его редактирование
        if (!$oTarget = \E::Module('Uploader')->CheckAccessAndGetTarget(
            $sTarget = F::getRequest('target', FALSE),
            $sTargetId = F::getRequest('target_id', FALSE))
        ) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

            return;
        }
        $sTmpFile = \E::Module('Session')->get("sTmp-{$sTarget}-{$sTargetId}");

        if (!F::File_Exists($sTmpFile)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));

            return;
        }
        $sPreviewFile = $this->_getTmpPreviewName($sTmpFile);

        \E::Module('Img')->delete($sTmpFile);
        \E::Module('Img')->delete($sPreviewFile);

        // * Удаляем из сессии
        \E::Module('Session')->drop('sTarget');
        \E::Module('Session')->drop('sTargetId');
        \E::Module('Session')->drop("sTmp-{$sTarget}-{$sTargetId}");
        \E::Module('Session')->drop("sPreview-{$sTarget}-{$sTargetId}");
    }

    /**
     * Загружаем картинку
     */
    public function eventMultiUpload()
    {
        // Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json', FALSE);

        \E::Module('Security')->validateSendForm();

        // Проверяем, загружен ли файл
        if (!($aUploadedFile = $this->getUploadedFile('uploader-upload-image'))) {
            $sError = $this->getUploadedFileError('uploader-upload-image');
            if ($sError) {
                $sError = \E::Module('Lang')->get('error_upload_image') . ' (' . $sError . ')';
            } else {
                $sError = \E::Module('Lang')->get('error_upload_image') . ' (Error #3001)';
            }
            \E::Module('Message')->addError($sError, \E::Module('Lang')->get('error'));

            return false;
        }

        $sTarget = F::getRequest('target', FALSE);
        $sTargetId = F::getRequest('target_id', FALSE);
        $oTarget = \E::Module('Uploader')->CheckAccessAndGetTarget($sTarget, $sTargetId);
        $bTmp = F::getRequest('tmp', FALSE);
        $bTmp = ($bTmp == 'true') ? true : false;


        // Проверяем, целевой объект и права на его редактирование
        if (!$oTarget) {
            // Здесь два варианта, либо редактировать нельзя, либо можно, но топика еще нет
            if ($oTarget === TRUE) {
                // Будем делать временную картинку

            } else {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

                return false;
            }
        }

        // Ошибок пока нет
        $sError = '';

        // Сделаем временный файд
        $sTmpFile = \E::Module('Uploader')->UploadLocal($aUploadedFile);

        // Вызовем хук перед началом загрузки картинки
        \HookManager::run('uploader_upload_before', array('oTarget' => $oTarget, 'sTmpFile' => $sTmpFile, 'sTarget' => $sTarget));

        // Если все ок, и по миме проходит, то
        if ($sTmpFile && \E::Module('Img')->mimeType($sTmpFile)) {

            // Проверим, проходит ли по количеству
            if (!\E::Module('Uploader')->getAllowedCount(
                $sTarget = F::getRequest('target', FALSE),
                $sTargetId = F::getRequest('target_id', FALSE))
            ) {
                \E::Module('Message')->addError(\E::Module('Lang')->get(
                    'uploader_photoset_error_count_photos',
                    array('MAX' => \C::get('module.topic.photoset.count_photos_max'))
                ), \E::Module('Lang')->get('error'));

                return FALSE;
            }

            // Определим, существует ли объект или он будет создан позже
            if (!($sTmpKey = \E::Module('Session')->getCookie(ModuleUploader::COOKIE_TARGET_TMP)) && $sTargetId == '0' && $bTmp) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('error_upload_image') . ' (Error #3012)', \E::Module('Lang')->get('error'));

                return FALSE;
            }

            // Пересохраним файл из кэша для применения к нему опций из конфига
            // Сохраняем фото во временный файл
            $oImg = \E::Module('Img')->Read($sTmpFile);
            $sExtension = strtolower(pathinfo($sTmpFile, PATHINFO_EXTENSION));
            if (!$sSavedTmpFile = $oImg->Save(\F::File_UploadUniqname($sExtension))) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('error_upload_image') . ' (Error #3013)', \E::Module('Lang')->get('error'));

                F::File_Delete($sTmpFile);
                return FALSE;
            }

            // Окончательная запись файла только через модуль Uploader
            if ($oStoredFile = \E::Module('Uploader')->StoreImage($sSavedTmpFile, $sTarget, $sTargetId, null, true)) {

                /** @var ModuleMedia_EntityMedia $oResource */
                //$oResource = $this->AddUploadedFileRelationInfo($oStoredFile, $sTarget, $sTargetId, TRUE);
                $oResource = \E::Module('Media')->getMresourcesByUuid($oStoredFile->getUuid());
                $sFile = $oStoredFile->GetUrl();
                if ($oResource) {
                    $oResource->setType(ModuleMedia::TYPE_PHOTO);
                    \E::Module('Media')->UpdateType($oResource);
                }

                $sFilePreview = $sFile;
                if ($sSize = F::getRequest('crop_size', FALSE)) {
                    $sFilePreview = \E::Module('Uploader')->ResizeTargetImage($sFile, $sSize);
                }

                // Запускаем хук на действия после загрузки картинки
                \HookManager::run('uploader_upload_image_after', array(
                    'sFile'        => $sFile,
                    'sFilePreview' => $sFilePreview,
                    'sTargetId'    => $sTargetId,
                    'sTarget'      => $sTarget,
                    'oTarget'      => $oTarget,
                ));

                \E::Module('Viewer')->assignAjax('file', $sFilePreview);
                \E::Module('Viewer')->assignAjax('id', $oResource->getMediaId());


                // Чистим
                \E::Module('Img')->delete($sTmpFile);
                \E::Module('Img')->delete($sSavedTmpFile);

                return TRUE;
            }

        } else {

            // Ошибки загрузки картинки
            $sError = \E::Module('Uploader')->getErrorMsg();
            if (!$sError) {
                $sError = \E::Module('Lang')->get('error_upload_image')  . ' (Error #3014)';
            }
        }

        // Выведем ошибки пользователю
        \E::Module('Message')->addError($sError, \E::Module('Lang')->get('error'));

        // Удалим ранее загруженый файл
        F::File_Delete($sTmpFile);
    }


    /**
     * Удаление картинки
     */
    public function eventRemoveImageById()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // Проверяем, целевой объект и права на его редактирование
        if (!$oTarget = \E::Module('Uploader')->CheckAccessAndGetTarget(
            $sTargetType = F::getRequest('target', FALSE),
            $sTargetId = F::getRequest('target_id', FALSE))
        ) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

            return;
        }

        if (!($sResourceId = F::getRequest('resource_id', FALSE))) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

            return;
        }

        if (!($oResource = \E::Module('Media')->getMresourceById($sResourceId))) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

            return;
        }

        // Удалим ресурс без проверки связи с объектом. Объект-то останется, а вот
        // изображение нам уже ни к чему.
        \E::Module('Media')->DeleteMresources($oResource, TRUE, TRUE);

        \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('topic_photoset_photo_deleted'));
    }


    /**
     * Удаление картинки
     */
    public function eventDescription()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // Проверяем, целевой объект и права на его редактирование
        if (!$oTarget = \E::Module('Uploader')->CheckAccessAndGetTarget(
            $sTargetType = F::getRequest('target', FALSE),
            $sTargetId = F::getRequest('target_id', FALSE))
        ) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

            return;
        }

        if (!($sResourceId = F::getRequest('resource_id', FALSE))) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

            return;
        }

        /** @var ModuleMedia_EntityMedia $oResource */
        if (!($oResource = \E::Module('Media')->getMresourceById($sResourceId))) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

            return;
        }

        $oResource->setDescription(\F::getRequestStr('description', ''));
        \E::Module('Media')->updateExtraData($oResource);

        \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('topic_photoset_description_done'));
    }


    /**
     * Удаление картинки
     */
    public function eventCover()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // Проверяем, целевой объект и права на его редактирование
        if (!$oTarget = \E::Module('Uploader')->CheckAccessAndGetTarget(
            $sTargetType = F::getRequest('target', FALSE),
            $sTargetId = F::getRequest('target_id', FALSE))
        ) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

            return;
        }

        if (!($sResourceId = F::getRequest('resource_id', FALSE))) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

            return;
        }

        /** @var ModuleMedia_EntityMedia $oResource */
        if (!($oResource = \E::Module('Media')->getMresourceById($sResourceId))) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

            return;
        }

        // Если картинка и так превьюшка, то отключим её
        if ($oResource->getType() == ModuleMedia::TYPE_PHOTO) {
            $oResource->setType(ModuleMedia::TYPE_PHOTO_PRIMARY);
            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('topic_photoset_is_preview'));
            \E::Module('Viewer')->assignAjax('bPreview', true);
            if ($oTarget) {
                $oTarget->setPreviewImage(null, false);
                E::Topic_UpdateTopic($oTarget);
            }
        } else {
            $oResource->setType(ModuleMedia::TYPE_PHOTO);
            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('topic_photoset_mark_is_not_preview'));
            \E::Module('Viewer')->assignAjax('bPreview', false);
        }

        \E::Module('Media')->UpdatePrimary($oResource, $sTargetType, $sTargetId);
    }


    /**
     * Меняет сортировку элементов фотосета
     */
    public function eventSort()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // Проверяем, целевой объект и права на его редактирование
        if (!$oTarget = \E::Module('Uploader')->CheckAccessAndGetTarget(
            $sTargetType = F::getRequest('target', FALSE),
            $sTargetId = F::getRequest('target_id', FALSE))
        ) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

            return;
        }

        if (!($aOrder = F::getRequest('order', FALSE))) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

            return;
        }

        if (!is_array($aOrder)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));

            return;
        }

        \E::Module('Media')->UpdateSort(array_flip($aOrder), $sTargetType, $sTargetId);

        \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('uploader_sort_changed'));

    }

}

// EOF