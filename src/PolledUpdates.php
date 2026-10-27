<?php

declare(strict_types=1);

namespace CoolMS\TelegramBotApi;

use SplObjectStorage;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_map;
use function array_values;

/**
 * Several bots' long polls read together over one HTTP client: the client's own stream() waits on every response at
 * once, so a bot's answer is read as soon as it arrives, whichever was asked first. Each poll must have been made
 * through `$http`.
 */
final class PolledUpdates
{
    /**
     * Each poll's answer under its key, in the order they arrive: its updates, or why there were none.
     *
     * @template K of array-key
     *
     * @param array<K, PendingUpdates> $polls
     *
     * @return iterable<K, list<Update>|Refused|Unreachable>
     */
    public static function asTheyArrive(HttpClientInterface $http, array $polls): iterable
    {
        /** @var SplObjectStorage<object, K> $keys */
        $keys = new SplObjectStorage();
        foreach ($polls as $key => $poll) {
            $keys[$poll->response] = $key;
        }
        $answered = [];
        $responses = array_values(array_map(static fn (PendingUpdates $poll) => $poll->response, $polls));
        foreach ($http->stream($responses) as $response => $chunk) {
            try {
                if ($chunk->isFirst()) {
                    // A refusal's status is read by the poll itself, not thrown by the client mid-stream.
                    $response->getHeaders(false);
                }
                if (!$chunk->isLast()) {
                    continue;
                }
            } catch (TransportExceptionInterface) {
                // The poll's own read says what failed, with the token masked.
            }
            $key = $keys[$response];
            if (isset($answered[$key])) {
                continue;
            }
            $answered[$key] = true;
            try {
                yield $key => $polls[$key]->updates();
            } catch (Refused|Unreachable $e) {
                yield $key => $e;
            }
        }
    }
}
