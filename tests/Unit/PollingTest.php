<?php

declare(strict_types=1);

namespace CoolMS\TelegramBotApi\Tests\Unit;

use CoolMS\TelegramBotApi\BotClient;
use CoolMS\TelegramBotApi\PolledUpdates;
use CoolMS\TelegramBotApi\Refused;
use CoolMS\TelegramBotApi\Unreachable;
use CoolMS\TelegramBotApi\Update;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function array_keys;
use function array_map;
use function iterator_to_array;
use function json_encode;
use function sort;
use function str_contains;

/**
 * Long polling says when Telegram refuses it -- another poller or a webhook on the same bot (409), too many requests
 * (429, with how long to wait) -- instead of answering with no updates; and several bots are polled together over one
 * HTTP client, each answer coming back under its own key as soon as it arrives.
 */
final class PollingTest extends TestCase
{
    private const string TOKEN_A = '111111:SECRET-a';

    private const string TOKEN_B = '222222:SECRET-b';

    private const string CONFLICT = 'Conflict: terminated by other getUpdates request;'
        . ' make sure that only one bot instance is running';

    #[Test]
    public function aConflictIsThrownWithItsStatusAndWordsAndNeverTheToken(): void
    {
        $http = new MockHttpClient(new MockResponse(self::refusal(409, self::CONFLICT), ['http_code' => 409]));

        try {
            new BotClient($http, self::TOKEN_A)->getUpdates(0, 25);
            self::fail('a refusal is not an empty batch');
        } catch (Refused $e) {
            self::assertSame(409, $e->status);
            self::assertSame(self::CONFLICT, $e->description);
            self::assertNull($e->retryAfter);
            self::assertStringContainsString('409', $e->getMessage());
            self::assertStringNotContainsString(self::TOKEN_A, $e->getMessage());
        }
    }

    #[Test]
    public function tooManyRequestsSaysHowLongToWait(): void
    {
        $body = (string) json_encode([
            'ok' => false,
            'error_code' => 429,
            'description' => 'Too Many Requests: retry after 7',
            'parameters' => ['retry_after' => 7],
        ]);
        $http = new MockHttpClient(new MockResponse($body, ['http_code' => 429]));

        try {
            new BotClient($http, self::TOKEN_A)->getUpdates(0, 25);
            self::fail('a refusal is not an empty batch');
        } catch (Refused $e) {
            self::assertSame(429, $e->status);
            self::assertSame(7, $e->retryAfter);
        }
    }

    #[Test]
    public function severalBotsArePolledTogetherAndEachAnswerComesBackUnderItsKey(): void
    {
        $asked = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$asked): MockResponse {
            $asked[] = $url;
            if (str_contains($url, self::TOKEN_B)) {
                return new MockResponse(self::refusal(409, self::CONFLICT), ['http_code' => 409]);
            }

            $updates = ['ok' => true, 'result' => [['update_id' => 11], ['update_id' => 12]]];

            return new MockResponse((string) json_encode($updates));
        });
        $support = new BotClient($http, self::TOKEN_A);
        $sales = new BotClient($http, self::TOKEN_B);

        $answers = iterator_to_array(PolledUpdates::asTheyArrive($http, [
            'support' => $support->pollUpdates(11, 25),
            'sales' => $sales->pollUpdates(0, 25),
        ]));

        self::assertCount(2, $asked, 'both requests were made before either answer was read');
        self::assertSame(['sales', 'support'], self::sortedKeys($answers));
        self::assertIsArray($answers['support']);
        self::assertSame([11, 12], array_map(static fn (Update $u): int => $u->updateId, $answers['support']));
        self::assertInstanceOf(Refused::class, $answers['sales']);
        self::assertSame(409, $answers['sales']->status);
    }

    #[Test]
    public function aBotThatCannotBeReachedComesBackAsUnreachableWithItsTokenMasked(): void
    {
        $http = new MockHttpClient(static function (string $method, string $url): MockResponse {
            return new MockResponse('', ['error' => 'Could not resolve host for "' . $url . '".']);
        });
        $support = new BotClient($http, self::TOKEN_A);

        $answers = iterator_to_array(PolledUpdates::asTheyArrive($http, ['support' => $support->pollUpdates(0, 25)]));

        self::assertInstanceOf(Unreachable::class, $answers['support']);
        self::assertStringNotContainsString(self::TOKEN_A, $answers['support']->getMessage());
        self::assertStringContainsString('/bot***/getUpdates', $answers['support']->getMessage());
    }

    private static function refusal(int $status, string $description): string
    {
        return (string) json_encode(['ok' => false, 'error_code' => $status, 'description' => $description]);
    }

    /**
     * @param array<string, mixed> $answers
     *
     * @return list<string>
     */
    private static function sortedKeys(array $answers): array
    {
        $keys = array_keys($answers);
        sort($keys);

        return $keys;
    }
}
