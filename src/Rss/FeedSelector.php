<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Rss;

final class FeedSelector
{
    /**
     * @param list<ParsedItem> $items
     * @return list<ParsedItem>
     */
    public function selectLatest(array $items, int $limit = 5): array
    {
        usort($items, static function (ParsedItem $left, ParsedItem $right): int {
            if ($left->publishedAt === null && $right->publishedAt === null) {
                return $left->originalIndex <=> $right->originalIndex;
            }

            if ($left->publishedAt === null) {
                return 1;
            }

            if ($right->publishedAt === null) {
                return -1;
            }

            $leftTimestamp = (int) $left->publishedAt->format('U');
            $rightTimestamp = (int) $right->publishedAt->format('U');

            if ($leftTimestamp === $rightTimestamp) {
                return $left->originalIndex <=> $right->originalIndex;
            }

            return $rightTimestamp <=> $leftTimestamp;
        });

        return array_values(array_slice($items, 0, $limit));
    }
}

