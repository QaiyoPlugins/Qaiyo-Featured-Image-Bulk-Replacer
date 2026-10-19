# Qaiyo Featured Image Bulk Replacer

> WordPress maintenance tool that swaps the featured image of published posts for an image that is already in your Media Library — one image or a random set, filtered by date, category, tag, author or any taxonomy, previewed first, processed in batches that never time out, and restorable with one click. Plus a featured image column in every post list and a broken-image cleanup.

[![WordPress 6.2+](https://img.shields.io/badge/WordPress-6.2%2B-21759b.svg)](https://wordpress.org/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![Free](https://img.shields.io/badge/free-1.3.0-6c5ce7.svg)](#free-plugin)
[![Pro](https://img.shields.io/badge/pro-1.1.0-f39c12.svg)](#pro-add-on)
[![WCAG 2.2 AA](https://img.shields.io/badge/WCAG-2.2%20AA-2e7d32.svg)](#accessibility)

This repository hosts the **free** Qaiyo Featured Image Bulk Replacer plugin. The optional
[Qaiyo Featured Image Bulk Replacer Pro](https://qaiyo-plugins.com/qaiyo-featured-image-bulk-replacer-pro/)
add-on adds background processing and scheduling, rule-based automatic featured images, a
title-image generator, Unsplash / Pixabay search, presets, WooCommerce product filters, WP-CLI
and run history with per-run restore.

- **Website:** [qaiyo-plugins.com](https://qaiyo-plugins.com)
- **Support:** info@qaiyo-plugins.com
- **Issues:** [GitHub Issues](../../issues)

---

## Why this plugin

Changing the featured image of hundreds of posts usually means one of two things: opening every
post by hand, or running a SQL query against `_thumbnail_id` and hoping. The first takes a day;
the second has no preview, no undo, and silently breaks WooCommerce and page caches.

This plugin is the careful middle path. It **only ever points posts at images you already own**
(the free plugin downloads nothing), shows you exactly which posts a run will touch before it
touches them, processes them in small batches, and keeps the original of every post it changes
so the whole thing can be undone.

The plugin is designed to be:

- **Safe by default** — a run must be bounded by a date; a category / tag / taxonomy filter that
  does not apply to the chosen post type makes the run *refused*, never silently widened.
- **Reversible** — the first original of every changed post is stored once and survives repeated
  runs. Bulk runs, list-column edits and broken-image cleanups all restore the same way.
- **Server-friendly** — AJAX batches with a progress bar in the free plugin; a time-boxed,
  memory-aware background queue with an off-peak window in Pro.
- **Accessible** — WCAG 2.2 AA admin screens (see [Accessibility](#accessibility)).
- **Extensible, never coupled** — the Pro add-on uses the same documented hooks your own code can
  use ([HOOKS.md](HOOKS.md)).
- **Translation-ready** — ten translations bundled, Polylang / WPML / TranslatePress compatible.

---

## Free plugin

### Bulk replacement

| Option | What it does |
|---|---|
| **Image** | Pick from the Media Library, or paste the URL of an uploaded image. Only image attachments are accepted. |
| **Random assignment** | Choose 2–50 images; each post gets one of them. The pick is derived from a run seed and the post ID, so a retried batch gives a post the *same* image again. |
| **Before / after date** | Posts published strictly before the "before" date (required) and on or after the optional "after" date. |
| **Scope** | All matching posts, or only posts that have no featured image yet. |
| **Post type** | Any public post type that supports featured images. |
| **Category, tag, author, other taxonomy** | Narrow the run further. Taxonomy filters must apply to the post type. |
| **Alt / title text** | Optionally set the chosen image's alt and title once. |

Then **Preview** (thumbnails of every affected post, with "would replace" / "would skip"), **Test
run** on the first few posts, or **Live run** for all of them.

### Featured image column

A compact 44 px column in the Posts, Pages and custom post type lists shows each post's featured
image. Click the thumbnail to choose another image from the Media Library, or the × button to
remove it — without opening the editor. The controls appear only for users who may edit that post,
and every change is restorable. The column has a fixed width, so the Title column keeps its room
at 1024, 1280 and 1440 px wide screens alike.

### "Featured image of" in the Media Library

A Media Library column and a field in the attachment details list the posts that use an image as
their featured image — useful before deleting an image.

### Broken featured images

Scans for posts whose `_thumbnail_id` points at an attachment that no longer exists (typically left
behind by a deleted image or a botched migration) and removes the dangling reference. Each post is
re-checked right before it is changed; images whose file is stored elsewhere (offloaded media with an
attachment record) are never treated as broken.

### Restore

**Restore originals** puts back the original featured image of every post the plugin has changed,
in batches. "There was no image" restores to no image.

---

## Pro add-on

The Pro add-on renders its cards into the same screen and requires the free plugin **1.3.0 or
newer** — with an older free plugin it stays off and says so, instead of failing.

### Background processing & scheduling

A self-managed WP-Cron queue processes posts in time-boxed ticks (5–60 s), stops early when PHP
memory gets tight, pauses between ticks if you want, and can be restricted to an off-peak window.
Start now or at a chosen date and time; close the browser and get an email when it is done.
Replacements, "restore all" and broken-image cleanups can all run in the background. One job runs
at a time, guarded by an owner-token lock.

### Rule-based automatic featured images

When a post is published without a featured image, the first matching rule sets one. Rules match
by post type, category / tag / taxonomy term, author and a word in the title, and take their image
from:

- fixed images (random if several),
- the first image of the content, a gallery, or an embedded YouTube / Vimeo video,
- an external image in the content,
- a generated title image (optionally as the fallback of any rule).

A rule never overwrites an image somebody chose — the post is checked again after the image is
resolved, because a download can take seconds. Downloads are opt-in. Rules can also be applied to
existing posts in the background.

### Title-image generator

Creates a featured image from the post title: size presets, solid or gradient background, text
colour, weight, font size (long titles shrink to fit), alignment, margin and an optional site name.
Live preview with a WCAG contrast check. Uses the bundled Work Sans font (SIL Open Font License);
a different font can be supplied through `qfibrp_title_image_font`.

### Stock photos

Search Unsplash or Pixabay from the dashboard and import a photo into the Media Library with its
attribution stored as the caption. API keys stay on the server and are shown masked. Imports
follow the Unsplash API guidelines (download tracking, attribution links with UTM parameters).

### Presets, WooCommerce, WP-CLI, history

- **Presets** — save the replacement settings under a name, load them in one click, move them
  between sites as JSON.
- **WooCommerce** — filter products by stock status and product type. Product images are saved
  through WooCommerce CRUD, so product caches and lookup tables stay correct.
- **WP-CLI** — `wp qfibr replace | preview | restore | cleanup | rules | title-image | history | presets`.
- **Run history** — the last 20 runs, each restorable on its own; only the posts that run
  actually changed are restored.

---

## Installation

### From a ZIP file

1. Download a [release ZIP](../../releases) and use **Plugins → Add New → Upload Plugin**.
2. Activate. A **Featured Images** menu appears in the Qaiyo group of the admin sidebar.
3. Choose an image (or several), set the filters, then Preview, Test run or Live run.

For the Pro add-on: install and activate the free plugin first, then upload
`qaiyo-featured-image-bulk-replacer-pro.zip` and enter the licence key under
**Featured Images → License**.

### From source (developers)

```bash
git clone https://github.com/qaiyo/qaiyo-featured-image-bulk-replacer.git
ln -s "$(pwd)/qaiyo-featured-image-bulk-replacer" /path/to/wordpress/wp-content/plugins/
```

**Requirements:** WordPress 6.2+ (tested up to 7.1), PHP 7.4+. Pro title images need GD with
FreeType. Optional: WooCommerce for the product filters.

---

## Developer API

The full reference — filters, actions, the `Qfibr_Plugin` facade, the criteria array, stored data,
AJAX endpoints and WP-CLI — is in **[HOOKS.md](HOOKS.md)**. In short:

| Hook | Type | Purpose |
|---|---|---|
| `qfibr_criteria` | filter | Adjust the sanitised criteria; add-on fields arrive in `extra`. |
| `qfibr_query_args` | filter | Adjust the `WP_Query` arguments of a run. |
| `qfibr_criteria_fields` | action | Print extra fields into the replacement form. |
| `qfibr_featured_image_changed` | action | `( $post_id, $new_id, $old_id )` after every change — clear caches here. |
| `qfibr_run_complete` | action | After a foreground run, restore or cleanup. |
| `qfibr_pro_active`, `qfibr_upgrade_url`, `qfibr_pro_admin_cards` | filter / filter / action | The free ↔ Pro contract. |

Filter callbacks receive whatever other code returned, so the plugin's own callbacks declare no
parameter or return types and pass unexpected values through unchanged — follow the same rule in
yours (see the example in HOOKS.md).

---

## Accessibility

Both the free plugin and the Pro cards target **WCAG 2.2 AA**:

- every field has a visible `<label>`; radio and checkbox groups use `<fieldset>` / `<legend>`;
  help text and errors are tied to their field with `aria-describedby`, invalid fields get
  `aria-invalid` and a focusable error;
- progress is a real `role="progressbar"` with `aria-valuenow`, and results, saves and errors are
  announced through `wp.a11y.speak()` and polite live regions;
- icon-only buttons (list column, rule reordering, stock photo picks) carry a full accessible name
  such as *Choose featured image for "Post title"*, and focus stays on a moved rule;
- text contrast ≥ 4.5:1, UI borders ≥ 3:1, visible `:focus-visible` outlines, click targets
  ≥ 24 × 24 px, links that open a new tab say so;
- `forced-colors` and `prefers-reduced-motion` are respected.

Every admin template is rendered in the unit tests and checked for unlabelled controls and unnamed
buttons.

---

## Translations

Both plugins ship with English plus ten languages in `/languages/` (`.pot`, `.po`, `.mo`):
Hungarian, German, French, Spanish, Japanese (`ja`), Portuguese (`pt_PT`), Italian, Russian,
Turkish and Polish. Locale variants fall back to their base locale (`de_AT → de_DE`,
`pt_BR → pt_PT`, `ja_JP → ja`), loaded with `load_textdomain()`.

---

## Standards & security

See **[SECURITY.md](SECURITY.md)** for the threat model. Highlights:

- every endpoint checks a nonce and a capability (`manage_options` for the settings screen,
  `edit_post` per post for the list column); there are no unauthenticated endpoints;
- bounded runs only; inapplicable filters refuse the run; every write re-checks its target;
- everything printed is escaped where it is printed; the escaping sniff is run with
  `--ignore-annotations`;
- Pro downloads use `wp_safe_remote_get()`, exact host allowlists where possible, type detection
  from the file bytes (raster images only, no SVG) and a size cap;
- multisite-aware uninstall in both plugins.

---

## Development

### Repository layout

```
qaiyo-featured-image-bulk-replacer.php   Main file: header, constants, class loading, translations
uninstall.php                            Multisite-aware cleanup of the backup meta and options
includes/
  class-qfibr-plugin.php         Facade + public API the Pro add-on builds on
  class-qfibr-criteria.php       Reading, sanitising and validating the filter set
  class-qfibr-query.php          Post enumeration; refuses inapplicable taxonomy filters
  class-qfibr-image-picker.php   Deterministic random pick per post
  class-qfibr-replacer.php       The write path: replace, remove, restore (one post)
  class-qfibr-attachment.php     Media Library image resolution
  class-qfibr-orphans.php        Broken featured image scan and cleanup
  class-qfibr-runner.php         Batch execution over a queued job
  class-qfibr-job-store.php      Foreground job storage
  class-qfibr-preview.php        Read-only preview
  class-qfibr-ajax.php           Settings-screen AJAX endpoints
  class-qfibr-list-column.php    Featured image column in post lists
  class-qfibr-usage.php          "Featured image of" in the Media Library
  class-qfibr-admin-page.php     Admin screen controller
  class-qfibr-admin-assets.php   Admin CSS / JS
  class-qfibr-pro-teaser.php     Locked Pro cards when Pro is not active
  class-qfibr-view.php           Traversal-safe template renderer
  class-qfibr-kses.php           Escaping allowlists
  class-qfibr-cast.php           Narrowing of WordPress' untyped values
  class-qfibr-brand-menu.php     Qaiyo admin menu group
  class-qfibr-more-plugins.php   "More plugins" discovery panel
templates/admin/                 page, orphan-result, pro-teaser
assets/css, assets/js            Admin screen + list column
languages/                       .pot, .po, .mo
```

The Pro add-on (`qaiyo-featured-image-bulk-replacer-pro/`) follows the same layout with the
`Qfibrp_` prefix: background queue (`background`, `job-store`, `settings`, `offpeak`, `memory`,
`reporter`), `rules` + `content-images` + `sideloader`, `title-image`, `stock`, `presets`, `woo`,
`cli`, `history`, and view models in `admin-data` rendered through `templates/admin/`.

### Quality gates

Development tooling lives in the sibling `qaiyo-featured-image-bulk-replacer-dev-tools/` folder,
outside the plugin, so the plugin folder stays exactly what is shipped. It serves the free plugin
and the Pro add-on together, so the free ↔ Pro contract is analysed and tested as one codebase:

- PHPUnit 9 + Brain Monkey unit tests with Xdebug branch coverage;
- PHPStan level 10 with zero errors and no ignore rules;
- WordPress Coding Standards security sniffs with `--ignore-annotations`;
- the official Plugin Check with zero errors;
- a placeholder check over every translation.

---

## License

GPL-2.0-or-later. The bundled Work Sans font in the Pro add-on is licensed under the SIL Open Font
License 1.1 (`assets/fonts/WorkSans-OFL.txt`).
