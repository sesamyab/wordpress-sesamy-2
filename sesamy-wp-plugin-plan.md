# Sesamy WordPress Plugin — Implementation Plan

## 1. Executive summary

The Sesamy WordPress plugin is the publisher-facing surface for connecting a WordPress site to a Sesamy account, marking content for gating, encrypting gated content via the Capsule standard, and enabling readers to unlock that content with their Sesamy entitlements.

The plugin's architecture differs materially from Memberful's because Sesamy has two capabilities Memberful does not:

1. **Capsule** — a client-side content encryption protocol (AES-256-GCM content keys sealed per issuer via ECDH P-256, unwrapped in the browser with a non-extractable RSA-OAEP key) that lets us ship encrypted content in static HTML and decrypt in-browser. This removes the need for server-side gating decisions on the hot path.
2. **Paywall strategies** — a centralized rules engine that makes access decisions based on content metadata (tags, categories, publish date, referer) and user context, living on the Sesamy issuer side rather than being replicated per-site.

A third capability (optional but strategically important) is **same-domain proxying**: publishers can route Sesamy auth and API traffic through their own domain (`publisher.com/sesamy/*`) rather than `api.sesamy.com`, which eliminates third-party cookie issues, ad-blocker exposure, and CSP complexity. This is designed for from day one but shipped as a distinct stage.

Together, these three capabilities let the WP plugin be dramatically smaller and simpler than Memberful's plugin, with zero runtime coupling between WP render paths and Sesamy's availability, full CDN cacheability of gated pages, and first-party cookie semantics throughout.

The plan is split into seven stages, each of which produces a shippable increment.

---

## 2. Architectural principles

### 2.1 The plugin collects signals; Sesamy makes decisions

The WordPress plugin should almost never decide whether a user can see content. It should:

- Encrypt content at publish time
- Attach rich metadata signals to the page (tags, author, publish date, custom fields, etc.)
- Load the Sesamy client JS and let it handle decryption

Access decisions live in paywall strategies on Sesamy. Publishers change paywall logic in the Sesamy dashboard, not in WordPress. WP doesn't need to know the strategy rules, so there's no sync burden and no stale-rules problem.

### 2.2 Static HTML, cacheable everywhere

Every page the plugin outputs is byte-identical for every reader until the author edits the post. Encrypted content goes in the HTML as ciphertext; teasers and public portions are plaintext; no per-user page variants. This plays cleanly with Cloudflare, WP Super Cache, Kinsta object cache — anything.

The corollary: no user-specific content logic runs server-side in PHP. The plugin outputs one page; the client JS transforms it based on the user's unsealing result.

This also means **no user-specific data may ever be injected into page HTML by the plugin**, even as a micro-optimization. Don't inject the current user's entitlements as a `window.SESAMY_USER` global — the moment you do, every cache layer breaks. Per-user data is fetched client-side via API call after page load.

### 2.3 URL routing is configurable

The plugin never hardcodes Sesamy domain names. All outbound URLs (API, auth, assets, checkout) are computed through a central helper that consults a routing configuration. This lets the same plugin work in three modes:

- **Direct mode** — `api.sesamy.com`, `auth.sesamy.com`, `js.sesamy.com` accessed directly as third-party origins
- **Proxied mode** — all Sesamy traffic routes through `publisher.com/sesamy/*` as first-party
- **Hybrid mode** — some endpoints proxied, others direct (e.g., checkout remains direct for PCI scope reasons)

This is a foundational design decision, not a late-stage feature. Every URL-emitting code path in the plugin goes through the helper from day one.

### 2.4 Default: no shadow WP users

Unlike Memberful, which creates a WP user row for every member, the Sesamy plugin defaults to no shadow users. Readers are Sesamy identities only; `is_user_logged_in()` continues to work as it does today for WP's actual authors/editors/admins.

A tiered opt-in is available for publishers who need WP-native user behavior (comments-with-author-names, forums, courses) — see Stage 6.

### 2.5 No server-side runtime dependency on Sesamy

If `api.sesamy.com` is slow or unavailable, pages still render, cached pages still serve, and the plugin degrades gracefully. Only active unlock attempts (reader clicking "Subscribe to continue") hit Sesamy in the critical path.

### 2.6 Publishers configure in Sesamy, not WordPress

The WP plugin's admin UI is deliberately minimal. No complex per-post plan pickers, no marketing-content-per-post textareas, no role mapping grids. Publishers configure paywall strategies, branding, and products in Sesamy's dashboard. WordPress is where content is authored; Sesamy is where access control is defined.

---

## 3. Comparison with Memberful

### 3.1 Mapping of concepts

| Memberful concept | Sesamy equivalent | Notes |
|---|---|---|
| Member (WP user row) | Sesamy identity (no WP user by default) | Stage 6 offers opt-in shadow users |
| Plan / Product | Sesamy product / entitlement | Same concept, different namespace |
| Post ACL (`memberful_acl` postmeta) | Locked post + paywall strategy match | Decision lives in Sesamy, not WP |
| Term ACL | Paywall strategy with category/tag signal | Same pattern, centralized |
| "Any registered user" / "any subscriber" wildcards | Paywall strategy rule | Simpler model, more expressive |
| Marketing content per post/term/global | Paywall rendering from Sesamy strategy response | Can still be per-post via override |
| OAuth connection (activation code paste) | Proxy auth-code flow with manual fallback | Better UX; paste is fallback |
| Webhook (member changes) | Webhook (entitlement changes) | Same pattern, HMAC-SHA256 verified from day one |
| Mapping table (member_id ↔ wp_user_id) | Only if shadow users enabled | Optional, Stage 6 |
| Private per-user RSS feed | Private per-user RSS feed | Direct match |
| Memberful-hosted podcasts | Sesamy-hosted podcasts | Direct match |
| Gutenberg visibility attributes on every block | Post-level locking via the WordPress `<!--more-->` block + per-post metabox | Reuses native WP convention; backwards compatible with existing more-block content |
| Classic-editor TinyMCE button | Same, for Sesamy shortcodes | Minor, covers Classic Editor holdouts |
| Third-party requests to `memberful.com` | Optional first-party proxying | **New capability Memberful doesn't have** |
| Ad provider suppression | Same, for Raptive/Mediavine/Advanced Ads | Only if specific publishers need it |

