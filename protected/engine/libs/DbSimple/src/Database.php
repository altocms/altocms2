<?php

/**
 * DbSimple_Database: Base class for all databases.
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
 * - DbSimple_Database: common database methods
 * - DbSimple_Blob: common BLOB support
 * - DbSimple_LastError: error reporting and tracking
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
 * @author Ivan Borzenkov, http://forum.dklab.ru/users/Ivan1986/
 *
 * @version 2.x $Id$
 */

namespace avadim\DbSimple;

/**
 * Use this constant as placeholder value to skip optional SQL block [...].
 */
if (!defined('DBSIMPLE_SKIP')) {
    define('DBSIMPLE_SKIP', log(0));
}

/**
 * Names of special columns in result-set which is used
 * as array key (or karent key in forest-based resultsets) in
 * resulting hash.
 */
if (!defined('DBSIMPLE_ARRAY_KEY')) {
    define('DBSIMPLE_ARRAY_KEY', 'ARRAY_KEY');   // hash-based resultset support
}

if (!defined('DBSIMPLE_PARENT_KEY')) {
    define('DBSIMPLE_PARENT_KEY', 'PARENT_KEY'); // forrest-based resultset support
}

/**
 *
 * Base class for all databases.
 * Can create transactions and new BLOBs, parse DSNs.
 *
 * Logger is COMMON for multiple transactions.
 * Error handler is private for each transaction and database.
 */
abstract class Database extends DbError
{
    protected $_dsnArray;
    protected $_cachePrefix = '';
    protected $_className = '';

    protected $_logger = null;
    protected $_preformatter = null;
    protected $_tablefunc = null;
    protected $_cacher = null;
    protected $_placeholderArgs, $_placeholderNativeArgs, $_placeholderCache=array();
    protected $_placeholderNoValueFound;

    // Identifiers prefix (used for ?_ placeholder).
    protected $_identPrefix = '';

    // Queries statistics.
    protected $_statistics = [
        'time'  => 0,
        'count' => 0,
    ];

    /**
     * When string representation of row (in characters) is greater than this,
     * row data will not be logged.
     */
    protected $MAX_LOG_ROW_LEN = 128;

    /**
     * Public methods.
     */

    public function __construct($dsn)
    {
        parent::__construct();
        $this->_dsnArray = $dsn;
    }

    /**
     * Create new blob

     * @param null $blob_id
     *
     * @return mixed
     */
    public function blob($blob_id = null)
    {
        $this->_resetLastError();

        return $this->_performNewBlob($blob_id);
    }

    /**
     * Create new transaction
     *
     * @param null $mode
     *
     * @return mixed
     */
    public function transaction($mode=null)
    {
        $this->_resetLastError();
        $this->_logQuery('-- START TRANSACTION ' . $mode);

        return $this->_performTransaction($mode);
    }

    /**
     * Commit the transaction
     *
     * @return mixed
     */
    public function commit()
    {
        $this->_resetLastError();
        $this->_logQuery('-- COMMIT');

        return $this->_performCommit();
    }

    /**
     * Rollback the transaction
     *
     * @return mixed
     */
    public function rollback()
    {
        $this->_resetLastError();
        $this->_logQuery('-- ROLLBACK');

        return $this->_performRollback();
    }

    /**
     * mixed select(string $query [, $arg1] [,$arg2] ...)
     * Execute query and return the result.
     *
     * @param $query
     *
     * @return array|null
     */
    public function select($query)
    {
        $args = func_get_args();
        $total = false;

        return $this->_query($args, $total);
    }

    /**
     * Execute query and return the result.
     * Total number of found rows (independent to LIMIT) is returned in $total
     * (in most cases second query is performed to calculate $total).
     *
     * mixed selectPage(int &$total, string $query [, $arg1] [,$arg2] ...)
     *
     * @param $total
     * @param $query
     *
     * @return mixed
     */
    public function selectPage(&$total, $query)
    {
        $args = func_get_args();
        array_shift($args);
        $total = true;

        return $this->_query($args, $total);
    }

    /**
     * hash selectRow(string $query [, $arg1] [,$arg2] ...)
     * Return the first row of query result.
     * On errors return false and set last error.
     * If no one row found, return array()! It is useful while debugging,
     * because PHP DOES NOT generates notice on $row['abc'] if $row === null
     * or $row === false (but, if $row is empty array, notice is generated).
     */
    public function selectRow()
    {
        $args = func_get_args();
        $total = false;
        $rows = $this->_query($args, $total);
        if (!is_array($rows)) {
            return $rows;
        }
        if (!count($rows)) {
            return [];
        }
        reset($rows);

        return current($rows);
    }

