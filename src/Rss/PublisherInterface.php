<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Rss;

interface PublisherInterface
{
    public function publish(string $contents, string $outputPath): int;
}

