<?php declare( strict_types=1 );

namespace Transgression\Modules\Email;

use Error;
use MailPoet\Config\ServicesChecker;

use MailPoet\Entities\{NewsletterEntity, SegmentEntity, SubscriberEntity};
use MailPoet\Logging\LoggerFactory;
use MailPoet\Newsletter\{NewslettersRepository, Renderer\Preprocessor};
use MailPoet\Newsletter\Renderer\{Renderer, Blocks\Renderer as BlocksRenderer, Columns\Renderer as ColumnsRenderer};
use MailPoet\Newsletter\Sending\SendingQueuesRepository;
use MailPoet\Newsletter\Shortcodes\Shortcodes;
use MailPoet\Segments\SegmentsRepository;
use MailPoet\Subscribers\SubscribersRepository;
use MailPoet\WP\Functions;
use MailPoetVendor\CSS;
use Transgression\Admin\Option;
use Transgression\Admin\Option_Select;

class MailPoet extends Email {
	/** @var ?\MailPoet\DI\ContainerWrapper; */
	private $mailpoet_container = null;

	/**
	 * @inheritDoc
	 */
	public function __construct(
		protected Emailer $emailer,
		public ?string $email = null,
		public ?string $subject = null
	) {
		if ( ! class_exists( '\\MailPoet\\DI\\ContainerWrapper' ) ) {
			throw new Error( 'MailPoet is not loaded' );
		}
		$this->mailpoet_container = \MailPoet\DI\ContainerWrapper::getInstance();
		parent::__construct( $emailer, $email, $subject );
	}

	protected function attempt_send(): bool {
		$template_id = absint( get_option( $this->template, 0 ) );
		if ( ! $template_id ) {
			throw new Error( 'Template not set' );
		}

		$is_html = true;
		$user = get_user_by( 'email', $this->email );
		if ( $user ) {
			$is_html = $user->email_preference !== 'text';
		}

		$newsletter = $this->get_newsletter( $template_id );
		$subject = $newsletter->getSubject();
		$body = $this->render_newsletter( $newsletter, ! $is_html );

		return wp_mail(
			$this->email,
			$subject,
			$this->process_body( $body, false ),
			$this->get_headers( $is_html )
		);
	}

	private function get_newsletter_repo(): NewslettersRepository {
		return $this->mailpoet_container->get( NewslettersRepository::class );
	}

	private function get_newsletter( int $id ): ?NewsletterEntity {
		return $this->get_newsletter_repo()->findOneById( $id );
	}

	private function get_renderer(): ?Renderer {
		return new Renderer(
			$this->mailpoet_container->get( BlocksRenderer::class ),
			$this->mailpoet_container->get( ColumnsRenderer::class ),
			$this->mailpoet_container->get( Preprocessor::class ),
			$this->mailpoet_container->get( CSS::class ),
			$this->mailpoet_container->get( ServicesChecker::class ),
			$this->mailpoet_container->get( Functions::class ),
			$this->mailpoet_container->get( LoggerFactory::class ),
			$this->get_newsletter_repo(),
			$this->mailpoet_container->get( SendingQueuesRepository::class )
		);
	}

	private function get_subscriber(): ?SubscriberEntity {
		/** @var SubscribersRepository */
		$repo = $this->mailpoet_container->get( SubscribersRepository::class );

		/** @var ?SubscriberEntity */
		$subscriber = $repo->findOneBy( [ 'email' => $this->email ] );
		return $subscriber;
	}

	private function render_newsletter( NewsletterEntity $newsletter, bool $text = false ): string {
		$renderer = $this->get_renderer();

		$type = $text ? 'text' : 'html';
		$body = $renderer->render( $newsletter, null, $type );

		/** @var Shortcodes */
		$shortcodes = $this->mailpoet_container->get( Shortcodes::class );
		$shortcodes->setNewsletter( $newsletter );
		$shortcodes->setSubscriber( $this->get_subscriber() );

		$body = $shortcodes->replace( $body );

		return $body;
	}

	/**
	 * @inheritDoc
	 */
	public static function init( Emailer $emailer ): void {
		$segments = [];
		if ( class_exists( '\\MailPoet\\DI\\ContainerWrapper' ) ) {
			$segment_entities = \MailPoet\DI\ContainerWrapper::getInstance()
				->get( SegmentsRepository::class )
				->findBy(
					[
						'type' => SegmentEntity::TYPE_DEFAULT,
						'deletedAt' => null,
					]
				);
			foreach ( $segment_entities as $segment ) {
				$segments[ $segment->getId() ] = $segment->getName();
			}
		}
		$segment_option = ( new Option_Select( 'app_list', 'Approved member optional list' ) )
			->with_options( $segments );
		$emailer->admin->add_setting( $segment_option );
	}

	/**
	 * @inheritDoc
	 */
	public static function template_option( string $key, string $name ): Option {
		$newsletters = [];
		if ( class_exists( '\\MailPoet\\DI\\ContainerWrapper' ) ) {
			$templates = \MailPoet\DI\ContainerWrapper::getInstance()
				->get( NewslettersRepository::class )
				->findDraftByTypes( [ NewsletterEntity::TYPE_STANDARD ] );
			foreach ( $templates as $newsletter ) {
				$newsletters[ $newsletter->getId() ] = $newsletter->getSubject();
			}
		}
		return ( new Option_Select( $key, $name ) )->with_options( $newsletters );
	}
}
