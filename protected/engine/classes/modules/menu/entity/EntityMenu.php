<?php

/**
 * EntityMenu.php
 * Файл сущности меню для модуля Menu
 *
 * @since     1.1 от 13.11.2014 19:55
 */
class ModuleMenu_EntityMenu extends Entity {

    /**
     * Элементы текущего меню
     * @var ModuleMenu_EntityItem[]
     */
    protected $_aItems = [];

    /**
     * Переменная для кэширования описания элемента меню
     * @var null|bool
     */
    protected $_description = NULL;

    public function init() {

        $this->_aItems = isset($this->aProps['items']) ? $this->aProps['items'] : NULL;
        if (!empty($this->_aItems)) {
            /** @var ModuleMenu_EntityItem $oItem */
            foreach($this->_aItems as $oItem) {
                if (!empty($oItem) && is_object($oItem)) {
                    $oItem->setMenu($this);
                }
            }
        }
    }

    /**
     * @param string $sTextTemplate
     * @param null   $sLang
     *
     * @return mixed
     */
    public function getLangText($sTextTemplate, $sLang = NULL) {

        return preg_replace_callback('~(\{\{\S*\}\})~', function ($sTextTemplatePart) {
            $sTextTemplatePart = array_shift($sTextTemplatePart);
            if (!is_null($sText = \E::Module('Lang')->get(substr($sTextTemplatePart, 2, strlen($sTextTemplatePart) - 4)))) {
                return $sText;
            }

            return $sTextTemplatePart;
        }, $sTextTemplate);

    }

    /**
     * Получает описание элемента меню
     *
     * @return bool|mixed|null
     */
    public function getDescription(){

        if ($this->_description) {
            return $this->_description;
        }
        $this->_description = isset($this->aProps['description']) ? $this->aProps['description'] : NULL;
        $this->_description = $this->getLangText($this->_description);

        return $this->_description;
    }

    public function getCssClass() {

        return $this->getProp('class');
    }

    public function setCssClass($sCssClass) {

        $this->setProp('class', $sCssClass);
    }

    /**
     * Возвращает числовую позицию элемента
     *
     * @param $xPosition
     * @return int
     */
    private function _getIntPosition($xPosition) {

        if (is_int($xPosition)) {

            if ($xPosition < 0)
                $xPosition = 0;
            if ($xPosition >= $this->getLength())
                $xPosition = $xPosition - 1;

            return $xPosition;
        }

        // Массив синонимов расположения
        $aMenuPosition = array(
            'first'  => 0,
            'last'   => count($this->_aItems),
            'middle' => (int)floor(count($this->_aItems) / 2),
        );

        // Позиция элемента
        return (int)(str_replace(
            array_keys($aMenuPosition),
            array_values($aMenuPosition),
            $xPosition)
        );
    }

    /**
     * Возвращает количество элементов в меню
     *
     * @return int
     */
    public function getLength() {

        return count($this->_aItems);
    }

    /**
     * @return bool
     */
    public function isEditable() {

        $aInitData = $this->getProp('init');

        return !empty($aInitData['editable']);
    }

    /**
     * Return fill list settings, if allow any item (mark as '*') then return empty array
     *
     * @return array
     */
    public function getFillList() {

        $aInitData = $this->getProp('init');
        if (!empty($aInitData['fill']['list'])) {
            if (is_array($aInitData['fill']['list']) && in_array('*', $aInitData['fill']['list'])) {
                return [];
            } elseif ($aInitData['fill']['list'] === '*') {
                return [];
            }
            return $aInitData['fill']['list'];
        }
        return [];
    }

    /**
     * Добавляет элемент меню в произвольное место списка меню
     *
     * @param ModuleMenu_EntityItem $oMenuItem
     * @param mixed                 $xPosition Позиция в списке. Может задаваться числом, а
     *                                         может строками 'first'|'last'
     *
     * @return array
     */
    public function addItem($oMenuItem, $xPosition = 'last') {

        $xPosition = strtolower($xPosition);
        $aItems = $this->GetItems();
        if ($xPosition === 'first') {
            $aResult = array($oMenuItem->getId() => $oMenuItem) + $aItems;
        } elseif ($xPosition === 'last') {
            $aResult = $aItems + array($oMenuItem->getId() => $oMenuItem);
        } else {
            $iPos = $this->_getIntPosition($xPosition);
            $aResult = array_slice($aItems, 0, $iPos, true) +
                array($oMenuItem->getId() => $oMenuItem) +
                array_slice($aItems, $iPos, null, true);
        }

        $this->SetItems($aResult);

        $aAllowedData = $this->getFillList();
        if (!empty($aAllowedData)) {
            // Добавим имя в список разрешенных
            $aNewItems = array_merge($aAllowedData, array($oMenuItem->getItemId()));
            $this->SetConfig('init.fill.list', $aNewItems);
        }

        return TRUE;
    }

    /**
     * Возвращает ид. меню
     *
     * @return int|null
     */
    public function getId() {

        return isset($this->aProps['id']) ? $this->aProps['id'] : NULL;
    }

