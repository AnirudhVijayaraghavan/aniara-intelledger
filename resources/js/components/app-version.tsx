import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export default function AppVersion({ className }: { className?: string }) {
    const { appVersion } = usePage().props;

    return (
        <p
            className={cn(
                'text-xs text-muted-foreground tabular-nums',
                className,
            )}
            aria-label={`Application version ${appVersion}`}
        >
            v{appVersion}
        </p>
    );
}
