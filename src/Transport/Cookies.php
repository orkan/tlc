<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */
namespace Orkan\TLC\Transport;

use Orkan\TLC\Factory;

/**
 * Cookies helper.
 *
 * @author Orkan <orkans+tlc@gmail.com>
 */
class Cookies
{
	/* @formatter:off */

	/**
	 * Default attributes.
	 */
	const DEFAULTS = [
		'name'     => '',
		'value'    => '',
		'expires'  => 0,
		'max-age'  => 0,
		'domain'   => '',
		'path'     => '/',
		'secure'   => false,
		'httponly' => false,
		'samesite' => 'Lax',
	];

	/**
	 * Map moz_cookies DB field to PHP setcookie().
	 */
	const MAP_MOZ_FIELD = [
	//   DB column   =>  Cookie attribute
		'name'       => 'name',
		'value'      => 'value',
		'path'       => 'path',
		'host'       => 'domain',
		'expiry'     => 'expires',
		'isSecure'   => 'secure',
		'isHttpOnly' => 'httponly',
		'sameSite'   => 'samesite',
	];

	/**
	 * Map moz_cookies DB sameSite field to cookie attr.
	 */
	const MAP_MOZ_SAMESITE = [
		'0' => 'None',
		'1' => 'Lax',
		'2' => 'Strict',
	];

	/* @formatter:on */

	/**
	 * Logged cookie files.
	 *
	 * This array help detects changes in cookie JAR file(s)
	 */
	protected $logCookieFiles = [];

	/*
	 * Services:
	 */
	protected $Factory;
	protected $Utils;
	protected $Logger;
	protected $Transport;

	/**
	 * Setup.
	 */
	public function __construct( Factory $Factory )
	{
		$this->Factory = $Factory;
		$this->Utils = $Factory->Utils();
		$this->Logger = $Factory->Logger();
		$this->Transport = $Factory->Transport();
	}

	/**
	 * Format the contents of the "Cookie: " header to be used in the HTTP request (not encoded!).
	 *
	 * NOTE:
	 * Multiple cookies are separated with a semicolon followed by a space (e.g., "fruit=apple; colour=red")
	 *
	 * @param  array  $cookies Array( Array( [name] => name, [value] => value, [Expires] => ... ), Array( ... ) )
	 * @return string          Contents of the "Cookie: " header
	 */
	public function buildCookieHeader( array $cookies = [] ): string
	{
		$pairs = [];
		foreach ( $cookies as $cookie ) {
			$pairs[] = $cookie['name'] . '=' . urlencode( $cookie['value'] );
		}

		return implode( '; ', $pairs );
	}

	/**
	 * Parse a cookie string as set in a Set-Cookie HTTP header and return an associative array of data.
	 *
	 * Change all keys to lowercase.
	 * Change Expires: to timestamp.
	 *
	 * @link https://docs.aws.amazon.com/aws-sdk-php/v2/api/class-Guzzle.Parser.Cookie.CookieParser.html
	 *
	 * @param  string $cookie Set-Cookie HTTP header
	 * @return array          Array( [name] => name, [value] => value, [expires] => ... ) )
	 */
	public function buildCookie( string $cookie ): array
	{
		$results = [];

		foreach ( explode( '; ', $cookie ) as $k => $v ) {
			$v = explode( '=', $v, 2 ); // XSRF-TOKEN=Zu4A6nHwjjFg/kG2oJbY5w==; <- 4 elements returned between '='!
			$name = $v[0];
			$value = $v[1] ?? ''; // Name=Value; HttpOnly(=no value);

			// First element: name=value
			if ( !$k ) {
				$results['name'] = $name;
				$results['value'] = urldecode( $value );
			}
			else {

				if ( '' === $value ) {
					$value = true; // HttpOnly; Secure;
				}
				elseif ( is_numeric( $value ) ) {
					$value = (int) $value;
				}
				elseif ( 'expires' === $name ) {
					$value = strtotime( $value );
				}
				else {
					// Normalize to lowercase string values: Path=/abc/def; SameSite=lax;
					$value = strtolower( $value );
				}

				// Normalize to lowercase attribute names: expires=...; path=AbC/dEf; samesite=Lax; httponly;
				$results[strtolower( $name )] = $value;
			}
		}

		return $this->prepare( $results );
	}

