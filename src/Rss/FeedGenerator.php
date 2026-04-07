<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Rss;

use DOMDocument;
use DOMElement;

final class FeedGenerator
{
    private const ATOM_NAMESPACE = 'http://www.w3.org/2005/Atom';

    /**
     * @param list<DOMElement> $items
     */
    public function generate(ParsedFeed $parsedFeed, array $items, string $publicFeedUrl): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;

        $rss = $document->createElement('rss');
        $document->appendChild($rss);
        $rss->setAttribute('version', $parsedFeed->rssElement->getAttribute('version') ?: '2.0');

        if ($parsedFeed->rssElement->hasAttributes()) {
            foreach ($parsedFeed->rssElement->attributes as $attribute) {
                if ($attribute->name === 'version') {
                    continue;
                }

                if ($attribute->prefix === 'xmlns' || $attribute->name === 'xmlns') {
                    $rss->setAttributeNS(
                        'http://www.w3.org/2000/xmlns/',
                        $attribute->name,
                        $attribute->value
                    );
                    continue;
                }

                if ($attribute->namespaceURI !== null) {
                    $rss->setAttributeNS($attribute->namespaceURI, $attribute->nodeName, $attribute->value);
                    continue;
                }

                $rss->setAttribute($attribute->name, $attribute->value);
            }
        }

        if (!$rss->hasAttribute('xmlns:atom')) {
            $rss->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', self::ATOM_NAMESPACE);
        }

        $channel = $document->createElement('channel');
        $rss->appendChild($channel);

        $selfLinkRewritten = false;
        foreach ($parsedFeed->channelElement->childNodes as $child) {
            if (!$child instanceof DOMElement || $child->localName === 'item') {
                continue;
            }

            $copy = $document->importNode($child, true);
            if ($copy instanceof DOMElement
                && $copy->namespaceURI === self::ATOM_NAMESPACE
                && $copy->localName === 'link'
                && strtolower($copy->getAttribute('rel')) === 'self'
            ) {
                $copy->setAttribute('href', $publicFeedUrl);
                $selfLinkRewritten = true;
            }

            if ($copy !== false) {
                $channel->appendChild($copy);
            }
        }

        if (!$selfLinkRewritten) {
            $atomSelfLink = $document->createElementNS(self::ATOM_NAMESPACE, 'atom:link');
            $atomSelfLink->setAttribute('href', $publicFeedUrl);
            $atomSelfLink->setAttribute('rel', 'self');
            $atomSelfLink->setAttribute('type', 'application/rss+xml');
            $channel->appendChild($atomSelfLink);
        }

        foreach ($items as $item) {
            $copy = $document->importNode($item, true);
            if ($copy !== false) {
                $channel->appendChild($copy);
            }
        }

        return $document->saveXML() ?: '';
    }
}
