<?php

declare(strict_types=1);

namespace Cuniform\Admin;

use Cuniform\Admin\Auth\AdminAccountStore;
use Cuniform\Admin\Auth\AdminCookie;
use Cuniform\Admin\Auth\CsrfToken;
use Cuniform\Admin\Auth\LoginService;
use Cuniform\Admin\Auth\PendingLoginStore;
use Cuniform\Admin\Auth\RateLimiter;
use Cuniform\Admin\Auth\SessionStore;
use Cuniform\Admin\Editor\EditorDocumentStore;
use Cuniform\Admin\Editor\GitRepository;
use Cuniform\Admin\Media\MediaLibrary;
use Cuniform\Admin\Media\MediaUploader;
use Cuniform\Admin\Preview\PreviewRenderer;
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

        $loginService = new LoginService(
            new AdminAccountStore($adminVarDir . '/accounts.json'),
            new PendingLoginStore($adminVarDir . '/pending-logins'),
            new SessionStore($adminVarDir . '/sessions'),
            new RateLimiter($adminVarDir . '/rate-limits'),
        );

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
        );

        // Shares $config/$editorDocumentStore with the editor rather than
        // building its own — previewPath()/render() are the exact same
        // path-derivation and front-matter-emission logic a save would use
        // (see PreviewRenderer's own docblock).
        $previewRenderer = new PreviewRenderer($config, $projectRoot . '/config/lang', $editorDocumentStore);

        $mediaRoot     = rtrim($config->paths->content, '/') . '/media';
        $mediaUploader = new MediaUploader($mediaRoot);
        $mediaLibrary  = new MediaLibrary($config->paths->content);

        return new AdminContext(
            $config,
            $loginService,
            new AdminCookie(),
            new CsrfToken(),
            $editorDocumentStore,
            $previewRenderer,
            $mediaUploader,
            $mediaLibrary,
        );
    }
}
