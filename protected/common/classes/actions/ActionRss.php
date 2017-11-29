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
 * @package actions
 * @since   1.0
 */
class ActionRss extends Action
{
    /**
     * Инициализация
     */
    public function init()
    {
        $this->setDefaultEvent('index');
    }

    /**
     * Указывает браузеру правильный content type в случае вывода RSS-ленты
     */
    protected function InitRss()
    {
        header('Content-Type: application/rss+xml; charset=utf-8');
    }

    /**
     * Регистрация евентов
     */
    protected function registerEvent()
    {
        $this->addEventPreg('/^index$/', '/^new$/', 'RssTopics');
        $this->addEventPreg('/^index$/', '/^all$/', 'RssTopics');
        $this->addEvent('index', 'RssTopics');
        $this->addEvent('new', 'RssTopics');
        $this->addEvent('wall', 'RssWall');
        $this->addEvent('allcomments', 'RssComments');
        $this->addEventPreg('/^comments$/', '/^\d+$/', 'RssCommentsByTopic');
        $this->addEvent('tag', 'RssTopics');
        $this->addEvent('blog', 'RssBlog');
        $this->addEvent('personal_blog', 'RssPersonalBlog');
    }

    /**
     * Вывод RSS последних комментариев
     */
    protected function rssWall()
    {
        $aResult = \E::Module('Wall')->getWall([], ['date_add' => 'DESC'], 1, \C::get('module.wall.per_page'));
        /** @var ModuleWall_EntityWall[] $aWall */
        $aWall = $aResult['collection'];

        $aRssChannelData = [
            'title' => C::get('view.name'),
            'description' => C::get('path.root.url') . ' / Wall RSS channel',
            'link' => C::get('path.root.url'),
            'languages' => C::get('lang.current'),
            'managing_editor' => C::get('general.rss_editor_mail'),
            'web_master' => C::get('general.rss_editor_mail'),
            'generator' => 'Alto CMS v.' . ALTO_VERSION,
        ];

        /** @var ModuleRss_EntityRssChannel $oRssChannel */
        $oRssChannel = \E::getEntity('ModuleRss_EntityRssChannel', $aRssChannelData);

        /** @var ModuleRss_EntityRss $oRss */
        $oRss = \E::getEntity('Rss');

        if ($aWall) {
            // Adds items into RSS channel
            foreach ($aWall as $oItem) {
                if ($oItem) {
                    $oRssChannel->AddItem($oItem->CreateRssItem());
                }
            }
        }
        $oRss->addChannel($oRssChannel);

        $this->_displayRss($oRss);
    }

    /**
     * Event RssComments
     *
     * @return string
     */
    protected function rssComments()
    {
        $sEvent = $this->getParam(0);
        $aParams = $this->getParams();
        array_shift($aParams);
        \HookManager::addHandler('action_after', [$this, 'ShowRssComments']);
        return R::redirect('comments', $sEvent, $aParams);
    }

    /**
     * Event RssCommentsByTopic
     *
     * @return string
     */
    protected function rssCommentsByTopic()
    {
        $sEvent = $this->getParam(0);
        $aParams = $this->getParams();
        array_shift($aParams);
        \HookManager::addHandler('action_after', [$this, 'ShowRssComments']);
        return R::redirect('blog', $sEvent . '.html', $aParams);
    }

    /**
     * Show rss comments by hook
     *
     */
    public function showRssComments()
    {
        $aComments = \E::Module('Viewer')->getTemplateVars('aComments');
        $this->_showRssItems($aComments);
    }

    /**
     * Event RssTopics
     *
     * @return string
     */
    protected function rssTopics()
    {
        $sEvent = $this->getParam(0);
        $aParams = $this->getParams();
        array_shift($aParams);
        \HookManager::addHandler('action_after', [$this, 'ShowRssTopics']);
        return R::redirect($this->sCurrentEvent, $sEvent, $aParams);
    }

    /**
     * Show rss topics by hook
     *
     */
    public function showRssTopics()
    {
        $aTopics = \E::Module('Viewer')->getTemplateVars('aTopics');
        $this->_showRssItems($aTopics);
    }

