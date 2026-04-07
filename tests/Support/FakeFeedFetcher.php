<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Tests\Support;

use GasConnect\RssCron\Rss\FeedFetcherInterface;
use GasConnect\RssCron\Rss\FetchedFeed;

final class FakeFeedFetcher implements FeedFetcherInterface
{
    /**
     * @var \Closure(string, int, array<string, string>): FetchedFeed
     */
    private \Closure $callback;

    /**
     * @param \Closure(string, int, array<string, string>): FetchedFeed $callback
     */
    public function __construct(\Closure $callback)
    {
        $this->callback = $callback;
    }

    public function fetch(string $url, int $timeoutSeconds, array $headers = []): FetchedFeed
    {
        return ($this->callback)($url, $timeoutSeconds, $headers);
    }
}

