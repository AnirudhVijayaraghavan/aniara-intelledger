export type FinancialTeam = { id: number; name: string; slug: string };

export type AccountType = {
    value: string;
    label: string;
    isLiability: boolean;
};

export type FinancialAccount = {
    id: number;
    name: string;
    type: string;
    typeLabel: string;
    isLiability: boolean;
    currency_code: string;
    financial_institution_id: number | null;
    institution: { id: number; name: string } | null;
    archived_at: string | null;
    owners: { id: number; owner_name: string; user_id: number | null }[];
};

export type BalanceObservation = {
    id: number;
    amount: string;
    as_of_date: string;
    recorded_at: string;
    source: 'manual' | 'imported';
    created_by: number | null;
    voided_at: string | null;
    voided_by: number | null;
    voided_by_name: string | null;
};

export type BalanceHistory = {
    data: BalanceObservation[];
    current_page: number;
    last_page: number;
    total: number;
};
