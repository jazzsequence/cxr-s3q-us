<?php
/**
 * Your base production configuration goes in this file.
 *
 * A good default policy is to deviate from the production config as little as
 * possible. Try to define as much of your configuration in this file as you
 * can.
 */

use Roots\WPConfig\Config;
use function Env\env;

// USE_ENV_ARRAY + CONVERT_* + STRIP_QUOTES.
Env\Env::$options = 31;

/**
 * Directory containing all of the site's files
 *
 * @var string
 */
$root_dir = dirname( __DIR__ );

/**
 * Document Root
 *
 * @var string
 */
$webroot_dir = $root_dir . '/web';

/**
 * Use Dotenv to set required environment variables and load .env file in root
 * .env.local will override .env if it exists
 */
$env_files = file_exists( $root_dir . '/.env.local' )
	? [ '.env', '.env.pantheon', '.env.local' ]
	: [ '.env', '.env.pantheon' ];

$dotenv = Dotenv\Dotenv::createImmutable( $root_dir, $env_files, false );
if (
	// Check if a .env file exists.
	file_exists( $root_dir . '/.env' ) ||
	// Also check if we're using Lando and a .env.local file exists.
	( file_exists( $root_dir . '/.env.local' ) && isset( $_ENV['LANDO'] ) && 'ON' === $_ENV['LANDO'] )
) {
	$dotenv->load();
	if ( ! env( 'DATABASE_URL' ) ) {
		$dotenv->required( [ 'DB_NAME', 'DB_USER', 'DB_PASSWORD' ] );
	}
}

/**
 * Include Pantheon application settings.
 */
require_once __DIR__ . '/application.pantheon.php';

/**
 * Set up our global environment constant and load its config first
 * Default: production
 */
define( 'WP_ENV', env( 'WP_ENV' ) ?: 'production' );

/**
 * DB settings
 */
Config::define( 'DB_NAME', env( 'DB_NAME' ) );
Config::define( 'DB_USER', env( 'DB_USER' ) );
Config::define( 'DB_PASSWORD', env( 'DB_PASSWORD' ) );
Config::define( 'DB_HOST', env( 'DB_HOST' ) ?: 'localhost' );
Config::define( 'DB_CHARSET', 'utf8mb4' );
Config::define( 'DB_COLLATE', '' );
$table_prefix = env( 'DB_PREFIX' ) ?: 'wp_';

if ( env( 'DATABASE_URL' ) ) {
	$dsn = (object) parse_url( env( 'DATABASE_URL' ) );

	Config::define( 'DB_NAME', substr( $dsn->path, 1 ) );
	Config::define( 'DB_USER', $dsn->user );
	Config::define( 'DB_PASSWORD', isset( $dsn->pass ) ? $dsn->pass : null );
	Config::define( 'DB_HOST', isset( $dsn->port ) ? "{$dsn->host}:{$dsn->port}" : $dsn->host );
}

/**
 * Pantheon modifications
 */
if ( isset( $_ENV['PANTHEON_ENVIRONMENT'] ) && ! isset( $_ENV['LANDO'] ) ) {
	Config::define( 'DB_HOST', $_ENV['DB_HOST'] . ':' . $_ENV['DB_PORT'] );
} else {
	/**
	 * URLs
	 */
	Config::define( 'WP_HOME', env( 'WP_HOME' ) );
	Config::define( 'WP_SITEURL', env( 'WP_SITEURL' ) );
	Config::define( 'DB_HOST', env( 'DB_HOST' ) ?: 'localhost' );
}

/**
 * Custom Content Directory
 */
Config::define( 'CONTENT_DIR', '/app' );
Config::define( 'WP_CONTENT_DIR', $webroot_dir . Config::get( 'CONTENT_DIR' ) );
Config::define( 'WP_CONTENT_URL', Config::get( 'WP_HOME' ) . Config::get( 'CONTENT_DIR' ) );

/**
 * Authentication Unique Keys and Salts
 */
