<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'satakamolmao' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         'bh[Ccc%U2}}T!2`K*6qX`0I3L8d*gH%lH<uQ;IeG+~EC[`[D`-k{CbeoD:?{Kl~S' );
define( 'SECURE_AUTH_KEY',  '2U)(TvH6vsNPt-n^r}v8o{+/[~*n3PCDphb*kW*,~7xftN+Q#`62M?Gp{)4x+&{H' );
define( 'LOGGED_IN_KEY',    '9+k/LT,On~%#O[ktX&/]jyz[?~&U[;tQ|tT0+Qa-5xb|vr7)vd{;ma etNhS_>=c' );
define( 'NONCE_KEY',        'DbrG WG]Xe.z0C-jK?%;xL]5T]o*_/G2O0:`L|HQ/5x8=]BGoB-9BiFtqfipk%.M' );
define( 'AUTH_SALT',        '8;M6p>0vbV:8`p`;HCAjV(V^hFHMndA7P.%$neJ{ckR.Allc_.h_ W%7AsTFcpS7' );
define( 'SECURE_AUTH_SALT', 'DZO!V_)&1(>Ne;XnTR.KCs/VF>zNRx-/s/7j[g=PSJS%42frU$!!hAJ6*s30P9S.' );
define( 'LOGGED_IN_SALT',   '3]O*D AKQ&C@2eUS7HSCrQdYJ}bFVBl$>Ye^{,CUkJt/?#qR oLP*P<1or<%d4v2' );
define( 'NONCE_SALT',       '*}86+I5zp5dGjIn.z5S<$6X}1:76Us~G;R)7W-obRHOY2v2dyAPRLoxYO r[JY2e' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

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
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
