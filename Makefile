.PHONY: build up down restart logs shell mysql-up mysql-down mysql-logs mysql-shell mysql-db test

# SQLite stack (default for local dev) ----------------------------------------

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart bot

logs:
	docker compose logs -f bot

shell:
	docker compose exec bot sh

# MySQL stack ------------------------------------------------------------------

mysql-up: .env
	docker compose -f docker-compose.mysql.yml up -d --build

.env:
	@test -f .env.example && cp .env.example .env && echo "Created .env from .env.example — set your DISCORD_TOKEN before starting the bot." || true

mysql-down:
	docker compose -f docker-compose.mysql.yml down

mysql-logs:
	docker compose -f docker-compose.mysql.yml logs -f bot

mysql-shell:
	docker compose -f docker-compose.mysql.yml exec bot sh

mysql-db:
	docker compose -f docker-compose.mysql.yml exec db mysql -utodbot -pchangeme todbot

# Tests ------------------------------------------------------------------------

test:
	composer test
