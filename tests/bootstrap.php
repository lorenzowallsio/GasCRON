<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$composerAutoload = $projectRoot . '/vendor/autoload.php';

if (is_file($composerAutoload)) {
    require $composerAutoload;
    return;
}

require $projectRoot . '/autoload.php';

spl_autoload_register(static function (string $class) use ($projectRoot): void {
    $prefix = 'GasConnect\\RssCron\\Tests\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $projectRoot . '/tests/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
