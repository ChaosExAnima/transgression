<?php declare( strict_types=1 );

namespace Transgression;

use Error;
use MailPoet\Config\ServicesChecker;
use WP_Error;

use MailPoet\Entities\{NewsletterEntity, SegmentEntity, SubscriberEntity};
use MailPoet\Models\{Segment, Subscriber, SubscriberSegment};
use MailPoet\Newsletter\{NewslettersRepository, Renderer\Preprocessor};
use MailPoet\Newsletter\Renderer\{Renderer, Blocks\Renderer as BlocksRenderer, Columns\Renderer as ColumnsRenderer};
use MailPoet\Newsletter\Shortcodes\Shortcodes;
use MailPoet\Segments\SegmentsRepository;
use MailPoet\Settings\SettingsController;
use MailPoet\Subscribers\{ConfirmationEmailMailer, Source, SubscribersRepository};
use MailPoet\WP\Functions;
use MailPoetVendor\CSS;
use Transgression\Helpers\{Admin, Admin_Option, Admin_Select_Option};

class Emails extends Helpers\Singleton {
	protected Admin $admin;

	const TEMPLATES = [
		'email_approved' => 'Approved Template',
		'email_denied' => 'Denied Template',
		'email_duplicate' => 'Duplicate Application Template',
		'email_login' => 'Login Template',
	];

	private ?SubscriberEntity $subscriber = null;

	/** @var ?\MailPoet\DI\ContainerWrapper; */
	private $mailpoet_container = null;

	private string $custom_url = '';

	public function __construct() {
		if ( class_exists( '\\MailPoet\\DI\\ContainerWrapper' ) ) {
			$this->mailpoet_container = \MailPoet\DI\ContainerWrapper::getInstance();
		}

		$admin = new Admin( 'emails' );
		$admin->as_post_subpage(
			Applications::POST_TYPE,
			'emails',
			'Emails'
		);
		$admin->add_action( 'test-email', [ $this, 'do_test_email' ] );

		$newsletters = [];
		foreach ( $this->get_newsletter_templates() as $newsletter ) {
			$newsletters[ $newsletter->getId() ] = $newsletter->getSubject();
		}
		foreach ( self::TEMPLATES as $key => $name ) {
			( new Admin_Select_Option( $key, $name ) )
				->with_options( $newsletters )
				->render_after( [ $this, 'render_test_button' ] )
				->on_page( $admin );
		}

		$segments = [];
		foreach ( $this->get_segments() as $segment ) {
			$segments[ $segment->getId() ] = $segment->getName();
		}
		( new Admin_Select_Option( 'approved_list', 'Approved member segment' ) )
			->with_options( $segments )
			->on_page( $admin );

		$this->admin = $admin;
	}

	public function init() {
		add_filter( 'wp_mail_from', [ $this, 'filter_from' ] );
	}

	public function send_email( string $email, string $template_key ): ?WP_Error {
		if ( !isset( self::TEMPLATES[$template_key] ) ) {
			return new WP_Error( 'email-no-template', 'Template not found' );
		}

		$template_id = absint( get_option( $template_key, 0 ) );
		if ( !$template_id ) {
			return new WP_Error( 'email-template-unset', 'Template not set' );
		}

		try {
			$newsletter = $this->get_newsletter( $template_id );
			if ( !$newsletter ) {
				return new WP_Error( 'email-template-missing', 'Template not found' );
			}

			$is_html = true;
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				$is_html = $user->email_preference !== 'text';
			}

			$subject = $newsletter->getSubject();
			$body = $this->render_newsletter( $newsletter, !$is_html );

			$headers = [];
			if ( $is_html ) {
				$headers[] = 'Content-Type: text/html; charset=UTF-8';
			}

			$body = str_replace( '[custom-link]', $this->custom_url, $body );

			wp_mail(
				$email,
				$subject,
				$body,
				$headers
			);
		} catch ( \Throwable $error ) {
			log_error( new WP_Error( $error->getMessage(), $error->getTraceAsString() ) );
			return new WP_Error( 'send-fail', "There was a problem sending the mail to {$email}" );
		}
		$this->subscriber = null;

