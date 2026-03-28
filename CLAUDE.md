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

**Storage-independent variables:** `DISCORD_TOKEN` (required), `TOD_STORAGE` (`json`|`mysql`, default `json`), `BOT_LOCALE` (`en`|`ru`|`fr`|`el`|`pt`|`uk`, default `en`), `TOD_WINDOW_START` (hours, default `12`), `TOD_WINDOW_RANDOM` (hours, default `9`), `BOSS_CONFIG` (path to bosses.yaml, default `config/bosses.yaml`).

**MySQL:** `MYSQL_HOST` (default `127.0.0.1`), `MYSQL_PORT` (default `3306`), `MYSQL_DATABASE` (default `todbot`), `MYSQL_USER` (default `todbot`), `MYSQL_PASSWORD`. Tables are created automatically on startup.

## Architecture

TodBot is a Discord bot (PHP 8.4, [DiscordPHP](https://github.com/discord-php/DiscordPHP)) that tracks raid boss Time of Death (ToD) for Lineage 2 and schedules reminders when respawn windows open/close (ToD+12h → ToD+21h).

### Entry point & wiring (`bin/bot.php` → `src/Bot/DiscordBot.php`)

`bin/bot.php` reads `Config`, selects a repository implementation (both ToD and channel config repos), and passes them to `DiscordBot`. `DiscordBot` connects to Discord via the ReactPHP event loop. On `init` it wires a message router and starts the reminder timer.

**Message routing** (inside `DiscordBot.wireEvents()`):
1. `.init` → `InitHandler` (works on any channel, registered or not)
2. Channel has a pending init conversation → `InitHandler`
3. Channel is not registered → silently ignored
4. Registered channel → `CommandHandler`

### Channel registration (`src/Service/InitHandler.php`)

`.init` starts a three-step conversation to register a channel:
1. Bot sends language menu; user replies with number or code (`1`–`6` or `en`, `ru`, `uk`, `fr`, `el`, `pt`)
2. Bot asks for confirmation; user replies `yes` or `no`
3. Bot asks whether to enable reminders for all bosses; user replies `yes` or `no`

On completion, channel config (`guild_id`, `guild_name`, `channel_name`, `locale`, `reminders_enabled`) is written to `ChannelConfigRepositoryInterface`. In-memory pending state tracks which channels are mid-conversation.

### Command handling (`src/Service/CommandHandler.php`)

Only reached for registered channels (enforced by router). Parses `.`-prefixed commands (`.tod`, `.window`/`.w`, `.del`, `.list`/`.ls`/`.all`, `.remind`, `.reminders`) plus Cyrillic aliases. At the top of `__invoke()`, sets `I18n::setLocale()` to the channel's configured locale so all subsequent `I18n::t()` calls use the right language. User command messages are auto-deleted.

### Reminder scheduler (`src/Service/ReminderScheduler.php`)

Runs every 60 seconds. Iterates all stored ToDs, checks window boundaries, and posts channel embeds. Behaviour depends on channel config:
- `reminders_enabled = true`: sends both start (`start_reminded`) and end (`end_reminded`) reminders for all bosses.
- `reminders_enabled = false`: sends only a start reminder if the boss has `remind = true` (set via `.remind BossName`); clears the flag after firing. End reminders are never sent in this mode.

### Repository pattern (`src/Repository/`)

**ToD data** — `TodRepositoryInterface`: `all()`, `allByChannel()`, `get()`, `set()`, `delete()`, `save()`.
- **`JsonTodRepository`** — `./data/tods.json`; auto-migrates old flat format to per-channel structure
- **`MysqlTodRepository`** — `tods` table; auto-creates schema; upserts via `ON DUPLICATE KEY UPDATE`; uses persistent PDO connection

Data shape: `['tod' => int, 'channel' => string, 'start_reminded' => bool, 'end_reminded' => bool, 'remind' => bool]`

**Channel config** — `ChannelConfigRepositoryInterface`: `get()`, `set()`, `delete()`, `save()`.
- **`JsonChannelConfigRepository`** — `./data/channels.json`
- **`MysqlChannelConfigRepository`** — `channels` table; auto-creates schema; upserts via `ON DUPLICATE KEY UPDATE`

Data shape: `['guild_id' => string, 'guild_name' => string, 'channel_name' => string, 'locale' => string, 'reminders_enabled' => bool]` keyed by `channel_id`.

**MySQL setup with Docker:** `docker compose -f docker-compose.mysql.yml up -d` (set `DISCORD_TOKEN` env var first). See `docker-compose.mysql.yml` — change the default passwords (`changeme`, `changeme_root`) before production use. For external MySQL, set the `MYSQL_*` env vars and use `TOD_STORAGE=mysql` without Docker Compose.

### Time utilities

- **`TimeParser::parse($timeArg, $tzArg, $now)`** (static) — accepts relative (`30m ago`, `2h`), clock (`14:30`, `1430`), epoch (10–11 digits), and full/short date strings; normalizes to UTC unix timestamp
- **`TimeFormatter::discord($ts, $style)`** (static) — returns Discord dynamic timestamp token `<t:TS:STYLE>`

### Internationalization (`src/Service/I18n.php`)

Wraps Symfony Translation. Translation files live in `translations/messages.LOCALE.php`. Call `I18n::t('key', ['param' => $val])` anywhere. Default locale from `BOT_LOCALE` env var. Per-channel locale is applied via `I18n::setLocale($locale)` before each command handler invocation and before each channel's reminder batch — Symfony's `Translator::trans()` receives the override locale as its 4th argument.
