<?php
/**
 * Uninstall Review Captain (RC-048).
 *
 * Data is kept unless "Beim Löschen des Plugins alle Daten entfernen" (privacy.delete_data_on_uninstall)
 * is enabled and confirmed with the typed word (privacy.delete_data_confirmed_at, SET-RC-20).
 * Runs without the plugin bootstrap: only the autoloader is loaded.
 *
 * @package ReviewCaptain
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	return; // The plugin never ran on this PHP version, so it stored no data.
}

require_once __DIR__ . '/src/Autoloader.php';
\ReviewCaptain\Autoloader::register( __DIR__ . '/src/' );

\ReviewCaptain\Privacy\Uninstaller::run();
