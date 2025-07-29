# Sesamy for WordPress (BETA)

## [![Sesamy Logo](https://assets.sesamy.com/static/images/sesamy/logos/sesamy_logo_white.svg)](https://sesamy.com)

**>>> ⚠️ IMPORTANT: BETA VERSION ⚠️ <<<**

**This plugin is currently in Beta.** It is primarily intended for testing, evaluation, and feedback.

**We highly value your feedback during this phase! Please report issues via https://support.sesamy.com.**

---

## Description

Connect your WordPress site with [Sesamy.com](https://sesamy.com) to sell and manage access to your premium digital content through secure single purchases or subscriptions.

This plugin provides the core integration to:

- Connect your wordpres installation to Sesamy
- Use our built in components to lock content
- Allow users to login and manage their subscription and purchases

---

## Requirements

- WordPress `4.9` or higher
- PHP `7.2` or higher
- A **Sesamy Publisher account** Please reach out to us for the inital setup

---

## Installation

1.  **Download:** Download the latest release `.zip` file from the [Releases page](https://github.com/sesamyab/wordpress-sesamy-2/releases) on this repository.
2.  **Upload to WordPress:**
    - Log in to your WordPress admin dashboard.
    - Navigate to `Plugins` > `Add New`.
    - Click `Upload Plugin`.
    - Choose the downloaded `.zip` file and click `Install Now`.
3.  **Activate:** Click `Activate Plugin`.

_(Alternatively, experienced users can clone the repository into their `/wp-content/plugins/` directory and manage dependencies if necessary, e.g., using composer)._

---

## Configuration

1.  After activating the plugin, navigate to the **Sesamy** settings page in your WordPress admin menu.
2.  Fill out the required settings
3.  Click **Save Changes**.

---

## Development

### Running Tests

This plugin includes PHPUnit tests to ensure code quality and functionality.

#### Prerequisites

- PHP 7.2 or higher
- Composer

#### Setup

1. Install test dependencies:

   ```bash
   composer install
   ```

2. Run the test suite:

   ```bash
   vendor/bin/phpunit
   ```

   Or run with detailed output:

   ```bash
   vendor/bin/phpunit --testdox --colors=always
   ```

#### Using npm/yarn Scripts

For convenience, you can also use the npm/yarn scripts:

```bash
# Run PHP tests
yarn test:php
# or
npm run test:php

# Run PHP tests with detailed output
yarn test:php:watch
# or
npm run test:php:watch

# Run JavaScript tests (Jest)
yarn test
# or
npm run test
```

#### VS Code Integration

If you're using VS Code, you can install the "PHPUnit Test Explorer" extension to run and debug tests directly from the editor.

The project includes:

- `phpunit.xml` configuration file
- VS Code task for running tests (`Run PHPUnit tests`)
- WP_Mock for mocking WordPress functions

#### Test Structure

Tests are located in the `tests/` directory and follow the same namespace structure as the source code:

- `tests/src/Frontend/ContentContainerTest.php` - Tests for content container functionality

The test structure mirrors the source code organization:

- `tests/src/` - PHP backend tests (mirrors `src/` directory)
- `tests/frontend/` - JavaScript/browser tests (for future frontend tests)

---
