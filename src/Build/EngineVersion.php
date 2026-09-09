<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * The "engine version" component of the incremental-build cache key
 * (SPEC §10.2) — bumped whenever a change to the engine itself (not
 * content, not templates, not the renderer fork, all of which already
 * have their own hash component) could change what a document renders
 * to, and there's no other signal that would already invalidate the
 * cache. Not tied to `composer.json` (a dev-only manifest, SPEC §4.2 —
 * nothing about it describes the running engine) or git state (a build
 * must be reproducible from the checkout alone, NFR-8).
 */
final class EngineVersion
{
    public const VERSION = '1.0.0';
}
