<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2013-2014 Vanilla Forums Inc.
 * @license MIT
 * @package Snidely
 */

namespace Snidely;

class ArgsTokenizer {
   const IN_VAR = 0;
   const IN_LITERAL = 1;
   const IN_STRING = 2;
   const IN_DOT = 3;

   // Token properties

   /// Properties ///

   protected $state;
   protected $argType;
   protected $buffer;
   protected $tokens;

   /// Methods ///

   /**
    * Flush the current buffer to a token.
    *
    * @return void
    */
   protected function flushBuffer($type = Tokenizer::T_VAR) {
      if (strlen($this->buffer) > 0) {
         $this->tokens[] = $token = array(Tokenizer::TYPE => $type, Tokenizer::VALUE => $this->buffer);
         $this->buffer = '';
      }
   }

   /**
    * Get the single name from the
    * @param type $tokens
    */
//   public function getName($tokens) {
//       foreach ($tokens as $token) {
//           switch ($token[Tokenizer::TYPE]) {
//               case Tokenizer::T_VAR:
//                   return $token[Tokenizer::VALUE];
//           }
//       }
//       return '';
//   }

   /**
    * Helper function to reset tokenizer internal state.
    *
    * @return void
    */
   protected function reset() {
      $this->state = self::IN_VAR;
      $this->argType = null;
      $this->buffer = '';
      $this->tokens = array();
   }

   public function scan($text) {
      $this->reset();

      $strlen = strlen($text);
      for ($i = 0; $i < $strlen; $i++) {
         $c = $text[$i];

         switch ($this->state) {
            case self::IN_LITERAL: // [literal]
               if ($c === Tokenizer::T_CLITERAL) {
                  $this->flushBuffer(Tokenizer::T_VAR);
                  $this->state = self::IN_VAR;
               } else {
                  $this->buffer .= $c;
               }

               break;
            case self::IN_STRING: // "string"
               if ($c === Tokenizer::T_QUOTE || $c === Tokenizer::T_QUOTE2) {
                  $this->flushBuffer(Tokenizer::T_STRING);
                  $this->state = self::IN_VAR;
               } else {
                  $this->buffer .= $c;
               }

               break;
            case self::IN_DOT:
               // Treat all whitespace as the same.
               if (preg_match('`\s`', $c))
                    $c = Tokenizer::T_SPACE;

               if ($c === Tokenizer::T_DOT) {
                  $this->buffer .= $c;
               } elseif ($c === Tokenizer::T_SLASH) {
                  $this->flushBuffer(Tokenizer::T_DOT);
                  $this->state = self::IN_VAR;
               } elseif ($c === Tokenizer::T_SPACE) {
                   $this->buffer = str_replace(Tokenizer::T_SLASH, Tokenizer::T_DOT, substr($this->buffer, 0, -1));
                   $this->flushBuffer(Tokenizer::T_DOT);
                   $this->state = self::IN_VAR;

                   // Add the space.
                   $this->tokens[] = array(Tokenizer::TYPE => Tokenizer::T_SPACE);
               } else {
                  $this->buffer = str_replace(Tokenizer::T_SLASH, Tokenizer::T_DOT, substr($this->buffer, 0, -1));
                  $this->flushBuffer(Tokenizer::T_DOT);
                  $this->state = self::IN_VAR;
                  $this->buffer .= $c;
               }

               break;
            default:
               // Treat all whitespace as the same.
               if (preg_match('`\s`', $c))
                  $c = Tokenizer::T_SPACE;

               switch ($c) {
                  case Tokenizer::T_OLITERAL: // open literal [
                     $this->flushBuffer();
                     $this->state = self::IN_LITERAL;
                     break;
                  case Tokenizer::T_QUOTE: // open quote " or '
                  case Tokenizer::T_QUOTE2:
                     $this->flushBuffer();
                     $this->state = self::IN_STRING;
                     break;
                  case Tokenizer::T_SPACE: // whitespace seprator
                     $this->flushBuffer();
                     if (sizeof($this->tokens) && $this->tokens[count($this->tokens) - 1][Tokenizer::TYPE] !== Tokenizer::T_SPACE)
                        $this->tokens[] = array(Tokenizer::TYPE => Tokenizer::T_SPACE);
                     break;
                  case Tokenizer::T_SLASH:
                  case Tokenizer::T_DOT:
                     if ($this->buffer)
                        $this->flushBuffer();
                     else {
                        $this->state = self::IN_DOT;
                        $this->buffer .= $c;
                     }
                     break;
                  case Tokenizer::T_EQUALS: // equals assignment
                     // This means that the previous token was a key.
                     $this->flushBuffer();
                     if (sizeof($this->tokens))
                        $this->tokens[count($this->tokens) - 1][Tokenizer::TYPE] = Tokenizer::T_KEY;

                     break;
                   default:
                      $this->buffer .= $c;
               }

               break;
         }
      }

      $map = array(self::IN_STRING => Tokenizer::T_STRING, self::IN_DOT => Tokenizer::T_DOT);

      $this->flushBuffer(array_key_exists($this->state, $map) ? $map[$this->state] : Tokenizer::T_VAR);

      $tokens = $this->tokens;
      $this->reset();
      return $tokens;
   }

   /**
    * Test whether it's time to change tags.
    *
    * @param string $tag   Current tag name
    * @param string $text  Mustache template source
    * @param int    $index Current tokenizer index
    *
    * @return boolean True if this is a closing section tag
    */
   protected function tagChange($tag, $text, $index) {
      return substr($text, $index, strlen($tag)) === $tag;
   }
}