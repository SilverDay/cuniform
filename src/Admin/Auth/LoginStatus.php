<?php

declare(strict_types=1);

namespace Cuniform\Admin\Auth;

enum LoginStatus
{
    case PendingTotp;
    case Authenticated;
    case InvalidCredentials;
    case InvalidCode;
    case RateLimited;
    case Expired;
}
