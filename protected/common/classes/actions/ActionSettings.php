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
 * Экшен обработки настроек профиля юзера (/settings/)
 *
 * @package actions
 * @since   1.0
 */
class ActionSettings extends Action {

    const PREVIEW_RESIZE = 250;

    /**
     * Какое меню активно
     *
     * @var string
     */
    protected $sMenuItemSelect = 'settings';
    /**
     * Какое подменю активно
     *
     * @var string
     */
    protected $sMenuSubItemSelect = 'profile';
    /**
     * Текущий юзер
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserCurrent = null;

    /**
     * Инициализация
     *
     */
    public function init() {

        // * Проверяем авторизован ли юзер
        if (!\E::isAuth()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));
            return R::redirect('error');
        }

        // * Получаем текущего юзера
        $this->oUserCurrent = \E::User();
        $this->setDefaultEvent('profile');

        // * Устанавливаем title страницы
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('settings_menu'));
    }

    /**
     * Регистрация евентов
     */
    protected function registerEvent() {

        $this->addEvent('profile', 'eventProfile');
        $this->addEvent('invite', 'eventInvite');
        $this->addEvent('tuning', 'eventTuning');
        $this->addEvent('account', 'eventAccount');
    }


    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    /**
     * Дополнительные настройки сайта
     */
    public function eventTuning() {

        $this->sMenuItemSelect = 'settings';
        $this->sMenuSubItemSelect = 'tuning';

        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('settings_menu_tuning'));
        $aTimezoneList = array('-12', '-11', '-10', '-9.5', '-9', '-8', '-7', '-6', '-5', '-4.5', '-4', '-3.5', '-3',
                               '-2', '-1', '0', '1', '2', '3', '3.5', '4', '4.5', '5', '5.5', '5.75', '6', '6.5', '7',
                               '8', '8.75', '9', '9.5', '10', '10.5', '11', '11.5', '12', '12.75', '13', '14');
        \E::Module('Viewer')->assign('aTimezoneList', $aTimezoneList);
        /**
         * Если отправили форму с настройками - сохраняем
         */
        if (\F::isPost('submit_settings_tuning')) {
            \E::Module('Security')->validateSendForm();

            if (in_array(\F::getRequestStr('settings_general_timezone'), $aTimezoneList)) {
                $this->oUserCurrent->setSettingsTimezone(\F::getRequestStr('settings_general_timezone'));
            }

            $this->oUserCurrent->setSettingsNoticeNewTopic(\F::getRequest('settings_notice_new_topic') ? 1 : 0);
            $this->oUserCurrent->setSettingsNoticeNewComment(\F::getRequest('settings_notice_new_comment') ? 1 : 0);
            $this->oUserCurrent->setSettingsNoticeNewTalk(\F::getRequest('settings_notice_new_talk') ? 1 : 0);
            $this->oUserCurrent->setSettingsNoticeReplyComment(\F::getRequest('settings_notice_reply_comment') ? 1 : 0);
            $this->oUserCurrent->setSettingsNoticeNewFriend(\F::getRequest('settings_notice_new_friend') ? 1 : 0);
            $this->oUserCurrent->setProfileDate(\F::Now());

            // * Запускаем выполнение хуков
            \HookManager::run('settings_tuning_save_before', array('oUser' => $this->oUserCurrent));
            if (\E::Module('User')->Update($this->oUserCurrent)) {
                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('settings_tuning_submit_ok'));
                \HookManager::run('settings_tuning_save_after', array('oUser' => $this->oUserCurrent));
            } else {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));
            }
        } else {
            if (is_null($this->oUserCurrent->getSettingsTimezone())) {
                $_REQUEST['settings_general_timezone']
                    = (strtotime(date('Y-m-d H:i:s')) - strtotime(gmdate('Y-m-d H:i:s'))) / 3600 - date('I');
            } else {
                $_REQUEST['settings_general_timezone'] = $this->oUserCurrent->getSettingsTimezone();
            }
        }
    }

    /**
     * Показ и обработка формы приглаешний
     *
     */
    public function eventInvite() {
        /**
         * Только при активном режиме инвайтов
         */
        if (!Config::get('general.reg.invite')) {
            return parent::eventNotFound();
        }

        $this->sMenuItemSelect = 'invite';
        $this->sMenuSubItemSelect = '';
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('settings_menu_invite'));
        /**
         * Если отправили форму
         */
        if (isPost('submit_invite')) {
            \E::Module('Security')->validateSendForm();

            $bError = false;
            /**
             * Есть права на отправку инфайтов?
             */
            if (!\E::Module('ACL')->canSendInvite($this->oUserCurrent) && !$this->oUserCurrent->isAdministrator()) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('settings_invite_available_no'), \E::Module('Lang')->get('error'));
                $bError = true;
            }
            /**
             * Емайл корректен?
             */
            if (!F::CheckVal(\F::getRequestStr('invite_mail'), 'mail')) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('settings_invite_mail_error'), \E::Module('Lang')->get('error'));
                $bError = true;
            }
            /**
             * Запускаем выполнение хуков
             */
            \HookManager::run('settings_invate_send_before', array('oUser' => $this->oUserCurrent));
            /**
             * Если нет ошибок, то отправляем инвайт
             */
            if (!$bError) {
                $oInvite = \E::Module('User')->GenerateInvite($this->oUserCurrent);
                \E::Module('Notify')->sendInvite($this->oUserCurrent, F::getRequestStr('invite_mail'), $oInvite);
                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('settings_invite_submit_ok'));
                \HookManager::run('settings_invate_send_after', array('oUser' => $this->oUserCurrent));
            }
        }

        \E::Module('Viewer')->assign('iCountInviteAvailable',   \E::Module('User')->getCountInviteAvailable($this->oUserCurrent));
        \E::Module('Viewer')->assign('iCountInviteUsed',   \E::Module('User')->getCountInviteUsed($this->oUserCurrent->getId()));
    }

    /**
     * Форма смены пароля, емайла
     */
    public function eventAccount()
    {
        // * Устанавливаем title страницы
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('settings_menu_profile'));
        $this->sMenuSubItemSelect = 'account';
        // * Если нажали кнопку "Сохранить"
        if (\F::isPost('submit_account_edit')) {
            \E::Module('Security')->validateSendForm();

            $bError = false;
            /**
             * Проверка мыла
             */
            if (\F::CheckVal(\F::getRequestStr('mail'), 'mail')) {
                if (($oUserMail = \E::Module('User')->getUserByMail(\F::getRequestStr('mail')))
                    && $oUserMail->getId() != $this->oUserCurrent->getId()
                ) {
                    \E::Module('Message')->addError(
                        \E::Module('Lang')->get('settings_profile_mail_error_used'), \E::Module('Lang')->get('error')
                    );
                    $bError = true;
                }
            } else {
                \E::Module('Message')->addError(\E::Module('Lang')->get('settings_profile_mail_error'), \E::Module('Lang')->get('error'));
                $bError = true;
            }
            /**
             * Проверка на смену пароля
             */
            if ($sPassword = $this->getPost('password')) {
                if (($nMinLen = \C::get('module.security.password_len')) < 3) {
                    $nMinLen = 3;
                }
                if (\F::CheckVal($sPassword, 'password', $nMinLen)) {
                    if ($sPassword === $this->getPost('password_confirm')) {
                        if (\E::Module('Security')->verifyPasswordHash($this->getPost('password_now'), $this->oUserCurrent->getPassword())) {
                            $this->oUserCurrent->setPassword($sPassword, true);
                        } else {
                            $bError = true;
                            \E::Module('Message')->addError(
                                \E::Module('Lang')->get('settings_profile_password_current_error'), \E::Module('Lang')->get('error')
                            );
                        }
                    } else {
                        $bError = true;
                        \E::Module('Message')->addError(
                            \E::Module('Lang')->get('settings_profile_password_confirm_error'), \E::Module('Lang')->get('error')
                        );
                    }
                } else {
                    $bError = true;
                    \E::Module('Message')->addError(
                        \E::Module('Lang')->get('settings_profile_password_new_error', array('num' => $nMinLen)),
                        \E::Module('Lang')->get('error')
                    );
                }
            }
            /**
             * Ставим дату последнего изменения
             */
            $this->oUserCurrent->setProfileDate(\F::Now());
            /**
             * Запускаем выполнение хуков
             */
            \HookManager::run(
                'settings_account_save_before', array('oUser' => $this->oUserCurrent, 'bError' => &$bError)
            );
            /**
             * Сохраняем изменения
             */
            if (!$bError) {
                if (\E::Module('User')->Update($this->oUserCurrent)) {
                    \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('settings_account_submit_ok'));
                    /**
                     * Подтверждение смены емайла
                     */
                    if (\F::getRequestStr('mail') && F::getRequestStr('mail') != $this->oUserCurrent->getMail()) {
                        if ($oChangemail = \E::Module('User')->MakeUserChangemail($this->oUserCurrent, F::getRequestStr('mail'))) {
                            if ($oChangemail->getMailFrom()) {
                                \E::Module('Message')->addNotice(\E::Module('Lang')->get('settings_profile_mail_change_from_notice'));
                            } else {
                                \E::Module('Message')->addNotice(\E::Module('Lang')->get('settings_profile_mail_change_to_notice'));
                            }
                        }
                    }

                    \HookManager::run('settings_account_save_after', array('oUser' => $this->oUserCurrent));
                } else {
                    \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));
                }
            }
        }
    }

    /**
     * Выводит форму для редактирования профиля и обрабатывает её
     *
     */
    public function eventProfile() {

        // * Устанавливаем title страницы
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('settings_menu_profile'));
        \E::Module('Viewer')->assign('aUserFields',   \E::Module('User')->getUserFields(''));
        \E::Module('Viewer')->assign('aUserFieldsContact',   \E::Module('User')->getUserFields(array('contact', 'social')));

        // * Загружаем в шаблон JS текстовки
        \E::Module('Lang')->addLangJs(
            array(
                 'settings_profile_field_error_max'
            )
        );

        // * Если нажали кнопку "Сохранить"
        if ($this->isPost('submit_profile_edit')) {
            \E::Module('Security')->validateSendForm();

            $bError = false;
            /**
             * Заполняем профиль из полей формы
             */

            // * Определяем гео-объект
            if (\F::getRequest('geo_city')) {
                $oGeoObject = \E::Module('Geo')->getGeoObject('city', F::getRequestStr('geo_city'));
            } elseif (\F::getRequest('geo_region')) {
                $oGeoObject = \E::Module('Geo')->getGeoObject('region', F::getRequestStr('geo_region'));
            } elseif (\F::getRequest('geo_country')) {
                $oGeoObject = \E::Module('Geo')->getGeoObject('country', F::getRequestStr('geo_country'));
            } else {
                $oGeoObject = null;
            }

            // * Проверяем имя
            if (\F::CheckVal(\F::getRequestStr('profile_name'), 'text', 2, \C::get('module.user.name_max'))) {
                $this->oUserCurrent->setProfileName(\F::getRequestStr('profile_name'));
            } else {
                $this->oUserCurrent->setProfileName(null);
            }

            // * Проверяем пол
            if (in_array(\F::getRequestStr('profile_sex'), array('man', 'woman', 'other'))) {
                $this->oUserCurrent->setProfileSex(\F::getRequestStr('profile_sex'));
            } else {
                $this->oUserCurrent->setProfileSex('other');
            }

            // * Проверяем дату рождения
            $nDay = F::getRequestInt('profile_birthday_day');
            $nMonth = F::getRequestInt('profile_birthday_month');
            $nYear = F::getRequestInt('profile_birthday_year');
            if (checkdate($nMonth, $nDay, $nYear)) {
                $this->oUserCurrent->setProfileBirthday(date('Y-m-d H:i:s', mktime(0, 0, 0, $nMonth, $nDay, $nYear)));
            } else {
                $this->oUserCurrent->setProfileBirthday(null);
            }

            // * Проверяем информацию о себе
            if (\F::CheckVal(\F::getRequestStr('profile_about'), 'text', 1, 3000)) {
                $this->oUserCurrent->setProfileAbout(\E::Module('Text')->Parse(\F::getRequestStr('profile_about')));
            } else {
                $this->oUserCurrent->setProfileAbout(null);
            }

            // * Ставим дату последнего изменения профиля
            $this->oUserCurrent->setProfileDate(\F::Now());

            // * Запускаем выполнение хуков
            \HookManager::run('settings_profile_save_before', array('oUser' => $this->oUserCurrent, 'bError' => &$bError));

            // * Сохраняем изменения профиля
            if (!$bError) {
                if (\E::Module('User')->Update($this->oUserCurrent)) {

                    // * Обновляем название личного блога
                    $oBlog = $this->oUserCurrent->getBlog();
                    if (\F::getRequestStr('blog_title') && $this->checkBlogFields($oBlog)) {
                        $oBlog->setTitle(strip_tags(\F::getRequestStr('blog_title')));
                        \E::Module('Blog')->UpdateBlog($oBlog);
                    }

                    // * Создаем связь с гео-объектом
                    if ($oGeoObject) {
                        \E::Module('Geo')->CreateTarget($oGeoObject, 'user', $this->oUserCurrent->getId());
                        if ($oCountry = $oGeoObject->getCountry()) {
                            $this->oUserCurrent->setProfileCountry($oCountry->getName());
                        } else {
                            $this->oUserCurrent->setProfileCountry(null);
                        }
                        if ($oRegion = $oGeoObject->getRegion()) {
                            $this->oUserCurrent->setProfileRegion($oRegion->getName());
                        } else {
                            $this->oUserCurrent->setProfileRegion(null);
                        }
                        if ($oCity = $oGeoObject->getCity()) {
                            $this->oUserCurrent->setProfileCity($oCity->getName());
                        } else {
                            $this->oUserCurrent->setProfileCity(null);
                        }
                    } else {
                        \E::Module('Geo')->DeleteTargetsByTarget('user', $this->oUserCurrent->getId());
                        $this->oUserCurrent->setProfileCountry(null);
                        $this->oUserCurrent->setProfileRegion(null);
                        $this->oUserCurrent->setProfileCity(null);
                    }
                    \E::Module('User')->Update($this->oUserCurrent);

                    // * Обрабатываем дополнительные поля, type = ''
                    $aFields = \E::Module('User')->getUserFields('');
                    $aData = [];
                    foreach ($aFields as $iId => $aField) {
                        if (isset($_REQUEST['profile_user_field_' . $iId])) {
                            $aData[$iId] = F::getRequestStr('profile_user_field_' . $iId);
                        }
                    }
                    \E::Module('User')->SetUserFieldsValues($this->oUserCurrent->getId(), $aData);

                    // * Динамические поля контактов, type = array('contact','social')
                    $aType = array('contact', 'social');
                    $aFields = \E::Module('User')->getUserFields($aType);

                    // * Удаляем все поля с этим типом
                    \E::Module('User')->DeleteUserFieldValues($this->oUserCurrent->getId(), $aType);
                    $aFieldsContactType = F::getRequest('profile_user_field_type');
                    $aFieldsContactValue = F::getRequest('profile_user_field_value');
                    if (is_array($aFieldsContactType)) {
                        $iMax = \C::get('module.user.userfield_max_identical');
                        foreach ($aFieldsContactType as $iFieldNum => $iFieldType) {
                            $iFieldType = (int)$iFieldType;
                            if (!empty($aFieldsContactValue[$iFieldNum])) {
                                $sFieldValue = (string)$aFieldsContactValue[$iFieldNum];
                                if (isset($aFields[$iFieldType]) && $sFieldValue) {
                                    \E::Module('User')->SetUserFieldsValues($this->oUserCurrent->getId(), array($iFieldType => $sFieldValue), $iMax);
                                }
                            }
                        }
                    }
                    \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('settings_profile_submit_ok'));
                    \HookManager::run('settings_profile_save_after', array('oUser' => $this->oUserCurrent));
                } else {
                    \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));
                }
            }
        }

        // * Загружаем гео-объект привязки
        $oGeoTarget = \E::Module('Geo')->getTargetByTarget('user', $this->oUserCurrent->getId());
        \E::Module('Viewer')->assign('oGeoTarget', $oGeoTarget);

        // * Загружаем в шаблон список стран, регионов, городов
        $aCountries = \E::Module('Geo')->getCountries(array(), array('sort' => 'asc'), 1, 300);
        \E::Module('Viewer')->assign('aGeoCountries', $aCountries['collection']);
        if ($oGeoTarget) {
            if ($oGeoTarget->getCountryId()) {
                $aRegions = \E::Module('Geo')->getRegions(
                    array('country_id' => $oGeoTarget->getCountryId()), array('sort' => 'asc'), 1, 500
                );
                \E::Module('Viewer')->assign('aGeoRegions', $aRegions['collection']);
            }
            if ($oGeoTarget->getRegionId()) {
                $aCities = \E::Module('Geo')->getCities(
                    array('region_id' => $oGeoTarget->getRegionId()), array('sort' => 'asc'), 1, 500
                );
                \E::Module('Viewer')->assign('aGeoCities', $aCities['collection']);
            }
        }
        \E::Module('Lang')->addLangJs(
            array(
                'settings_profile_avatar_resize_title',
                'settings_profile_avatar_resize_text',
                'settings_profile_photo_resize_title',
                'settings_profile_photo_resize_text',
            )
        );
    }

    /**
     * Проверка полей блога
     *
     * @param ModuleBlog_EntityBlog|null $oBlog
     *
     * @return bool
     */
    protected function checkBlogFields($oBlog = null) {

        $bOk = true;

        // * Проверяем есть ли название блога
        if (!F::CheckVal(\F::getRequestStr('blog_title'), 'text', 2, 200)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('blog_create_title_error'), \E::Module('Lang')->get('error'));
            $bOk = false;
        } else {

            // * Проверяем есть ли уже блог с таким названием
            if ($oBlogExists = \E::Module('Blog')->getBlogByTitle(\F::getRequestStr('blog_title'))) {
                if (!$oBlog || $oBlog->getId() != $oBlogExists->getId()) {
                    \E::Module('Message')->addError(
                        \E::Module('Lang')->get('blog_create_title_error_unique'), \E::Module('Lang')->get('error')
                    );
                    $bOk = false;
                }
            }
        }

        return $bOk;
    }

    /**
     * Выполняется при завершении работы экшена
     *
     */
    public function eventShutdown() {

        $iUserId = \E::userId();

        // Get stats of various user publications topics, comments, images, etc. and stats of favourites
        $aProfileStats = \E::Module('User')->getUserProfileStats($iUserId);

        // Получим информацию об изображениях пользователя
        /** @var ModuleMedia_EntityMediaCategory[] $aUserImagesInfo */
        $aUserImagesInfo = \E::Module('Media')->getAllImageCategoriesByUserId($iUserId);

        \E::Module('Viewer')->assign('oUserProfile', E::User());
        \E::Module('Viewer')->assign('aProfileStats', $aProfileStats);
        \E::Module('Viewer')->assign('aUserImagesInfo', $aUserImagesInfo);

        // Old style skin compatibility
        \E::Module('Viewer')->assign('iCountTopicUser', $aProfileStats['count_topics']);
        \E::Module('Viewer')->assign('iCountCommentUser', $aProfileStats['count_comments']);
        \E::Module('Viewer')->assign('iCountTopicFavourite', $aProfileStats['favourite_topics']);
        \E::Module('Viewer')->assign('iCountCommentFavourite', $aProfileStats['favourite_comments']);
        \E::Module('Viewer')->assign('iCountNoteUser', $aProfileStats['count_usernotes']);
        \E::Module('Viewer')->assign('iCountWallUser', $aProfileStats['count_wallrecords']);

        \E::Module('Viewer')->assign('iPhotoCount', $aProfileStats['count_images']);
        \E::Module('Viewer')->assign('iCountCreated', $aProfileStats['count_created']);

        \E::Module('Viewer')->assign('iCountFavourite', $aProfileStats['count_favourites']);
        \E::Module('Viewer')->assign('iCountFriendsUser', $aProfileStats['count_friends']);

        // * Загружаем в шаблон необходимые переменные
        \E::Module('Viewer')->assign('sMenuItemSelect', $this->sMenuItemSelect);
        \E::Module('Viewer')->assign('sMenuSubItemSelect', $this->sMenuSubItemSelect);

        \HookManager::run('action_shutdown_settings');
    }

}

// EOF