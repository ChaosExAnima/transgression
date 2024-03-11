<?php declare( strict_types = 1 );

namespace Transgression\Modules\Email;

// Email types
require_once __DIR__ . '/abstract-email.php';
require_once __DIR__ . '/class-elasticemail.php';
require_once __DIR__ . '/class-mailpoet.php';
require_once __DIR__ . '/class-wp-mail.php';

// Email factory
require_once __DIR__ . '/class-emailer.php';
