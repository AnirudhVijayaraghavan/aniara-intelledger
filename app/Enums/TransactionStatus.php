<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Posted = 'posted';
    case Cancelled = 'cancelled';
}
