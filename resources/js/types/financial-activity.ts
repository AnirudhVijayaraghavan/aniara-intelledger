export type ActivityTotals = {
    recordedIncome: string;
    recordedExpenses: string;
    netRecordedIncome: string;
    transactionCount: number;
};
export type CategoryActivity = {
    categoryId: number | null;
    categoryName: string;
    kind: 'income' | 'expense' | null;
    parentCategoryId: number | null;
    parentCategoryName: string | null;
    archivedAt: string | null;
    totals: ActivityTotals;
};
export type AccountActivity = {
    accountId: number;
    accountName: string;
    accountType: string;
    archivedAt: string | null;
    totals: ActivityTotals;
};
export type MonthlyActivity = { month: string; totals: ActivityTotals };
export type CurrencyActivity = {
    currencyCode: string;
    totals: ActivityTotals;
    byCategory: CategoryActivity[];
    byAccount: AccountActivity[];
    monthly: MonthlyActivity[];
};
export type FinancialActivity = {
    teamId: number;
    startDate: string;
    endDate: string;
    label: string;
    dateBasis: 'posted_date';
    coverageNote: string;
    currencies: Record<string, CurrencyActivity>;
};
export type ActivityFilters = {
    start_date: string;
    end_date: string;
    currency: 'USD' | 'INR';
};
export type ActivityPreset = {
    months: number;
    startDate: string;
    endDate: string;
};