    /**
     * array selectCol(string $query [, $arg1] [,$arg2] ...)
     * Return the first column of query result as array.
     */
    public function selectCol()
    {
        $args = func_get_args();
        $total = false;
        $rows = $this->_query($args, $total);
        if (!is_array($rows)) {
            return $rows;
        }
        $this->_shrinkLastArrayDimensionCallback($rows);

        return $rows;
    }

    /**
     * scalar selectCell(string $query [, $arg1] [,$arg2] ...)
     * Return the first cell of the first column of query result.
     * If no one row selected, return null.
     */
    public function selectCell()
    {
        $args = func_get_args();
        $total = false;
        $rows = $this->_query($args, $total);
        if (!is_array($rows)) {
            return $rows;
        }
        if (!count($rows)) {
            return null;
        }
        reset($rows);
        $row = current($rows);
        if (!is_array($row)) {
            return $row;
        }
        reset($row);

        return current($row);
    }

    /**
     * mixed query(string $query [, $arg1] [,$arg2] ...)
     * Alias for select(). May be used for INSERT or UPDATE queries.
     */
    public function query()
    {
        $args = func_get_args();
        $total = false;

        return $this->_query($args, $total);
    }

    /**
     * Enclose the string into database quotes correctly escaping
     * special characters. If $isIdent is true, value quoted as identifier
     * (e.g.: `value` in MySQL, "value" in Firebird, [value] in MSSQL).
     *
     * string escape(mixed $s, bool $isIdent=false)
     *
     * @param string $s
     * @param bool $isIdent
     *
     * @return string
     */
    public function escape($s, $isIdent=false)
    {
        return $this->_performEscape($s, $isIdent);
    }

    /**
     * Выполняет разворачивание плейсхолдеров без коннекта к базе
     * Нужно для сложных запросов, состоящих из кусков, которые полезно сохранить
     *
     * SubQuery subQuery(string $query [, $arg1] [,$arg2] ...)
     *
     * @return SubQuery
     */
    public function subQuery()
    {
        $args = func_get_args();
        $this->_expandPlaceholders($args,$this->_placeholderNativeArgs !== null);

        return new SubQuery($args);
    }


    /**
     * callback setLogger(callback $func)
     * Set query logger called before each query is executed.
     * Returns previous logger.
     *
     * @param $func
     *
     * @return mixed
     */
    public function setLogger($func)
    {
        $prev = $this->_logger;
        $this->_logger = $func;

        return $prev;
    }

    /**
     * callback setPreFormatter(callback $func)
     * Set query preformatter called before each query is executed.
     * Returns previous preformatter.
     */
    public function setPreFormatter($func)
    {
        $prev = $this->_preformatter;
        $this->_preformatter = $func;

        return $prev;
    }

    /**
     * @param callback $func
     *
     * @return callback
     */
    public function setTableNameFunc($func)
    {
        $prev = $this->_tablefunc;
        $this->_tablefunc = $func;

        return $prev;
    }

    /**
     * callback setCacher(callback $cacher)
     * Set cache mechanism called during each query if specified.
     * Returns previous handler.
     */
    public function setCacher(Zend_Cache_Backend_Interface $cacher=null)
    {
        $prev = $this->_cacher;
        $this->_cacher = $cacher;
        return $prev;
    }

    /**
     * string setIdentPrefix($prx)
     * Set identifier prefix used for $_ placeholder.
     */
    public function setIdentPrefix($prx)
    {
        $old = $this->_identPrefix;
        if ($prx !== null) {
            $this->_identPrefix = $prx;
        }
        return $old;
    }

    /**
     * string setCachePrefix($prx)
     * Set cache prefix used in key caclulation.
     */
    public function setCachePrefix($prx)
    {
        $old = $this->_cachePrefix;
        if ($prx !== null) {
            $this->_cachePrefix = $prx;
        }
        return $old;
    }

    /**
     * Задает имя класса строки
     *
     * <br>для следующего запроса каждая строка будет
     * заменена классом, конструктору которого передается
     * массив поле=>значение для этой строки
     *
     * @param string $name имя класса
     *
     * @return $this указатель на себя
     */
    public function setClassName($name)
    {
        $this->_className = $name;

        return $this;
    }

    /**
     * array getStatistics()
     * Returns various statistical information.
     */
    public function getStatistics()
    {
        return $this->_statistics;
    }


    /**
     * string _performEscape(mixed $s, bool $isIdent=false)
     */
    abstract protected function _performEscape($s, $isIdent=false);

    /**
     * object _performNewBlob($id)
     *
     * Returns new blob object.
     */
    abstract protected function _performNewBlob($id=null);

    /**
     * list _performGetBlobFieldNames($resultResource)
     * Get list of all BLOB field names in result-set.
     */
    abstract protected function _performGetBlobFieldNames($result);

