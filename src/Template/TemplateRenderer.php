<?php

declare(strict_types=1);

namespace Cuniform\Template;

use Cuniform\Render\RenderException;

/**
 * Executes one plain-PHP template file with $view bound in its local scope
 * (SPEC §9) — no extract(), just an isolated closure parameter. A template
 * error propagates rather than being caught here, which is what "a template
 * error aborts the build; a partial page is never emitted" (§9) requires —
 * this class does not decide to continue past one.
 */
final class TemplateRenderer
{
    private static ?TemplateResolver $currentResolver = null;

    public function __construct(private readonly ?TemplateResolver $resolver = null)
    {
    }

    public static function currentResolver(): ?TemplateResolver
    {
        return self::$currentResolver;
    }

    public function render(string $templatePath, object $context): string
    {
        if (!is_file($templatePath)) {
            throw RenderException::templateNotFound($templatePath);
        }

        $previousResolver = self::$currentResolver;
        if ($this->resolver !== null) {
            self::$currentResolver = $this->resolver;
        }

        ob_start();

        try {
            (function (string $templatePath, object $context): void {
                include $templatePath;
            })($templatePath, $context);
        } catch (\Throwable $e) {
            ob_end_clean();

            throw $e;
        } finally {
            self::$currentResolver = $previousResolver;
        }

        return (string) ob_get_clean();
    }
}
