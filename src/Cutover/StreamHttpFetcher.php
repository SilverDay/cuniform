<?php

declare(strict_types=1);

namespace Cuniform\Cutover;

/**
 * The real HttpFetcher, using PHP's stream wrappers rather than ext-curl —
 * SPEC §4.2/§15.1 name no HTTP-client extension as even a soft
 * requirement, and streams need nothing beyond `allow_url_fopen` (PHP's
 * own default). `ignore_errors => true` is what makes a 404/500 response
 * still readable as a normal HttpResponse instead of `file_get_contents()`
 * returning false and emitting a warning — this fetcher needs to see a
 * legacy site's actual error responses, not just its successes.
 */
final class StreamHttpFetcher implements HttpFetcher
{
    public function __construct(private readonly int $timeoutSeconds = 10)
    {
    }

    public function fetch(string $url): HttpResponse
    {
        $context = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'header'          => "User-Agent: Cuniform-LegacyUrlCrawler/1.0 (+SPEC §15.5)\r\n",
                'timeout'         => $this->timeoutSeconds,
                'follow_location' => 1,
                'max_redirects'   => 5,
                'ignore_errors'   => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw CutoverException::fetchFailed($url);
        }

        $parsed = HttpResponseHeaderParser::parse($http_response_header);

        return new HttpResponse($parsed['statusCode'], $body, $parsed['contentType']);
    }
}
