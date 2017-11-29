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
 * @package engine.modules
 * @since   1.0.2
 */
class ModuleMenu extends Module {

    protected $aMenu = [];

    public function init() {

    }

    protected function _getCacheKey($sMenuId, $aMenu) {

        $sCacheKey = $sMenuId . '-' . md5(serialize($aMenu))
            . ((isset($aMenu['init']['user_cache']) && $aMenu['init']['user_cache']) ? ('_' . \E::userId()) : '');

        return 'menu_' . $sCacheKey;
    }

    /**
     * Возвращает кэшированные элементы меню
     *
     * @param string $sMenuId Идентификатор меню
     * @param array $aMenu Конфиг меню
     *
     * @return ModuleMenu_EntityItem[]
     */
    protected function GetCachedItems($sMenuId, $aMenu) {

        // Нужно обновлять кэш каждый раз, когда изменился конфиг, для этого возьмем
        // хэш от сериализованного массива настроек и запишем его как имя кэша, а в теги
        // добавим идентификатор этого меню. И если кэша не будет, то на всякий случай
        // очистим по тегу.

        $sCacheKey = $this->_getCacheKey($sMenuId, $aMenu);
        if (FALSE === ($data = \E::Module('Cache')->Get($sCacheKey, ',file'))) {
            $this->ClearMenuCache($sMenuId);

            return [];
        }

        return $data;
    }

    /**
     * Подготавливает меню для вывода, заполняя его из указанных в
     * конфиге параметров.
     *
     * @param string $sMenuId Ид. меню, как оно указано в конфиге, например "main" для $config['view']['menu']['main']
     * @param array $aMenu Конфигурация самого меню
     *
     * @return string
     */
    public function Prepare($sMenuId, $aMenu) {

        // Проверим меню на разрешённые экшены
        if (isset($aMenu['actions']) && !R::allowControllers($aMenu['actions'])) {
            return [];
        }

        // Если тип меню не находится в списке разрешенных, то его не обрабатываем
        // Плагины же могут расширить этот список и переопределить данный метод.
//        if (!in_array($sMenuId, \C::Get('menu.allowed'))) {
//            return FALSE;
//        }

        // Почему-то при сохранении конфига добавляется пустой элемент массива с
        // числовым индексом
        if (isset($aMenu['list'][0]))
            unset($aMenu['list'][0]);

        // Тут возникает два варианта, либо есть закэшированные эелемнты меню,
        // либо их нет. Если есть, то вернем их
        /** @var ModuleMenu_EntityItem[] $aCashedItems */
        $aCashedItems = $this->GetCachedItems($sMenuId, $aMenu);
        if ($aCashedItems) {
            $aMenu['items'] = $aCashedItems;

            return $aMenu;
        }

        // Получим разрешенное количество элементов меню. Имеет смысл только для динамического
        // заполнения списка меню.
        /** @var int $iTotal */
        $iTotal = (isset($aMenu['init']['total'])
            ? (int)$aMenu['init']['total']
            : \C::get('module.menu.default_length'));

        // Получим список режимов заполнения меню
        /** @var string[] $aFillMode */
        if (!$aFillMode = (isset($aMenu['init']['fill']) ? $aMenu['init']['fill'] : FALSE)) {
            return FALSE;
        }

        // Проверим корректность переданного режима заполнения
        if (is_array($aFillMode) && $aModeName = array_keys($aFillMode)) {

            // Проверим режимы на наличие их обработчиков
            foreach ($aModeName as $sModeName) {
                // Если нет метода для обработки этого режима заполнения, то
                // удалим его за ненадобностью
                if (!method_exists($this, $this->_getProcessMethodName($sModeName))) {
                    unset($aFillMode[$sModeName]);
                }
            }

            // Если валидных режимов заполнения не осталось, то завершимся
            // и сбросим кэш, ведь очевидно, что меню пустое :(
            if (empty($aFillMode)) {
                $this->ClearMenuCache($sMenuId);

                return FALSE;
            }

        }

        // Заполняем элементы меню указанным способом
        $aItems = [];
        foreach ($aFillMode as $sModeName => $aFillSet) {
            $aItems = array_merge(
                $aItems,
                call_user_func_array(
                    array($this, $this->_getProcessMethodName($sModeName)),
                    array($aFillSet, $aMenu)
                )
            );
        }

        // Проверим количество элементов меню по допустимому максимальному количеству
        if (count($aItems) > $iTotal) {
            $aItems = array_slice($aItems, 0, $iTotal);
        }

        // Кэшируем результат, если нужно
        if (!(isset($aMenu['init']['cache']) && $aMenu['init']['cache'] == false)) {
            $sCacheKey = $this->_getCacheKey($sMenuId, $aMenu);
            \E::Module('Cache')->Set(
                $aItems,
                $sCacheKey,
                array('menu_' . $sMenuId, 'menu'),
                isset($aMenu['init']['cache']) ? $aMenu['init']['cache'] : 'P30D',
                ',file'
            );
        }

        // Добавим сформированные данные к конфигу меню
        $aMenu['items'] = $aItems;


        return $aMenu;
    }

