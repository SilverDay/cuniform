<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * WxrReader's output: the channel metadata plus every item the export
 * contains, in document order.
 */
final class WxrDocument
{
    /**
     * @param list<WxrItem> $items
     */
    public function __construct(
        public readonly WxrChannel $channel,
        public readonly array $items,
    ) {
    }
}
