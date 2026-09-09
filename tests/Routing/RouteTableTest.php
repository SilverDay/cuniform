<?php

declare(strict_types=1);

namespace Cuniform\Tests\Routing;

use Cuniform\Build\BuildException;
use Cuniform\Routing\RouteTable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouteTableTest extends TestCase
{
    public function testRegistersDistinctRoutesWithoutError(): void
    {
        $table = new RouteTable(['de', 'en']);
        $table->register('/de/sicherheitskultur/', 'sicherheitskultur', 'post:1');
        $table->register('/en/security-culture/', 'security-culture', 'post:2');

        self::assertTrue($table->has('/de/sicherheitskultur/'));
        self::assertTrue($table->has('/en/security-culture/'));
    }

    public function testCollidingPathsThrow(): void
    {
        $table = new RouteTable(['de']);
        $table->register('/de/about/', 'about', 'page:1');

        $this->expectException(BuildException::class);
        $this->expectExceptionMessageMatches("/Route collision at '\/de\/about\/'/");
        $table->register('/de/about/', 'about', 'post:1');
    }

    public function testRegisteringTheSamePathAndIdentifierTwiceIsNotACollision(): void
    {
        // Re-registering the exact same document (e.g. an incremental rebuild
        // re-processing one file) is not a collision with itself.
        $table = new RouteTable(['de']);
        $table->register('/de/about/', 'about', 'page:1');
        $table->register('/de/about/', 'about', 'page:1');

        self::assertTrue($table->has('/de/about/'));
    }

    #[DataProvider('reservedFirstSegments')]
    public function testReservedFirstSegmentIsRejected(string $segment): void
    {
        $table = new RouteTable(['de', 'en']);

        $this->expectException(BuildException::class);
        $this->expectExceptionMessageMatches("/reserved slug/");
        $table->register("/{$segment}/", $segment, 'post:1');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function reservedFirstSegments(): iterable
    {
        yield 'language code' => ['en'];
        yield 'tag' => ['tag'];
        yield 'series' => ['series'];
        yield 'archive' => ['archive'];
        yield 'page' => ['page'];
        yield 'search' => ['search'];
        yield 'admin' => ['admin'];
        yield 'media' => ['media'];
        yield 'feed.xml' => ['feed.xml'];
        yield 'atom.xml' => ['atom.xml'];
        yield 'sitemap.xml' => ['sitemap.xml'];
        yield 'robots.txt' => ['robots.txt'];
        yield 'well-known' => ['.well-known'];
    }

    public function testANonReservedSlugSimilarToAReservedOneIsAllowed(): void
    {
        // "tagged" is not "tag" — must not false-positive on a substring match.
        $table = new RouteTable(['de']);
        $table->register('/tagged/', 'tagged', 'post:1');

        self::assertTrue($table->has('/tagged/'));
    }

    public function testALegalPageSlugClaimedByAnotherDocumentIsCaughtAsAnOrdinaryCollision(): void
    {
        // No separate "legal slug" mechanism needed: a different document
        // claiming the same slug produces the same path, caught here.
        $table = new RouteTable(['de']);
        $table->register('/de/legal-notice/', 'legal-notice', 'page:impressum');

        $this->expectException(BuildException::class);
        $table->register('/de/legal-notice/', 'legal-notice', 'post:accidental');
    }
}
