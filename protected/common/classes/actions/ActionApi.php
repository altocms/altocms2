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
 * Экшен Api
 * REST API для Alto CMS
 *
 * Экшен принимает запрос к АПИ в виде http://example.com/api/method/cmd/[?param1=val1[&...]]
 * Здесь
 *      - method, определяет метод API (объект запроса, идентификатор ресурса), например user, blog, comment
 *      - cmd, команда, требуемое действие над объектом запроса
 *      - params, парметры запроса, его конкретизация
 *
 * Примеры API для работы с объектом пользователя:
 *      - GET: http://example.com/api/user/list           - список всех пользователей
 *      - GET: http://example.com/api/user/1/info         - информация о пользователе с ид. 1
 *      - GET: http://example.com/api/user/1/friends      - друзья пользователя с ид. 1
 *      - GET: http://example.com/api/user/1/comments     - комментарии пользователя с ид. 1
 *      - GET: http://example.com/api/user/1/publications - публикации пользователя с ид. 1
 *      - GET: http://example.com/api/user/1/blogs/       - блоги пользователя с ид. 1
 *      - GET: http://example.com/api/user/1/images       - изображения пользователя с ид. 1
 *      - GET: http://example.com/api/user/1/activity     - активность пользователя с ид. 1
 *
 *
 * @package actions
 * @since 1.0
 */
class ActionApi extends Action {

    /**
     * Текущий метод обращения к АПИ
     * @var null
     */
    protected $bIsAjax = NULL;

    /**
     * Проверяет метод запроса на соответствие
     *
     * @param string $sRequestMethod
     * @return bool
     */
    private function _CheckRequestMethod($sRequestMethod)
    {
        $sRequestMethod = strtoupper($sRequestMethod);

        if (!in_array($sRequestMethod, array('GET', 'POST', 'PUT', 'DELETE'), true)) {
            return FALSE;
        }

        return $this->_getRequestMethod() === $sRequestMethod;
    }

    /**
     * Выводит ошибку
     *
     * @param $aError
     */
    protected function _error($aError)
    {
        \E::Module('Api')->setLastError($aError);
        $this->eventError();
    }

    /**
     * Инициализация
     */
    public function init()
    {
        /**
         * Установим шаблон вывода
         */
        $this->setTemplate('api/answer.tpl');

        return TRUE;
    }

    /**
     * Ошибочные экшены отдаём как ошибку неизвестного API метода
     */
    public function eventNotFound()
    {
        \E::Module('Api')->setLastError(\E::Module('Api')->ERROR_CODE_9002);
        $this->eventError();
    }

    /**
     * Метод выода ошибки
     */
    public function eventError()
    {
        // Запретим прямой доступ
        if (!($aError = \E::Module('Api')->getLastError())) {
            $aError = \E::Module('Api')->ERROR_CODE_9002;
        }

        if ($aError['code'] == '0004') {
            F::HttpResponseCode(403);
        } else {
            // Установим код ошибки - Bad Request
            F::HttpResponseCode(400);
        }

        // Отправим ошибку пользователю
        if ($this->bIsAjax) {
            \E::Module('Message')->addErrorSingle('error');
            \E::Module('Viewer')->assignAjax('result', json_encode(['error' => $aError]));
        } else {
            \E::Module('Viewer')->assign('result', json_encode(['error' => $aError]));
        }

        \E::Module('Api')->SetLastError(NULL);

        return FALSE;
    }

    /**
     * Проверка на право доступа к методу API
     *
     * @param string $sEvent
     * @return bool|string
     */
    public function access($sEvent)
    {
        // Возможно это ajax-запрос, тогда нужно проверить разрешены ли
        // вообще такие запросы к нашему API
        if (\F::AjaxRequest()) {
            if (C::get('module.api.ajax')) {
                $this->bIsAjax = TRUE;
                \E::Module('Viewer')->setResponseAjax('json');
            } else {
                return $this->_error(\E::Module('Api')->ERROR_CODE_9014);
            }
        } else {
            // Проверим, разрешённые типы запросов к АПИ
            foreach ([
                         'post'   => \E::Module('Api')->ERROR_CODE_9010,
                         'get'    => \E::Module('Api')->ERROR_CODE_9011,
                         'put'    => \E::Module('Api')->ERROR_CODE_9012,
                         'delete' => \E::Module('Api')->ERROR_CODE_9013
                     ] as $sRequestMethod => $aErrorDescription) {
                if ($this->_checkRequestMethod($sRequestMethod) && !\C::get("module.api.{$sRequestMethod}")) {
                    return $this->_error($aErrorDescription);
                }
            }
        }

        return TRUE;
    }

    /**
     * @param null $sEvent
     *
     * @return string
     */
    public function accessDenied($sEvent = null)
    {
        return $this->_error(\E::Module('Api')->ERROR_CODE_9004);
    }

    /**
     * Получает все параметры указанного метода запроса вместе с требуемым действием
     *
     * @param array  $aData
     * @param string $sRequestMethod Метод запроса
     *
     * @return array
     */
    protected function _getParams($aData, $sRequestMethod)
    {
        $sRequestMethod = strtoupper($sRequestMethod);
        $aParams = $this->_getRequestData($sRequestMethod);

        foreach ($aParams as $k => $v) {
            if (strtoupper($aParams[$k]) == 'TRUE') $aParams[$k] = TRUE;
            if (strtoupper($aParams[$k]) == 'FALSE') $aParams[$k] = FALSE;
        }

        return array_merge($aData, ['params' => $aParams]);
    }

