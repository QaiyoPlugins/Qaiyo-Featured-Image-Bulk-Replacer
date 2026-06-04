# Qaiyo Featured Image Bulk Replacer

> Bulk‑replace the featured image of published posts with an image that already lives in your Media Library — filtered, previewed, batch‑processed and fully reversible.

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![Version](https://img.shields.io/badge/version-1.2.0-orange.svg)](#changelog)

Qaiyo Featured Image Bulk Replacer is a focused maintenance tool for swapping featured images across many posts at once. It only ever reuses an image that is **already in your Media Library** — nothing is uploaded or downloaded — and every post it changes keeps a record of its original image, so the whole batch can be rolled back with one click.

Built for sites of any size: live runs and restores are processed in small AJAX batches with a progress bar, so large sites don't hit PHP timeouts.

---

## Table of Contents

- [Features](#features)
- [How it works](#how-it-works)
- [Installation](#installation)
- [Usage](#usage)
- [Filtering options](#filtering-options)
- [Restore & safety](#restore--safety)
- [Translations](#translations)
- [Developer hooks & API](#developer-hooks--api)
- [Uninstall behavior](#uninstall-behavior)
- [WordPress.org compliance](#wordpressorg-compliance)
- [Changelog](#changelog)
- [Credits & license](#credits--license)

---

## Features

- **Existing‑image only** — Point it at a Media Library image; the plugin resolves the attachment and reuses it. No uploads, no external downloads.
- **Media Library picker** — Choose the image visually, or paste the URL of one that is already uploaded.
- **Flexible targeting** — Filter by a "before" date, an optional "after" date, post type, category, and a scope toggle for **only posts that have no featured image yet**.
- **Visual preview** — See thumbnails of the affected posts and exactly which would be *replaced* or *skipped* before doing anything.
- **Test run** — Apply the change to just the first few matching posts to verify the result.
- **Batch processing** — Live runs and restores run in small batches with a progress bar — no PHP timeouts on large sites.
- **One‑click restore** — Roll the whole batch back to each post's original featured image.
- **Last‑run summary** — See what the most recent run did, right on the screen.
- **Optional alt / title** — Optionally set the alt and title text of the chosen image once.
- **Translation ready** — Ships with English, Hungarian, German, French and Spanish.
- **Translation plugin compatible** — Works with WPML, Polylang, TranslatePress and others.
- **WordPress standards** — Nonce verification, capability checks, input sanitization, escaped output.

---

## How it works

1. You pick an image **that is already in the Media Library** and set your filters.
2. The plugin queries the matching published posts.
3. For each post, if its featured image already matches the chosen image it is **skipped**; otherwise the current featured image ID is **backed up** to post meta and replaced.
4. Posts are processed in batches (default 25 per request) until done.
5. At any time you can **restore** — every backed‑up post is reverted to its original featured image (or to *no* featured image if it had none).

No images are created, uploaded or deleted. The plugin only changes which existing attachment is set as a post's featured image.

---

## Installation

1. Upload the `qaiyo-featured-image-bulk-replacer` folder to `/wp-content/plugins/`, **or** install the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin from the **Plugins** page.
3. Open **Featured Images** in the admin sidebar.

**Requirements:** WordPress 5.8+ and PHP 7.4+.

---

## Usage

On the **Featured Images** screen:

1. **Choose the new featured image** — via the Media Library picker, or paste an existing image URL.
2. **Set the filters** — before date (required), optional after date, post type, category, scope.
3. Click **Preview** to see which posts would change.
4. Click **Test run** to apply it to the first few matching posts.
5. Click **Live run** to process every matching post in batches.
6. Use **Restore originals** whenever you want to revert.

Capability required: `manage_options` (administrators).

---

## Filtering options

| Option | Description |
|---|---|
| **New featured image** | A Media Library image (picker or URL). Sized URLs (`…-150x150.jpg`) resolve back to the base attachment. |
| **Target scope** | *All matching posts*, or *only posts without a featured image* (great for backfilling a default image). |
| **Post type** | Any public post type that supports featured images. |
| **Category** | Limit to a single category (applies to standard posts). |
| **After date** | Optional lower bound — only posts published on/after this date. |
| **Before date** | Required upper bound — only posts published strictly before this date. |
| **Alt / title text** | Optional. If set, the chosen image's alt and title are updated once. |

---

## Restore & safety

- The first time a post's featured image is changed, its original attachment ID is stored in the `_qfibr_original_thumbnail_id` post meta (a value of `0` means the post had no featured image).
- **Restore originals** reverts every post that still carries this backup: it re‑applies the stored image, or removes the featured image if the post originally had none, or drops it if the original attachment was since deleted.
- Always back up your database before a large live run.

---

## Translations

Ships with `.po`/`.mo` files and a `.pot` template in `languages/`:

| Locale | Language |
|---|---|
| `en_US` | English (source) |
| `hu_HU` | Magyar |
| `de_DE` | Deutsch |
| `fr_FR` | Français |
| `es_ES` | Español |

Variant locales (e.g. `de_AT`, `fr_CA`, `es_MX`) fall back to their base language; unsupported locales fall back to English. Translations load via `load_textdomain()` on `init` (no discouraged `load_plugin_textdomain()`), and are regenerated with `languages/build-mo.py`.

---

## Developer hooks & API

The plugin exposes a small, stable surface so other code (or the optional Pro add‑on) can extend it.

**Filters**

```php
// Whether premium features are unlocked (controls the upgrade teaser).
apply_filters( 'qfibr_pro_active', false );

// Product / upgrade URL used by the teaser.
apply_filters( 'qfibr_upgrade_url', 'https://qaiyo-plugins.com/...' );
```

**Actions**

```php
// Render extra admin cards on the plugin screen (used when premium is active).
do_action( 'qfibr_pro_admin_cards' );

// Fires when a foreground run/restore completes.
do_action( 'qfibr_run_complete', array $job ); // type, mode, ids, counters
```

**Public static API** (`Qfibr_Plugin`)

```php
Qfibr_Plugin::api_resolve_attachment( $attachment_id, $image_url ); // int|WP_Error
Qfibr_Plugin::api_query_ids( array $criteria, $limit = null );      // int[]
Qfibr_Plugin::api_restorable_ids();                                 // int[]
Qfibr_Plugin::replace_one_post( $post_id, $attachment_id );         // 'replaced'|'skipped'|'error'
Qfibr_Plugin::restore_one_post( $post_id );                         // 'restored'|'skipped'|'error'
```

`api_query_ids()` accepts a criteria array with the keys `before_date`, `after_date`, `post_type`, `scope` (`all`|`missing`) and `category`.

---

## Uninstall behavior

On uninstall the plugin removes its per‑post backup meta (`_qfibr_original_thumbnail_id`) and its last‑run option. **Featured images are not changed on uninstall** — use **Restore originals** in the admin first if you want to revert your changes.

---

## WordPress.org compliance

- Folder, main file, text domain and slug all match (`qaiyo-featured-image-bulk-replacer`).
- All output escaped, all input sanitized, nonces and capability checks on every action and AJAX endpoint.
- No echoed `<style>` / `<script>` blocks — assets are enqueued, dynamic CSS uses `wp_add_inline_style()`.
- Uses `load_textdomain()` (not the discouraged `load_plugin_textdomain()`).
- `Plugin URI` and `Author URI` differ; `Tested up to` tracks the current WordPress release.

---

## Changelog

### 1.2.0
- Developer hooks and a public static API for extensions
- Upgrade teaser for premium features

### 1.1.0
- Media Library image picker
- New filters: optional "after" date, category, "only posts without a featured image" scope
- Visual preview with post thumbnails
- Batch processing with a progress bar for live runs and restores
- Last‑run summary on the admin screen

### 1.0.0
- Initial release: bulk replace featured images of posts older than a chosen date with an existing Media Library image
- Preview, test run, live run, one‑click restore
- Optional alt / title text
- Translations: English, Hungarian, German, French, Spanish

---

## Credits & license

Made by **Qaiyo by PixelDesigns** — <https://qaiyo-plugins.com>

Licensed under the **GPL‑2.0‑or‑later**. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
