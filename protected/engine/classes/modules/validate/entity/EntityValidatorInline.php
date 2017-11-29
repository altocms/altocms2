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
 * Валидатор для кастомных методов объектов
 * Валидация происходит через метод внешнего объекта
 *
 * @package engine.modules.validate
 * @since   1.0
 */
class ModuleValidate_EntityValidatorInline extends ModuleValidate_EntityValidator {
    /**
     * Метод объекта для валидации, в него передаются параметры: $sValue и $aParam
     *
     * @var string
     */
    public $method;
    /**
     * Объект у которого будет вызван метод валидации, дляя сущности - это сам объект сущности
     *
     * @var Component object
     */
    public $object;
    /**
     * Список параметров для передачи в метод валидации
     *
     * @var array
     */
    public $params;

    /**
     * Запуск валидации
     *
     * @param mixed $sValue    Данные для валидации
     *
     * @return bool|string
     */
    public function validate($sValue) {

        $sMethod = $this->method;
        return $this->object->$sMethod($sValue, $this->params);
    }
}

// EOF