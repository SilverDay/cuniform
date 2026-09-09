<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Config\Config;

/**
 * `/.well-known/security.txt` (RFC 9116, SPEC §3.3, §11) — emitted as
 * ordinary release content, outside the ACME-only part of `.well-known/`
 * (§3.3's Alias is scoped to `acme-challenge/` specifically, so this path
 * is free for the build to own). RFC 9116 requires `Contact` and `Expires`;
 * `config.mail.notify` (already the operator's own notification address,
 * SPEC §15.2) doubles as the security contact rather than inventing a
 * second config key for it. `Expires` is capped at one year out, per the
 * RFC's own recommendation against a longer-lived value.
 */
final class SecurityTxtGenerator
{
    public function __construct(private readonly Config $config)
    {
    }

    public function generate(\DateTimeImmutable $now): ArtifactFile
    {
        $canonical = rtrim($this->config->baseUrl, '/') . '/.well-known/security.txt';
        $expires   = $now->modify('+1 year')->setTime(0, 0, 0)->format('Y-m-d\TH:i:s.000\Z');

        $lines = [
            "Contact: mailto:{$this->config->mail->notify}",
            "Expires: {$expires}",
            "Canonical: {$canonical}",
        ];

        return new ArtifactFile('.well-known/security.txt', implode("\n", $lines) . "\n");
    }
}
