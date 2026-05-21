(function () {
    function getConfig() {
        return window.messageShortcutsConfig || {};
    }

    function isEditable() {
        return !!getConfig().editable;
    }

    function getMessageField() {
        return document.getElementById("shortcuts_message");
    }

    function getMessageEditor() {
        if (!window.tinymce || typeof window.tinymce.get !== "function") {
            return null;
        }
        return window.tinymce.get("shortcuts_message");
    }

    function setMessageError(message) {
        var errorWrap = document.getElementById("shortcutsMessageError");
        if (!errorWrap) {
            return;
        }

        var span = errorWrap.querySelector("span") || errorWrap;
        span.textContent = message || "";
    }

    function getPlainTextFromHtml(html) {
        var temp = document.createElement("div");
        temp.innerHTML = String(html || "");
        return (temp.textContent || temp.innerText || "").replace(/\s+/g, " ").trim();
    }

    function syncEditorContent() {
        var editor = getMessageEditor();
        if (editor && typeof editor.save === "function") {
            editor.save();
        }
    }

    function validateMessageField() {
        if (!isEditable()) {
            return true;
        }

        syncEditorContent();
        var messageField = getMessageField();
        if (!messageField) {
            return true;
        }

        if (getPlainTextFromHtml(messageField.value) === "") {
            setMessageError("Message Shortcuts is required.");
            var editor = getMessageEditor();
            if (editor && typeof editor.focus === "function") {
                editor.focus();
            }
            return false;
        }

        setMessageError("");
        return true;
    }

    function initTinyMce() {
        if (!isEditable()) {
            return;
        }

        var messageField = getMessageField();
        if (!messageField || !window.tinymce || typeof window.tinymce.init !== "function") {
            return;
        }

        var existingEditor = getMessageEditor();
        if (existingEditor && typeof existingEditor.remove === "function") {
            existingEditor.remove();
        }

        window.tinymce.init({
            selector: "#shortcuts_message",
            base_url: getConfig().siteUrl ? getConfig().siteUrl + "/header/tinymce" : undefined,
            license_key: "gpl",
            menubar: false,
            branding: false,
            promotion: false,
            statusbar: false,
            height: 340,
            resize: true,
            plugins: "lists link emoticons autolink paste",
            toolbar: "undo redo | blocks | bold italic underline forecolor backcolor | bullist numlist | link emoticons | removeformat",
            browser_spellcheck: true,
            contextmenu: false,
            link_title: false,
            link_target_list: false,
            block_formats: "Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4",
            valid_elements: "p[style],br,strong/b,em/i,u,ul[style],ol[style],li[style],blockquote[style],code,pre,a[href|target|rel|style],span[style],div[style],h1[style],h2[style],h3[style],h4[style]",
            valid_styles: {
                "*": "color background-color text-align"
            },
            content_style: "body { font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; }",
            setup: function (editor) {
                var clearMessageError = function () {
                    setMessageError("");
                };

                editor.on("init change keyup undo redo setcontent", function () {
                    editor.save();
                    clearMessageError();
                });
            }
        });
    }

    function bindFormEvents() {
        var form = document.getElementById("form");
        if (!form) {
            return;
        }

        form.addEventListener("submit", function (event) {
            var submitter = event.submitter;
            if (submitter && submitter.value === "back") {
                return;
            }

            if (!validateMessageField()) {
                event.preventDefault();
            }
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () {
            initTinyMce();
            bindFormEvents();
        });
    } else {
        initTinyMce();
        bindFormEvents();
    }
})();
