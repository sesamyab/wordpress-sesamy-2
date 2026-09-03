# Changelog

All notable changes to this project will be documented in this file. See [commit-and-tag-version](https://github.com/absolute-version/commit-and-tag-version) for commit guidelines.

## [1.10.0](https://github.com/sesamyab/wordpress-sesamy-2/compare/v1.2.19...v1.10.0) (2026-09-03)


### Features

* add [sesamy-login] shortcode ([99fdeeb](https://github.com/sesamyab/wordpress-sesamy-2/commit/99fdeeb0a6371b4200b5811e980ab26dc0216a6d))
* add classic-menus support for Sesamy Login ([8475a5c](https://github.com/sesamyab/wordpress-sesamy-2/commit/8475a5cfa652e46562fd22bf1c5f30a19dff75ed))
* add dcr and capsule support ([089fd72](https://github.com/sesamyab/wordpress-sesamy-2/commit/089fd72d58a93b4a9758a1ed29c9af9051e3c8cf))
* lock articles automatically by category or tag ([#43](https://github.com/sesamyab/wordpress-sesamy-2/issues/43)) ([5df4f06](https://github.com/sesamyab/wordpress-sesamy-2/commit/5df4f067ed2997699ccc73715a79d793380d3998))
* make the plugin installable via Composer/Packagist ([4e32c70](https://github.com/sesamyab/wordpress-sesamy-2/commit/4e32c703b4795549d1503ba92d5cf2b102c7290b))
* register sesamy/login block ([6bd2692](https://github.com/sesamyab/wordpress-sesamy-2/commit/6bd2692c89294843451b912caf7aa403205e2aed))
* sesamy connect ([9acc329](https://github.com/sesamyab/wordpress-sesamy-2/commit/9acc329ef9bcfa049f5a1416e5d4ff959927b2da))
* surface connection-required banner on key admin screens ([2ac0236](https://github.com/sesamyab/wordpress-sesamy-2/commit/2ac023646dcdf2e711a9df5b7c08b23969b525d0))


### Bug Fixes

* add husky hook ([4afe75c](https://github.com/sesamyab/wordpress-sesamy-2/commit/4afe75c04e95f24aff94b534b8a1bb3066340c0f))
* address review feedback on composer constraint and CI credentials ([b117220](https://github.com/sesamyab/wordpress-sesamy-2/commit/b1172203bda9bffb91711225fcf3b849c59e3c5c))
* address review feedback on taxonomy locking ([0d73c71](https://github.com/sesamyab/wordpress-sesamy-2/commit/0d73c71bfe0c2fcba84141057d7740dbaa958d91))
* **capsule:** address review feedback on the pinned-PEM change ([c33eaa6](https://github.com/sesamyab/wordpress-sesamy-2/commit/c33eaa628c23aaf6ae8f5adf229ef22ea684950b))
* **capsule:** pin the publisher signing key instead of registering a jwksUri ([1c0fef8](https://github.com/sesamyab/wordpress-sesamy-2/commit/1c0fef80c6396904dcebc89f87049aa89d9a3dfe))
* ci issues ([644ffa3](https://github.com/sesamyab/wordpress-sesamy-2/commit/644ffa396e1c241a6eaea01ee1827e700cbeafd4))
* close the sesamy-paywall element explicitly ([c36dbcd](https://github.com/sesamyab/wordpress-sesamy-2/commit/c36dbcdf4eaaeaeeedad394adb7006cd49008f82))
* disable husky entirely in the release workflow ([b4efdd3](https://github.com/sesamyab/wordpress-sesamy-2/commit/b4efdd38545980cb3c032d9e17c89b3c865f67ab))
* enforce REST-exposed taxonomy constraint in locked_terms sanitizer ([8582c96](https://github.com/sesamyab/wordpress-sesamy-2/commit/8582c9668d7c0ab7d463802f768162abf4a81b22))
* phpstan api_version expects string ([a262429](https://github.com/sesamyab/wordpress-sesamy-2/commit/a262429ac1688e969bf5ec2ecddff65bfad9f605))
* review comments ([0d2f0cb](https://github.com/sesamyab/wordpress-sesamy-2/commit/0d2f0cb88798e3b45e603f9ee6c043884185caf0))
* review comments ([548c3c6](https://github.com/sesamyab/wordpress-sesamy-2/commit/548c3c64f88494c993c3659beca638a7436b74ed))
* review comments ([31ff164](https://github.com/sesamyab/wordpress-sesamy-2/commit/31ff164db003d0d80b5672b0f716a15a9bf665ad))
* skip git hooks when committing built assets in the release workflow ([7a5d962](https://github.com/sesamyab/wordpress-sesamy-2/commit/7a5d962f93807f8aa5ca76f0d050eaa8c292bd02))
* synthesize sesamy_connection from legacy settings on upgrade ([8c94e03](https://github.com/sesamyab/wordpress-sesamy-2/commit/8c94e03bd7b0fc5c5dffc3f7b66eee0e0e5f0fe0))
* treat legacy stubs as not-connected on the settings page ([6ba8109](https://github.com/sesamyab/wordpress-sesamy-2/commit/6ba8109e5fa9e2e295f97cdbc5e54033f190fe12))
* update docs and add login button ([9c57c75](https://github.com/sesamyab/wordpress-sesamy-2/commit/9c57c757d231e0612b56239fefd6b999a9de9384))

## [1.9.0](https://github.com/sesamyab/wordpress-sesamy-2/compare/v1.2.19...v1.9.0) (2026-09-03)


### Features

* add [sesamy-login] shortcode ([99fdeeb](https://github.com/sesamyab/wordpress-sesamy-2/commit/99fdeeb0a6371b4200b5811e980ab26dc0216a6d))
* add classic-menus support for Sesamy Login ([8475a5c](https://github.com/sesamyab/wordpress-sesamy-2/commit/8475a5cfa652e46562fd22bf1c5f30a19dff75ed))
* add dcr and capsule support ([089fd72](https://github.com/sesamyab/wordpress-sesamy-2/commit/089fd72d58a93b4a9758a1ed29c9af9051e3c8cf))
* lock articles automatically by category or tag ([#43](https://github.com/sesamyab/wordpress-sesamy-2/issues/43)) ([5df4f06](https://github.com/sesamyab/wordpress-sesamy-2/commit/5df4f067ed2997699ccc73715a79d793380d3998))
* make the plugin installable via Composer/Packagist ([4e32c70](https://github.com/sesamyab/wordpress-sesamy-2/commit/4e32c703b4795549d1503ba92d5cf2b102c7290b))
* register sesamy/login block ([6bd2692](https://github.com/sesamyab/wordpress-sesamy-2/commit/6bd2692c89294843451b912caf7aa403205e2aed))
* sesamy connect ([9acc329](https://github.com/sesamyab/wordpress-sesamy-2/commit/9acc329ef9bcfa049f5a1416e5d4ff959927b2da))
* surface connection-required banner on key admin screens ([2ac0236](https://github.com/sesamyab/wordpress-sesamy-2/commit/2ac023646dcdf2e711a9df5b7c08b23969b525d0))


### Bug Fixes

* add husky hook ([4afe75c](https://github.com/sesamyab/wordpress-sesamy-2/commit/4afe75c04e95f24aff94b534b8a1bb3066340c0f))
* address review feedback on composer constraint and CI credentials ([b117220](https://github.com/sesamyab/wordpress-sesamy-2/commit/b1172203bda9bffb91711225fcf3b849c59e3c5c))
* address review feedback on taxonomy locking ([0d73c71](https://github.com/sesamyab/wordpress-sesamy-2/commit/0d73c71bfe0c2fcba84141057d7740dbaa958d91))
* **capsule:** address review feedback on the pinned-PEM change ([c33eaa6](https://github.com/sesamyab/wordpress-sesamy-2/commit/c33eaa628c23aaf6ae8f5adf229ef22ea684950b))
* **capsule:** pin the publisher signing key instead of registering a jwksUri ([1c0fef8](https://github.com/sesamyab/wordpress-sesamy-2/commit/1c0fef80c6396904dcebc89f87049aa89d9a3dfe))
* ci issues ([644ffa3](https://github.com/sesamyab/wordpress-sesamy-2/commit/644ffa396e1c241a6eaea01ee1827e700cbeafd4))
* close the sesamy-paywall element explicitly ([c36dbcd](https://github.com/sesamyab/wordpress-sesamy-2/commit/c36dbcdf4eaaeaeeedad394adb7006cd49008f82))
* disable husky entirely in the release workflow ([b4efdd3](https://github.com/sesamyab/wordpress-sesamy-2/commit/b4efdd38545980cb3c032d9e17c89b3c865f67ab))
* enforce REST-exposed taxonomy constraint in locked_terms sanitizer ([8582c96](https://github.com/sesamyab/wordpress-sesamy-2/commit/8582c9668d7c0ab7d463802f768162abf4a81b22))
* phpstan api_version expects string ([a262429](https://github.com/sesamyab/wordpress-sesamy-2/commit/a262429ac1688e969bf5ec2ecddff65bfad9f605))
* review comments ([0d2f0cb](https://github.com/sesamyab/wordpress-sesamy-2/commit/0d2f0cb88798e3b45e603f9ee6c043884185caf0))
* review comments ([548c3c6](https://github.com/sesamyab/wordpress-sesamy-2/commit/548c3c64f88494c993c3659beca638a7436b74ed))
* review comments ([31ff164](https://github.com/sesamyab/wordpress-sesamy-2/commit/31ff164db003d0d80b5672b0f716a15a9bf665ad))
* skip git hooks when committing built assets in the release workflow ([7a5d962](https://github.com/sesamyab/wordpress-sesamy-2/commit/7a5d962f93807f8aa5ca76f0d050eaa8c292bd02))
* synthesize sesamy_connection from legacy settings on upgrade ([8c94e03](https://github.com/sesamyab/wordpress-sesamy-2/commit/8c94e03bd7b0fc5c5dffc3f7b66eee0e0e5f0fe0))
* treat legacy stubs as not-connected on the settings page ([6ba8109](https://github.com/sesamyab/wordpress-sesamy-2/commit/6ba8109e5fa9e2e295f97cdbc5e54033f190fe12))
* update docs and add login button ([9c57c75](https://github.com/sesamyab/wordpress-sesamy-2/commit/9c57c757d231e0612b56239fefd6b999a9de9384))

## [1.8.0](https://github.com/sesamyab/wordpress-sesamy-2/compare/v1.2.19...v1.8.0) (2026-08-19)


### Features

* add [sesamy-login] shortcode ([99fdeeb](https://github.com/sesamyab/wordpress-sesamy-2/commit/99fdeeb0a6371b4200b5811e980ab26dc0216a6d))
* add classic-menus support for Sesamy Login ([8475a5c](https://github.com/sesamyab/wordpress-sesamy-2/commit/8475a5cfa652e46562fd22bf1c5f30a19dff75ed))
* add dcr and capsule support ([089fd72](https://github.com/sesamyab/wordpress-sesamy-2/commit/089fd72d58a93b4a9758a1ed29c9af9051e3c8cf))
* lock articles automatically by category or tag ([#43](https://github.com/sesamyab/wordpress-sesamy-2/issues/43)) ([5df4f06](https://github.com/sesamyab/wordpress-sesamy-2/commit/5df4f067ed2997699ccc73715a79d793380d3998))
* make the plugin installable via Composer/Packagist ([4e32c70](https://github.com/sesamyab/wordpress-sesamy-2/commit/4e32c703b4795549d1503ba92d5cf2b102c7290b))
* register sesamy/login block ([6bd2692](https://github.com/sesamyab/wordpress-sesamy-2/commit/6bd2692c89294843451b912caf7aa403205e2aed))
* sesamy connect ([9acc329](https://github.com/sesamyab/wordpress-sesamy-2/commit/9acc329ef9bcfa049f5a1416e5d4ff959927b2da))
* surface connection-required banner on key admin screens ([2ac0236](https://github.com/sesamyab/wordpress-sesamy-2/commit/2ac023646dcdf2e711a9df5b7c08b23969b525d0))


### Bug Fixes

* add husky hook ([4afe75c](https://github.com/sesamyab/wordpress-sesamy-2/commit/4afe75c04e95f24aff94b534b8a1bb3066340c0f))
* address review feedback on composer constraint and CI credentials ([b117220](https://github.com/sesamyab/wordpress-sesamy-2/commit/b1172203bda9bffb91711225fcf3b849c59e3c5c))
* address review feedback on taxonomy locking ([0d73c71](https://github.com/sesamyab/wordpress-sesamy-2/commit/0d73c71bfe0c2fcba84141057d7740dbaa958d91))
* **capsule:** address review feedback on the pinned-PEM change ([c33eaa6](https://github.com/sesamyab/wordpress-sesamy-2/commit/c33eaa628c23aaf6ae8f5adf229ef22ea684950b))
* **capsule:** pin the publisher signing key instead of registering a jwksUri ([1c0fef8](https://github.com/sesamyab/wordpress-sesamy-2/commit/1c0fef80c6396904dcebc89f87049aa89d9a3dfe))
* ci issues ([644ffa3](https://github.com/sesamyab/wordpress-sesamy-2/commit/644ffa396e1c241a6eaea01ee1827e700cbeafd4))
* close the sesamy-paywall element explicitly ([c36dbcd](https://github.com/sesamyab/wordpress-sesamy-2/commit/c36dbcdf4eaaeaeeedad394adb7006cd49008f82))
* disable husky entirely in the release workflow ([b4efdd3](https://github.com/sesamyab/wordpress-sesamy-2/commit/b4efdd38545980cb3c032d9e17c89b3c865f67ab))
* enforce REST-exposed taxonomy constraint in locked_terms sanitizer ([8582c96](https://github.com/sesamyab/wordpress-sesamy-2/commit/8582c9668d7c0ab7d463802f768162abf4a81b22))
* phpstan api_version expects string ([a262429](https://github.com/sesamyab/wordpress-sesamy-2/commit/a262429ac1688e969bf5ec2ecddff65bfad9f605))
* review comments ([0d2f0cb](https://github.com/sesamyab/wordpress-sesamy-2/commit/0d2f0cb88798e3b45e603f9ee6c043884185caf0))
* review comments ([548c3c6](https://github.com/sesamyab/wordpress-sesamy-2/commit/548c3c64f88494c993c3659beca638a7436b74ed))
* review comments ([31ff164](https://github.com/sesamyab/wordpress-sesamy-2/commit/31ff164db003d0d80b5672b0f716a15a9bf665ad))
* skip git hooks when committing built assets in the release workflow ([7a5d962](https://github.com/sesamyab/wordpress-sesamy-2/commit/7a5d962f93807f8aa5ca76f0d050eaa8c292bd02))
* synthesize sesamy_connection from legacy settings on upgrade ([8c94e03](https://github.com/sesamyab/wordpress-sesamy-2/commit/8c94e03bd7b0fc5c5dffc3f7b66eee0e0e5f0fe0))
* treat legacy stubs as not-connected on the settings page ([6ba8109](https://github.com/sesamyab/wordpress-sesamy-2/commit/6ba8109e5fa9e2e295f97cdbc5e54033f190fe12))
* update docs and add login button ([9c57c75](https://github.com/sesamyab/wordpress-sesamy-2/commit/9c57c757d231e0612b56239fefd6b999a9de9384))

## [1.7.0](https://github.com/sesamyab/wordpress-sesamy-2/compare/v1.2.19...v1.7.0) (2026-08-19)


### Features

* add [sesamy-login] shortcode ([99fdeeb](https://github.com/sesamyab/wordpress-sesamy-2/commit/99fdeeb0a6371b4200b5811e980ab26dc0216a6d))
* add classic-menus support for Sesamy Login ([8475a5c](https://github.com/sesamyab/wordpress-sesamy-2/commit/8475a5cfa652e46562fd22bf1c5f30a19dff75ed))
* add dcr and capsule support ([089fd72](https://github.com/sesamyab/wordpress-sesamy-2/commit/089fd72d58a93b4a9758a1ed29c9af9051e3c8cf))
* lock articles automatically by category or tag ([#43](https://github.com/sesamyab/wordpress-sesamy-2/issues/43)) ([5df4f06](https://github.com/sesamyab/wordpress-sesamy-2/commit/5df4f067ed2997699ccc73715a79d793380d3998))
* make the plugin installable via Composer/Packagist ([4e32c70](https://github.com/sesamyab/wordpress-sesamy-2/commit/4e32c703b4795549d1503ba92d5cf2b102c7290b))
* register sesamy/login block ([6bd2692](https://github.com/sesamyab/wordpress-sesamy-2/commit/6bd2692c89294843451b912caf7aa403205e2aed))
* sesamy connect ([9acc329](https://github.com/sesamyab/wordpress-sesamy-2/commit/9acc329ef9bcfa049f5a1416e5d4ff959927b2da))
* surface connection-required banner on key admin screens ([2ac0236](https://github.com/sesamyab/wordpress-sesamy-2/commit/2ac023646dcdf2e711a9df5b7c08b23969b525d0))


### Bug Fixes

* add husky hook ([4afe75c](https://github.com/sesamyab/wordpress-sesamy-2/commit/4afe75c04e95f24aff94b534b8a1bb3066340c0f))
* address review feedback on composer constraint and CI credentials ([b117220](https://github.com/sesamyab/wordpress-sesamy-2/commit/b1172203bda9bffb91711225fcf3b849c59e3c5c))
* address review feedback on taxonomy locking ([0d73c71](https://github.com/sesamyab/wordpress-sesamy-2/commit/0d73c71bfe0c2fcba84141057d7740dbaa958d91))
* ci issues ([644ffa3](https://github.com/sesamyab/wordpress-sesamy-2/commit/644ffa396e1c241a6eaea01ee1827e700cbeafd4))
* close the sesamy-paywall element explicitly ([c36dbcd](https://github.com/sesamyab/wordpress-sesamy-2/commit/c36dbcdf4eaaeaeeedad394adb7006cd49008f82))
* disable husky entirely in the release workflow ([b4efdd3](https://github.com/sesamyab/wordpress-sesamy-2/commit/b4efdd38545980cb3c032d9e17c89b3c865f67ab))
* enforce REST-exposed taxonomy constraint in locked_terms sanitizer ([8582c96](https://github.com/sesamyab/wordpress-sesamy-2/commit/8582c9668d7c0ab7d463802f768162abf4a81b22))
* phpstan api_version expects string ([a262429](https://github.com/sesamyab/wordpress-sesamy-2/commit/a262429ac1688e969bf5ec2ecddff65bfad9f605))
* review comments ([0d2f0cb](https://github.com/sesamyab/wordpress-sesamy-2/commit/0d2f0cb88798e3b45e603f9ee6c043884185caf0))
* review comments ([548c3c6](https://github.com/sesamyab/wordpress-sesamy-2/commit/548c3c64f88494c993c3659beca638a7436b74ed))
* review comments ([31ff164](https://github.com/sesamyab/wordpress-sesamy-2/commit/31ff164db003d0d80b5672b0f716a15a9bf665ad))
* skip git hooks when committing built assets in the release workflow ([7a5d962](https://github.com/sesamyab/wordpress-sesamy-2/commit/7a5d962f93807f8aa5ca76f0d050eaa8c292bd02))
* synthesize sesamy_connection from legacy settings on upgrade ([8c94e03](https://github.com/sesamyab/wordpress-sesamy-2/commit/8c94e03bd7b0fc5c5dffc3f7b66eee0e0e5f0fe0))
* treat legacy stubs as not-connected on the settings page ([6ba8109](https://github.com/sesamyab/wordpress-sesamy-2/commit/6ba8109e5fa9e2e295f97cdbc5e54033f190fe12))
* update docs and add login button ([9c57c75](https://github.com/sesamyab/wordpress-sesamy-2/commit/9c57c757d231e0612b56239fefd6b999a9de9384))

## [1.6.0](https://github.com/sesamyab/wordpress-sesamy-2/compare/v1.2.19...v1.6.0) (2026-07-23)


### Features

* add [sesamy-login] shortcode ([99fdeeb](https://github.com/sesamyab/wordpress-sesamy-2/commit/99fdeeb0a6371b4200b5811e980ab26dc0216a6d))
* add classic-menus support for Sesamy Login ([8475a5c](https://github.com/sesamyab/wordpress-sesamy-2/commit/8475a5cfa652e46562fd22bf1c5f30a19dff75ed))
* add dcr and capsule support ([089fd72](https://github.com/sesamyab/wordpress-sesamy-2/commit/089fd72d58a93b4a9758a1ed29c9af9051e3c8cf))
* lock articles automatically by category or tag ([#43](https://github.com/sesamyab/wordpress-sesamy-2/issues/43)) ([5df4f06](https://github.com/sesamyab/wordpress-sesamy-2/commit/5df4f067ed2997699ccc73715a79d793380d3998))
* make the plugin installable via Composer/Packagist ([4e32c70](https://github.com/sesamyab/wordpress-sesamy-2/commit/4e32c703b4795549d1503ba92d5cf2b102c7290b))
* register sesamy/login block ([6bd2692](https://github.com/sesamyab/wordpress-sesamy-2/commit/6bd2692c89294843451b912caf7aa403205e2aed))
* sesamy connect ([9acc329](https://github.com/sesamyab/wordpress-sesamy-2/commit/9acc329ef9bcfa049f5a1416e5d4ff959927b2da))
* surface connection-required banner on key admin screens ([2ac0236](https://github.com/sesamyab/wordpress-sesamy-2/commit/2ac023646dcdf2e711a9df5b7c08b23969b525d0))


### Bug Fixes

* add husky hook ([4afe75c](https://github.com/sesamyab/wordpress-sesamy-2/commit/4afe75c04e95f24aff94b534b8a1bb3066340c0f))
* address review feedback on composer constraint and CI credentials ([b117220](https://github.com/sesamyab/wordpress-sesamy-2/commit/b1172203bda9bffb91711225fcf3b849c59e3c5c))
* address review feedback on taxonomy locking ([0d73c71](https://github.com/sesamyab/wordpress-sesamy-2/commit/0d73c71bfe0c2fcba84141057d7740dbaa958d91))
* ci issues ([644ffa3](https://github.com/sesamyab/wordpress-sesamy-2/commit/644ffa396e1c241a6eaea01ee1827e700cbeafd4))
* disable husky entirely in the release workflow ([b4efdd3](https://github.com/sesamyab/wordpress-sesamy-2/commit/b4efdd38545980cb3c032d9e17c89b3c865f67ab))
* enforce REST-exposed taxonomy constraint in locked_terms sanitizer ([8582c96](https://github.com/sesamyab/wordpress-sesamy-2/commit/8582c9668d7c0ab7d463802f768162abf4a81b22))
* phpstan api_version expects string ([a262429](https://github.com/sesamyab/wordpress-sesamy-2/commit/a262429ac1688e969bf5ec2ecddff65bfad9f605))
* review comments ([0d2f0cb](https://github.com/sesamyab/wordpress-sesamy-2/commit/0d2f0cb88798e3b45e603f9ee6c043884185caf0))
* review comments ([548c3c6](https://github.com/sesamyab/wordpress-sesamy-2/commit/548c3c64f88494c993c3659beca638a7436b74ed))
* review comments ([31ff164](https://github.com/sesamyab/wordpress-sesamy-2/commit/31ff164db003d0d80b5672b0f716a15a9bf665ad))
* skip git hooks when committing built assets in the release workflow ([7a5d962](https://github.com/sesamyab/wordpress-sesamy-2/commit/7a5d962f93807f8aa5ca76f0d050eaa8c292bd02))
* synthesize sesamy_connection from legacy settings on upgrade ([8c94e03](https://github.com/sesamyab/wordpress-sesamy-2/commit/8c94e03bd7b0fc5c5dffc3f7b66eee0e0e5f0fe0))
* treat legacy stubs as not-connected on the settings page ([6ba8109](https://github.com/sesamyab/wordpress-sesamy-2/commit/6ba8109e5fa9e2e295f97cdbc5e54033f190fe12))
* update docs and add login button ([9c57c75](https://github.com/sesamyab/wordpress-sesamy-2/commit/9c57c757d231e0612b56239fefd6b999a9de9384))

## [1.5.0](https://github.com/sesamyab/wordpress-sesamy-2/compare/v1.2.19...v1.5.0) (2026-07-08)


### Features

* add [sesamy-login] shortcode ([99fdeeb](https://github.com/sesamyab/wordpress-sesamy-2/commit/99fdeeb0a6371b4200b5811e980ab26dc0216a6d))
* add classic-menus support for Sesamy Login ([8475a5c](https://github.com/sesamyab/wordpress-sesamy-2/commit/8475a5cfa652e46562fd22bf1c5f30a19dff75ed))
* add dcr and capsule support ([089fd72](https://github.com/sesamyab/wordpress-sesamy-2/commit/089fd72d58a93b4a9758a1ed29c9af9051e3c8cf))
* make the plugin installable via Composer/Packagist ([4e32c70](https://github.com/sesamyab/wordpress-sesamy-2/commit/4e32c703b4795549d1503ba92d5cf2b102c7290b))
* register sesamy/login block ([6bd2692](https://github.com/sesamyab/wordpress-sesamy-2/commit/6bd2692c89294843451b912caf7aa403205e2aed))
* sesamy connect ([9acc329](https://github.com/sesamyab/wordpress-sesamy-2/commit/9acc329ef9bcfa049f5a1416e5d4ff959927b2da))
* surface connection-required banner on key admin screens ([2ac0236](https://github.com/sesamyab/wordpress-sesamy-2/commit/2ac023646dcdf2e711a9df5b7c08b23969b525d0))


### Bug Fixes

* add husky hook ([4afe75c](https://github.com/sesamyab/wordpress-sesamy-2/commit/4afe75c04e95f24aff94b534b8a1bb3066340c0f))
* address review feedback on composer constraint and CI credentials ([b117220](https://github.com/sesamyab/wordpress-sesamy-2/commit/b1172203bda9bffb91711225fcf3b849c59e3c5c))
* ci issues ([644ffa3](https://github.com/sesamyab/wordpress-sesamy-2/commit/644ffa396e1c241a6eaea01ee1827e700cbeafd4))
* disable husky entirely in the release workflow ([b4efdd3](https://github.com/sesamyab/wordpress-sesamy-2/commit/b4efdd38545980cb3c032d9e17c89b3c865f67ab))
* phpstan api_version expects string ([a262429](https://github.com/sesamyab/wordpress-sesamy-2/commit/a262429ac1688e969bf5ec2ecddff65bfad9f605))
* review comments ([548c3c6](https://github.com/sesamyab/wordpress-sesamy-2/commit/548c3c64f88494c993c3659beca638a7436b74ed))
* review comments ([31ff164](https://github.com/sesamyab/wordpress-sesamy-2/commit/31ff164db003d0d80b5672b0f716a15a9bf665ad))
* skip git hooks when committing built assets in the release workflow ([7a5d962](https://github.com/sesamyab/wordpress-sesamy-2/commit/7a5d962f93807f8aa5ca76f0d050eaa8c292bd02))
* synthesize sesamy_connection from legacy settings on upgrade ([8c94e03](https://github.com/sesamyab/wordpress-sesamy-2/commit/8c94e03bd7b0fc5c5dffc3f7b66eee0e0e5f0fe0))
* treat legacy stubs as not-connected on the settings page ([6ba8109](https://github.com/sesamyab/wordpress-sesamy-2/commit/6ba8109e5fa9e2e295f97cdbc5e54033f190fe12))
* update docs and add login button ([9c57c75](https://github.com/sesamyab/wordpress-sesamy-2/commit/9c57c757d231e0612b56239fefd6b999a9de9384))

## [1.4.0](https://github.com/sesamyab/wordpress-sesamy-2/compare/v1.2.19...v1.4.0) (2026-07-08)


### Features

* add [sesamy-login] shortcode ([99fdeeb](https://github.com/sesamyab/wordpress-sesamy-2/commit/99fdeeb0a6371b4200b5811e980ab26dc0216a6d))
* add classic-menus support for Sesamy Login ([8475a5c](https://github.com/sesamyab/wordpress-sesamy-2/commit/8475a5cfa652e46562fd22bf1c5f30a19dff75ed))
* add dcr and capsule support ([089fd72](https://github.com/sesamyab/wordpress-sesamy-2/commit/089fd72d58a93b4a9758a1ed29c9af9051e3c8cf))
* make the plugin installable via Composer/Packagist ([4e32c70](https://github.com/sesamyab/wordpress-sesamy-2/commit/4e32c703b4795549d1503ba92d5cf2b102c7290b))
* register sesamy/login block ([6bd2692](https://github.com/sesamyab/wordpress-sesamy-2/commit/6bd2692c89294843451b912caf7aa403205e2aed))
* sesamy connect ([9acc329](https://github.com/sesamyab/wordpress-sesamy-2/commit/9acc329ef9bcfa049f5a1416e5d4ff959927b2da))
* surface connection-required banner on key admin screens ([2ac0236](https://github.com/sesamyab/wordpress-sesamy-2/commit/2ac023646dcdf2e711a9df5b7c08b23969b525d0))


### Bug Fixes

* add husky hook ([4afe75c](https://github.com/sesamyab/wordpress-sesamy-2/commit/4afe75c04e95f24aff94b534b8a1bb3066340c0f))
* address review feedback on composer constraint and CI credentials ([b117220](https://github.com/sesamyab/wordpress-sesamy-2/commit/b1172203bda9bffb91711225fcf3b849c59e3c5c))
* ci issues ([644ffa3](https://github.com/sesamyab/wordpress-sesamy-2/commit/644ffa396e1c241a6eaea01ee1827e700cbeafd4))
* disable husky entirely in the release workflow ([b4efdd3](https://github.com/sesamyab/wordpress-sesamy-2/commit/b4efdd38545980cb3c032d9e17c89b3c865f67ab))
* phpstan api_version expects string ([a262429](https://github.com/sesamyab/wordpress-sesamy-2/commit/a262429ac1688e969bf5ec2ecddff65bfad9f605))
* review comments ([548c3c6](https://github.com/sesamyab/wordpress-sesamy-2/commit/548c3c64f88494c993c3659beca638a7436b74ed))
* review comments ([31ff164](https://github.com/sesamyab/wordpress-sesamy-2/commit/31ff164db003d0d80b5672b0f716a15a9bf665ad))
* skip git hooks when committing built assets in the release workflow ([7a5d962](https://github.com/sesamyab/wordpress-sesamy-2/commit/7a5d962f93807f8aa5ca76f0d050eaa8c292bd02))
* synthesize sesamy_connection from legacy settings on upgrade ([8c94e03](https://github.com/sesamyab/wordpress-sesamy-2/commit/8c94e03bd7b0fc5c5dffc3f7b66eee0e0e5f0fe0))
* treat legacy stubs as not-connected on the settings page ([6ba8109](https://github.com/sesamyab/wordpress-sesamy-2/commit/6ba8109e5fa9e2e295f97cdbc5e54033f190fe12))
* update docs and add login button ([9c57c75](https://github.com/sesamyab/wordpress-sesamy-2/commit/9c57c757d231e0612b56239fefd6b999a9de9384))

## [1.3.0](https://github.com/sesamyab/wordpress-sesamy-2/compare/v1.2.19...v1.3.0) (2026-07-08)


### Features

* add [sesamy-login] shortcode ([99fdeeb](https://github.com/sesamyab/wordpress-sesamy-2/commit/99fdeeb0a6371b4200b5811e980ab26dc0216a6d))
* add classic-menus support for Sesamy Login ([8475a5c](https://github.com/sesamyab/wordpress-sesamy-2/commit/8475a5cfa652e46562fd22bf1c5f30a19dff75ed))
* add dcr and capsule support ([089fd72](https://github.com/sesamyab/wordpress-sesamy-2/commit/089fd72d58a93b4a9758a1ed29c9af9051e3c8cf))
* make the plugin installable via Composer/Packagist ([4e32c70](https://github.com/sesamyab/wordpress-sesamy-2/commit/4e32c703b4795549d1503ba92d5cf2b102c7290b))
* register sesamy/login block ([6bd2692](https://github.com/sesamyab/wordpress-sesamy-2/commit/6bd2692c89294843451b912caf7aa403205e2aed))
* sesamy connect ([9acc329](https://github.com/sesamyab/wordpress-sesamy-2/commit/9acc329ef9bcfa049f5a1416e5d4ff959927b2da))
* surface connection-required banner on key admin screens ([2ac0236](https://github.com/sesamyab/wordpress-sesamy-2/commit/2ac023646dcdf2e711a9df5b7c08b23969b525d0))


### Bug Fixes

* add husky hook ([4afe75c](https://github.com/sesamyab/wordpress-sesamy-2/commit/4afe75c04e95f24aff94b534b8a1bb3066340c0f))
* address review feedback on composer constraint and CI credentials ([b117220](https://github.com/sesamyab/wordpress-sesamy-2/commit/b1172203bda9bffb91711225fcf3b849c59e3c5c))
* ci issues ([644ffa3](https://github.com/sesamyab/wordpress-sesamy-2/commit/644ffa396e1c241a6eaea01ee1827e700cbeafd4))
* disable husky entirely in the release workflow ([b4efdd3](https://github.com/sesamyab/wordpress-sesamy-2/commit/b4efdd38545980cb3c032d9e17c89b3c865f67ab))
* phpstan api_version expects string ([a262429](https://github.com/sesamyab/wordpress-sesamy-2/commit/a262429ac1688e969bf5ec2ecddff65bfad9f605))
* review comments ([548c3c6](https://github.com/sesamyab/wordpress-sesamy-2/commit/548c3c64f88494c993c3659beca638a7436b74ed))
* review comments ([31ff164](https://github.com/sesamyab/wordpress-sesamy-2/commit/31ff164db003d0d80b5672b0f716a15a9bf665ad))
* skip git hooks when committing built assets in the release workflow ([7a5d962](https://github.com/sesamyab/wordpress-sesamy-2/commit/7a5d962f93807f8aa5ca76f0d050eaa8c292bd02))
* synthesize sesamy_connection from legacy settings on upgrade ([8c94e03](https://github.com/sesamyab/wordpress-sesamy-2/commit/8c94e03bd7b0fc5c5dffc3f7b66eee0e0e5f0fe0))
* treat legacy stubs as not-connected on the settings page ([6ba8109](https://github.com/sesamyab/wordpress-sesamy-2/commit/6ba8109e5fa9e2e295f97cdbc5e54033f190fe12))
* update docs and add login button ([9c57c75](https://github.com/sesamyab/wordpress-sesamy-2/commit/9c57c757d231e0612b56239fefd6b999a9de9384))

## 1.2.19 (2025-10-13)

## 1.2.18 (2025-09-12)

## 1.2.17 (2025-07-29)

## 1.2.16 (2025-07-29)

## 1.2.15 (2025-06-05)

## 1.2.14 (2025-05-23)

## 1.2.13 (2025-04-15)

## 1.2.12 (2025-04-15)

## 1.2.11 (2025-04-14)

## 1.2.10 (2025-04-14)

## 1.2.9 (2025-04-14)

## 1.2.8 (2025-04-14)

## 1.2.7 (2025-04-14)

## 1.2.6 (2025-04-14)

## 1.2.5 (2025-04-10)


### Bug Fixes

* file structure review notes ([e37510e](https://github.com/sesamyab/wordpress-sesamy-2/commit/e37510ea4579fef45a85afc0e9f4c3c5df820853))

## 1.2.4 (2025-04-10)

## 1.2.3 (2025-04-08)

## 1.2.2 (2025-04-07)


### Bug Fixes

* development mode boolean ([fbb84f7](https://github.com/sesamyab/wordpress-sesamy-2/commit/fbb84f78a785c279f291e876648186e75ae07fe1))

## 1.2.1 (2025-04-07)

## 1.2.0 (2025-04-07)


### Features

* release notifaction ([2bfeb66](https://github.com/sesamyab/wordpress-sesamy-2/commit/2bfeb66c65795dbc95c5c65815af0378aa6e9120))

## 1.1.10 (2025-04-04)

## 1.1.9 (2025-04-04)

## 1.1.8 (2025-04-04)

## 1.1.7 (2025-04-04)

## [1.1.6](https://github.com/sesamyab/wordpress-sesamy-2/compare/v1.1.5...v1.1.6) (2025-04-04)

## [1.1.5](https://github.com/sesamyab/wordpress-sesamy-2/compare/v1.1.4...v1.1.5) (2025-04-04)

## 1.1.4 (2025-04-04)


### Bug Fixes

* merged ([0e8fe7c](https://github.com/sesamyab/wordpress-sesamy-2/commit/0e8fe7c10863c0a5c5748f3da00096777f4bb986))

## 1.1.3 (2025-04-04)

## [1.1.2](https://github.com/sesamyab/wordpress-sesamy-2/compare/v1.2.1...v1.1.2) (2025-04-04)

### Bug Fixes

- resolve merge conflicts ([c149d87](https://github.com/sesamyab/wordpress-sesamy-2/commit/c149d872272d1125265dafb8173ecdad300f4e68))

## 1.1.1 (2025-04-04)

### 1.1.1 (2025-04-04)
