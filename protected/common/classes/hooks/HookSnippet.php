<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------*/

/**
 * HookSnippet.php
 * Файл хука сниппетов
 *
 * @author      Андрей Воронов <andreyv@gladcode.ru>
 * @version     0.0.1.1 от 21.12.2014 21:45
 * @since       1.1
 */
class HookSnippet extends Hook {

    /**
     * Регистрация хуков
     */
    public function registerHook()
    {
        // Хук обработки шаблонного сниппета
        $this->AddHook('snippet_template_type', 'SnippetTemplateType');

        // Хук вывода имени пользователя
        $this->AddHook('snippet_user', 'SnippetUser');
        // Хук сниппета фотосета
        $this->AddHook('snippet_photoset', 'SnippetPhotoset');
    }

    /**
     * Хук обработки шаблонного сниппета
     *
     * @param array $aData
     *
     * @return bool|string
     */
    public function snippetTemplateType($aData)
    {
        // Для шаблонного сниппета обязательно должен быть параметр имени
        if (!isset($aData['params']['snippet_name'])) {
            return FALSE;
        }

        // Получим html-код сниппета
        $aVars = array('aParams' => isset($aData['params']) ? $aData['params'] : array());
        $sTemplate = "tpls/snippets/snippet.{$aData['params']['snippet_name']}.tpl";
        if (\E::Module('Viewer')->templateExists($sTemplate)) {
            $aData['result'] = trim(\E::Module('Viewer')->fetch($sTemplate, $aVars));
        } else {
            $aData['result'] = false;
        }

        return $aData['result'];
    }

    /**
     * Метод осуществляет обработку сниппета вставки имени
     * пользователя.
     *
     * @param array $aData
     *
     * @return bool|string
     */
    public function snippetUser($aData) {

        // Получим параметры, собственно, он тут единственный - это
        // имя пользователя которое и добавляем
        if (!($sUserLogin = isset($aData['params']['login']) ? $aData['params']['login'] : FALSE)) {
            return FALSE;
        }

        $aVars = array('sUserLogin' => $sUserLogin);
        // Если пользователь найден, то вернём ссылку на него
        if (is_string($sUserLogin) && ($oUser = \E::Module('User')->getUserByLogin($sUserLogin))) {
            $aVars['oUser'] = $oUser;
        }
        // Получим html-код сниппета
        $aData['result'] = trim(\E::Module('Viewer')->fetch('tpls/snippets/snippet.user.tpl', $aVars));

        return $aData['result'];
    }

    /**
     * Возвращает html-код фотосета
     *
     * @param array $aData
     *
     * @return bool|string
     */
    public function snippetPhotoset($aData) {

        // Попытаемся определить откуда вызывается сниппет фотосета
        // поскольку нужно точно определить целевой объект и его ид

        // Редактируется топик.
        // Получим его ид. и по нему поднимем необходимый фотосет
        $aAdminMatches = [];
        $sControllerPath =  R::getControllerPath();
        if ($sControllerPath === 'ajax/preview/topic/' && F::isPost('topic_id')) {
            $iTopicId = F::getRequestInt('topic_id');
        } elseif (
            preg_match('~content\/edit\/(\d+)\/~', $sControllerPath, $aMatches)
            || preg_match('~admin\/content-pages\/edit\/(\d+)\/~', $sControllerPath, $aAdminMatches)
        ) {

            // Найдем топик, из которого будем брать фотосет
            $iTopicId = !empty($aData['params']['topic'])
                ? (int)$aData['params']['topic']
                : ($aAdminMatches ? false : $aMatches[1]);
        } else {
            $iTopicId = 0;
        }

        if ($iTopicId) {
            // Странно, но топик не нашли - завернём сниппет
            if (!($oTopic = \E::Module('Topic')->getTopicById($iTopicId))) {
                return FALSE;
            }

            // Проверим, можно ли пользователю читать этот топик, а то вдруг
            // он запросил картинки из топика закрытого блога - а так нельзя
            if (!\E::Module('ACL')->isAllowShowBlog($oTopic->getBlog(), E::User())) {
                return FALSE;
            }

            // Попытаемся найти фотосет
            /** @var ModuleMedia_EntityMedia[] $aPhotoset */
            $aPhotoset = \E::Module('Media')->getMresourcesRelByTarget('photoset', $oTopic->getId());
            if (empty($aPhotoset)) {
                return FALSE;
            }

            // Фотосет нашли, теперь из него нужно выбрать только те фото,
            // которые выбрал пользователь в параметрах from и to
            $iFrom = isset($aData['params']['from']) ? $aData['params']['from'] : 0;
            $iFrom = (int)str_replace(array('last', 'first'), array(count($aPhotoset), 0), $iFrom);
            // Пользователи считают картинки с первой, а не с нулевой
            if ($iFrom) {
                $iFrom -= 1;
            }
            // Если указано количество, то правый предел игнорируем
            if (($iCount = (int)isset($aData['params']['count']) ? $aData['params']['count'] : FALSE)) {
                $iTo = $iFrom + $iCount - 1;
            } else {
                $iTo = isset($aData['params']['to']) ? $aData['params']['to'] : count($aPhotoset);
                $iTo = (int)str_replace(array('last', 'first'), array(count($aPhotoset), 0), $iTo);
                if ($iTo) {
                    $iTo -= 1;
                }
            }
            // Пользователь ошибочно указал диапазон. выдумывать ничего не будем,
            // просто не выведем фотосет
            if ($iTo - $iFrom < 0) {
                return FALSE;
            }
            // Сбросим ключи набора фото, так лучше считать диапазон
            $aPhotoset = array_values($aPhotoset);
            $aPhotos = [];
            for ($i = $iFrom; $i <= $iTo; $i++) {
                if (isset($aPhotoset[$i])) {
                    $oPhoto = $aPhotoset[$i];
                    $aPhotos[$oPhoto->getMediaId()] = $oPhoto;
                }
            }
            if (!$aPhotos) {
                return FALSE;
            }

            $sPosition = (isset($aData['params']['position']) ? $aData['params']['position'] : 'center');
            if (!in_array($sPosition, array('left', 'right'))) {
                $sPosition = 'center';
            }

            // Получим html-код сниппета
            $aVars = array(
                'oTopic'        => $oTopic,
                'aPhotos'       => $aPhotos,
                'sPosition'     => $sPosition,
                'sPhotosetHash' => md5(serialize($aData['params']))
            );

            $aData['result'] = trim(\E::Module('Viewer')->fetch('tpls/snippets/snippet.photoset.tpl', $aVars));

            return $aData['result'];
        }

        return FALSE;
    }

}

// EOF