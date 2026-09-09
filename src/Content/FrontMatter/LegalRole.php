<?php

declare(strict_types=1);

namespace Cuniform\Content\FrontMatter;

/**
 * SPEC §6.2/§6.4 `legal`. Marks a page's legal role rather than a fixed slug —
 * reserved-slug enforcement and the never-noindex/footer/pagination protections
 * key off this value, discovered per document rather than hardcoded.
 */
enum LegalRole: string
{
    case Impressum = 'impressum';
    case Privacy = 'privacy';
}
