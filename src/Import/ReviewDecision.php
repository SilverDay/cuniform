<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * The outcome of SPEC §A.5's manual review for one imported document.
 * `Pending` is both "not yet reviewed" and the file's own default state —
 * there's no separate boolean for that.
 */
enum ReviewDecision: string
{
    case Pending = 'pending';
    case Keep = 'keep';
    case Reject = 'reject';
}
