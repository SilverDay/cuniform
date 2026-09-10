<?php

declare(strict_types=1);

namespace Cuniform\Admin\Editor;

/**
 * Outcome discriminator for EditorDocumentStore::save()/move() — a business
 * outcome, not an exception, the same shape as Auth\LoginStatus/LoginOutcome:
 * "the operator submitted a stale version" or "this front matter is invalid"
 * are expected form-submission results the editor page re-renders inline,
 * not failures that should look like a 500.
 */
enum EditorSaveStatus
{
    case Saved;
    case Conflict;
    case Invalid;
    case NotFound;
}
