# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Repository: public

Follows the **headwall-wp-plugin** skill, which holds the shared conventions: PHP style,
templates, logging, comments, public-contract rules, testing, release and the GitHub updater.
Load it before working on this plugin. This file holds only what is specific to WP Mail DKIM
Sign.

## Project Overview

WP Mail DKIM Sign DKIM-signs outbound `wp_mail()` messages at the WordPress layer. It sets
PHPMailer's DKIM properties at `phpmailer_init`, so the `DKIM-Signature` header is attached
before the message leaves the site for the upstream MTA. Single signing domain, RSA-2048.

- **Namespace:** `WPMail_DKIM_Sign` · **Text domain:** `wpmail-dkim-sign` · **Prefixes:**
  `WPDKM_` / `wpdkm_` (see **Prefixes**)
- **PHP / WordPress floor:** 8.2 / 6.9, the skill's default
- **No build system:** no npm, no Composer, no bundler. Assets are plain CSS/JS

Commits before `chore: untrack dev-notes` still contain client material from `dev-notes/`;
the history was not rewritten.

## Testing

There are no harnesses in `dev-notes/testing/` yet (see `dev-notes/03-snagging-list.md`).
Until there are, exercise changes through WP-CLI against the dev site. Private methods are
reached with `ReflectionMethod`:

```bash
# Run the settings sanitiser without saving anything
wp eval '$method = new ReflectionMethod( "WPMail_DKIM_Sign\\Settings", "sanitize_settings" );
var_export( $method->invoke( null, array( "domain" => "example.com", "selector" => "website" ) ) );'
```

- **Never mutate real `wp_options` rows** (`wpdkm_settings`, `wpdkm_keypair`) or run a
  destructive path (key regeneration, key deletion, uninstall) without saving and restoring
  the original value, or explicit permission. Regenerating the key breaks signing until DNS
  is republished
- Delete any cache transient a test writes (`Github_Updater::delete_cache( 'wpmail-dkim-sign' )`)
- Test the defensive path as well as the happy one: a missing key, a `From:` domain that does
  not match, another plugin having already set `DKIM_domain`
- PHPMailer is not loaded under `wp eval` until something sends mail. `require_once` it from
  `ABSPATH . WPINC . '/PHPMailer/'` before constructing one

## Architecture

### Entry Point & Initialization

`wpmail-dkim-sign.php` defines the root-namespace `WPDKM_*` constants, requires every class
file (no autoloader), then `wpdkm_run()` constructs `Plugin` into the `$wpdkm_plugin` global
and calls `run()`. Sub-services reach the orchestrator through `get_plugin()` in
`functions-private.php`, and the orchestrator creates each sub-service lazily on first access.

The GitHub updater is constructed in the main file, on admin, cron and WP-CLI requests only,
with `WPDKM_FILE`, `UPDATER_GITHUB_REPO`, `WPDKM_VERSION` and the `wpdkm_updater_enabled`
filter name.

### Hook Strategy

All hooks other than the updater's are registered in `Plugin::run()`.

- **`phpmailer_init` (priority 10, `SIGNER_PRIORITY`):** `Signer::sign()`, in every request
  context, because mail is sent from cron, REST and front-end requests too
- **`phpmailer_init` (priority 999, `CAPTURE_PRIORITY`):** `Verifier::capture_headers()`, added
  only for the duration of a test send and removed straight after, so it records the headers
  after signing
- **`init`:** loads the text domain
- **Admin only:** the settings menu, admin assets, and the `admin_post_*` handlers for the
  `ACTION_*` constants

### Signing Flow

`Signer::sign()` checks it was given a `PHPMailer` instance, then runs four pre-flight checks,
and skips signing, with a `WP_DEBUG` log line, if any fails:

1. Signing is enabled
2. The stored private key loads
3. `DKIM_domain` is still empty (another plugin has not already signed)
4. The `From:` domain matches the signing domain (otherwise DMARC alignment would fail)

