<?php declare( strict_types=1 );

namespace Transgression;

use MailPoet\DI\ContainerWrapper;
use MailPoet\Newsletter\NewslettersRepository;

function get_newsletters() {
	$repo = get_mailpoet_instance();
	$all = $repo->findAll();
	var_dump( $all );
}

function get_mailpoet_instance(): NewslettersRepository {
	return ContainerWrapper::getInstance()->get( NewslettersRepository::class );
}
