<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */
namespace Orkan\TLC\Transport;

use Orkan\TLC\Factory;

/**
 * Curl http transport implementation.
 *
 * @link https://www.php.net/manual/en/function.curl-setopt.php
 * @link https://github.com/andriichuk/php-curl-cookbook#http-request-methods
 *
 * @author Orkan <orkans+tlc@gmail.com>
 */
class Curl extends TransportAbstract
{

	/**
	 * Default cURL options.
	 */
	protected $options = [];

	/* @formatter:off */
	const RETRY_ON = [
		CURLE_COULDNT_RESOLVE_HOST,
		CURLE_COULDNT_CONNECT,
		CURLE_HTTP_NOT_FOUND,
		CURLE_READ_ERROR,
		CURLE_OPERATION_TIMEOUTED,
		CURLE_OPERATION_TIMEDOUT,
		CURLE_HTTP_POST_ERROR,
		CURLE_SSL_CONNECT_ERROR,
	];
	/* @formatter:on */

	/**
	 * Build cURL.
	 */
	public function __construct( Factory $Factory )
	{
		$Factory->merge( self::defaults() );
		parent::__construct( $Factory );

		/**
		 * Default cURL options.
		 * @link https://www.php.net/manual/en/function.curl-setopt.php
		 *
		 * Can be replaced by cfg['net_curl']
		 * @see \Orkan\TLC\Transport\TransportAbstract::defaults()
		 *
		 * [CURLOPT_COOKIE]
		 * Cookie: http header. The resulting header line is:
		 * Cookie: CURLOPT_COOKIEFILE; CURLOPT_COOKIE;
		 * These cookies are appended after JAR file cookies thus overwriting previous with same name!
		 *
		 * [CURLOPT_HTTPHEADER]
		 * Send additional headers
		 *
		 * [CURLOPT_ENCODING]
		 * The contents of the "Accept-Encoding: " header. This enables decoding of the response. Supported encodings
		 * are "identity", "deflate", and "gzip". If an empty string, "", is set, a header containing all supported
		 * encoding types is sent.
		 * NOTE:
		 * This won't change the CURLOPT_HTTPHEADER => "Accept-Encoding: ..." string sent to the server, however
		 * an empty string will force cURL to manage the response encoding!
		 *
		 * @formatter:off */
		$this->options = [
			CURLOPT_USERAGENT      => $Factory->get( 'net_useragent' ),
			CURLOPT_HTTPHEADER     => $Factory->get( 'net_httpheader' ),
			CURLOPT_ENCODING       => '',
			CURLOPT_CONNECTTIMEOUT => $Factory->get( 'net_timeout' ),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true, // Follow 3xx redirects
			CURLOPT_CAINFO         => realpath( __DIR__ . '/../../ssl/cacert.pem' ), // CA certificates extracted from Mozilla
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 0,
		];
		/* @formatter:on */

		// Set one cookie for reading / writing
		if ( $cookieFile = $Factory->get( 'net_cookiefile' ) ) {
			$this->options[CURLOPT_COOKIEFILE] = $cookieFile; // read
			$this->options[CURLOPT_COOKIEJAR] = $cookieFile; // write
		}

		// Log errors?
		if ( DEBUG && $logFile = $Factory->get( 'net_logfile' ) ) {
			$this->options[CURLOPT_VERBOSE] = true;
			$this->options[CURLOPT_STDERR] = fopen( $logFile, 'w' );
		}

		// User cURL options to replace defaults
		if ( $options = $Factory->get( 'net_curl' ) ) {
			$this->options = $options + $this->options;
		}
	}

	/**
	 * {@inheritDoc}
	 * @see \Orkan\TLC\Transport\TransportAbstract::defaults()
	 */
	protected function defaults(): array
	{
		/**
		 * [net_retry]
		 * No. of request tries if connection failed
		 *
		 * [net_timeout]
		 * Max time to wait while trying to connect. O == infinite
		 *
		 *
		 * @formatter:off */
		return [
			'net_retry'   => 5,
			'net_timeout' => 5,
		];
		/* @formatter:on */
	}

