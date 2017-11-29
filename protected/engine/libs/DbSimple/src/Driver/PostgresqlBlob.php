<?php

namespace avadim\DbSimple\Driver;

use avadim\DbSimple\Blob;

class PostgresqlBlob implements Blob
{
    protected $blob; // resourse link
    protected $id;
    protected $database;

    public function __construct(&$database, $id=null)
    {
        $this->database =& $database;
        $this->database->transaction();
        $this->id = $id;
        $this->blob = null;
    }

    function read($len)
    {
        if ($this->id === false) {
            return '';
        } // wr-only blob
        if (!($e=$this->_firstUse())) {
            return $e;
        }
        $data = @pg_lo_read($this->blob, $len);
        if ($data === false) {
            return $this->_setDbError('read');
        }
        return $data;
    }

    function write($data)
    {
        if (!($e=$this->_firstUse())) {
            return $e;
        }
        $ok = @pg_lo_write($this->blob, $data);
        if ($ok === false) {
            return $this->_setDbError('add data to');
        }
        return true;
    }

    function close()
    {
        if (!($e=$this->_firstUse())) {
            return $e;
        }
        if ($this->blob) {
            $id = @pg_lo_close($this->blob);
            if ($id === false) {
                return $this->_setDbError('close');
            }
            $this->blob = null;
        } else {
            $id = null;
        }
        $this->database->commit();

        return $this->id? $this->id : $id;
    }

    function length()
    {
        if (!($e=$this->_firstUse())) {
            return $e;
        }

        @pg_lo_seek($this->blob, 0, PGSQL_SEEK_END);
        $len = @pg_lo_tell($this->blob);
        @pg_lo_seek($this->blob, 0, PGSQL_SEEK_SET);

        if (!$len) {
            return $this->_setDbError('get length of');
        }
        return $len;
    }

    function _setDbError($query)
    {
        $hId = $this->id === null? "null" : ($this->id === false? "false" : $this->id);
        $query = "-- $query BLOB $hId";
        $this->database->_setDbError($query);
    }

    // Called on each blob use (reading or writing).
    function _firstUse()
    {
        // BLOB opened - do nothing.
        if (is_resource($this->blob)) {
            return true;
        }

        // Open or create blob.
        if ($this->id !== null) {
            $this->blob = @pg_lo_open($this->database->link, $this->id, 'rw');
            if ($this->blob === false) {
                return $this->_setDbError('open');
            }
        } else {
            $this->id = @pg_lo_create($this->database->link);
            $this->blob = @pg_lo_open($this->database->link, $this->id, 'w');
            if ($this->blob === false) {
                return $this->_setDbError('create');
            }
        }
        return true;
    }
}

// EOF