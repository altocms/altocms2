<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

use \alto\engine\generic\Action;

/**
 * @package actions
 * @since 0.9
 */
class ActionAdmin extends Action {
    /**
     * Текущий пользователь
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserCurrent = null;
    /**
     * Главное меню
     *
     * @var string
     */
    protected $sMainMenuItem = '';

    protected $sMenuItem = '';

    /**
     * Инициализация
     *
     * @return string
     */
    public function init()
    {
        if (\E::isUser()) {
            $this->oUserCurrent = \E::User();
        }

        // * Если нет прав доступа - перекидываем на 404 страницу
        // * Но нужно это делать через Router::Location, т.к. Viewer может быть уже инициирован
        if (!$this->oUserCurrent || !$this->oUserCurrent->isAdministrator()) {
            R::Location('error/404/');
        }
        $this->setDefaultEvent('info-dashboard');
    }

    /**
     * Регистрация евентов
     */
    protected function registerEvent() {

        $this->addEvent('info-dashboard', 'eventDashboard');
        $this->addEvent('info-report', 'eventReport');
        $this->addEvent('info-phpinfo', 'eventPhpinfo');

        $this->addEvent('content-pages', 'eventPages');
        $this->addEvent('content-blogs', 'eventBlogs');
        $this->addEvent('content-topics', 'eventTopics');
        $this->addEvent('content-comments', 'eventComments');
        $this->addEvent('content-mresources', 'eventMresources');

        $this->addEvent('users-list', 'eventUsers');
        $this->addEvent('users-banlist', 'eventBanlist');
        $this->addEvent('users-invites', 'eventInvites');

        $this->addEvent('settings-site', 'eventConfig');
        $this->addEvent('settings-lang', 'eventLang');
        $this->addEvent('settings-blogtypes', 'eventBlogTypes');
        $this->addEvent('settings-userrights', 'eventUserRights');
        $this->addEvent('settings-userfields', 'eventUserFields');
        $this->addEvent('settings-menumanager', 'eventMenuManager');

        $this->addEvent('site-skins', 'eventSkins');
        $this->addEvent('site-widgets', 'eventWidgets');
        $this->addEvent('site-plugins', 'eventPlugins');
        $this->addEvent('site-scripts', 'eventScripts');

        $this->addEvent('logs-error', 'eventLogs');
        $this->addEvent('logs-sqlerror', 'eventLogs');
        $this->addEvent('logs-sqllog', 'eventLogs');

        $this->addEvent('tools-reset', 'eventReset');
        $this->addEvent('tools-commentstree', 'eventCommentsTree');
        $this->addEvent('tools-recalcfavourites', 'eventRecalculateFavourites');
        $this->addEvent('tools-recalcvotes', 'eventRecalculateVotes');
        $this->addEvent('tools-recalctopics', 'eventRecalculateTopics');
        if (C::get('rating.enabled')) {
            $this->addEvent('tools-recalcblograting', 'eventRecalculateBlogRating');
        }
        $this->addEvent('tools-checkdb', 'eventCheckDb');

        //поля контента
        $this->addEvent('settings-contenttypes', 'eventContentTypes');

        $this->addEvent('settings-contenttypes-fieldadd', 'eventAddField');
        $this->addEvent('settings-contenttypes-fieldedit', 'eventEditField');
        $this->addEvent('settings-contenttypes-fielddelete', 'eventDeleteField');

        $this->addEvent('ajaxchangeordertypes', 'eventAjaxChangeOrderTypes');
        $this->addEvent('ajaxchangeorderfields', 'eventAjaxChangeOrderFields');

        $this->addEvent('ajaxvote', 'eventAjaxVote');
        $this->addEvent('ajaxsetprofile', 'eventAjaxSetProfile');

        $this->addEventPreg('/^ajax$/i', '/^config$/i', 'eventAjaxConfig');
        $this->addEventPreg('/^ajax$/i', '/^user$/i', '/^add$/i', 'eventAjaxUserAdd');
        $this->addEventPreg('/^ajax$/i', '/^user$/i', '/^invite$/i', 'eventAjaxUserList');

        // Аякс для меню
        $this->addEvent('ajaxchangeordermenu', 'eventAjaxChangeOrderMenu');
        $this->addEvent('ajaxchangemenutext', 'eventAjaxChangeMenuText');
        $this->addEvent('ajaxchangemenulink', 'eventAjaxChangeMenuLink');
        $this->addEvent('ajaxmenuitemremove', 'eventAjaxRemoveItem');
        $this->addEvent('ajaxmenuitemdisplay', 'eventAjaxDisplayItem');
    }

    /**
     * @param   int         $nParam
     * @param   string      $sDefault
     * @param   array|null  $aAvail
     *
     * @return mixed
     */
    protected function _getMode($nParam = 0, $sDefault, $aAvail = null) {

        $sKey = R::GetAction() . '.' . R::getControllerAction() . '.' . $nParam;
        $sMode = $this->getParam($nParam, \E::Module('Session')->get($sKey, $sDefault));
        if (!is_null($aAvail) && !is_array($aAvail)) $aAvail = array($aAvail);
        if (is_null($aAvail) || ($sMode && in_array($sMode, $aAvail))) {
            $this->_saveMode(0, $sMode);
        }
        return $sMode;
    }

    /**
     * @param int $nParam
     * @param     $sData
     */
    protected function _saveMode($nParam = 0, $sData)
    {
        $sKey = R::GetAction() . '.' . R::getControllerAction() . '.' . $nParam;
        \E::Module('Session')->set($sKey, $sData);
    }

    /**
     * @param null $nNumParam
     *
     * @return int
     */
    protected function _getPageNum($nNumParam = null)
    {
        $nPage = 1;
        if (null !== $nNumParam && preg_match('/^page(\d+)$/i', $this->getParam((int)$nNumParam), $aMatch)) {
            $nPage = $aMatch[1];
        } elseif (preg_match('/^page(\d+)$/i', $this->getLastParam(), $aMatch)) {
            $nPage = $aMatch[1];
        }
        return $nPage;
    }

    /**
     * @param string $sTitle
     */
    protected function _setTitle($sTitle) {

        \E::Module('Viewer')->assign('sPageTitle', $sTitle);
        \E::Module('Viewer')->addHtmlTitle($sTitle);

    }

    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    public function eventDashboard()
    {
        $this->sMainMenuItem = 'info';

        $aDashboardWidgets = [
            'admin_dashboard_updates' => [
                'name' => 'admin_dashboard_updates',
                'key' => 'admin.dashboard.updates',
                'status' => Config::val('admin.dashboard.updates', true),
                'label' => \E::Module('Lang')->get('action.admin.dashboard_updates_title')
            ],
            'admin_dashboard_news' => [
                'name' => 'admin_dashboard_news',
                'key' => 'admin.dashboard.news',
                'status' => Config::val('admin.dashboard.news', true),
                'label' => \E::Module('Lang')->get('action.admin.dashboard_news_title')
            ],
        ];

        if ($this->isPost('widgets')) {
            $aWidgets = F::Array_FlipIntKeys($this->getPost('widgets'));
            $aConfig = [];
            foreach ($aDashboardWidgets as $aDashboardWidget) {
                if (isset($aWidgets[$aDashboardWidget['name']])) {
                    $aConfig[$aDashboardWidget['key']] = 1;
                } else {
                    $aConfig[$aDashboardWidget['key']] = 0;
                }
            }
            Config::writeCustomConfig($aConfig);
            R::Location('admin');
        }
        $this->_setTitle(\E::Module('Lang')->get('action.admin.menu_info_dashboard'));
        $this->setTemplateAction('info/index');

        $this->sMenuItem = $this->_getMode(0, 'index');

        $aData = ['e-alto' => ALTO_VERSION, 'e-uniq' => \E::Module('Security')->getUniqueKey()];
        $aPlugins = \E::PluginManager()->getPluginsList(true);
        foreach ($aPlugins as $oPlugin) {
            $aData['p-' . $oPlugin->GetId()] = $oPlugin->GetVersion();
        }
        $aSkins = \E::Module('Skin')->getSkinsList();
        foreach ($aSkins as $oSkin) {
            $aData['s-' . $oSkin->GetId()] = $oSkin->GetVersion();
        }

        \E::Module('Viewer')->assign('sUpdatesRequest', base64_encode(http_build_query($aData)));
        \E::Module('Viewer')->assign('sUpdatesRefresh', true);
        \E::Module('Viewer')->assign('aDashboardWidgets', $aDashboardWidgets);
    }

    /**
     *
     */
    public function eventReport()
    {
        $this->sMainMenuItem = 'info';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.menu_info'));
        $this->setTemplateAction('info/report');

        if ($sReportMode = F::getRequest('report', null, 'post')) {
            $this->_eventReportOut($this->_getInfoData(), $sReportMode);
        }

        \E::Module('Viewer')->assign('aInfoData', $this->_getInfoData());
    }

    protected function _getInfoData()
    {
        $aPlugins = \E::PluginManager()->GetList(null, false);
        $aActivePlugins = \E::PluginManager()->GetActivePlugins();
        $aPluginList = [];
        foreach ($aActivePlugins as $aPlugin) {
            if (is_array($aPlugin)) {
                $sPlugin = $aPlugin['id'];
            } else {
                $sPlugin = (string)$aPlugin;
            }
            if (isset($aPlugins[$sPlugin])) {
                /** @var ModulePlugin_EntityPlugin $oPluginEntity */
                $oPluginEntity = $aPlugins[$sPlugin];
                $sPluginName = $oPluginEntity->GetName();
                $aPluginInfo = [
                    'item' => $sPlugin,
                    'label' => $sPluginName,
                ];
                if ($sVersion = $oPluginEntity->GetVersion()) {
                    $aPluginInfo['value'] = 'v.' . $sVersion;
                }
                $sPluginClass = 'Plugin' . ucfirst($sPlugin);
                if (class_exists($sPluginClass) && method_exists($sPluginClass, 'GetUpdateInfo')) {
                    $oPlugin = new $sPluginClass;
                    $aPluginInfo['.html'] = ' - ' . $oPlugin->GetUpdateInfo();
                }
                $aPluginList[$sPlugin] = $aPluginInfo;
            }
        }

        $aSiteStat = \E::Module('Admin')->getSiteStat();
        $sSmartyVersion = \E::Module('Viewer')->getSmartyVersion();

        $aImgSupport = \E::Module('Img')->getDriversInfo();
        $sImgSupport = '';
        if ($aImgSupport) {
            foreach ($aImgSupport as $sDriver => $sVersion) {
                if ($sImgSupport) {
                    $sImgSupport .= '; ';
                }
                $sImgSupport .= $sDriver . ': ' . $sVersion;
            }
        } else {
            $sImgSupport = 'none';
        }

        $aInfo = [
            'versions' => [
                'label' => \E::Module('Lang')->get('action.admin.info_versions'),
                'data' => [
                    'php' => ['label' => \E::Module('Lang')->get('action.admin.info_version_php'), 'value' => PHP_VERSION,],
                    'img' => ['label' => \E::Module('Lang')->get('action.admin.info_version_img'), 'value' => $sImgSupport,],
                    'smarty' => ['label' => \E::Module('Lang')->get('action.admin.info_version_smarty'), 'value' => $sSmartyVersion ? $sSmartyVersion : 'n/a',],
                    'alto' => ['label' => \E::Module('Lang')->get('action.admin.info_version_alto'), 'value' => ALTO_VERSION,],
                ]

            ],
            'site' => [
                'label' => \E::Module('Lang')->get('action.admin.site_info'),
                'data' => [
                    'url' => ['label' => \E::Module('Lang')->get('action.admin.info_site_url'), 'value' => \C::get('path.root.url'),],
                    'skin' => ['label' => \E::Module('Lang')->get('action.admin.info_site_skin'), 'value' => \C::get('view.skin', Config::LEVEL_CUSTOM),],
                    'client' => ['label' => \E::Module('Lang')->get('action.admin.info_site_client'), 'value' => $_SERVER['HTTP_USER_AGENT'],],
                    'empty' => ['label' => '', 'value' => '',],
                ],
            ],
            'plugins' => [
                'label' => \E::Module('Lang')->get('action.admin.active_plugins'),
                'data' => $aPluginList,
            ],
            'stats' => [
                'label' => \E::Module('Lang')->get('action.admin.site_statistics'),
                'data' => [
                    'users' => ['label' => \E::Module('Lang')->get('action.admin.site_stat_users'), 'value' => $aSiteStat['users'],],
                    'blogs' => ['label' => \E::Module('Lang')->get('action.admin.site_stat_blogs'), 'value' => $aSiteStat['blogs'],],
                    'topics' => ['label' => \E::Module('Lang')->get('action.admin.site_stat_topics'), 'value' => $aSiteStat['topics'],],
                    'comments' => ['label' => \E::Module('Lang')->get('action.admin.site_stat_comments'), 'value' => $aSiteStat['comments'],],
                ],
            ],
        ];

        return $aInfo;
    }

    /**
     * @param array  $aInfo
     * @param string $sMode
     */
    protected function _eventReportOut($aInfo, $sMode = 'txt')
    {
        \E::Module('Security')->validateSendForm();
        $sMode = strtolower($sMode);
        $aParams = array(
            'filename' => $sFileName = str_replace(array('.', '/'), '_', str_replace(array('http://', 'https://'), '', \C::get('path.root.url'))) . '.' . $sMode,
            'date' => F::Now(),
        );

        if ($sMode === 'xml') {
            $this->_reportXml($aInfo, $aParams);
        } else {
            $this->_reportTxt($aInfo, $aParams);
        }
        exit;
    }

