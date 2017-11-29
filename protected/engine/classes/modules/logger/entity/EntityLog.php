<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

class ModuleLogger_EntityLog extends \Entity
{
    const MAX_LOCK_CNT    = 10;
    const MAX_LOCK_PERIOD = 50000;
    const MAX_LOCK_TIME   = 1000000;

    protected $aLogs = [];

    /**
     * Уровни логгирования
     *
     * @var array
     */
    protected $aLogLevels = [
            'DEBUG',
            'NOTICE',
            'ERROR',
    ];

    protected $aRecord = [];

    /**
     * EntityLog constructor.
     *
     * @param array $aParam
     */
    public function __construct($aParam = null)
    {
        $this->setFileName(Config::get('sys.logs.file'));
        $this->setFileDir(Config::get('sys.logs.dir'));

        // Max file size for rotation
        $this->setSizeForRotate(Config::get('sys.logs.size_for_rotate'));

        $this->setCountForRotate(Config::get('sys.logs.count_for_rotate'));

        // Текущий уровень логирования
        $this->setLogLevel(0);

        parent::__construct($aParam);
    }

    /**
     * EntityLog destructor.
     *
     */
    public function __destruct()
    {
        // Если есть несоханенная запись, то сохраняем ее
        if ($this->aRecord) {
            $this->dumpEnd();
        }
    }

    /**
     * Sets dir for log file
     *
     * @param $sDir
     */
    public function setFileDir($sDir)
    {
        if (substr($sDir, -1) !== '/') {
            $sDir .= '/';
        }
        parent::setFileDir($sDir);
    }

    /**
     * Sets max size for file rotation
     *
     * @param $iSize
     */
    public function setSizeForRotate($iSize)
    {
        $iSize = (int)$iSize;
        if ($iSize > 0) {
            $this->setProp('size_for_rotate', $iSize);
            // Set rotation to on
            $this->setUseRotate(true);
        } else {
            // Set rotation to off
            $this->setUseRotate(false);
        }
    }

    /**
     * Sets max files number in rotation
     *
     * @param int $iCount
     */
    public function setCountForRotate($iCount)
    {
        $this->setProp('count_for_rotate', (int)$iCount);
    }

    /**
     * Dump to log file
     *
     * @param   string $sMsg
     * @param   string $sLevel
     *
     * @return bool
     */
    public function Dump($sMsg, $sLevel = 'DEBUG')
    {
        // * Если уровень записи в лог больше либо равен текущему уровню, то пишем
        if (!$sLevel || ($nLevelIndex = array_search(strtoupper($sLevel), $this->aLogLevels)) === false) {
            $nLevelIndex = 0;
        }
        if ($nLevelIndex >= $this->getLogLevel()) {
            if (!is_scalar($sMsg)) {
                $sMsg = print_r($sMsg, true);
            }
            $this->dumpBegin($sMsg, $sLevel);
            return $this->dumpEnd();
        }
        return false;
    }

    /**
     * @param $sLevel
     *
     * @return bool|mixed
     */
    protected function _checkLogLevel($sLevel)
    {
        // Если уровень записи в лог больше либо равен текущему уровню, то создаем запись
        if (!$sLevel || ($nLevelIndex = array_search(strtoupper($sLevel), $this->aLogLevels)) === false) {
            $nLevelIndex = 0;
        }

        if ($nLevelIndex >= $this->getLogLevel()) {
            return $this->aLogLevels[$nLevelIndex];
        }
        return false;
    }

    /**
     * Begin of record for log file
     *
     * @param string $sMsg
     * @param string $sLevel
     */
    public function dumpBegin($sMsg, $sLevel = 'DEBUG')
    {
        if ($sLogLevel = $this->_checkLogLevel($sLevel)) {

            // Формируем запись
            $this->aRecord = [
                'id'    => sprintf('%014.3F-%s', microtime(true), strtoupper(uniqid())),
                'time'  => date('Y-m-d H:i:s'),
                'pid'   => @getmypid(),
                'level' => $sLogLevel,
                'trace' => null,
                'info'  => [$sMsg],
            ];

            if ($this->getUseTrace()) {
                $this->aRecord['trace'] = $this->_parserTrace(debug_backtrace());
            }
        }
    }

