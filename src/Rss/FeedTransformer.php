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
            $originalTitle = $titleElement === null ? '' : trim($titleElement->textContent);
            $description = $this->findFirstChildText($clone, 'description');

            if ($description === '' && $skipItemsWithEmptyTitle) {
                $logger->warning('Skipping item with empty or missing description because the transformed title would be empty.', [
                    'item_index' => $item->originalIndex,
                    'guid' => $this->findFirstChildText($clone, 'guid'),
                ]);

                continue;
            }

            if ($titleElement === null) {
                $titleElement = $clone->ownerDocument?->createElement('title');

                if ($titleElement === null) {
                    continue;
                }

                if ($clone->firstChild !== null) {
                    $clone->insertBefore($titleElement, $clone->firstChild);
                } else {
                    $clone->appendChild($titleElement);
                }
            }

            $this->replaceElementText($titleElement, $description);

            $authorElement = $this->findFirstDirectChild($clone, 'author');
            if ($authorElement === null) {
                $authorElement = $clone->ownerDocument?->createElement('author');

                if ($authorElement === null) {
                    continue;
                }

                if ($titleElement->nextSibling !== null) {
                    $clone->insertBefore($authorElement, $titleElement->nextSibling);
                } else {
                    $clone->appendChild($authorElement);
                }
            }

            $this->replaceElementText($authorElement, $originalTitle);
            $transformed[] = $clone;
        }

        return $transformed;
    }

    private function replaceElementText(DOMElement $element, string $value): void
    {
        while ($element->firstChild !== null) {
            $element->removeChild($element->firstChild);
        }

        $element->appendChild($element->ownerDocument->createTextNode($value));
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
