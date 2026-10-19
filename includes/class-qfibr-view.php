<?php
/**
 * Template renderer.
 *
 * The single way admin markup reaches the browser. Template names are matched
 * against a strict pattern and the resolved path is verified with realpath() to
 * stay inside this plugin's templates/admin directory, so a caller can never
 * reach an arbitrary file through this class.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders templates from templates/admin/.
 */
final class Qfibr_View {

	/**
	 * Render a template and return its markup.
	 *
	 * @param string               $name Template name, e.g. "page" or "card-restore".
	 * @param array<string, mixed> $vars Variables extracted into the template scope.
	 * @return string Markup, or an empty string when the template is not valid.
	 */
	public static function render( $name, array $vars = array() ) {
		$path = self::path( $name );
		if ( '' === $path ) {
			return '';
		}

		foreach ( $vars as $qfibr_key => $qfibr_value ) {
			if ( is_string( $qfibr_key ) && preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', $qfibr_key ) ) {
				${$qfibr_key} = $qfibr_value;
			}
		}
		unset( $vars, $qfibr_key, $qfibr_value );

		ob_start();
		require $path;
		return (string) ob_get_clean();
	}

	/**
	 * Echo a template.
	 *
	 * @param string               $name Template name.
	 * @param array<string, mixed> $vars Template variables.
	 * @return void
	 */
	public static function output( $name, array $vars = array() ) {
		$path = self::path( $name );
		if ( '' === $path ) {
			return;
		}

		foreach ( $vars as $qfibr_key => $qfibr_value ) {
			if ( is_string( $qfibr_key ) && preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', $qfibr_key ) ) {
				${$qfibr_key} = $qfibr_value;
			}
		}
		unset( $vars, $qfibr_key, $qfibr_value );

		require $path;
	}

	/**
	 * Resolve a template name to a readable path inside templates/admin/.
	 *
	 * @param string $name Template name.
	 * @return string Absolute path, or an empty string when invalid.
	 */
	private static function path( $name ) {
		if ( ! is_string( $name ) || ! preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $name ) ) {
			return '';
		}

		$base = realpath( QFIBR_PLUGIN_DIR . 'templates/admin' );
		$file = realpath( QFIBR_PLUGIN_DIR . 'templates/admin/' . $name . '.php' );

		if ( false === $base || false === $file ) {
			return '';
		}
		if ( 0 !== strpos( $file, $base . DIRECTORY_SEPARATOR ) ) {
			return '';
		}
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			return '';
		}

		return $file;
	}
}
