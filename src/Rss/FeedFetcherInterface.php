<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Rss;

interface FeedFetcherInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function fetch(string $url, int $timeoutSeconds, array $headers = []): FetchedFeed;
}

