<?php

declare(strict_types=1);

namespace Cuniform\Config;

final class Config
{
    /**
     * @param list<string> $languages BCP-47 primary subtags (SPEC §7.1).
     */
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $title,
        public readonly string $timezone,
        public readonly array $languages,
        public readonly string $defaultLanguage,
        public readonly UrlPrefix $urlPrefix,
        public readonly string $permalink,
        public readonly int $postsPerPage,
        public readonly int $feedItems,
        public readonly ConfigPaths $paths,
        public readonly BuildSettings $build,
        public readonly MailSettings $mail,
    ) {
    }
}