	/**
	 * Sort cookie attributes.
	 *
	 * NOTE:
	 * This approach allows the use of assertSame() instead of assertEquals().
	 * The latter doesn't check key order and types! :(
	 */
	public function prepare( array $cookie ): array
	{
		$order = array_intersect_key( self::DEFAULTS, $cookie );
		$cookie = array_merge( $order, $cookie );

		return $cookie;
	}

	/**
	 * Build a cookie in Netscape format.
	 *
	 * @link https://curl.se/libcurl/c/CURLOPT_COOKIELIST.html
	 * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#attributes
	 *
	 * @param  array  $options Same as setcookie() options: path, domain, expires, secure, httponly, samesite
	 * @param  bool   $eol     Append PHP_EOL
	 * @return string          Cookie string in a single line in Netscape / Mozilla format
	 */
	public function buildNetscapeCookie( string $name, string $value, array $options = [], bool $eol = true ): string
	{
		/*
		 * TIPS:
		 * Exercise caution if you are using this option and multiple transfers may occur.
		 * If you use the Set-Cookie format and do not specify a domain then the cookie is sent for any domain (even after
		 * redirects are followed) and cannot be modified by a server-set cookie.
		 * If a server sets a cookie of the same name (or maybe you have imported one) then both will be sent on a future
		 * transfer to that server, likely not what you intended. To address these issues set a domain in Set-Cookie
		 * (doing that will include sub-domains) or use the Netscape format.
		 *
		 * You can set the cookie as HttpOnly to prevent XSS attacks by prepending #HttpOnly_ to the hostname.
		 *
		 * Cookies that have the same hostname, path and name as in CURLOPT_COOKIELIST are skipped.
		 *
		 * Fields:
		 * domain name {TAB} include subdomains? {TAB} path {TAB} HTTPS only? {TAB} expires {TAB} name {TAB} value
		 *
		 * Examples:
		 * Set-Cookie: redirect=1; path=/; domain=htdocs.wamp
		 * .htdocs.wamp	TRUE	/	FALSE	0	cookie3	session
		 *
		 * Set-Cookie: cookie2=24h_httponly_lax; expires=Sat, 27-Aug-2022 00:00:00 GMT; Max-Age=51380; path=/; domain=htdocs.wamp; HttpOnly; SameSite=Lax
		 * #HttpOnly_.htdocs.wamp	TRUE	/	FALSE	1661558400	cookie2	24h_httponly_lax
		 *
		 * @formatter:off */
		$options = array_merge([
			'domain'   => '',
			'path'     => '/',
			'secure'   => false,
			'expires'  => 0,
			'httponly' => false,
		], $options);

		$cookie = [
			$options['domain'] ? '.' . $options['domain'] : '', // Hostname
			$options['domain'] ? 'TRUE' : 'FALSE', // Include subdomains?
			$options['path'],
			$options['secure'] ? 'TRUE' : 'FALSE',
			$options['expires'],
			$name,
			urlencode( $value ),
		];
		/* @formatter:on */

		$httponly = $options['httponly'] ? '#HttpOnly_' : '';
		$line = $httponly . implode( "\t", $cookie ) . ( $eol ? PHP_EOL : '' );

		return $line;
	}

