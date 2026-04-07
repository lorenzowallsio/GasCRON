<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Rss;

use GasConnect\RssCron\Exception\FeedFetchException;

final class CurlFeedFetcher implements FeedFetcherInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function fetch(string $url, int $timeoutSeconds, array $headers = []): FetchedFeed
    {
        $curl = curl_init($url);

        if ($curl === false) {
            throw new FeedFetchException('Could not initialize cURL.');
        }

        $headerLines = [
            'Accept: application/rss+xml, application/xml, text/xml;q=0.9, */*;q=0.1',
            'User-Agent: GasConnectRssCron/1.0',
        ];

        foreach ($headers as $name => $value) {
            $headerLines[] = sprintf('%s: %s', $name, $value);
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min($timeoutSeconds, 10),
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_HEADER => true,
            CURLOPT_ENCODING => '',
        ]);

        $response = curl_exec($curl);

        if ($response === false) {
            $message = curl_error($curl) ?: 'Unknown cURL error.';

            throw new FeedFetchException(sprintf('Failed to fetch RSS feed: %s', $message));
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE) ?: null;

        $body = substr($response, $headerSize);
        $body = is_string($body) ? $body : '';

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new FeedFetchException(sprintf('Unexpected HTTP status %d when fetching RSS feed.', $statusCode));
        }

        if (trim($body) === '') {
            throw new FeedFetchException('Fetched RSS feed body is empty.');
        }

        $looksLikeXml = str_contains(strtolower((string) $contentType), 'xml') || str_starts_with(ltrim($body), '<');
        if (!$looksLikeXml) {
            throw new FeedFetchException('Fetched response does not appear to be XML.');
        }

        return new FetchedFeed(
            body: $body,
            statusCode: $statusCode,
            contentType: $contentType
        );
    }
}
