import { defineConfig } from 'vite-plus';

/**
 * Builds the tracking SDK as a single self-executing script served from /sdk/pulse.js.
 */
export default defineConfig({
    build: {
        outDir: '../public/sdk',
        emptyOutDir: true,
        lib: {
            entry: 'src/index.ts',
            formats: ['iife'],
            name: 'ConversionPulseSdk',
            fileName: () => 'pulse.js',
        },
    },
    test: {
        include: ['tests/**/*.test.ts'],
    },
});