Config::define( 'AUTH_KEY', env( 'AUTH_KEY' ) );
Config::define( 'SECURE_AUTH_KEY', env( 'SECURE_AUTH_KEY' ) );
Config::define( 'LOGGED_IN_KEY', env( 'LOGGED_IN_KEY' ) );
Config::define( 'NONCE_KEY', env( 'NONCE_KEY' ) );
Config::define( 'AUTH_SALT', env( 'AUTH_SALT' ) );
Config::define( 'SECURE_AUTH_SALT', env( 'SECURE_AUTH_SALT' ) );
Config::define( 'LOGGED_IN_SALT', env( 'LOGGED_IN_SALT' ) );
Config::define( 'NONCE_SALT', env( 'NONCE_SALT' ) );

/**
 * Custom Settings
 */
Config::define( 'AUTOMATIC_UPDATER_DISABLED', true );
// Disable the plugin and theme file editor in the admin.
Config::define( 'DISALLOW_FILE_EDIT', true );
// Disable plugin and theme updates and installation from the admin.
Config::define( 'DISALLOW_FILE_MODS', true );
// Limit the number of post revisions that Wordpress stores (true (default WP): store every revision).
Config::define( 'WP_POST_REVISIONS', env( 'WP_POST_REVISIONS' ) ?? true );

/**
 * Debugging Settings
 */
switch ( $_ENV['PANTHEON_ENVIRONMENT'] ?? 'local' ) {
    case 'dev':
        Config::define('WP_DEBUG', true);
        Config::define('WP_DEBUG_LOG', true);
        Config::define('WP_DEBUG_DISPLAY', false); // Keep this at false until test/live environments work.
        break;
    case 'test':
        Config::define('WP_DEBUG', true);
        Config::define('WP_DEBUG_LOG', true);
        Config::define('WP_DEBUG_DISPLAY', false);
        break;
    case 'live':
        Config::define('WP_DEBUG', false);
        Config::define('WP_DEBUG_LOG', false);
        Config::define('WP_DEBUG_DISPLAY', false);
        break;
    default: // local or unset
        Config::define('WP_DEBUG', true);
        Config::define('WP_DEBUG_LOG', true);
        Config::define('WP_DEBUG_DISPLAY', true);
        break;
}

// Get OCP token.
$ocp_token = ( function_exists( 'pantheon_get_secret' ) ) ? pantheon_get_secret( 'ocp_token' ) : null;

/**
 * Object Cache Pro config
 */
$ocp_config = [
	'token' => $ocp_token,
	'host' => getenv('CACHE_HOST') ?: '127.0.0.1',
	'port' => getenv('CACHE_PORT') ?: 6379,
	'database' => getenv('CACHE_DB') ?: 0,
	'password' => getenv('CACHE_PASSWORD') ?: null,
	'maxttl' => 86400 * 7,
	'timeout' => 0.5,
	'read_timeout' => 0.5,
	'split_alloptions' => true,
	'prefetch' => true,
	'debug' => false,
	'save_commands' => false,
	'analytics' => [
		'enabled' => true,
		'persist' => true,
		'retention' => 3600, // 1 hour
		'footnote' => true,
	],
	'prefix' => "ocppantheon", // This prefix can be changed. Setting a prefix helps avoid conflict when switching from other plugins like wp-redis.
	'serializer' => 'igbinary',
	'compression' => 'zstd',
	'async_flush' => true,
	'strict' => true,	
];

if ( isset( $_ENV['LANDO'] ) && $_ENV['LANDO'] === 'ON' ) {
	$ocp_config['serializer'] = 'php';
	$ocp_config['compression'] = 'none';
}

Config::define( 'WP_REDIS_CONFIG', $ocp_config );

Config::define( 'APPLICATION_PASSWORDS_DISABLE_CONFLICT_CHECK', true );

/**
 * Allow WordPress to detect HTTPS when used behind a reverse proxy or a load balancer
 * See https://codex.wordpress.org/Function_Reference/is_ssl#Notes
 */
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
	$_SERVER['HTTPS'] = 'on';
}

$env_config = __DIR__ . '/environments/' . WP_ENV . '.php';

if ( file_exists( $env_config ) ) {
	require_once $env_config;
}

Config::apply();

/**
 * Bootstrap WordPress
 */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $webroot_dir . '/wp/' );
}
