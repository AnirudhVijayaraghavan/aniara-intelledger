import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { disable, enable, show } from '@/routes/two-factor';
import { type BreadcrumbItem } from '@/types';
import { Form, Head } from '@inertiajs/react';
import { ShieldBan, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

interface TwoFactorProps {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Two-Factor Authentication',
        href: show.url(),
    },
];

export default function TwoFactor({
    requiresConfirmation = false,
    twoFactorEnabled = false,
}: TwoFactorProps) {
    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const statusTone = twoFactorEnabled
        ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-200'
        : 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-200';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Two-Factor Authentication" />

            <h1 className="sr-only">Two-Factor Authentication Settings</h1>

            <SettingsLayout>
                <section className="space-y-4 rounded-3xl border border-slate-200/70 bg-white/80 p-5 shadow-[0_24px_60px_-50px_rgba(15,23,42,0.5)] backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70">
                    <div className="space-y-1">
                        <p className="text-xs uppercase tracking-[0.32em] text-emerald-600/80 dark:text-emerald-300/80">
                            Security
                        </p>
                        <h2 className="sr-only">Two-Factor Authentication</h2>
                    </div>

                    <div className="rounded-2xl border border-slate-200/70 bg-slate-50/80 p-3 dark:border-slate-800/70 dark:bg-slate-950/60">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <Badge className={statusTone}>
                                {twoFactorEnabled ? 'Enabled' : 'Disabled'}
                            </Badge>
                            <span className="text-xs text-slate-500 dark:text-slate-400">
                                Protection status
                            </span>
                        </div>
                        <p className="mt-3 text-sm text-slate-600 dark:text-slate-300">
                            {twoFactorEnabled
                                ? 'You will be prompted for a secure, time-based code during login, retrieved from your authenticator app.'
                                : 'Enable two-factor authentication to require a secure, time-based code during login.'}
                        </p>
                    </div>

                    {twoFactorEnabled ? (
                        <div className="flex flex-col items-start justify-start gap-4">
                            <TwoFactorRecoveryCodes
                                recoveryCodesList={recoveryCodesList}
                                fetchRecoveryCodes={fetchRecoveryCodes}
                                errors={errors}
                            />

                            <div className="relative inline">
                                <Form {...disable.form()}>
                                    {({ processing }) => (
                                        <Button
                                            variant="destructive"
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <ShieldBan /> Disable 2FA
                                        </Button>
                                    )}
                                </Form>
                            </div>
                        </div>
                    ) : (
                        <div className="flex flex-col items-start justify-start gap-3">
                            <div>
                                {hasSetupData ? (
                                    <Button
                                        onClick={() => setShowSetupModal(true)}
                                    >
                                        <ShieldCheck />
                                        Continue Setup
                                    </Button>
                                ) : (
                                    <Form
                                        {...enable.form()}
                                        onSuccess={() =>
                                            setShowSetupModal(true)
                                        }
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                <ShieldCheck />
                                                Enable 2FA
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </div>
                        </div>
                    )}

                    <TwoFactorSetupModal
                        isOpen={showSetupModal}
                        onClose={() => setShowSetupModal(false)}
                        requiresConfirmation={requiresConfirmation}
                        twoFactorEnabled={twoFactorEnabled}
                        qrCodeSvg={qrCodeSvg}
                        manualSetupKey={manualSetupKey}
                        clearSetupData={clearSetupData}
                        fetchSetupData={fetchSetupData}
                        errors={errors}
                    />
                </section>
            </SettingsLayout>
        </AppLayout>
    );
}
