<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2013-2014 Vanilla Forums Inc.
 * @license MIT
 * @package Snidely
 */

namespace Snidely;

class PhpCompiler extends Compiler {

    const MUSTACHE_CONTEXT = 0x1;
    const MUSTACHE_HELPERS = 0x2;
    const MUSTACHE = 0xF;

    const HANDLEBARS_IF = 0x100; // whether or not to push the scope on if statements.
    const HANDLEBARS_HELPERS = 0x200;
    const HANDLEBARS_JS_STRINGS = 0x400; // whether or not to return javascript compatible handlebars strings.
    const HANDLEBARS = 0xF00;

    /// Properties ///

    protected $flags = 0;

    protected $php = true;
    protected $str = false;

    protected $helpersClass;

    protected $escapeFormat = 'htmlspecialchars(%s)';
    protected $unescapeFormat = '%s';

    /// Methods ///

    public function __construct($snidely, $flags = 0) {
        $this->snidely = $snidely;
        $this->flags = $flags;

        if ($this->flags & self::MUSTACHE_HELPERS) {
            $this->helpersClass = '\Snidely\MustacheHelpers';
        } elseif ($this->flags & self::HANDLEBARS_HELPERS) {
            $this->helpersClass = '\Snidely\HandlebarsHelpers';
        } else {
            $this->helpersClass = '\Snidely\Helpers';
        }

        if ($this->flags & self::HANDLEBARS_JS_STRINGS) {
            $this->escapeFormat = 'HandlebarsHelpers::escapeStr(%s)';
            $this->unescapeFormat = 'HandlebarsHelpers::str(%s)';
        }

        if (!($this->flags & self::HANDLEBARS_IF)) {
            $this->registerCompileHelper('if', [$this, 'ifHelper']);
            $this->registerCompileHelper('unless', [$this, 'unlessHelper']);
        }
//        $this->snidely->registerHelper('each', ['\Snidely\Snidely', 'each']);
//        $this->snidely->registerHelper('with', ['\Snidely\Snidely', 'with']);

        call_user_func(array($this->helpersClass, 'registerHelpers'), $this->snidely);
        call_user_func(array($this->helpersClass, 'registerBuiltInHelers'), $this->snidely);
    }

    public function comment($node, $indent) {
        $comment = preg_replace('`([\n\r\b]+\s*)`', "\n".$this->indent($indent).'// ', $node[Tokenizer::NAME]);

        $result = $this->str().$this->php(true, $indent)
                . '// '.$comment."\n";

        return $result;
    }

    public function compile($nodes) {
        $result = $this->php(true)
                . "namespace Snidely {\n\n"
                . "return function(\$context) use (\$snidely) {\n"
                . $this->indent(1).'$scope = new Scope($context);'."\n\n"
                . $this->compileClosure($nodes, 0, false)
                . $this->str().$this->php(true)."};\n"
                ."\n}\n";

        return $result;
    }

    public function compileClosure($nodes, $indent, $declaration = true) {
        $result = $this->php(true);

        if ($declaration)
            $result .= "function(\$context, \$scope) use (\$snidely) {\n";
        $result .= $this->compileNodes($nodes, $indent + 1);
        if ($declaration)
            $result .= $this->str().$this->php(true, $indent) . '}';

        return $result;
    }

    public function str($str = null, $indent = 0) {
        if ($str === null) {
            if ($this->str) {
                $this->str = false;
                return ";\n";
            }
        } else {
            if ($this->str) {
                return "\n".$this->indent($indent).'   , '.$str;
            } else {
                $this->str = true;
                return $this->indent($indent).'echo '.$str;
            }
        }
        return '';
    }

    public function escaped($node, $indent) {
        $result = $this->php(true) .
                $this->indent($indent) .
                $this->str(
                    sprintf($this->escapeFormat, $this->getContext($node)),
                    $indent);

        return $result;
    }

    public function getContext($path, $i = 0, $node = null) {
        if (isset($path[Tokenizer::ARGS]))
            $path = $path[Tokenizer::ARGS][$i];

        if ($this->flags & self::MUSTACHE_CONTEXT)
            $var = '$scope';
        else
            $var = '$context';

        $paren = ['[', ']'];
        $first = true;
        $result = '';
        $root_count = 0;

        foreach ($path as $part) {
            if (!is_array($part)) {
                throw new \Exception(print_r($path, true));
            }

            $type = $part[Tokenizer::TYPE];
            $value = $part[Tokenizer::VALUE];

            switch ($type) {
                case Tokenizer::T_DOT:
                    switch ($value) {
                        case '.':
                        case 'this':
                            if ($first) {
                                $var = '$context';
                            }
                            break;
                        case '..':
                            $root_count++;

                            if ($root_count === 1) {
                                $var = '$scope->root';
                            } else {
                                $var = "\$scope->root(-$root_count)";
                            }
                            $var = "\$scope->root(-$root_count)";

                            break;
                    }

                    break;
                case Tokenizer::T_VAR:
                    if ($first && $value === 'this') {
                        // this is a synonym for "."
                        $var = '$context';
                    } elseif (in_array($value, array('@key', '@index', '@first', '@last'))) {
                        // These are special variables that refer to the index of a loop.
                        if (count($path) == 1) {
                            $value = substr($value, 1);
                            $var = '$scope->data';

                            $result .= '(' . var_export($value, true) . ')';
                        } else {
                            $result .= '[0]'; // should be an error
                        }
                    } else {
                        $result .= $paren[0] . var_export($value, true) . $paren[1];
                    }
                    break;
                case Tokenizer::T_STRING:
                    return var_export($value, true);
                default:
                    return var_export("error: unknown type $type", true);
            }

            $first = false;
        }

        return $var . $result;
    }

