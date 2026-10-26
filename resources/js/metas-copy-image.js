import { toSvg } from "html-to-image";

const MSG_OK =
    "Imagen copiada al portapapeles. Puedes pegarla en PowerPoint (Ctrl+V).";
const MSG_CLIPBOARD =
    "No se pudo acceder al portapapeles. Usa HTTPS o localhost y permite el permiso de portapapeles.";
const MSG_CAPTURE =
    "No se pudo generar la imagen. Reintenta o usa Imprimir / Guardar como PDF.";
const MSG_MISSING = "No se encontró el contenido a capturar.";

function notify(ok, message) {
    if (typeof toastr !== "undefined") {
        if (ok) {
            toastr.success(message);
        } else {
            toastr.error(message);
        }

        return;
    }

    window.alert(message);
}

function isExcludedFromCapture(node) {
    return (
        node instanceof HTMLElement &&
        (node.classList.contains("no-print") ||
            node.classList.contains("no-capture"))
    );
}

function captureOptions() {
    return {
        pixelRatio: 2,
        backgroundColor: "#ffffff",
        cacheBust: true,
        // Voyager carga Open Sans desde fonts.googleapis.com (CORS): skipFonts evita SecurityError en cssRules.
        skipFonts: true,
        filter: (node) => !isExcludedFromCapture(node),
    };
}

function waitForPaint() {
    return new Promise((resolve) => {
        requestAnimationFrame(() => {
            requestAnimationFrame(() => resolve());
        });
    });
}

function decodeSvgDataUrl(dataUrl) {
    const comma = dataUrl.indexOf(",");
    const payload = comma >= 0 ? dataUrl.slice(comma + 1) : dataUrl;

    return decodeURIComponent(payload);
}

function encodeSvgDataUrl(raw) {
    return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(raw)}`;
}

/**
 * Prepara el SVG de html-to-image para rasterizar en Chrome:
 * - sin url(http…) (recursos externos fallan en SVG-as-image)
 * - sin atributos Livewire/Alpine que inflan el markup
 * - externalResourcesRequired=false
 *
 * Luego se debe cargar como data-URL (blob: + foreignObject taintéa el canvas).
 */
function sanitizeSvgMarkup(raw) {
    let out = raw;

    out = out.replace(
        /externalResourcesRequired="true"/g,
        'externalResourcesRequired="false"',
    );
    out = out.replace(/url\(\s*(['"]?)https?:\/\/[^)]+\1\s*\)/gi, "none");
    out = out.replace(/\bsrc=(['"])https?:\/\/[^'"]+\1/gi, "src=$1$1");
    out = out.replace(/\bhref=(['"])https?:\/\/[^'"]+\1/gi, "href=$1#$1");
    out = out.replace(/\s(?:wire|x|[@]):[a-zA-Z0-9_.-]+="[^"]*"/g, "");
    out = out.replace(/\s(?:wire|x|[@]):[a-zA-Z0-9_.-]+='[^']*'/g, "");
    out = out.replace(/\s(?:wire|x|[@]):[a-zA-Z0-9_.-]+(?=[\s>])/g, "");

    return out;
}

function loadImage(url) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            if (typeof img.decode === "function") {
                img.decode()
                    .then(() => resolve(img))
                    .catch(() => resolve(img));
            } else {
                resolve(img);
            }
        };
        img.onerror = () => reject(new Error("capture-failed"));
        img.src = url;
    });
}

function canvasToPngBlob(canvas) {
    return new Promise((resolve, reject) => {
        try {
            canvas.toBlob((blob) => {
                if (blob instanceof Blob && blob.size > 0) {
                    resolve(blob);
                } else {
                    reject(new Error("capture-failed"));
                }
            }, "image/png");
        } catch (error) {
            reject(
                error instanceof Error ? error : new Error("capture-failed"),
            );
        }
    });
}

async function rasterizeSvgMarkup(
    markup,
    width,
    height,
    ratio,
    backgroundColor,
) {
    const img = await loadImage(encodeSvgDataUrl(markup));
    const canvas = document.createElement("canvas");
    canvas.width = Math.round(width * ratio);
    canvas.height = Math.round(height * ratio);
    const context = canvas.getContext("2d");

    if (!context) {
        throw new Error("capture-failed");
    }

    context.fillStyle = backgroundColor || "#ffffff";
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(img, 0, 0, canvas.width, canvas.height);

    return canvasToPngBlob(canvas);
}

async function rootToBlob(root) {
    const options = captureOptions();
    const width = Math.max(1, root.offsetWidth || root.scrollWidth);
    const height = Math.max(1, root.offsetHeight || root.scrollHeight);
    const ratio =
        typeof options.pixelRatio === "number" ? options.pixelRatio : 2;
    const rawDataUrl = await toSvg(root, options);
    const markup = sanitizeSvgMarkup(decodeSvgDataUrl(rawDataUrl));

    return rasterizeSvgMarkup(
        markup,
        width,
        height,
        ratio,
        options.backgroundColor,
    );
}

async function copyRootToClipboard(root) {
    if (!(root instanceof HTMLElement)) {
        throw new Error("missing-root");
    }

    if (
        !window.isSecureContext ||
        !navigator.clipboard ||
        typeof ClipboardItem === "undefined"
    ) {
        throw new Error("clipboard-unavailable");
    }

    document.body.classList.add("metas-capturing");

    try {
        await waitForPaint();
        const blob = await rootToBlob(root);

        if (!(blob instanceof Blob) || blob.size === 0) {
            throw new Error("capture-failed");
        }

        await navigator.clipboard.write([
            new ClipboardItem({
                "image/png": blob,
            }),
        ]);
    } finally {
        document.body.classList.remove("metas-capturing");
    }
}

document.addEventListener("click", (event) => {
    const button = event.target.closest("[data-metas-copy-image]");

    if (!(button instanceof HTMLElement)) {
        return;
    }

    event.preventDefault();

    const selector = button.getAttribute("data-metas-copy-image");
    const root = selector ? document.querySelector(selector) : null;

    if (!(root instanceof HTMLElement)) {
        notify(false, MSG_MISSING);

        return;
    }

    button.setAttribute("disabled", "disabled");

    copyRootToClipboard(root)
        .then(() => {
            notify(true, MSG_OK);
        })
        .catch((error) => {
            const code = error instanceof Error ? error.message : "";

            if (code === "clipboard-unavailable") {
                notify(false, MSG_CLIPBOARD);
            } else {
                notify(false, MSG_CAPTURE);
            }

            if (typeof console !== "undefined" && console.error) {
                console.error("metas-copy-image", error);
            }
        })
        .finally(() => {
            button.removeAttribute("disabled");
        });
});
