<?php declare( strict_types=1 );
/**
 * Plugin Name: Transgression Ticketing System
 * Plugin URI: https://transgression.party
 * Description: Vetting system and event ticketing manager.
 * Version: 1.0.0
 * Requires PHP: 8.1
 */

namespace Transgression;

const PLUGIN_ROOT = __DIR__;
const PLUGIN_VERSION = '1.0.0';
const PLUGIN_SLUG = 'transgression';

require_once PLUGIN_ROOT . '/helpers.php';

require_once PLUGIN_ROOT . '/admin/index.php';
require_once PLUGIN_ROOT . '/modules/index.php';

require_once PLUGIN_ROOT . '/class-logger.php';
require_once PLUGIN_ROOT . '/class-main.php';

$transgression_application = new Main();

add_action( 'plugins_loaded', [ $transgression_application, 'init' ] );
