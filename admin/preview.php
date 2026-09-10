<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/editor_form.php';

use Cuniform\Admin\AdminBootstrap;

/**
 * Preview (SPEC §12 Path C, T30): renders the editor form's current,
 * possibly-unsaved buffer through PreviewRenderer and returns the page
 * byte-for-byte as a real build would produce it, plus one banner. Never
 * writes to content/, releases/, or public/ — PreviewRenderer only reads.
 *
 * A GET is refused rather than silently rendering nothing: this page only
 * has content to show when the editor form actually posts one, and SPEC
 * §13.2 requires a CSRF token on every state-changing-shaped request —
 * preview writes nothing, but it is still POST-only editor input, so it
 * gets the same check as save/move rather than a carved-out exception.
 */
admin_security_headers();

$context = AdminBootstrap::create(dirname(__DIR__));

admin_require_session($context->loginService);

// SPEC §12 Path C: "Served X-Robots-Tag: noindex, nofollow and
// Cache-Control: no-store" — set on every response from this endpoint,
// success or failure.
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_csrf_valid($context->csrfToken)) {
    header('Content-Type: text/plain; charset=UTF-8', true, 400);
    echo "Your session expired — go back to the editor and try again.\n";
    exit;
}

$request = editor_request_from_post();
$result  = $context->previewRenderer->render($request);

if (!$result->ok) {
    header('Content-Type: text/plain; charset=UTF-8', true, 422);
    echo "This could not be previewed:\n";
    foreach ($result->errors as $error) {
        echo "- {$error}\n";
    }

    exit;
}

header('Content-Type: text/html; charset=UTF-8');
echo $result->html;
