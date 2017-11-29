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
 * @package actions
 * @since 1.0
 */
class ActionImg extends Action {

    const USER_AVATAR_SIZE = 100;
    const USER_PHOTO_SIZE = 250;
    const BLOG_AVATAR_SIZE = 100;

    /**
     * Инициализация
     *
     */
    public function init() {

        $this->setDefaultEvent('uploads');
    }

    protected function registerEvent() {

        $this->addEvent('uploads', 'eventUploads');
    }

    /**
     * Makes image with new size
     */
    public function eventUploads() {

        // Раз оказались здесь, то нет соответствующего изображения. Пробуем его создать
        $sUrl = F::File_RootUrl() . '/' . $this->sCurrentEvent . '/' . implode('/', $this->getParams());
        $sFile = F::File_Url2Dir($sUrl);
        $sNewFile = \E::Module('Img')->Duplicate($sFile);

        if (!$sNewFile) {
            if (preg_match('/\-(\d+)x(\d+)\.[a-z]{3}$/i', $sFile, $aMatches)) {
                $nSize = $aMatches[1];
            } else {
                $nSize = 0;
            }
            if (strpos(basename($sFile), 'avatar_blog') === 0) {
                // Запрашивается аватар блога
                $sNewFile = \E::Module('Img')->autoResizeSkinImage($sFile, 'avatar_blog', $nSize ? $nSize : self::BLOG_AVATAR_SIZE);
            } elseif (strpos(basename($sFile), 'avatar') === 0) {
                // Запрашивается аватар
                $sNewFile = \E::Module('Img')->autoResizeSkinImage($sFile, 'avatar', $nSize ? $nSize : self::USER_AVATAR_SIZE);
            } elseif (strpos(basename($sFile), 'user_photo') === 0) {
                // Запрашивается фото
                $sNewFile = \E::Module('Img')->autoResizeSkinImage($sFile, 'user_photo', $nSize ? $nSize : self::USER_PHOTO_SIZE);
            }
        }

        // Если файл успешно создан, то выводим его
        if ($sNewFile) {
            if (headers_sent($sFile, $nLine)) {
                R::Location($sUrl . '?rnd=' . uniqid('', true));
            } else {
                header_remove();
                \E::Module('Img')->RenderFile($sNewFile);
                exit;
            }
        }
        F::HttpHeader('404 Not Found');
        exit;
    }

}

// EOF