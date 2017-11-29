<?php

class LangArray extends Component implements ArrayAccess {
    protected $_container = [];

    public function __construct($array = null) {
    }

    public function offsetExists($offset) {
    }

    public function offsetGet($offset) {
        return Engine::getInstance()->Lang_Get($offset);
    }

    public function offsetSet($offset, $value) {
    }

    public function offsetUnset($offset) {
    }
}

// EOF