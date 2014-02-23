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
 * This class is responsible for turning raw template source into a set of tokens.
 */
class Tokenizer {

    // Finite state machine states
    const IN_TEXT = 0;
    const IN_TAG_TYPE = 1;
    const IN_TAG = 2;

    // Token types
    const T_SECTION = '#';
    const T_INVERTED = '^';
    const T_END_SECTION = '/';
    const T_COMMENT = '!';
    const T_PARTIAL = '>';
    const T_PARTIAL_2 = '<';
    const T_DELIM_CHANGE = '=';
    const T_ESCAPE_CHAR = '\\';
    const T_ESCAPED = '_v';
    const T_UNESCAPED = '{';
    const T_UNESCAPED_2 = '&';
    const T_TEXT = '_t';
    const T_PRAGMA = '%';
    const T_QUOTE = '"';
    const T_QUOTE2 = "'";
    const T_EQUALS = '=';
    const T_OLITERAL = '[';
    const T_CLITERAL = ']';
    const T_SPACE = ' ';
    const T_SLASH = '/';
    const T_DOT = '.';
    const T_ELSE = '_else';
    const T_STRING = '_str';
    const T_VAR = '_var';
    const T_KEY = '_key';
    const T_LITERAL_TRUE = 'true';
    const T_LITERAL_FALSE = 'false';

    // Valid token types
    const TYPE = 'type';

    // Interpolated tags
    const NAME = 'name';

    // Token properties
    const OTAG = 'otag';
    const CTAG = 'ctag';
    const LINE = 'line';
    const INDEX = 'index';
    const COL = 'col';
    const END = 'end';
    const INDENT = 'indent';
    const NODES = 'nodes';
    const VALUE = 'value';
    const INVERSE = 'inverse';
    const ARGS = 'args';
    const ARGS_STR = 'args_str'; // for else section a la handlebars
    const HASH = 'hash';
    private static $tagTypes = array(
        self::T_SECTION => true,
        self::T_INVERTED => true,
        self::T_END_SECTION => true,
        self::T_COMMENT => true,
        self::T_PARTIAL => true,
        self::T_PARTIAL_2 => true,
        self::T_DELIM_CHANGE => true,
        self::T_ESCAPED => true,
        self::T_UNESCAPED => true,
        self::T_UNESCAPED_2 => true,
        self::T_ELSE => true,
        self::T_PRAGMA => true,
    );
    private static $interpolatedTags = array(
        self::T_ESCAPED => true,
        self::T_UNESCAPED => true,
        self::T_UNESCAPED_2 => true,
    );
    private $argsTokenizer;
    private $state;
    private $tagType;
    private $tag;
    private $buffer;
    private $tokens;
    private $seenTag;
    private $line;
    private $col;
    private $otag;
    private $ctag;

    /**
     * @var bool Whether or not to allow escaping of tags with a \
     */
    private $allowEscapes = true;

    public function __construct() {
        $this->argsTokenizer = new ArgsTokenizer();
    }

