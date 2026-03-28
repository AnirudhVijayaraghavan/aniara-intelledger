import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

export default function AppearanceToggleTab({
    compact = false,
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement> & { compact?: boolean }) {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: 'Light' },
        { value: 'dark', icon: Moon, label: 'Dark' },
        { value: 'system', icon: Monitor, label: 'System' },
    ];

    return (
        <div
            className={cn(
                'inline-flex gap-1 rounded-xl border border-intel-line bg-intel-surface-muted p-1',
                className,
            )}
            {...props}
        >
            {tabs.map(({ value, icon: Icon, label }) => (
                <button
                    key={value}
                    onClick={() => updateAppearance(value)}
                    className={cn(
                        compact
                            ? 'flex items-center rounded-lg px-2.5 py-2 transition-colors'
                            : 'flex items-center rounded-lg px-3.5 py-1.5 transition-colors',
                        appearance === value
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-intel-text-muted hover:bg-intel-mint-soft hover:text-foreground',
                    )}
                    aria-label={label}
                    title={label}
                >
                    <Icon className={cn('h-4 w-4', !compact && '-ml-1')} />
                    {!compact && (
                        <span className="ml-1.5 text-sm">{label}</span>
                    )}
                </button>
            ))}
        </div>
    );
}