		return null;
	}

	public function send_user_email( int $user_id, string $template_key ): ?WP_Error {
		$user = get_userdata( $user_id );
		if ( !$user ) {
			return new WP_Error( 'email-no-user', 'User not found', compact( 'user_id' ) );
		}
		$this->subscriber = $this->get_subscriber( $user->user_email );
		return $this->send_email( $user->user_email, $template_key );
	}

	public function send_subscribe_confirmation( string $email, ?string $name = null, bool $create_subscriber = false ): void {
		if ( ! is_email( $email ) && ! $this->is_mp_enabled() ) {
			return;
		}

		if ( !SettingsController::getInstance()->get( 'signup_confirmation.enabled' ) ) {
			log_error( 'Confirmation signup not available' );
			return;
		}

		$subscriber = $this->get_subscriber( $email );
		if ( !$subscriber ) {
			if ( !$create_subscriber ) {
				log_error( "Could not find subscriber for {$email}" );
				return;
			}

			$subscriber_model = Subscriber::createOrUpdate( [
				'email' => $email,
				'first_name' => $name,
				'status' => SubscriberEntity::STATUS_UNCONFIRMED,
				'source' => Source::FORM,
			] );
			if ( $subscriber_model->getErrors() !== false || $subscriber_model->id === 0 ) {
				log_error( "Could not create subscriber for {$email}" );
				return;
			}
			/** @var SubscribersRepository */
			$subscriber_repo = $this->mailpoet_container->get( SubscribersRepository::class );
			/** @var SubscriberEntity */
			$entity = $subscriber_repo->findOneById( $subscriber_model->id );
		}

		/** @var ConfirmationEmailMailer */
		$confirmation_emailer = $this->mailpoet_container->get( ConfirmationEmailMailer::class );
		$confirmation_emailer->sendConfirmationEmailOnce( $entity );
		log_error( "Sent confirmation email to {$email}" );
	}

	public function subscribe_approved_user( int $user_id ) {
		$segment = get_option( 'approved_list' );
		if ( !$segment ) {
			return;
		}
		$subscriber = Subscriber::where( 'wp_user_id', $user_id )->findOne();
		SubscriberSegment::subscribeToSegments( $subscriber, [$segment] );
	}

	public function set_custom_url( string $url ) {
		$this->custom_url = $url;
	}

	public function filter_from( string $from ): string {
		if ( !is_email( $from ) ) {
			return 'events@transgression.party';
		}
		return $from;
	}

	/** MAILPOET */
	private function is_mp_enabled( bool $throw = false ): bool {
		if ( $this->mailpoet_container !== null ) {
			return true;
		}
		if ( $throw ) {
			throw new Error( 'MailPoet not enabled' );
		}
		return false;
	}

	private function get_newsletter_repo(): NewslettersRepository {
		$this->is_mp_enabled( true );

		/** @var NewslettersRepository */
		$repo = $this->mailpoet_container->get( NewslettersRepository::class );
		return $repo;
	}

	private function get_newsletter_templates(): array {
		if ( !$this->is_mp_enabled() ) {
			return [];
		}
		return $this->get_newsletter_repo()->findDraftByTypes( [NewsletterEntity::TYPE_STANDARD] );
	}

	private function get_newsletter( int $id ): ?NewsletterEntity {
		/** @var ?NewsletterEntity */
		$entity = $this->get_newsletter_repo()->findOneById( $id );
		return $entity;
	}

	/**
	 * @return SegmentEntity[]
	 */
	private function get_segments(): array {
		if ( !$this->is_mp_enabled() ) {
			return [];
		}

		/** @var SegmentsRepository */
		$segments_repo = $this->mailpoet_container->get( SegmentsRepository::class );
		/** @var SegmentEntity[] */
		$segments = $segments_repo->findBy( ['type' => SegmentEntity::TYPE_DEFAULT, 'deletedAt' => null] );
		return $segments;
	}

	private function get_renderer(): ?Renderer {
		$this->is_mp_enabled( true );

		return new Renderer(
			$this->mailpoet_container->get( BlocksRenderer::class ),
			$this->mailpoet_container->get( ColumnsRenderer::class ),
			$this->mailpoet_container->get( Preprocessor::class ),
			$this->mailpoet_container->get( CSS::class ),
			$this->mailpoet_container->get( ServicesChecker::class ),
			$this->mailpoet_container->get( Functions::class ),
		);
	}

	private function get_subscriber( string $email ): ?SubscriberEntity {
		if ( !$this->is_mp_enabled() ) {
			return null;
		}

		/** @var SubscribersRepository */
		$repo = $this->mailpoet_container->get( SubscribersRepository::class );

		/** @var ?SubscriberEntity */
		$subscriber = $repo->findOneBy( compact( 'email' ) );
		return $subscriber;
	}

	private function render_newsletter( NewsletterEntity $newsletter, bool $text = false ): string {
		$renderer = $this->get_renderer();

		$type = $text ? 'text' : 'html';
		$body = $renderer->render( $newsletter, null, $type );

		/** @var Shortcodes */
		$shortcodes = $this->mailpoet_container->get( Shortcodes::class );
		$shortcodes->setNewsletter( $newsletter );
		if ( $this->subscriber ) {
			$shortcodes->setSubscriber( $this->subscriber );
		}

		$body = $shortcodes->replace( $body );

		return $body;
	}

	/** ADMIN */
	public function do_test_email( string $template_key ) {
		check_admin_referer( 'test-email-' . $template_key );
		if ( ! isset( self::TEMPLATES[ $template_key ] ) ) {
			$this->admin->add_message( 'Could not find template', 'error' );
			return;
		}

		$user_id = get_current_user_id();
		$result = $this->send_user_email( $user_id, $template_key );
		if ( is_wp_error( $result ) ) {
			foreach ( $result->get_error_messages() as $message ) {
				$this->admin->add_message( $message, 'error' );
			}
		} else {
			$user = get_userdata( $user_id );
			$this->admin->add_message( "Email sent to {$user->user_email}!", 'success' );
		}
	}

	public function render_test_button( Admin_Option $option ): void {
		$test_url = $this->admin->get_url( [ 'test-email' => $option->key ] );
		printf(
			'&nbsp;<a class="button button-secondary" href="%s" id="%s-test">Send test</a>',
			esc_url( wp_nonce_url( $test_url, "test-email-{$option->key}" ) ),
			esc_attr( $option->key )
		);
	}
}
