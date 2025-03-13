<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2025 Orkan <orkans+tlc@gmail.com>
 */
namespace Orkan\TLC\Transport;

use Orkan\TLC\Application;
use Orkan\TLC\Factory;

/**
 * Flaresolverr proxy server handler.
 *
 * @link https://github.com/FlareSolverr/FlareSolverr
 * @link https://www.zenrows.com/blog/flaresolverr#how-to-use
 * @link https://www.selenium.dev/
 * @link https://github.com/ultrafunkamsterdam/undetected-chromedriver/tree/master
 *
 * @author Orkan <orkans+tlc@gmail.com>
 */
class Flaresolverr extends Transport
{
	/**
	 * List of active sessions.
	 * We could use "sessions.list" API but that could double the net call stats.
	 */
	protected array $sessions = [];

	/*
	 * Services:
	 */
	protected $Transport;

	/*
	 * Setup.
	 */
	public function __construct( Factory $Factory )
	{
		$Factory->merge( self::defaults() );
		parent::__construct( $Factory );

		$this->Transport = $Factory->Transport();
	}

	/**
	 * Shutdown proxy handler.
	 */
	public function __destruct()
	{
		$this->close();
	}

	/**
	 * {@inheritDoc}
	 * @see \Orkan\TLC\Transport\Transport::defaults()
	 */
	protected function defaults(): array
	{
		/**
		 * [proxy_endpoint]
		 * Flaresolverr api url.
		 * Its possible to change it via ENV variables. Then update this too!
		 *
		 * [proxy_sessions]
		 * Preserve browser instance between requests to the same host?
		 *
		 * [proxy_session_ttl]
		 * Re-create browser instance after this time. (sec)
		 *
		 * [proxy_timeout]
		 * Max timeout to solve the challenge. (sec)
		 *
		 * [proxy_headers]
		 * Headers required by Flaresolverr API server
		 *
		 * [net_timeout]
		 * Max time to wait while trying to connect to Flaresolverr proxy + time to solve the challenge.
		 * Use O to let Flaresolverr decide. (sec)
		 *
		 * [net_useragent]
		 * Dont cheat on Flaresolverr ;)
		 *
		 * @formatter:off */
		return [
			'proxy_endpoint'    => getenv( 'PROXY_ENDPOINT' ) ?: 'http://localhost:8191/v1',
			'proxy_sessions'    => getenv( 'PROXY_SESSIONS' ) ?: true,
			'proxy_session_ttl' => getenv( 'PROXY_SESSION_TTL' ) ?: 60,
			'proxy_timeout'     => getenv( 'PROXY_TIMEOUT' ) ?: 60,
			'proxy_headers'     => [
				'Content-Type: application/json',
			],
			'net_timeout'       => 0,
			'net_useragent'     => Application::getVersion(),
		];
		/* @formatter:on */
	}

	/**
	 * {@inheritdoc}
	 */
	public function get( string $url, array $opt = [] ): string
	{
		DEBUG && $this->Logger->debug( $url );
		DEBUG && $this->Logger->debug( 'Opt ' . $this->Utils->print_r( $opt ) );

		/* @formatter:off */
		$api = [
			'cmd' => 'request.get',
			'url' => $url,
		];
		/* @formatter:on */

		return $this->exec( $api, $opt );
	}


	/**
	 * Send POST data to target url.
	 *
	 * Flare requires URL-encoded POST string in api[postData]
	 * Later, the whole api[] command will be JSON-encoded and sent to Flare as POST string by Curl.
	 * @see Flaresolverr::getUrl()
	 *
	 * {@inheritDoc}
	 * @see \Orkan\TLC\Transport\Transport::post()
	 */
	public function post( string $url, array $opt = [] ): string
	{
		DEBUG && $this->Logger->debug( $url );
		DEBUG && $this->Logger->debug( 'Opt ' . $this->Utils->print_r( $opt ) );

		/* @formatter:off */
		$api = [
			'cmd'      => 'request.post',
			'url'      => $url,
			'postData' => http_build_query( $opt['fields'] ?? []),
		];
		/* @formatter:on */

		return $this->exec( $api, $opt );
	}

