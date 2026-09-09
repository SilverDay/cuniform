<?php

declare(strict_types=1);

namespace Cuniform\Cli;

/**
 * Entry point for bin/cuniform. The build pipeline itself lands in T19-T23;
 * until then this validates the command shape and reports it as not implemented.
 */
final class Application
{
    private const KNOWN_FLAGS = ['full', 'dry-run', 'rollback'];

    /**
     * @param list<string> $arguments Command-line arguments, without the script name.
     */
    public function run(array $arguments): int
    {
        if ($arguments === []) {
            fwrite(STDERR, $this->usage());

            return 2;
        }

        $command = array_shift($arguments);

        if ($command !== 'build') {
            fwrite(STDERR, "cuniform: unknown command '{$command}'\n" . $this->usage());

            return 2;
        }

        foreach ($arguments as $argument) {
            if (!str_starts_with($argument, '--') || !in_array(substr($argument, 2), self::KNOWN_FLAGS, true)) {
                fwrite(STDERR, "cuniform: unknown option '{$argument}'\n" . $this->usage());

                return 2;
            }
        }

        fwrite(STDOUT, "cuniform: build pipeline not implemented yet (see docs/BUILD-ORDER.md, T19-T23)\n");

        return 1;
    }

    private function usage(): string
    {
        return <<<'TXT'
        Usage:
          cuniform build [--full] [--dry-run]
          cuniform build --rollback

        TXT;
    }
}
