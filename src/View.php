<?php
/**
 * Utopia PHP Framework
 *
 * @package Framework
 * @subpackage Core
 *
 * @link https://github.com/utopia-php/framework
 * @author Appwrite Team <team@appwrite.io>
 * @license The MIT License (MIT) <http://www.opensource.org/licenses/mit-license.php>
 */

namespace Utopia;

use Exception;

class View
{
    const FILTER_ESCAPE = 'escape';
    const FILTER_NL2P   = 'nl2p';

    /**
     * @var self
     */
    protected $parent = null;

    /**
     * @var string
     */
    protected $path = '';

    /**
     * @var bool
     */
    protected $rendered = false;

    /**
     * @var array
     */
    protected $params = [];

    /**
     * @var array
     */
    protected $filters = [];

    /**
     * Constructor
     *
     * You can optionally initialize the View object with a template path, although this can also be set later using the $this->setPath($path) method
     *
     * @param string $path
     * @throws Exception
     */
    public function __construct($path = '')
    {
        $this->setPath($path);

        $this
            ->addFilter(self::FILTER_ESCAPE, function ($value) {
                return \htmlentities($value, ENT_QUOTES, 'UTF-8');
            })
            ->addFilter(self::FILTER_NL2P, function ($value) {
                $paragraphs = '';

                foreach (\explode("\n\n", $value) as $line) {
                    if (\trim($line)) {
                        $paragraphs .= '<p>' . $line . '</p>';
                    }
                }

                $paragraphs = \str_replace("\n", '<br />', $paragraphs);

                return $paragraphs;
            })
        ;
    }

    /**
     * Set param
     *
     * Assign a parameter by key
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     * @throws Exception
     */
    public function setParam($key, $value)
    {
        if (\strpos($key, '.') !== false) {
            throw new Exception('$key can\'t contain a dot "." character');
        }

        $this->params[$key] = $value;

        return $this;
    }

    /**
     * Set parent View object conatining this object
     *
     * @param self $view
     * @return View
     */
    public function setParent(self $view)
    {
        $this->parent = $view;
        return $this;
    }

    /**
     * Return a View instance of the parent view containing this view
     *
     * @return View|null
     */
    public function getParent()
    {
        if (!empty($this->parent)) {
            return $this->parent;
        }

        return null;
    }

