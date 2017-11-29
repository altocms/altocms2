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
 * Сущность записи на стене
 *
 * @package modules.wall
 * @since   1.0
 */
class ModuleWall_EntityWall extends Entity {
    /**
     * Определяем правила валидации
     *
     * @var array
     */
    protected $aValidateRules
        = array(
            array('pid', 'pid', 'on' => array('', 'add')),
            array('user_id', 'time_limit', 'on' => array('add')),
        );

    /**
     * Инициализация
     */
    public function init() {

        parent::init();
        $this->aValidateRules[] = array(
            'text',
            'string',
            'max'        => \C::get('module.wall.text_max'),
            'min'        => \C::get('module.wall.text_min'),
            'allowEmpty' => false,
            'on'         => array('', 'add')
        );
    }

    /**
     * Проверка на ограничение по времени
     *
     * @param string $sValue     Проверяемое значение
     * @param array  $aParams    Параметры
     *
     * @return bool|string
     */
    public function ValidateTimeLimit($sValue, $aParams) {

        if ($oUser = \E::Module('User')->getUserById($this->getUserId())) {
            if (\E::Module('ACL')->canAddWallTime($oUser, $this)) {
                return true;
            }
        }
        return \E::Module('Lang')->get('wall_add_time_limit');
    }

    /**
     * Валидация родительского сообщения
     *
     * @param string $sValue     Проверяемое значение
     * @param array  $aParams    Параметры
     *
     * @return bool|string
     */
    public function ValidatePid($sValue, $aParams) {

        if (!$sValue) {
            $this->setPid(null);
            return true;
        } elseif ($oParentWall = $this->GetPidWall()) {
            /**
             * Если отвечаем на сообщение нужной стены и оно корневое, то все ОК
             */
            if ($oParentWall->getWallUserId() == $this->getWallUserId() and !$oParentWall->getPid()) {
                return true;
            }
        }
        return \E::Module('Lang')->get('wall_add_pid_error');
    }

    /**
     * Возвращает родительскую запись
     *
     * @return ModuleWall_EntityWall|null
     */
    public function getPidWall() {

        if ($this->getPid()) {
            return \E::Module('Wall')->getWallById($this->getPid());
        }
        return null;
    }

    /**
     * Проверка на возможность удаления сообщения
     *
     * @return bool
     */
    public function isAllowDelete() {

        if ($oUserCurrent = \E::User()) {
            if ($oUserCurrent->getId() == $this->getWallUserId() or $oUserCurrent->isAdministrator()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Возвращает пользователя, которому принадлежит стена
     *
     * @return ModuleUser_EntityUser|null
     */
    public function getWallUser() {

        if (!$this->getProp('wall_user')) {
            $this->aProps['wall_user'] = \E::Module('User')->getUserById($this->getWallUserId());
        }
        return $this->getProp('wall_user');
    }

    /**
     * Возвращает URL стены
     *
     * @return string
     */
    public function getLink() {

        return $this->getWallUser()->getProfileUrl() . 'wall/';
    }

    /**
     * Creates RSS item for the wall record
     *
     * @return ModuleRss_EntityRssItem
     */
    public function createRssItem() {

        $aRssItemData = array(
            'title' => 'Wall of ' . $this->getWallUser()->getDisplayName() . ' (record #' . $this->getId() . ')',
            'description' => $this->getText(),
            'link' => $this->getLink(),
            'author' => $this->getWallUser() ? $this->getWallUser()->getMail() : '',
            'guid' => $this->getLink(),
            'pub_date' => $this->getDateAdd() ? date('r', strtotime($this->getDateAdd())) : '',
        );
        $oRssItem = \E::getEntity('ModuleRss_EntityRssItem', $aRssItemData);

        return $oRssItem;
    }

}

// EOF