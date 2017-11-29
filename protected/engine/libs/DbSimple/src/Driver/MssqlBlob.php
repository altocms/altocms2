<?php

namespace avadim\DbSimple\Driver;

class MssqlBlob implements \avadim\DbSimple\Blob
{
    // Mssql does not support separate BLOB fetching.
    protected $blobdata = null;
    protected $curSeek  = 0;

    public function __construct(&$database, $blobdata=null)
    {
        $this->blobdata = $blobdata;
        $this->curSeek = 0;
    }

    public function read($len)
    {
        $p = $this->curSeek;
        $this->curSeek = min($this->curSeek + $len, strlen($this->blobdata));

        return substr($this->blobdata, $this->curSeek, $len);
    }

    public function write($data)
    {
        $this->blobdata .= $data;
    }

    public function close()
    {
        return $this->blobdata;
    }

    public function length()
    {
        return strlen($this->blobdata);
    }
}

// EOF