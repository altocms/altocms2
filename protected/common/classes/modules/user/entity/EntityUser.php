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
 * Сущность пользователя
 *
 * @package modules.user
 */
class ModuleUser_EntityUser extends Entity
{
    const DEFAULT_AVATAR_SIZE = 100;
    const DEFAULT_PHOTO_SIZE = 250;

    /**
     * Определяем правила валидации
     * Правила валидации нужно определять только здесь!
     *
     * @var array
     */
    public function init()
    {
        parent::init();
        $this->aValidateRules[] = [
            'login',
            'login',
            'on' => ['registration', ''], // '' - означает дефолтный сценарий
        ];
        $this->aValidateRules[] = [
            'login',
            'login_exists',
            'on' => ['registration'],
        ];
        $this->aValidateRules[] = [
            'mail',
            'email',
            'allowEmpty' => false,
            'on'         => ['registration', ''],
        ];
        $this->aValidateRules[] = [
            'mail',
            'mail_exists',
            'on' => ['registration'],
        ];
        $this->aValidateRules[] = [
            'password',
            'password',
            'on'         => ['registration'],
        ];
        $this->aValidateRules[] = [
            'password_confirm',
            'compare',
            'compareField' => 'password',
            'on'           => ['registration'],
        ];

        // Определяем дополнительные правила валидации
        if (\C::get('module.user.captcha_use_registration')) {
            $this->aValidateRules[] = ['captcha', 'captcha', 'on' => ['registration']];
        }
    }

    /**
     * Определяем дополнительные правила валидации
     *
     * @param   array|null $aParam
     */
    public function __construct($aParam = null)
    {
        parent::__construct($aParam);
    }

    /**
     * Типы ресурсов, загружаемые в профайле пользователя
     *
     * @return array
     */
    protected function _getDefaultMediaTypes()
    {
        return ['profile_avatar', 'profile_photo'];
    }

    /**
     * Валидация пользователя
     *
     * @param string $sValue     Валидируемое значение
     * @param array  $aParams    Параметры
     *
     * @return bool|string
     */
    public function validateLogin($sValue, $aParams)
    {
        $xResult = true;
        if ($sValue) {
            $nError = \E::Module('User')->invalidLogin($sValue);
            if (!$nError) {
                return $xResult;
            }

            if ($nError === ModuleUser::USER_LOGIN_ERR_MIN) {
                $xResult = \E::Module('Lang')->get('registration_login_error_min', [
                    'min' => (int)\C::get('module.user.login.min_size'),
                ]);
            } elseif ($nError === ModuleUser::USER_LOGIN_ERR_LEN) {
                $xResult = \E::Module('Lang')->get('registration_login_error_len', [
                    'min' => (int)\C::get('module.user.login.min_size'),
                    'max' => (int)\C::get('module.user.login.max_size'),
                ]);
            } elseif ($nError === ModuleUser::USER_LOGIN_ERR_CHARS) {
                $xResult = \E::Module('Lang')->get('registration_login_error_chars');
            } elseif ($nError === ModuleUser::USER_LOGIN_ERR_DISABLED) {
                $xResult = \E::Module('Lang')->get('registration_login_error_used');
            } else {
                $xResult = \E::Module('Lang')->get('registration_login_error');
            }
        } else {
            $xResult = \E::Module('Lang')->get('registration_login_error');
        }
        return $xResult;
    }

    /**
     * Проверка логина на существование
     *
     * @param string $sValue     Валидируемое значение
     * @param array  $aParams    Параметры
     *
     * @return bool
     */
    public function validateLoginExists($sValue, $aParams)
    {
        if (!\E::Module('User')->getUserByLogin($sValue)) {
            return true;
        }
        return \E::Module('Lang')->get('registration_login_error_used');
    }

    /**
     * Проверка емайла на существование
     *
     * @param string $sValue     Валидируемое значение
     * @param array  $aParams    Параметры
     *
     * @return bool
     */
    public function validateMailExists($sValue, $aParams)
    {
        if (!\E::Module('User')->getUserByMail($sValue)) {
            return true;
        }
        return \E::Module('Lang')->get('registration_mail_error_used');
    }

