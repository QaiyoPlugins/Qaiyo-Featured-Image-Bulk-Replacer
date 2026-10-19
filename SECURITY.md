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

- Every settings-screen AJAX endpoint verifies a nonce (`check_ajax_referer`)
  **and** the `manage_options` capability before reading a single request
  value.
- The featured image column in post lists has its own nonce and checks
  `edit_post` **for the specific post**, so an author can change only the
  posts they may edit. Users without that right see the thumbnail but no
  controls.
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

Category, tag and taxonomy filters are validated against the chosen post
type (`Qfibr_Query::inapplicable_filters()`). A filter that cannot apply —
for example a product category on posts — makes the run **refused**, not
silently dropped: dropping it would widen the run to every post of the type.
The same check guards the preview, the live run, the public API, the Pro
background queue and WP-CLI.

### Reversibility

Every replacement stores the post's previous thumbnail ID once, in
`_qfibr_original_thumbnail_id`. "There was no image" is stored as `0` and
restores to *no image*, not to attachment `0`. The backup is never overwritten
by a second replacement, so the true original survives repeated runs. The
list column and the broken-image cleanup write the same backup, so "Restore
originals" covers them too; the Pro run history restores only the posts a
given run actually changed.

### Input handling

- All request values are sanitised at the point of access
  (`sanitize_key`, `absint`, `sanitize_text_field`, `esc_url_raw`,
  `sanitize_email`).
- Post types are validated against `get_post_types()` **and**
  `post_type_supports( …, 'thumbnail' )`, so a crafted request cannot store an
  arbitrary key.
- The Pro server-load numbers are clamped to explicit ranges, not merely cast:
  these values decide how hard the background queue hits the server.
- The free plugin never downloads anything. A run can only point at an image
  that is already in the site's Media Library, resolved by attachment ID or
  local URL, and only **image** attachments are accepted.
- The random image pool is capped (50 IDs) and the add-on `extra` channel is
  capped (20 keys, 200 characters each, sanitised strings only).

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
exist, rather than trusting the snapshot taken when the job was queued. The
broken-image cleanup re-checks, right before each removal, that the post's
thumbnail still points at a missing attachment — an image fixed in the
meantime is left alone. Images whose file lives elsewhere (offloaded media
with an existing attachment record) are never treated as broken.

### Pro: remote downloads

The Pro add-on can download images (stock photos, video thumbnails, external
images found in post content). Every download:

- goes through `wp_safe_remote_get()` (no private / loopback addresses),
  with a timeout, a redirect limit and a response size cap;
- rejects URLs with credentials, non-HTTP(S) schemes, whitespace or control
  characters; stock photos and video thumbnails are additionally restricted
  to an **exact host allowlist** (`images.unsplash.com`, `pixabay.com`,
  `cdn.pixabay.com`, `i.ytimg.com`, `img.youtube.com`, `i.vimeocdn.com`);
- decides the file type from the **downloaded bytes** (`wp_get_image_mime()`),
  never from the URL; only JPEG, PNG, GIF, WebP and AVIF are accepted — no SVG;
- deletes the temporary file on every failure path;
- happens only for users who may upload files, and for automatic rules only
  when the site owner switched "Allow importing" on.

A stock-photo import never trusts a URL from the browser: it re-reads the
photo from the provider's API by a pattern-checked ID and downloads the image
URL the API returns.

### Pro: API keys

Unsplash and Pixabay keys are stored server-side only. They are never printed
into the page or sent to the browser; the screen shows a masked value with the
last four characters. Keys are validated against a strict character pattern
before they are stored.

### Pro: automatic rules

A rule never overwrites a featured image that is already set. The post is
checked before the rule runs and again after the image is resolved — resolving
can mean a download that takes seconds, during which an editor or another
plugin may have set an image. The publish hook is guarded against re-entrancy
(the guard is released in a `finally`, so an exception cannot leave the rules
switched off for the rest of the request), and rule images must be image
attachments.

### Pro: presets import

An imported preset file is size-limited, must carry this plugin's format
marker, is capped in count, and every preset is narrowed through the same
criteria sanitiser as the settings form; IDs are regenerated.

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
branch coverage, PHPStan level 10 (zero errors, no ignore rules) over the free
plugin and the Pro add-on together, WordPress Coding Standards security sniffs
with `--ignore-annotations`, and the official Plugin Check.
