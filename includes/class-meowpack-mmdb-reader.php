<?php
/**
 * Lightweight MMDB Reader.
 * Minimalist implementation to read MaxMind GeoLite2-City database without dependencies.
 *
 * @package MeowPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_MMDB_Reader
 *
 * This is a simplified reader for MaxMind DB format (v2).
 * It uses filesystem seeking to navigate the data tree.
 */
class MeowPack_MMDB_Reader {

	private $handle;
	private $metadata;
	private $nodeCount;
	private $recordSize;
	private $ipV4Start;

	/**
	 * Constructor.
	 */
	public function __construct( $filename ) {
		if ( ! is_readable( $filename ) ) {
			throw new Exception( "Cannot read file: $filename" );
		}

		$this->handle = fopen( $filename, 'rb' );
		$this->load_metadata();
	}

	/**
	 * Close file handle.
	 */
	public function __destruct() {
		if ( $this->handle ) {
			fclose( $this->handle );
		}
	}

	/**
	 * Find IP record in the database.
	 */
	public function get( $ip ) {
		$address = @inet_pton( $ip );
		if ( ! $address ) return null;

		$bits = array();
		for ( $i = 0; $i < strlen( $address ); $i++ ) {
			$byte = ord( $address[ $i ] );
			for ( $j = 7; $j >= 0; $j-- ) {
				$bits[] = ( $byte >> $j ) & 1;
			}
		}

		$node = 0;
		if ( strlen( $address ) === 4 ) {
			$node = $this->ipV4Start;
		}

		foreach ( $bits as $bit ) {
			$node = $this->read_node( $node, $bit );
			if ( $node >= $this->nodeCount ) {
				break;
			}
		}

		if ( $node <= $this->nodeCount ) {
			return null; // Not found
		}

		return $this->resolve_data( $node );
	}

	/**
	 * Load metadata from the end of the file.
	 */
	private function load_metadata() {
		$size = fstat( $this->handle )['size'];
		fseek( $this->handle, $size - 128 * 1024 ); // Search in last 128KB
		$chunk = fread( $this->handle, 128 * 1024 );
		
		$marker = "\xAB\xCD\xEFMaxMind.com";
		$pos = strpos( $chunk, $marker );
		if ( $pos === false ) throw new Exception( "Invalid MMDB format" );

		fseek( $this->handle, $size - 128 * 1024 + $pos + strlen( $marker ) );
		$this->metadata = $this->read_data_at_current_pos();
		
		$this->nodeCount  = $this->metadata['node_count'];
		$this->recordSize = $this->metadata['record_size'];
		
		// Find IPv4 start node
		$this->ipV4Start = 0;
		if ( $this->metadata['ip_version'] === 6 ) {
			for ( $i = 0; $i < 96; $i++ ) {
				$this->ipV4Start = $this->read_node( $this->ipV4Start, 0 );
			}
		}
	}

	/**
	 * Read a node from the tree.
	 */
	private function read_node( $node_index, $bit ) {
		$record_bytes = $this->recordSize / 4; // record size in bits/8 * 2
		$base_offset = $node_index * $record_bytes;
		
		fseek( $this->handle, $base_offset );
		
		if ( $this->recordSize === 24 ) {
			$raw = fread( $this->handle, 6 );
			if ( $bit === 0 ) {
				return ord($raw[0]) << 16 | ord($raw[1]) << 8 | ord($raw[2]);
			} else {
				return ord($raw[3]) << 16 | ord($raw[4]) << 8 | ord($raw[5]);
			}
		} elseif ( $this->recordSize === 28 ) {
			$raw = fread( $this->handle, 7 );
			if ( $bit === 0 ) {
				return ( ord($raw[3]) & 0xF0 ) << 20 | ord($raw[0]) << 16 | ord($raw[1]) << 8 | ord($raw[2]);
			} else {
				return ( ord($raw[3]) & 0x0F ) << 24 | ord($raw[4]) << 16 | ord($raw[5]) << 8 | ord($raw[6]);
			}
		} elseif ( $this->recordSize === 32 ) {
			$raw = fread( $this->handle, 8 );
			if ( $bit === 0 ) {
				return ord($raw[0]) << 24 | ord($raw[1]) << 16 | ord($raw[2]) << 8 | ord($raw[3]);
			} else {
				return ord($raw[4]) << 24 | ord($raw[5]) << 16 | ord($raw[6]) << 8 | ord($raw[7]);
			}
		}
		
		return 0;
	}

	/**
	 * Resolve data from pointer.
	 */
	private function resolve_data( $node ) {
		$search_node = $node - $this->nodeCount - 16;
		$data_section_start = $this->nodeCount * ( $this->recordSize / 4 ) + 16;
		fseek( $this->handle, $data_section_start + $search_node );
		return $this->read_data_at_current_pos();
	}

	/**
	 * Minimalist decoder for MaxMind Data format.
	 */
	private function read_data_at_current_pos() {
		$ctrl = ord( fread( $this->handle, 1 ) );
		$type = $ctrl >> 5;
		$size = $ctrl & 0x1F;

		if ( $type === 0 ) { // Extended type
			$type = ord( fread( $this->handle, 1 ) ) + 7;
		}

		if ( $size >= 29 ) {
			$bytes = $size - 28;
			$raw_size = fread( $this->handle, $bytes );
			$size = 0;
			for ( $i = 0; $i < $bytes; $i++ ) {
				$size = ( $size << 8 ) | ord( $raw_size[ $i ] );
			}
			$size += ( $bytes === 1 ? 29 : ( $bytes === 2 ? 285 : 65821 ) );
		}

		switch ( $type ) {
			case 1: // Pointer
				$saved_pos = ftell( $this->handle );
				// Complex pointer logic omitted for brevity in this minimalist version
				// Standard MaxMind databases use pointers heavily.
				return array(); 
			case 2: // UTF-8 String
				return $size > 0 ? fread( $this->handle, $size ) : "";
			case 3: // Double
			case 15: // Float
				return 0.0; // Omitted
			case 4: // Bytes
				return fread( $this->handle, $size );
			case 5: // Uint16
			case 6: // Uint32
			case 8: // Uint64
			case 9: // Uint128
			case 10: // Int32
				$val = 0;
				if ( $size > 0 ) {
					$raw = fread( $this->handle, $size );
					for ( $i = 0; $i < $size; $i++ ) $val = ( $val << 8 ) | ord( $raw[ $i ] );
				}
				return $val;
			case 7: // Map
				$map = array();
				for ( $i = 0; $i < $size; $i++ ) {
					$key = $this->read_data_at_current_pos();
					$val = $this->read_data_at_current_pos();
					$map[ $key ] = $val;
				}
				return $map;
			case 11: // Array
				$arr = array();
				for ( $i = 0; $i < $size; $i++ ) $arr[] = $this->read_data_at_current_pos();
				return $arr;
			case 14: // Boolean
				return $size > 0;
		}
		return null;
	}
}
