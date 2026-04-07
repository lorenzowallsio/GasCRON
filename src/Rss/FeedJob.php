<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Rss;

use GasConnect\RssCron\Config;
use GasConnect\RssCron\Exception\FeedValidationException;
use GasConnect\RssCron\Support\Logger;

final class FeedJob
{
    private FeedFetcherInterface $fetcher;
    private FeedParser $parser;
    private FeedSelector $selector;
    private FeedTransformer $transformer;
    private FeedGenerator $generator;
    private FeedValidator $validator;
    private PublisherInterface $publisher;

    public function __construct(
        ?FeedFetcherInterface $fetcher = null,
        ?FeedParser $parser = null,
        ?FeedSelector $selector = null,
        ?FeedTransformer $transformer = null,
        ?FeedGenerator $generator = null,
        ?FeedValidator $validator = null,
        ?PublisherInterface $publisher = null
    ) {
        $this->fetcher = $fetcher ?? new CurlFeedFetcher();
        $this->parser = $parser ?? new FeedParser();
        $this->selector = $selector ?? new FeedSelector();
        $this->transformer = $transformer ?? new FeedTransformer();
        $this->generator = $generator ?? new FeedGenerator();
        $this->validator = $validator ?? new FeedValidator();
        $this->publisher = $publisher ?? new AtomicFilePublisher();
    }

    public function run(Config $config, Logger $logger): JobResult
    {
        $startedAt = microtime(true);
        $logger->info('Starting RSS transform job.', [
            'source_url' => $config->sourceUrl,
            'output_path' => $config->outputPath,
            'public_feed_url' => $config->publicFeedUrl,
            'cron_schedule' => $config->cronSchedule,
            'timezone' => $config->timezone->getName(),
        ]);

        $fetchedFeed = $this->fetcher->fetch(
            url: $config->sourceUrl,
            timeoutSeconds: $config->fetchTimeoutSeconds,
            headers: $config->requestHeaders
        );

        $logger->info('Fetched source RSS feed.', [
            'http_status' => $fetchedFeed->statusCode,
            'content_type' => $fetchedFeed->contentType,
            'body_bytes' => strlen($fetchedFeed->body),
        ]);

        $parsedFeed = $this->parser->parse($fetchedFeed);
        $sourceItemCount = count($parsedFeed->items);

        if ($sourceItemCount === 0) {
            throw new FeedValidationException('Source feed contains no items. Keeping previous published feed unchanged.');
        }

        foreach ($parsedFeed->items as $item) {
            if ($item->rawPubDate === '' || $item->publishedAt === null) {
                $logger->warning('Item is missing a valid pubDate and will sort after dated items.', [
                    'item_index' => $item->originalIndex,
                    'guid' => $this->extractItemGuid($item),
                    'raw_pub_date' => $item->rawPubDate !== '' ? $item->rawPubDate : null,
                ]);
            }
        }

        $selectedItems = $this->selector->selectLatest($parsedFeed->items, 5);
        $transformedItems = $this->transformer->transform(
            $selectedItems,
            $config->skipItemsWithEmptyTitle,
            $logger
        );

        if ($transformedItems === []) {
            throw new FeedValidationException('No publishable items remain after transformation. Keeping previous published feed unchanged.');
        }

        $generatedXml = $this->generator->generate(
            $parsedFeed,
            $transformedItems,
            $config->publicFeedUrl,
            $config->channelTitleOverride
        );
        $this->validator->validate(
            $generatedXml,
            $config->publicFeedUrl,
            $config->channelTitleOverride
        );
        $writtenBytes = $this->publisher->publish($generatedXml, $config->outputPath);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $logger->info('RSS transform job completed successfully.', [
            'source_item_count' => $sourceItemCount,
            'selected_item_count' => count($selectedItems),
            'published_item_count' => count($transformedItems),
            'publish_target' => $config->outputPath,
            'duration_ms' => $durationMs,
            'written_bytes' => $writtenBytes,
        ]);

        return new JobResult(
            sourceItemCount: $sourceItemCount,
            selectedItemCount: count($selectedItems),
            publishedItemCount: count($transformedItems),
            writtenBytes: $writtenBytes
        );
    }

    private function extractItemGuid(ParsedItem $item): string
    {
        foreach ($item->element->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'guid') {
                return trim($child->textContent);
            }
        }

        return '';
    }
}
