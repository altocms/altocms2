<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 * Based on
 *   LiveStreet Engine Social Networking by Mzhelskiy Maxim
 *   Site: www.livestreet.ru
 *   E-mail: rus.engine@gmail.com
 *----------------------------------------------------------------------------
 */

use avadim\DbSimple\DbSimple;
use avadim\DbSimple\DbConnect;

/**
 * Модуль для работы с базой данных
 * Создаёт объект БД библиотеки DbSimple Дмитрия Котерова
 * Модуль используется в основном для создания коннекта к БД и передачи его в маппер
 *
 * @see     Mapper::__construct
 * Так же предоставляет методы для быстрого выполнения запросов/дампов SQL, актуально для плагинов
 * @see     Plugin::exportSQL
 *
 * @package engine.modules
 * @since   1.0
 */

/**
 * TODO: подготовить описание, включая патчи:
 *
 * - мультиинсерт (?a и двумерный массив в "('1','2','3') ('4','5','6') ..."
 * - разворот двумерных массивов в группы условий AND и OR
 * - поддержка плейсхолдера ?s — подзапрос с проверкой типов, тоесть неэкранированные данные вставить нельзя,
 *   а кусок запроса с подстановкой параметров можно
 * - поддержка конструкций {?… } — условная вставка и {… |… } — аналог else
 */
class ModuleDatabase extends Module
{
    /** @var \alto\engine\generic\DataArray  */
    protected $aDbConfig = [];

    /** @var array Массив уникальных коннектов к БД */
    protected $aDbConnects = [];

    static protected $sLastQuery;

    static protected $sLastResult;

    protected $sLogFile;

    protected $aSqlErrors = [];


    /**
     * Инициализация модуля
     *
     */
    public function init()
    {
        $aAllDbConfig = \C::getData('db');
        if (!isset($aAllDbConfig[0])) {
            $aAllDbConfig = [$aAllDbConfig];
        }

        foreach ($aAllDbConfig as $xKey => $aDbConfig) {
            $sDsn = $aDbConfig['type'] . '://' . $aDbConfig['user'] . ':'
                . $aDbConfig['pass'] . '@' . $aDbConfig['host'] . ':'
                . $aDbConfig['port'] . '/' . $aDbConfig['dbname'];
            $sDsn .= '?db_num=' . $xKey;
            if (!empty($aDbConfig['table_prefix'])) {
                $sDsn .= '&table_prefix=' . $aDbConfig['table_prefix'];
            }
            $sCharset = isset($aDbConfig['charset']) ? $aDbConfig['charset'] : 'utf8';
            $aInitSql = isset($aDbConfig['init_sql']) ? (array)$aDbConfig['init_sql'] : [];
            foreach ($aInitSql as $iKey => $sInitSql) {
                $aInitSql[$iKey] = str_replace('%%charset%%', $sCharset, $sInitSql);
            }
            $this->aDbConfig[$xKey] = [
                'config' => $aDbConfig,
                'dsn' => $sDsn,
                'init_sql' => $aInitSql,
            ];
        }

        $this->sLogFile = \C::get('sys.logs.sql_query_file');
    }

    /**
     * @param string $sDsn
     *
     * @return array|null
     */
    protected function _getConfigByDsn($sDsn)
    {
        foreach($this->aDbConfig as $aDbConfig) {
            if (isset($aDbConfig['dsn']) && $aDbConfig['dsn'] === $sDsn) {
                return $aDbConfig;
            }
        }
        return null;
    }

    /**
     * @param string $sDsn
     * @param bool   $bLazy
     * @param array  $aInitSql
     *
     * @return DbConnect|null
     */
    protected function _newDbConnect($sDsn, $bLazy = true, $aInitSql = null)
    {
        if (!$aInitSql) {
            $aDbConfig = $this->_getConfigByDsn($sDsn);
            if (isset($aDbConfig['init_sql'])) {
                $aInitSql = $aDbConfig['init_sql'];
            }
        }
        if ($bLazy) {
            // lazy connection
            $oDbSimple = DbSimple::create($sDsn);
            foreach ((array)$aInitSql as $sSql) {
                $oDbSimple->addInit($sSql);
            }
        } else {
            // immediate connection
            $oDbSimple = DbSimple::connect($sDsn);
            foreach ((array)$aInitSql as $sSql) {
                $oDbSimple->query($sSql);
            }
        }
        return $oDbSimple;
    }

