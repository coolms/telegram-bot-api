<?php

declare(strict_types=1);

namespace CoolMS\TelegramBotApi;

use Closure;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * A long poll on its way: the request is made, and its answer is read by {@see self::updates()} -- or, with other
 * bots' polls, as each arrives ({@see PolledUpdates::asTheyArrive()}).
 */
final readonly class PendingUpdates
{
    /** @param Closure(ResponseInterface): list<Update> $read */
    public function __construct(
        public ResponseInterface $response,
        private Closure $read,
    ) {
    }

    /**
     * The updates the poll brought, waiting for them if they have not arrived.
     *
     * @throws Refused     when Telegram refused the poll
     * @throws Unreachable when no answer came
     *
     * @return list<Update>
     */
    public function updates(): array
    {
        return ($this->read)($this->response);
    }
}
