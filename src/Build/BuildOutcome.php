<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * One build log entry's outcome (SPEC §15.4). Only a real, non-dry-run
 * build attempt is ever logged (BuildLogWriter's own docblock) — a dry
 * run changes nothing live, so it has no "outcome" a dashboard's "last
 * build status" should reflect.
 */
enum BuildOutcome: string
{
    case Success = 'success';
    case Failed = 'failed';
}
