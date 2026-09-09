<?php

declare(strict_types=1);

namespace Cuniform\Config;

use Cuniform\CuniformException;

final class ConfigException extends CuniformException
{
    /**
     * @param list<string> $errors
     */
    public static function fromErrors(array $errors): self
    {
        $lines = array_map(static fn (string $error): string => '- ' . $error, $errors);

        return new self("Invalid configuration:\n" . implode("\n", $lines));
    }
}
