<?php

use Snidely\Snidely;
use Snidely\PhpCompiler;


class MustacheSpecTest extends PHPUnit_Framework_TestCase {
    public $skip = array(
//        'delimiters-sections' => 'Snidely does not support context stack traversal.',
        'partials-standalone-without-previous-line' => "Snidely doesn't indent partials.",
        'partials-standalone-without-newline' => "Snidely doesn't indent partials.",
        'partials-standalone-indentation' => "Snidely doesn't indent partials.",
//        'sections-deeply-nested-contexts' => 'Snidely does not support context stack traversal.',
//        'sections-nested-truthy' => 'Snidely does not support context stack traversal.',
        );

    protected function runSpec($basename, $number, $spec) {
        $name = preg_replace('`([^a-z0-9]+)`i', '-', $spec['name']);
        $name = preg_replace('`(-+)`', '-', $name);
        $name = trim($name, '-');

        $key = strtolower($basename.'-'.str_replace(' ', '-', $name));

        // Check to see if the spec is being skipped.
        if (isset($this->skip[$key])) {
            $this->markTestSkipped($this->skip[$key]);
            return;
        }


        $snidely = new Snidely();
        $snidely->compilerFlags = PhpCompiler::MUSTACHE;

        $snidely->cachePath($cache_path = PATH_TEST_CACHE.'/MustacheSpecTest');
        if (!file_exists($snidely->cachePath()))
            mkdir($snidely->cachePath(), 0777, true);

        // Register any partials.
        if (isset($spec['partials']) && is_array($spec['partials'])) {
            foreach ($spec['partials'] as $name => $template) {
                $snidely->registerPartial($name, $template);
            }
        }
        $sort_key = strtolower($basename.'-'.sprintf('%02d', $number).'-'.$name);
        $fn = $snidely->compile($spec['template'], $sort_key);

        $result = $snidely->fetch($fn, $spec['data']);

        $this->assertEquals($spec['expected'], $result, $spec['desc']);
    }

    /**
     * @dataProvider provideComments
     */
    public function testComments($basename, $number, $spec) {
        $this->runSpec($basename, $number, $spec);
    }

    /**
     * @dataProvider provideDelimiters
     */
    public function testDelimiters($basename, $number, $spec) {
        $this->runSpec($basename, $number, $spec);
    }

    /**
     * @dataProvider provideInterpolation
     */
    public function testInterpolation($basename, $number, $spec) {
        $this->runSpec($basename, $number, $spec);
    }

    /**
     * @dataProvider provideInverted
     */
    public function testInverted($basename, $number, $spec) {
        $this->runSpec($basename, $number, $spec);
    }

    /**
     * @dataProvider providePartials
     */
    public function testPartials($basename, $number, $spec) {
        $this->runSpec($basename, $number, $spec);
    }

    /**
     * @dataProvider provideSections
     */
    public function testSections($basename, $number, $spec) {
        $this->runSpec($basename, $number, $spec);
    }

    public function provideComments() {
        return $this->provider('comments');
    }

    public function provideDelimiters() {
        return $this->provider('delimiters');
    }

    public function provideInterpolation() {
        return $this->provider('interpolation');
    }

    public function provideInverted() {
        return $this->provider('inverted');
    }

    public function providePartials() {
        return $this->provider('partials');
    }

    public function provideSections() {
        return $this->provider('sections');
    }

    protected function provider($name) {
        $paths = glob(PATH_TEST."/mustache-spec/specs/$name.json");

        if (empty($paths)) {
            $this->markTestSkipped("Skipping $name. Make sure you symlink git@github.com:mustache/spec.git as mustache-spec.");
        }

        $result = array();
        $i = 0;
        foreach ($paths as $path) {
            $basename = basename($path, '.json');
            $json = json_decode(file_get_contents($path), true);
            $tests = $json['tests'];
            foreach ($tests as $test) {
                $result["$name-{$test['name']}"] = array($basename, $i, $test);
                $i++;
            }
        }
        return $result;
    }
}