import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, BookOpen, Compass } from 'lucide-react';
import LedgerBrand from '@/components/ledger-brand';
import LedgerPreview from '@/components/ledger-preview';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function LedgerAuthLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { component } = usePage();

    return (
        <div className="ledger-public grid h-dvh overflow-hidden bg-background text-foreground lg:grid-cols-[1.05fr_1fr]">
            <aside className="ledger-auth-aside hidden min-h-0 flex-col justify-between gap-5 border-r border-border bg-secondary/35 px-8 py-6 lg:flex xl:px-12">
                <LedgerBrand />
                <div className="ledger-auth-aside-content mx-auto flex min-h-0 w-full max-w-lg flex-col gap-5">
                    <div>
                        <p className="mb-3 text-xs tracking-[0.18em] text-muted-foreground uppercase">
                            Your financial operating system
                        </p>
                        <h2 className="text-3xl leading-tight">
                            A clear view.
                            <br />A considered next step.
                        </h2>
                        <p className="ledger-auth-aside-detail mt-3 max-w-sm text-sm leading-relaxed text-muted-foreground">
                            From the everyday ledger to the long-term portfolio.
                            Give your financial life a place to come together.
                        </p>
                    </div>
                    <LedgerPreview compact />
                    <div className="ledger-auth-aside-detail grid grid-cols-2 gap-6 border-t border-border pt-4 text-sm">
                        <p className="flex gap-2">
                            <BookOpen className="size-4 shrink-0" /> Every
                            detail in perspective.
                        </p>
                        <p className="flex gap-2">
                            <Compass className="size-4 shrink-0" /> Every
                            decision yours.
                        </p>
                    </div>
                </div>
                <div className="flex shrink-0 items-center justify-between gap-4 border-t border-border pt-3 text-xs text-muted-foreground">
                    <span>Clarity in every entry.</span>
                    <span className="rounded-sm bg-accent/40 px-2 py-1 text-accent-foreground">
                        Your financial perspective
                    </span>
                </div>
            </aside>
            <div className="flex min-h-0 min-w-0 flex-col px-6 py-4 sm:px-10 lg:py-6">
                <div className="shrink-0 lg:hidden">
                    <LedgerBrand />
                </div>
                <Link
                    href={home()}
                    className="hidden w-fit shrink-0 items-center gap-2 text-sm text-muted-foreground hover:text-foreground lg:inline-flex"
                >
                    <ArrowLeft className="size-4" /> Back to overview
                </Link>
                <main className="min-h-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-contain">
                    <div
                        key={component}
                        className="ledger-auth-enter ledger-auth-form mx-auto flex min-h-full w-full max-w-sm flex-col justify-center py-5"
                    >
                        <div className="ledger-auth-heading mb-6">
                            <p className="mb-3 flex items-center gap-2 text-xs tracking-[0.16em] text-muted-foreground uppercase">
                                <span className="size-1.5 bg-primary" /> Your
                                personal ledger
                            </p>
                            <h1 className="text-3xl">{title}</h1>
                            <p className="mt-3 text-sm leading-relaxed text-muted-foreground">
                                {description}
                            </p>
                        </div>
                        {children}
                    </div>
                </main>
                <p className="ledger-auth-footer shrink-0 text-center text-xs text-muted-foreground">
                    Your money. Your perspective. Your next chapter.
                </p>
            </div>
        </div>
    );
}
