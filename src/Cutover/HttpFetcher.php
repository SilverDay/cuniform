<?php

declare(strict_types=1);

namespace Cuniform\Cutover;

/**
 * One GET request. Implementations: StreamHttpFetcher (real, PHP streams —
 * no runtime dependency, SPEC §4.2) in production; a fake in tests, so
 * LegacyUrlCrawler's own logic is testable with zero network access
 * (php-style.md: "No network ... in tests").
 */
interface HttpFetcher
{
    /**
     * @throws CutoverException On a transport-level failure (DNS, connection
     *                          refused, timeout). A non-2xx HTTP status is
     *                          NOT an exception — it's an ordinary
     *                          HttpResponse the caller decides what to do
     *                          with.
     */
    public function fetch(string $url): HttpResponse;
}
