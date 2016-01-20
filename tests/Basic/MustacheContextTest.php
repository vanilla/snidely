<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license MIT
 */

namespace Snidely\Tests\Basic;

use Snidely\MustacheContext;

/**
 * Tests for the {@link MustacheContext} class.
 */
class MustacheContextTest extends \PHPUnit_Framework_TestCase {
    /**
     * Test basic getting.
     */
    public function testBasicArrayAccess() {
        $arr = ['foo' => 'bar'];
        $marr = new MustacheContext($arr);

        $this->assertSame($arr['foo'], $marr['foo']);
        $this->assertNull($marr['baz']);
    }

    /**
     * Test parent traversal for one level.
     */
    public function testRecursiveLookup1() {
        $arr = ['foo' => ['bar' => 'baz'], 'name' => 'tod'];
        $marr = new MustacheContext($arr);

        $this->assertSame('baz', $marr['foo']['bar']);

        $marr2 = $marr['foo'];
        $this->assertSame('tod', $marr2['name']);
    }

    /**
     * Test parent traversal for two levels.
     */
    public function testRecursiveLookup2() {
        $marr = new MustacheContext(['grandparent' => ['parent' => ['child' => 'hi!']], 'name' => 'babcia']);

        $this->assertSame('hi!', $marr['grandparent']['parent']['child']);
        $this->assertSame('babcia', $marr['grandparent']['parent']['name']);
    }
}
