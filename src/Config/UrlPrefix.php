<?php

declare(strict_types=1);

namespace Cuniform\Config;

/**
 * Whether generated URLs carry a language segment (SPEC §7.1, §7.4).
 */
enum UrlPrefix: string
{
    case Always = 'always';
    case Auto = 'auto';
    case Never = 'never';
}
