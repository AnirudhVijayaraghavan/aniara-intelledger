<?php

namespace App\Enums;

enum TransactionImportDisposition: string
{
    case Import = 'import';
    case Link = 'link';
    case Skip = 'skip';
    case PostPending = 'post_pending';
}