    /**
     * @param $iDbIndex
     *
     * @return null|string
     */
    protected function _getDsn($iDbIndex)
    {
        if (isset($this->aDbConfig[$iDbIndex]['dsn'])) {
            return $this->aDbConfig[$iDbIndex]['dsn'];
        }
        return null;
    }

    /**
     * @param $iDbIndex
     *
     * @return DbConnect|null
     */
    public function getConnect($iDbIndex)
    {
        $sDsn = $this->_getDsn($iDbIndex);
        if ($sDsn) {
            return $this->getDsnConnect($sDsn);
        }
        return null;
    }

    /**
     * @param $sDsn
     *
     * @return DbConnect|null
     */
    public function getDsnConnect($sDsn)
    {
        return $this->_getDbConnect($sDsn);
    }

    /**
     * Returns DB object
     *
     * @param string $sDsn
     *
     * @return  DbConnect|null
     */
    protected function _getDbConnect($sDsn)
    {
        // * Проверяем создавали ли уже коннект с такими параметрами подключения(DSN)
        if (isset($this->aDbConnects[$sDsn])) {
            return $this->aDbConnects[$sDsn];
        } else {
            // * Если такого коннекта еще не было то создаём его
            $oDbConnect = $this->_newDbConnect($sDsn);

            // * Устанавливаем хук на перехват ошибок при работе с БД
            $oDbConnect->setErrorHandler([$this, 'ErrorHandler']);

            // * Если нужно логировать все SQL запросы то подключаем логгер
            if (\C::get('sys.logs.sql_query')) {
                if (\C::get('sys.logs.sql_query_rewrite')) {
                    $oLog = \E::Module('Logger')->reset($this->sLogFile);
                    \F::File_DeleteAs($oLog->getFileDir() . pathinfo($oLog->getFileName(), PATHINFO_FILENAME) . '*');
                }
                $oDbConnect->setLogger([$this, 'Logger']);
            } else {
                $oDbConnect->setLogger([$this, '_internalLogger']);
            }
            $oDbConnect->setTableNameFunc([$this, 'TableNameTransformer']);

            // * Сохраняем коннект
            $this->aDbConnects[$sDsn] = $oDbConnect;

            // * Возвращаем коннект
            return $oDbConnect;
        }
    }

    /**
     * Возвращает статистику использования БД - время и количество запросов
     *
     * @return array
     */
    public function getStats()
    {
        // не считаем тот самый костыльный запрос, который устанавливает настройки DB соединения
        $aQueryStats = [
            'time'  => 0,
            'count' => -1,
        ];
        foreach ($this->aDbConnects as $oDb) {
            $aStats = $oDb->getStatistics();
            $aQueryStats['time'] += $aStats['time'];
            $aQueryStats['count'] += $aStats['count'];
        }
        $aQueryStats['time'] = round($aQueryStats['time'], 3);

        return $aQueryStats;
    }

    /**
     * Set SQL logger ON
     */
    public function setLoggerOn()
    {
        foreach ($this->aDbConnects as $sDsn => $oDb) {
            $oDb->setLogger([$this, 'Logger']);
            $this->aDbConnects[$sDsn] = $oDb;
        }
    }

    /**
     * Set SQL logger OFF
     */
    public function setLoggerOff()
    {
        foreach ($this->aDbConnects as $sDsn => $oDb) {
            $oDb->setLogger(null);
            $this->aDbConnects[$sDsn] = $oDb;
        }
    }

