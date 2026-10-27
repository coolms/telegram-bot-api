<?php

declare(strict_types=1);

namespace CoolMS\TelegramBotApi;

use SensitiveParameter;

use function hash_equals;

/** Whether a webhook request carries the secret token the webhook was set with, compared in constant time. */
final class WebhookSecret
{
    public const string HEADER = 'X-Telegram-Bot-Api-Secret-Token';

    public static function matches(#[SensitiveParameter] string $expected, #[SensitiveParameter] ?string $header): bool
    {
        return '' !== $expected && null !== $header && hash_equals($expected, $header);
    }
}
