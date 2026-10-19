=== Qaiyo Featured Image Bulk Replacer ===
Contributors: qaiyo
Tags: featured image, bulk edit, thumbnail, media, posts
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.3.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk-replace the featured image of published posts with an existing Media Library image. Filter, preview, batch-process and one-click restore.

== Description ==

Qaiyo Featured Image Bulk Replacer is a focused maintenance tool: it swaps the featured image of published posts for an image that **already exists in your Media Library**. Nothing is downloaded from external sources.

It is built for safe, repeatable use on sites of any size — you filter the target posts, preview them, optionally run a small test on the first few, then run it for real in small batches that won't time out. Every post the plugin changes keeps a record of its original featured image, so you can restore the whole batch with one click.

**Key features:**

* **Existing-image only** — Pick an image from the Media Library (or paste a URL of one that is already uploaded); nothing is downloaded.
* **Random assignment** — Choose several images and give each post one of them. The choice is stable per post, so a retried batch gives the same result.
* **Flexible filtering** — By "before" and "after" date, post type, category, tag, author or any other public taxonomy, plus a scope toggle for **only posts that have no featured image yet**. A filter that does not apply to the chosen post type is refused instead of silently widening the run.
* **Featured image column** — See every post's featured image in the Posts, Pages and custom post type lists, and change or remove it right there without opening the editor. The column stays compact (44 px), so the Title column keeps its room.
* **Media Library "used by"** — A column in the Media Library and a field in the attachment details show which posts use an image as their featured image.
* **Broken featured image cleanup** — Find posts whose featured image points at an attachment that no longer exists and remove the dangling reference. Offloaded media is never touched.
* **Visual preview** — See thumbnails of the affected posts and exactly which would be replaced or skipped, before doing anything.
* **Test run** — Apply the change to just the first few matching posts to verify the result.
* **Batch processing** — Live runs, cleanups and restores are processed in small AJAX batches with a progress bar, so large sites don't hit PHP timeouts.
* **One-click restore** — Roll back every changed post to its original featured image — bulk runs, list-column changes and cleanups alike.
* **Accessible** — The admin screens follow WCAG 2.2 AA: labelled fields, grouped options, keyboard-friendly controls, screen-reader announcements for progress and results, sufficient contrast, and support for forced colours and reduced motion.
* **Translation ready** — Ships with English, Hungarian, German, French, Spanish, Japanese, Portuguese, Italian, Russian, Turkish and Polish.
* **Translation plugin compatible** — Works with WPML, Polylang, TranslatePress and others.
* **WordPress standards** — Nonce verification, capability checks, input sanitization, escaped output.

**Pro add-on (separate plugin):** background processing and scheduling, rule-based automatic featured images, title-image generator, Unsplash / Pixabay search, presets, WooCommerce product filters, WP-CLI and run history. The free plugin is fully functional without it.

== Installation ==

1. Upload the `qaiyo-featured-image-bulk-replacer` folder to `/wp-content/plugins/`.
2. Activate the plugin from the WordPress admin Plugins page.
3. Open **Featured Images** in the admin sidebar.
4. Choose one image (or several for random assignment), set your filters, then Preview, Test run, or Live run.
5. To change a single post's image, use the Featured image column in the Posts list.

== Frequently Asked Questions ==

= Does it download or upload images? =

No. It only reuses an image that is already in your Media Library. You provide the URL of an existing attachment.

= Which posts are affected? =

Published posts of the selected post type whose publish date is strictly before the date you choose (and on or after the optional "after" date), narrowed by the category, tag, author and taxonomy filters you set.

= Can I undo a replacement? =

Yes. The original featured image ID of every changed post is stored. Use the "Restore originals" section to roll back the whole batch.

= What does the broken featured image cleanup remove? =

Only the reference to an attachment that no longer exists. Each post is re-checked right before it is changed, and the old value is stored, so "Restore originals" can put it back.

= Can I change one post's image from the Posts list? =

Yes. Click the thumbnail in the Featured image column to choose an image, or the × button to remove it. Only users who may edit that post see the controls.

= What happens if a post had no featured image? =

That is recorded too. On restore, such posts simply have their featured image removed again.

= Is it compatible with WPML / Polylang / TranslatePress? =

Yes. It uses the standard WordPress translation loading and does not interfere with language-based content filtering.

= What happens when the plugin is uninstalled? =

The per-post backup records are removed. Featured images are not changed on uninstall — restore them in-app first if you need to revert.

== Changelog ==

= 1.3.0 =
* New: random assignment from several images (stable per post)
* New: tag, author and custom taxonomy filters; filters that do not apply to the chosen post type are refused instead of widening the run
* New: Featured image column in post lists with in-place change and remove
* New: Media Library "Featured image of" column and attachment field
* New: broken featured image scan and cleanup (restorable)
* New: `qfibr_criteria`, `qfibr_query_args`, `qfibr_criteria_fields` and `qfibr_featured_image_changed` hooks for add-ons
* Accessibility: WCAG 2.2 AA pass — labels, fieldsets, live announcements, progress bar semantics, contrast, focus styles, 24 px targets, forced colours and reduced motion
* Only image attachments can be assigned
* Requires WordPress 6.2

= 1.2.0 =
* Security and quality pass: bounded runs only, lock ownership, re-check before every write, multisite uninstall
* Code split into small classes and templates; unit tests and static analysis
* Personal greeting on the admin screen
* Japanese, Portuguese, Italian, Russian, Turkish and Polish translations

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