### 3.2 What Sesamy does better

- **Content is never readable in HTML source.** Capsule encrypts. Memberful's plugin can only replace content with marketing copy after the filter runs; the raw content existed in `post_content` and could leak via preview URLs, REST API, or other plugins. Sesamy's encrypted-at-rest approach is structurally more secure.
- **Full CDN cacheability of gated pages.** Memberful pages vary by user (admin sees full content, non-member sees marketing). Sesamy pages are identical for everyone; JS handles per-user rendering.
- **Paywall logic changes don't require WP deployments.** A Memberful publisher changing which plans gate which posts has to edit posts. Sesamy publishers change a strategy in the dashboard; every WP site updates immediately.
- **No WP user table bloat.** At 250k members, Memberful-style shadow users means 250k `wp_users` rows per publisher site. Sesamy's default avoids this.
- **First-party cookies and ad-blocker resilience** via optional proxying. Memberful has no equivalent; their plugin always makes cross-origin requests to `memberful.com` domains, subject to ITP, ETP, and adblocker partition/blocking.
- **EU data sovereignty story.** Sesamy never receives publisher content (Capsule ciphertext only). With proxying enabled, Sesamy also doesn't directly receive reader traffic — requests flow through the publisher's domain. Memberful's model has neither property.
- **Centralized webhook verification from day one.** Memberful's HMAC was added as a CVE fix in v1.76 (CVE-2025-58000). We don't repeat that mistake.

### 3.3 What we deliberately match

- Shortcode namespace compatibility (`[memberful_*]` → `[sesamy_*]` with aliases)
- Helper function aliases (`is_subscribed_to_memberful_plan()` etc. as thin wrappers)
- Private RSS feed URL format (`?member-feed=TOKEN`) for continuity with podcast subscribers' existing feed URLs
- Account-linking confirmation dance for email collisions (only if Stage 6 shadow users are enabled)
- Role mapping (Stage 6)
- Per-post override metabox (minimal, not the full Memberful ACL UI)

### 3.4 What we deliberately skip

- Per-post plan/product ACL UI — strategies cover this better
- `memberful_acl` equivalent postmeta with plan/product IDs — not needed with strategies
- Global marketing content option — paywall template is a Sesamy-side concern
- bbPress integration in v1 — niche, revisit after core is stable
- Sensei integration — usage has collapsed
- WP Ultimate Recipe — very niche
- Debug endpoint exposing full plugin state — significant security liability in Memberful

---

## 4. Implementation stages

### Stage 0 — Foundation (weeks 1–2)

**Goal:** Publisher can install the plugin, click Connect, complete OAuth flow through `connect.sesamy.com`, and have working credentials stored in WP. No content gating yet.

**Deliverables:**

