import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

type AppearanceToggleProps = {
    className?: string;
};

export function AppearanceToggle({
    className = '',
}: AppearanceToggleProps) {
    const { resolvedAppearance, updateAppearance } = useAppearance();

    const isDark = resolvedAppearance === 'dark';
    const Icon = isDark ? Sun : Moon;
    const nextAppearance = isDark ? 'light' : 'dark';
    const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={() => updateAppearance(nextAppearance)}
            aria-label={label}
            title={label}
            className={cn(
                'size-9 rounded-full border border-intel-line bg-[linear-gradient(135deg,var(--color-intel-surface),var(--color-intel-mint-soft))] text-foreground shadow-xs transition-colors hover:border-intel-mint/30 hover:bg-[linear-gradient(135deg,var(--color-intel-surface-muted),var(--color-intel-mint-soft))] hover:text-foreground dark:border-intel-line dark:bg-[linear-gradient(135deg,var(--color-intel-surface),rgba(75,216,204,0.12))] dark:hover:bg-[linear-gradient(135deg,var(--color-intel-surface-muted),rgba(75,216,204,0.16))]',
                className,
            )}
        >
            <Icon className="size-4" />
        </Button>
    );
}
