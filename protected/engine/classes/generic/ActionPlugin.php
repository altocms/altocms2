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

namespace alto\engine\generic;

/**
 * Абстрактный класс экшена плагина.
 * От этого класса необходимо наследовать экшены плагина, эот позволит корректно определять текущий шаблон плагина для рендеринга экшена
 *
 * @package engine
 * @since   1.0
 */
abstract class ActionPlugin extends Action
{
    /**
     * Полный серверный путь до текущего шаблона плагина
     *
     * @var string|null
     */
    protected $sTemplatePathPlugin = null;

    /**
     * Возвращает путь к текущему шаблону плагина
     *
     * @return string
     */
    public function getTemplatePathPlugin()
    {
        if (is_null($this->sTemplatePathPlugin)) {
            preg_match('/^Plugin([\w]+)_Action([\w]+)$/i', $this->getActionClass(), $aMatches);
            /**
             * Проверяем в списке шаблонов
             */
            $sPlugin = strtolower($aMatches[1]);
            $sAction = strtolower($aMatches[2]);
            $sPluginDir = PluginManager::getDir($sPlugin);
            $aPaths = glob($sPluginDir . '/frontend/skin/*/actions/' . $sAction, GLOB_ONLYDIR);
            $sTemplateName = ($aPaths && in_array(
                \C::get('view.skin'),
                array_map(
                    create_function(
                        '$sPath',
                        'preg_match("/skin\/([\w\-]+)\/actions/i",$sPath,$aMatches); return $aMatches[1];'
                    ),
                    $aPaths
                )
            ))
                ? \C::get('view.skin')
                : 'default';

            $sDir = \F::File_NormPath($sPluginDir . '/frontend/skin/' . $sTemplateName . '/');
            $this->sTemplatePathPlugin = is_dir($sDir) ? $sDir : null;
        }

        return $this->sTemplatePathPlugin;
    }

    /**
     * Установить значение пути к директории шаблона плагина
     *
     * @param  string $sTemplatePath    Полный серверный путь до каталога с шаблоном
     *
     * @return bool
     */
    public function setTemplatePathPlugin($sTemplatePath)
    {
        if (!is_dir($sTemplatePath)) {
            return false;
        }
        $this->sTemplatePathPlugin = $sTemplatePath;
    }

}

// EOF