    /**
     * @param $sValue
     * @param $aParams
     *
     * @return bool|string
     */
    public function validatePassword($sValue, $aParams)
    {
        $iMinLength = Config::val('module.security.password_len', 3);
        if ($sValue && $this->getLogin() && $sValue === $this->getLogin()) {
            return \E::Module('Lang')->get('registration_password_error', ['min' => $iMinLength]);
        }
        if (mb_strlen($sValue, 'UTF-8') < $iMinLength) {
            return \E::Module('Lang')->get('registration_password_error', ['min' => $iMinLength]);
        }
        return true;
    }

    /**
     * Возвращает ID пользователя
     *
     * @return int
     */
    public function getId()
    {
        $iUserId = $this->getProp('user_id');
        return $iUserId ? (int)$iUserId : null;
    }

    /**
     * Возвращает логин
     *
     * @return string|null
     */
    public function getLogin()
    {
        return $this->getProp('user_login');
    }

    /**
     * Возвращает пароль (ввиде хеша)
     *
     * @return string|null
     */
    public function getPassword()
    {
        return $this->getProp('user_password');
    }

    /**
     * Возвращает емайл
     *
     * @return string|null
     */
    public function getMail()
    {
        return $this->getProp('user_mail');
    }

    /**
     * Возвращает силу
     *
     * @return string
     */
    public function getSkill()
    {
        return number_format(round($this->getProp('user_skill'), 3), 3, '.', '');
    }

    /**
     * Возвращает дату регистрации
     *
     * @return string|null
     */
    public function getDateRegister()
    {
        return $this->getProp('user_date_register');
    }

    /**
     * Возвращает дату активации
     *
     * @return string|null
     */
    public function getDateActivate()
    {
        return $this->getProp('user_date_activate');
    }

    /**
     * Возвращает дату последнего комментирования
     *
     * @return mixed|null
     */
    public function getDateCommentLast()
    {
        return $this->getProp('user_date_comment_last');
    }

    /**
     * Возвращает IP регистрации
     *
     * @return string|null
     */
    public function getIpRegister()
    {
        return $this->getProp('user_ip_register');
    }

    /**
     * Возвращает рейтинг
     *
     * @return string
     */
    public function getRating()
    {
        return number_format(round($this->getProp('user_rating'), 2), 2, '.', '');
    }

    /**
     * Вовзращает количество проголосовавших
     *
     * @return int|null
     */
    public function getCountVote()
    {
        return $this->getProp('user_count_vote');
    }

    /**
     * Возвращает статус активированности
     *
     * @return int|null
     */
    public function getActivate()
    {
        return (bool)$this->getProp('user_date_activate');
    }

    /**
     * Return activation key
     *
     * @return string|null
     */
    public function getActivationKey()
    {
        return $this->getProp('user_activation_key');
    }

    /**
     * Возвращает имя
     *
     * @return string|null
     */
    public function getProfileName()
    {
        return $this->getProp('user_profile_name');
    }

    /**
     * Возвращает пол
     *
     * @return string|null
     */
    public function getProfileSex()
    {
        $sSex = $this->getProp('user_profile_sex');
        return $sSex ?: 'other';
    }

    /**
     * Возвращает название страны
     *
     * @return string|null
     */
    public function getProfileCountry()
    {
        return $this->getProp('user_profile_country');
    }

    /**
     * Возвращает название региона
     *
     * @return string|null
     */
    public function getProfileRegion()
    {
        return $this->getProp('user_profile_region');
    }

    /**
     * Возвращает название города
     *
     * @return string|null
     */
    public function getProfileCity()
    {
        return $this->getProp('user_profile_city');
    }

    /**
     * Возвращает дату рождения
     *
     * @return string|null
     */
    public function getProfileBirthday()
    {
        return $this->getProp('user_profile_birthday');
    }

    /**
     * Возвращает информацию о себе
     *
     * @return string|null
     */
    public function getProfileAbout()
    {
        return $this->getProp('user_profile_about');
    }

    /**
     * Возвращает дату редактирования профиля
     *
     * @return string|null
     */
    public function getProfileDate()
    {
        return $this->getProp('user_profile_date');
    }

    /**
     * Возвращает полный веб путь до аватра
     *
     * @return string|null
     */
    public function getProfileAvatar() {

        return $this->getProp('user_profile_avatar');
    }

    /**
     * Возвращает расширение автара
     *
     * @return string|null
     */
    public function getProfileAvatarType() {

        return ($sPath = $this->getAvatarUrl()) ? pathinfo($sPath, PATHINFO_EXTENSION) : null;
    }

