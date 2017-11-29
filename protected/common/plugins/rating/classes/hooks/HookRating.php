<?php

/**
 * HookRating
 * Файл хука плагина Rating
 *
 * @author      Андрей Воронов <andreyv@gladcode.ru>
 *              Является частью плагина Rating
 * @version     0.0.1 от 30.01.2015 17:45
 */
class PluginRating_HookRating extends Hook {

    /**
     * Регистрация событий на хуки
     */
    public function registerHook() {

        // Выводим интерфейс работы с рейтингом только если он включён
        if (C::get('rating.enabled')) {
            if (C::get('plugin.rating.user.vote')) {
                $this->AddHook('template_profile_header', 'HookProfileRatingInject');
                $this->AddHook('template_user_list_header', 'HookUserListHeaderInject');
                $this->AddHook('template_user_list_line', 'HookUserListLineInject');
                $this->AddHook('template_user_list_linexxs', 'HookUserListLineXssInject');
            }

            if (C::get('plugin.rating.blog.vote')) {
                $this->AddHook('template_blog_infobox', 'HookBlogInfoboxRatingValueInject');
                $this->AddHook('template_blog_list_header', 'HookBlogListHeaderInject');
                $this->AddHook('template_blog_list_line', 'HookBlogListLineInject');
                $this->AddHook('template_blog_list_linexxs', 'HookBlogListLineXssInject');
                $this->AddHook('template_blog_header', 'HookBlogHeaderInject');
                $this->AddHook('template_blog_stat', 'HookBlogStatInject');
            }

            if (C::get('plugin.rating.comment.vote')) {
                $this->AddHook('template_comment_list_info', 'HookCommentListInfoInject');
                $this->AddHook('template_comment_info', 'HookCommentInfoInject');
            }

            if (C::get('plugin.rating.topic.vote') || C::get('plugin.rating.rating.vote')) {
                $this->AddHook('template_topic_show_info', 'HookTopicShowInfoInject');
            }

        }

    }

    /**
     * Метод добавления рейтинга в профиле пользователя
     * @param $aData
     */
    public function HookProfileRatingInject($aData) {

        /** @var ModuleUser_EntityUser $oUserProfile */
        $oUserProfile = $aData['oUserProfile'];
        /** @var ModuleVote_EntityVote $oVote */
        $oVote = $aData['oVote'];

        E::Module('Viewer')->assign('oUserProfile', $oUserProfile);
        E::Module('Viewer')->assign('oVote', $oVote);
        $sHtml = \E::Module('Viewer')->fetch(Plugin::GetTemplatePath(__CLASS__) . 'tpls/user/profile.header.inject.tpl');

        return $sHtml;

    }

    /**
     * Метод добавления ссылок в шапке списка пользователей
     * @param $aData
     */
    public function HookUserListHeaderInject($aData) {

        $bUsersUseOrder = $aData['bUsersUseOrder'];
        $sUsersRootPage = $aData['sUsersRootPage'];
        $sUsersOrderWay = $aData['sUsersOrderWay'];
        $sUsersOrder = $aData['sUsersOrder'];


        E::Module('Viewer')->assign('bUsersUseOrder', $bUsersUseOrder);
        E::Module('Viewer')->assign('sUsersRootPage', $sUsersRootPage);
        E::Module('Viewer')->assign('sUsersOrderWay', $sUsersOrderWay);
        E::Module('Viewer')->assign('sUsersOrder', $sUsersOrder);

        $sHtml = \E::Module('Viewer')->fetch(Plugin::GetTemplatePath(__CLASS__) . 'tpls/user/user.list.header.inject.tpl');

        return $sHtml;

    }

    /**
     * Метод добавления строки в списке пользователей
     * @param $aData
     */
    public function HookUserListLineInject($aData) {

        $oUserList = $aData['oUserList'];

        E::Module('Viewer')->assign('oUserList', $oUserList);

        $sHtml = \E::Module('Viewer')->fetch(Plugin::GetTemplatePath(__CLASS__) . 'tpls/user/user.list.line.inject.tpl');

        return $sHtml;

    }

    /**
     * Метод добавления строки в списке пользователей
     * @param $aData
     */
    public function HookUserListLineXssInject($aData) {

        $oUserList = $aData['oUserList'];

        E::Module('Viewer')->assign('oUserList', $oUserList);

        $sHtml = \E::Module('Viewer')->fetch(Plugin::GetTemplatePath(__CLASS__) . 'tpls/user/user.list.linexxs.inject.tpl');

        return $sHtml;

    }

    /**
     * Метод вывода рейтинга блога в инфобоксе
     * @param $aData
     */
    public function HookBlogInfoboxRatingValueInject($aData) {

        $oBlog = $aData['oBlog'];
        E::Module('Viewer')->assign('oBlog', $oBlog);

        return E::Module('Viewer')->fetch(Plugin::GetTemplatePath(__CLASS__) . 'tpls/blog/blog.infobox.rating.value.inject.tpl');

    }

