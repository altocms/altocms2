<?php
/**
 * This file is part of the avadim\Chrono package
 * https://github.com/aVadim483/Chrono
 */

spl_autoload_register(function ($class) {
    $namespace = 'avadim\\Chrono\\';
    if (0 === strpos($class, $namespace)) {
        include __DIR__ . '/' . str_replace($namespace, '/', $class) . '.php';
    }
});

// EOF