    /**
     * Get the expression that represents the $options argument passed to a helper.
     *
     * @param array $node The compiler node to get the options for.
     * @param int $indent The current compiler indent.
     * @param bool $force Whether or not to force an options return event if it is null.
     * @return string The options expression.
     */
    protected function getOptions($node, $indent, $force = false) {
        $this->php(true);

        $options = [];

        // First add the hash to the table.
        if (isset($node[Tokenizer::HASH])) {
            foreach ($node[Tokenizer::HASH] as $key => $arg) {
                $options['hash'][$key] = $this->getContext($arg, 0, $node);
            }
        }

        // Is there a section.
        if (!empty($node[Tokenizer::NODES])) {
            $options['fn'] = $this->compileClosure($node[Tokenizer::NODES], $indent);
        }

        // Is there an inverse section.
        if (!empty($node[Tokenizer::INVERSE])) {
            // TODO: This has to be rendered as a closure.
            $options['inverse'] = $this->compileClosure($node[Tokenizer::INVERSE], $indent);
        }

        // Put the options into array syntax.
        foreach ($options as $key => &$value) {
            if (is_array($value)) {
                foreach ($value as $hkey => &$hval) {
                    $hval = var_export($hkey, true) . ' => ' .$hval;
                }
                $value = var_export($key, true) . ' => [' . implode(', ', $value).']';
            } else {
                $value = var_export($key, true) . ' => ' . $value;
            }
        }

        if ($force || !empty($options))
            return '[' . implode(', ', $options) . ']';
        else
            return null;
    }

    /**
     *
     * @param array $node
     * @param int $indent
     * @param type $helper
     * @return string|null
     */
    public function helper($node, $indent, $helper) {
        $result = $this->str().$this->php(true, $indent);
        $call = null;
        $params = null;
        $call_comma = '';

        if ($node['name'] === 'awesome') {
            $foo = 'bar';
        }

        // Try and make the helper call nice.
        if (is_string($helper)) {
            // This is a simple function call.
            $call = $helper . '(';

            if (is_callable($helper)) {
                $rfunction = new \ReflectionFunction($helper);
                $params = $rfunction->getParameters();
            }
        } elseif (is_array($helper)) {
            if (is_string($helper[0])) {
                // This is a static method call.
                $call = "{$helper[0]}::{$helper[1]}(";
            }

            if (is_callable($helper)) {
                $rfunction = new \ReflectionMethod($helper[0], $helper[1]);
                $params = $rfunction->getParameters();
            }
        }

        if (!isset($call)) {
            // There is no nice call so we just need to call it at runtime.
            $call = 'call_user_func($snidely->helpers[' . var_export($node[Tokenizer::NAME], true) . ']';
            $call_comma = ', ';

            if (is_callable($helper)) {
                $rfunction = new \ReflectionFunction($helper);
                $params = $rfunction->getParameters();
            }
        }

        // Try and reflect the arguments out of the
        $node_args = [];
        if (isset($node[Tokenizer::ARGS])) {
            $node_args = $node[Tokenizer::ARGS];
            array_shift($node_args);
        }

        // Add all of the parameters to the call.
        $args = [];
        $options_passed = false;
        if (isset($params)) {
            // Use reflection to assign the parameters properly.
            $i = 0;
            foreach ($params as $i => $param) {
                $param_name = $param->getName();
                $default = null;
                if ($param->isDefaultValueAvailable())
                    $default = $param->getDefaultValue();

                if (isset($node_args[$i])) {
                    // The argument was passed in order.
                    $args[$i] = $this->getContext($node_args[$i], 0, $node);
                } elseif (isset($node[Tokenizer::HASH][$param_name])) {
                    // The argument was passed by name.
                    $args[$i] = $this->getContext($node[Tokenizer::HASH][$param_name]);

                    // Unset the argument from the hash so that it doesn't also come in the options.
                    unset($node[Tokenizer::HASH][$param_name]);
                } elseif (in_array(strtolower($param_name), array('context', 'scope', 'snidely'))) {
                    // This argument takes the current context or the scope.
                    $args[$i] = '$'.strtolower($param_name);
                } elseif (strtolower($param_name) === 'prev') {
                    $args[$i] = '$context';
                } elseif (strtolower($param_name) === 'options') {
                    $args[$i] = $this->getOptions($node, $indent, true);
                    $options_passed = true;
                } else {
                    // Pass the default value.
                    $args[$i] = var_export($default, true);
                }
            }

            // Add any additional arguments that were passed.
            // We only do this for user-definied functions because most built-in functions throw an error if too many arguments are passed.
            if (!isset($rfunction) || $rfunction->isUserDefined()) {
                for ($i = count($args); $i < count($node_args); $i++) {
                    $args[$i] = $this->getContext($node_args[$i]);
                }
            }

            // Add the options.
            if (!$options_passed) {
                $options = $this->getOptions($node, $indent);
                if ($options)
                    $args[] = $options;
            }
        } else {
            // Just assign the parameters to the function.
            foreach ($node_args as $node_arg) {
                $args[] = $this->getContext($node_arg);
            }
        }

        if (!empty($args)) {
            $call .= $call_comma;
        }
        $call .= implode(', ', $args) . ')';

        if ($call) {
            switch ($node[Tokenizer::TYPE]) {
                case Tokenizer::T_ESCAPED:
                    $result .= $this->str(sprintf($this->escapeFormat, $call), $indent - 1);
                    break;
                case Tokenizer::T_UNESCAPED:
                case Tokenizer::T_UNESCAPED_2:
                    $result .= $this->str(sprintf($this->unescapeFormt, $call), $indent - 1);
                    break;
                default;
                    $result .= "\n"
                            . $this->getSnidely($node, $indent)."\n"
                            . $this->indent($indent).$call.";\n\n";
                    break;
            }

            return $result;
        }
        return null;
    }