    /**
     * Метод вывода шапки таблицы блогов
     * @param $aData
     * @return mixed
     */
    public function HookBlogListHeaderInject($aData) {

        $bBlogsUseOrder = $aData['bBlogsUseOrder'];
        $sBlogsRootPage = $aData['sBlogsRootPage'];
        $sBlogOrder = $aData['sBlogOrder'];
        $sBlogOrderWayNext = $aData['sBlogOrderWayNext'];
        $sBlogOrderWay = $aData['sBlogOrderWay'];


        E::Module('Viewer')->assign('bBlogsUseOrder', $bBlogsUseOrder);
        E::Module('Viewer')->assign('sBlogsRootPage', $sBlogsRootPage);
        E::Module('Viewer')->assign('sBlogOrder', $sBlogOrder);
        E::Module('Viewer')->assign('sBlogOrderWayNext', $sBlogOrderWayNext);
        E::Module('Viewer')->assign('sBlogOrderWay', $sBlogOrderWay);

        $sHtml = \E::Module('Viewer')->fetch(Plugin::GetTemplatePath(__CLASS__) . 'tpls/blog/blog.list.header.inject.tpl');

        return $sHtml;

    }

    /**
     * Метод вывода строки блогов
     * @param $aData
     * @return mixed
     */
    public function HookBlogListLineInject($aData) {

        $oBlog = $aData['oBlog'];
        E::Module('Viewer')->assign('oBlog', $oBlog);

        return E::Module('Viewer')->fetch(Plugin::GetTemplatePath(__CLASS__) . 'tpls/blog/blog.list.line.inject.tpl');

    }

    /**
     * Метод вывода строки блогов
     * @param $aData
     * @return mixed
     */
    public function HookBlogListLineXssInject($aData) {

        $oBlog = $aData['oBlog'];
        E::Module('Viewer')->assign('oBlog', $oBlog);

        return E::Module('Viewer')->fetch(Plugin::GetTemplatePath(__CLASS__) . 'tpls/blog/blog.list.linexxs.inject.tpl');

    }

    /**
     * Вывод рейтинга блога в шапке блога
     * @param $aData
     * @return mixed
     */
    public function HookBlogHeaderInject($aData) {

        $oBlog = $aData['oBlog'];
        E::Module('Viewer')->assign('oBlog', $oBlog);

        return E::Module('Viewer')->fetch(Plugin::GetTemplatePath(__CLASS__) . 'tpls/blog/blog.header.inject.tpl');

    }

    /**
     * Вывод рейтинга в строке доп. информации блога
     * @param $aData
     * @return mixed
     */
    public function HookBlogStatInject($aData) {

        $oBlog = $aData['oBlog'];
        E::Module('Viewer')->assign('oBlog', $oBlog);

        return E::Module('Viewer')->fetch(Plugin::GetTemplatePath(__CLASS__) . 'tpls/blog/blog.info.inject.tpl');

    }

    /**
     * Рейтинг у комментария в списке комментариев
     * @param $aData
     * @return mixed
     */
    public function HookCommentListInfoInject($aData) {

        $oComment = $aData['oComment'];
        E::Module('Viewer')->assign('oComment', $oComment);

        return E::Module('Viewer')->fetch(Plugin::GetTemplatePath(__CLASS__) . 'tpls/comment/comment.list.info.inject.tpl');

    }

    /**
     * Вывод рейтинга комментария в дереве комментариев
     * @param $aData
     * @return mixed
     */
    public function HookCommentInfoInject($aData) {

        $oComment = $aData['oComment'];
        E::Module('Viewer')->assign('oComment', $oComment);

        return E::Module('Viewer')->fetch(Plugin::GetTemplatePath(__CLASS__) . 'tpls/comment/comment.info.inject.tpl');

    }

    /**
     * Вывод голосовалки топика
     * @param $aData
     * @return mixed
     * {hook run='topic_show_info' topic=$oTopic bTopicList=false bSidebar=true oVote=$oVote}
     */
    public function HookTopicShowInfoInject($aData) {

        $oTopic = $aData['topic'];
        /** @var ModuleVote_EntityVote $oVote */
        $oVote = isset($aData['oVote']) ? $aData['oVote'] : FALSE;
        $bTopicList = isset($aData['bTopicList']) ? $aData['bTopicList'] : FALSE;
        $bSidebar = isset($aData['bSidebar']) ? $aData['bSidebar'] : FALSE;

        E::Module('Viewer')->assign('oTopic', $oTopic);
        E::Module('Viewer')->assign('oVote', $oVote);
        E::Module('Viewer')->assign('bTopicList', $bTopicList);
        E::Module('Viewer')->assign('bSidebar', $bSidebar);

        return E::Module('Viewer')->fetch(Plugin::GetTemplatePath(__CLASS__) . 'tpls/topic/topic.show.info.inject.tpl');

    }

}
