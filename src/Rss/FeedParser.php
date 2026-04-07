<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Rss;

use DOMDocument;
use DOMElement;
use GasConnect\RssCron\Exception\FeedParseException;

final class FeedParser
{
    public function parse(FetchedFeed $fetchedFeed): ParsedFeed
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;
        $document->resolveExternals = false;
        $document->substituteEntities = false;

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $loaded = $document->loadXML($fetchedFeed->body, LIBXML_NONET | LIBXML_NOBLANKS);
        } finally {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }

        if (!$loaded) {
            $messages = array_map(
                static fn (\LibXMLError $error): string => trim($error->message),
                $errors
            );

            throw new FeedParseException(
                'Failed to parse source RSS XML' . ($messages !== [] ? ': ' . implode('; ', $messages) : '.')
            );
        }

        $rssElement = $document->documentElement;
        if (!$rssElement instanceof DOMElement || $rssElement->localName !== 'rss') {
            throw new FeedParseException('Expected an RSS document with an <rss> root element.');
        }

        $channelElement = $this->findFirstDirectChild($rssElement, 'channel');
        if ($channelElement === null) {
            throw new FeedParseException('Expected an RSS <channel> element.');
        }

        $items = [];
        $index = 0;
        foreach ($channelElement->childNodes as $child) {
            if (!$child instanceof DOMElement || $child->localName !== 'item') {
                continue;
            }

            $index++;
            $rawPubDate = $this->findFirstChildText($child, 'pubDate');
            $publishedAt = $rawPubDate === '' ? null : $this->parseDate($rawPubDate);

            $items[] = new ParsedItem(
                element: $child,
                originalIndex: $index,
                publishedAt: $publishedAt,
                rawPubDate: $rawPubDate,
                title: $this->findFirstChildText($child, 'title'),
                author: $this->findFirstChildText($child, 'author')
            );
        }

        return new ParsedFeed(
            document: $document,
            rssElement: $rssElement,
            channelElement: $channelElement,
            items: $items
        );
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable(trim($value));
        } catch (\Throwable) {
            return null;
        }
    }

    private function findFirstDirectChild(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function findFirstChildText(DOMElement $parent, string $localName): string
    {
        $child = $this->findFirstDirectChild($parent, $localName);

        return $child === null ? '' : trim($child->textContent);
    }
}