    /**
     * Возвращает меню по его идентификатору
     *
     * @param string $sMenuId
     * @param array  $aParams
     *
     * @return ModuleMenu_EntityMenu|bool
     */
    public function getMenu($sMenuId, $aParams = null) {

        if (!$sMenuId) {
            return null;
        }
        if (isset($this->aMenu[$sMenuId])) {
            return $this->aMenu[$sMenuId];
        }

        // Настройки меню
        if ($aMenu = \C::get('menu.data.' . $sMenuId)) {
            if ($aParams) {
                $aMenu =  \F::Array_Merge($aMenu, $aParams);
            }
            // Такая форма вызова используется для того,
            // чтобы можно было повесить хук на этот метод
            $oMenu = \E::Module('Menu')->CreateMenu($sMenuId, $aMenu);

            return $oMenu;
        }

        return null;
    }

    /**
     * Получает все меню сайта
     *
     * @return ModuleMenu_EntityMenu[]
     */
    public function getMenus() {

        /** @var string[] $aMenuId */
        $aMenuId = array_keys(Config::get('menu.data'));

        return $this->GetMenusByArrayId($aMenuId);
    }

    /**
     * Return list of editable menu
     *
     * @return ModuleMenu_EntityMenu[]
     */
    public function getEditableMenus() {

        $aResult = [];
        $aEditableMenus = \C::get('menu.editable');
        if ($aEditableMenus) {
            foreach($aEditableMenus as $sMenuId) {
                $aResult[$sMenuId] = $this->GetMenu($sMenuId);
            }
        }

        return $aResult;
    }

    /**
     * Получает все меню сайта
     *
     * @param string[] $aMenuId
     *
     * @return ModuleMenu_EntityMenu[]
     */
    public function getMenusByArrayId($aMenuId) {

        if (!is_array($aMenuId)) {
            $aMenuId = array($aMenuId);
        }

        /** @var ModuleMenu_EntityMenu[] $aResult */
        $aResult = [];
        if ($aMenuId) {
            foreach ($aMenuId as $sMenuId) {
                $aResult[$sMenuId] = $this->GetMenu($sMenuId);
            }
        }

        return $aResult;
    }

    public function setConfig($sKey, $xValue) {

    }

    /**
     * Сохраняем меню
     *
     * @param ModuleMenu_EntityMenu $oMenu
     */
    public function SaveMenu($oMenu) {

        // Get config data of the menu
        $aMenuConfig = $oMenu->GetConfig(true);
        $sConfigKey = 'menu.data.' . $oMenu->getId();

        // Set in current common config
        \C::Set($sConfigKey, null);
        \C::Set($sConfigKey, $aMenuConfig);

        // Save custom config
        \C::resetCustomConfig($sConfigKey);
        \C::writeCustomConfig(array($sConfigKey => $aMenuConfig));

        // Clear cache of the menu
        $this->ClearMenuCache($oMenu->getId());
    }

