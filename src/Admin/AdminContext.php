<?php

declare(strict_types=1);

namespace Cuniform\Admin;

use Cuniform\Admin\Audit\AuditLogReader;
use Cuniform\Admin\Auth\AdminCookie;
use Cuniform\Admin\Auth\CsrfToken;
use Cuniform\Admin\Auth\LoginService;
use Cuniform\Admin\Build\BuildRequestQueue;
use Cuniform\Admin\Editor\EditorDocumentStore;
use Cuniform\Admin\Media\MediaLibrary;
use Cuniform\Admin\Media\MediaUploader;
use Cuniform\Admin\Preview\PreviewRenderer;
use Cuniform\Admin\Reporting\AdminSiteReport;
use Cuniform\Build\BuildLogReader;
use Cuniform\Config\Config;

/**
 * The services every admin/*.php entry point needs (SPEC §13), wired by
 * AdminBootstrap::create(). A plain readonly value object rather than
 * `require`-injected local variables — see AdminBootstrap's own docblock
 * for why.
 */
final class AdminContext
{
    public function __construct(
        public readonly Config $config,
        public readonly LoginService $loginService,
        public readonly AdminCookie $adminCookie,
        public readonly CsrfToken $csrfToken,
        public readonly EditorDocumentStore $editorDocumentStore,
        public readonly PreviewRenderer $previewRenderer,
        public readonly MediaUploader $mediaUploader,
        public readonly MediaLibrary $mediaLibrary,
        public readonly BuildRequestQueue $buildRequestQueue,
        public readonly BuildRequestQueue $rollbackRequestQueue,
        public readonly AuditLogReader $auditLogReader,
        public readonly BuildLogReader $buildLogReader,
        public readonly AdminSiteReport $siteReport,
    ) {
    }
}
