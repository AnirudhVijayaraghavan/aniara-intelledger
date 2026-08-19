import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon({
    alt = 'IntelLedger',
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img src="/images/intelledger-ledger-mark.png" alt={alt} {...props} />
    );
}
