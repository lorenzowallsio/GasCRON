<?php

declare(strict_types=1);

namespace GasConnect\RssCron;

use DateTimeZone;
use GasConnect\RssCron\Exception\ConfigurationException;

final class Config
{
    /**
     * @param array<string, string> $requestHeaders
     */
    public function __construct(
        public readonly string $sourceUrl,
        public readonly string $outputPath,
        public readonly string $publicFeedUrl,
        public readonly string $channelTitleOverride,
        public readonly DateTimeZone $timezone,
        public readonly string $cronSchedule,
        public readonly int $fetchTimeoutSeconds,
        public readonly bool $skipItemsWithEmptyTitle,
        public readonly array $requestHeaders,
        public readonly string $logLevel
    ) {
    }

    /**
     * @param array<string, mixed> $environment
     */
    public static function fromEnvironment(array $environment): self
    {
        $sourceUrl = self::requireString($environment, 'RSS_SOURCE_URL');
        $outputPath = self::requireString($environment, 'RSS_OUTPUT_PATH');
        $publicFeedUrl = self::requireString($environment, 'RSS_PUBLIC_FEED_URL');

        if (!filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            throw new ConfigurationException('RSS_SOURCE_URL must be a valid URL.');
        }

        if (!filter_var($publicFeedUrl, FILTER_VALIDATE_URL)) {
            throw new ConfigurationException('RSS_PUBLIC_FEED_URL must be a valid URL.');
        }

        if (!str_starts_with($outputPath, DIRECTORY_SEPARATOR)) {
            throw new ConfigurationException('RSS_OUTPUT_PATH must be an absolute path.');
        }

        $timezoneName = self::stringOrDefault($environment, 'RSS_TIMEZONE', 'Europe/Vienna');

        try {
            $timezone = new DateTimeZone($timezoneName);
        } catch (\Throwable $exception) {
            throw new ConfigurationException(
                sprintf('RSS_TIMEZONE "%s" is not valid.', $timezoneName),
                0,
                $exception
            );
        }

        $timeout = self::intOrDefault($environment, 'RSS_FETCH_TIMEOUT_SECONDS', 15);
        if ($timeout <= 0) {
            throw new ConfigurationException('RSS_FETCH_TIMEOUT_SECONDS must be greater than zero.');
        }

        $logLevel = strtolower(self::stringOrDefault($environment, 'LOG_LEVEL', 'info'));
        if (!in_array($logLevel, ['debug', 'info', 'warning', 'error'], true)) {
            throw new ConfigurationException('LOG_LEVEL must be one of: debug, info, warning, error.');
        }

        $headersJson = self::stringOrDefault($environment, 'RSS_REQUEST_HEADERS_JSON', '{}');
        $headers = json_decode($headersJson, true);

        if (!is_array($headers)) {
            throw new ConfigurationException('RSS_REQUEST_HEADERS_JSON must decode to an object.');
        }

        $normalizedHeaders = [];
        foreach ($headers as $headerName => $headerValue) {
            if (!is_string($headerName) || !is_scalar($headerValue)) {
                throw new ConfigurationException('RSS_REQUEST_HEADERS_JSON must contain string header names and scalar values.');
            }

            $normalizedHeaders[$headerName] = (string) $headerValue;
        }

        return new self(
            sourceUrl: $sourceUrl,
            outputPath: $outputPath,
            publicFeedUrl: $publicFeedUrl,
            channelTitleOverride: self::stringOrDefault($environment, 'RSS_CHANNEL_TITLE_OVERRIDE', 'Gasconnect RSS'),
            timezone: $timezone,
            cronSchedule: self::stringOrDefault($environment, 'RSS_CRON_SCHEDULE', '0 2 * * *'),
            fetchTimeoutSeconds: $timeout,
            skipItemsWithEmptyTitle: self::boolOrDefault($environment, 'RSS_SKIP_ITEMS_WITH_EMPTY_TITLE', true),
            requestHeaders: $normalizedHeaders,
            logLevel: $logLevel
        );
    }

    /**
     * @param array<string, mixed> $environment
     */
    private static function requireString(array $environment, string $key): string
    {
        $value = self::stringOrDefault($environment, $key, '');

        if ($value === '') {
            throw new ConfigurationException(sprintf('%s is required.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $environment
     */
    private static function stringOrDefault(array $environment, string $key, string $default): string
    {
        $value = $environment[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return trim((string) $value);
    }

    /**
     * @param array<string, mixed> $environment
     */
    private static function intOrDefault(array $environment, string $key, int $default): int
    {
        $value = self::stringOrDefault($environment, $key, (string) $default);

        if (!preg_match('/^-?\d+$/', $value)) {
            throw new ConfigurationException(sprintf('%s must be an integer.', $key));
        }

        return (int) $value;
    }

    /**
     * @param array<string, mixed> $environment
     */
    private static function boolOrDefault(array $environment, string $key, bool $default): bool
    {
        $value = self::stringOrDefault($environment, $key, $default ? 'true' : 'false');
        $normalized = strtolower($value);

        return match ($normalized) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => throw new ConfigurationException(sprintf('%s must be a boolean value.', $key)),
        };
    }
}
