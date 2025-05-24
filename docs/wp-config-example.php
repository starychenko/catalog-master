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
define( 'DB_NAME', 'your_database_name' );

/** Database username */
define( 'DB_USER', 'your_database_username' );

/** Database password */
define( 'DB_PASSWORD', 'your_database_password' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

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
define( 'AUTH_KEY',          'put your unique phrase here' );
define( 'SECURE_AUTH_KEY',   'put your unique phrase here' );
define( 'LOGGED_IN_KEY',     'put your unique phrase here' );
define( 'NONCE_KEY',         'put your unique phrase here' );
define( 'AUTH_SALT',         'put your unique phrase here' );
define( 'SECURE_AUTH_SALT',  'put your unique phrase here' );
define( 'LOGGED_IN_SALT',    'put your unique phrase here' );
define( 'NONCE_SALT',        'put your unique phrase here' );
define( 'WP_CACHE_KEY_SALT', 'put your unique phrase here' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/* Add any custom values between this line and the "stop editing" line. */

// ============================================
// 🔧 НАЛАШТУВАННЯ ДЛЯ CATALOG MASTER ПЛАГІНА
// ============================================

// PHP налаштування для оптимальної роботи
ini_set('allow_url_fopen', '1');          // Для Google Sheets API
ini_set('file_uploads', '1');             // Для завантаження зображень
ini_set('log_errors', '1');               // Для логування помилок
ini_set('max_execution_time', '300');     // 5 хвилин для великих імпортів
ini_set('memory_limit', '512M');          // Достатньо пам'яті для обробки
ini_set('post_max_size', '64M');          // Розмір POST запитів
ini_set('upload_max_filesize', '32M');    // Розмір завантажених файлів
ini_set('max_file_uploads', '100');       // Кількість файлів

// WordPress налаштування для дебагу
define('WP_DEBUG', true);                 // Увімкнути дебаг (для розробки)
define('WP_DEBUG_LOG', true);             // Зберігати логи в файл
define('WP_DEBUG_DISPLAY', false);        // НЕ показувати на сайті
define('SAVEQUERIES', true);              // Логувати SQL запити

// Додаткові налаштування безпеки (опціонально)
define('DISALLOW_FILE_EDIT', true);       // Заборонити редагування файлів через адмін
define('AUTOMATIC_UPDATER_DISABLED', false); // Дозволити автооновлення

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
// WP_DEBUG вже налаштовано вище для Catalog Master

define( 'FS_METHOD', 'direct' );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php'; 