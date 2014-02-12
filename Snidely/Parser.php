<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2010 Justin Hileman
 * @copyright Modifications are Copyright (c) 2013-2014 Vanilla Forums Inc.
 * @license MIT
 * @package Snidely
 * @link https://github.com/bobthecow/mustache.php/blob/master/src/Mustache/Parser.php Original file.
 */

namespace Snidely;

/**
 * Mustache Parser class.
 *
 * This class is responsible for turning a set of Mustache tokens into a parse tree.
 */
class Parser {
    private $lineNum;
    private $lineTokens;

    /**
     * Process an array of Mustache tokens and convert them into a parse tree.
     *
     * @param array $tokens Set of Mustache tokens
     *
     * @return array Mustache token parse tree
     */
    public function parse(array $tokens = array())
    {
        $this->lineNum    = -1;
        $this->lineTokens = 0;

        return $this->buildTree($tokens);
    }

    protected function buildArgs(&$node) {
      if (!isset($node[Tokenizer::ARGS]))
         return;

      $args = array();
      $arg = array();

      $hash = array();
      $key = null;

      foreach ($node[Tokenizer::ARGS] as $argnode) {
         switch ($argnode[Tokenizer::TYPE]) {
            case Tokenizer::T_SPACE:
               if ($arg) {
                  if ($key)
                     $hash[$key] = $arg;
                  else
                     $args[] = $arg;

                  $arg = array();
                  $key = null;
               }
               break;
            case Tokenizer::T_KEY:
               $key = $argnode[Tokenizer::VALUE];
               break;
            default:
               $arg[] = $argnode;
               break;
         }
      }

      if ($arg) {
         if ($key)
            $hash[$key] = $arg;
         else
            $args[] = $arg;
      }

      $node[Tokenizer::ARGS] = $args;
      if ($hash) {
         $node[Tokenizer::HASH] = $hash;
      }
   }

    /**
     * Helper method for recursively building a parse tree.
     *
     * @throws SyntaxException when nesting errors or mismatched section tags are encountered.
     *
     * @param array &$tokens Set of Mustache tokens
     * @param array  $parent Parent token (default: null)
     *
     * @return array Mustache Token parse tree
     */
    private function buildTree(array &$tokens, array $parent = null)
    {
        $nodes = array();

        while (!empty($tokens)) {
            $token = array_shift($tokens);
            $this->buildArgs($token);

            if ($token[Tokenizer::LINE] === $this->lineNum) {
                $this->lineTokens++;
            } else {
                $this->lineNum    = $token[Tokenizer::LINE];
                $this->lineTokens = 0;
            }

            switch ($token[Tokenizer::TYPE]) {
                case Tokenizer::T_DELIM_CHANGE:
                    $this->clearStandaloneLines($nodes, $tokens);
                    break;

                case Tokenizer::T_SECTION:
                case Tokenizer::T_INVERTED:
                    $this->clearStandaloneLines($nodes, $tokens);
                    $nodes[] = $this->buildTree($tokens, $token);
                    break;

                case Tokenizer::T_END_SECTION:
                    if (!isset($parent)) {
                        $msg = sprintf('Unexpected closing tag: /%s', $token[Tokenizer::NAME]);
                        throw new SyntaxException($msg, $token);
                    }

                    if ($token[Tokenizer::NAME] !== $parent[Tokenizer::NAME]) {
                        $msg = sprintf('Nesting error: %s vs. %s', $parent[Tokenizer::NAME], $token[Tokenizer::NAME]);
                        throw new SyntaxException($msg, $token);
                    }

                    $this->clearStandaloneLines($nodes, $tokens);
                    $parent[Tokenizer::END]   = $token[Tokenizer::INDEX];

                    if (array_key_exists(Tokenizer::INVERSE, $parent)) {
                        $parent[Tokenizer::INVERSE] = $nodes;
                    } else {
                        $parent[Tokenizer::NODES] = $nodes;
                    }

                    return $parent;
                    break;
                case Tokenizer::T_ELSE:
                    if (!isset($parent)) {
                        $msg = sprintf('Unexpected else tag: /%s', $token[Tokenizer::NAME]);
                        throw new SyntaxException($msg, $token);
                    }

                    $this->clearStandaloneLines($nodes, $tokens);

                    // The else will end off the current nodes.
                    $parent[Tokenizer::NODES] = $nodes;
                    // Set up the inverse.
                    $parent[Tokenizer::INVERSE] = null;
                    $nodes = array();

                    break;
                case Tokenizer::T_PARTIAL:
                case Tokenizer::T_PARTIAL_2:
                    // store the whitespace prefix for laters!
                    if ($indent = $this->clearStandaloneLines($nodes, $tokens)) {
                        $token[Tokenizer::INDENT] = $indent[Tokenizer::VALUE];
                    }
                    $nodes[] = $token;
                    break;

                case Tokenizer::T_PRAGMA:
                case Tokenizer::T_COMMENT:
                    $this->clearStandaloneLines($nodes, $tokens);
                    $nodes[] = $token;
                    break;
                default:
                    $nodes[] = $token;
                    break;
            }
        }

        if (isset($parent)) {
            $msg = sprintf('Missing closing tag: %s', $parent[Tokenizer::NAME]);
            throw new SyntaxException($msg, $parent);
        }

        return $nodes;
    }

    /**
     * Clear standalone line tokens.
     *
     * Returns a whitespace token for indenting partials, if applicable.
     *
     * @param array  $nodes  Parsed nodes.
     * @param array  $tokens Tokens to be parsed.
     *
     * @return array Resulting indent token, if any.
     */
    private function clearStandaloneLines(array &$nodes, array &$tokens)
    {
        if ($this->lineTokens > 1) {
            // this is the third or later node on this line, so it can't be standalone
            return null;
        }

        $prev = null;
        if ($this->lineTokens === 1) {
            // this is the second node on this line, so it can't be standalone
            // unless the previous node is whitespace.
            if ($prev = end($nodes)) {
                if (!$this->tokenIsWhitespace($prev)) {
                    return null;
                }
            }
        }

        $next = null;
        if ($next = reset($tokens)) {
            // If we're on a new line, bail.
            if ($next[Tokenizer::LINE] !== $this->lineNum) {
                return null;
            }

            // If the next token isn't whitespace, bail.
            if (!$this->tokenIsWhitespace($next)) {
                return null;
            }

            if (count($tokens) !== 1) {
                // Unless it's the last token in the template, the next token
                // must end in newline for this to be standalone.
                if (substr($next[Tokenizer::VALUE], -1) !== "\n") {
                    return null;
                }
            }

            // Discard the whitespace suffix
            array_shift($tokens);
        }

        if ($prev) {
            // Return the whitespace prefix, if any
            return array_pop($nodes);
        }

        return null;
    }

    /**
     * Check whether token is a whitespace token.
     *
     * True if token type is T_TEXT and value is all whitespace characters.
     *
     * @param array $token
     *
     * @return boolean True if token is a whitespace token
     */
    private function tokenIsWhitespace(array $token)
    {
        if ($token[Tokenizer::TYPE] == Tokenizer::T_TEXT) {
            return preg_match('/^\s*$/', $token[Tokenizer::VALUE]);
        }

        return false;
    }
}
