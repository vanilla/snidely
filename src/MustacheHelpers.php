<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2013-2014 Vanilla Forums Inc.
 * @license MIT
 * @package Snidely
 */

namespace Snidely;

/**
 * Contains helper functions for mustache compatibility.
 */
class MustacheHelpers extends Helpers {

    /**
     * A helper that implements the runtime of block sections.
     * @param array $context
     * @param Scope $scope
     * @param mixed $prev
     * @param array $options
     */
    public static function section($context, Scope $scope, $prev, $options) {
        if (empty($context)) {
            return;
        } elseif (is_array($context)) {
            if (isset($context[0])) {
                // This is a numeric array and is looped.

                // Push a placeholder for the loop.
                $scope->push();
                $scope->pushData();

                $i = 0;
                $count = count($context);

                foreach ($context as $key => $context_row) {
                    $scope->replace($context_row);
                    $scope->replaceData([
                        'index' => $i,
                        'key' => $key,
                        'first' => $i === 0,
                        'last' => $i === $count - 1
                    ]);


                    $fn = $options['fn'];
                    $fn($context_row);

                    $i++;
                }

                $scope->pop();
                $scope->popData();
            } else {
                // This is an object-like array and is like a with.
                $scope->push($context);
                $options['fn']($context, $scope);
                $scope->pop();
            }
        } else {
            // This is an atomic value and is like an if (true).
            $options['fn']($scope->peek(), $scope);
        }
    }
} 