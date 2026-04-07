<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Tests\Unit;

use GasConnect\RssCron\Rss\FeedParser;
use GasConnect\RssCron\Rss\FeedSelector;
use GasConnect\RssCron\Rss\FetchedFeed;
use GasConnect\RssCron\Tests\Support\FixtureLoader;
use PHPUnit\Framework\TestCase;

final class FeedSelectorTest extends TestCase
{
    public function testSelectLatestSortsByPubDateDescendingEvenWhenSourceOrderIsUnsorted(): void
    {
        $parser = new FeedParser();
        $parsedFeed = $parser->parse(new FetchedFeed(
            FixtureLoader::load('source-feed.xml'),
            200,
            'application/rss+xml'
        ));

        $selected = (new FeedSelector())->selectLatest($parsedFeed->items, 5);

        self::assertSame([
            'First published',
            'Second published',
            'Third published',
            'Fourth published',
            'Fifth published',
        ], array_map(static fn ($item) => $item->title, $selected));
    }

    public function testSelectLatestPlacesUndatedItemsAfterDatedItems(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Example</title>
    <link>https://example.com/feed</link>
    <description>Example</description>
    <item>
      <title>No date</title>
      <guid>1</guid>
    </item>
    <item>
      <title>With date</title>
      <guid>2</guid>
      <pubDate>Thu, 02 Apr 2026 12:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML;

        $parser = new FeedParser();
        $parsedFeed = $parser->parse(new FetchedFeed($xml, 200, 'application/rss+xml'));

        $selected = (new FeedSelector())->selectLatest($parsedFeed->items, 2);

        self::assertSame(['With date', 'No date'], array_map(static fn ($item) => $item->title, $selected));
    }
}

