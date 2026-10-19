<?php
/**
 * Safe narrowing of WordPress' untyped values.
 *
 * get_option(), transients, filter results and decoded API responses are all
 * `mixed`: their real type depends on data nobody controls ahead of time. A raw
 * `(string) $value` is a runtime hazard as well as a static-analysis complaint —
 * casting an array emits "Array to string conversion", casting most objects
 * throws. These helpers make the narrowing explicit and safe in one place.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Qfibr_Cast {

    /**
     * @param mixed $value Any value.
     */
    public static function string( $value ): string {
        if ( is_string( $value ) ) {
            return $value;
        }
        if ( is_scalar( $value ) ) {
            return (string) $value;
        }
        return '';
    }

    /**
     * @param mixed $value Any value.
     */
    public static function int( $value ): int {
        return is_scalar( $value ) ? (int) $value : 0;
    }

    /**
     * @param mixed $value Any value.
     */
    public static function bool( $value ): bool {
        return is_scalar( $value ) ? (bool) $value : false;
    }

    /**
     * @param mixed $value Any value.
     * @return array<mixed,mixed>
     */
    public static function arr( $value ): array {
        return is_array( $value ) ? $value : array();
    }

    /**
     * An integer clamped to [ $min, $max ]; non-scalars become $min.
     *
     * @param mixed $value Any value.
     * @param int   $min   Lower bound.
     * @param int   $max   Upper bound.
     */
    public static function clamp( $value, int $min, int $max ): int {
        return max( $min, min( $max, self::int( $value ) ) );
    }
}
