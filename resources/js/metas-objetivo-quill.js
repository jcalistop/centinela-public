import Quill from "quill";
import "quill/dist/quill.snow.css";

const TOOLBAR = [["bold", "underline"]];

function findInput(editorEl) {
    const group = editorEl.closest(".form-group");

    return group
        ? group.querySelector("[data-metas-objetivo-quill-input]")
        : null;
}

function isPlainText(value) {
    return value === "" || !value.includes("<");
}

function syncInput(quill, input) {
    input.value = quill.root.innerHTML;
    input.dispatchEvent(new Event("input", { bubbles: true }));
}

function initEditor(editorEl) {
    if (editorEl.dataset.quillReady === "1") {
        return;
    }

    const input = findInput(editorEl);
    if (!input) {
        return;
    }

    const quill = new Quill(editorEl, {
        theme: "snow",
        modules: {
            toolbar: TOOLBAR,
        },
    });

    const initial = input.value || "";
    if (initial !== "") {
        if (isPlainText(initial)) {
            quill.setText(initial);
        } else {
            quill.clipboard.dangerouslyPasteHTML(initial);
        }
    }

    quill.on("text-change", () => {
        syncInput(quill, input);
    });

    editorEl.dataset.quillReady = "1";
}

export function initMetasObjetivoQuill(root = document) {
    root.querySelectorAll("[data-metas-objetivo-quill]").forEach(initEditor);
}

document.addEventListener("DOMContentLoaded", () => {
    initMetasObjetivoQuill();
});

document.addEventListener("livewire:initialized", () => {
    initMetasObjetivoQuill();

    Livewire.hook("morph.updated", () => {
        queueMicrotask(() => initMetasObjetivoQuill());
    });
});
