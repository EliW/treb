<?php
/**
 * HomeController
 *
 * The homepage's controller.  Not much more than that
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
class HomeController extends Controller
{
    // Some generic configuration:
    protected $session = true;

    /**
     * init
     *
     * Sets up some basic configuration for this controller, all endpoints:
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access public
     **/
    protected function init()
    {
        $this->addCSS('example');
    }

    /**
     * execHome
     *
     * Just the main 'thingy'
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access public
     **/
    protected $expect_home = array(
        'get' => array(
            'test' => 'string'
        )
    );
    public function execHome()
    {
        // Set a title for us:
        $this->title = 'Successful Treb!';

        // Set some data we will use in the view:
        $this->data->ip = Server::getIP();
        $this->data->test = $this->args->get['test'];
    }

} // END class