    /**
     * Логгирование SQL запросов
     *
     * @param   object $oDb
     * @param   array  $sSql
     */
    function Logger($oDb, $sSql)
    {

        $this->_internalLogger($oDb, $sSql);

        // Получаем информацию о запросе и сохраняем её в лог
        $sMsg = print_r($sSql, true);

        $oLog = \E::Module('Logger')->reset($this->sLogFile);
        if (substr(trim($sMsg), 0, 2) == '--') {
            // это результат запроса
            if (ALTO_DEBUG) {
                $aStack = debug_backtrace(false);
                $i = 0;
                while (empty($aStack[$i]['file']) || (isset($aStack[$i]['file']) && strpos($aStack[$i]['file'], 'DbSimple') === false)) {
                    $i += 1;
                }
                while (empty($aStack[$i]['file']) || (isset($aStack[$i]['file']) && strpos($aStack[$i]['file'], 'DbSimple') !== false)) {
                    $i += 1;
                }
                $sCaller = '';
                if (isset($aStack[$i]['file'])) {
                    $sCaller .= $aStack[$i]['file'];
                }
                if (isset($aStack[$i]['line'])) {
                    $sCaller .= ' (' . $aStack[$i]['line'] . ')';
                }
                $oLog->dumpAppend(trim($sMsg));
                $oLog->dumpEnd('-- [src]' . $sCaller);
            } else {
                $oLog->dumpEnd(trim($sMsg));
            }
        } else {
            // это сам запрос
            if (ALTO_DEBUG) {
                $aLines = array_map('trim', explode("\n", $sMsg));
                foreach ($aLines as $iIndex => $sLine) {
                    if (!$sLine) {
                        unset($aLines[$iIndex]);
                    } else {
                        $aLines[$iIndex] = '    ' . $sLine;
                    }
                }
                $sMsg = join(PHP_EOL, $aLines);
                $sMsg = '-- [id]' . md5($sMsg) . PHP_EOL . $sMsg;
            }
            $oLog->dumpBegin($sMsg);
        }
    }

    /**
     * @param string $sTable
     * @param int    $iDbNum
     *
     * @return string
     */
    public function TableNameTransformer($sTable, $iDbNum = 0)
    {
        if (substr($sTable, 0, 2) === '?_') {
            $sTable = substr($sTable, 2);
            if (isset($this->aDbConfig[$iDbNum]['config']['table'][$sTable])) {
                return $this->aDbConfig[$iDbNum]['config']['table'][$sTable];
            }
            if ($this->aDbConfig[$iDbNum]['config']['table_prefix']) {
                return $this->aDbConfig[$iDbNum]['config']['table_prefix'] . $sTable;
            }
        }
        return $sTable;
    }

    /**
     * Функция для перехвата SQL ошибок
     *
     * @param   string $sMessage     Сообщение об ошибке
     * @param   array  $aInfo        Информация об ошибке
     */
    public function ErrorHandler($sMessage, $aInfo)
    {
        // * Формируем текст сообщения об ошибке
        $sMsg = "SQL Error: $sMessage\n---\n";
        $sMsg .= print_r($aInfo, true);

        $this->aSqlErrors[] = $sMsg;

        // * Если нужно логировать SQL ошибки то пишем их в лог
        if (\C::get('sys.logs.sql_error')) {
            \E::Module('Logger')->dump(\C::get('sys.logs.sql_error_file'), $sMsg, 'ERROR');
        }

        // * Если стоит вывод ошибок то выводим ошибку на экран(браузер)
        if (error_reporting() && ini_get('display_errors')) {
            exit($sMsg);
        }
    }

    public function _internalLogger($oDb, $sSql)
    {
        if (0 === strpos($sSql, '  -- ')) {
            self::$sLastResult = $sSql;
        } else {
            self::$sLastQuery = $sSql;
        }
    }

    /**
     * @return string
     */
    public function getLastQuery() {

        return self::$sLastQuery;
    }

    /**
     * @return string
     */
    public function getLastResult() {

        return self::$sLastResult;
    }

    /**
     * @return string|null
     */
    public function getLastError()
    {
        if (!empty($this->aSqlErrors)) {
            return end($this->aSqlErrors);
        }
        return null;
    }
}

// EOF
