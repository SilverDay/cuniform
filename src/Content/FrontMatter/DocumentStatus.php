<?php

declare(strict_types=1);

namespace Cuniform\Content\FrontMatter;

/**
 * SPEC §5.2 `status` and §5.6 status semantics.
 */
enum DocumentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';
}
