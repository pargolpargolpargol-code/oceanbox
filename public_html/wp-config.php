<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('WP_CACHE', true);
define( 'WPCACHEHOME', '/home/oceanboxir/public_html/wp-content/plugins/wp-super-cache/' );
define( 'DB_NAME', 'oceanboxir_wp_qaivi' );

/** Database username */
define( 'DB_USER', 'oceanboxir_wp_xeszj' );

/** Database password */
define( 'DB_PASSWORD', 'gz@72r9L@I^o?Ro0' );

/** Database hostname */
define( 'DB_HOST', 'localhost:3306' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY', 'YjK4ZkL+6gZ7@5:x0[|87N24[m0PtX%)#MAj59AcMh3dT0K~G3c7/_9x5~!;|*&3');
define('SECURE_AUTH_KEY', 'Cnh2*6k4JK%7)aJd5p827&Re7XJAux6RO67wQ[hn+#p(O5c~FW06L51iv5dlK81a');
define('LOGGED_IN_KEY', 'Gw@xc3@Z796Y5uLRDu#q1+5lfuG7nS[-YDL|Stv9P2%7)W#0+Nk9%LCo8Z:&Ah%9');
define('NONCE_KEY', '3CJ41E0I[[VLW40%12QL]W_3x!U/#sC83lH180&/*wm-aa2H|DO7):1@Uf79JZ@w');
define('AUTH_SALT', '9n99Ox42ncU:1KdI;[FpuYEu;U4G1:V!4Qss[)16niw0BmTk/Q-[mv7[pEJ3w7LJ');
define('SECURE_AUTH_SALT', '_(RnZ]2*bP+:T877gk7[flI14P@Q&%by:K83126idG828--sf0I60Yz*hEu*nAFz');
define('LOGGED_IN_SALT', '3j9evLms(dse-9H-(~&AZ(b721Z4#ZlX*#3Q)+(-4|Y-q2i3w8#RSd-9B[48RgC*');
define('NONCE_SALT', 'I9J-CJ2&zr]]__kix4!S!*m6*~UxCx2zE2cE#aH1xxdgR*2D)u|59*1J5K*m1uoI');


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = '0ZyaMv3j_';


/* Add any custom values between this line and the "stop editing" line. */

define('WP_ALLOW_MULTISITE', true);
/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
