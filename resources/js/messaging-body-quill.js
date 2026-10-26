import Quill from "quill";
import "quill/dist/quill.snow.css";

const TOOLBAR = [
    ["bold", "italic", "underline"],
    [{ header: [2, 3, false] }],
    [{ list: "ordered" }, { list: "bullet" }],
    ["link"],
];

function findInput(editorEl) {
    const wrap = editorEl.closest(".form-group");

    return wrap
        ? wrap.querySelector("[data-messaging-body-quill-input]")
        : null;
}

function normalizeNbsp(html) {
    return html.replace(/\u00a0/g, " ").replace(/&nbsp;/gi, " ");
}

function syncInput(quill, input) {
    const html = typeof quill.getSemanticHTML === "function"
        ? quill.getSemanticHTML()
        : quill.root.innerHTML;
    input.value = normalizeNbsp(html);
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
        quill.clipboard.dangerouslyPasteHTML(initial);
    }

    quill.on("text-change", () => {
        syncInput(quill, input);
    });

    editorEl.dataset.quillReady = "1";
    editorEl.__messagingQuill = quill;
}

function initMessagingBodyQuill(root = document) {
    root.querySelectorAll("[data-messaging-body-quill]").forEach(initEditor);
}

function resetEditors() {
    document.querySelectorAll("[data-messaging-body-quill]").forEach((editorEl) => {
        const quill = editorEl.__messagingQuill;
        const input = findInput(editorEl);
        if (!quill || !input) {
            return;
        }
        quill.setText("");
        syncInput(quill, input);
    });
}

document.addEventListener("DOMContentLoaded", () => {
    initMessagingBodyQuill();
});

document.addEventListener("livewire:initialized", () => {
    initMessagingBodyQuill();

    Livewire.hook("morph.updated", () => {
        queueMicrotask(() => initMessagingBodyQuill());
    });

    Livewire.on("messaging-form-reset", () => {
        resetEditors();
    });
});
