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
 * @package engine.modules
 * @since 1.0
 */
class ModuleSkin extends Module {

    const SKIN_XML_FILE = 'skin.xml';

    protected $aSkins;

    public function init() {

    }

    /**
     * Load skin manifest from XML
     *
     * @param string $sSkin
     * @param string $sSkinDir
     *
     * @return string|bool
     */
    public function getSkinManifest($sSkin, $sSkinDir = null) {

        if (!$sSkinDir) {
            $sSkinDir = \C::get('path.skins.dir') . $sSkin . '/';
        }

        if (\F::File_Exists($sSkinDir . '/' . self::SKIN_XML_FILE)) {
            $sXmlFile = $sSkinDir . '/' . self::SKIN_XML_FILE;
        } else {
            $sXmlFile = $sSkinDir . '/settings/' . self::SKIN_XML_FILE;
        }
        if ($sXml =  \F::File_GetContents($sXmlFile)) {
            return $sXml;
        }
        return null;
    }

    /**
     * Returns array of skin entities
     *
     * @param   array   $aFilter    - array('type' => 'site'|'admin')
     * @return  ModuleSkin_EntitySkin[]
     */
    public function getSkinsList($aFilter = array()) {

        if (isset($aFilter['name']) && isset($this->aSkins[$aFilter['name']])) {
            return array($aFilter['name'] => $this->aSkins[$aFilter['name']]);
        }
        if (is_null($this->aSkins)) {
            $aSkinList = [];
            if (isset($aFilter['dir'])) {
                $sSkinsDir = $aFilter['dir'];
            } else {
                $sSkinsDir = \C::get('path.skins.dir');
            }
            $aList = glob($sSkinsDir . '*', GLOB_ONLYDIR);
            if ($aList) {
                if (!isset($aFilter['type'])) $aFilter['type'] = '';
                $sActiveSkin = \C::get('view.skin');
                foreach ($aList as $sSkinDir) {
                    $sSkin = basename($sSkinDir);
                    $aData = array(
                        'id' => $sSkin,
                        'dir' => $sSkinDir,
                    );
                    $oSkinEntity = \E::getEntity('Skin', $aData);
                    $oSkinEntity->SetIsActive($oSkinEntity->GetId() == $sActiveSkin);
                    $aSkinList[$oSkinEntity->GetId()] = $oSkinEntity;

                }
            }
            $this->aSkins = $aSkinList;
        }

        if (!$aFilter || empty($aFilter['type'])) {
            $aResult = $this->aSkins;
        } else {
            $aResult = [];
            foreach($this->aSkins as $sSkinName => $oSkinEntity) {
                if ($aFilter['type'] == $oSkinEntity->GetType()) {
                    $aResult[$sSkinName] = $oSkinEntity;
                }
            }
        }

        return $aResult;
    }

    /**
     * Returns array of skin names
     *
     * @param   string $sType
     *
     * @return  string[]
     */
    public function getSkinsArray($sType = null) {

        if ($sType) {
            $aFilter = array('type' => $sType);
        } else {
            $aFilter = [];
        }
        $aSkins = $this->GetSkinsList($aFilter);
        return array_keys($aSkins);
    }

    /**
     * Returns skin entity
     *
     * @param string $sName
     *
     * @return ModuleSkin_EntitySkin
     */
    public function getSkin($sName) {

        $aSkins = $this->GetSkinsList(array('name' => $sName));
        if (isset($aSkins[$sName])) {
            return $aSkins[$sName];
        }
        return null;
    }

    /**
     * Check the skin compatibility
     *
     * @param string      $sSkinName
     * @param string|null $sVersion
     * @param string|null $sOperator
     *
     * @return bool
     */
    public function SkinCompatible($sSkinName, $sVersion = null, $sOperator = null) {

        $oSkin = \E::Module('Skin')->GetSkin($sSkinName);
        return $oSkin->SkinCompatible($sVersion, $sOperator);
    }
}

// EOF