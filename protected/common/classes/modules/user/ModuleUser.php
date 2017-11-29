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
 * Модуль для работы с пользователями
 *
 * @package modules.user
 * @since   1.0
 */
class ModuleUser extends Module
{
    const USER_SESSION_KEY = 'user_key';

    const USER_LOGIN_ERR_MIN        = 1;
    const USER_LOGIN_ERR_LEN        = 2;
    const USER_LOGIN_ERR_CHARS      = 4;
    const USER_LOGIN_ERR_DISABLED   = 8;

    // * Статусы дружбы между пользователями
    const USER_FRIEND_OFFER     = 1;    // предложение дружбы
    const USER_FRIEND_ACCEPT    = 2;    // предложение принято
    const USER_FRIEND_CANCEL    = 4;    // отмена дружбы
    const USER_FRIEND_REJECT    = 8;    // предложение отклонено
    const USER_FRIEND_NULL      = 16;   // ?

    // * Права
    const USER_ROLE_USER = 1;
    const USER_ROLE_ADMINISTRATOR = 2;
    const USER_ROLE_MODERATOR = 4;

    // Результаты авторизации
    const USER_AUTH_RESULT_OK           = 0;

    const USER_AUTH_ERROR               = 1;
    const USER_AUTH_ERR_LOGIN           = 2;
    const USER_AUTH_ERR_MAIL            = 3;
    const USER_AUTH_ERR_ID              = 4;
    const USER_AUTH_ERR_SESSION         = 5;
    const USER_AUTH_ERR_PASSWORD        = 9;

    const USER_AUTH_ERR_NOT_ACTIVATED   = 11;
    const USER_AUTH_ERR_IP_BANNED       = 12;
    const USER_AUTH_ERR_BANNED_DATE     = 13;
    const USER_AUTH_ERR_BANNED_UNLIM    = 14;

    /**
     * Объект маппера
     *
     * @var ModuleUser_MapperUser
     */
    protected $oMapper;

    /**
     * Объект текущего пользователя
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oCurrentUser;

    /**
     * Объект сессии текущего пользователя
     *
     * @var ModuleUser_EntitySession|null
     */
    protected $oSession = null;

    /**
     * Список типов пользовательских полей
     *
     * @var array
     */
    protected $aUserFieldTypes = ['social', 'contact'];

    /**
     * @var array
     */
    protected $aAdditionalData = ['vote', 'session', 'friend', 'geo_target', 'note'];

    /**
     * Инициализация
     *
     */
    public function init()
    {
        $this->oMapper = \E::getMapper(__CLASS__);

        // * Проверяем есть ли у юзера сессия, т.е. залогинен или нет
        $iUserId = (int)\E::Module('Session')->get('user_id');
        if ($iUserId && ($oUser = $this->getUserById($iUserId)) && $oUser->getActivate()) {
            if ($this->oSession = $oUser->getCurrentSession()) {
                if ($this->oSession->getDateExit()) {
                    // Сессия была закрыта
                    $this->logout();
                    return;
                }
                $this->oCurrentUser = $oUser;
            }
        }
        // Если сессия оборвалась по таймауту (не сам пользователь ее завершил),
        // то пытаемся автоматически авторизоваться
        if (!$this->oCurrentUser) {
            $this->autoLogin();
        }
        // * Обновляем сессию
        if ($this->oSession) {
            $this->updateSession();
        }
    }

    /**
     * Compares user's password and passed password
     *
     * @param ModuleUser_EntityUser $oUser
     * @param string $sCheckPassword
     *
     * @return bool
     */
    public function checkPassword($oUser, $sCheckPassword)
    {
        $sUserPassword = $oUser->getPassword();
        return \E::Module('Security')->verifyPasswordHash($sUserPassword, $sCheckPassword)
            || \E::Module('Security')->verifyPasswordHash($sUserPassword, trim($sCheckPassword));
    }

    /**
     * Возвращает список типов полей
     *
     * @return array
     */
    public function getUserFieldTypes()
    {
        return $this->aUserFieldTypes;
    }

    /**
     * Добавляет новый тип с пользовательские поля
     *
     * @param string $sType    Тип
     *
     * @return bool
     */
    public function addUserFieldTypes($sType)
    {
        if (!in_array($sType, $this->aUserFieldTypes, true)) {
            $this->aUserFieldTypes[] = $sType;
            return true;
        }
        return false;
    }

    /**
     * Получает дополнительные данные(объекты) для юзеров по их ID
     *
     * @param array|int $aUsersId   - Список ID пользователей
     * @param array     $aAllowData - Список типоd дополнительных данных для подгрузки у пользователей
     *
     * @return ModuleUser_EntityUser[]
     */
    public function getUsersAdditionalData($aUsersId, $aAllowData = null)
    {
        if (!$aUsersId) {
            return [];
        }

        if (!is_array($aUsersId)) {
            $aUsersId = [$aUsersId];
        } else {
            $aUsersId = array_unique($aUsersId);
        }

        if (count($aUsersId) === 1) {
            $iUserId = (int)reset($aUsersId);
            if ($iUserId && $iUserId === \E::userId()) {
                return [$iUserId => \E::User()];
            }
        }

        if (null === $aAllowData) {
            $aAllowData = $this->aAdditionalData;
        }
        $aAllowData = F::Array_FlipIntKeys($aAllowData);

        // * Получаем юзеров
        $aUsers = $this->getUsersByArrayId($aUsersId);

        // * Получаем дополнительные данные
        $aSessions = [];
        $aFriends = [];
        $aVote = [];
        $aGeoTargets = [];
        $aNotes = [];
        if (isset($aAllowData['session'])) {
            $aSessions = $this->getSessionsByArrayId($aUsersId);
        }

        if ($this->oCurrentUser) {
            if (isset($aAllowData['friend'])) {
                $aFriends = $this->getFriendsByArray($aUsersId, $this->oCurrentUser->getId());
            }

            if (isset($aAllowData['vote'])) {
                $aVote = \E::Module('Vote')->getVoteByArray($aUsersId, 'user', $this->oCurrentUser->getId());
            }
            if (isset($aAllowData['note'])) {
                $aNotes = $this->getUserNotesByArray($aUsersId, $this->oCurrentUser->getId());
            }
        }

        if (isset($aAllowData['geo_target'])) {
            $aGeoTargets = \E::Module('Geo')->getTargetsByTargetArray('user', $aUsersId);
        }
        $aAvatars = \E::Module('Uploader')->getMediaObjects('profile_avatar', $aUsersId, null, array('target_id'));

        // * Добавляем данные к результату
        /** @var ModuleUser_EntityUser $oUser */
        foreach ($aUsers as $oUser) {
            if (isset($aSessions[$oUser->getId()])) {
                $oUser->setSession($aSessions[$oUser->getId()]);
            } else {
                $oUser->setSession(null); // или $oUser->setSession(new ModuleUser_EntitySession());
            }
            if ($aFriends && isset($aFriends[$oUser->getId()])) {
                $oUser->setUserFriend($aFriends[$oUser->getId()]);
            } else {
                $oUser->setUserFriend(null);
            }

            if (isset($aVote[$oUser->getId()])) {
                $oUser->setVote($aVote[$oUser->getId()]);
            } else {
                $oUser->setVote(null);
            }
            if (isset($aGeoTargets[$oUser->getId()])) {
                $aTargets = $aGeoTargets[$oUser->getId()];
                $oUser->setGeoTarget(isset($aTargets[0]) ? $aTargets[0] : null);
            } else {
                $oUser->setGeoTarget(null);
            }
            if (isset($aAllowData['note'])) {
                if (isset($aNotes[$oUser->getId()])) {
                    $oUser->setUserNote($aNotes[$oUser->getId()]);
                } else {
                    $oUser->setUserNote(false);
                }
            }
            if (isset($aAvatars[$oUser->getId()])) {
                $oUser->setMediaResources('profile_avatar', $aAvatars[$oUser->getId()]);
            } else {
                $oUser->setMediaResources('profile_avatar', []);
            }
        }

        return $aUsers;
    }

    /**
     * Список юзеров по ID
     *
     * @param array $aUsersId - Список ID пользователей
     *
     * @return ModuleUser_EntityUser[]
     */
    public function getUsersByArrayId($aUsersId) 
    {
        if (\C::get('sys.cache.solid')) {
            return $this->getUsersByArrayIdSolid($aUsersId);
        }

        if (!$aUsersId) {
            return [];
        } 
        if (!is_array($aUsersId)) {
            $aUsersId = [$aUsersId];
        } else {
            $aUsersId = array_unique($aUsersId);
        }

        $aUsers = [];
        $aUserIdNotNeedQuery = [];

        // * Делаем мульти-запрос к кешу
        $aCacheKeys = F::Array_ChangeValues($aUsersId, 'user_');
        if (false !== ($data = \E::Module('Cache')->get($aCacheKeys))) {

            // * Проверяем что досталось из кеша
            foreach ($aCacheKeys as $iIndex => $sKey) {
                if (array_key_exists($sKey, $data)) {
                    if ($data[$sKey]) {
                        $aUsers[$data[$sKey]->getId()] = $data[$sKey];
                    } else {
                        $aUserIdNotNeedQuery[] = $aUsersId[$iIndex];
                    }
                }
            }
        }

        // * Смотрим каких юзеров не было в кеше и делаем запрос в БД
        $aUserIdNeedQuery = array_diff($aUsersId, array_keys($aUsers));
        $aUserIdNeedQuery = array_diff($aUserIdNeedQuery, $aUserIdNotNeedQuery);
        $aUserIdNeedStore = $aUserIdNeedQuery;

        if ($aUserIdNeedQuery) {
            if ($data = $this->oMapper->getUsersByArrayId($aUserIdNeedQuery)) {
                /** @var ModuleUser_EntityUser $oUser */
                foreach ($data as $oUser) {
                    // * Добавляем к результату и сохраняем в кеш
                    $aUsers[$oUser->getId()] = $oUser;
                    \E::Module('Cache')->set($oUser, "user_{$oUser->getId()}", [], 'P4D');
                    $aUserIdNeedStore = array_diff($aUserIdNeedStore, [$oUser->getId()]);
                }
            }
        }

        // * Сохраняем в кеш запросы не вернувшие результата
        foreach ($aUserIdNeedStore as $sId) {
            \E::Module('Cache')->set(null, "user_{$sId}", [], 'P4D');
        }

        // * Сортируем результат согласно входящему массиву
        $aUsers = F::Array_SortByKeysArray($aUsers, $aUsersId);

        return $aUsers;
    }