	/**
	 * Do [get] http request.
	 *
	 * @see \Orkan\TLC\Transport\TransportAbstract::throttle()
	 *
	 * @param string $url     Full target url
	 * @param array  $options Extra options: Array (
	 *   [query]    => urlencoded string (if not in url)
	 *   [curl]     => per query CURLOPTS_
	 *   [throttle] => throttle opts
	 * )
	 * @return string Response from the server
	 */
	public function get( string $url, array $options = [] ): string
	{
		$options['curl'] = $options['curl'] ?? [];

		/* @formatter:off */
		$options['curl'] += [
			CURLOPT_URL => $url,
		];
		/* @formatter:on */

		return $this->exec( $options );
	}

	/**
	 * Do [post] http request.
	 *
	 * {@inheritdoc}
	 * @see \Orkan\TLC\Transport\TransportAbstract::post()
	 *
	 * [$options]
	 * @see \Orkan\TLC\Transport\Curl::get()
	 */
	public function post( string $url, array $options = [] ): string
	{
		$options['curl'] = $options['curl'] ?? [];

		/* @formatter:off */
		$options['curl'] += [
				CURLOPT_URL        => $url,
				CURLOPT_POST       => true,
				CURLOPT_POSTFIELDS => $options['fields'] ?? [],
		];
		/* @formatter:on */

		return $this->exec( $options );
	}

	/**
	 * Make HTTP request.
	 *
	 * @param array $options Array( [curl] => CURLOPTS_???, [throttle] => throttle opts, ... )
	 * @return string Response from the server
	 */
	protected function exec( array $options ): string
	{
		static $constErrors = [];

		/* @formatter:off */
		$options = array_replace_recursive([
			'curl'     => [],
			'throttle' => [
				'host' => parse_url( $options['curl'][CURLOPT_URL], PHP_URL_HOST ),
			],
		], $options );
		/* @formatter:on */

		// Join arrays. Preserve numerical keys! Tip: Left side arrays wins!
		$curlopts = $options['curl'] + $this->options;

		// Force use dafault http headers if empty string
		if ( !$curlopts[CURLOPT_HTTPHEADER] ) {
			unset( $curlopts[CURLOPT_HTTPHEADER] );
		}

		DEBUG && $this->Logger->debug( 'Options ' . $this->Utils->print_r( $options ) );
		DEBUG && $this->Logger->debug( $this->printOptions( $curlopts ) );

		$Request = $this->Factory->Request();
		$Request->init();
		$Request->setOptArray( $curlopts );
		$this->lastUrl = $curlopts[CURLOPT_URL];

		$retry = $this->Factory->get( 'net_retry' );

		while ( $retry-- ) {
			$this->throttle( $options['throttle'] );

			$response = $Request->exec();
			if ( false !== $response ) {
				break;
			}

			// Handle error...
			if ( !$constErrors ) {
				$constErrors = $this->Utils->constants( 'CURLE_', 'curl' );
			}

			$errno = $Request->errno();
			$error = $Request->error();
			$curle = $constErrors[$errno] ?? '???';
			$error = sprintf( 'Error #%1$d %2$s: %3$s', $errno, $curle, $error );

			// Try again?
			if ( $retry && true === in_array( $errno, self::RETRY_ON, true ) ) {
				$this->Logger->debug( $error );
				$this->Logger->debug( 'Retries left: ' . $retry );
				continue;
			}

			throw new \RuntimeException( $error );
		}

		$this->lastInfo = $Request->getInfo();
		$Request->close();

		DEBUG && $this->Logger->debug( 'Result ' . $this->Utils->print_r( $this->lastInfo ) );

		/**
		 * Grab some statistics
		 * @link https://www.php.net/manual/en/function.curl-getinfo.php
		 */
		$this->stats['total_time'] += $this->lastInfo['total_time']; // fractional seconds (float)
		$this->stats['total_sent'] += $this->lastInfo['header_size'] + $this->lastInfo['request_size'];
		$this->stats['total_size'] += $this->lastInfo['size_download']; // @todo: Missing response headers size

		return $response ?? '';
	}

	/**
	 * {@inheritDoc}
	 * @see \Orkan\TLC\Transport\TransportAbstract::printOptions()
	 */
	public function printOptions( array $options ): string
	{
		static $constants = [];

		if ( !$constants ) {
			$constants = $this->Utils->constants( 'CURLOPT_', 'curl' );
		}

		return $this->Utils->print_r( $options, true, $constants );
	}
}
