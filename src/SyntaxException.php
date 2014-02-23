<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2010-2013 Justin Hileman
 * @copyright Modifications are Copyright (c) 2013-2014 Vanilla Forums Inc.
 * @license MIT
 * @package Snidely
 * @link https://github.com/bobthecow/mustache.php/blob/master/src/Mustache/Parser.php Original file.
 */

namespace Snidely;

/**
 * Snidely syntax exception throws when templates are malformed.
 */
class SyntaxException extends \Exception {
    protected $token;

    public function __construct($msg, array $token) {
        $this->token = $token;
        parent::__construct($msg, 400);
    }

    public function getToken() {
        return $this->token;
    }

}
