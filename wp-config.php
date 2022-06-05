<?php declare( strict_types=1 );
// phpcs:disable NeutronStandard.Constants.DisallowDefine.Define

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', 'password' );

/** MySQL hostname */
define( 'DB_HOST', 'database' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '34[>*FMTdM+[$l(Q+t>2O q]*`6y/d-KdT^*-zo7q+f><NlbyCuEq){8gQqb_;;G' );
define( 'SECURE_AUTH_KEY',  ' fw?9[j!.1NPZ#tFlFM1+sgdmLMSM{y=fj?MWkEzvwf~7-P}]dsG&GIwY89]4xV(' );
define( 'LOGGED_IN_KEY',    'D%6t[A-{E/oxv|da?To1(}V8:h-y0=e9hh4jHEg|+#N]@xP}%|&3Z&`bN/_B_#B&' );
define( 'NONCE_KEY',        ':(ODd2,goEQ&LswN(GvK4C#W_!G{h9Y~OGbJqyK6n;+Rkbl&+gV&$1(1g>aU+SX>' );
define( 'AUTH_SALT',        ' `-t3RJ M OpQ%ql&P|/|3rr68ZWurZ/~^:v6(rU:`/#1fl{XRiX$L&+9,2+Q$pU' );
define( 'SECURE_AUTH_SALT', '/?X5xxvS|NN)|a`CcbAb}F.Up.DkK|R0t$?xFGe|v*C+@H>S*Z;Q!O.!PHz|6DvP' );
define( 'LOGGED_IN_SALT',   'kGRQ|byZ@+@Hk$E=|_NCwH}`?2x5rTO(yv-Y)*!t/SRW%+ag]bEHk~iqds3AYo;l' );
define( 'NONCE_SALT',       'cky<EHCe.)E|O-L`oNE>@LL]|j!||F0 3vex|gZ(;T&3Dav`U3W%+lL-Y r#`ua@' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * Limits total Post Revisions saved per Post/Page.
 * Change or comment this line out if you would like to increase or remove the limit.
 */
define( 'WP_POST_REVISIONS',  10 );

/**
 * WordPress Localized Language, defaults to English.
 *
 * Change this to localize WordPress. A corresponding MO file for the chosen
 * language must be installed to wp-content/languages. For example, install
 * de_DE.mo to wp-content/languages and set WPLANG to 'de_DE' to enable German
 * language support.
 */
define( 'WPLANG', '' );

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define( 'WP_DEBUG_LOG', true );

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined( 'ABSPATH' ) )
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );

/** Sets up WordPress vars and included files. */
require_once( ABSPATH . 'wp-settings.php' );
