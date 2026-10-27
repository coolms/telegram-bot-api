# Changelog

All notable changes to `coolms/telegram-bot-api` are recorded here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Changed
- `getUpdates()` throws `Refused` when the Bot API refuses a long poll, where it returned no updates before. To
  migrate, catch `Refused`: on 409 another poller or a webhook holds the bot, so stop polling it rather than retrying;
  on 429 wait `retryAfter` seconds.

### Added
- `BotClient` for one bot: `sendMessage()` (the text cut at the Bot API's 4096 characters), `getUpdates()` by long
  polling (the request waits longer than Telegram holds it), `setWebhook()` with a secret token and
  `deleteWebhook()`; only messages are asked for.
- `Update`, `Message`, `Answer` and `Unreachable`: what the Bot API sends and answers, and its silence.
- `StartCommand`: a deep-link start, in a private chat only, with a token from the deep link's alphabet.
- `WebhookSecret`: the secret-token header compared in constant time; an empty secret matches nothing.
- The bot token is never in what the client throws: a transport failure comes back with the token masked.
- `Refused`: long polling refused by the Bot API -- another poller or a webhook on the bot (409), too many requests
  (429, with `retryAfter`) -- is thrown with its status and words, never answered as an empty batch.
- `BotClient::pollUpdates()` and `PolledUpdates::asTheyArrive()`: several bots' long polls read together over one HTTP
  client, through the client's own `stream()`, each answer (updates, `Refused` or `Unreachable`) under its key as it
  arrives.