	/**
	 * Parse all cookies from JAR file.
	 *
	 * @link https://www.hashbangcode.com/article/netscape-http-cooke-file-parser-php
	 */
	public function getCookiesFromFile( string $file ): array
	{
		$cookies = [];

		foreach ( file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {

			if ( 7 != count( $line = explode( "\t", $line ) ) ) {
				continue;
			}

			$cookie = [];
			$tokens = array_combine( [ 'domain', 'subdomains', 'path', 'secure', 'expires', 'name', 'value' ], $line );

			if ( $cookie['httponly'] = 0 === strncmp( '#HttpOnly_', $tokens['domain'], 10 ) ) {
				$tokens['domain'] = substr( $tokens['domain'], 10 );
			}

			$cookie['name'] = $tokens['name'];
			$cookie['value'] = urldecode( $tokens['value'] );
			$cookie['path'] = $tokens['path'];
			$cookie['domain'] = ltrim( $tokens['domain'], '.' );
			$cookie['expires'] = (int) $tokens['expires'];
			$cookie['secure'] = 'TRUE' === $tokens['secure'];

			$cookies[$cookie['name']] = $this->prepare( $cookie );
		}

		return $cookies;
	}

	/**
	 * Parse all cookies from given url.
	 */
	public function getCookiesFromUrl( string $url, string $method = 'get', array $options = [] ): array
	{
		$options['curl'] = $options['curl'] ?? [];
		$options['curl'][CURLOPT_HEADER] = true;

		$response = $this->Transport->with( $method, $url, $options );
		DEBUG && $this->Logger->debug( 'Response ' . $response );

		$matches = $cookies = [];
		$info = $this->Factory->TransportStats()->lastInfo;

		$response = substr( $response, 0, $info['header_size'] ?? 0);
		preg_match_all( '/^Set-Cookie: (.*)$/mi', $response, $matches );
		$matches = $matches[1] ?? [];

		foreach ( $matches as $v ) {
			$cookie = $this->buildCookie( $v );
			$cookies[$cookie['name']] = $cookie; // keyed by [name] to remove doubles
		}

		return $cookies;
	}

	/**
	 * Translate Firefox moz_cookies DB row to PHP format.
	 */
	public function translateMozillaCookie( array $row ): array
	{
		$cookie = [];

		foreach ( $row as $k => $v ) {
			if ( !isset( self::MAP_MOZ_FIELD[$k] ) ) {
				continue;
			}
			else if ( 'host' === $k ) {
				$v = ltrim( $v, '.' );
			}
			else if ( 'value' === $k ) {
				$v = urldecode( $v );
			}
			else if ( 'expiry' === $k ) {
				$v = (int) $v;
			}
			else if ( in_array( $k, [ 'isSecure', 'isHttpOnly' ] ) ) {
				$v = (bool) $v;
			}
			else if ( 'sameSite' === $k ) {
				$v = self::MAP_MOZ_SAMESITE[$v];
			}

			$cookie[self::MAP_MOZ_FIELD[$k]] = $v;
		}

		return $this->prepare( $cookie );
	}

	/**
	 * Log changes in cookie JAR files. Tracks multiple files separately!
	 *
	 * @param string $file  Cookies file in Netscape format
	 * @param string $level Logger level/method name
	 */
	public function logCookiesFromFile( string $file, string $level = 'debug' ): void
	{
		if ( !is_file( $file ) || !$this->Logger->is( $level ) ) {
			return;
		}

		$old = $this->logCookieFiles[$file] ?? [];
		$this->logCookieFiles[$file] = $this->getCookiesFromFile( $file );

		$msg = [];
		$new = &$this->logCookieFiles[$file];

		foreach ( $new as $key => $cookie ) {
			if ( !isset( $old[$key] ) ) {
				$msg[] = 'Add: ' . $this->Utils->print_r( $cookie );
			}
		}

		foreach ( $old as $key => $cookie ) {
			if ( isset( $new[$key] ) ) {
				if ( $diff = array_diff_assoc( $new[$key], $cookie ) ) {
					$msg[] = 'Mod: ' . $this->Utils->print_r( $cookie );
					$msg[] = ' >> ' . $this->Utils->print_r( $diff );
				}
			}
			else {
				$msg[] = 'Del: ' . $this->Utils->print_r( $cookie );
			}
		}

		foreach ( $msg as $str ) {
			$str = str_replace( ': Array', ': Cookie', $str );
			$str = str_replace( '>> Array', '>> Attributes', $str );
			$this->Logger->$level( $str, 1 );
		}
	}
}
