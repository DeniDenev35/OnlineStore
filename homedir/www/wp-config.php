<?php
/** Enable W3 Total Cache */
//Begin Really Simple SSL session cookie settings
@ini_set('session.cookie_httponly', true);
@ini_set('session.cookie_secure', true);
@ini_set('session.use_only_cookies', true);
//END Really Simple SSL
if(!defined('DISALLOW_FILE_EDIT')){define('DISALLOW_FILE_EDIT', true);}
?><?php
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
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */
// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'vseobsht_8Xk');
/** MySQL database username */
define('DB_USER', 'vseobsht_8Xk');
/** MySQL database password */
define('DB_PASSWORD', 'R2OBz*jh-FkY');
/** MySQL hostname */
define('DB_HOST', 'localhost');
/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');
/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');
/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY', 'ztOPysx?`hJ`0u%9v?rEtI8,@PMS-!.k|HBz*4SUPZC$nq8eY!@;xA]=j]Dg7LN`');
define('SECURE_AUTH_KEY', 'Uc$KlPh0Wx[Fv>iotYg$~l$f3oy_MUkVz)?||g&Pt0G^#vZfzo_|JhBdOcE*bjc+');
define('LOGGED_IN_KEY', 'a;>MS89*]:UBK$MXR,qxFOK<a5:2Aq[r$TLU.lkV.h:+@JcK@e8k,6cbZQALxS5j');
define('NONCE_KEY', 'EUKh|H5i*0h^DFNo H2Jb~e$Fq!&dqG~r94cpOrwSk6Yz,GX( 6mpzzrq+7^wsgm');
define('AUTH_SALT', '<fB`lR k(3oupoMOQE*jkw2nZ38nW_<I!+B93^@g izx(H4>j!IBR^iAoJ$8:J=A');
define('SECURE_AUTH_SALT', '%fc:U4w3WQLIyq4].>D`9xYw2&Jy%S!<u,8U2M2WR;0/NoD0pLo_+Bg#81r6CH+]');
define('LOGGED_IN_SALT', '0=oD;BQL$aO <27Oq@%oG*Z.szjC7]nTOzJa/vh+c.~vHjco F*9Le5I4lKkb+~<');
define('NONCE_SALT', '%z)I[$4NwkderiD@:+tUGCWSp;L|=0(wA0r7TB7]wd*LZxJiwL,kYtO?OlE=R.uL');
/**#@-*/
/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = '43I_';
/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);
/* That's all, stop editing! Happy blogging. */
/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');
/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
