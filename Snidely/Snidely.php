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
     * @var array An array of helper functions for the template.
     */
    public $helpers = [];

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
     * @param Snidely\Compiler $compiler
     */
    public function compile($template, $key = null) {
        if ($key === null)
            $key = md5($template);

        $path = $this->cachePath()."/$key.php";
        if (true || !file_exists($path)) {
            $php = "<?php\n"
                 ."namespace Snidely;\n"
                 ."/*\n".$template."\n*/\n"
                 . $this->precompile($template);
            $this->file_put_contents_atomic($path, $php, $this->cacheFileMode);
        }

        return require_snidely($this, $path);
    }

    public function precompile($template) {
        $nodes = $this->parse($template);
        $compiler = new PhpCompiler($this);

        return $compiler->compile($nodes);
    }

    /**
     * A helper that implents the runtime version of the {{#each }} section.
     * @param array $context
     * @param array $options
     */
    public static function each($context, Scope $scope, $options) {
        if (empty($context)) {
            // The context is empty so display the inverse template.
            if (isset($options['inverse'])) {
                call_user_func($options['inverse'], $context, $scope, $options);
            }
        } elseif (is_array($context)) {
            // Get the item separator if any.
            if (isset($options['hash']['sep'])) {
                $sep = $options['hash']['sep'];
            } else {
                $sep = '';
            }

            // Push a placeholder for the value.
//            $scope->push();
            $scope = new Scope($context);
            $scope->pushData();

            $i = 0;
            $count = count($context);

            // Loop through the context and call the template on each one.
            $first = true;
            foreach ($context as $key => $value) {
                $scope->replace($value);
                $scope->replaceData([
                        'index' => $i,
                        'key' => $key,
                        'first' => $first,
                        'last' => $i === $count - 1
                        ]);

                // Echo the seperator between the items.
                if ($first === true) {
                    $first = false;
                } else {
                    echo $sep;
                }

                // Call the item template.
                call_user_func($options['fn'], $value, $scope, $options, $key);
                $i++;
            }

//            $scope->pop();
            $scope->popData();
        }
    }

    /**
     * Render a compiled template and return the resulting string.
     * @param callable $compiled_template
     * @param array $data
     * @return string
     */
    public function fetch(callable $compiled_template, array $data) {
        $this->pushErrorReporting();

        try {
            ob_start();
            $compiled_template($data);
            $result = ob_get_clean();
        } catch(Exception $ex) {
            $result = ob_get_clean();
        }

        $this->popErrorReporting();

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

    public function partial($name, $context, $scope) {
        if (isset($this->partials[$name])) {
            call_user_func($this->partials[$name], $context, $this);
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
     */
    public function registerPartial($name, $partial, $force_compile = false) {
        if (!$force_compile && is_callable($partial)) {
            $this->partials[$name] = $partial;
        } else {
            $fn = $this->compile($partial);
            $this->partials[$name] = $fn;
        }
    }

    /**
     * A helper that implements the runtime of block sections.
     * @param array $context
     * @param array $options
     */
    public static function section($context, Scope $scope, $options) {


        if (empty($context)) {
            return;
        } elseif (is_array($context)) {
            if (isset($context[0])) {
                // This is a numeric array and is looped.

                // Push a placeholder for the loop.
                $scope->push([]);
                $scope->pushData();

                $i = 0;
                $count = count($context);

                foreach ($context as $key => $context_row) {
                    $scope->replace($context_row);
                    $scope->replaceData([
                        'index' => $i,
                        'key' => $key,
                        'first' => $i === 0,
                        'last' => $i === $count - 1
                        ]);


                    $fn = $options['fn'];
                    $fn($context_row, $scope, $key);

                    $i++;
                }

                $scope->pop();
                $scope->popData();
            } else {
                // This is an object-like array and is like a with.
                $scope->push($context);
                $options['fn']($context, $scope);
                $scope->pop();
            }
        } else {
            // This is an atomic value and is like an if (true).
            $options['fn']($context, $scope);
        }
    }

    public static function with($context, Scope $scope, $options) {
        if (empty($context))
            return;

//        $scope->push($context);
        $scope = new Scope($context);

        $fn = $options['fn'];
        $fn($context, $scope, $options);

//        $scope->pop();
    }
}


function require_snidely($snidely, $path) {
    return require $path;
}