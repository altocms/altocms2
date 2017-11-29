<?php
/**
 * This file is part of the avadim\DbSimple package
 */

spl_autoload_register(function ($class) {
    $namespace = 'avadim\\DbSimple\\';
    if (0 === strpos($class, $namespace)) {
        include __DIR__ . '/src/' . str_replace($namespace, '/', $class) . '.php';
    }
});

// EOF