    /**
     * Сбрасывает сохраненное меню в исходное состояние
     *
     * @param ModuleMenu_EntityMenu| string $xMenu
     */
    public function ResetMenu($xMenu) {

        if (is_object($xMenu)) {
            $oMenu = $xMenu;
            $sMenuId = $oMenu->getId();
        } else {
            $sMenuId = (string)$xMenu;
            $oMenu = $this->GetMenu($sMenuId);
        }
        \C::resetCustomConfig("menu.data.{$sMenuId}");
        $this->ClearMenuCache($sMenuId);

        //$aMenu = \C::Get('menu.data.' . $sMenuId, \C::LEVEL_APP);
        $aMenu = \C::get('menu.data.' . $sMenuId, \C::LEVEL_SKIN);
        $aPreparedMenuData = \E::Module('Menu')->Prepare($sMenuId, $aMenu);
        $aPreparedMenuData['_cfg'] = $aMenu;
        $oMenu->setProps($aPreparedMenuData);
        $oMenu->setItems($aPreparedMenuData['items']);
    }

    /**
     * Создает меню, заполняя его из указанных в конфиге параметров
     *
     * @param string $sMenuId   ID меню, как оно указано в конфиге, например "main" для $config['view']['menu']['main']
     * @param array  $aMenuData Конфигурация самого меню
     *
     * @return ModuleMenu_EntityMenu
     */
    public function CreateMenu($sMenuId, $aMenuData = null) {

        if (is_null($aMenuData)) {
            $aMenuData = array(
                'init'        => array(
                    'fill' => array(
                        'list' => array('*'),
                    ),
                ),
                'list'        => array(),
            );
        }

        $aPreparedMenuData = \E::Module('Menu')->Prepare($sMenuId, $aMenuData);
        $aPreparedMenuData['_cfg'] = $aMenuData;
        $oMenu = \E::getEntity('Menu_Menu', $aPreparedMenuData);
        $oMenu->setProp('id', $sMenuId);

        return $oMenu;
    }

    /**
     * Возвращает имя метода обработки режима заполнения меню
     *
     * @param string $sModeName Название режима заполнения
     *
     * @return string
     */
    private function _getProcessMethodName($sModeName) {

        return 'Process' .  \F::StrCamelize($sModeName) . 'Mode';
    }

    /**
     * Создает элемент меню по конфигурационным параметрам
     * <pre>
     * 'index' => array(
     *      'text'      => '{{topic_title}}', // Текст из языкового файла
     *      'link'      => '___path.root.url___', // динамическая подстановка из конфига
     *      'active'    => 'blog.hello',
     *      'options' => array( // любые опции
     *                      'type' => 'special',
     *                      'icon_class' => 'fa fa-file-text-o',
     *                  ),
     *      'submenu' => array(
     *          // массив подменю
     *      ),
     *      'on'        => array('index', 'blog'), // где показывать
     *      'off'       => array('admin/*', 'settings/*', 'profile/*', 'talk/*', 'people/*'), // где НЕ показывать
     *      'display'   => true,  // true - выводить, false - не выводить
     *  ),
     * </pre>
     * @param $sItemId
     * @param $aItemConfig
     *
     * @return ModuleMenu_EntityItem
     */
    public function CreateMenuItem($sItemId, $aItemConfig) {

        if (is_string($aItemConfig)) {
            return $aItemConfig;
        }

        return \E::getEntity('Menu_Item',
            array_merge(
                array('item_id' => $sItemId, '_cfg' => $aItemConfig),
                isset($aItemConfig['title']) ? array('item_title' => $aItemConfig['title']) : array(),
                isset($aItemConfig['text']) ? array('item_text' => $aItemConfig['text']) : array(),
                isset($aItemConfig['link']) ? array('item_url' => $aItemConfig['link']) : array(),
                isset($aItemConfig['active']) ? array('item_active' => $aItemConfig['active']) : array(),
                isset($aItemConfig['description']) ? array('item_description' => $aItemConfig['description']) : array(),
                isset($aItemConfig['type']) ? array('item_active' => $aItemConfig['type']) : array(),
                isset($aItemConfig['submenu']) ? array('item_submenu' => $aItemConfig['submenu']) : array(),
                isset($aItemConfig['on']) ? array('item_on' => $aItemConfig['on']) : array(),
                isset($aItemConfig['off']) ? array('item_off' => $aItemConfig['off']) : array(),
                isset($aItemConfig['display']) ? array('item_display' => $aItemConfig['display']) : array(),
                isset($aItemConfig['show']) ? array('item_show' => $aItemConfig['show']) : array(),
                isset($aItemConfig['options']) ? array('item_options' => \E::getEntity('Menu_ItemOptions', $aItemConfig['options'])) : array()
            )
        );
    }



