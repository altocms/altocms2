<?php

namespace avadim\DbSimple;

/**
 * Класс для хранения подзапроса - результата выполнения функции
 * DbSimple_Generic_Database::subquery
 *
 */
class SubQuery
{
    private $query=array();

    public function __construct(array $q)
    {
        $this->query = $q;
    }

    /**
     * Возвращает сам запрос и добавляет плейсхолдеры в массив переданный по ссылке
     *
     * @param &array|null - ссылка на массив плейсхолдеров
     *
     * @return string
     */
    public function get(&$ph)
    {
        if ($ph !== null) {
            $ph = array_merge($ph, array_slice($this->query,1,null,true));
        }
        return $this->query[0];
    }
}

// EOF