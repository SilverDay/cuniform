<?php

declare(strict_types=1);

namespace Cuniform\Admin;

use Cuniform\Admin\Audit\AuditLogReader;
use Cuniform\Admin\Audit\AuditLogWriter;
use Cuniform\Admin\Auth\AdminAccountStore;
use Cuniform\Admin\Auth\AdminCookie;
use Cuniform\Admin\Auth\CsrfToken;
use Cuniform\Admin\Auth\LoginService;
use Cuniform\Admin\Auth\PendingLoginStore;
use Cuniform\Admin\Auth\RateLimiter;
use Cuniform\Admin\Auth\SessionStore;
use Cuniform\Admin\Build\BuildRequestQueue;
use Cuniform\Admin\Editor\EditorDocumentStore;
use Cuniform\Admin\Editor\GitRepository;
use Cuniform\Admin\Media\MediaLibrary;
use Cuniform\Admin\Media\MediaUploader;
use Cuniform\Admin\Preview\PreviewRenderer;
use Cuniform\Admin\Reporting\AdminSiteReport;
use Cuniform\Build\BuildLogReader;
use Cuniform\Config\ConfigLoader;

/**
 * Wires the Auth services (T28) for admin/*.php — one call, returning an
 * AdminContext, rather than admin/bootstrap.php assigning local variables
 * for a plain `require` to inject into the caller's scope. Cross-file
 * variable injection via `require` is opaque to PHPStan (each file is
 * analysed independently, so `$loginService` etc. would read as
 * "possibly undefined" in every page) — a real static-analysis
 * limitation, not something this project's "no @var overrides to silence
 * a real error" rule is meant to work around. A typed factory call sidesteps
 * the limitation entirely instead of suppressing it.
 */
final class AdminBootstrap
{
    public static function create(string $projectRoot): AdminContext
    {
        $config      = (new ConfigLoader())->load($projectRoot . '/config/site.php');
        $adminVarDir = rtrim($config->paths->var, '/') . '/admin';
        $logDir      = rtrim($config->paths->var, '/') . '/log';

        $loginService = new LoginService(
            new AdminAccountStore($adminVarDir . '/accounts.json'),
            new PendingLoginStore($adminVarDir . '/pending-logins'),
            new SessionStore($adminVarDir . '/sessions'),
            new RateLimiter($adminVarDir . '/rate-limits'),
        );

        // Only path this process ever touches to ask for a build (SPEC
        // §10.5) — deploy/systemd/cuniform-build.path (T27) watches this
        // exact file with PathExists. Never releases/ or public/: this
        // class holds no path to either (T32 acceptance — see its own
        // docblock).
        $buildQueue = new BuildRequestQueue(rtrim($config->paths->var, '/') . '/build-requested');

        // A rollback re-points the public symlink — exactly the same
        // releases/public write this process must never make directly
        // (SPEC §10.5/§15.1), so it gets the identical enqueue-and-let-a-
        // privileged-unit-consume treatment as a build, via a second
        // deploy/systemd/cuniform-rollback.path + .service pair (T33) that
        // mirrors cuniform-build.path/.service exactly except for what its
        // .service runs (`bin/cuniform build --rollback`).
        $rollbackQueue = new BuildRequestQueue(rtrim($config->paths->var, '/') . '/rollback-requested');

        $auditLog = new AuditLogWriter($logDir . '/audit.jsonl');

        // GitRepository runs with content/ itself as its cwd, not the
        // project root — EditorDocumentStore's own relative paths
        // ("posts/en/...") are relative to content/, and git resolves a
        // pathspec relative to wherever it's invoked from, walking upward
        // on its own to find the repository root (SPEC §3: content/ is a
        // subdirectory of the repository, not a repository of its own).
        $editorDocumentStore = new EditorDocumentStore(
            $config->paths->content,
            $config->languages,
            $config->timezone,
            new GitRepository($config->paths->content),
            $buildQueue,
            $auditLog,
        );

        // Shares $config/$editorDocumentStore with the editor rather than
        // building its own — previewPath()/render() are the exact same
        // path-derivation and front-matter-emission logic a save would use
        // (see PreviewRenderer's own docblock).
        $previewRenderer = new PreviewRenderer($config, $projectRoot . '/config/lang', $editorDocumentStore);

        $mediaRoot     = rtrim($config->paths->content, '/') . '/media';
        $mediaUploader = new MediaUploader($mediaRoot);
        $mediaLibrary  = new MediaLibrary($config->paths->content);

        $auditLogReader = new AuditLogReader($logDir . '/audit.jsonl');
        $buildLogReader = new BuildLogReader($logDir . '/build.jsonl');
        $siteReport     = new AdminSiteReport($config, $projectRoot . '/config/lang');

        return new AdminContext(
            $config,
            $loginService,
            new AdminCookie(),
            new CsrfToken(),
            $editorDocumentStore,
            $previewRenderer,
            $mediaUploader,
            $mediaLibrary,
            $buildQueue,
            $rollbackQueue,
            $auditLogReader,
            $buildLogReader,
            $siteReport,
        );
    }
}