    /**
     * mixed _performTransformQuery(array &$query, string $how)
     *
     * Transform query different way specified by $how.
     * May return some information about performed transform.
     */
    abstract protected function _performTransformQuery(&$queryMain, $how);


    /**
     * resource _performQuery($arrayQuery)
     * Must return:
     * - For SELECT queries: ID of result-set (PHP resource).
     * - For other  queries: query status (scalar).
     * - For error  queries: false (and call _setLastError()).
     */
    abstract protected function _performQuery($arrayQuery);

    /**
     * mixed _performFetch($resultResource)
     * Fetch ONE NEXT row from result-set.
     * Must return:
     * - For SELECT queries: all the rows of the query (2d arrray).
     * - For INSERT queries: ID of inserted row.
     * - For UPDATE queries: number of updated rows.
     * - For other  queries: query status (scalar).
     * - For error  queries: false (and call _setLastError()).
     */
    abstract protected function _performFetch($result);

    /**
     * mixed _performTransaction($mode)
     * Start new transaction.
     */
    abstract protected function _performTransaction($mode=null);

    /**
     * mixed _performCommit()
     * Commit the transaction.
     */
    abstract protected function _performCommit();

    /**
     * mixed _performRollback()
     * Rollback the transaction.
     */
    abstract protected function _performRollback();

    /**
     * string _performGetPlaceholderIgnoreRe()
     * Return regular expression which matches ignored query parts.
     * This is needed to skip placeholder replacement inside comments, constants etc.
     */
    protected function _performGetPlaceholderIgnoreRe()
    {
        return '';
    }

    /**
     * Returns marker for native database placeholder. E.g. in FireBird it is '?',
     * in PostgreSQL - '$1', '$2' etc.
     *
     * @param int $n Number of native placeholder from the beginning of the query (begins from 0!).
     *
     * @return string String representation of native placeholder marker (by default - '?').
     */
    protected function _performGetNativePlaceholderMarker($n)
    {
        return '?';
    }

