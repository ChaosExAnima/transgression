<?php declare( strict_types = 1 );

namespace Transgression;

enum LoggerLevels: string {
	case INFO = 'Info';
	case WARNING = 'Warning';
	case ERROR = 'Error';
}

class Logger {
	protected const ACTION_NAME = PLUGIN_SLUG . '_log';

	public function __construct( bool $default_destination = true ) {
		if ( $default_destination ) {
			$this->register_destination( [ $this, 'php_log' ] );
		}
	}

	/**
	 * Registers a logging destination
	 *
	 * @param callable $destination
	 * @return void
	 */
	public function register_destination( callable $destination ) {
		add_action( self::ACTION_NAME, $destination, 10, 3 );
	}

	public function php_log( string $message, LoggerLevels $severity ) {
		if ( $severity !== LoggerLevels::INFO ) {
			$message = "{$severity->value}: {$message}";
		}
		error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}

	public static function info( mixed $message ) {
		do_action( self::ACTION_NAME, self::to_string( $message ), LoggerLevels::INFO, $message );
	}

	public static function error( mixed $error ) {
		do_action( self::ACTION_NAME, self::to_string( $error ), LoggerLevels::ERROR, $error );
	}

	protected static function to_string( mixed $message ): string {
		if ( $message instanceof \Throwable ) {
			return $message->__toString();
		} elseif ( $message instanceof \WP_Error ) {
			$messages = [];
			foreach ( $message->get_error_codes() as $error_code ) {
				$messages[] = "{$error_code}: {$message->get_error_message( $error_code )}";
			}
			return implode( ', ', $messages );
		} elseif ( is_array( $message ) || is_object( $message ) ) {
			return wp_json_encode( $message, JSON_PRETTY_PRINT );
		}
		return (string) $message;
	}
}
