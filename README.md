## Discord ToD tracker

### Quick install (Docker Compose)

1) Create a file named `docker-compose.yml` in an empty folder:

```yaml
services:
  bot:
    image: ghcr.io/haswelldev/tod-bot:latest
    container_name: tod-bot
    environment:
      - DISCORD_TOKEN=${DISCORD_TOKEN}
      - TOD_STORAGE=sqlite
      - TZ=UTC
    volumes:
      - ./data:/app/data
    restart: unless-stopped
```

2) Start it:
```
export DISCORD_TOKEN=your_token
docker compose up -d
```

3) In Discord, run `.init` in the channel where the bot is present to register it and choose a language.

4) After registration, start tracking:
```
.tod hallate 14:30 Europe/Kyiv
```

### Overview
TodBot is a lightweight Discord bot to record boss Time of Death (ToD), show respawn windows, and post reminders when a window opens and closes.

### Features
- Commands: `.init`, `.tod`, `.window` / `.w`, `.del`, `.list` / `.ls` / `.all`
- Per-channel registration via `.init` — the bot ignores unregistered channels
- Per-channel language chosen during `.init` (English, Russian, French, Greek, Portuguese, Ukrainian)
- Configurable respawn windows — global defaults via env vars, per-boss overrides via `config/bosses.yaml`
- Partial/alias boss name matching — `taras` → `antharas`, `tezza` → `frintezza`, `aq` / `qa` → `queen ant`
- Epic boss windows pre-configured (Queen Ant 24+4h, Antharas 192+4h, Valakas 264+4h, etc.)
- Automatic reminders at window open and window close
- User-local time display using Discord dynamic timestamps
- Auto-deletes the invoking user message after handling (if the bot has permissions)
- Storage backends: JSON (default), SQLite, or MySQL
- Multi-server / multi-channel support with isolated data per channel

### Requirements
- Native run:
  - PHP 8.4 or newer
  - Composer
  - `pdo_sqlite` extension for SQLite backend; `pdo_mysql` for MySQL
- Docker run:
  - Docker Engine 24 or newer

### Installation (native)
1. Clone and install dependencies:
   ```
   git clone <repo-url>
   cd TodBot
   composer install
   ```
2. Copy the example env file and set your token:
   ```
   cp .env.example .env
   # edit .env and set DISCORD_TOKEN
   ```
3. Run:
   ```
   php bin/bot.php
   ```

### Installation (Docker) — recommended

#### Using the Makefile (simplest)

SQLite (default):
```
export DISCORD_TOKEN=your_token
make up        # builds image and starts bot with SQLite
```

MySQL:
```
export DISCORD_TOKEN=your_token
make mysql-up  # creates .env if missing, builds image, starts MySQL + bot
```

Other useful targets:
```
make logs        # follow SQLite bot logs
make mysql-logs  # follow MySQL bot logs
make mysql-db    # open MySQL shell in the db container
make shell       # open shell in the bot container
make down        # stop SQLite stack
make mysql-down  # stop MySQL stack
make test        # run PHPUnit tests locally
```

#### Manual Docker Compose (SQLite)

```yaml
services:
  bot:
    image: ghcr.io/haswelldev/tod-bot:latest
    container_name: tod-bot
    environment:
      - DISCORD_TOKEN=${DISCORD_TOKEN}
      - TOD_STORAGE=sqlite
      - TZ=UTC
    volumes:
      - ./data:/app/data
    restart: unless-stopped
```

```
export DISCORD_TOKEN=your_token
docker compose up -d
```

#### Manual Docker Compose (MySQL)

Use `docker-compose.mysql.yml` from the repo, or see the included file for a full example. The MySQL database and tables are created automatically on first start.

```
export DISCORD_TOKEN=your_token
docker compose -f docker-compose.mysql.yml up -d --build
```

#### Build and run locally without Compose

```
docker build -t tod-bot:latest .
docker run -d --name tod-bot \
  -e DISCORD_TOKEN=your_token \
  -e TOD_STORAGE=sqlite \
  -e TZ=UTC \
  -v "$(pwd)/data:/app/data" \
  --restart unless-stopped \
  tod-bot:latest
```

### Configuration

| Variable | Default | Description |
|---|---|---|
| `DISCORD_TOKEN` | — | **Required.** Your Discord bot token |
| `TOD_STORAGE` | `json` | Storage backend: `json`, `sqlite`, or `mysql` |
| `TOD_SQLITE` | `./data/tods.sqlite` | Path to SQLite file (SQLite backend only) |
| `TOD_WINDOW_START` | `12` | Default window start offset in hours |
| `TOD_WINDOW_RANDOM` | `9` | Default window random range in hours (`start + random = end`) |
| `BOSS_CONFIG` | `config/bosses.yaml` | Path to boss config YAML for custom windows and aliases |
| `BOT_LOCALE` | `en` | Fallback locale when a channel has no locale set |
| `TZ` | — | System timezone; the app uses UTC internally |
| `MYSQL_HOST` | `127.0.0.1` | MySQL host (MySQL backend only) |
| `MYSQL_PORT` | `3306` | MySQL port |
| `MYSQL_DATABASE` | `todbot` | MySQL database name |
| `MYSQL_USER` | `todbot` | MySQL username |
| `MYSQL_PASSWORD` | — | MySQL password |

