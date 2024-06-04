<?php declare( strict_types = 1 );

namespace Transgression;

use Transgression\Admin\Option_Select;
use Transgression\Admin\Page_Options;

enum LoggerLevels: int {
	case INFO = 2;
	case WARNING = 1;
	case ERROR = 0;

	public function name(): string {
		// @phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts
		return ucfirst( strtolower( $this->name ) );
	}
}

class Logger {
	protected const ACTION_NAME = PLUGIN_SLUG . '_log';
	protected const OPTION_LEVEL_NAME = 'log_level';

	public function __construct( bool $default_destination = true, Page_Options $settings ) {
		$settings->register_message( 'test_sent', __( 'Test log sent', 'transgression' ), 'success' );
		$settings->register_message( 'test_level_disabled', __( 'Log level disabled', 'transgression' ), 'warning' );
		$settings->register_message( 'test_invalid', __( 'Invalid log level', 'transgression' ), 'error' );

		$settings->add_section( 'logging', __( 'Logging', 'transgression' ) );
		$log_level_option = ( new Option_Select( self::OPTION_LEVEL_NAME, __( 'Logging Level', 'transgression' ), LoggerLevels::WARNING->name() ) )
			->describe( __( 'The level to log', 'transgression' ) )
			->without_none()
			->with_options( [
				LoggerLevels::INFO->value => ucfirst( LoggerLevels::INFO->name() ),
				LoggerLevels::WARNING->value => ucfirst( LoggerLevels::WARNING->name() ),
				LoggerLevels::ERROR->value => LoggerLevels::ERROR->name(),
			] )
			->in_section( 'logging' )
			->on_page( $settings );
		foreach ( LoggerLevels::cases() as $level ) {
			$settings->add_button(
				$log_level_option,
				// translators: %s: log level
				sprintf( __( '%s Test', 'transgression' ), $level->name() ),
				[ $this, 'send_test' ],
				(string) $level->value
			);
		}
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
			$message = "{$severity->name}: {$message}";
		}
		error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}

	/**
	 * Sends test log message
	 *
	 * @param string $level_name Log level
	 * @return string
	 */
	public function send_test( string $level_value ): string {
		$level = LoggerLevels::tryFrom( intval( $level_value ) );
		if ( $level === null ) {
			return 'test_invalid';
		}
		if ( ! self::check_level( $level ) ) {
			return 'test_level_disabled';
		}
		self::log( 'Test log', $level );
		return 'test_sent';
	}

	protected static function log( mixed $message, LoggerLevels $severity ): void {
		if ( self::check_level( $severity ) ) {
			do_action( self::ACTION_NAME, self::to_string( $message ), $severity, $message );
		}
	}

	public static function info( mixed $message ) {
		self::log( $message, LoggerLevels::INFO );
	}

	public static function warning( mixed $warning ) {
		self::log( $warning, LoggerLevels::WARNING );
	}

	public static function error( mixed $error ) {
		self::log( $error, LoggerLevels::ERROR );
	}

	protected static function check_level( LoggerLevels $level ): bool {
		return $level->value <= get_option( self::OPTION_LEVEL_NAME, LoggerLevels::WARNING->value );
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
