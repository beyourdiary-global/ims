/**
 * Task Sheets – Jira-style spreadsheet view
 * Depends on: jQuery, window.sheetsConfig (emitted by sheets.php)
 */
(function ($) {
  "use strict";

  /* ───── config from PHP ───── */
  var cfg = window.sheetsConfig || {};
  var ajaxUrl = cfg.ajaxUrl || "../task/board.php";
  var csrfToken = String(cfg.csrfToken || "");
  var canEdit = !!cfg.canEdit;
  var workTypes = cfg.workTypes || [];
  var assignees = cfg.assignees || [];
  var labels = cfg.labels || [];
  var statusLabels = cfg.statusLabels || [];
  var columns = cfg.columns || [];
  var isProjectOwner = !!cfg.isProjectOwner;
  var columnPermissions =
    cfg.columnPermissions && typeof cfg.columnPermissions === "object"
      ? cfg.columnPermissions
      : {};
  var projectKey = cfg.projectKey || {};
  var currentProjectId = Number(cfg.currentProjectId || 0);
  var sheetsDataAjaxUrl =
    cfg.sheetsDataAjaxUrl || cfg.sheetsColumnAjaxUrl || "sheets.php";
  var prefUserId = String(cfg.currentUserId || "").trim();
  var viewPrefCookieName =
    "task_sheets_view_pref_v1" +
    (prefUserId ? "_u" + prefUserId : "") +
    "_p" +
    String(currentProjectId > 0 ? currentProjectId : 0);

  /* ───── state ───── */
  var allItems = cfg.items || [];
  var displayItems = [];
  var selectedItemIds = [];
  var sortCol = "";
  var sortDir = ""; // '' | 'asc' | 'desc'
  var activeFilters = {}; // colKey -> { type, values/text/from/to ... }
  var groupByCol = ""; // '' | column key
  var collapsedGroups = {};
  var globalSearch = "";
  var showSummaryRow = false;
  var toolbarAssigneeFilter = ""; // '' = all, '__unassigned__' = unassigned, or user id string
  var columnWidths = {}; // colKey -> pixel width
  var activeColumnResize = null;

  var DEFAULT_COLUMN_WIDTHS = {
    work_type: 80,
    work_item_key: 100,
    title: 280,
    description: 200,
    board_status: 150,
    original_estimate: 120,
    task_status: 130,
    parent_display: 120,
    assignee_name: 150,
    reporter_name: 150,
    priority: 100,
    labels: 140,
    time_tracking: 130,
    start_date: 120,
    due_date: 120,
    amendement_date: 120,
    amendement_time_minutes: 110,
    second_amendement_date: 120,
    second_amendement_time_minutes: 110,
  };

  var MIN_COLUMN_WIDTH = 46;

  function normalizePermissionFieldKey(fieldKey) {
    var key = String(fieldKey || "")
      .trim()
      .toLowerCase();
    if (key === "parent") return "parent_display";
    if (key === "assignee") return "assignee_name";
    if (key === "reporter") return "reporter_name";
    return key;
  }

  function hasAnyColumnPermission(fieldKey) {
    if (isProjectOwner) {
      return true;
    }
    var row = columnPermissions[normalizePermissionFieldKey(fieldKey)];
    return !!(
      row &&
      (Number(row.add || 0) > 0 ||
        Number(row.edit || 0) > 0 ||
        Number(row.delete || 0) > 0)
    );
  }

  function isColumnPermissionGuarded(fieldKey) {
    switch (normalizePermissionFieldKey(fieldKey)) {
      case "original_estimate":
      case "task_status":
      case "parent_display":
      case "assignee_name":
      case "reporter_name":
      case "priority":
      case "labels":
      case "start_date":
      case "due_date":
      case "amendement_date":
      case "amendement_time_minutes":
      case "second_amendement_date":
      case "second_amendement_time_minutes":
        return true;
      default:
        return false;
    }
  }

  function hasColumnActionPermission(fieldKey, action) {
    if (isProjectOwner) {
      return true;
    }
    var row = columnPermissions[normalizePermissionFieldKey(fieldKey)];
    return !!(row && Number(row[action] || 0) > 0);
  }

  function isFieldValueEmpty(fieldKey, value) {
    var key = normalizePermissionFieldKey(fieldKey);
    if (Array.isArray(value)) {
      return value.length === 0;
    }
    switch (key) {
      case "original_estimate":
      case "amendement_time_minutes":
      case "second_amendement_time_minutes":
        return Number(value || 0) <= 0;
      case "task_status":
        return !String(value || "")
          .split(",")
          .map(function (x) {
            return parseInt(x, 10);
          })
          .filter(function (x) {
            return x > 0;
          }).length;
      case "parent_display":
      case "assignee_name":
      case "reporter_name":
        return Number(value || 0) <= 0;
      case "priority":
        return !value || String(value).toLowerCase() === "none";
      case "labels":
        return !Array.isArray(value) || value.length === 0;
      default:
        return !value;
    }
  }

  function getCurrentFieldValue(item, fieldKey) {
    switch (normalizePermissionFieldKey(fieldKey)) {
      case "original_estimate":
        return Number(item.original_estimate_value || 0);
      case "task_status":
        return item.task_status || "";
      case "parent_display":
        return Number(item.parent_item_id || 0);
      case "assignee_name":
        return Number(item.assignee_user_id || 0);
      case "reporter_name":
        return Number(item.reporter_user_id || 0);
      case "priority":
        return item.priority || "";
      case "labels":
        return Array.isArray(item.labels) ? item.labels : [];
      case "start_date":
      case "due_date":
      case "amendement_date":
      case "second_amendement_date":
        return item[fieldKey] || "";
      case "amendement_time_minutes":
      case "second_amendement_time_minutes":
        return Number(item[fieldKey] || 0);
      default:
        return item[fieldKey];
    }
  }

  function inferColumnPermissionAction(item, fieldKey, nextValue) {
    var currentEmpty = isFieldValueEmpty(
      fieldKey,
      getCurrentFieldValue(item, fieldKey),
    );
    if (nextValue === undefined) {
      return currentEmpty ? "add" : "edit";
    }
    var nextEmpty = isFieldValueEmpty(fieldKey, nextValue);
    if (currentEmpty && !nextEmpty) {
      return "add";
    }
    if (!currentEmpty && nextEmpty) {
      return "delete";
    }
    return "edit";
  }

  function getColumnPermissionDeniedMessage(action) {
    if (action === "add") {
      return "Cell cannot add - You don't have access to add data on this field";
    }
    if (action === "delete") {
      return "Cell cannot delete - You don't have access to delete data on this field";
    }
    return "Cell uneditable - You don't have access to edit data on this field";
  }

  function showColumnPermissionDenied(item, fieldKey, nextValue) {
    var action = inferColumnPermissionAction(item, fieldKey, nextValue);
    showToast(getColumnPermissionDeniedMessage(action));
  }

  function ensureColumnActionAllowed(item, fieldKey, nextValue) {
    if (!isColumnPermissionGuarded(fieldKey)) {
      return true;
    }
    var action = inferColumnPermissionAction(item, fieldKey, nextValue);
    if (hasColumnActionPermission(fieldKey, action)) {
      return true;
    }
    showToast(getColumnPermissionDeniedMessage(action));
    return false;
  }

  function canEditCellByPermission(item, colKey) {
    if (!canEdit) {
      return false;
    }

    switch (String(colKey || "")) {
      case "work_type":
        return Array.isArray(workTypes) && workTypes.length > 0;
      case "board_status":
        return Array.isArray(columns) && columns.length > 0;
      case "original_estimate":
      case "task_status":
      case "parent_display":
      case "assignee_name":
      case "reporter_name":
      case "priority":
      case "labels":
      case "start_date":
      case "due_date":
      case "amendement_date":
      case "amendement_time_minutes":
      case "second_amendement_date":
      case "second_amendement_time_minutes":
        return hasAnyColumnPermission(colKey);
      default:
        return true;
    }
  }

  function setCookie(name, value, days) {
    var expires = "";
    if (Number(days || 0) > 0) {
      var date = new Date();
      date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
      expires = "; expires=" + date.toUTCString();
    }
    document.cookie =
      name +
      "=" +
      encodeURIComponent(String(value || "")) +
      expires +
      "; path=/; SameSite=Lax";
  }

  function getCookie(name) {
    var key = name + "=";
    var parts = document.cookie ? document.cookie.split(";") : [];
    for (var i = 0; i < parts.length; i++) {
      var c = String(parts[i] || "").trim();
      if (c.indexOf(key) === 0) {
        return decodeURIComponent(c.substring(key.length));
      }
    }
    return "";
  }

  function saveViewPrefs() {
    var payload = {
      sortCol: sortCol,
      sortDir: sortDir,
      activeFilters: activeFilters,
      groupByCol: groupByCol,
      collapsedGroups: collapsedGroups,
      showSummaryRow: !!showSummaryRow,
      toolbarAssigneeFilter: toolbarAssigneeFilter,
      columnWidths: columnWidths,
    };
    setCookie(viewPrefCookieName, JSON.stringify(payload), 180);
  }

  function loadViewPrefs() {
    var raw = getCookie(viewPrefCookieName);
    if (!raw) {
      return;
    }
    try {
      var saved = JSON.parse(raw);
      if (!saved || typeof saved !== "object") {
        return;
      }
      sortCol = typeof saved.sortCol === "string" ? saved.sortCol : "";
      sortDir =
        saved.sortDir === "asc" || saved.sortDir === "desc"
          ? saved.sortDir
          : "";
      activeFilters =
        saved.activeFilters && typeof saved.activeFilters === "object"
          ? saved.activeFilters
          : {};
      groupByCol = typeof saved.groupByCol === "string" ? saved.groupByCol : "";
      collapsedGroups =
        saved.collapsedGroups && typeof saved.collapsedGroups === "object"
          ? saved.collapsedGroups
          : {};
      showSummaryRow = !!saved.showSummaryRow;
      toolbarAssigneeFilter =
        typeof saved.toolbarAssigneeFilter === "string"
          ? saved.toolbarAssigneeFilter
          : "";
      columnWidths =
        saved.columnWidths && typeof saved.columnWidths === "object"
          ? saved.columnWidths
          : {};
    } catch (e) {
      sortCol = "";
      sortDir = "";
      activeFilters = {};
      groupByCol = "";
      collapsedGroups = {};
      showSummaryRow = false;
      toolbarAssigneeFilter = "";
      columnWidths = {};
    }
  }

  function getColumnWidth(colKey) {
    var key = String(colKey || "");
    var stored = Number(columnWidths[key] || 0);
    if (stored > 0) {
      return Math.max(MIN_COLUMN_WIDTH, Math.round(stored));
    }
    return Number(DEFAULT_COLUMN_WIDTHS[key] || 140);
  }

  /* ───── master column definitions ───── */
  var ALL_COLUMN_DEFS = [
    {
      key: "work_type",
      label: "Type",
      icon: "fa-solid fa-table-cells",
      css: "sheets-col-type",
      filterType: "option",
      editable: "work_type",
    },
    {
      key: "work_item_key",
      label: "Key",
      icon: "fa-solid fa-key",
      css: "sheets-col-key",
      filterType: "option",
      editable: false,
    },
    {
      key: "title",
      label: "Summary",
      icon: "fa-solid fa-align-left",
      css: "sheets-col-summary",
      filterType: "text",
      editable: "text",
    },
    {
      key: "description",
      label: "Description",
      icon: "fa-solid fa-bars",
      css: "sheets-col-description",
      filterType: "text",
      editable: "popup",
    },
    {
      key: "board_status",
      label: "Status",
      icon: "fa-solid fa-columns",
      css: "sheets-col-board-status",
      filterType: "option",
      editable: "board_status",
    },
    {
      key: "original_estimate",
      label: "Original Estimate",
      icon: "fa-solid fa-clock",
      css: "sheets-col-estimate",
      filterType: "time",
      editable: "text",
    },
    {
      key: "task_status",
      label: "Task Status",
      icon: "fa-solid fa-circle-check",
      css: "sheets-col-status",
      filterType: "option",
      editable: "status",
    },
    {
      key: "parent_display",
      label: "Parent",
      icon: "fa-solid fa-sitemap",
      css: "sheets-col-parent",
      filterType: "option",
      editable: "parent",
    },
    {
      key: "assignee_name",
      label: "Assignee",
      icon: "fa-solid fa-user",
      css: "sheets-col-assignee",
      filterType: "option",
      editable: "user_assignee",
    },
    {
      key: "reporter_name",
      label: "Reporter",
      icon: "fa-solid fa-user-pen",
      css: "sheets-col-reporter",
      filterType: "option",
      editable: "user_reporter",
    },
    {
      key: "priority",
      label: "Priority",
      icon: "fa-solid fa-list-ol",
      css: "sheets-col-priority",
      filterType: "option",
      editable: "priority",
    },
    {
      key: "labels",
      label: "Labels",
      icon: "fa-solid fa-tags",
      css: "sheets-col-labels",
      filterType: "option",
      editable: "labels",
    },
    {
      key: "time_tracking",
      label: "Time Tracking",
      icon: "fa-solid fa-stopwatch",
      css: "sheets-col-time-tracking",
      filterType: "time",
      editable: false,
    },
    {
      key: "start_date",
      label: "Start Date",
      icon: "fa-solid fa-calendar",
      css: "sheets-col-date",
      filterType: "date",
      editable: "date",
    },
    {
      key: "due_date",
      label: "Due Date",
      icon: "fa-solid fa-calendar-check",
      css: "sheets-col-date",
      filterType: "date",
      editable: "date",
    },
    {
      key: "amendement_date",
      label: "Amendement Date",
      icon: "fa-solid fa-calendar-days",
      css: "sheets-col-date",
      filterType: "date",
      editable: "date",
    },
    {
      key: "amendement_time_minutes",
      label: "Amendement Time",
      icon: "fa-solid fa-clock",
      css: "sheets-col-amen-time",
      filterType: "option",
      editable: "amen_time",
    },
    {
      key: "second_amendement_date",
      label: "Second Amen-Date",
      icon: "fa-solid fa-calendar-days",
      css: "sheets-col-date",
      filterType: "date",
      editable: "date",
    },
    {
      key: "second_amendement_time_minutes",
      label: "Second Amen-Time",
      icon: "fa-solid fa-clock",
      css: "sheets-col-amen-time",
      filterType: "option",
      editable: "amen_time",
    },
  ];

  /* active COLUMNS – loaded from DB or default */
  var COLUMNS = [];

  function getDefaultColumns() {
    return ALL_COLUMN_DEFS.map(function (c) {
      return $.extend({}, c);
    });
  }

  function loadColumnsFromConfig() {
    var saved = cfg.sheetsColumns || [];
    if (!saved.length) {
      COLUMNS = getDefaultColumns();
      return;
    }
    COLUMNS = [];
    saved.forEach(function (sc) {
      var def = ALL_COLUMN_DEFS.find(function (d) {
        return d.key === sc.column_key;
      });
      if (def) {
        var col = $.extend({}, def);
        col._dbId = sc.id || 0;
        COLUMNS.push(col);
      }
    });
    if (!COLUMNS.length) COLUMNS = getDefaultColumns();
  }

  var PRIORITIES = ["Highest", "High", "Medium", "Low", "Lowest"];
  var AMEN_TIME_OPTIONS = [5, 10, 15, 20, 25, 30, 35, 40, 45];

  /* ───── helpers ───── */

  function esc(s) {
    if (s === null || s === undefined) return "";
    var d = document.createElement("div");
    d.appendChild(document.createTextNode(String(s)));
    return d.innerHTML;
  }

  function buildWorkItemKey(id) {
    var pk = projectKey.project_key || "ITEM";
    return pk + "-" + id;
  }

  function getWorkType(id) {
    for (var i = 0; i < workTypes.length; i++) {
      if (workTypes[i].id === id) return workTypes[i];
    }
    return { id: 0, name: "Task", svg_icon: "" };
  }

  function workTypeIconImg(iconPath, name) {
    if (!iconPath) return "";
    return (
      '<img class="sheets-wt-icon" src="' +
      esc(iconPath) +
      '" alt="' +
      esc(name || "") +
      '">'
    );
  }

  function getUserName(id) {
    for (var i = 0; i < assignees.length; i++) {
      if (assignees[i].id === id) return assignees[i].name;
    }
    return "";
  }

  function getColumnName(id) {
    for (var i = 0; i < columns.length; i++) {
      if (columns[i].id === id) return columns[i].name;
    }
    return "";
  }

  function getLabelNames(lblArr) {
    if (!lblArr || !lblArr.length) return [];
    return lblArr.map(function (l) {
      return l.name || "";
    });
  }

  function getStatusLabelNames(csv) {
    if (!csv) return [];
    var ids = String(csv)
      .split(",")
      .map(function (x) {
        return parseInt(x, 10);
      });
    var names = [];
    statusLabels.forEach(function (sl) {
      if (ids.indexOf(sl.id) !== -1) names.push(sl.name);
    });
    return names;
  }

  function estimateUnitToMinutes(value, unit) {
    var amount = Number(value || 0);
    if (!isFinite(amount) || amount <= 0) return 0;
    var u = String(unit || "minutes")
      .trim()
      .toLowerCase();
    if (u === "weeks" || u === "week" || u === "w") return amount * 7 * 24 * 60;
    if (u === "days" || u === "day" || u === "d") return amount * 24 * 60;
    if (u === "hours" || u === "hour" || u === "h") return amount * 60;
    if (
      u === "minutes" ||
      u === "minute" ||
      u === "mins" ||
      u === "min" ||
      u === "m"
    )
      return amount;
    if (u === "seconds" || u === "second" || u === "secs" || u === "sec" || u === "s")
      return amount / 60;
    return 0;
  }

  function parseDurationToMinutes(text) {
    var raw = String(text || "").trim();
    if (!raw) return 0;
    if (/^no time logged$/i.test(raw)) return 0;

    var total = 0;
    var re =
      /(\d+(?:\.\d+)?)\s*(weeks?|week|w|days?|day|d|hours?|hour|h|minutes?|minute|mins?|min|m|seconds?|second|secs?|sec|s)\b/gi;
    var match = null;
    while ((match = re.exec(raw))) {
      var n = Number(match[1] || 0);
      var u = String(match[2] || "").toLowerCase();
      total += estimateUnitToMinutes(n, u);
    }

    if (total > 0) return total;

    var asNum = Number(raw);
    return isFinite(asNum) && asNum > 0 ? asNum : 0;
  }

  function formatMinutesTotal(minutes) {
    var total = Math.round(Number(minutes || 0));
    if (!isFinite(total) || total <= 0) return "0m";

    var weeks = Math.floor(total / (7 * 24 * 60));
    total -= weeks * 7 * 24 * 60;
    var days = Math.floor(total / (24 * 60));
    total -= days * 24 * 60;
    var hours = Math.floor(total / 60);
    total -= hours * 60;
    var mins = total;

    var out = [];
    if (weeks) out.push(weeks + "w");
    if (days) out.push(days + "d");
    if (hours) out.push(hours + "h");
    if (mins || !out.length) out.push(mins + "m");
    return out.join(" ");
  }

  /**
   * Strip HTML tags; detect media elements and append [Media].
   */
  function stripHtmlToText(html) {
    if (!html) return "";
    var s = String(html);
    var hasMedia = /<(img|video|audio|embed|object|iframe|source)\b/i.test(s);
    if (!hasMedia)
      hasMedia =
        /\.(pdf|png|jpg|jpeg|gif|webp|svg|mp4|mp3|wav|zip|rar|doc|docx|xls|xlsx)\b/i.test(
          s,
        );
    var tmp = document.createElement("div");
    tmp.innerHTML = s;
    var text = (tmp.textContent || tmp.innerText || "")
      .trim()
      .replace(/\s+/g, " ");
    if (hasMedia) text = text ? text + " [Media]" : "[Media]";
    return text;
  }

  function getCellValue(item, key) {
    switch (key) {
      case "work_type":
        return item.work_type_name || "Task";
      case "work_item_key":
        return item.work_item_key || buildWorkItemKey(item.id);
      case "title":
        return item.title || "";
      case "description":
        return stripHtmlToText(item.description || "");
      case "board_status":
        return item.column_name || getColumnName(item.column_id) || "";
      case "original_estimate":
        var v = item.original_estimate_value || 0;
        var u = item.original_estimate_unit || "minutes";
        return v > 0 ? v + " " + u : "";
      case "task_status":
        return getStatusLabelNames(item.task_status).join(", ");
      case "parent_display":
        return item.parent_item_id > 0
          ? buildWorkItemKey(item.parent_item_id)
          : "None";
      case "assignee_name":
        return (
          item.assignee_name ||
          (item.assignee_user_id > 0
            ? getUserName(item.assignee_user_id)
            : "Unassigned")
        );
      case "reporter_name":
        return (
          item.reporter_name ||
          (item.reporter_user_id > 0
            ? getUserName(item.reporter_user_id)
            : "Unassigned")
        );
      case "priority":
        return item.priority || "Medium";
      case "labels":
        return getLabelNames(item.labels).join(", ");
      case "time_tracking":
        return item.time_tracking || "No time logged";
      case "start_date":
        return item.start_date || "";
      case "due_date":
        return item.due_date || "";
      case "amendement_date":
        return item.amendement_date || "";
      case "amendement_time_minutes":
        var m = item.amendement_time_minutes || 0;
        return m > 0 ? m + " min" : "";
      case "second_amendement_date":
        return item.second_amendement_date || "";
      case "second_amendement_time_minutes":
        var m2 = item.second_amendement_time_minutes || 0;
        return m2 > 0 ? m2 + " min" : "";
      default:
        return "";
    }
  }

  function priorityIcon(p) {
    var cls = "priority-" + (p || "medium").toLowerCase();
    var icons = {
      Highest: "fa-angles-up",
      High: "fa-angle-up",
      Medium: "fa-equals",
      Low: "fa-angle-down",
      Lowest: "fa-angles-down",
    };
    return (
      '<i class="fa-solid ' +
      (icons[p] || "fa-equals") +
      " sheets-priority-icon " +
      cls +
      '"></i>'
    );
  }

  /* ───── data processing ───── */

  function flattenItems() {
    if (cfg.items && cfg.items.length > 0) {
      var flat = cfg.items.slice();
      flat.forEach(function (item) {
        item.column_name = getColumnName(item.column_id);
      });
      return flat;
    }
    var flat = [];
    for (var colId in cfg.itemsByColumn) {
      var arr = cfg.itemsByColumn[colId] || [];
      arr.forEach(function (item) {
        item.column_name = getColumnName(parseInt(colId, 10));
        flat.push(item);
      });
    }
    return flat;
  }

  function applyFiltersAndSort() {
    var items = allItems.slice();

    // toolbar assignee filter
    if (toolbarAssigneeFilter) {
      if (toolbarAssigneeFilter === "__unassigned__") {
        items = items.filter(function (item) {
          var assigneeId = Number(
            item.assignee_user_id || item.assignee_id || 0,
          );
          return assigneeId <= 0;
        });
      } else {
        var filterId = String(toolbarAssigneeFilter);
        items = items.filter(function (item) {
          var assigneeId = Number(
            item.assignee_user_id || item.assignee_id || 0,
          );
          return String(assigneeId) === filterId;
        });
      }
    }

    // global search
    if (globalSearch) {
      var q = globalSearch.toLowerCase();
      items = items.filter(function (item) {
        for (var i = 0; i < COLUMNS.length; i++) {
          if (
            String(getCellValue(item, COLUMNS[i].key))
              .toLowerCase()
              .indexOf(q) !== -1
          )
            return true;
        }
        return false;
      });
    }

    // column filters
    Object.keys(activeFilters).forEach(function (colKey) {
      var f = activeFilters[colKey];
      if (!f) return;
      if (f.type === "option" && f.values && f.values.length > 0) {
        items = items.filter(function (item) {
          var cv = getCellValue(item, colKey);
          return f.values.indexOf(cv) !== -1;
        });
      } else if (f.type === "text" && f.text) {
        var txt = f.text.toLowerCase();
        var useRegex = f.regex;
        items = items.filter(function (item) {
          var cv = getCellValue(item, colKey);
          if (useRegex) {
            try {
              return new RegExp(f.text, "i").test(cv);
            } catch (e) {
              return false;
            }
          }
          return cv.toLowerCase().indexOf(txt) !== -1;
        });
      } else if (f.type === "date") {
        items = items.filter(function (item) {
          var cv = getCellValue(item, colKey);
          if (f.empty && !cv) return true;
          if (f.notEmpty && cv) return true;
          if (f.empty && cv) return false;
          if (f.notEmpty && !cv) return false;
          if (!cv) return !f.from && !f.to;
          if (f.from && cv < f.from) return false;
          if (f.to && cv > f.to) return false;
          return true;
        });
      } else if (f.type === "time") {
        items = items.filter(function (item) {
          var cv = getCellValue(item, colKey);
          if (f.empty && !cv) return true;
          if (f.notEmpty && cv) return true;
          if (f.empty && cv) return false;
          if (f.notEmpty && !cv) return false;
          if (!f.from && !f.to && !f.empty && !f.notEmpty) return true;
          var numVal = parseFloat(cv) || 0;
          if (f.from && numVal < parseFloat(f.from)) return false;
          if (f.to && numVal > parseFloat(f.to)) return false;
          return true;
        });
      }
    });

    // sort
    if (sortCol && sortDir) {
      items.sort(function (a, b) {
        var av = getCellValue(a, sortCol);
        var bv = getCellValue(b, sortCol);
        if (av < bv) return sortDir === "asc" ? -1 : 1;
        if (av > bv) return sortDir === "asc" ? 1 : -1;
        return 0;
      });
    }

    displayItems = items;
  }

  /* ───── rendering ───── */

  function renderTable() {
    applyFiltersAndSort();
    var $wrap = $("#sheetsTableWrap");
    var html = '<table class="sheets-table"><colgroup>';
    if (window.TaskBulkEdit) {
      html += '<col class="sheets-check-col" style="width:36px;">';
    }
    COLUMNS.forEach(function (c) {
      html +=
        '<col class="' +
        c.css +
        '" style="width:' +
        getColumnWidth(c.key) +
        'px;">';
    });
    html += "</colgroup><thead><tr>";
    if (window.TaskBulkEdit) {
      var headerAllChecked =
        displayItems.length > 0 &&
        displayItems.every(function (item) {
          return selectedItemIds.indexOf(Number(item.id)) !== -1;
        });
      html +=
        '<th class="sheets-check-cell"><input type="checkbox" id="sheetsSelectAllCheck"' +
        (headerAllChecked ? " checked" : "") +
        "></th>";
    }
    COLUMNS.forEach(function (c, colIdx) {
      var sortCls = sortCol === c.key ? " sort-active" : "";
      var sortIcon =
        sortCol === c.key
          ? sortDir === "asc"
            ? "fa-arrow-up-short-wide"
            : "fa-arrow-down-wide-short"
          : "fa-sort";
      var filterActive = activeFilters[c.key] ? ' style="color:#0c66e4"' : "";
      html +=
        '<th data-col="' +
        c.key +
        '" data-col-idx="' +
        colIdx +
        '"><div class="sheets-th-inner">' +
        '<span class="sheets-th-label">' +
        esc(c.label) +
        "</span>" +
        '<span class="sheets-th-actions">' +
        '<button class="sheets-th-btn btn-sort' +
        sortCls +
        '" data-col="' +
        c.key +
        '" title="Sort"><i class="fa-solid ' +
        sortIcon +
        '"></i></button>' +
        '<button class="sheets-th-btn btn-filter" data-col="' +
        c.key +
        '" title="Filter"' +
        filterActive +
        '><i class="fa-solid fa-filter"></i></button>' +
        '<button class="sheets-th-btn btn-more" data-col="' +
        c.key +
        '" data-col-idx="' +
        colIdx +
        '" title="More"><i class="fa-solid fa-ellipsis-vertical"></i></button>' +
        '</span></div><div class="sheets-col-resize-handle" data-col="' +
        c.key +
        '" aria-hidden="true"></div></th>';
    });
    html += "</tr></thead><tbody>";

    if (showSummaryRow) html += renderSummaryRow();

    if (groupByCol) {
      var groups = {},
        groupOrder = [];
      displayItems.forEach(function (item) {
        var gv = getCellValue(item, groupByCol) || "(empty)";
        if (!groups[gv]) {
          groups[gv] = [];
          groupOrder.push(gv);
        }
        groups[gv].push(item);
      });
      groupOrder.forEach(function (gv) {
        var collapsed = !!collapsedGroups[gv];
        var icon = collapsed ? "fa-chevron-right" : "fa-chevron-down";
        var colDef = ALL_COLUMN_DEFS.find(function (c) {
          return c.key === groupByCol;
        });
        var groupLabel = colDef ? colDef.label : groupByCol;
        html +=
          '<tr class="sheets-group-row" data-group="' +
          esc(gv) +
          '"><td colspan="' +
          (COLUMNS.length + (window.TaskBulkEdit ? 1 : 0)) +
          '">' +
          '<i class="fa-solid ' +
          icon +
          '"></i> ' +
          "<span>" +
          esc(groupLabel) +
          "</span> " +
          "<strong>" +
          esc(gv) +
          "</strong>" +
          '<span class="sheets-group-badge">' +
          groups[gv].length +
          " work items</span>" +
          "</td></tr>";
        if (!collapsed)
          groups[gv].forEach(function (item) {
            html += renderRow(item);
          });
      });
    } else {
      displayItems.forEach(function (item) {
        html += renderRow(item);
      });
    }

    html += "</tbody></table>";
    $wrap.html(html);
    updateToolbarInfo();
    updateBulkToolbarState();
  }

  /* ───── bulk edit selection ───── */

  function pruneSelection() {
    if (!selectedItemIds.length) return;
    var existingIds = {};
    allItems.forEach(function (item) {
      existingIds[Number(item.id)] = true;
    });
    selectedItemIds = selectedItemIds.filter(function (id) {
      return !!existingIds[id];
    });
  }

  function updateBulkToolbarState() {
    var $btn = $("#sheetsBulkEditBtn");
    if ($btn.length) {
      $btn.prop("disabled", selectedItemIds.length === 0);
    }
    var $selectAll = $("#sheetsSelectAllCheck");
    if ($selectAll.length) {
      var allChecked =
        displayItems.length > 0 &&
        displayItems.every(function (item) {
          return selectedItemIds.indexOf(Number(item.id)) !== -1;
        });
      var anyChecked = displayItems.some(function (item) {
        return selectedItemIds.indexOf(Number(item.id)) !== -1;
      });
      $selectAll.prop("checked", allChecked);
      $selectAll.prop("indeterminate", !allChecked && anyChecked);
    }
  }

  function openBulkEditModal() {
    if (!window.TaskBulkEdit || !selectedItemIds.length) return;
    var selectedSet = {};
    selectedItemIds.forEach(function (id) {
      selectedSet[id] = true;
    });
    var itemsForBulkEdit = allItems
      .filter(function (item) {
        return !!selectedSet[Number(item.id)];
      })
      .map(function (item) {
        return $.extend({}, item, {
          status_name: getColumnName(Number(item.column_id)),
        });
      });

    var $modalEl = $("#sheetsBulkEditModal");
    if (!$modalEl.length || typeof bootstrap === "undefined") return;
    var modalInstance = bootstrap.Modal.getOrCreateInstance($modalEl[0]);
    window.TaskBulkEdit.openWithItems(
      itemsForBulkEdit,
      selectedItemIds.slice(),
      function () {
        modalInstance.hide();
        selectedItemIds = [];
        refreshData();
      },
    );
    modalInstance.show();
  }

  function applyColumnWidth(colKey, px, saveImmediately) {
    var key = String(colKey || "");
    if (!key) {
      return;
    }
    var width = Math.max(MIN_COLUMN_WIDTH, Math.round(Number(px || 0)));
    columnWidths[key] = width;

    var $table = $("#sheetsTableWrap .sheets-table");
    var colIndex = -1;
    for (var i = 0; i < COLUMNS.length; i++) {
      if (COLUMNS[i].key === key) {
        colIndex = i;
        break;
      }
    }
    if (colIndex < 0 || !$table.length) {
      return;
    }

    var colOffset = window.TaskBulkEdit ? 1 : 0;
    $table.find("colgroup col").eq(colIndex + colOffset).css("width", width + "px");
    if (saveImmediately !== false) {
      saveViewPrefs();
    }
  }

  function startColumnResize(evt, colKey) {
    var $table = $("#sheetsTableWrap .sheets-table");
    if (!$table.length) {
      return;
    }

    var $th = $table.find('thead th[data-col="' + colKey + '"]').first();
    if (!$th.length) {
      return;
    }

    var startX = evt.pageX;
    var startWidth = $th.outerWidth();
    activeColumnResize = {
      key: colKey,
      startX: startX,
      startWidth: startWidth,
    };

    $(document.body).addClass("sheets-col-resizing");
  }

  function renderSummaryRow() {
    var html = '<tr class="sheets-summary-row">';
    if (window.TaskBulkEdit) {
      html += "<td></td>";
    }
    COLUMNS.forEach(function (c) {
      if (c.key === "work_item_key") {
        html += "<td>" + displayItems.length + " work items</td>";
      } else if (c.key === "original_estimate") {
        var estimateMins = 0;
        displayItems.forEach(function (it) {
          estimateMins += estimateUnitToMinutes(
            it.original_estimate_value || 0,
            it.original_estimate_unit || "minutes",
          );
        });
        html += "<td>" + esc(formatMinutesTotal(estimateMins)) + "</td>";
      } else if (c.key === "time_tracking") {
        var trackingMins = 0;
        displayItems.forEach(function (it) {
          trackingMins += parseDurationToMinutes(it.time_tracking || "");
        });
        html += "<td>" + esc(formatMinutesTotal(trackingMins)) + "</td>";
      } else if (c.key === "task_status") {
        var statusTypeSet = {};
        displayItems.forEach(function (it) {
          var ids = String(it.task_status || "")
            .split(",")
            .map(function (x) {
              return parseInt(x, 10);
            })
            .filter(function (x) {
              return Number(x || 0) > 0;
            });
          ids.forEach(function (id) {
            statusTypeSet[id] = 1;
          });
        });
        html += "<td>" + Object.keys(statusTypeSet).length + "</td>";
      } else if (c.key === "labels") {
        var labelTypeSet = {};
        displayItems.forEach(function (it) {
          var arr = Array.isArray(it.labels) ? it.labels : [];
          arr.forEach(function (l) {
            var id = Number((l && l.id) || 0);
            var name = String((l && l.name) || "").trim();
            if (id > 0) {
              labelTypeSet["id:" + id] = 1;
            } else if (name) {
              labelTypeSet["name:" + name.toLowerCase()] = 1;
            }
          });
        });
        html += "<td>" + Object.keys(labelTypeSet).length + "</td>";
      } else if (
        c.key === "assignee_name" ||
        c.key === "reporter_name" ||
        c.key === "priority"
      ) {
        var u = {};
        displayItems.forEach(function (it) {
          var n = getCellValue(it, c.key);
          if (n) u[n] = 1;
        });
        html += "<td>" + Object.keys(u).length + "</td>";
      } else {
        html += "<td></td>";
      }
    });
    html += "</tr>";
    return html;
  }

  function renderRow(item) {
    var html = '<tr data-item-id="' + item.id + '">';
    if (window.TaskBulkEdit) {
      var itemChecked = selectedItemIds.indexOf(Number(item.id)) !== -1;
      html +=
        '<td class="sheets-check-cell"><input type="checkbox" class="sheets-row-check" data-item-id="' +
        item.id +
        '"' +
        (itemChecked ? " checked" : "") +
        "></td>";
    }
    COLUMNS.forEach(function (c) {
      var editCls =
        canEditCellByPermission(item, c.key) && c.editable && c.key !== "work_item_key"
          ? " sheets-cell-editable"
          : "";
      html +=
        '<td data-col="' +
        c.key +
        '" class="' +
        editCls +
        '">' +
        renderCellContent(item, c) +
        "</td>";
    });
    html += "</tr>";
    return html;
  }

  function renderCellContent(item, col) {
    var val;
    switch (col.key) {
      case "work_type":
        var wt = getWorkType(item.work_type_id);
        return (
          '<div class="sheets-cell-type">' +
          workTypeIconImg(wt.svg_icon || item.work_type_svg_icon, wt.name) +
          "<span>" +
          esc(wt.name) +
          "</span></div>"
        );
      case "work_item_key":
        return (
          '<span class="sheets-cell-key">' +
          esc(item.work_item_key || buildWorkItemKey(item.id)) +
          "</span>"
        );
      case "title":
        return esc(item.title || "");
      case "description":
        val = stripHtmlToText(item.description || "");
        return (
          '<span title="' +
          esc(val) +
          '">' +
          esc(val.length > 60 ? val.substring(0, 60) + "..." : val) +
          "</span>"
        );
      case "assignee_name":
        var aName = getCellValue(item, "assignee_name");
        if (!aName || aName === "Unassigned")
          return '<div class="sheets-cell-user unassigned"><i class="fa-regular fa-user"></i> Unassigned</div>';
        return (
          '<div class="sheets-cell-user"><span class="sheets-user-avatar">' +
          esc(aName.charAt(0)) +
          "</span>" +
          esc(aName) +
          "</div>"
        );
      case "reporter_name":
        var rName = getCellValue(item, "reporter_name");
        if (!rName || rName === "Unassigned")
          return '<div class="sheets-cell-user unassigned"><i class="fa-regular fa-user"></i> Unassigned</div>';
        return (
          '<div class="sheets-cell-user"><span class="sheets-user-avatar">' +
          esc(rName.charAt(0)) +
          "</span>" +
          esc(rName) +
          "</div>"
        );
      case "priority":
        var p = item.priority || "Medium";
        return (
          '<span class="sheets-priority">' +
          priorityIcon(p) +
          " " +
          esc(p) +
          "</span>"
        );
      case "labels":
        var lbls = item.labels || [];
        if (!lbls.length) return "";
        return (
          '<div class="sheets-label-list">' +
          lbls
            .map(function (l) {
              return (
                '<span class="sheets-label-badge">' + esc(l.name) + "</span>"
              );
            })
            .join("") +
          "</div>"
        );
      case "task_status":
        var names = getStatusLabelNames(item.task_status);
        if (!names.length) return "";
        return (
          '<div class="sheets-status-list">' +
          names
            .map(function (n) {
              return '<span class="sheets-status-badge">' + esc(n) + "</span>";
            })
            .join("") +
          "</div>"
        );
      case "parent_display":
        return item.parent_item_id > 0
          ? '<span class="sheets-cell-key">' +
              esc(buildWorkItemKey(item.parent_item_id)) +
              "</span>"
          : "None";
      case "board_status":
        var colName = item.column_name || getColumnName(item.column_id) || "";
        return colName
          ? '<span class="sheets-status-badge">' + esc(colName) + "</span>"
          : "";
      case "time_tracking":
        return esc(item.time_tracking || "No time logged");
      case "start_date":
      case "due_date":
      case "amendement_date":
      case "second_amendement_date":
        return esc(item[col.key] || "");
      case "original_estimate":
        var ev = item.original_estimate_value || 0;
        var eu = item.original_estimate_unit || "minutes";
        return ev > 0 ? esc(ev + " " + eu) : "";
      case "amendement_time_minutes":
      case "second_amendement_time_minutes":
        var mins = item[col.key] || 0;
        return mins > 0 ? esc(mins + " min") : "";
      default:
        return esc(getCellValue(item, col.key));
    }
  }

  function updateToolbarInfo() {
    $(".sheets-toolbar-info").text(displayItems.length + " work items");
  }

  /* ───── toolbar actions ───── */

  function toggleSummary() {
    showSummaryRow = !showSummaryRow;
    $(".sheets-btn-sum").toggleClass("active", showSummaryRow);
    saveViewPrefs();
    renderTable();
  }

  function refreshData() {
    var $wrap = $("#sheetsTableWrap");
    var $refreshBtn = $(".sheets-btn-refresh");
    $wrap.addClass("is-loading");
    $refreshBtn.addClass("is-spinning");

    $.ajax({
      url: sheetsDataAjaxUrl,
      method: "POST",
      data: { task_action: "sheets_get_data", csrf_token: csrfToken },
      dataType: "json",
      success: function (resp) {
        if (resp && resp.ok) {
          cfg.itemsByColumn = resp.itemsByColumn || {};
          cfg.items = resp.items || [];
          allItems = flattenItems();
          pruneSelection();
          saveViewPrefs();
          renderTable();
        }
      },
      complete: function () {
        $wrap.removeClass("is-loading");
        $refreshBtn.removeClass("is-spinning");
      },
    });
  }

  /* ───── sort ───── */

  $(document).on("click", ".btn-sort", function (e) {
    e.stopPropagation();
    var col = $(this).data("col");
    if (sortCol === col) {
      sortDir = sortDir === "asc" ? "desc" : sortDir === "desc" ? "" : "asc";
      if (!sortDir) sortCol = "";
    } else {
      sortCol = col;
      sortDir = "asc";
    }
    saveViewPrefs();
    renderTable();
  });

  $(document).on("mousedown", ".sheets-col-resize-handle", function (e) {
    if (e.which !== 1) {
      return;
    }
    e.preventDefault();
    e.stopPropagation();
    startColumnResize(e, $(this).data("col"));
  });

  $(document).on("mousemove", function (e) {
    if (!activeColumnResize) {
      return;
    }
    e.preventDefault();
    var nextWidth =
      Number(activeColumnResize.startWidth || 0) +
      (Number(e.pageX || 0) - Number(activeColumnResize.startX || 0));
    applyColumnWidth(activeColumnResize.key, nextWidth, false);
  });

  $(document).on("mouseup", function () {
    if (!activeColumnResize) {
      return;
    }
    saveViewPrefs();
    activeColumnResize = null;
    $(document.body).removeClass("sheets-col-resizing");
  });

  /* ───── filter ───── */

  function closeAllPopups() {
    $(".sheets-filter-popup, .sheets-group-popup").remove();
  }

  $(document).on("click", ".btn-filter", function (e) {
    e.stopPropagation();
    closeAllPopups();
    closeAllDropdowns();
    var colKey = $(this).data("col");
    var colDef = COLUMNS.find(function (c) {
      return c.key === colKey;
    });
    if (!colDef) return;
    var $th = $(this).closest("th");
    var popup = buildFilterPopup(colDef);
    $th.append(popup);
    popup.addClass("show");
  });

  function buildFilterPopup(colDef) {
    var $popup = $('<div class="sheets-filter-popup"></div>');
    var ex = activeFilters[colDef.key] || null;

    if (colDef.filterType === "option") {
      $popup.append('<div class="filter-title">Option filter</div>');
      $popup.append(
        '<input type="text" class="filter-search" placeholder="Find..." data-col="' +
          colDef.key +
          '">',
      );
      var options = getUniqueValues(colDef.key);
      var $opts = $('<div class="filter-options"></div>');
      options.forEach(function (opt) {
        var chk =
          ex && ex.values && ex.values.indexOf(opt) !== -1 ? " checked" : "";
        $opts.append(
          '<label class="filter-option"><input type="checkbox" value="' +
            esc(opt) +
            '"' +
            chk +
            "> " +
            esc(opt) +
            "</label>",
        );
      });
      $popup.append($opts);
      $popup.append(
        '<div class="filter-actions"><span><a href="#" class="filter-clear">Clear</a> <a href="#" class="filter-select-all">Select all</a></span><button class="btn-apply" data-col="' +
          colDef.key +
          '" data-type="option">Apply</button></div>',
      );
    } else if (colDef.filterType === "text") {
      $popup.append('<div class="filter-title">Text filter</div>');
      var tv = ex && ex.text ? ex.text : "";
      var rc = ex && ex.regex ? " checked" : "";
      $popup.append(
        '<input type="text" class="filter-text-input" placeholder="Filter column..." value="' +
          esc(tv) +
          '" data-col="' +
          colDef.key +
          '">',
      );
      $popup.append(
        '<div class="filter-text-options"><label title="Regex"><input type="checkbox" class="filter-regex"' +
          rc +
          "> .*</label></div>",
      );
      $popup.append(
        '<div class="filter-actions"><a href="#" class="filter-clear">Clear</a><button class="btn-apply" data-col="' +
          colDef.key +
          '" data-type="text">Apply</button></div>',
      );
    } else if (colDef.filterType === "date") {
      $popup.append('<div class="filter-title">Date and time filter</div>');
      var fv = ex && ex.from ? ex.from : "",
        tv2 = ex && ex.to ? ex.to : "";
      var ec = ex && ex.empty ? " checked" : "",
        nc = ex && ex.notEmpty ? " checked" : "";
      $popup.append(
        '<div class="filter-date-row"><div><label>From</label><input type="date" class="filter-date-from" value="' +
          esc(fv) +
          '"></div><div><label>To</label><input type="date" class="filter-date-to" value="' +
          esc(tv2) +
          '"></div></div>',
      );
      $popup.append(
        '<div class="filter-options" style="margin-top:4px"><label class="filter-option"><input type="checkbox" class="filter-empty"' +
          ec +
          '> (Empty)</label><label class="filter-option"><input type="checkbox" class="filter-not-empty"' +
          nc +
          "> (Not empty)</label></div>",
      );
      $popup.append(
        '<div class="filter-actions"><a href="#" class="filter-clear">Clear</a><button class="btn-apply" data-col="' +
          colDef.key +
          '" data-type="date">Apply</button></div>',
      );
    } else if (colDef.filterType === "time") {
      $popup.append('<div class="filter-title">Time filter</div>');
      var tf = ex && ex.from ? ex.from : "",
        tt = ex && ex.to ? ex.to : "";
      var te = ex && ex.empty ? " checked" : "",
        tn = ex && ex.notEmpty ? " checked" : "";
      $popup.append(
        '<div class="filter-date-row"><div><label>From</label><input type="text" class="filter-time-from" placeholder="" value="' +
          esc(tf) +
          '"></div><div><label>To</label><input type="text" class="filter-time-to" placeholder="" value="' +
          esc(tt) +
          '"></div></div>',
      );
      $popup.append(
        '<div class="filter-options" style="margin-top:4px"><label class="filter-option"><input type="checkbox" class="filter-empty"' +
          te +
          '> (Empty)</label><label class="filter-option"><input type="checkbox" class="filter-not-empty"' +
          tn +
          "> (Not empty)</label></div>",
      );
      $popup.append(
        '<div class="filter-actions"><a href="#" class="filter-clear">Clear</a><button class="btn-apply" data-col="' +
          colDef.key +
          '" data-type="time">Apply</button></div>',
      );
    }

    return $popup;
  }

  function getUniqueValues(colKey) {
    var vals = {};
    allItems.forEach(function (item) {
      var v = getCellValue(item, colKey);
      if (v !== "" && v !== undefined && v !== null) vals[v] = 1;
    });
    return Object.keys(vals).sort();
  }

  $(document).on("input", ".sheets-filter-popup .filter-search", function () {
    var q = $(this).val().toLowerCase();
    $(this)
      .closest(".sheets-filter-popup")
      .find(".filter-option")
      .each(function () {
        $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
      });
  });

  $(document).on("click", ".sheets-filter-popup .btn-apply", function () {
    var colKey = $(this).data("col"),
      type = $(this).data("type");
    var $popup = $(this).closest(".sheets-filter-popup");

    if (type === "option") {
      var sel = [];
      $popup.find(".filter-options input:checked").each(function () {
        sel.push($(this).val());
      });
      if (sel.length) activeFilters[colKey] = { type: "option", values: sel };
      else delete activeFilters[colKey];
    } else if (type === "text") {
      var txt = $popup.find(".filter-text-input").val();
      var rx = $popup.find(".filter-regex").is(":checked");
      if (txt) activeFilters[colKey] = { type: "text", text: txt, regex: rx };
      else delete activeFilters[colKey];
    } else if (type === "date") {
      var from = $popup.find(".filter-date-from").val(),
        to = $popup.find(".filter-date-to").val();
      var emp = $popup.find(".filter-empty").is(":checked"),
        ne = $popup.find(".filter-not-empty").is(":checked");
      if (from || to || emp || ne)
        activeFilters[colKey] = {
          type: "date",
          from: from,
          to: to,
          empty: emp,
          notEmpty: ne,
        };
      else delete activeFilters[colKey];
    } else if (type === "time") {
      var tF = $popup.find(".filter-time-from").val(),
        tT = $popup.find(".filter-time-to").val();
      var tE = $popup.find(".filter-empty").is(":checked"),
        tN = $popup.find(".filter-not-empty").is(":checked");
      if (tF || tT || tE || tN)
        activeFilters[colKey] = {
          type: "time",
          from: tF,
          to: tT,
          empty: tE,
          notEmpty: tN,
        };
      else delete activeFilters[colKey];
    }

    closeAllPopups();
    saveViewPrefs();
    renderTable();
  });

  $(document).on("click", ".sheets-filter-popup .filter-clear", function (e) {
    e.preventDefault();
    var $popup = $(this).closest(".sheets-filter-popup");
    var colKey = $popup.find(".btn-apply").data("col");
    delete activeFilters[colKey];
    closeAllPopups();
    saveViewPrefs();
    renderTable();
  });

  $(document).on(
    "click",
    ".sheets-filter-popup .filter-select-all",
    function (e) {
      e.preventDefault();
      $(this)
        .closest(".sheets-filter-popup")
        .find(".filter-options input[type=checkbox]")
        .prop("checked", true);
    },
  );

  $(document).on("click", function (e) {
    if (
      !$(e.target).closest(
        ".sheets-filter-popup, .btn-filter, .btn-more, .sheets-btn-group, .sheets-group-popup, .sheets-col-add-popup, .sheets-col-add-backdrop",
      ).length
    ) {
      closeAllPopups();
    }
  });

  /* ───── more (column menu) – Add / Remove column ───── */

  $(document).on("click", ".btn-more", function (e) {
    e.stopPropagation();
    closeAllPopups();
    closeAllDropdowns();
    var colIdx = parseInt($(this).data("col-idx"), 10);
    var $th = $(this).closest("th");

    var $popup = $('<div class="sheets-filter-popup sheets-more-popup"></div>');
    $popup.append(
      '<a href="#" class="sheets-more-option" data-action="add-left"  data-col-idx="' +
        colIdx +
        '"><i class="fa-solid fa-plus"></i> Add column to the left</a>',
    );
    $popup.append(
      '<a href="#" class="sheets-more-option" data-action="add-right" data-col-idx="' +
        colIdx +
        '"><i class="fa-solid fa-plus"></i> Add column to the right</a>',
    );
    $popup.append('<hr class="sheets-more-divider">');
    $popup.append(
      '<a href="#" class="sheets-more-option sheets-more-danger" data-action="remove" data-col-idx="' +
        colIdx +
        '"><i class="fa-solid fa-trash"></i> Remove column</a>',
    );

    $th.append($popup);
    $popup.addClass("show");
  });

  $(document).on("click", ".sheets-more-option", function (e) {
    e.preventDefault();
    e.stopPropagation();
    var action = $(this).data("action"),
      colIdx = parseInt($(this).data("col-idx"), 10);
    closeAllPopups();

    if (action === "remove") {
      if (COLUMNS.length <= 1) {
        showToast("Cannot remove the last column.");
        return;
      }
      var label = COLUMNS[colIdx] ? COLUMNS[colIdx].label : "";
      if (!confirm('Remove column "' + label + '"?')) return;
      COLUMNS.splice(colIdx, 1);
      saveColumnsToDb();
      renderTable();
    } else if (action === "add-left" || action === "add-right") {
      showAddColumnPopup(colIdx, action === "add-right");
    }
  });

  /* ───── add column popup ───── */

  function showAddColumnPopup(refColIdx, addRight) {
    closeAllPopups();
    var $backdrop = $('<div class="sheets-col-add-backdrop"></div>');
    var $popup = $(
      '<div class="sheets-filter-popup sheets-col-add-popup"></div>',
    );
    $popup.append('<div class="filter-title">Search fields and data</div>');
    $popup.append(
      '<input type="text" class="filter-search sheets-col-add-search" placeholder="Search fields and data">',
    );

    var $list = $('<div class="sheets-col-add-list"></div>');
    ALL_COLUMN_DEFS.forEach(function (def) {
      var count = COLUMNS.filter(function (c) {
        return c.key === def.key;
      }).length;
      var badge =
        count > 0
          ? '<span class="sheets-col-add-badge"><i class="fa-solid fa-check"></i> ' +
            count +
            "</span>"
          : "";
      $list.append(
        '<div class="sheets-col-add-item" data-col-key="' +
          def.key +
          '">' +
          '<div class="sheets-col-add-info"><i class="' +
          (def.icon || "fa-solid fa-columns") +
          '" style="color:#626f86;width:20px;text-align:center"></i>' +
          '<div><div style="font-weight:500">' +
          esc(def.label) +
          "</div>" +
          '<div style="font-size:11px;color:#626f86">' +
          esc(getColumnDescription(def.key)) +
          "</div></div></div>" +
          badge +
          "</div>",
      );
    });
    $popup.append($list);
    $popup.append(
      '<div class="filter-actions" style="border-top:1px solid #ebecf0;padding-top:8px"><button class="sheets-col-add-cancel">Cancel</button></div>',
    );

    $("body").append($backdrop).append($popup);
    $popup.addClass("show");

    $popup.find(".sheets-col-add-search").on("input", function () {
      var q = $(this).val().toLowerCase();
      $list.find(".sheets-col-add-item").each(function () {
        $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
      });
    });

    $popup.on("click", ".sheets-col-add-item", function () {
      var key = $(this).data("col-key");
      var def = ALL_COLUMN_DEFS.find(function (d) {
        return d.key === key;
      });
      if (!def) return;
      COLUMNS.splice(
        addRight ? refColIdx + 1 : refColIdx,
        0,
        $.extend({}, def),
      );
      saveColumnsToDb();
      renderTable();
      $backdrop.remove();
      $popup.remove();
    });

    function close() {
      $backdrop.remove();
      $popup.remove();
    }
    $popup.on("click", ".sheets-col-add-cancel", close);
    $backdrop.on("click", close);
  }

  function getColumnDescription(key) {
    var d = {
      work_type: "The type of work item.",
      work_item_key: "The unique identifier for the work item.",
      title: "A brief one-line summary of the work item.",
      description: "A detailed description of the work item.",
      board_status: "The board column status of the work item.",
      original_estimate: "The amount of time originally anticipated.",
      task_status: "The current status of the work item.",
      parent_display: "The parent Epic of the work item.",
      assignee_name: "The person assigned to work on the item.",
      reporter_name: "The person who created the work item.",
      priority: "The priority level of the work item.",
      labels: "Labels categorizing the work item.",
      time_tracking: "Time logged working on the work item.",
      start_date: "The planned start date.",
      due_date: "The target completion date.",
      amendement_date: "The first amendment date.",
      amendement_time_minutes: "The first amendment time.",
      second_amendement_date: "The second amendment date.",
      second_amendement_time_minutes: "The second amendment time.",
    };
    return d[key] || "";
  }

  /* ───── save columns to DB ───── */

  function saveColumnsToDb() {
    var colData = COLUMNS.map(function (c, i) {
      return { column_key: c.key, sort_order: i };
    });
    var saveUrl = cfg.sheetsColumnAjaxUrl || ajaxUrl;
    $.ajax({
      url: saveUrl,
      method: "POST",
      data: {
        task_action: "sheets_save_columns",
        csrf_token: csrfToken,
        columns_json: JSON.stringify(colData),
      },
      dataType: "json",
      success: function (resp) {
        if (resp && resp.ok && resp.sheetsColumns)
          cfg.sheetsColumns = resp.sheetsColumns;
      },
    });
  }

  /* ───── grouping button (Jira-style popup) ───── */

  $(document).on("click", ".sheets-btn-group", function (e) {
    e.stopPropagation();
    closeAllPopups();
    closeAllDropdowns();

    var $popup = $(
      '<div class="sheets-filter-popup sheets-group-popup"></div>',
    );
    $popup.append(
      '<input type="text" class="filter-search sheets-group-search" placeholder="Group by...">',
    );

    var $list = $('<div class="sheets-group-list"></div>');
    var noneActive = !groupByCol ? " sheets-group-active" : "";
    $list.append(
      '<div class="sheets-group-option' +
        noneActive +
        '" data-group-key=""><i class="fa-solid fa-ban" style="width:20px;text-align:center;color:#626f86"></i> <span>None</span></div>',
    );
    $list.append('<div class="sheets-group-section">COLUMNS IN SHEET</div>');

    var groupable = [
      { key: "work_type", label: "Work type", icon: "fa-solid fa-table-cells" },
      { key: "work_item_key", label: "Work item key", icon: "fa-solid fa-key" },
      { key: "assignee_name", label: "Assignee", icon: "fa-solid fa-user" },
      { key: "reporter_name", label: "Reporter", icon: "fa-solid fa-user-pen" },
      { key: "priority", label: "Priority", icon: "fa-solid fa-list-ol" },
      { key: "task_status", label: "Status", icon: "fa-solid fa-circle-check" },
      { key: "labels", label: "Labels", icon: "fa-solid fa-tags" },
      {
        key: "board_status",
        label: "Status Column",
        icon: "fa-solid fa-columns",
      },
      { key: "start_date", label: "Start Date", icon: "fa-solid fa-calendar" },
      {
        key: "due_date",
        label: "Due Date",
        icon: "fa-solid fa-calendar-check",
      },
    ];

    groupable.forEach(function (g) {
      var active = groupByCol === g.key ? " sheets-group-active" : "";
      $list.append(
        '<div class="sheets-group-option' +
          active +
          '" data-group-key="' +
          g.key +
          '">' +
          '<i class="' +
          g.icon +
          '" style="width:20px;text-align:center;color:#626f86"></i>' +
          "<span>" +
          esc(g.label) +
          "</span></div>",
      );
    });

    $popup.append($list);
    $(this).parent().css("position", "relative").append($popup);
    $popup.addClass("show");

    $popup.find(".sheets-group-search").on("input", function () {
      var q = $(this).val().toLowerCase();
      $list.find(".sheets-group-option").each(function () {
        $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
      });
    });
  });

  $(document).on("click", ".sheets-group-option", function () {
    groupByCol = $(this).data("group-key") || "";
    collapsedGroups = {};
    closeAllPopups();
    saveViewPrefs();
    renderTable();
  });

  /* ───── grouping collapse ───── */

  $(document).on("click", ".sheets-group-row", function () {
    var gv = $(this).data("group");
    collapsedGroups[gv] = !collapsedGroups[gv];
    saveViewPrefs();
    renderTable();
  });

  /* ───── collapse all button ───── */

  $(document).on("click", ".sheets-btn-collapse", function () {
    if (!groupByCol) {
      showToast("Enable grouping first to use collapse.");
      return;
    }
    var anyOpen = false,
      unique = {};
    displayItems.forEach(function (item) {
      var gv = getCellValue(item, groupByCol) || "(empty)";
      if (!collapsedGroups[gv]) anyOpen = true;
      unique[gv] = 1;
    });
    Object.keys(unique).forEach(function (gv) {
      collapsedGroups[gv] = anyOpen;
    });
    saveViewPrefs();
    renderTable();
  });

  /* ───── search ───── */

  $(document).on("click", ".sheets-btn-search", function () {
    $(".sheets-search-wrap").toggleClass("open");
    if ($(".sheets-search-wrap").hasClass("open")) {
      $(".sheets-search-input").focus();
    } else {
      $(".sheets-search-input").val("");
      globalSearch = "";
      renderTable();
    }
  });

  $(document).on("input", ".sheets-search-input", function () {
    globalSearch = $(this).val();
    renderTable();
  });

  /* ───── cell editing ───── */

  function showToast(msg) {
    $(".sheets-toast").remove();
    var $t = $(
      '<div class="sheets-toast"><i class="fa-solid fa-circle-info"></i> ' +
        esc(msg) +
        ' <span class="sheets-toast-close">&times;</span></div>',
    );
    $("body").append($t);
    setTimeout(function () {
      $t.fadeOut(300, function () {
        $t.remove();
      });
    }, 3000);
  }

  $(document).on("click", ".sheets-toast-close", function () {
    $(this).closest(".sheets-toast").remove();
  });

  function closeAllDropdowns() {
    $(
      ".sheets-wt-dropdown, .sheets-user-dropdown, .sheets-priority-dropdown",
    ).remove();
  }

  document.addEventListener(
    "dblclick",
    function (e) {
      var td = e.target && e.target.closest
        ? e.target.closest(".sheets-table tbody td")
        : null;
      if (!td) return;

      var colKey = td.getAttribute("data-col");
      var tr = td.closest("tr");
      var itemId = tr ? Number(tr.getAttribute("data-item-id") || 0) : 0;
      if (!itemId) return;

      var colDef = COLUMNS.find(function (c) {
        return c.key === colKey;
      });
      if (!colDef || !colDef.editable) return;

      if (!canEdit) {
        showToast("Cell uneditable - You don't have access to edit data on this field");
        e.preventDefault();
        e.stopImmediatePropagation();
        return;
      }

      var item = allItems.find(function (it) {
        return Number(it.id || 0) === itemId;
      });
      if (!item) return;

      if (!canEditCellByPermission(item, colKey)) {
        if (isColumnPermissionGuarded(colKey)) {
          showColumnPermissionDenied(item, colKey);
        } else {
          showToast("Cell uneditable - You don't have access to edit data on this field");
        }
        e.preventDefault();
        e.stopImmediatePropagation();
      }
    },
    true,
  );

  $(document).on("dblclick", ".sheets-table tbody td", function (e) {
    if (!canEdit) {
      showToast("Cell uneditable – This data can't be edited.");
      return;
    }
    var $td = $(this),
      colKey = $td.data("col"),
      $tr = $td.closest("tr"),
      itemId = $tr.data("item-id");
    if (!itemId) return;
    var colDef = COLUMNS.find(function (c) {
      return c.key === colKey;
    });
    if (!colDef || !colDef.editable) {
      showToast("Cell uneditable – This data can't be edited.");
      return;
    }
    var item = allItems.find(function (it) {
      return it.id === itemId;
    });
    if (!item) return;
    if (!canEditCellByPermission(item, colKey)) {
      showToast("Cell uneditable â€“ This data can't be edited.");
      return;
    }
    e.stopPropagation();
    closeAllDropdowns();

    if (colDef.editable === "popup") {
      openItemDetailModal(item);
      return;
    }
    if (colDef.editable === "text") startTextEdit($td, item, colKey);
    else if (colDef.editable === "work_type") showWorkTypeDropdown($td, item);
    else if (
      colDef.editable === "user_assignee" ||
      colDef.editable === "user_reporter"
    )
      showUserDropdown(
        $td,
        item,
        colDef.editable === "user_assignee" ? "assignee" : "reporter",
      );
    else if (colDef.editable === "priority") showPriorityDropdown($td, item);
    else if (colDef.editable === "date") startDateEdit($td, item, colKey);
    else if (colDef.editable === "status") showStatusDropdown($td, item);
    else if (colDef.editable === "labels") showLabelsDropdown($td, item);
    else if (colDef.editable === "amen_time")
      showAmenTimeDropdown($td, item, colKey);
    else if (colDef.editable === "parent") showParentDropdown($td, item);
    else if (colDef.editable === "board_status")
      showBoardStatusDropdown($td, item);
  });

  /* --- text edit --- */
  function startTextEdit($td, item, colKey) {
    if ($td.hasClass("sheets-cell-editing")) return;
    $td.addClass("sheets-cell-editing");
    var currentVal = "";
    if (colKey === "title") currentVal = item.title || "";
    else if (colKey === "original_estimate")
      currentVal =
        (item.original_estimate_value || 0) +
        " " +
        (item.original_estimate_unit || "minutes");
    var $input = $('<input type="text" value="">');
    $input.val(currentVal);
    $td.html($input);
    $input.focus().select();

    function save() {
      var newVal = $input.val().trim();
      $td.removeClass("sheets-cell-editing");
      if (colKey === "title") {
        if (!newVal || newVal === item.title) {
          renderCellInPlace($td, item, colKey);
          return;
        }
        saveField(
          item,
          "update_item_core",
          { title: newVal, description: item.description || "" },
          function (resp) {
            if (resp && resp.ok) item.title = newVal;
            renderCellInPlace($td, item, colKey);
          },
        );
      } else if (colKey === "original_estimate") {
        var parts = newVal.match(/^(\d+)\s*(minutes?|hours?|days?|weeks?)?$/i);
        var estVal = 0,
          estUnit = "minutes";
        if (parts) {
          estVal = parseInt(parts[1], 10);
          estUnit = parts[2]
            ? parts[2].toLowerCase().replace(/s$/, "")
            : "minutes";
          if (["minute", "hour", "day", "week"].indexOf(estUnit) === -1)
            estUnit = "minutes";
          else estUnit += "s";
        }
        if (!ensureColumnActionAllowed(item, "original_estimate", estVal)) {
          renderCellInPlace($td, item, colKey);
          return;
        }
        saveItemDetail(
          item,
          { original_estimate_value: estVal, original_estimate_unit: estUnit },
          function () {
            item.original_estimate_value = estVal;
            item.original_estimate_unit = estUnit;
            renderCellInPlace($td, item, colKey);
          },
        );
      } else {
        renderCellInPlace($td, item, colKey);
      }
    }

    $input.on("blur", save);
    $input.on("keydown", function (ev) {
      if (ev.key === "Enter") {
        ev.preventDefault();
        $input.blur();
      }
      if (ev.key === "Escape") {
        $td.removeClass("sheets-cell-editing");
        renderCellInPlace($td, item, colKey);
      }
    });
  }

  /* --- date edit --- */
  function startDateEdit($td, item, colKey) {
    if ($td.hasClass("sheets-cell-editing")) return;
    $td.addClass("sheets-cell-editing");
    var currentVal = item[colKey] || "";
    var $input = $('<input type="date">');
    $input.val(currentVal);
    $td.html($input);
    $input.focus();

    function save() {
      var newVal = $input.val();
      $td.removeClass("sheets-cell-editing");
      if (newVal === (item[colKey] || "")) {
        renderCellInPlace($td, item, colKey);
        return;
      }
      if (!ensureColumnActionAllowed(item, colKey, newVal)) {
        renderCellInPlace($td, item, colKey);
        return;
      }
      var payload = {};
      payload[colKey] = newVal;
      saveItemDetail(item, payload, function () {
        item[colKey] = newVal;
        renderCellInPlace($td, item, colKey);
      });
    }

    $input.on("blur", save);
    $input.on("change", function () {
      $input.blur();
    });
    $input.on("keydown", function (ev) {
      if (ev.key === "Escape") {
        $td.removeClass("sheets-cell-editing");
        renderCellInPlace($td, item, colKey);
      }
    });
  }

  /* --- work type dropdown --- */
  function showWorkTypeDropdown($td, item) {
    var offset = $td.offset();
    var $dd = $('<div class="sheets-wt-dropdown"></div>');
    $dd.append(
      '<input type="text" class="sheets-wt-search" placeholder="Find...">',
    );
    workTypes.forEach(function (wt) {
      $dd.append(
        '<div class="sheets-wt-option' +
          (wt.id === item.work_type_id ? " selected" : "") +
          '" data-wt-id="' +
          wt.id +
          '">' +
          workTypeIconImg(wt.svg_icon, wt.name) +
          "<span>" +
          esc(wt.name) +
          "</span></div>",
      );
    });
    $dd.css({ top: offset.top + $td.outerHeight(), left: offset.left });
    $("body").append($dd);
    $dd.find(".sheets-wt-search").on("input", function () {
      var q = $(this).val().toLowerCase();
      $dd.find(".sheets-wt-option").each(function () {
        $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
      });
    });
    $dd.on("click", ".sheets-wt-option", function () {
      var wtId = parseInt($(this).data("wt-id"), 10);
      if (wtId === item.work_type_id) {
        closeAllDropdowns();
        return;
      }
      saveField(
        item,
        "set_item_work_type",
        { work_type_id: wtId },
        function (resp) {
          if (resp && resp.ok) {
            item.work_type_id = wtId;
            var wt = getWorkType(wtId);
            item.work_type_name = wt.name;
            item.work_type_svg_icon = wt.svg_icon;
            renderCellInPlace($td, item, "work_type");
          }
        },
      );
      closeAllDropdowns();
    });
  }

  /* --- user dropdown --- */
  function showUserDropdown($td, item, field) {
    var offset = $td.offset();
    var currentId =
      field === "assignee" ? item.assignee_user_id : item.reporter_user_id;
    var $dd = $('<div class="sheets-user-dropdown"></div>');
    $dd.append(
      '<input type="text" class="sheets-user-search" placeholder="Find...">',
    );
    $dd.append(
      '<div class="sheets-user-option' +
        (currentId === 0 ? " selected" : "") +
        '" data-user-id="0">Unassigned</div>',
    );
    assignees.forEach(function (u) {
      $dd.append(
        '<div class="sheets-user-option' +
          (u.id === currentId ? " selected" : "") +
          '" data-user-id="' +
          u.id +
          '">' +
          esc(u.name) +
          "</div>",
      );
    });
    $dd.css({ top: offset.top + $td.outerHeight(), left: offset.left });
    $("body").append($dd);
    $dd.find(".sheets-user-search").on("input", function () {
      var q = $(this).val().toLowerCase();
      $dd.find(".sheets-user-option").each(function () {
        $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
      });
    });
    $dd.on("click", ".sheets-user-option", function () {
      var userId = parseInt($(this).data("user-id"), 10),
        payload = {};
      if (field === "assignee") {
        if (!ensureColumnActionAllowed(item, "assignee_name", userId)) {
          closeAllDropdowns();
          return;
        }
        payload.assignee_user_id = userId;
        saveItemDetail(item, payload, function () {
          item.assignee_user_id = userId;
          item.assignee_name = userId > 0 ? getUserName(userId) : "";
          renderCellInPlace($td, item, "assignee_name");
        });
      } else {
        if (!ensureColumnActionAllowed(item, "reporter_name", userId)) {
          closeAllDropdowns();
          return;
        }
        payload.reporter_user_id = userId;
        saveItemDetail(item, payload, function () {
          item.reporter_user_id = userId;
          item.reporter_name = userId > 0 ? getUserName(userId) : "";
          renderCellInPlace($td, item, "reporter_name");
        });
      }
      closeAllDropdowns();
    });
  }

  /* --- priority dropdown --- */
  function showPriorityDropdown($td, item) {
    var offset = $td.offset();
    var $dd = $('<div class="sheets-priority-dropdown"></div>');
    PRIORITIES.forEach(function (p) {
      $dd.append(
        '<div class="sheets-priority-option" data-priority="' +
          p +
          '">' +
          priorityIcon(p) +
          " " +
          esc(p) +
          "</div>",
      );
    });
    $dd.css({ top: offset.top + $td.outerHeight(), left: offset.left });
    $("body").append($dd);
    $dd.on("click", ".sheets-priority-option", function () {
      var newP = $(this).data("priority");
      if (newP === item.priority) {
        closeAllDropdowns();
        return;
      }
      if (!ensureColumnActionAllowed(item, "priority", newP)) {
        closeAllDropdowns();
        return;
      }
      saveItemDetail(item, { priority: newP }, function () {
        item.priority = newP;
        renderCellInPlace($td, item, "priority");
      });
      closeAllDropdowns();
    });
  }

  /* --- status dropdown --- */
  function showStatusDropdown($td, item) {
    var offset = $td.offset();
    var currentIds = (item.task_status || "")
      .split(",")
      .map(function (x) {
        return parseInt(x, 10);
      })
      .filter(function (x) {
        return x > 0;
      });
    var $dd = $('<div class="sheets-wt-dropdown"></div>');
    $dd.append(
      '<input type="text" class="sheets-wt-search" placeholder="Find...">',
    );
    var $opts = $('<div style="max-height:200px;overflow-y:auto"></div>');
    statusLabels.forEach(function (sl) {
      $opts.append(
        '<label class="sheets-wt-option" style="cursor:pointer"><input type="checkbox" value="' +
          sl.id +
          '"' +
          (currentIds.indexOf(sl.id) !== -1 ? " checked" : "") +
          "> " +
          esc(sl.name) +
          "</label>",
      );
    });
    $dd.append($opts);
    $dd.append(
      '<div style="padding:6px 12px;text-align:right"><button class="btn-apply sheets-status-apply" style="font-size:12px;padding:3px 12px;border:none;background:#0c66e4;color:#fff;border-radius:4px;cursor:pointer">Apply</button></div>',
    );
    $dd.css({ top: offset.top + $td.outerHeight(), left: offset.left });
    $("body").append($dd);
    $dd.find(".sheets-wt-search").on("input", function () {
      var q = $(this).val().toLowerCase();
      $opts.find(".sheets-wt-option").each(function () {
        $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
      });
    });
    $dd.on("click", ".sheets-status-apply", function () {
      var sel = [];
      $dd.find("input[type=checkbox]:checked").each(function () {
        sel.push(parseInt($(this).val(), 10));
      });
      var csv = sel.join(",");
      if (!ensureColumnActionAllowed(item, "task_status", csv)) {
        closeAllDropdowns();
        return;
      }
      saveItemDetail(item, { task_status_label_ids: csv }, function () {
        item.task_status = csv;
        renderCellInPlace($td, item, "task_status");
      });
      closeAllDropdowns();
    });
  }

  /* --- labels dropdown --- */
  function showLabelsDropdown($td, item) {
    var offset = $td.offset();
    var currentIds = (item.labels || []).map(function (l) {
      return l.id;
    });
    var $dd = $('<div class="sheets-wt-dropdown"></div>');
    $dd.append(
      '<input type="text" class="sheets-wt-search" placeholder="Find...">',
    );
    var $opts = $('<div style="max-height:200px;overflow-y:auto"></div>');
    labels.forEach(function (l) {
      $opts.append(
        '<label class="sheets-wt-option" style="cursor:pointer"><input type="checkbox" value="' +
          l.id +
          '"' +
          (currentIds.indexOf(l.id) !== -1 ? " checked" : "") +
          "> " +
          esc(l.name) +
          "</label>",
      );
    });
    $dd.append($opts);
    $dd.append(
      '<div style="padding:6px 12px;text-align:right"><button class="btn-apply sheets-labels-apply" style="font-size:12px;padding:3px 12px;border:none;background:#0c66e4;color:#fff;border-radius:4px;cursor:pointer">Apply</button></div>',
    );
    $dd.css({ top: offset.top + $td.outerHeight(), left: offset.left });
    $("body").append($dd);
    $dd.find(".sheets-wt-search").on("input", function () {
      var q = $(this).val().toLowerCase();
      $opts.find(".sheets-wt-option").each(function () {
        $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
      });
    });
    $dd.on("click", ".sheets-labels-apply", function () {
      var sel = [];
      $dd.find("input[type=checkbox]:checked").each(function () {
        sel.push(parseInt($(this).val(), 10));
      });
      if (!ensureColumnActionAllowed(item, "labels", sel)) {
        closeAllDropdowns();
        return;
      }
      saveField(
        item,
        "set_item_labels",
        { label_ids: sel.join(",") },
        function (resp) {
          if (resp && resp.ok) {
            item.labels = sel.map(function (id) {
              var f = labels.find(function (l) {
                return l.id === id;
              });
              return f || { id: id, name: "Label" };
            });
            renderCellInPlace($td, item, "labels");
          }
        },
      );
      closeAllDropdowns();
    });
  }

  /* --- amendement time dropdown --- */
  function showAmenTimeDropdown($td, item, colKey) {
    var offset = $td.offset(),
      currentVal = item[colKey] || 0;
    var $dd = $('<div class="sheets-priority-dropdown"></div>');
    $dd.append('<div class="sheets-priority-option" data-val="0">None</div>');
    AMEN_TIME_OPTIONS.forEach(function (m) {
      $dd.append(
        '<div class="sheets-priority-option' +
          (m === currentVal ? " selected" : "") +
          '" data-val="' +
          m +
          '">' +
          m +
          " min</div>",
      );
    });
    $dd.css({ top: offset.top + $td.outerHeight(), left: offset.left });
    $("body").append($dd);
    $dd.on("click", ".sheets-priority-option", function () {
      var val = parseInt($(this).data("val"), 10),
        payload = {};
      if (!ensureColumnActionAllowed(item, colKey, val)) {
        closeAllDropdowns();
        return;
      }
      payload[colKey] = val;
      saveItemDetail(item, payload, function () {
        item[colKey] = val;
        renderCellInPlace($td, item, colKey);
      });
      closeAllDropdowns();
    });
  }

  /* --- parent dropdown --- */
  function showParentDropdown($td, item) {
    var offset = $td.offset();
    var currentParentId = item.parent_item_id || 0;
    var $dd = $('<div class="sheets-wt-dropdown"></div>');
    $dd.append(
      '<input type="text" class="sheets-wt-search" placeholder="Search work items...">',
    );
    var $opts = $('<div style="max-height:220px;overflow-y:auto"></div>');
    $opts.append(
      '<div class="sheets-wt-option' +
        (currentParentId === 0 ? " selected" : "") +
        '" data-parent-id="0"><i class="fa-solid fa-ban" style="color:#8993a4;margin-right:4px"></i> None</div>',
    );
    // Only show epics as potential parents
    allItems.forEach(function (it) {
      if (it.id === item.id) return;
      var wtName = (it.work_type_name || "Task").toLowerCase();
      if (wtName !== "epic") return;
      var key = it.work_item_key || buildWorkItemKey(it.id);
      $opts.append(
        '<div class="sheets-wt-option' +
          (it.id === currentParentId ? " selected" : "") +
          '" data-parent-id="' +
          it.id +
          '">' +
          workTypeIconImg(it.work_type_svg_icon, it.work_type_name) +
          "<span>" +
          esc(key) +
          " " +
          esc(it.title) +
          "</span></div>",
      );
    });
    $dd.append($opts);
    $dd.css({ top: offset.top + $td.outerHeight(), left: offset.left });
    $("body").append($dd);
    $dd.find(".sheets-wt-search").on("input", function () {
      var q = $(this).val().toLowerCase();
      $dd.find(".sheets-wt-option").each(function () {
        $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
      });
    });
    $dd.on("click", ".sheets-wt-option", function () {
      var parentId = parseInt($(this).data("parent-id"), 10);
      if (parentId === currentParentId) {
        closeAllDropdowns();
        return;
      }
      if (!ensureColumnActionAllowed(item, "parent_display", parentId)) {
        closeAllDropdowns();
        return;
      }
      saveField(
        item,
        "set_item_parent",
        { parent_item_id: parentId },
        function (resp) {
          if (resp && resp.ok) {
            item.parent_item_id = parentId;
            renderCellInPlace($td, item, "parent_display");
          }
        },
      );
      closeAllDropdowns();
    });
  }

  /* --- board status dropdown (move item to column) --- */
  function showBoardStatusDropdown($td, item) {
    var offset = $td.offset();
    var currentColId = item.column_id || 0;
    var $dd = $('<div class="sheets-wt-dropdown"></div>');
    $dd.append(
      '<input type="text" class="sheets-wt-search" placeholder="Find status...">',
    );
    var $opts = $('<div style="max-height:220px;overflow-y:auto"></div>');
    columns.forEach(function (col) {
      $opts.append(
        '<div class="sheets-wt-option' +
          (col.id === currentColId ? " selected" : "") +
          '" data-col-id="' +
          col.id +
          '">' +
          esc(col.name) +
          "</div>",
      );
    });
    $dd.append($opts);
    $dd.css({ top: offset.top + $td.outerHeight(), left: offset.left });
    $("body").append($dd);
    $dd.find(".sheets-wt-search").on("input", function () {
      var q = $(this).val().toLowerCase();
      $dd.find(".sheets-wt-option").each(function () {
        $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
      });
    });
    $dd.on("click", ".sheets-wt-option", function () {
      var targetColId = parseInt($(this).data("col-id"), 10);
      if (targetColId === currentColId) {
        closeAllDropdowns();
        return;
      }
      saveField(
        item,
        "change_item_status",
        { target_column_id: targetColId },
        function (resp) {
          if (resp && resp.ok) {
            item.column_id = targetColId;
            item.column_name = getColumnName(targetColId);
            renderCellInPlace($td, item, "board_status");
          }
        },
      );
      closeAllDropdowns();
    });
  }

  /* ───── save helpers ───── */

  function saveField(item, action, extra, callback) {
    $.ajax({
      url: ajaxUrl,
      method: "POST",
      data: $.extend(
        { task_action: action, csrf_token: csrfToken, item_id: item.id },
        extra,
      ),
      dataType: "json",
      success: function (resp) {
        if (callback) callback(resp);
      },
      error: function () {
        showToast("Failed to save. Please try again.");
      },
    });
  }

  function saveItemDetail(item, fields, callback) {
    var data = {
      task_action: "update_item_detail",
      csrf_token: csrfToken,
      item_id: item.id,
      assignee_user_id:
        fields.assignee_user_id !== undefined
          ? fields.assignee_user_id
          : item.assignee_user_id,
      reporter_user_id:
        fields.reporter_user_id !== undefined
          ? fields.reporter_user_id
          : item.reporter_user_id,
      priority: fields.priority !== undefined ? fields.priority : item.priority,
      original_estimate_value:
        fields.original_estimate_value !== undefined
          ? fields.original_estimate_value
          : item.original_estimate_value,
      original_estimate_unit:
        fields.original_estimate_unit !== undefined
          ? fields.original_estimate_unit
          : item.original_estimate_unit,
      task_status_label_ids:
        fields.task_status_label_ids !== undefined
          ? fields.task_status_label_ids
          : item.task_status || "",
      start_date:
        fields.start_date !== undefined
          ? fields.start_date
          : item.start_date || "",
      due_date:
        fields.due_date !== undefined ? fields.due_date : item.due_date || "",
      amendement_date:
        fields.amendement_date !== undefined
          ? fields.amendement_date
          : item.amendement_date || "",
      amendement_time_minutes:
        fields.amendement_time_minutes !== undefined
          ? fields.amendement_time_minutes
          : item.amendement_time_minutes || 0,
      second_amendement_date:
        fields.second_amendement_date !== undefined
          ? fields.second_amendement_date
          : item.second_amendement_date || "",
      second_amendement_time_minutes:
        fields.second_amendement_time_minutes !== undefined
          ? fields.second_amendement_time_minutes
          : item.second_amendement_time_minutes || 0,
    };
    $.ajax({
      url: ajaxUrl,
      method: "POST",
      data: data,
      dataType: "json",
      success: function (resp) {
        if (resp && resp.ok && resp.detail)
          Object.keys(resp.detail).forEach(function (k) {
            item[k] = resp.detail[k];
          });
        if (callback) callback(resp);
      },
      error: function () {
        showToast("Failed to save. Please try again.");
      },
    });
  }

  function renderCellInPlace($td, item, colKey) {
    var colDef = COLUMNS.find(function (c) {
      return c.key === colKey;
    });
    if (colDef) $td.html(renderCellContent(item, colDef));
  }

  /* ───── open detail modal ───── */

  function buildBoardModalCardMock(item) {
    function escAttr(value) {
      return esc(value).replace(/"/g, "&quot;");
    }

    var cardHtml =
      '<article class="task-item-card" data-item-id="' +
      Number(item.id || 0) +
      '" data-status-column-id="' +
      Number(item.column_id || 0) +
      '" data-work-type-id="' +
      Number(item.work_type_id || 0) +
      '" data-work-type-name="' +
      escAttr(item.work_type_name || "Task") +
      '" data-work-type-icon="' +
      escAttr(item.work_type_svg_icon || "") +
      '" data-work-item-key="' +
      escAttr(item.work_item_key || "") +
      '" data-item-description="' +
      escAttr(item.description || "") +
      '" data-priority="' +
      escAttr(item.priority || "Medium") +
      '" data-assignee-user-id="' +
      Number(item.assignee_user_id || 0) +
      '" data-assignee-name="' +
      escAttr(item.assignee_name || "Unassigned") +
      '" data-reporter-user-id="' +
      Number(item.reporter_user_id || 0) +
      '" data-reporter-name="' +
      escAttr(item.reporter_name || "") +
      '" data-parent-item-id="' +
      Number(item.parent_item_id || 0) +
      '">' +
      '<div class="task-item-title">' +
      esc(item.title || "") +
      "</div></article>";

    return $(cardHtml);
  }

  function openItemDetailModal(item) {
    if (typeof window.openItemDetailModal === "function") {
      var $mockCard = buildBoardModalCardMock(item);
      window.openItemDetailModal($mockCard);
      return;
    }

    var boardUrl =
      ajaxUrl.replace(/\?.*$/, "") +
      "?project_id=" +
      String(currentProjectId > 0 ? currentProjectId : 0) +
      "&open_item=" +
      item.id;
    window.location.href = boardUrl;
  }

  /* ───── bulk edit selection handlers ───── */
  $(document).on("change", ".sheets-row-check", function () {
    var id = Number($(this).data("item-id")) || 0;
    if ($(this).is(":checked")) {
      if (selectedItemIds.indexOf(id) === -1) selectedItemIds.push(id);
    } else {
      selectedItemIds = selectedItemIds.filter(function (selectedId) {
        return selectedId !== id;
      });
    }
    updateBulkToolbarState();
  });

  $(document).on("change", "#sheetsSelectAllCheck", function () {
    var checked = $(this).is(":checked");
    var visibleIds = displayItems.map(function (item) {
      return Number(item.id);
    });
    if (checked) {
      visibleIds.forEach(function (id) {
        if (selectedItemIds.indexOf(id) === -1) selectedItemIds.push(id);
      });
    } else {
      selectedItemIds = selectedItemIds.filter(function (id) {
        return visibleIds.indexOf(id) === -1;
      });
    }
    renderTable();
  });

  $(document).on("click", "#sheetsBulkEditBtn", function (e) {
    e.preventDefault();
    openBulkEditModal();
  });

  $(document).on("click", ".sheets-cell-key", function (e) {
    e.preventDefault();
    e.stopPropagation();
    var $tr = $(this).closest("tr");
    var itemId = Number($tr.data("item-id")) || 0;
    if (!itemId) return;
    var item = allItems.find(function (it) {
      return Number(it.id || 0) === itemId;
    });
    if (!item) return;
    openItemDetailModal(item);
  });

  /* ───── close dropdowns on outside click ───── */
  $(document).on("click", function (e) {
    if (
      !$(e.target).closest(
        ".sheets-wt-dropdown, .sheets-user-dropdown, .sheets-priority-dropdown",
      ).length
    )
      closeAllDropdowns();
  });

  /* ───── init ───── */

  $(function () {
    loadColumnsFromConfig();
    loadViewPrefs();
    allItems = flattenItems();
    $(".sheets-btn-sum").toggleClass("active", showSummaryRow);

    // restore assignee filter dropdown
    if (toolbarAssigneeFilter) {
      $("#sheetsAssigneeFilter").val(toolbarAssigneeFilter);
    }

    renderTable();
    $(".sheets-btn-refresh").on("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      refreshData();
      return false;
    });
    $(".sheets-btn-sum").on("click", toggleSummary);

    // assignee filter change
    $("#sheetsAssigneeFilter").on("change", function () {
      toolbarAssigneeFilter = $(this).val();
      saveViewPrefs();
      renderTable();
    });
  });
})(jQuery);
