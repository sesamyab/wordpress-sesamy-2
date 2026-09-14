# Sesamy for WordPress (BETA)

Connect your WordPress site to [Sesamy.com](https://sesamy.com) to sell and manage access to your premium digital content through single purchases or subscriptions. The plugin handles the publisher side of the integration: marking content as gated, encrypting it for delivery, attaching the metadata that Sesamy's paywall strategies need, and rendering the paywall + reader unlock UI.

> **Beta** — intended for testing, evaluation, and feedback. Report issues via https://support.sesamy.com.

---

## Table of contents

- [What the plugin does](#what-the-plugin-does)
- [Requirements](#requirements)
- [Installation](#installation)
- [Connecting to Sesamy](#connecting-to-sesamy)
- [Plugin settings](#plugin-settings)
  - [Pre-connect: Development mode](#pre-connect-development-mode)
  - [General: Content Types](#general-content-types)
  - [General: Default Paywall](#general-default-paywall)
  - [General: Default Pass](#general-default-pass)
  - [General: Automatic locking](#general-automatic-locking)
  - [Advanced: Lock Mode](#advanced-lock-mode)
  - [Advanced: First-party proxy](#advanced-first-party-proxy)
  - [Advanced: API token](#advanced-api-token)
- [Publisher registration](#publisher-registration)
- [Per-post controls](#per-post-controls)
- [Rendering Sesamy Login](#rendering-sesamy-login)
- [Frontend behavior](#frontend-behavior)
- [Hooks](#hooks)
- [Local development](#local-development)
- [Linting and tests](#linting-and-tests)
- [Building a release](#building-a-release)
- [Project structure](#project-structure)
- [Documentation and support](#documentation-and-support)

---

## What the plugin does

Once connected, the plugin:

1. Adds a **Sesamy** menu in WP admin where you connect the site, pick the default paywall and pass, and decide which post types are gateable.
2. Adds **per-post controls** (Block Editor sidebar, Classic Editor meta box, Quick Edit, and Bulk Edit) to lock individual posts and override defaults, plus **automatic locking** by category or tag for whole content sections.
3. Emits Sesamy meta tags in `<head>` — vendor id, default pass, currency, per-post access level, price, and any locked-content redirect — so the Sesamy frontend bundle can identify the publisher and the article.
4. Wraps the post content in a Sesamy container at render time so the bundle can apply the paywall, swap in unlocked content for entitled readers, or redirect to an alternative URL.
5. Optionally proxies Sesamy auth and API traffic through your own domain (`/sesamy/api/*`, `/sesamy/auth/*`) for first-party cookies and ad-blocker resilience.
6. Optionally encrypts gated regions on save using **Capsule** (server-side AES-256-GCM with sealed keys) so ciphertext — not plaintext — is what ships in the page HTML.

Access decisions (who can read which article) live in **paywall strategies on Sesamy**, not in WordPress. The plugin's job is to attach the right signals to each page; the strategy decides.

---

## Requirements

- WordPress 4.9 or newer
- PHP 8.3 or newer
- A Sesamy publisher account (contact Sesamy for initial onboarding)

---

## Installation

### Production install (zip upload)

1. Download the latest `.zip` from the [Releases page](https://github.com/sesamyab/wordpress-sesamy-2/releases).
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin** and upload the `.zip`.
3. Activate the plugin. A new top-level **Sesamy** menu appears in the admin sidebar.
4. Open **Sesamy** and follow [Connecting to Sesamy](#connecting-to-sesamy).

### Composer install

For Composer-managed sites (Bedrock and similar), the plugin is published on
[Packagist](https://packagist.org/packages/sesamy/sesamy-wordpress):

```bash
composer require sesamy/sesamy-wordpress
```

It is typed as a `wordpress-plugin`, so [composer/installers](https://github.com/composer/installers)
places it under your plugins directory (e.g. `wp-content/plugins/sesamy-wordpress`).
Released tags ship with the built frontend assets bundled, so there's no
`yarn build` step — activate the plugin and continue with
[Connecting to Sesamy](#connecting-to-sesamy).

### Local development install

See [Local development](#local-development) below.

---

## Connecting to Sesamy

The Sesamy admin page opens in a "not connected" state. You have two options:

- **Connect to Sesamy** (recommended) — clicks through an OAuth-style handshake with `connect.sesamy.com`. You'll see a confirmation screen showing the domain that's connecting, then return to WP with credentials provisioned. The site's canonical domain is also automatically registered as a trusted publisher (see [Publisher registration](#publisher-registration)).
- **Disconnect** — once connected, the same panel exposes a Disconnect button that revokes the integration server-side and wipes the locally stored credentials.

What gets stored after a successful connect:

| Field | Purpose |
|---|---|
| `client_id` | The M2M credential identifying this WP install to Sesamy |
| `tenant` (vendor id) | Stable publisher identifier emitted in the `sesamy:vendor-id` meta tag |
| `domain` | The canonical site domain that was connected |
| `environment` | `prod` or `dev` — which Sesamy cluster the credential lives on |
| `connected_at`, `connected_by` | Audit fields displayed on the settings page |

The connection status panel at the top of the Sesamy settings page shows all of these.

---

## Plugin settings

The settings page at **Sesamy → Settings** is split into three sections. Pre-connect fields appear before you connect; General and Advanced only render once a connection is established (most settings depend on data fetched from your Sesamy account).

### Pre-connect: Development mode

- **Setting key:** `development_mode`
- **Type:** Checkbox
- **Default:** Off (production cluster)
- **Visible only on local installs** (`local`/`development` `WP_ENVIRONMENT_TYPE`, or `.local` / `.test` / `localhost` / `127.0.0.1` host)

Switches every Sesamy URL the plugin emits from `*.sesamy.com` to `*.sesamy.dev` (api2, auth2, scripts, js, checkout, embed). Must be set **before** clicking Connect, because the connect flow has to know which cluster to register the credential against.

If you toggle it after connecting, you'll need to disconnect and reconnect — credentials minted on `.com` will not authenticate against `.dev` and vice-versa.

This setting is intentionally hidden on production installs. Production publishers should never need to touch it, and exposing it would invite accidental flips.

### General: Content Types

- **Setting key:** `enabled_content_types`
- **Type:** Checkbox list of public post types (excludes `attachment`)
- **Default:** None enabled

Selects which WordPress post types the plugin operates on. For each enabled post type:

- The Sesamy meta box, Quick Edit, and Bulk Edit fields appear on its admin screens.
- Custom-fields support is added (so per-post lock metadata can be saved).
- An extra **Sesamy** column shows up in the post list, with lock/single-purchase indicators.
- The frontend `the_content` filter wraps singular pages of that post type in a Sesamy article container.

Post types that are not in this list are completely untouched by the plugin.

### General: Default Paywall

- **Setting key:** `default_paywall`
- **Type:** Select (live-fetched from your Sesamy account)
- **Default:** None

The paywall configuration that's served to readers when a locked post does not have a per-post override. The dropdown is populated by calling the Sesamy management API at render time, so it reflects exactly what's in your Sesamy dashboard.

If a previously-saved paywall id is no longer returned by the API (e.g. it was deleted in the dashboard), it's still preserved as a "(not found)" option so saving the form doesn't silently clear it.

If the management API call fails, an error message is shown inline and the existing value is kept.

### General: Default Pass

- **Setting key:** `default_pass`
- **Type:** Select (live-fetched from your Sesamy account)
- **Default:** None

The Sesamy pass (entitlement bundle) that's announced to the frontend bundle via the `sesamy:pass` meta tag. Used by the Sesamy reader UI to tell users what subscription unlocks the content. Like `default_paywall`, the dropdown is populated live from the management API and previously-saved-but-deleted SKUs are preserved as "(not found)".

### General: Automatic locking

- **Setting key:** `locked_terms`
- **Type:** Checkbox list of taxonomy terms, grouped per taxonomy
- **Default:** None

Locks every article carrying any of the selected terms — categories, tags, or terms of any public, REST-exposed custom taxonomy registered on the enabled content types. This is the recommended path when migrating from rule-based membership plugins (MemberPress, Paid Memberships Pro, Restrict Content Pro): recreate the old "protect category X" rule as one term selection and the entire archive is locked retroactively, without editing any posts.

How it combines with the per-post toggle:

- **Additive (OR)** — an article is locked when its per-post toggle is on *or* it has a selected term. A term rule can lock, never unlock.
- **Non-destructive** — the rule is evaluated at render time and never writes the `_sesamy_locked` post meta. Remove the term (or deselect it here) and each post falls back to whatever its own toggle says.
- **Consistent everywhere** — the same effective state drives frontend rendering (all lock modes, including Capsule encryption), the `<head>` meta tags, the REST endpoint, and the admin UI.

In the editor, a term-locked post shows its lock toggle checked and disabled with a note naming the term ("Locked automatically by category 'Premium'"); the per-post fields (single purchase, custom paywall URL, redirect) remain editable. The post list column distinguishes the source: `🔒 Locked` for the per-post toggle vs `🔒 Locked (Category: Premium)` for term rules.

Notes:

- Because Bulk Edit only writes the per-post meta, setting "Not Locked" in bulk does not unlock term-locked posts — the column will keep showing the term as the lock source.
- On sites with full-page caching, flush the cache after changing this setting; it affects every article with the selected terms.
- Developers can hook the `sesamy_is_post_locked` filter (`bool $locked, int $post_id`) to add bespoke lock rules (by author, legacy plugin meta, etc.); filter-driven locks display as `Locked (filter)` in the post list. See [Hooks](#hooks).

### Advanced: Lock Mode

- **Setting key:** `lock_mode`
- **Type:** Select
- **Default:** `capsule` (server-side encryption)
- **Options:**
  - **Server-side encryption (`capsule`)** — content is encrypted at publish time with AES-256-GCM, sealed per issuer, and shipped as ciphertext inside the page HTML. The Sesamy bundle requests an unseal from the Sesamy issuer; on success the browser decrypts in-place. Plaintext never appears in the rendered HTML, REST output, or any cache layer. **Recommended for premium content.**
  - **Embed (`embed`)** — gated content is served in plaintext inside a `<sesamy-content-container>` slot. The bundle hides it behind the paywall in the DOM and reveals it for entitled readers. Easy to set up, but the plaintext is present in the HTML source.
  - **Encode (`encode`)** — gated content is base64-encoded inside the slot. A trivial obfuscation (anyone can decode it), but enough to defeat naive scrapers and the WP REST API's `content.rendered` field.

Note: posts that aren't locked always render in `embed` mode regardless of this setting — there's nothing to protect, and `embed` keeps the content fully readable for SEO.

### Advanced: First-party proxy

- **Setting key:** `use_first_party_proxy`
- **Type:** Checkbox
- **Default:** On (recommended)

When on, all Sesamy auth (`/authorize`, OIDC discovery, JWKS) and API (`/api/*`) traffic for the reader bundle routes through `https://your-domain.com/sesamy/auth/*` and `https://your-domain.com/sesamy/api/*` rather than directly to `auth2.sesamy.com` and `api2.sesamy.com`. Benefits:

- **First-party cookies** — Safari ITP, Firefox ETP, and Brave shields no longer degrade the session.
- **Ad-blocker resilience** — Sesamy hostnames are no longer visible to filter lists.
- **Simpler CSP** — `connect-src 'self'` covers it.
- **EU data sovereignty** — reader traffic flows through your domain.

Server-to-server PHP calls (token exchange, paywall/pass listing, publisher registration) and admin-only browser flows (Connect, DCR handshake) always bypass the proxy — they don't benefit from first-party cookies and only add a hop.

When off, the bundle calls Sesamy directly. Capsule ciphertext is routing-agnostic, so you can flip this setting without re-encrypting any content.

### Advanced: API token

Not a saved setting — a button that mints a short-lived management-API access token using the connected client credentials and displays it once for copy-paste use (curl, Postman, etc.). The token has the same scope as the connected client and is invalidated when the connection is removed.

The token is delivered via a one-shot per-user transient (5 minutes) and never appears in a query string. Reading the settings page after the token is issued will display it once and immediately wipe it.

---

## Publisher registration

When you connect, the plugin automatically registers the site's canonical domain as a **trusted publisher** with the Sesamy api-proxy. Sesamy verifies every capsule unlock against this list, so a missing or stale registration is the leading cause of "I subscribed but the content stays locked" reports.

The **Publisher registration** panel (only visible after connecting) lets you:

- See every domain currently registered for this vendor, the verification mode, last-updated timestamp, and a live probe result (✓/⚠).
- **Add a domain** with a verification mode:
  - **Auto** (recommended) — pinned PEM, for every host.
  - **Pinned PEM** — the plugin pushes its public signing key to Sesamy, which then verifies from its own settings. No request ever comes back to this site.
  - **JWKS URI** — Sesamy fetches this site's public keys from `<domain>/.well-known/dca-publishers.json` on unlock. Only pick this if a consumer specifically needs it: it puts a live, fail-closed request to this site in the path of every unlock, so a WAF rule, a rate limit, or a few minutes of downtime here locks readers out.
- **Re-register this site** — forces a fresh PUT for the canonical domain. Useful after rotating the publisher signing key.
- **Remove** any registration.

The plugin serves its JWKS document at `/.well-known/dca-publishers.json` either way, so a consumer configured for JWKS keeps working.

Older installs were registered with a JWKS URI. The first admin or cron request after the update re-registers this site's own domain with a pinned PEM. It is recorded as done only when it succeeds — a failed attempt (site offline, Sesamy unreachable) backs off for an hour and is retried on a later request, so the repin is applied once and only once. Hand-added domains are left as they are. If you operate the same Sesamy account from multiple WP installs (multisite, regional editions), register each one.

---

## Per-post controls

For every enabled post type, additional UI surfaces let editors gate individual posts.

### Block Editor sidebar / Classic Editor meta box

Fields:

| Field | Meta key | Notes |
|---|---|---|
| Lock this article | `_sesamy_locked` | When off (and no [Automatic locking](#general-automatic-locking) term matches), the post is fully public and the lock-mode setting is ignored. Shown checked + disabled when a term rule locks the post. |
| Enable single purchase | `_sesamy_enable_single_purchase` | Shows the Price field and emits a `sesamy:price` meta. Only meaningful when Locked. |
| Price | `_sesamy_price` | Per-post override of `default_price`. Currency comes from the global `default_currency`. |
| Custom Paywall URL | `_sesamy_custom_paywall_url` | Per-post override of `default_paywall`. Accepts a full URL or a paywall id. |
| Locked content redirect URL | `_sesamy_locked_content_redirect_url` | When set, locked readers are redirected here instead of seeing a paywall. Emitted as `sesamy:locked-content-redirect-url`. |
| Access level | `_sesamy_access_level` | Stored value emitted as `sesamy:accessLevel`. Defaults to `entitlement` when locked, `public` when unlocked. |

### Quick Edit

A compact two-checkbox version (Locked, Single purchase) appears in the post list table's Quick Edit panel.

### Bulk Edit

The Bulk Edit panel exposes the same two fields as `— No Change — / Locked / Not Locked` and `— No Change — / Enabled / Disabled` selects, so you can flip lock state on dozens of posts at once.

### Post list column

Enabled post types get an extra **Sesamy** column showing 🔒 Locked and 💰 Single Purchase pills based on the meta values above. Posts locked by an [Automatic locking](#general-automatic-locking) rule show the source instead: `🔒 Locked (Category: Premium)`.

---

## Rendering Sesamy Login

The plugin exposes the Sesamy Login `<sesamy-login>` web component through three insertion surfaces so editors can drop a sign-in button anywhere it's needed. All three are gated on a valid plugin connection — they render nothing while disconnected.

### Block (`sesamy/login`)

Available in the inserter (under Widgets) and inside any Navigation block. The block has an optional **Button text** attribute that becomes a `<span slot="button-text">…</span>` inside the component; leave it empty to use the web component's default label.

Inside a Navigation block the rendered output is wrapped in `<li>` so it sits alongside other nav items; elsewhere it renders inside a plain `<div>`.

### Shortcode (`[sesamy-login]`)

For the Classic Editor, text widgets, and any context that runs shortcodes:

```text
[sesamy-login]
[sesamy-login button_text="Sign in"]
```

The optional `button_text` attribute behaves the same as the block's Button text.

### Classic menu item

For sites still using **Appearance → Menus**, a "Sesamy" meta box in the left column lets editors add a "Sesamy Login" item to any menu like a built-in item. Tick the option, click **Add to Menu**, and save — the rendered menu item is swapped for `<sesamy-login>` on the frontend, using the menu item's title as the button label.

---

## Frontend behavior

When the plugin is connected and a post type is enabled, every singular page of that post type:

1. Outputs a `<!-- Sesamy WordPress plugin vX.Y.Z -->` source comment on `wp_head`.
2. Emits these meta tags (when applicable):
   - `sesamy:vendor-id` — your tenant id
   - `sesamy:pass` — the default pass SKU
   - `sesamy:currency` — your default currency
   - `sesamy:accessLevel` — `public` or `entitlement` (or whatever the post override is)
   - `sesamy:price` — per-post override or `default_price`, only when single purchase is enabled
   - `sesamy:locked-content-redirect-url` — per-post redirect URL, only when set
3. Wraps the post content in `<article class="sesamy-article" item-src="..." publisher-content-id="...">` and applies the configured lock mode (Capsule / Embed / Encode).
4. Appends the paywall (`<sesamy-paywall settings-url="..."></sesamy-paywall>`) for locked posts that don't have a redirect URL.
5. Passes the finished markup through the `sesamy_article_html` filter before `the_content` returns it. See [Hooks](#hooks).
6. Loads the Sesamy frontend bundle, configured with the proxied or direct API/auth bases as appropriate.

The output is **byte-identical for every reader**. Per-user state is resolved client-side after page load, which keeps every page fully cacheable on Cloudflare, WP Super Cache, Kinsta object cache, etc.

---

## Hooks

The plugin exposes a small set of filters and actions so integrators can change lock behavior, customize paywall markup, and boot dependent code without forking.

| Hook | Type | Signature | Since |
| ---- | ---- | --------- | ----- |
| `sesamy_plugin_loaded` | action | none | 1.3.0 |
| `sesamy_plugin_init` | action | none | 1.3.0 |
| `sesamy_plugin_init_priority` | filter | `int $priority` (default `8`) | 1.3.0 |
| `sesamy_is_post_locked` | filter | `bool $locked, int $post_id` | 1.5.0 |
| `sesamy_paywall_preview` | filter | `string $preview_html` | 1.3.0 |
| `sesamy_paywall` | filter | `string $paywall_html` | 1.3.0 |
| `sesamy_article_html` | filter | `string $html, WP_Post $post, string $content` | 1.10.0 |

**Full reference with examples:** [developers.sesamy.com — WordPress Plugin Hooks](https://developers.sesamy.com/integrations/cms/wordpress-hooks.html). That page is the source of truth; keep it in sync when adding or changing a hook.

These are the supported hooks. The `sesamy_content` filter from the v1 plugin (`sesamyab/wordpress-sesamy`) is not applied by this plugin: `sesamy_article_html` replaces it, with the rendered markup as the filtered value. A callback still hooked to `sesamy_content` never runs.

One thing worth knowing before you reach for these: rendered output must stay reader-independent. The plugin emits byte-identical HTML for every visitor and resolves entitlements client-side, which is what keeps pages cacheable. Vary hook output on the post, never on the current user.

---

## Local development

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- Node.js 18+ (use `nvm` to match `.nvmrc`)
- PHP 8.3+
- [Composer](https://getcomposer.org/)

### Setup

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

The local environment automatically loads `dist/fast-refresh.php` and uses the 10up Toolkit's dev URLs when the host matches `*.local` / `*.test` / `localhost` / `127.0.0.1` or `WP_ENVIRONMENT_TYPE` is `local`/`development`.

---

## Linting and tests

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

---

## Building a release

```bash
./build-zip.sh
```

This produces `sesamy-wordpress.zip` ready for distribution via WP admin → Plugins → Upload Plugin.

The bundled `@sesamy/sesamy-js` version is pinned in `sesamy2.php` (`SESAMY_JS_VERSION`) and kept in sync with `package.json` by `update-plugin-version.js` on precommit, so the script-host chain (auth0-plugin, capsule-plugin, sesamy-components, core) loads in lockstep.

---

## Project structure

```text
sesamy2.php                # Plugin entry point and bootstrap
src/
  Admin/Settings/Core.php  # Plugin settings registration + sanitization
  Admin/Settings/Post.php  # Per-post fields, Quick Edit, Bulk Edit, list column
  Admin/View/SettingsPage.php   # Sesamy settings page UI
  Admin/View/MetaBox.php        # Classic Editor meta box
  Admin/View/ReleaseNotice.php  # Plugin update banner
  Api/Rest.php             # Plugin REST endpoints
  Capsule/Service.php      # Capsule encrypt/seal pipeline
  Capsule/Jwks.php         # Serves the publisher signing key as JWKS
  Capsule/Registration.php # Trusted-publisher registration with api-proxy
  Connect/ConnectFlow.php  # OAuth-style connect/disconnect handlers
  Connect/SesamyApi.php    # Management API client (paywalls, passes, tokens)
  Connect/AuthHeroClient.php    # Token exchange against AuthHero
  Core/Assets.php          # Enqueues the Sesamy frontend bundle
  Frontend/ContentContainer.php # Wraps `the_content` per lock mode
  Frontend/Meta.php        # `<head>` meta tags
  Frontend/Login.php       # `sesamy/login` block, `[sesamy-login]` shortcode, classic-menus meta box
  Proxy/Router.php         # `/sesamy/api/*` and `/sesamy/auth/*` proxy
  Support/helpers.php      # Settings defaults, URL builder, helpers
assets/
  js/                      # JS source (admin.js, post-settings.js, sesamy.js)
  css/                     # Stylesheets
tests/
  src/                     # PHP unit tests (mirrors src/ structure)
```

---

## Documentation and support

- Full setup and usage guide: [Sesamy WordPress Plugin Documentation](https://developers.sesamy.com/integrations/cms/wordpress.html)
- Bug reports and questions: https://support.sesamy.com
- Source: https://github.com/sesamyab/wordpress-sesamy-2
