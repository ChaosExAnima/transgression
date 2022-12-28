<?php declare( strict_types=1 );

namespace Transgression\Modules\Email;

use Error;
use MailPoet\Config\ServicesChecker;

use MailPoet\Entities\{NewsletterEntity, SegmentEntity, SubscriberEntity};
use MailPoet\Newsletter\{NewslettersRepository, Renderer\Preprocessor};
use MailPoet\Newsletter\Renderer\{Renderer, Blocks\Renderer as BlocksRenderer, Columns\Renderer as ColumnsRenderer};
use MailPoet\Newsletter\Shortcodes\Shortcodes;
use MailPoet\Segments\SegmentsRepository;
use MailPoet\Subscribers\SubscribersRepository;
use MailPoetVendor\CSS;
use Transgression\Admin\Option;
use Transgression\Admin\Option_Select;

class MailPoet extends Email {
	/** @var ?\MailPoet\DI\ContainerWrapper; */
	private $mailpoet_container = null;

	public function __construct() {
		if ( !class_exists( '\\MailPoet\\DI\\ContainerWrapper' ) ) {
			throw new Error( 'MailPoet is not loaded' );
		}
		$this->mailpoet_container = \MailPoet\DI\ContainerWrapper::getInstance();
	}

	public function send() {
		$template_id = absint( get_option( $this->template, 0 ) );
		if ( !$template_id ) {
			throw new Error( 'Template not set' );
		}

		$is_html = true;
		$user = get_user_by( 'email', $this->email );
		if ( $user ) {
			$is_html = $user->email_preference !== 'text';
		}

		$newsletter = $this->get_newsletter( $template_id );
		$subject = $newsletter->getSubject();
		$body = $this->render_newsletter( $newsletter, !$is_html );

		$headers = [];
		if ( $is_html ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}

		wp_mail(
			$this->email,
			$subject,
			$this->process_body( $body ),
			$headers
		);
	}

	public function template_option( string $key, string $name ): Option {
		$newsletters = [];
		foreach ( $this->get_newsletter_templates() as $newsletter ) {
			$newsletters[ $newsletter->getId() ] = $newsletter->getSubject();
		}
		return ( new Option_Select( $key, $name ) )->with_options( $newsletters );
	}

	protected function load_template(): int {
		if ( !$this->template ) {
			throw new Error( 'MailPoet requires a template' );
		}
		$template_id = absint( get_option( $this->template, 0 ) );
		if ( !$template_id ) {
			throw new Error( 'Template not set' );
		}
		return $template_id;
	}

	private function get_newsletter_repo(): NewslettersRepository {
		return $this->mailpoet_container->get( NewslettersRepository::class );
	}

	private function get_newsletter_templates(): array {
		return $this->get_newsletter_repo()->findDraftByTypes( [NewsletterEntity::TYPE_STANDARD] );
	}

	private function get_newsletter( int $id ): ?NewsletterEntity {
		return $this->get_newsletter_repo()->findOneById( $id );
	}

	/**
	 * @return SegmentEntity[]
	 */
	private function get_segments(): array {
		/** @var SegmentsRepository */
		$segments_repo = $this->mailpoet_container->get( SegmentsRepository::class );
		return $segments_repo->findBy( ['type' => SegmentEntity::TYPE_DEFAULT, 'deletedAt' => null] );
	}

	private function get_renderer(): ?Renderer {
		return new Renderer(
			$this->mailpoet_container->get( BlocksRenderer::class ),
			$this->mailpoet_container->get( ColumnsRenderer::class ),
			$this->mailpoet_container->get( Preprocessor::class ),
			$this->mailpoet_container->get( CSS::class ),
			$this->mailpoet_container->get( ServicesChecker::class ),
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
}
