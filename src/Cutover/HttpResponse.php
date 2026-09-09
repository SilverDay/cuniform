<?php

declare(strict_types=1);

namespace Cuniform\Cutover;

/**
 * One HTTP response, already past any redirects the fetcher followed
 * (SPEC §15.5's crawl doesn't need to distinguish "redirected then
 * resolved" from "resolved directly" — either way the URL is live).
 */
final class HttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        public readonly ?string $contentType,
    ) {
    }
}
