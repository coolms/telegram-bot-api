<?php

declare(strict_types=1);

namespace CoolMS\TelegramBotApi;

use SensitiveParameter;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function is_array;
use function is_int;
use function is_string;
use function mb_strlen;
use function mb_substr;
use function sprintf;
use function str_replace;

/**
 * The Telegram Bot API for one bot: sending a message, reading updates by long polling, and setting or removing the
 * webhook updates come to otherwise. A bot is read one way at a time: Telegram refuses getUpdates while a webhook is
 * set.
 *
 * The token rides in the request path, as the Bot API wants it. It is never part of what this client throws or
 * returns: a transport failure's message, which names the URL, is given back with the token masked and the original
 * not chained.
 */
final readonly class BotClient
{
    public const int MAX_TEXT_LENGTH = 4096;

    /** Only messages are asked for: a start, and later the person's replies. */
    public const array UPDATES = ['message'];

    private const string API = 'https://api.telegram.org/bot%s/%s';

    public function __construct(
        private HttpClientInterface $http,
        #[SensitiveParameter]
        private string $token,
        private float $timeout = 15.0,
    ) {
    }

    /**
     * Sends a text to a chat; the text is cut at the Bot API's limit.
     *
     * @throws Unreachable when no answer came: nothing is known of the send
     */
    public function sendMessage(string $chatId, string $text): Answer
    {
        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_TEXT_LENGTH);
        }
        [$answer] = $this->call('sendMessage', ['chat_id' => $chatId, 'text' => $text], $this->timeout);

        return $answer;
    }

    /**
     * The updates from `$offset` on, held by Telegram up to `$waitSeconds` when there are none (long polling). The
     * request waits a little longer than Telegram holds it.
     *
     * @throws Unreachable when no answer came
     *
     * @return list<Update>
     */
    public function getUpdates(int $offset, int $waitSeconds): array
    {
        $body = ['offset' => $offset, 'timeout' => $waitSeconds, 'allowed_updates' => self::UPDATES];
        [, $decoded] = $this->call('getUpdates', $body, $waitSeconds + $this->timeout);
        $updates = [];
        foreach (is_array($decoded['result'] ?? null) ? $decoded['result'] : [] as $raw) {
            if (is_array($raw) && is_int($raw['update_id'] ?? null)) {
                $updates[] = Update::from($raw);
            }
        }

        return $updates;
    }

    /**
     * Sends updates to `$url` from now on, each with `$secretToken` in its X-Telegram-Bot-Api-Secret-Token header.
     *
     * @throws Unreachable when no answer came
     */
    public function setWebhook(string $url, #[SensitiveParameter] string $secretToken): Answer
    {
        $body = ['url' => $url, 'secret_token' => $secretToken, 'allowed_updates' => self::UPDATES];
        [$answer] = $this->call('setWebhook', $body, $this->timeout);

        return $answer;
    }

    /**
     * Stops sending updates to a webhook, so they can be read by long polling.
     *
     * @throws Unreachable when no answer came
     */
    public function deleteWebhook(): Answer
    {
        [$answer] = $this->call('deleteWebhook', [], $this->timeout);

        return $answer;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{Answer, array<mixed>}
     */
    private function call(string $method, array $body, float $timeout): array
    {
        try {
            $response = $this->http->request('POST', sprintf(self::API, $this->token, $method), [
                'json' => $body,
                'timeout' => $timeout,
                'max_redirects' => 0,
            ]);
            $status = $response->getStatusCode();
            try {
                $decoded = $response->toArray(false);
            } catch (DecodingExceptionInterface) {
                // A gateway's error page, or nothing: the status says all there is.
                $decoded = [];
            }
        } catch (TransportExceptionInterface $e) {
            throw new Unreachable(str_replace($this->token, '***', $e->getMessage()));
        }
        $result = $decoded['result'] ?? null;
        $messageId = is_array($result) && is_int($result['message_id'] ?? null) ? $result['message_id'] : null;
        $description = $decoded['description'] ?? null;

        return [new Answer($status, $messageId, is_string($description) ? $description : null), $decoded];
    }
}
