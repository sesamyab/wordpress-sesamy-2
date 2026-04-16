# Sesamy for WordPress (BETA)

Connect your WordPress site with [Sesamy.com](https://sesamy.com) to sell and manage access to your premium digital content through secure single purchases or subscriptions.

> **Beta** — intended for testing, evaluation, and feedback. Report issues via https://support.sesamy.com.

## Quick Start (Local Development)

Prerequisites: [Docker Desktop](https://www.docker.com/products/docker-desktop/), Node.js 18+ (recommended: use `nvm`), PHP 7.3+, [Composer](https://getcomposer.org/).

```bash
# 1. Install dependencies
nvm use
yarn install
composer install

# 2. Build frontend assets
yarn build

# 3. Start local WordPress (Docker)
yarn wp-env
```

WordPress is now running at **http://127.0.0.1:8888** (login: `admin` / `password`).

For development with hot reload:

```bash
yarn watch
```

## Linting & Tests

```bash
# PHP
composer lint          # PHPCS code style check
composer lint-fix      # Auto-fix code style issues
composer static        # PHPStan static analysis (level 8)
vendor/bin/phpunit --testdox --colors=always   # Unit tests

# JavaScript
yarn lint-js           # ESLint
yarn lint-style        # Stylelint
yarn test              # Jest
```

## Documentation

Full setup and usage guide: [Sesamy WordPress Plugin Documentation](https://developers.sesamy.com/integrations/cms/wordpress.html)

## Installation (Production)

1. Download the latest `.zip` from the [Releases page](https://github.com/sesamyab/wordpress-sesamy-2/releases).
2. In WordPress admin, go to **Plugins > Add New > Upload Plugin** and upload the `.zip`.
3. Activate the plugin and configure it under the **Sesamy** settings page.

### Requirements

- WordPress 4.9+
- PHP 7.3+
- A Sesamy Publisher account (reach out to us for initial setup)

## Building a Release

```bash
./build-zip.sh
```

This produces `sesamy-wordpress.zip` ready for distribution.

## Project Structure

```
sesamy2.php              # Plugin entry point
src/
  Admin/Settings/        # Settings registration and sanitization
  Admin/View/            # Admin UI (settings page, release notices)
  Api/                   # REST API endpoints
  Core/                  # Asset enqueuing
  Frontend/              # Content container and meta tag rendering
  Support/               # Helper functions
assets/
  js/                    # JavaScript source (admin.js, post-settings.js)
  css/                   # Stylesheets
tests/
  src/                   # PHP unit tests (mirrors src/ structure)
```
