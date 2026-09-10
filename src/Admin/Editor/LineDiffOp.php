<?php

declare(strict_types=1);

namespace Cuniform\Admin\Editor;

enum LineDiffOp
{
    case Same;
    case Added;
    case Removed;
}
