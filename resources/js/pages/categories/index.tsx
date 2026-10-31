import FinancialReferenceManager from '@/components/financial-reference-manager';
import type { ReferenceManagerProps } from '@/components/financial-reference-manager';

export default function Categories(props: ReferenceManagerProps) {
    return <FinancialReferenceManager {...props} entity="categories" />;
}
