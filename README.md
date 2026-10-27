# coolms/telegram-bot-api

[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%E2%89%A5%208.5-777bb4)](https://www.php.net/releases/8.5/en.php)

**A Telegram Bot API client for PHP**, for one bot: sending a message, reading updates by long polling or taking them
from a webhook, and reading a deep-link start. Framework-free: it needs PHP and `symfony/http-client-contracts`,
nothing of a framework.

```
composer require coolms/telegram-bot-api
```

- `BotClient`: `sendMessage()`, `getUpdates()` (long polling), `setWebhook()` with a secret token, `deleteWebhook()`.
  A bot is read one way at a time: Telegram refuses long polling while a webhook is set.
- `Update`, `Message`: an update and the message it carries, as much of them as is read.
- `StartCommand`: a person starting the bot through `t.me/<bot>?start=<token>`; only a start in a private chat with a
  token from the deep link's alphabet counts.
- `WebhookSecret`: whether a webhook request carries the secret token the webhook was set with, compared in constant
  time.

**The token** rides in the request path, as the Bot API wants it, and is never part of what the client throws or
returns: a transport failure's message, which names the URL, comes back with the token masked.

## Licence

MIT. See [LICENSE](LICENSE).
