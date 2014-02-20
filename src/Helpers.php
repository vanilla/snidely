<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2013-2014 Vanilla Forums Inc.
 * @license MIT
 * @package Snidely
 */

namespace Snidely;

class Helpers {
    /// Methods ///

    /**
     * A helper that implements the runtime version of the {{#each }} section.
     * @param mixed $context
     * @param Scope $scope
     * @param mixed $prev
     * @param array $options
     */
    public static function each($context, Scope $scope, $prev, $options) {
        if (empty($context)) {
            // The context is empty so display the inverse template.
            if (isset($options['inverse'])) {
                call_user_func($options['inverse'], $context, $scope, $options);
            }
        } elseif (is_array($context)) {
            // Get the item separator if any.
            if (isset($options['hash']['sep'])) {
                $sep = $options['hash']['sep'];
            } else {
                $sep = '';
            }

            // Push a placeholder for the value.
//            $scope = new Scope($context, $scope->root);
            $scope->push($prev);
            $scope->pushData();

            $i = 0;
            $count = count($context);
            $has_key = !isset($context[0]);

            // Loop through the context and call the template on each one.
            $first = true;
            foreach ($context as $key => $context_row) {
//                $scope->replace($context_row);
                $scope->replaceData([
                    'index' => $i,
                    'key' => $has_key ? $key : null,
                    'first' => $first,
                    'last' => $i === $count - 1
                ]);

                // Echo the separator between the items.
                if ($first === true) {
                    $first = false;
                } else {
                    echo $sep;
                }

                // Call the item template.
                if (is_array($context_row)) {
                    call_user_func($options['fn'], $context_row, $scope, $options, $key);
                } else {
                    call_user_func($options['fn'], new ValueContext($context_row), $scope, $options, $key);
                }
                $i++;
            }

            $scope->pop();
            $scope->popData();
        }
    }

    public static function iif($context, $then, $else) {
        if ($context) {
            return $then;
        } else {
            return $else;
        }
    }

    public static function join($context, $sep = ',') {
        if (is_array($context))
            echo implode($sep, $context);
        else
            echo $context;
    }

    public static function json($context, $pretty = true) {
        $pretty = $pretty ? JSON_PRETTY_PRINT : 0;

        return json_encode($context, $pretty | JSON_UNESCAPED_SLASHES);
    }

    public static function lookup($context, $index) {
        if (is_array($context) || is_string($context)) {
            if ($index instanceof ValueContext)
                $index = $index->value;

            return $context[$index];
        }
        return '';
    }

    public static function noop() {

    }

    protected static function registerHelper(Snidely $snidely, $name, $fname = null) {
        if ($fname === null)
            $fname = $name;
        $snidely->registerHelper($name, ['\\'.get_called_class(), $fname]);
    }

    public static function registerHelpers(Snidely $snidely) {
        static::registerHelper($snidely, 'each');
        static::registerHelper($snidely, 'iif');
        static::registerHelper($snidely, 'join');
        static::registerHelper($snidely, 'json');
        static::registerHelper($snidely, 'lookup');
        static::registerHelper($snidely, 'section');
        static::registerHelper($snidely, 'with');
    }

    public static function registerBuiltInHelers(Snidely $snidely) {
        $snidely->registerHelper('count', 'count');
//        $snidely->registerHelper('fist', 'reset');
//        $snidely->registerHelper('last', 'end');
        $snidely->registerHelper('lowercase', 'strtolower');
        $snidely->registerHelper('titleize', 'ucwords');
        $snidely->registerHelper('uppercase', 'strtoupper');
    }

    /**
     * A helper that implements the runtime of block sections.
     * @param array $context The context passed to the section.
     * @param Scope $scope The scope of data and parents.
     * @param array $prev The context before the section was called.
     * @param array $options An array of handlebars style options passed to the section.
     */
    public static function section($context, Scope $scope, $prev, $options) {
        if (empty($context)) {
            if (isset($options['inverse'])) {
                $options['inverse']($prev, $scope);
            }
        } elseif (is_array($context)) {
            if (isset($context[0])) {
                static::each($context, $scope, $prev, $options);
            } else {
                // This is an object-like array and is like a with.
//                $scope->push($context);
                $options['fn']($context, $scope);
//                $scope->pop();
            }
        } else {
            if (static::truthy($context)) {
                // A truthy value is like an if.
                $options['fn']($prev, $scope);
            } else {
                // An atomic non-true value is like a with.
                $options['fn'](new ValueContext($context), $scope);
            }
        }
    }

    public static function truthy($context) {
        return $context === true || $context === 1 || $context === "1";
    }

    public static function with($context, Scope $scope, $prev, $options) {
        if (empty($context))
            return;

        $scope->push($prev);

        $fn = $options['fn'];
        if (is_array($context)) {
            $fn($context, $scope, $options);
        } else {
            $fn(new ValueContext($context), $scope, $options);
        }
        $scope->pop();
    }
}