<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

namespace alto\engine\core;

use alto\engine\generic\Singleton;

/**
 * @package engine
 * @since   1.0
 */
class Storage extends Singleton
{
    const SAVE_MODE_ORG = 0; // хранение в оригинальном виде
    const SAVE_MODE_ARR = 1; // хранение в виде массива

    /**
     * Storage container
     *
     * @var array
     */
    protected $aStorage = [];

    protected $nSaveMode = self::SAVE_MODE_ARR;

    /** @var string - используется для внутренней фильтрации */
    protected $sFilterKey = '';

    /**
     * Нормализует передаваемый массив данных,
     * т.е. ключи вида 'a.b.c' преобразует в массив
     *
     * @param array $aData
     *
     * @return  array
     */
    protected function _keysArray($aData)
    {
        $aData = (array)$aData;
        foreach ($aData as $sKey => $xVal) {
            if (strpos($sKey, '.')) {
                $aKeys = array_reverse(explode('.', $sKey));
                $aNewData = null;
                foreach ($aKeys as $sKeyPart) {
                    if (!$aNewData) {
                        $aNewData = [$sKeyPart => $xVal];
                    } else {
                        $aNewData = [$sKeyPart => $aNewData];
                    }
                }
                unset($aData[$sKey]);
                $aData = array_merge_recursive($aData, $aNewData);
            }
        }
        return $aData;
    }

    /**
     * Создает денормализованный массив данных,
     * т.е. массив вида array('a'=>array('b'=>array('c'=>1))) преобразует к виду ['a.b.c'=>1]
     *
     * @param array $aData
     * @param bool  $bStringKeysOnly - денормализует только строковые ключи, не меняя массивы с числовыми ключами
     *
     * @return  array
     */
    protected function _keysPlain($aData, $bStringKeysOnly = false)
    {
        $aResult = [];
        foreach ($aData as $sKey => $xVal) {
            if ($bStringKeysOnly && is_int($sKey)) {
                return $aData;
            }
            $this->_keysPlainAdd($aResult, $sKey, $xVal, $bStringKeysOnly);
        }
        return $aResult;
    }

    /**
     * @param array  $aResult
     * @param string $sKey
     * @param mixed  $xVal
     * @param bool   $bStringKeysOnly
     */
    protected function _keysPlainAdd(&$aResult, $sKey, $xVal, $bStringKeysOnly = false)
    {
        if (!is_array($xVal)) {
            $aResult[$sKey] = $xVal;
        } else {
            foreach ($xVal as $sSubKey => $xSubVal) {
                if ($bStringKeysOnly && is_int($sSubKey)) {
                    $aResult[$sKey] = $xVal;
                    return;
                }
                $this->_keysPlainAdd($aResult, $sKey . '.' . $sSubKey, $xSubVal);
            }
        }
    }

    /**
     * Внутренняя функция - для фильтрации ключей
     *
     * @param $sKey
     *
     * @return bool
     */
    protected function _filterKeys($sKey)
    {
        return ($this->sFilterKey && strpos($sKey, $this->sFilterKey) === 0);
    }

    /**
     * Поиск значения в массиве по ключу (в т.ч по составному - как массив, как строка вида 'a.b.c')
     *
     * @param $aItems
     * @param $aKeys
     * @param $bFound
     *
     * @return bool
     */
    protected function _seekItem(&$aItems, &$aKeys, &$bFound)
    {
        if (!is_array($aKeys) && array_key_exists($sKey = (string)$aKeys, $aItems)) {
            // Если ключ в виде строки и есть по нему значение, то сразу возвращаем
            $bFound = true;
            return $aItems[$sKey];
        }
        if (!is_array($aKeys)) {
            // расскладываем составной строковый ключ в массив
            $aKeys = explode('.', $aKeys);
        } else {
            // исключаем влияние "кривых" индексов
            $aKeys = array_values($aKeys);
        }

        if ($this->nSaveMode != self::SAVE_MODE_ARR) {
            // проверяем наличие в массиве пар 'key.xxx' => val
            $this->sFilterKey = $aKeys[0] . '.';
            $aFilteredKeys = array_filter(array_keys($aItems), [$this, '_filterKeys']);
            if ($aFilteredKeys) {
                $aFilteredItems = array_intersect_key($aItems, array_fill_keys($aFilteredKeys, 1));
                $aSubItems = $this->_keysArray($aFilteredItems);
                foreach ($aFilteredKeys as $sExcludeKey) {
                    unset($aItems[$sExcludeKey]);
                }
                $aItems = \F::Array_Merge($aItems, $aSubItems);
            }
        }

        $bFound = false;
        foreach ($aKeys as $sK) {
            if (!array_key_exists($sK, $aItems)) {
                return false;
            } else {
                $aItems = & $aItems[$sK];
            }
        }
        $bFound = true;
        return $aItems;
    }

