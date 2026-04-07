<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Rss;

use DOMElement;

final class ParsedItem
{
    public function __construct(
        public readonly DOMElement $element,
        public readonly int $originalIndex,
        public readonly ?\DateTimeImmutable $publishedAt,
        public readonly string $rawPubDate,
        public readonly string $title,
        public readonly string $author
    ) {
    }
}

