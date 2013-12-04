<?php
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
    public $cachePath;

    /**
     * @var array An array of helper functions for the template.
     */
    public $helpers = array();

    /**
     * @var array An array of partials for the template.
     */
    protected $partials = array();

    /**
     * @var int The pushed error reporting.
     */
    protected $pushedErrorReporting;

    /// Methods ///

    public function __construct() {
        $this->cachePath = __DIR__ . '/../cache/';
    }

    /**
     * @param string $template
     * @param Snidely\Compiler $compiler
     */
    public function compile($template, $key = null) {
        if ($key === null)
            $key = md5($template);

        $path = $this->cachePath."$key.php";
        if (true || !file_exists($path)) {
            $php = "<?php\n".$this->precompile($template);
            $this->file_put_contents_atomic($path, $php, $this->cacheFileMode);
        }

        return require_snidely($this, $path);
    }

    public function precompile($template) {
        $nodes = $this->parse($template);

        $compiler = new PhpCompiler();
        $compiler->snidely = $this;

        return $compiler->compile($nodes);
    }

    /**
     * A helper that implents the runtime version of the {{#each }} section.
     * @param array $context
     * @param array $options
     */
    public static function each($context, $options) {
        if (empty($context)) {
            // The context is empty so display the inverse template.
            if (isset($options['inverse'])) {
                call_user_func($options['inverse'], $context, $options);
            }
        } else {
            // Get the item seperator if any.
            if (isset($options['hash']['sep'])) {
                $sep = $options['hash']['sep'];
            } else {
                $sep = '';
            }

            // Loop through the context and call the template on each one.
            $first = true;
            foreach ($context as $key => $value) {
                $context['@index'] = $context['@key'] = $key;

                // Echo the seperator between the items.
                if ($first) {
                    $first = false;
                } else {
                    echo $sep;
                }

                // Call the item template.
                call_user_func($options['fn'], $value, $options);
            }
        }
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

    public function partial($name, $context, $root) {
        if (isset($this->partials[$name])) {
            call_user_func($this->partials[$name], $context, $root);
        } else {
            echo "\n<!-- Could not find partial $name -->\n";
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
            $this->pushedErrorReporting = error_reporting(error_reporting() & ~E_NOTICE);
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
    public static function section($context, $root, $options) {
        if (empty($context)) {
            return;
        } elseif (is_array($context) && isset($context[0])) {
            foreach ($context as $key => $context_row) {
                $fn = $options['fn'];
                $context_row['@index'] = $context_row['@key'] = $key;
                $fn($context_row, $root);
                //         call_user_func($options['fn'], $context_row, $key);
            }
        } else {
            $options['fn']($context, $root);
        }
    }

    public static function with($context, $options) {
        $fn = $options['fn'];
        $fn($context, $options);
    }
}


function require_snidely($snidely, $path) {
    return require $path;
}