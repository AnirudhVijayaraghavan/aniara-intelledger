import { Link } from '@inertiajs/react';
import { PanelsTopLeft } from 'lucide-react';
import { home } from '@/routes';

export default function LedgerBrand() {
    return (
        <Link
            href={home()}
            className="inline-flex items-center gap-3 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
        >
            <span className="flex size-10 shrink-0 items-center justify-center rounded-sm bg-primary text-primary-foreground">
                <PanelsTopLeft className="size-6" strokeWidth={1.4} />
            </span>
            <span className="text-xl leading-none">
                Aniara{' '}
                <span className="block pt-1 text-xs tracking-[0.16em] uppercase">
                    Intelledger
                </span>
            </span>
        </Link>
    );
}
