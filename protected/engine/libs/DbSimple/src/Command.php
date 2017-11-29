<?php

namespace avadim\DbSimple;

/**
 * Class DbSimple_Command
 * SQL-command class with parameters
 */
class Command
{
    /** @var  Database */
    protected $_db;

    /** @var  string */
    protected $_sql;

    /** @var  array */
    protected $_values;

    /** @var  array */
    protected $_args;

    public function __construct($oDbSimple, $sSql, $aValues = [])
    {
        $this->_db = $oDbSimple;
        $this->_sql = $sSql;
        $this->bind($aValues);
    }

    /**
     * @return $this
     */
    public function bind()
    {
        $aArgs = func_get_args();
        if (count($aArgs) == 2 && is_string($aArgs[0])) {
            $aArgs = array($aArgs[0] => $aArgs[1]);
        }
        if (is_array($aArgs[0]) && count($aArgs[0])) {
            foreach($aArgs[0] as $sKey => $xVal) {
                $this->_values[$sKey] = $xVal;
            }
        }
        return $this;
    }

    /**
     * @param $matches
     *
     * @return mixed
     */
    public function _prepareValues($matches)
    {
        if (isset($this->_values[$matches[2]])) {
            $this->_args[] = $this->_values[$matches[2]];
        } else {
            $this->_args[] = null;
        }
        return $matches[1];
    }

    /**
     * @return mixed
     */
    public function query()
    {
        $this->_args = [];
        $sSql = preg_replace_callback('/(\?[a-z\#]?)(:\w+)/si', [$this, '_prepareValues'], $this->_sql);
        array_unshift($this->_args, $sSql);
        $total = false;

        return $this->_db->_query($this->_args, $total);
    }

    /**
     * @return mixed
     */
    public function select()
    {
        return $this->query();
    }

    /**
     * @return array
     */
    public function selectRow()
    {
        $rows = $this->query();
        if (is_array($rows)) {
            if (!count($rows)) {
                return array();
            } elseif(count($rows) > 1) {
                return array_shift($rows);
            }
        }
        return $rows;
    }

    /**
     * @return mixed
     */
    public function selectCol()
    {
        $rows = $this->query();
        if (!is_array($rows)) {
            return $rows;
        }
        Database::firstColumnArray($rows);

        return $rows;
    }

    /**
     * @return mixed
     */
    public function selectCell()
    {
        $rows = $this->query();
        if (!is_array($rows)) {
            return $rows;
        }
        if (!count($rows)) {
            return null;
        }
        $row = array_shift($rows);
        if (!is_array($row)) {
            return $row;
        }
        return array_shift($row);
    }

}

// EOF