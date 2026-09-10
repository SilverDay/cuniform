<?php

declare(strict_types=1);

namespace Cuniform\Admin\Media;

/**
 * MediaUploadOutcome's status — same two-case shape as
 * Editor\EditorSaveStatus's Saved/Invalid pair, for the same reason: an
 * upload that fails validation is an expected business outcome the operator
 * needs to see, not an exception.
 */
enum MediaUploadStatus
{
    case Uploaded;
    case Invalid;
}
