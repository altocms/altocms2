<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

F::IncludeLib('less.php/Less.php');

class ModuleLess extends Module
{
    /**
     * Инициализация модуля
     *
     */
    public function init()
    {
    }

    /**
     * Компилирует файл less и возвращает текст css-файла
     *
     * @param $aFile
     * @param $sCacheDir
     * @param $sMapPath
     * @param $aParams
     * @param $bCompress
     * @return string
     */
    public function compileFile($aFile, $sCacheDir, $sMapPath, $aParams, $bCompress)
    {
        if (!($sMapPath && $sCacheDir && $aFile)) {
            return '';
        }

        try {
            $options = [
                'sourceMap'        => TRUE,
                'sourceMapWriteTo' => $sMapPath,
                'sourceMapURL'     => \E::Module('ViewerAsset')->assetFileUrl($sMapPath),
                'cache_dir'        => $sCacheDir
            ];

            if ($bCompress) {
                $options = array_merge($options, ['compress' => TRUE]);
            }


            $sCssFileName = Less_Cache::Get(
                $aFile,
                $options,
                $aParams
            );

            return file_get_contents($sCacheDir . $sCssFileName);

        } catch (Exception $e) {
            \E::Module('Message')->addErrorSingle($e->getMessage());
        }

        return '';

    }

}