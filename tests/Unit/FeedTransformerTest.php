<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Tests\Unit;

use DOMElement;
use GasConnect\RssCron\Rss\FeedParser;
use GasConnect\RssCron\Rss\FeedSelector;
use GasConnect\RssCron\Rss\FeedTransformer;
use GasConnect\RssCron\Rss\FetchedFeed;
use GasConnect\RssCron\Tests\Support\InMemoryLogger;
use PHPUnit\Framework\TestCase;

final class FeedTransformerTest extends TestCase
{
    public function testTransformCreatesAuthorWhenMissingAndCopiesTitleIntoIt(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Example</title>
    <link>https://example.com/feed</link>
    <description>Example</description>
    <item>
      <title>Created author</title>
      <guid>1</guid>
      <pubDate>Thu, 02 Apr 2026 12:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML;

        $parsedFeed = (new FeedParser())->parse(new FetchedFeed($xml, 200, 'application/rss+xml'));
        $selected = (new FeedSelector())->selectLatest($parsedFeed->items, 1);
        $logger = new InMemoryLogger();

        $transformed = (new FeedTransformer())->transform($selected, true, $logger);

        self::assertCount(1, $transformed);
        $author = $this->findDirectChild($transformed[0], 'author');
        self::assertInstanceOf(DOMElement::class, $author);
        self::assertSame('Created author', $author->textContent);
        self::assertSame('Created author', $this->findDirectChild($transformed[0], 'title')?->textContent);
    }

    public function testTransformSkipsBlankTitleWhenConfiguredToSkip(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Example</title>
    <link>https://example.com/feed</link>
    <description>Example</description>
    <item>
      <title>   </title>
      <author>Original</author>
      <guid>1</guid>
      <pubDate>Thu, 02 Apr 2026 12:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML;

        $parsedFeed = (new FeedParser())->parse(new FetchedFeed($xml, 200, 'application/rss+xml'));
        $selected = (new FeedSelector())->selectLatest($parsedFeed->items, 1);
        $logger = new InMemoryLogger();

        $transformed = (new FeedTransformer())->transform($selected, true, $logger);

        self::assertCount(0, $transformed);
        self::assertCount(1, array_filter(
            $logger->records,
            static fn (array $record): bool => $record['level'] === 'warning'
        ));
    }

    public function testTransformCanKeepBlankTitleAndWriteEmptyAuthorWhenConfigured(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Example</title>
    <link>https://example.com/feed</link>
    <description>Example</description>
    <item>
      <title></title>
      <author>Original</author>
      <guid>1</guid>
      <pubDate>Thu, 02 Apr 2026 12:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML;

        $parsedFeed = (new FeedParser())->parse(new FetchedFeed($xml, 200, 'application/rss+xml'));
        $selected = (new FeedSelector())->selectLatest($parsedFeed->items, 1);
        $logger = new InMemoryLogger();

        $transformed = (new FeedTransformer())->transform($selected, false, $logger);

        self::assertCount(1, $transformed);
        self::assertSame('', $this->findDirectChild($transformed[0], 'author')?->textContent);
    }

    private function findDirectChild(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }
}

