(function () {
  "use strict";

  var config = window.taskBulkEditConfig || {};
  var state = {
    step: 1,
    parentId: 0,
    parent: null,
    items: [],
    selectedIds: [],
    operation: "",
    changes: { fields: {} },
  };

  function byId(id) {
    return document.getElementById(id);
  }

  function escapeHtml(value) {
    return String(value === null || value === undefined ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function displayValue(value, fallback) {
    var text = String(value === null || value === undefined ? "" : value).trim();
    return text === "" ? (fallback || "—") : text;
  }

  function showAlert(message, type) {
    var alert = byId("taskBulkEditAlert");
    if (!alert) return;
    alert.className = "task-bulk-edit-alert " + (type === "success" ? "is-success" : "is-error");
    alert.textContent = message || "An unexpected error occurred.";
    alert.classList.remove("d-none");
    alert.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  function clearAlert() {
    var alert = byId("taskBulkEditAlert");
    if (!alert) return;
    alert.textContent = "";
    alert.className = "task-bulk-edit-alert d-none";
  }

  function postAction(payload) {
    var body = new URLSearchParams();
    body.append("csrf_token", String(config.csrfToken || ""));
    Object.keys(payload || {}).forEach(function (key) {
      var value = payload[key];
      if (Array.isArray(value)) {
        value.forEach(function (entry) {
          body.append(key + "[]", String(entry));
        });
      } else {
        body.append(key, value === null || value === undefined ? "" : String(value));
      }
    });

    return fetch(String(config.ajaxUrl || "bulk_edit_task.php"), {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: body.toString(),
    }).then(function (response) {
      return response.json().catch(function () {
        return { ok: 0, message: "The server returned an invalid response." };
      });
    }).then(function (data) {
      if (!data || !data.ok) {
        throw new Error(data && data.message ? data.message : "The bulk operation could not be completed.");
      }
      return data;
    });
  }

  function getParentIdFromHash() {
    var match = String(window.location.hash || "").match(/^#parent-task-item-(\d+)$/i);
    return match ? Number(match[1]) : 0;
  }

  function updateUrlStep(step) {
    var url = new URL(window.location.href);
    url.searchParams.set("project_id", String(config.projectId || 0));
    url.searchParams.set("step", String(step));
    window.history.replaceState(null, "", url.pathname + "?" + url.searchParams.toString() + window.location.hash);
  }

  function renderItems() {
    var table = byId("taskBulkItemsTable");
    if (!table) return;
    var tbody = table.querySelector("tbody");
    if (!state.items.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="task-bulk-empty">This parent has no active child work items.</td></tr>';
      updateSelectionUi();
      return;
    }

    var selected = state.selectedIds;
    tbody.innerHTML = state.items.map(function (item) {
      var id = Number(item.id) || 0;
      var checked = selected.indexOf(id) !== -1;
      var assignee = item.assignee_name || "Unassigned";
      var reporter = item.reporter_name || "Unassigned";
      return '<tr>' +
        '<td class="task-bulk-check-cell"><input class="task-bulk-item-check" type="checkbox" value="' + id + '"' + (checked ? " checked" : "") + ' aria-label="Select ' + escapeHtml(item.work_item_key) + '"></td>' +
        '<td><a href="' + escapeHtml(String(config.siteUrl || "") + "/task/board.php?project_id=" + encodeURIComponent(config.projectId || 0) + "#task-item-" + id) + '">' + escapeHtml(item.work_item_key) + '</a></td>' +
        '<td class="task-bulk-summary-cell"><strong>' + escapeHtml(item.title || "") + '</strong><small>' + escapeHtml(item.work_type_name || "Task") + '</small></td>' +
        '<td>' + escapeHtml(assignee) + '</td>' +
        '<td>' + escapeHtml(reporter) + '</td>' +
        '<td><span class="task-bulk-priority priority-' + escapeHtml(String(item.priority || "Medium").toLowerCase()) + '">' + escapeHtml(item.priority || "Medium") + '</span></td>' +
        '<td>' + escapeHtml(item.status_name || "") + '</td>' +
        '</tr>';
    }).join("");
    updateSelectionUi();
  }

  function updateSelectionUi() {
    var count = state.selectedIds.length;
    var countNode = byId("taskBulkSelectionCount");
    if (countNode) countNode.textContent = count + " selected";
    var selectAll = byId("taskBulkSelectAll");
    if (selectAll) {
      selectAll.checked = state.items.length > 0 && state.items.every(function (item) {
        return state.selectedIds.indexOf(Number(item.id)) !== -1;
      });
      selectAll.indeterminate = !selectAll.checked && count > 0;
    }
    document.querySelectorAll(".task-bulk-item-check").forEach(function (checkbox) {
      checkbox.disabled = !checkbox.checked && count >= 1000;
    });
  }

  function renderParentSummary() {
    var summary = byId("taskBulkParentSummary");
    if (!summary || !state.parent) return;
    summary.innerHTML = "Children of <strong>" + escapeHtml(state.parent.work_item_key || "") + "</strong> " + escapeHtml(state.parent.title || "") + " (" + state.items.length + " available)";
  }

  function optionList(options, valueKey, labelBuilder, selectedValue) {
    return (options || []).map(function (option) {
      var value = String(option[valueKey] === undefined ? "" : option[valueKey]);
      var label = labelBuilder(option);
      var selected = Array.isArray(selectedValue)
        ? selectedValue.indexOf(Number(value)) !== -1
        : String(selectedValue === undefined || selectedValue === null ? "" : selectedValue) === value;
      return '<option value="' + escapeHtml(value) + '"' + (selected ? " selected" : "") + '>' + escapeHtml(label) + '</option>';
    }).join("");
  }

  function fieldRow(key, label, controlHtml, hint) {
    return '<div class="task-bulk-field-row" data-field-row="' + escapeHtml(key) + '">' +
      '<label class="task-bulk-field-toggle"><input type="checkbox" data-bulk-field="' + escapeHtml(key) + '"><span>' + escapeHtml(label) + '</span></label>' +
      '<div class="task-bulk-field-control">' + controlHtml + (hint ? '<small>' + escapeHtml(hint) + '</small>' : "") + '</div>' +
      '</div>';
  }

  function labelOperationOptions() {
    return '<option value="replace">Replace all with</option>' +
      '<option value="add">Add to existing</option>' +
      '<option value="clear">Clear field</option>' +
      '<option value="remove">Find and remove these</option>';
  }

  function labelEditor(key) {
    return '<div class="task-bulk-label-editor" data-label-editor="' + escapeHtml(key) + '">' +
      '<select class="form-select task-bulk-label-operation" data-bulk-value="' + escapeHtml(key + "_operation") + '">' + labelOperationOptions() + '</select>' +
      '<div class="task-bulk-label-picker">' +
        '<div class="task-bulk-label-chips" data-label-chips></div>' +
        '<div class="task-bulk-label-input-wrap"><input class="form-control" type="text" data-label-input data-bulk-label-control autocomplete="off" placeholder="Type to search or create a label"><i class="fa-solid fa-chevron-down task-bulk-label-caret" aria-hidden="true"></i></div>' +
        '<div class="task-bulk-label-suggestions" data-label-suggestions hidden></div>' +
      '</div>' +
    '</div>';
  }

  function labelOptionsForKey(key) {
    return key === "labels" ? (config.labels || []) : (config.statusLabels || []);
  }

  function labelEntryFromValue(key, value) {
    var id = 0;
    var name = "";
    if (value && typeof value === "object") {
      id = Number(value.id) || 0;
      name = String(value.name || "").trim();
    } else if (Number(value) > 0) {
      id = Number(value);
    } else {
      name = String(value || "").trim();
    }
    if (!name && id > 0) {
      var option = labelOptionsForKey(key).find(function (item) { return Number(item.id) === id; });
      name = option ? String(option.name || "") : "";
    }
    return { id: id, name: name };
  }

  function labelEditorEntries(editor) {
    return Array.prototype.map.call(editor.querySelectorAll("[data-label-chip]"), function (chip) {
      return { id: Number(chip.getAttribute("data-label-id")) || 0, name: chip.getAttribute("data-label-name") || "" };
    });
  }

  function initializeLabelEditor(editor) {
    if (!editor || editor.getAttribute("data-label-ready") === "1") return;
    editor.setAttribute("data-label-ready", "1");
    var key = editor.getAttribute("data-label-editor");
    var input = editor.querySelector("[data-label-input]");
    var chips = editor.querySelector("[data-label-chips]");
    var suggestions = editor.querySelector("[data-label-suggestions]");
    if (!input || !chips || !suggestions) return;

    function hasEntry(name) {
      var normalized = String(name || "").trim().toLowerCase();
      return labelEditorEntries(editor).some(function (entry) { return entry.name.toLowerCase() === normalized; });
    }

    function addEntry(value) {
      var entry = labelEntryFromValue(key, value);
      entry.name = entry.name.trim();
      if (!entry.name || hasEntry(entry.name)) return;
      var chip = document.createElement("span");
      chip.className = "task-bulk-label-chip";
      chip.setAttribute("data-label-chip", "1");
      chip.setAttribute("data-label-id", String(entry.id));
      chip.setAttribute("data-label-name", entry.name);
      chip.innerHTML = '<span>' + escapeHtml(entry.name) + '</span><button type="button" data-label-remove aria-label="Remove ' + escapeHtml(entry.name) + '">&times;</button>';
      chips.appendChild(chip);
      input.value = "";
      suggestions.hidden = true;
    }

    function renderSuggestions(showAll) {
      var query = String(input.value || "").trim().toLowerCase();
      var options = labelOptionsForKey(key).filter(function (option) {
        return (showAll || query === "" || String(option.name || "").toLowerCase().indexOf(query) !== -1) && !hasEntry(String(option.name || ""));
      }).slice(0, 100);
      var exact = options.some(function (option) { return String(option.name || "").toLowerCase() === query; });
      var html = options.map(function (option) {
        return '<button type="button" class="task-bulk-label-suggestion" data-label-suggestion-id="' + Number(option.id) + '" data-label-suggestion-name="' + escapeHtml(option.name || "") + '">' + escapeHtml(option.name || "") + '</button>';
      }).join("");
      if (query && !exact && !hasEntry(query)) {
        html = '<button type="button" class="task-bulk-label-suggestion is-new" data-label-suggestion-id="0" data-label-suggestion-name="' + escapeHtml(input.value.trim()) + '">' + escapeHtml(input.value.trim()) + ' <small>(New label)</small></button>' + html;
      }
      suggestions.innerHTML = html || '<span class="task-bulk-label-suggestion-empty">No suggestions.</span>';
      suggestions.hidden = false;
    }

    input.addEventListener("input", function () { renderSuggestions(false); });
    input.addEventListener("focus", function () { renderSuggestions(false); });
    input.addEventListener("keydown", function (event) {
      if (event.key === "ArrowDown") {
        event.preventDefault();
        renderSuggestions(true);
      } else if (event.key === "Enter" || event.key === ",") {
        event.preventDefault();
        addEntry(input.value.replace(/,$/, ""));
      } else if (event.key === "Backspace" && input.value === "") {
        var last = chips.lastElementChild;
        if (last) last.remove();
      }
    });
    input.addEventListener("blur", function () { window.setTimeout(function () { suggestions.hidden = true; }, 150); });
    suggestions.addEventListener("mousedown", function (event) {
      var option = event.target.closest("[data-label-suggestion-name]");
      if (!option) return;
      event.preventDefault();
      addEntry({ id: Number(option.getAttribute("data-label-suggestion-id")) || 0, name: option.getAttribute("data-label-suggestion-name") || "" });
    });
    chips.addEventListener("click", function (event) {
      var remove = event.target.closest("[data-label-remove]");
      if (remove) remove.closest("[data-label-chip]").remove();
    });
  }

  function restoreLabelEditor(editor, values) {
    if (!editor) return;
    var chips = editor.querySelector("[data-label-chips]");
    var input = editor.querySelector("[data-label-input]");
    if (!chips) return;
    chips.innerHTML = "";
    (Array.isArray(values) ? values : []).forEach(function (value) {
      var entry = labelEntryFromValue(editor.getAttribute("data-label-editor"), value);
      if (!entry.name) return;
      var chip = document.createElement("span");
      chip.className = "task-bulk-label-chip";
      chip.setAttribute("data-label-chip", "1");
      chip.setAttribute("data-label-id", String(entry.id));
      chip.setAttribute("data-label-name", entry.name);
      chip.innerHTML = '<span>' + escapeHtml(entry.name) + '</span><button type="button" data-label-remove aria-label="Remove ' + escapeHtml(entry.name) + '">&times;</button>';
      chips.appendChild(chip);
    });
    if (input) input.value = "";
  }

  function renderEditDetails() {
    var assigneeOptions = '<option value="0">Unassigned</option>' + optionList(config.assignees, "id", function (item) { return item.name; }, "");
    var reporterOptions = '<option value="0">Unassigned</option>' + optionList(config.assignees, "id", function (item) { return item.name; }, "");
    var priorityOptions = ["Highest", "High", "Medium", "Low", "Lowest"].map(function (value) {
      return '<option value="' + value + '">' + value + '</option>';
    }).join("");
    var estimateUnits = ["minutes", "hours", "days", "weeks"].map(function (value) {
      return '<option value="' + value + '">' + value + '</option>';
    }).join("");
    var times = '<option value="0">None</option>';
    for (var minute = 5; minute <= 45; minute += 5) times += '<option value="' + minute + '">' + minute + ' minutes</option>';
    return '<div class="task-bulk-fields">' +
      fieldRow("assignee", "Assignee", '<select class="form-select" data-bulk-value="assignee">' + assigneeOptions + '</select>') +
      fieldRow("reporter", "Reporter", '<select class="form-select" data-bulk-value="reporter">' + reporterOptions + '</select>') +
      fieldRow("priority", "Priority", '<select class="form-select" data-bulk-value="priority">' + priorityOptions + '</select>') +
      fieldRow("original_estimate", "Original Estimate", '<div class="task-bulk-inline-controls"><input class="form-control" type="number" min="0" step="1" value="0" data-bulk-value="original_estimate_value"><select class="form-select" data-bulk-value="original_estimate_unit">' + estimateUnits + '</select></div>', "The value replaces the existing estimate.") +
      fieldRow("labels", "Labels", labelEditor("labels"), "Begin typing to find or create labels, or press down to select a suggested label.") +
      fieldRow("task_status", "Task Status labels", labelEditor("task_status"), "Begin typing to find or create task status labels, or press down to select a suggestion.") +
      fieldRow("start_date", "Start date", '<input class="form-control" type="date" data-bulk-value="start_date">') +
      fieldRow("due_date", "Due date", '<input class="form-control" type="date" data-bulk-value="due_date">') +
      fieldRow("amendement_date", "Amendment date", '<input class="form-control" type="date" data-bulk-value="amendement_date">') +
      fieldRow("amendement_time", "Amendment time", '<select class="form-select" data-bulk-value="amendement_time">' + times + '</select>') +
      fieldRow("second_amendement_date", "Second amendment date", '<input class="form-control" type="date" data-bulk-value="second_amendement_date">') +
      fieldRow("second_amendement_time", "Second amendment time", '<select class="form-select" data-bulk-value="second_amendement_time">' + times + '</select>') +
      '</div>';
  }

  function renderMoveDetails() {
    var workTypes = optionList(config.workTypes, "id", function (item) { return item.name; }, "");
    var parents = '<option value="0">No parent</option>' + optionList(config.parentOptions, "id", function (item) { return item.work_item_key + " — " + item.title; }, "");
    return '<div class="task-bulk-fields">' +
      fieldRow("work_type", "Work Type", '<select class="form-select" data-bulk-value="work_type"><option value="0">Choose work type</option>' + workTypes + '</select>') +
      fieldRow("parent", "Parent", '<select class="form-select" data-bulk-value="parent">' + parents + '</select>', "Only an active Epic in this project can be selected.") +
      '</div>';
  }

  function transitionStatusPill(column) {
    var color = String(column && column.color ? column.color : "").trim();
    if (!/^#[0-9a-f]{6}$/i.test(color)) color = "#44546f";
    return '<span class="task-bulk-transition-status-pill" style="--task-bulk-status-color:' + color + '">' + escapeHtml(column && column.name ? column.name : "") + '</span>';
  }

  function affectedIssuesHtml(targetColumnId) {
    var affected = state.items.filter(function (item) {
      return state.selectedIds.indexOf(Number(item.id)) !== -1 && Number(item.column_id) !== Number(targetColumnId);
    });
    if (!affected.length) return '<span class="task-bulk-transition-none">No affected issues</span>';
    var visible = affected.slice(0, 8);
    var text = visible.map(function (item) { return escapeHtml(item.work_item_key || ""); }).join(", ");
    if (affected.length > visible.length) {
      text += " ... (" + affected.length + " affected issues)";
    } else {
      text += " (" + affected.length + " affected issues)";
    }
    return text;
  }

  function renderTransitionDetails() {
    var sourceColumns = Array.isArray(config.allColumns) ? config.allColumns : [];
    var workflowColumns = Array.isArray(config.workflowColumns) ? config.workflowColumns : (config.columns || []);
    var sourcePills = sourceColumns.map(transitionStatusPill).join("");
    var rows = workflowColumns.map(function (target, index) {
      return '<div class="task-bulk-transition-row" data-field-row="transition">' +
        '<label class="task-bulk-transition-action"><input type="radio" name="bulk_transition_status" data-bulk-field="transition" data-bulk-value="transition" value="' + Number(target.id) + '"' + (index === 0 ? ' checked' : '') + '><span>' + escapeHtml(target.name || "") + '</span></label>' +
        '<div class="task-bulk-transition-map"><div class="task-bulk-transition-sources">' + sourcePills + '</div><span class="task-bulk-transition-arrow">&#8594;</span>' + transitionStatusPill(target) + '</div>' +
        '<div class="task-bulk-transition-affected">' + affectedIssuesHtml(Number(target.id)) + '</div>' +
      '</div>';
    }).join("");
    return '<div class="task-bulk-transition-table">' +
      '<div class="task-bulk-transition-head"><div>Available Workflow Actions</div><div>Status Transition</div><div>Affected Issues</div></div>' +
      (rows || '<div class="task-bulk-transition-empty">No workflow actions are available.</div>') +
      '</div>';
  }

  function renderDetails() {
    var details = byId("taskBulkOperationDetails");
    var intro = byId("taskBulkDetailsIntro");
    if (!details) return;
    if (state.operation === "edit") {
      intro.textContent = "Tick each field you want to replace. Unticked fields remain unchanged.";
      details.innerHTML = renderEditDetails();
    } else if (state.operation === "move") {
      intro.textContent = "Tick the destination fields to change. The selected work type and parent are validated again when confirmed.";
      details.innerHTML = renderMoveDetails();
    } else if (state.operation === "transition") {
      intro.textContent = "Select the workflow transition to execute on the selected child work items.";
      details.innerHTML = renderTransitionDetails();
    } else {
      intro.textContent = "The selected work items will be soft-deleted.";
      details.innerHTML = '<div class="task-bulk-delete-warning"><i class="fa-solid fa-triangle-exclamation"></i><strong>Delete selected work items</strong><p>The work items will be marked inactive using the existing soft-delete behavior. This can only be completed with delete permission.</p></div>';
    }

    details.querySelectorAll("[data-label-editor]").forEach(initializeLabelEditor);

    details.querySelectorAll("[data-bulk-field]").forEach(function (checkbox) {
      if (checkbox.type === "radio") {
        checkbox.addEventListener("change", function () {
          details.querySelectorAll('[data-bulk-field="transition"]').forEach(function (radio) {
            var row = radio.closest("[data-field-row]");
            if (row) row.classList.toggle("is-enabled", radio.checked);
          });
        });
        return;
      }
      checkbox.addEventListener("change", function () {
        var row = checkbox.closest("[data-field-row]");
        if (row) row.classList.toggle("is-enabled", checkbox.checked);
        if (row) row.querySelectorAll("[data-bulk-value], [data-bulk-label-control]").forEach(function (control) { control.disabled = !checkbox.checked; });
      });
      var row = checkbox.closest("[data-field-row]");
      if (row) row.querySelectorAll("[data-bulk-value], [data-bulk-label-control]").forEach(function (control) { control.disabled = true; });
    });

    var savedFields = state.changes && state.changes.fields ? state.changes.fields : {};
    details.querySelectorAll("[data-bulk-field]").forEach(function (checkbox) {
      var key = checkbox.getAttribute("data-bulk-field");
      var saved = savedFields[key];
      if (!saved || !saved.enabled) return;
      if (checkbox.type === "radio") {
        checkbox.checked = String(checkbox.value) === String(saved.value);
        var radioRow = checkbox.closest("[data-field-row]");
        if (radioRow) radioRow.classList.toggle("is-enabled", checkbox.checked);
        return;
      }
      checkbox.checked = true;
      var row = checkbox.closest("[data-field-row]");
      if (row) {
        row.classList.add("is-enabled");
        row.querySelectorAll("[data-bulk-value], [data-bulk-label-control]").forEach(function (control) {
          var valueKey = control.getAttribute("data-bulk-value");
          var savedValue = savedFields[valueKey] && savedFields[valueKey].value !== undefined
            ? savedFields[valueKey].value
            : (valueKey === key ? saved.value : "");
          if (control.type === "checkbox") {
            var checkboxValues = Array.isArray(savedValue) ? savedValue.map(String) : [];
            control.checked = checkboxValues.indexOf(String(control.value)) !== -1;
          } else if (control.multiple) {
            var values = Array.isArray(savedValue) ? savedValue.map(String) : [];
            Array.prototype.forEach.call(control.options, function (option) {
              option.selected = values.indexOf(String(option.value)) !== -1;
            });
          } else if (savedValue !== undefined && savedValue !== null) {
            control.value = String(savedValue);
          }
          control.disabled = false;
        });
        if (key === "labels" || key === "task_status") {
          restoreLabelEditor(row.querySelector("[data-label-editor]"), saved.value);
        }
      }
    });
  }

  function collectChanges() {
    var fields = {};
    document.querySelectorAll("[data-bulk-field]").forEach(function (checkbox) {
      if (checkbox.type === "radio" && !checkbox.checked) return;
      var key = checkbox.getAttribute("data-bulk-field");
      var row = checkbox.closest("[data-field-row]");
      if (key === "labels" || key === "task_status") {
        var editor = row ? row.querySelector("[data-label-editor]") : null;
        var operation = editor ? editor.querySelector("[data-bulk-value$='_operation']") : null;
        fields[key] = {
          enabled: checkbox.checked ? 1 : 0,
          value: editor ? labelEditorEntries(editor) : [],
        };
        fields[key + "_operation"] = {
          enabled: checkbox.checked ? 1 : 0,
          value: operation && operation.value ? operation.value : "replace",
        };
        return;
      }
      var values = {};
      if (row) {
        row.querySelectorAll("[data-bulk-value]").forEach(function (control) {
          var valueKey = control.getAttribute("data-bulk-value");
          if (control.type === "checkbox") {
            if (!Array.isArray(values[valueKey])) values[valueKey] = [];
            if (control.checked) values[valueKey].push(Number(control.value) || 0);
          } else if (control.type === "radio") {
            if (control.checked) values[valueKey] = control.value;
          } else {
            values[valueKey] = control.multiple
              ? Array.prototype.map.call(control.selectedOptions || [], function (option) { return Number(option.value) || 0; }).filter(function (value) { return value > 0; })
              : control.value;
          }
        });
      }
      fields[key] = {
        enabled: checkbox.checked ? 1 : 0,
        value: values[key] !== undefined ? values[key] : (key === "original_estimate" ? values.original_estimate_value : ""),
      };
      Object.keys(values).forEach(function (valueKey) {
        if (valueKey !== key) fields[valueKey] = { enabled: checkbox.checked ? 1 : 0, value: values[valueKey] };
      });
    });
    return { fields: fields };
  }

  function fieldDisplay(key, field) {
    var value = field && field.value !== undefined ? field.value : "";
    if (Array.isArray(value)) {
      var source = key === "labels" ? config.labels : config.statusLabels;
      return value.map(function (entry) {
        var id = entry && typeof entry === "object" ? entry.id : entry;
        var item = (source || []).find(function (option) { return Number(option.id) === Number(id); });
        return entry && typeof entry === "object" && entry.name ? entry.name : (item ? item.name : String(id));
      }).join(", ") || "None";
    }
    if (key === "labels" || key === "task_status") {
      return displayValue(value, "None");
    }
    if (key === "assignee" || key === "reporter") {
      var user = (config.assignees || []).find(function (entry) { return Number(entry.id) === Number(value); });
      return user ? user.name : "Unassigned";
    }
    if (key === "work_type") {
      var type = (config.workTypes || []).find(function (entry) { return Number(entry.id) === Number(value); });
      return type ? type.name : value;
    }
    if (key === "parent") {
      var parent = (config.parentOptions || []).find(function (entry) { return Number(entry.id) === Number(value); });
      return parent ? parent.work_item_key + " — " + parent.title : "No parent";
    }
    if (key === "transition") {
      var column = (config.workflowColumns || config.columns || []).find(function (entry) { return Number(entry.id) === Number(value); });
      return column ? column.name : value;
    }
    return displayValue(value, "None");
  }

  function renderSummary() {
    var summary = byId("taskBulkSummary");
    if (!summary) return;
    var rows = [];
    var fields = state.changes.fields || {};
    var labels = {
      assignee: "Assignee", reporter: "Reporter", priority: "Priority", original_estimate: "Original Estimate",
      labels: "Labels", task_status: "Task Status labels", start_date: "Start date", due_date: "Due date",
      amendement_date: "Amendment date", amendement_time: "Amendment time", second_amendement_date: "Second amendment date",
      second_amendement_time: "Second amendment time", work_type: "Work Type", parent: "Parent", transition: "Target status",
    };
    Object.keys(labels).forEach(function (key) {
      if (!fields[key] || !fields[key].enabled) return;
      var value = fields[key];
      if (key === "original_estimate") {
        var unit = fields.original_estimate_unit && fields.original_estimate_unit.value ? fields.original_estimate_unit.value : "minutes";
        var estimateValue = fields.original_estimate_value && fields.original_estimate_value.value !== undefined
          ? fields.original_estimate_value.value
          : value.value;
        rows.push([labels[key], "Replace with", displayValue(estimateValue, "0") + " " + unit]);
      } else if (key !== "original_estimate_unit") {
        var action = "Replace with";
        if (key === "labels" || key === "task_status") {
          var operationField = fields[key + "_operation"];
          var operationNames = { replace: "Replace all with", add: "Add to existing", clear: "Clear field", remove: "Find and remove these" };
          action = operationNames[operationField && operationField.value] || "Replace all with";
        }
        rows.push([labels[key], action, fieldDisplay(key, value)]);
      }
    });
    if (state.operation === "delete") rows.push(["Work items", "Soft delete", state.selectedIds.length + " selected child work item(s)"]);
    var fieldsHtml = rows.length
      ? '<table class="table task-bulk-summary-table"><thead><tr><th>Field</th><th>Action</th><th>Value</th></tr></thead><tbody>' + rows.map(function (row) { return '<tr><td>' + escapeHtml(row[0]) + '</td><td>' + escapeHtml(row[1]) + '</td><td>' + escapeHtml(row[2]) + '</td></tr>'; }).join("") + '</tbody></table>'
      : '<div class="task-bulk-empty">No changes selected.</div>';
    var selectedItems = state.items.filter(function (item) { return state.selectedIds.indexOf(Number(item.id)) !== -1; });
    summary.innerHTML = '<div class="task-bulk-summary-title">Changes for ' + state.selectedIds.length + ' selected child work item(s)</div>' + fieldsHtml +
      '<h3>Selected work items</h3><div class="task-bulk-table-wrap"><table class="table task-bulk-table"><thead><tr><th>Key</th><th>Summary</th><th>Assignee</th><th>Priority</th><th>Status</th></tr></thead><tbody>' +
      selectedItems.map(function (item) { return '<tr><td>' + escapeHtml(item.work_item_key) + '</td><td>' + escapeHtml(item.title) + '</td><td>' + escapeHtml(item.assignee_name || "Unassigned") + '</td><td>' + escapeHtml(item.priority) + '</td><td>' + escapeHtml(item.status_name) + '</td></tr>'; }).join("") +
      '</tbody></table></div>';
  }

  function validateDetails() {
    if (state.operation === "delete") return true;
    var enabled = document.querySelectorAll("[data-bulk-field]:checked");
    if (!enabled.length) {
      showAlert(state.operation === "transition" ? "Choose a target status." : "Select at least one field to change.", "error");
      return false;
    }
    if (state.operation === "move" && !Array.prototype.some.call(enabled, function (field) { return field.getAttribute("data-bulk-field") === "work_type" || field.getAttribute("data-bulk-field") === "parent"; })) {
      showAlert("Select a work type or parent destination.", "error");
      return false;
    }
    if (state.operation === "transition") {
      var transition = document.querySelector('[data-bulk-value="transition"]:checked');
      if (!transition || Number(transition.value) <= 0) {
        showAlert("Choose a target status.", "error");
        return false;
      }
    }
    if (state.operation === "move") {
      var typeField = document.querySelector('[data-bulk-field="work_type"]:checked');
      var parentField = document.querySelector('[data-bulk-field="parent"]:checked');
      if (typeField && Number(document.querySelector('[data-bulk-value="work_type"]').value) <= 0) {
        showAlert("Choose a work type.", "error");
        return false;
      }
      if (parentField && document.querySelector('[data-bulk-value="parent"]') === null) {
        showAlert("Choose a parent destination.", "error");
        return false;
      }
    }
    return true;
  }

  function showStep(step) {
    state.step = step;
    document.querySelectorAll("[data-bulk-step]").forEach(function (panel) {
      panel.classList.toggle("d-none", Number(panel.getAttribute("data-bulk-step")) !== step);
    });
    document.querySelectorAll("[data-bulk-step-link]").forEach(function (link) {
      var linkStep = Number(link.getAttribute("data-bulk-step-link"));
      link.classList.toggle("is-active", linkStep === step);
      link.classList.toggle("is-complete", linkStep < step);
    });
    updateUrlStep(step);
    clearAlert();
    if (step === 3) renderDetails();
    if (step === 4) renderSummary();
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function loadItems() {
    state.parentId = getParentIdFromHash();
    if (state.parentId <= 0) {
      showAlert("The parent work item could not be identified from the page URL.", "error");
      return;
    }
    postAction({ bulk_action: "load", project_id: config.projectId, parent_item_id: state.parentId })
      .then(function (data) {
        state.parent = data.parent || null;
        state.items = Array.isArray(data.items) ? data.items : [];
        renderParentSummary();
        renderItems();
      })
      .catch(function (error) { showAlert(error.message, "error"); });
  }

  function applyChanges() {
    var button = byId("taskBulkConfirmBtn");
    if (!button || button.disabled) return;
    button.disabled = true;
    button.textContent = "Applying...";
      postAction({
      bulk_action: "apply",
      project_id: config.projectId,
      parent_item_id: state.parentId,
      selected_item_ids: state.selectedIds,
      operation: state.operation,
      changes_json: JSON.stringify(state.changes),
    }).then(function () {
      var returnUrl = String(config.siteUrl || "") + "/task/board.php?project_id=" + encodeURIComponent(config.projectId || 0) + "#task-item-" + encodeURIComponent(state.parentId);
      clearAlert();
      if (typeof window.confirmationDialog === "function") {
        window.confirmationDialog("", "", "Child Work Items", "", returnUrl, "E");
      } else {
        showAlert("Bulk changes applied successfully. Returning to the board...", "success");
        window.setTimeout(function () { window.location.href = returnUrl; }, 700);
      }
    }).catch(function (error) {
      button.disabled = false;
      button.textContent = "Confirm";
      showAlert(error.message, "error");
    });
  }

  function bindEvents() {
    var table = byId("taskBulkItemsTable");
    if (table) {
      table.addEventListener("change", function (event) {
        if (!event.target.classList.contains("task-bulk-item-check")) return;
        var id = Number(event.target.value) || 0;
        if (event.target.checked) {
          if (state.selectedIds.length >= 1000) {
            event.target.checked = false;
            showAlert("Bulk changes are limited to 1,000 work items.", "error");
            return;
          }
          if (state.selectedIds.indexOf(id) === -1) state.selectedIds.push(id);
        } else {
          state.selectedIds = state.selectedIds.filter(function (selectedId) { return selectedId !== id; });
        }
        updateSelectionUi();
      });
    }
    var selectAll = byId("taskBulkSelectAll");
    if (selectAll) selectAll.addEventListener("change", function () {
      if (selectAll.checked) state.selectedIds = state.items.slice(0, 1000).map(function (item) { return Number(item.id); });
      else state.selectedIds = [];
      renderItems();
    });
    document.querySelectorAll('[data-bulk-next="1"]').forEach(function (button) { button.addEventListener("click", function () {
      if (!state.selectedIds.length) { showAlert("Select at least one child work item.", "error"); return; }
      showStep(2);
    }); });
    document.querySelectorAll('[data-bulk-next="2"]').forEach(function (button) { button.addEventListener("click", function () {
      var selected = document.querySelector('input[name="bulk_operation"]:checked');
      if (!selected) { showAlert("Choose one bulk operation.", "error"); return; }
      state.operation = selected.value;
      showStep(3);
    }); });
    document.querySelectorAll('[data-bulk-next="3"]').forEach(function (button) { button.addEventListener("click", function () {
      if (!validateDetails()) return;
      state.changes = collectChanges();
      showStep(4);
    }); });
    document.querySelectorAll("[data-bulk-back]").forEach(function (button) { button.addEventListener("click", function () {
      showStep(Math.max(1, Number(button.getAttribute("data-bulk-back")) - 1));
    }); });
    document.querySelectorAll("[data-bulk-step-link]").forEach(function (link) { link.addEventListener("click", function () {
      var target = Number(link.getAttribute("data-bulk-step-link"));
      if (target < state.step) showStep(target);
    }); });
    var confirmButton = byId("taskBulkConfirmBtn");
    if (confirmButton) confirmButton.addEventListener("click", applyChanges);
  }

  document.addEventListener("DOMContentLoaded", function () {
    bindEvents();
    loadItems();
    var requestedStep = Number(new URL(window.location.href).searchParams.get("step")) || 1;
    if (requestedStep > 1 && requestedStep <= 4) showStep(1);
  });
})();
