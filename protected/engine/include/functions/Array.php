<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

use \alto\engine\generic\DataArray;

/**
 * Array functions for Alto CMS
 */
class AltoFunc_Array
{
    /**
     * Рекурсивно изменяет элементы массива, добавляя ко всем значениям (кроме объектов) префикс и суффикс
     *
     * @param   array   $aData
     * @param   string  $sBefore
     * @param   string  $sAfter
     *
     * @return  array
     */
    public static function changeValues($aData, $sBefore = '', $sAfter = '')
    {
        foreach ($aData as $xKey => $xValue) {
            if (is_array($xValue)) {
                $array[$xKey] = static::ChangeValues($xValue, $sBefore, $sAfter);
            } elseif (!is_object($xValue)) {
                $aData[$xKey] = $sBefore . $aData[$xKey] . $sAfter;
            }
        }
        return $aData;
    }

    /**
     * Меняет числовые ключи массива на их значения
     * Т.е. если ключ - число, а значение элемента - строка, то они меняются местами
     *
     * @param   array   $aData
     * @param   mixed   $xDefValue
     *
     * @return  array
     */
    public static function flipIntKeys($aData, $xDefValue = 1)
    {
        if (empty($aData)) {
            $aData = array();
        } elseif (!is_array($aData) && !($aData instanceof DataArray)) {
            $aData = (array)$aData;
        }
        $aResult = array();
        foreach ($aData as $xKey => $xValue) {
            if (is_int($xKey) && is_string($xValue) && $xValue) {
                $aResult[$xValue] = $xDefValue;
            } else {
                $aResult[$xKey] = $xValue;
            }
        }
        return $aResult;
    }

    /**
     * Сортировка массива данных по заданному массиву ключей
     *
     * @param   array   $aData
     * @param   array   $aKeys
     *
     * @return  array
     */
    public static function sortByKeysArray($aData, $aKeys)
    {
        $aResult = array();
        foreach ($aKeys as $xKey) {
            if (isset($aData[$xKey])) {
                $aResult[$xKey] = $aData[$xKey];
            }
        }
        return $aResult;
    }

    /**
     * Сливает рекурсивно два массива с сохранением ключей
     *
     * @param   array   $aData1
     * @param   array   $aData2
     *
     * @return  array
     */
    public static function merge($aData1, $aData2)
    {
        if (empty($aData1)) {
            $aData1 = array();
        } elseif (!is_array($aData1) && !($aData1 instanceof DataArray)) {
            $aData1 = (array)$aData1;
        }
        if ($aData2) {
            foreach ($aData2 as $sKey => $xVal) {
                $bIsKeyInt = false;
                if (is_array($xVal)) {
                    foreach ($xVal as $k => $v) {
                        if (is_int($k)) {
                            $bIsKeyInt = true;
                            break;
                        }
                    }
                }
                if (is_array($xVal) && !$bIsKeyInt && isset($aData1[$sKey])) {
                    $aData1[$sKey] = static::Merge($aData1[$sKey], $xVal);
                } else {
                    $aData1[$sKey] = $xVal;
                }
            }
        }
        return $aData1;
    }

    /**
     * Рекурсивное сливание двух массивов
     * Массивы (и их элементы) могут быть как ассоциативными, так и обычными.
     * Если ключ элемента строковый, то он заменяет соответствующий элемент в результирующем массиве.
     * Если ключ элемента числовой, то он добавляется в соответствующий элемент результирующего массива.
     *
     * @param array $aData1
     * @param array $aData2
     *
     * @return array
     */
    public static function MergeCombo($aData1, $aData2)
    {
        if (empty($aData1)) {
            $aData1 = array();
        } elseif (!is_array($aData1) && !($aData1 instanceof DataArray)) {
            $aData1 = (array)$aData1;
        }
        if ($aData2) {
            foreach ($aData2 as $xKey => $xVal) {
                if (is_int($xKey)) {
                    $aData1[] = $xVal;
                } else {
                    if (is_array($xVal) && isset($aData1[$xKey])) {
                        $aData1[$xKey] = static::MergeCombo($aData1[$xKey], $xVal);
                    } else {
                        $aData1[$xKey] = $xVal;
                    }
                }
            }
        }
        return $aData1;
    }

    /**
     * Рекурсивный вариант array_keys
     *
     * @param  array $aData     Массив
     *
     * @return array|bool
     */
    public static function keysRecursive($aData, $sDelimiter = '.')
    {
        if (!is_array($aData) && !($aData instanceof DataArray)) {
            return false;
        }
        $aKeys = array_keys($aData);
        foreach ($aKeys as $k => $v) {
            if ($aAppend = static::KeysRecursive($aData[$v])) {
                unset($aKeys[$k]);
                foreach ($aAppend as $sNewKey) {
                    $aKeys[] = $v . $sDelimiter . $sNewKey;
                }
            }
        }
        return $aKeys;
    }

