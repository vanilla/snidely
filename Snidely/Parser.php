<?php

/**
 * This file is part of Handlebars-php
 * Base on mustache-php https://github.com/bobthecow/mustache.php
 * re-write to use with handlebars
 *
 * PHP version 5.3
 *
 * @category  Xamin
 * @package   Handlebars
 * @author    fzerorubigd <fzerorubigd@gmail.com>
 * @copyright 2012 (c) ParsPooyesh Co
 * @license   GPLv3 <http://www.gnu.org/licenses/gpl-3.0.html>
 * @version   GIT: $Id$
 * @link      http://xamin.ir
 */

namespace Snidely;

/**
 * Handlebars parser (infact its a mustache parser)
 * This class is responsible for turning raw template source into a set of Mustache tokens.
 *
 * @category  Xamin
 * @package   Handlebars
 * @author    fzerorubigd <fzerorubigd@gmail.com>
 * @copyright 2012 (c) ParsPooyesh Co
 * @license   GPLv3 <http://www.gnu.org/licenses/gpl-3.0.html>
 * @version   Release: @package_version@
 * @link      http://xamin.ir
 */
class Parser {
   /**
    * Process array of tokens and convert them into parse tree
    *
    * @param array $tokens Set of
    *
    * @return array Token parse tree
    */
   public function parse(array $tokens = array()) {
      return $this->_buildTree(new \ArrayIterator($tokens));
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
    * @param ArrayIterator $tokens Stream of  tokens
    *
    * @return array Token parse tree
    *
    * @throws LogicException when nesting errors or mismatched section tags are encountered.
    */
   protected function _buildTree(\ArrayIterator $tokens) {
      $stack = array();

      $subsection = Tokenizer::NODES;
      do {
         $token = $tokens->current();
         $tokens->next();

         if ($token === null) {
            continue;
         } else {
            $this->buildArgs($token);

            switch ($token[Tokenizer::TYPE]) {
               case Tokenizer::T_ELSE:
                  $newNodes = array();
                  do {
                     $result = array_pop($stack);
                     if ($result === null) {
                        throw new \Exception('{{else}} tag found outside of a block section.');
                     }

                     if (!array_key_exists($subsection, $result) && isset($result[Tokenizer::TYPE]) && $result[Tokenizer::TYPE] === Tokenizer::T_SECTION) {
                        $result[$subsection] = $newNodes;
                        array_push($stack, $result);
                        $subsection = Tokenizer::INVERSE;

                        break 2;
                     } else {
                        array_unshift($newNodes, $result);
                     }
                  } while (true);

                  break;
               case Tokenizer::T_END_SECTION:
                  $newNodes = array();
                  do {
                     $result = array_pop($stack);
                     if ($result === null) {
                        throw new \Exception('Unexpected closing tag: /' . $token[Tokenizer::NAME]);
                     }

                     if (!key_exists($subsection, $result) && isset($result[Tokenizer::NAME]) && $result[Tokenizer::NAME] == $token[Tokenizer::NAME]
                     ) {
                        $result[$subsection] = $newNodes;

                        $result[Tokenizer::END] = $token[Tokenizer::INDEX];
                        array_push($stack, $result);

                        $subsection = Tokenizer::NODES;
                        break 2;
                     } else {
                        array_unshift($newNodes, $result);
                     }
                  } while (true);

                  break;
               default:
                  array_push($stack, $token);
            }
         }
      } while ($tokens->valid());

      return $stack;
   }

}