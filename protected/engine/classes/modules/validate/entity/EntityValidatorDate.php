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
 * CDateValidator class file.
 *
 * @author    Qiang Xue <qiang.xue@gmail.com>
 * @link      http://www.yiiframework.com/
 * @copyright Copyright &copy; 2008-2011 Yii Software LLC
 * @license   http://www.yiiframework.com/license/
 */

/**
 * Валидатор даты
 * Валидатор использует внешний класс DateTimeParser
 *
 * @package engine.modules.validate
 * @since   1.0
 */
class ModuleValidate_EntityValidatorDate extends ModuleValidate_EntityValidator {
    /**
     * Формат допустимой даты, может содержать список форматов в массиве
     *
     * @var string|array
     */
    public $format = 'dd-MM-yyyy';
    /**
     * Допускать или нет пустое значение
     *
     * @var bool
     */
    public $allowEmpty = true;

    /**
     * Запуск валидации
     *
     * @param mixed $sValue    Данные для валидации
     *
     * @return bool|string
     */
    public function validate($sValue) {

        if (is_array($sValue)) {
            return $this->getMessage(\E::Module('Lang')->get('validate_date_format_invalid', null, false), 'msg');
        }
        if ($this->allowEmpty && $this->isEmpty($sValue)) {
            return true;
        }

         \F::IncludeLib('DateTime/DateTimeParser.php');

        $aFormats = is_string($this->format) ? array($this->format) : $this->format;
        $bValid = false;
        foreach ($aFormats as $sFormat) {
            $iTimestamp = DateTimeParser::parse(
                $sValue, $sFormat, array('month' => 1, 'day' => 1, 'hour' => 0, 'minute' => 0, 'second' => 0)
            );
            if ($iTimestamp !== false) {
                $bValid = true;
                break;
            }
        }

        if (!$bValid) {
            return $this->getMessage(\E::Module('Lang')->get('validate_date_format_invalid', null, false), 'msg');
        }
        return true;
    }
}

// EOF