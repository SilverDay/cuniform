<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Config\Config;
use Cuniform\Render\Md2Html;
use Cuniform\Template\NavItem;

/**
 * SPEC §10.2's cache key: `SHA-256(file) + SHA-256(templates) +
 * SHA-256(UI strings) + engine version + SHA-256(src/Render/Md2Html.php)`.
 * The last four are the same for every document in a given build — this
 * class computes them once as `$globalSuffix` and folds it into each
 * document's own file hash via forDocument(), rather than recomputing
 * per document. Combining them this way is also what makes "a changed
 * template, UI string file, or renderer file invalidates everything"
 * (SPEC §10.2) fall out for free: if any of those change, `$globalSuffix`
 * changes, so *every* document's key changes, with no separate rule
 * needed (BuildCache still short-circuits this case explicitly, without
 * comparing keys one by one — see its own docblock — but the key
 * design is what makes that short-circuit safe to rely on).
 *
 * Also folds in the parts of Config that change what a cached page's own
 * bytes would need to say (`base_url`, `title`, language set, permalink
 * pattern, ...) — not part of SPEC's literal formula, but every one of
 * them is embedded directly in every page's `<head>` or route, so
 * treating them as outside the cache key would let a config edit produce
 * stale cached output. `paths.*` (filesystem locations) and `build.*`/
 * `mail.*` (deploy/notification behaviour, never rendered into a page)
 * are deliberately excluded.
 *
 * The nav tree (SPEC §10.2's "a nav-affecting page invalidates
 * everything") is NOT part of `$globalSuffix` — it isn't a static file
 * or a config value, it's derived from every page's own front matter
 * collectively. navHash() computes it separately so BuildCache can
 * compare it build-to-build on its own.
 */
final class BuildCacheKey
{
    private readonly string $globalSuffix;

    public function __construct(Config $config, string $templatesDir, string $langDir)
    {
        $rendererPath = (new \ReflectionClass(Md2Html::class))->getFileName();
        \assert($rendererPath !== false);

        $this->globalSuffix = hash('sha256', implode('|', [
            $this->hashDirectory($templatesDir),
            $this->hashDirectory($langDir),
            EngineVersion::VERSION,
            $this->hashFile($rendererPath),
            $this->hashConfig($config),
        ]));
    }

    public function forDocument(string $fileSha256): string
    {
        return hash('sha256', $fileSha256 . '|' . $this->globalSuffix);
    }

    /**
     * @param array<string, array{primary: list<NavItem>, footer: list<NavItem>}> $navByLanguage
     */
    public function navHash(array $navByLanguage): string
    {
        $languages = array_keys($navByLanguage);
        sort($languages);

        $serialized = [];
        foreach ($languages as $language) {
            $serialized[$language] = [
                'primary' => array_map($this->serializeNavItem(...), $navByLanguage[$language]['primary']),
                'footer'  => array_map($this->serializeNavItem(...), $navByLanguage[$language]['footer']),
            ];
        }

        return hash('sha256', (string) json_encode($serialized, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{label: string, url: string, children: list<mixed>}
     */
    private function serializeNavItem(NavItem $item): array
    {
        return [
            'label'    => $item->label,
            'url'      => $item->url,
            'children' => array_map($this->serializeNavItem(...), $item->children),
        ];
    }

    private function hashConfig(Config $config): string
    {
        $languages = $config->languages;
        sort($languages);

        return hash('sha256', (string) json_encode([
            $config->baseUrl,
            $config->title,
            $config->timezone,
            $languages,
            $config->defaultLanguage,
            $config->urlPrefix->value,
            $config->permalink,
            $config->postsPerPage,
            $config->feedItems,
        ], \JSON_THROW_ON_ERROR));
    }

    private function hashDirectory(string $dir): string
    {
        if (!is_dir($dir)) {
            return '';
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            \assert($fileInfo instanceof \SplFileInfo);
            if ($fileInfo->isFile()) {
                $files[] = $fileInfo->getPathname();
            }
        }

        sort($files);

        $parts = [];
        foreach ($files as $file) {
            $parts[] = substr($file, strlen($dir)) . ':' . $this->hashFile($file);
        }

        return hash('sha256', implode('|', $parts));
    }

    private function hashFile(string $path): string
    {
        $hash = hash_file('sha256', $path);

        return $hash === false ? '' : $hash;
    }
}
