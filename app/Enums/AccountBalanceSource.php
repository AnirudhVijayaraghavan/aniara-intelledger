<?php

namespace App\Enums;

enum AccountBalanceSource: string
{
    case Manual = 'manual';
    case Imported = 'imported';
}
