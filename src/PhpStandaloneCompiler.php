<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Snidely;


class PhpStandaloneCompiler extends PhpCompiler {
    /**
     * @param Snidely $snidely
     * @param int $flags
     */
    public function __construct($snidely, $flags = 0) {
        parent::__construct($snidely, $flags);

        $snidely->helpers = [];

        $this->registerCompileHelper('each', [$this, 'eachHelper']);
    }


    public function compile($nodes) {
        $result = $this->php(true)
            . "function(\$context) {\n"
            . $this->indent(1).'$depth0 = $context;'."\n\n"
            . $this->compileClosure($nodes, 0, false)
            . $this->str().$this->php(true)."};\n";

        return $result;
    }

    protected function eachHelper($node, $indent) {
        $result = $this->php(true).$this->str();
        $context = $this->getContext($node, 1);
        $this->depth++;

        $childIndex = '$i'.$this->depth;
        $childContext = '$depth'.$this->depth;

        $result .= "\n";

        if (!empty($node[Tokenizer::INVERSE])) {
            $result .= $this->indent($indent)."if (empty({$context})) {\n";
            $result .= $this->compileNodes($node[Tokenizer::INVERSE], $indent + 1);
            $result .= $this->str().$this->php(true, $indent)."}\n\n";
        }

        $result .= $this->indent($indent)."foreach ({$context} as {$childIndex} => {$childContext}) {\n";
        $result .= $this->compileNodes($node[Tokenizer::NODES], $indent + 1);
        $result .= $this->str().$this->php(true, $indent)."}\n";
        $result .= "\n";

        $this->depth--;

        return $result;
    }

    /**
     * Return the variable to grab the context data from.
     *
     * @param int $parent The relative level to the current context.
     * 0 for current, 1 for the parent, 2 for the grandparent, etc.
     * @return string Returns the variable string.
     */
    protected function getContextVar($parent = 0) {
        $depth = max(0, $this->depth - $parent);
        return '$depth'.$depth;
    }
}