    /******************************************************************************
     *          МЕТОДЫ ЗАПОЛНЕНИЯ МЕНЮ
     ******************************************************************************/

    /**
     * Обработчик формирования меню в режиме list
     *
     * @param string[] $aFillSet Набор элементов меню
     * @param array $aMenu Само меню
     *
     * @return array
     */
    public function ProcessListMode($aFillSet, $aMenu) {

        // Результирующий набор меню
        $aItems = [];

        if (!$aFillSet) {
            return $aItems;
        }

        //
        if (isset($aFillSet[0]) && $aFillSet[0] == '*') {
            $aFillSet = (isset($aMenu['list']) && $aMenu['list']) ? array_keys($aMenu['list']) : [];
        }

        // Добавим в вывод только нужные элементы меню
        foreach ($aFillSet as $sItemId) {
            if (isset($aMenu['list'][$sItemId])) {
                /** @var ModuleMenu_EntityItem $oMenuItem */
                $oMenuItem = \E::Module('Menu')->CreateMenuItem($sItemId, $aMenu['list'][$sItemId]);

                // Это не хук, добавим флаг режима заполнения
                if (!is_string($oMenuItem)) {
                    $oMenuItem->setMenuMode('list');
                }

                // Это хук
                if (is_string($oMenuItem)) {
                    $aItems[$sItemId] = $oMenuItem;
                    continue;
                }

                $aItems[$sItemId] = $oMenuItem;

            }
        }

        return $aItems;
    }

    /**
     * Обработчик формирования меню в режиме blogs
     *
     * @param string[] $aFillSet Набор элементов меню
     * @param array $aMenu Само меню
     *
     * @return array
     */
    public function ProcessInsertImageMode($aFillSet, $aMenu = NULL) {

        /** @var ModuleMenu_EntityItem[] $aItems */
        $aItems = [];

        // Только пользователь может смотреть своё дерево изображений
//        if (!\E::IsUser()) {
//            return $aItems;
//        }

        $sTopicId =  \F::getRequestStr('topic_id',  \F::getRequestStr('target_id', FALSE));
        if ($sTopicId && !\E::Module('Topic')->GetTopicById($sTopicId)) {
            $sTopicId = FALSE;
        }

        /** @var ModuleMedia_EntityMediaCategory[] $aResources Категории объектов пользователя */
        $aCategories = \E::Module('Media')->GetImageCategoriesByUserId(isset($aMenu['uid']) ? $aMenu['uid'] : \E::userId(), $sTopicId);

        // Получим категорию топиков для пользователя
        if ($aTopicsCategory = \E::Module('Media')->GetTopicsImageCategory(isset($aMenu['uid']) ? $aMenu['uid'] : \E::userId())) {
            foreach ($aTopicsCategory as $oTopicsCategory) {
                $aCategories[] = $oTopicsCategory;
            }
        }

        // Временные изображения
//        if ($oTmpTopicCategory = \E::Module('Media')->GetCurrentTopicImageCategory(isset($aMenu['uid']) ? $aMenu['uid'] : \E::UserId(), false)) {
//            $aCategories[] = $oTmpTopicCategory;
//        }

        if ($sTopicId && $oCurrentTopicCategory = \E::Module('Media')->GetCurrentTopicImageCategory(isset($aMenu['uid']) ? $aMenu['uid'] : \E::userId(), $sTopicId)) {
            $aCategories[] = $oCurrentTopicCategory;
        }

        if (!isset($aMenu['protect']) && (!isset($aMenu['uid']) || $aMenu['uid'] == \E::userId())) {
            if ($oTalksCategory = \E::Module('Media')->GetTalksImageCategory(isset($aMenu['uid']) ? $aMenu['uid'] : \E::userId())) {
                $aCategories[] = $oTalksCategory;
            }
        }

        if ($oCommentsCategory = \E::Module('Media')->GetCommentsImageCategory(isset($aMenu['uid']) ? $aMenu['uid'] : \E::userId())) {
            $aCategories[] = $oCommentsCategory;
        }

        if ($aCategories) {
            /** @var ModuleMedia_EntityMediaCategory $oCategory */
            foreach ($aCategories as $oCategory) {
                $aItems['menu_insert_' . $oCategory->getId()] = \E::Module('Menu')->CreateMenuItem('menu_insert_' . $oCategory->getId(), array(
                    'text'    => $oCategory->getLabel() . '<span>' . $oCategory->getCount() . '</span>',
                    'link'    => '#',
                    'active'  => FALSE,
                    'submenu' => array(),
                    'display' => TRUE,
                    'options' => array(
                        'link_class' => '',
                        'link_url'   => '#',
                        'class'      => 'category-show category-show-' . $oCategory->getId(),
                        'link_data'  => array(
                            'category' => $oCategory->getId(),
                        ),
                    ),
                ));
            }
        }

        return $aItems;

    }

