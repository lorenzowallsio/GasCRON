<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Rss;

use DOMElement;
use GasConnect\RssCron\Support\Logger;

final class FeedTransformer
{
    /**
     * @param list<ParsedItem> $items
     * @return list<DOMElement>
     */
    public function transform(array $items, bool $skipItemsWithEmptyTitle, Logger $logger): array
    {
        $transformed = [];

        foreach ($items as $item) {
            $clone = $item->element->cloneNode(true);
            if (!$clone instanceof DOMElement) {
                continue;
            }

            $titleElement = $this->findFirstDirectChild($clone, 'title');
            $title = $titleElement === null ? '' : trim($titleElement->textContent);

            if ($title === '' && $skipItemsWithEmptyTitle) {
                $logger->warning('Skipping item with empty or missing title.', [
                    'item_index' => $item->originalIndex,
                    'guid' => $this->findFirstChildText($clone, 'guid'),
                ]);

                continue;
            }

            $authorElement = $this->findFirstDirectChild($clone, 'author');
            if ($authorElement === null) {
                $authorElement = $clone->ownerDocument?->createElement('author');

                if ($authorElement === null) {
                    continue;
                }

                if ($titleElement !== null && $titleElement->nextSibling !== null) {
                    $clone->insertBefore($authorElement, $titleElement->nextSibling);
                } else {
                    $clone->appendChild($authorElement);
                }
            }

            while ($authorElement->firstChild !== null) {
                $authorElement->removeChild($authorElement->firstChild);
            }

            $authorElement->appendChild($clone->ownerDocument->createTextNode($title));
            $transformed[] = $clone;
        }

        return $transformed;
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