    public function inverted($node, $indent) {
        $result = $this->str()."\n"
                . $this->getSnidely($node, $indent);

        $node[Tokenizer::ARGS] = array_merge($node[Tokenizer::ARGS], $node[Tokenizer::ARGS]);
        $result .= $this->_ifHelper($node, $indent, '!');
        return $result;
    }

    protected function ifHelper($node, $indent) {
        $result = $this->str()."\n"
                . $this->getSnidely($node, $indent)
                . $this->_ifHelper($node, $indent);
        return $result;
    }

    protected function _ifHelper($node, $indent, $mod = '') {
        $result = $this->php(true).$this->str();
        $context = $this->getContext($node, 1);

        $result .= "\n";

        // Push the context because handlebars does.
        if ($this->flags & self::HANDLEBARS_IF) {
            $result .= $this->indent($indent)."\$scope->push(\$context);\n";
        }

        $result .= $this->indent($indent)."if ({$mod}{$context}) {\n";

        $result .= $this->compileNodes($node[Tokenizer::NODES], $indent + 1);

        if (!empty($node[Tokenizer::INVERSE])) {
            $result .= $this->str().$this->php(true, $indent)."} else {\n";

            $result .= $this->compileNodes($node[Tokenizer::INVERSE], $indent + 1);
        }

        $result .= $this->str().$this->php(true, $indent)."}\n";

        if ($this->flags & self::HANDLEBARS_IF) {
            $result .= $this->indent($indent)."\$scope->pop();\n";
        }

        $result .= "\n";

        return $result;
    }

    protected function literalText($val) {
        if (substr($val, -1) === "\n") {
            if (strlen($val) === 1)
                return '"\n"';
            else
                return var_export(substr($val, 0, -1), true).'."\n"';
        } else  {
            return var_export($val, true);
        }
    }

    public function partial($node, $indent) {
        $result = '';

        // First render out an indent if one exists.
        if (isset($node[Tokenizer::INDENT])) {
            $result .= $this->str(var_export($node[Tokenizer::INDENT], true), $indent);
        }

        $result .= $this->str().$this->php(true);

        $name = $node[Tokenizer::NAME];

        $result .= "\n"
                . $this->getSnidely($node, $indent)."\n"
                . $this->indent($indent).'$snidely->partial('
                . var_export($name, true)
                . ", \$context, \$scope);\n\n";

        return $result;
    }

    public function php($value, $indent = 0) {
        if ($value == $this->php)
            return $this->indent($indent);

        $this->php = $value;
        if ($this->php)
            return "<?php\n" . $this->indent($indent);
        else
            return "?>";
    }

    public function reset() {
        $this->php = false;
    }

    public function section($node, $indent) {
        if (empty($node[Tokenizer::NODES]))
            return '';

        $result = $this->str().$this->php(true);
        $context = $this->getContext($node);
        $options = $this->getOptions($node, $indent);

        $result .= "\n"
                . $this->getSnidely($node, $indent)."\n"
                . $this->indent($indent)."{$this->helpersClass}::section($context, \$scope, \$context, $options);\n\n";
        return $result;
    }

    public function text($node, $indent) {
        return $this->php(true).
               $this->str($this->literalText($node[Tokenizer::VALUE]), $indent);
    }

    public function unknown($node, $indent) {
        throw new \Exception("Unknown node type {$node['type']}", 500);
    }

    public function unescaped($node, $indent) {
        $result = $this->php(true, $indent).
                $this->str(sprintf($this->unescapeFormat, $this->getContext($node)), $indent);

        return $result;
    }

    protected function unlessHelper($node, $indent) {
        $result = $this->str()."\n"
                . $this->getSnidely($node, $indent)
                . $this->_ifHelper($node, $indent, '!');
        return $result;
    }
}