    /**
     * Append message to record
     *
     * @param string $sMsg
     * @param string $sLevel
     */
    public function dumpAppend($sMsg, $sLevel = 'DEBUG')
    {
        if ($sLogLevel = $this->_checkLogLevel($sLevel)) {
            if (!$this->aRecord) {
                // Запись не создавалась, надо ее создать
                $this->dumpBegin($sMsg, $sLevel);
                return;
            }
            if ($this->aRecord['level'] !== $sLogLevel) {
                // Если уровень логгирования изменился, то пишем текущую запись и создаем новую
                $this->dumpEnd(null, $sLevel);
                $this->dumpBegin($sMsg, $sLevel);
                return;
            }

            // Добавляем текст в запись
            $this->aRecord['info'][] = $sMsg;
        }
    }

    /**
     * End of record for log file
     *
     * @param string $sMsg
     * @param string $sLevel
     *
     * @return bool
     */
    public function dumpEnd($sMsg = null, $sLevel = 'DEBUG')
    {
        $xResult = false;
        // Если аргументы не переданы, а запись есть, то сохраняем ее
        $bForce = ((func_num_args() === 0) && $this->aRecord);
        if (($sLogLevel = $this->_checkLogLevel($sLevel)) || $bForce) {
            if (!$this->aRecord) {
                // Запись не создавалась, надо ее создать
                return $this->dumpBegin($sMsg, $sLevel);
            }
            if (($this->aRecord['level'] !== $sLogLevel) && $sMsg) {
                // Если уровень логгирования изменился, то пишем текущую запись и создаем новую
                $this->dumpEnd();
                return $this->dumpBegin($sMsg, $sLevel);
            }

            // Формируем текст лога со служебной информацией
            if ($this->aRecord) {
                if ($sMsg) {
                    $this->aRecord['info'][] = $sMsg;
                }

                $sMsgOut = '[LOG:' . $this->aRecord['id'] . ']';
                $sMsgOut .= '[' . $this->aRecord['time'] . ']';
                $sMsgOut .= '[PID:' . $this->aRecord['pid'] . ']';
                $sMsgOut .= '[' . $this->aRecord['level'] . "][[\n";

                if (count($this->aRecord['info']) > 1) {
                    $sMsgText = '';
                    foreach ($this->aRecord['info'] as $sTxt) {
                        if ($sMsgText) {
                            $sMsgText .= "\n";
                        }
                        $sMsgText .= $sTxt;
                    }
                } else {
                    $sMsgText = $this->aRecord['info'][0];
                }
                $sMsgOut .= $sMsgText . "\n]]";

                // Если нужно, то добавляем трассировку
                if ($this->aRecord['trace']) {
                    $sMsgOut .= "\n[TRACE: " . $this->aRecord['trace'] . "]\n";
                }
                $sMsgOut .= '[END:' . $this->aRecord['id'] . "]\n";

                // Записываем
                $xResult = $this->_write($sMsgOut);

                // Очищаем текущую запись
                $this->aRecord = [];
            }
        }
        return $xResult;
    }

