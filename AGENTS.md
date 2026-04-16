# AGENTS.md

## Project Overview

**lan-install.online** - Laravel 12 application for managing LAN installation requests, monitoring systems, and video surveillance in educational institutions. Features brigade management, request tracking, reporting, and Yandex Maps integration.

## Key Commands

```bash
# Setup
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate

# Development
composer run dev           # Full dev environment (server + queue + logs + vite)
php artisan serve          # Quick start
npm run build              # Frontend build

# Code quality
vendor/bin/pint            # PHP linting/formatting
php artisan test           # Run tests

# Useful scripts
./clear-cash               # Clear Laravel caches
./show-logs                # Tail Laravel logs (storage/logs/local.log)
./url_gen.sh [address_id]  # Generate public link with token
./git-deploy "message"     # Deploy to server
./update-bot               # Recompile C binaries on server
./commit-list              # View recent commits
./backup_db_remote         # Backup database
```

## Architecture

- **No Eloquent models** - Uses raw SQL via `DB` facade
- **Controllers**: `app/Http/Controllers/`
- **Views**: `resources/views/` (Blade templates)
- **Routes**: `routes/web.php`, `routes/api.php`
- **JS handlers**: `public/js/` (individual files, not in Blade)

## Database

- PostgreSQL (`lan_install` database)
- Check schema:
  - macOS: `sudo psql -U postgres -d lan_install -c "\d"`
  - Linux: `sudo -u postgres psql -d lan_install -c "\d"`

## JavaScript Conventions

- New handlers → `public/js/[descriptive-name].js`
- Include via Blade templates as needed
- Avoid adding to `form-handlers.js` unless generic
- Use `public/js/init.js` for DOM-ready initialization
- No inline JS in Blade templates

## C Binaries (Telegram Bot)

- Source: `utils/C/notify-bot/telegram_notify.c`
- Config: `utils/C/notify-bot/telegram.conf` (gitignored)
- Recompile after changes: `./update-bot` (run on server)
- Test bot token: local file only (not committed)

## Security

- Never hardcode secrets - use `.env` or local config files
- Sensitive files in `.gitignore`: `.env`, `telegram.conf`, compiled binaries

## Logs

Errors → `storage/logs/local.log` (check first for "Server Error" or "Network Error")

## Deployment

Before deploying:
1. Review `git status` and `git diff`
2. Check for regressions, sensitive data, logic changes
3. Verify no `.env` or credentials in changes
4. Get user confirmation before running `./git-deploy`

## API Documentation

Full API reference: `docs/API.md`

## Code Style

PHP formatting: `laravel/pint` via `vendor/bin/pint`

## Bash Scripts

New scripts must include `set -e` after shebang for fail-fast behavior.
