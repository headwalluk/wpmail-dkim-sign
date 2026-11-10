# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WP Mail DKIM Sign DKIM-signs outbound `wp_mail()` messages at the WordPress layer. It sets
PHPMailer's DKIM properties at `phpmailer_init`, so the `DKIM-Signature` header is attached
before the message leaves the site for the upstream MTA. Single signing domain, RSA-2048.

- **Namespace:** `WPMail_DKIM_Sign` for all classes
- **Text Domain:** `wpmail-dkim-sign`
- **PHP:** 8.0+ (do NOT use `declare(strict_types=1)`, it breaks WordPress interop). Check any
  type syntax newer than 8.0 (`true`/`false`/`null` standalone types, `readonly`, `never`,
  enums, first-class callables) against the floor before using it. Nothing enforces it mechanically
- **WordPress:** 6.0+
- **No build system:** no npm, no Composer, no bundler. Assets are plain CSS/JS

Structure and conventions come from the maintainer's **reference plugin**, Quick 2FA
(`../quick-2fa/CLAUDE.md`). Where this file is silent, that one applies. Where this plugin
deliberately differs (PHP floor, date storage, the dual prefix), this file says so.

This plugin is published publicly on GitHub. Tracked files must contain no client names,
client URLs, server host names, fleet measurements or client data of any kind: in code,
comments, docs, fixtures or commit messages. Use `example.com` in examples.

`dev-notes/` is **private and untracked** (`.gitignore`), backed up with the dev site, and
blocked from the web by `dev-notes/.htaccess`. It is the right home for client-identifying
material. Never copy content from `dev-notes/` into a tracked file without scrubbing it, and
never reference a `dev-notes/` path from a file that ships in the release zip. Commits before
`chore: untrack dev-notes` still contain that material; history was not rewritten.

## Commands

```bash
phpcs                          # Check WordPress Coding Standards (configured in phpcs.xml)
phpcbf                         # Auto-fix coding standards violations
phpcs includes/class-signer.php  # Check a specific file
```

```bash
wp-translate . --check-instructions   # Is the block at the end of this file still current?
wp-translate . --sync-instructions    # Update it; review the diff afterwards
wp-translate . --dry-run              # Preview; no DeepL calls, no writes
```

Run `--check-instructions` after a `wp-translate` upgrade; if it reports drift, run
`--sync-instructions` and review the diff. Never hand-edit inside the
`wp-translate:begin`/`end` markers: the block is hash-validated and edits break it.

## Testing

There is no unit-test framework and none is wanted. Behaviour is exercised against the dev
site through WP-CLI. Private methods are reached with `ReflectionMethod`:

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

The GitHub updater is loaded separately from the main file, on admin, cron and WP-CLI requests
only. See **Porting the GitHub updater**.

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

`Signer::sign()` runs four pre-flight checks and skips signing, with a `WP_DEBUG` log line,
if any fails:

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
| `includes/class-github-updater.php` | Shared GitHub updater. Never edit here; see **Porting the GitHub updater** |
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

Treat everything in this table as a contract, whether or not it is documented as public:

| Contract | Examples | Breaks when |
|----------|----------|-------------|
| Filters | `wpdkm_updater_enabled`, `wpdkm_dns_zone` | renamed or removed, or an argument is removed or reordered |
| Stored keys and formats | `wpdkm_settings` and its field names, `wpdkm_keypair` | a constant's **value** or a stored format changes. A changed keypair key loses the private key, and signing stops until DNS is republished |
| Script and style handles | `wpdkm-admin` | renamed |
| Settings page URL | `options-general.php?page=wpmail-dkim-sign` (`MENU_SLUG`) | `MENU_SLUG` changes. Bookmarks and docs break |
| DKIM selector default | `website` (`DEF_SELECTOR`) | changed. New installs would publish a different DNS record name from the docs |
| Release assets | `wpmail-dkim-sign.zip`, `wpmail-dkim-sign-<version>.zip` | renamed. Every installed copy stops finding updates |

- **Add, don't change.** New filter arguments go at the end. New behaviour gets a new filter
- **Deprecate, don't rename.** Fire the old name through `apply_filters_deprecated()` and pass
  its result into the new filter. Remove the old name no earlier than the next major version
- **Stored data has no migration path.** Changing a stored key or value format is a breaking
  change: stop and ask

Before changing anything in the table: name the contract, assume there are consumers you can't
see, take the additive path if one exists, record it in `CHANGELOG.md`, and if you can't tell
whether anything depends on it, stop and ask.

### High-Impact Code

These paths decide whether mail is signed, and whether the signature verifies:

- `Signer::sign()`, its pre-flight checks and `apply_dkim_config()`
- `Keypair` generation, storage and `load()`
- `Settings::sanitize_domain()` and `sanitize_selector()`, which produce `d=` and `s=`

When a change touches one, say so, and recommend the maintainer read that diff line by line
before tagging. A review by an AI agent, your own included, does not count as that read.

### Where the Plugin Runs

