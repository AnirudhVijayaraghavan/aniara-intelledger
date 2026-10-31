import { useState } from 'react';
import { createRoot } from 'react-dom/client';
import PasswordInput from '@/components/password-input';
import {
    TransactionFinancialFields,
    TransactionMetadataFields,
} from '@/components/transaction-fields';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import './styles.css';
import { SharedPrimitives } from './shared-primitives';

function Fixture() {
    const [submits, setSubmits] = useState(0);
    const [selection, setSelection] = useState('');
    const [menuAction, setMenuAction] = useState('None');
    const [data, setData] = useState({
        amount: '123.00000001',
        transaction_date: '2026-10-01',
        posted_date: '2026-10-02',
        kind: 'expense',
        category_id: '',
        payee_id: '',
        description: 'Synthetic observation',
        excluded_from_analytics: false,
        reason: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({
        amount: 'Enter a valid recorded amount.',
        transaction_date: 'Check the transaction date.',
        posted_date: 'Check the posting date.',
        kind: 'Check the kind.',
        category_id: 'Check the category.',
        payee_id: 'Check the payee.',
        description: 'Check the description.',
        excluded_from_analytics: 'Check the exclusion.',
        reason: 'Check the reason.',
    });
    return (
        <main className="mx-auto grid max-w-4xl gap-6 bg-background p-4 text-foreground sm:p-6">
            <h1 className="text-2xl font-semibold">
                Synthetic component accessibility
            </h1>
            <p>
                Isolated examples only. No household records or backend
                connections.
            </p>
            <section aria-labelledby="password-heading" className="grid gap-3">
                <h2 id="password-heading" className="text-xl">
                    Password controls
                </h2>
                <form
                    className="grid max-w-sm gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        setSubmits((count) => count + 1);
                    }}
                >
                    <Label htmlFor="fixture-password">Synthetic password</Label>
                    <PasswordInput
                        id="fixture-password"
                        name="password"
                        autoComplete="current-password"
                        defaultValue="synthetic-only"
                    />
                    <Button type="submit">Submit synthetic form</Button>
                    <output aria-label="Submission count">{submits}</output>
                </form>
                <div className="grid max-w-sm gap-2">
                    <Label htmlFor="disabled-password">Disabled password</Label>
                    <PasswordInput id="disabled-password" disabled />
                    <Button variant="outline">After disabled password</Button>
                </div>
            </section>
            <section
                aria-labelledby="fields-heading"
                className="grid gap-4 rounded-md bg-card p-4 sm:grid-cols-2"
            >
                <h2 id="fields-heading" className="text-xl sm:col-span-2">
                    Transaction fields
                </h2>
                <TransactionFinancialFields
                    prefix="fixture"
                    data={data}
                    onChange={(patch) =>
                        setData((current) => ({ ...current, ...patch }))
                    }
                    errors={errors}
                    currency="USD"
                    isLiability
                    includePosted
                />
                <TransactionMetadataFields
                    prefix="fixture"
                    data={data}
                    onChange={(patch) =>
                        setData((current) => ({ ...current, ...patch }))
                    }
                    errors={errors}
                    categories={[
                        {
                            id: 1,
                            name: 'Synthetic supplies',
                            kind: 'expense',
                            parent_id: null,
                        },
                    ]}
                    payees={[{ id: 1, name: 'Synthetic payee' }]}
                    team={{
                        id: 1,
                        slug: 'synthetic',
                        name: 'Synthetic household',
                    }}
                />
                <Button variant="outline" onClick={() => setErrors({})}>
                    Clear validation errors
                </Button>
            </section>
            <section
                aria-labelledby="primitives-heading"
                className="grid gap-4"
            >
                <h2 id="primitives-heading" className="text-xl">
                    Interactive primitives
                </h2>
                <div className="grid gap-2">
                    <Label htmlFor="valid-input">Valid reference</Label>
                    <Input
                        id="valid-input"
                        defaultValue="Synthetic reference"
                    />
                    <Label htmlFor="currency-choice">Display currency</Label>
                    <Select value={selection} onValueChange={setSelection}>
                        <SelectTrigger id="currency-choice" className="w-full">
                            <SelectValue placeholder="Choose a currency" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="USD">USD</SelectItem>
                            <SelectItem value="INR">INR</SelectItem>
                            <SelectItem value="disabled" disabled>
                                Unavailable currency
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <output aria-label="Selected currency">
                        {selection || 'None'}
                    </output>
                </div>
                <div className="flex flex-wrap gap-3">
                    <Dialog>
                        <DialogTrigger asChild>
                            <Button>Open example dialog</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>
                                    Review synthetic observation
                                </DialogTitle>
                                <DialogDescription>
                                    This example cannot save financial records.
                                </DialogDescription>
                            </DialogHeader>
                            <Label htmlFor="dialog-note">Review note</Label>
                            <Input id="dialog-note" />
                            <DialogClose asChild>
                                <Button variant="outline">Finish review</Button>
                            </DialogClose>
                        </DialogContent>
                    </Dialog>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline">Example actions</Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent>
                            <DropdownMenuItem
                                onSelect={() => setMenuAction('Review')}
                            >
                                Review example
                            </DropdownMenuItem>
                            <DropdownMenuItem disabled>
                                Unavailable action
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => setMenuAction('Discard')}
                            >
                                Discard example
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Button variant="destructive">Destructive example</Button>
                    <Button disabled>Unavailable example</Button>
                    <Button disabled aria-busy="true">
                        Saving example
                    </Button>
                </div>
                <output aria-label="Selected action">{menuAction}</output>
                <Alert variant="destructive">
                    <AlertTitle>Synthetic validation notice</AlertTitle>
                    <AlertDescription>
                        Review the highlighted fields. No records were changed.
                    </AlertDescription>
                </Alert>
            </section>
            <SharedPrimitives />
        </main>
    );
}

const root = document.getElementById('root');
if (!root) {
    throw new Error('Missing component fixture root.');
}
const dark = matchMedia('(prefers-color-scheme: dark)').matches;
document.documentElement.classList.toggle('dark', dark);
document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
createRoot(root).render(<Fixture />);