    /**
     * Обработчик формирования меню в режиме blogs
     *
     * @param string[] $aFillSet Набор элементов меню
     * @param array $aMenu Само меню
     *
     * @return array
     */
    public function ProcessBlogsMode($aFillSet, $aMenu = NULL) {

        /** @var ModuleMenu_EntityItem[] $aItems */
        $aItems = [];

        /** @var ModuleBlog_EntityBlog[] $aBlogs */
        $aBlogs = [];

        if ($aFillSet) {
            $aBlogs = \E::Module('Blog')->GetBlogsByUrl($aFillSet['items']);
        } else {
            if ($aResult = \E::Module('Blog')->GetBlogsRating(1, $aFillSet['limit'])) {
                $aBlogs = $aResult['collection'];
            }
        }

        if ($aBlogs) {
            foreach ($aBlogs as $oBlog) {
                $aItems[$oBlog->getUrl()] = \E::Module('Menu')->CreateMenuItem($oBlog->getUrl(), array(
                    'title'   => $oBlog->getTitle(),
                    'link'    => $oBlog->getUrlFull(),
                    'active'  => (Config::get('router.rewrite.blog') ? \C::get('router.rewrite.blog') : 'blog') . '.' . $oBlog->getUrl(),
                    'submenu' => array(),
                    'on'      => array('{*}'),
                    'off'     => NULL,
                    'display' => TRUE,
                    'options' => array(
                        'image_url' => $oBlog->getAvatarUrl(Config::get('module.menu.blog_logo_size')),
                    ),
                ));
            }
        }

        return $aItems;

    }


    /******************************************************************************
     *          МЕТОДЫ ПРОВЕРКИ
     ******************************************************************************/

    /**
     * Вызывается по строке "is_user"
     *
     * @return bool
     */
    public function IsUser() {

        return \E::isUser();
    }

    /**
     * Вызывается по строке "is_admin"
     *
     * @return bool
     */
    public function IsAdmin() {

        return \E::isAdmin();
    }

    /**
     * Вызывается по строке "is_not_admin"
     *
     * @return bool
     */
    public function IsNotAdmin() {

        return \E::isNotAdmin();
    }

    /**
     * Вызывается по строке "user_id_is"
     *
     * @param $iUserId
     *
     * @return bool
     */
    public function UserIdIs($iUserId) {

        return \E::userId() == $iUserId;
    }

    /**
     * Вызывается по строке "user_id_not_is"
     *
     * @param $iUserId
     *
     * @return bool
     */
    public function UserIdNotIs($iUserId) {

        return \E::userId() != $iUserId;
    }

    /**
     * Вызывается по строке "check_plugin"
     *
     * @param $aPlugins
     *
     * @return bool
     */
    public function CheckPlugin($aPlugins) {

        if (is_string($aPlugins)) {
            $aPlugins = array($aPlugins);
        }

        $bResult = FALSE;
        foreach ($aPlugins as $sPluginName) {
            $bResult = $bResult || \E::activePlugin($sPluginName);
            if ($bResult) {
                break;
            }
            continue;
        }

        return $bResult;
    }

    /**
     * Вызывается по строке "compare_action"
     *
     * @param $aActionName
     *
     * @return bool
     */
    public function CompareAction($aActionName) {

        if (is_string($aActionName)) {
            $aActionName = array($aActionName);
        }

        return in_array(R::getController(), $aActionName);

    }

