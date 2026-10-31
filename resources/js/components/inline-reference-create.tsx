import { useHttp } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { ReferenceFields } from '@/components/reference-fields';
import type { ReferenceInput } from '@/components/reference-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { store as storeCategory } from '@/routes/categories';
import { store as storePayee } from '@/routes/payees';
import type { FinancialTeam } from '@/types/finance';
import type { Payee, TransactionCategory } from '@/types/transactions';

type Props = { team: FinancialTeam; prefix: string } & (
    | {
          entity: 'category';
          initialKind: 'income' | 'expense';
          roots: TransactionCategory[];
          onCreated: (category: TransactionCategory) => void;
      }
    | { entity: 'payee'; onCreated: (payee: Payee) => void }
);
export default function InlineReferenceCreate(props: Props) {
    const [open, setOpen] = useState(false);
    const [failure, setFailure] = useState('');
    const http = useHttp<
        ReferenceInput,
        { category?: TransactionCategory; payee?: Payee }
    >({
        name: '',
        kind: props.entity === 'category' ? props.initialKind : 'expense',
        parent_id: '',
    });
    const launch = () => {
        http.setData({
            name: '',
            kind: props.entity === 'category' ? props.initialKind : 'expense',
            parent_id: '',
        });
        http.clearErrors();
        setFailure('');
        setOpen(true);
    };
    const submit = async (event: FormEvent) => {
        event.preventDefault();
        event.stopPropagation();
        setFailure('');
        http.transform((data) =>
            props.entity === 'category'
                ? { ...data, parent_id: data.parent_id || null }
                : { name: data.name },
        );
        try {
            await http.post(
                (props.entity === 'category' ? storeCategory : storePayee).url(
                    props.team.slug,
                ),
                {
                    onSuccess: (data) => {
                        if (props.entity === 'category' && data.category) {
                            props.onCreated(data.category);
                        }
                        if (props.entity === 'payee' && data.payee) {
                            props.onCreated(data.payee);
                        }
                        setOpen(false);
                    },
                },
            );
        } catch {
            setFailure('Could not save. Review the details or try again.');
        }
    };
    return (
        <>
            <Button type="button" size="sm" variant="outline" onClick={launch}>
                <Plus />
                New {props.entity}
            </Button>
            <Dialog
                open={open}
                onOpenChange={(value) => {
                    if (!http.processing) {
                        setOpen(value);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>New {props.entity}</DialogTitle>
                        <DialogDescription>
                            Create a household reference and keep your
                            transaction draft in place.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-4">
                        <ReferenceFields
                            prefix={props.prefix + '-inline-' + props.entity}
                            category={props.entity === 'category'}
                            data={http.data}
                            onChange={(patch) =>
                                http.setData((current) => ({
                                    ...current,
                                    ...patch,
                                }))
                            }
                            errors={http.errors}
                            roots={
                                props.entity === 'category' ? props.roots : []
                            }
                            lockKind
                            kindHint="Kind follows the transaction being classified."
                        />
                        {failure && (
                            <div role="alert">
                                <InputError message={failure} />
                            </div>
                        )}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={http.processing}
                                onClick={() => setOpen(false)}
                            >
                                Keep draft
                            </Button>
                            <Button disabled={http.processing}>
                                Create {props.entity}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
