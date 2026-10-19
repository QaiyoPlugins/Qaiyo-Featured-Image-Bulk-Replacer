# Developer hooks & API

Everything below is public API. The Pro add-on is built on exactly these, so
they are treated as a contract and will not change without a major version.

## Filters

| Filter | Arguments | Purpose |
|---|---|---|
| `qfibr_pro_active` | `bool $active` | Whether Pro features are unlocked. The Pro add-on returns `true` when its licence is active; the free plugin then renders the Pro cards instead of the locked teaser. |
| `qfibr_upgrade_url` | `string $url` | The product URL used by the teaser cards. |
| `qfibr_criteria` | `array $criteria` | The sanitised run criteria, before any query. Add-ons read their own keys from `$criteria['extra']` and may adjust the criteria. Whatever comes back is narrowed again, so a broken callback cannot break a run. *Since 1.3.0.* |
| `qfibr_query_args` | `array $args, array $criteria` | The `WP_Query` arguments of a run (preview, test run, live run, background job). A non-array return value is ignored. *Since 1.3.0.* |

## Actions

| Action | Arguments | Fires |
|---|---|---|
| `qfibr_pro_admin_cards` | — | Inside the admin screen, where the Pro add-on renders its own cards. Only fired when `qfibr_pro_active` is true. |
| `qfibr_criteria_fields` | — | Inside the replacement settings form, after the built-in filters. Print extra fields here and mark each with `data-qfibr-extra="your_key"`: the admin script sends its value as `extra[your_key]`, and it arrives in `$criteria['extra']['your_key']`. *Since 1.3.0.* |
| `qfibr_run_complete` | `array $job` | After a foreground run, restore or cleanup finishes. `$job` carries `type` (`replace`, `restore`, `cleanup`), `mode`, `ids`, `changed` and the counters. Pro uses it to record run history. |
| `qfibr_featured_image_changed` | `int $post_id, int $new_id, int $old_id` | After this plugin changed a post's featured image — replace, remove, restore or cleanup. `0` means "no image". Use it to clear caches. *Since 1.3.0.* |

## Public API

`Qfibr_Plugin` is a stable facade over the internal services.

| Method | Returns | Notes |
|---|---|---|
| `Qfibr_Plugin::pro_active()` | `bool` | Result of the `qfibr_pro_active` filter. |
| `Qfibr_Plugin::upgrade_url()` | `string` | Result of the `qfibr_upgrade_url` filter. |
| `Qfibr_Plugin::api_resolve_attachment( $id, $url )` | `int\|WP_Error` | Resolves an existing Media Library **image**. Never downloads. |
| `Qfibr_Plugin::api_query_ids( array $criteria, $limit = null )` | `int[]` | Post IDs matching the criteria. **Returns an empty array** when the criteria have no valid `before_date`, or when a category / tag / taxonomy filter does not apply to the post type — a run is refused rather than widened. |
| `Qfibr_Plugin::api_restorable_ids()` | `int[]` | Posts that still carry a stored original. |
| `Qfibr_Plugin::replace_one_post( $post_id, $attachment_id )` | `'replaced'\|'skipped'\|'error'` | Backs the original up once, then writes. |
| `Qfibr_Plugin::restore_one_post( $post_id )` | `'restored'\|'skipped'\|'error'` | Puts the stored original back and clears the backup. |

### Criteria array

```php
array(
    'before_date'    => '2024-01-01', // required; without it nothing matches
    'after_date'     => '2023-01-01', // optional lower bound (inclusive)
    'post_type'      => 'post',       // must support thumbnails
    'scope'          => 'all',        // or 'missing' (only posts with no image)
    'category'       => 0,            // term ID
    'tag'            => 0,            // term ID
    'author'         => 0,            // user ID
    'taxonomy'       => '',           // any public taxonomy of the post type…
    'term'           => 0,            // …and one of its term IDs
    'assign_mode'    => 'single',     // or 'random'
    'attachment_id'  => 0,            // single mode
    'attachment_ids' => array(),      // random mode: 2–50 image IDs
    'extra'          => array(),      // add-on fields, short sanitised strings
)
```

In random mode each post gets `attachment_ids[ crc32( seed . ':' . post_id ) % n ]`,
so a retried batch gives a post the same image again.

## Stored data

| Key | Type | Meaning |
|---|---|---|
| `_qfibr_original_thumbnail_id` | post meta | The post's featured image before this plugin first changed it. `0` means "there was none". Written by bulk runs, the list column and the broken-image cleanup alike. |
| `qfibr_last_run` | option | Summary of the last completed run. |
| `qfibr_job_*` | transient | Queue and counters of an in-progress foreground run (1 hour TTL). |

## AJAX endpoints

All require a nonce. The settings screen endpoints require `manage_options`;
the list-column endpoints require `edit_post` on the post in question.

| Action | Capability | Purpose |
|---|---|---|
| `qfibr_preview`, `qfibr_prepare`, `qfibr_batch`, `qfibr_orphan_scan` | `manage_options` | Settings screen: preview, start a run, process a batch, scan for broken images. |
| `qfibr_list_set`, `qfibr_list_remove` | `edit_post` | Featured image column in post lists. |

## Example

```php
// Add a "Only sticky posts" option to the replacement form.
add_action( 'qfibr_criteria_fields', function () {
    echo '<p><label><input type="checkbox" value="1" data-qfibr-extra="sticky_only"> Only sticky posts</label></p>';
} );

add_filter( 'qfibr_query_args', function ( $args, $criteria ) {
    if ( ! is_array( $args ) || ! is_array( $criteria ) ) {
        return $args; // Filter callbacks may receive anything: pass it through.
    }
    if ( '1' === ( $criteria['extra']['sticky_only'] ?? '' ) ) {
        $args['post__in'] = get_option( 'sticky_posts' ) ?: array( 0 );
    }
    return $args;
}, 10, 2 );

// Log every completed run.
add_action( 'qfibr_run_complete', function ( array $job ) {
    error_log( sprintf(
        '[FIBR] %s finished: %d processed, %d skipped, %d errors',
        $job['type'],
        $job['processed'],
        $job['skipped'],
        $job['errors']
    ) );
} );
```

## Pro add-on

The Pro add-on (1.1.0+, requires this plugin 1.3.0+) adds:

| Hook | Arguments | Purpose |
|---|---|---|
| `qfibrp_title_image_font` (filter) | `string $path, string $weight` | Absolute path of the TTF / OTF font used by the title-image generator (e.g. one with CJK glyphs). An unreadable path disables rendering. |

WP-CLI (`wp qfibr`):

| Command | What it does |
|---|---|
| `wp qfibr replace --before=<date> [--image=<id-or-url>] [--images=<ids>] [--after=] [--post-type=] [--scope=all\|missing] [--category=] [--tag=] [--author=] [--taxonomy=<tax:term-id>] [--dry-run] [--yes]` | Bulk replacement, same rules as the screen. |
| `wp qfibr preview …` | Same options as `replace`; lists the matching posts. |
| `wp qfibr restore [--run=<id>] [--yes]` | Restore every original, or only the posts one run changed. |
| `wp qfibr cleanup [--dry-run] [--yes]` | Remove broken featured image references. |
| `wp qfibr rules --before=<date> [--post-type=] [--dry-run] [--yes]` | Apply the automatic rules to existing posts without an image. |
| `wp qfibr title-image <post-id>... [--set]` | Generate title images; `--set` makes them the featured image. |
| `wp qfibr history [--format=table\|csv\|json\|ids]` | Run history. |
| `wp qfibr presets export \| import <file>` | Move presets between sites. |
