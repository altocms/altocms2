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

class PluginSphinx_ActionSphinx extends ActionPlugin 
{
    public function init() 
    {
    }

    protected function registerEvent() 
    {
        $this->addEvent('config', 'eventConfig');
    }

    public function eventConfig() 
    {
        $sFile = Plugin::GetPath(__CLASS__) . 'config/sphinx-src.conf';
        $sText = F::File_GetContents($sFile);

        $sPath = F::File_NormPath(C::get('plugin.sphinx.path') . '/');
        $sDescription = \E::Module('Lang')->get(
            'plugin.sphinx.conf_description',
            [
                'path'   => $sPath,
                'prefix' => C::get('plugin.sphinx.prefix')
            ]
        );
        $sDescription = preg_replace('/\s\s+/', ' ', str_replace("\n", "\n## ", $sDescription));
        $sTitle = \E::Module('Lang')->get('plugin.sphinx.conf_title');

        $aData = [
            '{{title}}'        => $sTitle,
            '{{description}}'  => $sDescription,
            '{{db_type}}'      => (C::get('db.params.type') === 'postgresql') ? 'pgsql' : 'mysql',
            '{{db_host}}'      => C::get('db.params.host'),
            '{{db_user}}'      => C::get('db.params.user'),
            '{{db_pass}}'      => C::get('db.params.pass'),
            '{{db_name}}'      => C::get('db.params.dbname'),
            '{{db_port}}'      => C::get('db.params.port'),
            '{{db_prefix}}'    => C::get('db.table.prefix'),
            '{{db_socket}}'    => C::get('plugin.sphinx.db_socket'),
            '{{spinx_prefix}}' => C::get('plugin.sphinx.prefix'),
            '{{spinx_path}}'   => $sPath,
        ];

        $sText = str_replace(array_keys($aData), array_values($aData), $sText);

        echo '<pre>';
        echo $sText;
        echo '</pre>';
        exit;
    }
    
}

// EOF