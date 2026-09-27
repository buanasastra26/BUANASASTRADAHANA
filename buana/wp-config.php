<?php
define( 'WP_CACHE', true );


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
define( 'DB_NAME', 'u282736333_cvbuana' );

/** Database username */
define( 'DB_USER', 'u282736333_cvbuana' );

/** Database password */
define( 'DB_PASSWORD', 'Tedi1996_' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

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
define( 'AUTH_KEY',          'ts 0)Cl!L,`S4EeUkt5q>Hg2Vk2Vr56~huYa38)WlP+*|J1mn[dolw$L=.4ge9)k' );
define( 'SECURE_AUTH_KEY',   'KZj~FD^5P!ZmrK4JId@2#TD`mH~($R };tRF+P/laizh<5?[9xd}R~tH>ibaI$v>' );
define( 'LOGGED_IN_KEY',     'dVEz1yr3QedIzQIK(G]{!E?KU[gz+5nD>{WCVd4P OKucl]NY+!v ^XGEdBDZ&rR' );
define( 'NONCE_KEY',         '>UQ-pYHoN74PB(?.ech<#!.f]vX`X=(>tezAaLHE=r:C3|E&$A=wuPpC5tl}u;*L' );
define( 'AUTH_SALT',         'R7=il4O0o#t.L+t:#(oh/|@jtQl;Sx>70!^l((e|pFcMH` 7<hO$d!(@=8y++5tC' );
define( 'SECURE_AUTH_SALT',  'UDy/[35mjUs_2D~JzyAjvk0]1/,C-CNsPKeWYNmoyD/rz!rO;-sD,pd8ua{}zO?+' );
define( 'LOGGED_IN_SALT',    'R4h.y~7E! UDIR+^-m+zS~{^14|:AQ-W?mKKKG1)[O1|wp@Of(<B5@ uS=@98TUj' );
define( 'NONCE_SALT',        'Bd^h$c30i+KSaO&C)s<Gc#z8Rq?4 `VqfGR}~rvgZX^<!Mm47AUJL*dN}(j<?ls@' );
define( 'WP_CACHE_KEY_SALT', '_#tK%oVO*=GSUQT|B0*N :I-4M?^M%I!*mW`13pR,j5S_?J75t2*B,zXKo_v?ZMx' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



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

define( 'FS_METHOD', 'direct' );
define( 'COOKIEHASH', 'ab3b2dbb7220b207b45d8c0e99c3a007' );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
