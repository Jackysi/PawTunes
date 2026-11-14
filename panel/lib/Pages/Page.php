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

use Services\Request;
use Throwable;

/**
 * Base of every control panel page. "index.php?page=ai" runs Pages\Ai:
 *
 *  - index() renders the page. It runs on every request, after the action (if any).
 *  - A POST with an "action" field first runs the matching post<Action>() method:
 *    action "save-text" -> postSaveText().
 *
 * The constructor of a page lists the services it needs by type, panel/index.php passes them in.
 * The CSRF token of every POST is verified in panel/index.php before a page is built.
 */
abstract class Page
{

    /**
     * Run the posted action, then render the page
     *
     * @param  Request  $request
     *
     * @return void
     * @throws Throwable
     */
    final public function dispatch(Request $request): void
    {
        $action = $request->post('action');
        $method = $this->actionMethod($action);

        if ($request->isPost() && $method !== null) {

            try {
                $this->$method();
            } catch (Throwable $e) {
                $this->actionFailed($action, $e);
            }

        }

        $this->index();

    }


    /**
     * Render the page
     *
     * @return void
     */
    abstract protected function index(): void;


    /**
     * Called when an action method throws. Pages that can show the error on the page override it,
     * everything else lets the error through to PHP's error handling.
     *
     * @param  string  $action  The posted action name
     * @param  Throwable  $e
     *
     * @return void
     * @throws Throwable
     */
    protected function actionFailed(string $action, Throwable $e): void
    {
        throw $e;
    }


    /**
     * Method name for a posted action. The fixed "post" prefix is the security boundary: the action name
     * comes from the browser, and with the prefix it can only ever reach methods written as actions,
     * never index(), dispatch() or a helper.
     *
     * @param  string  $action  e.g. "save-text"
     *
     * @return string|null  e.g. "postSaveText", null when the page has no such action
     */
    private function actionMethod(string $action): ?string
    {
        if ( ! preg_match('/^[a-z]+(-[a-z]+)*$/', $action)) {
            return null;
        }

        $method = 'post'.str_replace('-', '', ucwords($action, '-'));

        return method_exists($this, $method) ? $method : null;

    }

}
