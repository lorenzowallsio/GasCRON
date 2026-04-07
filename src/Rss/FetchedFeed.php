<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Rss;

final class FetchedFeed
{
    public function __construct(
        public readonly string $body,
        public readonly int $statusCode,
        public readonly ?string $contentType
    ) {
    }
}

