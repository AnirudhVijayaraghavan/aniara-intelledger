<?php

namespace App\Enums;

enum TransactionRevisionOperation: string
{
    case Created = 'created';
    case Updated = 'updated';
}
