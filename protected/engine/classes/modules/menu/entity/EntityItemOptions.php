<?php

/**
 * EntityItemOptions.php
 * Файл сущности настроек меню для модуля Menu плагина
 *
 * @since     1.1 от 13.11.2014 21:03
 */
class ModuleMenu_EntityItemOptions extends Entity {

    /**
     * Переменная для кэширвоания обработки правил хранит
     * url картинки
     * @var null|bool
     */
    protected $_image_url = NULL;
    /**
     * Переменная для кэширвоания обработки правил хранит
     * title картинки
     * @var null|bool
     */
    protected $_image_title = NULL;
    /**
     * Переменная для кэширвоания обработки правил хранит
     * title сылки
     * @var null|bool
     */
    protected $_link_title = NULL;


    public function getLangText($sTextTemplate, $sLang = NULL) {

        return preg_replace_callback('~(\{\{\S*\}\})~', function($sTextTemplatePart){
            $sTextTemplatePart = array_shift($sTextTemplatePart);
            if (!is_null($sText = \E::Module('Lang')->get(substr($sTextTemplatePart, 2, strlen($sTextTemplatePart) - 4)))) {
                return $sText;
            }
            return $sTextTemplatePart;
        }, $sTextTemplate);

    }

    /**
     * Возвращает название текстовки для ссылки
     * @return int|null
     */
    public function getLinkTitle() {
        // Выведем кэш
        if (is_string($this->_link_title)) {
            return $this->_link_title;
        }

        /** @var callable[]|[][] $aActiveRule Правило вычисления активности */
        $aActiveRule = isset($this->aProps['link_title']) ? $this->aProps['link_title'] : '';

        $this->_link_title = $this->checkCustomRules($aActiveRule, TRUE);

        $this->_link_title = $this->getLangText($this->_link_title);

        return $this->_link_title;
    }

    /**
     * Возвращает класс иконки меню
     *
     * @return string|null
     */
    public function getIconClass() {
        return isset($this->aProps['icon_class']) ? $this->aProps['icon_class'] : NULL;
    }

    /**
     * Возвращает класс иконки меню
     *
     * @return string|null
     */
    public function getImageClass() {
        return isset($this->aProps['image_class']) ? $this->aProps['image_class'] : NULL;
    }

    /**
     * Возвращает картинку блога
     *
     * @return string|null
     */
    public function getImageUrl() {

        // Выведем кэш
        if (is_string($this->_image_url)) {
            return $this->_image_url;
        }

        /** @var callable[]|[][] $aActiveRule  */
        $aActiveRule = isset($this->aProps['image_url']) ? $this->aProps['image_url'] : '';

        $this->_image_url = $this->checkCustomRules($aActiveRule, TRUE);

        return $this->_image_url;

    }

    /**
     * Возвращает картинку блога
     *
     * @return string|null
     */
    public function getImageTitle() {

        // Выведем кэш
        if (is_string($this->_image_title)) {
            return $this->_image_title;
        }

        /** @var callable[]|[][] $aActiveRule  */
        $aActiveRule = isset($this->aProps['image_title']) ? $this->aProps['image_title'] : '';

        $this->_image_title = $this->checkCustomRules($aActiveRule, TRUE);

        return $this->_image_title;

    }

    /**
     * Возвращает класс иконки меню
     *
     * @return string|null
     */
    public function getSkin() {
        return isset($this->aProps['skin']) ? $this->aProps['skin'] : NULL;
    }

    /**
     * Возвращает тему
     *
     * @return string|null
     */
    public function getTheme() {
        return isset($this->aProps['theme']) ? $this->aProps['theme'] : NULL;
    }

    /**
     * Возвращает класс иконки меню
     *
     * @return string|null
     */
    public function getPlugin() {
        return isset($this->aProps['plugin']) ? $this->aProps['plugin'] : NULL;
    }


    /**
     * Возвращает класс элемента
     *
     * @return string|null
     */
    public function getClass() {
        return isset($this->aProps['class']) ? $this->aProps['class'] : NULL;
    }


    /**
     * Возвращает класс элемента
     *
     * @return string|null
     */
    public function getActiveClass() {
        return isset($this->aProps['active_class']) ? $this->aProps['active_class'] : NULL;
    }


    /**
     * Возвращает класс элемента
     *
     * @return string|null
     */
    public function getLinkClass() {
        return isset($this->aProps['link_class']) ? $this->aProps['link_class'] : NULL;
    }


    /**
     * Возвращает класс элемента
     *
     * @return string|null
     */
    public function getActiveLinkClass() {
        return isset($this->aProps['active_link_class']) ? $this->aProps['active_link_class'] : NULL;
    }

    /**
     * Возвращает массив атрибутов data
     *
     * @return array
     */
    public function getData() {
        return isset($this->aProps['data']) ? $this->aProps['data'] : NULL;
    }

    /**
     * Возвращает массив атрибутов data
     *
     * @return array
     */
    public function getLinkData() {
        return isset($this->aProps['link_data']) ? $this->aProps['link_data'] : NULL;
    }

    /**
     * Возвращает массив атрибутов data
     *
     * @return array
     */
    public function getLinkId() {
        return isset($this->aProps['link_id']) ? $this->aProps['link_id'] : NULL;
    }

}