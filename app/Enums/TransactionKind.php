<?php

namespace App\Enums;

enum TransactionKind: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';
    case Unclassified = 'unclassified';
}
