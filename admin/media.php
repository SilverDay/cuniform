<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Cuniform\Admin\AdminBootstrap;
use Cuniform\Admin\Media\MediaFormat;
use Cuniform\Admin\Media\MediaUploadRequest;
use Cuniform\Admin\Media\MediaUploadStatus;

admin_security_headers();

$context = AdminBootstrap::create(dirname(__DIR__));
$config  = $context->config;

admin_require_session($context->loginService);
$csrf = admin_csrf_value($context->csrfToken, $context->adminCookie);

$formErrors = [];
$uploadedPath = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid($context->csrfToken)) {
        $formErrors = ['Your session expired — please try again.'];
    } else {
        $file = $_FILES['file'] ?? null;

        if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['name'], $file['type'])) {
            $formErrors = ['No file was uploaded.'];
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $formErrors = ['Upload failed (error code ' . (string) $file['error'] . ').'];
        } elseif (!is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            // is_uploaded_file() confirms this file actually arrived via a
            // real HTTP upload for *this* request — the real-I/O check that
            // belongs at this boundary, not inside the tested MediaUploader
            // (same "thin, untested" split as admin_current_session() and
            // StreamHttpFetcher, T25).
            $formErrors = ['No file was uploaded.'];
        } else {
            $request = new MediaUploadRequest(
                tmpPath: $file['tmp_name'],
                originalFilename: is_string($file['name']) ? $file['name'] : '',
                reportedMimeType: is_string($file['type']) ? $file['type'] : '',
            );

            $outcome = $context->mediaUploader->upload($request);

            if ($outcome->status === MediaUploadStatus::Uploaded && $outcome->file !== null) {
                header('Location: /admin/media.php?uploaded=' . urlencode($outcome->file->relativePath));
                exit;
            }

            $formErrors = $outcome->errors;
        }
    }
} else {
    $uploaded = $_GET['uploaded'] ?? null;
    $uploadedPath = is_string($uploaded) && $uploaded !== '' ? $uploaded : null;
}

$library = $context->mediaLibrary->list();

$uploadedEntry = null;
foreach ($library as $entry) {
    if ($entry->relativePath === $uploadedPath) {
        $uploadedEntry = $entry;

        break;
    }
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cuniform admin — media</title>
<link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<main class="admin-page">
<h1><?= e($config->title) ?> — media</h1>
<p><a href="/admin/index.php">&larr; Dashboard</a></p>

<?php if ($uploadedPath !== null): ?>
<p class="admin-notice">
Uploaded: <code><?= e('/media/' . $uploadedPath) ?></code>
<?php if ($uploadedEntry !== null): ?>
(<?= e((string) $uploadedEntry->width) ?>&times;<?= e((string) $uploadedEntry->height) ?>,
<?= e(number_format($uploadedEntry->bytes / 1024, 1)) ?> KB)
<?php endif; ?>
— copy this path into a document's <code>image</code> field or a <code>[figure src="..."]</code> shortcode.
</p>
<?php endif; ?>

<?php if ($formErrors !== []): ?>
<p class="admin-error" role="alert">
This could not be uploaded:
<ul>
<?php foreach ($formErrors as $formError): ?>
<li><?= e($formError) ?></li>
<?php endforeach; ?>
</ul>
</p>
<?php endif; ?>

<form method="post" action="/admin/media.php" enctype="multipart/form-data" class="admin-upload-form">
<input type="hidden" name="csrf_token" value="<?= eAttr($csrf) ?>">
<label for="file">Image (JPEG, PNG, or WebP)</label>
<input type="file" id="file" name="file" accept="<?= eAttr(implode(',', array_map(static fn (MediaFormat $f): string => $f->mimeType(), MediaFormat::cases()))) ?>" required>
<p class="admin-hint">Uploaded images are re-encoded and stripped of EXIF/embedded metadata, and
stored under a randomized filename (SPEC §13.2) — the original filename is not kept.</p>
<button type="submit">Upload</button>
</form>

<h2>Existing media</h2>
<?php if ($library === []): ?>
<p>No media uploaded yet.</p>
<?php else: ?>
<table class="admin-table">
<thead>
<tr><th>Path</th><th>Dimensions</th><th>Size</th><th>Modified</th></tr>
</thead>
<tbody>
<?php foreach ($library as $entry): ?>
<tr>
<td><code><?= e($entry->url) ?></code></td>
<td><?= e((string) $entry->width) ?>&times;<?= e((string) $entry->height) ?></td>
<td><?= e(number_format($entry->bytes / 1024, 1)) ?> KB</td>
<td><?= e($entry->modifiedAt->format('Y-m-d H:i')) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</main>
</body>
</html>
