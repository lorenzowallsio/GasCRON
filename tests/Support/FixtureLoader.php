<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Tests\Support;

final class FixtureLoader
{
    public static function load(string $name): string
    {
        $path = dirname(__DIR__) . '/Fixtures/' . $name;
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException(sprintf('Could not read fixture: %s', $path));
        }

        return $contents;
    }
}

