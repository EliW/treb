<?php
/**
 * Application
 *
 * Otherwise known as the Front Controller (plus some).  This is where the
 *  basic bootstrap functionality takes place, to keep the index.php to be
 *  extremely minimal. As well as any other number of 'basic' functionality
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
require_once 'creators.php';
class Application
{
    // Hold the main directory that all items are stored in:
    public $controller_path;
    public $controller_name;

    // Hold some private data that only we need to know about
    private $_controller;
    private $_mode;

    /**
     * __construct
     *
     * Constructor for the Application class, actually does the bootstrapping
     *
     * @author Eli White <eli@eliw.com>
     * @param string $mode Allows for a variance of the typical startup process to be defined
     * @access public
     **/
    function __construct($mode = FALSE)
    {
        $this->_mode = $mode;

        // Create a define for our root directory, it will come in handy later:
        define('ROOT', dirname(__FILE__) . '/..');

        // Handle the basic setup:
        $this->_startup();

        // Now bootstrap us, if we aren't a cron job:
        if ($mode != 'cron') {
            $this->_bootstrap();
        }

        // Finally if we had any 'shutdown' work to do, handle that now:
        $this->_shutdown();
    }

    /**
     * http
     *
     * Just a common place to put code for 'throwing an HTTP error code'
     *  This may need expanded upon in the future, but for now handles a
     *  number of codes, and even allows for some extra details to be
     *  provided, that are used based upon the type of code.
     *
     * Most of this is pretty 'raw' in how it's handled at the moment.  A few
     *  of the error codes that need more specific handling (301/302/401/etc) just
     *  do what they need to.  Others, well, they look for a $code.php file, and just
     *  render it.  Allowing you to do whatever you want for that error.  Some sample
     *  404/500 pages are provided.
     *
     * TODO:  Update this to PHP 5.4's http_header_code(), so more proper responses are generated
     *
     * @author Eli White <eli@eliw.com>
     * @param int $code Representation of the code to be thrown
     * @param string $extra Additional information, varies based upon code.
     * @return void
     * @access public
     **/
    public static function http($code, $extra = '')
    {
        // Nominally normalize code, worst case default to a good-ole-500
        $code = (int)$code;
        if ($code < 100 || $code >= 600) { $code = 500; }
        
        // Regardless of any custom effort later, set the error code now:
        http_response_code($code);

        // Now for specific codes, do some custom work w/ $extra
        switch ($code) {
            case 301:
            case 302:
                // Redirect, $extra is the Location path:
                $path = $extra ? strtr($extra, "\r\n", '  ') : '/';
                header("Location: {$path}");
                exit;
            case 401:
                // Authorization request, $extra is the realm:
                $realm = $extra ? strtr($extra, "\r\n\"", '   ') : 'Treb Framework';
                header("WWW-Authenticate: Basic realm=\"{$realm}\"");
                die('Unauthorized access');
            case 403:
                // 403, denied, just issue the text-error given, or a generic response.
                die($extra ?: 'Request Denied');
            default:
                // Set the header, attempt to include the appropriate file, then just die.
                // NOTE:  $extra will be available in the .php file
                include ROOT . "/errors/{$code}.php";
                die;
        }
    }

    /**
     * _startup
     *
     * Everything that needs to happen to actually start the application running.
     *  You know, basic stuff, like setting up paths, autoload, error handling…
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access private
     **/
    private function _startup()
    {
        // We are going to force 'UTC' timezone here. Yes it should be in the php.ini
        //  However, not every dev environment may have it set right, this ensures that:
        date_default_timezone_set('UTC');

        // We will be using autoloading for a few of our own things.
        //  But go ahead and add the 'libraries' directory into the include
        //  path for when we need to pull in 3rd party libraries:
        set_include_path(get_include_path() . PATH_SEPARATOR . ROOT . '/library');

        // Load configuration first before anything else:
        require_once(ROOT . '/framework/config.php');

        // Parse our configuration once now:
        $config = config();

        // Set error reporting.  No errors before this will probably have been reported,
        //  if if we are in a development environment now, show everything, else nothing.
        if ((int)$config->env->development) {
            ini_set('display_errors', 1);
        } else {
            ini_set('display_errors', 0);
        }

        // Either way, make sure that every error is created/logged.
        error_reporting(-1);

        // Create an autoloader for our core classes, the models and 'classes' directory:
        spl_autoload_register(function($class) {
            $class = strtolower($class);

            // All locations we want to autoload classes from:
            $locations = array(
                "/framework/{$class}.php",
                "/models/{$class}.model.php",
                "/classes/{$class}.php");

            // Look through each and attempt to load them if the file exists.
            foreach ($locations as $file) {
                if (file_exists(ROOT . $file)) {
                    require ROOT . $file;
                    return TRUE;
                }
            }

            return FALSE;
        });

        // Setup what's needed for a session.  Don't actually start one though.
        //   Lets scripts that need them, use them.

        // If the handler is 'files':
        if ((string)$config->session->handler == 'files') {
            ini_set('session.save_handler', 'files');
            ini_set('session.save_path', ROOT . '/tmp/session');
        } else {
            // Default to memcache handling if not otherwise specified:
            ini_set('session.save_handler', 'memcached');
            ini_set('session.save_path', cache()->sessionString());
        }
        ini_set('session.name', (string)$config->session->name ?: 'SESSID');

        // Setup a generic exception handler that will Log all uncaught exceptions:
        $mode = $this->_mode;
        set_exception_handler(function ($e) use ($mode) {
            // Log details of this exception:
            Log::write('exception', '|' . get_class($e) . '|' . $e->getMessage() . '|' .
                $e->getFile() . '|' . $e->getLine() . '|', Log::FATAL);

            // If we are on a dev server, then show the errors:
            $error = '';
            if ((int)config()->env->development || ($mode == 'cron')) {
                $error = "<p style='color:red'>Unhandled Exception: " . get_class($e) .
                    ', ' . $e->getMessage() . "\n<br />FILE: " . $e->getFile() .
                    "\n<br />LINE: " . $e->getLine() . "\n<br />TRACE:\n<br />" .
                    nl2br($e->getTraceAsString(), true) . "</p>\n";
            }

            // Handle the error situation
            if (!headers_sent()) {
                // Well good, we hadn't echo'd anything yet.  Change the response to a 500
                Application::http(500, $error);
            } elseif ($error) {
                // Else if headers were already sent, and yet we have an error to display
                //  Just go ahead and echo it, best we can do.
                echo $error;
            }

            // Now die, we shouldn't continue after an unhandled Exception.
            exit;
        });
    }

    /**
     * _shutdown
     *
     * Handles any final shutdown work that we need to do
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access private
     **/
    private function _shutdown()
    {
        // Actually, nothing right now.
    }

    /**
     * _bootstrap
     *
     * Handles the bootstrap section of the code.  Finds the controller/view that we
     *  are wanting to refer to, and pushes 'em through.
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access private
     **/
    private function _bootstrap()
    {
        // Now attempt to find the Controller
        $uri = empty($_SERVER['REDIRECT_URI']) ? $_SERVER['REQUEST_URI'] : $_SERVER['REDIRECT_URI'];
        $path = explode('/', parse_url($uri, PHP_URL_PATH));

        // If the final entry was blank (ended in a /) get rid of it:
        if ($path[count($path)-1] === '') {
            array_pop($path);
        }
        // The path always has a blank entry to start, get rid of it.
        array_shift($path);

        // Dive as deep into the directory structure as possible.
        // We are assuming that any non resource URL is redirected to us, and we are
        //  going to attempt to find the Controller that is therefore referred to,
        //  and launch it.
        $name = 'home';
        $prefix = ROOT . '/controllers';
        $dir = '';

        // Loop down as many directories as possible
        while (isset($path[0]) && file_exists($prefix . $dir . '/' . $path[0])) {
            $name = array_shift($path);
            $dir .= '/' . $name;
        }

        // So we have the Directory Name, let's see if we have the Application Name
        if (isset($path[0]) && file_exists($prefix . $dir . '/' . $path[0] . '.controller.php')) {
            // Oh, we do?   Then overwrite the 'name' and remove that from the path
            $name = array_shift($path);
        }

        // Now let's see if the controller exists, if not, it's 404 time:
        $controller = $prefix . $dir . '/' . $name . '.controller.php';
        if (!file_exists($controller)) {
            // Ack, abort, abort:
            Application::http(404);
        }

        // We think we found it, let's save this data to the class:
        $this->controller_path = $dir;
        $this->controller_name = $name;

        // Now let's go ahead and load the controller
        require_once $controller;
        $class = "{$name}Controller";

        // Now does the next section of the path exist as an 'exec' function?
        $exec = $name;
        if (isset($path[0]) && method_exists($class, "exec{$path[0]}")) {
            $exec = array_shift($path);
        }
        $method = "exec{$exec}";

        // Save this extra data as if it was another superglobal, makes it easier
        //  to process later just like those others:
        $GLOBALS['_EXTRA'] = $path;

        // Now instantiate it:
        $this->_controller = new $class($name, $exec, $dir);

        // If we have extra path data, but the controller didn't want it, bail.
        if (!in_array($exec, $this->_controller->allowExtra) && count($path)) {
            Application::http(404);
        }

        // One last sanity check.  If at this point the method doesn't exist, 404
        //  This would most likely happy because, say, you make a controller class, but
        //  never make a 'homepage' method for that new controller.
        if (!method_exists($this->_controller, $method)) {
            Application::http(404);
        }

        // Call the method to execute the Controller:
        $this->_controller->$method();

        // Then call the controller's view method to display it:
        $this->_controller->view();
    }
} // END class
