<?php

use Snidely\Snidely;

class BasicTest extends PHPUnit_Framework_TestCase {
    protected $familyTree = <<<EOT
{
    "name": "grandparent",
    "parent": {
        "name": "parent",
        "child": {
            "name": "child",
            "grandchild": {
                "name": "grandchild"
            }
        },
        "children": [
            { "name": "child1" },
            { "name": "child2" }
        ]
    }
}
EOT;

    public function testEmptyDoubleRoot() {
        $this->shouldCompileTo(__FUNCTION__, '{{../../foo}}', ['foo' => 'bar'], '');
    }

    /**
     * @param string $template
     * @param string $expected
     * @dataProvider provideHbRoots
     */
    public function testHbRoots($template, $expected) {
        $snidely = $this->snidely();
        $snidely->compilerFlags = \Snidely\PhpCompiler::HANDLEBARS;

        $data = json_decode($this->familyTree, true);

        $fn = $snidely->compile($template);

        $actual = $snidely->fetch($fn, $data);
        $expected = trimWhitespace($expected);
        $this->assertEquals($expected, $actual);
    }

    public function provideHbRoots() {
        $result = [
            ['{{parent.name}} {{parent.child.name}} {{parent.child.grandchild.name}}', 'parent child grandchild'],
            ['{{#parent}}{{name}}{{/parent}} {{#with parent}}{{name}}{{/with}}', 'parent parent'],
            ['{{#parent}}{{name}} {{../name}}{{/parent}}', 'parent grandparent'],
            ["{{#with parent.child}}{{../name}}{{/with}}", 'grandparent']
        ];

        return $result;
    }

    protected function shouldCompileTo($key, $template, $data, $expected) {
        $snidely = $this->snidely();
        $fn = $snidely->compile($template, $key);

        $result = $snidely->fetch($fn, $data);
        $expected = trimWhitespace($expected);
        $this->assertEquals($expected, $result);
    }

    protected function snidely() {
        $snidely = new Snidely();
        $snidely->cachePath($cache_path = PATH_TEST_CACHE.'/SimpleTest');
        if (!file_exists($snidely->cachePath()))
            mkdir($snidely->cachePath(), 0777, true);

        return $snidely;
    }

} 