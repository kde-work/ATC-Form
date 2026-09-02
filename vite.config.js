import { spawn } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const rootDir = path.dirname(fileURLToPath(import.meta.url));

/**
 * После Vite-сборки Laravel-ассетов собирает Angular SPA (калькулятор / admin),
 * которую nginx отдаёт из frontend/dist/frontend/browser.
 */
function angularFrontendBuildPlugin() {
    let ran = false;

    return {
        name: 'angular-frontend-build',
        apply: 'build',
        enforce: 'post',
        async closeBundle() {
            if (ran) {
                return;
            }
            ran = true;

            const frontendDir = path.join(rootDir, 'frontend');
            const npmCmd = process.platform === 'win32' ? 'npm.cmd' : 'npm';

            await new Promise((resolve, reject) => {
                // Одна строка команды: на Windows shell нужен для npm.cmd, без DEP0190.
                const child = spawn(`${npmCmd} run build`, {
                    cwd: frontendDir,
                    env: process.env,
                    stdio: 'inherit',
                    shell: true,
                });

                child.on('error', reject);
                child.on('close', (code) => {
                    if (code === 0) {
                        resolve();
                        return;
                    }

                    reject(new Error(`Angular frontend build failed with exit code ${code}`));
                });
            });
        },
    };
}

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
            angularFrontendBuildPlugin(),
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
