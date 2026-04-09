<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Tests\Integration;

use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMXPath;
use GasConnect\RssCron\Config;
use GasConnect\RssCron\Exception\FeedParseException;
use GasConnect\RssCron\Rss\AtomicFilePublisher;
use GasConnect\RssCron\Rss\FeedJob;
use GasConnect\RssCron\Rss\FetchedFeed;
use GasConnect\RssCron\Tests\Support\FakeFeedFetcher;
use GasConnect\RssCron\Tests\Support\FixtureLoader;
use GasConnect\RssCron\Tests\Support\InMemoryLogger;
use PHPUnit\Framework\TestCase;

final class FeedJobTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/rss-feed-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->temporaryDirectory . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        @rmdir($this->temporaryDirectory);
    }

    public function testJobGeneratesValidWallsIoCompatibleFeed(): void
    {
        $fixture = FixtureLoader::load('source-feed.xml');
        $outputPath = $this->temporaryDirectory . '/feed.xml';
        $config = $this->buildConfig($outputPath);
        $logger = new InMemoryLogger();
        $job = new FeedJob(
            fetcher: new FakeFeedFetcher(
                static fn (string $_url, int $_timeout, array $_headers): FetchedFeed
                    => new FetchedFeed($fixture, 200, 'application/rss+xml')
            ),
            publisher: new AtomicFilePublisher()
        );

        $result = $job->run($config, $logger);

        self::assertSame(7, $result->sourceItemCount);
        self::assertSame(5, $result->selectedItemCount);
        self::assertSame(5, $result->publishedItemCount);
        self::assertFileExists($outputPath);

        $document = new DOMDocument();
        $document->load($outputPath);

        $items = $document->getElementsByTagName('item');
        self::assertCount(5, $items);

        $expectedCreators = [
            'First published',
            'Second published',
            'Third published',
            'Fourth published',
            'Fifth published',
        ];

        $firstItem = $items->item(0);
        self::assertInstanceOf(DOMElement::class, $firstItem);
        self::assertSame('First item description.', $this->findDirectChildText($firstItem, 'title'));
        self::assertSame('First published', $this->findDirectChildText($firstItem, 'creator'));
        self::assertSame('', $this->findDirectChildText($firstItem, 'author'));
        self::assertSame('First item description.', $this->findDirectChildText($firstItem, 'description'));

        foreach ($items as $index => $item) {
            self::assertInstanceOf(DOMElement::class, $item);
            self::assertSame(
                $this->findDirectChildText($item, 'description'),
                $this->findDirectChildText($item, 'title')
            );
            self::assertSame('', $this->findDirectChildText($item, 'author'));
            self::assertSame(
                $expectedCreators[$index],
                $this->findDirectChildText($item, 'creator')
            );
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('atom', 'http://www.w3.org/2005/Atom');
        $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        self::assertSame('Gasconnect RSS', $xpath->evaluate('string(/rss/channel/title)'));

        $selfLink = $xpath->query('/rss/channel/atom:link[@rel="self"]/@href');
        self::assertNotFalse($selfLink);
        self::assertSame('https://example.com/feeds/feed.xml', $selfLink->item(0)?->nodeValue);

        self::assertGreaterThan(0, $xpath->query('/rss/channel/item/enclosure')->length);
        self::assertGreaterThan(0, $xpath->query('/rss/channel/item/source')->length);
        self::assertCount(5, $xpath->query('/rss/channel/item/dc:creator'));
        self::assertCount(0, $xpath->query('/rss/channel/item/author'));
        self::assertGreaterThan(0, $xpath->query('/rss/channel/item/dc:publisher')->length);

        $xml = file_get_contents($outputPath);
        self::assertIsString($xml);
        self::assertStringNotContainsString('<author>', $xml);
        self::assertStringContainsString(
            '<dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/">First published</dc:creator>',
            $xml
        );
    }

    public function testJobKeepsPreviousOutputWhenParsingFails(): void
    {
        $outputPath = $this->temporaryDirectory . '/feed.xml';
        file_put_contents($outputPath, 'last-known-good');

        $config = $this->buildConfig($outputPath);
        $logger = new InMemoryLogger();
        $job = new FeedJob(
            fetcher: new FakeFeedFetcher(
                static fn (string $_url, int $_timeout, array $_headers): FetchedFeed
                    => new FetchedFeed('<rss><channel><item></rss>', 200, 'application/rss+xml')
            ),
            publisher: new AtomicFilePublisher()
        );

        $this->expectException(FeedParseException::class);
        try {
            $job->run($config, $logger);
        } finally {
            self::assertSame('last-known-good', file_get_contents($outputPath));
        }
    }

    private function buildConfig(string $outputPath): Config
    {
        return new Config(
            sourceUrl: 'https://customers.pressrelations.de/apps/nrx/bff/export/media_review/d70a6efc-7373-4f16-b6c5-7ec575b843f5',
            outputPath: $outputPath,
            publicFeedUrl: 'https://example.com/feeds/feed.xml',
            channelTitleOverride: 'Gasconnect RSS',
            timezone: new DateTimeZone('Europe/Rome'),
            cronSchedule: '37 7 * * *; 11 13 * * *',
            fetchTimeoutSeconds: 15,
            skipItemsWithEmptyTitle: true,
            requestHeaders: [],
            logLevel: 'info'
        );
    }

    private function findDirectChildText(DOMElement $parent, string $localName): string
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return trim($child->textContent);
            }
        }

        return '';
    }
}