    /**
     * Вызывается по строке "not_action"
     *
     * @param $aActionName
     *
     * @return bool
     */
    public function NotAction($aActionName) {

        if (is_string($aActionName)) {
            $aActionName = array($aActionName);
        }

        return !in_array(R::getController(), $aActionName);

    }

    /**
     * Вызывается по строке "not_event"
     *
     * @param $aEventName
     *
     * @return bool
     */
    public function NotEvent($aEventName) {

        if (is_string($aEventName)) {
            $aEventName = array($aEventName);
        }

        return !in_array(R::getControllerAction(), $aEventName);

    }

    /**
     * Вызывается по строке "new_talk"
     *
     * @param bool $sTemplate
     *
     * @return bool
     */
    public function NewTalk($sTemplate = false) {

        $sKeyString = 'menu_new_talk_' . \E::userId() . '_' . (string)$sTemplate;

        if (FALSE === ($sData = \E::Module('Cache')->getTmp($sKeyString))) {

            $iValue = (int)E::Module('Talk')->GetCountTalkNew(\E::userId());
            if ($sTemplate && $iValue) {
                $sData = str_replace('{{new_talk_count}}', $iValue, $sTemplate);
            } else {
                $sData = $iValue ? $iValue : '';
            }

            \E::Module('Cache')->setTmp($sData, $sKeyString);
        }

        return $sData;

    }

    /**
     * Вызывается по строке "new_talk_string"
     *
     * @param string $sIcon
     *
     * @return bool
     */
    public function NewTalkString($sIcon = '') {

        $iCount = $this->NewTalk();
        if ($iCount) {
            return $sIcon . '+' . $iCount;
        }

        return $sIcon ? ($sIcon . '0') : '';
    }

    /**
     * Вызывается по строке "user_avatar_url"
     *
     * @return bool
     */
    public function UserAvatarUrl($sSize) {

        if ($oUser = \E::User()) {
            return $oUser->getAvatarUrl($sSize);
        }

        return '';

    }

    /**
     * Вызывается по строке "user_name"
     *
     * @return bool
     */
    public function UserName() {

        if ($oUser = \E::User()) {
            return $oUser->getDisplayName();
        }

        return '';

    }

    /**
     * Вызывается по строке "compare_param"
     *
     * @param $iParam
     * @param $sParamData
     *
     * @return bool
     */
    public function CompareParam($iParam, $sParamData) {

        return R::getParam($iParam) == $sParamData;
    }

    /**
     * Вызывается по строке "compare_get_param"
     *
     * @param string $sParam
     * @param mixed $mParamData
     *
     * @return bool
     */
    public function CompareGetParam($sParam, $mParamData) {

        if (!empty($_GET[$sParam])) {
            return $_GET[$sParam] == $mParamData;
        }
        return false;
    }

    /**
     * Вызывается по строке "topic_kind"
     *
     * @param $sTopicType
     *
     * @internal param $iParam
     * @internal param $sParamData
     *
     * @return bool
     */
    public function TopicKind($sTopicType)
    {
        $sViewTopicFilter = \E::Module('Viewer')->getTemplateVars('sTopicFilter');
        if ($sViewTopicFilter && $sViewTopicFilter == $sTopicType) {
            return true;
        }

        if (R::getController() !== 'index') {
            return false;
        }

        if (null === R::getControllerAction()) {
            return 'good' === $sTopicType;
        }

        return R::getControllerAction() == $sTopicType;
    }

    /**
     * Вызывается по строке "topic_filter"
     *
     * @param $sParamData
     *
     * @return bool
     */
    public function TopicFilter($sParamData) {

        $sViewTopicFilter = \E::Module('Viewer')->getTemplateVars('sTopicFilter');
        if ($sViewTopicFilter && $sViewTopicFilter == $sParamData) {
            return true;
        }

        return false;
    }

    /**
     * Вызывается по строке "topic_filter_period"
     *
     * @param $sParamData
     *
     * @return bool
     */
    public function TopicFilterPeriod($sParamData) {

        $sViewTopicFilterPeriod = \E::Module('Viewer')->getTemplateVars('sTopicFilterPeriod');
        if ($sViewTopicFilterPeriod && $sViewTopicFilterPeriod == $sParamData) {
            return true;
        }

        return false;
    }

