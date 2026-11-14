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

namespace Pages;

use lib\PawTunes;
use Panel;
use Services\Request;
use Throwable;

/**
 * Panel API ("index.php?page=api&action=<name>"): the JSON and event stream (SSE) endpoints the panel's
 * JavaScript calls. Each action is a method of a class in panel/lib/API. Unlike other pages the action
 * name comes from the query string and may be a GET, so it is looked up in the ROUTES list below instead
 * of going through post<Action>() methods.
 */
final class Api extends Page
{

    /**
     * action => [API class, method]
     */
    private const ROUTES
        = [
            'artwork-lookup'    => [\API\Artwork::class, 'artworkLookup'],
            'import-artwork'    => [\API\Artwork::class, 'importArtwork'],
            'get-artwork'       => [\API\Artwork::class, 'getArtwork'],
            'delete-artwork'    => [\API\Artwork::class, 'deleteArtwork'],
            'get-themes-list'   => [\API\Themes::class, 'getThemes'],
            'delete-theme'      => [\API\Themes::class, 'deleteTheme'],
            'get-log'           => [\API\Debug::class, 'readLog'],
            'delete-log'        => [\API\Debug::class, 'deleteLog'],
            'debug'             => [\API\Debug::class, '__invoke'],
            'check-warnings'    => [\API\CheckRequirements::class, '__invoke'],
            'update'            => [\API\Updates::class, '__invoke'],
            'update-check'      => [\API\Updates::class, 'checkForUpdates'],
            'update-history'    => [\API\Updates::class, 'getHistory'],
            'update-postscript' => [\API\Updates::class, 'manualPostUpdate'],
        ];

    /**
     * Actions that delete something. They must be POSTed, so panel/index.php has verified their CSRF token.
     */
    private const POST_ONLY = ['delete-artwork', 'delete-log', 'delete-theme'];

    /**
     * Streaming actions. EventSource can only GET, so they carry the CSRF token as a "_token" query parameter.
     */
    private const TOKEN_IN_QUERY = ['import-artwork', 'update', 'update-postscript'];

    private PawTunes $pawtunes;

    private Panel $panel;

    private Request $request;


    public function __construct(PawTunes $pawtunes, Panel $panel, Request $request)
    {
        $this->pawtunes = $pawtunes;
        $this->panel    = $panel;
        $this->request  = $request;
    }


    protected function index(): void
    {
        $handler = null;

        try {

            $this->prepareOutput();

            $action = $this->request->query('action');
            if ( ! isset(self::ROUTES[$action])) {
                http_response_code(404);
                die('404 - Not Found');
            }

            if (in_array($action, self::POST_ONLY, true) && ! $this->request->isPost()) {
                http_response_code(405);
                header('Allow: POST');
                die('405 - Method Not Allowed');
            }

            if (in_array($action, self::TOKEN_IN_QUERY, true) && ! $this->panel->verifyCsrfQueryToken()) {
                http_response_code(403);
                die('Invalid or expired security token. Please reload the page and try again.');
            }

            [$class, $method] = self::ROUTES[$action];

            $handler = new $class($this->pawtunes, $this->panel);
            $handler->$method();
            exit;

        } catch (Throwable $e) {

            // API classes that stream may want to end their stream with an error event
            if ($handler !== null && is_callable([$handler, 'handleError'])) {
                $handler->handleError($e);
            }

            $this->pawtunes->writeLog('panel_errors', $e->getMessage());
            $this->pawtunes->writeLog('panel_errors', $e->getTraceAsString());

            http_response_code(500);
            die('500 - Internal Server Error');

        }

    }


    /**
     * Event streams must reach the browser line by line and may run for minutes (an update, an artwork
     * import): no buffering anywhere, no time limit, and the session is closed so its lock does not
     * block the admin's other requests meanwhile.
     */
    private function prepareOutput(): void
    {
        header('X-Accel-Buffering: no');
        header('Content-Encoding: none');
        header('Cache-Control: no-cache');
        set_time_limit(0);

        session_write_close();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

    }

}
