<?php

declare(strict_types=1);

namespace CoolMS\TelegramBotApi;

use RuntimeException;

/** The Bot API gave no answer: refused, timed out or never reached. The message never carries the token. */
final class Unreachable extends RuntimeException
{
}
