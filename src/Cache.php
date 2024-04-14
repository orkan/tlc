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
	 * Prepare cache only once per instance.
	 *
	 * @var bool
	 */
	protected $prepared = false;

	/**
	 * Cache sub-dir for current instance.
	 *
	 * @var string
	 */
	protected $dir;

	/**
	 * A DateTime object for internal use.
	 *
	 * @var \DateTime
	 */
	protected $DT;

	/*
	 * Services:
	 */
	protected $Factory;
	protected $Utils;
	protected $Logger;

	/**
	 * Setup.
	 */
	public function __construct( Factory $Factory )
	{
		$this->Factory = $Factory->merge( self::defaults() );
		$this->Utils = $Factory->Utils();
		$this->Logger = $Factory->Logger();

		$this->DT = new \DateTime( 'now', ( new \DateTimeZone( $Factory->cfg( 'app_timezone' ) ) ) );
		$this->dir = $Factory->cfg( 'cache_dir' ) . '/' . $Factory->cfg( 'cache_name' );
	}

	/**
	 * Get default config.
	 */
	protected function defaults(): array
	{
		/* @formatter:off */
		return [
			'cache_keep' => 24 * 3600, // Cache duration (secs). [0] disabled, [-1] keep forever.
			'cache_name' => 'unknown',
			'cache_dir'  => dirname( __DIR__ ) . '/cache', // [vendor/author/package]/cache
		];
		/* @formatter:on */
	}

	/**
	 * Get full path to current cache dir.
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
		$time = is_file( $file ) ? $this->DT->setTimestamp( filemtime( $file ) )->format( 'Y-m-d H:i:s' ) : 'unavailable';
		return sprintf( '%s [%s]', $file, $time );
	}

	/**
	 * Cache refresh.
	 * Delete all cached files older than ['cache_keep']
	 */
	private function prepare( bool $force = false ): bool
	{
		if ( in_array( $this->Factory->cfg( 'cache_keep' ), [ self::DISABLED, self::FOREVER ] ) ) {
			return false;
		}

		if ( $this->prepared ) {
			return true;
		}

		// Prepare cache dir
		if ( is_dir( $this->dir ) ) {

			$this->DT->setTimestamp( $expired = time() - $this->Factory->cfg( 'cache_keep' ) );
			$this->Logger->debug( 'Clear cache until: ' . $this->DT->format( 'Y-m-d H:i:s' ) );

			// Clear expired cache
			foreach ( glob( $this->dir . '/*' ) as $cfile ) {
				if ( filemtime( $cfile ) < $expired ) {
					$this->unlink( $cfile );
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
	 * @param string       $id File identifier. Can be enything.
	 * @return string|null     File contents or false if no cache
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

		DEBUG && $this->Logger->debug( $this->render( $cfile ) );

		return $data;
	}

	/**
	 * Save data to gzipped file.
	 *
	 * @param  string   $id File identifier (can be enything)
	 * @return int|bool     Number of bytes saved (gzipped!) or false on error
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

		if ( self::DISABLED === $this->Factory->cfg( 'cache_keep' ) || !is_file( $old ) ) {
			return false;
		}

		rename( $old, $new );
		touch( $new, time() );

		DEBUG && $this->Logger->debug( $this->render( $old ) );
		DEBUG && $this->Logger->debug( $this->render( $new ) );

		return $new;
	}

	/**
	 * Get full path fo cached file from string identifier.
	 *
	 * @param  string $id String identifier
	 * @return string     Path to cached file
	 */
	public function name( string $id ): string
	{
		return sprintf( '%s/%s.gz', $this->dir, $this->Utils->strSlug( $id ) );
	}

	/**
	 * Remove cached file by id.
	 *
	 * @param  string $id String identifier
	 * @return bool       True if deleted, fale otherwise
	 */
	public function del( string $id ): bool
	{
		return $this->unlink( $this->name( $id ), 1 );
	}

	/**
	 * Remove cached file by path.
	 */
	private function unlink( string $cfile, int $backtrace = 0 ): bool
	{
		DEBUG && $this->Logger->debug( $this->render( $cfile ), $backtrace );
		return @unlink( $cfile );
	}
}
