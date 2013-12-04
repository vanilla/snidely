<?php

namespace Snidely;

class PhpCompiler extends Compiler {

    /// Properties ///

    protected $php = true;
    protected $str = false;

    /// Methods ///

    public function __construct() {
        $this->registerCompileHelper('if', array($this, 'ifHelper'));
        $this->registerCompileHelper('unless', array($this, 'unlessHelper'));
//        $this->registerCompileHelper('with', array($this, 'withHelper'));

//        $this->registerHelper('each', array($this, 'eachHelper'));
    }

    public function compile($nodes) {
        $this->snidely->registerHelper('each', array('Snidely', 'each'));
        $this->snidely->registerHelper('with', array('Snidely', 'with'));

        $nodes = $this->stripStandalone($nodes);

        $result = $this->php(true)
                . "return function(\$context, \$root = null) use (\$snidely) {\n"
                . $this->indent(1).'if ($root === null) $root = $context;'."\n\n"
                . $this->compileClosure($nodes, 0, false)
                . $this->str().$this->php(true)."};\n";

        return $result;
    }

    public function compileClosure($nodes, $indent, $declaration = true) {
        $result = $this->php(true);

        if ($declaration)
            $result .= "function(\$context, \$root = null, \$key = null) use (\$snidely) {\n";
        $result .= $this->compileNodes($nodes, $indent + 1);
        if ($declaration)
            $result .= $this->str().$this->php(true, $indent) . '}';

        return $result;
    }

    public static function each($context, $options) {
    }

    protected function eachHelper($node, $indent) {
        if (empty($node[Tokenizer::INVERSE])) {
            $result = $this->foreachHelper($node);
        } else {
            $result = $this->php(true);
            $context = $this->getContext($node, 1);

            $result .= "if(!empty($context) && is_array($context)):";

            $result .= $this->foreachHelper($node, $indent + 1);

            $result .= $this->php(true) . "\nelse:\n";

            $result .= $this->compileNodes($node[Tokenizer::INVERSE], $indent + 1);

            $result .= $this->php(true) . "\nendif;\n";
        }

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
    }

    public function escaped($node, $indent) {
        $result = $this->php(true) .
                $this->indent($indent) .
                $this->str(
                    'htmlspecialchars(' .
                    $this->getContext($node) .
                    ")", $indent);

        return $result;
    }

