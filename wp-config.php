<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'iqwinbot_sql' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', '' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         '{ol/L/o^}T}R:gQ(ZGMk};8}^uV4+BJ|_}8i@;5@G/;/hX(r?jwWI0*&WzOe%N`h' );
define( 'SECURE_AUTH_KEY',  'E_Wg,j=l*ta8[IR2rs%>E2~:t^H-H8VosBYm5`Af/bym5yjf]/7<5@?z|^5ZIP7x' );
define( 'LOGGED_IN_KEY',    'e)STa8ew 4Gv}jCTa&7R_&Il/7YJ~X*Z5*SOdF*|i=`eICoP^gF482tpaW|u.MNM' );
define( 'NONCE_KEY',        '&AT`7|RE>|wBaUc0H6)}m]M@Om$YO<.o::m=^=[_o,m!Oa^*^:$u7.8T?VwTb{Q6' );
define( 'AUTH_SALT',        'M;ZBNb={-qzg#:<iJb:24zG~)#x:xeiRe:gGke-u_sSx&`$YN9dpYk>:x )[JgPL' );
define( 'SECURE_AUTH_SALT', 'E!9MS|UAj`:?oZ((kx3m4gd*k:BzIp]|BrI[D^H8;c5=s]QF-J<mjA:Lv5chN|_r' );
define( 'LOGGED_IN_SALT',   '5lDYp!zVvvi.H[ML7!k[[aJ!2#a;)*$/2u60L5W%!]aMW}SFHN:JKGnhE&_yaIT`' );
define( 'NONCE_SALT',       'lbNkTFQ=]yOUs~>f8%UJ*cuT63h,VISzK9 Qd3>>q/EDvcn0/ N@8VroE7FNvmEg' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
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
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
