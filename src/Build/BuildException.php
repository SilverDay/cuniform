<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\CuniformException;

/**
 * Build-pipeline-stage failures that span more than one document — as
 * opposed to ContentException, which is about a single document or file.
 * Route-table validation (SPEC §8.2) is the first consumer.
 */
final class BuildException extends CuniformException
{
    public static function reservedSlugClaimed(string $slug, string $identifier): self
    {
        return new self("'{$identifier}' claims '{$slug}', which is a reserved slug (SPEC §8.2)");
    }

    public static function routeCollision(string $path, string $firstIdentifier, string $secondIdentifier): self
    {
        return new self(
            "Route collision at '{$path}': claimed by both '{$firstIdentifier}' and "
            . "'{$secondIdentifier}' (SPEC §8.2)"
        );
    }

    public static function alreadyLocked(string $lockPath): self
    {
        return new self("Another build is already running (lock held: {$lockPath}) — SPEC §10.1");
    }

    public static function imageDoesNotResolve(string $identifier, string $image): self
    {
        return new self("'{$identifier}': image '{$image}' does not resolve to a file under content/ (SPEC §5.5)");
    }

    public static function aliasCollidesWithRoute(string $identifier, string $alias): self
    {
        return new self(
            "'{$identifier}': alias '{$alias}' collides with a real route — an alias may never "
            . 'shadow a page or post that actually exists (SPEC §5.5)'
        );
    }

    /**
     * @param list<string> $errors
     */
    public static function fromErrors(array $errors): self
    {
        $lines = array_map(static fn (string $error): string => '- ' . $error, $errors);

        return new self("Build validation failed:\n" . implode("\n", $lines));
    }
}
