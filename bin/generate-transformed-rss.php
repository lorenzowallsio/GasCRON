#!/usr/bin/env php
<?php

declare(strict_types=1);

use GasConnect\RssCron\Config;
use GasConnect\RssCron\Rss\FeedJob;
use GasConnect\RssCron\Support\ConsoleLogger;
use GasConnect\RssCron\Support\EnvironmentLoader;

$projectRoot = dirname(__DIR__);
$composerAutoload = $projectRoot . '/vendor/autoload.php';

require is_file($composerAutoload) ? $composerAutoload : $projectRoot . '/autoload.php';

EnvironmentLoader::load($projectRoot . '/.env');

$runId = bin2hex(random_bytes(8));
$logger = new ConsoleLogger('info', ['run_id' => $runId]);

try {
    $config = Config::fromEnvironment($_ENV + $_SERVER);
    $logger = new ConsoleLogger($config->logLevel, ['run_id' => $runId]);

    $job = new FeedJob();
    $job->run($config, $logger);

    exit(0);
} catch (\Throwable $exception) {
    $logger->error('RSS transform job failed.', [
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
    ]);

    exit(1);
}

