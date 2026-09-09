<?php

declare(strict_types=1);

namespace Cuniform\Render\Shortcode;

use Cuniform\Render\RenderException;

final class ShortcodeHandlerRegistry
{
    /** @var array<string, ShortcodeHandler> */
    private array $handlers = [];

    /**
     * @param list<ShortcodeHandler> $handlers
     */
    public function __construct(array $handlers = [])
    {
        foreach ($handlers as $handler) {
            if (isset($this->handlers[$handler->name()])) {
                throw RenderException::duplicateHandler($handler->name());
            }

            $this->handlers[$handler->name()] = $handler;
        }
    }

    public function has(string $name): bool
    {
        return isset($this->handlers[$name]);
    }

    public function get(string $name): ShortcodeHandler
    {
        return $this->handlers[$name];
    }
}