    /**
     * Returns display name according with pattern from configuration
     * If pattern is missed or if result string is empty then returns login
     *
     * @return string
     */
    public function getDisplayName() {

        $sDisplayName = $this->getProp('_display_name');
        if (!$sDisplayName) {
            $sDisplayName = \C::get('module.user.display_name');
            if (!$sDisplayName) {
                $sDisplayName = $this->getLogin();
            } else {
                $sDisplayName = str_replace(array('%%login%%', '%%profilename%%'), array($this->getLogin(), $this->getProfileName()), $sDisplayName);
                if (!$sDisplayName) {
                    $sDisplayName = $this->getLogin();
                }
            }
            $this->setProp('_display_name', $sDisplayName);
        }
        return $sDisplayName;
    }

    /**
     * Возвращает статус уведомления о новых топиках
     *
     * @return int|null
     */
    public function getSettingsNoticeNewTopic() {

        return $this->getProp('user_settings_notice_new_topic');
    }

    /**
     * Возвращает статус уведомления о новых комментариях
     *
     * @return int|null
     */
    public function getSettingsNoticeNewComment() {

        return $this->getProp('user_settings_notice_new_comment');
    }

    /**
     * Возвращает статус уведомления о новых письмах
     *
     * @return int|null
     */
    public function getSettingsNoticeNewTalk() {

        return $this->getProp('user_settings_notice_new_talk');
    }

    /**
     * Возвращает статус уведомления о новых ответах в комментариях
     *
     * @return int|null
     */
    public function getSettingsNoticeReplyComment() {

        return $this->getProp('user_settings_notice_reply_comment');
    }

    /**
     * Возвращает статус уведомления о новых друзьях
     *
     * @return int|null
     */
    public function getSettingsNoticeNewFriend() {

        return $this->getProp('user_settings_notice_new_friend');
    }

    /**
     * @return mixed|null
     */
    public function getLastSession() {

        return $this->getProp('user_last_session');
    }

    /**
     * Возвращает значения пользовательских полей
     *
     * @param bool         $bNotEmptyOnly Возвращать или нет только не пустые
     * @param string|array $xType         Тип полей
     *
     * @return ModuleUser_EntityField[]
     */
    public function getUserFieldValues($bNotEmptyOnly = true, $xType = [])
    {
        $aUserFields = $this->getProp('_user_fields');
        if (null === $aUserFields) {
            $aUserFields = \E::Module('User')->getUserFieldsValues($this->getId(), false);
            $this->setProp('_user_fields', $aUserFields);
        }
        $aResult = [];
        if (!is_array($xType)) {
            $aType = [$xType];
        } else {
            $aType = $xType;
        }
        if ($aUserFields) {
            foreach($aUserFields as $iIndex => $oUserField) {
                if (!$bNotEmptyOnly || $oUserField->getValue()) {
                    if (empty($aType) || in_array($oUserField->getType(), $aType, true)) {
                        $aResult[$iIndex] = $oUserField;
                    }
                }
            }
        }
        return $aResult;
    }

    /**
     * Возвращает объект сессии
     *
     * @return ModuleUser_EntitySession|null
     */
    public function getSession()
    {
        if (!$this->getProp('session')) {
            $this->aProps['session'] = \E::Module('User')->getSessionByUserId($this->getId());
        }
        return $this->getProp('session');
    }

    /**
     * Returns current session of user
     *
     * @return ModuleUser_EntitySession|null
     */
    public function getCurrentSession()
    {
        if (!$this->getProp('_current_session')) {
            $this->aProps['_current_session'] = \E::Module('User')->getSessionByUserId($this->getId(), \E::Module('Session')->getKey());
        }
        return $this->getProp('_current_session');
    }

    /**
     * Устанавливает роль пользователя
     *
     * @param $data
     *
     * @return $this
     */
    public function setRole($data)
    {
        return $this->setProp('user_role', $data);
    }

    /**
     * Возвращает роль пользователя
     *
     * @return int|null
     */
    public function getRole()
    {
        return (int)$this->getProp('user_role');
    }

    /**
     * Checks role of user
     *
     * @param int $iRole
     *
     * @return bool
     */
    public function hasRole($iRole)
    {
        return (bool)$this->getPropMask('user_role', $iRole);
    }