### Data files
- JSON: `./data/tods.json`, `./data/channels.json`
- SQLite: `./data/tods.sqlite` (stores both ToDs and channel config)
- MySQL: tables `tods` and `channels` (auto-created on first start)

### Discord permissions
- Required: Read Messages/View Channel, Send Messages
- Recommended: Manage Messages (lets the bot delete user command messages)

### Commands and examples

> Boss names are case-insensitive and support partial/alias matching.
> Displayed times are rendered by Discord in each viewer's local timezone.

#### `.init`
Registers the current channel with the bot. The bot will not respond to any commands in a channel until `.init` has been run there.

Steps:
1. Run `.init` in the channel.
2. The bot replies with a numbered language menu — send the number for your language.
3. The bot asks for confirmation — reply `yes` to complete registration.

#### `.tod <boss> [time] [timezone]`
Sets ToD for a boss. If time is omitted, now is used. If timezone is omitted, UTC is used.

Examples:
```
.tod skylancer
.tod skylancer now
.tod skylancer 1700000000
.tod skylancer 14:30 Europe/Kyiv
.tod skylancer 1430 UTC+2
.tod skylancer 2025-11-28 14:00 UTC
.tod skylancer 28.11.2025 14:00 UTC
.tod skylancer 28-11 14:00 UTC
.tod skylancer 30m ago
.tod skylancer 2h
.tod taras 14:30        (resolves to antharas)
.tod aq 14:30           (resolves to queen ant)
```

#### `.window <boss>` (alias: `.w`)
Shows last ToD and window start/end for the boss.

#### `.del <boss>`
Deletes the stored ToD.

#### `.list` (aliases: `.ls`, `.all`, `.список`)
Lists bosses for the current channel whose window has not yet closed.
Shows "opens in …" for upcoming windows and "closes in …" for active ones.

### Accepted time formats
- `now`
- Unix timestamp (10 digits)
- Relative: `30m ago`, `2h`, `-45m`
- Clock: `HH:MM` or `HHMM`
- Full date-time: `Y-m-d H:i`, `d.m.Y H:i`, `d-m-Y H:i`, `d/m/Y H:i`
- Short date-time: `d-m H:i`, `d.m H:i` (current year assumed)

### Accepted timezones
- IANA (e.g. `Europe/Kyiv`, `America/New_York`)
- `UTC` or `GMT`
- Offsets like `UTC+2`, `+2`, `GMT-3`

### Boss aliases and custom windows

Partial name matching resolution order:
1. Exact canonical name match
2. Exact alias match
3. Input is a substring of a canonical name
4. Input is a substring of an alias
5. Unknown boss — use default window

Pre-configured epic bosses (in `config/bosses.yaml`):

| Boss | Aliases | Window |
|---|---|---|
| queen ant | qa, aq, ant queen | 24+4h |
| core | — | 48+4h |
| orfen | — | 33+4h |
| zaken | — | 45+4h |
| baium | — | 125+4h |
| frintezza | tezza | 48+4h |
| antharas | taras | 192+4h |
| valakas | — | 264+4h |

To add or override bosses, edit `config/bosses.yaml`:
```yaml
bosses:
  "queen ant":
    aliases: [qa, aq, "ant queen"]
    respawn: 24
    random: 4
  antharas:
    aliases: [taras]
    respawn: 192
    random: 4
```

### Localization (i18n)
- Supported locales: `en`, `ru`, `fr`, `el`, `pt`, `uk`
- Locale is set **per channel** during `.init` — each channel can use a different language.
- `BOT_LOCALE` is the fallback for channels without a locale set (default: `en`).
- Translation files: `translations/messages.<locale>.php`

### Per-channel isolation
Each channel has its own ToD list, reminders, and language. Data from different servers and channels is fully isolated.

### Project internals
- Entry point: `bin/bot.php`
- Key classes:
  - `src/Bot/DiscordBot.php` — bootstraps Discord client, wires events, routes messages
  - `src/Service/InitHandler.php` — multi-step channel registration state machine
  - `src/Service/CommandHandler.php` — parses and responds to commands
  - `src/Service/ReminderScheduler.php` — 60s periodic checks to post start/end reminders
  - `src/Service/BossRegistry.php` — canonical name resolution and window lookup
  - `src/Service/TimeParser.php` — parses flexible time inputs into UTC
  - `src/Service/TimeFormatter.php` — formats Discord timestamp tokens
  - `src/Repository/` — JSON, SQLite, and MySQL backends implementing repository interfaces
- Storage schema:
  - `tods` table/file: primary key is `boss + channel_id`; flags `start_reminded`, `end_reminded`
  - `channels` table/file: primary key is `channel_id`; stores `guild_id`, `locale`

### Testing
```
composer test          # run PHPUnit locally
make test              # alias
```

### Troubleshooting
- `DISCORD_TOKEN is not set` — run `export DISCORD_TOKEN=your_token` or set it in `.env`
- Bot does not respond — the channel must be registered with `.init` first
- Bot does not delete user messages — grant Manage Messages permission
- SQLite errors (native) — enable `pdo_sqlite` or use the Docker image
- MySQL errors — ensure `MYSQL_*` env vars are set and the MySQL container is healthy
- No reminders — the scheduler ticks every 60 seconds; ensure the bot can send messages in the channel
- Time parsing failed — the bot will respond with examples; try another format or specify a timezone
