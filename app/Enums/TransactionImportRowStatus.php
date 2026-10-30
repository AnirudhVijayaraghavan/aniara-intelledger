<?php

namespace App\Enums;

enum TransactionImportRowStatus: string
{
    case Pending = 'pending';
    case Valid = 'valid';
    case Invalid = 'invalid';
    case NeedsReview = 'needs_review';
    case Approved = 'approved';
    case Imported = 'imported';
    case Linked = 'linked';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