    /**
     * Return online status of user
     *
     * @return bool
     */
    public function isOnline()
    {
        if ($oSession = $this->getSession()) {
            if ($oSession->getDateExit()) {
                // User has logout
                return false;
            }
            if ($iTime = \C::get('module.user.online_time')) {
                if (time() - strtotime($oSession->getDateLast()) < $iTime) {
                    // Last session time less then $iTime seconds ago
                    return true;
                }
            } else {
                return false;
            }
        }
        return false;
    }


    /**
     * @param string $sType
     * @param string $xSize
     *
     * @return string
     */
    protected function _getProfileImageUrl($sType, $xSize = null) {

        $sPropKey = '_profile_imge_url_' . $sType . '-' . $xSize;
        $sUrl = $this->getProp($sPropKey);
        if ($sUrl === null) {
            $sUrl = '';
            $aImages = $this->getMediaResources($sType);
            if (!empty($aImages)) {
                /** @var ModuleMedia_EntityMediaRel $oImage */
                $oImage = reset($aImages);
                $sUrl = $oImage->getImageUrl($xSize);
            }
            $this->setProp($sPropKey, $sUrl);
        }
        return $sUrl;
    }

    /**
     * Возвращает полный URL до аватары нужного размера
     *
     * @param int|string $xSize - Размер (120 | '120x100')
     *
     * @return  string
     */
    public function getAvatarUrl($xSize = null) {

        // Gets default size from config or sets it to 100
        if (!$xSize) {
            $xSize = \C::get('module.user.profile_avatar_size');
            if (!$xSize) {
                $xSize = self::DEFAULT_AVATAR_SIZE;
            }
        }

        $sPropKey = '_avatar_url_' . $xSize;
        $sUrl = $this->getProp($sPropKey);
        if (null === $sUrl) {
            if ($sRealSize = C::get('module.uploader.images.profile_avatar.size.' . $xSize)) {
                $xSize = $sRealSize;
            }
            $sUrl = $this->_getProfileImageUrl('profile_avatar', $xSize);
            if (!$sUrl) {
                // Old version compatibility
                $sUrl = $this->getProfileAvatar();
                if ($sUrl) {
                    if ($xSize) {
                        $sUrl = \E::Module('Uploader')->ResizeTargetImage($sUrl, $xSize);
                    }
                } else {
                    $sUrl = $this->getDefaultAvatarUrl($xSize);
                }
            }
            $this->setProp($sPropKey, $sUrl);
        }
        return $sUrl;
    }

