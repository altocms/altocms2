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
 * Регистрация хука для вывода статистики производительности
 *
 * @package hooks
 * @since   1.0
 */
class HookStatisticsPerformance extends Hook
{
    protected $bShown = false;

    /**
     * Регистрируем хуки
     */
    public function registerHook()
    {
        if (\F::ajaxRequest()) {
            return;
        }

        $xShowStats = \C::get('general.show.stats');

        // if is null then show to admins only
        if (($xShowStats === true) || (null === $xShowStats && E::isAdmin()) || (is_array($xShowStats) && in_array(\E::userId(), $xShowStats)))  {
            $xShowStats = R::GetIsShowStats();
        } else {
            $xShowStats = false;
        }
        if ($xShowStats) {
            $this->addHandler('template_layout_body_end', [$this, 'statistics'], -1000);
        }
    }

    /**
     * Обработка хука перед закрывающим тегом body
     *
     * @return string
     */
    public function statistics()
    {
        if ($this->bShown) {
            return '';
        }
        $sTemplate = 'commons/common.statistics_performance.tpl';

        // * Получаем статистику по БД, кешу и проч.
        $aStats = \E::getStats();
        $aStats['cache']['mode'] = (\C::get('sys.cache.use') ? \C::get('sys.cache.type') : 'off');
        $aStats['cache']['time'] = round($aStats['cache']['time'], 5);
        $aStats['memory']['limit'] = F::MemSizeFormat(\F::MemSize2Int(ini_get('memory_limit')), 3);
        $aStats['memory']['usage'] = F::MemSizeFormat(memory_get_usage(), 3);
        $aStats['memory']['peak'] = F::MemSizeFormat(memory_get_peak_usage(true), 3);
        $aStats['viewer']['count'] = ModuleViewer::getRenderCount();
        $aStats['viewer']['time'] = round(ModuleViewer::getRenderTime(), 3);
        $aStats['viewer']['preproc'] = round(ModuleViewer::getPreProcessingTime(), 3);
        $aStats['viewer']['total'] = round(ModuleViewer::getTotalTime(), 3);

        \E::Module('Viewer')->assign('aStatsPerformance', $aStats);

        // * В ответ рендерим шаблон статистики
        if (!\E::Module('Viewer')->templateExists($sTemplate)) {
            $sSkin = \C::get('view.skin', \C::LEVEL_CUSTOM);
            $sTemplate = \C::get('path.skins.dir') . $sSkin . '/tpls/' . $sTemplate;
        }
        if (\E::Module('Viewer')->templateExists($sTemplate)) {
            $this->bShown = true;
            return \E::Module('Viewer')->fetch($sTemplate);
        }

        return '';
    }
}

// EOF