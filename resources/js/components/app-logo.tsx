import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-9 items-center justify-center rounded-xl bg-emerald-600/10 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200">
                <AppLogoIcon className="size-5 fill-current" />
            </div>
            <div className="ml-2 grid flex-1 text-left text-sm">
                <span className="truncate font-['Space_Grotesk',_ui-sans-serif] font-semibold text-slate-900 dark:text-white">
                    IntelLedger
                </span>
                <span className="text-xs text-slate-500 dark:text-slate-400">
                    Finance OS
                </span>
            </div>
        </>
    );
}
