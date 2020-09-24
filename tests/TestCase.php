<?php

namespace Nesk\Puphpeteer\Tests;

use Nesk\Puphpeteer\Puppeteer;
use Monolog\Logger;
use ReflectionClass;
use Psr\Log\LogLevel;
use Symfony\Component\Process\Process;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\TestCase as BaseTestCase;
use PHPUnit\Framework\MockObject\Matcher\Invocation;

class TestCase extends BaseTestCase
{
    private $dontPopulateProperties = [];

    public function setUp(): void
    {
        parent::setUp();

        $testMethod = new \ReflectionMethod($this, $this->getName());
        $docComment = $testMethod->getDocComment();

        if (preg_match('/@dontPopulateProperties (.*)/', $docComment, $matches)) {
            $this->dontPopulateProperties = array_values(array_filter(explode(' ', $matches[1])));
        }
    }

    /**
     * Serves the resources folder locally on port 8089
     */
    protected function serveResources(): void
    {
        // Spin up a local server to deliver the resources.
        $this->host = '127.0.0.1:8089';
        $this->url = "http://{$this->host}";
        $this->serverDir = __DIR__.'/resources';

        $this->servingProcess = new Process(['php', '-S', $this->host, '-t', $this->serverDir]);
        $this->servingProcess->start();
    }

    /**
     * Launches the PuPHPeteer-controlled browser
     */
    protected function launchBrowser(): void
    {
        // Chrome doesn't support Linux sandbox on many CI environments
        // See: https://github.com/GoogleChrome/puppeteer/blob/master/docs/troubleshooting.md#chrome-headless-fails-due-to-sandbox-issues
        $this->browserOptions = ['args' => ['--no-sandbox', '--disable-setuid-sandbox']];

        if ($this->canPopulateProperty('browser')) {
            $this->browser = (new Puppeteer)->launch($this->browserOptions);
        }
    }

    /**
     * Stops the browser and local server
     */
    public function tearDown(): void
    {
        // Close the browser.
        if (isset($this->browser)) {
            $this->browser->close();
        }

        // Shutdown the local server
        if (isset($this->servingProcess)) {
            $this->servingProcess->stop(0);
        }
    }

    public function canPopulateProperty(string $propertyName): bool
    {
        return !in_array($propertyName, $this->dontPopulateProperties);
    }

    public function loggerMock($expectations) {
        $loggerMock = $this->getMockBuilder(Logger::class)
            ->setConstructorArgs(['rialto'])
            ->setMethods(['log'])
            ->getMock();

        if ($expectations instanceof Invocation) {
            $expectations = [func_get_args()];
        }

        foreach ($expectations as $expectation) {
            [$matcher] = $expectation;
            $with = array_slice($expectation, 1);

            $loggerMock->expects($matcher)
                ->method('log')
                ->with(...$with);
        }

        return $loggerMock;
    }

    public function isLogLevel(): Callback {
        $psrLogLevels = (new ReflectionClass(LogLevel::class))->getConstants();
        $monologLevels = (new ReflectionClass(Logger::class))->getConstants();
        $monologLevels = array_intersect_key($monologLevels, $psrLogLevels);

        return $this->callback(function ($level) use ($psrLogLevels, $monologLevels) {
            if (is_string($level)) {
                return in_array($level, $psrLogLevels, true);
            } else if (is_int($level)) {
                return in_array($level, $monologLevels, true);
            }

            return false;
        });
    }
}
