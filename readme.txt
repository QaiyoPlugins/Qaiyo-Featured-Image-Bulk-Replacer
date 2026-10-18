=== Qaiyo Featured Image Bulk Replacer ===
Contributors: qaiyo
Tags: featured image, bulk edit, thumbnail, media, posts
Requires at least: 5.8
Tested up to: 7.1
Stable tag: 1.2.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk-replace the featured image of published posts with an existing Media Library image. Filter, preview, batch-process and one-click restore.

== Description ==

Qaiyo Featured Image Bulk Replacer is a focused maintenance tool: it swaps the featured image of published posts for an image that **already exists in your Media Library**. Nothing is downloaded from external sources.

It is built for safe, repeatable use on sites of any size — you filter the target posts, preview them, optionally run a small test on the first few, then run it for real in small batches that won't time out. Every post the plugin changes keeps a record of its original featured image, so you can restore the whole batch with one click.

**Key features:**

* **Existing-image only** — Pick an image from the Media Library (or paste a URL of one that is already uploaded); nothing is downloaded.
* **Media Library picker** — Choose the image visually instead of hunting for its URL.
* **Flexible filtering** — By "before" date, an optional "after" date, post type, category, and a scope toggle for **only posts that have no featured image yet**.
* **Visual preview** — See thumbnails of the affected posts and exactly which would be replaced or skipped, before doing anything.
* **Test run** — Apply the change to just the first few matching posts to verify the result.
* **Batch processing** — Live runs and restores are processed in small AJAX batches with a progress bar, so large sites don't hit PHP timeouts.
* **One-click restore** — Roll back every changed post to its original featured image.
* **Last-run summary** — See what the most recent run did, right on the screen.
* **Optional alt / title** — Optionally set the alt and title text of the chosen image once.
* **Translation ready** — Ships with English, Hungarian, German, French and Spanish translations.
* **Translation plugin compatible** — Works with WPML, Polylang, TranslatePress and others.
* **WordPress standards** — Nonce verification, capability checks, input sanitization, escaped output.

== Installation ==

1. Upload the `qaiyo-featured-image-bulk-replacer` folder to `/wp-content/plugins/`.
2. Activate the plugin from the WordPress admin Plugins page.
3. Open **Featured Images** in the admin sidebar.
4. Choose the Media Library image, set your filters (date, scope, post type, category), then Preview, Test run, or Live run.

== Frequently Asked Questions ==

= Does it download or upload images? =

No. It only reuses an image that is already in your Media Library. You provide the URL of an existing attachment.

= Which posts are affected? =

Published posts of the selected post type whose publish date is strictly before the date you choose.

= Can I undo a replacement? =

Yes. The original featured image ID of every changed post is stored. Use the "Restore originals" section to roll back the whole batch.

= What happens if a post had no featured image? =

That is recorded too. On restore, such posts simply have their featured image removed again.

= Is it compatible with WPML / Polylang / TranslatePress? =

Yes. It uses the standard WordPress translation loading and does not interfere with language-based content filtering.

= What happens when the plugin is uninstalled? =

The per-post backup records are removed. Featured images are not changed on uninstall — restore them in-app first if you need to revert.

== Changelog ==

= 1.1.0 =
* Media Library image picker (no more hunting for the URL)
* New filters: optional "after" date, category, and a "only posts without a featured image" scope
* Visual preview with post thumbnails
* Batch processing with a progress bar for live runs and restores — no PHP timeouts on large sites
* Last-run summary shown on the admin screen
* Updated brand product URL

= 1.0.0 =
* Initial Qaiyo release
* Bulk replace featured images of posts older than a chosen date with an existing Media Library image
* Preview, test run and live run
* One-click restore of original featured images
* Optional alt / title text for the chosen image
* Translations: English, Hungarian, German, French, Spanish
* Translation plugin compatible (WPML, Polylang, TranslatePress)
