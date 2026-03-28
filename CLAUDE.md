# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Install dependencies
composer install

# Run tests
composer test
# or
./vendor/bin/phpunit

# Run a single test file
./vendor/bin/phpunit tests/Service/TimeParserTest.php

# Run the bot locally
php bin/bot.php

# Docker
docker compose up -d
```

Required environment variables: `DISCORD_TOKEN` (required), `TOD_STORAGE` (`json`|`sqlite`, default `json`), `TOD_SQLITE` (path, default `./data/tods.sqlite`), `BOT_LOCALE` (`en`|`ru`|`fr`|`el`|`pt`|`uk`, default `en`).

## Architecture

NapevBot is a Discord bot (PHP 8.4, [DiscordPHP](https://github.com/discord-php/DiscordPHP)) that tracks raid boss Time of Death (ToD) for Lineage 2 and schedules reminders when respawn windows open/close (ToD+12h → ToD+21h).

### Entry point & wiring (`bin/bot.php` → `src/Bot/DiscordBot.php`)

`bin/bot.php` reads `Config`, selects a repository implementation, and hands both to `DiscordBot`. `DiscordBot` connects to Discord via the ReactPHP event loop and wires two handlers on the `init` event:
- **`CommandHandler`** — registered as a callback on each incoming `message` event
- **`ReminderScheduler`** — started as a 60-second repeating timer

### Command handling (`src/Service/CommandHandler.php`)

Invoked on every Discord message. Parses `.`-prefixed commands (`.tod`, `.window`/`.w`, `.del`, `.list`/`.ls`/`.all`) plus Cyrillic aliases, then delegates to private handler methods. Each handler reads/writes via the injected repository and responds with a Discord embed. User command messages are auto-deleted.

### Reminder scheduler (`src/Service/ReminderScheduler.php`)

Runs every 60 seconds. Iterates all stored ToDs, checks window boundaries, and posts channel embeds when a window opens (`start_reminded`) or closes (`end_reminded`). Flags prevent duplicate reminders.

### Repository pattern (`src/Repository/`)

`TodRepositoryInterface` defines `all()`, `allByChannel()`, `get()`, `set()`, `delete()`, `save()`. Two implementations:
- **`JsonTodRepository`** — JSON file (`./data/tods.json`); auto-migrates old flat format to per-channel structure
- **`SqliteTodRepository`** — SQLite (`./data/tods.sqlite`); auto-creates schema; `save()` is a no-op (immediate writes)

Data shape per entry: `['tod' => int, 'channel' => string, 'start_reminded' => bool, 'end_reminded' => bool]`

### Time utilities

- **`TimeParser::parse($timeArg, $tzArg, $now)`** (static) — accepts relative (`30m ago`, `2h`), clock (`14:30`, `1430`), epoch (10–11 digits), and full/short date strings; normalizes to UTC unix timestamp
- **`TimeFormatter::discord($ts, $style)`** (static) — returns Discord dynamic timestamp token `<t:TS:STYLE>`

### Internationalization (`src/Service/I18n.php`)

Wraps Symfony Translation. Translation files live in `translations/messages.LOCALE.php`. Call `I18n::t('key', ['param' => $val])` anywhere. Locale set via `BOT_LOCALE` env var.
