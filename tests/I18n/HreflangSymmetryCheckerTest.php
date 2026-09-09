<?php

declare(strict_types=1);

namespace Cuniform\Tests\I18n;

use Cuniform\Build\BuildException;
use Cuniform\I18n\HreflangEntry;
use Cuniform\I18n\HreflangSet;
use Cuniform\I18n\HreflangSymmetryChecker;
use PHPUnit\Framework\TestCase;

final class HreflangSymmetryCheckerTest extends TestCase
{
    public function testSymmetricSetsPassWithoutError(): void
    {
        $de = new HreflangSet([
            new HreflangEntry('de', '/de/x/'),
            new HreflangEntry('en', '/en/x/'),
            new HreflangEntry('x-default', '/en/'),
        ], '/de/x/');
        $en = new HreflangSet([
            new HreflangEntry('de', '/de/x/'),
            new HreflangEntry('en', '/en/x/'),
            new HreflangEntry('x-default', '/en/'),
        ], '/en/x/');

        (new HreflangSymmetryChecker())->assertSymmetric(['/de/x/' => $de, '/en/x/' => $en]);

        $this->addToAssertionCount(1);
    }

    public function testAsymmetricSetsThrow(): void
    {
        $de = new HreflangSet([
            new HreflangEntry('de', '/de/x/'),
            new HreflangEntry('en', '/en/x/'),
        ], '/de/x/');
        // /en/x/ does not advertise /de/x/ back.
        $en = new HreflangSet([
            new HreflangEntry('en', '/en/x/'),
        ], '/en/x/');

        $this->expectException(BuildException::class);
        $this->expectExceptionMessageMatches('/asymmetric hreflang/');
        (new HreflangSymmetryChecker())->assertSymmetric(['/de/x/' => $de, '/en/x/' => $en]);
    }

    public function testAdvertisingAUrlWithNoHreflangSetAtAllThrows(): void
    {
        $de = new HreflangSet([
            new HreflangEntry('de', '/de/x/'),
            new HreflangEntry('en', '/en/missing/'),
        ], '/de/x/');

        $this->expectException(BuildException::class);
        $this->expectExceptionMessageMatches('/has no hreflang set/');
        (new HreflangSymmetryChecker())->assertSymmetric(['/de/x/' => $de]);
    }

    public function testXDefaultNeverNeedsReciprocation(): void
    {
        $de = new HreflangSet([
            new HreflangEntry('de', '/de/x/'),
            new HreflangEntry('x-default', '/en/'),
        ], '/de/x/');

        (new HreflangSymmetryChecker())->assertSymmetric(['/de/x/' => $de]);

        $this->addToAssertionCount(1);
    }

    public function testSelfReferenceNeverNeedsExternalReciprocation(): void
    {
        $de = new HreflangSet([
            new HreflangEntry('de', '/de/x/'),
        ], '/de/x/');

        (new HreflangSymmetryChecker())->assertSymmetric(['/de/x/' => $de]);

        $this->addToAssertionCount(1);
    }
}
