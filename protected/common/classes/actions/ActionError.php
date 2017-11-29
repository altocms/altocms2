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

/**
 * Экшен обработки УРЛа вида /error/ т.е. ошибок
 *
 * @package actions
 * @since   1.0
 */
class ActionError extends Action {
    /**
     * Список специфических HTTP ошибок для которых необходимо отдавать header
     *
     * @var array
     */
    protected $aHttpErrors
        = array(
            '404' => array(
                'header' => '404 Not Found',
            ),
        );

    /**
     * Инициализация экшена
     *
     */
    public function init() {

        /**
         * issue #104, {@see https://github.com/altocms/altocms/issues/104}
         * Проверим, не пришли ли мы в ошибку с логаута, если да, то перейдем на главную,
         * поскольку страница на самом деле есть, но только когда мы авторизованы.
         */
        if (isset($_SERVER['HTTP_REFERER']) && \E::Module('Session')->getCookie('lgp') === md5(\F::RealUrl($_SERVER['HTTP_REFERER']) . 'logout')) {
            return R::Location((string)Config::get('module.user.logout.redirect'));
        }

        /**
         * Устанавливаем дефолтный евент
         */
        $this->setDefaultEvent('index');
    }

    /**
     * Регистрируем евенты
     *
     */
    protected function registerEvent() {
        $this->addEvent('index', 'eventError');
        $this->addEventPreg('/^\d{3}$/i', 'eventError');
    }

    /**
     * Вывод ошибки
     *
     */
    public function eventError() {
        /**
         * Если евент равен одной из ошибок из $aHttpErrors, то шлем браузеру специфичный header
         * Например, для 404 в хидере будет послан браузеру заголовок HTTP/1.1 404 Not Found
         */
        if (array_key_exists($this->sCurrentEvent, $this->aHttpErrors)) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('system_error_' . $this->sCurrentEvent), $this->sCurrentEvent
            );
            $aHttpError = $this->aHttpErrors[$this->sCurrentEvent];
            if (isset($aHttpError['header'])) {
                $sProtocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
                header("{$sProtocol} {$aHttpError['header']}");
            }
        }
        /**
         * Устанавливаем title страницы
         */
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('error'));
        $this->setTemplateAction('index');
    }
}

// EOF