<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2013-2014 Vanilla Forums Inc.
 * @license MIT
 * @package Snidely
 */

namespace Snidely;

class Snidely {
    /// Properties ///

    /**
     * @var int The permission mask for cached files.
     */
    public $cacheFileMode = 0644;

    /**
     * @var string The location of cached compiled templates.
     */
    protected $cachePath;

    /**
     * @var int Flags to pass to the {@link PhpCompile}.
     */
    public $compilerFlags = 0;

    /**
     * @var array An array of helper functions for the template.
     */
    public $helpers = [];

    /**
     * @var array[callable]
     */
    protected $partialLoaders = [];

    /**
     * @var array An array of partials for the template.
     */
    protected $partials = [];

    /**
     * @var int The pushed error reporting.
     */
    protected $pushedErrorReporting;

    /// Methods ///

    public function __construct() {
        $this->cachePath(__DIR__ . '/../cache');
    }

    public function cachePath($value = null) {
        if ($value !== null)
            $this->cachePath = rtrim($value, DIRECTORY_SEPARATOR);
        return $this->cachePath;
    }

    /**
     * @param string $template
     * @param string $key
     * @return callable Returns a closure that will render the template when called.
     */
    public function compile($template, $key = null) {
        if ($key === null)
            $key = md5($template);

        $path = $this->cachePath()."/$key.php";
        if (true || !file_exists($path)) {
            $php = "<?php\n"
                 ."/*\n".$template."\n*/\n"
                 . $this->precompile($template);

            if (file_exists($path)) {
                $current_php = file_get_contents($path);
                if ($current_php != $php) {
                    $this->file_put_contents_atomic($path, $php, $this->cacheFileMode);
                }
            } else {
                $this->file_put_contents_atomic($path, $php, $this->cacheFileMode);
            }
        }

        return require_snidely($this, $path);
    }

    public function precompile($template) {
        $nodes = $this->parse($template);
        $compiler = new PhpCompiler($this, $this->compilerFlags);

        return $compiler->compile($nodes);
    }

    /**
     * Render a compiled template and return the resulting string.
     * @param callable $compiled_template
     * @param mixed $data
     * @return string
     */
    public function fetch(callable $compiled_template, $data) {
        try {
            ob_start();
            $this->pushErrorReporting();
            $compiled_template($data, $this);
            $this->popErrorReporting();
            $result = ob_get_clean();
        } catch(\Exception $ex) {
            $this->popErrorReporting();
            $result = ob_get_clean();
        }

        return $result;
    }

    protected function file_put_contents_atomic($filename, $content, $mode = 0644) {
        $temp = tempnam(dirname($filename), 'atomic');

        if (!($fp = @fopen($temp, 'wb'))) {
            $temp = dirname($filename).DIRECTORY_SEPARATOR.uniqid('atomic');
            if (!($fp = @fopen($temp, 'wb'))) {
                trigger_error("file_put_contents_atomic() : error writing temporary file '$temp'", E_USER_WARNING);
                return false;
            }
        }

        fwrite($fp, $content);
        fclose($fp);

        if (!@rename($temp, $filename)) {
            @unlink($filename);
            @rename($temp, $filename);
        }

        @chmod($filename, $mode);
        return true;
    }

    public function parse($template) {
        $tokenizer = new \Snidely\Tokenizer();
        $parser = new \Snidely\Parser();

//      die(json_encode($tokenizer->argsTokenizer->scan('.')));

        $tokens = $tokenizer->scan($template);
        $nodes = $parser->parse($tokens);

        return $nodes;
    }

    public function partial($name, $context, Scope $scope) {
        if (isset($this->partials[$name])) {
            call_user_func($this->partials[$name], $context, $this);
        } else {
            foreach ($this->partialLoaders as $callback) {
                $partial = $callback($name, $context, $scope, $this);

                if ($partial !== null) {
                    call_user_func($partial, $context, $this);
                }
            }
        }
    }

    public function popErrorReporting() {
        if ($this->pushedErrorReporting !== null) {
            error_reporting($this->pushedErrorReporting);
            $this->pushedErrorReporting = null;
        }
    }

    public function pushErrorReporting() {
        if ($this->pushedErrorReporting === null) {
            $this->pushedErrorReporting = error_reporting(error_reporting() & ~E_NOTICE & ~E_WARNING);
//            $this->pushedErrorReporting = error_reporting();
        }
    }

    public function registerHelper($name, $callback = null) {
        if (!$callback)
            $callback = $name;

        $this->helpers[$name] = $callback;
    }

    /**
     * Register a
     * @param string $name The name of the partial.
     * @param string|callable $partial A template source or function that implements the partial.
     * @param bool $force_compile
     */
    public function registerPartial($name, $partial, $force_compile = false) {
        if (!$force_compile && is_callable($partial)) {
            $this->partials[$name] = $partial;
        } else {
            $fn = $this->compile($partial);
            $this->partials[$name] = $fn;
        }
    }

    public function registerPartialLoader(callable $callback) {
        $this->partialLoaders[] = $callback;
    }
}


/**
 * @param Snidely $snidely
 * @param string $path
 * @return callable
 */
function require_snidely(Snidely $snidely, $path) {
    return require $path;
}