    /**
     * Scan and tokenize template source.
     *
     * @param string $text Mustache template source to tokenize
     * @param string $delimiters Optionally, pass initial opening and closing delimiters (default: null)
     *
     * @return array Set of Mustache tokens
     */
    public function scan($text, $delimiters = null) {
        $this->reset();

        if ($delimiters = trim($delimiters)) {
            list($otag, $ctag) = explode(' ', $delimiters);
            $this->otag = $otag;
            $this->ctag = $ctag;
        }

        $len = strlen($text);
        for ($i = 0; $i < $len; $i++, $this->col++) {
            switch ($this->state) {
                case self::IN_TEXT:
                    if ($this->tagEscape($this->otag, $text, $i, $b, $i_inc)) {
                        $i += $i_inc;
                        $this->col += $i_inc;
                        $this->buffer .= $b;
                    } elseif ($this->tagChange($this->otag, $text, $i)) {
                        $i--;
                        $this->col--;
                        $this->flushBuffer();
                        $this->state = self::IN_TAG_TYPE;
                    } else {
                        $char = substr($text, $i, 1);
                        $this->buffer .= $char;
                        if ($char == "\n") {
                            $this->flushBuffer();
                            $this->line++;
                            $this->col = 0;
                        }
                    }
                    break;

                case self::IN_TAG_TYPE:
                    $i_inc = strlen($this->otag) - 1;
                    $i += $i_inc;
                    $this->col += $i_inc;
                    $char = substr($text, $i + 1, 1);
                    if (isset(self::$tagTypes[$char])) {
                        $tag = $char;
                        $this->tagType = $tag;
                    } else {
                        $tag = null;
                        $this->tagType = self::T_ESCAPED;
                    }

                    if ($this->tagType === self::T_DELIM_CHANGE) {
                        $i = $this->changeDelimiters($text, $i, $i_inc);
                        $this->col += $i_inc;
                        $this->state = self::IN_TEXT;
                    } elseif ($this->tagType === self::T_PRAGMA) {
                        $i = $this->addPragma($text, $i, $i_inc);
                        $this->col += $i_inc;
                        $this->state = self::IN_TEXT;
                    } else {
                        if ($tag !== null) {
                            $i++;
                            $this->col++;
                        }
                        $this->state = self::IN_TAG;
                    }
                    $this->seenTag = $i;
                    break;

                default:
                    if ($this->tagChange($this->ctag, $text, $i)) {
                        $t = array(
                            self::TYPE  => $this->tagType,
                            self::NAME  => trim($this->buffer),
                            self::OTAG  => $this->otag,
                            self::CTAG  => $this->ctag,
                            self::LINE  => $this->line,
                            self::COL   => $this->col + strlen($this->ctag),
                            self::INDEX => ($this->tagType == self::T_END_SECTION) ? $this->seenTag - strlen($this->otag) : $i + strlen($this->ctag)
                        );

                        if ($this->tagType === self::T_ESCAPED && $t[self::NAME] === 'else') {
                            $t[self::TYPE] = self::T_ELSE;
                        } elseif ($this->tagType === self::T_INVERTED && $t[self::NAME] === '') {
                            $t[self::TYPE] = self::T_ELSE;
                        } elseif (in_array($this->tagType, array(self::T_SECTION, self::T_INVERTED, self::T_ESCAPED, self::T_UNESCAPED, self::T_UNESCAPED_2, self::T_PARTIAL, self::T_PARTIAL_2))) {
                            // Certain tags can take arguments. Check that here.
                            $args_str = trim($this->buffer);

                            $t[self::ARGS_STR] = $args_str;
                            $t[self::ARGS] = $this->argsTokenizer->scan($args_str, $t);

                            $nameParts = explode(' ', trim($this->buffer), 2);
                            $t[self::NAME] = $nameParts[0]; //
                        }
                        $this->tokens[] = $t;

                        $this->buffer = '';
                        $i_inc = strlen($this->ctag) - 1;
                        $i += $i_inc;
                        $this->col += $i_inc;
                        $this->state = self::IN_TEXT;
                        if ($this->tagType == self::T_UNESCAPED) {
                            if ($this->ctag == '}}') {
                                $i++;
                                $this->col++;
                            } else {
                                // Clean up `{{{ tripleStache }}}` style tokens.
                                $lastName = $this->tokens[count($this->tokens) - 1][self::NAME];
                                if (substr($lastName, -1) === '}') {
                                    $this->tokens[count($this->tokens) - 1][self::NAME] = trim(substr($lastName, 0, -1));
                                }
                            }
                        }
                    } else {
                        $this->buffer .= substr($text, $i, 1);
                    }
                    break;
            }
        }

        $this->flushBuffer();

        return $this->tokens;
    }

