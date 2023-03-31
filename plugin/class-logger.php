<?php declare( strict_types = 1 );

namespace Transgression;

enum LoggerLevels: string {
	case INFO = 'Info';
	case WARNING = 'Warning';
	case ERROR = 'Error';
}

class Logger {
	public const ACTION_NAME = 'transgression_log';

	public function __construct() {
		add_action( self::ACTION_NAME, [ $this, 'php_log' ], 10, 2 );
	}

	public function log( mixed $message ) {
		do_action( self::ACTION_NAME, $this->to_string( $message ), LoggerLevels::INFO, $message );
	}

	public function error( mixed $error ) {
		do_action( self::ACTION_NAME, $this->to_string( $error ), LoggerLevels::ERROR, $error );
	}

	public function php_log( string $message, LoggerLevels $severity ) {
		if ( $severity !== LoggerLevels::INFO ) {
			$message = "{$severity->value}: {$message}";
		}
		error_log( $message );
	}

	protected function to_string( mixed $message ): string {
		if ( $message instanceof \Throwable ) {
			return $message->__toString();
		} else if ( $message instanceof \WP_Error ) {
			$messages = [];
			foreach ( $message->get_error_codes() as $error_code ) {
				$messages[] = "{$error_code}: {$message->get_error_message( $error_code )}";
			}
			return implode( ', ', $messages );
		}
		return (string) $message;
	}
}
