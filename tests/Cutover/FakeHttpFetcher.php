<?php

declare(strict_types=1);

namespace Cuniform\Tests\Cutover;

use Cuniform\Cutover\CutoverException;
use Cuniform\Cutover\HttpFetcher;
use Cuniform\Cutover\HttpResponse;

/**
 * A canned-response HttpFetcher for tests — LegacyUrlCrawler's own logic
 * is tested against this, never against a real network call
 * (php-style.md: "No network ... in tests").
 */
final class FakeHttpFetcher implements HttpFetcher
{
    /** @var array<string, HttpResponse> */
    private array $responses = [];

    /** @var list<string> */
    public array $requestedUrls = [];

    public function respond(string $url, int $statusCode, string $body = '', ?string $contentType = null): self
    {
        $this->responses[$url] = new HttpResponse($statusCode, $body, $contentType);

        return $this;
    }

    public function fetch(string $url): HttpResponse
    {
        $this->requestedUrls[] = $url;

        if (!isset($this->responses[$url])) {
            throw CutoverException::fetchFailed($url);
        }

        return $this->responses[$url];
    }
}
