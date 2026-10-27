<?php

declare(strict_types=1);

namespace CoolMS\TelegramBotApi\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function file_get_contents;
use function glob;
use function json_decode;
use function preg_match_all;
use function sort;

/** Framework-free: PHP and the HTTP client contracts, nothing else -- the integration lives in its own package. */
final class BoundaryTest extends TestCase
{
    #[Test]
    public function theClientRequiresAndImportsOnlyTheHttpClientContracts(): void
    {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../../composer.json'), true);
        self::assertIsArray($manifest);
        self::assertIsArray($manifest['require'] ?? null);
        $required = array_keys($manifest['require']);
        sort($required);
        self::assertSame(['php', 'symfony/http-client-contracts'], $required);

        $files = glob(__DIR__ . '/../../src/*.php') ?: [];
        self::assertNotEmpty($files);
        foreach ($files as $file) {
            preg_match_all('/^use ([^ ;]+)/m', (string) file_get_contents($file), $uses);
            foreach ($uses[1] as $use) {
                self::assertMatchesRegularExpression('/^(CoolMS\\\\TelegramBotApi\\\\|Symfony\\\\Contracts\\\\HttpClient\\\\|[A-Za-z]+$)/', $use, $file);
            }
        }
    }
}