    /**
     * Проверка наличия значения в хранилище по ключу (в т.ч по составному - как массив, как строка вида 'a.b.c')
     *
     * @param string    $sStorageKey
     * @param array     $aKeys
     * @param bool      $bAutocreate
     * @param bool      $bCheckOnly
     *
     * @return  mixed
     */
    protected function _checkItem($sStorageKey, $aKeys, $bAutocreate = false, $bCheckOnly = false)
    {
        if (array_key_exists($sStorageKey, $this->aStorage)) {
            $aItems = & $this->aStorage[$sStorageKey];
        } elseif ($bAutocreate) {
            $this->aStorage[$sStorageKey] = [];
            $aItems = & $this->aStorage[$sStorageKey];
        } else {
            $aItems = [];
        }

        if ((count($aItems) === 0) && !$bAutocreate) {
            // хранилище пустое
            return null;
        }

        $bFound = false;
        $xResult = $this->_seekItem($aItems, $aKeys, $bFound);

        if ($bCheckOnly) {
            return $bFound;
        }

        if (!$bFound && $bAutocreate) {
            foreach ($aKeys as $sK) {
                if (array_key_exists($sK, $aItems)) {
                    $aItem = & $aItem[$sK];
                } elseif ($bAutocreate) {
                    $aItem[$sK] = [];
                    $aItem = & $aItem[$sK];
                }
            }
        }

        return $xResult;
    }

    /**
     * Сохраняет данные в хранилище
     *
     * @param string $sStorageKey
     * @param array  $aData
     * @param bool   $bReset
     *
     * @return  bool
     */
    public function setStorage($sStorageKey, $aData = [], $bReset = true)
    {
        if (is_array($aData)) {
            if ($aData) {
                if ($this->nSaveMode == self::SAVE_MODE_ARR) {
                    $aData = $this->_keysArray($aData);
                }
                if ($bReset || !isset($this->aStorage[$sStorageKey])) {
                    $this->aStorage[$sStorageKey] = $aData;
                } else {
                    $this->aStorage[$sStorageKey] = \F::Array_Merge($this->aStorage[$sStorageKey], $aData);
                }
            } elseif (!isset($this->aStorage[$sStorageKey])) {
                $this->aStorage[$sStorageKey] = [];
            }
            return true;
        }
        $this->aStorage[$sStorageKey] = [];
        return false;
    }

    /**
     * @param string       $sStorageKey
     * @param string|array $sKey
     * @param null         $xVal
     */
    public function setItem($sStorageKey, $sKey, $xVal = null)
    {
        if (is_array($sKey) && $xVal === null) {
            // формат SetItem($sStorageKey, array $data)
            // нормализуем передаваемый массив
            $aData = $this->_keysArray($sKey);
        } else {
            // формат SetItem($sStorageKey, $sKey, $xVal)
            if ($this->nSaveMode == self::SAVE_MODE_ARR) {
                // нормализуем
                $aData = $this->_keysArray(array($sKey => $xVal));
            } else {
                // сохраняем как есть
                $aData = [$sKey => $xVal];
            }
        }
        $this->setStorage($sStorageKey, $aData, false);
    }

    /**
     * Возвращает данные из хранилища
     *
     * @param string $sStorageKey
     *
     * @return  array
     */
    public function getStorage($sStorageKey)
    {
        if ($sStorageKey) {
            if (isset($this->aStorage[$sStorageKey])) {
                return $this->aStorage[$sStorageKey];
            } else {
                return [];
            }
        }
        return $this->aStorage;
    }

    /**
     * @param $sStorageKey
     *
     * @return array
     */
    public function getStoragePlain($sStorageKey)
    {
        $aData = $this->getStorage($sStorageKey);
        return $this->_keysPlain($aData);
    }

    /**
     * @param $sStorageKey
     *
     * @return array
     */
    public function getStorageStr($sStorageKey)
    {
        $aData = $this->getStorage($sStorageKey);
        return $this->_keysPlain($aData, true);
    }

