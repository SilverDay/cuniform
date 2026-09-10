<?php

declare(strict_types=1);

namespace Cuniform\Admin\Preview;

/**
 * PreviewRenderer::render()'s outcome — an expected business result
 * (invalid front matter, an unresolvable document) rather than an
 * exception, same pattern as Auth\LoginOutcome and Editor\EditorSaveOutcome.
 */
final class PreviewResult
{
    /**
     * @param list<string> $errors
     */
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $html,
        public readonly array $errors,
    ) {
    }

    public static function rendered(string $html): self
    {
        return new self(true, $html, []);
    }

    /**
     * @param list<string> $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
