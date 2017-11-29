<?php

namespace avadim\DbSimple;

/**
 * Database BLOB.
 * Can read blob chunk by chunk, write data to BLOB.
 */
interface Blob
{
    /**
     * string read(int $length)
     * Returns following $length bytes from the blob.
     */
    public function read($len);

    /**
     * string write($data)
     * Appends data to blob.
     */
    public function write($data);

    /**
     * int length()
     * Returns length of the blob.
     */
    public function length();

    /**
     * blobid close()
     * Closes the blob. Return its ID. No other way to obtain this ID!
     */
    public function close();
}

// EOF