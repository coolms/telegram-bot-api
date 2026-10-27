<?php

declare(strict_types=1);

namespace CoolMS\TelegramBotApi;

use function preg_match;

/**
 * A person starting the bot through a deep link, `t.me/<bot>?start=<token>`: Telegram sends `/start <token>` in the
 * private chat that link opened. Only that is a start; a start in a group, a bare `/start`, or a token outside the
 * deep link's alphabet is none.
 */
final readonly class StartCommand
{
    private function __construct(
        public string $chatId,
        public string $token,
        public ?string $fromId,
    ) {
    }

    public static function from(?Message $message): ?self
    {
        if (null === $message || 'private' !== $message->chatType || null === $message->text) {
            return null;
        }
        if (1 !== preg_match('/^\/start ([A-Za-z0-9_-]{1,64})$/', $message->text, $match)) {
            return null;
        }

        return new self($message->chatId, $match[1], $message->fromId);
    }
}
