<?php

declare(strict_types=1);

namespace Cuniform\Admin\Media;

/**
 * MediaUploader::upload()'s result — same outcome-object pattern as
 * Auth\LoginOutcome, Editor\EditorSaveOutcome, and Preview\PreviewResult.
 */
final class MediaUploadOutcome
{
    /**
     * @param list<string> $errors
     */
    private function __construct(
        public readonly MediaUploadStatus $status,
        public readonly ?MediaFile $file,
        public readonly array $errors,
    ) {
    }

    public static function uploaded(MediaFile $file): self
    {
        return new self(MediaUploadStatus::Uploaded, $file, []);
    }

    /**
     * @param list<string> $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(MediaUploadStatus::Invalid, null, $errors);
    }
}
