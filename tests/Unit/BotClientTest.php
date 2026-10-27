<?php

declare(strict_types=1);

namespace CoolMS\TelegramBotApi\Tests\Unit;

use CoolMS\TelegramBotApi\BotClient;
use CoolMS\TelegramBotApi\Unreachable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function json_decode;
use function json_encode;
use function mb_strlen;
use function str_repeat;

final class BotClientTest extends TestCase
{
    private const string TOKEN = '123456:SECRET-bot-token';

    #[Test]
    public function aTransportFailureIsThrownWithTheTokenMasked(): void
    {
        $failure = 'Could not resolve host for "https://api.telegram.org/bot' . self::TOKEN . '/sendMessage".';
        $client = new BotClient(new MockHttpClient(new MockResponse('', ['error' => $failure])), self::TOKEN);

        try {
            $client->sendMessage('424242', 'hello');
            self::fail('no answer was taken for one');
        } catch (Unreachable $e) {
            self::assertStringNotContainsString(self::TOKEN, $e->getMessage());
            self::assertStringContainsString('/bot***/sendMessage', $e->getMessage());
            self::assertNull($e->getPrevious(), 'the original, URL and all, is not chained');
        }
    }

    #[Test]
    public function aLongTextIsCutAndARefusalKeepsItsOwnWords(): void
    {
        $sent = null;
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = self::body($options);
            $refusal = '{"ok":false,"description":"Forbidden: bot was blocked by the user"}';

            return new MockResponse($refusal, ['http_code' => 403]);
        });

        $answer = new BotClient($http, self::TOKEN)->sendMessage('424242', str_repeat('a', 5000));

        self::assertIsArray($sent);
        self::assertIsString($sent['text'] ?? null);
        self::assertSame(BotClient::MAX_TEXT_LENGTH, mb_strlen($sent['text']));
        self::assertSame(403, $answer->status);
        self::assertSame('Forbidden: bot was blocked by the user', $answer->description);
    }

    #[Test]
    public function longPollingAsksFromTheOffsetAndWaitsLongerThanTelegramHoldsTheRequest(): void
    {
        $asked = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$asked): MockResponse {
            $asked = ['url' => $url, 'body' => self::body($options), 'timeout' => $options['timeout']];
            $updates = ['ok' => true, 'result' => [
                ['update_id' => 7, 'message' => ['message_id' => 1, 'chat' => ['id' => 424242, 'type' => 'private'], 'from' => ['id' => 99], 'text' => '/start abc']],
                ['update_id' => 8, 'edited_message' => ['message_id' => 2]],
            ]];

            return new MockResponse((string) json_encode($updates));
        });

        $updates = new BotClient($http, self::TOKEN)->getUpdates(7, 25);

        self::assertIsString($asked['url'] ?? null);
        self::assertStringEndsWith('/getUpdates', $asked['url']);
        self::assertSame(['offset' => 7, 'timeout' => 25, 'allowed_updates' => ['message']], $asked['body']);
        self::assertGreaterThan(25, $asked['timeout']);
        self::assertSame([7, 8], [$updates[0]->updateId, $updates[1]->updateId]);
        self::assertSame('424242', $updates[0]->message?->chatId);
        self::assertNull($updates[1]->message, 'only messages are read');
    }

    #[Test]
    public function aWebhookIsSetWithItsSecretTokenAndRemoved(): void
    {
        $asked = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$asked): MockResponse {
            $asked[] = [$url, self::body($options)];

            return new MockResponse('{"ok":true,"result":true}');
        });
        $client = new BotClient($http, self::TOKEN);

        $client->setWebhook('https://example.test/hook/dev', 'S3cret_token-1');
        $client->deleteWebhook();

        self::assertStringEndsWith('/setWebhook', $asked[0][0]);
        self::assertSame('https://example.test/hook/dev', $asked[0][1]['url']);
        self::assertSame('S3cret_token-1', $asked[0][1]['secret_token']);
        self::assertSame(['message'], $asked[0][1]['allowed_updates']);
        self::assertStringEndsWith('/deleteWebhook', $asked[1][0]);
    }

    /**
     * The JSON body a request was made with.
     *
     * @param array<mixed> $options
     *
     * @return array<mixed>
     */
    private static function body(array $options): array
    {
        $body = $options['body'] ?? null;
        self::assertIsString($body);
        $decoded = json_decode($body, true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
