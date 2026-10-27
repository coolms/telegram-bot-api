<?php

declare(strict_types=1);

namespace CoolMS\TelegramBotApi;

use function is_array;
use function is_int;

/** One update from the Bot API, by long polling or webhook alike: its id, and the message it carries if any. */
final readonly class Update
{
    public function __construct(
        public int $updateId,
        public ?Message $message = null,
    ) {
    }

    /** @param array<mixed> $raw an update as the Bot API sends it */
    public static function from(array $raw): self
    {
        $id = is_int($raw['update_id'] ?? null) ? $raw['update_id'] : 0;
        $message = $raw['message'] ?? null;

        return new self($id, is_array($message) ? Message::from($message) : null);
    }
}
