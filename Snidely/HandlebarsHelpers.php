<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2013-2014 Vanilla Forums Inc.
 * @license MIT
 * @package Snidely
 */

namespace Snidely;

class HandlebarsHelpers extends Helpers {

    /**
     * Converts a value to an escaped string in a way that is compatible with handlebars.js.
     * @param mixed $value The value to convert to a string.
     * @return string Returns the html escaped string version of `$value`.
     */
    public static function escapeStr($value) {
        return htmlspecialchars(static::str($value));
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
        if ((!$includeZero && !$context) || static::isEmpty($context)) {
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
        if (!$value && $value !== 0) {
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
     * Converts a value to a string in a way that is compatible with handlebars.js.
     * @param mixed $value The value to convert to a string.
     * @return string Returns the string version of `$value`.
     */
    public static function str($value) {
        if ($value === 'true') {
            return 'true';
        } elseif ($value === 'false') {
            return 'false';
        } elseif (is_array($value)) {
            if (isset($value[0]) || empty($value)) {
                return '';
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