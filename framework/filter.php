<?php
/**
 * Filter
 *
 * A library to handle filtering of any data.
 *
 * Pass in an array of data that you wanted filtered and a configuration array
 *  showing what data you end up actually wanting & in what format.
 *
 * A sample complicated configuration array, extrapolate from here:
 * $config = array(
 *    'user' => 'string',
 *    'type' => 'enum:work,home,other',
 *    'page' => 'integer',
 *    'ids' => array (
 *        '_keys' => 'integer'
 *        '_values' => 'integer'
 *        ),
 *    'ratings' => array (
 *        '_values' => 'float',
 *        ),
 *    'work' => array(
 *        '_keys' => 'string',
 *        '_values' => array(
 *            'name' => 'string',
 *            'desc' => 'string',
 *            'details' => array(
 *                'id' => 'integer',
 *                'extra' => 'string',
 *                ),
 *            ),
 *        ),
 *    );
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
class Filter
{
    /**
     * __construct
     *
     * Bare constructor, doesn't do anything.
     *
     * @author Eli White <eli@eliw.com>
     * @return Filter
     * @access public
     **/
    public function __construct() {}

    /**
     * sanitize
     *
     * The 'meat' of this class.  Takes the input array and filter options.
     *
     * Returns an array of data back, sanitized.
     *
     * @author  Eli White <eli@eliw.com>
     * @param   array   $input      The array to sanitize (i.e. GET or POST)
     * @param   array   $filters    Configuration matching the above examples (expect_[SOURCE])
     * @return  array   Cleaned data
     * @access  public
     **/
    public function sanitize(Array $input, Array $filters)
    {
        // Loop through all the requested data points
        $output = array();
        foreach ($filters as $source => $filter) {
            // Ensure this data exists, then assert it:
            $value = isset($input[$source]) ? $input[$source] : null;
            $output[$source] = $this->_callAssert($value, $filter);
        }

        return $output;
    }

    /**
     * _callAssert
     *
     * A utility function that handles the logic of looking at a filter
     *  and calling the right assert method on a single value.
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $value Value to be asserted.
     * @param mixed $filter The filter config (an array) that was requested.
     * @return mixed The asserted value
     * @access private
     **/
    private function _callAssert($value, $filter)
    {
        // Handle potentially recursive array case first:
        if (is_array($filter)) {
            $retval = $this->_assertArray($value, $filter);
        } else {
            // Check for : delimited options, then pass on:
            $opt = explode(':', $filter, 2);
            if (count($opt) == 2) {
                $retval = $this->{"_assert{$opt[0]}"}($value, $opt[1]);
            } else {
                $retval = $this->{"_assert{$opt[0]}"}($value);
            }
        }

        return $retval;
    }

    /**
     * _assertArray
     *
     * Ensures that we've got an array, and that it matches either a
     *  recursive definition, a 'every entry is this' definition, or
     *  a mix of those.  Returns a blank array if this wasn't an array.
     *
     * @author  Eli White <eli@eliw.com>
     * @param   mixed $value    Value to be asserted.
     * @param   mixed $filter   The filter config (an array) that was requested.
     * @return  array
     * @access  private
     **/
    private function _assertArray($value, Array $filter)
    {
        // First step, is the value isn't even an array, we are done.
        if (!is_array($value)) {
            $value = array();
        }

        // Second case, if the filter is a request for a series of identical
        //  items and/or keys
        $output = array();
        if (isset($filter['_values'])) {
            // Loop through each entry, validating as we go:
            foreach ($value as $key => $data) {
                if (isset($filter['_keys'])) {
                    $key = $this->_callAssert($key, $filter['_keys']);
                    $output[$key] = $this->_callAssert($data, $filter['_values']);
                } else {
                    // If you didn't ask for key validation, you get new keys:
                    $output[] = $this->_callAssert($data, $filter['_values']);
                }
            }
        } else {
            // They didn't give us a generic '_values' statement, therefore assume
            //  this is a basic subarray & recurse:
            $output = $this->sanitize($value, $filter);
        }

        return $output;
    }

    /**
     * _assertRaw
     *
     * Doesn't do anything.  Exists to allow raw data passed through, but
     *  should RARELY ever be used.
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $value Value to be asserted.
     * @return mixed
     * @access private
     **/
    private function _assertRaw($value)
    {
        return $value;
    }

    /**
     * _assertInteger
     *
     * Ensures that the value returned is an Integer
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $value Value to be asserted.
     * @return integer
     * @access private
     **/
    private function _assertInteger($value)
    {
        return intval($value);
    }

    /**
     * _assertHex
     *
     * Ensures that the value returned is a valid hexadecimal string, such as a
     *  md5() or sha1() response, or null.
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $value Value to be asserted.
     * @return string
     * @access private
     **/
    private function _assertHex($value)
    {
        if (!ctype_xdigit($value)) {
            $value = null;
        }
        return $value;
    }

    /**
     * _assertBase36
     *
     * Ensures that the value returned is a valid base36 string, or NULL
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $value Value to be asserted.
     * @return string
     * @access private
     **/
    private function _assertBase36($value)
    {
        // Force it to lowercase, then check:
        $value = strtolower($value);
        if (!ctype_alnum($value)) {
            $value = null;
        }
        return $value;
    }

    /**
     * _assertFloat
     *
     * Ensures that the value returned is a Float
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $value Value to be asserted.
     * @return float
     * @access private
     **/
    private function _assertFloat($value)
    {
        return floatval($value);
    }

    /**
     * _assertBoolean
     *
     * Ensures that the value returned is a Boolean Value
     *
     * NOTE:  This doesn't look for things like 'false', 'no', 'off', etc.
     *  so be careful when using it.
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $value Value to be asserted.
     * @return boolean
     * @access private
     **/
    private function _assertBoolean($value)
    {
        return (boolean)$value;
    }

    /**
     * _assertString
     *
     * Ensures that the value returned is a clean string, stripped of tags & trimmed
     *
     * If the optional second parameter is supplied, such as: 'string:50', then it
     *  ensures that the string is no longer than that many characters via truncation.
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $value Value to be asserted.
     * @param integer $max Maximum length of the string
     * @return string
     * @access private
     **/
    private function _assertString($value, $max = FALSE)
    {
        // Strips tags, trims, and attempts to replace all UTF8 'space' characters with a
        //  natural 'space'.  Note this doesn't touch paragraph/line spaces, because we allow
        //  them in descriptions/etc.  We may need to get more 'detailed' later to handle that.
        if ($value !== '') {
            $value = trim(preg_replace('/\p{Zs}/u', ' ', strip_tags(strval($value))));
            // for now, strip out silly MS Word bullets
            $value = strtr($value, array('' => '', '' => ''));
            if ($max) { $value = substr($value, 0, $max); }
        }
        return $value;
    }

    /**
     * _assertEmail
     *
     * Makes sure that the value is a valid email or returns FALSE/NULL
     *
     * NULL if no value was passed in at all.
     * FALSE if the value failed the assertion.
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $value Value to be asserted.
     * @return string
     * @access private
     **/
    private function _assertEmail($value)
    {
        if (empty($value)) {
            $value = NULL;
        } elseif (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $value = FALSE;
        }
        return $value;
    }

    /**
     * _assertUrl
     *
     * Makes sure that the value is a valid url or returns FALSE/NULL
     *
     * NULL if no value was passed in at all.
     * FALSE if the value failed the assertion.
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $value Value to be asserted.
     * @return string
     * @access private
     **/
    private function _assertUrl($value)
    {
        if (empty($value)) {
            $value = NULL;
        } elseif (!filter_var($value, FILTER_VALIDATE_URL)) {
            $value = FALSE;
        }
        return $value;
    }

    /**
     * _assertEnum
     *
     * Ensures that the value returned is one of a list of allowed values only.
     * It will assume that the 1st value is the 'default' one.  If you want to allow
     *  for a 'blank'/false type default, just make your first option start with
     *  a comma, so:  enum:,red,blue,green
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $value Value to be asserted.
     * @param array $enum An enumeration CSV of options that are possible.
     * @return mixed
     * @access private
     **/
    private function _assertEnum($value, $enum)
    {
        // Break the list up into an array:
        $options = explode(',', $enum);
        if (!in_array($value, $options)) {
            $value = $options[0];
        }

        return $value;
    }

    /**
     * _assertRegex
     *
     * Runs the value through a provided regex, if it doesn't match, clear it.
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $value Value to be asserted.
     * @param array $regex The preg based regex
     * @return mixed
     * @access private
     **/
    private function _assertRegex($value, $regex)
    {
        if (!$value || !preg_match($regex, $value)) {
            $value = null;
        }
        return $value;
    }

    /**
     * _assertDate
     *
     * Ensures that the value passed in is a valid date and converts it
     *  to a timestamp.  Just uses strtotime.
     *
     * Returns FALSE if it can't parse the date.
     *
     * @param mixed $value the value to be filtered
     * @return mixed
     * @access private
     */
    private function _assertDate($value)
    {
        // Only current option is images, so use as such:
        return strtotime($value);
    }

    /**
     * _assertData
     *
     * A very specific filter, for convenience.  This filter looks against an existing 'Data' class
     *  that will need defined in the /classes/ directory, for static variables, and then
     *   essentially performs like the 'enum' filter does above.  Allowing you to make sure your
     *   data matches your lookup tables.
     *
     * Usage is a little more complex, but straight forward:
     * data:array:name
     * data:keys:name
     *
     * These two references do, well, what you would expect.  Like the enum class, they will
     *  default you to the '1st' entry in the array if nothing matches.  If you want to
     *  modify that behavior, and allow a 'null' response as well, end with a :null
     *
     * (Yeah, it's messy, but it works).  so:
     * data:array:availability_types:null
     *
     * @param mixed $value the value to be filtered
     * @return mixed
     * @access private
     */
    private function _assertData($value, $extra)
    {
        // First break up our $extra array:
        $options = explode(':', $extra);

        // Determine what we are doing a lookup on:
        $lookup = Data::${$options[1]};
        $null = (isset($options[2]) && ($options[2] == 'null'));

        if ($options[0] == 'keys') {
            // Key lookup
            if (!array_key_exists($value, $lookup)) {
                reset($lookup);
                $value = $null ? NULL : key($lookup);
            }
        } else {
            // Value lookup:
            if (!in_array($value, $lookup)) {
                $value = $null ? NULL : reset($lookup);
            }
        }

        return $value;
    }

}
