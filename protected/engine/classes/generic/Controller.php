<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 */

namespace alto\engine\generic;

use alto\engine\core\HttpRequest;
use alto\engine\core\HttpResponse;

abstract class Controller extends Component
{
    protected $sControllerTemplate;

    /**
     * Метод инициализации экшена
     *
     * @param HttpRequest
     *
     * @return HttpRequest
     */
    public function init($oRequest)
    {
        return $oRequest;
    }

    /**
     * @param HttpRequest  $oRequest
     * @param HttpResponse $oResponse
     *
     * @return HttpResponse
     */
    public function execAction($oRequest, $oResponse)
    {
        return $oResponse;
    }

    /**
     * @param HttpResponse $oResponse
     *
     * @return HttpResponse
     */
    public function shutdown($oResponse)
    {
        return $oResponse;
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        if (null === $this->sControllerTemplate) {
            $this->_setControllerTemplate($this->sCurrentEvent);
        }
        return $this->sControllerTemplate;
    }

    /**
     * @param string $sTemplate
     */
    protected function _setControllerTemplate($sTemplate)
    {
        if (substr($sTemplate, -4) !== '.tpl') {
            $sTemplate .= '.tpl';
        }
        $sControllerTemplatePath = $sTemplate;

        if (!\F::File_IsLocalDir($sControllerTemplatePath)) {
            // If not absolute path then defines real path of template
            $aDelegates = \E::PluginManager()->GetDelegationChain('controller', $this->getActionClass());
            foreach ($aDelegates as $sAction) {
                if (preg_match('/^(Plugin([\w]+)_)?Action([\w]+)$/i', $sAction, $aMatches)) {
                    // for LS-compatibility
                    $sActionNameOriginal = $aMatches[3];
                    // New-style action templates
                    $sControllerName = strtolower($sActionNameOriginal);
                    $sTemplatePath = \E::PluginManager()->getDelegate('template', 'controllers/' . $sControllerName . '/controller.' . $sControllerName . '.' . $sTemplate);
                    $sControllerTemplatePath = $sTemplatePath;
                    if (!empty($aMatches[1])) {
                        $aPluginTemplateDirs = [\PluginManager::getTemplateDir($sAction)];
                        if (basename($aPluginTemplateDirs[0]) !== 'default') {
                            $aPluginTemplateDirs[] = dirname($aPluginTemplateDirs[0]) . '/default/';
                        }

                        if ($sTemplatePath = \F::File_Exists('tpls/' . $sTemplatePath, $aPluginTemplateDirs)) {
                            $sControllerTemplatePath = $sTemplatePath;
                            break;
                        }
                        if ($sTemplatePath = \F::File_Exists($sTemplatePath, $aPluginTemplateDirs)) {
                            $sControllerTemplatePath = $sTemplatePath;
                            break;
                        }

                    }
                }
            }
        }

        $this->sControllerTemplate = $sControllerTemplatePath;
    }

}

// EOF