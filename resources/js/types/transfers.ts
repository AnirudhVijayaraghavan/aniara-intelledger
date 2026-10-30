import type {
    TransactionAccount,
    TransactionStatus,
} from '@/types/transactions';

export type TransferAccount = TransactionAccount & {
    institution: { id: number; name: string } | null;
};
export type TransferLeg = {
    id: number;
    amount: string;
    currency_code: string;
    transaction_date: string;
    posted_date: string | null;
    status: TransactionStatus;
    description: string | null;
    excluded_from_analytics: boolean;
    account: TransferAccount;
    expected_revision: number;
};
export type ExecutionFx = {
    base_currency_code: string;
    quote_currency_code: string;
    rate: string;
    source: string;
    effective_at: string | null;
};
export type Transfer = {
    id: number;
    source: TransferLeg;
    destination: TransferLeg;
    recorded_at: string;
    creator: { id: number; name: string } | null;
    execution_fx: ExecutionFx | null;
    original_execution_fx: ExecutionFx | null;
    reverses_transfer_id: number | null;
    reversal: { id: number; recorded_at: string } | null;
};

export type TransferReversalPreview = {
    source_amount: string;
    destination_amount: string;
    minimum_posted_date: string;
};
