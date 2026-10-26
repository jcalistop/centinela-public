import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/sass/app.scss",
                "resources/js/app.js",
                "resources/js/metas-copy-image.js",
                "resources/js/metas-objetivo-quill.js",
                "resources/js/messaging-body-quill.js",
                "resources/css/filament/filament/theme.css",
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                quietDeps: true,
                silenceDeprecations: [
                    "import",
                    "global-builtin",
                    "color-functions",
                ],
            },
        },
    },
});