    /**
     * Вызывается по строке "new_topics_count"
     *
     * @param string $newClass
     *
     * @internal param $iParam
     * @internal param $sParamData
     *
     * @return bool
     */
    public function NewTopicsCount($newClass = '') {

        $sKeyString = 'menu_new_topics_count_' . \E::userId() . '_' . $newClass;

        if (FALSE === ($sData = \E::Module('Cache')->getTmp($sKeyString))) {

            $iCount = \E::Module('Topic')->GetCountTopicsCollectiveNew() + \E::Module('Topic')->GetCountTopicsPersonalNew();

            if ($newClass && $iCount) {
                $sData = '<span class="' . $newClass . '"> +' . $iCount . '</span>';
            } else {
                $sData =  $iCount;
            }

            \E::Module('Cache')->setTmp($sData, $sKeyString);

        }

        return $sData;

    }

    /**
     * Вызывается по строке "no_new_topics"
     *
     * @internal param $iParam
     * @internal param $sParamData
     *
     * @return bool
     */
    public function NoNewTopics() {

        $sKeyString = 'menu_no_new_topics';

        if (FALSE === ($xData = \E::Module('Cache')->getTmp($sKeyString))) {

            $iCountTopicsCollectiveNew = \E::Module('Topic')->GetCountTopicsCollectiveNew();
            $iCountTopicsPersonalNew = \E::Module('Topic')->GetCountTopicsPersonalNew();

            $xData = $iCountTopicsCollectiveNew + $iCountTopicsPersonalNew == 0;

            \E::Module('Cache')->setTmp($xData, $sKeyString);

        }

        return $xData;

    }


    /**
     * Вызывается по строке "user_rating"
     *
     * @param string $sIcon
     * @param string $sNegativeClass
     *
     * @return bool
     */
    public function UserRating($sIcon = '', $sNegativeClass='') {

        if (!C::get('rating.enabled')) {
            return '';
        }

        if (\E::isUser()) {
            $fRating = number_format(\E::User()->getRating(), \C::get('view.rating_length'));
            if ($sNegativeClass && $fRating < 0) {
                $fRating = '<span class="'. $sNegativeClass .'">' . $fRating . '</span>';
            }
            return $sIcon . $fRating;
        }

        return '';

    }


    /**
     * Вызывается по строке "count_track"
     *
     * @param string $sIcon
     *
     * @return bool
     */
    public function CountTrack($sIcon = '') {

        if (!\E::isUser()) {
            return '';
        }

        $sKeyString = 'menu_count_track_' . \E::User()->getId() . '_' . $sIcon;

        if (FALSE === ($xData = \E::Module('Cache')->getTmp($sKeyString))) {

            $sCount = \E::Module('Userfeed')->GetCountTrackNew(\E::User()->getId());
            $xData = $sIcon . ($sCount ? '+' . $sCount : '0');

            \E::Module('Cache')->setTmp($xData, $sKeyString);

        }

        return $xData;

    }

    /**
     * Возвращает количество сообщений для пользователя
     *
     * @param bool $sTemplate
     *
     * @return int|mixed|string
     */
    public function CountMessages($sTemplate = false) {

        if (!\E::isUser()) {
            return '';
        }

        $sKeyString = 'menu_count_messages_' . \E::userId() . '_' . (string)$sTemplate;

        if (FALSE === ($sData = \E::Module('Cache')->getTmp($sKeyString))) {

            $iValue = (int)$this->CountTrack() + (int)$this->NewTalk();
            if ($sTemplate && $iValue) {
                $sData = str_replace('{{count_messages}}', $iValue, $sTemplate);
            } else {
                $sData = $iValue ? $iValue : '';
            }

            \E::Module('Cache')->setTmp($sData, $sKeyString);
        }

        return $sData;

    }

    /**
     * Clear cache for required menu
     *
     * @param $sMenuId
     */
    public function ClearMenuCache($sMenuId) {

        \E::Module('Cache')->cleanByTags(array('menu_' . $sMenuId), ',file');
    }

    /**
     * Clear cache for all menus
     */
    public function ClearAllMenuCache() {

        \E::Module('Cache')->cleanByTags(array('menu'), ',file');
    }

}

// EOF