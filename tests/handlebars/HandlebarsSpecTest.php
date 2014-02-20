<?php

use Snidely\Snidely;
use Snidely\Scope;

class HandlebarsSpecTest extends PHPUnit_Framework_TestCase {
    public $skip = [
        // Basic
        'basic context-functions returning safestrings shouldn\'t be escaped-00' => 0,
        'basic context-functions-00' => 'Context functions not supported.',
        'basic context-functions-01' => 'Context functions not supported.',
        'basic context-functions with context argument-00' => 'Context functions not supported.',
        'basic context-pathed functions with context argument-00' => 'Context functions not supported.',
        'basic context-depthed functions with context argument-00' => 'Context functions not supported.',
        'basic context-block functions with context argument-00' => 'Context functions not supported.',
        'basic context-depthed block functions with context argument-00' => 'Context functions not supported.',
        'basic context-block functions without context argument-00' => 'Context functions not supported.',
        'basic context-pathed block functions without context argument-00' => 'Context functions not supported.',
        'basic context-depthed block functions without context argument-00' => 'Context functions not supported.',
        'basic context-that current context path ({{.}}) doesn\'t hit helpers-00' => 'Context functions not supported.',

        // Builtins
        '#if-if with function argument-00' => 'Context functions not supported.',
        '#if-if with function argument-01' => 'Context functions not supported.',
        '#with-with with function argument-00' => 'Context functions not supported.',
        '#each-each with function argument-00' => 1,
        '#each-data passed to helpers-00' => "Data isn't currently passed through the scope."

    ];

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

    protected function sanitizeFilename($str) {
        $str = preg_replace('`[^a-z0-9-]`i', '-', $str);
        $str = preg_replace('`-+`', '-', $str);
        $str = trim($str, '-');

        return $str;
    }


    /**
     * @param string The name of the test.
     * @param array $spec The spec to test.
     */
    public function runSpec($name, $spec) {
        // Check to see if the spec is being skipped.
        if (isset($this->skip[$name])) {
            $this->markTestSkipped($this->skip[$name]);
            return;
        }

        if (!isset($spec['template'])) {
            $this->markTestIncomplete('Missing template in spec');
        }

        // Load and compile the template.
        $snidely = new Snidely();
        $snidely->compilerFlags = \Snidely\PhpCompiler::HANDLEBARS;

        $snidely->cachePath($cache_path = PATH_TEST_CACHE.'/handlebars-spec');
        if (!file_exists($snidely->cachePath()))
            mkdir($snidely->cachePath(), 0777, true);

        // Grab the helpers.
        if (isset($spec['helpers'])) {
            foreach ($spec['helpers'] as $fname => $defs) {
                if (isset($defs['php'])) {
                    $helper_fn = function() { return ''; };
                    eval("\$helper_fn = {$defs['php']};");
                    $snidely->registerHelper($fname, $helper_fn);
                } else {
                    $this->markTestIncomplete("No definition for helper $fname.");
                }
            }
        }

        // Grab the partials.
        if (isset($spec['partials'])) {
            foreach ($spec['partials'] as $pname => $ptemplate) {
                $pfn = $snidely->compile($ptemplate);
                $snidely->registerPartial($pname, $pfn);
            }
        }

        if (isset($spec['exception']) && $spec['exception']) {
            $this->setExpectedException('Snidely\SyntaxException');
        }

        $fn = $snidely->compile($spec['template'], $this->sanitizeFilename($name));

        // Grab the data.
        if (isset($spec['data']))
            $data = $spec['data'];
        else
            $data = [];

       if (isset($spec['options']))
          $options = $spec['options'];
       else
          $options = [];

        // Run the template.
        $actual = $snidely->fetch($fn, $data, $options);

        // Compare to the expected.
        $expected = isset($spec['expected']) ? $spec['expected'] : '';

        $this->assertStrippedEquals($expected, $actual);
    }


    /**
     * @dataProvider provideBasic
     * @param $name
     * @param $spec
     */
    public function testBasic($name, $spec) {
        $this->runSpec($name, $spec);
    }

    /**
     * @dataProvider provideBlocks
     * @param $name
     * @param $spec
     */
    public function testBlocks($name, $spec) {
        $this->runSpec($name, $spec);
    }

    /**
     * @dataProvider provideBuiltins
     * @param $name
     * @param $spec
     */
    public function testBuiltins($name, $spec) {
        $this->runSpec($name, $spec);
    }

    /**
     * @dataProvider provideData
     * @param $name
     * @param $spec
     */
//    public function testData($name, $spec) {
//        $this->runSpec($name, $spec);
//    }

    /**
     * @dataProvider provideHelpers
     * @param $name
     * @param $spec
     */
//    public function testHelpers($name, $spec) {
//        $this->runSpec($name, $spec);
//    }

    /**
     * @dataProvider providePartials
     * @param $name
     * @param $spec
     */
//    public function testPartials($name, $spec) {
//        $this->runSpec($name, $spec);
//    }

    public function provideBasic() {
        return $this->provider('basic');
    }

    public function provideBlocks() {
        return $this->provider('blocks');
    }

    public function provideBuiltins() {
        return $this->provider('builtins');
    }

    public function provideData() {
        return $this->provider('data');
    }

    public function provideHelpers() {
        return $this->provider('helpers');
    }

    public function providePartials() {
        return $this->provider('partials');
    }

    public function provideRegressions() {
        return $this->provider('regressions');
    }

    public function provideStringParams() {
        return $this->provider('string-params');
    }

    public function provider($name) {
        $paths = glob(PATH_TEST."/handlebars-spec/spec/$name.json");

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