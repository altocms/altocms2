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
 * Модуль для работы с машиной полнотекстового поиска Sphinx
 *
 * @package modules.sphinx
 * @since   1.0
 */
class PluginSphinx_ModuleSphinx extends Module 
{
    /**
     * Объект сфинкса
     *
     * @var \NilPortugues\Sphinx\SphinxClient
     */
    protected $oSphinx = null;

    /**
     * Инициализация
     *
     */
    public function init() 
    {
        // * Получаем объект Сфинкса(из Сфинкс АПИ)
        $this->oSphinx = new \NilPortugues\Sphinx\SphinxClient();
        $sHost = C::get('plugin.sphinx.host');
        $nPort = 0;
        if (strpos($sHost, ':')) {
            list($sHost, $nPort) = explode(':', $sHost);
        }
        // * Подключаемся
        $this->oSphinx->setServer($sHost, (int)$nPort);
        $sError = $this->getLastError();
        if ($sError) {
            $sError .= "\nhost:$sHost";
            $this->logError($sError);
            return false;
        }

        // * Устанавливаем тип сортировки
        $this->oSphinx->setSortMode(SPH_SORT_EXTENDED, "@weight DESC, @id DESC");
    }

    /**
     * Возвращает число найденых элементов в зависимоти от их типа
     *
     * @param string $sTerms           Поисковый запрос
     * @param string $sObjType         Тип поиска
     * @param array  $aExtraFilters    Список фильтров
     *
     * @return int
     */
    public function getNumResultsByType($sTerms, $sObjType = 'topics', $aExtraFilters) 
    {
        $aResults = $this->findContent($sTerms, $sObjType, 1, 1, $aExtraFilters);
        return $aResults['total_found'];
    }

    /**
     * Непосредственно сам поиск
     *
     * @param string $sQuery           Поисковый запрос
     * @param string $sObjType         Тип поиска
     * @param int    $iOffset          Сдвиг элементов
     * @param int    $iLimit           Количество элементов
     * @param array  $aExtraFilters    Список фильтров
     *
     * @return array|bool
     */
    public function findContent($sQuery, $sObjType, $iOffset, $iLimit, $aExtraFilters) 
    {
        // * используем кеширование при поиске
        $sExtraFilters = serialize($aExtraFilters);
        $sCacheKey = C::get('plugin.sphinx.prefix') . "searchResult_{$sObjType}_{$sQuery}_{$iOffset}_{$iLimit}_{$sExtraFilters}";
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {

            // * Параметры поиска
            $this->oSphinx->setMatchMode(SPH_MATCH_ALL);
            $this->oSphinx->setLimits($iOffset, $iLimit, 1000);

            // * Устанавливаем атрибуты поиска
            $this->oSphinx->resetFilters();
            if (null !== $aExtraFilters) {
                foreach ($aExtraFilters AS $sAttributeName => $xAttributeValue) {
                    $this->oSphinx->setFilter($sAttributeName, (is_array($xAttributeValue)) ? $xAttributeValue : [$xAttributeValue]);
                }
            }

            // * Ищем
            $sIndex = C::get('plugin.sphinx.prefix') . $sObjType . 'Index';
            $data = $this->oSphinx->query($sQuery, $sIndex);
            if (!is_array($data)) {
                // Если false, то, скорее всего, ошибка и ее пишем в лог
                $sError = $this->getLastError();
                if ($sError) {
                    $sError .= "\nquery:$sQuery\nindex:$sIndex";
                    if ($aExtraFilters) {
                        $sError .= "\nfilters:";
                        foreach ($aExtraFilters as $sAttributeName => $sAttributeValue) {
                            $sError .= $sAttributeName . '=(' . (is_array($sAttributeValue) ? implode(',', $sAttributeValue) : $sAttributeValue) . ')';
                        }
                    }
                    $this->logError($sError);
                }
                return false;
            }
            /**
             * Если результатов нет, то и в кеш писать не стоит...
             * хотя тут момент спорный
             */
            if ($data['total'] > 0) {
                E::Module('Cache')->Set($data, $sCacheKey, array(), 60 * 15);
            }
        }
        return $data;
    }

    /**
     * Получить ошибку при последнем обращении к поиску
     *
     * @return string
     */
    public function getLastError()
    {
        return mb_convert_encoding($this->oSphinx->getLastError(), 'UTF-8');
    }

    /**
     * Получаем сниппеты(превью найденых элементов)
     *
     * @param string $sText           Текст
     * @param string $sIndex          Название индекса
     * @param string $sTerms          Поисковый запрос
     * @param string $before_match    Добавляемый текст перед ключом
     * @param string $after_match     Добавляемый текст после ключа
     *
     * @return array
     */
    public function getSnippet($sText, $sIndex, $sTerms, $before_match, $after_match)
    {
        $aReturn = $this->oSphinx->buildExcerpts(
            [$sText],
            C::get('plugin.sphinx.prefix') . $sIndex . 'Index', $sTerms,
            [
                'before_match' => $before_match,
                'after_match'  => $after_match,
            ]
        );
        return $aReturn[0];
    }

}

// EOF