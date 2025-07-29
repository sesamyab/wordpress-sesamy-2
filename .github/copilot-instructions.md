# Copilot Instructions for Sesamy WordPress Plugin

This document contains important context and guidelines for working with the Sesamy WordPress plugin codebase.

## Project Overview

The Sesamy WordPress plugin integrates WordPress sites with Sesamy.com for selling and managing access to premium digital content through secure single purchases or subscriptions.

## Key Architecture Decisions

### Lock Mode Logic
- **Public articles** (not locked) always use `'embed'` lock mode, regardless of plugin settings
- **Locked articles** use the lock mode specified in plugin settings (`'encode'` or `'embed'`)
- This logic is implemented in `src/Frontend/ContentContainer.php` in the `process_content()` method

### Code Organization
- `src/` - Main plugin source code (PHP backend)
- `src/Frontend/` - PHP classes that handle frontend output generation (still backend code)
- `assets/js/` - JavaScript frontend code
- `tests/src/` - PHP unit tests (mirrors `src/` structure)
- `tests/frontend/` - JavaScript/browser tests (future)

## Testing Setup

### PHP Tests (PHPUnit + WP_Mock)
- **Framework**: PHPUnit 9.6.23 with WP_Mock for WordPress function mocking
- **Location**: `tests/src/` (mirrors source structure)
- **Configuration**: `phpunit.xml`
- **Run commands**:
  - Direct: `vendor/bin/phpunit`
  - With output: `vendor/bin/phpunit --testdox --colors=always`
  - Via npm/yarn: `yarn test:php` or `yarn test:php:watch`

### JavaScript Tests (Jest)
- **Framework**: Jest with jsdom environment
- **Run command**: `yarn test`
- **Currently**: No JS tests exist, passes with `--passWithNoTests`

### Test Writing Guidelines
- Use the `setupCommonMocks()` helper method for WordPress function mocking
- Mock all WordPress functions used in the code under test
- Follow the existing test structure for consistency
- Tests should focus on behavior, not implementation details

## Development Workflow

### Code Standards
- **PHP**: WordPress Coding Standards (enforced via PHPCS/PHPCBF)
- **Validation commands**:
  - Fix issues: `vendor/bin/phpcbf <file>`
  - Check issues: `vendor/bin/phpcs <file>`
- **Key rules**: Use Yoda conditions (`'value' === $variable`)

### VS Code Integration
- PHPUnit Test Explorer extension recommended for running tests
- VS Code task available: "Run PHPUnit tests"
- Test runner integration available in VS Code

## Important Files

### Core Plugin Files
- `src/Frontend/ContentContainer.php` - Main content processing logic
- `src/Admin/Settings/Core.php` - Plugin settings management
- `sesamy2.php` - Main plugin file

### Configuration Files
- `phpunit.xml` - PHPUnit test configuration
- `phpcs.xml` - PHP CodeSniffer configuration
- `composer.json` - PHP dependencies
- `package.json` - Node.js dependencies and scripts

### Ignored Files
- `.phpunit.result.cache` - PHPUnit cache (in .gitignore)
- `vendor/` - Composer dependencies
- `node_modules/` - npm dependencies

## Common Patterns

### WordPress Function Mocking
```php
WP_Mock::userFunction('function_name', [
    'args' => ['expected', 'arguments'],
    'return' => 'expected_return_value',
]);
```

### Test Structure
```php
public function test_feature_behavior() {
    // Setup
    $this->setupCommonMocks($postId, $isLocked, $lockMode);
    
    // Execute
    $result = $this->contentContainer->method($input);
    
    // Assert
    $this->assertStringContainsString('expected', $result);
}
```

### Lock Mode Check Pattern
```php
// Always use embed for public articles
if ( empty( $is_locked ) || '0' === $is_locked || false === $is_locked ) {
    $lock_mode = 'embed';
} else {
    $lock_mode = get_sesamy_setting( 'lock_mode' );
}
```

## Dependencies

### PHP (Composer)
- `phpunit/phpunit` - Unit testing framework
- `10up/wp_mock` - WordPress function mocking
- Various WordPress coding standard packages

### JavaScript (npm/yarn)
- `10up-toolkit` - Build tools and linting
- `jest-environment-jsdom` - Jest DOM environment
- WordPress packages for frontend functionality

## Development Scripts

### npm/yarn Scripts
- `yarn test` - Run JavaScript tests (Jest)
- `yarn test:php` - Run PHP tests (PHPUnit)
- `yarn test:php:watch` - Run PHP tests with detailed output
- `yarn build` - Build production assets
- `yarn watch` - Development build with hot reload
- `yarn lint-js` / `yarn lint-style` - Code linting

## Branch Strategy

- Main development happens on feature branches
- Current work on `ma/lockmode-embed-public-articles` branch
- Tests should pass before merging to main

## Notes for Future Sessions

1. **Test Organization**: Tests are properly organized to mirror source structure
2. **Mocking**: Use the established `setupCommonMocks()` pattern for consistency
3. **Code Standards**: Always run PHPCBF before committing PHP changes
4. **Lock Mode Logic**: Remember that public articles always use 'embed' regardless of settings
5. **Documentation**: Keep README.md updated with any new testing or development procedures

## Troubleshooting

### Common Issues
- **Jest environment errors**: Ensure `jest-environment-jsdom` is installed
- **WP_Mock errors**: Check that all WordPress functions are properly mocked
- **PHPCS errors**: Run `vendor/bin/phpcbf` to auto-fix formatting issues
- **Node version**: Project requires Node.js >= 18.0.0

### Test Debugging
- Use `--testdox` flag for readable test output
- VS Code Test Explorer provides good debugging interface
- Check `.phpunit.result.cache` is in .gitignore to avoid conflicts
