<?php

declare(strict_types=1);

namespace Cuniform\Import;

use Cuniform\CuniformException;

/**
 * Failures specific to the P3 WordPress import (SPEC Appendix A) — kept
 * separate from BuildException/ContentException: this pipeline runs
 * standalone, ahead of a build (SPEC §1.2), against operator-supplied
 * input (a WXR file), not `content/`.
 */
final class ImportException extends CuniformException
{
    public static function fileNotReadable(string $path): self
    {
        return new self("could not read WXR export: {$path}");
    }

    public static function malformedXml(string $detail): self
    {
        return new self("malformed WXR XML: {$detail}");
    }

    /**
     * @param list<string> $errors
     */
    public static function fromErrors(array $errors): self
    {
        $lines = array_map(static fn (string $error): string => '- ' . $error, $errors);

        return new self("WXR import failed:\n" . implode("\n", $lines));
    }
}
