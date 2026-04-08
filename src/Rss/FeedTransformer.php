<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Rss;

use DOMElement;
use GasConnect\RssCron\Support\Logger;

final class FeedTransformer
{
    private const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';

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

            $this->removeDirectChildren($clone, 'author');

            $creatorElement = $this->findFirstDirectChild($clone, 'creator');
            if ($creatorElement === null) {
                $creatorElement = $clone->ownerDocument?->createElementNS(self::DC_NAMESPACE, 'dc:creator');

                if ($creatorElement === null) {
                    continue;
                }

                $insertAfter = $this->findFirstDirectChild($clone, 'description') ?? $titleElement;
                if ($insertAfter->nextSibling !== null) {
                    $clone->insertBefore($creatorElement, $insertAfter->nextSibling);
                } else {
                    $clone->appendChild($creatorElement);
                }
            }

            $this->replaceElementText($creatorElement, $originalTitle);
            $this->normalizeDcNamespaceDeclarations($clone);
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

    private function removeDirectChildren(DOMElement $parent, string $localName): void
    {
        while (($child = $this->findFirstDirectChild($parent, $localName)) !== null) {
            $parent->removeChild($child);
        }
    }

    private function normalizeDcNamespaceDeclarations(DOMElement $item): void
    {
        foreach ($item->childNodes as $child) {
            if (!$child instanceof DOMElement || $child->namespaceURI !== self::DC_NAMESPACE || $child->prefix !== 'dc') {
                continue;
            }

            $child->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:dc', self::DC_NAMESPACE);
        }

        $item->removeAttributeNS('http://www.w3.org/2000/xmlns/', 'dc');
        $item->removeAttribute('xmlns:dc');
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
