<?php

// Load Monolog from composer global install
$globalAutoload = getenv('HOME') . '/.composer/vendor/autoload.php';
if (file_exists($globalAutoload)) {
    require_once $globalAutoload;
}

// Autoload NoFraud module classes
spl_autoload_register(function ($class) {
    $prefix = 'NoFraud\\Connect\\';
    $baseDir = dirname(__DIR__) . '/';

    if (strncmp($prefix, $class, strlen($prefix)) === 0) {
        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

// Autoload Magento framework stubs for unit testing
spl_autoload_register(function ($class) {
    $prefix = 'Magento\\';
    $stubDir = __DIR__ . '/Stubs/';

    if (strncmp($prefix, $class, strlen($prefix)) === 0) {
        $file = $stubDir . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});