    /**
     * Преобразует строку в массив
     *
     * @param   string|array    $sStr
     * @param   string          $sSeparator
     * @param   bool            $bSkipEmpty
     *
     * @return  array
     */
    public static function str2Array($sStr, $sSeparator = ',', $bSkipEmpty = false)
    {
        if (!is_string($sStr) && !is_array($sStr) && !($sStr instanceof DataArray)) {
            return (array)$sStr;
        }
        if (is_array($sStr)) {
            $aData = $sStr;
        } else {
            $aData = explode($sSeparator, $sStr);
        }

        $aResult = [];
        foreach ($aData as $xKey => $sVal) {
            if ($sVal || !$bSkipEmpty) {
                $aResult[$xKey] = trim($sVal);
            }
        }
        return $aResult;
    }

    /**
     * Преобразует строку в массив целых чисел
     *
     * @param   string|array    $sStr
     * @param   string          $sSeparator
     * @param   bool            $bUnique
     *
     * @return  array
     */
    public static function str2ArrayInt($sStr, $sSeparator = ',', $bUnique = true)
    {
        $aData = static::str2Array($sStr, $sSeparator, $bUnique);
        $aResult = [];
        foreach ($aData as $sItem) {
            $aResult[] = (int)$sItem;
        }
        if ($bUnique) {
            $aResult = array_unique($aResult);
        }
        return $aResult;
    }

    /**
     * Преобразует значение в массив
     * <pre>
     * Значение               Результат
     * ---------------------+--------------------
     *   null               | array()
     *   false              | array(false)
     *   array(true)        | array(true)
     *   array(true, false) | array(true, false)
     *   'a, b'             | array('a', 'b')
     *   array('a, b')      | array('a', 'b')
     *   array('a', 'b')    | array('a', 'b')
     * ---------------------+--------------------
     * </pre>
     *
     * @param mixed  $xVal
     * @param string $sSeparator
     * @param bool   $bSkipEmpty
     *
     * @return array
     */
    public static function val2Array($xVal, $sSeparator = ',', $bSkipEmpty = false)
    {
        if (is_array($xVal) && (count($xVal) == 1) && isset($xVal[0]) && strpos($xVal[0], ',')) {
            $aResult = static::Str2Array($xVal[0], $sSeparator, $bSkipEmpty);
        } elseif (is_array($xVal)) {
            $aResult = $xVal;
        } elseif (is_null($xVal)) {
            $aResult = array();
        } elseif (!is_string($xVal)) {
            $aResult = (array)$xVal;
        } else {
            $aResult = static::Str2Array($xVal, $sSeparator, $bSkipEmpty);
        }
        return $aResult;
    }

    /**
     * Returns the first key of array
     *
     * @param array $aData
     *
     * @return mixed
     */
    public static function firstKey($aData)
    {
        if (is_array($aData) || ($aData instanceof DataArray)) {
            $aKeys = array_keys($aData);
            return reset($aKeys);
        }
        return null;
    }

    /**
     * Returns the last key of array
     *
     * @param array $aData
     *
     * @return mixed
     */
    public static function lastKey($aData)
    {
        if (is_array($aData) || ($aData instanceof DataArray)) {
            $aKeys = array_keys($aData);
            return end($aKeys);
        }
        return null;
    }

    /**
     * Search string in array with case-insensitive string comparison
     *
     * @param string $sStr
     * @param array  $aArray
     *
     * @return bool
     */
    public static function strInArray($sStr, $aArray)
    {
        $sStr = mb_strtolower($sStr);
        foreach ($aArray as $sCompare) {
            $sCompare = mb_strtolower($sCompare);
            if (strcmp($sStr, mb_strtolower($sCompare)) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the values from a single column of the input array, identified by the columnKey
     *
     * @param array  $aArray
     * @param string $sColumnKey
     * @param null   $sIndexKey
     *
     * @return array|bool
     */
    public static function column($aArray, $sColumnKey, $sIndexKey = null)
    {
        if (!is_array($aArray)) {
            return false;
        }
        return array_column($aArray, $sColumnKey, $sIndexKey);
    }

    /**
     * @param array $aArray
     *
     * @return string
     */
    public static function hash($aArray)
    {
        array_multisort($aArray);
        return md5(json_encode($aArray));
    }

    /**
     * Property key for entities array sorting
     *
     * @var string
     */
    static public $sSortProp = '';
    /**
     * Direction for entities array sorting
     *
     * @var string
     */
    static public $iSortDirect = 1;

    /**
     * Sort an array of enityes with maintain index association
     *
     * @param array  $aEntities
     * @param string $sProp
     * @param bool   $bReverse
     *
     * @return array
     */
    public static function sortEntities($aEntities, $sProp, $bReverse = false)
    {
        if ($sProp && is_array($aEntities) && count($aEntities)) {
            self::$sSortProp = $sProp;
            if ($bReverse) {
                self::$iSortDirect = -1;
            } else {
                self::$iSortDirect = 1;
            }
            uasort($aEntities, [__CLASS__, '_sortByProp']);
        }
        return $aEntities;
    }

    /**
     * Callback function for entities array sorting
     *
     * @param Entity $oEntity1
     * @param Entity $oEntity2
     *
     * @return int
     */
    public static function _sortByProp($oEntity1, $oEntity2)
    {
        if ($oEntity1->getProp(self::$sSortProp) == $oEntity2->getProp(self::$sSortProp)) {
            return 0;
        }
        return ($oEntity1->getProp(self::$sSortProp) < $oEntity2->getProp(self::$sSortProp)) ? -1 * self::$iSortDirect : 1 * self::$iSortDirect;
    }

}

// EOF