    /**
     * Возвращает дефолтный аватар пользователя
     *
     * @param int|string $xSize
     * @param string     $sSex
     *
     * @return string
     */
    public function getDefaultAvatarUrl($xSize = null, $sSex = null) {

        if (!$sSex) {
            $sSex = ($this->getProfileSex() === 'woman' ? 'female' : 'male');
        }
        if ($sSex !== 'female' && $sSex !== 'male') {
            $sSex = 'male';
        }

        $sPath = \E::Module('Uploader')->getUserAvatarDir(0)
            . 'avatar_' . \C::get('view.skin', Config::LEVEL_CUSTOM) . '_'
            . $sSex . '.png';

        if (!$xSize) {
            if (\C::get('module.user.profile_avatar_size')) {
                $xSize = \C::get('module.user.profile_avatar_size');
            } else {
                $xSize = self::DEFAULT_AVATAR_SIZE;
            }
        }

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
     * Возвращает информацию о том, есть ли вообще у пользователя аватар
     *
     * @return bool
     */
    public function hasAvatar() {

        $aImages = $this->getMediaResources('profile_avatar');
        return !empty($aImages);
    }

    /**
     * Возвращает полный URL до фото профиля
     *
     * @param int|string $xSize - рвзмер (240 | '240x320')
     *
     * @return string
     */
    public function getPhotoUrl($xSize = null)
    {
        $sPropKey = '_photo_url_' . $xSize;
        $sUrl = $this->getProp($sPropKey);
        if (null === $sUrl) {
            if (!$xSize) {
                if (\C::get('module.user.profile_photo_size')) {
                    $xSize = \C::get('module.user.profile_photo_size');
                } else {
                    $xSize = self::DEFAULT_PHOTO_SIZE;
                }
            }
            if ($sRealSize = C::get('module.uploader.images.profile_photo.size.' . $xSize)) {
                $xSize = $sRealSize;
            }

            $sUrl = $this->_getProfileImageUrl('profile_photo', $xSize);
            if (!$sUrl) {
                // Old version compatibility
                $sUrl = $this->getProfilePhoto();
                if ($sUrl) {
                    if ($xSize) {
                        $sUrl = \E::Module('Uploader')->ResizeTargetImage($sUrl, $xSize);
                    }
                } else {
                    $sUrl = $this->GetDefaultPhotoUrl($xSize);
                }
            }
            $this->setProp($sPropKey, $sUrl);
        }
        return $sUrl;
    }

    /**
     * Возвращает информацию о том, есть ли вообще у пользователя аватар
     *
     * @return bool
     */
    public function hasPhoto()
    {
        $aImages = $this->getMediaResources('profile_photo');

        return !empty($aImages);
    }

    /**
     * Returns URL for default photo of current skin
     *
     * @param int|string $xSize
     * @param string     $sSex
     *
     * @return string
     */
    public function getDefaultPhotoUrl($xSize = null, $sSex = null)
    {
        if (!$sSex) {
            $sSex = ($this->getProfileSex() === 'woman' ? 'female' : 'male');
        }
        if (!$xSize) {
            $xSize = self::DEFAULT_PHOTO_SIZE;
        }
        if (is_numeric($xSize)) {
            $xSize = $xSize . 'x' . $xSize;
        }
        $sResult = \E::Module('User')->getDefaultPhotoUrl($xSize, $sSex);

        return $sResult;
    }

    /**
     * Возвращает объект голосования за пользователя текущего пользователя
     *
     * @return ModuleVote_EntityVote|null
     */
    public function getVote()
    {
        return $this->getProp('vote');
    }

    /**
     * Возвращает статус дружбы
     *
     * @return bool|null
     */
    public function getUserIsFriend()
    {
        return $this->getProp('user_is_friend');
    }

    /**
     * Возвращает статус администратора сайта
     *
     * @return bool
     */
    public function isAdministrator()
    {
        return $this->hasRole(ModuleUser::USER_ROLE_ADMINISTRATOR);
    }

    /**
     * Возвращает статус модкратора сайта
     *
     * @return bool
     */
    public function isModerator()
    {
        return $this->hasRole(ModuleUser::USER_ROLE_MODERATOR);
    }

    /**
     * @return bool
     */
    public function isActivated()
    {
        return (bool)$this->getProp('user_date_activate');
    }

    /**
     * @return string
     */
    public function getUserUrl() {

        return $this->getProfileUrl();
    }

    /**
     * Возвращает URL до профиля пользователя
     *
     * @param   string|null $sUrlMask - еcли передан параметр, то формирует URL по этой маске
     * @param   bool        $bFullUrl - возвращать полный путь (или относительный, если false)
     *
     * @return string
     */
    public function getProfileUrl($sUrlMask = null, $bFullUrl = true) {

        $sKey = '-url-' . ($sUrlMask ?: '') . ($bFullUrl ? '-1' : '-0');
        $sUrl = $this->getProp($sKey);
        if (null !== $sUrl) {
            return $sUrl;
        }

        if (!$sUrlMask) {
            $sUrlMask = R::getUserUrlMask();
        }
        if (!$sUrlMask) {
            // формирование URL по умолчанию
            $sUrl = R::getLink('profile/' . $this->getLogin());
            $this->setProp($sKey, $sUrl);
            return $sUrl;
        }
        $aReplace = array(
            '%user_id%' => $this->getId(),
            '%login%'   => $this->getLogin(),
        );
        $sUrl = strtr($sUrlMask, $aReplace);
        if (strpos($sUrl, '/')) {
            list($sAction, $sPath) = explode('/', $sUrl, 2);
            $sUrl = R::getLink($sAction) . $sPath;
        } else {
            $sUrl = F::File_RootUrl() . $sUrl;
        }
        if (substr($sUrl, -1) !== '/') {
            $sUrl .= '/';
        }
        $this->setProp($sKey, $sUrl);

        return $sUrl;
    }

    /**
     * Возвращает объект дружбы с текущим пользователем
     *
     * @return ModuleUser_EntityFriend|null
     */
    public function getUserFriend() {

        return $this->getProp('user_friend');
    }

    /**
     * Проверяет подписан ли текущий пользователь на этого
     *
     * @return bool
     */
    public function isFollow() {

        if ($oUserCurrent = \E::User()) {
            return \E::Module('Stream')->isSubscribe($oUserCurrent->getId(), $this->getId());
        }
        return false;
    }

    /**
     * Возвращает объект заметки о подльзователе, которую оставил текущий пользователй
     *
     * @return ModuleUser_EntityNote|null
     */
    public function getUserNote() {

        $oUserCurrent = \E::User();
        if ($oUserCurrent && $this->getProp('user_note') === null) {
            $this->aProps['user_note'] = \E::Module('User')->getUserNote($this->getId(), $oUserCurrent->getId());
        }
        return $this->getProp('user_note');
    }

    /**
     * Возвращает личный блог пользователя
     *
     * @return ModuleBlog_EntityBlog|null
     */
    public function getBlog() {

        if (!$this->getProp('blog')) {
            $this->aProps['blog'] = \E::Module('Blog')->getPersonalBlogByUserId($this->getId());
        }
        return $this->getProp('blog');
    }

    public function getBanLine() {

        return $this->getProp('banline');
    }

    public function isBannedUnlim() {

        return ((bool)$this->getProp('banunlim'));
    }

    public function getBanComment() {

        return $this->getProp('bancomment');
    }

    /**
     * @return bool
     */
    public function isBannedByLogin()
    {
        $sBanLine = $this->getBanLine();
        return ($this->isBannedUnlim()
            || ($sBanLine && ($sBanLine > date('Y-m-d H:i:s')) && $this->getProp('banactive')));
    }

    /**
     * @return bool
     */
    public function isBannedByIp() {

        // return ($this->GetProp('ban_ip'));

        // issue 258 {@link https://github.com/altocms/altocms/issues/258}
        $bResult = $this->getProp('ban_ip');
        if (null === $bResult) {
            $bResult = (bool)E::Module('User')->ipIsBanned(\F::GetUserIp());
            $this->setProp('ban_ip', $bResult);
        }
        return $bResult;
    }

    /**
     * @return bool
     */
    public function isBanned()
    {
        return ($this->isBannedByLogin() || $this->isBannedByIp());
    }


    /**
     * Устанавливает ID пользователя
     *
     * @param int $data
     *
     * @return $this
     */
    public function setId($data) {

        return $this->setProp('user_id', $data);
    }

    /**
     * Устанавливает логин
     *
     * @param string $data
     *
     * @return $this
     */
    public function setLogin($data) {

        return $this->setProp('user_login', trim($data));
    }

    /**
     * Устанавливает пароль
     *
     * @param   string $sPassword
     * @param   bool   $bEncrypt   - false, если пароль уже захеширован
     *
     * @return $this
     */
    public function setPassword($sPassword, $bEncrypt = false)
    {
        if ($bEncrypt) {
            $sPassword = \E::Module('Security')->getPasswordHash($sPassword);
        }
        return $this->setProp('user_password', $sPassword);
    }

    /**
     * Устанавливает емайл
     *
     * @param string $data
     *
     * @return $this
     */
    public function setMail($data)
    {
        return $this->setProp('user_mail', trim($data));
    }

    /**
     * Устанавливает силу
     *
     * @param float $data
     *
     * @return $this
     */
    public function setSkill($data) {

        return $this->setProp('user_skill', $data);
    }

    /**
     * Устанавливает дату регистрации
     *
     * @param string $data
     *
     * @return $this
     */
    public function setDateRegister($data) {

        return $this->setProp('user_date_register', $data);
    }

    /**
     * Устанавливает дату активации
     *
     * @param string $data
     *
     * @return $this
     */
    public function setDateActivate($data) {

        return $this->setProp('user_date_activate', $data);
    }

    /**
     * Устанавливает дату последнего комментирования
     *
     * @param string $data
     *
     * @return $this
     */
    public function setDateCommentLast($data) {

        return $this->setProp('user_date_comment_last', $data);
    }

    /**
     * Устанавливает IP регистрации
     *
     * @param string $data
     *
     * @return $this
     */
    public function setIpRegister($data) {

        return $this->setProp('user_ip_register', $data);
    }

    /**
     * Устанавливает рейтинг
     *
     * @param float $data
     *
     * @return $this
     */
    public function setRating($data) {

        return $this->setProp('user_rating', $data);
    }

    /**
     * Устанавливает количество проголосовавших
     *
     * @param int $data
     *
     * @return $this
     */
    public function setCountVote($data) {

        return $this->setProp('user_count_vote', $data);
    }

    /**
     * Set activation key
     *
     * @param string $data
     *
     * @return $this
     */
    public function setActivationKey($data)
    {
        return $this->setProp('user_activation_key', $data);
    }

    /**
     * Устанавливает имя
     *
     * @param string $data
     *
     * @return $this
     */
    public function setProfileName($data) {

        return $this->setProp('user_profile_name', $data);
    }

    /**
     * Устанавливает пол
     *
     * @param string $data
     *
     * @return $this
     */
    public function setProfileSex($data) {

        return $this->setProp('user_profile_sex', $data);
    }

    /**
     * Устанавливает название страны
     *
     * @param string $data
     *
     * @return $this
     */
    public function setProfileCountry($data) {

        return $this->setProp('user_profile_country', $data);
    }

    /**
     * Устанавливает название региона
     *
     * @param string $data
     *
     * @return $this
     */
    public function setProfileRegion($data) {

        return $this->setProp('user_profile_region', $data);
    }

    /**
     * Устанавливает название города
     *
     * @param string $data
     *
     * @return $this
     */
    public function setProfileCity($data) {

        return $this->setProp('user_profile_city', $data);
    }

    /**
     * Устанавливает дату рождения
     *
     * @param string $data
     *
     * @return $this
     */
    public function setProfileBirthday($data) {

        return $this->setProp('user_profile_birthday', $data);
    }

    /**
     * Устанавливает информацию о себе
     *
     * @param string $data
     *
     * @return $this
     */
    public function setProfileAbout($data) {

        return $this->setProp('user_profile_about', $data);
    }

    /**
     * Устанавливает дату редактирования профиля
     *
     * @param string $data
     *
     * @return $this
     */
    public function setProfileDate($data) {

        return $this->setProp('user_profile_date', $data);
    }

    /**
     * Устанавливает полный веб путь до аватра
     *
     * @param string $data
     *
     * @return $this
     */
    public function setProfileAvatar($data) {

        return $this->setProp('user_profile_avatar', $data);
    }

    /**
     * Устанавливает полный веб путь до фото
     *
     * @param string $data
     *
     * @return $this
     */
    public function setProfilePhoto($data) {

        return $this->setProp('user_profile_foto', $data);
    }

    /**
     * Устанавливает статус уведомления о новых топиках
     *
     * @param int $data
     *
     * @return $this
     */
    public function setSettingsNoticeNewTopic($data) {

        return $this->setProp('user_settings_notice_new_topic', $data);
    }

    /**
     * Устанавливает статус уведомления о новых комментариях
     *
     * @param int $data
     *
     * @return $this
     */
    public function setSettingsNoticeNewComment($data) {

        return $this->setProp('user_settings_notice_new_comment', $data);
    }

    /**
     * Устанавливает статус уведомления о новых письмах
     *
     * @param int $data
     *
     * @return $this
     */
    public function setSettingsNoticeNewTalk($data) {

        return $this->setProp('user_settings_notice_new_talk', $data);
    }

    /**
     * Устанавливает статус уведомления о новых ответах в комментариях
     *
     * @param int $data
     *
     * @return $this
     */
    public function setSettingsNoticeReplyComment($data) {

        return $this->setProp('user_settings_notice_reply_comment', $data);
    }

    /**
     * Устанавливает статус уведомления о новых друзьях
     *
     * @param int $data
     *
     * @return $this
     */
    public function setSettingsNoticeNewFriend($data) {

        return $this->setProp('user_settings_notice_new_friend', $data);
    }

    /**
     * Устанавливает объект сессии
     *
     * @param ModuleUser_EntitySession $data
     *
     * @return $this
     */
    public function setSession($data)
    {
        return $this->setProp('session', $data);
    }

    /**
     * Устанавливает статус дружбы
     *
     * @param int $data
     *
     * @return $this
     */
    public function setUserIsFriend($data)
    {
        return $this->setProp('user_is_friend', $data);
    }

    /**
     * Устанавливает объект голосования за пользователя текущего пользователя
     *
     * @param ModuleVote_EntityVote $data
     *
     * @return $this
     */
    public function setVote($data)
    {
        return $this->setProp('vote', $data);
    }

    /**
     * Устанавливаем статус дружбы с текущим пользователем
     *
     * @param int $data
     *
     * @return $this
     */
    public function setUserFriend($data)
    {
        return $this->setProp('user_friend', $data);
    }

    /**
     * @param $data
     *
     * @return $this
     */
    public function setLastSession($data)
    {
        return $this->setProp('user_last_session', $data);
    }

}

// EOF