    /**
     * Returns item from storage by key
     *
     * @param string       $sStorageKey
     * @param string|array $xKey
     *
     * @return mixed|null
     */
    public function getStorageItem($sStorageKey, $xKey)
    {
        if ($this->nSaveMode !== self::SAVE_MODE_ARR) {
            return $this->getItem($sStorageKey, $xKey);
        }

        if (isset($this->aStorage[$sStorageKey])) {
            if ($xKey === null) {
                return $this->aStorage[$sStorageKey];
            } else {
                if (is_array($xKey)) {
                    $aKeys = array_values($xKey);
                } else {
                    $aKeys = explode('.', $xKey);
                }
                switch (count($aKeys)) {
                    case 1:
                        if (isset($this->aStorage[$sStorageKey][$aKeys[0]])) {
                            return $this->aStorage[$sStorageKey][$aKeys[0]];
                        }
                        break;
                    case 2:
                        if (isset($this->aStorage[$sStorageKey][$aKeys[0]][$aKeys[1]])) {
                            return $this->aStorage[$sStorageKey][$aKeys[0]][$aKeys[1]];
                        }
                        break;
                    case 3:
                        if (isset($this->aStorage[$sStorageKey][$aKeys[0]][$aKeys[1]][$aKeys[2]])) {
                            return $this->aStorage[$sStorageKey][$aKeys[0]][$aKeys[1]][$aKeys[2]];
                        }
                        break;
                    case 4:
                        if (isset($this->aStorage[$sStorageKey][$aKeys[0]][$aKeys[1]][$aKeys[2]][$aKeys[3]])) {
                            return $this->aStorage[$sStorageKey][$aKeys[0]][$aKeys[1]][$aKeys[2]][$aKeys[3]];
                        }
                        break;
                    case 5:
                        if (isset($this->aStorage[$sStorageKey][$aKeys[0]][$aKeys[1]][$aKeys[2]][$aKeys[3]][$aKeys[4]])) {
                            return $this->aStorage[$sStorageKey][$aKeys[0]][$aKeys[1]][$aKeys[2]][$aKeys[3]][$aKeys[4]];
                        }
                        break;
                    case 6:
                        if (isset($this->aStorage[$sStorageKey][$aKeys[0]][$aKeys[1]][$aKeys[2]][$aKeys[3]][$aKeys[4]][$aKeys[5]])) {
                            return $this->aStorage[$sStorageKey][$aKeys[0]][$aKeys[1]][$aKeys[2]][$aKeys[3]][$aKeys[4]][$aKeys[5]];
                        }
                        break;
                    case 7:
                        if (isset($this->aStorage[$sStorageKey][$aKeys[0]][$aKeys[1]][$aKeys[2]][$aKeys[3]][$aKeys[4]][$aKeys[5]][$aKeys[6]])) {
                            return $this->aStorage[$sStorageKey][$aKeys[0]][$aKeys[1]][$aKeys[2]][$aKeys[3]][$aKeys[4]][$aKeys[5]][$aKeys[6]];
                        }
                        break;
                    case 8:
                        if (isset($this->aStorage[$sStorageKey][$aKeys[0]][$aKeys[1]][$aKeys[2]][$aKeys[3]][$aKeys[4]][$aKeys[5]][$aKeys[6]][$aKeys[7]])) {
                            return $this->aStorage[$sStorageKey][$aKeys[0]][$aKeys[1]][$aKeys[2]][$aKeys[3]][$aKeys[4]][$aKeys[5]][$aKeys[6]][$aKeys[7]];
                        }
                        break;
                    case 9:
                        if (isset($this->aStorage[$sStorageKey][$aKeys[0]][$aKeys[1]][$aKeys[2]][$aKeys[3]][$aKeys[4]][$aKeys[5]][$aKeys[6]][$aKeys[7]][$aKeys[8]])) {
                            return $this->aStorage[$sStorageKey][$aKeys[0]][$aKeys[1]][$aKeys[2]][$aKeys[3]][$aKeys[4]][$aKeys[5]][$aKeys[6]][$aKeys[7]][$aKeys[8]];
                        }
                        break;
                    default:
                        $xData = $this->aStorage[$sStorageKey];
                        foreach ($aKeys as $sK) {
                            if (isset($xData[$sK])) {
                                $xData = $xData[$sK];
                            } else {
                                return [];
                            }
                        }
                        return $xData;
                }
            }
        }
        return null;
    }


    /**
     * Возвращает ссылку на элемент данных
     *
     * @param string       $sStorageKey
     * @param string|array $xKeys
     *
     * @return  array|null
     */
    public function getItem($sStorageKey, $xKeys)
    {
        return $this->_checkItem($sStorageKey, $xKeys, false, false);
    }

    /**
     * Возвращает ссылку на элемент данных
     *
     * @param string       $sStorageKey
     * @param string|array $aKeys
     * @param bool         $bAutocreate
     *
     * @return  mixed|null
     */
    public function getItemPtr($sStorageKey, $aKeys, $bAutocreate = false)
    {
        return $this->_checkItem($sStorageKey, $aKeys, $bAutocreate, false);
    }

    /**
     * Проверка наличия ключа
     *
     * @param string       $sStorageKey
     * @param string|array $aKeys
     *
     * @return mixed
     */
    public function isExists($sStorageKey, $aKeys)
    {
        return $this->_checkItem($sStorageKey, $aKeys, false, true);
    }

}

// EOF