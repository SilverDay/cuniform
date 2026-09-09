<?php

declare(strict_types=1);

namespace Cuniform;

/**
 * Base for every Cuniform exception. Never thrown or caught directly —
 * catch a subclass (ConfigException, ContentException, RenderException,
 * BuildException) so callers can tell failure categories apart.
 */
abstract class CuniformException extends \RuntimeException
{
}
