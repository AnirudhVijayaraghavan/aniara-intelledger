import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form } from '@inertiajs/react';
import { useRef } from 'react';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <section className="space-y-4 rounded-3xl border border-rose-200/60 bg-rose-50/70 p-5 shadow-[0_24px_60px_-50px_rgba(15,23,42,0.45)] dark:border-rose-500/20 dark:bg-rose-500/10">
            <div className="space-y-1">
                <p className="text-xs uppercase tracking-[0.32em] text-rose-600/80 dark:text-rose-200/80">
                    Danger zone
                </p>
                <HeadingSmall
                    title="Delete account"
                    description="Delete your account and all of its resources"
                />
            </div>
            <div className="space-y-3 rounded-2xl border border-rose-200/70 bg-white/70 p-3 text-rose-700 dark:border-rose-500/20 dark:bg-rose-950/40 dark:text-rose-100">
                <div className="space-y-1">
                    <p className="font-semibold">Warning</p>
                    <p className="text-sm">
                        Please proceed with caution, this cannot be undone.
                    </p>
                </div>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="destructive"
                            data-test="delete-user-button"
                        >
                            Delete account
                        </Button>
                    </DialogTrigger>
                    <DialogContent className="rounded-3xl border border-rose-200/70 bg-white/90 p-6 shadow-[0_30px_80px_-60px_rgba(15,23,42,0.7)] backdrop-blur dark:border-rose-500/30 dark:bg-slate-900/90">
                        <DialogTitle className="text-slate-900 dark:text-white">
                            Are you sure you want to delete your account?
                        </DialogTitle>
                        <DialogDescription className="text-slate-600 dark:text-slate-300">
                            Once your account is deleted, all of its resources
                            and data will also be permanently deleted. Please
                            enter your password to confirm you would like to
                            permanently delete your account.
                        </DialogDescription>

                        <Form
                            {...ProfileController.destroy.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            onError={() => passwordInput.current?.focus()}
                            resetOnSuccess
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="password"
                                            className="sr-only"
                                        >
                                            Password
                                        </Label>

                                    <Input
                                        id="password"
                                        type="password"
                                        name="password"
                                        ref={passwordInput}
                                        placeholder="Password"
                                        autoComplete="current-password"
                                        className="bg-white/80 dark:bg-slate-950/60"
                                    />

                                        <InputError message={errors.password} />
                                    </div>

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>

                                        <Button
                                            variant="destructive"
                                            disabled={processing}
                                            asChild
                                        >
                                            <button
                                                type="submit"
                                                data-test="confirm-delete-user-button"
                                            >
                                                Delete account
                                            </button>
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </section>
    );
}
