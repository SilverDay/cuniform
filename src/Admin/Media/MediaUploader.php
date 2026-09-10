<?php

declare(strict_types=1);

namespace Cuniform\Admin\Media;

use Cuniform\Admin\AdminException;
use Cuniform\CuniformException;

/**
 * Validates and stores one upload (SPEC §13.2). Three independent checks
 * must all agree on the same image format before anything is decoded:
 * the original filename's extension, the browser-reported content type,
 * and the file's own magic bytes. A mismatch between any two of them —
 * a `.png` that is actually a JPEG, a browser lying about the content
 * type — is refused outright rather than resolved by trusting whichever
 * signal looks most authoritative; SPEC names all three as checks to
 * perform, not as a priority order to fall back through.
 *
 * The stored filename is always engine-generated (random, SPEC §13.2) —
 * $originalFilename is read for its extension and echoed back to the
 * operator, never used to build a path (security.md: "A path never comes
 * from request input").
 */
final class MediaUploader
{
    public function __construct(
        private readonly string $mediaRoot,
        private readonly MediaReencoder $reencoder = new MediaReencoder(),
    ) {
    }

    public function upload(MediaUploadRequest $request): MediaUploadOutcome
    {
        if (!is_file($request->tmpPath)) {
            return MediaUploadOutcome::invalid(['no uploaded file was received']);
        }

        $extensionFormat = MediaFormat::fromExtension(pathinfo($request->originalFilename, PATHINFO_EXTENSION));
        if ($extensionFormat === null) {
            $allowed = implode(', ', MediaFormat::allowedExtensions());

            return MediaUploadOutcome::invalid(["'{$request->originalFilename}' has a file extension that isn't allowed — allowed: {$allowed}"]);
        }

        $mimeFormat = MediaFormat::fromMimeType($request->reportedMimeType);
        if ($mimeFormat === null) {
            return MediaUploadOutcome::invalid(["the browser reported an unsupported content type: '{$request->reportedMimeType}'"]);
        }

        $header      = (string) file_get_contents($request->tmpPath, false, null, 0, 32);
        $magicFormat = MediaMagicBytes::detect($header);
        if ($magicFormat === null) {
            return MediaUploadOutcome::invalid(["the file's contents are not a recognized image format (allowed: JPEG, PNG, WebP)"]);
        }

        if ($extensionFormat !== $mimeFormat || $extensionFormat !== $magicFormat) {
            return MediaUploadOutcome::invalid([
                "the file extension ('.{$extensionFormat->primaryExtension()}' implied), the browser's reported "
                . "content type ('{$mimeFormat->mimeType()}'), and the file's actual contents "
                . "('{$magicFormat->mimeType()}') don't all agree on the image format — this upload "
                . 'is refused rather than guessed at',
            ]);
        }

        try {
            $reencoded = $this->reencoder->reencode($request->tmpPath, $magicFormat);

            return MediaUploadOutcome::uploaded($this->store($reencoded, $magicFormat));
        } catch (CuniformException $e) {
            return MediaUploadOutcome::invalid([$e->getMessage()]);
        }
    }

    private function store(ReencodedImage $image, MediaFormat $format): MediaFile
    {
        $now      = new \DateTimeImmutable();
        $filename = bin2hex(random_bytes(16)) . '.' . $format->primaryExtension();
        $relative = $now->format('Y') . '/' . $now->format('m') . '/' . $filename;
        $absolute = rtrim($this->mediaRoot, '/') . '/' . $relative;

        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw AdminException::mediaWriteFailed($dir);
        }

        $tmp = $absolute . '.tmp-' . bin2hex(random_bytes(8));
        if (file_put_contents($tmp, $image->bytes) === false) {
            throw AdminException::mediaWriteFailed($absolute);
        }

        if (!rename($tmp, $absolute)) {
            @unlink($tmp);

            throw AdminException::mediaWriteFailed($absolute);
        }

        return new MediaFile($relative, '/media/' . $relative, strlen($image->bytes), $image->width, $image->height);
    }
}
