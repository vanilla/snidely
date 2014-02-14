<?php

use Snidely\Snidely;
use Snidely\Scope;

class HandlebarsSpecTest extends PHPUnit_Framework_TestCase {
    public $skip = array(
    );


    /**
     * @dataProvider provider
     * @param string The name of the test.
     * @param array $spec The spec to test.
     */
    public function testSpec($name, $spec) {
        // Check to see if the spec is being skipped.
        if (isset($this->skip[$name])) {
            $this->markTestSkipped($this->skip[$name]);
            return;
        }

        // Load and compile the template.
        $snidely = new Snidely();
        $snidely->compilerFlags = \Snidely\PhpCompiler::HANDLEBARS;

        $snidely->cachePath($cache_path = __DIR__.'/cache/handlebars-spec');
        if (!file_exists($snidely->cachePath()))
            mkdir($snidely->cachePath(), 0777, true);

        $fn = $snidely->compile($spec['template']);

        // Run the template.
        if (isset($spec['data']))
            $data = $spec['data'];
        else
            $data = [];

        $actual = $snidely->fetch($fn, $data);

        // Compare to the expected.
        $expected = $spec['expected'];

        $this->assertStrippedEquals($expected, $actual);
    }

    /**
     * Handlebars doesn't strip whitespace like mustache or snidely.
     * This method strips all applicable whitespace before comparing two strings.
     * @param string $expected
     * @param string $actual
     */
    public function assertStrippedEquals($expected, $actual) {
        $expected = trimWhitespace($expected);
        $actual = trimWhitespace($actual);
        $this->assertEquals($expected, $actual);
    }

    public function provider() {
        $paths = glob(__DIR__."/handlebars-spec/spec/*.json");

        $result = array();
        foreach ($paths as $path) {
            $basename = basename($path, '.json');

            $json = json_decode(file_get_contents($path), true);
            foreach ($json as $spec) {
                $desc =  $spec['description'];
                $it = $spec['it'];

                $base_testname = strtolower("$desc-$it");

                for ($i = 0; $i < 20; $i++) {
                    $testname = "$base_testname-".sprintf('%02d', $i);

                    if (!isset($result[$testname])) {
                        $result[$testname] = [$testname, $spec];
                        break;
                    }
                }
            }
        }
        return $result;
    }
}