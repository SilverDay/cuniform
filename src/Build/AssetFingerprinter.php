<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * `style.<hash8>.css` with `Cache-Control: immutable` (SPEC §11.4) — the
 * immutable cache header itself is an Apache response-header concern
 * (§14.2, vhost config), not something a static file can carry; what this
 * class owns is the fingerprinted filename that makes an immutable cache
 * header safe to set in the first place, since a content change always
 * produces a new URL.
 */
final class AssetFingerprinter
{
    /**
     * @return array{0: ArtifactFile, 1: string} [the file to write, its
     *         site-root-relative URL, e.g. "/style.a1b2c3d4.css"]
     */
    public function fingerprint(string $sourcePath): array
    {
        if (!is_file($sourcePath)) {
            throw BuildException::fromErrors(["stylesheet source not found: {$sourcePath}"]);
        }

        $contents = file_get_contents($sourcePath);
        if ($contents === false) {
            throw BuildException::fromErrors(["could not read stylesheet source: {$sourcePath}"]);
        }

        $hash     = substr(hash('sha256', $contents), 0, 8);
        $filename = "style.{$hash}.css";

        return [new ArtifactFile($filename, $contents), "/{$filename}"];
    }
}
