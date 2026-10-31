import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export function FinancialChoice({
    id,
    label,
    value,
    options,
    onChange,
    error,
    emptyLabel,
    disabled,
}: {
    id: string;
    label: string;
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
    error?: string;
    emptyLabel?: string;
    disabled?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select
                disabled={disabled}
                value={value || 'none'}
                onValueChange={(value) =>
                    onChange(value === 'none' ? '' : value)
                }
            >
                <SelectTrigger
                    id={id}
                    className="w-full"
                    aria-invalid={!!error}
                    aria-describedby={error ? id + '-error' : undefined}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {emptyLabel && (
                        <SelectItem value="none">{emptyLabel}</SelectItem>
                    )}
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError id={id + '-error'} message={error} />
        </div>
    );
}
