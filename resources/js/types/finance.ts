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

export type AccountNetWorthContribution = {
    accountId: number;
    accountName: string;
    type: string;
    typeLabel: string;
    isLiability: boolean;
    currencyCode: string;
    archivedAt: string | null;
    institutionId: number | null;
    institutionName: string | null;
    balanceObservationId: number | null;
    balanceAsOfDate: string | null;
    balanceRecordedAt: string | null;
    balanceAmount: string | null;
    netWorthContribution: string | null;
    missingData: boolean;
};

export type CurrencyNetWorth = {
    currencyCode: string;
    totalAssets: string;
    totalLiabilities: string;
    netWorth: string;
    assetsByAccountType: Record<string, string>;
    liabilitiesByAccountType: Record<string, string>;
    accounts: AccountNetWorthContribution[];
    missingAccountIds: number[];
    isComplete: boolean;
};

export type NetWorthOverview = {
    teamId: number;
    asOfDate: string;
    currencies: Record<string, CurrencyNetWorth>;
    isComplete: boolean;
};

export type FxQuote = {
    fromCurrencyCode: string;
    toCurrencyCode: string;
    valuationAt: string;
    rateId: number | null;
    baseCurrencyCode: string;
    quoteCurrencyCode: string;
    rate: string;
    source: string;
    effectiveAt: string | null;
    recordedAt: string | null;
    isInverse: boolean;
};

export type ConsolidatedNetWorthOverview = {
    original: NetWorthOverview;
    reportingCurrencyCode: string;
    valuationAt: string;
    totalAssets: string | null;
    totalLiabilities: string | null;
    netWorth: string | null;
    fxQuotes: Record<string, FxQuote>;
    missingRateCurrencies: string[];
    isComplete: boolean;
};

export type ExchangeRateObservation = {
    id: number;
    baseCurrencyCode: string;
    quoteCurrencyCode: string;
    rate: string;
    effectiveAt: string;
    recordedAt: string;
    source: string;
};
