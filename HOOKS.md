# Developer hooks & API

Everything below is public API. The Pro add-on is built on exactly these, so
they are treated as a contract and will not change without a major version.

## Filters

| Filter | Arguments | Purpose |
|---|---|---|
| `qfibr_pro_active` | `bool $active` | Whether Pro features are unlocked. The Pro add-on returns `true` when its licence is active; the free plugin then renders the Pro cards instead of the locked teaser. |
| `qfibr_upgrade_url` | `string $url` | The product URL used by the teaser cards. |

## Actions

| Action | Arguments | Fires |
|---|---|---|
| `qfibr_pro_admin_cards` | — | Inside the admin screen, where the Pro add-on renders its own cards. Only fired when `qfibr_pro_active` is true. |
| `qfibr_run_complete` | `array $job` | After a foreground run or restore finishes. `$job` carries `type`, `mode`, `ids` and the counters. Pro uses it to record run history. |

## Public API

`Qfibr_Plugin` is a stable facade over the internal services.

| Method | Returns | Notes |
|---|---|---|
| `Qfibr_Plugin::pro_active()` | `bool` | Result of the `qfibr_pro_active` filter. |
| `Qfibr_Plugin::upgrade_url()` | `string` | Result of the `qfibr_upgrade_url` filter. |
| `Qfibr_Plugin::api_resolve_attachment( $id, $url )` | `int\|WP_Error` | Resolves an existing Media Library item. Never downloads. |
| `Qfibr_Plugin::api_query_ids( array $criteria, $limit = null )` | `int[]` | Post IDs matching the criteria. **Returns an empty array when the criteria have no valid `before_date`** — an unbounded run is refused by design. |
| `Qfibr_Plugin::api_restorable_ids()` | `int[]` | Posts that still carry a stored original. |
| `Qfibr_Plugin::replace_one_post( $post_id, $attachment_id )` | `'replaced'\|'skipped'\|'error'` | Backs the original up once, then writes. |
| `Qfibr_Plugin::restore_one_post( $post_id )` | `'restored'\|'skipped'\|'error'` | Puts the stored original back and clears the backup. |

### Criteria array

```php
array(
    'before_date' => '2024-01-01', // required; without it nothing matches
    'after_date'  => '2023-01-01', // optional lower bound
    'post_type'   => 'post',
    'scope'       => 'all',        // or 'missing' (only posts with no image)
    'category'    => 0,            // term ID, posts only
)
```

## Stored data

| Key | Type | Meaning |
|---|---|---|
| `_qfibr_original_thumbnail_id` | post meta | The post's featured image before this plugin changed it. `0` means "there was none". |
| `qfibr_last_run` | option | Summary of the last completed run. |
| `qfibr_job_*` | transient | Queue and counters of an in-progress foreground run (1 hour TTL). |

## Example

```php
// Treat Pro as active in a staging environment.
add_filter( 'qfibr_pro_active', '__return_true' );

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