It then sets `DKIM_domain`, `DKIM_selector`, `DKIM_private_string` (the in-memory PEM, never a
file path), empty `DKIM_identity` and `DKIM_passphrase`, and `DKIM_copyHeaderFields = false`.

**Canonicalisation is always `relaxed/simple`.** PHPMailer hardcodes it in `DKIM_Add()` and
has no property to change it. Do not add a setting for it. `PHPMAILER_CANONICALISATION`
exists only to show the value on the Setup tab. Only set PHPMailer properties that PHPMailer
declares: any other name creates a dynamic property, which is deprecated from PHP 8.2.

### Key Files

| File | Purpose |
|------|---------|
| `wpmail-dkim-sign.php` | Main plugin file: `WPDKM_*` defines, class loading, bootstrap, updater loading |
| `constants.php` | Namespaced constants: option keys, defaults, action and nonce names, notices, transient prefixes, priorities |
| `functions-private.php` | `get_plugin()` accessor and internal helpers, including the DNS zone lookup behind `wpdkm_dns_zone` |
| `includes/class-plugin.php` | Orchestrator: hook registration and lazy sub-service accessors |
| `includes/class-settings.php` | Settings page, form handlers, sanitisers, page data for the templates |
| `includes/class-keypair.php` | RSA key generation, storage and loading, public key for the DNS record |
| `includes/class-signer.php` | `phpmailer_init` signing and its pre-flight checks |
| `includes/class-verifier.php` | Verify tab: test email with header capture, DNS check and misconfiguration probes |
| `includes/class-github-updater.php` | The shared updater, ported from Quick 2FA. Never edit it here; see the skill's `github-updater.md` |
| `admin-templates/` | Settings page and its partials (code-first templates) |
| `uninstall.php` | Opt-in data removal |

### Data Storage

- **`wpdkm_settings`** (`OPT_SETTINGS`): one array holding `domain`, `selector`, `enabled`
  and `remove_on_uninstall`. Settings are merged over computed defaults on read, so a key
  removed from the code is ignored on existing sites and dropped on the next save
- **`wpdkm_keypair`** (`OPT_KEYPAIR`): the private key PEM and its metadata
- **Transients:** test-email capture and DNS-check results (`TRANSIENT_*_PREFIX`, 30 minutes),
  the DNS zone lookup (24 hours), and the updater's release cache and failure back-off
- **Uninstall deletes nothing by default.** Only with *Remove all data on uninstall* ticked
  does `uninstall.php` delete both options and the updater cache. Deactivation never deletes data

### Prefixes

Two prefixes, by where a name lives:

