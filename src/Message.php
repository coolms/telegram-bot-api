<?php

declare(strict_types=1);

namespace CoolMS\TelegramBotApi;

use function is_array;
use function is_int;
use function is_string;

/** A message as much of it as is read: where it was sent, what kind of chat that is, its text, and who sent it. */
final readonly class Message
{
    public function __construct(
        /** The chat's id, as a string: ids pass 2^53, and a group's is negative. */
        public string $chatId,
        /** private, group, supergroup or channel. */
        public string $chatType,
        public ?string $text = null,
        public ?string $fromId = null,
    ) {
    }

    /** @param array<mixed> $raw a message as the Bot API sends it; null when it names no chat */
    public static function from(array $raw): ?self
    {
        $chat = $raw['chat'] ?? null;
        if (!is_array($chat) || !is_int($chat['id'] ?? null)) {
            return null;
        }
        $from = $raw['from'] ?? null;
        $text = $raw['text'] ?? null;

        return new self(
            (string) $chat['id'],
            is_string($chat['type'] ?? null) ? $chat['type'] : '',
            is_string($text) ? $text : null,
            is_array($from) && is_int($from['id'] ?? null) ? (string) $from['id'] : null,
        );
    }
}
