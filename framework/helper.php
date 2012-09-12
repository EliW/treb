<?php
/**
 * Helper
 *
 * This class holds all the 'helper' methods for our Views to use.  Things to generate
 *  form lists for us, to sanitize output, etc
 *
 * On the name of the class:  Well maybe not the best named class, but it's a hard one to
 *  name.  I've had this problem at previous jobs.  You don't want to name it View, because
 *  while that kinda makes sense, it's NOT a View Class.  I've had it named Form before,
 *  but it's not just 'form' helpers.  I've named it HTML, which 95% works, but then when
 *  writing XML output, you are calling HTML:: methods.  So settled for 'Helper' here.
 *
 * NOTE:  In practice, you'll probably be accessing this through the H:: alias
 *
 * ALSO:  All methods here should be public static, to just be available:
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
class Helper
{
    /**
     * escape
     *
     * Escapes/Sanitizes all data for inclusion in HTML:
     *
     * @author Eli White <eli@eliw.com>
     * @param string $value The value to be sanitized for output.
     * @param boolean $quotes Should we encode single and double quotes. Default is true, "encode"
     * @return string Escaped Value
     * @access public
     **/
    public static function escape($value, $quotes = true)
    {
        // Static cache of charset:
        static $charset = NULL;
        if (!$charset) { $charset = trim((string)config()->env->charset); }
        
        // Now handle the quoting:
        $quotes = $quotes ? ENT_QUOTES : ENT_NOQUOTES;
        return htmlspecialchars($value, $quotes | ENT_HTML5, $charset, FALSE);
    }

    /**
     * xmlify
     *
     * Same as above, does escaping, but this time in an XML fashion, not HTML.
     *  This is one technique I used before, others exist:
     *
     * @author Eli White <eli@eliw.com>
     * @param string $value The value to be sanitized for output.
     * @return string Escaped Value
     * @access public
     **/
    public static function xmlify($value)
    {
        // Static cache of charset:
        static $charset = NULL;
        if (!$charset) { $charset = trim((string)config()->env->charset); }
        
        return iconv($charset, $charset.'//IGNORE', preg_replace("#[\\x00-\\x1f]#msi", ' ',
            str_replace('&', '&amp;', $value)));
    }

    /**
     * jsify
     *
     * Similar to above, but now makes output specifically prepared to be injected int
     *  a javascript variable.  Which is a different beast again.
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $value The value to be sanitized for output.
     * @return string Escaped Value
     * @access public
     **/
    public static function jsify($value)
    {
        return str_replace(array("\r\n","\r","\n"),array("\n","\n","\\\n"),addslashes($value));
    }

    /**
     * json
     *
     * Similar to jsify above, but instead of just escaping the 'content' to be injected,
     *  this version actually takes a whole array or object and makes it JS-ready.
     *
     * For now just uses json_encode to do this, but unless you tell it otherwise, it defaults
     *  to a very safe set of parameters.
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $var The variable (array or object probably) to translate into JS
     * @param integer $options Bitmask of various JSON encode options (defaults sane)
     * @return string The JSON representation of this data.
     * @access public
     */
    public static function json($var, $options = NULL)
    {
        // If $options is NULL (vs 0), default to an extremely safe set.
        //  If 0 is passed in, you turn everything off:
        if ($options === NULL) {
            $options = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;
        }

        return json_encode($var, $options);
    }

    /**
     * selectOptions
     *
     * Based upon input, generates a list of select options and returns it.
     *
     * Has multiple use cases.  You can pass it just a basic array, and it makes
     *  a basic list.  You can also give it a 'selected' value to do a comparison
     *  against, and if so it marks that one as selected.
     *
     * And you can decide to tell if if it should use the keys of the array as the values.
     *
     * Oh, will also automatically recurse and make optgroups for you, if you give it a
     *  nested array.
     *
     * @author Eli White <eli@eliw.com>
     * @param array $data The array of options, assoc or not.
     * @param mixed $selected A comparison of which should be selected.
     * @param boolean $keys Whether or not the keys of the array should be used as values.
     * @return string An HTML option list.
     * @access public
     **/
    public static function selectOptions(Array $data, $selected = NULL, $keys = false)
    {
        $html = '';
        foreach ($data as $k => $v) {
            // At this point, if the value is actually an array, we have an opt-group situation:
            if (is_array($v)) {
                // Recursion time!
                $html .= '<optgroup label="' . self::escape($k) . '">';
                $html .= self::selectOptions($v, $selected, $keys);
                $html .= '</optgroup>';
            } else {
                // Just a single entry:
                $id = $keys ? $k : $v;
                $sel = ($selected == $id) ? ' selected="selected"' : '';
                $id = self::escape($id);
                $v = self::escape($v);
                $html .= "<option value=\"{$id}\"{$sel}>{$v}</option>";
            }
        }
        return $html;
    }

    /**
     * selectOptionsKeys
     *
     * Just a shortcut to use to call the above function, but with Keys set to true
     *  Which means that keys in the array are used as the 'real values', while the
     *  'values' of the array are what are displayed to the user.
     *
     * This is a more descriptive/readable way of calling that method.
     *
     * @author Eli White <eli@eliw.com>
     * @param array $data The array of options, assoc or not.
     * @param mixed $selected A comparison of which should be selected.
     * @return string An HTML option list.
     * @access public
     **/
    public static function selectOptionsKeys(Array $data, $selected = NULL)
    {
        return self::selectOptions($data, $selected, true);
    }

    /**
     * radioGroup
     *
     * Generate a group of radio buttons from an array.  Similar to the select above.
     *
     * @author Eli White <eli@eliw.com>
     * @param string $name the HTML 'name' element to apply
     * @param array $data The array of options, assoc or not.
     * @param mixed $checked A comparison of which should be selected.
     * @param boolean $keys Whether or not the keys of the array should be used as values.
     * @return string An HTML radio list.
     * @access public
     **/
    public static function radioGroup($name, Array $data, $checked = NULL, $keys = false)
    {
        $html = '';
        foreach ($data as $k => $v) {
            $id = $keys ? $k : $v;
            $sel = ($checked == $id) ? 'checked="checked" ' : '';
            $id = self::escape($id);
            $display = self::escape($v);
            $html .= "<input type=\"radio\" name=\"{$name}\" value=\"{$id}\" {$sel}/>{$display} ";
        }

        return $html;
    }

    /**
     * radioGroupKeys
     *
     * Shortcut descriptive method, for making radio groups w/ an assoc array, see above
     *
     * @author Eli White <eli@eliw.com>
     * @param array $data The array of options, assoc or not.
     * @param mixed $checked A comparison of which should be selected.
     * @return string An HTML radio list.
     * @access public
     **/
    public static function radioGroupKeys($name, Array $data, $checked = NULL)
    {
        return self::radioGroup($name, $data, $checked, true);
    }

    /**
     * protect
     *
     * A shortcut to use in a view to generate the input element needed to protect any form
     *  from a CSRF attack.  Instead of needing to create the input element manually:
     *
     * @author Eli White <eli@eliw.com>
     * @return string HTML element to be used for CSRF protection
     * @access public
     **/
    public static function protect()
    {
        return '<input type="hidden" name="token" value="' .
            Utility::generateToken() . '" />';
    }

    /**
     * wrap
     *
     * Allows a programmatic way to wrap some content, in a tag.  Tends to be cleaner in some
     *  echo mechanisms. Also by default, handles escaping your text for you as well:
     *
     * To use this, pass it the opening tag, with all parameters how you want it, it handles the
     *  escaping as well as closing the tag for you.
     *
     * IE:  <?= H::wrap($data->errors->name, '<span class="failure">'); ?>
     *
     * @author Eli White <eli@eliw.com>
     * @param string $text The original text you want wrapped
     * @param string $tag The opening HTML tag, with all parameters how you want 'em
     * @param boolean $escape Do we want escaping?  (Defaults TRUE)
     * @return string Formatted HTML with the wrapped content
     * @access public
     **/
    public static function wrap($text, $tag, $escape = TRUE)
    {
        // Determine the closing tag, based upon the opening one:
        preg_match('/^<([^ >]+)/', $tag, $matches);
        $closing = "</{$matches[1]}>";

        // Escape if we were asked to:
        if ($escape) {
            $text = self::escape($text);
        }

        // Build it:
        return "{$tag}{$text}{$closing}";
    }

    /**
     * ifWrap
     *
     * Similar to the wrap above (in fact, uses it).  But with one specific modification:
     *
     * If the $text itself doesn't exist (is blank), then it just returns a blank
     *  string, with no wrapping.
     *
     * Why?  Because it's much nicer to do this, than to litter the views with samples such
     *  as the following which was happening alot:
     *  <?= $data->errors->name ? H::wrap($data->errors->name, '<span class="failure">') : ''; ?>
     *
     * This helper allows you, in these cases, to just do ifWrap instead.
     *
     * @author Eli White <eli@eliw.com>
     * @param string $text The original text you want wrapped
     * @param string $tag The opening HTML tag, with all parameters how you want 'em
     * @param boolean $escape Do we want escaping?  (Defaults TRUE)
     * @return string Formatted HTML with the wrapped content
     * @access public
     **/
    public static function ifWrap($text, $tag, $escape = TRUE)
    {
        return $text ? self::wrap($text, $tag, $escape) : '';
    }

    /**
     * dump
     *
     * A simple wrapper to make it easy to dump a variable to the screen:
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $var The variable to dump.
     * @return string
     * @access public
     **/
    public static function dump($var)
    {
        // Using print_r instead of var_dump because you can capture the output easier
        //  to escape it.  Might change later if someone cares.
        return '<pre>' . self::escape(print_r($var, true)) . '</pre>';
    }

    /**
     * src
     *
     * The name of this is less than descriptive, while at the same time telling you everything
     *  that you actually need to know.   This is the method you want to use, when you need to
     *  include any 'src file', specifically at the moment at least an img source file, into
     *  your views.
     *
     * Why?  Because we are doing media versioning and want to make sure that we can inject the
     *  appropriate media versioned, well, version of this.   Also because in the future we may
     *  move to a geodiverse static image hosting solution, such as CloudFront, which once
     *  everything is already working in this manner, becomes trivial to do.
     *
     * Why the name?  Why not:  imageSource() or something more descriptive?  Because this thing
     *  was going to live a million times in a million views, and I wanted it to be as simple
     *  looking as possible.
     *
     * Anyway, just pass it the 'src' string for an image, sans the /img/ beginning part, it takes
     *  care of the rest for you.
     *
     * @author Eli White <eli@eliw.com>
     * @param string $src A partial pathname to the image to load in.
     * @return void
     * @access public
     **/
    public static function src($src)
    {
        // For now just a passthrough to 'version' below:
        return self::version('img', $src);
    }

    /**
     * version
     *
     * A counterpart to the above 'src', which handles creating versioned URLs for all our
     *  various media types.  'js', 'css', 'img', and potentially future ones.   This was written
     *  in this fashion so that include.js and include.css could 'share', also to make it easy to
     *  handle different things, differently, in the future:
     *
     * @author Eli White <eli@eliw.com>
     * @param string $type Just a text string for the type of media we want to version js/css/img
     * @param string $href The partial href, not counting for original $type
     * @return string Full 'href' to use to link to this media
     * @access public
     **/
    public static function version($type, $href)
    {
        // Remember staticly the 'version' config, don't read it each time:
        static $version = NULL;
        if ($version === NULL) {
            $val = (int)config()->env->version;
            $version = $val ? "/v{$val}" : '';
        }

        // Currently everything is just a sub-dir:
        return "{$version}/{$type}/{$href}";
    }

    /**
     * truncate
     *
     * truncate text that is too long
     *
     * @author Elizabeth M Smith <elizabeth@mojolive.com>
     * @param string $string what we want
     * @param integer $length maximum size
     * @param string $ellipsis to tack on the end
     * @param bool $whole_words control truncating at whole words
     * @return string
     */
    public static function truncate($string, $length, $ellipsis = '…', $whole_words = false)
    {
        if (strlen($string) <= $length) {
            return $string;
        }

        // size of ellipsis unicode character
        $length = $length - 1;

        if (!$whole_words) {
            return substr($string, 0, $length) . $ellipsis;
        }

        // use wordwrap to get nearest word before desired length.
        return substr($string, 0, strpos(wordwrap($string, $length), "\n")) . $ellipsis;
    }
}