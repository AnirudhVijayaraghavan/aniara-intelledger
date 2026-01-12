import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import { Head } from '@inertiajs/react';

export default function AuthLayout({
    children,
    title,
    description,
    ...props
}: {
    children: React.ReactNode;
    title: string;
    description: string;
}) {
    return (
        <>
            <Head>
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700&display=swap"
                    rel="stylesheet"
                />
                <link
                    href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600,700&display=swap"
                    rel="stylesheet"
                />
                <link
                    href="https://fonts.bunny.net/css?family=ibm-plex-mono:400,500&display=swap"
                    rel="stylesheet"
                />
            </Head>
            <AuthLayoutTemplate title={title} description={description} {...props}>
                {children}
            </AuthLayoutTemplate>
        </>
    );
}
