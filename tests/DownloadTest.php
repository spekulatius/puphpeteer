<?php

namespace Nesk\Puphpeteer\Tests;

use Nesk\Rialto\Data\JsFunction;

class DownloadTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Serve the content of the resources to test these.
        $this->serveResources();

        // Launch the browser to run tests on.
        $this->launchBrowser();
    }

    /**
     * @test
     */
    public function baseTest(): void
    {
        // Go to the image URL directly and attempt to download it.
        $page = $this->browser->newPage();
        $page->goto($this->url);

        // Get the title
        $title = $page->querySelectorEval(
            'title',
            JsFunction::createWithParameters(['node'])
                ->body('return node.textContent;')
        );
        $this->assertEquals('Document', $title);
    }

    /**
     * Attempts to download a small file (puppeteer-logo.png, 86kb)
     *
     * @test
     */
    public function downloadSmallFileTest(): void
    {
        // Go to the image URL directly and attempt to download it.
        $page = $this->browser->newPage();


        // works, but doesn't actually download anything - displayed in a preview page. See saved file.
        // $page->goto($this->url . '/puphpeteer-logo.png');
        // $pdf = $page->content();
        // file_put_contents('/tmp/test.html', $pdf);




        // Directly accessing a binary file - doesn't work.
        // $page->goto($this->url . '/Puppeteer.pdf');
        // $pdf = $page->content();




        // Attempt to download the file using JS.
        // https://stackoverflow.com/questions/49245080/how-to-download-file-with-puppeteer-using-headless-true
        $page->goto($this->url);

        $file = $page->evaluate(JsFunction::createWithBody(sprintf(
            // JS helper script to fetch the resource and return the content
            "
                return fetch('%s', {
                    method: 'GET',
                    credentials: 'include'
                }).then(r => r.text());
            ",
            $this->url . '/Puppeteer.pdf'
        )));

        // Binary-safe comparing of the two strings.
        $this->assertTrue(strcmp(
            $file,
            file_get_contents('tests/resources/Puppeteer.pdf')
        ));
    }
}
