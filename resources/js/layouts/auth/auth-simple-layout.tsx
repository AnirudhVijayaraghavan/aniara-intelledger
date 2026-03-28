import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-[linear-gradient(145deg,rgba(203,243,240,0.68),rgba(255,255,255,0.98),rgba(255,191,105,0.14))] p-6 md:p-10 dark:bg-[linear-gradient(145deg,rgba(15,31,30,0.98),rgba(11,22,21,1),rgba(46,196,182,0.12))]">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(46,196,182,0.14),transparent_45%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(46,196,182,0.20),transparent_50%)]" />

            <div className="relative w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-5">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-3 font-medium"
                        >
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl border border-[#2ec4b6] bg-[#2ec4b6] text-sm font-semibold text-white shadow-lg shadow-[#2ec4b6]/30">
                                IL
                            </div>
                            <div className="space-y-1 text-center">
                                <p className="text-xs font-semibold uppercase tracking-[0.35em] text-[#5c7875] dark:text-[#95b9b5]">
                                    IntelLedger
                                </p>
                                <p className="text-xs text-[#5c7875] dark:text-[#95b9b5]">
                                    Investment intelligence ledger
                                </p>
                            </div>
                        </Link>

                        <div className="w-full rounded-[1.75rem] border border-[#0f1f1e]/10 bg-white/92 px-6 py-7 shadow-xl shadow-[#2ec4b6]/10 backdrop-blur dark:border-white/10 dark:bg-[#132725]/92">
                            <div className="space-y-2 text-center">
                                <h1 className="text-xl font-semibold text-[#0f1f1e] dark:text-white">
                                    {title}
                                </h1>
                                <p className="text-center text-sm leading-6 text-[#5c7875] dark:text-[#95b9b5]">
                                    {description}
                                </p>
                            </div>

                            <div className="mt-6">{children}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
