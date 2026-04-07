<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Rss;

use DOMDocument;
use DOMElement;

final class ParsedFeed
{
    /**
     * @param list<ParsedItem> $items
     */
    public function __construct(
        public readonly DOMDocument $document,
        public readonly DOMElement $rssElement,
        public readonly DOMElement $channelElement,
        public readonly array $items
    ) {
    }
}

