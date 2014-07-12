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
        $this->registerCompileHelper('with', [$this, 'withHelper']);
    }


    public function compile($nodes) {
        $result = $this->php(true)
            . "function(\$context) {\n"
            . $this->indent(1).'$depth0 = $context;'."\n\n"
            . $this->compileClosure($nodes, 0, false)
            . $this->str().$this->php(true)."};\n";

        return $result;
    }

    /**
     * A helper that compiles `{{#each}}` nodes.
     * 
     * @param array $node The compiler node.
     * @param int $indent The current indent.
     * @return string Returns the compile php code for the code.
     */
    protected function eachHelper(array $node, $indent) {
        $result = $this->php(true).$this->str();
        $context = $this->getContext($node, 1);
        $this->depth++;

        $childIndex = '$i'.$this->depth;
        $childContext = '$depth'.$this->depth;

        $result .= "\n".$this->getSnidely($node, $indent)."\n";

        if (!empty($node[Tokenizer::INVERSE])) {
            $result .= $this->indent($indent)."if (empty({$context})) {\n";
            $result .= $this->compileNodes($node[Tokenizer::INVERSE], $indent + 1);
            $result .= $this->str().$this->php(true, $indent)."}\n\n";
        }

        $result .= $this->indent($indent)."foreach ({$context} as {$childIndex} => {$childContext}) {\n";
        $result .= $this->compileNodes($node[Tokenizer::NODES], $indent + 1);
        $result .= $this->str().$this->php(true, $indent)."}\n\n";

        $this->depth--;

        return $result;
    }

    public function section($node, $indent) {
        $result = $this->php(true).$this->str();
        $context = $this->getContext($node, 0);
        $this->depth++;

        $childIndex = '$i'.$this->depth;
        $childContext = '$depth'.$this->depth;
        $sectionContext = '$section'.$this->depth;

        $result .= "\n".$this->getSnidely($node, $indent)."\n";

        $result .= $this->indent($indent)."if ({$context}) {\n";
        $indent++;

        $result .= $this->indent($indent).
                   "$sectionContext = is_array($context) && isset({$context}[0]) ? $context : [$context];\n";
        $result .= $this->indent($indent)."foreach ({$sectionContext} as {$childIndex} => {$childContext}) {\n";
        $result .= $this->compileNodes($node[Tokenizer::NODES], $indent + 1);
        $result .= $this->str().$this->php(true, $indent)."}\n";

        $indent--;
        $result .= $this->str().$this->php(true, $indent)."}";

        if (!empty($node[Tokenizer::INVERSE])) {
            $result .= $this->indent($indent)." else {\n";
            $result .= $this->compileNodes($node[Tokenizer::INVERSE], $indent + 1);
            $result .= $this->str().$this->php(true, $indent)."}\n\n";
        }

        $this->depth--;

        $result .= "\n\n";

        return $result;
    }

    protected function withHelper($node, $indent) {
        $result = $this->php(true).$this->str();
        $context = $this->getContext($node, 1);
        $this->depth++;
        $childContext = '$depth'.$this->depth;

        $result .= "\n".$this->getSnidely($node, $indent)."\n";

        // Write the with variable assignment.
        $result .= $this->php(true, $indent)."$childContext = $context;\n";

        $result .= $this->indent($indent)."if ($childContext) {\n";
        $result .= $this->compileNodes($node[Tokenizer::NODES], $indent + 1);
        $result .= $this->str().$this->php(true, $indent)."}";

        if (!empty($node[Tokenizer::INVERSE])) {
            $result .= $this->indent($indent)." else {\n";
            $result .= $this->compileNodes($node[Tokenizer::INVERSE], $indent + 1);
            $result .= $this->str().$this->php(true, $indent)."}";
        }
        $result .= "\n\n";

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