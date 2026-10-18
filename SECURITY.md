# Security policy

## Reporting a vulnerability

Email **info@qaiyo-plugins.com** with the details and, if possible, a
proof-of-concept. Please do not open a public issue for an unfixed
vulnerability. We aim to acknowledge within 72 hours.

## Threat model

This plugin performs **bulk, irreversible-looking writes to post content**
(the featured image of published posts). The security posture is built around
that: every path that can write is bounded, authenticated and reversible.

### Authorisation

- Every AJAX endpoint verifies a nonce (`check_ajax_referer`) **and** the
  `manage_options` capability before reading a single request value.
- There is no `wp_ajax_nopriv_` endpoint: nothing here is reachable
  unauthenticated.
- The admin screen re-checks the capability in `render()`, not only in
  `add_menu_page()`.

### Bounded runs

A replacement is refused unless the criteria carry a valid `before` date.
Without that bound, a run would match *every* published post of the chosen
type. The check lives in `Qfibr_Criteria::is_bounded()` and is enforced in
three independent places:

1. the free AJAX preview and prepare endpoints,
2. the public API `Qfibr_Plugin::api_query_ids()` (returns an empty list),
3. the Pro background queue, before anything is queued.

### Reversibility

Every replacement stores the post's previous thumbnail ID once, in
`_qfibr_original_thumbnail_id`. "There was no image" is stored as `0` and
restores to *no image*, not to attachment `0`. The backup is never overwritten
by a second replacement, so the true original survives repeated runs.

### Input handling

- All request values are sanitised at the point of access
  (`sanitize_key`, `absint`, `sanitize_text_field`, `esc_url_raw`,
  `sanitize_email`).
- Post types are validated against `get_post_types()` **and**
  `post_type_supports( …, 'thumbnail' )`, so a crafted request cannot store an
  arbitrary key.
- The Pro server-load numbers are clamped to explicit ranges, not merely cast:
  these values decide how hard the background queue hits the server.
- Nothing is ever downloaded. A run can only point at an image that is already
  in the site's Media Library, resolved by attachment ID or local URL.

### Output handling

- All output is escaped at the point of echo.
- The only inline SVG (the Pro padlock badge) goes through `wp_kses()` with an
  explicit allowlist (`Qfibr_Kses::svg()`) that contains no event handlers, no
  `style` and no `href`.
- Templates are reached only through `Qfibr_View`, which pattern-checks the
  name and verifies with `realpath()` that the resolved file is inside
  `templates/admin/`. 34 hostile-name cases are covered by tests.

### Time-of-check / time-of-use

A queued background job can run hours after it was created. Before each write
the plugin re-checks that the target attachment and the target post still
exist, rather than trusting the snapshot taken when the job was queued.

### Concurrency

The Pro queue holds a lock transient carrying an owner token. A tick that
overran the lock TTL cannot release a lock a newer tick has since taken. The
lock is a stampede guard, not a distributed mutex: the work is idempotent
(re-running a replacement on an already-correct post reports `skipped`), so a
rare double tick costs duplicated effort, not corrupted data.

### Uninstall

`uninstall.php` walks **every site** on multisite (`get_sites()` +
`switch_to_blog()`); per-site data would otherwise be orphaned by a network
uninstall. Featured images themselves are never touched on uninstall — use
"Restore originals" first if you want the original images back.

## Supported versions

The latest released version receives security fixes.

## Verification

Each release is checked with: PHPUnit 9 + Brain Monkey unit tests, Xdebug
branch coverage, PHPStan level 5 (zero errors), WordPress Coding Standards
security sniffs with `--ignore-annotations`, and the official Plugin Check.
