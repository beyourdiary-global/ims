(function () {
    var config = window.customizeBotMsgConfig || {};
    var contexts = config.contexts || {};
    var editable = !!config.editable;
    var state = {
        context: config.initialContext || Object.keys(contexts)[0] || "shopee",
        components: Array.isArray(config.initialComponents) ? config.initialComponents : []
    };
    var dragComponentKey = "";
    var spacerCounter = 0;

    function deepClone(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function getForm() {
        return document.getElementById("customizeBotMsgForm");
    }

    function getContextConfig(contextKey) {
        return contexts[contextKey] || null;
    }

    function normalizeLines(lines) {
        var parsed = parseInt(lines, 10);
        if (!(parsed >= 1 && parsed <= 3)) {
            parsed = 1;
        }
        return parsed;
    }

    function normalizeRemoved(value) {
        return String(value || "N").toUpperCase() === "Y" ? "Y" : "N";
    }

    function normalizeBuilderMode(value) {
        return String(value || "editable").toLowerCase() === "readonly" ? "readonly" : "editable";
    }

    function normalizeUseBuilderText(value, builderMode) {
        if (String(value || "").toUpperCase() === "N") {
            return "N";
        }
        return builderMode === "readonly" ? "N" : "Y";
    }

    function normalizeJoinWithPrevious(value) {
        return String(value || "N").toUpperCase() === "Y" ? "Y" : "N";
    }

    function inferBuilderText(componentText, textPrefix, textSuffix, fallbackText) {
        var builderText = String(componentText || "").replace(/\r\n/g, "\n");

        if (textPrefix && builderText.indexOf(textPrefix) === 0) {
            builderText = builderText.slice(textPrefix.length);
        }

        if (textSuffix && builderText.slice(-textSuffix.length) === textSuffix) {
            builderText = builderText.slice(0, builderText.length - textSuffix.length);
        }

        builderText = builderText.replace(/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/g, "").replace(/[ \t]{2,}/g, " ").trim();
        return builderText || String(fallbackText || "");
    }

    function composeLineText(component) {
        var useBuilderText = String(component && component.use_builder_text || "Y").toUpperCase() === "N" ? "N" : "Y";
        if (useBuilderText === "N") {
            if (component && Object.prototype.hasOwnProperty.call(component, "locked_text")) {
                return String(component.locked_text || "");
            }
            return String(component && component.text || "");
        }

        return String(component && component.text_prefix || "")
            + String(component && component.builder_text || component && component.text || "")
            + String(component && component.text_suffix || "");
    }

    function normalizeComponent(component, index) {
        var fallbackKey = "component_" + (index + 1);
        var type = String(component && component.type || "line").toLowerCase() === "spacer" ? "spacer" : "line";
        var sortOrder = parseInt(component && component.sort_order, 10);
        if (!Number.isFinite(sortOrder)) {
            sortOrder = (index + 1) * 10;
        }
        var textValue = type === "line" ? String(component && component.text || "") : "";
        var builderMode = normalizeBuilderMode(component && component.builder_mode);
        var textPrefix = String(component && component.text_prefix || "");
        var textSuffix = String(component && component.text_suffix || "");
        var defaultBuilderText = String(component && component.default_builder_text || "");
        var useBuilderText = normalizeUseBuilderText(component && component.use_builder_text, builderMode);
        var builderText = String(component && component.builder_text || "");
        if (!builderText) {
            builderText = useBuilderText === "Y"
                ? inferBuilderText(textValue, textPrefix, textSuffix, defaultBuilderText || textValue)
                : (defaultBuilderText || textValue);
        }

        return {
            component_key: String(component && component.component_key || fallbackKey),
            type: type,
            text: textValue,
            default_text: String(component && component.default_text || ""),
            builder_text: type === "line" ? builderText : "",
            default_builder_text: type === "line" ? (defaultBuilderText || builderText) : "",
            builder_mode: type === "line" ? builderMode : "editable",
            text_prefix: type === "line" ? textPrefix : "",
            text_suffix: type === "line" ? textSuffix : "",
            locked_text: type === "line" ? String(component && component.locked_text || "") : "",
            use_builder_text: type === "line" ? useBuilderText : "Y",
            join_with_previous: type === "line" ? normalizeJoinWithPrevious(component && component.join_with_previous) : "N",
            lines: type === "spacer" ? normalizeLines(component && component.lines) : 0,
            default_lines: type === "spacer" ? normalizeLines(component && component.default_lines) : 0,
            sort_order: sortOrder,
            default_order: parseInt(component && component.default_order, 10) || sortOrder,
            removed: normalizeRemoved(component && component.removed)
        };
    }

    function normalizeComponents(components) {
        return (Array.isArray(components) ? components : []).map(function (component, index) {
            return normalizeComponent(component, index);
        });
    }

    function updateSpacerCounter() {
        state.components.forEach(function (component) {
            var match = String(component.component_key || "").match(/^custom_spacer_(\d+)$/);
            if (match) {
                spacerCounter = Math.max(spacerCounter, parseInt(match[1], 10));
            }
        });
    }

    function ensureStateComponents() {
        var contextConfig = getContextConfig(state.context);
        if (!contextConfig) {
            return;
        }

        if (!state.components.length) {
            state.components = normalizeComponents(deepClone(contextConfig.default_components || []));
        } else {
            state.components = normalizeComponents(state.components);
        }

        updateSpacerCounter();
    }

    function sortComponents(components) {
        return components.slice().sort(function (a, b) {
            if (a.sort_order !== b.sort_order) {
                return a.sort_order - b.sort_order;
            }
            return String(a.component_key).localeCompare(String(b.component_key));
        });
    }

    function getActiveComponents() {
        return sortComponents(state.components.filter(function (component) {
            return component.removed !== "Y";
        }));
    }

    function getRemovedComponents() {
        return sortComponents(state.components.filter(function (component) {
            return component.removed === "Y";
        }));
    }

    function rebuildSortOrders() {
        getActiveComponents().forEach(function (component, index) {
            component.sort_order = (index + 1) * 10;
        });

        var nextOrder = (getActiveComponents().length + 1) * 10;
        getRemovedComponents().forEach(function (component) {
            if (!Number.isFinite(component.sort_order)) {
                component.sort_order = nextOrder;
                nextOrder += 10;
            }
        });
    }

    function buildTemplateBody() {
        var lines = [];
        getActiveComponents().forEach(function (component) {
            if (component.type === "spacer") {
                for (var blankIndex = 0; blankIndex < normalizeLines(component.lines); blankIndex++) {
                    lines.push("");
                }
                return;
            }

            component.text = composeLineText(component);
            var textParts = String(component.text || "").replace(/\r\n/g, "\n").split("\n");
            if (component.join_with_previous === "Y" && textParts.length && lines.length) {
                var firstPart = textParts.shift();
                var lastLineIndex = lines.length - 1;
                var separator = lines[lastLineIndex] && firstPart ? " " : "";
                lines[lastLineIndex] += separator + firstPart;
            }
            textParts.forEach(function (line) {
                lines.push(line);
            });
        });
        return lines.join("\n");
    }

    function renderTemplate(templateBody, data, parseMode) {
        return String(templateBody || "").replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, function (_, key) {
            var value = Object.prototype.hasOwnProperty.call(data || {}, key) ? data[key] : "";
            if (Array.isArray(value)) {
                value = value.join(", ");
            }
            value = value == null ? "" : String(value);

            if (String(parseMode || "plain").toLowerCase() === "html") {
                if (/_html$/.test(key)) {
                    return value;
                }
                return value
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            return value;
        });
    }

    function getCurrentContextConfig() {
        return getContextConfig(state.context) || {};
    }

    function getCurrentParseMode() {
        return getCurrentContextConfig().parse_mode || "plain";
    }

    function getCurrentSampleData() {
        return getCurrentContextConfig().sample_data || {};
    }

    function getComponentTemplateDisplayText(component) {
        if (component && component.builder_mode === "readonly") {
            return String(component.builder_text || component.default_builder_text || composeLineText(component) || "");
        }
        return String(composeLineText(component) || "");
    }

    function createRenderedDisplay(content, className, parseMode) {
        var element = document.createElement("div");
        element.className = className;

        if (String(parseMode || "plain").toLowerCase() === "html") {
            element.innerHTML = content || "";
        } else {
            element.textContent = content || "";
        }

        return element;
    }

    function renderComponentTemplateText(templateText) {
        return renderTemplate(templateText, getCurrentSampleData(), getCurrentParseMode());
    }

    function cleanFixedDisplayText(content) {
        return String(content == null ? "" : content).replace(/^\s+|\s+$/g, "");
    }

    function getRenderedComponentText(component) {
        return renderComponentTemplateText(composeLineText(component));
    }

    function getRenderedComponentPrefix(component) {
        return cleanFixedDisplayText(String(component && component.text_prefix || ""));
    }

    function getRenderedComponentSuffix(component) {
        return cleanFixedDisplayText(String(component && component.text_suffix || ""));
    }

    function syncHiddenFields() {
        rebuildSortOrders();
        state.components.forEach(function (component) {
            if (component.type === "line") {
                component.text = composeLineText(component);
            }
        });

        var contextConfig = getContextConfig(state.context);
        var parseMode = contextConfig ? contextConfig.parse_mode : "plain";
        var templateBody = buildTemplateBody();
        var previewValue = renderTemplate(templateBody, contextConfig ? (contextConfig.sample_data || {}) : {}, parseMode);

        var parseModeInput = document.getElementById("parse_mode");
        var templateBodyInput = document.getElementById("template_body");
        var componentsInput = document.getElementById("components_json");
        var previewInput = document.getElementById("preview_sample");

        if (parseModeInput) {
            parseModeInput.value = parseMode;
        }
        if (templateBodyInput) {
            templateBodyInput.value = templateBody;
        }
        if (componentsInput) {
            componentsInput.value = JSON.stringify(sortComponents(state.components));
        }
        if (previewInput) {
            previewInput.value = previewValue;
        }

        renderPreview(previewValue, parseMode);
    }

    function renderPreview(previewValue, parseMode) {
        var previewTarget = document.getElementById(config.previewTargetId || "templatePreview");
        if (!previewTarget) {
            return;
        }

        if (String(parseMode || "plain").toLowerCase() === "html") {
            previewTarget.innerHTML = previewValue || "";
        } else {
            previewTarget.textContent = previewValue || "";
        }
    }

    function removeComponent(componentKey) {
        state.components.forEach(function (component) {
            if (component.component_key === componentKey) {
                component.removed = "Y";
            }
        });
        syncHiddenFields();
        renderBuilder();
    }

    function restoreComponent(componentKey) {
        var restoredComponent = null;
        var reorderedComponents = getActiveComponents().slice();

        state.components.forEach(function (component) {
            if (component.component_key === componentKey) {
                component.removed = "N";
                restoredComponent = component;
            }
        });

        if (restoredComponent) {
            var insertIndex = reorderedComponents.findIndex(function (component) {
                return Number(component.default_order || 0) > Number(restoredComponent.default_order || 0);
            });

            if (insertIndex === -1) {
                reorderedComponents.push(restoredComponent);
            } else {
                reorderedComponents.splice(insertIndex, 0, restoredComponent);
            }

            reorderedComponents.forEach(function (component, index) {
                component.sort_order = (index + 1) * 10;
            });
        }

        syncHiddenFields();
        renderBuilder();
    }

    function focusComponentInput(componentKey) {
        var target = document.querySelector('[data-component-input="' + componentKey + '"]');
        if (target) {
            target.focus();
            if (typeof target.select === "function" && target.tagName === "INPUT") {
                target.select();
            }
        }
    }

    function moveComponentBefore(dragKey, targetKey) {
        if (!dragKey || !targetKey || dragKey === targetKey) {
            return;
        }

        var activeKeys = getActiveComponents().map(function (component) {
            return component.component_key;
        });
        var dragIndex = activeKeys.indexOf(dragKey);
        var targetIndex = activeKeys.indexOf(targetKey);
        if (dragIndex === -1 || targetIndex === -1) {
            return;
        }

        activeKeys.splice(targetIndex, 0, activeKeys.splice(dragIndex, 1)[0]);
        activeKeys.forEach(function (componentKey, index) {
            state.components.forEach(function (component) {
                if (component.component_key === componentKey) {
                    component.sort_order = (index + 1) * 10;
                }
            });
        });

        syncHiddenFields();
        renderBuilder();
    }

    function bindRowDragEvents(row, componentKey) {
        if (!editable) {
            return;
        }

        row.addEventListener("dragstart", function () {
            dragComponentKey = componentKey;
            row.classList.add("dragging");
        });

        row.addEventListener("dragend", function () {
            dragComponentKey = "";
            row.classList.remove("dragging");
            document.querySelectorAll(".customize-bot-row.drop-target").forEach(function (targetRow) {
                targetRow.classList.remove("drop-target");
            });
        });

        row.addEventListener("dragover", function (event) {
            event.preventDefault();
            if (dragComponentKey && dragComponentKey !== componentKey) {
                row.classList.add("drop-target");
            }
        });

        row.addEventListener("dragleave", function () {
            row.classList.remove("drop-target");
        });

        row.addEventListener("drop", function (event) {
            event.preventDefault();
            row.classList.remove("drop-target");
            moveComponentBefore(dragComponentKey, componentKey);
        });
    }

    function createTextControl(component) {
        var renderedPrefix = getRenderedComponentPrefix(component);
        var renderedSuffix = getRenderedComponentSuffix(component);
        var hasLockedDisplay = !!(renderedPrefix || renderedSuffix);

        if (!hasLockedDisplay) {
            var inputOnly = document.createElement("input");
            inputOnly.type = "text";
            inputOnly.value = component.builder_text || "";
            inputOnly.disabled = !editable;
            inputOnly.setAttribute("data-component-input", component.component_key);
            inputOnly.addEventListener("input", function () {
                component.builder_text = inputOnly.value;
                component.text = composeLineText(component);
                syncHiddenFields();
            });
            return inputOnly;
        }

        var wrapper = document.createElement("div");
        wrapper.className = "customize-bot-line-control";

        if (renderedPrefix) {
            wrapper.appendChild(createRenderedDisplay(renderedPrefix, "customize-bot-line-fixed", getCurrentParseMode()));
        }

        var input = document.createElement("input");
        input.type = "text";
        input.value = component.builder_text || "";
        input.disabled = !editable;
        input.className = "customize-bot-line-input";
        input.setAttribute("data-component-input", component.component_key);
        input.addEventListener("input", function () {
            component.builder_text = input.value;
            component.text = composeLineText(component);
            syncHiddenFields();
        });
        wrapper.appendChild(input);

        if (renderedSuffix) {
            wrapper.appendChild(createRenderedDisplay(renderedSuffix, "customize-bot-line-value", getCurrentParseMode()));
        }

        return wrapper;
    }

    function createReadonlyLineControl(component) {
        var label = document.createElement("div");
        label.className = "customize-bot-locked-label";
        label.textContent = getComponentTemplateDisplayText(component);
        label.setAttribute("data-component-input", component.component_key);
        return label;
    }

    function createSpacerControl(component) {
        var label = document.createElement("div");
        label.className = "customize-bot-spacer-label";
        label.setAttribute("data-component-input", component.component_key);
        label.textContent = "Spacer (1 blank line)";
        return label;
    }

    function createIconButton(iconClass, titleText, clickHandler) {
        var button = document.createElement("button");
        button.type = "button";
        button.className = "customize-bot-row-action-btn";
        button.title = titleText;
        button.innerHTML = '<i class="' + iconClass + '"></i>';
        button.disabled = !editable;
        button.addEventListener("click", clickHandler);
        return button;
    }

    function insertSpacerAfter(componentKey) {
        var activeComponents = getActiveComponents().slice();
        var insertIndex = activeComponents.findIndex(function (component) {
            return component.component_key === componentKey;
        });

        spacerCounter += 1;
        var spacerComponent = normalizeComponent({
            component_key: "custom_spacer_" + spacerCounter,
            type: "spacer",
            lines: 1,
            default_lines: 1,
            removed: "N"
        }, state.components.length);

        if (insertIndex === -1) {
            activeComponents.push(spacerComponent);
        } else {
            activeComponents.splice(insertIndex + 1, 0, spacerComponent);
        }

        activeComponents.forEach(function (component, index) {
            component.sort_order = (index + 1) * 10;
            if (!Number.isFinite(Number(component.default_order))) {
                component.default_order = component.sort_order;
            }
        });

        spacerComponent.default_order = spacerComponent.sort_order;
        state.components.push(spacerComponent);
        syncHiddenFields();
        renderBuilder();
    }

    function renderBuilder() {
        var builderRows = document.getElementById("builderRows");
        var removedRows = document.getElementById("removedComponents");
        if (!builderRows || !removedRows) {
            return;
        }

        builderRows.innerHTML = "";
        removedRows.innerHTML = "";

        getActiveComponents().forEach(function (component) {
            var row = document.createElement("div");
            row.className = "customize-bot-row";
            row.draggable = editable;

            var handle = document.createElement("button");
            handle.type = "button";
            handle.className = "customize-bot-handle";
            handle.innerHTML = '<i class="fa-solid fa-grip-vertical"></i>';
            handle.disabled = !editable;
            row.appendChild(handle);

            if (component.type === "spacer") {
                row.appendChild(createSpacerControl(component));
            } else if (component.builder_mode === "readonly") {
                row.appendChild(createReadonlyLineControl(component));
            } else {
                row.appendChild(createTextControl(component));
            }

            var actions = document.createElement("div");
            actions.className = "customize-bot-row-actions";
            actions.appendChild(createIconButton("fa-solid fa-plus", "Add Spacer", function () {
                insertSpacerAfter(component.component_key);
            }));
            if (component.type !== "spacer" && component.builder_mode !== "readonly") {
                actions.appendChild(createIconButton("fa-regular fa-pen-to-square", "Edit", function () {
                    focusComponentInput(component.component_key);
                }));
            }
            actions.appendChild(createIconButton("fa-regular fa-trash-can", "Remove", function () {
                removeComponent(component.component_key);
            }));
            row.appendChild(actions);

            bindRowDragEvents(row, component.component_key);
            builderRows.appendChild(row);
        });

        if (getActiveComponents().length === 0) {
            var emptyState = document.createElement("div");
            emptyState.className = "text-muted small";
            emptyState.textContent = "No active components. Restore one from the right panel.";
            builderRows.appendChild(emptyState);
        }

        getRemovedComponents().forEach(function (component) {
            var removedItem = document.createElement("div");
            removedItem.className = "customize-bot-removed-item";

            var handle = document.createElement("button");
            handle.type = "button";
            handle.className = "customize-bot-handle";
            handle.disabled = true;
            handle.innerHTML = '<i class="fa-solid fa-grip-vertical"></i>';
            removedItem.appendChild(handle);

            var text = document.createElement("div");
            text.className = "customize-bot-removed-text";
            if (component.type === "spacer") {
                text.textContent = "Spacer (" + normalizeLines(component.lines) + " blank line" + (normalizeLines(component.lines) > 1 ? "s" : "") + ")";
            } else {
                text.textContent = getComponentTemplateDisplayText(component) || component.component_key;
            }
            removedItem.appendChild(text);

            var restoreButton = document.createElement("button");
            restoreButton.type = "button";
            restoreButton.className = "customize-bot-restore-btn";
            restoreButton.title = "Restore";
            restoreButton.innerHTML = '<i class="fa-solid fa-rotate-left"></i>';
            restoreButton.disabled = !editable;
            restoreButton.addEventListener("click", function () {
                restoreComponent(component.component_key);
            });
            removedItem.appendChild(restoreButton);

            removedRows.appendChild(removedItem);
        });

        if (getRemovedComponents().length === 0) {
            var removedEmptyState = document.createElement("div");
            removedEmptyState.className = "text-muted small";
            removedEmptyState.textContent = "No removed components.";
            removedRows.appendChild(removedEmptyState);
        }
    }

    function resetCurrentContext() {
        var contextConfig = getContextConfig(state.context);
        if (!contextConfig) {
            return;
        }

        state.components = normalizeComponents(deepClone(contextConfig.default_components || []));
        updateSpacerCounter();
        syncHiddenFields();
        renderBuilder();
    }

    function handleContextChange(event) {
        var nextContext = String(event.target.value || "");
        if (!getContextConfig(nextContext)) {
            return;
        }

        state.context = nextContext;
        resetCurrentContext();
    }

    function setErrorText(containerId, message) {
        var container = document.getElementById(containerId);
        if (!container) {
            return;
        }
        var span = container.querySelector("span") || container;
        span.textContent = message || "";
    }

    function bindEvents() {
        var contextSelect = document.getElementById("message_context");
        var resetButton = document.getElementById("resetTemplateBtn");
        var form = getForm();

        if (contextSelect && editable) {
            contextSelect.addEventListener("change", handleContextChange);
        }
        if (resetButton) {
            resetButton.addEventListener("click", resetCurrentContext);
        }
        if (form) {
            form.addEventListener("submit", function (event) {
                var submitter = event.submitter;
                if (submitter && submitter.value === "back") {
                    return;
                }

                syncHiddenFields();

                var templateName = document.getElementById("template_name");
                var contextSelectEl = document.getElementById("message_context");
                var templateBodyInput = document.getElementById("template_body");

                setErrorText("templateNameError", "");
                setErrorText("messageContextError", "");
                setErrorText("templateBodyError", "");
                var hasError = false;

                if (templateName && templateName.value.trim() === "") {
                    setErrorText("templateNameError", "Template Name is required.");
                    hasError = true;
                }
                if (contextSelectEl && !getContextConfig(contextSelectEl.value)) {
                    setErrorText("messageContextError", "Please select a valid Message Context.");
                    hasError = true;
                }
                if (templateBodyInput && templateBodyInput.value === "") {
                    setErrorText("templateBodyError", "Template Body cannot be empty.");
                    hasError = true;
                }

                if (hasError) {
                    event.preventDefault();
                }
            });
        }
    }

    function init() {
        ensureStateComponents();
        syncHiddenFields();
        renderBuilder();
        bindEvents();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