    /**
     * Абстрактный метод регистрации евентов.
     * В нём необходимо вызывать метод AddEvent($sEventName, $sEventFunction)
     * Например:
     *      $this->AddEvent('index', 'eventIndex');
     *      $this->AddEventPreg('/^admin$/i', '/^\d+$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventAdminBlog');
     */
    protected function registerEvent()
    {
        // Установим экшены ресурсов
        $this->addEventPreg('/^user$/i', '/^\d+$/i', '/^info$/i', 'eventApiUserIdInfo');
        $this->addEventPreg('/^topic$/i', '/^\d+$/i', '/^info$/i', 'eventApiTopicIdInfo');
        $this->addEventPreg('/^blog$/i', '/^\d+$/i', '/^info$/i', 'eventApiBlogIdInfo');

        // И экшен ошибки
        $this->addEventPreg('/^error/i', 'eventError');
    }


    /******************************************************************************************************
     *              МЕТОД USER
     ******************************************************************************************************/
    /**
     * Экшен обработки API вида 'api/user/id/info'
     * @return bool
     */
    public function eventApiUserIdInfo()
    {
        $sErrorDescription = $this->_apiResult(
            'api/user/id/info',
            $this->_getParams(['uid' => R::getParam(0), 'cmd' => R::getParam(1)], 'GET')
        );

        if ($sErrorDescription !== FALSE) {
            return $this->_error($sErrorDescription);
        }

        return TRUE;
    }


    /******************************************************************************************************
     *              МЕТОД TOPIC
     ******************************************************************************************************/
    /**
     * Экшен обработки API вида topic/*
     * @return bool
     */
    public function eventApiTopicIdInfo()
    {
        $sErrorDescription = $this->_apiResult(
            'api/topic/id/rating',
            $this->_getParams(array('tid' => R::getParam(0), 'cmd' => R::getParam(1)), 'GET')
        );

        if ($sErrorDescription !== FALSE) {
            return $this->_error($sErrorDescription);
        }

        return TRUE;
    }


    /******************************************************************************************************
     *              МЕТОД BLOG
     ******************************************************************************************************/
    /**
     * Экшен обработки API вида topic/*
     * @return bool
     */
    public function eventApiBlogIdInfo() {


        $sErrorDescription = $this->_apiResult(
            'api/blog/id/info',
            $this->_getParams(array('uid' => R::getParam(0), 'cmd' => R::getParam(1)), 'GET')
        );

        if ($sErrorDescription !== FALSE) {
            return $this->_error($sErrorDescription);
        }

        return TRUE;
    }


    /******************************************************************************************************
     *              ОБЩИЕ ЗАЩИЩЁННЫЕ И ПРИВАТНЫЕ МЕТОДЫ
     ******************************************************************************************************/
    /**
     * Получение результата от модуля API
     * @param string $sResourceName Имя объекта ресурса
     * @param array $aData Данные для формировния ресурса
     * @return string
     */
    protected function _apiResult($sResourceName, $aData)
    {
        $sApiMethod = '';
        foreach (explode('/', $sResourceName) as $sPart) {
            $sApiMethod .= ucfirst($sPart);
        }

        // Если результата нет, выведем ошибку плохого ресурса
        if (!\E::Module('Api')->MethodExists($sApiMethod)) {
            return \E::Module('Api')->ERROR_CODE_9001;
        }
        // Или отсутствие ресурса
        if (!($aResult = \E::Module('Api')->$sApiMethod($aData))) {
            return \E::Module('Api')->ERROR_CODE_9003;
        }

        // Определим формат данных
        if (!empty($aData['params']['tpl'])) {
            $sTemplate = $aData['params']['tpl'];
        } elseif(!empty($aData['params']['role']) && $aData['params']['role'] == 'popover') {
            $sTemplate = 'default';
        } else {
            $sTemplate = null;
        }
        if ($sTemplate) {
            $sResult = $this->_fetch($sResourceName, $aResult['data'], $sTemplate);
        } else {
            $sResult = $aResult['json'];
        }

        $aResult = array(
            'data'   => $sResult,
            'params' => $aData['params'],
        );

        $sResult = json_encode($aResult);

        if ($this->bIsAjax) {
            \E::Module('Viewer')->assignAjax('result', $sResult);
        } else {
            \E::Module('Viewer')->assign('result', $sResult);
        }

        return FALSE;
    }

    /**
     * Рендеринг шаблона
     *
     * @param string      $sCmd
     * @param array       $aData
     * @param string|null $sTemplate
     *
     * @return string
     */
    protected function _fetch($sCmd, $aData, $sTemplate = null)
    {
        /** @var ModuleViewer $oLocalViewer */
        $oLocalViewer =  \E::Module('Viewer')->getLocalViewer();

        $sHtml = '';
        if ($sTpl = $sCmd . '/' . str_replace('/', '.', $sCmd . '.' . (is_string($sTemplate) ? $sTemplate : 'default') . '.tpl')) {
            if (!$oLocalViewer->templateExists($sTpl)) {
                $sTpl = $sCmd . '/' . str_replace('/', '.', $sCmd . '.' . 'default.tpl');
            }
            $oLocalViewer->assign($aData);
            $sHtml = $oLocalViewer->fetch($sTpl);
        }

        return $sHtml;
    }

}

// EOF