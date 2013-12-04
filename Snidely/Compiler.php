<?php
namespace Snidely;

abstract class Compiler {
   /// Properties ///

   public $indent = 0;

   /**
    *
    * @var Snidely
    */
   public $snidely;

   /**
    * A list of helpers that will produce specific compiler output.
    *
    * @var array
    */
   protected $compileHelpers = array();

   /// Methods ///

   public function compile($nodes) {
      return $this->compileNodes($nodes);
   }

   protected function compileNodes($nodes, $indent = 0) {
      $result = '';

      foreach ($nodes as $node) {
         $type = $node[Tokenizer::TYPE];
         $name = isset($node[Tokenizer::NAME]) ? $node[Tokenizer::NAME] : false;

         if (isset($this->snidely->helpers[$name])) {
            // There is a helper.
            $result .= $this->helper($node, $indent, $this->snidely->helpers[$name]);
            continue;
         } elseif (isset($this->compileHelpers[$name])) {
            // There is a specific compile helper.

            $callback = $this->compileHelpers[$name];

            if ($type == Tokenizer::T_SECTION)
               $result .= call_user_func($callback, $node, $indent, $this);
            else
               $result .= call_user_func($callback, $node, $indent, $this);

            continue;
         }

         switch ($type) {
            case Tokenizer::T_COMMENT:
               $result .= $this->comment($node, $indent);
               break;
            case Tokenizer::T_TEXT:
               $result .= $this->text($node, $indent);
               break;
            case Tokenizer::T_ESCAPED:
               $result .= $this->escaped($node, $indent);
               break;
            case Tokenizer::T_UNESCAPED:
               $result .= $this->unescaped($node, $indent);
               break;
            case Tokenizer::T_SECTION:
               $result .= $this->section($node, $indent);
               break;
            case Tokenizer::T_DELIM_CHANGE:
                // Do nothing, the tokenizer took care of this.
                break;
            default:
               $result .= $this->unknown($node, $indent);
         }
      }
      return $result;
   }

   public function comment($node, $indent) {
      // Do nothing for comments.
   }

   public function escaped($node, $indent) {
      return $this->uknown($node, $indent);
   }



    protected function getSnidely($node, $indent, $comment = true) {
        // Figure out the brackets.
        switch ($node[Tokenizer::TYPE]) {
            case Tokenizer::T_ESCAPED:
               $lbracket = '{{';
               $rbracket = '}}';
               break;
            case Tokenizer::T_UNESCAPED:
               $lbracket = '{{{';
               $rbracket = '}}}';
               break;
            case Tokenizer::T_SECTION:
               $lbracket = '{{#';
               $rbracket = '}}';
               break;
            default:
               return '';
         }

         // Go through the args.
         $fargs = array();
         foreach ($node[Tokenizer::ARGS] as $arg) {
             $fargs[] = $this->getSnidelyArg($arg);
         }

         // Go through the hash.
         if (isset($node[Tokenizer::HASH]) && is_array($node[Tokenizer::HASH])) {
             foreach ($node[Tokenizer::HASH] as $key => $arg) {
                 $fargs[] = "$key=".$this->getSnidelyArg($arg);
             }
         }

         $result = $lbracket.implode(' ', $fargs).$rbracket;

         if ($comment) {
             $result = $this->str().$this->indent($indent).'// '.$result;
         }

        return $result;
    }

    protected function getSnidelyArg($arg) {
        $px = '';
        $farg_parts = array();
        foreach ($arg as $arg_part) {
           switch ($arg_part[Tokenizer::TYPE]) {
               case Tokenizer::T_VAR:
                   $farg_parts[] = $arg_part[Tokenizer::VALUE];
                   break;
               case Tokenizer::T_STRING:
                   $farg_parts[] = '"'.$arg_part[Tokenizer::VALUE].'"';
                   break;
               case Tokenizer::T_DOT:
                   $px = $arg_part[Tokenizer::VALUE].'/';
                   break;
           }
        }

        return $px.implode('.', $farg_parts);
    }

   public function helper($node, $indent, $helper) {
      return $this->unknown($node, $indent);
   }

   public function indent($indent) {
      $indent = str_repeat('    ', $indent);
      return $indent;
   }

   public function registerCompileHelper($name, $callback) {
      $this->compileHelpers[$name] = $callback;
   }

   public function reset() {
   }

   public function section($node, $indent) {
      return $this->unknown($node, $indent);
   }

   public function text($node, $indent) {
      return $this->unknown($node, $indent);
   }

   abstract function unknown($node, $indent);

   public function unescaped($node, $indent) {
      return $this->unknown($node, $indent);
   }
}
