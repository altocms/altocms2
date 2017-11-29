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
 * Экшен обработки УРЛа вида /comments/
 *
 * @package actions
 * @since   1.0
 */
class ActionComments extends Action {
    /**
     * Текущий юзер
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserCurrent = null;
    /**
     * Главное меню
     *
     * @var string
     */
    protected $sMenuHeadItemSelect = 'blog';

    /**
     * Инициализация
     */
    public function init()
    {
        $this->oUserCurrent = \E::User();
    }

    /**
     * Регистрация евентов
     */
    protected function registerEvent()
    {
        $this->addEvent('index', 'eventComments');
        $this->addEventPreg('/^(page([1-9]\d{0,5}))?$/i', 'eventComments');
        $this->addEventPreg('/^\d+$/i', 'eventShowComment');
    }


    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    /**
     * Выводим список комментариев
     *
     */
    public function eventComments()
    {
        // * Передан ли номер страницы
        $iPage = $this->getEventMatch(2) ? $this->getEventMatch(2) : 1;

        // * Исключаем из выборки идентификаторы закрытых блогов (target_parent_id)
        $aCloseBlogs = ($this->oUserCurrent)
            ? \E::Module('Blog')->getInaccessibleBlogsByUser($this->oUserCurrent)
            : \E::Module('Blog')->getInaccessibleBlogsByUser();

        // * Получаем список комментов
        $aResult = \E::Module('Comment')->getCommentsAll(
            'topic', $iPage, \C::get('module.comment.per_page'), [], $aCloseBlogs
        );
        $aComments = $aResult['collection'];

        // * Формируем постраничность
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.comment.per_page'), \C::get('pagination.pages.count'),
            R::getLink('comments')
        );

        // * Загружаем переменные в шаблон
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aComments', $aComments);

        // * Устанавливаем title страницы
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('comments_all'));
        \E::Module('Viewer')->setHtmlRssAlternate(R::getLink('rss') . 'allcomments/', \E::Module('Lang')->get('comments_all'));

        // * Устанавливаем шаблон вывода
        $this->setTemplateAction('index');
    }

    /**
     * Обрабатывает ссылку на конкретный комментарий, определят к какому топику он относится и перенаправляет на него
     * Актуально при использовании постраничности комментариев
     */
    public function eventShowComment()
    {
        $iCommentId = $this->sCurrentEvent;

        // * Проверяем к чему относится комментарий
        if (!($oComment = \E::Module('Comment')->getCommentById($iCommentId))) {
            return parent::eventNotFound();
        }
        if ($oComment->getTargetType() != 'topic' || !($oTopic = $oComment->getTarget())) {
            return parent::eventNotFound();
        }

        // * Определяем необходимую страницу для отображения комментария
        if (!\C::get('module.comment.use_nested') || !\C::get('module.comment.nested_per_page')) {
            R::Location($oTopic->getUrl() . '#comment' . $oComment->getId());
        }
        $iPage = \E::Module('Comment')->getPageCommentByTargetId(
            $oComment->getTargetId(), $oComment->getTargetType(), $oComment
        );
        if ($iPage == 1) {
            R::Location($oTopic->getUrl() . '#comment' . $oComment->getId());
        } else {
            R::Location($oTopic->getUrl() . "?cmtpage={$iPage}#comment" . $oComment->getId());
        }
        exit();
    }

    /**
     * Выполняется при завершении работы экшена
     *
     */
    public function eventShutdown()
    {
        // * Загружаем в шаблон необходимые переменные
        \E::Module('Viewer')->assign('sMenuHeadItemSelect', $this->sMenuHeadItemSelect);
    }

}

// EOF