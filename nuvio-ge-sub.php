<?php
/**
 * Plugin Name: Nuvio Geo Subs Pro
 * Description: Stremio / Nuvio subtitles addon that serves Georgian (ka) .srt files straight from the WordPress Media Library.
 * Version:     1.0.0
 * Author:      Zurab Kostava
 *
 * ---------------------------------------------------------------------------
 * HOW IT WORKS
 * ---------------------------------------------------------------------------
 * Every request whose path contains "/nuvio-ge-sub" is intercepted on the
 * WordPress `init` hook (priority 0) and answered directly, before WP runs its
 * main query or template loader.
 *
 *   OPTIONS  *                                        -> 204 + CORS preflight
 *   GET      /nuvio-ge-sub/                           -> small JSON index
 *   GET      /nuvio-ge-sub/manifest.json              -> Stremio manifest
 *   GET      /nuvio-ge-sub/subtitles/{type}/{id}.json -> subtitle list
 *   GET      /nuvio-ge-sub/subtitles/{type}/{id}/{extra}.json (TV clients)
 *   GET      /nuvio-ge-sub/stream/{attachment_id}.srt -> BOM-free, range-capable SRT
 *
 * ---------------------------------------------------------------------------
 * FILE NAMING CONVENTION (Media Library)
 * ---------------------------------------------------------------------------
 * The IMDB / TMDB id must appear in the attachment's title, slug or file name.
 * Stremio uses ":" as a separator, WordPress strips ":" from file names, so
 * use "-" instead:
 *
 *   Movie   tt26657236          ->  tt26657236_backrooms.srt
 *   Series  tt0903747:1:2       ->  tt0903747-1-2.srt        (S01E02)
 *   TMDB    tmdb:12345          ->  tmdb-12345.srt
 *
 * ---------------------------------------------------------------------------
 * INSTALL
 * ---------------------------------------------------------------------------
 * Either drop this file into wp-content/plugins/ (or mu-plugins/) and activate,
 * or require it from the active theme:
 *
 *   require_once get_template_directory() . '/nuvio-ge-sub.php';
 *
 * Optional: define( 'NUVIO_GE_SUB_DEBUG', true ) in wp-config.php to log every
 * addon request to the PHP error log (wp-content/debug.log with WP_DEBUG_LOG).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Nuvio_GE_Sub' ) ) :

final class Nuvio_GE_Sub {

	/** URL prefix that this addon owns. */
	const PREFIX = '/nuvio-ge-sub';

	/** Stremio manifest values. */
	const ADDON_ID      = 'org.zurabkostava.geosubtitles.v2';
	const ADDON_VERSION = '1.0.0';
	const ADDON_NAME    = 'Nuvio Geo Subs Pro';

	/** Language code returned to the client. Must be exactly "ka" for Nuvio. */
	const LANG = 'ka';

	/** Attachment file extension that is considered a subtitle. */
	const FILE_EXT = 'srt';

	/**
	 * Extension appended to generated stream URLs. The stream route also accepts
	 * the extensionless form, so set this to '' if an NGINX static-file rule
	 * swallows "*.srt" requests before they reach PHP.
	 */
	const STREAM_EXT = '.srt';

	/** Maximum number of subtitle entries returned for one id. */
	const MAX_RESULTS = 10;

	/** Cache-Control values. */
	const JSON_CACHE   = 'no-cache, must-revalidate, max-age=0';
	const STREAM_CACHE = 'public, max-age=3600';

	/* ------------------------------------------------------------------ */
	/*  Bootstrap                                                          */
	/* ------------------------------------------------------------------ */

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'handle' ), 0 );
	}

	/**
	 * Router. Returns early (and cheaply) for every request that is not ours.
	 */
	public static function handle() {
		$uri  = self::server( 'REQUEST_URI' );
		$path = (string) parse_url( $uri, PHP_URL_PATH );

		// Cheap pre-check before the regex.
		if ( false === strpos( $path, self::PREFIX ) ) {
			return;
		}

		// Match "/nuvio-ge-sub" anywhere in the path (sub-directory installs are
		// fine) as long as it is followed by "/" or the end of the path.
		if ( ! preg_match( '#' . preg_quote( self::PREFIX, '#' ) . '(/.*)?$#', $path, $m ) ) {
			return;
		}

		$route  = isset( $m[1] ) ? rtrim( $m[1], '/' ) : '';
		$method = strtoupper( self::server( 'REQUEST_METHOD' ) );
		if ( '' === $method ) {
			$method = 'GET';
		}

		self::prepare_output();
		self::cors_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );

		// 1. Preflight.
		if ( 'OPTIONS' === $method ) {
			http_response_code( 204 );
			header( 'Content-Length: 0' );
			exit;
		}

		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			header( 'Allow: GET, HEAD, OPTIONS' );
			self::json( array( 'error' => 'Method not allowed' ), 405 );
		}

		self::log(
			sprintf(
				'%s %s | Range=%s | UA=%s',
				$method,
				$uri,
				self::server( 'HTTP_RANGE' ),
				self::server( 'HTTP_USER_AGENT' )
			)
		);

		// Index.
		if ( '' === $route ) {
			self::index();
		}

		// 2. Manifest.
		if ( '/manifest.json' === $route ) {
			self::manifest();
		}

		// 3. Subtitles.
		//    /subtitles/movie/tt123.json
		//    /subtitles/series/tt123:1:2.json
		//    /subtitles/movie/tt123/videoHash=abc&videoSize=123.json   (TV clients)
		//    The optional extra segment is accepted and ignored.
		if ( preg_match( '#^/subtitles/(movie|series)/([^/]+?)(?:/[^/]*)?\.json$#', $route, $m ) ) {
			self::subtitles( $m[1], rawurldecode( $m[2] ) );
		}

		// 4. Stream proxy (with or without the .srt extension).
		if ( preg_match( '#^/stream/(\d+)(?:\.' . preg_quote( self::FILE_EXT, '#' ) . ')?$#i', $route, $m ) ) {
			self::stream( (int) $m[1], 'HEAD' === $method );
		}

		self::json( array( 'error' => 'Not found' ), 404 );
	}

	/* ------------------------------------------------------------------ */
	/*  Endpoints                                                          */
	/* ------------------------------------------------------------------ */

	private static function index() {
		self::json(
			array(
				'id'       => self::ADDON_ID,
				'name'     => self::ADDON_NAME,
				'version'  => self::ADDON_VERSION,
				'manifest' => self::url( '/manifest.json' ),
				'routes'   => array(
					'subtitles' => self::url( '/subtitles/{movie|series}/{id}.json' ),
					'stream'    => self::url( '/stream/{attachment_id}' . self::STREAM_EXT ),
				),
			)
		);
	}

	private static function manifest() {
		self::json(
			array(
				'id'            => self::ADDON_ID,
				'version'       => self::ADDON_VERSION,
				'name'          => self::ADDON_NAME,
				'description'   => 'Georgian (ka) subtitles served from the WordPress Media Library.',
				'resources'     => array( 'subtitles' ),
				'types'         => array( 'movie', 'series' ),
				'idPrefixes'    => array( 'tt', 'tmdb' ),
				'catalogs'      => array(),
				'behaviorHints' => array(
					'configurable'          => false,
					'configurationRequired' => false,
				),
			)
		);
	}

	/**
	 * @param string $type "movie" or "series".
	 * @param string $id   Stremio id, e.g. "tt26657236", "tt0903747:1:2", "tmdb:12345".
	 */
	private static function subtitles( $type, $id ) {
		// Accept only the id shapes we advertise via idPrefixes; anything else
		// gets an empty (but valid) response instead of hitting the database.
		if ( strlen( $id ) > 64 || ! preg_match( '/^(?:tt\d+|tmdb:\d+)(?::\d+:\d+)?$/i', $id ) ) {
			self::log( "subtitles: unsupported id '{$id}' ({$type})" );
			self::json( array( 'subtitles' => array() ) );
		}

		// Stremio "tt123:1:2" -> file name "tt123-1-2".
		$term = str_replace( ':', '-', $id );

		$subtitles = array();
		foreach ( self::find_attachments( $term ) as $i => $row ) {
			$subtitles[] = array(
				'id'   => $id . '_' . self::LANG . ( $i > 0 ? '_' . ( $i + 1 ) : '' ),
				'url'  => self::stream_url( (int) $row->ID ),
				'lang' => self::LANG,
			);
		}

		self::log( sprintf( 'subtitles: %s (%s) -> term "%s" -> %d result(s)', $id, $type, $term, count( $subtitles ) ) );

		self::json( array( 'subtitles' => $subtitles ) );
	}

	/**
	 * Serves the attachment as a clean, BOM-free SRT with full HTTP Range support.
	 *
	 * @param int  $attachment_id
	 * @param bool $is_head
	 */
	private static function stream( $attachment_id, $is_head ) {
		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type || 'inherit' !== $post->post_status ) {
			self::log( "stream: attachment {$attachment_id} not found" );
			self::json( array( 'error' => 'Subtitle not found' ), 404 );
		}

		$file = get_attached_file( $attachment_id );
		if (
			! $file
			|| strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) !== self::FILE_EXT
			|| ! is_file( $file )
			|| ! is_readable( $file )
		) {
			self::log( "stream: attachment {$attachment_id} has no readable ." . self::FILE_EXT . ' file' );
			self::json( array( 'error' => 'Subtitle file not found' ), 404 );
		}

		$data = @file_get_contents( $file );
		if ( false === $data ) {
			self::log( "stream: attachment {$attachment_id} could not be read" );
			self::json( array( 'error' => 'Subtitle file could not be read' ), 500 );
		}

		$data = self::normalize_text( $data );
		self::send_bytes( $data, $file, $is_head );
	}

	/* ------------------------------------------------------------------ */
	/*  Data access                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Finds .srt attachments whose title, slug, guid or file path contains the
	 * search term as a whole token (so "tt123-1-2" does not match "tt123-1-20"
	 * and "tt2665723" does not match "tt26657236").
	 *
	 * @param  string $term e.g. "tt26657236" or "tt0903747-1-2".
	 * @return object[]     Rows with ID, post_title, post_name, guid, file.
	 */
	private static function find_attachments( $term ) {
		global $wpdb;

		$like     = '%' . $wpdb->esc_like( $term ) . '%';
		$ext_like = '%' . $wpdb->esc_like( '.' . self::FILE_EXT );

		// One query: posts + the "_wp_attached_file" meta so we can match the
		// physical file name as well and confirm the extension in SQL.
		$sql = $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_name, p.guid, pm.meta_value AS file
			 FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm
			        ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file'
			 WHERE p.post_type   = 'attachment'
			   AND p.post_status = 'inherit'
			   AND ( pm.meta_value LIKE %s OR p.guid LIKE %s )
			   AND ( p.post_title LIKE %s OR p.post_name LIKE %s OR p.guid LIKE %s OR pm.meta_value LIKE %s )
			 ORDER BY p.post_date DESC, p.ID DESC
			 LIMIT %d",
			$ext_like,
			$ext_like,
			$like,
			$like,
			$like,
			$like,
			self::MAX_RESULTS * 5
		);

		$rows = $wpdb->get_results( $sql );
		if ( empty( $rows ) ) {
			return array();
		}

		// Whole-token check: not preceded by a letter/digit, not followed by a digit.
		$pattern = '/(?<![a-z0-9])' . preg_quote( $term, '/' ) . '(?![0-9])/i';
		$found   = array();

		foreach ( $rows as $row ) {
			$haystacks = array(
				(string) $row->post_title,
				(string) $row->post_name,
				basename( (string) parse_url( (string) $row->guid, PHP_URL_PATH ) ),
				basename( (string) $row->file ),
			);

			foreach ( $haystacks as $hay ) {
				if ( '' !== $hay && preg_match( $pattern, $hay ) ) {
					$found[] = $row;
					break;
				}
			}

			if ( count( $found ) >= self::MAX_RESULTS ) {
				break;
			}
		}

		return $found;
	}

	/* ------------------------------------------------------------------ */
	/*  Streaming helpers                                                  */
	/* ------------------------------------------------------------------ */

	/**
	 * Converts UTF-16 (with or without BOM) to UTF-8, strips every UTF-8 BOM
	 * and guarantees a trailing newline so the last cue is always flushed.
	 */
	private static function normalize_text( $data ) {
		$len = strlen( $data );

		if ( $len >= 2 && function_exists( 'mb_convert_encoding' ) ) {
			if ( "\xFF\xFE" === substr( $data, 0, 2 ) ) {
				$data = mb_convert_encoding( substr( $data, 2 ), 'UTF-8', 'UTF-16LE' );
			} elseif ( "\xFE\xFF" === substr( $data, 0, 2 ) ) {
				$data = mb_convert_encoding( substr( $data, 2 ), 'UTF-8', 'UTF-16BE' );
			} elseif ( $len >= 4 && "\0" === $data[1] && "\0" === $data[3] && "\0" !== $data[0] ) {
				// UTF-16LE without BOM (ASCII digits of the first cue index).
				$data = mb_convert_encoding( $data, 'UTF-8', 'UTF-16LE' );
			} elseif ( $len >= 4 && "\0" === $data[0] && "\0" === $data[2] && "\0" !== $data[1] ) {
				// UTF-16BE without BOM.
				$data = mb_convert_encoding( $data, 'UTF-8', 'UTF-16BE' );
			}
		}

		// Remove leading and any stray UTF-8 BOMs (U+FEFF); ExoPlayer chokes on them.
		$data = str_replace( "\xEF\xBB\xBF", '', $data );

		if ( '' !== $data && "\n" !== substr( $data, -1 ) ) {
			$data .= "\r\n";
		}

		return $data;
	}

	/**
	 * Parses a single-range "Range" header against an entity of $total bytes.
	 *
	 * @return array|null|false [start, end] to serve, null to ignore the header
	 *                          (serve the full body), false when unsatisfiable (416).
	 */
	private static function parse_range( $header, $total ) {
		$header = trim( $header );
		if ( '' === $header ) {
			return null;
		}

		if ( ! preg_match( '/^bytes\s*=\s*(.+)$/i', $header, $m ) ) {
			return null; // Unknown unit: ignore.
		}

		$spec = trim( $m[1] );
		if ( false !== strpos( $spec, ',' ) ) {
			return null; // Multi-range: a server may ignore it; we serve the full body.
		}

		if ( ! preg_match( '/^(\d*)-(\d*)$/', $spec, $r ) || ( '' === $r[1] && '' === $r[2] ) ) {
			return null; // Malformed: ignore.
		}

		if ( $total <= 0 ) {
			return false;
		}

		if ( '' === $r[1] ) {
			// Suffix range: "bytes=-500" -> last 500 bytes.
			$suffix = (int) $r[2];
			if ( $suffix <= 0 ) {
				return false;
			}
			$start = max( 0, $total - $suffix );
			$end   = $total - 1;
		} else {
			$start = (int) $r[1];
			$end   = ( '' === $r[2] ) ? $total - 1 : min( (int) $r[2], $total - 1 );
		}

		if ( $start >= $total ) {
			return false; // Beyond the end: 416.
		}
		if ( $start > $end ) {
			return null; // Inverted: treat as malformed and ignore.
		}

		return array( $start, $end );
	}

	/**
	 * Writes the SRT payload with correct 200 / 206 / 304 / 416 semantics.
	 */
	private static function send_bytes( $data, $file, $is_head ) {
		$total         = strlen( $data );
		$etag          = '"' . md5( $data ) . '"';
		$mtime         = (int) @filemtime( $file );
		$last_modified = gmdate( 'D, d M Y H:i:s', $mtime > 0 ? $mtime : time() ) . ' GMT';
		$filename      = preg_replace( '/[^A-Za-z0-9._-]+/', '_', basename( $file ) );

		header( 'Content-Type: application/x-subrip; charset=utf-8' );
		header( 'Content-Disposition: inline; filename="' . $filename . '"' );
		header( 'Accept-Ranges: bytes' );
		header( 'ETag: ' . $etag );
		header( 'Last-Modified: ' . $last_modified );
		header( 'Cache-Control: ' . self::STREAM_CACHE );

		// Conditional GET.
		if ( trim( self::server( 'HTTP_IF_NONE_MATCH' ) ) === $etag ) {
			http_response_code( 304 );
			exit;
		}

		// Range is only honoured on GET (RFC 7233 §3.1).
		$range = $is_head ? null : self::parse_range( self::server( 'HTTP_RANGE' ), $total );

		// If-Range: honour the range only when the validator still matches.
		if ( null !== $range ) {
			$if_range = trim( self::server( 'HTTP_IF_RANGE' ) );
			if ( '' !== $if_range && $if_range !== $etag && $if_range !== $last_modified ) {
				$range = null;
			}
		}

		if ( false === $range ) {
			http_response_code( 416 );
			header( 'Content-Range: bytes */' . $total );
			header( 'Content-Length: 0' );
			exit;
		}

		if ( null === $range ) {
			http_response_code( 200 );
			header( 'Content-Length: ' . $total );
			self::log( "stream: {$filename} -> 200 ({$total} bytes)" );
			if ( ! $is_head ) {
				echo $data;
			}
			exit;
		}

		list( $start, $end ) = $range;
		$length = $end - $start + 1;

		http_response_code( 206 );
		header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $total );
		header( 'Content-Length: ' . $length );
		self::log( "stream: {$filename} -> 206 ({$start}-{$end}/{$total})" );
		echo substr( $data, $start, $length );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/*  Small utilities                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Clears output buffers and disables compression so Content-Length and
	 * Content-Range describe exactly the bytes that leave PHP.
	 */
	private static function prepare_output() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // Tell page-cache plugins to stay away.
		}

		$level = ob_get_level();
		while ( $level-- > 0 && @ob_end_clean() ) {
			// Keep unwinding until every removable buffer is gone.
		}

		@ini_set( 'zlib.output_compression', '0' );
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' );
		}
	}

	private static function cors_headers() {
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET, HEAD, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Range, If-Range, If-None-Match, If-Modified-Since' );
		header( 'Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges, Content-Type' );
		header( 'Access-Control-Max-Age: 86400' );
	}

	/**
	 * Emits a JSON response and terminates the request.
	 */
	private static function json( $data, $status = 200 ) {
		$body = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $body ) {
			$body = '{"error":"Encoding failure"}';
			$status = 500;
		}

		http_response_code( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: ' . self::JSON_CACHE );
		header( 'Content-Length: ' . strlen( $body ) );
		echo $body;
		exit;
	}

	/**
	 * Absolute URL for an addon route. Forces https when the current request
	 * came in over TLS (directly or via a proxy) so TV clients never get an
	 * http:// link that NGINX would redirect.
	 */
	private static function url( $route ) {
		$url = home_url( self::PREFIX . $route );

		$forwarded = strtolower( self::server( 'HTTP_X_FORWARDED_PROTO' ) );
		if ( is_ssl() || 0 === strpos( $forwarded, 'https' ) ) {
			$url = set_url_scheme( $url, 'https' );
		}

		return $url;
	}

	private static function stream_url( $attachment_id ) {
		$url = self::url( '/stream/' . $attachment_id . self::STREAM_EXT );

		/**
		 * Filters the public stream URL for a subtitle attachment.
		 *
		 * @param string $url
		 * @param int    $attachment_id
		 */
		return (string) apply_filters( 'nuvio_ge_sub_stream_url', $url, $attachment_id );
	}

	/**
	 * Reads a $_SERVER value. WordPress adds magic quotes to $_SERVER, so the
	 * value is unslashed (matters for ETags in If-Range / If-None-Match).
	 */
	private static function server( $key ) {
		if ( ! isset( $_SERVER[ $key ] ) || ! is_scalar( $_SERVER[ $key ] ) ) {
			return '';
		}
		return (string) wp_unslash( $_SERVER[ $key ] );
	}

	private static function log( $message ) {
		if ( defined( 'NUVIO_GE_SUB_DEBUG' ) && NUVIO_GE_SUB_DEBUG ) {
			error_log( '[nuvio-ge-sub] ' . $message );
		}
	}
}

Nuvio_GE_Sub::boot();

endif;
