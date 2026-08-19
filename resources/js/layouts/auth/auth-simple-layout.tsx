import { Link, usePage } from '@inertiajs/react';
import { Landmark, ShieldCheck, WalletCards } from 'lucide-react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="grid min-h-svh bg-background lg:grid-cols-[minmax(0,0.9fr)_minmax(28rem,0.7fr)]">
            <aside className="relative hidden overflow-hidden bg-foreground px-10 py-9 text-background lg:flex lg:flex-col">
                <Link
                    href={home()}
                    className="flex items-center gap-3 self-start text-lg font-semibold"
                >
                    <span className="flex size-9 items-center justify-center rounded-md bg-primary text-primary-foreground">
                        <Landmark className="size-5" />
                    </span>
                    {name}
                </Link>

                <div className="my-auto max-w-md">
                    <p className="mb-5 flex items-center gap-2 text-sm font-medium text-secondary">
                        <ShieldCheck className="size-4" />
                        Private financial operating surface
                    </p>
                    <h2 className="text-4xl leading-tight font-semibold text-balance">
                        A complete view of the wealth you are building.
                    </h2>
                    <p className="mt-5 text-base leading-7 text-background/70">
                        Bring together U.S. and India accounts, expenses,
                        investments, and market watchlists in a single
                        considered ledger.
                    </p>

                    <div className="mt-10 border-y border-background/20">
                        <div className="flex items-center gap-4 py-4">
                            <span className="flex size-9 items-center justify-center rounded-md bg-secondary text-secondary-foreground">
                                <WalletCards className="size-4" />
                            </span>
                            <span>
                                <span className="block text-sm font-medium">
                                    Consolidated
                                </span>
                                <span className="text-sm text-background/65">
                                    USD and INR positions
                                </span>
                            </span>
                        </div>
                        <div className="flex items-center justify-between border-t border-background/20 py-4 text-sm">
                            <span className="text-background/65">
                                Your records
                            </span>
                            <span className="rounded-md bg-background/10 px-2 py-1 font-medium">
                                Protected
                            </span>
                        </div>
                    </div>
                </div>

                <p className="text-xs text-background/55">
                    Clear records. Measured decisions.
                </p>
            </aside>

            <main className="flex min-h-svh items-center justify-center p-6 sm:p-10">
                <div className="w-full max-w-sm">
                    <Link
                        href={home()}
                        className="mb-10 flex items-center gap-3 font-semibold lg:hidden"
                    >
                        <span className="flex size-9 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <Landmark className="size-5" />
                        </span>
                        {name}
                    </Link>
                    <div className="mb-8 space-y-2">
                        <h1 className="text-2xl font-semibold">{title}</h1>
                        <p className="text-sm leading-6 text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
            </main>
        </div>
    );
}
