<?php

declare(strict_types=1);

namespace GasConnect\RssCron\Support;

use DateTimeImmutable;
use DateTimeZone;

final class ConsoleLogger implements Logger
{
    /**
     * @var array<string, int>
     */
    private const LEVEL_PRIORITY = [
        'debug' => 100,
        'info' => 200,
        'warning' => 300,
        'error' => 400,
    ];

    /**
     * @param array<string, mixed> $baseContext
     */
    public function __construct(
        private readonly string $minimumLevel = 'info',
        private readonly array $baseContext = []
    ) {
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $level, string $message, array $context): void
    {
        if (self::LEVEL_PRIORITY[$level] < self::LEVEL_PRIORITY[$this->minimumLevel]) {
            return;
        }

        $timestamp = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $lineContext = array_filter(
            array_merge($this->baseContext, $context),
            static fn (mixed $value): bool => $value !== null
        );

        $contextSuffix = $lineContext === []
            ? ''
            : ' ' . json_encode($lineContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $line = sprintf(
            "[%s] %s %s%s\n",
            $timestamp->format(DATE_ATOM),
            strtoupper($level),
            $message,
            $contextSuffix
        );

        $stream = in_array($level, ['warning', 'error'], true) ? STDERR : STDOUT;
        fwrite($stream, $line);
    }
}

