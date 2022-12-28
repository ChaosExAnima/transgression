<?php declare( strict_types = 1 );

namespace Transgression;

class Logger {
	public function log( mixed $message ) {
		error_log( $this->to_string( $message ) );
	}

	public function error( mixed $error ) {
		error_log( 'Error: ' . $this->to_string( $error ) );
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
