<?php

namespace Snidely\Tests\Handlebars;

use Snidely\Snidely;
use Snidely\Scope;

class HandlebarsTest extends \PHPUnit_Framework_TestCase {
    public $skip = array(
//        '001-simple-vars-006' => 'Snidely doesn\'s support imploding array parameters.'
    );


    /**
     * @dataProvider provider
     * @param string $name The base name of the test.
     * @param string $template_path The path to the template file.
     * @param string $json_path The path to the json
     * @param type $expected_path
     */
    public function testHandlebars($name, $template_path, $json_path, $expected_path) {
        // Check to see if the spec is being skipped.
        if (isset($this->skip[$name])) {
            $this->markTestSkipped($this->skip[$name]);
            return;
        }

        // Load and compile the template.
        $snidely = new Snidely();
        $snidely->compilerFlags = \Snidely\PhpCompiler::HANDLEBARS | \Snidely\PhpCompiler::STANDLONE;

        $snidely->cachePath($cache_path = PATH_TEST_CACHE.'/HandlebarsTest');
        if (!file_exists($snidely->cachePath()))
            mkdir($snidely->cachePath(), 0777, true);

        $snidely->registerPartialLoader([$this, 'partialLoader']);

        $fn = $snidely->compile(file_get_contents($template_path), basename($template_path, '.tpl'));

        // Run the template.
        $data = json_decode(file_get_contents($json_path), true);

        // Coerce the data to string similar to what javascript would render.
//        array_walk_recursive($data, function (&$val) {
//            if ($val === true)
//                $val = 'true';
//            elseif ($val === false)
//                $val = '';
//        });

        $actual = $snidely->fetch($fn, $data);

        // Compare to the expected.
        $expected = file_get_contents($expected_path);


//        if (strlen($actual) < 1024) {
            $this->assertStrippedEquals($expected, $actual);
//        } else {
            // assertEquals can have a stack overflow on long strings.
//            $this->assertTrue($expected === $actual);
//        }
    }

    /**
     * Handlebars doesn't strip whitespace like mustache or snidely.
     * This method strips all applicable whitespace before comparing two strings.
     * @param string $expected
     * @param string $actual
     */
    public function assertStrippedEquals($expected, $actual) {
        $expected = $this->normalizeString($expected);
        $actual = $this->normalizeString($actual);
        $this->assertEquals($expected, $actual);
    }

    protected function normalizeString($str) {
        $lines = explode("\n", $str);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines);
        return implode("\n", $lines);
    }

    public function partialLoader($name, $context, Scope $scope, Snidely $snidely) {
        $path = PATH_TEST."/HandlebarsTest/fixture/$name.tmpl";

        if (file_exists($path)) {
            $partial = $snidely->compile(file_get_contents($path));
            return $partial;
        }

        return null;
    }

    public function provider() {
        $name = '*';
        $template_paths = glob(PATH_TEST."/HandlebarsTest/fixture/$name.tmpl");

        $result = array();
        foreach ($template_paths as $path) {
            $basename = basename($path, '.tmpl');
            $json_paths = glob(PATH_TEST."/HandlebarsTest/fixture/$basename*.json");

            foreach ($json_paths as $json_path) {
                $base_test_name = basename($json_path, '.json');

                $args = array(
                    $base_test_name,
                    $path,
                    $json_path,
                    dirname($json_path)."/$base_test_name.txt"
                    );

                $key = $base_test_name;
                $i = 2;
                while (isset($result[$key])) {
                    $key = $base_test_name.$i;
                    $i++;
                }

                $result[$key] = $args;
            }
        }

        return $result;
    }
}