- Plugin scaffold with proper headers, GPL license, namespacing (`Sesamy\WP\...`)
- Settings page at `Settings → Sesamy` with "Connect to Sesamy" button and connection state display
- Connection flow endpoint (`?sesamy_endpoint=connect_complete` handler)
- `connect.sesamy.com` worker with three routes: `/wordpress/start`, `/wordpress/callback`, `/wordpress/activate`
- AuthHero application `wordpress-connect` configured with single registered callback URL
- Confidential credentials stored in `wp_options` (`sesamy_client_id`, `sesamy_refresh_token`, `sesamy_webhook_secret`, `sesamy_issuer_public_key`, `sesamy_site`)
- Disconnect flow that revokes integration server-side and wipes local options
- Manual activation-code paste fallback for air-gapped/CLI scenarios
- Confirmation screen in AuthHero displaying the connecting domain (prerequisite: AuthHero Issue #1)
- **URL routing helper `sesamy_url($path, $type)`** — single entry point that all plugin code uses when emitting Sesamy URLs. Reads from routing config; defaults to direct mode. Enables later proxy support without touching any calling code.
- Routing config stored in `wp_options` as `sesamy_routing_mode` with default `"direct"`, alongside optional `sesamy_proxy_base_path` for future use
- Basic admin notices and error states

**Reasoning:** The connection must be rock-solid before any other feature is meaningful. Getting the URL helper right at this stage costs nothing and avoids a painful refactor later when proxy support lands.

**Key decisions:**

- Refresh token vs long-lived client secret: prefer refresh token. WP exchanges it for short-lived access tokens on-demand. Leaked WP DB = revocable refresh token, not eternal credentials.
- `home_url()` is the canonical site identifier. Normalize before comparing on the Sesamy side.
- Credentials are always written via `update_option` with `autoload=false` to avoid bloating the alloptions cache.
- Every URL-emitting call site uses `sesamy_url()` — no hardcoded `sesamy.com` strings anywhere in the plugin code.

### Stage 1 — Core gating with Capsule (weeks 3–5)

**Goal:** Publisher can mark a region of content to gate, content is encrypted on save, readers see a teaser and paywall, subscribed readers can decrypt and read.

**Deliverables:**

- PHP implementation of the Capsule encrypt side (library from Sesamy, or implement against the spec) — Capsule is the underlying protocol; never named in publisher-facing UI
- Post-level locking using the WordPress `<!--more-->` block as the gate: content above the more tag is the public teaser, content below is the locked region. Backwards compatible — posts already authored with the more block work without re-tagging
- Per-post locking metabox toggling `_sesamy_locked` and the required entitlement level. Labels read "Locked content" / "Unlock requirement" — no protocol naming
- `save_post` hook that splits `post_content` at the more tag and pre-encrypts the locked portion, storing ciphertext in `post_content` and plaintext in `_sesamy_locked_plaintext` postmeta for subsequent edits
- Issuer public key fetching, caching in `wp_options`, refetch on webhook trigger
- **Client JS enqueueing with configurable endpoint resolution.** The Capsule client reads its API base URL from a global set by the plugin (via `wp_localize_script`), which itself comes from `sesamy_url()`. Direct mode → `api.sesamy.com`. Proxied mode (Stage 2.5) → `/sesamy/api`. No code changes to the client between modes.
- Teaser rendering — configurable via filter, default is "first N words" of the encrypted region
- Paywall UI — minimal default that can be styled/overridden by the publisher's theme, with a "Subscribe to unlock" CTA linking to the publisher's Sesamy checkout
- Key rotation support — plugin handles multiple sealing generations so existing content doesn't break when issuer keys rotate

**Reasoning:** This is the core product. Everything else is scaffolding around this capability. Capsule integration has to be robust enough that publishers trust it with their premium content.

**Key decisions:**

- **When to encrypt.** At `save_post`, not at render time. The locked portion of `post_content` is stored already-encrypted in the database. Plaintext is kept in `_sesamy_locked_plaintext` postmeta for subsequent edits and ciphertext is regenerated on every save.
- **Atomic save.** If encryption fails mid-save (e.g., issuer public key unreachable), the save must abort cleanly.
- **Teaser extraction.** The portion above the `<!--more-->` block is the teaser. Publishers control the split point by where they place the more tag — same model they already know.
- **SEO meta.** The teaser is what ends up in `og:description`, Twitter cards, meta description — not the full content. Hook into Yoast/Rank Math/SEOPress or provide defaults.
- **No `[sesamy_capsule]` shortcode, no `sesamy/capsule-region` block.** The more-block + metabox is the only locking surface. "Capsule" is an implementation detail and stays out of publisher-facing labels.

### Stage 2 — Signals and strategy integration (weeks 6–7)

**Goal:** Paywall strategies on the Sesamy side have the signals they need to make decisions. Publishers can tag posts in ways that strategies interpret.

**Deliverables:**

- Rich context metadata embedded in the locked content payload: post ID, slug, type, publish date, authors, word count, categories, tags, custom taxonomies, featured-image flag, video/audio flags
- Custom taxonomy `sesamy_paywall_tag` for publishers who want paywall-specific tags separate from regular content tags
- Filter `sesamy_lock_context` so publishers can add custom signals (ACF fields, SCF fields, custom taxonomies). Name is impl-agnostic — it describes the locked post's signal envelope, not the underlying encryption protocol
- Post edit screen panel showing which Sesamy strategy would currently match this post, fetched live from Sesamy (read-only, admin-side only)
- Per-post override metabox: `never_gate` / `use_strategy` / `always_gate` radio. Defaults to `use_strategy`.
- Request-time signals added to the client-side unseal request: referer, UTM params, `?unlock_token=...` for campaign-based unlocks
- Server-side validation that the override flags are only writable by users with `manage_options` or a new `edit_sesamy_gating` capability

**Reasoning:** The Capsule + strategies architecture is only as good as the signals the plugin surfaces. Getting the schema right here means publishers can build sophisticated strategies without plugin updates every time they want a new signal.

**Key decisions:**

- **Custom taxonomy vs regular tags.** Offer both. Dedicated `sesamy_paywall_tag` for paywall-only tagging; regular tags for editorial tagging tied to strategies.
- **Signal schema versioning.** Include `signals_version` in the payload metadata so we can evolve the schema without breaking old posts.
- **Live strategy preview.** Admin-side only. Cache for 60 seconds. Fail silently if Sesamy is slow — not a blocker for saving the post.

### Stage 2.5 — Same-domain proxying (weeks 7–9, overlaps Stage 2/3)

**Goal:** Publishers can optionally route all Sesamy auth/API traffic through their own domain for first-party cookie semantics, ad-blocker resilience, simpler CSP, and improved trust signals.

**Why a distinct stage:** Proxying is its own feature with its own testing surface and its own configuration UX. It builds on the URL routing helper from Stage 0. Treating it separately rather than folding into Stage 1 or 2 keeps those earlier stages focused on the core product capability. Publishers who don't want proxying can skip this stage entirely.

**What gets proxied (three categories, different cache behavior):**

**Category A — API calls (never cached):**
- `publisher.com/sesamy/api/capsule/unseal` → `api.sesamy.com/v1/capsule/unseal`
- `publisher.com/sesamy/api/user/entitlements` → `api.sesamy.com/v1/user/entitlements`
- `publisher.com/sesamy/api/checkout/start` → `api.sesamy.com/v1/checkout/start`

**Category B — Auth flow (mixed caching):**
- `publisher.com/sesamy/auth/authorize` → `auth.sesamy.com/authorize` (no-cache, transient per request)
- `publisher.com/sesamy/auth/callback` → locally handled, sets first-party cookie, redirects (no-cache)
- `publisher.com/sesamy/auth/logout` → clears cookie, calls Sesamy to revoke (no-cache)
- `publisher.com/sesamy/.well-known/openid-configuration` → cacheable for minutes to hours
- `publisher.com/sesamy/.well-known/jwks.json` → cacheable for minutes to hours

**Category C — Static assets (aggressively cached):**
- `publisher.com/sesamy/assets/capsule-client.{hash}.js` → `Cache-Control: public, max-age=31536000, immutable`
- `publisher.com/sesamy/assets/paywall.{hash}.css` → same
- `publisher.com/sesamy/assets/icons/*` → same

**Deliverables:**

- **Three proxy implementation modes**, selectable in plugin admin:

  1. **Cloudflare Worker mode (recommended)** — Sesamy publishes a Wrangler template; publisher deploys to their own CF account, adds a route `publisher.com/sesamy/*` → Worker. Edge execution, sub-50ms overhead, proper `Cache-Control` headers per endpoint type, signing credentials held in the Worker (not in WP DB).

  2. **WordPress PHP mode** — plugin registers a rewrite rule matching `/sesamy/*` and handles via `template_redirect` hook, streaming responses via `wp_remote_request`. Works anywhere WP works, including shared hosting. Slower and less secure (signing credentials in WP DB) but universally deployable.

  3. **Sesamy-managed mode (Stage 6)** — Sesamy operates the proxy on the publisher's behalf; publisher sets a CNAME and enables the mode in plugin config. Zero deployment effort. Requires Sesamy infrastructure investment; scope for Stage 6.

- **Cache compatibility module** — detects active caching systems and programmatically registers bypass rules for `/sesamy/api/*` and `/sesamy/auth/*`, while allowing aggressive caching of `/sesamy/assets/*` and `/sesamy/.well-known/*`. Supported systems:
  - WP Super Cache (via `wp_cache_add_pages_not_cached` filter or config file append)
  - WP Rocket (via `rocket_cache_reject_uri` filter)
  - W3 Total Cache (via config API)
  - LiteSpeed Cache (via its filters)
  - Cloudflare (detect Cloudflare headers; rely on response `Cache-Control`; document manual page rules for "Cache Everything" setups)
  - Managed hosts (WP Engine, Kinsta, Pantheon, Flywheel) — cannot configure automatically; show admin notice with host-specific instructions

- **Cache diagnostic tool** in plugin admin — probe requests verify that:
  - API/auth endpoints return `no-store` and aren't being cached
  - Asset endpoints return long-TTL `Cache-Control` and are cached
  - The proxy reaches Sesamy (verified via magic response header Sesamy sets on probe endpoints)
  - First-party cookies are being set correctly on the publisher domain

- **First-party session cookie handling** — when reader authenticates via `/sesamy/auth/callback`, set `__Host-sesamy-session` cookie on publisher domain (Secure, HttpOnly, SameSite=Lax, Path=/). The cookie contains an opaque session reference; actual tokens live in Sesamy's backing store or are derived via signing at the proxy edge.

- **Routing configuration UI** in plugin admin:
  - Current routing mode (Direct / WordPress / Cloudflare / Sesamy-managed)
  - "Switch to proxy mode" wizard with mode-specific setup instructions
  - Deploy-to-Cloudflare button for the Worker mode
  - Diagnostic tool that runs on every settings page load

- **CSP header recommendations** — admin guidance showing simplified CSP that becomes possible when proxying is enabled

- **Webserver config templates** for publishers running nginx or Apache directly (non-Cloudflare) who want a non-PHP proxy: nginx snippet, `.htaccess` with `mod_proxy`, Caddy docs — fourth optional mode beyond the primary three

**Reasoning:** Each proxy mode covers a different publisher segment. Non-technical publishers on managed hosts use WordPress PHP mode (works everywhere, acceptable perf for their scale). Technical publishers on Cloudflare use Worker mode (best perf and security). Largest publishers who want turnkey use Sesamy-managed mode when it ships.

**Key decisions:**

- **Caching correctness over caching aggression.** Err toward no-cache for new endpoint types when in doubt. A too-cold cache is a performance problem; a too-hot cache that serves User A's entitlements to User B is a catastrophic bug.
- **The plugin handles page-cache bypass rules; the proxy handles HTTP Cache-Control headers.** Clear separation of concerns.
- **Worker mode holds signing credentials; PHP mode holds them in `wp_options`.** Document this security difference clearly. Recommend Worker mode to publishers who can deploy it.
- **Don't inject user-specific data into HTML even in proxied mode.** Same cacheability rule as Stage 0.
- **Asset path structure supports content-hashed filenames.** `/sesamy/assets/capsule-client.abc123.js` not `/sesamy/assets/capsule-client.js?v=123`. Content hashes enable `immutable` caching.
- **Subdirectory WP installs** (`publisher.com/blog/`) require a decision: proxy at domain root (needs CF/webserver config) or under the WP install (works with PHP mode). Default to the former when CF/webserver available, latter for PHP mode.

**Migration path:** Publishers currently in direct mode can switch to proxied mode via the admin UI without re-encrypting any content (Capsule ciphertext is routing-agnostic). The switch updates the routing config; next page load uses the new endpoints.

### Stage 3 — Memberful migration compatibility (week 10)

**Goal:** A publisher currently on Memberful can install the Sesamy plugin, run a one-click importer, and have their existing content continue to work.

**Deliverables:**

- Shortcode aliases registered with `add_shortcode()`:
  - `[memberful]` → Sesamy equivalent (reads same `has_subscription`, `has_product`, `does_not_have_subscription`, `does_not_have_product` attrs)
  - `[memberful_if_has_active_subscription]` / `[memberful_if_does_not_have_active_subscription]`
  - `[memberful_sign_in_link]` / `[memberful_sign_out_link]` / `[memberful_register_link]` / `[memberful_account_link]`
  - `[memberful_buy_subscription_link]` / `[memberful_buy_download_link]` / `[memberful_buy_gift_link]` / `[memberful_download_link]`
  - `[memberful_podcasts_link]` / `[memberful_podcast_url]` / `[memberful_private_rss_feed_link]`
- Helper function aliases guarded with `function_exists()`:
  - `is_subscribed_to_memberful_plan()`, `is_subscribed_to_any_memberful_plan()`
  - `has_memberful_download()`, `has_memberful_feed()`, `has_memberful_product()`, `has_memberful_subscription()`
  - `memberful_wp_user_downloads()`, `memberful_wp_user_plans_subscribed_to()`
  - `memberful_can_user_access_post()`
- Block visibility attribute reader: recognizes `memberful_visibility`, `memberful_visibility_hide`, `memberful_visibility_plans` on any block in rendered content and applies equivalent Sesamy gating (read-only)
- Filter bridges: re-fire key Memberful filter names alongside Sesamy equivalents
- Migration admin screen: one-click import that reads:
  - `memberful_acl` option (global post ACL map)
  - `memberful_term_acl` option
  - `memberful_posts_available_to_any_registered_user` / `..._to_anybody_subscribed_to_a_plan`
  - `memberful_role_active_customer`, `memberful_role_inactive_customer`, `memberful_plan_role_mappings`, `memberful_use_per_plan_roles`
  - `memberful_global_marketing_content`
  - `memberful_private_user_feed_plan`
  - Per-post: `memberful_acl`, `memberful_available_to_any_registered_user`, `memberful_available_to_anybody_subscribed_to_a_plan`, `memberful_marketing_content`
  - Per-term: same keys as posts
  - `{prefix}_memberful_mapping` table
  - Per-user: `memberful_product`, `memberful_subscription`, `memberful_feed`, `memberful_custom_field`, `memberful_private_user_feed_token`
- Strategy suggestion: the importer analyzes existing ACL patterns and suggests Sesamy strategies to create
- Post-import verification: walks a sample of posts and confirms the existing gating behavior is preserved
- Migration dry-run mode
- Cleanup tool (explicit, confirmed)

**Reasoning:** Migration friction is the single biggest determinant of whether a publisher actually switches. Making migration a one-click button is what converts the "we should consider switching" conversation into a completed switch.

**Key decisions:**

- **Don't auto-convert every per-post ACL into a bespoke strategy.** Cluster similar patterns; suggest a small number of broad strategies.
- **Leave Memberful data in place until the publisher confirms success.** Non-destructive by default.
- **Handle simultaneous activation.** Sesamy takes precedence once connected; Memberful's `the_content` filter becomes a no-op on imported posts.

### Stage 4 — Member-facing features (weeks 11–12)

**Goal:** Publishers have the supporting UI elements their readers expect: sign-in/sign-out links, account pages, private RSS feeds.

**Deliverables:**

- Sesamy shortcodes parallel to the Memberful aliases from Stage 3
- Sidebar widget `sesamy_profile_widget`
- Nav menu items for Sign In / Sign Out / Account
- Private per-user RSS feed implementation maintaining `?member-feed=TOKEN` URL compat
- Podcast feed integration via shortcodes
- Comments integration (honor post's gated state, RSS feed filtering)
- Login / account UI states rendered by the client JS
- All URLs go through `sesamy_url()` — works transparently in direct or proxied modes

**Key decisions:**

- **Private RSS feeds deliver plaintext content**, not encrypted. The tokenized URL is the entitlement check; podcast players can't decrypt locked content.
- **Comments above vs below the more tag.** Comments placed above the more block are public; placed below they're part of the locked region. Default theme placement is above (public).

### Stage 5 — Optional integrations (weeks 13–14, parallel work)

**Goal:** Publishers using common WP plugins don't have to rebuild their stack.

**Deliverables (prioritize based on actual publisher demand):**

- WooCommerce integration
- LearnDash integration
- Ad suppression: Raptive, Mediavine, Advanced Ads
- WP-CLI commands:
  - `wp sesamy connect` — loopback+PKCE connection flow
  - `wp sesamy import-memberful` — headless migration
  - `wp sesamy encrypt-all` — force re-encryption
  - `wp sesamy doctor` — diagnostic incl. routing mode status
  - `wp sesamy proxy test` — cache diagnostic CLI
  - `wp sesamy proxy deploy-worker` — CF Worker deployment

**Key decisions:**

- **Cover only what actual publishers need.** Don't build LearnDash integration if no current publisher uses it.
- **Publisher-side config for ad suppression** lives in Sesamy dashboard.

### Stage 6 — Advanced and opt-in features (weeks 15+)

**Goal:** Cover the edge cases that matter to sophisticated publishers.

**Deliverables:**

- **Shadow WP user support (opt-in, tiered):**
  - "No shadow users" — default
  - "Lazy shadow users" — create WP user only on first interaction that requires one
  - "Eager shadow users (Memberful-compatible mode)" — create on every sign-in
  - Account-linking confirmation flow for email collisions
  - Mapping table `{prefix}_sesamy_mapping`
  - Role assignment with safelist
  - Deletion policy: only hard-delete shadow users with no content

- **Per-entitlement role mapping** — match Memberful's v1.77 feature

- **Sesamy-managed proxy** — third routing mode as standalone product:
  - Sesamy operates proxy Workers on publishers' behalf
  - Publisher configures via CNAME (`_sesamy.publisher.com` → Sesamy infrastructure) and plugin setting
  - Auto-provisioned TLS via Cloudflare for SaaS or equivalent
  - Operational monitoring, SLAs, scaling on Sesamy side
  - Useful for managed-host publishers where custom Worker config is restricted

- **Multi-domain proxy support** — shared entitlement sessions across publisher's domains

- **Custom subdomain support** — `sesamy.publisher.com/*` instead of `publisher.com/sesamy/*`

- **Advanced editor tools:**
  - "Preview as [entitlement]" in post edit screen
  - "Why is this gated?" explanation panel
  - Bulk operations

- **Content freshness rules UI**
- **Metered access display**
- **Shortcode editor button** (TinyMCE)
- **Multisite support**
- **CPT/taxonomy allowlist**

**Key decisions:**

- **Shadow users as a separately-licensed feature?** Consider pricing this tier given disproportionate support burden.
- **Sesamy-managed proxy pricing.** Likely a paid tier given real per-publisher infrastructure costs.
- **Don't build preemptively.** Each Stage 6 item needs real publisher requests.

---

## 5. Detailed component designs

### 5.1 Account linking and connection flow

- **Default flow:** Auth-code OAuth 2.0 with `connect.sesamy.com` as the registered redirect URI. Publisher → WP Connect button → `connect.sesamy.com/wordpress/start` → AuthHero `/authorize` with confirmation screen showing connecting domain → `connect.sesamy.com/wordpress/callback` (provisions integration, mints 60-sec activation code) → back to WP → WP POSTs activation code server-to-server to `api.sesamy.com/wordpress/activate` → receives credentials bundle → stored in `wp_options`.
- **Fallback:** Paste activation code directly. Same endpoint, no browser redirect.
- **WP-CLI:** Loopback+PKCE (RFC 8252).
- **Nonce protection:** WP-originated transient bound to initiating user.
- **Disconnect:** Authenticated DELETE to Sesamy, revoke integration, wipe local options.

**Storage (`wp_options`, all `autoload=false`):**

```
sesamy_connection = {
    integration_id: "int_abc123",
    client_id: "cli_...",
    refresh_token: "rt_...",
    webhook_secret: "whsec_...",
    site_subdomain: "publisher.sesamy.com",
    issuer_public_key: "-----BEGIN PUBLIC KEY-----...",
    issuer_key_id: "key_2026_04",
    connected_at: 1714000000,
    connected_by: 42
}

sesamy_routing = {
    mode: "direct" | "wordpress_proxy" | "cloudflare_worker" | "sesamy_managed",
    proxy_base_path: "/sesamy",
    worker_deployed_at: null | timestamp,
    cache_bypass_rules_applied: [...]
}
```

### 5.2 URL routing helper

Single function: `sesamy_url( string $path, string $type = 'api' ): string`

`$type` is one of `api`, `auth`, `assets`, `checkout`, `embed`. Reads `sesamy_routing` option:

- `mode: "direct"` → `https://{type-appropriate-subdomain}.sesamy.com/{path}`
- `mode: "wordpress_proxy" | "cloudflare_worker" | "sesamy_managed"` → `{home_url}/sesamy/{type}/{path}`

Every URL-emitting code path in the plugin calls this. Checkout may be an exception if PCI scope requires it to remain at `checkout.sesamy.com` even in proxied mode — decision captured in the helper via the `type` parameter.

### 5.3 User identity model

**Default (no shadow users):**

- Readers authenticate to Sesamy, receive OIDC session cookie
  - Direct mode: cookie scoped to `sesamy.com` (third-party)
  - Proxied mode: cookie scoped to `publisher.com` (first-party, ITP-friendly)
- WP plugin doesn't know the reader server-side
- Client JS sends Sesamy session credential for unseal; decryption happens in-browser
- `is_user_logged_in()` refers only to WP's own authors/editors/admins

**Theme author helpers:**

```php
sesamy_is_member()
sesamy_current_member_id()
sesamy_member_has_entitlement($slug)
sesamy_member_entitlements()
```

All client-side-aware — read from data attribute set by client JS after session resolution, so consistent regardless of whether page was cache-served.

**Shadow users (Stage 6):** matches Memberful's model with the safeguards listed above.

### 5.4 Content locking

Locking is **post-level**, controlled by two pieces of state the publisher already understands:

1. The native WordPress `<!--more-->` block, which marks the boundary between teaser (above) and locked content (below).
2. A per-post metabox toggle (`_sesamy_locked`) plus an entitlement level (`_sesamy_access_level`).

If the toggle is off, the post renders normally — the more tag behaves as it always did. If the toggle is on, content below the more tag is encrypted on save and the frontend renders teaser + paywall.

**No dedicated locking block, no `[sesamy_capsule]` shortcode, no protocol-named UI.** The implementation uses the Capsule encryption protocol under the hood, but publishers never see that name in their editor, metabox, or settings.

**Per-post locking metabox (sidebar):**

```
- Lock content below the "Read more" tag  [toggle]
- Required entitlement                      [select: any signed-in / specific entitlement]
- Override paywall strategy                 [optional, see §5.5]
```

**Save behavior:**

- Split `post_content` at the `<!--more-->` block
- Public portion stays as plaintext in `post_content`
- Locked portion is encrypted via the Capsule protocol; ciphertext + DCA metadata replace the locked section in `post_content`; plaintext is kept in `_sesamy_locked_plaintext` postmeta for editing
- Re-encrypt on every save
- If `_sesamy_locked` is off, no transformation — `post_content` stays as written

**Render output (when locked):**

```html
<!-- Public teaser (above the more tag), unchanged from author input -->
<div class="sesamy-locked" data-post-id="12345">
  <template class="sesamy-locked-payload">
    <!-- DCA: sealed_keys, ciphertext, metadata, integrity_proof -->
  </template>
  <div class="sesamy-paywall" data-state="locked">
    <!-- Default paywall UI, themable -->
  </div>
</div>
```

**Client JS responsibility:**

1. Find `.sesamy-locked` elements
2. Read sealed keys and ciphertext from `<template>`
3. Check session state — via `sesamy_url('session', 'api')`
4. Request unsealing — via `sesamy_url('capsule/unseal', 'api')` (internal API path; user-facing URL is `/sesamy/api/...` in proxied mode)
5. On success: decrypt in-browser, replace paywall div with content
6. On failure: keep paywall div, show appropriate message

**Classic Editor:** the more tag works in Classic Editor too, so locking is fully supported there without a separate shortcode path.

### 5.5 Editor tools

**Post edit screen:**

- **Sesamy Gating metabox** (sidebar, collapsible):
  - Current strategy match display (read-only, fetched live)
  - Override selector: `Use strategy (default)` / `Never gate` / `Always gate`
  - Sesamy paywall tags field
  - Link to "Edit paywall strategies in Sesamy dashboard"
- **"Preview as reader"** button
- **Locked content indicator** in block editor — shown when the post is marked locked and a `<!--more-->` block is present; warns if locked is on but no more tag exists

**Posts list:**

- "Gating status" column showing whether the post is locked, whether a `<!--more-->` block is present, and the matched strategy

### 5.6 Custom tags and signals

**Primary: regular WP tags and categories.** Publishers tag naturally, configure Sesamy strategies to match.

**Secondary: dedicated `sesamy_paywall_tag` taxonomy.** For keeping paywall logic separate from editorial tagging.

**Signals per post (in the locked content payload metadata):**

```json
{
  "signals_version": 1,
  "post": {
    "id": 12345,
    "type": "post",
    "slug": "breaking-news-article",
    "published_at": "2026-04-23T09:00:00Z",
    "modified_at": "2026-04-23T09:15:00Z",
    "authors": ["jane-doe"],
    "word_count": 1850,
    "reading_time_minutes": 8,
    "categories": ["news", "politics"],
    "tags": ["breaking-news", "election-2026"],
    "paywall_tags": ["premium"],
    "custom_taxonomies": {
      "article_type": ["investigation"]
    },
    "custom_fields": {},
    "has_video": false,
    "has_audio": true,
    "override": null
  },
  "site": {
    "url": "https://publisher.com",
    "language": "en_US",
    "timezone": "Europe/Madrid"
  }
}
```

Extensible via `sesamy_lock_context` filter.

### 5.7 Shortcodes

**Sesamy-native:**

- `[sesamy_sign_in_link]`, `[sesamy_sign_out_link]`, `[sesamy_account_link]`, `[sesamy_register_link]`
- `[sesamy_if_entitled entitlement="premium_monthly"]...[/sesamy_if_entitled]`
- `[sesamy_if_not_entitled entitlement="premium_monthly"]...[/sesamy_if_not_entitled]`
- `[sesamy_if_authenticated]...[/sesamy_if_authenticated]`
- `[sesamy_if_not_authenticated]...[/sesamy_if_not_authenticated]`
- `[sesamy_buy_link product="monthly" label="Subscribe"]`
- `[sesamy_private_rss_feed_link category="premium"]`

**Memberful compatibility aliases (Stage 3).** All Memberful shortcodes registered as aliases that translate to Sesamy equivalents.

### 5.8 Blocks

**No dedicated locking block.** Post-level locking uses the native WordPress `<!--more-->` block as the gate; the per-post metabox controls whether locking is active and at what entitlement level. This preserves backwards compatibility with existing more-block content and avoids exposing protocol-level naming to publishers.

**Helper blocks:**

- `sesamy/sign-in-link`
- `sesamy/account-link`
- `sesamy/if-entitled` — container-only, not paywall-safe (visibility helper for already-public content; not a substitute for locking)
- `sesamy/paywall-cta`

**Deliberately not doing:** block-level visibility attribute injection on every block (Memberful's pattern), and no `sesamy/capsule-region` wrapper block. The more-block + metabox model is the only locking surface.

### 5.9 Hooks and filters

**Actions:**

```
sesamy_connected
sesamy_disconnected
sesamy_before_lock_post
sesamy_after_lock_post
sesamy_webhook_received
sesamy_strategy_match_changed
sesamy_routing_mode_changed
```

**Filters:**

```
sesamy_lock_context
sesamy_lock_teaser
sesamy_paywall_html
sesamy_client_js_url
sesamy_issuer_url
sesamy_after_sign_in_url
sesamy_after_sign_out_url
sesamy_shadow_user_mode            (Stage 6)
sesamy_shadow_user_data_on_create  (Stage 6)
sesamy_shadow_user_role            (Stage 6)
sesamy_url
sesamy_cache_bypass_patterns
```

**Memberful bridge filters (Stage 3):**

```
memberful_wp_after_sign_in_url
memberful_wp_after_sign_out_url
memberful_marketing_content
memberful.map_user.create
memberful.map_user.update
memberful_user_role_for_active_customer
memberful_user_role_for_inactive_customer
```

### 5.10 Webhooks

**Endpoint:** `?sesamy_endpoint=webhook` — always at WP origin, not proxied (needs server-side event processing).

**Verification:** HMAC-SHA256 with `sesamy_webhook_secret`, compared with `hash_equals`. From day one.

**Events:**

- `integration.connected` / `integration.disconnected`
- `issuer_keys.rotated`
- `entitlements.updated` (Stage 6)
- `paywall_strategies.updated`
- `member.deleted` (Stage 6)

### 5.11 Error logging

Don't replicate Memberful's `wp_options`-bloating error log. Instead:

- `error_log()` for ops
- Custom table `{prefix}_sesamy_events` with fixed schema, capped rows, rotation
- Admin UI surfaces recent events; never stores raw stack traces

---

## 6. Proxy architecture deep-dive

### 6.1 Why proxying matters

1. **First-party cookies and ITP resilience.** Safari's ITP, Firefox's ETP, Brave's shields all degrade third-party cookies. Proxied mode sidesteps this entire category.
2. **Ad-blocker resilience.** Third-party subscription API hostnames will end up on filter lists at scale. Proxied through publisher's domain, they're invisible.
3. **Simplified CSP and corporate compatibility.** `connect-src 'self'; script-src 'self';` is dramatically easier for security-review processes and works in locked-down corporate/government environments.
4. **Trust signals and conversion.** URL bar stays on `publisher.com` throughout paywall and auth flows.

### 6.2 Cache interaction matrix

| Endpoint pattern | Page cache | Browser cache | Cloudflare cache | Notes |
|---|---|---|---|---|
| `/sesamy/api/*` | BYPASS | `no-store` | BYPASS | Per-user, sensitive |
| `/sesamy/auth/*` | BYPASS | `no-store` | BYPASS | Per-request, cookies |
| `/sesamy/.well-known/*` | OK (1h) | `max-age=3600` | OK (1h) | Discovery docs |
| `/sesamy/assets/*` | OK (1y) | `max-age=31536000, immutable` | OK (forever) | Content-hashed URLs |
| All other WP pages | OK | publisher-controlled | publisher-controlled | Static per Capsule design |

Blanket "bypass everything under /sesamy/" would correctly avoid caching sensitive content but incorrectly avoid caching static assets we want aggressively cached. The cache compatibility module must distinguish between URL subpatterns.

### 6.3 Cloudflare Worker reference implementation

Sesamy publishes as a `wrangler`-deployable template:

```
sesamy-wp-proxy/
├── wrangler.toml
├── src/
│   ├── index.ts          # route dispatcher
│   ├── api.ts            # /api/* handler
│   ├── auth.ts           # /auth/* handler incl. callback
│   ├── assets.ts         # /assets/* handler
│   ├── wellknown.ts      # /.well-known/* handler
│   └── config.ts         # per-publisher config from env
└── README.md
```

Key behaviors:

- Reads `SESAMY_INTEGRATION_ID` and `SESAMY_SIGNING_KEY` from environment
- `/api/*`: signs outbound requests with integration credential, forwards to `api.sesamy.com`, returns response with `Cache-Control: no-store`
- `/auth/callback`: handles OAuth callback locally, sets first-party cookie, redirects back
- `/assets/*`: proxies with long TTL, serves from CF edge cache after first fetch
- `/.well-known/*`: proxies with short TTL

Publisher deploys via:

```bash
git clone https://github.com/sesamy/wp-proxy-template
cd wp-proxy-template
export SESAMY_INTEGRATION_ID=int_abc123
export SESAMY_SIGNING_KEY=sk_...
npx wrangler deploy
# Add route publisher.com/sesamy/* -> worker in CF dashboard
```

Or via the plugin's "Deploy to Cloudflare" button (automates through CF API).

### 6.4 PHP proxy fallback

```php
add_action('init', 'sesamy_register_proxy_routes');

function sesamy_register_proxy_routes() {
    add_rewrite_rule('^sesamy/(api|auth|assets)/(.+)$', 
                     'index.php?sesamy_proxy_type=$matches[1]&sesamy_proxy_path=$matches[2]', 
                     'top');
}

add_action('template_redirect', 'sesamy_handle_proxy');

function sesamy_handle_proxy() {
    $type = get_query_var('sesamy_proxy_type');
    $path = get_query_var('sesamy_proxy_path');
    if (!$type) return;
    
    $upstream = sesamy_url_upstream($path, $type);
    // ... proxy with signing, stream response back
    exit;
}
```

Same logical behavior, different perf/security profile.

### 6.5 Host-specific cache bypass configuration

| Cache system | Bypass mechanism |
|---|---|
| WP Super Cache | `wp_cache_add_pages_not_cached()` |
| WP Rocket | `rocket_cache_reject_uri` filter |
| W3 Total Cache | `pgcache.reject.uri` config entry |
| LiteSpeed Cache | `litespeed_cache_api_purge_post` + config API |
| Cloudflare plugin | Response `Cache-Control` headers honored; document page rules for "Cache Everything" |
| WP Engine | Cannot auto-configure; admin notice with support ticket link |
| Kinsta | Cannot auto-configure; admin notice |
| Pantheon | Cannot auto-configure; admin notice |
| Varnish (generic) | Generate VCL snippet, copy-paste instructions |

### 6.6 Diagnostic tool

`Settings → Sesamy → Routing → Run diagnostic`:

1. Probe `/sesamy/api/__probe` — expects `Cache-Control: no-store` and round-trip to Sesamy (verified via magic response header)
2. Probe `/sesamy/assets/__probe` — expects long-TTL `Cache-Control` with `immutable`
3. Probe `/sesamy/.well-known/openid-configuration` — expects valid JSON with matching `issuer` field
4. Check `sesamy_routing_mode` matches actual behavior
5. For each detected cache layer, verify bypass rules applied
6. Output: ✅ working / ⚠️ warnings / ❌ blocking issues with remediation steps

Same logic as `wp sesamy proxy test` CLI command.

---

## 7. Migration path from Memberful

### 7.1 The migration checklist (Stage 3)

1. **Pre-flight check.** Memberful active, Sesamy connected, at least one paywall strategy exists (or offer to auto-create).
2. **Analysis phase (dry run).** Read all Memberful data, cluster ACL patterns, suggest Sesamy strategies, show preview.
3. **Strategy creation.** Create suggested strategies in Sesamy with publisher approval.
4. **Post tagging.** Apply `sesamy_paywall_tag` terms based on existing `memberful_acl` config. Non-matching ACLs get `sesamy_override = always_gate`.
5. **Locked post conversion.** For each post that had Memberful gating, mark `_sesamy_locked = true` and ensure a `<!--more-->` block exists at the appropriate split point (insert at top if the publisher gated the entire post; preserve existing more-tag position otherwise). Encrypt on save.
6. **Marketing content migration.** Transfer `memberful_marketing_content` to per-strategy Sesamy templates or per-post override copy.
7. **User migration (shadow users only).** Copy `{prefix}_memberful_mapping` rows into `{prefix}_sesamy_mapping`, translate member IDs via email lookup.
8. **Private RSS token preservation.** Copy feed tokens so existing subscribed URLs keep working.
9. **Verification.** Sample-test 10 posts.
10. **Cleanup (explicit).** Delete `memberful_*` options, drop mapping table, deactivate Memberful.

### 7.2 Rollback plan

Until Step 10, migration is reversible:

- Memberful active throughout
- Memberful options and tables untouched
- Sesamy sees its data in new options/tables
- Roll back: deactivate Sesamy, Memberful takes over again

### 7.3 Staged migration for large sites

For 10k+ posts or 100k+ members:

- Posts in chunks of 500 via WP-Cron queue
- User mappings in chunks of 1,000
- Progress persisted; resumable if interrupted
- "Pause migration" button for overnight operations

---

## 8. Open questions

1. **Does Sesamy publish a PHP Capsule library, or do we build one against the spec?** Affects Stage 1.
2. **Key rotation cadence.** How often do issuer keys rotate in practice?
3. **Multi-issuer support.** Single WP site connecting to multiple Sesamy issuers?
4. **Preview/draft posts.** Authenticated editors see plaintext via `_sesamy_locked_plaintext`. Shared preview tokens gate on the token itself.
5. **Non-block editor support.** How many publishers are on Classic Editor only? Affects Stage 1 shortcode priority.
6. **Compliance and audit.** Publishers needing audit logs? Sesamy-side feature; WP contributes request context.
7. **Multisite.** Any publisher on WP multisite? May move Stage 6 multisite support earlier.
8. **Stage 1 beta cohort.** Which 2–3 Sesamy publishers go first?
9. **Sesamy-managed proxy investment (Stage 6).** Product/business decision needed before Stage 6 planning.
10. **Checkout proxying.** PCI scope — can checkout be proxied or must it stay at `checkout.sesamy.com`?
11. **Multi-domain publishers.** Session sharing across `publisher.com`, `publisher.co.uk` etc. in proxied mode needs OIDC federation, not just cookies.

---

## 9. Summary of what ships in each stage

| Stage | Weeks | Deliverable | Ship criterion |
|---|---|---|---|
| 0 | 1–2 | Connection flow, credentials, URL routing helper | Publisher connects and disconnects cleanly |
| 1 | 3–5 | Post-level locking via more-block + metabox, encryption under the hood, client JS, paywall | Publisher locks an article; subscriber reads it |
| 2 | 6–7 | Signals, paywall tags, override metabox, strategy preview | Publisher tags posts; sees which strategy matches |
| 2.5 | 7–9 | Proxy modes (WP, CF Worker), cache compat, diagnostic | Publisher switches to first-party proxying |
| 3 | 10 | Memberful shortcode/function/block-attr compat, importer | Memberful publisher runs importer; site works |
| 4 | 11–12 | Member-facing UI: shortcodes, widget, nav, private RSS, comments | Feature-parity with Memberful reader surfaces |
| 5 | 13–14 | WooCommerce, LearnDash, ad suppression, WP-CLI | Publishers with these needs covered |
| 6 | 15+ | Shadow users, role mapping, Sesamy-managed proxy, advanced editor tools | Polish, power-user features, deep Memberful parity |

Stages 0–2 are the minimum viable plugin (~5 weeks). Stage 2.5 overlaps Stage 3 for publishers who want first-party proxying. Stage 3 makes it a credible Memberful replacement. Stages 4–6 are ongoing polish after public launch.

## 10. Dependencies outside the plugin

- **AuthHero Issue #1 (confirmation screens)** — prerequisite for Stage 0
- **PHP Capsule library** — prerequisite for Stage 1
- **`connect.sesamy.com` worker** — prerequisite for Stage 0
- **Paywall strategies feature on Sesamy dashboard** — prerequisite for Stage 2
- **Sesamy webhook infrastructure** — needed from Stage 0; event coverage evolves
- **Sesamy API endpoints:** `/wordpress/activate`, `/wordpress/disconnect`, `/paywall/strategy-preview`, `/issuer/public-keys`, `/api/__probe`, `/assets/__probe`
- **Cloudflare Worker template** — prerequisite for Stage 2.5 CF mode; separate open-source repo
- **Sesamy proxy infrastructure** — prerequisite for Stage 6 managed proxy; significant investment
- **Coordination with managed hosts** (WP Engine, Kinsta, Pantheon, Flywheel) to get `/sesamy/*` onto default cache-bypass lists — improves Stage 2.5 experience

Most of these exist in some form on the Sesamy side; Stage 0 work includes auditing which need new endpoints vs adapting existing ones.
