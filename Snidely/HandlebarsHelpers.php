<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2013-2014 Vanilla Forums Inc.
 * @license MIT
 * @package Snidely
 */

namespace Snidely;

class HandlebarsHelpers extends Helpers {

//    /**
//     *
//     */
//    public static function each($context, Scope $scope, $prev, $options) {
//        $fn = $options['fn'];
//        $i = 0;
//
//        $scope->pushData();
//
//        if ($context && is_array($context)) {
//            if (static::isArray($context)) {
//                $scope->push($prev);
//
//                for ($j = count($context); $i < $j; $i++) {
//                    $scope->replaceData([
//                        'index' => $i,
//                        'first' => $i === 0,
//                        'last' => $i === count($context) - 1
//
//                    ]);
//
////                            if (contextPath) {
////                                data.contextPath = contextPath + i;
////                            }
//                    $fn($context[$j], $scope);
//                }
//
//                $scope->pop();
//            } else {
//                $scope->push($prev);
//
//                foreach ($context as $key => $context_row) {
//                    $scope->replaceData([
//                        'key' => $key,
//                        'index' => $index,
//                        'first' => $i === 0,
//                        'last' => $i === count($context) - 1
//                    ]);
//
//                    $fn($context_row, $scope);
//                    $i++;
//                }
//
//                $scope->pop();
//            }
//        }
//        $scope->popData();
//
//        if (i === 0 && is_callable($options['inverse'])) {
//            $options['inverse']($prev, $scope);
//        }
//
//    }

    public static function isArray($value) {
        return isset($value[0]);
    }

    /**
     * Converts a value to an escaped string in a way that is compatible with handlebars.js.
     * @param mixed $value The value to convert to a string.
     * @return string Returns the html escaped string version of `$value`.
     */
    public static function escapeStr($value) {
        return strtr(
            htmlspecialchars(static::str($value)),
            ["'" => "&#x27;", "`" => "&#x60;"]
            );
    }

    /**
     * Converts a value to a string in a way that is compatible with handlebars.js.
     * @param mixed $value The value to convert to a string.
     * @return string Returns the string version of `$value`.
     */
    public static function str($value, $literal_false = false) {
        // The ValueContext just does a simple string cast so make sure we override that.
        if ($value instanceof ValueContext) {
            $value = $value->value;
        }

        if ($value === true) {
            return 'true';
        } elseif ($value === false) {
            return $literal_false ? 'false' : '';
        } elseif (is_array($value)) {
            if (empty($value)) {
                return '';
            } elseif (isset($value[0])) {
                $value = array_map(function($value) { return static::str($value, true); }, $value);
                return implode(',', $value);
            } else {
                return '[object Object]';
            }
        }

        return (string)$value;
    }

    /**
     * An if block helper function.
     *
     * @param mixed $context
     * @param mixed $includeZero
     * @param Scope $scope
     * @param mixed $prev
     * @param array $options
     */
    public static function ifb($context, $includeZero = false, Scope $scope, $prev, $options) {
        // Default behavior is to render the positive path if the value is truthy and not empty.
        // The `includeZero` option may be set to treat the condtional as purely not empty based on the
        // behavior of isEmpty. Effectively this determines if 0 is handled by the positive path or negative.
        if ((!$includeZero && $context === 0) || static::isEmpty($context)) {
            if (isset($options['inverse'])) {
                $scope->push($prev);
                $options['inverse']($prev, $scope);
                $scope->pop();
            }
        } else {
            if (isset($options['fn'])) {
                $scope->push($prev);
                $options['fn']($prev, $scope);
                $scope->pop();
            }
        }
    }

    public static function isEmpty($value) {
        if (!$value && $value !== 0 && $value !== '0') {
            return true;
        } else if (is_array($value) && count($value) === 0) {
            return true;
        } else {
            return false;
        }
    }

    public static function registerHelpers(Snidely $snidely) {
        parent::registerHelpers($snidely);
        static::registerHelper($snidely, 'if', 'ifb');
        static::registerHelper($snidely, 'unless');
    }

    /**
     * An unless block helper function.
     *
     * @param mixed $context
     * @param mixed $includeZero
     * @param Scope $scope
     * @param mixed $prev
     * @param array $options
     */
    public static function unless($context, $includeZero = false, Scope $scope, $prev, $options) {
        // Default behavior is to render the positive path if the value is truthy and not empty.
        // The `includeZero` option may be set to treat the condtional as purely not empty based on the
        // behavior of isEmpty. Effectively this determines if 0 is handled by the positive path or negative.
        if ((!$includeZero && !$context) || static::isEmpty($context)) {
            if (isset($options['fn'])) {
                $scope->push($prev);
                $options['fn']($prev, $scope);
                $scope->pop();
            }
        } else {
            if (isset($options['inverse'])) {
                $scope->push($prev);
                $options['inverse']($prev, $scope);
                $scope->pop();
            }
        }
    }
} 