<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Rss;

use GasConnect\RssCron\Exception\FeedPublishException;

final class AtomicFilePublisher implements PublisherInterface
{
    public function publish(string $contents, string $outputPath): int
    {
        $directory = dirname($outputPath);

        if (!is_dir($directory)) {
            throw new FeedPublishException(sprintf('Output directory does not exist: %s', $directory));
        }

        if (!is_writable($directory)) {
            throw new FeedPublishException(sprintf('Output directory is not writable: %s', $directory));
        }

        $temporaryPath = tempnam($directory, basename($outputPath) . '.tmp-');
        if ($temporaryPath === false) {
            throw new FeedPublishException('Could not create a temporary output file.');
        }

        try {
            $writtenBytes = file_put_contents($temporaryPath, $contents, LOCK_EX);
            if ($writtenBytes === false) {
                throw new FeedPublishException(sprintf('Could not write temporary feed file: %s', $temporaryPath));
            }

            if (!rename($temporaryPath, $outputPath)) {
                throw new FeedPublishException(sprintf('Could not atomically replace feed file: %s', $outputPath));
            }

            return $writtenBytes;
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }
}

