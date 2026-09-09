<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Config\Config;

/**
 * "The URL scheme changed since the current release (...) without
 * --allow-url-scheme-change" (SPEC §10.3) — a different `url_prefix`
 * result, a changed `default_language`, or a language added or removed
 * silently moves every URL on the site, so it's blocked unless the flag
 * states the intent explicitly. The previous build's scheme is read from
 * `var/last-build-meta.json` (not part of the release tree — this is
 * build-internal bookkeeping, never served) and rewritten there after a
 * successful, non-dry-run build (BuildPipeline's job, not this class's —
 * a failed or dry-run build must never update the stored baseline).
 */
final class UrlSchemeGuard
{
    public function __construct(private readonly string $metaPath)
    {
    }

    /**
     * @throws BuildException
     */
    public function assert(Config $config, bool $allowChange): void
    {
        $message = $this->check($config, $allowChange);
        if ($message !== null) {
            throw BuildException::fromErrors([$message]);
        }
    }

    /**
     * Same check as assert(), but returns the violation message instead of
     * throwing — see PageCountGuard::check() for why BuildVerifier uses
     * this form rather than catching an already-wrapped exception.
     */
    public function check(Config $config, bool $allowChange): ?string
    {
        $previous = $this->read();
        if ($previous === null) {
            return null;
        }

        $current = $this->snapshot($config);
        if ($previous === $current || $allowChange) {
            return null;
        }

        return $this->describeChange($previous, $current);
    }

    public function persist(Config $config): void
    {
        $dir = dirname($this->metaPath);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw BuildException::fromErrors(["could not create directory: {$dir}"]);
        }

        $json = json_encode($this->snapshot($config), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);
        if (file_put_contents($this->metaPath, $json) === false) {
            throw BuildException::fromErrors(["could not write: {$this->metaPath}"]);
        }
    }

    /**
     * @return array{url_prefix: string, default_language: string, languages: list<string>}
     */
    private function snapshot(Config $config): array
    {
        $languages = $config->languages;
        sort($languages);

        return [
            'url_prefix'       => $config->urlPrefix->value,
            'default_language' => $config->defaultLanguage,
            'languages'        => $languages,
        ];
    }

    /**
     * @return array{url_prefix: string, default_language: string, languages: list<string>}|null
     */
    private function read(): ?array
    {
        if (!is_file($this->metaPath)) {
            return null;
        }

        $raw = file_get_contents($this->metaPath);
        if ($raw === false) {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (
            !is_array($decoded)
            || !isset($decoded['url_prefix'], $decoded['default_language'], $decoded['languages'])
            || !is_string($decoded['url_prefix'])
            || !is_string($decoded['default_language'])
            || !is_array($decoded['languages'])
        ) {
            return null;
        }

        return [
            'url_prefix'       => $decoded['url_prefix'],
            'default_language' => $decoded['default_language'],
            'languages'        => array_values($decoded['languages']),
        ];
    }

    /**
     * @param array{url_prefix: string, default_language: string, languages: list<string>} $old
     * @param array{url_prefix: string, default_language: string, languages: list<string>} $new
     */
    private function describeChange(array $old, array $new): string
    {
        $changes = [];
        if ($old['url_prefix'] !== $new['url_prefix']) {
            $changes[] = "url_prefix: '{$old['url_prefix']}' -> '{$new['url_prefix']}'";
        }

        if ($old['default_language'] !== $new['default_language']) {
            $changes[] = "default_language: '{$old['default_language']}' -> '{$new['default_language']}'";
        }

        if ($old['languages'] !== $new['languages']) {
            $changes[] = "languages: [" . implode(',', $old['languages']) . '] -> [' . implode(',', $new['languages']) . ']';
        }

        return 'URL scheme changed since the last build (' . implode('; ', $changes) . ') without '
            . '--allow-url-scheme-change (SPEC §10.3). Every URL on the site moves — update the vhost '
            . "before deploying:\n"
            . "  - RedirectMatch 302 ^/\$ /{$new['default_language']}/  (SPEC §7.4.1)\n"
            . '  - <Location "/' . implode('/">, <Location "/', $new['languages']) . "/\"> ErrorDocument 404 blocks (SPEC §3.2, §7.12)\n"
            . '  - reserved language segments at the route root (SPEC §7.4): '
            . implode(', ', $new['languages']);
    }
}
