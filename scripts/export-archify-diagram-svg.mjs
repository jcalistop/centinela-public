import { execSync } from "node:child_process";
import { mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");

const archifyRoot =
    process.env.ARCHIFY_HOME ??
    join(
        process.env.USERPROFILE ?? process.env.HOME ?? "",
        ".agents",
        "skills",
        "archify",
    );

const defaults = {
    spec: "docs/architecture/modules/comges/ciclo-comges/centinela-ciclo-comges.workflow.json",
    html: "docs/architecture/modules/comges/ciclo-comges/centinela-ciclo-comges.html",
    output: "public/docs/diagrams/ciclo-comges.svg",
    theme: "light",
    preset: "classic",
};

const specPath = resolve(root, process.argv[2] ?? defaults.spec);
const htmlPath = resolve(root, process.argv[3] ?? defaults.html);
const outputPath = resolve(root, process.argv[4] ?? defaults.output);
const theme = process.argv[5] ?? defaults.theme;
const preset = process.argv[6] ?? defaults.preset;

const archifyBin = join(archifyRoot, "bin", "archify.mjs");
const visualCheckPath = pathToFileURL(
    join(archifyRoot, "bin", "visual-check.mjs"),
).href;

async function evaluate(cdp, sessionId, expression, awaitPromise = false) {
    const response = await cdp.send(
        "Runtime.evaluate",
        {
            expression,
            awaitPromise,
            returnByValue: true,
        },
        sessionId,
    );

    if (response.exceptionDetails) {
        throw new Error(
            response.exceptionDetails.exception?.description ??
                response.exceptionDetails.text ??
                "Runtime.evaluate failed",
        );
    }

    return response.result?.value;
}

function lockSvgTheme(svgText) {
    return svgText.replace(/<svg\b([^>]*)>/, (match, attrs) => {
        let next = attrs;

        if (/\bdata-theme=/.test(next)) {
            next = next.replace(/\bdata-theme="[^"]*"/, 'data-theme="light"');
        } else {
            next = ` data-theme="light"${next}`;
        }

        if (/\bdata-preset=/.test(next)) {
            next = next.replace(
                /\bdata-preset="[^"]*"/,
                `data-preset="${preset}"`,
            );
        } else {
            next = ` data-preset="${preset}"${next}`;
        }

        return `<svg${next}>`;
    });
}

async function exportSvg() {
    const spec = JSON.parse(readFileSync(specPath, "utf8"));
    const diagramType = spec.diagram_type ?? "workflow";

    console.log(`[diagrams:export-archify] deliver showcase (${diagramType})…`);
    execSync(
        `node "${archifyBin}" deliver ${diagramType} "${specPath}" "${htmlPath}" --quality showcase --json`,
        { cwd: root, stdio: "inherit" },
    );

    const { findChrome, ChromeVisualBrowser } = await import(visualCheckPath);
    const chromePath = findChrome();

    if (!chromePath) {
        console.error(
            "[diagrams:export-archify] Chrome/Chromium no encontrado. Instala Chrome o define CHROME_PATH.",
        );
        process.exit(1);
    }

    const browser = new ChromeVisualBrowser(chromePath);

    try {
        const sessionId = await browser.sessionPromise;
        const url = new URL(pathToFileURL(htmlPath).href);
        url.searchParams.set("theme", theme);

        const loaded = browser.cdp.waitFor("Page.loadEventFired", sessionId);
        const navigation = await browser.cdp.send(
            "Page.navigate",
            { url: url.href },
            sessionId,
        );

        if (navigation.errorText) {
            throw new Error(
                `Chrome navigation failed: ${navigation.errorText}`,
            );
        }

        await loaded;

        await evaluate(
            browser.cdp,
            sessionId,
            `(function () {
            document.documentElement.setAttribute('data-motion', 'still');
            var panel = document.querySelector('.diagram-container');
            if (panel) panel.setAttribute('data-detail-level', 'read');
            var fontsReady = document.fonts && document.fonts.ready
                ? document.fonts.ready.catch(function () {})
                : Promise.resolve();
            return fontsReady.then(function () {
                if (window.Archify && Archify.readerLayout && typeof Archify.readerLayout.whenStable === 'function') {
                    return Archify.readerLayout.whenStable();
                }
            }).then(function () {
                if (window.Archify && Archify.viewerChromeLayout && typeof Archify.viewerChromeLayout.whenStable === 'function') {
                    return Archify.viewerChromeLayout.whenStable();
                }
            });
        })()`,
            true,
        );

        await evaluate(
            browser.cdp,
            sessionId,
            "!!(window.Archify && Archify.exportMenu)",
            false,
        );

        const svgText = await evaluate(
            browser.cdp,
            sessionId,
            String.raw`(async function () {
            var originalCreateObjectURL = URL.createObjectURL;
            var captured;
            URL.createObjectURL = function (blob) {
                captured = blob.text();
                return 'blob:archify-export';
            };
            try {
                await Archify.exportMenu.run('svg');
                return await captured;
            } finally {
                URL.createObjectURL = originalCreateObjectURL;
            }
        })()`,
            true,
        );

        if (
            !svgText ||
            typeof svgText !== "string" ||
            !svgText.includes("<svg")
        ) {
            throw new Error(
                "La exportación SVG no devolvió un documento válido.",
            );
        }

        mkdirSync(dirname(outputPath), { recursive: true });
        const locked = lockSvgTheme(svgText);
        writeFileSync(outputPath, locked, "utf8");
        console.log(
            `[diagrams:export-archify] ${htmlPath} (${theme}/${preset}) -> ${outputPath}`,
        );
    } finally {
        await browser.close();
    }
}

exportSvg().catch((error) => {
    console.error(`[diagrams:export-archify] ${error.message}`);
    process.exit(1);
});
