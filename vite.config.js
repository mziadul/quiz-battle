import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: [
            laravel({
                input: 'resources/js/app.js',
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
        ],
        server: {
            host: env.VITE_HOST || '0.0.0.0',
            port: Number(env.VITE_PORT) || 5175,
            strictPort: true,
            hmr: {
                host: env.VITE_HMR_HOST || 'localhost',
                port: Number(env.VITE_HMR_PORT) || 5175,
            },
            watch: {
                usePolling: true,
            },
        },
    };
});
