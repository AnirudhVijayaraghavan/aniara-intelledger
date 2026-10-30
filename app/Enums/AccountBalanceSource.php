<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountBalanceSource: string
{
    case Manual = 'manual';
    case Imported = 'imported';
}
