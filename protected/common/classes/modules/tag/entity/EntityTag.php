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
 * Объект сущности тега
 *
 * @package modules.tag
 */
class ModuleTag_EntityTag extends Entity
{
    /**
     * Возвращает ID тега
     *
     * @return int|null
     */
    public function getId()
    {
        return $this->getProp('tag_id');
    }

    /**
     * @return int|null
     */
    public function getTargetId()
    {
        return $this->getProp('target_id');
    }

    /**
     * @return string
     */
    public function getTargetType()
    {
        return $this->getProp('target_type');
    }

    /**
     * Возвращает ID пользователя
     *
     * @return int
     */
    public function getUserId()
    {
        return $this->getProp('user_id');
    }

    /**
     * Возвращает ID блога
     *
     * @return int|null
     */
    public function getTargetParentId()
    {
        return $this->getProp('target_parent_id');
    }

    /**
     * Возвращает текст тега
     *
     * @return string|null
     */
    public function getText()
    {
        return $this->getProp('tag_text');
    }

    /**
     * Возвращает количество тегов
     *
     * @return int|null
     */
    public function getCount()
    {
        return $this->getProp('count');
    }

    /**
     * Возвращает просчитанный размер тега для облака тегов
     *
     * @return int|null
     */
    public function getSize()
    {
        return $this->getProp('size');
    }


    /**
     * Устанавливает ID тега
     *
     * @param int $data
     */
    public function setId($data)
    {
        $this->setProp('tag_id', $data);
    }

    /**
     * Устанавливает ID топика
     *
     * @param int $data
     */
    public function setTargetId($data)
    {
        $this->setProp('target_id', $data);
    }

    /**
     * Устанавливает ID пользователя
     *
     * @param int $data
     */
    public function setUserId($data)
    {
        $this->setProp('user_id', $data);
    }

    /**
     * Устанавливает ID блога
     *
     * @param int $data
     */
    public function setTargetParentId($data)
    {
        $this->setProp('target_parent__id', $data);
    }

    /**
     * Устанавливает текст тега
     *
     * @param string $data
     */
    public function setText($data)
    {
        $this->setProp('tag_text', $data);
    }

    /**
     * Устанавливает просчитанный размер тега для облака тегов
     *
     * @param int $data
     */
    public function setSize($data)
    {
        $this->setProp('size', $data);
    }

    /**
     * @return string
     */
    public function getLink()
    {
        return \R::getLink('tag') . $this->getId() . '-' . \F::urlEncode($this->getText(), true) . '/';
    }

}

// EOF