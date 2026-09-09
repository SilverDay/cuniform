<?php

declare(strict_types=1);

namespace Cuniform\Config;

final class MailSettings
{
    public function __construct(
        public readonly bool $enabled,
        public readonly string $from,
        public readonly string $notify,
        public readonly string $envelopeSender,
    ) {
    }
}
