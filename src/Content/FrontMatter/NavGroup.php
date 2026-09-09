<?php

declare(strict_types=1);

namespace Cuniform\Content\FrontMatter;

/**
 * SPEC §6.2 `nav_group`. Absence has no engine-invented default here — that's
 * for whichever task builds the nav tree (T18) to decide.
 */
enum NavGroup: string
{
    case Primary = 'primary';
    case Footer = 'footer';
    case None = 'none';
}
