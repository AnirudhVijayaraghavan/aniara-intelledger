import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 48 48"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <rect width="48" height="48" rx="10" fill="#cbf3f0" />
            <path
                d="M13 34V14"
                stroke="#2ec4b6"
                strokeLinecap="round"
                strokeWidth="5"
            />
            <path
                d="M21 34V14"
                stroke="#2ec4b6"
                strokeLinecap="round"
                strokeWidth="5"
            />
            <path
                d="M14 34H35"
                stroke="#102c2a"
                strokeLinecap="round"
                strokeWidth="5"
            />
            <path
                d="M28 34V14"
                stroke="#ff9f1c"
                strokeLinecap="round"
                strokeWidth="5"
            />
            <path
                d="M28 15H37"
                stroke="#ff9f1c"
                strokeLinecap="round"
                strokeWidth="5"
            />
        </svg>
    );
}
