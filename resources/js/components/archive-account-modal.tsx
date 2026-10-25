import { Form } from '@inertiajs/react';
import { Archive } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { archive } from '@/routes/accounts';
import type { FinancialAccount, FinancialTeam } from '@/types/finance';

export default function ArchiveAccountModal({
    team,
    account,
    open,
    onOpenChange,
}: {
    team: FinancialTeam;
    account: FinancialAccount;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    {...archive.form([team.slug, account.id])}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Archive {account.name}?
                                </DialogTitle>
                                <DialogDescription>
                                    This account will move to the archived list.
                                    Its balance history and recorded holders
                                    will be preserved.
                                </DialogDescription>
                            </DialogHeader>
                            <input
                                type="hidden"
                                name="confirmation"
                                value="1"
                            />
                            <InputError message={errors.confirmation} />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    <Archive />
                                    Archive account
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
