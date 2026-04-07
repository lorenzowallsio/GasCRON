<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Rss;

final class JobResult
{
    public function __construct(
        public readonly int $sourceItemCount,
        public readonly int $selectedItemCount,
        public readonly int $publishedItemCount,
        public readonly int $writtenBytes
    ) {
    }
}

