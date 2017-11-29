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
 * Сущность связи пользователя и блога
 *
 * @package modules.blog
 * @since   1.0
 */
class ModuleBlog_EntityBlogUser extends Entity {
    /**
     * Возвращает ID блога
     *
     * @return int|null
     */
    public function getBlogId() {

        return $this->getProp('blog_id');
    }

    /**
     * Возвращает ID пользователя
     *
     * @return int|null
     */
    public function getUserId() {

        return $this->getProp('user_id');
    }

    /**
     * Возвращает статус модератор пользователь или нет
     *
     * @return bool
     */
    public function getIsModerator() {

        return $this->IsBlogModerator();
    }

    /**
     * Возвращает статус администратор пользователь или нет
     *
     * @return bool
     */
    public function getIsAdministrator() {

        return $this->IsBlogAdministrator();
    }

    /**
     * Whether the user is a blog moderator
     *
     * @return bool
     */
    public function isBlogModerator() {

        return ($this->getUserRole() == ModuleBlog::BLOG_USER_ROLE_MODERATOR);
    }

    /**
     * Whether the user is a blog administrator
     *
     * @return bool
     */
    public function isBlogAdministrator() {

        return ($this->getUserRole() == ModuleBlog::BLOG_USER_ROLE_ADMINISTRATOR);
    }

    public function isBlogMember() {

        return ($this->getUserRole() == ModuleBlog::BLOG_USER_ROLE_MEMBER);
    }

    /**
     * Возвращает текущую роль пользователя в блоге
     *
     * @return int|null
     */
    public function getUserRole() {

        $iResult = $this->getProp('user_role');
        return (null === $iResult) ? null : (int)$iResult;
    }

    /**
     * Возвращает объект блога
     *
     * @return ModuleBlog_EntityBlog|null
     */
    public function getBlog() {

        return $this->getProp('blog');
    }

    /**
     * Возвращает объект пользователя
     *
     * @return ModuleUser_EntityUser|null
     */
    public function getUser() {

        return $this->getProp('user');
    }


    /**
     * Устанавливает ID блога
     *
     * @param int $data
     */
    public function setBlogId($data) {

        $this->setProp('blog_id', $data);
    }

    /**
     * Устанавливает ID пользователя
     *
     * @param int $data
     */
    public function setUserId($data) {

        $this->setProp('user_id', $data);
    }

    /**
     * Устанавливает статус модератора блога
     *
     * @param bool $data
     */
    public function setIsModerator($data) {

        if ($data && !$this->IsBlogModerator()) {
            /**
             * Повышаем статус до модератора
             */
            $this->setUserRole(ModuleBlog::BLOG_USER_ROLE_MODERATOR);
        }
    }

    /**
     * Устанавливает статус администратора блога
     *
     * @param bool $data
     */
    public function setIsAdministrator($data) {

        if ($data && !$this->IsBlogAdministrator()) {
            /**
             * Повышаем статус до администратора
             */
            $this->setUserRole(ModuleBlog::BLOG_USER_ROLE_ADMINISTRATOR);
        }
    }

    /**
     * Устанавливает роль пользователя
     *
     * @param int $data
     */
    public function setUserRole($data) {

        $this->setProp('user_role', $data);
    }

    /**
     * Устанавливает блог
     *
     * @param ModuleBlog_EntityBlog $data
     */
    public function setBlog($data) {

        $this->setProp('blog', $data);
    }

    /**
     * Устанавливаем пользователя
     *
     * @param ModuleUser_EntityUser $data
     */
    public function setUser($data) {

        $this->setProp('user', $data);
    }

}

// EOF