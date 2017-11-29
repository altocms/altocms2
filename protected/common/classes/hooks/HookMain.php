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
 * Регистрация основных хуков
 *
 * @package hooks
 * @since   1.0
 */
class HookMain extends Hook
{
    protected $aScripts;

    /**
     * Регистрируем хуки
     */
    public function registerHook()
    {
        $this->addHandler('module_session_init_after', [$this, 'sessionInitAfter'], PHP_INT_MAX);
        $this->addHandler('action_before', [$this, 'initAction'], PHP_INT_MAX);

        $this->addHandler('template_form_add_content', [$this, 'insertFields'], -1);

        // * Показывавем поля при просмотре топика
        $this->addHandler('template_topic_content_end', [$this, 'showFields'], 150);
        $this->addHandler('template_topic_preview_content_end', [$this, 'showFields'], 150);

        // * Упрощенный вывод JS в футере, для проблемных файлов
        $this->addHandler('template_body_end', [$this, 'buildFooterJsCss'], -150);

        $this->addHandler('template_html_head_tags', [$this, 'insertHtmlHeadTags']);

        $this->addHookTemplate('layout_head_end', 'tplLayoutHeadEnd');
        $this->addHookTemplate('layout_body_begin', 'tplLayoutBodyBegin');
        $this->addHookTemplate('layout_body_end', 'tplLayoutBodyEnd');
    }


    public function sessionInitAfter()
    {
        if (!C::get('_db_')) {
            $aConfig = C::reReadStorageConfig();
            if ($aConfig) {
                C::load($aConfig, null, 'storage');
            }
        }
    }

    /**
     * Обработка хука инициализации экшенов
     */
    public function initAction()
    {
        // * Проверяем наличие директории install
        if (is_dir(rtrim(C::get('path.root.dir'), '/') . '/install')
            && (!isset($_SERVER['HTTP_APP_ENV']) || $_SERVER['HTTP_APP_ENV'] !== 'test')
        ) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('install_directory_exists'));
            R::redirect('error');
        }

        // * Проверка на закрытый режим
        $oUserCurrent = \E::User();
        if (!$oUserCurrent && C::get('general.close.mode')){
            $aEnabledActions = F::Str2Array(C::get('general.close.actions'));
            if (!in_array(R::GetAction(), $aEnabledActions)) {
                return R::redirect('login');
            }
        }
        return null;
    }


    public function insertFields()
    {
        return \E::Module('Viewer')->fetch('inject.topic.fields.tpl');
    }


    public function showFields($aVars)
    {
        $sReturn = '';
        if (isset($aVars['topic']) && isset($aVars['bTopicList'])) {
            /** @var ModuleTopic_EntityTopic $oTopic */
            $oTopic = $aVars['topic'];
            $bTopicList = $aVars['bTopicList'];
            if (!$bTopicList) {
                //получаем данные о типе топика
                if ($oType = $oTopic->getContentType()) {
                    //получаем поля для данного типа
                    if ($aFields = $oType->getFields()) {
                        //вставляем поля, если они прописаны для топика
                        foreach ($aFields as $oField) {
                            if ($oTopic->getField($oField->getFieldId()) || $oField->getFieldType() == 'photoset') {
                                \E::Module('Viewer')->assign('oField', $oField);
                                \E::Module('Viewer')->assign('oTopic', $oTopic);
                                if (\E::Module('Viewer')->templateExists('forms/view_field_' . $oField->getFieldType() . '.tpl')) {
                                    $sReturn .= \E::Module('Viewer')->fetch('forms/view_field_' . $oField->getFieldType() . '.tpl');
                                }
                            }
                        }
                    }
                }

            }
        }
        return $sReturn;
    }


    public function buildFooterJsCss()
    {
        $sCssFooter = '';
        $sJsFooter = '';

        foreach (['js', 'css'] as $sType) {
            // * Проверяем наличие списка файлов данного типа
            $aFiles = C::get('assets.footer.' . $sType);
            if (is_array($aFiles) && count($aFiles)) {
                foreach ($aFiles as $sFile) {
                    if ($sType == 'js') {
                        $sJsFooter .= "<script type='text/javascript' src='" . $sFile . "'></script>";
                    } elseif ($sType == 'css') {
                        $sCssFooter .= "<link rel='stylesheet' type='text/css' href='" . $sFile . "' />";
                    }
                }
            }
        }

        return $sCssFooter . $sJsFooter;
    }


    public function insertHtmlHeadTags()
    {
        $aTags =  \E::Module('Viewer')->getHtmlHeadTags();
        $sResult = '';
        foreach($aTags as $sTag) {
            $sResult .= $sTag . "\n";
        }
        return $sResult;
    }


    protected function _scriptCode($aScripts, $sPlace)
    {
        $sResult = '';
        foreach($aScripts as $aScript) {
            if (!empty($aScript['place']) && $aScript['place'] == $sPlace && empty($aScript['disable']) && !empty($aScript['code'])) {
                $aIncludePaths = (!empty($aScript['on']) ? $aScript['on'] : array());
                $aExcludePaths = (!empty($aScript['off']) ? $aScript['off'] : array());
                if ((!$aIncludePaths && !$aExcludePaths) || R::allowControllerPath($aIncludePaths, $aExcludePaths)) {
                    $sResult .= PHP_EOL . $aScript['code'] . PHP_EOL;
                }
            }
        }
        if ($sResult) {
            $sResult = PHP_EOL . $sResult . PHP_EOL;
        }
        return $sResult;
    }


    public function tplLayoutHeadEnd()
    {
        $sResult = '';
        $this->aScripts = C::get('script');
        if ($this->aScripts) {
            $sResult = $this->_scriptCode($this->aScripts, 'head');
        }
        return $sResult;
    }


    public function tplLayoutBodyBegin()
    {
        $sResult = '';
        if ($this->aScripts) {
            $sResult = $this->_scriptCode($this->aScripts, 'body');
        }
        return $sResult;
    }


    public function tplLayoutBodyEnd()
    {
        $sResult = $this->buildFooterJsCss();
        if ($this->aScripts) {
            $sResult .= PHP_EOL . $this->_scriptCode($this->aScripts, 'end');
        }
        return $sResult;
    }

}

// EOF