<?php

namespace App\Enums;

enum TransactionImportBatchStatus: string
{
    case Uploaded = 'uploaded';
    case Mapping = 'mapping';
    case ReadyForReview = 'ready_for_review';
    case Approved = 'approved';
    case Processing = 'processing';
    case Completed = 'completed';
    case PartiallyCompleted = 'partially_completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
