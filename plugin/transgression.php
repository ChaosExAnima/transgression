<?php declare( strict_types=1 );

namespace Transgression;

const PLUGIN_ROOT = __DIR__;
const PLUGIN_VERSION = '0.1.0';
const PLUGIN_SLUG = 'transgression';

require_once './admin/index.php';
require_once './modules/index.php';

require_once './class-logger.php';
require_once './class-main.php';

$application = new Main();

add_action( 'init', [ $application, 'init' ] );
