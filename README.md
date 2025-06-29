# Web Codigos 5.0

## Database Configuration

Database credentials are no longer stored in `instalacion/basededatos.php`.
Instead, the application loads them from environment variables or from a
non-tracked file `config/db_credentials.php`.

1. **Using environment variables**: set `DB_HOST`, `DB_USER`, `DB_PASSWORD`
   and `DB_NAME` in your server environment.
2. **Using a credentials file**: copy `config/db_credentials.sample.php` to
   `config/db_credentials.php` and fill in your database details. This file is
   ignored by Git so your credentials remain private.

During installation the system will automatically create
`config/db_credentials.php` with the data you provide.

## Installation

For a step-by-step guide to installing the system, see
[docs/INSTALACION.md](docs/INSTALACION.md).

After cloning the repository run `composer install` to download the PHP
dependencies.

## User Manual

Once installed and logged in, a **Manual** option appears in the navigation bar.
It links to an interactive help page (`manual.php`) with basic usage
instructions. The same content is also available in
[docs/MANUAL_USO.md](docs/MANUAL_USO.md).

## Telegram Bot (Experimental)

A new Telegram bot is being integrated to replicate the web search features. The initial skeleton lives under `telegram_bot/` and requires Composer dependencies. To install them run:

```bash
composer install
```

Configure your bot token in `telegram_bot/config/bot_config.php` and set up the webhook to point to `telegram_bot/webhook.php`.
From version 5.0.1 these valores pueden modificarse desde la pestaña **Bot Telegram** del panel de administración.


Once configured, you can query codes via /codigo <id> or search with /buscar <palabras>. The bot uses the same database as the website, so results are consistent across both platforms.

