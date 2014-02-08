<?php

use Snidely\Snidely;
use Symfony\Component\Yaml\Yaml;


class MustacheSpecTest extends PHPUnit_Framework_TestCase {
    public $skip = array(
        'delimiters-sections' => 'Snidely does not support context stack traversal.',
        'partials-standalone-without-previous-line' => "Snidely doesn't indent partials.",
        'partials-standalone-without-newline' => "Snidely doesn't indent partials.",
        'partials-standalone-indentation' => "Snidely doesn't indent partials.",
        'sections-deeply-nested-contexts' => 'Snidely does not support context stack traversal.',
        'sections-nested-truthy' => 'Snidely does not support context stack traversal.',
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

        $snidely->cachePath($cache_path = __DIR__.'/cache/mustache-spec');
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

        ob_start();
        $snidely->pushErrorReporting();
        $fn($spec['data']);
        $snidely->popErrorReporting();
        $result = ob_get_clean();

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
        $paths = glob(__DIR__."/mustache-spec/specs/$name.yml");

        if (empty($paths)) {
            $this->markTestSkipped("Skipping $name. Make sure you symlink git@github.com:mustache/spec.git as mustache-spec.");
        }

        $result = array();
        $i = 0;
        foreach ($paths as $path) {
            $basename = basename($path, '.yml');
            $json = Yaml::parse($path);
            $tests = $json['tests'];
            foreach ($tests as $test) {
                $result[] = array($basename, $i, $test);
            }
        }
        return $result;
    }
}