    /**
     * Helper function to reset tokenizer internal state.
     */
    private function reset() {
        $this->state = self::IN_TEXT;
        $this->tagType = null;
        $this->tag = null;
        $this->buffer = '';
        $this->tokens = array();
        $this->seenTag = false;
        $this->line = 0;
        $this->col = 0;
        $this->otag = '{{';
        $this->ctag = '}}';
    }

    /**
     * Test whether the tag should be escaped.
     *
     * @param string $tag The tag delimiter to check against/
     * @param string $text The full text of the template.
     * @param int $index The current index of the tokenizer.
     * @param string $buffer The buffer stub that is returned after escaping.
     * @param int $index_inc An amount to increment the index by.
     * @return bool Wether or not tags were escaped.
     */
    private function tagEscape($tag, $text, $index, &$buffer = null, &$index_inc = 0) {
        if (!$this->allowEscapes) {
            return false;
        }

        // Check for \\{{ -> \{{
        $str = self::T_ESCAPE_CHAR . self::T_ESCAPE_CHAR . $tag;
        if (substr($text, $index, strlen($str)) === $str) {
            $buffer = self::T_ESCAPE_CHAR;
            $index_inc = 1; // + strlen('\\')

            return true;
        }

        // Check for \{{ -> {{
        $str = self::T_ESCAPE_CHAR . $tag;
        if (substr($text, $index, strlen($str)) === $str) {
            $buffer = $tag;
            $index_inc = strlen($str) - 1;

            return true;
        }

        return false;
    }

    /**
     * Test whether it's time to change tags.
     *
     * @param string $tag Current tag name
     * @param string $text Mustache template source
     * @param int $index Current tokenizer index
     *
     * @return boolean True if this is a closing section tag
     */
    private function tagChange($tag, $text, $index) {
        return substr($text, $index, strlen($tag)) === $tag;
    }

    /**
     * Flush the current buffer to a token.
     */
    private function flushBuffer() {
        if (!empty($this->buffer)) {
            $this->tokens[] = array(
                self::TYPE => self::T_TEXT,
                self::LINE => $this->line,
                self::COL => $this->col,
                self::VALUE => $this->buffer
            );
            $this->buffer = '';
        }
    }

    /**
     * Change the current Mustache delimiters. Set new `otag` and `ctag` values.
     *
     * @param string $text Mustache template source
     * @param int $index Current tokenizer index
     * @param int $i_inc The amount to increment the index by.
     *
     * @return int New index value
     */
    private function changeDelimiters($text, $index, &$i_inc = 0) {
        $startIndex = strpos($text, '=', $index) + 1;
        $close = '=' . $this->ctag;
        $closeIndex = strpos($text, $close, $index);

        list($otag, $ctag) = explode(' ', trim(substr($text, $startIndex, $closeIndex - $startIndex)));
        $this->otag = $otag;
        $this->ctag = $ctag;

        $this->tokens[] = array(
            self::TYPE => self::T_DELIM_CHANGE,
            self::LINE => $this->line,
            self::COL  => $this->col
        );

        $i_inc = ($closeIndex + strlen($close) - 1) - $index;

        return $index + $i_inc;
    }

    /**
     * Add pragma token.
     *
     * Pragmas are hoisted to the front of the template, so all pragma tokens
     * will appear at the front of the token list.
     *
     * @param string $text
     * @param int $index
     * @param int $i_inc
     *
     * @return int New index value
     */
    private function addPragma($text, $index, &$i_inc = 0) {
        $end = strpos($text, $this->ctag, $index);
        $pragma = trim(substr($text, $index + 2, $end - $index - 2));

        // Pragmas are hoisted to the front of the template.
        array_unshift($this->tokens, array(
            self::TYPE => self::T_PRAGMA,
            self::NAME => $pragma,
            self::LINE => 0,
        ));

        $i_inc = ($end + strlen($this->ctag) - 1) - $index;

        return $i_inc;
    }
}
