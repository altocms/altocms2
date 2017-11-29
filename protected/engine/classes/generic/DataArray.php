<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

namespace alto\engine\generic;

/**
 * Class DataArray
 *
 * Class allows to work with an object as a common array.
 * Also also makes it possible to access nested array elements via combined index using a separator
 *
 * <pre>
 * $aArray = array(
 *   'a' => 'one',
 *   'b' => array(
 *     'c' => 'two',
 *     'd' => 'three',
 *   ),
 * );
 * $data = new DataArray($aArray);
 * var_dump($data['a']); // result: 'one'
 * var_dump($data['b']); // result: ['c' => 'two', 'd' => 'three']
 * var_dump($data['b.d']); // result: 'three'
 * var_dump($data['c.f']); // result: null
 * </pre>
 */
class DataArray extends \ArrayObject
{
    protected $sDelimiter = '.';
    protected $aQuickMap = [];

    /**
     * Construct a new ArrayData object
     *
     * @param array $aData
     */
    public function __construct($aData = [])
    {
        //parent::__construct($data, ArrayObject::ARRAY_AS_PROPS);
        parent::__construct((array)$aData, \ArrayObject::STD_PROP_LIST);
    }

    /**
     * Sets the index delimiter
     *
     * @param string $sDelimiter
     */
    public function setDelimiter($sDelimiter)
    {
        $this->sDelimiter = $sDelimiter;
    }

    /**
     * Returns the index delimiter
     *
     * @return string
     */
    public function getDelimiter()
    {
        return $this->sDelimiter;
    }

    /**
     * Check specified index (used in isset(...))
     *
     * @param mixed $xIndex
     *
     * @return bool
     */
    public function offsetExists($xIndex)
    {
        if (!is_string($xIndex) || !strpos($xIndex, $this->sDelimiter)) {
            return parent::offsetExists($xIndex);
        } else {
            $aPath = explode($this->sDelimiter, $xIndex);
            $xItem = null;
            foreach($aPath as $iNum => $sPiece) {
                if ($iNum === 0) {
                    if (parent::offsetExists($sPiece)) {
                        $xItem = parent::offsetGet($sPiece);
                    } else {
                        return false;
                    }
                } else {
                    if (is_array($xItem) && isset($xItem[$sPiece])) {
                        $xItem = $xItem[$sPiece];
                    } else {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Returns the value at the specified index
     *
     * @param string|int $xIndex
     *
     * @return mixed|null
     */
    public function offsetGet($xIndex)
    {
        if (is_string($xIndex)) {
            $xIndex = trim($xIndex, $this->sDelimiter);
        }
        if (isset($this->aQuickMap[$xIndex])) {
            $xResult = $this->aQuickMap[$xIndex];
        } else {
            $xResult = null;
        }

        if (!is_string($xIndex) || !strpos($xIndex, $this->sDelimiter)) {
            if (parent::offsetExists($xIndex)) {
                $xResult = parent::offsetGet($xIndex);
            }
        } else {
            $aPath = explode($this->sDelimiter, $xIndex);
            $xResult = null;
            foreach($aPath as $sPiece) {
                if (is_null($xResult)) {
                    if (parent::offsetExists($sPiece)) {
                        $xResult = parent::offsetGet($sPiece);
                    }
                } else {
                    if (isset($xResult[$sPiece])) {
                        $xResult = $xResult[$sPiece];
                    } else {
                        $xResult = null;
                    }
                }
                if (is_null($xResult)) {
                    break;
                }
            }
        }
        if (null !== $xResult) {
            $this->aQuickMap[$xIndex] = $xResult;
        }
        return $xResult;
    }

    /**
     * Sets the value at the specified $xIndex to $xValue
     * $xIndex can be combined via the delimiter
     *
     * @param mixed $xIndex
     * @param mixed $xValue
     */
    public function offsetSet($xIndex, $xValue)
    {
        if (is_string($xIndex) && strpos($xIndex, $this->sDelimiter)) {
            $xIndex = trim($xIndex, $this->sDelimiter);
            $aPath = explode($this->sDelimiter, $xIndex);
            $aData = [];
            $xItem = null;
            foreach($aPath as $sPiece) {
                if (is_null($xItem)) {
                    $aData[$sPiece] = [];
                    $xItem = &$aData[$sPiece];
                } else {
                    $xItem[$sPiece] = [];
                    $xItem = &$xItem[$sPiece];
                }
            }
            $xItem = $xValue;
            $xIndex = \F::Array_FirstKey($aData);
            $xValue = reset($aData);
        }
        if (is_array($xValue) && parent::offsetExists($xIndex)) {
            $xOldValue = parent::offsetGet($xIndex);
            if (is_array($xOldValue)) {
                $xValue = \F::Array_MergeCombo($xOldValue, $xValue);
            }
        }
        parent::offsetSet($xIndex, $xValue);
        $this->aQuickMap = [];
    }

    /**
     * Merges new data with currents
     *
     * @param $aData
     */
    public function merge($aData)
    {
        foreach($aData as $xIndex => $xValue) {
            $this[$xIndex] = $xValue;
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return 'Array';
    }

}

// EOF