    public function getContext($path, $i = 0) {
        if (isset($path[Tokenizer::ARGS]))
            $path = $path[Tokenizer::ARGS][$i];

        $var = '$context';
        $result = '';

        foreach ($path as $part) {
            if (!is_array($part)) {
                throw new \Exception(print_r($path, true));
            }

            $type = $part[Tokenizer::TYPE];
            $value = $part[Tokenizer::VALUE];

            switch ($type) {
                case Tokenizer::T_DOT:
                    if ($value === '..')
                        $var = '$root';
                    break;
                case Tokenizer::T_VAR:
                    if (in_array($value, array('@key', '@index'))) {
                        // These are special variables that refer to the index of a loop.
                        if (count($path) == 1) {
                            return '$key';
                        } else {
                            $result .= '[$key]';
                        }
                    } else {
                        $result .= '[' . var_export($value, true) . ']';
                    }
                    break;
                case Tokenizer::T_STRING:
                    return var_export($value, true);
                default:
                    return var_export("error: unknown type $type", true);
            }
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

        $options = array();

        // First add the hash to the table.
        if (isset($node[Tokenizer::HASH])) {
            foreach ($node[Tokenizer::HASH] as $key => $arg) {
                $options['hash'][$key] = $this->getContext($arg);
            }
        }

        // Is there a section.
        if (!empty($node[Tokenizer::NODES])) {
            $options['fn'] = $this->compileClosure($node[Tokenizer::NODES], $indent);
        }

        // Is there an inverse section.
        if (!empty($node[Tokenizer::INVERSE])) {
            // TODO: This has to be rendered as a closure.
            $options['inverse'] = $this->compileClosure($node[Tokenizer::NODES], $indent);
        }

        // Put the options into array syntax.
        foreach ($options as $key => &$value) {
            if (is_array($value)) {
                foreach ($value as $hkey => &$hval) {
                    $hval = var_export($hkey, true) . ' => ' .$hval;
                }
                $value = var_export($key, true) . ' => array(' . implode(', ', $value).')';
            } else {
                $value = var_export($key, true) . ' => ' . $value;
            }
        }

        if ($force || !empty($options))
            return 'array(' . implode(', ', $options) . ')';
        else
            return null;
    }

    public function helper($node, $indent, $helper) {
        $result = $this->str().$this->php(true, $indent);

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
            $call = 'call_user_func_array($helpers[' . var_export($helper, true) . '], ';
        }

        // Try and reflect the arguments out of the
        $node_args = array();
        if (isset($node[Tokenizer::ARGS])) {
            $node_args = $node[Tokenizer::ARGS];
            array_shift($node_args);
        }

        // Add all of the parameters to the call.
        $args = array();
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
                    $args[$i] = $this->getContext($node_args[$i]);
                } elseif (isset($node[Tokenizer::HASH][$param_name])) {
                    // The argument was passed by name.
                    $args[$i] = $this->getContext($node[Tokenizer::HASH][$param_name]);

                    // Unset the argument from the hash so that it doesn't also come in the options.
                    unset($node[Tokenizer::HASH][$param_name]);
                } elseif (in_array(strtolower($param_name), array('context', 'root', 'snidely'))) {
                    // This argument takes the current context or the root.
                    $args[$i] = '$'.strtolower($param_name);
                } elseif (strtolower($param_name) === 'options') {
                    $args[$i] = $this->getOptions($node, $indent, true);
                    $options_passed = true;
                } else {
                    // Pass the default value.
                    $args[$i] = var_export($default, true);
                }
            }
            // Add any additional arguments that were passed.
            for ($i = $i; $i < count($node_args); $i++) {
                $args[$i] = $this->getContext($node_args[$i]);
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

        $call .= implode(', ', $args) . ')';

        if ($call) {
            switch ($node[Tokenizer::TYPE]) {
                case Tokenizer::T_ESCAPED:
                    $result .= $this->str('htmlspecialchars(' . $call . ')', $indent);
                    break;
                case Tokenizer::T_UNESCAPED:
                case Tokenizer::T_UNESCAPED2:
                    $result .= $this->str($call, $indent);
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
        $node[Tokenizer::ARGS] = array_merge($node[Tokenizer::ARGS], $node[Tokenizer::ARGS]);

        return $this->unlessHelper($node, $indent);
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

        // Push the context.
        $result .= "\n".$this->indent($indent)."if ({$mod}{$context}) {\n";

        $result .= $this->compileNodes($node[Tokenizer::NODES], $indent + 1);

        if (!empty($node[Tokenizer::INVERSE])) {
            $result .= $this->str().$this->php(true, $indent)."} else {\n";

            $result .= $this->compileNodes($node[Tokenizer::INVERSE], $indent + 1);
        }

        $result .= $this->str().$this->php(true, $indent)."}\n\n";

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
        $result = $this->str().$this->php(true);

        $name = $node[Tokenizer::NAME];

        $result .= "\n"
                . $this->getSnidely($node, $indent)."\n"
                . $this->indent($indent).'$snidely->partial('
                . var_export($name, true)
                . ", \$context, \$root);\n\n";

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
                . $this->indent($indent)."Snidely::section($context, \$root, $options);\n\n";
        return $result;

        $result .= "if (!empty($context)):\n" .
                "array_push(\$context, \$contexts);\n" .
                "\$section_context = $context;\n";

        $this->indent++;

        // Render an associative array like a with.
        // Nest it into an array so it can render like a foreach.
        $result .= "if (!isset(\$section_context[0]))\n" .
                "\$section_context = array(\$section_context);\n";

        $result .= "foreach (\$section_context as \$context):\n";
        $this->indent++;

        $result .= $this->compileNodes($node[Tokenizer::NODES]);

        $this->indent--;
        $result .= $this->php(true) . "endforeach;\n" .
                "\$context = array_pop(\$contexts);\n";

        $this->indent--;
        $result .= $this->php(true) . "endif;\n";

        return $result;
    }

    public function text($node, $indent) {
        return $this->php(true).
               $this->str($this->literalText($node[Tokenizer::VALUE]), $indent);

//      return $this->php(false).$node[Tokenizer::VALUE];
    }

    public function unknown($node, $indent) {
        throw new \Exception("Unknown node type {$node['type']}", 500);

        $text = "\n<pre><b>unknown node type: {$node['type']}</b>\n" .
                json_encode($node, JSON_PRETTY_PRINT) .
                "</pre>\n";

        $result = $this->php(true, $indent) . 'echo ' . var_export($text, true) . ";\n";
        return $result;
    }

    public function unescaped($node, $indent) {
        $result = $this->php(true, $indent).
                $this->str($this->getContext($node), $indent);

        return $result;
    }

    protected function unlessHelper($node, $indent) {
        $result = $this->str()."\n"
                . $this->getSnidely($node, $indent)
                . $this->_ifHelper($node, $indent, '!');
        return $result;
    }

    protected function withHelper($node) {
        $result = $this->php(true);
        $context = $this->getContext($node, 1);

        // Push the context.
        $result .= "\narray_push(\$contexts, \$context);\n" .
                "\$context = $context;\n";

        $this->indent++;
        $result .= $this->compileNodes($node[Tokenizer::NODES]);
        $this->indent--;

        $result .= $this->php(true) .
                '$context = array_pop($contexts);' . "\n";

        return $result;
    }

}
