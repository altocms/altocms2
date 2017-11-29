<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

namespace alto\engine\generic;

/**
 * Абстрактный класс виджета
 * Это те блоки которые обрабатывают шаблоны Smarty перед выводом(например блок "Облако тегов")
 *
 * @package engine
 * @since 1.0
 */
abstract class Widget extends Component
{
    /**
     * Список параметров блока
     *
     * @var array
     */
    protected $aParams = [];

    /**
     * При создании блока передаем в него параметры
     *
     * @param array $aParams Список параметров блока
     */
    public function __construct($aParams)
    {
        parent::__construct();
        $this->aParams = $aParams;
    }

    /**
     * Возвращает параметр по имени
     *
     * @param   string  $sName      - Имя параметра
     * @param   mixed   $xDefault   - Значение параметра по умолчанию
     *
     * @return  mixed
     */
    protected function getParam($sName, $xDefault = null)
    {
        if (isset($this->aParams[$sName])) {
            return $this->aParams[$sName];
        }
        return $xDefault;
    }

    /**
     * Return widget params
     *
     * @return array
     */
    protected function getParams()
    {
        return $this->aParams;
    }

    /**
     * Метод запуска обработки блока.
     * Его необходимо определять в конкретном блоге.
     *
     * @abstract
     */
    abstract public function exec();

    /**
     * Fetch widget template
     *
     * @param string $sTemplate
     * @param array  $aVars
     *
     * @return string
     */
    protected function fetch($sTemplate, $aVars = [])
    {
        $aVars = \F::Array_Merge(['aWidgetParams' => $this->aParams], $aVars);
        return \E::Module('Viewer')->fetchWidget($sTemplate, $aVars);
    }

}

// EOF