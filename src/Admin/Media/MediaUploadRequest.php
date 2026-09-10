<?php

declare(strict_types=1);

namespace Cuniform\Admin\Media;

/**
 * One upload attempt, already past the HTTP-layer checks (`admin/media.php`
 * confirms `is_uploaded_file()` and `UPLOAD_ERR_OK` before ever constructing
 * one of these — see that file's own comment, same "thin, untested
 * real-I/O boundary" split T25/T28 already established for this codebase).
 * $originalFilename is used only to read its extension and to echo back to
 * the operator — never as, or as part of, a filesystem path (SPEC §13.2:
 * "path validation ... never a path from the request"; the stored filename
 * is always engine-generated, see MediaUploader).
 */
final class MediaUploadRequest
{
    public function __construct(
        public readonly string $tmpPath,
        public readonly string $originalFilename,
        public readonly string $reportedMimeType,
    ) {
    }
}
