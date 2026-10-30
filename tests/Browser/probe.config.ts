import configuration from '../../playwright.config';

export default {
    ...configuration,
    globalTimeout: 60_000,
    testMatch: 'violation.probe.ts',
    projects: [
        { name: 'intentional-failure', use: { colorScheme: 'light' as const } },
    ],
};
