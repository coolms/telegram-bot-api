<?php

declare(strict_types=1);

namespace CoolMS\TelegramBotApi;

/** What the Bot API answered a call with: its HTTP status, the message it made, and its own words on a refusal. */
final readonly class Answer
{
    public function __construct(
        public int $status,
        public ?int $messageId = null,
        public ?string $description = null,
    ) {
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