    /**
     * Заменить элемент меню
     *
     * @param mixed $xPosition Позиция в списке.
     * @param ModuleMenu_EntityItem $oMenuItem
     * @return bool
     */
    public function ReplaceItem($xPosition, $oMenuItem) {

        if ($this->GetItemById($oMenuItem->getId())) {
            return;
        }

        $xPosition = $this->_getIntPosition($xPosition);

        $aIds = array_keys($this->_aItems);
        $aVals = array_values($this->_aItems);
        $aResult = [];


        for ($i = 0; $i < count($this->_aItems); $i++) {
            if ($i == $xPosition) {
                $aResult[$oMenuItem->getId()] = $oMenuItem;
                continue;
            }
            $aResult[$aIds[$i]] = $aVals[$i];
        }

        $this->_aItems = $aResult;
    }

    /**
     * Удаляет элемент меню
     *
     * @param mixed $xPosition Позиция в списке.
     * @return array
     */
    public function RemoveItem($xPosition) {

        $xPosition = $this->_getIntPosition($xPosition);

        $aIds = array_keys($this->_aItems);
        $aVals = array_values($this->_aItems);
        $aResult = [];


        for ($i = 0; $i < count($this->_aItems); $i++) {
            if ($i == $xPosition) {
                continue;
            }
            $aResult[$aIds[$i]] = $aVals[$i];
        }

        $this->_aItems = $aResult;

    }

    /**
     * Возвращает элемент меню из указанной позиции
     *
     * @param $xPosition
     * @return bool|ModuleMenu_EntityItem
     */
    public function SelectItem($xPosition) {

        $iPosition = $this->_getIntPosition($xPosition);

        return isset($this->_aItems[$iPosition])
            ? $this->_aItems[$iPosition]
            : FALSE;

    }

    /**
     * Возвращает элемент меню из указанной позиции, синоним {@see SelectItem}
     *
     * @param $xPosition
     * @return bool|ModuleMenu_EntityItem
     */
    public function getItem($xPosition) {

        return $this->SelectItem($xPosition);
    }

    /**
     * Возвращает элемент меню из указанной позиции, синоним {@see SelectItem}
     *
     * @param string $sItemId
     *
     * @return ModuleMenu_EntityItem|null
     */
    public function getItemById($sItemId) {

        return isset($this->_aItems[$sItemId]) ? $this->_aItems[$sItemId] : NULL;
    }

    /**
     * Удаляет элемент меню по его ID
     *
     * @param string|array|object $xItem
     * @param bool                $bClearCache
     */
    public function RemoveItemById($xItem, $bClearCache = false) {

        if (is_array($xItem)) {
            /** @var string|object $xItemId */
            foreach($xItem as $xItemId) {
                if (is_object($xItemId)) {
                    $sItemId = $xItemId->getId();
                } else {
                    $sItemId = (string)$xItemId;
                }
                if (isset($this->_aItems[$sItemId])) {
                    unset($this->_aItems[$sItemId]);
                }
            }
        } else {
            if (is_object($xItem)) {
                $sItemId = $xItem->getId();
            } else {
                $sItemId = (string)$xItem;
            }
            if (isset($this->_aItems[$sItemId])) {
                unset($this->_aItems[$sItemId]);
            }
        }

        if ($bClearCache) {
            \C::resetCustomConfig('menu.data.' . $this->getId() . '.list');
            \E::Module('Menu')->SaveMenu($this);
        }
    }

    /**
     * Возвращает все элементы меню
     *
     * @return ModuleMenu_EntityItem[]
     */
    public function getItems() {

        $aAllowedItems = $this->_aItems;
        if ($aAllowedItems) {
            $aAllowedData = $this->getFillList();
            if (!empty($aAllowedData)) {
                foreach ($aAllowedItems as $sItemId => $oItem) {
                    if (!in_array($sItemId, $aAllowedData)) {
                        unset($aAllowedItems[$sItemId]);
                    }
                }
            }

            return $aAllowedItems;
        }
        return [];
    }

    /**
     * Устанавливает все элементы меню
     *
     * @param $aMenuItems
     */
    public function setItems($aMenuItems) {

        $this->_aItems = $aMenuItems;
    }

    /**
     * @param string $sKey
     * @param mixed  $xValue
     */
    public function setConfig($sKey, $xValue) {

        if (is_array($xValue)) {
            // Only scalar can be used as end value
            array_walk_recursive($xValue, function(&$xV){
                if (!is_scalar($xV)) {
                    $xV = null;
                }
            });
        }
        if ($sKey && (is_scalar($xValue) || is_array($xValue))) {
            $aKeys = explode('.', $sKey);
            $aData = &$this->aProps;
            $aCfg = &$this->aProps['_cfg'];
            foreach ($aKeys as $sSubKey) {
                $aData = &$aData[$sSubKey];
                $aCfg = &$aCfg[$sSubKey];
            }
            $aData = $xValue;
            $aCfg = $xValue;
        }
    }

    /**
     * @param bool|false $bRefreshItemList
     *
     * @return array
     */
    public function getConfig($bRefreshItemList = false) {

        $aMenuConfig = $this->aProps['_cfg'];

        if ($bRefreshItemList) {
            $aItemList = [];
            foreach ($this->GetItems() as $sMenuId => $oMenuItem) {
                $aItemList[$sMenuId] = $oMenuItem ? $oMenuItem->getItemConfig() : '';
            }
            $aMenuConfig['list'] = $aItemList;
        }

        return $aMenuConfig;
    }

    /**
     * @param string $sItemId
     * @param string $sKey
     * @param string $xValue
     */
    public function setConfigItem($sItemId, $sKey, $xValue) {

        $oItem = $this->GetItemById($sItemId);
        if ($oItem) {
            $oItem->SetConfig($sKey, $xValue);
        }
    }

}

// EOF