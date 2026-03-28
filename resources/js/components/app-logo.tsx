import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-xl bg-[linear-gradient(135deg,#2ec4b6,#ffbf69)] text-sidebar-primary-foreground shadow-sm shadow-[#2ec4b6]/20 dark:shadow-[#081412]/40">
                <AppLogoIcon className="size-5 fill-current text-white" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold [font-family:var(--font-editorial)]">
                    IntelLedger
                </span>
            </div>
        </>
    );
}