    /**
     * Get param
     *
     * Returns an assigned parameter by its key or $default if param key doesn't exists
     *
     * @param string $path
     * @param mixed $default (optional)
     * @return mixed
     */
    public function getParam($path, $default = null)
    {
        $path   = \explode('.', $path);
        $temp   = $this->params;

        foreach ($path as $key) {
            $temp = (isset($temp[$key])) ? $temp[$key] : null;

            if (null !== $temp) {
                $value = $temp;
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Set path
     *
     * Set object template path that will be used to render view output
     *
     * @param  string    $path
     * @throws Exception
     * @return $this
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Set rendered
     *
     * By enabling rendered state to true, the object will not render its template and will return an empty string instead
     *
     * @param bool $state
     * @return $this
     */
    public function setRendered($state = true)
    {
        $this->rendered = $state;

        return $this;
    }

    /**
     * Is rendered
     *
     * Return whether current View rendering state is set to true or false
     *
     * @return bool
     */
    public function isRendered()
    {
        return (bool) $this->rendered;
    }

    /**
     * Add Filter
     *
     * @param string $name
     * @param callable $callback
     *
     * @return View
     */
    public function addFilter(string $name, $callback)
    {
        $this->filters[$name] = $callback;
        return $this;
    }

    /**
     * Output and filter value
     *
     * @param mixed $value
     * @param string|string[] $filter
     * @return string
     * @throws Exception
     */
    public function print($value, $filter = '')
    {
        if (!empty($filter)) {
            if (\is_array($filter)) {
                foreach ($filter as $callback) {
                    if (!isset($this->filters[$callback])) {
                        throw new Exception('Filter "' . $callback . '"" is not registered');
                    }

                    $value = $this->filters[$callback]($value);
                }
            } else {
                if (!isset($this->filters[$filter])) {
                    throw new Exception('Filter "' . $filter . '"" is not registered');
                }

                $value = $this->filters[$filter]($value);
            }
        }

        return $value;
    }

    /**
     * Render
     *
     * Render view .phtml template file if template has not been set as rendered yet using $this->setRendered(true).
     * In case path is not readable throws Exception.
     *
     * @var boolean $minify
     *
     * @return string
     * @throws Exception
     */
    public function render($minify = true)
    {
        if ($this->rendered) { // Don't render any template

            return '';
        }

        \ob_start(); //Start of build

        if (\is_readable($this->path)) {
            include $this->path; // Include template file
        } else {
            \ob_end_clean();
            throw new Exception('"' . $this->path . '" view template is not readable');
        }

        $html = \ob_get_contents();

        \ob_end_clean(); //End of build

        if ($minify) {
            // Searching textarea and pre
            \preg_match_all('#\<textarea.*\>.*\<\/textarea\>#Uis', $html, $foundTxt);
            \preg_match_all('#\<pre.*\>.*\<\/pre\>#Uis', $html, $foundPre);

            // replacing both with <textarea>$index</textarea> / <pre>$index</pre>
            $html = \str_replace($foundTxt[0], \array_map(function ($el) {
                return '<textarea>'.$el.'</textarea>';
            }, \array_keys($foundTxt[0])), $html);
            $html = \str_replace($foundPre[0], \array_map(function ($el) {
                return '<pre>'.$el.'</pre>';
            }, \array_keys($foundPre[0])), $html);

            // your stuff
            $search = [
                '/\>[^\S ]+/s',  // strip whitespaces after tags, except space
                '/[^\S ]+\</s',  // strip whitespaces before tags, except space
                '/(\s)+/s'       // shorten multiple whitespace sequences
            ];

            $replace = [
                '>',
                '<',
                '\\1'
            ];

            $html = \preg_replace($search, $replace, $html);

            // Replacing back with content
            $html = \str_replace(\array_map(function ($el) {
                return '<textarea>'.$el.'</textarea>';
            }, \array_keys($foundTxt[0])), $foundTxt[0], $html);
            $html = \str_replace(\array_map(function ($el) {
                return '<pre>'.$el.'</pre>';
            }, \array_keys($foundPre[0])), $foundPre[0], $html);
        }

        return $html;
    }

    /* View Helpers */

    /**
     * Exec
     *
     * Exec child View components
     *
     * @param array|self $view
     * @return string
     * @throws Exception
     */
    public function exec($view)
    {
        $output = '';

        if (\is_array($view)) {
            foreach ($view as $node) { /* @var $node self */
                if ($node instanceof self) {
                    $node->setParent($this);
                    $output .= $node->render();
                }
            }
        } elseif ($view instanceof self) {
            $view->setParent($this);
            $output = $view->render();
        }

        return $output;
    }

    /**
     * Escape
     *
     * Convert all applicable characters to HTML entities
     *
     * @param  string $str
     * @return string
     * @deprecated Use print method with escape filter
     */
    public function escape($str)
    {
        return \htmlentities($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * nl2p
     *
     * Convert new line breaks text to HTML paragraphs
     *
     * @note This function will remove any single line-breaks.
     * @see http://stackoverflow.com/a/14467470
     *
     * @param string $string
     * @return string
     * @deprecated Use print method with nl2p filter
     */
    public function nl2p($string)
    {
        $paragraphs = '';

        foreach (\explode("\n\n", $string) as $line) {
            if (\trim($line)) {
                $paragraphs .= '<p>' . $line . '</p>';
            }
        }

        $paragraphs = \str_replace("\n", '<br />', $paragraphs);

        return $paragraphs;
    }
}
