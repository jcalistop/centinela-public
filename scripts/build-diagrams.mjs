import { execSync } from "node:child_process";
import { mkdirSync, readdirSync } from "node:fs";
import { basename, dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const sourceDir = join(root, "resources/docs/diagrams");
const outputDir = join(root, "public/docs/diagrams");
const configPath = join(sourceDir, "mermaid.config.json");

mkdirSync(outputDir, { recursive: true });

const sources = readdirSync(sourceDir).filter((name) => name.endsWith(".mmd"));

if (sources.length === 0) {
    console.error(
        "[diagrams:build] No se encontraron archivos .mmd en resources/docs/diagrams",
    );
    process.exit(1);
}

const skip = new Set(["ciclo-comges.mmd"]);

for (const source of sources) {
    if (skip.has(source)) {
        console.log(
            `[diagrams:build] omitido ${source} (export Archify: npm run diagrams:export-ciclo-comges)`,
        );
        continue;
    }
    const input = join(sourceDir, source);
    const slug = basename(source, ".mmd");
    const output = join(outputDir, `${slug}.svg`);

    try {
        execSync(`npx mmdc -i "${input}" -o "${output}" -c "${configPath}"`, {
            cwd: root,
            stdio: "inherit",
        });
        console.log(
            `[diagrams:build] ${source} -> public/docs/diagrams/${slug}.svg`,
        );
    } catch {
        console.error(`[diagrams:build] Error al compilar ${source}`);
        process.exit(1);
    }
}
