<?php

namespace App\Enums;

enum TransactionCategoryKind: string
{
    case Income = 'income';
    case Expense = 'expense';
}
