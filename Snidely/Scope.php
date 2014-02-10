<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2013-2014 Vanilla Forums Inc.
 * @license MIT
 * @package Snidely
 */

namespace Snidely;

class Scope implements \ArrayAccess {
    /// Properties ///

    /**
     * @var array A stack of the current contexts.
     */
    protected $contextStack;

    /**
     *
     * @var array A stack of the current data contexts.
     */
    protected $dataStack;

    /// Methods ///

    public function __construct($initial = null) {
        if ($initial === null) {
            $this->contextStack = [];
        } else {
            $this->contextStack = [$initial];
        }

        $this->dataStack = [];
    }

    /**
     * Get the data data from the given key.
     * @param name $key
     * @return mixed|null
     */
    public function data($key) {
        for ($i = count($this->dataStack) - 1; $i >= 0; $i--) {
            if (array_key_exists($key, $this->dataStack[$i])) {
                $result = $this->dataStack[$i][$key];
                return $result;
            }
        }
        return null;
    }

    public function offsetExists($offset) {
        for ($i = count($this->contextStack) - 1; $i >= 0; $i--) {
            $item = $this->contextStack[$i];
            if (isset($item[$offset]))
                return true;
        }
        return false;
    }

    public function offsetGet($offset) {
        for ($i = count($this->contextStack) - 1; $i >= 0; $i--) {
            $item = $this->contextStack[$i];

            if (array_key_exists($offset, $item))
                return $item[$offset];
        }
        return null;
    }

    public function offsetSet($offset, $value) {
        for ($i = count($this->contextStack) - 1; $i >= 0; $i--) {
            $item =& $this->contextStack[$i];

            $item[$offset] = $value;
            break;
        }
    }

    public function offsetUnset($offset) {
        for ($i = count($this->contextStack) - 1; $i >= 0; $i--) {
            $item =& $this->contextStack[$i];
            unset($item[$offset]);
        }
    }

    public function pop() {
        return array_pop($this->contextStack);
    }

    public function popData() {
        return array_pop($this->dataStack);
    }

    public function push(array $context = []) {
        array_push($this->contextStack, $context);
    }

    public function pushData(array $context = []) {
        array_push($this->dataStack, $context);
    }

    /**
     * Replace the top of the stack with a context.
     * @param array $context
     */
    public function replace($context) {
        if (empty($this->contextStack)) {
            $this->contextStack = [$context];
        } else {
            $this->contextStack[count($this->contextStack) - 1] = $context;
        }
    }

    public function replaceData($context) {
        $this->dataStack[count($this->dataStack) - 1] = $context;
    }

    public function root($name) {
        if (!isset($this->contextStack[0][$name]))
            return null;
        return $this->contextStack[0][$name];
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

