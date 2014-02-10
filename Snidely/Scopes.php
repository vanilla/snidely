<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2013-2014 Vanilla Forums Inc.
 * @license MIT
 * @package Snidely
 */

namespace Snidely;

class Scopes implements \ArrayAccess {
    /// Properties ///

    protected $stack;

    /// Methods ///

    public function __construct($initial = null) {
        if ($initial === null) {
            $this->stack = [];
        } else {
            $this->stack = [$initial];
        }
    }

    public function offsetExists($offset) {
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            $item = $this->stack[$i];
            if (isset($item[$offset]))
                return true;
        }
        return false;
    }

    public function offsetGet($offset) {
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            $item = $this->stack[$i];

            if (isset($item[$offset]))
                return $item[$offset];
        }
        return null;
    }

    public function offsetSet($offset, $value) {
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            $item =& $this->stack[$i];

            $item[$offset] = $value;
            break;
        }
    }

    public function offsetUnset($offset) {
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            $item =& $this->stack[$i];
            unset($item[$offset]);
        }
    }

    public function pop() {
        return array_pop($this->stack);
    }

    public function push($context) {
        array_push($this->stack, $context);
    }

    /**
     * Replace the top of the stack with a context.
     * @param array $context
     */
    public function replace($context) {
        if (empty($this->stack)) {
            $this->stack = [$context];
        } else {
            $this->stack[count($this->stack) - 1] = $context;
        }
    }

    public function root($name) {
        if (!isset($this->stack[0][$name]))
            return null;
        return $this->stack[0][$name];
    }

//    public static function wrap(&$array) {
//        if ($array instanceof Context) {
//            return $array;
//        } else {
//            $context = new Context();
//            $context->push($array);
//            $array = $context;
//            return $array;
//        }
//    }
}

