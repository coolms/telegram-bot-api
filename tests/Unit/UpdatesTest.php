<?php

declare(strict_types=1);

namespace CoolMS\TelegramBotApi\Tests\Unit;

use CoolMS\TelegramBotApi\Message;
use CoolMS\TelegramBotApi\StartCommand;
use CoolMS\TelegramBotApi\WebhookSecret;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UpdatesTest extends TestCase
{
    #[Test]
    public function aDeepLinkStartInAPrivateChatCarriesItsToken(): void
    {
        $start = StartCommand::from(new Message('424242', 'private', '/start Ab_9-z', '99'));

        self::assertNotNull($start);
        self::assertSame('Ab_9-z', $start->token);
        self::assertSame('424242', $start->chatId);
    }

    #[Test]
    public function aGroupAStartWithoutATokenOrAnyOtherTextIsNoStart(): void
    {
        self::assertNull(StartCommand::from(new Message('-1001', 'group', '/start Ab_9-z', '99')));
        self::assertNull(StartCommand::from(new Message('424242', 'private', '/start', '99')));
        self::assertNull(StartCommand::from(new Message('424242', 'private', 'hello', '99')));
        self::assertNull(StartCommand::from(new Message('424242', 'private', '/start bad token!', '99')));
    }

    #[Test]
    public function theWebhookSecretMustMatchExactly(): void
    {
        self::assertTrue(WebhookSecret::matches('S3cret_token-1', 'S3cret_token-1'));
        self::assertFalse(WebhookSecret::matches('S3cret_token-1', 'S3cret_token-2'));
        self::assertFalse(WebhookSecret::matches('S3cret_token-1', null));
        self::assertFalse(WebhookSecret::matches('', ''), 'no secret configured matches nothing');
    }
}