    /**
     * @param array $aInfo
     * @param array $aParams
     */
    protected function _reportTxt($aInfo, $aParams)
    {
        $sText = '[report]' . "\n";
        foreach ($aParams as $sKey => $sVal) {
            $sText .= $sKey . ' = ' . $sVal . "\n";
        }
        $sText .= "\n";

        foreach ($aInfo as $sSectionKey => $aSection) {
            if (\F::getRequest('adm_report_' . $sSectionKey)) {
                $sText .= '[' . $sSectionKey . '] ; ' . $aSection['label'] . "\n";
                foreach ($aSection['data'] as $sItemKey => $aItem) {
                    $sText .= $sItemKey . ' = ' . $aItem['value'] . '; ' . $aItem['label'] . "\n";
                }
                $sText .= "\n";
            }
        }
        $sText .= "; EOF\n";

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $aParams['filename'] . '"');
        echo $sText;
        exit;
    }

    /**
     * @param array $aInfo
     * @param array $aParams
     */
    protected function _reportXml($aInfo, $aParams)
    {
        $sText = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<report';
        foreach ($aParams as $sKey => $sVal) {
            $sText .= ' ' . $sKey . '="' . $sVal . '"';
        }
        $sText .= ">\n";
        foreach ($aInfo as $sSectionKey => $aSection) {
            if (\F::getRequest('adm_report_' . $sSectionKey)) {
                $nLevel = 1;
                $sText .= str_repeat(' ', $nLevel * 2) . '<' . $sSectionKey . ' label="' . $aSection['label'] . '">' . "\n";
                $nLevel += 1;
                foreach ($aSection['data'] as $sItemKey => $aItem) {
                    $sText .= str_repeat(' ', $nLevel * 2) . '<' . $sItemKey . ' label="' . $aItem['label'] . '">';
                    if (is_array($aItem['value'])) {

                        $sText .= "\n" . str_repeat(' ', $nLevel * 2) . '</' . $sItemKey . '>' . "\n";
                    } else {
                        $sText .= $aItem['value'];
                    }
                    $sText .= '</' . $sItemKey . '>' . "\n";
                }
                $nLevel -= 1;
                $sText .= str_repeat(' ', $nLevel * 2) . '</' . $sSectionKey . '>' . "\n";
            }
        }

        $sText .= '</report>';

        header('Content-Type: text/xml; charset=utf-8', true);
        header('Content-Disposition: attachment; filename="' . $aParams['filename'] . '"', true);
        echo $sText;
        exit;
    }

    public function eventPhpInfo()
    {
        $this->sMainMenuItem = 'info';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.menu_info_phpinfo'));
        $this->setTemplateAction('info/phpinfo');

        $this->_phpInfo(1);
    }

    protected function _phpInfo($nMode = 0)
    {
        if ($nMode) {
            ob_start();
            phpinfo(-1);

            $sPhpinfo = preg_replace(
                array('#^.*<body>(.*)</body>.*$#ms', '#<h2>PHP License</h2>.*$#ms',
                    '#<h1>Configuration</h1>#', "#\r?\n#", "#</(h1|h2|h3|tr)>#", '# +<#',
                    "#[ \t]+#", '#&nbsp;#', '#  +#', '# class=".*?"#', '%&#039;%',
                    '#<tr>(?:.*?)" src="(?:.*?)=(.*?)" alt="PHP Logo" /></a>'
                        . '<h1>PHP Version (.*?)</h1>(?:\n+?)</td></tr>#',
                    '#<h1><a href="(?:.*?)\?=(.*?)">PHP Credits</a></h1>#',
                    '#<tr>(?:.*?)" src="(?:.*?)=(.*?)"(?:.*?)Zend Engine (.*?),(?:.*?)</tr>#',
                    "# +#", '#<tr>#', '#</tr>#'),
                array('$1', '', '', '', '</$1>' . "\n", '<', ' ', ' ', ' ', '', ' ',
                    '<h2>PHP Configuration</h2>' . "\n" . '<tr><td>PHP Version</td><td>$2</td></tr>' .
                        "\n" . '<tr><td>PHP Egg</td><td>$1</td></tr>',
                    '<tr><td>PHP Credits Egg</td><td>$1</td></tr>',
                    '<tr><td>Zend Engine</td><td>$2</td></tr>' . "\n" .
                        '<tr><td>Zend Egg</td><td>$1</td></tr>', ' ', '%S%', '%E%'),
                ob_get_clean());
            $aSections = explode('<h2>', strip_tags($sPhpinfo, '<h2><th><td>'));
            unset($aSections[0]);

            $aPhpInfo = [];
            foreach ($aSections as $sSection) {
                $n = substr($sSection, 0, strpos($sSection, '</h2>'));
                preg_match_all(
                    '#%S%(?:<td>(.*?)</td>)?(?:<td>(.*?)</td>)?(?:<td>(.*?)</td>)?%E%#',
                    $sSection, $aMatches, PREG_SET_ORDER);
                foreach ($aMatches as $m) {
                    if (!isset($m[2])) $m[2] = '';
                    $aPhpInfo[$n][$m[1]] = (!isset($m[3]) || $m[2] === $m[3]) ? $m[2] : array_slice($m, 2);
                }
            }
            \E::Module('Viewer')->assign('aPhpInfo', array('collection' => $aPhpInfo, 'count' => count($aPhpInfo)));
        } else {
            ob_start();
            phpinfo();
            $phpinfo = ob_get_contents();
            ob_end_clean();
            $phpinfo = str_replace("\n", ' ', $phpinfo);
            $info = '';
            if (preg_match('|<style\s*[\w="/]*>(.*)<\/style>|imu', $phpinfo, $match)) $info .= $match[0];
            if (preg_match('|<body\s*[\w="/]*>(.*)<\/body>|imu', $phpinfo, $match)) $info .= $match[1];
            if (!$info) $info = $phpinfo;
            \E::Module('Viewer')->assign('sPhpInfo', $info);
        }
    }

    /**********************************************************************************/

    /**
     * Site settings
     */
    public function eventConfig() {

        $this->sMainMenuItem = 'settings';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.config_title'));

        $sMode = $this->_getMode(0, 'base');

        if ($sMode === 'links') {
            $this->_eventConfigLinks();
        } elseif ($sMode === 'edit') {
            $this->_eventConfigEdit($sMode);
        } else {
            $this->_eventConfigParams($sMode);
        }
        \E::Module('Viewer')->assign('sMode', $sMode);
    }

    /**
     * Site settings > Config parameters
     *
     * @param   string  $sSelectedSection
     */
    protected function _eventConfigParams($sSelectedSection) {

        $this->setTemplateAction('settings/params');

        $aFields = F::IncludeFile(\C::get('path.dir.config') . 'actions/admin.settings.php', false, true);
        foreach ($aFields as $nSec => $aSection) {
            foreach ($aSection as $nKey => $aItem) {
                $aItem['text'] = \E::Module('Lang')->text($aItem['label']);
                if (isset($aItem['help'])) $aItem['help'] = \E::Module('Lang')->text($aItem['help']);
                if (isset($aItem['config'])) {
                    $aItem['value'] = \C::get($aItem['config'], Config::LEVEL_CUSTOM);
                    $aItem['config'] = str_replace('.', '--', $aItem['config']);
                    if (!isset($aItem['valtype']) && isset($aItem['type']) && $aItem['type'] === 'checkbox') {
                        $aItem['valtype'] = 'boolean';
                    }
                }
                if (!empty($aItem['type'])) {
                    if ($aItem['type'] === 'password') {
                        $aItem['valtype'] = 'string';
                    } elseif ($aItem['type'] === 'select' && !empty($aItem['options'])) {
                        $aItem['options'] = F::Array_FlipIntKeys($aItem['options'], null);
                        foreach ($aItem['options'] as $sValue => $sText) {
                            if (is_null($sText)) {
                                $aItem['options'][$sValue] = $sValue;
                            } else {
                                $aItem['options'][$sValue] = \E::Module('Lang')->text($sText);
                            }
                        }
                    }
                }
                $aFields[$nSec][$nKey] = $aItem;
            }
        }
        if (($aData = $this->getPost()) && isset($aFields[$sSelectedSection])) {
            $this->_eventConfigSave($aFields[$sSelectedSection], $aData);
        }
        if (!isset($aFields[$sSelectedSection])) {
            $sSelectedSection = F::Array_FirstKey($aFields);
            $this->_saveMode(0, $sSelectedSection);
        }
        \E::Module('Viewer')->assign('aFields', $aFields[$sSelectedSection]);
    }

    /**
     * Site settings > Links
     */
    protected function _eventConfigLinks() {

        if ($sHomePage = $this->getPost('submit_data_save')) {
            $aConfig = [];
            $sHomePageSelect = '';
            if ($sHomePage = $this->getPost('homepage')) {
                if ($sHomePage === 'page') {
                    $sHomePage = $this->getPost('page_url');
                    $sHomePageSelect = 'page';
                } elseif($sHomePage === 'other') {
                    $sHomePage = $this->getPost('other_url');
                    $sHomePageSelect = 'other';
                }
                $aConfig = array(
                    'router.config.action_default' => 'homepage',
                    'router.config.homepage' => $sHomePage,
                    'router.config.homepage_select' => $sHomePageSelect,
                );
            }
            if ($sDraftLink = $this->getPost('draft_link')) {
                if ($sDraftLink === 'on') {
                    $aConfig['module.topic.draft_link'] = true;
                } else {
                    $aConfig['module.topic.draft_link'] = false;
                }
            }
            if ($sTopicLink = $this->getPost('topic_link')) {
                $aConfig['module.topic.url_mode'] = $sTopicLink;
                if ($sTopicLink === 'alto') {
                    $aConfig['module.topic.url'] = '%topic_id%.html';
                } elseif ($sTopicLink === 'friendly') {
                    $aConfig['module.topic.url'] = '%topic_url%.html';
                } elseif ($sTopicLink === 'ls') {
                    $aConfig['module.topic.url'] = '';
                } elseif ($sTopicLink === 'id') {
                    $aConfig['module.topic.url'] = '%topic_id%';
                } elseif ($sTopicLink === 'day_name') {
                    $aConfig['module.topic.url'] = '%year%/%month%/%day%/%topic_url%/';
                } elseif ($sTopicLink === 'month_name') {
                    $aConfig['module.topic.url'] = '%year%/%month%/%topic_url%/';
                } else {
                    if ($sTopicUrl = trim($this->getPost('topic_link_url'))) {
                        if ($sTopicUrl[0] === '/') {
                            $sTopicUrl = substr($sTopicUrl, 1);
                        }
                        $aConfig['module.topic.url'] = strtolower($sTopicUrl);
                    } else {
                        $aConfig['module.topic.url'] = '';
                    }
                }
            }
            if ($aConfig) {
                Config::writeCustomConfig($aConfig);
                R::Location('admin/settings-site/links/');
            }
        }
        if ($this->getPost('adm_cmd') === 'generate_topics_url') {
            // Генерация URL топиков
            $nRest = \E::Module('Admin')->GenerateTopicsUrl();
            if ($nRest > 0) {
                \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.set_links_generate_next', array('num' => $nRest)), null, true);
            } elseif ($nRest < 0) {
                \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.set_links_generate_done'), null, true);
            } else {
                \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.set_links_generate_done'), null, true);
            }
            R::Location('admin/settings-site/links/');
        }
        $this->setTemplateAction('settings/links');
        $sHomePage = \C::get('router.config.homepage');
        $sHomePageSelect = \C::get('router.config.homepage_select');

        /** @var ModulePage_EntityPage[] $aPages */
        $aPages = \E::Module('Page')->getPages();
        $sHomePageUrl = '';
        if (!$sHomePage || $sHomePage === 'index') {
            $sHomePageSelect = 'index';
            $sHomePageUrl = '';
        } elseif ($sHomePageSelect === 'page') {
            foreach($aPages as $oPage) {
                if ($oPage->getUrl() == $sHomePage) {
                    $sHomePageUrl = $oPage->getLink();
                }
            }
        } elseif ($sHomePageSelect === 'other') {
            $sHomePageUrl = $sHomePage;
        } elseif(!$sHomePageSelect) {
            $sHomePageSelect = $sHomePage;
        }

        $sPermalinkUrl = trim(\C::get('module.topic.url'), '/');
        if (!$sPermalinkUrl) {
            $sPermalinkMode = 'ls';
        } elseif ($sPermalinkUrl === '%topic_id%') {
            $sPermalinkMode = 'id';
        } elseif ($sPermalinkUrl === '%year%/%month%/%day%/%topic_url%') {
            $sPermalinkMode = 'day_name';
        } elseif ($sPermalinkUrl === '%year%/%month%/%topic_url%') {
            $sPermalinkMode = 'month_name';
        } elseif ($sPermalinkUrl === '%topic_id%.html') {
            $sPermalinkMode = 'alto';
        } elseif ($sPermalinkUrl === '%topic_url%.html') {
            $sPermalinkMode = 'friendly';
        } else {
            $sPermalinkMode = 'custom';
        }

        \E::Module('Viewer')->assign('sHomePageSelect', $sHomePageSelect);
        \E::Module('Viewer')->assign('sHomePageUrl', $sHomePageUrl);
        \E::Module('Viewer')->assign('aPages', $aPages);
        \E::Module('Viewer')->assign('sPermalinkMode', $sPermalinkMode);
        \E::Module('Viewer')->assign('sPermalinkUrl', $sPermalinkUrl);
        \E::Module('Viewer')->assign('nTopicsWithoutUrl', \E::Module('Admin')->getNumTopicsWithoutUrl());
    }

    /**
     * Site settings > Edit
     */
    protected function _eventConfigEdit($sMode)
    {
        $aUnits = [
            'S' => ['name' => 'seconds'],
            'M' => ['name' => 'minutes'],
            'H' => ['name' => 'hours'],
            'D' => ['name' => 'days'],
        ];

        if ($this->getPost('submit_data_save')) {
            $aConfig = [];
            if ($this->getPost('view--wysiwyg')) {
                $aConfig['view.wysiwyg'] = true;
            } else {
                $aConfig['view.wysiwyg'] = false;
            }
            if ($this->getPost('view--noindex')) {
                $aConfig['view.noindex'] = true;
            } else {
                $aConfig['view.noindex'] = false;
            }

            //$aConfig['view.img_resize_width'] = (int)$this->GetPost('view--img_resize_width');
            //$aConfig['view.img_max_width'] = (int)$this->GetPost('view--img_max_width');
            //$aConfig['view.img_max_height'] = (int)$this->GetPost('view--img_max_height');
            $aConfig['module.uploader.images.default.max_width'] = (int)$this->getPost('view--img_max_width');
            $aConfig['module.uploader.images.default.max_height'] = (int)$this->getPost('view--img_max_height');

            if ($this->getPost('tag_required')) {
                $aConfig['module.topic.allow_empty_tags'] = false;
            } else {
                $aConfig['module.topic.allow_empty_tags'] = true;
            }
            if ($nVal = (int)$this->getPost('module--topic--max_length')) {
                $aConfig['module.topic.max_length'] = $nVal;
            }
            $aConfig['module.comment.edit.enable'] = false;
            if ($this->getPost('edit_comment') === 'on') {
                $nEditTime = (int)$this->getPost('edit_comment_time');
                if ($nEditTime) {
                    $sEditUnit = '';
                    if ($this->getPost('edit_comment_unit')) {
                        foreach ($aUnits as $sKey => $sUnit) {
                            if ($sUnit['name'] == $this->getPost('edit_comment_unit')) {
                                $sEditUnit = $sKey;
                                break;
                            }
                        }
                    }
                    if (!$sEditUnit) $sEditUnit = 'S';
                    if ($sEditUnit === 'D') $nEditTime = F::ToSeconds('P' . $nEditTime . 'D');
                    else $nEditTime = F::ToSeconds('PT' . $nEditTime . $sEditUnit);
                    $aConfig['module.comment.edit.enable'] = $nEditTime;
                }
            }

            Config::writeCustomConfig($aConfig);
            R::Location('admin/settings-site/');
            exit;
        }
        $this->setTemplateAction('settings/edit');
        $nCommentEditTime = F::ToSeconds(\C::get('module.comment.edit.enable'));
        if ($nCommentEditTime) {
            $sCommentEditUnit = $aUnits['S']['name'];
            if (($nCommentEditTime % 60) == 0) {
                $nCommentEditTime = $nCommentEditTime / 60;
                $sCommentEditUnit = $aUnits['M']['name'];
                if (($nCommentEditTime % 60) == 0) {
                    $nCommentEditTime = $nCommentEditTime / 60;
                    $sCommentEditUnit = $aUnits['H']['name'];
                    if (($nCommentEditTime % 24) == 0) {
                        $nCommentEditTime = $nCommentEditTime / 24;
                        $sCommentEditUnit = $aUnits['D']['name'];
                    }
                }
            }
        } else {
            $sCommentEditUnit = $aUnits['S']['name'];
        }
        \E::Module('Viewer')->assign('nCommentEditTime', $nCommentEditTime);
        \E::Module('Viewer')->assign('sCommentEditUnit', $sCommentEditUnit);
        \E::Module('Viewer')->assign('aTimeUnits', $aUnits);
    }

    /**
     * Сохраняет пользовательские настройки
     *
     * @param array $aFields
     * @param array $aData
     */
    protected function _eventConfigSave($aFields, $aData) {

        $aConfig = [];
        foreach ($aFields as $aParam) {
            if (isset($aParam['config'])) {
                if (isset($aData[$aParam['config']])) {
                    $sVal = $aData[$aParam['config']];
                } else {
                    $sVal = '';
                }
                if (($sVal === '') && isset($aParam['default'])) {
                    $sVal = $aParam['default'];
                }
                if (isset($aParam['valtype'])) {
                    settype($sVal, $aParam['valtype']);
                }
                $aConfig[str_replace('--', '.', $aParam['config'])] = $sVal;
            }
        }
        if ($aConfig) {
            Config::writeCustomConfig($aConfig);
        }
        R::Location('admin/settings-site/');
    }

    /**********************************************************************************/

    public function eventWidgets() {

        $this->sMainMenuItem = 'site';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.widgets_title'));
        $this->setTemplateAction('site/widgets');

        $sMode = $this->getParam(0);
        $aWidgets = \E::Module('Widget')->getWidgets(true);

        if ($sMode === 'edit') {
            $sWidgetId = $this->getParam(1);
            if (isset($aWidgets[$sWidgetId])) {
                $this->_eventWidgetsEdit($aWidgets[$sWidgetId]);
            }
        } elseif (($sCmd = $this->getPost('widget_action')) && ($aWidgets = $this->getPost('widget_sel'))) {
            $aWidgets = array_keys($aWidgets);
            if ($sCmd === 'activate') {
                $this->_eventWidgetsActivate($aWidgets);
            } elseif ($sCmd === 'deactivate') {
                $this->_eventWidgetsDeactivate($aWidgets);
            }
        }
        \E::Module('Viewer')->assign('aWidgetsList', $aWidgets);
    }

    /**
     * @param ModuleWidget_EntityWidget $oWidget
     */
    public function _eventWidgetsEdit($oWidget) {

        if ($this->getPost()) {
            $aConfig = [];
            $sPrefix = 'widget.' . $oWidget->GetId() . '.config.';
            if ($xVal = $this->getPost('widget_group')) {
                $aConfig[$sPrefix . 'wgroup'] = $xVal;
            }

            $aConfig[$sPrefix . 'active'] = (bool)$this->getPost('widget_active');

            $xVal = strtolower($this->getPost('widget_priority'));
            $aConfig[$sPrefix . 'priority'] = ($xVal === 'top' ? 'top' : (int)$xVal);

            if ($this->getPost('widget_display') === 'period') {
                if ($sFrom = $this->getPost('widget_period_from')) {
                    $aConfig[$sPrefix . 'display.date_from'] = date('Y-m-d', strtotime($sFrom));;
                }
                if ($sUpto = $this->getPost('widget_period_upto')) {
                    $aConfig[$sPrefix . 'display.date_upto'] = date('Y-m-d', strtotime($sUpto));;
                }
            }

            $xVal = strtolower($this->getPost('widget_visitors'));
            $aConfig[$sPrefix . 'visitors'] = (in_array($xVal, array('users', 'admins')) ? $xVal : null);

            Config::writeCustomConfig($aConfig);
            R::Location('admin/site-widgets');
        }
        $this->_setTitle(\E::Module('Lang')->get('action.admin.widget_edit_title'));
        $this->setTemplateAction('site/widgets_add');
        \E::Module('Viewer')->assign('oWidget', $oWidget);
    }

    /**
     * @param $aWidgets
     */
    public function _eventWidgetsActivate($aWidgets) {

        if ($this->getPost()) {
            $aConfig = [];
            foreach ($aWidgets as $sWidgetId) {
                $sPrefix = 'widget.' . $sWidgetId . '.config.';
                $aConfig[$sPrefix . 'active'] = true;
            }
            Config::writeCustomConfig($aConfig);
            R::Location('admin/site-widgets');
        }
    }

    /**
     * @param $aWidgets
     */
    public function _eventWidgetsDeactivate($aWidgets) {

        if ($this->getPost()) {
            $aConfig = [];
            foreach ($aWidgets as $sWidgetId) {
                $sPrefix = 'widget.' . $sWidgetId . '.config.';
                $aConfig[$sPrefix . 'active'] = false;
            }
            Config::writeCustomConfig($aConfig);
            R::Location('admin/site-widgets');
        }
    }

    /**********************************************************************************/

    public function eventPlugins() {

        $this->sMainMenuItem = 'site';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.plugins_title'));
        $this->setTemplateAction('site/plugins');

        if ($this->getParam(0) === 'add') {
            return $this->_EventPluginsAdd();
        } elseif ($this->getParam(0) === 'config') {
            $this->sMenuSubItemSelect = 'config';
            return $this->_eventPluginsConfig();
        } else {
            $sParam = $this->getParam(0, 'list');
            if ($sParam !== 'list') {
                $aPlugins = \E::PluginManager()->GetActivePlugins();
                if (in_array($sParam, $aPlugins)) {
                    return $this->_EventPluginsExternalAdmin(0);
                }
            }
            return $this->_eventPluginsList();
        }
    }

    protected function _eventPluginsConfig() {

        $this->PluginDelBlock('right', 'AdminInfo');
        $sPluginCode = $this->getParam(1);
        $oPlugin = $this->PluginAceadminpanel_Plugin_GetPlugin($sPluginCode);
        if ($oPlugin) {
            $sClass = $oPlugin->GetAdminClass();
            return $this->eventPluginsExec($sClass);
        } else {
            return false;
        }
    }

    protected function _eventPluginsMenu()
    {
        $this->PluginDelBlock('right', 'AdminInfo');
        $sEvent = R::getControllerAction();
        if (isset($this->aExternalEvents[$sEvent])) {
            return $this->eventPluginsExec($this->aExternalEvents[$sEvent]);
        }
    }

    protected function _eventPluginsList()
    {
        if ($this->getPost('plugin_action') === 'delete' && ($aSelectedPlugins = $this->getPost('plugin_sel'))) {
            // Удаление плагинов
            $this->_eventPluginsDelete($aSelectedPlugins);
            R::Location('admin/site-plugins/');
        } elseif ($sAction = $this->getPost('plugin_action')) {
            $aPlugins = \E::PluginManager()->DecodeId($this->getPost('plugin_sel'));
            if ($sAction === 'activate') {
                $this->_eventPluginsActivate($aPlugins);
            } elseif ($sAction === 'deactivate') {
                $this->_eventPluginsDeactivate($aPlugins);
            }
            R::Location('admin/site-plugins/');
        }

        $sMode = $this->getParam(1, 'all');

        if ($sMode === 'active') {
            $aPlugins = \E::PluginManager()->getPluginsList(true);
        } elseif ($sMode === 'inactive') {
            $aPlugins = \E::PluginManager()->getPluginsList(false);
        } else {
            $aPlugins = \E::PluginManager()->getPluginsList();
        }

        \E::Module('Viewer')->assign('aPluginList', $aPlugins);
        \E::Module('Viewer')->assign('sMode', $sMode);
    }

    /**
     * @param $aPlugins
     *
     * @return bool
     */
    protected function _eventPluginsActivate($aPlugins)
    {
        if (is_array($aPlugins)) {
            // если передан массив, то обрабатываем только первый элемент
            $sPluginId = array_shift($aPlugins);
        } else {
            $sPluginId = (string)$aPlugins;
        }
        return \E::PluginManager()->Activate($sPluginId);
    }

    /**
     * @param $aPlugins
     *
     * @return bool|null
     */
    protected function _eventPluginsDeactivate($aPlugins)
    {
        if (is_array($aPlugins)) {
            // если передан массив, то обрабатываем только первый элемент
            $sPluginId = array_shift($aPlugins);
        } else {
            $sPluginId = (string)$aPlugins;
        }
        return \E::PluginManager()->Deactivate($sPluginId);
    }

    /**
     * @param $aPlugins
     */
    protected function _eventPluginsDelete($aPlugins) {

        \E::PluginManager()->delete($aPlugins);
    }

    protected function _eventPluginsAdd() {

        if ($aZipFile = $this->getUploadedFile('plugin_arc')) {
            if ($sPackFile = F::File_MoveUploadedFile($aZipFile['tmp_name'], $aZipFile['name'] . '/' . $aZipFile['name'])) {
                \E::PluginManager()->UnpackPlugin($sPackFile);
                F::File_RemoveDir(dirname($sPackFile));
            }
        }
        $this->_setTitle(\E::Module('Lang')->get('action.admin.plugins_title'));
        $this->setTemplateAction('site/plugins_add');
        \E::Module('Viewer')->assign('sMode', 'add');
    }

    /**********************************************************************************/

    public function eventScripts() {

        $this->sMainMenuItem = 'site';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.scripts_title'));
        $this->setTemplateAction('site/scripts');

        if ($this->getParam(0) === 'add') {
            return $this->_eventScriptsAdd();
        } elseif ($this->getParam(0) === 'edit') {
            return $this->_eventScriptsEdit();
        }
        return $this->_eventScriptsList();
    }

    /**
     * Show list of scripts
     */
    protected function _eventScriptsList() {

        if ($sAction = $this->getPost('script_action')) {
            $aSelected = $this->getPost('script_sel');
            if ($sAction === 'delete' && $aSelected) {
                // Delete scripts
                $this->_eventScriptsDelete($aSelected);
            } elseif ($sAction === 'activate') {
                $this->_eventScriptsActivate($aSelected);
            } elseif ($sAction === 'deactivate') {
                $this->_eventScriptsDeactivate($aSelected);
            }
            R::Location('admin/site-scripts/');
        }

        $sMode = $this->getParam(1, 'all');

        if ($sMode === 'active') {
            $aScripts = \E::Module('Admin')->getScriptsList(true);
        } elseif ($sMode === 'inactive') {
            $aScripts = \E::Module('Admin')->getScriptsList(false);
        } else {
            $aScripts = \E::Module('Admin')->getScriptsList();
        }

        \E::Module('Viewer')->assign('aScripts', $aScripts);
        \E::Module('Viewer')->assign('sMode', $sMode);
    }

    /**
     * Save scripts' settings
     *
     * @param string[] $aScript
     */
    protected function _eventScriptSave($aScript = null) {

        if (!$aScript) {
            $sScriptId = 'script-' . time();
            $aScript = array(
                'id' => $sScriptId,
            );
        }
        $aScript['name'] = $this->getPost('script_name');
        $aScript['description'] = $this->getPost('script_description');
        $aScript['place'] = $this->getPost('script_place');
        $aScript['disable'] = !(int)$this->getPost('script_active');
        $aScript['code'] = $this->getPost('script_code');
        if ($this->getPost('script_exclude_adminpanel')) {
            $aScript['off'] = 'admin/*';
        } else {
            $aScript['off'] = null;
        }

        \E::Module('Admin')->SaveScript($aScript);
        R::Location('admin/site-scripts');
    }

    /**
     * Add new script
     */
    protected function _eventScriptsAdd() {

        if ($this->getPost()) {
            $this->_eventScriptSave();
        }
        $this->_setTitle(\E::Module('Lang')->get('action.admin.scripts_title'));
        $this->setTemplateAction('site/scripts_add');
        \E::Module('Viewer')->assign('sMode', 'add');
    }

    /**
     * Edit script's settings
     */
    protected function _eventScriptsEdit() {

        $sScriptId = $this->getParam(1);
        $aScript = \E::Module('Admin')->getScriptById($sScriptId);
        if ($this->getPost()) {
            $this->_eventScriptSave($aScript);
        }

        $_REQUEST['script_name'] = $aScript['name'];
        $_REQUEST['script_description'] = $aScript['description'];
        $_REQUEST['script_place'] = $aScript['place'];
        $_REQUEST['script_code'] = $aScript['code'];
        $_REQUEST['script_active'] = ($aScript['disable'] ? 0 : 1);
        $_REQUEST['script_exclude_adminpanel'] = ($aScript['off'] === 'admin/*');

        \E::Module('Viewer')->assign('aEditScript', $aScript);

        $this->_setTitle(\E::Module('Lang')->get('action.admin.scripts_title'));
        $this->setTemplateAction('site/scripts_add');
        \E::Module('Viewer')->assign('sMode', 'edit');
    }

    /**
     * Delete scripts
     *
     * @param string[] $aSelected
     */
    protected function _eventScriptsDelete($aSelected) {

        foreach($aSelected as $sScriptId) {
            $aScript = \E::Module('Admin')->getScriptById($sScriptId);
            if ($aScript) {
                \E::Module('Admin')->DeleteScript($aScript);
            }
        }
    }

    /**
     * Activate scripts
     *
     * @param string[] $aSelected
     */
    protected function _eventScriptsActivate($aSelected) {

        foreach($aSelected as $sScriptId) {
            $aScript = \E::Module('Admin')->getScriptById($sScriptId);
            if ($aScript) {
                $aScript['disable'] = 0;
                \E::Module('Admin')->SaveScript($aScript);
            }
        }
    }

    /**
     * Deactivate scripts
     *
     * @param string[] $aSelected
     */
    protected function _eventScriptsDeactivate($aSelected) {

        foreach($aSelected as $sScriptId) {
            $aScript = \E::Module('Admin')->getScriptById($sScriptId);
            if ($aScript) {
                $aScript['disable'] = 1;
                \E::Module('Admin')->SaveScript($aScript);
            }
        }
    }

    /**********************************************************************************/

    public function eventPages() {

        $this->sMainMenuItem = 'content';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.pages_title'));
        // * Получаем и загружаем список всех страниц
        $aPages = \E::Module('Page')->getPages();
        if (count($aPages) == 0 && \E::Module('Page')->getCountPage()) {
            \E::Module('Page')->SetPagesPidToNull();
            $aPages = \E::Module('Page')->getPages();
        }
        \E::Module('Viewer')->assign('aPages', $aPages);
        if ($this->getParam(0) === 'add') {
            $this->_eventPagesEdit('add');
        } elseif ($this->getParam(0) === 'edit') {
            $this->_eventPagesEdit('edit');
        } else {
            $this->_eventPagesList();
        }
    }

    protected function _eventPagesList() {

        // * Обработка удаления страницы
        if ($this->getParam(0) === 'delete') {
            \E::Module('Security')->validateSendForm();
            if (\E::Module('Page')->DeletePageById($this->getParam(1))) {
                \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.pages_admin_action_delete_ok'). null, true);
                R::Location('admin/content-pages/');
            } else {
                \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.pages_admin_action_delete_error'), \E::Module('Lang')->get('error'));
            }
        }

        // * Обработка изменения сортировки страницы
        if ($this->getParam(0) === 'sort') {
            $this->_eventPagesListSort();
        }
        $this->setTemplateAction('content/pages_list');
    }

    protected function _eventPagesListSort() {

        \E::Module('Security')->validateSendForm();
        if ($oPage = \E::Module('Page')->getPageById($this->getParam(1))) {
            $sWay = $this->getParam(2) === 'down' ? 'down' : 'up';
            $iSortOld = $oPage->getSort();
            if ($oPagePrev = \E::Module('Page')->getNextPageBySort($iSortOld, $oPage->getPid(), $sWay)) {
                $iSortNew = $oPagePrev->getSort();
                $oPagePrev->setSort($iSortOld);
                \E::Module('Page')->UpdatePage($oPagePrev);
            } else {
                if ($sWay === 'down') {
                    $iSortNew = $iSortOld - 1;
                } else {
                    $iSortNew = $iSortOld + 1;
                }
            }

            // * Меняем значения сортировки местами
            $oPage->setSort($iSortNew);
            \E::Module('Page')->UpdatePage($oPage);
            \E::Module('Page')->ReSort();
        }
        R::Location('admin/content-pages');
    }

    /**
     * @param $sMode
     */
    protected function _eventPagesEdit($sMode) {

        $this->_setTitle(\E::Module('Lang')->get('action.admin.pages_title'));
        $this->setTemplateAction('content/pages_add');
        \E::Module('Viewer')->assign('sMode', $sMode);

        // * Обработка создания новой страницы
        if (\F::isPost('submit_page_save')) {
            if (!F::getRequest('page_id')) {
                $this->SubmitAddPage();
            }
        }
        // * Обработка показа страницы для редактирования
        if ($this->getParam(0) === 'edit') {
            if ($oPageEdit = \E::Module('Page')->getPageById($this->getParam(1))) {
                if (!F::isPost('submit_page_save')) {
                    $_REQUEST['page_title'] = $oPageEdit->getTitle();
                    $_REQUEST['page_pid'] = $oPageEdit->getPid();
                    $_REQUEST['page_url'] = $oPageEdit->getUrl();
                    $_REQUEST['page_text'] = $oPageEdit->getTextSource();
                    $_REQUEST['page_seo_keywords'] = $oPageEdit->getSeoKeywords();
                    $_REQUEST['page_seo_description'] = $oPageEdit->getSeoDescription();
                    $_REQUEST['page_active'] = $oPageEdit->getActive();
                    $_REQUEST['page_main'] = $oPageEdit->getMain();
                    $_REQUEST['page_order'] = $oPageEdit->getOrder();
                    $_REQUEST['page_auto_br'] = $oPageEdit->getAutoBr();
                    $_REQUEST['page_id'] = $oPageEdit->getId();
                } else {
                    // * Если отправили форму с редактированием, то обрабатываем её
                    $this->SubmitEditPage($oPageEdit);
                }
                \E::Module('Viewer')->assign('oPageEdit', $oPageEdit);
            } else {
                \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.pages_edit_notfound'), \E::Module('Lang')->get('error'));
                $this->setParam(0, null);
            }
        }
    }

    /**
     * Обработка отправки формы при редактировании страницы
     *
     * @param ModulePage_EntityPage $oPageEdit
     */
    protected function submitEditPage($oPageEdit)
    {
        // * Проверяем корректность полей
        if (!$this->CheckPageFields()) {
            return;
        }
        if ($oPageEdit->getId() == F::getRequest('page_pid')) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'));
            return;
        }
        
        // * Проверяем есть ли страница с указанным URL
        if ($oPageEdit->getUrlFull() !== F::getRequest('page_url')) {
            if (\E::Module('Page')->getPageByUrlFull(\F::getRequest('page_url'))) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.page_url_exist'), \E::Module('Lang')->get('error'));
                return;
            }
        }

        // * Обновляем свойства страницы
        $oPageEdit->setActive(\F::getRequest('page_active') ? 1 : 0);
        $oPageEdit->setAutoBr(\F::getRequest('page_auto_br') ? 1 : 0);
        $oPageEdit->setMain(\F::getRequest('page_main') ? 1 : 0);
        $oPageEdit->setDateEdit(\F::Now());
        if (\F::getRequest('page_pid') == 0) {
            $oPageEdit->setUrlFull(\F::getRequest('page_url'));
            $oPageEdit->setPid(null);
        } else {
            $oPageEdit->setPid(\F::getRequest('page_pid'));
            $oPageParent = \E::Module('Page')->getPageById(\F::getRequest('page_pid'));
            $oPageEdit->setUrlFull($oPageParent->getUrlFull() . '/' . F::getRequest('page_url'));
        }
        $oPageEdit->setSeoDescription(\F::getRequest('page_seo_description'));
        $oPageEdit->setSeoKeywords(\F::getRequest('page_seo_keywords'));
        $oPageEdit->setText(\E::Module('Text')->SnippetParser(\F::getRequest('page_text')));
        $oPageEdit->setTextSource(\F::getRequest('page_text'));
        $oPageEdit->setTitle(\F::getRequest('page_title'));
        $oPageEdit->setUrl(\F::getRequest('page_url'));
        $oPageEdit->setSort(\F::getRequest('page_sort'));

        // * Обновляем страницу
        if (\E::Module('Page')->UpdatePage($oPageEdit)) {
            \E::Module('Page')->RebuildUrlFull($oPageEdit);
            \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.pages_edit_submit_save_ok'));
            $this->setParam(0, null);
            $this->setParam(1, null);
            R::Location('admin/content-pages/');
        } else {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'));
        }
    }

    /**
     * Обработка отправки формы добавления новой страницы
     *
     */
    protected function submitAddPage() 
    {
        // * Проверяем корректность полей
        if (!$this->CheckPageFields()) {
            return;
        }
        // * Заполняем свойства
        /** @var ModulePage_EntityPage $oPage */
        $oPage = \E::getEntity('Page');
        $oPage->setActive(\F::getRequest('page_active') ? 1 : 0);
        $oPage->setAutoBr(\F::getRequest('page_auto_br') ? 1 : 0);
        $oPage->setMain(\F::getRequest('page_main') ? 1 : 0);
        $oPage->setDateAdd(\F::Now());
        if ((int)F::getRequest('page_pid') === 0) {
            $oPage->setUrlFull(\F::getRequest('page_url'));
            $oPage->setPid(null);
        } else {
            $oPage->setPid(\F::getRequest('page_pid'));
            $oPageParent = \E::Module('Page')->getPageById(\F::getRequest('page_pid'));
            $oPage->setUrlFull($oPageParent->getUrlFull() . '/' . F::getRequest('page_url'));
        }
        $oPage->setSeoDescription(\F::getRequest('page_seo_description'));
        $oPage->setSeoKeywords(\F::getRequest('page_seo_keywords'));
        $oPage->setText(\E::Module('Text')->SnippetParser(\F::getRequest('page_text')));
        $oPage->setTextSource(\F::getRequest('page_text'));
        $oPage->setTitle(\F::getRequest('page_title'));
        $oPage->setUrl(\F::getRequest('page_url'));
        if (\F::getRequest('page_sort')) {
            $oPage->setSort(\F::getRequest('page_sort'));
        } else {
            $oPage->setSort(\E::Module('Page')->getMaxSortByPid($oPage->getPid()) + 1);
        }
        
        // * Проверяем есть ли страница с таким URL
        if (\E::Module('Page')->getPageByUrlFull($oPage->getUrlFull())) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.page_url_exist'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Добавляем страницу
        if (\E::Module('Page')->AddPage($oPage)) {
            \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.pages_create_submit_save_ok'));
            $this->setParam(0, null);
            R::Location('admin/content-pages/');
        } else {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'));
        }
    }

    /**
     * Проверка полей на корректность
     *
     * @return bool
     */
    protected function CheckPageFields() {

        \E::Module('Security')->validateSendForm();

        $bOk = true;

        // * Проверяем есть ли заголовок топика
        if (!F::CheckVal(\F::getRequest('page_title', null, 'post'), 'text', 2, 200)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.pages_create_title_error'), \E::Module('Lang')->get('error'));
            $bOk = false;
        }

        // * Проверяем есть ли заголовок топика, с заменой всех пробельных символов на "_"
        $pageUrl = preg_replace('/\s+/', '_', (string)F::getRequest('page_url', null, 'post'));
        $_REQUEST['page_url'] = $pageUrl;
        if (!F::CheckVal(\F::getRequest('page_url', null, 'post'), 'login', 1, 50)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.pages_create_url_error'), \E::Module('Lang')->get('error'));
            $bOk = false;
        }

        // * Проверяем есть ли содержание страницы
        if (!F::CheckVal(\F::getRequest('page_text', null, 'post'), 'text', 1, 50000)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.pages_create_text_error'), \E::Module('Lang')->get('error'));
            $bOk = false;
        }

        // * Проверяем страницу в которую хотим вложить
        if (\F::getRequest('page_pid') != 0 && !($oPageParent = \E::Module('Page')->getPageById(\F::getRequest('page_pid')))) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.pages_create_parent_page_error'), \E::Module('Lang')->get('error'));
            $bOk = false;
        }

        // * Проверяем сортировку
        if (\F::getRequest('page_sort') && !is_numeric(\F::getRequest('page_sort'))) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.pages_create_sort_error'), \E::Module('Lang')->get('error'));
            $bOk = false;
        }

        // * Выполнение хуков
        \HookManager::run('check_page_fields', array('bOk' => &$bOk));

        return $bOk;
    }


    /**********************************************************************************/

    public function eventBlogs()
    {
        $this->sMainMenuItem = 'content';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.blogs_title'));
        $this->setTemplateAction('content/blogs_list');

        $sMode = 'all';

        $sCmd = $this->getPost('cmd');
        if ($sCmd === 'delete_blog') {
            $this->_eventBlogsDelete();
        }

        // * Передан ли номер страницы
        $nPage = $this->_getPageNum();

        if ($this->getParam(1) && !strstr($this->getParam(1), 'page')) $sMode = $this->getParam(1);

        $aFilter = [];
        if ($sMode && $sMode !== 'all') {
            $aFilter['type'] = $sMode;
        }

        $aResult = \E::Module('Blog')->getBlogsByFilter($aFilter, '', $nPage, \C::get('admin.items_per_page'));
        $aPaging = \E::Module('Viewer')->makePaging($aResult['count'], $nPage, \C::get('admin.items_per_page'), 4,
            R::getLink('admin') . 'content-blogs/list/' . $sMode);

        $aBlogTypes = \E::Module('Blog')->getBlogTypes();
        $nBlogsTotal = 0;
        foreach ($aBlogTypes as $oBlogType) {
            $nBlogsTotal += $oBlogType->GetBlogsCount();
        }
        $aAllBlogs = \E::Module('Blog')->getBlogs();
        foreach($aAllBlogs as $nBlogId=>$oBlog) {
            $aAllBlogs[$nBlogId] = $oBlog->GetTitle();
        }

        \E::Module('Viewer')->assign('nBlogsTotal', $nBlogsTotal);
        \E::Module('Viewer')->assign('aBlogTypes', $aBlogTypes);
        \E::Module('Viewer')->assign('aBlogs', $aResult['collection']);
        \E::Module('Viewer')->assign('aAllBlogs', $aAllBlogs);

        \E::Module('Viewer')->assign('sMode', $sMode);
        \E::Module('Viewer')->assign('aPaging', $aPaging);
    }

    protected function _eventBlogsDelete()
    {
        $nBlogId = $this->getPost('delete_blog_id');
        if (!$nBlogId || !($oBlog = \E::Module('Blog')->getBlogById($nBlogId))) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.blog_del_error'));
            return false;
        }

        if ($this->getPost('delete_topics') !== 'delete') {
            // Топики перемещаются в новый блог
            $aTopics = \E::Module('Topic')->getTopicsByBlogId($nBlogId);
            $nNewBlogId = (int)$this->getPost('topic_move_to');
            if (($nNewBlogId > 0) && is_array($aTopics) && count($aTopics)) {
                if (!$oBlogNew = \E::Module('Blog')->getBlogById($nNewBlogId)) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('blog_admin_delete_move_error'), \E::Module('Lang')->get('error'));
                    return false;
                }
                // * Если выбранный блог является персональным, возвращаем ошибку
                if ($oBlogNew->getType() === 'personal') {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('blog_admin_delete_move_personal'), \E::Module('Lang')->get('error'));
                    return false;
                }
                // * Перемещаем топики
                if (!\E::Module('Topic')->moveTopics($nBlogId, $nNewBlogId)) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.blog_del_move_error'), \E::Module('Lang')->get('error'));
                    return false;
                }
            } else {
                \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.blog_del_move_error'), \E::Module('Lang')->get('error'));
                return false;
            }
        }

        // * Удаляяем блог
        \HookManager::run('blog_delete_before', ['sBlogId' => $nBlogId]);
        if (\E::Module('Blog')->DeleteBlog($nBlogId)) {
            \HookManager::run('blog_delete_after', array('sBlogId' => $nBlogId));
            \E::Module('Message')->addNoticeSingle(
                \E::Module('Lang')->get('blog_admin_delete_success'), \E::Module('Lang')->get('attention'), true
            );
        } else {
            \E::Module('Message')->addNoticeSingle(
                \E::Module('Lang')->get('action.admin.blog_del_error'), \E::Module('Lang')->get('error'), true
            );
        }
        R::returnBack();
    }

    /**********************************************************************************/

    public function eventTopics()
    {
        $this->sMainMenuItem = 'content';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.topics_title'));
        $this->setTemplateAction('content/topics_list');

        $sCmd = $this->getPost('cmd');
        if ($sCmd === 'delete') {
            $this->_topicDelete();
        } else {
            // * Передан ли номер страницы
            $nPage = $this->_getPageNum();
        }

        $aResult = \E::Module('Topic')->getTopicsByFilter(array(), $nPage, \C::get('admin.items_per_page'));
        $aPaging = \E::Module('Viewer')->makePaging($aResult['count'], $nPage, \C::get('admin.items_per_page'), 4,
            R::getLink('admin') . 'content-topics/');

        \E::Module('Viewer')->assign('aTopics', $aResult['collection']);
        \E::Module('Viewer')->assign('aPaging', $aPaging);

        \E::Module('Lang')->addLangJs(array(
                'topic_delete_confirm_title',
                'topic_delete_confirm_text',
                'topic_delete_confirm',
            ));
    }

    /**********************************************************************************/

    public function eventComments()
    {
        $this->sMainMenuItem = 'content';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.comments_title'));
        $this->setTemplateAction('content/comments_list');

        $sCmd = $this->getPost('cmd');
        if ($sCmd === 'delete') {
            $this->_commentDelete();
        }

        // * Передан ли номер страницы
        $nPage = $this->_getPageNum();

        $aResult = \E::Module('Comment')->getCommentsByFilter([], '', $nPage, \C::get('admin.items_per_page'));
        $aPaging = \E::Module('Viewer')->makePaging($aResult['count'], $nPage, \C::get('admin.items_per_page'), 4,
            R::getLink('admin') . 'content-comments/');

        \E::Module('Viewer')->assign('aComments', $aResult['collection']);
        \E::Module('Viewer')->assign('aPaging', $aPaging);
    }

    /**********************************************************************************/

    /**
     * View and management of Mresources
     */
    public function eventMedias()
    {
        $this->sMainMenuItem = 'content';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.mresources_title'));
        $this->setTemplateAction('content/mresources_list');

        $sCmd = $this->getPost('cmd');
        if ($sCmd === 'delete') {
            $this->_eventMediaDelete();
        }

        $sMode = $this->_getMode(1, 'all');

        // * Передан ли номер страницы
        $nPage = $this->_getPageNum();

        $aFilter = array(
            //'type' => ModuleMedia::TYPE_IMAGE,
        );

        if ($sMode &&  $sMode !== 'all') {
            $aFilter = array('target_type' => $sMode);
        }

        $aCriteria = array(
            'fields' => array('mr.*', 'targets_count'),
            'filter' => $aFilter,
            'limit'  => array(($nPage - 1) * \C::get('admin.items_per_page'), \C::get('admin.items_per_page')),
            'with'   => array('user'),
        );

        $aResult = \E::Module('Media')->getMresourcesByCriteria($aCriteria);
        $aResult['count'] = \E::Module('Media')->getMediaCountByTarget($sMode);

        $aPaging = \E::Module('Viewer')->makePaging($aResult['count'], $nPage, \C::get('admin.items_per_page'), 4,
            R::getLink('admin') . 'content-mresources/list/' . $sMode . '/');

        \E::Module('Lang')->addLangJs(
            array(
                 'action.admin.mresource_delete_confirm',
                 'action.admin.mresource_will_be_delete',
            )
        );

        $aTargetTypes = \E::Module('Media')->getTargetTypes();

        \E::Module('Viewer')->assign('aMresources', $aResult['collection']);
        \E::Module('Viewer')->assign('aTargetTypes', $aTargetTypes);
        \E::Module('Viewer')->assign('sMode', $sMode);
        if (strpos($sMode, 'single-image-uploader') === 0) {
            $sMode = str_replace('single-image-uploader', \E::Module('Lang')->get('target_type_single-image-uploader'), $sMode);
        } else {
            if (strpos($sMode, 'plugin.') === 0) {
                $sMode = \E::Module('Lang')->get($sMode);
            } else {
                $sLabelKey = 'target_type_' . $sMode;
                if (($sLabel = \E::Module('Lang')->get($sLabelKey)) === mb_strtoupper($sLabelKey)) {
                    /** @var ModuleTopic_EntityContentType $oContentType */
                    $oContentType = \E::Module('Topic')->getContentTypeByUrl($sMode);
                    if ($oContentType) {
                        $sLabel = $oContentType->getContentTitleDecl();
                    }
                }
                $sMode = $sLabel;
            }

        }
        \E::Module('Viewer')->assign('sPageSubMenu', $sMode);
        \E::Module('Viewer')->assign('aPaging', $aPaging);
    }

    /**
     * @return bool
     */
    protected function _eventMediaDelete()
    {
        if ($iMresourceId = $this->getPost('media_id')) {
            if (\E::Module('Media')->deleteMedia($iMresourceId)) {
                \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.mresource_deleted'));
                return true;
            }
        }
        \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.mresource_not_deleted'));
        return false;
    }

    /**********************************************************************************/

    public function eventUsers()
    {
        $this->sMainMenuItem = 'users';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.users_title'));
        $this->setTemplateAction('users/users');

        $sMode = $this->_getMode(0, 'list', array('list', 'admins'));

        if ($sCmd = $this->getPost('adm_user_cmd')) {
            if ($sCmd === 'adm_ban_user') {
                $sUsersList = $this->getPost('adm_user_list');
                $nBanDays = $this->getPost('ban_days');
                $sBanComment = $this->getPost('ban_comment');

                $sIp = $this->getPost('user_ban_ip1');
                if ($sIp) {
                    $sIp .= $this->getPost('user_ban_ip2', '0')
                        . '.' . $this->getPost('user_ban_ip3', '0')
                        . '.' . $this->getPost('user_ban_ip4', '0');
                }

                if ($sUsersList) {
                    $aUsersId = F::Array_Str2Array($sUsersList);
                    $this->_eventUsersCmdBan($aUsersId, $nBanDays, $sBanComment);
                } elseif ($sIp) {
                    $this->_eventIpsCmdBan($sIp, $nBanDays, $sBanComment);
                }
            } elseif ($sCmd === 'adm_unban_user') {
                $aUsersId = $this->getPost('adm_user_list');
                $this->_eventUsersCmdUnban($aUsersId);
            } elseif ($sCmd === 'adm_user_setadmin') {
                $this->_eventUsersCmdSetAdministrator();
            } elseif ($sCmd === 'adm_user_unsetadmin') {
                $this->_eventUsersCmdUnsetAdministrator();
            } elseif ($sCmd === 'adm_user_setmoderator') {
                $this->_eventUsersCmdSetModerator();
            } elseif ($sCmd === 'adm_user_unsetmoderator') {
                $this->_eventUsersCmdUnsetModerator();
            } elseif ($sCmd === 'adm_del_user') {
                if ($this->_eventUsersCmdDelete()) {
                    $nPage = $this->_getPageNum();
                    R::Location('admin/users-list/' . ($nPage ? 'page' . $nPage : ''));
                } else {
                    R::returnBack();
                }
            } elseif ($sCmd === 'adm_user_message') {
                $this->_eventUsersCmdMessage();
            } elseif ($sCmd === 'adm_user_activate') {
                $this->_eventUsersCmdActivate();
            }
            R::Location('admin/users-list/');
        }

        if ($this->getPost('adm_userlist_filter')) {
            $this->_eventUsersFilter();
        }

        if ($sMode === 'profile') {
            // админ-профиль юзера
            return $this->_eventUsersProfile();
        }

        if ($this->getParam(0) === 'admins' && $this->getParam(1) === 'del') {
            $this->eventUsersDelAdministrator();
        } else {
            $this->_eventUsersList($sMode);
        }
        \E::Module('Viewer')->assign('sMode', $sMode);
        \E::Module('Viewer')->assign('nCountUsers', \E::Module('User')->getCountUsers());
        \E::Module('Viewer')->assign('nCountAdmins', \E::Module('User')->getCountAdmins());
        \E::Module('Viewer')->assign('nCountModerators', \E::Module('User')->getCountModerators());
    }

    /**
     * @param $aUsersId
     * @param $nDays
     * @param $sComment
     *
     * @return bool
     */
    protected function _eventUsersCmdBan($aUsersId, $nDays, $sComment)
    {
        if ($aUsersId) {
            if (in_array(\E::userId(), $aUsersId, true)) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.cannot_ban_self'), null, true);
                return false;
            }
            if (in_array(1, $aUsersId, true)) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.cannot_ban_admin'), null, true);
                return false;
            }
            $aUsers = \E::Module('User')->getUsersByArrayId($aUsersId);
            foreach ($aUsers as $oUser) {
                if ($oUser->isAdministrator()) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.cannot_ban_admin'), null, true);
                    return false;
                }
            }
            if (\E::Module('Admin')->banUsers($aUsersId, $nDays, $sComment)) {
                \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.action_ok'), null, true);
                return true;
            } else {
                \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.action_err'), null, true);
            }
        }
        return false;
    }

    /**
     * @param $aUsersId
     *
     * @return bool
     */
    protected function _eventUsersCmdUnban($aUsersId)
    {
        if ($aUsersId) {
            $aId = F::Array_Str2ArrayInt($aUsersId, ',', true);
            if (\E::Module('Admin')->unbanUsers($aId)) {
                \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.action_ok'), null, true);
                return true;
            }

            \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.action_err'), null, true);
        }
        return false;
    }

    /**
     * @param $sIp
     * @param $nDays
     * @param $sComment
     *
     * @return bool
     */
    protected function _eventIpsCmdBan($sIp, $nDays, $sComment)
    {
        $aIp = explode('.', $sIp) + [0, 0, 0, 0];
        if ($aIp[0] < 1 || $aIp[0] > 254) {
            // error - first part cannot be empty
        } else {
            $sIp1 = '';
            foreach ($aIp as $sPart) {
                $n = (int)$sPart;
                if ($n < 0 || $n >= 255) $n = 0;
                if ($sIp1) $sIp1 .= '.';
                $sIp1 .= $n;
            }
            $sIp2 = '';
            foreach ($aIp as $sPart) {
                $n = (int)$sPart;
                if ($n <= 0 || $n >= 255) $n = 255;
                if ($sIp2) $sIp2 .= '.';
                $sIp2 .= $n;
            }
            if (\E::Module('Admin')->setBanIp($sIp1, $sIp2, $nDays, $sComment)) {
                \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.action_ok'), null, true);
                return true;
            }
        }
        \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.action_err'), null, true);
        return false;
    }

    /**
     * @param $sMode
     */
    protected function _eventUsersList($sMode)
    {
        $this->setTemplateAction('users/list');
        // * Передан ли номер страницы
        $nPage = $this->_getPageNum();

        $aFilter = [];
        $sData = \E::Module('Session')->get('adm_userlist_filter');
        if ($sData) {
            $aFilter = @unserialize($sData);
            if (!is_array($aFilter)) {
                $aFilter = [];
            }
        }

        if ($sMode === 'admins') {
            $aFilter['admin'] = 1;
        }


        if ($sMode === 'moderators') {
            $aFilter['moderator'] = 1;
        }

        $aResult = \E::Module('User')->getUsersByFilter($aFilter, '', $nPage, \C::get('admin.items_per_page'));
        $aPaging = \E::Module('Viewer')->makePaging($aResult['count'], $nPage, \C::get('admin.items_per_page'), 4,
            R::getLink('admin') . 'users-list/');

        foreach ($aFilter as $sKey => $xVal) {
            if ($sKey === 'ip') {
                if (!$xVal || ($xVal === '*.*.*.*') || ($xVal === '0.0.0.0')) {
                    unset($aFilter[$sKey]);
                } else {
                    $aIp = explode('.', $xVal) + array('*', '*', '*', '*');
                    foreach ($aIp as $n => $sPart) {
                        if ($sPart === '*') {
                            $aIp[$n] = '';
                        } else {
                            $aIp[$n] = $sPart;
                        }
                    }
                    $aFilter[$sKey] = $aIp;
                }
            } elseif ($sKey === 'moderator' || !$xVal) {
                unset($aFilter[$sKey]);
            } elseif ($sKey === 'admin' || !$xVal) {
                unset($aFilter[$sKey]);
            }
        }
        \E::Module('Viewer')->assign('aUsers', $aResult['collection']);
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aFilter', $aFilter);
    }

    protected function _eventUsersCmdSetAdministrator() {

        $aUserLogins = F::Str2Array($this->getPost('user_login_admin'), ',', true);
        if ($aUserLogins)
            foreach ($aUserLogins as $sUserLogin) {
                if (!$sUserLogin || !($oUser = \E::Module('User')->getUserByLogin($sUserLogin))) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.user_not_found', array('user' => $sUserLogin)));
                } elseif ($oUser->IsBanned()) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.cannot_banned_admin'));
                } elseif ($oUser->isAdministrator()) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.already_added'));
                } else {
                    if (\E::Module('Admin')->setAdministrator($oUser->GetId())) {
                        \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.saved_ok'));
                    } else {
                        \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.saved_err'));
                    }
                }
            }
        R::returnBack(true);
    }

    protected function _eventUsersCmdUnsetAdministrator() {

        $aUserLogins = F::Str2Array($this->getPost('users_list'), ',', true);
        if ($aUserLogins)
            foreach ($aUserLogins as $sUserLogin) {
                if (!$sUserLogin || !($oUser = \E::Module('User')->getUserByLogin($sUserLogin))) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.user_not_found', array('user' => $sUserLogin)), 'admins:delete');
                } else {
                    if (mb_strtolower($sUserLogin) === 'admin') {
                        \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.cannot_with_admin'), 'admins:delete');
                    } elseif (\E::Module('Admin')->UnsetAdministrator($oUser->GetId())) {
                        \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.saved_ok'), 'admins:delete');
                    } else {
                        \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.saved_err'), 'admins:delete');
                    }
                }
            }
        R::returnBack(true);
    }

    protected function _eventUsersCmdSetModerator() {

        $aUserLogins = F::Str2Array($this->getPost('user_login_moderator'), ',', true);
        if ($aUserLogins)
            foreach ($aUserLogins as $sUserLogin) {
                if (!$sUserLogin || !($oUser = \E::Module('User')->getUserByLogin($sUserLogin))) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.user_not_found', array('user' => $sUserLogin)));
                } elseif ($oUser->IsBanned()) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.cannot_banned_admin'));
                } elseif ($oUser->IsModerator()) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.already_added'));
                } else {
                    if (\E::Module('Admin')->setModerator($oUser->GetId())) {
                        \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.saved_ok'));
                    } else {
                        \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.saved_err'));
                    }
                }
            }
        R::returnBack(true);
    }

    protected function _eventUsersCmdUnsetModerator() {

        $aUserLogins = F::Str2Array($this->getPost('users_list'), ',', true);
        if ($aUserLogins)
            foreach ($aUserLogins as $sUserLogin) {
                if (!$sUserLogin || !($oUser = \E::Module('User')->getUserByLogin($sUserLogin))) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.user_not_found', array('user' => $sUserLogin)), 'admins:delete');
                } else {
                    if (mb_strtolower($sUserLogin) === 'admin') {
                        \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.cannot_with_admin'), 'admins:delete');
                    } elseif (\E::Module('Admin')->UnsetModerator($oUser->GetId())) {
                        \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.saved_ok'), 'admins:delete');
                    } else {
                        \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.saved_err'), 'admins:delete');
                    }
                }
            }
        R::returnBack(true);
    }

    protected function _eventUsersProfile() {

        $nUserId = $this->getParam(1);
        $oUserProfile = \E::Module('User')->getUserById($nUserId);
        if (!$oUserProfile) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.user_not_found'));
            return;
        }

        $sMode = $this->getParam(2);
        $aUserVoteStat = \E::Module('Vote')->getUserVoteStats($oUserProfile->getId());

        if ($sMode === 'topics') {
            $this->eventUsersProfileTopics($oUserProfile);
        } elseif ($sMode === 'blogs') {
            $this->eventUsersProfileBlogs($oUserProfile);
        } elseif ($sMode === 'comments') {
            $this->eventUsersProfileComments($oUserProfile);
        } elseif ($sMode === 'voted') {
            $this->eventUsersProfileVotedBy($oUserProfile);
        } elseif ($sMode === 'votes') {
            $this->eventUsersProfileVotesFor($oUserProfile);
        } elseif ($sMode === 'ips') {
            $this->eventUsersProfileIps($oUserProfile);
        } else {
            $sMode = 'info';
            $this->_eventUsersProfileInfo($oUserProfile);
        }

        \E::Module('Viewer')->assign('sMode', $sMode);
        \E::Module('Viewer')->assign('oUserProfile', $oUserProfile);
        \E::Module('Viewer')->assign('aUserVoteStat', $aUserVoteStat);
        \E::Module('Viewer')->assign('nParamVoteValue', 1);

    }

    /**
     * @param ModuleUser_EntityUser $oUserProfile
     */
    protected function _eventUsersProfileInfo($oUserProfile)
    {
        /** @var ModuleUser_EntityUser[] $aUsersFriend */
        $aUsersFriend = \E::Module('User')->getUsersFriend($oUserProfile->getId(), 1, 10);
        /** @var ModuleUser_EntityUser[] $aUserInvite */
        $aUsersInvite = \E::Module('User')->getUsersInvite($oUserProfile->getId());
        $oUserInviteFrom = \E::Module('User')->getUserInviteFrom($oUserProfile->getId());
        $aBlogsOwner = \E::Module('Blog')->getBlogsByOwnerId($oUserProfile->getId());
        $aBlogsModeration = \E::Module('Blog')->getBlogUsersByUserId($oUserProfile->getId(), ModuleBlog::BLOG_USER_ROLE_MODERATOR);
        $aBlogsAdministration = \E::Module('Blog')->getBlogUsersByUserId($oUserProfile->getId(), ModuleBlog::BLOG_USER_ROLE_ADMINISTRATOR);
        $aBlogsUser = \E::Module('Blog')->getBlogUsersByUserId($oUserProfile->getId(), ModuleBlog::BLOG_USER_ROLE_MEMBER);
        $aBlogsBanUser = \E::Module('Blog')->getBlogUsersByUserId($oUserProfile->getId(), ModuleBlog::BLOG_USER_ROLE_BAN);
        $aLastTopicList = \E::Module('Topic')->getLastTopicsByUserId($oUserProfile->getId(), \C::get('acl.create.topic.limit_time')*1000000, 3);
        $iCountTopicsByUser = \E::Module('Topic')->getCountTopicsByFilter(array('user_id' => $oUserProfile->getId()));
        $iCountCommentsByUser = \E::Module('Comment')->getCountCommentsByUserId($oUserProfile->getId(), 'topic');

        \E::Module('Viewer')->assign('aUsersFriend', isset($aUsersFriend['collection'])? $aUsersFriend['collection']:false);
        \E::Module('Viewer')->assign('aUsersInvite', $aUsersInvite);
        \E::Module('Viewer')->assign('oUserInviteFrom', $oUserInviteFrom);
        \E::Module('Viewer')->assign('aBlogsOwner', $aBlogsOwner);
        \E::Module('Viewer')->assign('aBlogsModeration', $aBlogsModeration);
        \E::Module('Viewer')->assign('aBlogsAdministration', $aBlogsAdministration);
        \E::Module('Viewer')->assign('aBlogsUser', $aBlogsUser);
        \E::Module('Viewer')->assign('aBlogsBanUser', $aBlogsBanUser);
        \E::Module('Viewer')->assign('iCountTopicsByUser', $iCountTopicsByUser);
        \E::Module('Viewer')->assign('iCountCommentsByUser', $iCountCommentsByUser);
        \E::Module('Viewer')->assign('aLastTopicList', isset($aLastTopicList['collection'])? $aLastTopicList['collection']:false);

        $this->setTemplateAction('users/profile_info');
    }

    protected function _eventUsersFilter()
    {
        $aFilter = [];

        if (($sUserLogin = $this->getPost('user_filter_login'))) {
            $aFilter['login'] = $sUserLogin;
        } else {
            $aFilter['login'] = null;
        }

        if (($sUserEmail = $this->getPost('user_filter_email'))) {
            $aFilter['email'] = $sUserEmail;
        } else {
            $aFilter['email'] = null;
        }

        $aUserFilterIp = array('*', '*', '*', '*');
        if (is_numeric($n = $this->getPost('user_filter_ip1')) && $n < 256) {
            $aUserFilterIp[0] = $n;
        }
        if (is_numeric($n = $this->getPost('user_filter_ip2')) && $n < 256) {
            $aUserFilterIp[1] = $n;
        }
        if (is_numeric($n = $this->getPost('user_filter_ip3')) && $n < 256) {
            $aUserFilterIp[2] = $n;
        }
        if (is_numeric($n = $this->getPost('user_filter_ip4')) && $n < 256) {
            $aUserFilterIp[3] = $n;
        }

        $sUserFilterIp = implode('.', $aUserFilterIp);
        if ($sUserFilterIp !== '*.*.*.*') {
            $aFilter['ip'] = $sUserFilterIp;
        } else {
            $aFilter['ip'] = null;
        }

        if ($sDate = F::getRequest('user_filter_regdate')) {
            $sUserRegDate = null;
            if (preg_match('/(\d{4})(\-(\d{1,2})){0,1}(\-(\d{1,2})){0,1}/', $sDate, $aMatch)) {
                if (isset($aMatch[1])) {
                    $sUserRegDate = $aMatch[1];
                    if (isset($aMatch[3])) {
                        $sUserRegDate .= '-' . sprintf('%02d', $aMatch[3]);
                        if (isset($aMatch[5])) {
                            $sUserRegDate .= '-' . sprintf('%02d', $aMatch[5]);
                        }
                    }
                }
            }
            if ($sUserRegDate) {
                $aFilter['regdate'] = $sUserRegDate;
            } else {
                $aFilter['regdate'] = null;
            }
        }
        \E::Module('Session')->set('adm_userlist_filter', serialize($aFilter));
    }

    /**
     * Deletes user
     *
     * @return bool
     */
    protected function _eventUsersCmdDelete()
    {
        \E::Module('Security')->validateSendForm();

        $aUsersId = F::Str2Array(\F::getRequest('adm_user_list'), ',', true);
        $bResult = true;
        foreach ($aUsersId as $iUserId) {
            if ((int)$iUserId === (int)$this->oUserCurrent->GetId()) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.cannot_del_self'), null, true);
                $bResult = false;
                break;
            } elseif (($oUser = \E::Module('User')->getUserById($iUserId))) {
                if ($oUser->isAdministrator()) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.cannot_del_admin'), null, true);
                    $bResult = false;
                    break;
                } elseif (!F::getRequest('adm_user_del_confirm') && !F::getRequest('adm_bulk_confirm')) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.cannot_del_confirm'), null, true);
                    $bResult = false;
                    break;
                } else {
                    \E::Module('Admin')->delUser($oUser->GetId());
                    \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.user_deleted', Array('user' => $oUser->getLogin())), null, true);
                }
            } else {
                \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.user_not_found'), null, true);
                $bResult = false;
                break;
            }
        }
        return $bResult;
    }

    protected function _eventUsersCmdMessage() {

        if ($this->getPost('send_common_message') === 'yes') {
            $this->_eventUsersCmdMessageCommon();
        } else {
            $this->_eventUsersCmdMessageSeparate();
        }
    }

    protected function _eventUsersCmdMessageCommon() {

        $bOk = true;

        $sTitle = $this->getPost('talk_title');
        $sText = \E::Module('Text')->parse(\F::getRequest('talk_text'));
        $sDate = date(\F::Now());
        $sIp = F::GetUserIp();

        if (($sUsers = $this->getPost('users_list'))) {
            $aUsers = explode(',', str_replace(' ', '', $sUsers));
        } else {
            $aUsers = [];
        }

        if ($aUsers) {
            if ($bOk && $aUsers) {
                /** @var ModuleTalk_EntityTalk $oTalk */
                $oTalk = \E::getEntity('Talk_Talk');
                $oTalk->setUserId($this->oUserCurrent->getId());
                $oTalk->setUserIdLast($this->oUserCurrent->getId());
                $oTalk->setTitle($sTitle);
                $oTalk->setText($sText);
                $oTalk->setDate($sDate);
                $oTalk->setDateLast($sDate);
                $oTalk->setUserIp($sIp);
                $oTalk = \E::Module('Talk')->AddTalk($oTalk);

                // добавляем себя в общий список
                $aUsers[] = $this->oUserCurrent->getLogin();
                // теперь рассылаем остальным
                foreach ($aUsers as $sUserLogin) {
                    if ($sUserLogin && ($oUserRecipient = \E::Module('User')->getUserByLogin($sUserLogin))) {
                        /** @var ModuleTalk_EntityTalkUser $oTalkUser */
                        $oTalkUser = \E::getEntity('Talk_TalkUser');
                        $oTalkUser->setTalkId($oTalk->getId());
                        $oTalkUser->setUserId($oUserRecipient->GetId());
                        if ($sUserLogin != $this->oUserCurrent->getLogin()) {
                            $oTalkUser->setDateLast(null);
                        } else {
                            $oTalkUser->setDateLast($sDate);
                        }
                        \E::Module('Talk')->AddTalkUser($oTalkUser);

                        // Отправляем уведомления
                        if ($sUserLogin != $this->oUserCurrent->getLogin() || F::getRequest('send_copy_self')) {
                            $oUserToMail = \E::Module('User')->getUserById($oUserRecipient->GetId());
                            \E::Module('Notify')->SendTalkNew($oUserToMail, $this->oUserCurrent, $oTalk);
                        }
                    }
                }
            }
        }

        if ($bOk) {
            \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.msg_sent_ok'), null, true);
        } else {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), null, true);
        }
    }

    protected function _eventUsersCmdMessageSeparate() {

        $bOk = true;

        $sTitle = F::getRequest('talk_title');

        $sText = \E::Module('Text')->parse(\F::getRequest('talk_text'));
        $sDate = date(\F::Now());
        $sIp = F::GetUserIp();

        if (($sUsers = $this->getPost('users_list'))) {
            $aUsers = explode(',', str_replace(' ', '', $sUsers));
        } else {
            $aUsers = [];
        }

        if ($aUsers) {
            // Если указано, то шлем самому себе со списком получателей
            if (\F::getRequest('send_copy_self')) {
                /** @var ModuleTalk_EntityTalk $oSelfTalk */
                $oSelfTalk = \E::getEntity('Talk_Talk');
                $oSelfTalk->setUserId($this->oUserCurrent->getId());
                $oSelfTalk->setUserIdLast($this->oUserCurrent->getId());
                $oSelfTalk->setTitle($sTitle);
                $oSelfTalk->setText(\E::Module('Text')->parse('To: <i>' . $sUsers . '</i>' . "\n\n" . 'Msg: ' . $this->getPost('talk_text')));
                $oSelfTalk->setDate($sDate);
                $oSelfTalk->setDateLast($sDate);
                $oSelfTalk->setUserIp($sIp);
                if (($oSelfTalk = \E::Module('Talk')->AddTalk($oSelfTalk))) {
                    /** @var ModuleTalk_EntityTalkUser $oTalkUser */
                    $oTalkUser = \E::getEntity('Talk_TalkUser');
                    $oTalkUser->setTalkId($oSelfTalk->getId());
                    $oTalkUser->setUserId($this->oUserCurrent->getId());
                    $oTalkUser->setDateLast($sDate);
                    \E::Module('Talk')->AddTalkUser($oTalkUser);

                    // уведомление по e-mail
                    $oUserToMail = $this->oUserCurrent;
                    \E::Module('Notify')->SendTalkNew($oUserToMail, $this->oUserCurrent, $oSelfTalk);
                } else {
                    $bOk = false;
                }
            }

            if ($bOk) {
                // теперь рассылаем остальным - каждому отдельное сообщение
                foreach ($aUsers as $sUserLogin) {
                    if ($sUserLogin && $sUserLogin != $this->oUserCurrent->getLogin() && ($oUserRecipient = \E::Module('User')->getUserByLogin($sUserLogin))) {
                        /** @var ModuleTalk_EntityTalk $oTalk */
                        $oTalk = \E::getEntity('Talk_Talk');
                        $oTalk->setUserId($this->oUserCurrent->getId());
                        $oTalk->setUserIdLast($this->oUserCurrent->getId());
                        $oTalk->setTitle($sTitle);
                        $oTalk->setText($sText);
                        $oTalk->setDate($sDate);
                        $oTalk->setDateLast($sDate);
                        $oTalk->setUserIp($sIp);
                        if (($oTalk = \E::Module('Talk')->AddTalk($oTalk))) {
                            /** @var ModuleTalk_EntityTalkUser $oTalkUser */
                            $oTalkUser = \E::getEntity('Talk_TalkUser');
                            $oTalkUser->setTalkId($oTalk->getId());
                            $oTalkUser->setUserId($oUserRecipient->GetId());
                            $oTalkUser->setDateLast(null);
                            \E::Module('Talk')->AddTalkUser($oTalkUser);

                            // Отправка самому себе, чтобы можно было читать ответ
                            /** @var ModuleTalk_EntityTalkUser $oTalkUser */
                            $oTalkUser = \E::getEntity('Talk_TalkUser');
                            $oTalkUser->setTalkId($oTalk->getId());
                            $oTalkUser->setUserId($this->oUserCurrent->getId());
                            $oTalkUser->setDateLast($sDate);
                            \E::Module('Talk')->AddTalkUser($oTalkUser);

                            // Отправляем уведомления
                            $oUserToMail = \E::Module('User')->getUserById($oUserRecipient->GetId());
                            \E::Module('Notify')->SendTalkNew($oUserToMail, $this->oUserCurrent, $oTalk);
                        } else {
                            $bOk = false;
                            break;
                        }
                    }
                }
            }
        }

        if ($bOk) {
            \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.msg_sent_ok'), null, true);
        } else {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), null, true);
        }
    }

    protected function _eventUsersCmdActivate() {

        if (($sUsers = $this->getPost('users_list'))) {
            $aUsers = explode(',', str_replace(' ', '', $sUsers));
        } else {
            $aUsers = [];
        }
        if ($aUsers) {
            foreach ($aUsers as $sUserLogin) {
                $oUser = \E::Module('User')->getUserByLogin($sUserLogin);
                $oUser->setActivate(1);
                $oUser->setDateActivate(\F::Now());
                \E::Module('User')->Update($oUser);
            }
        }
        R::returnBack();
    }

    /**********************************************************************************/

    public function eventInvites() {

        $this->sMainMenuItem = 'users';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.invites_title'));
        $this->setTemplateAction('users/invites_list');

        $sMode = $this->getParam(0);
        if ($sMode === 'add') {
            $this->_eventInvitesAdd();
        } else {
            $this->_eventInvitesList($sMode);
        }

        if ($this->oUserCurrent->isAdministrator()) {
            $iCountInviteAvailable = -1;
        } else {
            $iCountInviteAvailable = \E::Module('User')->getCountInviteAvailable($this->oUserCurrent);
        }
        \E::Module('Viewer')->assign('iCountInviteAvailable', $iCountInviteAvailable);
        \E::Module('Viewer')->assign('iCountInviteUsed', \E::Module('User')->getCountInviteUsed($this->oUserCurrent->getId()));
    }

    protected function _eventInvitesList($sMode) {

        if (\F::getRequest('action', null, 'post') === 'delete') {
            $this->_eventInvitesDelete();
        }

        $nPage = $this->_getPageNum();

        if ($sMode === 'used') {
            $aFilter = array(
                'used' => true,
            );
        } elseif ($sMode === 'unused') {
            $aFilter = array(
                'unused' => true,
            );
        } else {
            $sMode = 'all';
            $aFilter = [];
        }
        // Получаем список инвайтов
        $aResult = \E::Module('Admin')->getInvites($nPage, \C::get('admin.items_per_page'), $aFilter);
        $aInvites = $aResult['collection'];
        $aCounts = \E::Module('Admin')->getInvitesCount();

        // Формируем постраничность
        $aPaging = \E::Module('Viewer')->makePaging($aResult['count'], $nPage, \C::get('admin.items_per_page'), 4, R::getLink('admin') . 'users-invites');
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aInvites', $aInvites);
        \E::Module('Viewer')->assign('aCounts', $aCounts);
        \E::Module('Viewer')->assign('sMode', $sMode);
    }

    protected function _eventInvitesDelete()
    {
        \E::Module('Security')->validateSendForm();

        $aIds = [];
        foreach ($_POST as $sKey => $sVal) {
            if ((0 === strpos($sKey, 'invite_')) && ($nId = (int)substr($sKey, 7))) {
                $aIds[] = $nId;
            }
        }
        if ($aIds) {
            $nResult = \E::Module('Admin')->DeleteInvites($aIds);
            \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.invaite_deleted', array('num' => $nResult)));
        }
        R::returnBack(true);
    }

    /**********************************************************************************/

    public function eventBanlist()
    {
        $this->sMainMenuItem = 'users';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.banlist_title'));
        $sMode = $this->_getMode(0, 'ids');
        $nPage = $this->_getPageNum();

        if ($sCmd = $this->getPost('adm_user_cmd')) {
            $this->_eventBanListCmd($sCmd);
        }
        if ($sMode === 'ips') {
            $this->_eventBanlistIps($nPage);
        } else {
            $sMode = 'ids';
            $this->_eventBanlistIds($nPage);
        }
        \E::Module('Viewer')->assign('sMode', $sMode);
    }

    /**
     * @param $sCmd
     */
    protected function _eventBanListCmd($sCmd) {

        if ($sCmd === 'adm_ban_user') {
            $sUsersList = $this->getPost('user_login');
            $nBanDays = $this->getPost('ban_days');
            $sBanComment = $this->getPost('ban_comment');

            $sIp = $this->getPost('user_ban_ip1');
            if ($sIp) {
                $sIp .= '.' . $this->getPost('user_ban_ip2', '0')
                    . '.' . $this->getPost('user_ban_ip3', '0')
                    . '.' . $this->getPost('user_ban_ip4', '0');
            }

            if ($sUsersList) {
                // здесь получаем логины юзеров
                $aUsersLogin = F::Array_Str2Array($sUsersList);
                // по логинам получаем список юзеров
                $aUsers = \E::Module('User')->getUsersByFilter(array('login' => $aUsersLogin), '', 1, 100, array());
                if ($aUsers) {
                    // и их баним
                    $this->_eventUsersCmdBan(array_keys($aUsers['collection']), $nBanDays, $sBanComment);
                }
            } elseif ($sIp) {
                $this->_eventIpsCmdBan($sIp, $nBanDays, $sBanComment);
            }
        } elseif ($sCmd === 'adm_unsetban_ip') {
            $aId = F::Array_Str2ArrayInt($this->getPost('bans_list'), ',', true);
            \E::Module('Admin')->UnsetBanIp($aId);
        } elseif ($sCmd === 'adm_unsetban_user') {
            $aUsersId = F::Array_Str2ArrayInt($this->getPost('bans_list'), ',', true);
            $this->_eventUsersCmdUnban($aUsersId);
        }
        R::returnBack(true);
    }

    /**
     * @param $nPage
     */
    protected function _eventBanlistIds($nPage) {

        $this->setTemplateAction('users/banlist_ids');

        // Получаем список забаненных юзеров
        $aResult = \E::Module('Admin')->getUsersBanList($nPage, \C::get('admin.items_per_page'));

        // Формируем постраничность
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $nPage, \C::get('admin.items_per_page'), 4, R::getLink('admin') . 'banlist/ids/'
        );
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aUserList', $aResult['collection']);
    }

    protected function _eventBanlistIps($nPage) {

        $this->setTemplateAction('users/banlist_ips');

        // Получаем список забаненных ip-адресов
        $aResult = \E::Module('Admin')->getIpsBanList($nPage, \C::get('admin.items_per_page'));

        // Формируем постраничность
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $nPage, \C::get('admin.items_per_page'), 4, R::getLink('admin') . 'banlist/ips/'
        );
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aIpsList', $aResult['collection']);
    }

    /**********************************************************************************/

    protected function _getSkinFromConfig($sSkin)
    {
        $sSkinTheme = null;
        if (\F::File_Exists($sFile = \C::get('path.skins.dir') . $sSkin . '/settings/config/config.php')) {
            $aSkinConfig = F::includeFile($sFile, false, true);
            if (isset($aSkinConfig['view'], $aSkinConfig['view']['theme'])) {
                $sSkinTheme = $aSkinConfig['view']['theme'];
            } elseif (isset($aSkinConfig['view.theme'])) {
                $sSkinTheme = $aSkinConfig['view.theme'];
            }
        }
        return $sSkinTheme;
    }

    public function eventSkins() {

        $this->sMainMenuItem = 'site';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.skins_title'));
        $this->setTemplateAction('site/skins');

        // Определяем скин и тему основного сайта (не админки)
        $sSiteSkin = \C::get('view.skin', Config::LEVEL_CUSTOM);
        $sSiteTheme = \C::get('skin.' . $sSiteSkin . '.config.view.theme');

        // Определяем скин и тему админки
        $sAdminSkin = \C::get('view.skin');
        $sAdminTheme = \C::get('skin.' . $sAdminSkin . '.config.view.theme');

        if (!$sSiteTheme && ($sSkinTheme = $this->_getSkinFromConfig($sSiteSkin))) {
            $sSiteTheme = $sSkinTheme;
        }

        if (!$sAdminTheme && ($sSkinTheme = $this->_getSkinFromConfig($sAdminSkin))) {
            $sAdminTheme = $sSkinTheme;
        }

        $sMode = $this->getParam(0);
        if ($sMode === 'adm') {
            $aFilter = array('type' => 'adminpanel');
        } elseif ($sMode === 'all') {
            $aFilter = array('type' => '');
        } else {
            $sMode = 'site';
            $aFilter = array('type' => 'site');
        }
        if ($this->getPost('submit_skins_del')) {
            // Удаление плагинов
            $this->_eventSkinsDelete($sMode);
        } elseif ($sSkin = $this->getPost('skin_activate')) {
            $this->_eventSkinActivate($sMode, $sSkin);
        } elseif (($sSkin = $this->getPost('skin')) && ($sTheme = $this->getPost('theme_activate'))) {
            $this->_eventSkinThemeActivate($sMode, $sSkin, $sTheme);
        }

        $aSkins = \E::Module('Skin')->getSkinsList($aFilter);
        $oActiveSkin = null;
        foreach ($aSkins as $sKey => $oSkin) {
            if ($sMode === 'adm') {
                if ($sKey == $sAdminSkin) {
                    $oActiveSkin = $oSkin;
                    unset($aSkins[$sKey]);
                }
            } else {
                if ($sKey == $sSiteSkin) {
                    $oActiveSkin = $oSkin;
                    unset($aSkins[$sKey]);
                }
            }
        }

        if ($sMode === 'adm') {
            \E::Module('Viewer')->assign('sSiteSkin', $sAdminSkin);
            \E::Module('Viewer')->assign('sSiteTheme', $sAdminTheme);
        } else {
            \E::Module('Viewer')->assign('sSiteSkin', $sSiteSkin);
            \E::Module('Viewer')->assign('sSiteTheme', $sSiteTheme);
        }

        \E::Module('Viewer')->assign('oActiveSkin', $oActiveSkin);
        \E::Module('Viewer')->assign('aSkins', $aSkins);
        \E::Module('Viewer')->assign('sMode', $sMode);
    }

    protected function _eventSkinActivate($sMode, $sSkin) {

        $aConfig = array('view.skin' => $sSkin);
        Config::writeCustomConfig($aConfig);
        R::Location('admin/site-skins/' . $sMode . '/');
    }

    protected function _eventSkinThemeActivate($sMode, $sSkin, $sTheme) {

        $aConfig = array('skin.' . $sSkin . '.config.view.theme' => $sTheme);
        Config::writeCustomConfig($aConfig);
        R::Location('admin/site-skins/' . $sMode . '/');
    }

    /**********************************************************************************/

    /**
     * View logs
     */
    public function eventLogs() {

        $this->sMainMenuItem = 'logs';

        if ($this->sCurrentEvent === 'logs-sqlerror') {
            $sLogFile = \C::get('sys.logs.dir') . \C::get('sys.logs.sql_error_file');
        } elseif ($this->sCurrentEvent === 'logs-sqllog') {
            $sLogFile = \C::get('sys.logs.dir') . \C::get('sys.logs.sql_query_file');
        } else {
            $sLogFile = \C::get('sys.logs.dir') . F::ERROR_LOGFILE;
        }

        if (!is_null($this->getPost('submit_logs_del'))) {
            $this->_eventLogsErrorDelete($sLogFile);
        }

        $sLogTxt = F::File_GetContents($sLogFile);
        if ($this->sCurrentEvent === 'logs-sqlerror') {
            $this->_setTitle(\E::Module('Lang')->get('action.admin.logs_sql_errors_title'));
            $this->setTemplateAction('logs/sql_errors');
            $this->_eventLogsSqlErrors($sLogTxt);
        } elseif ($this->sCurrentEvent === 'logs-sqllog') {
            $this->_setTitle(\E::Module('Lang')->get('action.admin.logs_sql_title'));
            $this->setTemplateAction('logs/sql_log');
            $this->_eventLogsSql($sLogTxt);
        } else {
            $this->_setTitle(\E::Module('Lang')->get('action.admin.logs_errors_title'));
            $this->setTemplateAction('logs/errors');
            $this->_eventLogsErrors($sLogTxt);
        }

        \E::Module('Viewer')->assign('sLogTxt', $sLogTxt);
    }

    protected function _eventLogsErrorDelete($sLogFile) {

        F::File_Delete($sLogFile);
    }

    protected function _parseLog($sLogTxt) {

        $aLogs = [];
        if (preg_match_all('/\[LOG\:(?<id>[\d\-\.\,A-F]+)\]\[(?<date>[\d\-\s\:]+)\].*\[\[(?<text>.*)\]\]/siuU', $sLogTxt, $aM, PREG_PATTERN_ORDER)) {
            if (preg_last_error() == PREG_BACKTRACK_LIMIT_ERROR) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.logs_too_long'), null);
            }
            foreach ($aM[0] as $nRec => $sVal) {
                $aRec = array(
                    'id' => $aM['id'][$nRec],
                    'date' => $aM['date'][$nRec],
                    'text' => $aM['text'][$nRec],
                );
                array_unshift($aLogs, $aRec);
            }
        } else {
            $aTmp = [];
            // Текст кривой, поэтому будем так
            $aParts = explode('[LOG:', $sLogTxt);
            if ($aParts) {
                foreach ($aParts as $sPart) {
                    if ($sPart) {
                        $aRec = array('id' => '', 'date' => '', 'text' => $sPart);
                        $nPos = strpos($sPart, ']');
                        if ($nPos) {
                            $aRec['id'] = substr($sPart, 0, $nPos);
                            $aRec['text'] = substr($aRec['text'], $nPos+1);
                        }
                        if (preg_match('/^\[(\d{4}\-\d{2}\-\d{2}\s\d{2}\:\d{2}\:\d{2})\]/', $aRec['text'])) {
                            $aRec['date'] = substr($aRec['text'], 1, 19);
                            $aRec['text'] = substr($aRec['text'], 21);
                        }
                        $nPos = strpos($aRec['text'], '[END:' . $aRec['id'] . ']');
                        if ($nPos) {
                            $aRec['text'] = substr($aRec['text'], 0, $nPos);
                        }
                        if (preg_match('/\[\[(.*)\]\]/siuU', $aRec['text'], $aM)) {
                            $aRec['text'] = trim($aM[1]);
                        }
                        $aTmp[] = $aRec;
                    }
                }
            }
            $aLogs = array_reverse($aTmp);
        }
        return $aLogs;
    }

    /**
     * Runtime errors of engine
     *
     * @param $sLogTxt
     */
    protected function _eventLogsErrors($sLogTxt) {

        $aLogs = $this->_parseLog($sLogTxt);
        foreach ($aLogs as $nRec => $aRec) {
            if ($n = strpos($aRec['text'], '---')) {
                $aRec['text'] = nl2br(trim(substr($aRec['text'], 0, $n)));
            } else {
                $aRec['text'] = nl2br(trim($aRec['text']));
            }
            $aLogs[$nRec] = $aRec;
        }

        \E::Module('Viewer')->assign('aLogs', $aLogs);
    }

    /**
     * @param $sLogTxt
     */
    protected function _eventLogsSqlErrors($sLogTxt) {

        $aLogs = $this->_parseLog($sLogTxt);
        foreach ($aLogs as $nRec => $aRec) {
            if ($n = strpos($aRec['text'], '---')) {
                $aRec['info'] = trim(substr($aRec['text'], $n + 3));
                $aRec['sql'] = '';
                if (strpos($aRec['info'], 'Array') !== false && preg_match('/\[query\]\s*\=\>(.*)\[context\]\s*\=\>(.*)$/siuU', $aRec['info'], $aM)) {
                    $aRec['sql'] = trim($aM[1]);
                }
                $aRec['text'] = trim(substr($aRec['text'], 0, $n));
            } else {
                $aRec['info'] = '';
                $aRec['sql'] = '';
                $aRec['text'] = trim($aRec['text']);
            }
            $aLogs[$nRec] = $aRec;
        }

        \E::Module('Viewer')->assign('aLogs', $aLogs);
    }

    /**
     * @param $sLogTxt
     */
    protected function _eventLogsSql($sLogTxt) {

        $aLogs = $this->_parseLog($sLogTxt);
        foreach ($aLogs as $nRec => $aRec) {
            if (preg_match('/--\s(\d+)\s(\ws);(.*)$/U', $aRec['text'], $aM, PREG_OFFSET_CAPTURE)) {
                $aRec['text'] = trim(substr($aRec['text'], 0, $aM[0][1]));
                $aRec['time'] = $aM[1][0] . ' ' . $aM[2][0];
                $aRec['result'] = trim($aM[3][0]);
                if (($n = strpos($aRec['result'], 'returned')) !== false) {
                    $aRec['result'] = trim(substr($aRec['result'], 8));
                }
            } else {
                $aRec['text'] = trim($aRec['text']);
                $aRec['time'] = 'unknown';
                $aRec['result'] = '';
            }
            $aLogs[$nRec] = $aRec;
        }

        \E::Module('Viewer')->assign('aLogs', $aLogs);
    }

    /**********************************************************************************/

    public function eventReset() {

        $this->sMainMenuItem = 'tools';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.reset_title'));
        $this->setTemplateAction('tools/reset');

        $aSettings = [];
        if ($this->getPost('adm_reset_submit')) {
            $aConfig = [];
            if ($this->getPost('adm_cache_clear_data')) {
                \E::Module('Cache')->clean();
                $aSettings['adm_cache_clear_data'] = 1;
            }
            if ($this->getPost('adm_cache_clear_assets')) {
                \E::Module('Viewer')->ClearAssetsFiles();
                $aConfig['assets.version'] = time();
                $aSettings['adm_cache_clear_assets'] = 1;
            }
            if ($this->getPost('adm_cache_clear_smarty')) {
                \E::Module('Viewer')->ClearSmartyFiles();
                $aSettings['adm_cache_clear_smarty'] = 1;
            }
            if ($this->getPost('adm_reset_config_data')) {
                $this->_eventResetCustomConfig();
                $aSettings['adm_reset_config_data'] = 1;
            }

            if ($aConfig) {
                Config::writeCustomConfig($aConfig);
            }
            \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.action_ok'), null, true);

            if ($aSettings) {
                \E::Module('Session')->setCookie('adm_tools_reset', serialize($aSettings));
            } else {
                \E::Module('Session')->delCookie('adm_tools_reset');
            }
            R::Location('admin/tools-reset/');
        }
        if ($sSettings = \E::Module('Session')->getCookie('adm_tools_reset')) {
            $aSettings = @unserialize($sSettings);
            if (is_array($aSettings)) {
                \E::Module('Viewer')->assign('aSettings', $aSettings);
            }
        }
    }

    /**
     * Сброс кастомного конфига
     */
    protected function _eventResetCustomConfig() {

        Config::resetCustomConfig();
    }

    /**********************************************************************************/

    /**
     * Перестроение дерева комментариев, актуально при $config['module']['comment']['use_nested'] = true;
     *
     */
    public function eventCommentsTree() {

        $this->sMainMenuItem = 'tools';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.comments_tree_title'));
        $this->setTemplateAction('tools/comments_tree');
        if (\F::isPost('comments_tree_submit')) {
            \E::Module('Security')->validateSendForm();
            set_time_limit(0);
            \E::Module('Comment')->RestoreTree();
            \E::Module('Cache')->clean();

            \E::Module('Message')->addNotice(\E::Module('Lang')->get('comments_tree_restored'), \E::Module('Lang')->get('attention'));
            \E::Module('Viewer')->assign('bActionEnable', false);
        } else {
            if (\C::get('module.comment.use_nested')) {
                \E::Module('Viewer')->assign('sMessage', \E::Module('Lang')->get('action.admin.comments_tree_message'));
                \E::Module('Viewer')->assign('bActionEnable', true);
            } else {
                \E::Module('Viewer')->assign('sMessage', \E::Module('Lang')->get('action.admin.comments_tree_disabled'));
                \E::Module('Viewer')->assign('bActionEnable', false);
            }
        }
    }

    /**********************************************************************************/

    /**
     * Пересчет счетчика избранных
     *
     */
    public function eventRecalculateFavourites() {

        $this->sMainMenuItem = 'tools';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.recalcfavourites_title'));
        $this->setTemplateAction('tools/recalcfavourites');
        if (\F::isPost('recalcfavourites_submit')) {
            \E::Module('Security')->validateSendForm();
            set_time_limit(0);
            \E::Module('Comment')->RecalculateFavourite();
            \E::Module('Topic')->RecalculateFavourite();
            \E::Module('Cache')->clean();

            \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.favourites_recalculated'), \E::Module('Lang')->get('attention'));
            \E::Module('Viewer')->assign('bActionEnable', false);
        } else {
            \E::Module('Viewer')->assign('sMessage', \E::Module('Lang')->get('action.admin.recalcfavourites_message'));
            \E::Module('Viewer')->assign('bActionEnable', true);
        }
    }

    /**********************************************************************************/

    /**
     * Пересчет счетчика голосований
     */
    public function eventRecalculateVotes() {

        $this->sMainMenuItem = 'tools';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.recalcvotes_title'));
        $this->setTemplateAction('tools/recalcvotes');
        if (\F::isPost('recalcvotes_submit')) {
            \E::Module('Security')->validateSendForm();
            set_time_limit(0);
            \E::Module('Topic')->RecalculateVote();
            \E::Module('Cache')->clean();

            \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.votes_recalculated'), \E::Module('Lang')->get('attention'));
        } else {
            \E::Module('Viewer')->assign('sMessage', \E::Module('Lang')->get('action.admin.recalcvotes_message'));
            \E::Module('Viewer')->assign('bActionEnable', true);
        }
    }

    /**********************************************************************************/

    /**
     * Пересчет количества топиков в блогах
     */
    public function eventRecalculateTopics() {

        $this->sMainMenuItem = 'tools';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.recalctopics_title'));
        $this->setTemplateAction('tools/recalctopics');
        if (\F::isPost('recalctopics_submit')) {
            \E::Module('Security')->validateSendForm();
            set_time_limit(0);
            \E::Module('Blog')->RecalculateCountTopic();
            \E::Module('Cache')->clean();

            \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.topics_recalculated'), \E::Module('Lang')->get('attention'));
        } else {
            \E::Module('Viewer')->assign('sMessage', \E::Module('Lang')->get('action.admin.recalctopics_message'));
            \E::Module('Viewer')->assign('bActionEnable', true);
        }
    }

    /**
     * Пересчет рейтинга блогов
     */
    public function eventRecalculateBlogRating() {

        $this->sMainMenuItem = 'tools';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.recalcblograting_title'));
        $this->setTemplateAction('tools/recalcblograting');
        if (\F::isPost('recalcblograting_submit')) {
            \E::Module('Security')->validateSendForm();
            set_time_limit(0);
            \E::Module('Rating')->RecalculateBlogRating();
            \E::Module('Cache')->clean();

            \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.blograting_recalculated'), \E::Module('Lang')->get('attention'));
        } else {
            \E::Module('Viewer')->assign('sMessage', \E::Module('Lang')->get('action.admin.recalcblograting_message'));
            \E::Module('Viewer')->assign('bActionEnable', true);
        }
    }

    /**
     * Контроль БД
     */
    public function eventCheckDb() {

        $this->sMainMenuItem = 'tools';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.checkdb_title'));
        $this->setTemplateAction('tools/checkdb');

        $sMode = $this->getParam(0, 'db');
        if ($sMode === 'blogs') {
            $this->_eventCheckDbBlogs();
        } elseif ($sMode === 'topics') {
            $this->_eventCheckDbTopics();
        }
        \E::Module('Viewer')->assign('sMode', $sMode);
    }

    protected function _eventCheckDbBlogs() {

        $this->setTemplateAction('tools/checkdb_blogs');
        $sDoAction = F::getRequest('do_action');
        if ($sDoAction === 'clear_blogs_joined') {
            $aJoinedBlogs = \E::Module('Admin')->getUnlinkedBlogsForUsers();
            if ($aJoinedBlogs) {
                \E::Module('Admin')->DelUnlinkedBlogsForUsers(array_keys($aJoinedBlogs));
            }
        } elseif ($sDoAction === 'clear_blogs_co') {
            $aCommentsOnlineBlogs = \E::Module('Admin')->getUnlinkedBlogsForCommentsOnline();
            if ($aCommentsOnlineBlogs) {
                \E::Module('Admin')->DelUnlinkedBlogsForCommentsOnline(array_keys($aCommentsOnlineBlogs));
            }
        }
        $aJoinedBlogs = \E::Module('Admin')->getUnlinkedBlogsForUsers();
        $aCommentsOnlineBlogs = \E::Module('Admin')->getUnlinkedBlogsForCommentsOnline();
        \E::Module('Viewer')->assign('aJoinedBlogs', $aJoinedBlogs);
        \E::Module('Viewer')->assign('aCommentsOnlineBlogs', $aCommentsOnlineBlogs);
    }

    protected function _eventCheckDbTopics() {

        $this->setTemplateAction('tools/checkdb_topics');
        $sDoAction = F::getRequest('do_action');
        if ($sDoAction === 'clear_topics_co') {
            $aTopics = \E::Module('Admin')->getUnlinkedTopicsForCommentsOnline();
            if ($aTopics) {
                \E::Module('Admin')->DelUnlinkedTopicsForCommentsOnline(array_keys($aTopics));
            }
            $aTopics = \E::Module('Admin')->getUnlinkedTopicsForComments();
            if ($aTopics) {
                \E::Module('Admin')->DelUnlinkedTopicsForComments(array_keys($aTopics));
            }
        }

        $aCommentsOnlineTopics = \E::Module('Admin')->getUnlinkedTopicsForCommentsOnline();
        \E::Module('Viewer')->assign('aCommentsOnlineTopics', $aCommentsOnlineTopics);

        $aCommentsTopics = \E::Module('Admin')->getUnlinkedTopicsForComments();
        \E::Module('Viewer')->assign('aCommentsTopics', $aCommentsTopics);
    }

    /**********************************************************************************/

    /**
     *
     */
    public function eventLang() {

        $this->sMainMenuItem = 'settings';

        $aLanguages = \E::Module('Lang')->getAvailableLanguages();
        $aAllows = (array)Config::get('lang.allow');
        if (!$aAllows) $aAllows = array(\C::get('lang.current'));
        if (!$aAllows) $aAllows = array(\C::get('lang.default'));
        if (!$aAllows) $aAllows = array('ru');
        $aLangAllow = [];
        if ($sLang = \C::get('lang.current')) {
            $n = array_search($sLang, $aAllows);
            if ($n !== false && isset($aLanguages[$sLang])) {
                $aLangAllow[$sLang] = $aLanguages[$sLang];
                $aLangAllow[$sLang]['current'] = true;
                unset($aAllows[$n]);
                unset($aLanguages[$sLang]);
            }
        }
        foreach($aAllows as $sLang) {
            if (isset($aLanguages[$sLang])) {
                $aLangAllow[$sLang] = $aLanguages[$sLang];
                $aLangAllow[$sLang]['current'] = false;
                unset($aLanguages[$sLang]);
            }
        }

        if ($this->getPost('submit_data_save')) {
            $aConfig = [];

            // добавление новых языков в список используемых
            $aAddLangs = $this->getPost('lang_allow');
            if ($aAddLangs) {
                $aAliases = (array)Config::get('lang.aliases');
                foreach($aAddLangs as $sLang) {
                    if (isset($aLanguages[$sLang])) {
                        $aLangAllow[$sLang] = $aLanguages[$sLang];
                        if (!isset($aAliases[$sLang]) && isset($aLanguages[$sLang]['name'])) {
                            $aAliases[$sLang] = strtolower($aLanguages[$sLang]['name']);
                        }
                    }
                }
                $aConfig['lang.allow'] = array_keys($aLangAllow);
                $aConfig['lang.aliases'] = $aAliases;
            }

            // смена текущего языка
            $sCurrent = $this->getPost('lang_current');
            if ($sCurrent && isset($aLangAllow[$sCurrent])) {
                $aConfig['lang.current'] = $sCurrent;
            }

            // исключение языков из списка используемых
            $sExclude = $this->getPost('lang_exclude');
            if ($sExclude) {
                $aExclude = array_unique(\F::Array_Str2Array($sExclude, ',', true));
                if ($aExclude) {
                    foreach($aExclude as $sLang) {
                        if (isset($aLangAllow[$sLang]) && count($aLangAllow) > 1) {
                            unset($aLangAllow[$sLang]);
                        }
                    }
                    $aConfig['lang.allow'] = array_keys($aLangAllow);
                }
            }

            if ($aConfig) {
                Config::writeCustomConfig($aConfig);
            }
            R::Location('admin/settings-lang/');
        }

        $this->_setTitle(\E::Module('Lang')->get('action.admin.set_title_lang'));
        $this->setTemplateAction('settings/lang');

        \E::Module('Viewer')->assign('aLanguages', $aLanguages);
        \E::Module('Viewer')->assign('aLangAllow', $aLangAllow);
    }

    /**********************************************************************************/

    /**
     * Типы блогов
     */
    public function eventBlogTypes() {

        $this->sMainMenuItem = 'settings';

        $sMode = $this->getParam(0);
        \E::Module('Viewer')->assign('sMode', $sMode);

        if ($sMode === 'add') {
            return $this->_eventBlogTypesAdd();
        } elseif ($sMode === 'edit') {
            return $this->_eventBlogTypesEdit();
        } elseif ($sMode === 'delete') {
            return $this->_eventBlogTypesDelete();
        } elseif ($this->getPost('blogtype_action') === 'activate') {
            return $this->_eventBlogTypeSetActive(1);
        } elseif ($this->getPost('blogtype_action') === 'deactivate') {
            return $this->_eventBlogTypeSetActive(0);
        }
        return $this->_eventBlogTypesList();
    }

    /**
     *
     */
    protected function _eventBlogTypesList() {

        $this->_setTitle(\E::Module('Lang')->get('action.admin.blogtypes_menu'));
        $this->setTemplateAction('settings/blogtypes');

        $aBlogTypes = \E::Module('Blog')->getBlogTypes();
        $aLangList = \E::Module('Lang')->getLangList();

        \E::Module('Viewer')->assign('aBlogTypes', $aBlogTypes);
        \E::Module('Viewer')->assign('aLangList', $aLangList);

        \E::Module('Lang')->addLangJs(array(
                'action.admin.blogtypes_del_confirm_title',
                'action.admin.blogtypes_del_confirm_text',
            ));
    }

    /**
     *
     */
    protected function _eventBlogTypesEdit() {

        $this->_setTitle(\E::Module('Lang')->get('action.admin.blogtypes_menu'));
        $this->setTemplateAction('settings/blogtypes_edit');

        $nBlogTypeId = (int)$this->getParam(1);
        if ($nBlogTypeId) {
            /** @var ModuleBlog_EntityBlogType $oBlogType */
            $oBlogType = \E::Module('Blog')->getBlogTypeById($nBlogTypeId);

            $aLangList = \E::Module('Lang')->getLangList();
            if ($this->isPost('submit_type_add')) {
                return $this->_eventBlogTypesEditSubmit();
            } else {
                $_REQUEST['blogtypes_typecode'] = $oBlogType->GetTypeCode();
                $_REQUEST['blogtypes_allow_add'] = $oBlogType->IsAllowAdd();
                $_REQUEST['blogtypes_min_rating'] = $oBlogType->GetMinRateAdd();
                $_REQUEST['blogtypes_max_num'] = $oBlogType->GetMaxNum();
                $_REQUEST['blogtypes_show_title'] = $oBlogType->IsShowTitle();
                $_REQUEST['blogtypes_index_content'] = !$oBlogType->IsIndexIgnore();
                $_REQUEST['blogtypes_membership'] = $oBlogType->GetMembership();
                $_REQUEST['blogtypes_min_rate_write'] = $oBlogType->GetMinRateWrite();
                $_REQUEST['blogtypes_min_rate_read'] = $oBlogType->GetMinRateRead();
                $_REQUEST['blogtypes_min_rate_comment'] = $oBlogType->GetMinRateComment();
                $_REQUEST['blogtypes_active'] = $oBlogType->IsActive();
                $_REQUEST['blogtypes_candelete'] = $oBlogType->CanDelete();
                $_REQUEST['blogtypes_norder'] = $oBlogType->GetNorder();
                $_REQUEST['blogtypes_active'] = $oBlogType->IsActive();

                if ($oBlogType->GetAclWrite() & ModuleBlog::BLOG_USER_ACL_GUEST) {
                    $_REQUEST['blogtypes_acl_write'] = ModuleBlog::BLOG_USER_ACL_GUEST;
                } elseif ($oBlogType->GetAclWrite() & ModuleBlog::BLOG_USER_ACL_USER) {
                    $_REQUEST['blogtypes_acl_write'] = ModuleBlog::BLOG_USER_ACL_USER;
                } elseif ($oBlogType->GetAclWrite() & ModuleBlog::BLOG_USER_ACL_MEMBER) {
                    $_REQUEST['blogtypes_acl_write'] = ModuleBlog::BLOG_USER_ACL_MEMBER;
                } else {
                    $_REQUEST['blogtypes_acl_write'] = 0;
                }

                if ($oBlogType->GetAclRead() & ModuleBlog::BLOG_USER_ACL_GUEST) {
                    $_REQUEST['blogtypes_acl_read'] = ModuleBlog::BLOG_USER_ACL_GUEST;
                } elseif ($oBlogType->GetAclRead() & ModuleBlog::BLOG_USER_ACL_USER) {
                    $_REQUEST['blogtypes_acl_read'] = ModuleBlog::BLOG_USER_ACL_USER;
                } elseif ($oBlogType->GetAclRead() & ModuleBlog::BLOG_USER_ACL_MEMBER) {
                    $_REQUEST['blogtypes_acl_read'] = ModuleBlog::BLOG_USER_ACL_MEMBER;
                } else {
                    $_REQUEST['blogtypes_acl_read'] = 0;
                }

                if ($oBlogType->GetAclComment() & ModuleBlog::BLOG_USER_ACL_GUEST) {
                    $_REQUEST['blogtypes_acl_comment'] = ModuleBlog::BLOG_USER_ACL_GUEST;
                } elseif ($oBlogType->GetAclComment() & ModuleBlog::BLOG_USER_ACL_USER) {
                    $_REQUEST['blogtypes_acl_comment'] = ModuleBlog::BLOG_USER_ACL_USER;
                } elseif ($oBlogType->GetAclComment() & ModuleBlog::BLOG_USER_ACL_MEMBER) {
                    $_REQUEST['blogtypes_acl_comment'] = ModuleBlog::BLOG_USER_ACL_MEMBER;
                } else {
                    $_REQUEST['blogtypes_acl_comment'] = 0;
                }

                $_REQUEST['blogtypes_name'] = $oBlogType->GetProp('type_name');
                $_REQUEST['blogtypes_description'] = $oBlogType->GetProp('type_description');
                foreach ($aLangList as $sLang) {
                    $_REQUEST['blogtypes_title'][$sLang] = $oBlogType->GetTitle($sLang);
                }

//                $_REQUEST['blogtypes_contenttype'] = $oBlogType->GetContentType();
                foreach ($oBlogType->getContentTypes() as $oContentType) {
                    $_REQUEST['blogtypes_contenttype'][] = $oContentType->GetId();
                }

            }
            \E::Module('Viewer')->assign('oBlogType', $oBlogType);
            \E::Module('Viewer')->assign('aLangList', $aLangList);
            $aFilter = array('content_active' => 1);
            $aContentTypes = \E::Module('Topic')->getContentTypes($aFilter, false);
            \E::Module('Viewer')->assign('aContentTypes', $aContentTypes);
        }
    }

    /**
     *
     */
    protected function _eventBlogTypesEditSubmit() {

        $nBlogTypeId = (int)$this->getParam(1);
        if ($nBlogTypeId) {
            /** @var ModuleBlog_EntityBlogType $oBlogType */
            $oBlogType = \E::Module('Blog')->getBlogTypeById($nBlogTypeId);
            if ($oBlogType) {
                $oBlogType->_setValidateScenario('update');

                $oBlogType->setProp('type_name', $this->getPost('blogtypes_name'));
                $oBlogType->setProp('type_description', $this->getPost('blogtypes_description'));

                $oBlogType->SetAllowAdd($this->getPost('blogtypes_allow_add') ? 1 : 0);
                $oBlogType->SetMinRateAdd($this->getPost('blogtypes_min_rating'));
                $oBlogType->SetMaxNum($this->getPost('blogtypes_max_num'));
                $oBlogType->SetAllowList($this->getPost('blogtypes_show_title'));
                $oBlogType->SetIndexIgnore($this->getPost('blogtypes_index_content') ? 0 : 1);
                $oBlogType->SetMembership((int)$this->getPost('blogtypes_membership'));
                $oBlogType->SetMinRateWrite($this->getPost('blogtypes_min_rate_write'));
                $oBlogType->SetMinRateRead($this->getPost('blogtypes_min_rate_read'));
                $oBlogType->SetMinRateComment($this->getPost('blogtypes_min_rate_comment'));
                $oBlogType->SetActive($this->getPost('blogtypes_active'));

                // Теперь здесь null будет всегда...
//                $oBlogType->SetContentType($this->GetPost('blogtypes_contenttype'));
                $oBlogType->SetContentType(NULL);
                $aBlogContentypes = (array)$this->getPost('blogtypes_contenttype');
                if (!$aBlogContentypes) {
                    $oBlogType->setContentTypes(array());
                } else {
                    $oBlogType->setContentTypes(array_unique(array_keys($this->getPost('blogtypes_contenttype'))));
                }

                // Установка прав на запись
                $nAclValue = (int)$this->getPost('blogtypes_acl_write');
                $oBlogType->SetAclWrite($nAclValue);

                // Установка прав на чтение
                $nAclValue = (int)$this->getPost('blogtypes_acl_read');
                $oBlogType->SetAclRead($nAclValue);

                // Установка прав на комментирование
                $nAclValue = (int)$this->getPost('blogtypes_acl_comment');
                $oBlogType->SetAclComment($nAclValue);

                \HookManager::run('blogtype_edit_validate_before', array('oBlogType' => $oBlogType));
                if ($oBlogType->_validate()) {
                    if ($this->_updateBlogType($oBlogType)) {
                        R::Location('admin/settings-blogtypes');
                    }
                } else {
                    \E::Module('Message')->addError($oBlogType->_getValidateError(), \E::Module('Lang')->get('error'));
                }
                \E::Module('Viewer')->assign('oBlogType', $oBlogType);
            } else {
                \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.blogtypes_err_id_notfound'), \E::Module('Lang')->get('error'));
            }
        }
    }

    /**
     *
     */
    protected function _eventBlogTypesAdd() {

        $this->_setTitle(\E::Module('Lang')->get('action.admin.blogtypes_menu'));
        $this->setTemplateAction('settings/blogtypes_edit');

        $aLangList = \E::Module('Lang')->getLangList();
        \E::Module('Viewer')->assign('aLangList', $aLangList);

        if ($this->isPost('submit_type_add')) {
            return $this->_eventBlogTypesAddSubmit();
        }
        $_REQUEST['blogtypes_show_title'] = true;
        $_REQUEST['blogtypes_index_content'] = true;
        $_REQUEST['blogtypes_allow_add'] = true;
        $_REQUEST['blogtypes_min_rating'] = \C::get('acl.create.blog.rating');
        $_REQUEST['blogtypes_min_rate_comment'] = \C::get('acl.create.comment.rating');

        $_REQUEST['blogtypes_acl_write'] = array(
            'notmember' => ModuleBlog::BLOG_USER_ROLE_NOTMEMBER,
        );
        $_REQUEST['blogtypes_acl_read'] = array(
            'notmember' => ModuleBlog::BLOG_USER_ROLE_NOTMEMBER,
        );
        $_REQUEST['blogtypes_acl_comment'] = array(
            'notmember' => ModuleBlog::BLOG_USER_ROLE_NOTMEMBER,
        );
        $_REQUEST['blogtypes_contenttypes'] = '';
        $aFilter = array('content_active' => 1);
        $aContentTypes = \E::Module('Topic')->getContentTypes($aFilter, false);
        \E::Module('Viewer')->assign('aContentTypes', $aContentTypes);
    }

    /**
     *
     */
    protected function _eventBlogTypesAddSubmit() {
        /** @var ModuleBlog_EntityBlogType $oBlogType */
        $oBlogType = \E::getEntity('Blog_BlogType');
        $oBlogType->_setValidateScenario('add');

        $sTypeCode = $this->getPost('blogtypes_typecode');
        $oBlogType->SetTypeCode($sTypeCode);
        $oBlogType->setProp('type_name', $this->getPost('blogtypes_name'));
        $oBlogType->setProp('type_description', $this->getPost('blogtypes_description'));

        $oBlogType->SetAllowAdd($this->getPost('blogtypes_allow_add') ? 1 : 0);
        $oBlogType->SetMinRateAdd($this->getPost('blogtypes_min_rating'));
        $oBlogType->SetMaxNum($this->getPost('blogtypes_max_num'));
        $oBlogType->SetAllowList($this->getPost('blogtypes_show_title'));
        $oBlogType->SetIndexIgnore(!(bool)$this->getPost('blogtypes_index_content'));
        $oBlogType->SetMembership((int)$this->getPost('blogtypes_membership'));
        $oBlogType->SetMinRateWrite($this->getPost('blogtypes_min_rate_write'));
        $oBlogType->SetMinRateRead($this->getPost('blogtypes_min_rate_read'));
        $oBlogType->SetMinRateComment($this->getPost('blogtypes_min_rate_comment'));
        $oBlogType->SetActive($this->getPost('blogtypes_active'));

//        $oBlogType->SetContentType($this->GetPost('blogtypes_contenttype'));
        $oBlogType->SetContentType(NULL);
        $aBlogContentypes = (array)$this->getPost('blogtypes_contenttype');
        if (!$aBlogContentypes) {
            $oBlogType->setContentTypes(array());
        } else {
            $oBlogType->setContentTypes(array_unique(array_keys($this->getPost('blogtypes_contenttype'))));
        }

        // Установка прав на запись
        $nAclValue = (int)$this->getPost('blogtypes_acl_write');
        $oBlogType->SetAclWrite($nAclValue);

        // Установка прав на чтение
        $nAclValue = (int)$this->getPost('blogtypes_acl_read');
        $oBlogType->SetAclRead($nAclValue);

        // Установка прав на комментирование
        $nAclValue = (int)$this->getPost('blogtypes_acl_comment');
        $oBlogType->SetAclComment($nAclValue);

        \HookManager::run('blogtype_add_validate_before', array('oBlogType' => $oBlogType));
        if ($oBlogType->_validate()) {
            if ($this->_addBlogType($oBlogType)) {
                R::Location('admin/settings-blogtypes');
            }
        } else {
            \E::Module('Message')->addError($oBlogType->_getValidateError(), \E::Module('Lang')->get('error'));
            \E::Module('Viewer')->assign('aFormErrors', $oBlogType->_getValidateErrors());
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function _eventBlogTypesDelete() {

        $iBlogTypeId = (int)$this->getParam(1);
        if ($iBlogTypeId && ($oBlogType = \E::Module('Blog')->getBlogTypeById($iBlogTypeId))) {

            if ($oBlogType->GetBlogsCount()) {
                \E::Module('Message')->addErrorSingle(
                    \E::Module('Lang')->get('action.admin.blogtypes_del_err_notempty', array('count' => $oBlogType->GetBlogsCount())),
                    \E::Module('Lang')->get('action.admin.blogtypes_del_err'),
                    true
                );
            } else {
                $sName = $oBlogType->getTypeCode() . ' - ' . htmlentities($oBlogType->getName());
                if ($this->_deleteBlogType($oBlogType)) {
                    \E::Module('Message')->addNoticeSingle(
                        \E::Module('Lang')->get('action.admin.blogtypes_del_success', array('name' => $sName)),
                        null,
                        true
                    );
                } else {
                    \E::Module('Message')->addErrorSingle(
                        \E::Module('Lang')->get('action.admin.blogtypes_del_err_text', array('name' => $sName)),
                        \E::Module('Lang')->get('action.admin.blogtypes_del_err'),
                        true
                    );
                }
            }
        }

        R::Location('admin/settings-blogtypes');
    }

    /**
     * @param $oBlogType
     *
     * @return bool
     */
    protected function _addBlogType($oBlogType) {

        return \E::Module('Blog')->AddBlogType($oBlogType);
    }

    /**
     * @param $oBlogType
     *
     * @return bool
     */
    protected function _updateBlogType($oBlogType) {

        return \E::Module('Blog')->UpdateBlogType($oBlogType);
    }

    /**
     * @param $oBlogType
     *
     * @return bool
     */
    protected function _deleteBlogType($oBlogType) {

        return \E::Module('Blog')->DeleteBlogType($oBlogType);
    }

    /**
     * @param $nVal
     */
    protected function _eventBlogTypeSetActive($nVal) {

        $aBlogTypes = $this->getPost('blogtype_sel');
        if (is_array($aBlogTypes) && count($aBlogTypes)) {
            $aBlogTypes = array_keys($aBlogTypes);
            foreach ($aBlogTypes as $nBlogTypeId) {
                $oBlogType = \E::Module('Blog')->getBlogTypeById($nBlogTypeId);
                if ($oBlogType) {
                    $oBlogType->SetActive($nVal);
                    \E::Module('Blog')->UpdateBlogType($oBlogType);
                }
            }
        }
        R::Location('admin/settings-blogtypes');
    }

    /**********************************************************************************/

    /**
     * Права пользователей
     */
    public function eventUserRights() {

        $this->sMainMenuItem = 'settings';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.userrights_menu'));
        $this->setTemplateAction('settings/userrights');

        if ($this->isPost('submit_type_add')) {
            return $this->_eventUserRightsEditSubmit();
        } else {
            $_REQUEST['userrights_administrator'] = \E::Module('ACL')->getUserRights('blogs', 'administrator');
            $_REQUEST['userrights_moderator'] = \E::Module('ACL')->getUserRights('blogs', 'moderator');
        }
    }

    protected function _eventUserRightsEditSubmit() {

        $aAdmin = $this->getPost('userrights_administrator');
        $aModer = $this->getPost('userrights_moderator');
        $aConfig = [];
        $aConfig['rights.blogs.administrator'] = array(
            'control_users'  => (isset($aAdmin['control_users'])  && $aAdmin['control_users'])  ? true : false,
            'edit_blog'      => (isset($aAdmin['edit_blog'])      && $aAdmin['edit_blog'])      ? true : false,
            'edit_content'   => (isset($aAdmin['edit_content'])   && $aAdmin['edit_content'])   ? true : false,
            'delete_content' => (isset($aAdmin['delete_content']) && $aAdmin['delete_content']) ? true : false,
            'edit_comment'   => (isset($aAdmin['edit_comment'])   && $aAdmin['edit_comment'])   ? true : false,
            'delete_comment' => (isset($aAdmin['delete_comment']) && $aAdmin['delete_comment']) ? true : false,
        );
        $aConfig['rights.blogs.moderator'] = array(
            'control_users'  => (isset($aModer['control_users'])  && $aModer['control_users'])  ? true : false,
            'edit_blog'      => (isset($aModer['edit_blog'])      && $aModer['edit_blog'])      ? true : false,
            'edit_content'   => (isset($aModer['edit_content'])   && $aModer['edit_content'])   ? true : false,
            'delete_content' => (isset($aModer['delete_content']) && $aModer['delete_content']) ? true : false,
            'edit_comment'   => (isset($aModer['edit_comment'])   && $aModer['edit_comment'])   ? true : false,
            'delete_comment' => (isset($aModer['delete_comment']) && $aModer['delete_comment']) ? true : false,
        );
        Config::writeCustomConfig($aConfig);
    }

    /**********************************************************************************/

    /**
     * Change the order of menu items
     */
    public function eventAjaxChangeOrderMenu() {

        // * Устанавливаем формат ответа
        \E::Module('Viewer')->SetResponseAjax('json');

        if (!\E::isUser()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        if (!$this->oUserCurrent->isAdministrator()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        if (!F::getRequest('order')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        if (!F::getRequest('menu_id')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        /** @var ModuleMenu_EntityMenu $oMenu */
        $oMenu = \E::Module('Menu')->getMenu(\F::getRequest('menu_id'));

        if (is_array(\F::getRequest('order')) && $oMenu) {

            $aData = [];
            //$aAllowedData = array_keys(Config::Get("menu.data.{$oMenu->getId()}.items"));
            foreach (\F::getRequest('order') as $aOrder) {
                if (!($sId = (isset($aOrder['id']) ? $aOrder['id'] : false))) {
                    continue;
                }
                //if (!in_array($sId, $aAllowedData)) {
                //    continue;
                //}
                $aData[]=$sId;
            }

            if ($aData) {
                $oMenu->SetConfig('init.fill.list', $aData);
                \E::Module('Menu')->SaveMenu($oMenu);
            }


            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('action.admin.save_sort_success'));
            return;
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

    }

    /**
     * Change the text of the menu item
     */
    public function eventAjaxChangeMenuText() {

        // * Устанавливаем формат ответа
        \E::Module('Viewer')->setResponseAjax('json');

        if (!\E::isUser()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        if (!$this->oUserCurrent->isAdministrator()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        if (!F::getRequest('menu_id')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        if (!F::getRequest('item_id')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        if (!F::getRequest('text')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        $sMenuId = F::getRequest('menu_id');
        $sItemId = F::getRequest('item_id');
        $sText = trim(\F::getRequest('text'));

        /** @var ModuleMenu_EntityMenu $oMenu */
        $oMenu = \E::Module('Menu')->getMenu($sMenuId);

        /** @var ModuleMenu_EntityItem $oItem */
        $oItem = $oMenu->GetItemById($sItemId);
        if ($oItem) {
            if ($sText) {
                $oMenu->SetConfigItem($sItemId, 'text', $sText);
                \E::Module('Menu')->SaveMenu($oMenu);

                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('action.admin.menu_manager_save_text_ok'));
                \E::Module('Viewer')->assignAjax('text', $sText);
                return;
            }
        }

        \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
    }

    /**
     * Change the link of the menu item
     */
    public function eventAjaxChangeMenuLink() {

        // * Устанавливаем формат ответа
        \E::Module('Viewer')->setResponseAjax('json');

        if (!\E::isUser()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        if (!$this->oUserCurrent->isAdministrator()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        if (!F::getRequest('menu_id')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        if (!F::getRequest('item_id')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        if (!F::getRequest('text')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        $sMenuId = F::getRequest('menu_id');
        $sItemId = F::getRequest('item_id');
        $sLink = trim(\F::getRequest('text'));

        /** @var ModuleMenu_EntityMenu $oMenu */
        $oMenu = \E::Module('Menu')->getMenu($sMenuId);

        /** @var ModuleMenu_EntityItem $oItem */
        $oItem = $oMenu->GetItemById($sItemId);
        if ($oItem) {
            if ($sLink) {
                $oMenu->SetConfigItem($sItemId, 'link', $sLink);
                \E::Module('Menu')->SaveMenu($oMenu);

                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('action.admin.menu_manager_save_link_ok'));
                \E::Module('Viewer')->assignAjax('text', $sLink);
                return;
            }
        }

        \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
    }

    /**
     * Remove the menu item
     */
    public function eventAjaxRemoveItem() {

        // * Устанавливаем формат ответа
        \E::Module('Viewer')->SetResponseAjax('json');

        if (!\E::isUser()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        if (!$this->oUserCurrent->isAdministrator()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        if (!F::getRequest('menu_id')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        if (!F::getRequest('item_id')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        $sMenuId = F::getRequest('menu_id');
        $sItemId = F::getRequest('item_id');

        /** @var ModuleMenu_EntityMenu $oMenu */
        $oMenu = \E::Module('Menu')->getMenu($sMenuId);

        /** @var ModuleMenu_EntityItem $oItem */
        $oItem = $oMenu->GetItemById($sItemId);
        if ($oItem) {
            $aAllowedData = array_values(\C::get("menu.data.{$oMenu->getId()}.init.fill.list"));
            if (count($aAllowedData) > 1 && isset($aAllowedData[0]) && $aAllowedData[0] === '*') {
                unset($aAllowedData[0]);
            }
            if (is_array($aAllowedData) && isset($aAllowedData[0]) && $aAllowedData[0] === '*') {
                $aAllowedData = array_keys(\C::get("menu.data.{$oMenu->getId()}.list"));
            }

            $aAllowedData = array_flip($aAllowedData);
            if (isset($aAllowedData[$oItem->getId()])) {
                unset($aAllowedData[$oItem->getId()]);
                $aAllowedData = array_flip($aAllowedData);
                if (!$aAllowedData) {
                    $aAllowedData = array(\F::RandomStr(12));
                }

                //$sMenuKey = "menu.data.{$oMenu->getId()}";
                //$aMenu = C::Get($sMenuKey);
                //$aMenu['init']['fill']['list'] = $aAllowedData;
                //Config::WriteCustomConfig(array($sMenuKey => $aMenu), false);
                $oMenu->SetConfig('init.fill.list', $aAllowedData);
                \E::Module('Menu')->SaveMenu($oMenu);

                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('action.admin.menu_manager_remove_link_ok'));
                return;
            }

        }

        \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
    }

    /**
     *
     */
    public function eventAjaxDisplayItem() {

        // * Устанавливаем формат ответа
        \E::Module('Viewer')->setResponseAjax('json');

        if (!\E::isUser()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        if (!$this->oUserCurrent->isAdministrator()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        if (!F::getRequest('menu_id')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        if (!F::getRequest('item_id')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        $sMenuId = F::getRequest('menu_id');
        $sItemId = F::getRequest('item_id');

        /** @var ModuleMenu_EntityMenu $oMenu */
        $oMenu = \E::Module('Menu')->getMenu($sMenuId);

        /** @var ModuleMenu_EntityItem $oItem */
        $oItem = $oMenu->GetItemById($sItemId);
        if ($oItem) {
            $aAllowedData = array_values(\C::get("menu.data.{$oMenu->getId()}.init.fill.list"));
            if (count($aAllowedData) > 1 && isset($aAllowedData[0]) && $aAllowedData[0] === '*') {
                unset($aAllowedData[0]);
            }
            if (is_array($aAllowedData) && isset($aAllowedData[0]) && $aAllowedData[0] === '*') {
                $aAllowedData = array_keys(\C::get("menu.data.{$oMenu->getId()}.list"));
            }

            $aAllowedData = array_flip($aAllowedData);
            if (isset($aAllowedData[$oItem->getId()])) {

                $bDisplay = \C::get("menu.data.{$oMenu->getId()}.list.{$oItem->getId()}.display");
                if (is_null($bDisplay)) {
                    $bDisplay = FALSE;
                } else {
                    $bDisplay = !$bDisplay;
                }

                if ($bDisplay) {
                    \E::Module('Viewer')->assignAjax('class', 'icon-eye-open');
                } else {
                    \E::Module('Viewer')->assignAjax('class', 'icon-eye-close');
                }

                //$sMenuKey = "menu.data.{$oMenu->getId()}";
                //$aMenu = C::Get($sMenuKey);
                //$aMenu['list'][$oItem->getId()]['display'] = $bDisplay;
                //Config::WriteCustomConfig(array($sMenuKey => $aMenu), false);
                $oMenu->SetConfigItem($sItemId, 'display', $bDisplay);
                \E::Module('Menu')->SaveMenu($oMenu);

                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('action.admin.menu_manager_display_link_ok'));

                return;
            }
        }

        \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
    }

    /**
     * @return null|string
     */
    protected function _eventMenuEdit() {

        // * Получаем тип
        $sMenuId = $this->getParam(1);

        if (!$oMenu = \E::Module('Menu')->getMenu($sMenuId)) {
            return parent::eventNotFound();
        }

        \E::Module('Viewer')->assign('oMenu', $oMenu);

        if (strpos($oMenu->getId(), 'submenu_') === 0) {
            \E::Module('Viewer')->assign('isSubMenu', \E::Module('Lang')->get('action.admin.menu_manager_submenu'));
        }

        // * Устанавливаем шаблон вывода
        $this->_setTitle(\E::Module('Lang')->get('action.admin.menu_manager_edit_menu'));
        $this->setTemplateAction('settings/menumanager_edit');

        // * Проверяем отправлена ли форма с данными
        if (\F::GetPost('submit_add_new_item')) {

            $sItemTitle = '';
            if (!(($sItemLink = trim(\F::getRequestStr('menu-item-link'))) && ($sItemTitle = trim(\F::getRequestStr('menu-item-title'))))) {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('menu_manager_item_add_error'), \E::Module('Lang')->get('error'));
                return null;
            }

            $sRoot = F::getRequest('menu-item-place');
            if ($sRoot === 'root_item') {
                // Add new item in root of menu
                $sItemName = F::RandomStr(10);
                $oMenuItem = \E::Module('Menu')->CreateMenuItem($sItemName, array(
                    'text' => $sItemTitle,
                    'link'    => $sItemLink,
                    'active'      => false,
                ));

                // Добавим в меню
                $oMenu->addItem($oMenuItem);
                \E::Module('Menu')->SaveMenu($oMenu);

                R::Location("admin/settings-menumanager/edit/{$sMenuId}");

                return null;

            } elseif ($sRoot) {

                // Разрешенные идентификаторы меню
                $aAllowedData = $oMenu->getFillList();
                if (!empty($aAllowedData) && !in_array($sRoot, $aAllowedData, true)) {
                    \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('menu_manager_item_add_error'), \E::Module('Lang')->get('error'));
                    return null;
                }

                // Проверим, есть ли подменю для этого элемента?
                $sSubMenuName = \C::get("menu.data.{$oMenu->getId()}.list.{$sRoot}.submenu");
                if (!$sSubMenuName) {
                    $sSubMenuName = 'submenu_' . F::RandomStr(10);
                    // Сохраним указатель на подменю
                    $oMenu->SetConfigItem($sRoot, 'submenu', $sSubMenuName);
                    \E::Module('Menu')->SaveMenu($oMenu);

                    // Сохраним само подменю (пока пустое)
                    $oSubMenu = \E::Module('Menu')->CreateMenu($sSubMenuName);
                    \E::Module('Menu')->SaveMenu($oSubMenu);
                } else {
                    $oSubMenu = \E::Module('Menu')->getMenu($sSubMenuName);
                }

                // Добавим новый элемент в подменю
                $sItemName = F::RandomStr(10);
                $oMenuItem = \E::Module('Menu')->CreateMenuItem($sItemName, array(
                    'text'   => $sItemTitle,
                    'link'   => $sItemLink,
                    'active' => false,
                ));
                // Добавим в меню
                $oSubMenu->AddItem($oMenuItem);
                \E::Module('Menu')->SaveMenu($oSubMenu);

                R::Location("admin/settings-menumanager/edit/{$sMenuId}");

                return null;
            }

            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('menu_manager_item_add_error'), \E::Module('Lang')->get('error'));
            return null;
        }

        return null;
    }

    /**
     * Reset menu
     *
     * @return bool|string
     */
    protected function _eventMenuReset() {

        // * Получаем тип
        $sMenuId = $this->getParam(1);

        if (!$sMenuId || !($oMenu = \E::Module('Menu')->getMenu($sMenuId))) {
            return parent::eventNotFound();
        }

        \E::Module('Menu')->ResetMenu($oMenu);

        // Это подменю, удалим его
        if (strpos($oMenu->getId(), 'submenu_') === 0) {
            $aMenus = \C::get('menu.data');
            $bFound = false;
            foreach ($aMenus as $sConfigMenuId => $aConfigMenuData) {
                foreach ($aConfigMenuData['list'] as $sItemKey => $aItemData) {
                    if (isset($aItemData['submenu']) && $aItemData['submenu'] == $sMenuId) {
                        $sMenuListKey = 'menu.data.' . $sConfigMenuId;
                        $aMenu = C::get($sMenuListKey);
                        if ($aMenu && isset($aMenu['list'][$sItemKey]['submenu'])) {
                            $aMenu['list'][$sItemKey]['submenu'] = '';
                            C::writeCustomConfig(array($sMenuListKey => $aMenu), false);
                            $bFound = true;
                            break;
                        }
                    }
                }
                if ($bFound) {
                    break;
                }
            }

            R::Location("admin/settings-menumanager/");
        }

        R::Location("admin/settings-menumanager/edit/{$sMenuId}");

        return FALSE;
    }

    /**
     * Обработчик экшена менеджера меню
     *
     * @return bool|null|string
     */
    public function eventMenuManager() {

        // Активная вкладка главного меню
        $this->sMainMenuItem = 'settings';

        // Получим страницу, на которой находится пользователь
        $sMode = $this->getParam(0);

        // В зависимости от страницы запускаем нужный обработчик
        if ($sMode === 'edit') {
            return $this->_eventMenuEdit();
        } else if ($sMode === 'reset') {
            return $this->_eventMenuReset();
        } else {

            // Получим те меню, которые можно редактировать пользователю
            $aMenus = \E::Module('Menu')->getEditableMenus();

            // Заполним вьювер
            \E::Module('Viewer')->assign(array(
                'aMenu' => $aMenus,
                'sMode' => $sMode,
            ));

            // Установим заголовок страницы
            $this->_setTitle(\E::Module('Lang')->get('action.admin.menu_manager'));

            // Установми страницу вывода
            $this->setTemplateAction('settings/menu_manager');
        }
        return null;
    }

    /**********************************************************************************/

    /**
     * Управление полями пользователя
     *
     */
    public function eventUserFields() {

        $this->sMainMenuItem = 'settings';

        switch (\F::getRequestStr('action')) {
            // * Создание нового поля
            case 'add':
                // * Обрабатываем как ajax запрос (json)
                \E::Module('Viewer')->SetResponseAjax('json');
                if (!$this->checkUserField()) {
                    return;
                }
                /** @var ModuleUser_EntityField $oField */
                $oField = \E::getEntity('User_Field');
                $oField->setName(\F::getRequestStr('name'));
                $oField->setTitle(\F::getRequestStr('title'));
                $oField->setPattern(\F::getRequestStr('pattern'));
                if (in_array(\F::getRequestStr('type'), \E::Module('User')->getUserFieldTypes())) {
                    $oField->setType(\F::getRequestStr('type'));
                } else {
                    $oField->setType('');
                }

                $iId = \E::Module('User')->AddUserField($oField);
                if (!$iId) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                    return;
                }
                // * Прогружаем переменные в ajax ответ
                \E::Module('Viewer')->assignAjax('id', $iId);
                \E::Module('Viewer')->assignAjax('lang_delete', \E::Module('Lang')->get('user_field_delete'));
                \E::Module('Viewer')->assignAjax('lang_edit', \E::Module('Lang')->get('user_field_update'));
                \E::Module('Message')->addNotice(\E::Module('Lang')->get('user_field_added'), \E::Module('Lang')->get('attention'));
                break;

            // * Удаление поля
            case 'delete':
                // * Обрабатываем как ajax запрос (json)
                \E::Module('Viewer')->SetResponseAjax('json');
                if (!F::getRequestStr('id')) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                    return;
                }
                \E::Module('User')->DeleteUserField(\F::getRequestStr('id'));
                \E::Module('Message')->addNotice(\E::Module('Lang')->get('user_field_deleted'), \E::Module('Lang')->get('attention'));
                break;

            // * Изменение поля
            case 'update':
                // * Обрабатываем как ajax запрос (json)
                \E::Module('Viewer')->SetResponseAjax('json');
                if (!F::getRequestStr('id')) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                    return;
                }
                if (!\E::Module('User')->UserFieldExistsById(\F::getRequestStr('id'))) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                    return false;
                }
                if (!$this->checkUserField()) {
                    return;
                }
                /** @var ModuleUser_EntityField $oField */
                $oField = \E::getEntity('User_Field');
                $oField->setId(\F::getRequestStr('id'));
                $oField->setName(\F::getRequestStr('name'));
                $oField->setTitle(\F::getRequestStr('title'));
                $oField->setPattern(\F::getRequestStr('pattern'));
                if (in_array(\F::getRequestStr('type'), \E::Module('User')->getUserFieldTypes())) {
                    $oField->setType(\F::getRequestStr('type'));
                } else {
                    $oField->setType('');
                }
                if (!\E::Module('User')->UpdateUserField($oField)) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                    return;
                }
                \E::Module('Message')->addNotice(\E::Module('Lang')->get('user_field_updated'), \E::Module('Lang')->get('attention'));
                break;

            // * Показываем страницу со списком полей
            default:
                // * Загружаем в шаблон JS текстовки
                \E::Module('Lang')->addLangJs(array(
                    'action.admin.user_field_delete_confirm_title',
                    'action.admin.user_field_delete_confirm_text',
                    'action.admin.user_field_admin_title_add',
                    'action.admin.user_field_admin_title_edit',
                    'action.admin.user_field_add',
                    'action.admin.user_field_update',
                ));

                // * Получаем список всех полей
                \E::Module('Viewer')->assign('aUserFields', \E::Module('User')->getUserFields());
                \E::Module('Viewer')->assign('aUserFieldTypes', \E::Module('User')->getUserFieldTypes());
                $this->_setTitle(\E::Module('Lang')->get('action.admin.user_fields_title'));
                $this->setTemplateAction('settings/userfields');
        }
    }

    /**
     * Проверка поля пользователя на корректность из реквеста
     *
     * @return bool
     */
    public function checkUserField() {

        if (!F::getRequestStr('title')) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('user_field_error_add_no_title'), \E::Module('Lang')->get('error'));
            return false;
        }
        if (!F::getRequestStr('name')) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('user_field_error_add_no_name'), \E::Module('Lang')->get('error'));
            return false;
        }
        /**
         * Не допускаем дубликатов по имени
         */
        if (\E::Module('User')->UserFieldExistsByName(\F::getRequestStr('name'), F::getRequestStr('id'))) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('user_field_error_name_exists'), \E::Module('Lang')->get('error'));
            return false;
        }
        return true;
    }

    /**********************************************************************************/

    public function eventContentTypes() {

        $this->sMainMenuItem = 'settings';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.contenttypes_menu'));
        $this->setTemplateAction('settings/contenttypes');

        $sMode = $this->getParam(0);
        \E::Module('Viewer')->assign('sMode', $sMode);

        \E::Module('Lang')->addLangJs(array(
            'action.admin.contenttypes_del_confirm_title',
            'action.admin.contenttypes_del_confirm_text',
        ));

        if ($sMode === 'add') {
            return $this->_eventContentTypesAdd();
        } elseif ($sMode === 'edit') {
            return $this->_eventContentTypesEdit();
        } elseif ($sMode === 'delete') {
            return $this->_eventContentTypesDelete();
        }

        // * Получаем список
        $aFilter = [];
        $aTypes = \E::Module('Topic')->getContentTypes($aFilter, false);
        \E::Module('Viewer')->assign('aTypes', $aTypes);

        // * Выключатель
        if (\F::getRequest('toggle') && F::CheckVal(\F::getRequest('content_id'), 'id', 1, 10) && in_array(\F::getRequest('toggle'), array('on', 'off'))) {
            \E::Module('Security')->validateSendForm();
            if ($oTypeTog = \E::Module('Topic')->getContentTypeById(\F::getRequest('content_id'))) {
                $iToggle = 1;
                if (\F::getRequest('toggle') === 'off') {
                    $iToggle = 0;
                }
                $oTypeTog->setContentActive($iToggle);
                \E::Module('Topic')->UpdateContentType($oTypeTog);

                R::Location('admin/settings-contenttypes/');
            }
        }

        return null;
    }

    /**
     * @return bool
     */
    protected function _eventContentTypesAdd() {

        $this->_setTitle(\E::Module('Lang')->get('action.admin.contenttypes_add_title'));
        $this->setTemplateAction('settings/contenttypes_edit');

        // * Вызов хуков
        \HookManager::run('topic_type_add_show');

        // * Загружаем переменные в шаблон
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('action.admin.contenttypes_add_title'));

        // * Обрабатываем отправку формы
        return $this->_eventContentTypesAddSubmit();

    }

    /**
     * @return bool
     */
    protected function _eventContentTypesAddSubmit() {

        // * Проверяем отправлена ли форма с данными
        if (!F::isPost('submit_type_add')) {
            return false;
        }

        // * Проверка корректности полей формы
        if (!$this->_checkContentFields()) {
            return false;
        }

        /** @var ModuleTopic_EntityContentType $oContentType */
        $oContentType = \E::getEntity('Topic_ContentType');
        $oContentType->setContentTitle(\F::getRequest('content_title'));
        $oContentType->setContentTitleDecl(\F::getRequest('content_title_decl'));
        $oContentType->setContentUrl(\F::getRequest('content_url'));
        $oContentType->setContentCandelete('1');
        $oContentType->setContentAccess(\F::getRequest('content_access'));
        $aConfig = F::getRequest('config');
        if (is_array($aConfig)) {
            $oContentType->setExtraValue('photoset', isset($aConfig['photoset']) ? 1 : 0);
            $oContentType->setExtraValue('link', isset($aConfig['link']) ? 1 : 0);
            $oContentType->setExtraValue('question', isset($aConfig['question']) ? 1 : 0);
        } else {
            $oContentType->setExtra('');
        }

        if (\E::Module('Topic')->AddContentType($oContentType)) {
            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('action.admin.contenttypes_success_add'), null, true);
            R::Location('admin/settings-contenttypes/');
        }
        return false;
    }

    protected function _eventContentTypesEdit() {

        // * Получаем тип
        $iContentTypeById = (int)$this->getParam(1);
        if (!$iContentTypeById || !($oContentType = \E::Module('Topic')->getContentTypeById($iContentTypeById))) {
            return parent::eventNotFound();
        }
        \E::Module('Viewer')->assign('oContentType', $oContentType);

        // * Устанавливаем шаблон вывода
        $this->_setTitle(\E::Module('Lang')->get('action.admin.contenttypes_edit_title'));
        $this->setTemplateAction('settings/contenttypes_edit');

        // * Проверяем отправлена ли форма с данными
        if ($this->isPost('submit_type_add')) {

            // * Обрабатываем отправку формы
            return $this->_eventContentTypesEditSubmit($oContentType);
        } else {
            $_REQUEST['content_id'] = $oContentType->getContentId();
            $_REQUEST['content_title'] = $oContentType->getContentTitle();
            $_REQUEST['content_title_decl'] = $oContentType->getContentTitleDecl();
            $_REQUEST['content_url'] = $oContentType->getContentUrl();
            $_REQUEST['content_candelete'] = $oContentType->getContentCandelete();
            $_REQUEST['content_access'] = $oContentType->getContentAccess();
            $_REQUEST['config']['photoset'] = $oContentType->getExtraValue('photoset');
            $_REQUEST['config']['question'] = $oContentType->getExtraValue('question');
            $_REQUEST['config']['link'] = $oContentType->getExtraValue('link');
        }
        return null;
    }

    /**
     * @param ModuleTopic_EntityContentType $oContentType
     *
     * @return bool
     */
    protected function _eventContentTypesEditSubmit($oContentType) {

        // * Проверяем отправлена ли форма с данными
        if (!F::isPost('submit_type_add')) {
            return false;
        }

        // * Проверка корректности полей формы
        if (!$this->_checkContentFields()) {
            return false;
        }

        $sTypeOld = $oContentType->getContentUrl();

        $oContentType->setContentTitle(\F::getRequest('content_title'));
        $oContentType->setContentTitleDecl(\F::getRequest('content_title_decl'));
        $oContentType->setContentUrl(\F::getRequest('content_url'));
        $oContentType->setContentAccess(\F::getRequest('content_access'));
        $aConfig = F::getRequest('config');
        if (is_array($aConfig)) {
            $oContentType->setExtraValue('photoset', isset($aConfig['photoset']) ? 1 : 0);
            $oContentType->setExtraValue('link', isset($aConfig['link']) ? 1 : 0);
            $oContentType->setExtraValue('question', isset($aConfig['question']) ? 1 : 0);
        } else {
            $oContentType->setExtra('');
        }

        if (\E::Module('Topic')->UpdateContentType($oContentType)) {

            if ($oContentType->getContentUrl() != $sTypeOld) {

                //меняем у уже созданных топиков системный тип
                \E::Module('Topic')->ChangeType($sTypeOld, $oContentType->getContentUrl());
            }

            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('action.admin.contenttypes_success_edit'), null, true);
            R::Location('admin/settings-contenttypes/');
        }
        return false;
    }

    protected function _eventContentTypesDelete() {

        // * Получаем тип
        $iContentTypeById = (int)$this->getParam(1);
        if (!$iContentTypeById || !($oContentType = \E::Module('Topic')->getContentTypeById($iContentTypeById))) {
            return parent::eventNotFound();
        }

        if ($oContentType->getContentCandelete()) {
            $aFilter = array(
                'topic_type' => $oContentType->getContentUrl(),
            );
            $iCountTopic = \E::Module('Topic')->getCountTopicsByFilter($aFilter);
            if ($iCountTopic) {
                \E::Module('Message')->addErrorSingle(
                    \E::Module('Lang')->get('action.admin.contenttypes_del_err_notempty', array('count' => $iCountTopic)),
                    \E::Module('Lang')->get('action.admin.contenttypes_del_err_text', array('name' => '')),
                    true
                );
                R::Location('admin/settings-contenttypes/');
            } elseif (\E::Module('Topic')->DeleteContentType($oContentType)) {
                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('action.admin.contenttypes_success_edit'), null, true);
                R::Location('admin/settings-contenttypes/');
            }
        }
        return false;
    }

    public function eventAjaxChangeOrderTypes()
    {
        // * Устанавливаем формат ответа
        \E::Module('Viewer')->setResponseAjax('json');

        if (!\E::isUser()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        if (!$this->oUserCurrent->isAdministrator()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        if (!F::getRequest('order')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }


        if (is_array(\F::getRequest('order'))) {

            foreach (\F::getRequest('order') as $oOrder) {
                if (is_numeric($oOrder['order']) && is_numeric($oOrder['id']) && $oContentType = \E::Module('Topic')->getContentTypeById($oOrder['id'])) {
                    $oContentType->setContentSort($oOrder['order']);
                    \E::Module('Topic')->UpdateContentType($oContentType);
                }
            }

            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('action.admin.save_sort_success'));
            return;
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
    }

    public function eventAjaxChangeOrderFields()
    {
        // * Устанавливаем формат ответа
        \E::Module('Viewer')->setResponseAjax('json');

        if (!\E::isUser()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        if (!$this->oUserCurrent->isAdministrator()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        if (!F::getRequest('order')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }


        if (is_array(\F::getRequest('order'))) {

            foreach (\F::getRequest('order') as $oOrder) {
                if (is_numeric($oOrder['order']) && is_numeric($oOrder['id']) && $oField = \E::Module('Topic')->getContentFieldById($oOrder['id'])) {
                    $oField->setFieldSort($oOrder['order']);
                    \E::Module('Topic')->UpdateContentField($oField);
                }
            }

            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('action.admin.save_sort_success'));
            return;
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

    }

    /***********************
     ****** Поля ***********
     **********************/
    public function eventAddField() {

        $this->sMainMenuItem = 'settings';

        $this->_setTitle(\E::Module('Lang')->get('action.admin.contenttypes_add_field_title'));

        // * Получаем тип
        if (!$oContentType = \E::Module('Topic')->getContentTypeById($this->getParam(0))) {
            return parent::eventNotFound();
        }

        \E::Module('Viewer')->assign('oContentType', $oContentType);

        // * Устанавливаем шаблон вывода
        $this->setTemplateAction('settings/contenttypes_fieldadd');

        // * Обрабатываем отправку формы
        return $this->SubmitAddField($oContentType);

    }

    /**
     * @param ModuleTopic_EntityContentType $oContentType
     *
     * @return bool
     */
    protected function submitAddField($oContentType) {

        // * Проверяем отправлена ли форма с данными
        if (!F::isPost('submit_field')) {
            return false;
        }

        // * Проверка корректности полей формы
        if (!$this->_checkFieldsField($oContentType)) {
            return false;
        }

        /** @var ModuleTopic_EntityField $oField */
        $oField = \E::getEntity('Topic_Field');
        $oField->setFieldType(\F::getRequest('field_type'));
        $oField->setContentId($oContentType->getContentId());
        $oField->setFieldName(\F::getRequest('field_name'));
        $oField->setFieldDescription(\F::getRequest('field_description'));
        $oField->setFieldRequired(\F::getRequest('field_required'));
        if (\F::getRequest('field_type') === 'select') {
            $oField->setOptionValue('select', F::getRequest('field_values'));
        }

        if (\E::Module('Topic')->AddContentField($oField)) {
            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('action.admin.contenttypes_success_fieldadd'), null, true);
            R::Location('admin/settings-contenttypes/edit/' . $oContentType->getContentId() . '/');
        }
        return false;
    }

    public function eventEditField() {

        $this->sMainMenuItem = 'settings';

        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('action.admin.contenttypes_edit_field_title'));

        // * Получаем поле
        if (!$oField = \E::Module('Topic')->getContentFieldById($this->getParam(0))) {
            return parent::eventNotFound();
        }

        \E::Module('Viewer')->assign('oField', $oField);

        // * Получаем тип
        if (!$oContentType = \E::Module('Topic')->getContentTypeById($oField->getContentId())) {
            return parent::eventNotFound();
        }

        \E::Module('Viewer')->assign('oContentType', $oContentType);

        // * Устанавливаем шаблон вывода
        $this->setTemplateAction('settings/contenttypes_fieldadd');

        // * Проверяем отправлена ли форма с данными
        if (isset($_REQUEST['submit_field'])) {

            // * Обрабатываем отправку формы
            return $this->SubmitEditField($oContentType, $oField);
        } else {
            $_REQUEST['field_id'] = $oField->getFieldId();
            $_REQUEST['field_type'] = $oField->getFieldType();
            $_REQUEST['field_name'] = $oField->getFieldName();
            $_REQUEST['field_description'] = $oField->getFieldDescription();
            $_REQUEST['field_required'] = $oField->getFieldRequired();
            $_REQUEST['field_values'] = $oField->getFieldValues();
        }
    }

    /**
     * Редактирование поля контента
     *
     * @param ModuleTopic_EntityContentType $oContentType
     * @param ModuleTopic_EntityField $oField
     * @return bool
     */
    protected function submitEditField($oContentType, $oField) {

        // * Проверяем отправлена ли форма с данными
        if (!F::isPost('submit_field')) {
            return false;
        }

        // * Проверка корректности полей формы
        if (!$this->_checkFieldsField($oContentType)) {
            return false;
        }

        if (!\E::Module('Topic')->getFieldValuesCount($oField->getFieldId())) {
            // Нет ещё ни одного значения этого поля, тогда можно сменить ещё и тип
            $oField->setFieldType(\F::getRequest('field_type'));
        }
        $oField->setFieldName(\F::getRequest('field_name'));
        $oField->setFieldDescription(\F::getRequest('field_description'));
        $oField->setFieldRequired(\F::getRequest('field_required'));
        if ($oField->getFieldType() === 'select') {
            $oField->setOptionValue('select', F::getRequest('field_values'));
        }

        if (\E::Module('Topic')->UpdateContentField($oField)) {
            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('action.admin.contenttypes_success_fieldedit'), null, true);
            R::Location('admin/settings-contenttypes/edit/' . $oContentType->getContentId() . '/');
        }
        return false;
    }

    public function eventDeleteField() {

        $this->sMainMenuItem = 'settings';

        \E::Module('Security')->validateSendForm();
        $iContentFieldId = (int)$this->getParam(0);
        if (!$iContentFieldId) {
            return parent::eventNotFound();
        }

        $oField = \E::Module('Topic')->getContentFieldById($iContentFieldId);
        if ($oField) {
            $oContentType = \E::Module('Topic')->getContentTypeById($oField->getContentId());
        } else {
            $oContentType = null;
        }

        if (\E::Module('Topic')->DeleteField($iContentFieldId)) {
            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('action.admin.contenttypes_success_fielddelete'), null, true);
            if ($oContentType) {
                R::Location('admin/settings-contenttypes/edit/' . $oContentType->getContentId() . '/');
            } else {
                R::Location('admin/settings-contenttypes/');
            }
        }
        return false;
    }


    /*************************************************************
     *
     */
    protected function _checkContentFields()
    {
        \E::Module('Security')->validateSendForm();

        $bOk = true;

        if (!F::CheckVal(\F::getRequest('content_title', null, 'post'), 'text', 2, 200)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.contenttypes_type_title_error'), \E::Module('Lang')->get('error'));
            $bOk = false;
        }

        if (!F::CheckVal(\F::getRequest('content_title_decl', null, 'post'), 'text', 2, 200)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.contenttypes_type_title_decl_error'), \E::Module('Lang')->get('error'));
            $bOk = false;
        }
        $sContentUrl = \F::getRequest('content_url', null, 'post');
        if (!$sContentUrl || !F::CheckVal(\F::getRequest('content_url', null, 'post'), 'login', 2, 50) || array_key_exists($sContentUrl, (array)\C::get('router.page'))) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.contenttypes_type_url_error'), \E::Module('Lang')->get('error'));
            $bOk = false;
        }

        if (!in_array(\F::getRequest('content_access'), array('1', '2', '4'))) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            $bOk = false;
        }

        return $bOk;
    }

    /**
     * @param null $oContentType
     *
     * @return bool
     */
    protected function _checkFieldsField($oContentType = null) {

        \E::Module('Security')->validateSendForm();

        $bOk = true;

        if (!F::CheckVal(\F::getRequest('field_name', null, 'post'), 'text', 2, 100)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.contenttypes_field_name_error'), \E::Module('Lang')->get('error'));
            $bOk = false;
        }
        /*
        if (!F::CheckVal(\F::getRequest('field_description', null, 'post'), 'text', 2, 200)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.contenttypes_field_description_error'), \E::Module('Lang')->get('error'));
            $bOk = false;
        }
        */
        if (R::getControllerAction() === 'fieldadd') {
            if ($oContentType === 'photoset' && (\F::getRequest('field_type', null, 'post') === 'photoset' || $oContentType->isPhotosetEnable())) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                $bOk = false;
            }

            if (!in_array(\F::getRequest('field_type', null, 'post'), \E::Module('Topic')->getAvailableFieldTypes())) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.contenttypes_field_type_error'), \E::Module('Lang')->get('error'));
                $bOk = false;
            }
        }

        // * Выполнение хуков
        \HookManager::run('check_admin_content_fields', array('bOk' => &$bOk));

        return $bOk;
    }

    /**
     * Голосование админа
     */
    public function eventAjaxVote() {

        // * Устанавливаем формат ответа
        \E::Module('Viewer')->setResponseAjax('json');

        if (!\E::isAdmin()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }

        $nUserId = $this->getPost('idUser');
        if (!$nUserId || !($oUser = \E::Module('User')->getUserById($nUserId))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('user_not_found'), \E::Module('Lang')->get('error'));
            return;
        }

        $nValue = $this->getPost('value');

        /** @var ModuleVote_EntityVote $oUserVote */
        $oUserVote = \E::getEntity('Vote');
        $oUserVote->setTargetId($oUser->getId());
        $oUserVote->setTargetType('user');
        $oUserVote->setVoterId($this->oUserCurrent->getId());
        $oUserVote->setDirection($nValue);
        $oUserVote->setDate(\F::Now());
        $iVal = (float)\E::Module('Rating')->VoteUser($this->oUserCurrent, $oUser, $nValue);
        $oUserVote->setValue($iVal);
        $oUser->setCountVote($oUser->getCountVote() + 1);
        if (\E::Module('Vote')->addVote($oUserVote) && \E::Module('User')->Update($oUser)) {
            \E::Module('Viewer')->assignAjax('iRating', $oUser->getRating());
            \E::Module('Viewer')->assignAjax('iSkill', $oUser->getSkill());
            \E::Module('Viewer')->assignAjax('iCountVote', $oUser->getCountVote());
            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('user_vote_ok'), \E::Module('Lang')->get('attention'));
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('action.admin.vote_error'), \E::Module('Lang')->get('error'));
        }
    }

    public function eventAjaxSetProfile() {

        // * Устанавливаем формат ответа
        \E::Module('Viewer')->SetResponseAjax('json');

        if (!\E::isAdmin()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }

        $nUserId = (int)$this->getPost('user_id');
        if ($nUserId && ($oUser = \E::Module('User')->getUserById($nUserId))) {
            $sData = $this->getPost('profile_about');
            if (!is_null($sData)) {
                $oUser->setProfileAbout($sData);
            }
            $sData = $this->getPost('profile_site');
            if (!is_null($sData)) {
                $oUser->setUserProfileSite(trim($sData));
            }
            $sData = $this->getPost('profile_email');
            if (!is_null($sData)) {
                $oUser->setMail(trim($sData));
            }

            if (\E::Module('User')->Update($oUser) !== false) {
                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('action.admin.saved_ok'));
            } else {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('action.admin.saved_err'));
            }
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('user_not_found'), \E::Module('Lang')->get('error'));
        }
    }

    public function eventAjaxConfig() {

        \E::Module('Viewer')->SetResponseAjax('json');

        if ($sKeys = $this->getPost('keys')) {
            if (!is_array($sKeys)) {
                $aKeys = F::ArrayToStr($sKeys);
            } else {
                $aKeys = (array)$sKeys;
            }
            $aConfig = [];
            foreach ($aKeys as $sKey) {
                $sValue = $this->getPost($sKey);
                $aConfig[str_replace('--', '.', $sKey)] = $sValue;
            }
            Config::writeCustomConfig($aConfig);
        }
    }

    public function eventAjaxUserAdd() {

        \E::Module('Viewer')->SetResponseAjax('json');

        if ($this->isPost()) {
            Config::Set('module.user.captcha_use_registration', false);

            /** @var ModuleUser_EntityUser $oUser */
            $oUser = \E::getEntity('ModuleUser_EntityUser');
            $oUser->_setValidateScenario('registration');

            // * Заполняем поля (данные)
            $oUser->setLogin($this->getPost('user_login'));
            $oUser->setMail($this->getPost('user_mail'));
            $oUser->setPassword($this->getPost('user_password'));
            $oUser->setPasswordConfirm($this->getPost('user_password'));
            $oUser->setDateRegister(\F::Now());
            $oUser->setIpRegister('');
            $oUser->setActivate(1);

            if ($oUser->_validate()) {
                \HookManager::run('registration_validate_after', array('oUser' => $oUser));
                $oUser->setPassword($oUser->getPassword(), true);
                if (\E::Module('User')->Add($oUser)) {
                    \HookManager::run('registration_after', array('oUser' => $oUser));

                    // Подписываем пользователя на дефолтные события в ленте активности
                    \E::Module('Stream')->SwitchUserEventDefaultTypes($oUser->getId());

                    if ($this->isPost('user_setadmin')) {
                        \E::Module('Admin')->setAdministrator($oUser->GetId());
                    }
                }
                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('registration_ok'));
            } else {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('error'));
                \E::Module('Viewer')->assignAjax('aErrors', $oUser->_getValidateErrors());
            }
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));
        }
    }

    public function eventAjaxUserList() {

        \E::Module('Viewer')->SetResponseAjax('json');

        if ($this->isPost()) {
            $sList = trim($this->getPost('invite_listmail'));
            if ($aList = F::Array_Str2Array($sList, "\n", true)) {
                $iSentCount = 0;
                foreach($aList as $iKey => $sMail) {
                    if (\F::CheckVal($sMail, 'mail')) {
                        $oInvite = \E::Module('User')->GenerateInvite($this->oUserCurrent);
                        if (\E::Module('Notify')->SendInvite($this->oUserCurrent, $sMail, $oInvite)) {
                            unset($aList[$iKey]);
                            $iSentCount++;
                        }
                    }
                }

                \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.invaite_mail_done', array('num' => $iSentCount)), null, true);
                if ($aList) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.invaite_mail_err', array('num' => count($aList))), null, true);
                }
            }
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));
        }
    }

    /**
     * Выполняется при завершении работы экшена
     *
     */
    public function eventShutdown() {

        // * Загружаем в шаблон необходимые переменные
        \E::Module('Viewer')->assign('sMainMenuItem', $this->sMainMenuItem);
        \E::Module('Viewer')->assign('sMenuItem', $this->sMenuItem);
        \E::Module('Lang')->addLangJs(array('action.admin.form_choose_file', 'action.admin.form_no_file_selected'));
    }

}

// EOF
