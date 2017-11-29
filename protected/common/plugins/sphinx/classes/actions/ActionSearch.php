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
 * Экшен обработки поиска по сайту через поисковый движок Sphinx
 *
 * @package actions
 * @since   1.0
 */
class PluginSphinx_ActionSearch extends ActionPlugin 
{
    /**
     * Допустимые типы поиска с параметрами
     *
     * @var array
     */
    protected $aTypesEnabled = [
        'topics'   => ['topic_publish' => 1],
        'comments' => ['comment_delete' => 0]
    ];

    /**
     * Массив результата от Сфинкса
     *
     * @var null|array
     */
    protected $aSphinxRes = null;

    /**
     * Поиск вернул результат или нет
     *
     * @var bool
     */
    protected $bIsResults = false;

    /**
     * Инициализация
     */
    public function init() 
    {
        $this->setDefaultEvent('index');
        E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('search'));
    }

    /**
     * Регистрация евентов
     */
    protected function registerEvent() 
    {
        $this->addEvent('index', 'eventIndex');
        $this->addEvent('topics', 'eventTopics');
        $this->addEvent('comments', 'eventComments');
        $this->addEvent('opensearch', 'eventOpenSearch');
    }

    /**
     * Отображение формы поиска
     */
    public function eventIndex() 
    {
    }

    /**
     * Обработка стандарта для браузеров Open Search
     */
    public function eventOpenSearch() 
    {
        E::Module('Viewer')->assign('sAdminMail', Config::get('sys.mail.from_email'));
    }

    /**
     * Поиск топиков
     *
     */
    public function eventTopics() 
    {
        // * Ищем
        $aReq = $this->_prepareRequest();
        $aRes = $this->_prepareResults($aReq, Config::get('module.topic.per_page'));
        if (false === $aRes) {
            E::Module('Message')->AddErrorSingle(\E::Module('Lang')->get('system_error'));
            return R::redirect('error');
        }

        // * Если поиск дал результаты
        if ($this->bIsResults) {

            // * Получаем топик-объекты по списку идентификаторов
            $aTopics = \E::Module('Topic')->getTopicsAdditionalData(array_keys($this->aSphinxRes['matches']));

            // * Конфигурируем парсер
            $oTextParser = ModuleText::newTextParser('search');

            $aErrors = [];

            // *  Делаем сниппеты
            /** @var ModuleTopic_EntityTopic $oTopic */
            foreach ($aTopics AS $oTopic) {
                // * Т.к. текст в сниппетах небольшой, то можно прогнать через парсер
                $oTopic->setTextShort(
                    $oTextParser->parse(
                        E::Module('Sphinx')->getSnippet(
                            $oTopic->getText(),
                            'topics',
                            $aReq['q'],
                            '<span class="searched-item">',
                            '</span>'
                        ), $aErrors
                    )
                );
            }
            /**
             *  Отправляем данные в шаблон
             */
            E::Module('Viewer')->assign('bIsResults', true);
            E::Module('Viewer')->assign('aRes', $aRes);
            E::Module('Viewer')->assign('aTopics', $aTopics);
        }
    }

    /**
     * Поиск комментариев
     *
     */
    public function eventComments()
    {
        // * Ищем
        $aReq = $this->_prepareRequest();
        $aRes = $this->_prepareResults($aReq, Config::get('module.comment.per_page'));
        if (false === $aRes) {
            E::Module('Message')->AddErrorSingle(\E::Module('Lang')->get('system_error'));
            return R::redirect('error');
        }

        // * Если поиск дал результаты
        if ($this->bIsResults) {

            // *  Получаем топик-объекты по списку идентификаторов
            $aComments = \E::Module('Comment')->getCommentsAdditionalData(array_keys($this->aSphinxRes['matches']));

            // * Конфигурируем парсер
            $oTextParser = ModuleText::newTextParser('search');

            $aErrors = [];
            // * Делаем сниппеты
            /** @var ModuleComment_EntityComment $oComment */
            foreach ($aComments AS $oComment) {
                $oComment->setText(
                    $oTextParser->parse(
                        E::Module('Sphinx')->getSnippet(
                            htmlspecialchars($oComment->getText()),
                            'comments',
                            $aReq['q'],
                            '<span class="searched-item">',
                            '</span>'
                        ), $aErrors
                    )
                );
            }
            /**
             *  Отправляем данные в шаблон
             */
            E::Module('Viewer')->assign('aRes', $aRes);
            E::Module('Viewer')->assign('aComments', $aComments);
        }
    }

    /**
     * Подготовка запроса на поиск
     *
     * @return array
     */
    protected function _prepareRequest() 
    {
        $aReq['q'] = F::getRequestStr('q');
        if (!F::CheckVal($aReq['q'], 'text', 2, 255)) {
            // * Если запрос слишком короткий перенаправляем на начальную страницу поиска
            // * Хотя тут лучше показывать юзеру в чем он виноват
            R::Location(R::getLink('search'));
        }
        $aReq['sType'] = strtolower(R::getControllerAction());

        // * Определяем текущую страницу вывода результата
        $aReq['iPage'] = (int)preg_replace('#^page([1-9]\d{0,5})$#', '\1', $this->getParam(0));
        if (!$aReq['iPage']) {
            $aReq['iPage'] = 1;
        }

        // *  Передача данных в шаблонизатор
        E::Module('Viewer')->assign('aReq', $aReq);

        return $aReq;
    }

    /**
     * Поиск и формирование результата
     *
     * @param array $aReq
     * @param int   $iLimit
     *
     * @return array|bool
     */
    protected function _prepareResults($aReq, $iLimit) 
    {
        // *  Количество результатов по типам
        $aRes = [];
        foreach ($this->aTypesEnabled as $sType => $aExtra) {
            $aRes['aCounts'][$sType] = (int)E::Module('Sphinx')->getNumResultsByType($aReq['q'], $sType, $aExtra);
        }
        if (empty($aRes['aCounts'][$aReq['sType']])) {
            // *  Объектов необходимого типа не найдено
            unset($this->aTypesEnabled[$aReq['sType']]);
            // * Проверяем отсальные типы
            foreach(array_keys($this->aTypesEnabled) as $sType) {
                if ($aRes['aCounts'][$sType]) {
                    R::Location(R::getLink('search') . $sType . '/?q=' . $aReq['q']);
                }
            }
        } elseif (($aReq['iPage'] - 1) * $iLimit <= $aRes['aCounts'][$aReq['sType']]) {
            // * Ищем
            $this->aSphinxRes = \E::Module('Sphinx')->findContent(
                $aReq['q'],
                $aReq['sType'],
                ($aReq['iPage'] - 1) * $iLimit,
                $iLimit,
                $this->aTypesEnabled[$aReq['sType']]
            );

            // * Возможно демон Сфинкса не доступен
            if (false === $this->aSphinxRes) {
                return false;
            }

            $this->bIsResults = true;

            // * Формируем постраничный вывод
            $aPaging = \E::Module('Viewer')->makePaging(
                $aRes['aCounts'][$aReq['sType']],
                $aReq['iPage'],
                $iLimit,
                Config::get('pagination.pages.count'),
                R::getLink('search') . $aReq['sType'],
                [
                     'q' => $aReq['q']
                ]
            );
            E::Module('Viewer')->assign('aPaging', $aPaging);
        }

        $this->setTemplateAction('results');
        E::Module('Viewer')->addHtmlTitle($aReq['q']);
        E::Module('Viewer')->assign('bIsResults', $this->bIsResults);
        return $aRes;
    }

}

// EOF