import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

export default function AppearanceToggleTab({
    className = '',
    compact = false,
    ...props
}: HTMLAttributes<HTMLDivElement> & { compact?: boolean }) {
    const { appearance, resolvedAppearance, updateAppearance } =
        useAppearance();
    const isDark = resolvedAppearance === 'dark';
    const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';

    if (!compact) {
        return (
            <div
                role="group"
                aria-label="Color appearance"
                className={cn(
                    'inline-flex flex-wrap gap-1 rounded-md bg-muted p-1',
                    className,
                )}
                {...props}
            >
                {(
                    [
                        { value: 'light', icon: Sun, label: 'Light' },
                        { value: 'dark', icon: Moon, label: 'Dark' },
                        { value: 'system', icon: Monitor, label: 'System' },
                    ] as const
                ).map(({ value, icon: Icon, label: optionLabel }) => (
                    <Button
                        key={value}
                        type="button"
                        variant="ghost"
                        aria-pressed={appearance === value}
                        onClick={() => updateAppearance(value)}
                        className={cn(
                            'rounded-sm',
                            appearance === value
                                ? 'bg-card text-foreground shadow-xs'
                                : 'text-muted-foreground',
                        )}
                    >
                        <Icon aria-hidden="true" />
                        {optionLabel}
                    </Button>
                ))}
            </div>
        );
    }

    return (
        <div className={cn('inline-flex', className)} {...props}>
            <Button
                type="button"
                variant="outline"
                size="icon"
                className="rounded-full"
                aria-label={label}
                title={label}
                onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
            >
                {isDark ? (
                    <Sun aria-hidden="true" />
                ) : (
                    <Moon aria-hidden="true" />
                )}
            </Button>
        </div>
    );
}
