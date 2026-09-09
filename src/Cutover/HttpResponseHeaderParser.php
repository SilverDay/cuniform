<?php

declare(strict_types=1);

namespace Cuniform\Cutover;

/**
 * Parses the shape PHP's `$http_response_header` produces after a stream
 * `file_get_contents()` GET (StreamHttpFetcher's only caller): a flat list
 * of header lines, with one `HTTP/x.y NNN ...` status line per hop when
 * `follow_location` followed one or more redirects — everything from the
 * *last* hop is what actually answered the request, so that's the block
 * this returns. A pure function of that array, kept separate from
 * StreamHttpFetcher specifically so it's unit-testable without a real
 * HTTP round trip.
 */
final class HttpResponseHeaderParser
{
    /**
     * @param  list<string>                              $rawHeaders
     * @return array{statusCode: int, contentType: ?string}
     */
    public static function parse(array $rawHeaders): array
    {
        $blocks  = [];
        $current = [];

        foreach ($rawHeaders as $line) {
            if (self::isStatusLine($line) && $current !== []) {
                $blocks[] = $current;
                $current  = [];
            }

            $current[] = $line;
        }

        if ($current !== []) {
            $blocks[] = $current;
        }

        $lastBlock = $blocks[count($blocks) - 1] ?? [];

        $statusCode = 0;
        if (isset($lastBlock[0]) && preg_match('#^HTTP/\d(?:\.\d)?\s+(\d{3})#', $lastBlock[0], $matches)) {
            $statusCode = (int) $matches[1];
        }

        $contentType = null;
        foreach ($lastBlock as $line) {
            if (preg_match('#^Content-Type:\s*(.+)$#i', $line, $matches)) {
                $contentType = trim($matches[1]);
            }
        }

        return ['statusCode' => $statusCode, 'contentType' => $contentType];
    }

    private static function isStatusLine(string $line): bool
    {
        return (bool) preg_match('#^HTTP/\d(?:\.\d)?\s+\d{3}#', $line);
    }
}
