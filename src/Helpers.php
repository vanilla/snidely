<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2013-2014 Vanilla Forums Inc.
 * @license MIT
 * @package Snidely
 */

namespace Snidely;

/**
 * Contains helper functions for template runtime execution.
 */
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

    /**
     * Performs an inline if statement.
     * @param mixed $context The context being examined.
     * @param mixed $then What to return if `$context` is true.
     * @param mixed $else What to return if `$context is false.
     * @return mixed Returns either `$then` or `$else`.
     */
    public static function iif($context, $then, $else) {
        if ($context) {
            return $then;
        } else {
            return $else;
        }
    }

    /**
     * Performs an array join.
     *
     * This is analogous to the php {$link implode()} function.
     * @param array $context The array to join.
     * @param string $sep The character used to seperate array elements.
     * @return string Returns the joined array.
     */
    public static function join($context, $sep = ',') {
        if (is_array($context))
            return implode($sep, $context);
        else
            return (string)$context;
    }

    /**
     * JSON encodes an array.
     * @param mixed $context The context to encode.
     * @param bool $pretty Whether the output should be pretty printed.
     * @return string Returns the json encoded string.
     */
    public static function json($context, $pretty = true) {
        $pretty = $pretty ? JSON_PRETTY_PRINT : 0;

        return json_encode($context, $pretty | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Converts a value to a string in a way that is compatible with javascript.
     * @param mixed $value The value to convert to a string.
     * @param bool $literal_false Whether or not to return 'false' for a boolean false value.
     * @return string Returns the string version of `$value`.
     */
    public static function jstr($value, $literal_false = true) {
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
     * Looks up an index in an array and returns the result.
     *
     * @param array|string $context The array or string to search.
     * @param int|string $index The index within the array or string to find.
     * @param mixed $default a default value to return if the index isn't found.
     * @return mixed Returns the found value or `$default` if it isn't found.
     */
    public static function lookup($context, $index, $default = '') {
        if (is_array($context) || is_string($context)) {
            if ($index instanceof ValueContext)
                $index = $index->value;

            return $context[$index];
        }
        return $default;
    }

    /**
     * Performs no operation.
     *
     * This method is useful if you need to pass a callable to a function, but don't want to do anything.
     */
    public static function noop() {

    }

    /**
     * Register a helper in this class to a {@link Snidely} instance.
     * @param Snidely $snidely The {@link Snidely} instance to register the helper with.
     * @param string $name The alias of the helper.
     * @param string $fname The name of the method if different than `$name`.
     */
    protected static function registerHelper(Snidely $snidely, $name, $fname = null) {
        if ($fname === null)
            $fname = $name;
        $snidely->registerHelper($name, ['\\'.get_called_class(), $fname], ['overwrite' => false]);
    }

    /**
     * Register all of the helpers in this class with a {@link Snidely} instance.
     * @param Snidely $snidely The {@link Snidely} instance to register the helpers with.
     */
    public static function registerHelpers(Snidely $snidely) {
        static::registerHelper($snidely, 'each');
        static::registerHelper($snidely, 'iif');
        static::registerHelper($snidely, 'join');
        static::registerHelper($snidely, 'json');
        static::registerHelper($snidely, 'lookup');
        static::registerHelper($snidely, 'section');
        static::registerHelper($snidely, 'with');
    }

    /**
     * Register some built in php functions with a {@link Snidely} instance.
     * @param Snidely $snidely The {@link Snidely} instance to register the helpers with.
     */
    public static function registerBuiltInHelers(Snidely $snidely) {
        $snidely->registerHelper('count', 'count', ['overwrite' => false]);
        $snidely->registerHelper('lowercase', 'strtolower', ['overwrite' => false]);
        $snidely->registerHelper('titlecase', 'ucwords', ['overwrite' => false]);
        $snidely->registerHelper('ucwords', 'ucwords', ['overwrite' => false]);
        $snidely->registerHelper('uppercase', 'strtoupper', ['overwrite' => false]);
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

    /**
     * Returns whether or not a value is a truthy value.
     * @param mixed $context The value to test.
     * @return bool Returns `true` if the value is true or a value that can represent true (such as 1).
     */
    public static function truthy($context) {
        return $context === true || $context === 1 || $context === "1";
    }

    /**
     * Invokes a template block with a given context.
     *
     * @param mixed $context The context to invoke the block against.
     * @param Scope $scope The current scope.
     * @param mixed $prev The previous context.
     * @param array $options Options that have been passed to this helper.
     */
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