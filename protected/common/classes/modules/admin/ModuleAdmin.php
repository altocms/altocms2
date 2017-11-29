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
 * Модуль для работы с админпанелью
 *
 * @package modules.blog
 * @since 1.0
 */
class ModuleAdmin extends Module
{
    /** @var ModuleAdmin_MapperAdmin */
    protected $oMapper;

    /**
     * Initialization
     */
    public function init()
    {
        $this->oMapper = \E::getMapper(__CLASS__);
    }

    /**
     * Grt stats of the site
     *
     * @return array
     */
    public function getSiteStat()
    {
        $sCacheKey = 'adm_site_stat';
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getSiteStat();
            \E::Module('Cache')->set($data, $sCacheKey, ['user_new', 'blog_new', 'topic_new', 'comment_new'], 60 * 15);
        }
        return $data;
    }

    /**
     * @param array  $aUsers
     * @param int    $nDays
     * @param string $sComment
     *
     * @return bool
     */
    public function banUsers($aUsers, $nDays = null, $sComment = null)
    {
        $aUserIds = $this->_entitiesId($aUsers);
        $bOk = true;
        if ($aUserIds) {
            // для все юзеров, добавляемых в бан, закрываются сессии
            foreach ($aUserIds as $nUserId) {
                if ($nUserId) {
                    \E::Module('Session')->drop($nUserId);
                    \E::Module('User')->CloseAllSessions($nUserId);
                }
            }
            if (!$nDays) {
                $nUnlim = 1;
                $dDate = null;
            } else {
                $nUnlim = 0;
                $dDate = date('Y-m-d H:i:s', time() + 3600 * 24 * $nDays);
            }
            $bOk = $this->oMapper->BanUsers($aUserIds, $dDate, $nUnlim, $sComment);
            \E::Module('Cache')->cleanByTags(['user_update']);
        }
        return $bOk;
    }

    /**
     * @param array $aUsers
     *
     * @return bool
     */
    public function unbanUsers($aUsers)
    {
        $aUserIds = $this->_entitiesId($aUsers);
        $bOk = true;
        if ($aUserIds) {
            $bOk = $this->oMapper->unbanUsers($aUserIds);
            \E::Module('Cache')->cleanByTags(array('user_update'));
        }
        return $bOk;
    }

    /**
     * @param int $iCurrPage
     * @param int $iPerPage
     *
     * @return array
     */
    public function getUsersBanList($iCurrPage, $iPerPage)
    {
        $sCacheKey = 'adm_banlist_' . $iCurrPage . '_' . $iPerPage;
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $aUsersId = $this->oMapper->getBannedUsersId($iCount, $iCurrPage, $iPerPage);
            if ($aUsersId) {
                $aUsers = \E::Module('User')->getUsersByArrayId($aUsersId);
                $data = ['collection' => $aUsers, 'count' => $iCount];
            } else {
                $data = ['collection' => [], 'count' => 0];
            }
            \E::Module('Cache')->set($data, $sCacheKey, ['adm_banlist', 'user_update'], 60 * 15);
        }
        return $data;
    }

    /**
     * @param int $iCurrPage
     * @param int $iPerPage
     *
     * @return array
     */
    public function getIpsBanList($iCurrPage, $iPerPage)
    {
        $sCacheKey = 'adm_banlist_ips_' . $iCurrPage . '_' . $iPerPage;
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = ['collection' => $this->oMapper->getIpsBanList($iCount, $iCurrPage, $iPerPage), 'count' => $iCount];
            \E::Module('Cache')->set($data, $sCacheKey, ['adm_banlist_ip'], 60 * 15);
        }
        return $data;
    }

    /**
     * Бан диапазона IP-адресов
     *
     * @param string $sIp1
     * @param string $sIp2
     * @param int    $nDays
     * @param string $sComment
     *
     * @return bool
     */
    public function setBanIp($sIp1, $sIp2, $nDays = null, $sComment = null)
    {
        $nDays = (int)$nDays;
        if ($nDays) {
            $nUnlim = 0;
            $dDate = date('Y-m-d H:i:s', time() + 3600 * 24 * $nDays);
        } else {
            $nUnlim = 1;
            $dDate = null;
        }

        //чистим зависимые кеши
        $bResult = $this->oMapper->SetBanIp($sIp1, $sIp2, $dDate, $nUnlim, $sComment);
        \E::Module('Cache')->cleanByTags(['adm_banlist_ip']);

        return $bResult;
    }

    /**
     * Снятие бана с диапазона IP-адресов
     *
     * @param array $aIds
     *
     * @return bool
     */
    public function unsetBanIp($aIds)
    {
        if (!is_array($aIds)) {
            $aIds = [(int)$aIds];
        }
        $bResult = $this->oMapper->UnsetBanIp($aIds);
        //чистим зависимые кеши
        \E::Module('Cache')->cleanByTags(['adm_banlist_ip']);

        return $bResult;
    }


    /**
     * Получить все инвайты
     *
     * @param integer $nCurrPage
     * @param integer $nPerPage
     * @param array   $aFilter
     *
     * @return array
     */
    public function getInvites($nCurrPage, $nPerPage, $aFilter = [])
    {
        // Инвайты не кешируются, поэтому работаем напрямую с БД
        $aResult = ['collection' => $this->oMapper->getInvites($iCount, $nCurrPage, $nPerPage, $aFilter), 'count' => $iCount];

        return $aResult;
    }

    /**
     * @return array
     */
    public function getInvitesCount()
    {
        return $this->oMapper->getInvitesCount();
    }

    /**
     * Удаляет инвайты по списку ID
     *
     * @param array $aIds
     *
     * @return mixed
     */
    public function deleteInvites($aIds)
    {
        return $this->oMapper->deleteInvites($aIds);
    }

    /**
     * Update config data in database
     *
     * @param   array $aConfig
     *
     * @return  bool
     */
    public function updateStorageConfig($aConfig)
    {
        $bResult = $this->oMapper->updateStorageConfig($aConfig);
        \E::Module('Cache')->cleanByTags(['config_update']);

        return $bResult;
    }

    /**
     * Read config data by prefix from database
     *
     * @param   string   $sKeyPrefix
     *
     * @return  array
     */
    public function getStorageConfig($sKeyPrefix = null)
    {
        $sCacheKey = 'config_' . $sKeyPrefix;
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getStorageConfig($sKeyPrefix);
            \E::Module('Cache')->set($data, $sCacheKey, ['config_update'], 'P1M');
        }
        return $data;
    }

    /**
     * Delete config data by prefix from database
     *
     * @param   string  $sKeyPrefix
     *
     * @return  bool
     */
    public function deleteStorageConfig($sKeyPrefix = null)
    {
        // Удаляем в базе
        $bResult = $this->oMapper->deleteStorageConfig($sKeyPrefix);
        // Чистим кеш
        \E::Module('Cache')->cleanByTags(['config_update']);

        return $bResult;
    }

    /**
     * @return array
     */
    public function getUnlinkedBlogsForUsers()
    {
        return $this->oMapper->getUnlinkedBlogsForUsers();
    }

    /**
     * @param array $aBlogIds
     *
     * @return mixed
     */
    public function delUnlinkedBlogsForUsers($aBlogIds)
    {
        $bResult = $this->oMapper->delUnlinkedBlogsForUsers($aBlogIds);
        \E::Module('Cache')->clean();
        return $bResult;
    }

    /**
     * @return array
     */
    public function getUnlinkedBlogsForCommentsOnline()
    {
        return $this->oMapper->getUnlinkedBlogsForCommentsOnline();
    }

    /**
     * @param array $aBlogIds
     *
     * @return mixed
     */
    public function delUnlinkedBlogsForCommentsOnline($aBlogIds)
    {
        $bResult = $this->oMapper->delUnlinkedBlogsForCommentsOnline($aBlogIds);
        \E::Module('Cache')->clean();
        return $bResult;
    }

    /**
     * @return array
     */
    public function getUnlinkedTopicsForCommentsOnline() {

        return $this->oMapper->getUnlinkedTopicsForCommentsOnline();
    }

    /**
     * @param array $aTopicIds
     *
     * @return mixed
     */
    public function delUnlinkedTopicsForCommentsOnline($aTopicIds)
    {
        $bResult = $this->oMapper->delUnlinkedTopicsForCommentsOnline($aTopicIds);
        \E::Module('Cache')->clean();

        return $bResult;
    }

    /**
     * @return array
     */
    public function getUnlinkedTopicsForComments()
    {
        return $this->oMapper->getUnlinkedTopicsForComments();
    }

    /**
     * @param array $aTopicIds
     *
     * @return mixed
     */
    public function delUnlinkedTopicsForComments($aTopicIds)
    {
        $bResult = $this->oMapper->delUnlinkedTopicsForComments($aTopicIds);
        \E::Module('Cache')->clean();

        return $bResult;
    }

    /**
     * @param int $nUserId
     *
     * @return bool
     */
    public function setAdministrator($nUserId)
    {
        /** @var ModuleUser_EntityUser $oUser */
        $oUser = \E::Module('User')->getUserById($nUserId);
        $bOk = false;
        if ($oUser && !$oUser->hasRole(ModuleUser::USER_ROLE_ADMINISTRATOR)) {
            $bOk = $this->oMapper->updateRole($oUser, $oUser->getRole() | ModuleUser::USER_ROLE_ADMINISTRATOR);
        }

        return $bOk;

    }

    /**
     * @param int $nUserId
     *
     * @return bool
     */
    public function unsetAdministrator($nUserId)
    {
        /** @var ModuleUser_EntityUser $oUser */
        $oUser = \E::Module('User')->getUserById($nUserId);
        if ($oUser && $oUser->hasRole(ModuleUser::USER_ROLE_ADMINISTRATOR)) {
            return $this->oMapper->updateRole($oUser, $oUser->getRole() ^ ModuleUser::USER_ROLE_ADMINISTRATOR);
        }
        return false;

    }

    /**
     * @param int $nUserId
     *
     * @return bool
     */
    public function setModerator($nUserId)
    {
        /** @var ModuleUser_EntityUser $oUser */
        $oUser = \E::Module('User')->getUserById($nUserId);
        $bOk = false;
        if ($oUser && $oUser->getRole() != ($oUser->getRole() | ModuleUser::USER_ROLE_MODERATOR)) {
            $bOk = $this->oMapper->updateRole($oUser, $oUser->getRole() | ModuleUser::USER_ROLE_MODERATOR);
        }

        return $bOk;

    }

    /**
     * @param int $nUserId
     *
     * @return bool
     */
    public function unsetModerator($nUserId)
    {
        /** @var ModuleUser_EntityUser $oUser */
        $oUser = \E::Module('User')->getUserById($nUserId);
        if ($oUser) {
            return $this->oMapper->updateRole($oUser, $oUser->getRole() ^ ModuleUser::USER_ROLE_MODERATOR);
        }
        return false;

    }

    /**
     * Число топиков без URL
     */
    public function getNumTopicsWithoutUrl() {

        return $this->oMapper->getNumTopicsWithoutUrl();
    }

    /**
     * Генерация URL топиков. Процесс может быть долгим, поэтому стараемся предотвратить ошибку по таймауту
     */
    public function generateTopicsUrl() {
        $nRecLimit = 500;

        $nTimeLimit = F::ToSeconds(ini_get('max_execution_time')) * 0.8 - 5 + time();

        $nResult = -1;
        while (true) {
            $aData = $this->oMapper->getTitleTopicsWithoutUrl($nRecLimit);
            if (!$aData) {
                $nResult = 0;
                break;
            }
            foreach ($aData as $nTopicId=>$sTopicTitle) {
                $aData[$nTopicId]['topic_url'] = substr(\F::TranslitUrl($aData[$nTopicId]['topic_title']), 0, 240);
            }
            if (!$this->oMapper->SaveTopicsUrl($aData)) {
                return -1;
            }

            // если время на исходе, то завершаем
            if (time() >= $nTimeLimit) {
                break;
            }
        }
        if ($nResult == 0) {
            // нужно ли проверять ссылки на дубликаты
            $iOnDuplicateUrl = Config::val('module.topic.on_duplicate_url', 1);
            if ($iOnDuplicateUrl) {
                $this->CheckDuplicateTopicsUrl();
            }
        } else {
            $nResult = $this->GetNumTopicsWithoutUrl();
        }
        return $nResult;
    }

    /**
     * Контроль дублей URL топиков и исправление, если нужно
     *
     * @return bool
     */
    public function checkDuplicateTopicsUrl() {

        $aData = (array)$this->oMapper->getDuplicateTopicsUrl();
        if ($aData) {
            $aSeekUrls = [];
            foreach ($aData as $aRec) {
                $aSeekUrls[] = $aRec['topic_url'];
            }
            $aData = $this->oMapper->getTopicsDataByUrl($aSeekUrls);
        }
        $aUrls = [];
        $aUpdateData = [];
        foreach ($aData as $nKey => $aRec) {
            if (!isset($aUrls[$aRec['topic_url']])) {
                $aUrls[$aRec['topic_url']] = 1;
                unset($aData[$nKey]);
            } else {
                $aUpdateData[$aRec['topic_id']]['topic_url'] = $aRec['topic_url'] . '-' . (++$aUrls[$aRec['topic_url']]);
            }
        }
        if ($aUpdateData) {
            return $this->oMapper->SaveTopicsUrl($aUpdateData);
        }
        return true;
    }

    /**
     * @param   int|object $oUserId
     *
     * @return  bool
     */
    public function delUser($oUserId) {

        if (is_object($oUserId)) {
            $nUserId = $oUserId->getId();
        } else {
            $nUserId = (int)$oUserId;
        }

        // Удаляем блоги
        $aBlogsId = \E::Module('Blog')->getBlogsByOwnerId($nUserId, true);
        if ($aBlogsId) {
            \E::Module('Blog')->DeleteBlog($aBlogsId);
        }
        $oBlog = \E::Module('Blog')->getPersonalBlogByUserId($nUserId);
        if ($oBlog) {
            \E::Module('Blog')->DeleteBlog($oBlog->getId());
        }

        // Удаляем переписку
        $iPerPage = 10000;
        do {
            $aTalks = \E::Module('Talk')->getTalksByFilter(array('user_id' => $nUserId), 1, $iPerPage);
            if ($aTalks['count']) {
                $aTalksId = [];
                foreach ($aTalks['collection'] as $oTalk) {
                    $aTalksId[] = $oTalk->getId();
                }
                if ($aTalksId) {
                    \E::Module('Talk')->DeleteTalkUserByArray($aTalksId, $nUserId);
                }
            }
        } while ($aTalks['count'] > $iPerPage);

        $bOk = $this->oMapper->delUser($nUserId);

        // Слишком много взаимосвязей, поэтому просто сбрасываем кеш
        \E::Module('Cache')->clean();

        return $bOk;
    }

    /**
     * @param bool $bActive
     *
     * @return array
     */
    public function getScriptsList($bActive = null) {

        $aResult = [];
        $aScripts = (array)C::get('script');
        if ($aScripts) {
            if (is_null($bActive)) {
                return $aScripts;
            }
            foreach($aScripts as $sScriptName => $aScript) {
                if ($bActive) {
                    if (!isset($aScript['disable']) && !$aScript['disable']) {
                        $aResult[$sScriptName] = $aScript;
                    }
                } else {
                    if (isset($aScript['disable']) && $aScript['disable']) {
                        $aResult[$sScriptName] = $aScript;
                    }
                }
            }
        }
        return $aResult;
    }

    public function getScriptById($sScriptId)
    {
        $aScript = \C::get('script.' . $sScriptId);

        return $aScript;
    }

    public function saveScript($aScript) {

        $sConfigKey = 'script.' . $aScript['id'];
        Config::writeCustomConfig(array($sConfigKey => $aScript));
    }

    public function deleteScript($xScript)
    {
        if (is_array($xScript)) {
            $sScriptId = $xScript['id'];
        } else {
            $sScriptId = (string)$xScript;
        }
        $sConfigKey = 'script.' . $sScriptId;
        Config::resetCustomConfig($sConfigKey);
    }
}

// EOF