<?php
/**
 * Controller
 *
 * The abstract Controller class.  All 'actual' controllers should extend off of this
 *  and add their own methods/endpoints.
 *
 * All URLs/endpoints on the website will have a directory in
 *  the top of the controllers directory with the name
 *  of that top level endpoint.  Subdirectories can go down as well
 *
 * Inside that directory should be NAME.controller.php that contains the
 *  core functionality.  Methods named execNAME will be automatically
 *  called.
 *
 * Additionally any directory path not found will attempt to be turned into
 *  a new filename to be accessed.  And failing that, additional data will be
 *  passed in as the $extra array.
 *
 * Also, all parameters coming in will be sanitized to match the expected input
 *  This happens automatically for you.  You need to create a variable called:
 *  $expect_NAME for each NAMEd endpoint, giving an array of all data sources
 *  that you expect, with Sanitize based values passed in, such as:
 * public $expect_home = array(
 *        'post' => array(
 *             'username' => 'string',
 *             'type' => 'enum:paid,free,other',
 *             ),
 *        'get' => array(
 *             'pageID' => 'integer',
 *             ),
 * );
 *
 * // TODO:  Also allow it to sanitize 'extra' params
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
abstract class Controller
{
    // Endpoints that allow for 'extra' data, if one is set here then a 404 will never be
    //  thrown for additional data after that endpoint, and instead it
    //  will be stored in the above $extra variable.
    public $allowExtra = array();

    // Used to hold the 'name' of the endpoint.
    protected $name;
    protected $action;
    protected $path;  // The path that was found to hold the Controller

    // A generic data holder, store stuff here in the Controller, and access it in the View
    protected $data;

    // The location where 'clean' sanitized copies of all these datapoints will be stored:
    protected $args;

    // Holder for CSS/JS/other external include files to be stored:
    protected $externals;

    // Holders for 'links' and 'meta' that might need added in - unfortunately just diff enough.
    protected $links = array();
    protected $meta = array();

    // Title of the page (for HTML Header Purposes)
    protected $title = '';

    // Does this Controller need a session?  If so, we will automatically fire one up for you.
    protected $session = false;

    // General view configuration:
    // Name, to which -header.php and -footer.php will be added and will wrap all templates.
    // Set blank/false to disable template wrapping.
    protected $template = 'default';
    protected $view;              // The name of the view file, sans .php
    protected $content;           // Raw content to display instead of the view.
    protected $cacheable = false; // Is the output of this cachable by the browser?
    protected $mode = 'html';     // 'html', 'json', 'rss', 'xml', etc.
    protected $prevent_clickjack = true; // prevent clickjacking

    /**
     * __construct
     *
     * Constructor for Controller, just configures a few basic data points for us:
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access public
     **/
    final public function __construct($name, $method, $path)
    {
        // Setup the default home name:
        $this->view = $this->action = strtolower($method);
        $this->name = strtolower($name);
        $this->path = $path;

        // Perform some basic setup for the controller
        $this->data = new Storage();
        $this->externals = new Storage();

        // Prepopulate the 'js' and 'css' properties, just as a convenience.
        $this->externals->js = new Storage();
        $this->externals->css = new Storage();

        // Sanitize the data that comes in from GET/POST/COOKIE/etc:
        $this->sanitize();

        // Start a session if we need one
        if ($this->session) {
            $this->sessionStart();
        }

        // If an 'init' method exists, call it:
        $this->callInit();
    }

    /**
     * sessionStart
     *
     * Handle all aspects of starting a session.  This includes handling session
     *  hijacking, remember me cookies, and more.
     *
     * This is protected so that you can extend it for your own controllers, adding in other
     *  things that you might want to happen on all sessions being started, such as checking
     *  remember me cookies to log someone in, etc.
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access protected
     **/
    protected function sessionStart()
    {
        $security = config()->security;

        // OK, start the session:
        session_start();

        if (!((int)$security->hijack->disable)) {
            $cookie = (string)$security->hijack->cookie ?: 'MyVoiceIsMyPassport';
            // If the session is not empty, then verify that anti-hijacking tokens are correct:
            if (!empty($_SESSION)) {
                $token = $this->_hijackToken();
                $sh = empty($_SESSION['hijack']) ? false : $_SESSION['hijack'];
                $ch = Cookie::get($cookie);

                if (!$sh || !$ch || ($sh != $ch) || ($sh != $token)) {
                    // Looks like a hijack, go through the process of giving them a brand new shiny
                    //  session to use.
                    session_write_close();
                    session_id(md5(time()));
                    session_start();
                    Cookie::delete($cookie);
                    Log::write('hijack', array($sh, $ch, $token, Server::getAgent()), Log::WARNING);
                    // If Ajax, 403 error, otherwise be nice and redirect to homepage:
                    if (Server::isAjax()) {
                        Application::http(403);
                    } else {
                        Application::http(302);
                    }
                }
            } else {
                // Their session was empty/new, therefore we need to create
                //  these tokens for them for the future:
                $now = new DateTime();
                $_SESSION['started'] = $now->format(DateTime::ISO8601);
                $token = $this->_hijackToken();
                $_SESSION['hijack'] = $token;
                Cookie::set($cookie, $token);
            }
        }
    }

    /**
     * _hijackToken
     *
     * Generates the anti session hijacking token
     *
     * @author Eli White <eli@eliw.com>
     * @return string Anti Session Hijacking token
     * @access private
     **/
    private function _hijackToken()
    {
        // Use User Agent, and a Salt, to generate a token
        //  You might want to enhance this later, adding in the user_id if the user is
        //  logged in, but then we have to regenerate this on login/logout, and that logic is
        //  elsewhere.   We do use a session timestamp though also, so there is something 'unique'
        $token = Server::getAgent();
        if (!($salt = (string)config()->security->hijack->salt)) {
            $salt = "Someone didn't configure their salt!";
            trigger_error("Anti-hijack salt left blank in site config file!", E_USER_WARNING);
        }
        $token .= "|{$salt}|";
        $token .= $_SESSION['started'];   // Random unique thing to this session
        return sha1($token);
    }

    /**
     * view
     *
     * The View of this application, simply displays/executes it all.
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access public
     **/
    public function view()
    {
        // Only start the header section, if headers haven't already been sent
        //  This stops errors from happening in the code if you put debugging statements
        //  in, for example:
        if (!headers_sent()) {
            $charset = trim((string)config()->env->charset);
            // Based upon some potential view-configuration, handle headers for us:
            switch ($this->mode) {
                case 'html':
                    header('Content-Type: text/html; charset='.$charset);
                    if ($this->prevent_clickjack) {
                        // Clickjack Protection, rough & absolute for now
                        //  You still need to add a JS/CSS solution as well for full protection
                        header('X-Frame-Options: DENY');
                    }
                    break;
                case 'text':
                    header('Content-Type: text/plain; charset='.$charset);
                    break;
                case 'json':
                    header('Content-Type: application/json; charset='.$charset);
                    break;
                case 'rss':
                    header('Content-Type: application/rss+xml; charset='.$charset);
                    break;
                case 'xml':
                    header('Content-Type: text/xml; charset='.$charset);
                    break;
                case 'png':
                    header('Content-Type: image/png');
                    break;
                case 'php':
                    header('Content-Type: application/vnd.php.serialized; charset='.$charset);
                    break;

            }

            // Do they want this to be cacheable?
            if (empty($this->cacheable)) {
                // Slam them with every 'don't cache this' possible:
                header('Cache-Control: no-cache, no-store, must-revalidate, ' .
                        'pre-check=0, post-check=0, max-age=0');
                header('Pragma: no-cache');
                header('Expires: Fri, 03 Dec 1973 08:08:08 GMT');
            }
        }

        // Now assuming that we have a view: Actually process it:
        if (!empty($this->view)) {
            // For now we are trusting the programmer to only access $data, even though
            //  they could do much more.

            // Copy any 'data' for the template into a local namespace:
            $data = $this->data;

            // If we are using a template, load the header now:
            if ($this->template) {
                require ROOT . "/views/templates/{$this->template}.header.php";
            }

            // Now include the view:
            require ROOT . "/views{$this->path}/{$this->view}.view.php";

            // Footer!
            if ($this->template) {
                require ROOT . "/views/templates/{$this->template}.footer.php";
            }
        } elseif (!empty($this->content)) {
            // This section used in cases where we want to just raw output something
            //  from the controller, without any view in the way.  Say some JSON
            echo $this->content;
        }
    }

    /**
     * sanitize
     *
     * Uses the $expect_NAME variable to clean up data
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access private
     **/
    private function sanitize()
    {
        $this->args = new Storage();
        $expect = "expect_{$this->action}";

        // All 'untrusted' sources of data:
        $untrusted = array('_POST' => 'post', '_GET' => 'get', '_COOKIE' => 'cookie',
            '_ENV' => 'env', '_SERVER' => 'server', '_EXTRA' => 'extra');

        // Loop through all sources of data
        $filter = new Filter();
        foreach ($untrusted as $raw => $source) {
            if (!empty($this->{$expect}[$source])) {
                $this->args->{$source} = $filter->sanitize($GLOBALS[$raw], $this->{$expect}[$source]);
            }
            // Blow away the POST, GET, and EXTRA arrays so that a dev won't accidentily use them
            //  We unfortunately can't touch ENV, SERVER, or COOKIE though.  Because too many
            //  3rd party libraries (firePHP) or built in PHP features (sessions) rely on them.
            if (in_array($raw, array('_POST', '_GET', '_EXTRA'))) {
                unset($GLOBALS[$raw]);
            }
        }
    }

    /**
     * addJS
     *
     * Adds in a Javascript library to be included on this page's template
     *
     * File names passed in should not have the .js, and are assumed to live in the default
     *  JS directory.  Relative paths can be included to dive deeper.
     *
     * You can pass in a single string, or an array of strings to reduce the number of calls
     *
     * Really just a passthrough to addExternal
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $file String or Array of string, of the name of JS files to be included
     * @param string $sub A subtype of this. Such as IE only
     * @return void
     * @access public
     **/
    protected function addJS($file, $sub = 'normal')
    {
        $this->addExternal($file, 'js', $sub);
    }

    /**
     * addCSS
     *
     * Similar to addJS above, however meant for CSS.  Also includes a secondary parameter
     *  that allows you to specify the 'type' of CSS file this is.  Typically meant to be
     *  used to specify 'mobile' or 'IE' files, to be included only in those cases.  However
     *  open form at the moment, so you could get crazy.
     *
     * Really just a passthrough to addExternal
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $file String or Array of string, of the name of CSS files to be included
     * @param string $sub Type of file 'normal', 'mobile', 'IE', etc.
     * @return void
     * @access public
     **/
    protected function addCSS($file, $sub = 'normal')
    {
        $this->addExternal($file, 'css', $sub);
    }

    /**
     * addExternal
     *
     * Allows you to add in references to external 'files' or 'links' that need to be
     *  included in the template, be that JS, CSS, or perhaps other future file types.
     *
     * Typically the filename itself should just be included, and the 'type' of file.
     *  Later that template code can figure out what to do w/ each type of file.
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $what String or Array of string, of the name of files to be included
     * @param string $type Type of file/link 'js', 'css', etc.
     * @param string $sub The subtype (defaults to 'normal')
     * @return void
     * @access public
     **/
    protected function addExternal($what, $type, $sub = 'normal')
    {
        // If the 'type' doesn't exist yet, instantiate it:
        if (!$this->externals->{$type}) {
            $this->externals->{$type} = new Storage();
        }

        // Now merge in the file/files provided
        $this->externals->{$type}->{$sub} =
            array_merge((array)$this->externals->{$type}->{$sub}, (array)$what);
    }

    /**
     * addLink
     *
     * Stores header 'link' information that templates might need/want to use.  Unfortunately
     *  this is different enough, and varied enough, to not fit under a nice 'neat' container
     *  such as addCSS/addJS.
     *
     * NOTE: While YES, it's possible to add CSS via this command.  DON'T DO IT.  Really, I mean
     *  it.  This is meant for all the 'other assorted link thingies' that might need to exist.
     *
     * Such as: RSS/ATOM feeds, shorturl links, prev/next, 'canonical', and all those other messy
     *  things.
     *
     * @author Eli White <eli@eliw.com>
     * @param string $rel The 'rel' attribute content
     * @param string $href The 'href' of this link
     * @param string $type The 'type' attribute value (optional)
     * @param string $title The 'title' attribute value (optional)
     * @return void
     * @access public
     **/
    protected function addLink($rel, $href, $type = NULL, $title = NULL)
    {
        // Just store it, really, that's all we do, up to templates to use this data
        $link = new Storage();
        $link->rel = $rel;
        $link->href = $href;
        $link->type = $type;
        $link->title = $title;
        $this->links[] = $link;
    }

    /**
     * addMeta
     *
     * Same concept as addLink above, but this one stores meta tag information.  Use this
     *  to add in any specific things, such as Facebook graphics, keywords, descriptions, etc.
     *
     * Thanks to the RDFa making stuff complicated, sometimes we will have 'name' sometimes
     *  'property'.  I debated a bunch of different ways to handle this.  For now the decision was
     *  to just accept a 'name' and 'content', then have a switch to declare if this is really a
     *  'property' instead (FACEBOOK!).  We can rejigger that later.
     *
     * @author Eli White <eli@eliw.com>
     * @param string $name The descriptive attribute of this link
     * @param string $content The content of the meta tag
     * @param boolean $property Is the descriptor a 'property' instead of a 'name' (default FALSE)
     * @return void
     * @access public
     **/
    protected function addMeta($name, $content, $property = FALSE)
    {
        // Just store it, really, that's all we do, up to templates to use this data
        $meta = new Storage();
        $meta->name = $name;
        $meta->content = $content;
        $meta->property = $property;

        // save the meta tag info
        $this->meta[$name] = $meta;
    }

    /**
     * callInit
     *
     * Calls the init function of sub-classes.
     *
     * Moved from the constructor to allow sub-classes to perform actions
     * prior to calling the actual init() function.
     *
     * @author Oscar Merida <oscar@mojolive.com>
     * @return void
     * @access private
     */
    protected function callInit()
    {
        // If an 'init' method exists, call it:
        if (method_exists($this, 'init')) {
            $this->init();
        }
    }
}
