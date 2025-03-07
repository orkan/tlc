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
class Curl extends Transport
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
		 * @see \Orkan\TLC\Transport\Transport::defaults()
		 *
		 * [CURLOPT_COOKIE]
		 * Cookie: http header. The resulting header line is:
		 * Cookie: CURLOPT_COOKIEFILE; CURLOPT_COOKIE;
		 * These cookies are appended after JAR file cookies thus overwriting previous with same name!
		 *
		 * [CURLOPT_USERAGENT]
		 * It is used to set the User-Agent: header field in the HTTP request sent to the remote server.
		 * You can also set any custom header with CURLOPT_HTTPHEADER.
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
		 * [CURLOPT_CONNECTTIMEOUT]
		 * Connection timeout only
		 *
		 * [CURLOPT_TIMEOUT]
		 * Total timeout: connection + data transfer
		 *
		 * [CURLOPT_CAINFO]
		 * CA certificates. Can be set globbaly in php.ini: [curl] curl.cainfo="path to crt file"
		 *
		 * @formatter:off */
		$this->options = [
			CURLOPT_USERAGENT      => $Factory->get( 'net_useragent' ),
			CURLOPT_HTTPHEADER     => $Factory->get( 'net_headers' ),
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
	 * @see \Orkan\TLC\Transport\Transport::defaults()
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
	 * Options:
	 * [curl] => force custom CURLOPTS_***
	 *
	 * {@inheritDoc}
	 * @see \Orkan\TLC\Transport\Transport::get()
	 */
	public function get( string $url, array $opt = [] ): string
	{
		$opt['curl'] = $opt['curl'] ?? [];

		/* @formatter:off */
		$opt['curl'] += [
			CURLOPT_URL => $url,
		];
		/* @formatter:on */

		return $this->exec( $opt );
	}

	/**
	 * Do [post] http request.
	 *
	 * Options:
	 * [curl]   => force custom CURLOPTS_***
	 * [fields] => (array)  send as: multipart/form-data
	 * [fields] => (string) send as: application/x-www-form-urlencoded
	 * @see http_build_query()
	 *
	 * {@inheritdoc}
	 * @see \Orkan\TLC\Transport\Transport::post()
	 */
	public function post( string $url, array $opt = [] ): string
	{
		$opt['curl'] = $opt['curl'] ?? [];

		/* @formatter:off */
		$opt['curl'] += [
			CURLOPT_URL        => $url,
			CURLOPT_POST       => true,
			CURLOPT_POSTFIELDS => $opt['fields'] ?? [],
		];
		/* @formatter:on */

		return $this->exec( $opt );
	}

	/**
	 * Make HTTP request.
	 *
	 * TLC options:
	 * [curl] => force custom CURLOPTS_***
	 *
	 * @param array   $opt TLC options
	 * @return string Server response
	 */
	protected function exec( array $opt ): string
	{
		static $constErrors = [];

		/* @formatter:off */
		$opt = array_replace_recursive([
			'throttle' => [
				'host' => $this->host( $opt['curl'][CURLOPT_URL] ),
			],
		], $opt );
		/* @formatter:on */

		// Join arrays. Preserve numerical keys! Tip: Left side arrays wins!
		$curlopts = $opt['curl'] + $this->options;

		// Force use dafault http headers if empty string
		if ( !$curlopts[CURLOPT_HTTPHEADER] ) {
			unset( $curlopts[CURLOPT_HTTPHEADER] );
		}

		DEBUG && $this->Logger->debug( 'Opt ' . $this->Utils->print_r( $opt ) );
		DEBUG && $this->Logger->debug( 'Curl ' . $this->printOptions( $curlopts ) );

		$Request = $this->Factory->Request();
		$Request->init();
		$Request->setOptArray( $curlopts );
		$this->Stats->lastUrl = $curlopts[CURLOPT_URL];

		$retry = $this->Factory->get( 'net_retry' );

		while ( $retry-- ) {
			$this->throttle( $opt['throttle'] );

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

		$info = $Request->getInfo();
		$Request->close();

		DEBUG && $this->Logger->debug( 'Result ' . $this->Utils->print_r( $info ) );

		/**
		 * Grab some statistics
		 * @link https://www.php.net/manual/en/function.curl-getinfo.php
		 */
		$this->Stats->lastInfo = $info;
		$this->Stats->time += $info['total_time']; // fractional seconds (float)
		$this->Stats->sent += $info['header_size'] + $info['request_size'];
		$this->Stats->size += $info['size_download']; // @todo: Missing response headers size

		return $response ?? '';
	}

	/**
	 * Print all CURLOPT_***
	 */
	public function printOptions( array $opt ): string
	{
		static $constants = [];

		if ( !$constants ) {
			$constants = $this->Utils->constants( 'CURLOPT_', 'curl' );
		}

		return $this->Utils->print_r( $opt, [ 'keys' => $constants ] );
	}
}