- **`WPMail_DKIM_Sign\`** for class code and namespaced constants
- **`WPDKM_` / `wpdkm_`** for anything global or stored: root-namespace defines, global
  functions, `wp_options` keys, filter names, `admin-post` action names, nonce actions,
  transient keys, script and style handles

`phpcs.xml` allow-lists both for `WordPress.NamingConventions.PrefixAllGlobals`. The updater's
cache transients are the one exception: the shared updater names them from the slug
(`wpmail_dkim_sign_github_release`, `_github_failed`).

## Public Contracts

The skill's `public-contracts.md` has the rules. This plugin's contracts:

| Contract | Examples | Breaks when |
|----------|----------|-------------|
| Filters | `wpdkm_updater_enabled`, `wpdkm_dns_zone` | renamed or removed, or an argument is removed or reordered |
| Stored keys and formats | `wpdkm_settings` and its field names, `wpdkm_keypair` | a constant's **value** or a stored format changes. A changed keypair key loses the private key, and signing stops until DNS is republished |
| Stored date format | keypair metadata dates, `DATE_FORMAT` (`Y-m-d H:i:s T`) | the format changes. Dates are stored as these strings, not Unix timestamps |
| Script and style handles | `wpdkm-admin` | renamed |
| Settings page URL | `options-general.php?page=wpmail-dkim-sign` (`MENU_SLUG`) | `MENU_SLUG` changes. Bookmarks and docs break |
| DKIM selector default | `website` (`DEF_SELECTOR`) | changed. New installs would publish a different DNS record name from the docs |
| Release assets | `wpmail-dkim-sign.zip`, `wpmail-dkim-sign-<version>.zip` | renamed. Every installed copy stops finding updates |

### High-Impact Code

These paths decide whether mail is signed, and whether the signature verifies:

- `Signer::sign()`, its pre-flight checks and `apply_dkim_config()`
- `Keypair` generation, storage and `load()`
- `Settings::sanitize_domain()` and `sanitize_selector()`, which produce `d=` and `s=`

### Where the Plugin Runs

- **Request context.** `Signer::sign()` runs under cron, REST, AJAX, WP-CLI and front-end
  requests, where there may be no current user or admin screen. Check for what the code needs
- **Multisite is untested.** Settings and the key are per-site options

## Conventions specific to this plugin

- **Template partials scope their locals in an immediately-invoked closure** rather than
  prefixing them, and list what they receive in a `@var` docblock
- **Short translatable strings:** avoid words with two common senses ("Fixed by" came back
  from DeepL as "repaired by")

## Release

Follow the skill's `release.md` (public). The version lives in the `Version:` header and
`WPDKM_VERSION`; `.github/workflows/release.yml` refuses to build unless both match the tag.

## Reference Files

`docs/` is tracked and public, one audience per document:

- `docs/installation.md`, `docs/how-it-works.md`, `docs/configuration.md`,
  `docs/troubleshooting.md`: site administrators
- `docs/self-hosting.md`: server administrators
- `docs/developers/hooks-and-filters.md`: the public extension surface

`dev-notes/` (private, untracked):

- `dev-notes/00-project-tracker.md`: milestones and validation records
- `dev-notes/01-decisions.md`: numbered design decisions (D1, D2, ...), including the prefix
  scheme (D12) and canonicalisation (D11)
- `dev-notes/02-architecture.md`: architecture notes
- `dev-notes/03-snagging-list.md`: small agreed items awaiting a convenient moment. Check it
  before starting work: an item there may already cover the file you are about to touch
- `dev-notes/archive/`: superseded material, including the retired `copilot-instructions.md`
  and the old `patterns/` and `workflows/` guides. Archived, not authoritative

## Translations

Run `wp-translate . --check-instructions` after a `wp-translate` upgrade; if it reports drift,
run `wp-translate . --sync-instructions` and review the diff. Never hand-edit inside the
`wp-translate:begin`/`end` markers: the block is hash-validated and edits break it.

<!-- wp-translate:begin v=1.2.0 hash=29b7e4d6bfac8a4c97a450265016f270234ae13c4b35f17195732c5543efc7d0 -->
## Translating this plugin (wp-translate conventions)

This plugin's `.po`/`.mo` files are generated from source by
[wp-translate](https://github.com/headwalluk/wp-translate-tool), which
machine-translates strings with DeepL. Machine translation is only as good as
the strings you give it — follow these conventions when adding or editing
user-facing text.

### 1. Disambiguate short or ambiguous strings with `_x()`

DeepL handles full sentences well but guesses badly on short, context-free
labels. Give it context with `_x()` (or `esc_html_x()`, `_ex()`):

```php
// Ambiguous out of context — DeepL may read "Sent" as "late", "Folder" as "leaflet"
__( 'Sent', 'wpmail-dkim-sign' );

