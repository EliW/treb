<?php
/**
 * H
 *
 * This is a slight hack, using PHP to our benefit.  Basically it's a way to alias
 *  the methods of Helper::method() in a slightly shorter fashion, in order for
 *  us to use them here instead of a View:  H::method() is just a little cleaner for
 *  view purposes.
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
class H extends Helper {}