	/**
	 * Make POST request to proxy server.
	 *
	 * @param array   $api API command (cmd, url, ...)
	 * @param array   $opt TLC options
	 * @return string Html body
	 */
	protected function exec( array $api, array $opt ): string
	{
		if ( $this->Factory->get( 'proxy_sessions' ) ) {
			$host = $this->host( $api['url'] );
			$api['session'] = $host;

			if ( !isset( $this->sessions[$host] ) ) {
				/*
				 * response:
				 * {
				 *   "status": "ok",
				 *   "message": "Session created successfully.",
				 *   "session": "telemagazyn",
				 *   "startTimestamp": 1736021823757,
				 *   "endTimestamp": 1736021824299,
				 *   "version": "3.3.21"
				 * }
				 * {
				 *   "error": "Method not allowed.",
				 *   "status_code": 405
				 * }
				 */
				$this->call( [ 'cmd' => 'sessions.create', 'session' => $host ] );

				$this->sessions[$host] = $this->Utils->exectime();
				$this->Logger->debug( 'Session created: ' . $api['session'] );
			}
			else {
				$this->Logger->debug( 'Session used: ' . $api['session'] );
			}
		}

		if ( $tmp = $this->Factory->get( 'proxy_session_ttl' ) ) {
			$api['session_ttl_minutes'] = floor( $tmp / 60 ); // to min
		}

		if ( $tmp = $this->Factory->get( 'proxy_timeout' ) ) {
			$api['maxTimeout'] = $tmp * 1000; // to msec
		}

		/*
		 * response:
		 * {
		 *   "status": "ok",
		 *   "message": "Challenge not detected!",
		 *   "solution": {
		 *     "url": "https://telemagazyn.pl/stacje",
		 *     "status": 200,
		 *     "cookies": [
		 *       {
		 *         "domain": ".telemagazyn.pl",
		 *         "expiry": 1767590889,
		 *         "httpOnly": true,
		 *         "name": "cf_clearance",
		 *         "path": "/",
		 *         "sameSite": "None",
		 *         "secure": true,
		 *         "value": "Sx354..."
		 *       },
		 *       {
		 *       ...
		 *       },
		 *           ],
		 *     "userAgent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
		 *     "headers": {},
		 *     "response": "<html>..."
		 *   },
		 *   "startTimestamp": 1736054887231,
		 *   "endTimestamp": 1736054892524,
		 *   "version": "3.3.21"
		 * }
		 */
		$json = $this->call( $api );

		return $json['solution']['response'];
	}

	/**
	 * Comunicate with proxy server via Curl.
	 *
	 * Possible requests:
	 * - Setup proxy server: $api = [ cmd => sessions.create, session => ... ]
	 * - Get html page:      $api = [ cmd => request.get    , url     => ... ]
	 *
	 * @param  array $api API data sent to proxy server
	 * @return mixed Decoded JSON
	 */
	protected function call( array $api = [] )
	{
		$end = $this->Factory->get( 'proxy_endpoint' );
		$url = $api['cmd'] === 'request.get' || $api['cmd'] === 'request.post' ? $api['url'] : '';

		/**
		 * [fields]
		 * POST data Array ( [field1] => val1, ... )
		 * @see Curl::post()
		 *
		 * [transport]
		 * To comunicate with proxy server: POST + json
		 *
		 * [throttle]
		 * Even if we communicate with proxy via getJson(), use NET throttles when requesting external urls!
		 * No info about Flare internal throttle functionality.
		 *
		 * @formatter:off */
		$opt = [
			'curl' => [
				CURLOPT_HTTPHEADER => $this->Factory->get( 'proxy_headers' ),
			],
			'throttle' => [
				'host'     => $this->host( $url ?: $end ),
				'wait_min' => $url ? $this->Factory->get( 'net_throttle' ) : 0,
				'wait_max' => $url ? $this->Factory->get( 'net_throttle_max' ) : 0,
			],
			'cache' => [
				'key'     => $url ?: $end . '?' . $api['cmd'],
				'refresh' => empty( $url ),
			],
			'api' => $api,
		];
		/* @formatter:on */

		$json = parent::getJson( $end, $opt );

		// Archive last cached error response, so it won't block next run
		if ( $err = $this->error( $json ) ) {
			$this->Cache->archive( $opt['cache']['key'] );
			throw new \RuntimeException( $err );
		}

		return $json;
	}

	/**
	 * Loop back external urls then use cURL to comunicate with Flare.
	 *
	 * {@inheritDoc}
	 * @see \Orkan\TLC\Transport\Transport::getUrl()
	 */
	public function getUrl( string $url, array $opt = [] ): string
	{
		// Is loop back?
		if ( isset( $opt['api'] ) ) {
			/**
			 * JSON-encode API command and send as POST string.
			 * Internally Curl will mark it as "application/x-www-form-urlencoded"
			 * @see Curl::post()
			 */
			$opt['fields'] = json_encode( $opt['api'] );
			return $this->Transport->post( $url, $opt );
		}

		// Loop back this request via:
		// parent::getUrl() > this::get() > this::exec() > this::call() > parent::getJson() > this::getUrl()
		return parent::getUrl( $url, $opt );
	}

	/**
	 * Check for json errors.
	 */
	protected function error( array $json ): string
	{
		$module = 'Flare';
		if ( isset( $json['error'] ) ) {
			return "$module error: {$json['error']}";
		}
		if ( isset( $json['status'] ) && $json['status'] === 'error' ) {
			return "$module error: {$json['message']}";
		}

		$module = 'TLC';
		if ( isset( $json['errors'] ) ) {
			return "$module errors: " . implode( ', ', $json['errors'] );
		}

		return '';
	}

	/**
	 * Close any running Chromium sessions.
	 *
	 * NOTE:
	 * This will remove Chromium.exe processes from memory,
	 * otherwise they'll stay oppened even after closing flaresolverr.exe
	 *
	 * In most cases it's not required to call this method manually,
	 * since it's called from class destructor anyway.
	 */
	public function close()
	{
		foreach ( array_keys( $this->sessions ) as $v ) {
			$this->Logger->debug( 'Session destroy: ' . $v );
			$this->call( [ 'cmd' => 'sessions.destroy', 'session' => $v ] );
			unset( $this->sessions[$v] );
		}
	}
}