// Disambiguated — the context is passed to the translator and to DeepL
_x( 'Sent', 'email delivery status', 'wpmail-dkim-sign' );
_x( 'Folder', 'IMAP mailbox', 'wpmail-dkim-sign' );
_x( 'Open', 'verb; button label', 'wpmail-dkim-sign' );
```

The context (2nd argument) is never shown to users. Use it whenever a string is a
single word, a short label, or has more than one plausible meaning.

### 2. Use placeholders, never concatenation

Build dynamic text with `printf`/`sprintf` so the whole sentence translates as a
unit, and add a `translators:` comment to explain each placeholder:

```php
/* translators: %s is the user's display name */
printf( esc_html__( 'Welcome back, %s', 'wpmail-dkim-sign' ), $name );
```

Never split a sentence across multiple translation calls — word order differs
between languages.

### 3. Use `_n()` for anything that can be counted

Never build a count-dependent sentence by hand, and never settle for a single
form that reads correctly only for one number. Languages differ in how many
plural forms they have — English and German have two, French treats 0 as
singular, Polish and Russian have three, Japanese has one, Arabic has six — and
`_n()` is the only way to express that.

```php
// Wrong — "1 reviews", and untranslatable into languages with other forms
printf( esc_html__( '%d reviews', 'wpmail-dkim-sign' ), $count );

// Right — wp-translate fills every form the target locale needs
printf(
    esc_html( _n( '%d review', '%d reviews', $count, 'wpmail-dkim-sign' ) ),
    $count
);
```

Keep the placeholder in **both** forms, even when the singular reads fine
without it (`'%d review'`, not `'One review'`) — some locales use the singular
slot for other numbers too.

For a short or ambiguous countable noun, use `_nx()` — the plural equivalent of
`_x()` — so the context reaches DeepL:

```php
// "Review" alone is ambiguous: critique? opinion? inspection?
_nx( '%d review', '%d reviews', $count, 'customer feedback on a company', 'wpmail-dkim-sign' );
```

**Locales needing more than two forms will have their extra slots left empty for
a human translator.** DeepL supplies a singular and a plural; nobody can invent
Polish's third form from those, and wp-translate deliberately leaves it blank
rather than filling it with a plausible guess. Expect to see empty
`msgstr[2]` entries in `pl_PL` — that is correct behaviour, not a failure.

### 4. Acronyms and technical tokens

wp-translate keeps common acronyms (`TLS`, `API`, `SMTP`, `URL`, `ID`, `UTC`, …)
verbatim automatically. If you introduce an unusual acronym or product name that
must not be translated, keep it as its own standalone string so it is recognised,
or ask the maintainer to add it to the tool's acronym list.

### 5. Don't translate dates — let WordPress localise them

Never add month or day-of-week names (full or abbreviated) as translatable
strings. DeepL frequently mistranslates short forms like `Mon`, `Tue`, `Jan`,
`Feb` even with context hints. WordPress already ships locale-aware names — use
`$wp_locale`:

```php
global $wp_locale;
$wp_locale->get_month( $month_number );        // "January" (1-based)
$wp_locale->get_month_abbrev( $month_name );   // "Jan"
$wp_locale->get_weekday( $weekday_number );     // "Monday" (0 = Sunday)
$wp_locale->get_weekday_abbrev( $weekday_name ); // "Mon"
```

For formatted dates, prefer `wp_date()` / `date_i18n()`, which localise month and
day names automatically.

### 6. English source dialect

Write source strings in standard English. wp-translate handles English targets
locally (no DeepL): `en`/`en_US` use the source as-is, and `en_GB`/`en_AU`/… get
American spellings converted to British automatically (`color` → `colour`).

### Running wp-translate

After changing strings, regenerate translations:

```bash
wp-translate /path/to/this-plugin              # auto-detect locales from languages/
wp-translate /path/to/this-plugin en_GB,fr_FR  # explicit locales
wp-translate /path/to/this-plugin --dry-run    # preview; no API calls, no writes
```

Requires WP-CLI (`wp`) and a DeepL API key at `~/.config/deepl.env`. The tool
regenerates the `.pot` from source, translates new/changed strings for each
locale, and compiles the `.mo` files.
<!-- wp-translate:end -->
