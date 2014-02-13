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

    /**
     * @var array
     */
    public $root;

    /**
     * @var array
     */
    protected $rootStack;


    /// Methods ///

    public function __construct($context = null, $root = null) {
        if ($context === null) {
            $this->contextStack = [[]];
        } else {
            $this->contextStack = [$context];
        }

        if ($root === null) {
            $this->root = reset($this->contextStack);
        } else {
            $this->root = $root;
        }
        $this->rootStack = [$this->root];


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
        // Try finding the offset to update its value.
        for ($i = count($this->contextStack) - 1; $i >= 0; $i--) {
            if (array_key_exists($offset, $this->contextStack[$i])) {
                $this->contextStack[$i][$offset] = $value;
                return;
            }
        }

        // The offset didn't exist so just set the top of the stack.
        $this->contextStack[count($this->contextStack) - 1][$offset] = $value;
    }

    public function offsetUnset($offset) {
        for ($i = count($this->contextStack) - 1; $i >= 0; $i--) {
            $item =& $this->contextStack[$i];
            unset($item[$offset]);
        }
    }

    public function peek() {
        assert(!empty($this->contextStack));

        return end($this->contextStack);
    }

    public function pop() {
        if (empty($this->contextStack))
            throw new \Exception("You tried to pop off an empty stack.", 500);

        return array_pop($this->contextStack);
    }

    public function popData() {
        assert(!empty($this->dataStack));

        return array_pop($this->dataStack);
    }

    public function push($context = []) {
        array_push($this->contextStack, $context);
    }

    public function pushData($context = []) {
        array_push($this->dataStack, $context);
    }

    /**
     * Replace the top of the stack with a context.
     * @param array $context
     */
    public function replace($context) {
        assert(!empty($this->contextStack));

        if (empty($this->contextStack)) {
            $this->contextStack = [$context];
        } else {
            $this->contextStack[count($this->contextStack) - 1] = $context;
        }
    }

    public function replaceData($context) {
        assert(!empty($this->dataStack));
        $this->dataStack[count($this->dataStack) - 1] = $context;
    }

    public function root($level = -1) {
        return $this->contextStack[count($this->contextStack) + $level];
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

