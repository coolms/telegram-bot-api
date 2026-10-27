<?php

declare(strict_types=1);

namespace CoolMS\TelegramBotApi;

use RuntimeException;

use function sprintf;

/**
 * The Bot API answered, and refused: its status, its own words, and -- for too many requests -- how many seconds to
 * wait. A 409 on long polling means another poller, or a webhook, holds the bot: polling again at once only repeats
 * it. The message never carries the token.
 */
final class Refused extends RuntimeException
{
    public function __construct(
        public readonly string $method,
        public readonly int $status,
        public readonly ?string $description = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct(sprintf('%s was refused: %d %s', $method, $status, $description ?? '(no description)'));
    }
}
