<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Snidely\Standalone;

use Snidely\Snidely;
use Snidely\Compiler;

class StandaloneComplilerTest extends \PHPUnit_Framework_TestCase {
    public function testDiscussions() {
        $snidely = $this->getSnidely();
        $template = file_get_contents(__DIR__.'/fixtures/discussions.hbs');

        $fn = $snidely->compile($template, 'discussions');
    }

    public function testViewSummary() {
        $snidely = $this->getSnidely();
        
        $template = file_get_contents(__DIR__.'/fixtures/viewsummary.hbs');
        $fn = $snidely->compile($template, 'viewsummary');

        $template = file_get_contents(__DIR__.'/fixtures/viewsummary_sect.hbs');
        $fn = $snidely->compile($template, 'viewsummary_sect');
    }

    /**
     * Get a new {@link Snidely} object with appropriate flags set for standalone compilation.
     *
     * @return Snidely Returns the new snidely object.
     */
    public function getSnidely() {
        $snidely = new Snidely();

        $snidely->cachePath($cache_path = PATH_TEST_CACHE.'/Standalone');
        if (!file_exists($snidely->cachePath()))
            mkdir($snidely->cachePath(), 0777, true);

        $snidely->compilerFlags = Compiler::STANDLONE;

        return $snidely;
    }
}
