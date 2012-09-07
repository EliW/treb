<?php
/**
 * Sample
 *
 * Extends Model for a sample table, called, let's say:  "samples" table
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 *
 **/
class Sample extends Model
{
    // Define our basic configuration:
    static protected $_table = 'samples';

    /**
     * getAll
     *
     * Returns a Set all the current Examples
     *
     * @author Eli White <eli@eliw.com>
     * @return Set
     * @access public
     **/
    static public function getAll()
    {
        $table = self::$_table;

        return new Set('Sample', "SELECT id FROM {$table} ORDER BY id ASC");
    }

    /**
     * getByName
     *
     * Assume there's a 'name' column and it's unique:
     *
     * @author Eli White <eli@eliw.com>
     * @param string $name The email to search for
     * @return Example || false
     * @access public
     **/
    static public function getByName($name)
    {
        $table = self::$_table;

        // Attempt to find the name:
        $row = Utility::cachedRow(
            "SELECT id FROM {$table} WHERE name = ?",
            array($name), self::$_read);

        return $row ? new Sample($row->id) : FALSE;
    }

} // END class