    /**
     * Алиас для корректной работы ORM
     *
     * @param array $aUsersId - Список ID пользователей
     *
     * @return ModuleUser_EntityUser[]
     */
    public function getUserItemsByArrayId($aUsersId) 
    {
        return $this->getUsersByArrayId($aUsersId);
    }

    /**
     * Получение пользователей по списку ID используя общий кеш
     *
     * @param array $aUsersId    Список ID пользователей
     *
     * @return ModuleUser_EntityUser[]
     */
    public function getUsersByArrayIdSolid($aUsersId)
    {
        if (!$aUsersId) {
            return [];
        } 
        if (!is_array($aUsersId)) {
            $aUsersId = [$aUsersId];
        } else {
            $aUsersId = array_unique($aUsersId);
        }

        $aUsers = [];
        $sCacheKey =\E::Module('Cache')->key('user_id', $aUsersId);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getUsersByArrayId($aUsersId);
            /** @var ModuleUser_EntityUser $oUser */
            foreach ($data as $oUser) {
                $aUsers[$oUser->getId()] = $oUser;
            }
            \E::Module('Cache')->set($aUsers, $sCacheKey, ['user_update', 'user_new'], 'P1D');
            return $aUsers;
        }
        return $data;
    }

    /**
     * Список сессий юзеров по ID
     *
     * @param array|int $aUsersId    Список ID пользователей
     *
     * @return ModuleUser_EntitySession[]
     */
    public function getSessionsByArrayId($aUsersId)
    {
        if (\C::get('sys.cache.solid')) {
            return $this->getSessionsByArrayIdSolid($aUsersId);
        }

        if (!$aUsersId) {
            return [];
        } 
        
        if (!is_array($aUsersId)) {
            $aUsersId = [$aUsersId];
        } else {
            $aUsersId = array_unique($aUsersId);
        }

        $aSessions = [];
        $aUserIdNotNeedQuery = [];

        // * Делаем мульти-запрос к кешу
        /** @var array $aCacheKeys */
        $aCacheKeys = F::Array_ChangeValues($aUsersId, 'user_session_');
        if (false !== ($data = \E::Module('Cache')->get($aCacheKeys))) {
            // * проверяем что досталось из кеша
            foreach ($aCacheKeys as $iIndex => $sKey) {
                if (array_key_exists($sKey, $data)) {
                    if ($data[$sKey] && $data[$sKey]['session']) {
                        $aSessions[$data[$sKey]['session']->getUserId()] = $data[$sKey]['session'];
                    } else {
                        $aUserIdNotNeedQuery[] = $aUsersId[$iIndex];
                    }
                }
            }
        }

        // * Смотрим каких юзеров не было в кеше и делаем запрос в БД
        $aUserIdNeedQuery = array_diff($aUsersId, array_keys($aSessions));
        $aUserIdNeedQuery = array_diff($aUserIdNeedQuery, $aUserIdNotNeedQuery);
        $aUserIdNeedStore = $aUserIdNeedQuery;

        if ($aUserIdNeedQuery) {
            if ($data = $this->oMapper->getSessionsByArrayId($aUserIdNeedQuery)) {
                foreach ($data as $oSession) {
                    // * Добавляем к результату и сохраняем в кеш
                    $aSessions[$oSession->getUserId()] = $oSession;
                    \E::Module('Cache')->set(
                        ['time' => time(), 'session' => $oSession],
                        "user_session_{$oSession->getUserId()}", ['user_session_update'],
                        'P4D'
                    );
                    $aUserIdNeedStore = array_diff($aUserIdNeedStore, [$oSession->getUserId()]);
                }
            }
        }

        // * Сохраняем в кеш запросы не вернувшие результата
        foreach ($aUserIdNeedStore as $sId) {
            \E::Module('Cache')->set(['time' => time(), 'session' => null], "user_session_{$sId}", ['user_session_update'], 'P4D');
        }

        // * Сортируем результат согласно входящему массиву
        $aSessions = F::Array_SortByKeysArray($aSessions, $aUsersId);

        return $aSessions;
    }

    /**
     * Получить список сессий по списку айдишников, но используя единый кеш
     *
     * @param array $aUsersId    Список ID пользователей
     *
     * @return ModuleUser_EntitySession[]
     */
    public function getSessionsByArrayIdSolid($aUsersId) 
    {
        if (!$aUsersId) {
            return [];
        } 
        if (!is_array($aUsersId)) {
            $aUsersId = [$aUsersId];
        } else {
            $aUsersId = array_unique($aUsersId);
        }

        $aSessions = [];

        $sCacheKey =\E::Module('Cache')->key('user_session_id_', $aUsersId);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getSessionsByArrayId($aUsersId);
            foreach ($data as $oSession) {
                $aSessions[$oSession->getUserId()] = $oSession;
            }
            \E::Module('Cache')->set($aSessions, $sCacheKey, ['user_session_update'], 'P1D');
            return $aSessions;
        }
        return $data;
    }

    /**
     * Return user's session
     *
     * @param int    $iUserId     User ID
     * @param string $sSessionKey Session ID
     *
     * @return ModuleUser_EntitySession|null
     */
    public function getSessionByUserId($iUserId, $sSessionKey = null) 
    {
        if ($sSessionKey) {
            $aSessions = $this->oMapper->getSessionsByArrayId([$iUserId], $sSessionKey);
            if ($aSessions) {
                return reset($aSessions);
            }
        } else {
            $aSessions = $this->getSessionsByArrayId($iUserId);
            if (isset($aSessions[$iUserId])) {
                return $aSessions[$iUserId];
            }
        }
        return null;
    }

    /**
     * При завершенни модуля загружаем в шалон объект текущего юзера
     *
     * @param null $oResponse
     */
    public function shutdown($oResponse = null) 
    {
        if ($this->oCurrentUser) {
            \E::Module('Viewer')->assign(
                'iUserCurrentCountTrack', \E::Module('Userfeed')->getCountTrackNew($this->oCurrentUser->getId())
            );
            \E::Module('Viewer')->assign('iUserCurrentCountTalkNew', \E::Module('Talk')->getCountTalkNew($this->oCurrentUser->getId()));
            \E::Module('Viewer')->assign(
                'iUserCurrentCountTopicDraft', \E::Module('Topic')->getCountDraftTopicsByUserId($this->oCurrentUser->getId())
            );
        }
        \E::Module('Viewer')->assign('oUserCurrent', $this->oCurrentUser);
        \E::Module('Viewer')->assign('aContentTypes', \E::Module('Topic')->getContentTypes(['content_active' => 1]));
        if ($this->oCurrentUser) {
            \E::Module('Viewer')->assign('aAllowedContentTypes', \E::Module('Topic')->getAllowContentTypeByUserId($this->oCurrentUser));
        }
    }

    /**
     * Добавляет юзера
     *
     * @param ModuleUser_EntityUser $oUser    Объект пользователя
     *
     * @return ModuleUser_EntityUser|bool
     */
    public function add($oUser) 
    {
        if ($nId = $this->oMapper->add($oUser)) {
            $oUser->setId($nId);

            //чистим зависимые кеши
            \E::Module('Cache')->cleanByTags(['user_new']);

            // * Создаем персональный блог (проверки на права там внутри)
            \E::Module('Blog')->createPersonalBlog($oUser);

            if (!\E::isUser()) {
                // Авторизуем пользователя
                $this->authorization($oUser, true);
            }
            return $oUser;
        }
        return false;
    }

    /**
     * @param ModuleUser_EntityUser $oUser
     *
     * @return bool
     */
    public function activate($oUser) 
    {
        $oUser->setActivate(1);
        $oUser->setDateActivate(\F::Now());

        return \E::Module('User')->update($oUser);
    }

    /**
     * Получить юзера по ключу активации
     *
     * @param string $sKey    Ключ активации
     *
     * @return ModuleUser_EntityUser|null
     */
    public function getUserByActivationKey($sKey) 
    {
        $id = $this->oMapper->getUserByActivationKey($sKey);
        return $this->getUserById($id);
    }

    /**
     * Получить юзера по ключу сессии
     *
     * @param   string $sKey    Сессионный ключ
     *
     * @return  ModuleUser_EntityUser|null
     */
    public function getUserBySessionKey($sKey) 
    {
        $nUserId = $this->oMapper->getUserBySessionKey($sKey);
        return $this->getUserById($nUserId);
    }

    /**
     * Получить юзера по мылу
     *
     * @param   string $sMail
     *
     * @return  ModuleUser_EntityUser|null
     */
    public function getUserByMail($sMail) 
    {
        $sMail = strtolower($sMail);
        $sCacheKey = "user_mail_{$sMail}";
        if (false === ($nUserId = \E::Module('Cache')->get($sCacheKey))) {
            if ($nUserId = $this->oMapper->getUserByMail($sMail)) {
                \E::Module('Cache')->set($nUserId, $sCacheKey, [], 'P1D');
            }
        }
        if ($nUserId) {
            return $this->getUserById($nUserId);
        }
        return null;
    }

    /**
     * Получить юзера по логину
     *
     * @param string $sLogin
     *
     * @return ModuleUser_EntityUser|null
     */
    public function getUserByLogin($sLogin)
    {
        $sLogin = mb_strtolower($sLogin, 'UTF-8');
        $sCacheKey = "user_login_{$sLogin}";
        if (false === ($nUserId = \E::Module('Cache')->get($sCacheKey))) {
            if ($nUserId = $this->oMapper->getUserByLogin($sLogin)) {
                \E::Module('Cache')->set($nUserId, $sCacheKey, [], 'P1D');
            }
        }
        if ($nUserId) {
            return $this->getUserById($nUserId);
        }
        return null;
    }

    /**
     * @param $sUserMailOrLogin
     *
     * @return ModuleUser_EntityUser|null
     */
    public function getUserByMailOrLogin($sUserMailOrLogin) 
    {
        if ((\F::CheckVal($sUserMailOrLogin, 'mail') && ($oUser = $this->getUserByMail($sUserMailOrLogin)))
            || ($oUser = $this->getUserByLogin($sUserMailOrLogin))) {
            return $oUser;
        }
        return null;
    }

    /**
     * @param      $aUserAuthData
     *
     * @return bool|ModuleUser_EntityUser|null
     */
    public function getUserAuthorization($aUserAuthData) 
    {
        $oUser = null;
        $iError = null;
        if (!empty($aUserAuthData['login'])) {
            $oUser = $this->getUserByLogin($aUserAuthData['login']);
            if (!$oUser) {
                $iError = self::USER_AUTH_ERR_LOGIN;
            }
        }
        if (!$oUser && !empty($aUserAuthData['email'])) {
            if (\F::CheckVal($aUserAuthData['email'], 'email')) {
                $oUser = $this->getUserByMail($aUserAuthData['email']);
                if ($oUser) {
                    $iError = null;
                } else {
                    $iError = self::USER_AUTH_ERR_MAIL;
                }
            }
        }
        if (!$oUser && !empty($aUserAuthData['id'])) {
            if (\F::CheckVal(!empty($aUserAuthData['id']), 'id')) {
                $oUser = $this->getUserById($aUserAuthData['id']);
                if (!$oUser) {
                    $iError = self::USER_AUTH_ERR_ID;
                }
            }
        }
        if (!$oUser && !empty($aUserAuthData['session'])) {
            $oUser = $this->getUserBySessionKey($aUserAuthData['session']);
            if (!$oUser) {
                $iError = self::USER_AUTH_ERR_SESSION;
            }
        }
        if ($oUser && !empty($aUserAuthData['password'])) {
            if (!$this->checkPassword($oUser, $aUserAuthData['password'])) {
                $iError = self::USER_AUTH_ERR_PASSWORD;
            }
        }
        if ($oUser && !$iError) {
            $iError = self::USER_AUTH_RESULT_OK;
            if (!$oUser->getActivate()) {
                $iError = self::USER_AUTH_ERR_NOT_ACTIVATED;
            }
            // Не забанен ли юзер
            if ($oUser->isBanned()) {
                if ($oUser->isBannedByIp()) {
                    $iError = self::USER_AUTH_ERR_IP_BANNED;
                } elseif ($oUser->getBanLine()) {
                    $iError = self::USER_AUTH_ERR_BANNED_DATE;
                } else {
                    $iError = self::USER_AUTH_ERR_BANNED_UNLIM;
                }
            }
        } elseif(!$iError) {
            $iError = self::USER_AUTH_ERROR;
        }
        $aUserAuthData['error'] = $iError;

        return $oUser;
    }

    /**
     * Получить юзера по ID
     *
     * @param int $nId    ID пользователя
     *
     * @return ModuleUser_EntityUser|null
     */
    public function getUserById($nId) 
    {
        if (empty($nId)) {
            return null;
        }
        $aUsers = $this->getUsersAdditionalData($nId);
        if (isset($aUsers[$nId])) {
            return $aUsers[$nId];
        }
        return null;
    }

    /**
     * Обновляет юзера
     *
     * @param ModuleUser_EntityUser $oUser    Объект пользователя
     *
     * @return bool
     */
    public function update($oUser)
    {
        $bResult = $this->oMapper->update($oUser);
        //чистим зависимые кеши
        \E::Module('Cache')->cleanByTags(['user_update']);
        \E::Module('Cache')->delete("user_{$oUser->getId()}");

        return $bResult;
    }

    /**
     * Авторизация юзера
     *
     * @param   ModuleUser_EntityUser $oUser       - Объект пользователя
     * @param   bool                  $bRemember   - Запоминать пользователя или нет
     * @param   string                $sSessionKey - Ключ сессии
     *
     * @return  bool
     */
    public function authorization($oUser, $bRemember = true, $sSessionKey = null)
    {
        if (!$oUser->getId() || !$oUser->getActivate()) {
            return false;
        }

        // * Получаем ключ текущей сессии
        if (null === $sSessionKey) {
            $sSessionKey = \E::Module('Session')->getKey();
        }

        // * Создаём новую сессию
        if (!$this->createSession($oUser, $sSessionKey)) {
            return false;
        }

        // * Запоминаем в сесси юзера
        \E::Module('Session')->set('user_id', $oUser->getId());
        $this->oCurrentUser = $oUser;

        // * Ставим куку
        if ($bRemember) {
            \E::Module('Session')->setCookie($this->getKeyName(), $sSessionKey, \C::get('sys.cookie.time'));
        }
        return true;
    }

    /**
     * Автоматическое залогинивание по ключу из куков
     *
     */
    protected function autoLogin()
    {
        if ($this->oCurrentUser) {
            return;
        }
        $sSessionKey = $this->restoreSessionKey();
        if ($sSessionKey) {
            if ($oUser = $this->getUserBySessionKey($sSessionKey)) {
                // Не забываем продлить куку
                $this->authorization($oUser, true);
            } else {
                $this->logout();
            }
        }
    }

    /**
     * @return string
     */
    protected function getKeyName() 
    {
        if (!($sKeyName = \C::get('security.user_session_key'))) {
            $sKeyName = self::USER_SESSION_KEY;
        }
        return $sKeyName;
    }

    /**
     * Restores user's session key from cookie
     *
     * @return string|null
     */
    protected function restoreSessionKey() 
    {
        $sSessionKey = \E::Module('Session')->getCookie($this->getKeyName());
        if ($sSessionKey && is_string($sSessionKey)) {
            return $sSessionKey;
        }
        return null;
    }

    /**
     * Получить текущего юзера
     *
     * @return ModuleUser_EntityUser|null
     */
    public function getCurrentUser()
    {
        return $this->oCurrentUser;
    }

    /**
     * Разлогинивание
     *
     */
    public function logout()
    {
        if ($this->oSession) {
            // Обновляем сессию
            $this->oMapper->updateSession($this->oSession);
        }
        if ($this->oCurrentUser) {
            // Close current session of the current user
            $this->closeSession();
        }
        \E::Module('Cache')->cleanByTags(['user_session_update']);

        // * Удаляем из сессии
        \E::Module('Session')->drop('user_id');

        // * Удаляем куки
        \E::Module('Session')->delCookie($this->getKeyName());

        \E::Module('Session')->dropSession();

        $this->oCurrentUser = null;
        $this->oSession = null;
    }

    /**
     * Обновление данных сессии
     * Важный момент: сессию обновляем в кеше и раз в 10 минут скидываем в БД
     */
    protected function updateSession()
    {
        $this->oSession->setDateLast(\F::Now());
        $this->oSession->setIpLast(\F::GetUserIp());

        $sCacheKey = "user_session_{$this->oSession->getUserId()}";

        // Используем кеширование по запросу
        if (false === ($data = \E::Module('Cache')->get($sCacheKey, true))) {
            $data = [
                'time'    => time(),
                'session' => $this->oSession
            ];
        } else {
            $data['session'] = $this->oSession;
        }
        if ($data['time'] <= time()) {
            $data['time'] = time() + 600;
            $this->oMapper->updateSession($this->oSession);
        }
        \E::Module('Cache')->set($data, $sCacheKey, ['user_session_update'], 'PT20M', true);
    }

    /**
     * Close current session of the user
     *
     * @param ModuleUser_EntityUser|null $oUser
     */
    public function closeSession($oUser = null)
    {
        if (!$oUser) {
            $oUser = $this->oCurrentUser;
        }
        if (!$this->oSession) {
            $oSession = $oUser->getSession();
        } else {
            $oSession = $this->oSession;
        }
        if ($oUser) {
            $this->oMapper->closeSession($oSession);
            \E::Module('Cache')->cleanByTags(['user_session_update']);
        }
    }

    /**
     * Закрытие всех сессий для заданного или для текущего юзера
     *
     * @param ModuleUser_EntityUser|null $oUser
     */
    public function closeAllSessions($oUser = null)
    {
        if (!$oUser) {
            $oUser = $this->oCurrentUser;
        }
        if ($oUser) {
            $this->oMapper->closeUserSessions($oUser);
            \E::Module('Cache')->cleanByTags(['user_session_update']);
        }
    }

    /**
     * Создание пользовательской сессии
     *
     * @param ModuleUser_EntityUser $oUser   - Объект пользователя
     * @param string                $sKey    - Сессионный ключ
     *
     * @return bool
     */
    protected function createSession($oUser, $sKey)
    {
        \E::Module('Cache')->cleanByTags(['user_session_update']);
        \E::Module('Cache')->delete("user_session_{$oUser->getId()}");

        /** @var $oSession ModuleUser_EntitySession */
        $oSession = \E::getEntity('User_Session');

        $oSession->setUserId($oUser->getId());
        $oSession->setKey($sKey);
        $oSession->setIpLast(\F::GetUserIp());
        $oSession->setIpCreate(\F::GetUserIp());
        $oSession->setDateLast(\F::Now());
        $oSession->setDateCreate(\F::Now());
        $oSession->setUserAgentHash();
        if ($this->oMapper->createSession($oSession)) {
            if ($nSessionLimit = \C::get('module.user.max_session_history')) {
                $this->limitSession($oUser, $nSessionLimit);
            }
            $oUser->setLastSession($sKey);
            if ($this->update($oUser)) {
                $this->oSession = $oSession;
                return true;
            }
        }
        return false;
    }

    /**
     * Remove old session of user
     *
     * @param $oUser
     * @param $nSessionLimit
     *
     * @return bool
     */
    protected function limitSession($oUser, $nSessionLimit)
    {
        return $this->oMapper->limitSession($oUser, $nSessionLimit);
    }

    /**
     * Получить список юзеров по дате последнего визита
     *
     * @param int $nLimit Количество
     *
     * @return ModuleUser_EntityUser[]
     */
    public function getUsersByDateLast($nLimit = 20)
    {
        if (\E::isUser()) {
            $data = $this->oMapper->getUsersByDateLast($nLimit);
        } elseif (false === ($data = \E::Module('Cache')->get("user_date_last_{$nLimit}"))) {
            $data = $this->oMapper->getUsersByDateLast($nLimit);
            \E::Module('Cache')->set($data, "user_date_last_{$nLimit}", ['user_session_update'], 'P1D');
        }
        if ($data) {
            $data = $this->getUsersAdditionalData($data);
        }
        return $data;
    }

    /**
     * Возвращает список пользователей по фильтру
     *
     * @param   array $aFilter    - Фильтр
     * @param   array $aOrder     - Сортировка
     * @param   int   $iCurrPage  - Номер страницы
     * @param   int   $iPerPage   - Количество элментов на страницу
     * @param   array $aAllowData - Список типо данных для подгрузки к пользователям
     *
     * @return  array('collection'=>array,'count'=>int)
     */
    public function getUsersByFilter($aFilter, $aOrder, $iCurrPage, $iPerPage, $aAllowData = null)
    {
        if (null === $iPerPage) {
            $iPerPage = self::DEFAULT_ITEMS_PER_PAGE;
        }
        $sCacheKey =\E::Module('Cache')->key('user_filter_', $aFilter, $aOrder, $iCurrPage, $iPerPage);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = [
                'collection' => $this->oMapper->getUsersByFilter($aFilter, $aOrder, $iCount, $iCurrPage, $iPerPage),
                'count'      => $iCount];
            \E::Module('Cache')->set($data, $sCacheKey, ['user_update', 'user_new'], 'P1D');
        }
        if ($data['collection']) {
            $data['collection'] = $this->getUsersAdditionalData($data['collection'], $aAllowData);
        }
        return $data;
    }

    /**
     * Получить список юзеров по дате регистрации
     *
     * @param int $nLimit    Количество
     *
     * @return ModuleUser_EntityUser[]
     */
    public function getUsersByDateRegister($nLimit = 20)
    {
        $aResult = $this->getUsersByFilter(['activate' => 1], ['id' => 'desc'], 1, $nLimit);
        return $aResult['collection'];
    }

    /**
     * Получить статистику по юзерам
     *
     * @return array
     */
    public function getStatUsers()
    {
        if (false === ($aStat = \E::Module('Cache')->get('user_stats'))) {
            $aStat['count_all'] = $this->oMapper->getCountByRole(self::USER_ROLE_USER);
            $sDate = date('Y-m-d H:i:s', time() - \C::get('module.user.time_active'));
            $aStat['count_active'] = $this->oMapper->getCountUsersActive($sDate);
            $aStat['count_inactive'] = $aStat['count_all'] - $aStat['count_active'];
            $aSex = $this->oMapper->getCountUsersSex();
            $aStat['count_sex_man'] = (isset($aSex['man']) ? $aSex['man']['count'] : 0);
            $aStat['count_sex_woman'] = (isset($aSex['woman']) ? $aSex['woman']['count'] : 0);
            $aStat['count_sex_other'] = (isset($aSex['other']) ? $aSex['other']['count'] : 0);

            \E::Module('Cache')->set($aStat, 'user_stats', ['user_update', 'user_new'], 'P4D');
        }
        return $aStat;
    }

    /**
     * Получить список юзеров по первым  буквам логина
     *
     * @param string $sUserLogin - Логин
     * @param int    $nLimit     - Количество
     *
     * @return ModuleUser_EntityUser[]
     */
    public function getUsersByLoginLike($sUserLogin, $nLimit)
    {
        $sCacheKey =\E::Module('Cache')->key('user_like_', $sUserLogin, $nLimit);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getUsersByLoginLike($sUserLogin, $nLimit);
            \E::Module('Cache')->set($data, $sCacheKey, ['user_new'], 'P2D');
        }
        if ($data) {
            $data = $this->getUsersAdditionalData($data);
        }
        return $data;
    }

    /**
     * Получить список отношений друзей
     *
     * @param   int|array $aUsersId - Список ID пользователей проверяемых на дружбу
     * @param   int       $iUserId  - ID пользователя у которого проверяем друзей
     *
     * @return ModuleUser_EntityFriend[]
     */
    public function getFriendsByArray($aUsersId, $iUserId)
    {
        if (\C::get('sys.cache.solid')) {
            return $this->getFriendsByArraySolid($aUsersId, $iUserId);
        }

        if (!$aUsersId) {
            return [];
        }
        if (!is_array($aUsersId)) {
            $aUsersId = [$aUsersId];
        } else {
            $aUsersId = array_unique($aUsersId);
        }

        $aFriends = [];
        $aUserIdNotNeedQuery = [];

        // * Делаем мульти-запрос к кешу
        $aCacheKeys = F::Array_ChangeValues($aUsersId, 'user_friend_', '_' . $iUserId);
        if (false !== ($data = \E::Module('Cache')->get($aCacheKeys))) {
            // * проверяем что досталось из кеша
            foreach ($aCacheKeys as $iIndex => $sKey) {
                if (array_key_exists($sKey, $data)) {
                    if ($data[$sKey]) {
                        $aFriends[$data[$sKey]->getFriendId()] = $data[$sKey];
                    } else {
                        $aUserIdNotNeedQuery[] = $aUsersId[$iIndex];
                    }
                }
            }
        }

        // * Смотрим каких френдов не было в кеше и делаем запрос в БД
        $aUserIdNeedQuery = array_diff($aUsersId, array_keys($aFriends));
        $aUserIdNeedQuery = array_diff($aUserIdNeedQuery, $aUserIdNotNeedQuery);
        $aUserIdNeedStore = $aUserIdNeedQuery;

        if ($aUserIdNeedQuery) {
            if ($data = $this->oMapper->getFriendsByArrayId($aUserIdNeedQuery, $iUserId)) {
                foreach ($data as $oFriend) {
                    // * Добавляем к результату и сохраняем в кеш
                    $aFriends[$oFriend->getFriendId($iUserId)] = $oFriend;
                    /**
                     * Тут кеш нужно будет продумать как-то по другому.
                     * Пока не трогаю, ибо этот код все равно не выполняется.
                     * by Kachaev
                     */
                    \E::Module('Cache')->set(
                        $oFriend, "user_friend_{$oFriend->getFriendId()}_{$oFriend->getUserId()}", [], 'P4D'
                    );
                    $aUserIdNeedStore = array_diff($aUserIdNeedStore, [$oFriend->getFriendId()]);
                }
            }
        }

        // * Сохраняем в кеш запросы не вернувшие результата
        foreach ($aUserIdNeedStore as $sId) {
            \E::Module('Cache')->set(null, "user_friend_{$sId}_{$iUserId}", [], 'P4D');
        }

        // * Сортируем результат согласно входящему массиву
        $aFriends = F::Array_SortByKeysArray($aFriends, $aUsersId);

        return $aFriends;
    }

    /**
     * Получить список отношений друзей используя единый кеш
     *
     * @param  array $aUsersId    Список ID пользователей проверяемых на дружбу
     * @param  int   $nUserId    ID пользователя у которого проверяем друзей
     *
     * @return ModuleUser_EntityFriend[]
     */
    public function getFriendsByArraySolid($aUsersId, $nUserId)
    {
        if (!$aUsersId) {
            return [];
        }
        if (!is_array($aUsersId)) {
            $aUsersId = [$aUsersId];
        } else {
            $aUsersId = array_unique($aUsersId);
        }

        $aFriends = [];
        $sCacheKey = "user_friend_{$nUserId}_id_" . implode(',', $aUsersId);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getFriendsByArrayId($aUsersId, $nUserId);
            foreach ($data as $oFriend) {
                $aFriends[$oFriend->getFriendId($nUserId)] = $oFriend;
            }

            \E::Module('Cache')->set($aFriends, $sCacheKey, ["friend_change_user_{$nUserId}"], 'P1D');
            return $aFriends;
        }
        return $data;
    }

    /**
     * Получаем привязку друга к юзеру(есть ли у юзера данный друг)
     *
     * @param  int $nFriendId    ID пользователя друга
     * @param  int $nUserId      ID пользователя
     *
     * @return ModuleUser_EntityFriend|null
     */
    public function getFriend($nFriendId, $nUserId)
    {
        $data = $this->getFriendsByArray($nFriendId, $nUserId);
        if (isset($data[$nFriendId])) {
            return $data[$nFriendId];
        }
        return null;
    }

    /**
     * Добавляет друга
     *
     * @param  ModuleUser_EntityFriend $oFriend    Объект дружбы(связи пользователей)
     *
     * @return bool
     */
    public function addFriend($oFriend)
    {
        $bResult = $this->oMapper->addFriend($oFriend);
        //чистим зависимые кеши
        \E::Module('Cache')->cleanByTags(
            ["friend_change_user_{$oFriend->getUserFrom()}", "friend_change_user_{$oFriend->getUserTo()}"]
        );
        \E::Module('Cache')->delete("user_friend_{$oFriend->getUserFrom()}_{$oFriend->getUserTo()}");
        \E::Module('Cache')->delete("user_friend_{$oFriend->getUserTo()}_{$oFriend->getUserFrom()}");

        return $bResult;
    }

    /**
     * Удаляет друга
     *
     * @param  ModuleUser_EntityFriend $oFriend Объект дружбы(связи пользователей)
     *
     * @return bool
     */
    public function deleteFriend($oFriend)
    {
        // устанавливаем статус дружбы "удалено"
        $oFriend->setStatusByUserId(ModuleUser::USER_FRIEND_CANCEL, $oFriend->getUserId());
        $bResult = $this->oMapper->updateFriend($oFriend);
        // чистим зависимые кеши
        \E::Module('Cache')->cleanByTags(
            ["friend_change_user_{$oFriend->getUserFrom()}", "friend_change_user_{$oFriend->getUserTo()}"]
        );
        \E::Module('Cache')->delete("user_friend_{$oFriend->getUserFrom()}_{$oFriend->getUserTo()}");
        \E::Module('Cache')->delete("user_friend_{$oFriend->getUserTo()}_{$oFriend->getUserFrom()}");

        return $bResult;
    }

    /**
     * Удаляет информацию о дружбе из базы данных
     *
     * @param  ModuleUser_EntityFriend $oFriend    Объект дружбы(связи пользователей)
     *
     * @return bool
     */
    public function EraseFriend($oFriend)
    {
        $bResult = $this->oMapper->EraseFriend($oFriend);
        // чистим зависимые кеши
        \E::Module('Cache')->cleanByTags(
            ["friend_change_user_{$oFriend->getUserFrom()}", "friend_change_user_{$oFriend->getUserTo()}"]
        );
        \E::Module('Cache')->delete("user_friend_{$oFriend->getUserFrom()}_{$oFriend->getUserTo()}");
        \E::Module('Cache')->delete("user_friend_{$oFriend->getUserTo()}_{$oFriend->getUserFrom()}");

        return $bResult;
    }

    /**
     * Обновляет информацию о друге
     *
     * @param  ModuleUser_EntityFriend $oFriend    Объект дружбы(связи пользователей)
     *
     * @return bool
     */
    public function updateFriend($oFriend)
    {
        $bResult = $this->oMapper->updateFriend($oFriend);
        // чистим зависимые кеши
        \E::Module('Cache')->cleanByTags(
            ["friend_change_user_{$oFriend->getUserFrom()}", "friend_change_user_{$oFriend->getUserTo()}"]
        );
        \E::Module('Cache')->delete("user_friend_{$oFriend->getUserFrom()}_{$oFriend->getUserTo()}");
        \E::Module('Cache')->delete("user_friend_{$oFriend->getUserTo()}_{$oFriend->getUserFrom()}");
        return $bResult;
    }

    /**
     * Получает список друзей
     *
     * @param  int $nUserId     ID пользователя
     * @param  int $iPage       Номер страницы
     * @param  int $iPerPage    Количество элементов на страницу
     *
     * @return array
     */
    public function getUsersFriend($nUserId, $iPage = 1, $iPerPage = 10)
    {
        $sCacheKey = "user_friend_{$nUserId}_{$iPage}_{$iPerPage}";
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = [
                'collection' => $this->oMapper->getUsersFriend($nUserId, $iCount, $iPage, $iPerPage),
                'count'      => $iCount
            ];
            \E::Module('Cache')->set($data, $sCacheKey, ["friend_change_user_{$nUserId}"], 'P2D');
        }
        if ($data['collection']) {
            $data['collection'] = $this->getUsersAdditionalData($data['collection']);
        }
        return $data;
    }

    /**
     * Получает количество друзей
     *
     * @param  int $nUserId    ID пользователя
     *
     * @return int
     */
    public function getCountUsersFriend($nUserId)
    {
        $sCacheKey = "count_user_friend_{$nUserId}";
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getCountUsersFriend($nUserId);
            \E::Module('Cache')->set($data, $sCacheKey, ["friend_change_user_{$nUserId}"], 'P2D');
        }
        return $data;
    }

    /**
     * Получает инвайт по его коду
     *
     * @param  string $sCode    Код инвайта
     * @param  int    $iUsed    Флаг испольщования инвайта
     *
     * @return ModuleUser_EntityInvite|null
     */
    public function getInviteByCode($sCode, $iUsed = 0)
    {
        return $this->oMapper->getInviteByCode($sCode, $iUsed);
    }

    /**
     * Добавляет новый инвайт
     *
     * @param ModuleUser_EntityInvite $oInvite    Объект инвайта
     *
     * @return ModuleUser_EntityInvite|bool
     */
    public function addInvite($oInvite)
    {
        if ($nId = $this->oMapper->addInvite($oInvite)) {
            $oInvite->setId($nId);
            return $oInvite;
        }
        return false;
    }

    /**
     * Обновляет инвайт
     *
     * @param ModuleUser_EntityInvite $oInvite    бъект инвайта
     *
     * @return bool
     */
   public function updateInvite($oInvite)
   {
        $bResult = $this->oMapper->updateInvite($oInvite);
        // чистим зависимые кеши
        \E::Module('Cache')->cleanByTags(
            ["invate_new_to_{$oInvite->getUserToId()}", "invate_new_from_{$oInvite->getUserFromId()}"]
        );
        return $bResult;

   }

    /**
     * Генерирует новый инвайт
     *
     * @param ModuleUser_EntityUser $oUser    Объект пользователя
     *
     * @return ModuleUser_EntityInvite|bool
     */
    public function generateInvite($oUser)
    {
        /** @var ModuleUser_EntityInvite $oInvite */
        $oInvite = \E::getEntity('User_Invite');
        $oInvite->setCode(\F::RandomStr(32));
        $oInvite->setDateAdd(\F::Now());
        $oInvite->setUserFromId($oUser->getId());

        return $this->addInvite($oInvite);
    }

    /**
     * Получает число использованых приглашений юзером за определенную дату
     *
     * @param int    $nUserIdFrom    ID пользователя
     * @param string $sDate          Дата
     *
     * @return int
     */
    public function getCountInviteUsedByDate($nUserIdFrom, $sDate)
    {
        return $this->oMapper->getCountInviteUsedByDate($nUserIdFrom, $sDate);
    }

    /**
     * Получает полное число использованных приглашений юзера
     *
     * @param int $nUserIdFrom    ID пользователя
     *
     * @return int
     */
    public function getCountInviteUsed($nUserIdFrom)
    {
        return $this->oMapper->getCountInviteUsed($nUserIdFrom);
    }

    /**
     * Получаем число доступных приглашений для юзера
     *
     * @param ModuleUser_EntityUser $oUserFrom Объект пользователя
     *
     * @return int
     */
    public function getCountInviteAvailable($oUserFrom)
    {
        $sDay = 7;
        $iCountUsed = $this->getCountInviteUsedByDate(
            $oUserFrom->getId(), date('Y-m-d 00:00:00', mktime(0, 0, 0, date('m'), date('d') - $sDay, date('Y')))
        );
        $iCountAllAvailable = round((float)$oUserFrom->getRating() + (float)$oUserFrom->getSkill());
        $iCountAllAvailable = $iCountAllAvailable < 0 ? 0 : $iCountAllAvailable;
        $iCountAvailable = $iCountAllAvailable - $iCountUsed;
        $iCountAvailable = $iCountAvailable < 0 ? 0 : $iCountAvailable;

        return $iCountAvailable;
    }

    /**
     * Получает список приглашенных юзеров
     *
     * @param int $nUserId    ID пользователя
     *
     * @return array
     */
    public function getUsersInvite($nUserId)
    {
        if (false === ($data = \E::Module('Cache')->get("users_invite_{$nUserId}"))) {
            $data = $this->oMapper->getUsersInvite($nUserId);
            \E::Module('Cache')->set($data, "users_invite_{$nUserId}", ["invate_new_from_{$nUserId}"], 'P1D');
        }
        if ($data) {
            $data = $this->getUsersAdditionalData($data);
        }
        return $data;
    }

    /**
     * Получает юзера который пригласил
     *
     * @param int $nUserIdTo    ID пользователя
     *
     * @return ModuleUser_EntityUser|null
     */
    public function getUserInviteFrom($nUserIdTo)
    {
        if (false === ($id = \E::Module('Cache')->get("user_invite_from_{$nUserIdTo}"))) {
            $id = $this->oMapper->getUserInviteFrom($nUserIdTo);
            \E::Module('Cache')->set($id, "user_invite_from_{$nUserIdTo}", ["invate_new_to_{$nUserIdTo}"], 'P1D');
        }
        return $this->getUserById($id);
    }

    /**
     * Добавляем воспоминание(восстановление) пароля
     *
     * @param ModuleUser_EntityReminder $oReminder    Объект восстановления пароля
     *
     * @return bool
     */
    public function addReminder($oReminder)
    {
        return $this->oMapper->addReminder($oReminder);
    }

    /**
     * Сохраняем воспомнинание(восстановление) пароля
     *
     * @param ModuleUser_EntityReminder $oReminder    Объект восстановления пароля
     *
     * @return bool
     */
    public function updateReminder($oReminder)
    {
        return $this->oMapper->updateReminder($oReminder);
    }

    /**
     * Получаем запись восстановления пароля по коду
     *
     * @param string $sCode    Код восстановления пароля
     *
     * @return ModuleUser_EntityReminder|null
     */
    public function getReminderByCode($sCode)
    {
        return $this->oMapper->getReminderByCode($sCode);
    }

    /**
     * Загрузка аватара пользователя
     *
     * @param  string     $sFile - Путь до оригинального файла
     * @param  object|int $xUser - Сущность пользователя или ID пользователя
     * @param  array      $aSize - Размер области из которой нужно вырезать картинку - array('x1'=>0,'y1'=>0,'x2'=>100,'y2'=>100)
     *
     * @return string|bool
     */
    public function uploadAvatar($sFile, $xUser, $aSize = [])
    {
        if ($sFile && $xUser) {
            if (is_object($xUser)) {
                $iUserId = $xUser->getId();
            } else {
                $iUserId = (int)$xUser;
            }
            if ($iUserId && ($oStoredFile = \E::Module('Uploader')->storeImage($sFile, 'profile_avatar', $iUserId, $aSize))) {
                return $oStoredFile->getUrl();
            }
        }
        return false;
    }

    /**
     * Удаляет аватары пользователя всех размеров
     *
     * @param ModuleUser_EntityUser $oUser - Объект пользователя
     *
     * @return bool
     */
    public function deleteAvatar($oUser)
    {
        $bResult = true;
        // * Если аватар есть, удаляем его и его рейсайзы
        if ($sAvatar = $oUser->getProfileAvatar()) {
            $sFile = \E::Module('Uploader')->url2Dir($sAvatar);
            $bResult = \E::Module('Img')->delete($sFile);
            if ($bResult) {
                $oUser->setProfileAvatar(null);
                \E::Module('User')->update($oUser);
            }
        }
        return $bResult;
    }

    /**
     * Удаляет аватары производных размеров (основной не трогает)
     *
     * @param ModuleUser_EntityUser $oUser
     *
     * @return bool
     */
    public function deleteAvatarSizes($oUser)
    {
        // * Если аватар есть, удаляем его и его рейсайзы
        if ($sAvatar = $oUser->getProfileAvatar()) {
            $sFile = \E::Module('Uploader')->url2Dir($sAvatar);
            return \E::Module('Uploader')->deleteAs($sFile . '-*.*');
        }
        return true;
    }

    /**
     * Загрузка фотографии пользователя
     *
     * @param  string     $sFile - Серверный путь до временной фотографии
     * @param  object|int $xUser - Сущность пользователя или ID пользователя
     * @param  array      $aSize - Размер области из которой нужно вырезать картинку - array('x1'=>0,'y1'=>0,'x2'=>100,'y2'=>100)
     *
     * @return string|bool
     */
    public function uploadPhoto($sFile, $xUser, $aSize = [])
    {
        if ($sFile && $xUser) {
            if (is_object($xUser)) {
                $iUserId = $xUser->getId();
            } else {
                $iUserId = (int)$xUser;
            }
            if ($iUserId && ($oStoredFile = \E::Module('Uploader')->storeImage($sFile, 'profile_photo', $iUserId, $aSize))) {
                return $oStoredFile->getUrl();
            }
        }
        return false;
    }

    /**
     * Удаляет фото пользователя
     *
     * @param ModuleUser_EntityUser $oUser
     *
     * @return bool
     */
    public function deletePhoto($oUser)
    {
        $bResult = true;
        if ($sPhoto = $oUser->getProfilePhoto()) {
            $sFile = \E::Module('Uploader')->url2Dir($sPhoto);
            $bResult = \E::Module('Img')->delete($sFile);
            if ($bResult) {
                $oUser->setProfilePhoto(null);
                \E::Module('User')->update($oUser);
            }
        }
        return $bResult;
    }

    /**
     * Проверяет логин на корректность
     *
     * @param string $sLogin    Логин пользователя
     * @param int    $nError    Ошибка (если есть)
     *
     * @return bool
     */
    public function checkLogin($sLogin, &$nError)
    {
        // проверка на допустимость логина
        $aDisabledLogins = F::Array_Str2Array(\C::get('module.user.login.disabled'));
        if (\F::Array_StrInArray($sLogin, $aDisabledLogins)) {
            $nError = self::USER_LOGIN_ERR_DISABLED;
            return false;
        } elseif(strpos(strtolower($sLogin), 'id-') === 0 || strpos(strtolower($sLogin), 'login-') === 0) {
            $nError = self::USER_LOGIN_ERR_DISABLED;
            return false;
        }

        $sCharset = \C::get('module.user.login.charset');
        $nMin = (int)\C::get('module.user.login.min_size');
        $nMax = (int)\C::get('module.user.login.max_size');

        // Логин не может быть меньше 1
        if ($nMin < 1) {
            $nMin = 1;
        }

        $nError = 0;
        // поверка на длину логина
        if (!$nMax) {
            $bOk = mb_strlen($sLogin, 'UTF-8') >= $nMin;
            if (!$bOk) {
                $nError = self::USER_LOGIN_ERR_MIN;
            }
        } else {
            $bOk = mb_strlen($sLogin, 'UTF-8') >= $nMin && mb_strlen($sLogin, 'UTF-8') <= $nMax;
            if (!$bOk) {
                $nError = self::USER_LOGIN_ERR_LEN;
            }
        }
        if ($bOk && $sCharset) {
            // поверка на набор символов
            if (!preg_match('/^([' . $sCharset . ']+)$/iu', $sLogin)) {
                $nError = self::USER_LOGIN_ERR_CHARS;
                $bOk = false;
            }
        }
        return $bOk;
    }

    /**
     * @param string $sLogin
     *
     * @return int
     */
    public function invalidLogin($sLogin)
    {
        $this->checkLogin($sLogin, $nError);

        return (int)$nError;
    }

    /**
     * Получить дополнительные поля профиля пользователя
     *
     * @param array|null $aType Типы полей, null - все типы
     *
     * @return ModuleUser_EntityField[]
     */
    public function getUserFields($aType = null)
    {
        $sCacheKey = 'user_fields';
        if (false === ($data = \E::Module('Cache')->get($sCacheKey, 'tmp,'))) {
            $data = $this->oMapper->getUserFields();
            \E::Module('Cache')->set($data, $sCacheKey, ['user_fields_update'], 'P10D', 'tmp,');
        }
        $aResult = [];
        if ($data) {
            if (empty($aType)) {
                $aResult = $data;
            } else {
                if (!is_array($aType)) {
                    $aType = [$aType];
                }
                foreach($data as $oUserField) {
                    if (in_array($oUserField->getType(), $aType, true)) {
                        $aResult[$oUserField->getId()] = $oUserField;
                    }
                }
            }
        }

        return $aResult;
    }

    /**
     * Получить значения дополнительных полей профиля пользователя
     *
     * @param int          $iUserId       ID пользователя
     * @param bool         $bNotEmptyOnly Загружать только непустые поля
     * @param array|string $xType         Типы полей, null - все типы
     *
     * @return ModuleUser_EntityField[]
     */
    public function getUserFieldsValues($iUserId, $bNotEmptyOnly = true, $xType = [])
    {
        if (!is_array($xType)) {
            $xType = [$xType];
        }
        $sCacheKey = 'user_fields_values_' . serialize([$iUserId, $bNotEmptyOnly, $xType]);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $aResult = [];
            // Get all user fields
            $aAllFields = $this->getUserFields($xType);
            // Get user fields with values and group them by ID
            $data = $this->oMapper->getUserFieldsValues($iUserId, $xType);
            $aValuesByTypes = [];
            foreach($data as $oFieldValue) {
                if ($oFieldValue->getValue()) {
                    $aValuesByTypes[$oFieldValue->getId()][] = $oFieldValue;
                }
            }
            // Forming result
            foreach($aAllFields as $iIdx => $oUserField) {
                if (isset($aValuesByTypes[$oUserField->getId()])) {
                    // If field of the type has values then add them ...
                    foreach($aValuesByTypes[$oUserField->getId()] as $oFieldValue) {
                        $aResult[] = $oFieldValue;
                    }
                } elseif(!$bNotEmptyOnly) {
                    // ... else add empty field (if has no flag $bNotEmptyOnly)
                    $aResult[] = $oUserField;
                }
            }
            \E::Module('Cache')->set($aResult, $sCacheKey, ['user_fields_update', "user_update_{$iUserId}"], 'P10D');
        } else {
            $aResult = $data;
        }

        return $aResult;
    }

    /**
     * Получить по имени поля его значение для определённого пользователя
     *
     * @param int    $nUserId - ID пользователя
     * @param string $sName   - Имя поля
     *
     * @return string
     */
    public function getUserFieldValueByName($nUserId, $sName)
    {
        return $this->oMapper->getUserFieldValueByName($nUserId, $sName);
    }

    /**
     * Установить значения дополнительных полей профиля пользователя
     *
     * @param int   $nUserId    ID пользователя
     * @param array $aFields    Ассоциативный массив полей id => value
     * @param int   $nCountMax  Максимальное количество одинаковых полей
     *
     * @return bool
     */
    public function setUserFieldsValues($nUserId, $aFields, $nCountMax = 1)
    {
        $xResult = $this->oMapper->setUserFieldsValues($nUserId, $aFields, $nCountMax);
        \E::Module('Cache')->cleanByTags("user_update_{$nUserId}");

        return $xResult;
    }

    /**
     * Добавить поле
     *
     * @param ModuleUser_EntityField $oField - Объект пользовательского поля
     *
     * @return bool
     */
    public function addUserField($oField)
    {
        $xResult = $this->oMapper->addUserField($oField);
        \E::Module('Cache')->cleanByTags('user_fields_update');

        return $xResult;
    }

    /**
     * Изменить поле
     *
     * @param ModuleUser_EntityField $oField - Объект пользовательского поля
     *
     * @return bool
     */
    public function updateUserField($oField)
    {
        $xResult = $this->oMapper->updateUserField($oField);
        \E::Module('Cache')->cleanByTags('user_fields_update');

        return $xResult;
    }

    /**
     * Удалить поле
     *
     * @param int $nId - ID пользовательского поля
     *
     * @return bool
     */
    public function deleteUserField($nId)
    {
        $xResult = $this->oMapper->deleteUserField($nId);
        \E::Module('Cache')->cleanByTags('user_fields_update');

        return $xResult;
    }

    /**
     * Проверяет существует ли поле с таким именем
     *
     * @param string $sName - Имя поля
     * @param int    $nId   - ID поля
     *
     * @return bool
     */
    public function userFieldExistsByName($sName, $nId = null)
    {
        return $this->oMapper->userFieldExistsByName($sName, $nId);
    }

    /**
     * Проверяет существует ли поле с таким ID
     *
     * @param int $nId    ID поля
     *
     * @return bool
     */
    public function userFieldExistsById($nId)
    {
        return $this->oMapper->userFieldExistsById($nId);
    }

    /**
     * Удаляет у пользователя значения полей
     *
     * @param  int|array  $aUsersId   ID пользователя
     * @param  array|null $aTypes     Список типов для удаления
     *
     * @return bool
     */
    public function deleteUserFieldValues($aUsersId, $aTypes = null)
    {
        $xResult = $this->oMapper->deleteUserFieldValues($aUsersId, $aTypes);
        \E::Module('Cache')->cleanByTags('user_fields_update');

        return $xResult;
    }

    /**
     * Возвращает список заметок пользователя
     *
     * @param int $nUserId      ID пользователя
     * @param int $iCurrPage    Номер страницы
     * @param int $iPerPage     Количество элементов на страницу
     *
     * @return array('collection'=>array,'count'=>int)
     */
    public function getUserNotesByUserId($nUserId, $iCurrPage, $iPerPage)
    {
        $aResult = $this->oMapper->getUserNotesByUserId($nUserId, $iCount, $iCurrPage, $iPerPage);

        if ($aResult) {
            // * Цепляем пользователей
            $aUsersId = [];
            foreach ($aResult as $oNote) {
                $aUsersId[] = $oNote->getTargetUserId();
            }
            if ($aUsersId) {
                $aUsers = $this->getUsersAdditionalData($aUsersId, []);
                foreach ($aResult as $oNote) {
                    if (isset($aUsers[$oNote->getTargetUserId()])) {
                        $oNote->setTargetUser($aUsers[$oNote->getTargetUserId()]);
                    } else {
                        // пустого пользователя во избеания ошибок, т.к. пользователь всегда должен быть
                        $oNote->setTargetUser(\E::getEntity('User'));
                    }
                }
            }
        }
        return ['collection' => $aResult, 'count' => $iCount];
    }

    /**
     * Возвращает количество заметок у пользователя
     *
     * @param int $nUserId    ID пользователя
     *
     * @return int
     */
    public function getCountUserNotesByUserId($nUserId)
    {
        return $this->oMapper->getCountUserNotesByUserId($nUserId);
    }

    /**
     * Возвращет заметку по автору и пользователю
     *
     * @param int $nTargetUserId    ID пользователя о ком заметка
     * @param int $nUserId          ID пользователя автора заметки
     *
     * @return ModuleUser_EntityNote
     */
    public function getUserNote($nTargetUserId, $nUserId)
    {
        return $this->oMapper->getUserNote($nTargetUserId, $nUserId);
    }

    /**
     * Возвращает заметку по ID
     *
     * @param int $nId    ID заметки
     *
     * @return ModuleUser_EntityNote
     */
    public function getUserNoteById($nId)
    {
        return $this->oMapper->getUserNoteById($nId);
    }

    /**
     * Возвращает список заметок пользователя по ID целевых юзеров
     *
     * @param array $aUsersId    Список ID целевых пользователей
     * @param int   $nUserId    ID пользователя, кто оставлял заметки
     *
     * @return array
     */
    public function getUserNotesByArray($aUsersId, $nUserId)
    {
        if (!$aUsersId) {
            return [];
        }
        if (!is_array($aUsersId)) {
            $aUsersId = [$aUsersId];
        } else {
            $aUsersId = array_unique($aUsersId);
        }

        $aNotes = [];

        $sCacheKey = "user_notes_{$nUserId}_id_" . implode(',', $aUsersId);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getUserNotesByArrayUserId($aUsersId, $nUserId);
            foreach ($data as $oNote) {
                $aNotes[$oNote->getTargetUserId()] = $oNote;
            }

            \E::Module('Cache')->set($aNotes, $sCacheKey, ["user_note_change_by_user_{$nUserId}"], 'P1D');
            return $aNotes;
        }
        return $data;
    }

    /**
     * Удаляет заметку по ID
     *
     * @param int $nId    ID заметки
     *
     * @return bool
     */
    public function deleteUserNoteById($nId)
    {
        $bResult = $this->oMapper->deleteUserNoteById($nId);
        if ($oNote = $this->getUserNoteById($nId)) {
            \E::Module('Cache')->cleanByTags(["user_note_change_by_user_{$oNote->getUserId()}"]);
        }
        return $bResult;
    }

    /**
     * Сохраняет заметку в БД, если ее нет то создает новую
     *
     * @param ModuleUser_EntityNote $oNote    Объект заметки
     *
     * @return bool|ModuleUser_EntityNote
     */
    public function saveNote($oNote)
    {
        if (!$oNote->getDateAdd()) {
            $oNote->setDateAdd(\F::Now());
        }

        \E::Module('Cache')->cleanByTags(["user_note_change_by_user_{$oNote->getUserId()}"]);
        if ($oNoteOld = $this->getUserNote($oNote->getTargetUserId(), $oNote->getUserId())) {
            $oNoteOld->setText($oNote->getText());
            $this->oMapper->updateUserNote($oNoteOld);
            return $oNoteOld;
        }

        if ($nId = $this->oMapper->addUserNote($oNote)) {
            $oNote->setId($nId);
            return $oNote;
        }

        return false;
    }

    /**
     * Возвращает список префиксов логинов пользователей (для алфавитного указателя)
     *
     * @param int $nPrefixLength    Длина префикса
     *
     * @return string[]
     */
    public function getGroupPrefixUser($nPrefixLength = 1)
    {
        $sCacheKey = "group_prefix_user_{$nPrefixLength}";
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getGroupPrefixUser($nPrefixLength);
            \E::Module('Cache')->set($data, $sCacheKey, ['user_new'], 'P1D');
        }
        return $data;
    }

    /**
     * Добавляет запись о смене емайла
     *
     * @param ModuleUser_EntityChangemail $oChangeMail    Объект смены емайла
     *
     * @return bool|ModuleUser_EntityChangemail
     */
    public function addUserChangeMail($oChangeMail)
    {
        if ($sId = $this->oMapper->addUserChangeMail($oChangeMail)) {
            $oChangeMail->setId($sId);
            return $oChangeMail;
        }
        return false;
    }

    /**
     * Обновляет запись о смене емайла
     *
     * @param ModuleUser_EntityChangemail $oChangeMail    Объект смены емайла
     *
     * @return int
     */
    public function updateUserChangeMail($oChangeMail)
    {
        return $this->oMapper->updateUserChangeMail($oChangeMail);
    }

    /**
     * Возвращает объект смены емайла по коду подтверждения
     *
     * @param string $sCode Код подтверждения
     *
     * @return ModuleUser_EntityChangemail|null
     */
    public function getUserChangeMailByCodeFrom($sCode)
    {
        return $this->oMapper->getUserChangeMailByCodeFrom($sCode);
    }

    /**
     * Возвращает объект смены емайла по коду подтверждения
     *
     * @param string $sCode Код подтверждения
     *
     * @return ModuleUser_EntityChangemail|null
     */
    public function getUserChangemailByCodeTo($sCode) {

        return $this->oMapper->getUserChangeMailByCodeTo($sCode);
    }

    /**
     * Формирование процесса смены емайла в профиле пользователя
     *
     * @param ModuleUser_EntityUser $oUser       Объект пользователя
     * @param string                $sMailNew    Новый емайл
     *
     * @return bool|ModuleUser_EntityChangemail
     */
    public function MakeUserChangemail($oUser, $sMailNew) {

        /** @var ModuleUser_EntityChangemail $oChangeMail */
        $oChangeMail = \E::getEntity('ModuleUser_EntityChangemail');
        $oChangeMail->setUserId($oUser->getId());
        $oChangeMail->setDateAdd(date('Y-m-d H:i:s'));
        $oChangeMail->setDateExpired(date('Y-m-d H:i:s', time() + 3 * 24 * 60 * 60)); // 3 дня для смены емайла
        $oChangeMail->setMailFrom($oUser->getMail() ?: '');
        $oChangeMail->setMailTo($sMailNew);
        $oChangeMail->setCodeFrom(\F::RandomStr(32));
        $oChangeMail->setCodeTo(\F::RandomStr(32));
        if ($this->addUserChangeMail($oChangeMail)) {
            // * Если у пользователя раньше не было емайла, то сразу шлем подтверждение на новый емайл
            if (!$oChangeMail->getMailFrom()) {
                $oChangeMail->setConfirmFrom(1);
                \E::Module('User')->updateUserChangeMail($oChangeMail);

                // * Отправляем уведомление на новый емайл
                \E::Module('Notify')->send(
                    $oChangeMail->getMailTo(),
                    'user_changemail_to.tpl',
                    \E::Module('Lang')->get('notify_subject_user_changemail'),
                    [
                         'oUser'       => $oUser,
                         'oChangemail' => $oChangeMail,
                    ],
                    null,
                    true
                );

            } else {
                // * Отправляем уведомление на старый емайл
                \E::Module('Notify')->send(
                    $oUser,
                    'user_changemail_from.tpl',
                    \E::Module('Lang')->get('notify_subject_user_changemail'),
                    [
                         'oUser'       => $oUser,
                         'oChangemail' => $oChangeMail,
                    ],
                    null,
                    true
                );
            }
            return $oChangeMail;
        }
        return false;
    }

    /**
     * @return int
     */
    public function getCountUsers()
    {
        return $this->getCountByRole(self::USER_ROLE_USER);
    }

    /**
     * @return int
     */
    public function getCountModerators()
    {
        return $this->getCountByRole(self::USER_ROLE_MODERATOR);
    }

    /**
     * @return int
     */
    public function getCountAdmins()
    {
        return $this->getCountByRole(self::USER_ROLE_ADMINISTRATOR);
    }

    /**
     * Возвращает количество пользователей по роли
     *
     * @param $iRole
     *
     * @return int
     */
    public function getCountByRole($iRole)
    {
        return $this->oMapper->getCountByRole($iRole);
    }

    /**
     * Удаление пользователей
     *
     * @param $aUsersId
     */
    public function deleteUsers($aUsersId)
    {
        if (!is_array($aUsersId)) {
            $aUsersId = [(int)$aUsersId];
        }
        \E::Module('Blog')->deleteBlogsByUsers($aUsersId);
        \E::Module('Topic')->deleteTopicsByUsersId($aUsersId);

        if ($bResult = $this->oMapper->deleteUser($aUsersId)) {
            $this->deleteUserFieldValues($aUsersId, $aType = null);
            $aUsers = $this->getUsersByArrayId($aUsersId);
            foreach ($aUsers as $oUser) {
                $this->deleteAvatar($oUser);
                $this->deletePhoto($oUser);
            }
        }
        foreach ($aUsersId as $nUserId) {
            \E::Module('Cache')->cleanByTags(["topic_update_user_{$nUserId}"]);
            \E::Module('Cache')->delete("user_{$nUserId}");
        }
        return $bResult;
    }

    /**
     * issue 258 {@link https://github.com/altocms/altocms/issues/258}
     * Проверяет, не забанен ли этот адрес
     *
     * @param string $sIp Ip Адрес
     *
     * @return mixed
     */
    public function ipIsBanned($sIp)
    {
        return $this->oMapper->ipIsBanned($sIp);
    }

    /**
     * @param int|string $xSize
     * @param string     $sSex
     *
     * @return string
     */
    public function getDefaultAvatarUrl($xSize, $sSex)
    {
        if ($sSex === null) {
            $sSex = 'male';
        }

        $sPath = \E::Module('Uploader')->getUserAvatarDir(0)
            . 'avatar_' . \C::get('view.skin', \C::LEVEL_CUSTOM) . '_'
            . $sSex . '.png';

        if ($sRealSize = C::get('module.uploader.images.profile_avatar.size.' . $xSize)) {
            $xSize = $sRealSize;
        }
        if (is_string($xSize) && strpos($xSize, 'x')) {
            list($nW, $nH) = array_map('intval', explode('x', $xSize));
        } else {
            $nW = $nH = (int)$xSize;
        }

        $sResizePath = $sPath . '-' . $nW . 'x' . $nH . '.' . pathinfo($sPath, PATHINFO_EXTENSION);
        if (\C::get('module.image.autoresize') && !F::File_Exists($sResizePath)) {
            $sResizePath = \E::Module('Img')->autoResizeSkinImage($sResizePath, 'avatar', max($nH, $nW));
        }
        if ($sResizePath) {
            $sPath = $sResizePath;
        } elseif (!F::File_Exists($sPath)) {
            $sPath = \E::Module('Img')->autoResizeSkinImage($sPath, 'avatar', null);
        }

        return \E::Module('Uploader')->dir2Url($sPath);
    }

    /**
     * @param int|string $xSize
     * @param string     $sSex
     *
     * @return string
     */
    public function getDefaultPhotoUrl($xSize, $sSex)
    {
        $sPath = \E::Module('Uploader')->getUserAvatarDir(0)
            . 'user_photo_' . \C::get('view.skin', \C::LEVEL_CUSTOM) . '_'
            . $sSex . '.png';

        if (strpos($xSize, 'x') !== false) {
            list($nW, $nH) = array_map('intval', explode('x', $xSize));
        } else {
            $nW = $nH = (int)$xSize;
        }
        $sPath .= '-' . $nW . 'x' . $nH . '.' . pathinfo($sPath, PATHINFO_EXTENSION);

        if (\C::get('module.image.autoresize') && !F::File_Exists($sPath)) {
            $sPath = \E::Module('Img')->autoResizeSkinImage($sPath, 'user_photo', max($nH, $nW));
        }

        return \E::Module('Uploader')->dir2Url($sPath);
    }

    /**
     * Returns stats of user publications and favourites
     *
     * @param int|object $xUser
     *
     * @return int[]
     */
    public function getUserProfileStats($xUser)
    {
        if (is_object($xUser)) {
            $iUserId = $xUser->getId();
        } else {
            $iUserId = (int)$xUser;
        }

        $iCountTopicFavourite = \E::Module('Topic')->getCountTopicsFavouriteByUserId($iUserId);
        $iCountCommentFavourite = \E::Module('Comment')->getCountCommentsFavouriteByUserId($iUserId);
        $iCountTopics = \E::Module('Topic')->getCountTopicsPersonalByUser($iUserId, 1);
        $iCountComments = \E::Module('Comment')->getCountCommentsByUserId($iUserId, 'topic');
        $iCountWallRecords = \E::Module('Wall')->getCountWall(['wall_user_id' => $iUserId, 'pid' => null]);
        $iImageCount = \E::Module('Media')->getCountImagesByUserId($iUserId);

        $iCountUserNotes = $this->getCountUserNotesByUserId($iUserId);
        $iCountUserFriends = $this->getCountUsersFriend($iUserId);

        $aUserPublicationStats = [
            'favourite_topics'      => $iCountTopicFavourite,
            'favourite_comments'    => $iCountCommentFavourite,
            'count_topics'          => $iCountTopics,
            'count_comments'        => $iCountComments,
            'count_usernotes'       => $iCountUserNotes,
            'count_wallrecords'     => $iCountWallRecords,
            'count_images'          => $iImageCount,
            'count_friends'         => $iCountUserFriends,
        ];
        $aUserPublicationStats['count_created'] =
            $aUserPublicationStats['count_topics']
            + $aUserPublicationStats['count_comments']
            + $aUserPublicationStats['count_images'];

        if ($iUserId == \E::userId()) {
            $aUserPublicationStats['count_created'] += $aUserPublicationStats['count_usernotes'];
        }

        $aUserPublicationStats['count_favourites'] =
            $aUserPublicationStats['favourite_topics']
            + $aUserPublicationStats['favourite_comments'];

        return $aUserPublicationStats;
    }

    /**
     * @param int $nError
     *
     * @return string
     */
    public function getLoginErrorMessage($nError)
    {
        switch ((int)$nError) {
            case self::USER_LOGIN_ERR_MIN:
                $sResult = \E::Module('Lang')->get('registration_login_error_min', [
                    'min' => (int)\C::get('module.user.login.min_size'),
                ]);
                break;
            case self::USER_LOGIN_ERR_LEN:
                $sResult = \E::Module('Lang')->get('registration_login_error_len', [
                    'min' => (int)\C::get('module.user.login.min_size'),
                    'max' => (int)\C::get('module.user.login.max_size'),
                ]);
                break;
            case self::USER_LOGIN_ERR_CHARS:
                $sResult = \E::Module('Lang')->get('registration_login_error_chars');
                break;
            case self::USER_LOGIN_ERR_DISABLED:
                $sResult = \E::Module('Lang')->get('registration_login_error_used');
                break;
            default:
                $sResult = \E::Module('Lang')->get('registration_login_error');
                break;
        }
        return $sResult;
    }

}

// EOF
