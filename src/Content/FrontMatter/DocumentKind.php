<?php

declare(strict_types=1);

namespace Cuniform\Content\FrontMatter;

/**
 * Which schema a document's front matter is validated against (SPEC §5.2 shared
 * keys plus §5.3 post-only or §6.2 page-only keys). Derived from the document's
 * location under content/posts/ or content/pages/ (§5.1) — never front matter itself.
 */
enum DocumentKind
{
    case Post;
    case Page;
}
