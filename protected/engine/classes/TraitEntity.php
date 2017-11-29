<?php

/**
 * Class PluginNova_TraitEntity
 *
 * @property string $sTableName
 */
trait PluginNova_TraitEntity
{
    //static protected $sTableName;

    static protected $sPkAttribute = 'id';
    static protected $aAttributes = [];

    static protected $aComboAttributesKeys = [];

    protected $bComboJson = true;
    protected $aExtra = null;

    protected $oModule;

    /**
     * @param string $sTableName
     */
    static public function setTableName($sTableName)
    {
        static::$sTableName = $sTableName;
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    static public function getTableName()
    {
        if (empty(static::$sTableName)) {
            throw new Exception('Table name for ' . __CLASS__ . ' is not defined');
        }
        return static::$sTableName;
    }

    /**
     * Задает список атрибутов сущности (маппинг таблицы)
     * Если имя атрибута заканчивается на '@', то это составной атрибут (комбо-атрибут)
     * Если имя атрибута начинается с '^', то это первичный ключ
     *
     * @param array $aAttributes
     *
     */
    static public function setAttributes($aAttributes = array())
    {
        $aAttributes = F::Array_FlipIntKeys($aAttributes);
        foreach ($aAttributes as $sAttributeKey => $xAttributeParam) {
            if (substr($sAttributeKey, -1) === '@') {
                static::setComboAttribute($sAttributeKey);
            } elseif ($sAttributeKey[0] === '^') {
                static::setPkName(substr($sAttributeKey, 1));
            } else {
                static::$aAttributes[$sAttributeKey] = [];
            }
        }
    }

    /**
     * @param string $sName
     */
    static public function setPkName($sName)
    {
        static::$sPkAttribute = $sName;
        if (!isset(static::$aAttributes[static::$sPkAttribute])) {
            static::$aAttributes[static::$sPkAttribute] = [];
        }
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    static public function getPkName()
    {
        if (empty(static::$sPkAttribute)) {
            throw new \RuntimeException('Primary key  for ' . __CLASS__ . ' is not defined');
        }
        return static::$sPkAttribute;
    }


    /**
     * @param string $sAttributeKey
     *
     */
    static public function setComboAttribute($sAttributeKey)
    {
        if (substr($sAttributeKey, -1) === '@') {
            $sAttributeKey = substr($sAttributeKey, 0, -1);
        }
        static::$aComboAttributesKeys[$sAttributeKey] = [];
        static::$aAttributes[$sAttributeKey] = [];
    }

    /**
     * @param string $sTableName
     * @param array  $aAttributes
     *
     */
    static public function setTable($sTableName, $aAttributes)
    {
        static::setTableName($sTableName);
        static::setAttributes($aAttributes);
    }

    /**
     * @return array
     */
    static public function getAttributes()
    {
        $sPkName = static::getPkName();
        if (!isset(static::$aAttributes[$sPkName])) {
            static::setPkName($sPkName);
        }
        return static::$aAttributes;
    }

    /**
     * @param $oModule
     *
     * @return $this
     */
    public function setModule($oModule)
    {
        $this->oModule = $oModule;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getModule()
    {
        return $this->oModule;
    }

    /**
     * Задает значение атрибута
     *
     * @param string $sKey
     * @param mixed  $xVal
     *
     * @return $this
     */
    public function setAttributeVal($sKey, $xVal)
    {
        if (strpos($sKey, '@') !== false) {
            $this->setComboValue($sKey, $xVal);
        } elseif (array_key_exists($sKey, static::$aAttributes)) {
            $this->setProp($sKey, $xVal);
        }

        return $this;
    }

    /**
     * @param string $sKey
     *
     * @return mixed
     */
    public function getAttributeVal($sKey)
    {
        if (isset(static::$aAttributes[$sKey])) {
            return static::$aAttributes[$sKey];
        }
        return null;
    }

    /**
     * setAttr('foo', 1) - set attribute value 'foo' as 1
     * setAttr(['foo' => 1, 'bar' => 2]) - set attribute value 'foo' as 1 and 'bar' as 2
     * setAttr(['foo', 'bar'], 3) - set attribute values 'foo' and 'bar' as 3
     *
     * @param $xKey
     * @param $xVal
     *
     * @return $this
     */
    public function setAttr($xKey, $xVal = null)
    {
        /** @var array $xKey */
        if (is_array($xKey)) {
            if (func_num_args() === 1) {
                foreach((array)$xKey as $sAttrKey => $xAttrVal) {
                    $this->setAttributeVal($sAttrKey, $xAttrVal);
                }
            } else {
                foreach($xKey as $sKey) {
                    $this->setAttributeVal($sKey, $xVal);
                }
            }
        } else {
            $this->setAttributeVal($xKey, $xVal);
        }
        return $this;
    }

    /**
     * @param string $sKey
     *
     * @return mixed
     */
    public function getAttr($sKey)
    {
        return $this->getAttributeVal($sKey);
    }

    /**
     * @param array $aData
     *
     * @return $this
     */
    public function loadAttributes($aData)
    {
        if (!empty($aData) && is_array($aData)) {
            foreach ($aData as $sKey => $xVal) {
                $this->setAttributeVal($sKey, $xVal);
            }
        }
        return $this;
    }

    /**
     * @param string $sKey
     *
     * @return null
     */
    public function getOriginalProp($sKey)
    {
        return $this->getAttributeVal($sKey);
    }

    /**
     * @return mixed
     */
    public function getPk()
    {
        return $this->getProp(static::getPkName());
    }

    /**
     * @param $xValue
     *
     * @return Entity
     */
    public function setPk($xValue)
    {
        return $this->setProp(static::getPkName(), $xValue);
    }

    /**
     * @return array
     */
    public function getAttributesKey()
    {
        return array_keys(static::getAttributes());
    }

    /**
     * @return array
     */
    public function getInsertAttributesKey()
    {
        return $this->getAttributesKey();
    }

    /**
     * @return array
     */
    public function getInsertAttributes()
    {
        $aAttributesKey = $this->getInsertAttributesKey();
        $aResult = [];
        foreach($aAttributesKey as $sKey) {
            if (array_key_exists($sKey, static::$aComboAttributesKeys)) {
                $aResult[$sKey] = $this->getCombo($sKey);
            } else {
                $aResult[$sKey] = $this->getProp($sKey);
            }
        }
        return $aResult;
    }

    /**
     * @return array
     */
    public function getInsertAttributesVal()
    {
        return array_values($this->getInsertAttributes());
    }

    /**
     * @return array
     */
    public function getUpdateAttributesKey()
    {
        return $this->getAttributesKey();
    }

    /**
     * @return array
     */
    public function getUpdateAttributes()
    {
        $aAttributesKey = $this->getUpdateAttributesKey();
        $aResult = [];
        foreach($aAttributesKey as $sKey) {
            $aResult[$sKey] = $this->getProp($sKey);
        }
        return $aResult;
    }

    /**
     * @return array
     */
    public function getUpdateAttributesVal()
    {
        return array_values($this->getUpdateAttributes());
    }

    // ***
    // Combo attributes

    /**
     * Pack combo attributes into string
     *
     * @param array $aData
     *
     * @return string
     */
    protected function _comboPack($aData)
    {
        if (empty($aData)) {
            $aData = [];
        }
        if ($this->bComboJson) {
            return json_encode($aData, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);
        }
        return serialize($aData);
    }

    /**
     * Unpack serialized data into combo attributes
     *
     * @param string $sData
     *
     * @return array
     */
    protected function _comboUnpack($sData)
    {
        if (empty($sData) || !is_string($sData)) {
            return [];
        }
        if ($sData[0] === '{' || $sData[0] === '[') {
            return @json_decode($sData, true);
        }
        return @unserialize($sData);
    }

    /**
     * @return string|null
     */
    protected function _getDefaultComboKey()
    {
        if (static::$aComboAttributesKeys) {
            $aKeys = array_keys(static::$aComboAttributesKeys);
            if ($aKeys) {
                return reset($aKeys);
            }
        }
        return null;
    }

    /**
     * Разбивает ключ свойства сущнсти на ключ комбо-атрибута и подключ
     *
     * @param $sPropKey
     *
     * @return array
     */
    protected function _splitKeys($sPropKey)
    {
        if (strpos($sPropKey, '@') !== false) {
            list($sComboKey, $sSubKey) = explode('@', $sPropKey);
        } else {
            $sComboKey = '';
            $sSubKey = $sPropKey;
        }
        if (!$sComboKey) {
            $sComboKey = $this->_getDefaultComboKey();
        }
        return [$sComboKey, $sSubKey];
    }
    /**
     * Extracts serialized data
     *
     * @param string $sComboKey
     */
    protected function _comboExtract($sComboKey = null)
    {
        if (empty($sComboKey)) {
            $sComboKey = $this->_getDefaultComboKey();
        }
        if ($sComboKey) {
            if ($this->aExtra === null || !isset($this->aExtra[$sComboKey])) {
                $sData = $this->getProp($sComboKey);
                if (empty($sData)) {
                    $this->aExtra[$sComboKey] = [];
                } else {
                    $this->aExtra[$sComboKey] = $this->_comboUnpack($sData);
                }
            }
        }
    }

    /**
     * @param string $sComboKey
     * @param mixed $xData
     *
     * @return $this
     */
    public function setCombo($sComboKey, $xData = null)
    {
        if (func_num_args() == 1) {
            $xData = $sComboKey;
            $sComboKey = $this->_getDefaultComboKey();
        }
        if ($sComboKey) {
            $this->setProp($sComboKey, $this->_comboPack($xData));
        }
        return $this;
    }

    /**
     * @param string $sComboKey
     *
     * @return null|string
     */
    public function getCombo($sComboKey = null)
    {
        if (!$sComboKey) {
            $sComboKey = $this->_getDefaultComboKey();
        }
        $sResult = $this->getProp($sComboKey);

        return $this->_comboPack($sResult);
    }

    /**
     * Устанавливает значение свойства комбо-атрибута
     *
     * @param string $sPropKey Name of extra attribute
     * @param mixed  $xData    Value of extra attribute
     *
     * @return $this
     */
    protected function setComboValue($sPropKey, $xData)
    {
        list($sComboKey, $sSubKey) = $this->_splitKeys($sPropKey);
        $this->_comboExtract($sComboKey);
        $this->aExtra[$sComboKey][$sSubKey] = $xData;
        $this->setCombo($sComboKey, $this->aExtra[$sComboKey]);

        return $this;
    }

    /**
     * Извлекает значение свойства комбо-атрибута
     *
     * @param string $sPropKey Название параметра
     *
     * @return null|mixed
     */
    protected function getComboValue($sPropKey)
    {
        list($sComboKey, $sSubKey) = $this->_splitKeys($sPropKey);
        $this->_comboExtract($sComboKey);
        if (isset($this->aExtra[$sComboKey][$sSubKey])) {
            return $this->aExtra[$sComboKey][$sSubKey];
        }
        return null;
    }

    /**
     * @param $sPropKey
     * @param $xData
     *
     * @return $this
     */
    public function appendComboValue($sPropKey, $xData)
    {
        list($sComboKey, $sSubKey) = $this->_splitKeys($sPropKey);
        $this->_comboExtract($sComboKey);
        if (empty($this->aExtra[$sComboKey][$sSubKey])) {
            $this->aExtra[$sComboKey][$sSubKey] = [];
        }
        $this->aExtra[$sComboKey][$sSubKey][] = $xData;
        $this->setCombo($sComboKey, $this->aExtra[$sComboKey]);

        return $this;
    }

    /**
     * @return bool
     */
    public function isNew()
    {
        return $this->getPk() === null;
    }

    /**
     * @return mixed
     */
    public function save()
    {
        return $this->oModule->save($this);
    }
}

// EOF