    /**
     * array parseDSN(mixed $dsn)
     * Parse a data source name.
     * See parse_url() for details.
     *
     * @param $dsn
     *
     * @return array|null
     */
    protected function parseDSN($dsn)
    {
        if (is_array($dsn)) {
            return $dsn;
        }
        $parsed = @parse_url($dsn);
        if (!$parsed) {
            return null;
        }
        $params = null;
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $params);
            $parsed += $params;
        }
        $parsed['dsn'] = $dsn;

        return $parsed;
    }

    /**
     * @param mixed $v
     *
     * @return string
     */
    protected function _hash($v)
    {
        return md5(serialize($v));
    }

    /**
     * array _query($query, &$total)
     * @see _performQuery().
     */
    public function _query($query, &$total)
    {
        $this->_resetLastError();

        if ($this->_preformatter) {
            $query = call_user_func_array($this->_preformatter, $query);
        }
        // Fetch query attributes.
        $this->attributes = $this->_transformQuery($query, 'GET_ATTRIBUTES');

        // Modify query if needed for total counting.
        if ($total)
            $this->_transformQuery($query, 'CALC_TOTAL');

        $rows = false;
        $cache_it = false;
        // Кешер у нас либо null либо соответствует Zend интерфейсу
        if (!empty($this->attributes['CACHE']) && $this->_cacher) {

            $hash = $this->_cachePrefix . $this->_hash($query);
            // Getting data from cache if possible
            $fetchTime = $firstFetchTime = 0;
            $qStart    = microtime(true);
            $cacheData = unserialize($this->_cacher->load($hash));
            $queryTime = microtime(true) - $qStart;

            $invalCache = isset($cacheData['invalCache']) ? $cacheData['invalCache'] : null;
            $result     = isset($cacheData['result'])     ? $cacheData['result']     : null;
            $rows       = isset($cacheData['rows'])       ? $cacheData['rows']       : null;


            $cache_params = $this->attributes['CACHE'];

            // Calculating cache time to live
            $re = '/
                (?>
                    ([0-9]+)           #1 - hours
                h)? [ \t]*
                (?>
                    ([0-9]+)           #2 - minutes
                m)? [ \t]*
                (?>
                    ([0-9]+)           #3 - seconds
                s?)? (,)?
            /sx';
            $m = null;
            preg_match($re, $cache_params, $m);
            $ttl = (isset($m[3])?$m[3]:0)
                 + (isset($m[2])?$m[2]:0) * 60
                 + (isset($m[1])?$m[1]:0) * 3600;
            // Cutting out time param - now there are just fields for uniqKey or nothing
            $cache_params = trim(preg_replace($re, '', $cache_params, 1));

            $uniq_key = null;

            // UNIQ_KEY calculation
            if (!empty($cache_params)) {
                $dummy = null;
                // There is no need in query, cos' needle in $this->attributes['CACHE']
                $this->_transformQuery($dummy, 'UNIQ_KEY');
                $uniq_key = $this->select($dummy);
                $uniq_key = $this->_hash($uniq_key);
            }
            // Check TTL?
            $ok = empty($ttl) || $cacheData;

            // Invalidate cache?
            if ($ok && $uniq_key == $invalCache) {
                $this->_logQuery($query);
                $this->_logQueryStat($queryTime, $fetchTime, $firstFetchTime, $rows);

            }
            else $cache_it = true;
        }

        if (false === $rows || true === $cache_it) {
            $this->_logQuery($query);

            // Run the query (counting time).
            $qStart = microtime(true);
            $result = $this->_performQuery($query);
            $fetchTime = $firstFetchTime = 0;

            if (is_resource($result) || is_object($result)) {
                $rows = array();
                // Fetch result row by row.
                $fStart = microtime(true);
                $row = $this->_performFetch($result);
                $firstFetchTime = microtime(true) - $fStart;
                if (!empty($row)) {
                    $rows[] = $row;
                    while ($row=$this->_performFetch($result)) {
                        $rows[] = $row;
                    }
                }
                $fetchTime = microtime(true) - $fStart;
            } else {
                $rows = $result;
            }
            $queryTime = microtime(true) - $qStart;

            // Log query statistics.
            $this->_logQueryStat($queryTime, $fetchTime, $firstFetchTime, $rows);

            // Prepare BLOB objects if needed.
            if (is_array($rows) && !empty($this->attributes['BLOB_OBJ'])) {
                $blobFieldNames = $this->_performGetBlobFieldNames($result);
                foreach ($blobFieldNames as $name) {
                    for ($r = count($rows)-1; $r>=0; $r--) {
                        $rows[$r][$name] =& $this->_performNewBlob($rows[$r][$name]);
                    }
                }
            }

            // Transform resulting rows.
            $result = $this->_transformResult($rows);

            // Storing data in cache
            if ($cache_it && $this->_cacher)
            {
                $this->_cacher->save(
                    serialize(array(
                        'invalCache' => $uniq_key,
                        'result'     => $result,
                        'rows'       => $rows
                    )),
                    $hash,
                    [],
                    $ttl===0?false:$ttl
                );
            }

        }
        // Count total number of rows if needed.
        if (is_array($result) && $total) {
            $this->_transformQuery($query, 'GET_TOTAL');
            $total = call_user_func_array(array($this, 'selectCell'), $query);
        }

        if ($this->_className)
        {
            foreach($result as $k=>$v)
                $result[$k] = new $this->_className($v);
            $this->_className = '';
        }

        return $result;
    }

    /**
     * Transform query different way specified by $how.
     * May return some information about performed transform.
     *
     * @param array $query
     * @param string $how
     *
     * @return array|bool
     */
    private function _transformQuery(&$query, $how)
    {
        // Do overriden transformation.
        $result = $this->_performTransformQuery($query, $how);
        if ($result === true) return $result;
        // Common transformations.
        switch ($how) {
            case 'GET_ATTRIBUTES':
                // Extract query attributes.
                $options = [];
                $q = $query[0];
                $m = null;
                while (preg_match('/^ \s* -- [ \t]+ (\w+): ([^\r\n]+) [\r\n]* /sx', $q, $m)) {
                    $options[$m[1]] = trim($m[2]);
                    $q = substr($q, strlen($m[0]));
                }
                return $options;
            case 'UNIQ_KEY':
                $q = $this->attributes['CACHE'];
                $query = array();
                while(preg_match('/(\w+)\.\w+/sx', $q, $m)) {
                    $query[] = 'SELECT MAX('.$m[0].') AS M, COUNT(*) AS C FROM '.$m[1];
                    $q = substr($q, strlen($m[0]));
                }
                $query = "  -- UNIQ_KEY\n".
                    implode("\nUNION\n", $query);
                return true;
        }
        // No such transform.
        $this->_setLastError(-1, "No such transform type: $how", $query);
        return false;
    }

    /**
     * void _expandPlaceholders(array &$queryAndArgs, bool $useNative=false)
     * Replace placeholders by quoted values.
     * Modify $queryAndArgs.
     *
     * @param $queryAndArgs
     * @param bool $useNative
     */
    protected function _expandPlaceholders(&$queryAndArgs, $useNative=false)
    {
        $cacheCode = null;
        if ($this->_logger) {
            // Serialize is much faster than placeholder expansion. So use caching.
            $cacheCode = $this->_hash([$queryAndArgs, $useNative, $this->_identPrefix]);
            if (isset($this->_placeholderCache[$cacheCode])) {
                $queryAndArgs = $this->_placeholderCache[$cacheCode];
                return;
            }
        }

        if (!is_array($queryAndArgs)) {
            $queryAndArgs = [$queryAndArgs];
        }

        $this->_placeholderNativeArgs = $useNative? [] : null;
        $this->_placeholderArgs = array_reverse($queryAndArgs);

        $query = array_pop($this->_placeholderArgs); // array_pop is faster than array_shift

        // Do all the work.
        $this->_placeholderNoValueFound = false;
        $query = $this->_expandPlaceholdersFlow($query);

        if ($useNative) {
            array_unshift($this->_placeholderNativeArgs, $query);
            $queryAndArgs = $this->_placeholderNativeArgs;
        } else {
            $queryAndArgs = [$query];
        }

        if ($cacheCode) {
            $this->_placeholderCache[$cacheCode] = $queryAndArgs;
        }
    }


    /**
     * Do real placeholder processing.
     * Imply that all interval variables (_placeholder_*) already prepared.
     * May be called recurrent!
     */
    private function _expandPlaceholdersFlow($query)
    {
        if (strpos($query, '?') === false) {
            return $query;
        }
        $re = '{
            (?>
                # Ignored chunks.
                (?>
                    # Comment.
                    -- [^\r\n]*
                )
                  |
                (?>
                    # DB-specifics.
                    ' . trim($this->_performGetPlaceholderIgnoreRe()) . '
                )
            )
              |
            (?>
                # Optional blocks
                \{
                    # Use "+" here, not "*"! Else nested blocks are not processed well.
                    ( (?> (?>(\??)[^{}]+)  |  (?R) )* )             #1
                \}
            )
              |
            (?>
                # Placeholder
                (\?) ( [_dsafn&|\#]?\w* )                           #2 #3
            )
        }sx';
        $query = preg_replace_callback(
            $re,
            array($this, '_expandPlaceholdersCallback'),
            $query
        );
        return $query;
    }

    static protected $join = [
        '|' => ['inner' => ' AND ', 'outer' => ') OR (',],
        '&' => ['inner' => ' OR ', 'outer' => ') AND (',],
        'a' => ['inner' => ', ', 'outer' => '), (',],
    ];

    /**
     * string _expandPlaceholdersCallback(list $m)
     * Internal function to replace placeholders (see preg_replace_callback).
     */
    private function _expandPlaceholdersCallback($m)
    {
        // Placeholder.
        if (!empty($m[3])) {
            $type = $m[4];

            // Idenifier prefix.
            if ($type && $type[0] === '_') {
                return $this->_addPrefix2Table($m[0]);
            }

            // Value-based placeholder.
            if (!$this->_placeholderArgs) return 'DBSIMPLE_ERROR_NO_VALUE';
            $value = array_pop($this->_placeholderArgs);

            // Skip this value?
            if ($value === DBSIMPLE_SKIP) {
                $this->_placeholderNoValueFound = true;
                return '';
            }

            // First process guaranteed non-native placeholders.
            switch ($type) {
                case 's':
                    if (!($value instanceof SubQuery)) {
                        return 'DBSIMPLE_ERROR_VALUE_NOT_SUBQUERY';
                    }
                    return $value->get($this->_placeholderNativeArgs);
                case '|':
                case '&':
                case 'a':
                    if (!$value) $this->_placeholderNoValueFound = true;
                    if (!is_array($value)) {
                        return 'DBSIMPLE_ERROR_VALUE_NOT_ARRAY';
                    }
                    $parts = [];
                    $multi = []; //массив для двойной вложенности
                    $mult = $type !== 'a' || (is_int(key($value)) && is_array(current($value)));
                    foreach ($value as $prefix => $field) {
                        //превращаем $value в двумерный нуменованный массив
                        if (!is_array($field)) {
                            $field = [$prefix => $field];
                            $prefix = 0;
                        }
                        $prefix = is_int($prefix) ? '' :
                            $this->escape($this->_addPrefix2Table($prefix), true) . '.';
                        //для мультиинсерта очищаем ключи - их быть не может по синтаксису
                        if ($mult && $type==='a')
                            $field = array_values($field);
                        foreach ($field as $k => $v)
                        {
                            if ($v instanceof SubQuery)
                                $v = $v->get($this->_placeholderNativeArgs);
                            else
                            $v = $v === null? 'NULL' : $this->escape($v);
                            if (!is_int($k)) {
                                $k = $this->escape($k, true);
                                $parts[] = "$prefix$k=$v";
                            } else {
                                $parts[] = $v;
                            }
                        }
                        if ($mult)
                        {
                            $multi[] = implode(self::$join[$type]['inner'], $parts);
                            $parts = [];
                        }
                    }
                    return $mult ? implode(self::$join[$type]['outer'], $multi) : implode(', ', $parts);
                case '#':
                    // Identifier.
                    if (!is_array($value))
                    {
                        if ($value instanceof SubQuery)
                            return $value->get($this->_placeholderNativeArgs);
                        return $this->escape($this->_addPrefix2Table($value), true);
                    }
                    $parts = array();
                    foreach ($value as $table => $identifiers)
                    {
                        if (!is_array($identifiers))
                            $identifiers = array($identifiers);
                        $prefix = '';
                        if (!is_int($table)) {
                            $prefix = $this->escape($this->_addPrefix2Table($table), true) . '.';
                        }
                        foreach ($identifiers as $identifier) {
                            if ($identifier instanceof SubQuery) {
                                $parts[] = $identifier->get($this->_placeholderNativeArgs);
                            } elseif (!is_string($identifier)) {
                                return 'DBSIMPLE_ERROR_ARRAY_VALUE_NOT_STRING';
                            } else {
                                $parts[] = $prefix . ($identifier === '*' ? '*' : $this->escape($this->_addPrefix2Table($identifier), true));
                            }
                        }
                    }
                    return implode(', ', $parts);
                case 'n':
                    // NULL-based placeholder.
                    return empty($value)? 'NULL' : (int)$value;
            }

            // Native arguments are not processed.
            if ($this->_placeholderNativeArgs !== null) {
                $this->_placeholderNativeArgs[] = $value;
                return $this->_performGetNativePlaceholderMarker(count($this->_placeholderNativeArgs) - 1);
            }

            // In non-native mode arguments are quoted.
            if ($value === null) return 'NULL';
            switch ($type) {
                case '':
                    if (!is_scalar($value)) {
                        return 'DBSIMPLE_ERROR_VALUE_NOT_SCALAR';
                    }
                    return $this->escape($value);
                case 'd':
                    return (int)$value;
                case 'f':
                    return str_replace(',', '.', (float)$value);
            }
            // By default - escape as string.
            return $this->escape($value);
        }

        // Optional block.
        $prevPh = null;
        if (isset($m[1]) && strlen($block=$m[1])) {
            $prev  = $this->_placeholderNoValueFound;
            if ($this->_placeholderNativeArgs !== null) {
                $prevPh = $this->_placeholderNativeArgs;
            }

            // Проверка на {?  } - условный блок
            $skip = false;
            if ($m[2] === '?') {
                $skip = array_pop($this->_placeholderArgs) === DBSIMPLE_SKIP;
                $block[0] = ' ';
            }

            $block = $this->_expandOptionalBlock($block);

            if ($skip) {
                $block = '';
            }

            if ($this->_placeholderNativeArgs !== null) {
                if ($this->_placeholderNoValueFound) {
                    $this->_placeholderNativeArgs = $prevPh;
                }
            }
            $this->_placeholderNoValueFound = $prev; // recurrent-safe
            return $block;
        }

        // Default: skipped part of the string.
        return $m[0];
    }


    /**
     * Заменяет ?_ на текущий префикс
     *
     * @param string $table имя таблицы
     *
     * @return string имя таблицы
     */
    private function _addPrefix2Table($table)
    {
        if ($this->_tablefunc) {
            $iDbNum = isset($this->_dsnArray['db_num']) ? $this->_dsnArray['db_num'] : 0;
            $table = call_user_func($this->_tablefunc, $table, $iDbNum);
        } elseif (0 === strpos($table, '?_')) {
            $table = $this->_identPrefix . substr($table, 2);
        }
        return $table;
    }


    /**
     * Разбирает опциональный блок - условие |
     *
     * @param string $block блок, который нужно разобрать
     *
     * @return string что получается в результате разбора блока
     */
    private function _expandOptionalBlock($block)
    {
        $alts = [];
        $alt = '';
        $sub = 0;
        $exp = explode('|',$block);
        // Оптимизация, так как в большинстве случаев | не используется
        if (count($exp) === 1) {
            $alts = $exp;
        } else {
            foreach ($exp as $v) {
                // Реализуем автоматный магазин для нахождения нужной скобки
                // На суммарную парность скобок проверять нет необходимости - об этом заботится регулярка
                $sub += substr_count($v,'{');
                $sub -= substr_count($v,'}');
                if ($sub>0) {
                    $alt .= $v . '|';
                } else {
                    $alts[]=$alt.$v;
                    $alt = '';
                }
            }
        }
        $r='';
        foreach ($alts as $alt_block) {
            $this->_placeholderNoValueFound = false;
            $alt_block = $this->_expandPlaceholdersFlow($block);
            // Необходимо пройти все блоки, так как если пропустить оставшиесь,
            // то это нарушит порядок подставляемых значений
            if ($this->_placeholderNoValueFound === false && $r === '') {
                $r = ' ' . $alt_block . ' ';
            }
        }
        return $r;
    }

    /**
     * void _setLastError($code, $msg, $query)
     * Set last database error context.
     * Aditionally expand placeholders.
     *
     * @param $code
     * @param $msg
     * @param $query
     *
     * @return bool
     */
    protected function _setLastError($code, $msg, $query)
    {
        if (is_array($query)) {
            $this->_expandPlaceholders($query, false);
            $query = $query[0];
        }
        return parent::_setLastError($code, $msg, $query);
    }


    /**
     * Convert SQL field-list to COUNT(...) clause
     * (e.g. 'DISTINCT a AS aa, b AS bb' -> 'COUNT(DISTINCT a, b)').
     *
     * @param $fields
     *
     * @return string
     */
    protected function _fieldList2Count($fields)
    {
        $m = null;
        if (preg_match('/^\s* DISTINCT \s* (.*)/sx', $fields, $m)) {
            $fields = $m[1];
            $fields = preg_replace('/\s+ AS \s+ .*? (?=,|$)/sx', '', $fields);
            return "COUNT(DISTINCT $fields)";
        }
        return 'COUNT(*)';
    }


    /**
     * Transform resulting rows to various formats.
     *
     * @param array|string $rows
     *
     * @return array
     */
    private function _transformResult($rows)
    {
        // is not array
        if (!is_array($rows) || !$rows)
            return $rows;

        // Find ARRAY_KEY* AND PARENT_KEY fields in field list.
        $pk = null;
        $ak = array();
        foreach (array_keys(current($rows)) as $fieldName) {
            if (0 === strncasecmp($fieldName, DBSIMPLE_ARRAY_KEY, strlen(DBSIMPLE_ARRAY_KEY))) {
                $ak[] = $fieldName;
            } elseif (0 === strncasecmp($fieldName, DBSIMPLE_PARENT_KEY, strlen(DBSIMPLE_PARENT_KEY))) {
                $pk = $fieldName;
            }
        }

        if (!$ak) {
            return $rows;
        }

        natsort($ak); // sort ARRAY_KEY* using natural comparision
        // Tree-based array? Fields: ARRAY_KEY, PARENT_KEY
        if ($pk !== null) {
            return $this->_transformResultToForest($rows, $ak[0], $pk);
        }
        // Key-based array? Fields: ARRAY_KEY.
        return $this->_transformResultToHash($rows, $ak);
    }


    /**
     * Converts rowset to key-based array.
     *
     * @param array $rows          Two-dimensional array of resulting rows.
     * @param array $arrayKeys     List of ARRAY_KEY* field names.
     *
     * @return array        Transformed array.
     */
    private function _transformResultToHash(array $rows, array $arrayKeys)
    {
        $result = array();
        foreach ($rows as $row) {
            // Iterate over all of ARRAY_KEY* fields and build array dimensions.
            $current =& $result;
            foreach ($arrayKeys as $ak) {
                $key = $row[$ak];
                unset($row[$ak]); // remove ARRAY_KEY* field from result row
                if ($key !== null) {
                    $current =& $current[$key];
                } else {
                    // IF ARRAY_KEY field === null, use array auto-indices.
                    $tmp = array();
                    $current[] =& $tmp;
                    $current =& $tmp;
                    unset($tmp); // we use $tmp, because don't know the value of auto-index
                }
            }
            $current = $row; // save the row in last dimension
        }
        return $result;
    }


    /**
     * Converts rowset to the forest.
     *
     * @param array $rows       Two-dimensional array of resulting rows.
     * @param string $idName    Name of ID field.
     * @param string $pidName   Name of PARENT_ID field.
     *
     * @return array            Transformed array (tree).
     */
    private function _transformResultToForest(array $rows, $idName, $pidName)
    {
        $children = array(); // children of each ID
        $ids = array();
        // Collect who are children of whom.
        foreach ($rows as $i => $r) {
            $row =& $rows[$i];
            $id = $row[$idName];
            if ($id === null) {
                // Rows without an ID are totally invalid and makes the result tree to
                // be empty (because PARENT_ID = null means "a root of the tree"). So
                // skip them totally.
                continue;
            }
            $pid = $row[$pidName];
            if ($id == $pid) $pid = null;
            $children[$pid][$id] =& $row;
            if (!isset($children[$id])) {
                $children[$id] = [];
            }
            $row['childNodes'] =& $children[$id];
            $ids[$id] = true;
        }
        // Root elements are elements with non-found PIDs.
        $forest = [];
        foreach ($rows as $i=>$r) {
            $row =& $rows[$i];
            $id = $row[$idName];
            $pid = $row[$pidName];
            if ($pid == $id) {
                $pid = null;
            }
            if (!isset($ids[$pid])) {
                $forest[$row[$idName]] =& $row;
            }
            unset($row[$idName]);
            unset($row[$pidName]);
        }
        return $forest;
    }

    /**
     * Replaces the last array in a multi-dimensional array $V by its first value.
     * Used for selectCol(), when we need to transform (N+1)d resulting array
     * to Nd array (column).
     *
     * @param $v
     */
    private function _shrinkLastArrayDimensionCallback(&$v)
    {
        static::firstColumnArray($v);
    }

    /**
     * void _logQuery($query, $noTrace=false)
     * Must be called on each query.
     * If $noTrace is true, library caller is not solved (speed improvement).
     *
     * @param $query
     * @param bool $noTrace
     *
     * @return mixed|null
     */
    protected function _logQuery($query, $noTrace=false)
    {
        if (!$this->_logger) {
            return null;
        }
        $this->_expandPlaceholders($query, false);
        $args = [];
        $args[] =& $this;
        $args[] = $query[0];
        $args[] = $noTrace? null : $this->findLibraryCaller();

        return call_user_func_array($this->_logger, $args);
    }

    /**
     * void _logQueryStat($queryTime, $fetchTime, $firstFetchTime, $rows)
     * Log information about performed query statistics.
     *
     * @param $queryTime
     * @param $fetchTime
     * @param $firstFetchTime
     * @param $rows
     */
    private function _logQueryStat($queryTime, $fetchTime, $firstFetchTime, $rows)
    {
        // Always increment counters.
        $this->_statistics['time'] += $queryTime;
        $this->_statistics['count']++;

        // If no logger, economize CPU resources and actually log nothing.
        if (!$this->_logger) {
            return;
        }

        $dt = round($queryTime * 1000);
        $firstFetchTime = round($firstFetchTime*1000);
        $tailFetchTime = round($fetchTime * 1000) - $firstFetchTime;
        if ($firstFetchTime + $tailFetchTime) {
            $log = sprintf('  -- %d ms = %d+%d' . ($tailFetchTime? '+%d' : ''), $dt, $dt-$firstFetchTime-$tailFetchTime, $firstFetchTime, $tailFetchTime);
        } else {
            $log = sprintf('  -- %d ms', $dt);
        }
        $log .= '; returned ';

        if (!is_array($rows)) {
            $log .= $this->escape($rows);
        } else {
            $detailed = null;
            if (count($rows) === 1) {
                $len = 0;
                $values = [];
                foreach ((array)$rows[0] as $k => $v) {
                    $len += strlen($v);
                    if ($len > $this->MAX_LOG_ROW_LEN) {
                        break;
                    }
                    $values[] = $v === null? 'NULL' : $this->escape($v);
                }
                if ($len <= $this->MAX_LOG_ROW_LEN) {
                    $detailed = '(' . preg_replace("/\r?\n/", "\\n", implode(', ', $values)) . ')';
                }
            }
            if ($detailed) {
                $log .= $detailed;
            } else {
                $log .= count($rows). ' row(s)';
            }
        }

        $this->_logQuery($log, true);
    }


    /**
     * Replaces the last array in a multi-dimensional array $V by its first value.
     * Used for selectCol(), when we need to transform (N+1)d resulting array
     * to Nd array (column).
     *
     * @param $a
     */
    public static function firstColumnArray(&$a)
    {
        if (!$a || !is_array($a)) {
            return;
        }
        reset($a);
        if (!is_array($firstCell = current($a))) {
            $a = $firstCell;
        } else {
            array_walk($a, 'DbSimple_Database::firstColumnArray');
        }
    }

    /**
     * @param $sSql
     * @param array $aValues
     *
     * @return Command
     */
    public function sql($sSql, $aValues = [])
    {
        return new Command($this, $sSql, $aValues);
    }

    /**
     * @param $sSql
     * @param array $aValues
     *
     * @return mixed
     */
    public function sqlQuery($sSql, $aValues = [])
    {
        return $this->sql($sSql)->bind($aValues)->query();
    }

    /**
     * @param $sSql
     * @param array $aValues
     *
     * @return mixed
     */
    public function sqlSelect($sSql, $aValues = [])
    {
        return $this->sql($sSql)->bind($aValues)->select();
    }

    /**
     * @param $sSql
     * @param array $aValues
     *
     * @return array
     */
    public function sqlSelectRow($sSql, $aValues = [])
    {
        return $this->sql($sSql)->bind($aValues)->selectRow();
    }

    /**
     * @param $sSql
     * @param array $aValues
     *
     * @return mixed
     */
    public function sqlSelectCol($sSql, $aValues = [])
    {
        return $this->sql($sSql)->bind($aValues)->selectCol();
    }

    /**
     * @param $sSql
     * @param array $aValues
     *
     * @return mixed
     */
    public function sqlSelectCell($sSql, $aValues = [])
    {
        return $this->sql($sSql)->bind($aValues)->selectCell();
    }

}

// EOF

