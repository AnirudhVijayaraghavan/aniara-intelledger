import type { FinancialTeam } from '@/types/finance';

export type TransactionKind =
    | 'income'
    | 'expense'
    | 'transfer'
    | 'adjustment'
    | 'unclassified';
export type TransactionStatus = 'pending' | 'posted' | 'cancelled';
export type TransactionAccount = {
    id: number;
    name: string;
    currency_code: string;
    typeLabel: string;
    isLiability: boolean;
    archived_at: string | null;
};
export type TransactionCategory = {
    id: number;
    name: string;
    kind: 'income' | 'expense';
    parent_id: number | null;
    parent?: { id: number; name: string } | null;
    archived_at?: string | null;
};
export type Payee = { id: number; name: string; archived_at?: string | null };
export type Transaction = {
    id: number;
    financial_account_id: number;
    amount: string;
    currency_code: string;
    kind: TransactionKind;
    status: TransactionStatus;
    description: string | null;
    transaction_date: string;
    posted_date: string | null;
    recorded_at: string;
    category_id: number | null;
    payee_id: number | null;
    reverses_transaction_id: number | null;
    replaces_transaction_id: number | null;
    excluded_from_analytics: boolean;
    expected_revision: number;
    account: TransactionAccount;
    category: TransactionCategory | null;
    payee: Payee | null;
};
export type Revision = {
    id: number;
    revision_number: number;
    operation: 'created' | 'updated';
    snapshot: Record<string, string | number | boolean | null>;
    actor: { id: number; name: string } | null;
    reason: string | null;
    recorded_at: string;
};
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};
export type ClassificationInput = {
    kind: string;
    category_id: string;
    payee_id: string;
    description: string;
    excluded_from_analytics: boolean;
    reason: string;
};
export type FinancialInput = {
    amount: string;
    transaction_date: string;
    posted_date: string;
};
export type TransactionOptions = {
    team: FinancialTeam;
    categories: TransactionCategory[];
    payees: Payee[];
    today: string;
};
export const manualKinds = ['income', 'expense', 'adjustment', 'unclassified'];
export const kindLabel = (kind: string) =>
    kind.charAt(0).toUpperCase() + kind.slice(1);
