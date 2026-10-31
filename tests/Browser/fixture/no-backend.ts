// Only the unused inline-reference route imports are replaced. Components stay real.
export const store = {
    url(): never {
        throw new Error(
            'Backend routes are unavailable in component fixtures.',
        );
    },
};
