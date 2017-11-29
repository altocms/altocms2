<?php
/**
 * DbSimple_Generic: universal database connected by DSN.
 * (C) Dk Lab, http://en.dklab.ru
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 * See http://www.gnu.org/copyleft/lesser.html
 *
 * Use static DbSimple_Generic::connect($dsn) call if you don't know
 * database type and parameters, but have its DSN.
 *
 * Additional keys can be added by appending a URI query string to the
 * end of the DSN.
 *
 * The format of the supplied DSN is in its fullest form:
 *   phptype(dbsyntax)://username:password@protocol+hostspec/database?option=8&another=true
 *
 * Most variations are allowed:
 *   phptype://username:password@protocol+hostspec:110//usr/db_file.db?mode=0644
 *   phptype://username:password@hostspec/database_name
 *   phptype://username:password@hostspec
 *   phptype://username@hostspec
 *   phptype://hostspec/database
 *   phptype://hostspec
 *   phptype(dbsyntax)
 *   phptype
 *
 * Parsing code is partially grabbed from PEAR DB class,
 * initial author: Tomas V.V.Cox <cox@idecnet.com>.
 *
 * Contains 3 classes:
 * - DbSimple_Generic: database factory class
 * - DbSimple_Generic_Database: common database methods
 * - DbSimple_Generic_Blob: common BLOB support
 * - DbSimple_Generic_LastError: error reporting and tracking
 *
 * Special result-set fields:
 * - ARRAY_KEY* ("*" means "anything")
 * - PARENT_KEY
 *
 * Transforms:
 * - GET_ATTRIBUTES
 * - CALC_TOTAL
 * - GET_TOTAL
 * - UNIQ_KEY
 *
 * Query attributes:
 * - BLOB_OBJ
 * - CACHE
 *
 * @author Dmitry Koterov, http://forum.dklab.ru/users/DmitryKoterov/
 * @author Konstantin Zhinko, http://forum.dklab.ru/users/KonstantinGinkoTit/
 *
 * @version 2.x $Id$
 */

namespace avadim\DbSimple;

/**
 * DbSimple factory.
 */
class DbSimple
{
    /**
     * Universal static function to connect ANY database using DSN syntax.
     * Choose database driver according to DSN. Return new instance
     * of this driver.
     *
     * @param mixed $dsn
     *
     * @return Database
     */
    public static function connect($dsn)
    {
        // Load database driver and create its instance.
        $parsedDSN = static::parseDSN($dsn);
        if (!$parsedDSN) {
            $dummy = null;
            return $dummy;
        }
        $class = __NAMESPACE__ . '\\Driver\\' . ucfirst($parsedDSN['scheme']);
        /** @var Database $oDb */
        $oDb = new $class($parsedDSN);
        if (isset($parsedDSN['table_prefix'])) {
            $oDb->setIdentPrefix($parsedDSN['table_prefix']);
        }
        $oDb->setCachePrefix(md5(serialize($parsedDSN['dsn'])));

        return $oDb;
    }

    /**
     * @param $dsn
     *
     * @return DbConnect
     */
    public static function create($dsn)
    {
        return new DbConnect($dsn);
    }

    /**
     * array parseDSN(mixed $dsn)
     * Parse a data source name.
     * See parse_url() for details.
     *
     * @param mixed $dsn
     *
     * @return array|null
     */
    public static function parseDSN($dsn)
    {
        if (is_array($dsn)) {
            return $dsn;
        }
        $parsed = parse_url($dsn);
        if (!$parsed) {
            return null;
        }
        $params = null;
        if (!empty($parsed['query'])) {
            $query = $parsed['query'];
            unset($parsed['query']);
            parse_str($query, $params);
            $parsed += $params;
        }
        $parsed['dsn'] = $dsn;

        return $parsed;
    }

}

// EOF


