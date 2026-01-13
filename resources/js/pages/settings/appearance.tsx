import { Head } from '@inertiajs/react';

import AppearanceTabs from '@/components/appearance-tabs';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit as editAppearance } from '@/routes/appearance';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: editAppearance().url,
    },
];

export default function Appearance() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Appearance settings" />

            <h1 className="sr-only">Appearance Settings</h1>

            <SettingsLayout>
                <section className="space-y-4 rounded-3xl border border-slate-200/70 bg-white/80 p-5 shadow-[0_24px_60px_-50px_rgba(15,23,42,0.5)] backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70">
                    <div className="space-y-1">
                        <p className="text-xs uppercase tracking-[0.32em] text-emerald-600/80 dark:text-emerald-300/80">
                            Appearance
                        </p>
                        <h2 className="sr-only">Appearance settings</h2>
                    </div>
                    <AppearanceTabs className="rounded-2xl border border-slate-200/70 bg-white/80 p-1.5 shadow-[0_12px_30px_-24px_rgba(15,23,42,0.4)] backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70" />
                </section>
            </SettingsLayout>
        </AppLayout>
    );
}