- **Request context.** `Signer::sign()` runs under cron, REST, AJAX, WP-CLI and front-end
  requests, where there may be no current user or admin screen. Check for what the code needs
- **Install layout.** Build URLs and paths with `admin_url()`, `home_url()`, `WPDKM_URL` and
  `WPDKM_PATH`, never by joining strings onto the domain
- **Multisite is untested.** Settings and the key are per-site options

## Code Conventions

Quick 2FA's **Code Conventions** apply in full: SESE, a plain `else` closing every `if` with
`elseif` branches, no assignment in conditions, no unreachable `return`, constants for magic
values, type hints everywhere, `mixed` parameters on callbacks for hooks the plugin doesn't
own, checking what a filter returns, `instanceof` guards on object lookups, casting at the
boundary, code-first `printf()` templates, logical CSS properties, plain JavaScript,
`filter_var( ..., FILTER_VALIDATE_BOOLEAN )` for booleans, and the comment rules. The
differences and additions for this plugin:

- **Dates are stored as `DATE_FORMAT` strings** (`Y-m-d H:i:s T`, from
  `DateTime( 'now', wp_timezone() )`), not Unix timestamps as in Quick 2FA. The keypair
  metadata already holds them, so the format is a stored contract: don't change it
- **Template partials scope their locals in an immediately-invoked closure** rather than prefixing
  them, and list what they receive in a `@var` docblock
- **Logging.** Hard failures (OpenSSL errors in `Keypair`) log unconditionally. Routine flow
  (signing skip reasons in `Signer`) logs only under `WP_DEBUG`. There is no logging
  dependency; `error_log()` with a `phpcs:ignore` naming the sniff and a reason is correct
- **Translatable strings follow the wp-translate block below.** Avoid words with two common
  senses in short strings ("Fixed by" came back from DeepL as "repaired by")

### Commit Messages

Format: `type: brief description` where type is one of: `feat:`, `fix:`, `chore:`,
`refactor:`, `docs:`, `style:`, `test:`

### Pre-Commit Workflow

1. `phpcs`: check violations
2. `phpcbf`: auto-fix
3. `phpcs`: verify clean, with no errors **and no warnings**
4. Stage and commit

Every `phpcs:ignore` and `phpcs:disable` names the exact sniff and ends with `-- reason`.

## Porting the GitHub updater

`includes/class-github-updater.php` is a copy of the shared Headwall updater, maintained in
Quick 2FA. It differs from that copy only in its `namespace` and `@package` lines. **Never
edit it here:** fix it in Quick 2FA, test it there, then copy it across. To check for drift:

```bash
diff <(grep -v '^namespace \|@package ' ../quick-2fa/includes/class-github-updater.php) \
     <(grep -v '^namespace \|@package ' includes/class-github-updater.php)
```

It is constructed in `wpmail-dkim-sign.php` with `WPDKM_FILE`, `UPDATER_GITHUB_REPO`,
`WPDKM_VERSION` and the `wpdkm_updater_enabled` filter name. `uninstall.php` calls
`Github_Updater::delete_cache()` on opt-in removal.

## Release Workflow

1. Update the version in `wpmail-dkim-sign.php`: **both** the `Version:` header and the
   `WPDKM_VERSION` constant
2. Update `CHANGELOG.md`: move the `[Unreleased]` entries under the new version, and give each
   bug fix the version that introduced it, when known. Update any new `@since` tags to match
3. Update `readme.txt` stable tag
4. Run `phpcs` (clean) and `wp-translate . --check-instructions`
5. If the release touches **High-Impact Code**, the maintainer reads that diff line by line
6. Tag the release in git (`vX.Y.Z`)

The version lives in **three** places that must agree with the git tag: the header `Version:`,
`WPDKM_VERSION`, and the `readme.txt` stable tag. `.github/workflows/release.yml` refuses to
build on a mismatch. The `Version` must always correspond to a real GitHub Release tag, or the
updater advertises an update that doesn't exist.

## Reference Files

`docs/` is tracked and public. **One audience per document:**

- `docs/installation.md`, `docs/how-it-works.md`, `docs/configuration.md`,
  `docs/troubleshooting.md`: site administrators
- `docs/self-hosting.md`: server administrators
- `docs/developers/hooks-and-filters.md`: the public extension surface

Rationale, evidence and history belong in docs, not in code comments. Where a mechanism needs
more than a sentence to justify, write it up and reference the file from the code.

Supporting material (private, untracked):

- `dev-notes/00-project-tracker.md`: milestones and validation records
- `dev-notes/01-decisions.md`: numbered design decisions (D1, D2, ...), including the prefix
  scheme (D12) and canonicalisation (D11)
- `dev-notes/02-architecture.md`: architecture notes
- `dev-notes/03-snagging-list.md`: small agreed items awaiting a convenient moment. Check it
  before starting work: an item there may already cover the file you are about to touch
- `dev-notes/archive/`: superseded material, including the retired
  `copilot-instructions.md` and the old `patterns/` and `workflows/` guides. Archived, not
  authoritative: **this file is the standard**

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
