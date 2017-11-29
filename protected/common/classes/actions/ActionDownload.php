<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */
class ActionDownload extends Action {

    protected $oType = null;

    /**
     * Инициализация экшена
     */
    public function init() {
        /**
         * Проверяем авторизован ли юзер
         */
        $this->oUserCurrent = \E::User();
        $this->setDefaultEvent('file');
    }

    /**
     * Регистрируем евенты
     */
    protected function registerEvent() {
        $this->addEvent('file', 'eventDownloadFile');
    }


    public function eventDownloadFile() {

        $this->setTemplate(false);

        $sTopicId = $this->getParam(0);
        $sFieldId = $this->getParam(1);

        \E::Module('Security')->validateSendForm();

        if (!($oTopic = \E::Module('Topic')->getTopicById($sTopicId))) {
            return parent::eventNotFound();
        }

        if (!$this->oType = \E::Module('Topic')->getContentType($oTopic->getType())) {
            return parent::eventNotFound();
        }

        if (!($oField = \E::Module('Topic')->getContentFieldById($sFieldId))) {
            return parent::eventNotFound();
        }

        if ($oField->getContentId() != $this->oType->getContentId()) {
            return parent::eventNotFound();
        }

        //получаем объект файла
        $oFile = $oTopic->getFieldFile($oField->getFieldId());
        //получаем объект поля топика, содержащий данные о файле
        $oValue = $oTopic->getField($oField->getFieldId());

        if ($oFile && $oValue) {

            if (preg_match("/^(http:\/\/)/i", $oFile->getFileUrl())) {
                $sFullPath = $oFile->getFileUrl();
                R::Location($sFullPath);
            } else {
                $sFullPath = \C::get('path.root.dir') . $oFile->getFileUrl();
            }

            $sFilename = $oFile->getFileName();

            /*
            * Обновляем данные
            */
            $aFileObj = [];
            $aFileObj['file_name'] = $oFile->getFileName();
            $aFileObj['file_url'] = $oFile->getFileUrl();
            $aFileObj['file_size'] = $oFile->getFileSize();
            $aFileObj['file_extension'] = $oFile->getFileExtension();
            $aFileObj['file_downloads'] = $oFile->getFileDownloads() + 1;
            $sText = serialize($aFileObj);
            $oValue->setValue($sText);
            $oValue->setValueSource($sText);

            //сохраняем
            \E::Module('Topic')->updateContentFieldValue($oValue);

            /*
            * Отдаем файл
            */
            header('Content-type: ' . $oFile->getFileExtension());
            header('Content-Disposition: attachment; filename="' . $sFilename . '"');
            F::File_PrintChunked($sFullPath);


        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('content_download_file_error'));
            return R::redirect('error');
        }

    }

}

// EOF
