<?php
/**
 * Data
 *
 * A location for us to do Data decoupling from the database.
 *
 * Basically a class where it is expected to place lots of arrays of commonly
 *  needed/accessed data.  Instead of storing them in lookup tables in the database.
 *
 * NOTE: All data should be public static variables, to just be 'magically'
 *  accessible from anywhere.
 *
 * NOTE: All static arrays listed in here, become available to the Filter 'data' type for
 *  input filtering against.
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
class Data
{
    // Example
    static public $example_types = array(
        0 => 'Red',
        2 => 'White',
        4 => 'Blue',
    );
}
?>