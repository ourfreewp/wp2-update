<?php
namespace WP2\Update\Utils\Console;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Console color sanitizer for Termwind.
 *
 * Maps Tailwind-like tokens (e.g. red-500, slate-700) to Termwind-supported names.
 * Fallback strips shade suffix (e.g. red-500 -> red).
 */
final class Palette {
	/**
	 * Map a single color token to a Termwind-supported color.
	 *
	 * @param string $token Tailwind-like token or plain color.
	 * @return string Supported color token for Termwind.
	 */
	public static function map_color( string $token ): string {
		$token = strtolower( trim( $token ) );

		$map = [
			// reds
			'red-50' => 'light-red', 'red-100' => 'light-red', 'red-200' => 'light-red',
			'red-300' => 'light-red', 'red-400' => 'light-red', 'red-500' => 'red',
			'red-600' => 'red', 'red-700' => 'red', 'red-800' => 'red', 'red-900' => 'red',

			// greens / emeralds
			'green-500' => 'green', 'green-600' => 'green', 'emerald-500' => 'green', 'emerald-600' => 'green',

			// blues / sky
			'blue-500' => 'blue', 'blue-600' => 'blue', 'sky-500' => 'blue', 'sky-600' => 'blue',

			// yellow
			'yellow-500' => 'yellow', 'amber-500' => 'yellow',

			// neutrals
			'gray-500' => 'gray', 'zinc-500' => 'gray', 'neutral-500' => 'gray',
			'slate-700' => 'black', 'slate-800' => 'black', 'slate-900' => 'black',
		];

		if ( isset( $map[ $token ] ) ) {
			return $map[ $token ];
		}

		// hex -> nearest basic color (very coarse)
		if ( preg_match( '/^#?[0-9a-f]{6}$/i', $token ) ) {
			return 'white'; // keep simple; expand if you need smarter mapping
		}

		// fallback: strip shade suffix (red-500 -> red), or return as-is
		return preg_replace( '/-\d{2,3}$/', '', $token );
	}

	/**
	 * Map all color classes in a Termwind class string.
	 *
	 * Supports patterns like "text-red-500", "bg-emerald-600".
	 *
	 * @param string $class_list Space-separated class list.
	 * @return string Sanitized class list.
	 */
	public static function sanitize_classes( string $class_list ): string {
		$tokens = preg_split( '/\s+/', trim( $class_list ) ) ?: [];

		foreach ( $tokens as &$t ) {
			if ( preg_match( '/^(text|bg)-([a-z]+(?:-\d{2,3})?)$/', $t, $m ) ) {
				$t = $m[1] . '-' . self::map_color( $m[2] );
			}
		}

		return implode( ' ', array_filter( $tokens ) );
	}
}