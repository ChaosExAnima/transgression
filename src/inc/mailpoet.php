<?php declare( strict_types=1 );

namespace Transgression;

use MailPoet\DI\ContainerWrapper;
use MailPoet\Entities\NewsletterEntity;
use MailPoet\Newsletter\NewslettersRepository;

function get_newsletters() {
	$repo = get_mailpoet_instance();
	return $repo->findDraftByTypes( [NewsletterEntity::TYPE_STANDARD] );
}

function get_mailpoet_instance(): NewslettersRepository {
	return ContainerWrapper::getInstance()->get( NewslettersRepository::class );
}
