<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Media;

use Cuniform\Admin\Media\MediaFormat;
use Cuniform\Admin\Media\MediaUploader;
use Cuniform\Admin\Media\MediaUploadRequest;
use Cuniform\Admin\Media\MediaUploadStatus;
use PHPUnit\Framework\TestCase;

final class MediaUploaderTest extends TestCase
{
    private string $root;
    private string $mediaRoot;
    private MediaUploader $uploader;

    protected function setUp(): void
    {
        $this->root      = sys_get_temp_dir() . '/cuniform_uploader_' . uniqid();
        $this->mediaRoot = $this->root . '/content/media';
        mkdir($this->root . '/uploads', 0o755, true);
        mkdir($this->mediaRoot, 0o755, true);

        $this->uploader = new MediaUploader($this->mediaRoot);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testAValidJpegUploadIsStoredUnderYearMonthWithARandomFilename(): void
    {
        $tmp = $this->makeUpload('holiday photo.jpg', MediaFormat::Jpeg, 50, 40);

        $outcome = $this->uploader->upload(new MediaUploadRequest($tmp, 'holiday photo.jpg', 'image/jpeg'));

        self::assertSame(MediaUploadStatus::Uploaded, $outcome->status);
        self::assertNotNull($outcome->file);
        self::assertSame(50, $outcome->file->width);
        self::assertSame(40, $outcome->file->height);
        self::assertMatchesRegularExpression(
            '#^\d{4}/\d{2}/[0-9a-f]{32}\.jpg$#',
            $outcome->file->relativePath,
            'stored under year/month with a random, non-guessable filename — never the original name'
        );
        self::assertStringNotContainsString('holiday', $outcome->file->relativePath);
        self::assertFileExists($this->mediaRoot . '/' . $outcome->file->relativePath);
        self::assertSame('/media/' . $outcome->file->relativePath, $outcome->file->url);
    }

    public function testTwoUploadsOfTheSameOriginalFilenameGetDifferentStoredNames(): void
    {
        $first  = $this->uploader->upload(new MediaUploadRequest($this->makeUpload('a.png', MediaFormat::Png, 5, 5), 'a.png', 'image/png'));
        $second = $this->uploader->upload(new MediaUploadRequest($this->makeUpload('a.png', MediaFormat::Png, 5, 5), 'a.png', 'image/png'));

        self::assertNotNull($first->file);
        self::assertNotNull($second->file);
        self::assertNotSame($first->file->relativePath, $second->file->relativePath);
    }

    public function testAWebpUploadRoundTrips(): void
    {
        $tmp = $this->makeUpload('icon.webp', MediaFormat::Webp, 8, 8);

        $outcome = $this->uploader->upload(new MediaUploadRequest($tmp, 'icon.webp', 'image/webp'));

        self::assertSame(MediaUploadStatus::Uploaded, $outcome->status);
        self::assertNotNull($outcome->file);
        self::assertStringEndsWith('.webp', $outcome->file->relativePath);
    }

    public function testRejectsADisallowedExtension(): void
    {
        $tmp = $this->root . '/uploads/x.gif';
        file_put_contents($tmp, "GIF89a\x01\x00\x01\x00");

        $outcome = $this->uploader->upload(new MediaUploadRequest($tmp, 'x.gif', 'image/gif'));

        self::assertSame(MediaUploadStatus::Invalid, $outcome->status);
        self::assertNotEmpty($outcome->errors);
        self::assertStringContainsString('extension', $outcome->errors[0]);
    }

    public function testRejectsADisallowedContentType(): void
    {
        $tmp = $this->makeUpload('x.jpg', MediaFormat::Jpeg, 5, 5);

        $outcome = $this->uploader->upload(new MediaUploadRequest($tmp, 'x.jpg', 'application/octet-stream'));

        self::assertSame(MediaUploadStatus::Invalid, $outcome->status);
        self::assertStringContainsString('content type', $outcome->errors[0]);
    }

    public function testRejectsBytesThatAreNotAnImageAtAllDespiteAPlausibleNameAndContentType(): void
    {
        $tmp = $this->root . '/uploads/fake.jpg';
        file_put_contents($tmp, '<?php echo "this is not an image"; ?>');

        $outcome = $this->uploader->upload(new MediaUploadRequest($tmp, 'fake.jpg', 'image/jpeg'));

        self::assertSame(MediaUploadStatus::Invalid, $outcome->status);
        self::assertStringContainsString('not a recognized image format', $outcome->errors[0]);
    }

    public function testRejectsAFileWhoseExtensionDisagreesWithItsActualContent(): void
    {
        // A real PNG renamed to claim a .jpg extension — extension and
        // magic bytes disagree, so this is refused rather than trusted on
        // either signal alone.
        $tmp = $this->makeUpload('renamed.png', MediaFormat::Png, 5, 5);
        $mismatchedPath = $this->root . '/uploads/renamed-as.jpg';
        rename($tmp, $mismatchedPath);

        $outcome = $this->uploader->upload(new MediaUploadRequest($mismatchedPath, 'renamed-as.jpg', 'image/jpeg'));

        self::assertSame(MediaUploadStatus::Invalid, $outcome->status);
        self::assertStringContainsString("don't all agree", $outcome->errors[0]);
    }

    public function testRejectsWhenNoFileWasActuallyReceived(): void
    {
        $outcome = $this->uploader->upload(new MediaUploadRequest($this->root . '/uploads/does-not-exist.jpg', 'does-not-exist.jpg', 'image/jpeg'));

        self::assertSame(MediaUploadStatus::Invalid, $outcome->status);
        self::assertNotEmpty($outcome->errors);
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    private function makeUpload(string $originalName, MediaFormat $format, int $width, int $height): string
    {
        $path  = $this->root . '/uploads/' . $originalName;
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 10, 150, 220));

        match ($format) {
            MediaFormat::Jpeg => imagejpeg($image, $path, 90),
            MediaFormat::Png => imagepng($image, $path, 6),
            MediaFormat::Webp => imagewebp($image, $path, 90),
        };
        imagedestroy($image);

        return $path;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            is_link($full) || !is_dir($full) ? unlink($full) : $this->removeDirectory($full);
        }

        rmdir($path);
    }
}
