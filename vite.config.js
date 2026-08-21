import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const bindIp = env.APP_BIND_IP || '127.0.0.5';
    const vitePort = Number(env.VITE_PORT || 5173);
    const devOrigin = `http://${bindIp}:${vitePort}`;

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
            tailwindcss(),
        ],
        server: {
            host: '0.0.0.0',
            port: vitePort,
            strictPort: true,
            // В Docker public/hot не должен указывать на 0.0.0.0: браузер с хоста не откроет CSS.
            origin: devOrigin,
            hmr: {
                host: bindIp,
                port: vitePort,
            },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
