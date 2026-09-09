<?php

declare(strict_types=1);

namespace Cuniform\Tests\Cutover;

use Cuniform\Cutover\HttpResponseHeaderParser;
use PHPUnit\Framework\TestCase;

final class HttpResponseHeaderParserTest extends TestCase
{
    public function testParsesAPlainSuccessfulResponse(): void
    {
        $result = HttpResponseHeaderParser::parse([
            'HTTP/1.1 200 OK',
            'Content-Type: text/html; charset=UTF-8',
            'Server: nginx',
        ]);

        self::assertSame(200, $result['statusCode']);
        self::assertSame('text/html; charset=UTF-8', $result['contentType']);
    }

    public function testUsesTheLastHopAfterARedirect(): void
    {
        $result = HttpResponseHeaderParser::parse([
            'HTTP/1.1 301 Moved Permanently',
            'Location: /new-path/',
            'Content-Type: text/html',
            'HTTP/1.1 200 OK',
            'Content-Type: text/plain',
        ]);

        self::assertSame(200, $result['statusCode']);
        self::assertSame('text/plain', $result['contentType']);
    }

    public function testMissingContentTypeIsNull(): void
    {
        $result = HttpResponseHeaderParser::parse(['HTTP/1.1 204 No Content']);

        self::assertSame(204, $result['statusCode']);
        self::assertNull($result['contentType']);
    }

    public function testEmptyInputYieldsAZeroStatusCode(): void
    {
        $result = HttpResponseHeaderParser::parse([]);

        self::assertSame(0, $result['statusCode']);
        self::assertNull($result['contentType']);
    }
}
