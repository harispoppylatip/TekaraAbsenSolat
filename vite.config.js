import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

// Huruf Montserrat dan Inter dimuat langsung dari lembar gaya pada tata letak,
// supaya berkas tema bisa dipakai tanpa langkah tambahan saat pemasangan.
export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
