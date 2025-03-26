<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */
namespace Orkan\TLC;

/**
 * File cache.
 *
 * @author Orkan <orkans+tlc@gmail.com>
 */
class Cache
{

	/**
	 * Cache duration constants.
	 *
	 * @var integer
	 */
	const DISABLED = 0;
	const FOREVER = -1;

	/**
	 * Is cache subfolder prepared?
	 * @see Cache::prepare()
	 */
	protected $prepared = false;

	/*
	 * Cache dir for current instance.
	 */
	protected $dir;

	/**
	 * Date object for internal use.
	 * @var \DateTime
	 */
	protected $Date;

	/*
	 * Services:
	 */
	protected $Factory;
	protected $Utils;
	protected $Logger;

	/**
	 * Setup.
	 *
	 * IMPORTANT:
	 * Only one instance allowed, meaning theres only one cache location for the whole app!
	 */
	public function __construct( Factory $Factory )
	{
		$this->Factory = $Factory->merge( self::defaults() );
		$this->Utils = $Factory->Utils();
		$this->Logger = $Factory->Logger();

		$this->Date = new \DateTime( 'now', ( new \DateTimeZone( $Factory->get( 'app_timezone', 'UTC' ) ) ) );
		$this->dir = $Factory->get( 'cache_dir' ) . '/' . $Factory->get( 'cache_name', 'unknown' );

		$Factory->cfg( 'cache_orig', $keep = $Factory->get( 'cache_keep' ) );
		$Factory->cfg( 'cache_keep', $this->Utils->dateDuration( $keep ) );
	}

	/**
	 * Get defaults.
	 *
	 * [cache_dir]
	 * Home cache dir. Default: {vendor/author/package}/cache
	 *
	 * [cache_name]
	 * Cache subfolder name inside cfg[cache_dir]
	 *
	 * [cache_keep]
	 * Cache duration (int|string). [0] disabled, [-1] keep forever
	 * Eg. 24*3600, "1 day", etc...
	 *
	 * [cache_wipe]
	 * Randomly purge preserved cache to increase timestamp deviation of remaining artefacts
	 */
	protected function defaults(): array
	{
		/* @formatter:off */
		return [
			'cache_dir'  => getenv( 'CACHE_DIR' ) ?: dirname( __DIR__ ) . '/cache',
			'cache_name' => null,
			'cache_keep' => getenv( 'CACHE_KEEP' ) ?: '1 year',
			'cache_wipe' => 0,
		];
		/* @formatter:on */
	}

	/**
	 * Cache dir full path.
	 */
	public function dir(): string
	{
		return $this->dir;
	}

	/**
	 * Render file info.
	 */
	private function render( string $file )
	{
		$time = is_file( $file ) ? $this->Date->setTimestamp( filemtime( $file ) )->format( 'Y-m-d H:i:s' ) : 'unavailable';
		return sprintf( '%s [%s]', $file, $time );
	}

	/**
	 * Refresh cache?
	 * Delete all cached files older than cfg[cache_keep]
	 */
	private function prepare( bool $force = false ): bool
	{
		if ( in_array( $this->Factory->get( 'cache_keep' ), [ self::DISABLED, self::FOREVER ] ) ) {
			return false;
		}

		if ( $this->prepared ) {
			return true;
		}

		// Prepare cache dir
		if ( is_dir( $this->dir ) ) {

			$keep = time() - $this->Factory->get( 'cache_keep' );
			$this->Logger->debug( 'Clear cache before: ' . $this->Date->setTimestamp( $keep )->format( DATE_RSS ) );

			// Clear expired cache
			$files = [];
			foreach ( glob( $this->dir . '/*' ) as $file ) {
				if ( filemtime( $file ) < $keep ) {
					$this->unlink( $file );
				}
				else {
					$files[] = $file;
				}
			}

			// @todo test
			if ( $files && $wipe = $this->Factory->get( 'cache_wipe' ) ) {
				$this->Logger->debug( "Wipe more cache: $wipe" );
				$this->Utils->arrayShuffle( $files );
				$files = array_slice( $files, 0, $wipe );
				foreach ( $files as $file ) {
					$this->unlink( $file );
				}
			}
		}
		// Create cache dir
		else {
			$this->Logger->debug( 'Create: ' . $this->dir );
			mkdir( $this->dir );
		}

		return $this->prepared = true;
	}

	/**
	 * Load gzipped data from file.
	 *
	 * @param  string      $id File identifier. Can be enything.
	 * @return string|bool File contents or false if no cache
	 */
	public function get( string $id )
	{
		if ( !$this->prepare() ) {
			return false;
		}

		if ( !is_file( $cfile = $this->name( $id ) ) ) {
			return false;
		}

		$data = file_get_contents( $cfile );
		$data = gzdecode( $data );

		$this->Logger->debug( $this->render( $cfile ) );

		return $data;
	}

	/**
	 * Save data to gzipped file.
	 *
	 * @param  string   $id File identifier (can be enything)
	 * @return int|bool Number of bytes saved (gzipped!) or false on error
	 */
	public function put( string $id, string $data )
	{
		if ( !$this->prepare() ) {
			return false;
		}

		$cfile = $this->name( $id );
		$bytes = file_put_contents( $cfile, gzencode( $data ) );

		$msg = sprintf( '%s (%s)', $cfile, $this->Utils->byteString( $bytes ) );
		$bytes ? $this->Logger->debug( $msg ) : $this->Logger->error( $msg );

		return $bytes;
	}

	/**
	 * Archive previously saved file under unique name, so it won't collide with cache name used.
	 *
	 * @param  string $id    Cache identifier
	 * @param  string $sufix Filename sufix
	 * @return string New file location
	 */
	public function archive( string $id, string $sufix = 'archived' )
	{
		$old = $this->name( $id );
		$new = $this->name( $id . '-' . $sufix . time() );

		if ( self::DISABLED === $this->Factory->get( 'cache_keep' ) || !is_file( $old ) ) {
			return false;
		}

		rename( $old, $new );
		touch( $new, time() );

		$this->Logger->debug( $this->render( $old ) );
		$this->Logger->debug( $this->render( $new ) );

		return $new;
	}

	/**
	 * Get full path fo cached file from string identifier.
	 *
	 * @param  string $id String identifier
	 * @return string Path to cached file
	 */
	protected function name( string $id ): string
	{
		return sprintf( '%s/%s.gz', $this->dir, $this->Utils->strSlug( $id ) );
	}

	/**
	 * Remove cached file by id.
	 *
	 * @param  string $id String identifier
	 * @return bool   True if deleted, fale otherwise
	 */
	public function del( string $id ): bool
	{
		return $this->unlink( $this->name( $id ), 1 );
	}

	/**
	 * Remove cached file by path.
	 */
	protected function unlink( string $cfile, int $backtrace = 0 ): bool
	{
		$this->Logger->debug( $this->render( $cfile ), $backtrace );
		return @unlink( $cfile );
	}
}
