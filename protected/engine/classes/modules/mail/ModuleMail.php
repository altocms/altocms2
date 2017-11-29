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

F::IncludeLib('phpMailer/class.phpmailer.php');

/**
 * Модуль для отправки почты(e-mail) через phpMailer
 * <pre>
 * \E::Module('Mail')->SetAdress('claus@mail.ru','Claus');
 * \E::Module('Mail')->SetSubject('Hi!');
 * \E::Module('Mail')->SetBody('How are you?');
 * \E::Module('Mail')->SetHTML();
 * \E::Module('Mail')->Send();
 * </pre>
 *
 * @package engine.modules
 * @since   1.0
 */
class ModuleMail extends Module {
    /**
     * Объект phpMailer
     *
     * @var phpmailer
     */
    protected $oMailer;
    /**
     * Настройки SMTP сервера для отправки писем
     *
     */
    /**
     * Хост smtp
     *
     * @var string
     */
    protected $sHost;
    /**
     * Порт smtp
     *
     * @var int
     */
    protected $iPort;
    /**
     * Логин smtp
     *
     * @var string
     */
    protected $sUsername;
    /**
     * Пароль smtp
     *
     * @var string
     */
    protected $sPassword;
    /**
     * Треубется или нет авторизация на smtp
     *
     * @var bool
     */
    protected $bSmtpAuth;
    /**
     * Префикс соединения к smtp - "", "ssl" или "tls"
     *
     * @var string
     */
    protected $sSmtpSecure;
    /**
     * Метод отправки почты
     *
     * @var string
     */
    protected $sMailerType;
    /**
     * Кодировка писем
     *
     * @var string
     */
    protected $sCharSet;
    /**
     * Кодирование писем
     *
     * @var string
     */
    protected $sEncoding;
    /**
     * Делать или нет перенос строк в письме
     *
     * @var int
     */
    protected $iWordWrap = 0;

    /**
     * Мыло от кого отправляется вся почта
     *
     * @var string
     */
    protected $sFrom;
    /**
     * Имя от кого отправляется вся почта
     *
     * @var string
     */
    protected $sFromName;
    /**
     * Тема письма
     *
     * @var string
     */
    protected $sSubject = '';
    /**
     * Текст письма
     *
     * @var string
     */
    protected $sBody = '';
    /**
     * Строка последней ошибки
     *
     * @var string
     */
    protected $sError;

    protected $aErrors = [];

    /**
     * Инициализация модуля
     *
     */
    public function init() {

        // * Настройки SMTP сервера для отправки писем
        $this->sHost = \C::get('sys.mail.smtp.host');
        $this->iPort = \C::get('sys.mail.smtp.port');
        $this->sUsername = \C::get('sys.mail.smtp.user');
        $this->sPassword = \C::get('sys.mail.smtp.password');
        $this->bSmtpAuth = \C::get('sys.mail.smtp.auth');
        $this->sSmtpSecure = \C::get('sys.mail.smtp.secure');

        // * Метод отправки почты
        $this->sMailerType = \C::get('sys.mail.type');

        // * Кодировка писем
        $this->sCharSet = \C::get('sys.mail.charset');

        // * Кодирование писем
        $this->sEncoding = \C::get('sys.mail.encoding');

        // * Мыло от кого отправляется вся почта
        $this->sFrom = \C::get('sys.mail.from_email');

        // * Имя от кого отправляется вся почта
        $this->sFromName = \C::get('sys.mail.from_name');

        // * Создаём объект phpMailer и устанвливаем ему необходимые настройки
        $this->oMailer = new PHPMailer();
        // Вывод ошибок через ob_get_clean() возможен только с включением этой опции.
        // Иначе все ошибки будут с содержанием: «Cannot send email».
        // Однако, в случае ошибки отправки, в лог будет записан текст запросов к smtp-серверу, включая логин и пароль.
        $this->oMailer->SMTPDebug = defined('ALTO_DEBUG') && ALTO_DEBUG;
        $this->oMailer->Host = $this->sHost;
        $this->oMailer->Port = $this->iPort;
        $this->oMailer->Username = $this->sUsername;
        $this->oMailer->Password = $this->sPassword;
        $this->oMailer->SMTPAuth = $this->bSmtpAuth;
        $this->oMailer->SMTPSecure = $this->sSmtpSecure;
        $this->oMailer->Mailer = $this->sMailerType;
        $this->oMailer->WordWrap = $this->iWordWrap;
        $this->oMailer->CharSet = $this->sCharSet;
        $this->oMailer->Encoding = $this->sEncoding;

        // see https://github.com/altocms/altocms/issues/259
        //$this->oMailer->From = $this->sFrom;
        //$this->oMailer->FromName = $this->sFromName;

        $this->oMailer->SetFrom($this->sFrom, $this->sFromName);
    }

    /**
     * Устанавливает тему сообщения
     *
     * @param string $sText - Тема сообщения
     */
    public function setSubject($sText) {

        $this->sSubject = $sText;
    }

    /**
     * Устанавливает текст сообщения
     *
     * @param string $sText - Текст сообщения
     */
    public function setBody($sText) {

        $this->sBody = $sText;
    }

    /**
     * Добавляем новый адрес получателя
     *
     * @param string $sMail - Адрес
     * @param string $sName - Имя
     */
    public function addAdress($sMail, $sName = null) {

        ob_start();
        $this->oMailer->AddAddress($sMail, $sName);
        $sError = ob_get_clean();
        if ($sError) {
            $this->_addError($sError);
        }
    }

    /**
     * Отправляет сообщение
     *
     * @return bool
     */
    public function Send() {

        $this->oMailer->Subject = $this->sSubject;
        $this->oMailer->Body = $this->sBody;
        ob_start();
        $bResult = $this->oMailer->Send();
        $sError = ob_get_clean();
        if (!$bResult && !$sError) {
            // Письмо не отправлено, но ошибки нет - такое бывает
            $sError = 'Cannot send email';
        }
        if ($sError) {
            $this->_addError($sError);
        }
        return $bResult;
    }

    /**
     * Очищает все адреса получателей
     *
     */
    public function ClearAddresses() {

        $this->oMailer->ClearAddresses();
    }

    /**
     * Устанавливает единственный адрес получателя
     *
     * @param string $sMail - Алрес
     * @param string $sName - Имя
     */
    public function setAdress($sMail, $sName = null) {

        $this->ClearAddresses();
        ob_start();
        $this->oMailer->AddAddress($sMail, $sName);
        $sError = ob_get_clean();
        if ($sError) {
            $this->_addError($sError);
        }
    }

    /**
     * Устанавливает режим отправки письма как HTML
     *
     */
    public function setHTML() {

        $this->oMailer->IsHTML(true);
    }

    /**
     * Устанавливает режим отправки письма как Text(Plain)
     *
     */
    public function setPlain() {

        $this->oMailer->IsHTML(false);
    }

    protected function _addError($sError) {

        $this->aErrors[] = $sError;
        $this->sError = $sError;
    }

    /**
     * Возвращает строку последней ошибки
     *
     * @param bool $bClear - сборс ошибки после чтения
     *
     * @return string
     */
    public function getError($bClear = false) {

        if (!$bClear) {
            return $this->sError;
        }
        $sError = $this->sError;
        if ($this->aErrors) {
            $this->sError = array_pop($this->aErrors);
        } else {
            $this->sError = null;
        }
        return $sError;
    }

    /**
     * При завершении работы модуля пишем ошибки в лог, если они есть
     */
    public function shutdown() {

        while ($sError = $this->GetError(true)) {
             \F::SysWarning($sError);
        }
    }

}

// EOF
