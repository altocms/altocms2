<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

/**
 * Модуль логирования
 */
class ModuleLogger extends \Module
{
    static protected $aLogs = [];

    protected $nLogLevel;

    /**
     * Инициализация, устанавливает имя файла лога
     *
     */
    public function init()
    {
        parent::init();
    }

    /**
     * Уставливает текущий уровень лога, тот уровень при котором будет производиться запись в файл лога
     *
     * @param int, string('DEBUG','NOTICE','ERROR') $level    Уровень логирования
     *
     * @return bool
     */
    public function setWriteLevel($xLevel)
    {
        if (preg_match("/^\d$/", $xLevel) && isset($this->aLogLevels[$xLevel])) {
            $this->nLogLevel = $xLevel;
            return true;
        }
        $xLevel = strtoupper($xLevel);
        if ($key = array_search($xLevel, $this->aLogLevels, true)) {
            $this->nLogLevel = $key;
            return true;
        }
        return false;
    }

    /**
     * Возвращает текущий уровень лога
     *
     * @return int
     */
    public function getWriteLevel()
    {
        return $this->reset('default')->getLogLevel();
    }

    /**
     * Использовать трассировку или нет
     *
     * @param bool $bool    Использовать или нет троссировку в логах
     */
    public function setUseTrace($bool)
    {
        return $this->reset('default')->setUseTrace((bool)$bool);
    }

    /**
     * Использует трассировку или нет
     *
     * @return bool
     */
    public function getUseTrace()
    {
        return (bool)$this->reset('default')->getUseTrace();
    }

    /**
     * Использовать ротацию логов или нет
     *
     * @param bool $bool
     */
    public function setUseRotate($bool)
    {
        return $this->reset('default')->setUseRotate((bool)$bool);
    }

    /**
     * Использует ротацию логов или нет
     *
     * @return bool
     */
    public function getUseRotate()
    {
        return (bool)$this->reset('default')->getUseRotate();
    }

    /**
     * Устанавливает имя файла лога
     *
     * @param string $sFile
     */
    public function setFileName($sFile)
    {
        return $this->reset('default')->setFileName($sFile);
    }

    /**
     * Получает имя файла лога
     *
     * @return string
     */
    public function getFileName()
    {
        return $this->reset('default')->getFileName();
    }

    /**
     * Запись в лог с уровнем логирования 'DEBUG'
     *
     * @param string $msg    Сообщение для записи в лог
     */
    public function debug($msg)
    {
        $this->log($msg, 'DEBUG');
    }

    /**
     * Запись в лог с уровнем логирования 'ERROR'
     *
     * @param string $msg    Сообщение для записи в лог
     */
    public function error($msg)
    {
        $this->log($msg, 'ERROR');
    }

    /**
     * Запись в лог с уровнем логирования 'NOTICE'
     *
     * @param string $msg    Сообщение для записи в лог
     */
    public function notice($msg)
    {
        $this->log($msg, 'NOTICE');
    }

    /**
     * Записывает лог
     *
     * @param string $sMsg   - Сообщение для записи в лог
     * @param string $sLevel - Уровень логирования
     *
     * @return bool
     */
    protected function log($sMsg, $sLevel)
    {
        return $this->dump('default', $sMsg, $sLevel);
    }

    /**
     * Производит сохранение в файл
     *
     * @param string $sMsg    Сообщение
     * @return bool
     */
    protected function write($sMsg)
    {
        return $this->dump('default', $sMsg);
    }

    /**
     * @param string $sLog
     * @param string $sFileName
     *
     * @return EntityLog
     *
     * @throws \RuntimeException
     */
    public function reset($sLog, $sFileName = null)
    {
        if (!isset(self::$aLogs[$sLog])) {
            if (!$sFileName) {
                $sFileName = $sLog;
            }
            $oLog = \E::getInstance()->getEntity('Logger_Log', array(
                'file_name' => $sFileName,
                'file_dir' => \C::get('sys.logs.dir'),
            ));
            self::$aLogs[$sLog] = $oLog;
        }
        return self::$aLogs[$sLog];
    }

    /**
     * @param object|string $oLog
     * @param string        $sMsg
     * @param string        $sLevel
     *
     * @return bool
     */
    public function dump($oLog, $sMsg, $sLevel = null)
    {
        if (!is_object($oLog)) {
            $oLog = $this->reset((string)$oLog);
        }
        return $oLog->Dump($sMsg, $sLevel);
    }

}

// EOF