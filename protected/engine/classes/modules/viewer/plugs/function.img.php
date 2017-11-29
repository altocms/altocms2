<?php

/**
 * Выводит изображение и прикрепляет его ко временному объекту
 *
 * @param $aParams
 * @param Smarty $oSmarty
 * @return string
 */
function smarty_function_img($aParams, &$oSmarty = NULL) {

    // Пропущен тип объекта
    if (!isset($aParams['attr']['target-type'])) {
        trigger_error("img: missing 'target-type' parameter", E_USER_WARNING);

        return '';
    }

    // Пропущен идентификатор объекта
    if (!isset($aParams['attr']['target-id'])) {
        trigger_error("img: missing 'target-id' parameter", E_USER_WARNING);

        return '';
    }


    // Получим тип объекта
    $sTargetType = $aParams['attr']['target-type'];
    unset($aParams['attr']['target-type']);

    // Получим ид объекта
    $iTargetId = (int)$aParams['attr']['target-id'];
    unset($aParams['attr']['target-id']);

    // Получим ид объекта
    $sCrop = isset($aParams['attr']['crop']) ? $aParams['attr']['crop'] : FALSE;
    unset($aParams['attr']['crop']);


    // Получим изображение по временному ключу, или создадим этот ключ
    if (($sTargetTmp = \E::Module('Session')->getCookie(ModuleUploader::COOKIE_TARGET_TMP)) && \E::isUser()) {

        // Продлим куку
        \E::Module('Session')->setCookie(ModuleUploader::COOKIE_TARGET_TMP, $sTargetTmp, 'P1D', FALSE);
        // Получим предыдущее изображение и если оно было, установим в качестве текущего
        // Получим и удалим все ресурсы
        $aMresourceRel = \E::Module('Media')->GetMresourcesRelByTargetAndUser($sTargetType, $iTargetId, \E::userId());
        if ($aMresourceRel) {
            /** @var ModuleMedia_EntityMedia $oResource */
            $oMresource = array_shift($aMresourceRel);
            if ($oMresource) {
                if ($sCrop) {
                    $aParams['attr']['src'] = \E::Module('Uploader')->ResizeTargetImage($oMresource->GetUrl(), $sCrop);
                } else {
                    $aParams['attr']['src'] = $oMresource->GetUrl();
                }
                $oSmarty->assign("bImageIsTemporary", TRUE);
            }
        }

    } else {

        // Куки нет, это значит, что пользователь первый раз создает этот тип
        // и старой картинки просто нет
        if ($iTargetId == '0') {
            \E::Module('Session')->setCookie(ModuleUploader::COOKIE_TARGET_TMP,  \F::RandomStr(), 'P1D', FALSE);
        } else {
            \E::Module('Session')->delCookie(ModuleUploader::COOKIE_TARGET_TMP);
            $sImage = \E::Module('Uploader')->GetTargetImageUrl($sTargetType, $iTargetId, $sCrop);
            if ($sImage) {
                $aParams['attr']['src'] = $sImage;
                $oSmarty->assign("bImageIsTemporary", TRUE);
            }
        }

    }


    // Формируем строку атрибутов изображения
    $sAttr = '';
    if (isset($aParams['attr']) && is_array($aParams['attr'])) {
        foreach ($aParams['attr'] as $sAttrName => $sAttrValue) {
            $sAttr .= ' ' . $sAttrName . '="' . $sAttrValue . '"';
        }
    }


    // Сформируем тег изображения
    $sImageTag = '<img ' . $sAttr . '/>';


    return $sImageTag;

}

// EOF