    /**
     * Writes message to log file
     *
     * @param string $sMsg - message to log
     *
     * @return bool|int
     */
    protected function _write($sMsg)
    {
        $xResult = false;
        // if no filename then nothing to do
        if (!($sFileName = $this->getFileName())) {
            //throw new Exception("Empty file name for log!");
            return false;
        }
        // if filename equal '-' then wtites message to browser
        if ($sFileName === '-') {
            echo($sMsg . "<br/>\n");
        } else {
            // writes to file
            $sFile = $this->getFileDir() . $sFileName;

            // file for locking
            $sCheckFileName = $sFile . '.lock';
            if (is_file($sCheckFileName) && !is_writable($sCheckFileName)) {
                 \F::sysWarning('Cannot write to file ' . $sCheckFileName);
            } else {
                // Ignore errors
                $nErrorReporting =  \F::errorIgnored(E_NOTICE | E_WARNING, true);
                $fp = @fopen($sCheckFileName, 'c');
                // Restore errors
                 \F::errorReporting($nErrorReporting);

                if (!$fp) {
                    // It is not clear what to do here
                    if ($xResult =  \F::File_PutContents($sFile, $sMsg . "\n", FILE_APPEND | LOCK_EX)) {
                        // Do rotation if need
                        if ($this->getUseRotate() && $this->getSizeForRotate()) {
                            $this->_rotate();
                        }
                    }
                } else {
                    // Tries to lock
                    $iTotal = 0;
                    // Check the count of attempts at competitive lock requests
                    for ($iCnt = 0; $iCnt < self::MAX_LOCK_CNT; $iCnt++) {
                        if (flock($fp, LOCK_EX)) {
                            if ($xResult =  \F::File_PutContents($sFile, $sMsg . "\n", FILE_APPEND | LOCK_EX)) {
                                // Do rotation if need
                                if ($this->getUseRotate() && $this->getSizeForRotate()) {
                                    $this->_rotate();
                                }
                            }
                            flock($fp, LOCK_UN);
                            break;
                        } else {
                            $iTotal += self::MAX_LOCK_PERIOD;
                            if ($iTotal >= self::MAX_LOCK_TIME) {
                                break;
                            }
                            usleep(self::MAX_LOCK_PERIOD);
                        }
                    }
                    fclose($fp);
                }
            }
        }
        return $xResult;
    }

    /**
     * Rotates log files
     */
    protected function _rotate()
    {
        clearstatcache();
        // Если размер файла лога привысил максимальный то сохраняем текущий файл в архивный,
        // а текущий становится пустым
        $sFileName = $this->getFileDir() . $this->getFileName();
        if (filesize($sFileName) >= $this->getSizeForRotate()) {
            $aPathInfo = pathinfo($sFileName);
            $i = 1;
            $sPathFile = $aPathInfo['dirname'] . '/' . $aPathInfo['filename'];
            while (true) {
                $sNewFullName = $sPathFile . ".$i." . $aPathInfo['extension'];
                if (!F::File_Exists($sNewFullName)) {
                    $this->_rotateRename($sFileName, $i - 1);
                    break;
                }
                $i++;
            }
        }
    }

    /**
     * Переименовывает все файлы логов согласно их последовательности
     *
     * @param string $sFileName
     * @param int    $iNumberLast
     */
    protected function _rotateRename($sFileName, $iNumberLast)
    {
        $aPathInfo = pathinfo($sFileName);
        $iMaxCount = $this->getCountForRotate();
        $sPathFile = $aPathInfo['dirname'] . '/' . $aPathInfo['filename'];
        for ($i = $iNumberLast; $i > 0; $i--) {
            $sFullNameCur = $sPathFile . ".$i." . $aPathInfo['extension'];
            if ($iMaxCount && $iMaxCount <= $i) {
                 \F::File_Delete($sFullNameCur);
            } else {
                $sFullNameNew = $sPathFile . '.' . ($i + 1) . '.' . $aPathInfo['extension'];
                rename($sFullNameCur, $sFullNameNew);
            }
        }
        rename($sFileName, $sPathFile . '.1.' . $aPathInfo['extension']);
    }

    /**
     * Выполняет форматирование трассировки
     *
     * @param array $aTrace
     *
     * @return string
     */
    protected function _parserTrace($aTrace)
    {
        $sMsg = '';
        for ($i = count($aTrace) - 1; $i >= 0; $i--) {
            if (isset($aTrace[$i]['class'])) {
                $sFunc = $aTrace[$i]['class'] . $aTrace[$i]['type'] . $aTrace[$i]['function'] . '()';
            } else {
                $sFunc = $aTrace[$i]['function'] . '()';
            }
            $sMsg .= $aTrace[$i]['file'] . '(line:' . $aTrace[$i]['line'] . '){' . $sFunc . '}';
            if ($i !== 0) {
                $sMsg .= ' => ';
            }
        }
        return $sMsg;
    }

}

// EOF