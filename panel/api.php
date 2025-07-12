<?php

/**
 * PawTunes Project - Open Source Radio Player
 *
 * @author       Jacky (Jaka Prasnikar)
 * @email        jacky@prahec.com
 * @website      https://prahec.com
 * @repository   https://github.com/Jackysi/pawtunes
 * This file is part of the PawTunes open-source project.
 * Contributions and feedback are welcome! Visit the repository or website for more details.
 */

/**
 * Hint IDE already defined variables from parent (this file is included)
 * Returns default image if not found
 *
 * @var lib\PawTunes $pawtunes
 */
try {

    if ( ! isset($panel)) {
        header("Location: index.php?page=home");
        exit;
    }

    // Set headers
    header('X-Accel-Buffering: no');
    header('Content-Encoding: none');
    header('Cache-Control: no-cache');
    set_time_limit(0);

    // Avoid session locking
    session_write_close();

    // Stop previous buffers
    if (ob_get_status()) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    $routes = [
        'artwork-lookup'    => 'API\Artwork::artworkLookup',
        'import-artwork'    => 'API\Artwork::importArtwork',
        'get-themes-list'   => 'API\Themes::getThemes',
        'delete-theme'      => 'API\Themes::deleteTheme',
        'get-artwork'       => 'API\Artwork::getArtwork',
        'delete-artwork'    => 'API\Artwork::deleteArtwork',
        'delete-log'        => 'API\Debug::deleteLog',
        'get-log'           => 'API\Debug::readLog',
        'get-settings'      => 'API\Settings::getSettings',
        'update-settings'   => 'API\Settings::updateSettings',
        'get-api-status'    => 'API\Api::getApiStatus',
        'check-warnings'    => 'API\CheckRequirements::__invoke',
        'debug'             => 'API\Debug::__invoke',
        'update'            => 'API\Updates::__invoke',
        'update-check'      => 'API\Updates::checkForUpdates',
        'update-history'    => 'API\Updates::getHistory',
        'update-postscript' => 'API\Updates::manualPostUpdate',
    ];

    // Get the action from $_GET
    $action = $_GET['action'] ?? null;
    if ($action && isset($routes[$action])) {

        // Extract class and method from the route
        [$className, $methodName] = explode('::', $routes[$action]);

        // Instantiate the class
        $instance = new $className($pawtunes, $panel);

        // Call the method with parameters
        call_user_func_array([$instance, $methodName], []);
        exit;

    }

    http_response_code(404);
    die("404 - Not Found");

} catch (Throwable|Exception $e) {

    if (isset($instance) && is_callable([$instance, 'handleError'])) {
        $instance->handleError($e);
    }

    $pawtunes->writeLog('panel_errors', $e->getMessage());
    $pawtunes->writeLog('panel_errors', $e->getTraceAsString());

    http_response_code(500);
    die("500 - Internal Server Error");

}