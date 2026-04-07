<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Rss;

use DOMDocument;
use DOMElement;
use DOMXPath;
use GasConnect\RssCron\Exception\FeedValidationException;

final class FeedValidator
{
    private const ATOM_NAMESPACE = 'http://www.w3.org/2005/Atom';

    public function validate(string $xml, string $publicFeedUrl): void
    {
        if (trim($xml) === '') {
            throw new FeedValidationException('Generated RSS XML is empty.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
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

            throw new FeedValidationException(
                'Generated RSS XML is malformed' . ($messages !== [] ? ': ' . implode('; ', $messages) : '.')
            );
        }

        $rssElement = $document->documentElement;
        if (!$rssElement instanceof DOMElement || $rssElement->localName !== 'rss') {
            throw new FeedValidationException('Generated XML is not an RSS document.');
        }

        $channel = $rssElement->getElementsByTagName('channel')->item(0);
        if (!$channel instanceof DOMElement) {
            throw new FeedValidationException('Generated RSS XML is missing a channel element.');
        }

        $items = [];
        foreach ($channel->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'item') {
                $items[] = $child;
            }
        }

        if (count($items) > 5) {
            throw new FeedValidationException('Generated RSS XML contains more than 5 items.');
        }

        foreach ($items as $index => $item) {
            $title = $this->findFirstChildText($item, 'title');
            $description = $this->findFirstChildText($item, 'description');
            $author = $this->findFirstChildText($item, 'author');

            if ($title !== $description) {
                throw new FeedValidationException(
                    sprintf('Generated item %d has title/description mismatch.', $index + 1)
                );
            }

            if ($author === '') {
                throw new FeedValidationException(
                    sprintf('Generated item %d is missing an author value.', $index + 1)
                );
            }
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('atom', self::ATOM_NAMESPACE);
        $selfLinks = $xpath->query('/rss/channel/atom:link[@rel="self"]');

        if ($selfLinks === false || $selfLinks->length === 0) {
            throw new FeedValidationException('Generated RSS XML is missing channel atom:link rel="self".');
        }

        $selfLink = $selfLinks->item(0);
        if (!$selfLink instanceof DOMElement || $selfLink->getAttribute('href') !== $publicFeedUrl) {
            throw new FeedValidationException('Generated RSS XML has the wrong public atom:link rel="self" URL.');
        }
    }

    private function findFirstChildText(DOMElement $parent, string $localName): string
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return trim($child->textContent);
            }
        }

        return '';
    }
}