    /**
     * Create and show rss channel
     *
     * @param $aItems
     */
    protected function _showRssItems($aItems)
    {
        $aParts = explode('/', trim(R::url('path'), '/'), 2);
        if (isset($aParts[1])) {
            $sLink = R::getLink('/' . $aParts[1]);
        } else {
            $sLink = R::getLink('/');
        }
        if ($sQuery = R::url('query')) {
            $sLink .= '?' . $sQuery;
        }

        $aRssChannelData = [
            'title' =>  \E::Module('Viewer')->getHtmlTitle(),
            'description' =>  \E::Module('Viewer')->getHtmlDescription(),
            'link' => $sLink,
            'languages' => C::get('lang.current'),
            'managing_editor' => C::get('general.rss_editor_mail'),
            'web_master' => C::get('general.rss_editor_mail'),
            'generator' => 'Alto CMS v.' . ALTO_VERSION,
        ];

        /** @var ModuleRss_EntityRssChannel $oRssChannel */
        $oRssChannel = \E::getEntity('ModuleRss_EntityRssChannel', $aRssChannelData);

        /** @var ModuleRss_EntityRss $oRss */
        $oRss = \E::getEntity('Rss');

        if ($aItems) {
            // Adds items into RSS channel
            foreach ($aItems as $oItem) {
                if ($oItem) {
                    $oRssChannel->addItem($oItem->CreateRssItem());
                }
            }
        }
        $oRss->AddChannel($oRssChannel);

        $this->_displayRss($oRss);
    }

    /**
     * Вывод RSS топиков из блога
     */
    protected function rssBlog()
    {
        $sBlogUrl = $this->getParam(0);
        $aParams = $this->getParams();
        array_shift($aParams);
        \HookManager::addHandler('action_after', [$this, 'ShowRssBlog']);

        if ($iMaxItems = (int)C::get('module.topic.max_rss_count')) {
            C::set('module.topic.per_page', $iMaxItems);
        }

        return R::redirect('blog', $sBlogUrl, $aParams);
    }

    /**
     * @return null|string
     */
    protected function rssPersonalBlog()
    {
        $sUserLogin = $this->getParam(0);
        $aParams = $this->getParams();
        array_shift($aParams);

        if ($iMaxItems = (int)C::get('module.topic.max_rss_count')) {
            C::set('module.topic.per_page', $iMaxItems);
        }

        $oUser = \E::Module('User')->getUserByLogin($sUserLogin);
        if ($oUser && ($oBlog = \E::Module('Blog')->getPersonalBlogByUserId($oUser->getId()))) {
            \HookManager::addHandler('action_after', array($this, 'ShowRssBlog'));
            return R::redirect('blog', $oBlog->getId(), $aParams);
        } else {
            $this->_displayEmptyRss();
        }
        return null;
    }

    /**
     *
     */
    public function showRssBlog()
    {
        /** @var ModuleTopic_EntityTopic[] $aTopics */
        $aTopics = \E::Module('Viewer')->getTemplateVars('aTopics');

        /** @var ModuleBlog_EntityBlog $oBlog */
        $oBlog = \E::Module('Viewer')->getTemplateVars('oBlog');

        if ($oBlog) {
            /** @var ModuleRss_EntityRss $oRss */
            $oRss = \E::getEntity('Rss');

            // Creates RSS channel from the blog
            $oRssChannel = $oBlog->CreateRssChannel();

            if (is_array($aTopics)) {
                // Adds items into RSS channel
                foreach ($aTopics as $oTopic) {
                    $oRssChannel->AddItem($oTopic->CreateRssItem());
                }
            }
            $oRss->AddChannel($oRssChannel);

            $this->_displayRss($oRss);
        } else {
            F::httpResponseCode(404);
            $this->_displayEmptyRss();
        }
    }

    /**
     * @param $oRss
     */
    protected function _displayRss($oRss)
    {
        \E::Module('Viewer')->assign('oRss', $oRss);
        \E::Module('Viewer')->responseSetHeader('Content-type', 'text/xml; charset=utf-8');
        \E::Module('Viewer')->display('actions/rss/action.rss.index.tpl');

        exit;
    }

    /**
     *
     * @throws \RuntimeException
     */
    protected function _displayEmptyRss()
    {
        $oRss = \E::getEntity('Rss');
        $this->_displayRss($oRss);
    }

}

// EOF