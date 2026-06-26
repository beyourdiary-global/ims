"use strict";

if (typeof jQuery === "undefined") {
  console.error("task_board_core.js requires jQuery");
}

var $ = window.jQuery;

function escHtml(text) {
  return String(text || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function activityValueHasRichContent(fieldName, value) {
  if (
    String(fieldName || "")
      .trim()
      .toLowerCase() !== "description"
  ) {
    return false;
  }

  var $tmp = $("<div>").html(String(value || ""));
  var text = String($tmp.text() || "")
    .replace(/\u00a0/g, " ")
    .replace(/\s+/g, " ")
    .trim();

  if (text) {
    return true;
  }

  return $tmp.find("img,video,audio,iframe,object,embed,table,a").length > 0;
}

function buildActivityValueHtml(fieldName, value) {
  var normalizedValue = String(value || "").trim();
  if (!normalizedValue) {
    return '<span class="task-item-activity-badge">None</span>';
  }

  if (activityValueHasRichContent(fieldName, normalizedValue)) {
    return (
      '<div class="task-item-activity-rich-value">' + normalizedValue + "</div>"
    );
  }

  return (
    '<span class="task-item-activity-badge">' +
    escHtml(normalizedValue) +
    "</span>"
  );
}

function initials(name) {
  var value = String(name || "").trim();
  if (!value) {
    return "U";
  }

  var parts = value.split(/\s+/);
  var out = "";
  for (var i = 0; i < parts.length; i++) {
    if (parts[i]) {
      out += parts[i].charAt(0).toUpperCase();
    }
    if (out.length >= 2) {
      break;
    }
  }

  return out || "U";
}

var cfg = window.taskBoardConfig || {};
var ajaxUrl = cfg.ajaxUrl || "";
var csrfToken = String(cfg.csrfToken || "");
var canAdd = !!cfg.canAdd;
var canEdit = !!cfg.canEdit;
var canDelete = !!cfg.canDelete;
var state = {
  siteUrl: String(cfg.siteUrl || "").replace(/\/+$/, ""),
  currentProjectId: Number(cfg.currentProjectId || 0),
  isProjectOwner: !!cfg.isProjectOwner,
  currentProject:
    cfg.currentProject && typeof cfg.currentProject === "object"
      ? cfg.currentProject
      : {},
  projectKey:
    cfg.projectKey && typeof cfg.projectKey === "object"
      ? cfg.projectKey
      : { id: 0, project_key: "" },
  workTypes: Array.isArray(cfg.workTypes) ? cfg.workTypes.slice() : [],
  workTypeIcons: Array.isArray(cfg.workTypeIcons)
    ? cfg.workTypeIcons.slice()
    : [],
  assignees: Array.isArray(cfg.assignees) ? cfg.assignees.slice() : [],
  labels: Array.isArray(cfg.labels) ? cfg.labels.slice() : [],
  statusLabels: Array.isArray(cfg.statusLabels) ? cfg.statusLabels.slice() : [],
  linkRelationTypes: Array.isArray(cfg.linkRelationTypes)
    ? cfg.linkRelationTypes.slice()
    : [],
  columns: Array.isArray(cfg.columns) ? cfg.columns.slice() : [],
  allowedWorkTypeIds: Array.isArray(cfg.allowedWorkTypeIds)
    ? cfg.allowedWorkTypeIds.slice()
    : [],
  allowedStatusIds: Array.isArray(cfg.allowedStatusIds)
    ? cfg.allowedStatusIds.slice()
    : [],
  columnPermissions:
    cfg.columnPermissions && typeof cfg.columnPermissions === "object"
      ? cfg.columnPermissions
      : {},
};

function isTouchBoardViewport() {
  var viewportWidth =
    window.innerWidth || document.documentElement.clientWidth || 0;
  var hasTouch = false;

  if (typeof window.matchMedia === "function") {
    try {
      hasTouch =
        window.matchMedia("(pointer: coarse)").matches ||
        window.matchMedia("(hover: none)").matches;
    } catch (e) {}
  }

  if (!hasTouch && typeof navigator !== "undefined") {
    hasTouch = Number(navigator.maxTouchPoints || 0) > 0;
  }

  return hasTouch && viewportWidth > 0 && viewportWidth <= 991.98;
}

function normalizeProjectPermissionFieldKey(fieldKey) {
  var key = String(fieldKey || "")
    .trim()
    .toLowerCase();

  if (key === "parent") {
    return "parent_display";
  }
  if (key === "assignee") {
    return "assignee_name";
  }
  if (key === "reporter") {
    return "reporter_name";
  }
  if (key === "amendement_time") {
    return "amendement_time_minutes";
  }
  if (key === "second_amendement_time") {
    return "second_amendement_time_minutes";
  }

  return key;
}

function getProjectColumnPermission(fieldKey) {
  var key = normalizeProjectPermissionFieldKey(fieldKey);
  var row = state.columnPermissions[key];
  if (!row || typeof row !== "object") {
    return { add: 0, edit: 0, delete: 0 };
  }

  return {
    add: Number(row.add || 0) > 0 ? 1 : 0,
    edit: Number(row.edit || 0) > 0 ? 1 : 0,
    delete: Number(row.delete || 0) > 0 ? 1 : 0,
  };
}

function hasAnyProjectFieldPermission(fieldKey) {
  if (state.isProjectOwner) {
    return true;
  }

  var permission = getProjectColumnPermission(fieldKey);
  return !!(permission.add || permission.edit || permission.delete);
}

function canTargetStatusColumn(columnId) {
  var id = Number(columnId || 0);
  if (id <= 0) {
    return false;
  }

  if (state.isProjectOwner) {
    return true;
  }

  return state.allowedStatusIds.indexOf(id) !== -1;
}

function normalizeLabelColor(color, fallback) {
  return normalizeHexColorValue(color || "", fallback || "#DCE8FF");
}

function labelTextColor(color) {
  return getReadableTextColor(normalizeLabelColor(color, "#DCE8FF"));
}

function labelPillStyle(color, fallback) {
  var bg = normalizeLabelColor(color, fallback || "#DCE8FF");
  return (
    '--task-label-bg:' +
    escHtml(bg) +
    ';--task-label-text:' +
    escHtml(labelTextColor(bg))
  );
}

function getLabelById(labelId) {
  var id = Number(labelId || 0);
  for (var i = 0; i < state.labels.length; i++) {
    var label = state.labels[i] || {};
    if (Number(label.id || 0) === id) {
      return label;
    }
  }
  return null;
}

function getStatusLabelById(labelId) {
  var id = Number(labelId || 0);
  for (var i = 0; i < state.statusLabels.length; i++) {
    var label = state.statusLabels[i] || {};
    if (Number(label.id || 0) === id) {
      return label;
    }
  }
  return null;
}

var dragState = {
  $item: null,
  $sourceList: null,
  $sourceNext: null,
};

var $layout = $("#taskModuleLayout");
var $sidebarToggle = $("#taskSidebarToggle");
var $sidebarBackdrop = $("#taskSidebarBackdrop");
var $taskTopMenuTrigger = $(".task-top-menu-trigger");
var sidebarStorageKey = "task_module_sidebar_open";

function isMobile() {
  return window.matchMedia("(max-width: 991.98px)").matches;
}

function setSidebar(open) {
  if (!$layout.length) {
    return;
  }

  $layout.toggleClass("task-sidebar-open", open);
  $layout.toggleClass("task-sidebar-closed", !open);

  if (open && isMobile()) {
    $sidebarBackdrop.show();
  } else {
    $sidebarBackdrop.hide();
  }

  var canShiftTopMenu =
    !isMobile() && !$("body").hasClass("task-global-sidebar-enabled");
  $("body").toggleClass("task-local-sidebar-open", !!open && canShiftTopMenu);

  try {
    window.localStorage.setItem(sidebarStorageKey, open ? "1" : "0");
  } catch (e) { }
}

if ($layout.length) {
  var hasGlobalTaskSidebar = $("#taskGlobalSidebar").length > 0;
  var shouldOpen = true;
  if (hasGlobalTaskSidebar) {
    shouldOpen = false;
  }
  try {
    shouldOpen = window.localStorage.getItem(sidebarStorageKey) !== "0";
  } catch (e) { }

  if (hasGlobalTaskSidebar) {
    shouldOpen = false;
  }

  setSidebar(shouldOpen);

  if ($taskTopMenuTrigger.length && !hasGlobalTaskSidebar) {
    $taskTopMenuTrigger.on("click", function (e) {
      if (!isMobile()) {
        e.preventDefault();
        setSidebar(!$layout.hasClass("task-sidebar-open"));
      }
    });
  }

  if ($sidebarToggle.length) {
    $sidebarToggle.on("click", function () {
      setSidebar(!$layout.hasClass("task-sidebar-open"));
    });
  }

  $sidebarBackdrop.on("click", function () {
    setSidebar(false);
  });

  $(window).on("resize", function () {
    if (!isMobile()) {
      $sidebarBackdrop.hide();
    } else if ($layout.hasClass("task-sidebar-open")) {
      $sidebarBackdrop.show();
    }

    setSidebar($layout.hasClass("task-sidebar-open"));
  });
}

var $app = $("#taskBoardApp");
if (!$app.length) {
  console.warn("task_board_core: #taskBoardApp not found.");
}

var labelsPanelState = {
  itemId: 0,
  selected: [],
};

var statusLabelsPanelState = {
  itemId: 0,
  selected: [],
};

var workTypeModalState = {
  mode: "add",
  workTypeId: 0,
  iconPath: "",
  initialIconPath: "",
  composerEl: null,
};

var itemDetailModalState = {
  itemId: 0,
  cardEl: null,
  initialTitle: "",
  initialDescription: "",
  titleEditing: false,
  descriptionEditing: false,
  initialSaveSnapshot: "",
  lastSavedCoreSnapshot: "",
  lastSavedDetailSnapshot: "",
  lastSavedLabelsSnapshot: "",
  attachments: [],
  attachmentSort: {
    field: "date",
    direction: "desc",
  },
  attachmentsCollapsed: false,
  attachmentView: "list",
  showAttachmentPanelWhenEmpty: false,
  pendingAttachmentPicker: false,
  coreAutosaveTimer: 0,
  detailAutosaveTimer: 0,
  coreSaveInFlight: false,
  detailSaveInFlight: false,
  labelsSaveInFlight: false,
  queuedCoreSave: false,
  queuedDetailSave: false,
  queuedLabelsSave: false,
  selectedPriority: "Medium",
  detailStatusColumnId: 0,
  selectedStatusLabelIds: [],
  selectedLabelIds: [],
  comments: [],
  commentsLoading: false,
  worklogs: [],
  worklogsLoading: false,
  parentItemId: 0,
  parentOptions: [],
  webLinks: [],
  webLinkEditorOpen: false,
  detailsCollapsed: false,
  workTypeName: "Task",
  workTypeIcon: "",
  workItemKey: "",
  parentWorkItemKey: "",
  parentWorkTypeName: "Task",
  parentWorkTypeIcon: "",
  timeTracking: {
    ownText: "No time logged",
    ownSeconds: 0,
    ownRemainingSeconds: 0,
    ownEstimateSeconds: 0,
    childText: "No time logged",
    childSeconds: 0,
    childRemainingSeconds: 0,
    canIncludeChild: false,
    includeChild: false,
  },
  childWorkItems: {
    items: [],
    total: 0,
    done: 0,
    progress_percent: 0,
  },
  isParentType: false,
  childCreatePanelOpen: false,
  childCreateMode: "create",
  childCreateSelectedItemId: 0,
  childCreateSearchResults: [],
  childCreateSearchTimer: 0,
  itemLinks: {
    groups: [],
    total: 0,
  },
  linkEditorOpen: false,
  linkRelationType: "",
  linkSelectedItemId: 0,
  linkSearchResults: [],
  linkSearchTimer: 0,
  childTitleEditingItemId: 0,
  childPickerItemId: 0,
  childPickerField: "",
  childWorkItemsCollapsed: false,
  history: [],
  historyRequestSeq: 0,
  activityCollapsed: false,
  activityTab: "all",
  activitySortDirection: "desc",
  worklogRequestSeq: 0,
};

var statusModalState = {
  mode: "create",
  columnId: 0,
  initialName: "",
};

var worklogTimerState = {
  itemId: 0,
  elapsedSeconds: 0,
  running: false,
  startedAtMs: 0,
  collapsed: false,
};

var worklogTickerId = null;
var worklogStoragePrefix = "task_board_worklog_timer_v1_";

function nextItemDetailHistoryRequestSeq() {
  itemDetailModalState.historyRequestSeq =
    Number(itemDetailModalState.historyRequestSeq || 0) + 1;
  return itemDetailModalState.historyRequestSeq;
}

function nextItemDetailWorklogRequestSeq() {
  itemDetailModalState.worklogRequestSeq =
    Number(itemDetailModalState.worklogRequestSeq || 0) + 1;
  return itemDetailModalState.worklogRequestSeq;
}

var currentUserId = Number(cfg.currentUserId || 0);
var boardProjectId = Number(cfg.currentProjectId || 0);
var boardFilterCookiePrefix = "task_board_filters_v2_user_";
var boardFilterCookieName =
  boardFilterCookiePrefix +
  String(currentUserId > 0 ? currentUserId : 0) +
  "_project_" +
  String(boardProjectId > 0 ? boardProjectId : 0);
var boardViewFieldCookiePrefix = "task_board_view_fields_v1_user_";
var boardViewFieldCookieName =
  boardViewFieldCookiePrefix +
  String(currentUserId > 0 ? currentUserId : 0) +
  "_project_" +
  String(boardProjectId > 0 ? boardProjectId : 0);
var boardGroupCookiePrefix = "task_board_group_v1_user_";
var boardGroupCookieName =
  boardGroupCookiePrefix +
  String(currentUserId > 0 ? currentUserId : 0) +
  "_project_" +
  String(boardProjectId > 0 ? boardProjectId : 0);
var boardZoomStorageKeyPrefix = "task_board_zoom_v1_user_";
var boardZoomStorageKey =
  boardZoomStorageKeyPrefix +
  String(currentUserId > 0 ? currentUserId : 0) +
  "_project_" +
  String(boardProjectId > 0 ? boardProjectId : 0);
var boardZoomDefault = 90;
var boardZoomMin = 50;
var boardZoomMax = 120;
var boardZoomStep = 1;
var boardGroupBy = "status";
var boardStatusColumns = [];
var boardZoomPercent = boardZoomDefault;

var boardViewFieldDefaults = {
  work_item_key: true,
  work_type: true,
  labels: true,
  assignee: true,
  priority: true,
  reporter: true,
  due_date: true,
  created: true,
  updated: true,
  amendement_date: true,
  amendement_time: true,
  second_amendement_date: true,
  second_amendement_time: true,
  start_date: true,
  original_estimate: true,
  parent: true,
};

var boardViewFieldState = $.extend({}, boardViewFieldDefaults);

var boardFilterState = {
  activePart: "none",
  partA: {
    assignedToMe: false,
    dueThisWeek: false,
  },
  partB: {
    dateStart: "",
    dateDue: "",
    assigneeIds: [],
    createdFrom: "",
    createdTo: "",
    labelIds: [],
    parentIds: [],
    priorityValues: [],
    reporterIds: [],
    statusIds: [],
    updatedFrom: "",
    updatedTo: "",
    workTypeIds: [],
  },
  search: {
    label: "",
    parent: "",
  },
};

var taskPriorityValues = ["Highest", "High", "Medium", "Low", "Lowest"];
var boardSearchQuery = "";

function normalizeProjectKey(projectKey) {
  return String(projectKey || "")
    .trim()
    .toUpperCase()
    .replace(/\s+/g, "")
    .replace(/[^A-Z0-9\-]/g, "")
    .slice(0, 20);
}

function normalizeHexColorValue(color, fallback) {
  var value = String(color || "")
    .trim()
    .toUpperCase();
  var defaultColor = String(fallback || "#DFE1E6")
    .trim()
    .toUpperCase();

  if (/^#[0-9A-F]{6}$/.test(value)) {
    return value;
  }
  if (/^#[0-9A-F]{3}$/.test(value)) {
    return (
      "#" +
      value.charAt(1) +
      value.charAt(1) +
      value.charAt(2) +
      value.charAt(2) +
      value.charAt(3) +
      value.charAt(3)
    );
  }

  return /^#[0-9A-F]{6}$/.test(defaultColor) ? defaultColor : "#DFE1E6";
}

function getReadableTextColor(backgroundColor) {
  return "#292A2E";
}

function buildWorkItemKey(itemId) {
  var key = normalizeProjectKey(
    state.projectKey && state.projectKey.project_key
      ? state.projectKey.project_key
      : "",
  );
  var id = Number(itemId || 0);
  if (!key || id <= 0) {
    return "";
  }
  return key + "-" + id;
}

function defaultWorkTypeIconByName(name) {
  var key = String(name || "")
    .trim()
    .toLowerCase();
  if (key === "epic") {
    return "svg_icon/10307.svg";
  }
  return "svg_icon/10318.svg";
}

function normalizeWorkTypeIcon(iconPath, name) {
  var icons = Array.isArray(state.workTypeIcons) ? state.workTypeIcons : [];
  var fallback = defaultWorkTypeIconByName(name);
  var value = String(iconPath || "")
    .trim()
    .replace(/\\/g, "/");

  if (value && value.indexOf("svg_icon/") !== 0) {
    value = "svg_icon/" + value.split("/").pop();
  }

  if (value && icons.indexOf(value) !== -1) {
    return value;
  }

  if (icons.indexOf(fallback) !== -1) {
    return fallback;
  }

  if (icons.length) {
    return String(icons[0]);
  }

  return fallback;
}

function normalizeWorkTypeEntry(raw) {
  var item = raw || {};
  var name = String(item.name || "Task").trim() || "Task";
  return {
    id: Number(item.id || 0),
    name: name,
    remark: String(item.remark || "").trim(),
    svg_icon: normalizeWorkTypeIcon(item.svg_icon, name),
  };
}

function normalizeAllWorkTypes() {
  state.workTypes = (Array.isArray(state.workTypes) ? state.workTypes : []).map(
    function (item) {
      return normalizeWorkTypeEntry(item);
    },
  );
}

function workTypeIconHtml(iconPath, name, className) {
  return (
    '<img class="' +
    escHtml(className || "") +
    '" src="' +
    escHtml(normalizeWorkTypeIcon(iconPath, name)) +
    '" alt="">'
  );
}

function findWorkTypeById(workTypeId) {
  var id = Number(workTypeId || 0);
  for (var i = 0; i < state.workTypes.length; i++) {
    var type = normalizeWorkTypeEntry(state.workTypes[i]);
    if (Number(type.id || 0) === id) {
      return type;
    }
  }
  return null;
}

function setComposerWorkType($toggle, workType) {
  var type = normalizeWorkTypeEntry(workType || {});
  $toggle
    .attr("data-work-type-id", Number(type.id || 0))
    .attr("data-work-type-name", type.name)
    .attr("data-work-type-remark", type.remark)
    .attr("data-work-type-icon", type.svg_icon)
    .attr("title", type.name)
    .html(
      workTypeIconHtml(type.svg_icon, type.name, "task-work-type-toggle-icon"),
    );
}

normalizeAllWorkTypes();

function notify(message) {
  showNotification(message || "Operation completed.", "info");
}

function cleanupTaskDialogArtifacts() {
  $("#modal-confirm").remove();

  // Keep at most one backdrop for an existing modal (e.g. item detail modal).
  var $backdrops = $(".modal-backdrop");
  if ($backdrops.length > 1) {
    $backdrops.slice(1).remove();
  }

  var hasVisibleModal = document.querySelector(".modal.show");
  if (!hasVisibleModal) {
    $(".modal-backdrop").remove();
  }

  $("body").removeClass("modal-open").css({
    overflow: "",
    paddingRight: "",
  });

  if (hasVisibleModal) {
    $("body").addClass("modal-open").css("overflow", "hidden");
  }
}

function showConfirmationDialog(act, pagename, msg) {
  if (typeof window.confirmationDialog !== "function") {
    if (act === "NC") {
      notify("No changes were made.");
      return;
    }
    notify(pagename || "Operation completed.");
    return;
  }

  var dialogResult = window.confirmationDialog(
    "",
    Array.isArray(msg) ? msg : [],
    pagename || "",
    "",
    "",
    act,
  );

  // Run immediate cleanup passes so page is never left in a locked state.
  window.setTimeout(cleanupTaskDialogArtifacts, 0);
  window.setTimeout(cleanupTaskDialogArtifacts, 30);
  window.setTimeout(cleanupTaskDialogArtifacts, 120);

  // confirmationDialog sometimes leaves backdrop/body lock behind.
  if (dialogResult && typeof dialogResult.finally === "function") {
    dialogResult.finally(function () {
      window.setTimeout(cleanupTaskDialogArtifacts, 30);
      window.setTimeout(cleanupTaskDialogArtifacts, 120);
    });
  }

  window.setTimeout(cleanupTaskDialogArtifacts, 5200);
}

function showTaskSuccess(message) {
  return;
}

function showNoChangeMessage() {
  showBoardToast("No changes", normalizeToastMessage("No changes were made."));
}

function normalizeToastMessage(message) {
  var text = String(message || "").trim();
  return text || "Operation completed.";
}

function parseDateTimeToMs(dateValue, timeValue) {
  var dateText = String(dateValue || "").trim();
  if (!dateText) {
    return 0;
  }

  var fullText = dateText + (timeValue ? " " + String(timeValue) : "");
  var parsed = Date.parse(fullText.replace(/-/g, "/"));
  return Number.isNaN(parsed) ? 0 : parsed;
}

function formatRelativeTime(dateValue, timeValue) {
  var createdAtMs = parseDateTimeToMs(dateValue, timeValue);
  if (!createdAtMs) {
    return "";
  }

  var diffSec = Math.max(1, Math.floor((Date.now() - createdAtMs) / 1000));
  if (diffSec < 60) {
    return diffSec + " seconds ago";
  }

  var diffMin = Math.floor(diffSec / 60);
  if (diffMin < 60) {
    return diffMin + (diffMin === 1 ? " minute ago" : " minutes ago");
  }

  var diffHour = Math.floor(diffMin / 60);
  if (diffHour < 24) {
    return diffHour + (diffHour === 1 ? " hour ago" : " hours ago");
  }

  var diffDay = Math.floor(diffHour / 24);
  return diffDay + (diffDay === 1 ? " day ago" : " days ago");
}

function formatActivityDateTime(dateValue, timeValue) {
  var createdAtMs = parseDateTimeToMs(dateValue, timeValue);
  if (!createdAtMs) {
    return "";
  }

  try {
    var dt = new Date(createdAtMs);
    var dateText = new Intl.DateTimeFormat("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
    }).format(dt);
    var timeText = new Intl.DateTimeFormat("en-US", {
      hour: "numeric",
      minute: "2-digit",
    }).format(dt);
    return dateText + " at " + timeText;
  } catch (e) {
    return String(dateValue || "").trim();
  }
}

function parseDurationTextToSeconds(text) {
  var source = String(text || "").trim();
  if (!source) {
    return 0;
  }

  if (source.toLowerCase() === "no time logged") {
    return 0;
  }

  var total = 0;
  var regex =
    /(\d+)\s*(days?|d|hours?|hrs?|h|minutes?|mins?|m|seconds?|secs?|s)/gi;
  var matched = false;
  var token;
  while ((token = regex.exec(source)) !== null) {
    matched = true;
    var value = Number(token[1] || 0);
    var unit = String(token[2] || "")
      .trim()
      .toLowerCase();
    if (!value) {
      continue;
    }

    if (unit === "d" || unit.indexOf("day") === 0) {
      total += value * 86400;
    } else if (
      unit === "h" ||
      unit.indexOf("hour") === 0 ||
      unit.indexOf("hr") === 0
    ) {
      total += value * 3600;
    } else if (unit === "m" || unit.indexOf("min") === 0) {
      total += value * 60;
    } else if (unit === "s" || unit.indexOf("sec") === 0) {
      total += value;
    }
  }

  return matched ? total : 0;
}

function formatDurationBrief(totalSeconds) {
  var remain = Math.max(0, Math.floor(Number(totalSeconds || 0)));
  var days = Math.floor(remain / 86400);
  remain -= days * 86400;
  var hours = Math.floor(remain / 3600);
  remain -= hours * 3600;
  var minutes = Math.floor(remain / 60);
  remain -= minutes * 60;
  var seconds = remain;

  var parts = [];
  if (days > 0) {
    parts.push(days + "d");
  }
  if (hours > 0) {
    parts.push(hours + "h");
  }
  if (minutes > 0) {
    parts.push(minutes + "m");
  }
  if (!parts.length || (parts.length < 2 && seconds > 0)) {
    parts.push(seconds + "s");
  }

  return parts.slice(0, 2).join(" ");
}

function formatDurationInputText(totalSeconds) {
  var remain = Math.max(0, Math.floor(Number(totalSeconds || 0)));
  var days = Math.floor(remain / 86400);
  remain -= days * 86400;
  var hours = Math.floor(remain / 3600);
  remain -= hours * 3600;
  var minutes = Math.floor(remain / 60);
  remain -= minutes * 60;
  var seconds = remain;
  var parts = [];

  if (days > 0) {
    parts.push(days + "d");
  }
  if (hours > 0) {
    parts.push(hours + "h");
  }
  if (minutes > 0) {
    parts.push(minutes + "m");
  }
  if (seconds > 0) {
    parts.push(seconds + "s");
  }

  return parts.length ? parts.join(" ") : "0m";
}

function estimateUnitToSeconds(value, unit) {
  var amount = Math.max(0, Number(value || 0));
  if (!amount) {
    return 0;
  }

  var normalizedUnit = String(unit || "minutes")
    .trim()
    .toLowerCase();
  if (normalizedUnit === "weeks") {
    return amount * 604800;
  }
  if (normalizedUnit === "days") {
    return amount * 86400;
  }
  if (normalizedUnit === "hours") {
    return amount * 3600;
  }
  return amount * 60;
}

function renderDetailTimeTracking(
  timeTrackingText,
  estimateValue,
  estimateUnit,
) {
  var info =
    timeTrackingText && typeof timeTrackingText === "object"
      ? {
        ownText:
          String(
            timeTrackingText.ownText ||
            timeTrackingText.own_time_tracking ||
            "",
          ).trim() || "No time logged",
        ownSeconds: Number(
          timeTrackingText.ownSeconds ||
          timeTrackingText.own_time_tracking_seconds ||
          parseDurationTextToSeconds(
            String(
              timeTrackingText.ownText ||
              timeTrackingText.own_time_tracking ||
              "",
            ),
          ) ||
          0,
        ),
        ownRemainingSeconds: Number(
          timeTrackingText.ownRemainingSeconds ||
          timeTrackingText.own_remaining_seconds ||
          0,
        ),
        ownEstimateSeconds: Number(
          timeTrackingText.ownEstimateSeconds ||
          timeTrackingText.own_estimate_seconds ||
          0,
        ),
        childText:
          String(
            timeTrackingText.childText ||
            timeTrackingText.child_time_tracking ||
            "",
          ).trim() || "No time logged",
        childSeconds: Number(
          timeTrackingText.childSeconds ||
          timeTrackingText.child_time_tracking_seconds ||
          parseDurationTextToSeconds(
            String(
              timeTrackingText.childText ||
              timeTrackingText.child_time_tracking ||
              "",
            ),
          ) ||
          0,
        ),
        childEstimateSeconds: Number(
          timeTrackingText.childEstimateSeconds ||
          timeTrackingText.child_original_estimate_seconds ||
          0,
        ),
        childRemainingSeconds: Number(
          timeTrackingText.childRemainingSeconds ||
          timeTrackingText.child_remaining_seconds ||
          0,
        ),
        canIncludeChild:
          Number(
            timeTrackingText.canIncludeChild ||
            timeTrackingText.can_include_child_time_tracking ||
            0,
          ) > 0,
        includeChild:
          Number(
            timeTrackingText.includeChild ||
            timeTrackingText.include_child_time_tracking ||
            0,
          ) > 0,
      }
      : {
        ownText: String(timeTrackingText || "").trim() || "No time logged",
        ownSeconds: parseDurationTextToSeconds(
          String(timeTrackingText || ""),
        ),
        ownRemainingSeconds: 0,
        ownEstimateSeconds: 0,
        childText: "No time logged",
        childSeconds: 0,
        childEstimateSeconds: 0,
        childRemainingSeconds: 0,
        canIncludeChild: false,
        includeChild: false,
      };

  itemDetailModalState.timeTracking = info;

  var $target = $("#taskItemDetailTimeTrackingValue");
  if (!$target.length) {
    return;
  }

  var includeChild = info.canIncludeChild && info.includeChild;
  var loggedSeconds = info.ownSeconds + (includeChild ? info.childSeconds : 0);
  var trackingText =
    loggedSeconds > 0 ? formatDurationBrief(loggedSeconds) : "No time logged";
  var ownEstimateSeconds = Math.max(
    Number(info.ownEstimateSeconds || 0),
    estimateUnitToSeconds(estimateValue, estimateUnit),
  );
  var ownRemainingSeconds = Math.max(
    0,
    Number(info.ownRemainingSeconds || Math.max(ownEstimateSeconds - info.ownSeconds, 0)),
  );
  var childEstimateSeconds = Number(info.childEstimateSeconds || 0);
  var childRemainingSeconds = Math.max(
    0,
    Number(
      info.childRemainingSeconds ||
      Math.max(childEstimateSeconds - Number(info.childSeconds || 0), 0),
    ),
  );
  var estimateSeconds = includeChild
    ? loggedSeconds + ownRemainingSeconds + childRemainingSeconds
    : loggedSeconds + ownRemainingSeconds;
  var remainingSeconds = includeChild
    ? ownRemainingSeconds + childRemainingSeconds
    : ownRemainingSeconds;
  var toggleHtml = info.canIncludeChild
    ? '<label class="task-item-detail-time-tracking-toggle"><input id="taskItemDetailIncludeChildTimeTrackingInput" type="checkbox"' +
    (includeChild ? " checked" : "") +
    "> <span>Include child work items</span></label>"
    : "";

  if (estimateSeconds <= 0) {
    $target
      .removeClass("task-item-detail-time-tracking")
      .html(
        '<div class="task-item-detail-time-tracking-block">' +
        '<div class="task-item-detail-time-tracking-summary task-item-detail-time-tracking-summary-plain">' +
        escHtml(trackingText) +
        "</div>" +
        toggleHtml +
        "</div>",
      );
    return;
  }

  var isOvertime = loggedSeconds > estimateSeconds;
  var overtimeSeconds = isOvertime ? loggedSeconds - estimateSeconds : 0;
  var loggedText =
    loggedSeconds > 0
      ? formatDurationBrief(loggedSeconds) + " logged"
      : "No time logged";
  var estimateTooltip = isOvertime
    ? "Original estimate: " + formatDurationBrief(estimateSeconds)
    : loggedSeconds > 0
      ? loggedText +
      " of " +
      formatDurationBrief(estimateSeconds) +
      " original estimate"
      : "Original estimate: " + formatDurationBrief(estimateSeconds);
  var overtimeTooltip = isOvertime
    ? formatDurationBrief(overtimeSeconds) + " over original estimate"
    : "";
  var estimatePercent = 0;
  var overtimePercent = 0;

  if (loggedSeconds > 0) {
    if (isOvertime) {
      estimatePercent = Math.max(
        0,
        Math.min(100, (estimateSeconds / loggedSeconds) * 100),
      );
      overtimePercent = Math.max(0, 100 - estimatePercent);
    } else {
      estimatePercent = Math.max(
        0,
        Math.min(100, (loggedSeconds / estimateSeconds) * 100),
      );
    }
  }

  var remainingText = isOvertime
    ? formatDurationBrief(overtimeSeconds) + " over original estimate"
    : formatDurationBrief(remainingSeconds) + " remaining";
  var markerPercent = !isOvertime && estimatePercent > 0
    ? Math.min(100, Math.max(0, estimatePercent))
    : 0;

  var html =
    '<div class="task-item-detail-time-tracking-block">' +
    '<div class="task-item-detail-time-tracking-bar">' +
    '<span class="task-item-detail-time-tracking-segment task-item-detail-time-tracking-segment-estimate" title="' +
    escHtml(estimateTooltip) +
    '" style="width:' +
    estimatePercent.toFixed(2) +
    '%"></span>' +
    (isOvertime && overtimePercent > 0
      ? '<span class="task-item-detail-time-tracking-segment task-item-detail-time-tracking-segment-overtime" title="' +
      escHtml(overtimeTooltip) +
      '" style="width:' +
      overtimePercent.toFixed(2) +
      '%"></span>'
      : "") +
    (markerPercent > 0
      ? '<span class="task-item-detail-time-tracking-marker" style="left:' +
      markerPercent.toFixed(2) +
      '%"></span>'
      : "") +
    "</div>" +
    '<div class="task-item-detail-time-tracking-summary task-item-detail-time-tracking-summary-muted' +
    (isOvertime ? ' task-item-detail-time-tracking-summary-overtime' : '') +
    '">' +
    escHtml(loggedText) +
    "</div>" +
    '<div class="task-item-detail-time-tracking-summary task-item-detail-time-tracking-summary-muted">' +
    escHtml(remainingText) +
    "</div>" +
    toggleHtml +
    "</div>";

  $target.addClass("task-item-detail-time-tracking").html(html);
}

function renderActivityFeed($target, historyRows) {
  if (!$target || !$target.length) {
    return;
  }

  var rows = sortedActivityRows(Array.isArray(historyRows) ? historyRows : []);
  if (!rows.length) {
    $target.html(
      '<div class="task-item-activity-empty">No activity yet.</div>',
    );
    return;
  }

  var html = "";
  for (var i = 0; i < rows.length; i++) {
    var row = rows[i] || {};
    var actor = String(row.actor_name || "User").trim() || "User";
    var remark = String(row.remark || "updated the Work item").trim();
    var ago = formatRelativeTime(row.create_date, row.create_time);
    var fieldName = String(row.field_name || "").trim();
    var fromValue = String(row.from_value || "").trim();
    var toValue = String(row.to_value || "").trim();

    html +=
      '<div class="task-item-activity-entry">' +
      '<div class="task-item-activity-avatar">' +
      escHtml(initials(actor)) +
      "</div>" +
      '<div class="task-item-activity-content">' +
      '<div class="task-item-activity-text"><span class="task-item-activity-actor">' +
      escHtml(actor) +
      "</span> " +
      escHtml(remark) +
      "</div>" +
      '<div class="task-item-activity-meta">' +
      (ago
        ? '<div class="task-item-activity-ago">' + escHtml(ago) + "</div>"
        : "") +
      activityTypeBadgeHtml("history") +
      "</div>";

    if (fromValue || toValue) {
      html +=
        '<div class="task-item-activity-diff">' +
        buildActivityValueHtml(fieldName, fromValue) +
        '<span class="task-item-activity-arrow"><i class="fa-solid fa-arrow-right"></i></span>' +
        buildActivityValueHtml(fieldName, toValue) +
        "</div>";
    }

    html += "</div></div>";
  }

  $target.html(html);
}

function buildCommentRepliesHtml(commentRow) {
  var commentId = Number((commentRow || {}).id || 0);
  var canReplyAction = !!canEdit;
  var replies = Array.isArray(commentRow && commentRow.replies)
    ? commentRow.replies
    : [];
  if (!replies.length) {
    return "";
  }

  var html = '<div class="task-item-comment-replies">';
  for (var i = 0; i < replies.length; i++) {
    var reply = replies[i] || {};
    var replyId = Number(reply.id || 0);
    var actor = String(reply.actor_name || "User").trim() || "User";
    var ago = formatRelativeTime(reply.create_date, reply.create_time);
    var isEdited = Number(reply.is_edited || 0) === 1;
    var replyHtml = String(reply.reply_html || "").trim();

    html +=
      '<div id="taskItemReplyEntry_' +
      commentId +
      "_" +
      replyId +
      '" class="task-item-comment-reply-entry" data-comment-id="' +
      commentId +
      '" data-reply-id="' +
      replyId +
      '">' +
      '<div class="task-item-activity-avatar">' +
      escHtml(initials(actor)) +
      "</div>" +
      '<div class="task-item-activity-content">' +
      '<div class="task-item-activity-text"><span class="task-item-activity-actor">' +
      escHtml(actor) +
      "</span> replied</div>" +
      '<div class="task-item-activity-meta">' +
      (ago
        ? '<div class="task-item-activity-ago">' +
        escHtml(ago) +
        (isEdited
          ? ' <span class="task-item-comment-edited">(edited)</span>'
          : "") +
        "</div>"
        : "") +
      '<span class="task-item-activity-type-badge task-item-activity-type-comment">REPLY</span>' +
      "</div>" +
      '<div class="task-item-activity-comment-body">' +
      (replyHtml || '<p class="mb-0">(empty reply)</p>') +
      "</div>" +
      (canReplyAction && replyId > 0
        ? '<div class="task-item-comment-actions-row task-item-reply-actions-row">' +
        '<button type="button" class="btn task-item-comment-action-btn task-item-reply-edit-btn" data-reply-id="' +
        replyId +
        '" title="Edit" aria-label="Edit reply"><i class="fa-solid fa-pen-to-square"></i></button>' +
        '<span class="task-item-inline-draft-link task-item-reply-edit-draft-notice d-none" data-reply-id="' +
        replyId +
        '">(Unsaved changes)</span>' +
        '<div class="task-item-comment-more-wrap">' +
        '<button type="button" class="btn task-item-comment-action-btn task-item-reply-more-btn" data-reply-id="' +
        replyId +
        '" title="More" aria-label="More reply actions"><i class="fa-solid fa-ellipsis"></i></button>' +
        '<div class="task-item-comment-more-menu task-item-reply-more-menu d-none">' +
        '<button type="button" class="dropdown-item task-item-reply-copy-link-btn" data-reply-id="' +
        replyId +
        '">Copy link</button>' +
        '<button type="button" class="dropdown-item task-item-reply-delete-btn text-danger" data-reply-id="' +
        replyId +
        '">Delete</button>' +
        "</div>" +
        "</div>" +
        "</div>" +
        '<div class="task-item-comment-reply-edit-slot" data-reply-id="' +
        replyId +
        '"></div>'
        : "") +
      "</div>" +
      "</div>";
  }

  html += "</div>";
  return html;
}

function buildCommentEntryHtml(row, entryPrefix) {
  var commentId = Number(row.id || 0);
  var actor = String(row.actor_name || "User").trim() || "User";
  var ago = formatRelativeTime(row.create_date, row.create_time);
  var isEdited = Number(row.is_edited || 0) === 1;
  var isDeleted = Number(row.is_deleted || 0) === 1;
  var commentHtml = String(row.comment_html || "").trim();
  var canCommentAction = !!canEdit && commentId > 0 && !isDeleted;
  var entryIdPrefix = String(entryPrefix || "comment").trim() || "comment";

  if (isDeleted) {
    commentHtml =
      '<p class="mb-0 text-muted"><em>This comment was deleted.</em></p>';
  }

  return (
    '<div id="taskItemCommentEntry_' +
    entryIdPrefix +
    "_" +
    commentId +
    '" class="task-item-activity-entry task-item-activity-entry-comment task-item-comment-entry" data-comment-id="' +
    commentId +
    '" data-actor-name="' +
    escHtml(actor) +
    '">' +
    '<div class="task-item-activity-avatar">' +
    escHtml(initials(actor)) +
    "</div>" +
    '<div class="task-item-activity-content">' +
    '<div class="task-item-activity-text"><span class="task-item-activity-actor">' +
    escHtml(actor) +
    "</span> " +
    (isDeleted ? "deleted a comment" : "commented") +
    "</div>" +
    '<div class="task-item-activity-meta">' +
    (ago
      ? '<div class="task-item-activity-ago">' +
      escHtml(ago) +
      (isEdited
        ? ' <span class="task-item-comment-edited">(edited)</span>'
        : "") +
      "</div>"
      : "") +
    activityTypeBadgeHtml("comment") +
    "</div>" +
    '<div class="task-item-activity-comment-body">' +
    (commentHtml || '<p class="mb-0">(empty comment)</p>') +
    "</div>" +
    buildCommentRepliesHtml(row) +
    (canCommentAction
      ? '<div class="task-item-comment-actions-row">' +
      '<button type="button" class="btn task-item-comment-action-btn task-item-comment-reply-btn" data-comment-id="' +
      commentId +
      '" title="Reply" aria-label="Reply"><i class="fa-solid fa-reply"></i></button>' +
      '<button type="button" class="btn task-item-comment-action-btn task-item-comment-edit-btn" data-comment-id="' +
      commentId +
      '" title="Edit" aria-label="Edit comment"><i class="fa-solid fa-pen-to-square"></i></button>' +
      '<span class="task-item-inline-draft-link task-item-comment-edit-draft-notice d-none" data-comment-id="' +
      commentId +
      '">(Unsaved changes)</span>' +
      '<span class="task-item-inline-draft-link task-item-reply-draft-notice d-none" data-comment-id="' +
      commentId +
      '">(Unsaved Reply)</span>' +
      '<div class="task-item-comment-more-wrap">' +
      '<button type="button" class="btn task-item-comment-action-btn task-item-comment-more-btn" data-comment-id="' +
      commentId +
      '" title="More" aria-label="More actions"><i class="fa-solid fa-ellipsis"></i></button>' +
      '<div class="task-item-comment-more-menu d-none">' +
      '<button type="button" class="dropdown-item task-item-comment-copy-link-btn" data-comment-id="' +
      commentId +
      '">Copy link</button>' +
      '<button type="button" class="dropdown-item task-item-comment-delete-btn text-danger" data-comment-id="' +
      commentId +
      '">Delete</button>' +
      "</div>" +
      "</div>" +
      "</div>"
      : "") +
    '<div class="task-item-comment-edit-slot" data-comment-id="' +
    commentId +
    '"></div>' +
    '<div class="task-item-comment-reply-slot" data-comment-id="' +
    commentId +
    '"></div>' +
    "</div></div>"
  );
}

function renderCommentFeed($target, commentRows) {
  if (!$target || !$target.length) {
    return;
  }

  var rows = sortedActivityRows(Array.isArray(commentRows) ? commentRows : []);
  if (!rows.length) {
    $target.html(
      '<div class="task-item-activity-empty">No comments yet.</div>',
    );
    return;
  }

  var html = "";
  for (var i = 0; i < rows.length; i++) {
    html += buildCommentEntryHtml(rows[i] || {}, "comment");
  }

  $target.html(html);
  focusCommentFromHash();
}

function renderWorklogEntryHtml(row, options) {
  var settings = options && typeof options === "object" ? options : {};
  var worklogId = Number((row || {}).id || 0);
  var actor = String((row || {}).actor_name || "User").trim() || "User";
  var durationText = String((row || {}).duration_text || "").trim() || "0s";
  var startedText = formatActivityDateTime(
    row && row.started_date ? row.started_date : "",
    row && row.started_time ? row.started_time : "",
  );
  var descriptionHtml = String((row || {}).work_description_html || "").trim();
  var isEdited = Number((row || {}).is_edited || 0) === 1;
  var actionHtml =
    settings.showActions && canEdit && worklogId > 0
      ? '<div class="task-item-worklog-actions-row">' +
        '<button type="button" class="btn task-item-worklog-action-btn task-item-worklog-edit-btn" data-worklog-id="' +
        worklogId +
        '">Edit</button>' +
        '<span class="task-item-worklog-action-sep">&middot;</span>' +
        '<button type="button" class="btn task-item-worklog-action-btn task-item-worklog-delete-btn" data-worklog-id="' +
        worklogId +
        '">Delete</button>' +
        "</div>"
      : "";

  return (
    '<div class="task-item-activity-entry task-item-worklog-entry" data-worklog-id="' +
    worklogId +
    '">' +
    '<div class="task-item-activity-avatar">' +
    escHtml(initials(actor)) +
    "</div>" +
    '<div class="task-item-activity-content">' +
    '<div class="task-item-activity-text"><span class="task-item-activity-actor">' +
    escHtml(actor) +
    "</span> logged " +
    escHtml(durationText) +
    (isEdited
      ? ' <span class="task-item-worklog-edited">(edited)</span>'
      : "") +
    "</div>" +
    '<div class="task-item-activity-meta">' +
    (startedText
      ? '<div class="task-item-activity-ago">' + escHtml(startedText) + "</div>"
      : "") +
    activityTypeBadgeHtml("worklog") +
    "</div>" +
    (descriptionHtml
      ? '<div class="task-item-activity-comment-body">' +
        descriptionHtml +
        "</div>"
      : "") +
    actionHtml +
    "</div></div>"
  );
}

function renderWorklogFeed($target, worklogRows) {
  if (!$target || !$target.length) {
    return;
  }

  var rows = sortedActivityRows(Array.isArray(worklogRows) ? worklogRows : []);
  if (!rows.length) {
    $target.html(
      '<div class="task-item-activity-empty">No work log yet.</div>',
    );
    return;
  }

  var html = "";
  for (var i = 0; i < rows.length; i++) {
    html += renderWorklogEntryHtml(rows[i] || {}, {
      showActions: true,
    });
  }

  $target.html(html);
}

function activityTypeBadgeHtml(type) {
  var value = String(type || "history").toLowerCase();
  if (value === "comment") {
    return '<span class="task-item-activity-type-badge task-item-activity-type-comment">COMMENT</span>';
  }
  if (value === "worklog") {
    return '<span class="task-item-activity-type-badge task-item-activity-type-worklog">WORKLOG</span>';
  }
  return '<span class="task-item-activity-type-badge task-item-activity-type-history">HISTORY</span>';
}

function getActivitySortDirection() {
  return String(itemDetailModalState.activitySortDirection || "desc") === "asc"
    ? "asc"
    : "desc";
}

function sortedActivityRows(rows) {
  var sorted = Array.isArray(rows) ? rows.slice() : [];
  sorted.sort(function (a, b) {
    var tsA = toActivitySortTimestamp(
      a && (a.create_date || a.started_date) ? a.create_date || a.started_date : "",
      a && (a.create_time || a.started_time) ? a.create_time || a.started_time : "",
    );
    var tsB = toActivitySortTimestamp(
      b && (b.create_date || b.started_date) ? b.create_date || b.started_date : "",
      b && (b.create_time || b.started_time) ? b.create_time || b.started_time : "",
    );

    if (tsA !== tsB) {
      return tsB - tsA;
    }

    var idA = Number(a && a.id ? a.id : 0);
    var idB = Number(b && b.id ? b.id : 0);
    return idB - idA;
  });

  if (getActivitySortDirection() === "asc") {
    sorted.reverse();
  }

  return sorted;
}

function parseActivityPermalinkHash() {
  var hash = String(window.location.hash || "").trim();
  var commentMatch = hash.match(/^#task-item-(\d+)-comment-(\d+)$/i);
  if (commentMatch) {
    return {
      type: "comment",
      itemId: Number(commentMatch[1] || 0),
      commentId: Number(commentMatch[2] || 0),
      replyId: 0,
    };
  }

  var replyMatch = hash.match(/^#task-item-(\d+)-reply-(\d+)$/i);
  if (replyMatch) {
    return {
      type: "reply",
      itemId: Number(replyMatch[1] || 0),
      commentId: 0,
      replyId: Number(replyMatch[2] || 0),
    };
  }

  return null;
}

function focusCommentFromHash() {
  var target = parseActivityPermalinkHash();
  if (!target) {
    return;
  }

  var activeItemId = Number(itemDetailModalState.itemId || 0);
  if (activeItemId <= 0 || activeItemId !== target.itemId) {
    return;
  }

  var $entry = $();
  if (target.type === "reply") {
    $entry = $(
      '#taskItemActivityCommentList .task-item-comment-reply-entry[data-reply-id="' +
      target.replyId +
      '"]',
    ).first();
  } else {
    $entry = $(
      '#taskItemActivityCommentList .task-item-comment-entry[data-comment-id="' +
      target.commentId +
      '"]',
    ).first();
  }
  if (!$entry.length) {
    return;
  }

  setItemActivityTab("comment");

  var $list = $("#taskItemActivityCommentList");
  if ($list.length) {
    var top = $entry.position().top + $list.scrollTop() - 10;
    $list.stop(true).animate({ scrollTop: Math.max(0, top) }, 180);
  }

  $(".task-item-comment-entry").removeClass("task-item-comment-entry-focus");
  $(".task-item-comment-reply-entry").removeClass(
    "task-item-comment-reply-entry-focus",
  );
  $entry.addClass(
    target.type === "reply"
      ? "task-item-comment-reply-entry-focus"
      : "task-item-comment-entry-focus",
  );
  window.setTimeout(function () {
    $entry
      .removeClass("task-item-comment-entry-focus")
      .removeClass("task-item-comment-reply-entry-focus");
  }, 1800);
}

function updateActivitySortButtons() {
  var isAsc = getActivitySortDirection() === "asc";
  $(".task-item-activity-sort-btn").each(function () {
    var $btn = $(this);
    $btn.attr("title", isAsc ? "Sort newest first" : "Reverse sort direction");
    $btn.attr(
      "aria-label",
      isAsc ? "Sort newest first" : "Reverse sort direction",
    );
    var $icon = $btn.find("i");
    $icon.toggleClass("fa-arrow-down-short-wide", !isAsc);
    $icon.toggleClass("fa-arrow-up-short-wide", isAsc);
  });
}

function toActivitySortTimestamp(createDate, createTime) {
  var dateText = String(createDate || "").trim();
  var timeText = String(createTime || "").trim();
  var isoText = "";

  if (dateText && timeText) {
    isoText = dateText + "T" + timeText;
  } else if (dateText) {
    isoText = dateText;
  } else if (timeText) {
    isoText = "1970-01-01T" + timeText;
  }

  if (!isoText) {
    return 0;
  }

  var ms = Date.parse(isoText);
  return Number.isFinite(ms) ? ms : 0;
}

function renderAllActivityFeed($target, historyRows, commentRows, worklogRows) {
  if (!$target || !$target.length) {
    return;
  }

  var allRows = [];
  var history = Array.isArray(historyRows) ? historyRows : [];
  var comments = Array.isArray(commentRows) ? commentRows : [];
  var worklogs = Array.isArray(worklogRows) ? worklogRows : [];
  var i;

  for (i = 0; i < history.length; i++) {
    var historyRow = history[i] || {};
    allRows.push({
      type: "history",
      create_date: historyRow.create_date,
      create_time: historyRow.create_time,
      sort_ts: toActivitySortTimestamp(
        historyRow.create_date,
        historyRow.create_time,
      ),
      payload: historyRow,
    });
  }

  for (i = 0; i < comments.length; i++) {
    var commentRow = comments[i] || {};
    allRows.push({
      type: "comment",
      create_date: commentRow.create_date,
      create_time: commentRow.create_time,
      sort_ts: toActivitySortTimestamp(
        commentRow.create_date,
        commentRow.create_time,
      ),
      payload: commentRow,
    });
  }

  for (i = 0; i < worklogs.length; i++) {
    var worklogRow = worklogs[i] || {};
    allRows.push({
      type: "worklog",
      create_date: worklogRow.started_date || worklogRow.create_date,
      create_time: worklogRow.started_time || worklogRow.create_time,
      sort_ts: toActivitySortTimestamp(
        worklogRow.started_date || worklogRow.create_date,
        worklogRow.started_time || worklogRow.create_time,
      ),
      payload: worklogRow,
    });
  }

  if (!allRows.length) {
    $target.html(
      '<div class="task-item-activity-empty">No activity yet.</div>',
    );
    return;
  }

  allRows.sort(function (a, b) {
    var tsDiff = Number(b.sort_ts || 0) - Number(a.sort_ts || 0);
    if (tsDiff !== 0) {
      return tsDiff;
    }

    var idA = Number(a && a.payload ? a.payload.id || 0 : 0);
    var idB = Number(b && b.payload ? b.payload.id || 0 : 0);
    return idB - idA;
  });

  if (getActivitySortDirection() === "asc") {
    allRows.reverse();
  }

  var html = "";
  for (i = 0; i < allRows.length; i++) {
    var row = allRows[i] || {};
    var payload = row.payload || {};
    var actor = String(payload.actor_name || "User").trim() || "User";
    var ago = formatRelativeTime(payload.create_date, payload.create_time);

    if (row.type === "comment") {
      html += buildCommentEntryHtml(payload, "all");
      continue;
    }

    if (row.type === "worklog") {
      html += renderWorklogEntryHtml(payload, {
        showActions: false,
      });
      continue;
    }

    var remark = String(payload.remark || "updated the Work item").trim();
    var fieldName = String(payload.field_name || "").trim();
    var fromValue = String(payload.from_value || "").trim();
    var toValue = String(payload.to_value || "").trim();

    html +=
      '<div class="task-item-activity-entry">' +
      '<div class="task-item-activity-avatar">' +
      escHtml(initials(actor)) +
      "</div>" +
      '<div class="task-item-activity-content">' +
      '<div class="task-item-activity-text"><span class="task-item-activity-actor">' +
      escHtml(actor) +
      "</span> " +
      escHtml(remark) +
      "</div>" +
      '<div class="task-item-activity-meta">' +
      (ago
        ? '<div class="task-item-activity-ago">' + escHtml(ago) + "</div>"
        : "") +
      activityTypeBadgeHtml("history") +
      "</div>";

    if (fromValue || toValue) {
      html +=
        '<div class="task-item-activity-diff">' +
        buildActivityValueHtml(fieldName, fromValue) +
        '<span class="task-item-activity-arrow"><i class="fa-solid fa-arrow-right"></i></span>' +
        buildActivityValueHtml(fieldName, toValue) +
        "</div>";
    }

    html += "</div></div>";
  }

  $target.html(html);
}

function renderItemHistoryPanels() {
  renderAllActivityFeed(
    $("#taskItemActivityAllList"),
    itemDetailModalState.history,
    itemDetailModalState.comments,
    itemDetailModalState.worklogs,
  );
  renderCommentFeed(
    $("#taskItemActivityCommentList"),
    itemDetailModalState.comments,
  );
  renderActivityFeed(
    $("#taskItemActivityHistoryList"),
    itemDetailModalState.history,
  );
  renderWorklogFeed(
    $("#taskItemActivityWorklogList"),
    itemDetailModalState.worklogs,
  );
  updateActivitySortButtons();
  $(document).trigger("task:historyPanelsRendered");
}

function setItemActivityTab(tabName) {
  var tab = String(tabName || "all").toLowerCase();
  if (["all", "comment", "history", "worklog"].indexOf(tab) === -1) {
    tab = "all";
  }

  itemDetailModalState.activityTab = tab;
  $(".task-item-activity-tab").removeClass("active");
  $('.task-item-activity-tab[data-tab-target="' + tab + '"]').addClass(
    "active",
  );

  $("#taskItemActivityPanelAll")
    .toggleClass("d-none", tab !== "all")
    .prop("hidden", tab !== "all")
    .toggleClass("is-active", tab === "all");
  $("#taskItemActivityPanelComment")
    .toggleClass("d-none", tab !== "comment")
    .prop("hidden", tab !== "comment")
    .toggleClass("is-active", tab === "comment");
  $("#taskItemActivityPanelHistory")
    .toggleClass("d-none", tab !== "history")
    .prop("hidden", tab !== "history")
    .toggleClass("is-active", tab === "history");
  $("#taskItemActivityPanelWorklog")
    .toggleClass("d-none", tab !== "worklog")
    .prop("hidden", tab !== "worklog")
    .toggleClass("is-active", tab === "worklog");

  updateActivitySortButtons();
}

function loadItemHistory(itemId) {
  var id = Number(itemId || 0);
  if (!id) {
    nextItemDetailHistoryRequestSeq();
    itemDetailModalState.history = [];
    renderItemHistoryPanels();
    return;
  }

  var requestSeq = nextItemDetailHistoryRequestSeq();
  postAction(
    {
      task_action: "get_item_history",
      item_id: id,
    },
    function (res) {
      if (
        Number(itemDetailModalState.itemId || 0) !== id ||
        requestSeq !== Number(itemDetailModalState.historyRequestSeq || 0)
      ) {
        return;
      }
      itemDetailModalState.history = Array.isArray(res.history)
        ? res.history.slice()
        : [];
      renderItemHistoryPanels();
    },
  );
}

function loadItemWorklogs(itemId) {
  var id = Number(itemId || 0);
  if (!id) {
    nextItemDetailWorklogRequestSeq();
    itemDetailModalState.worklogs = [];
    itemDetailModalState.worklogsLoading = false;
    renderItemHistoryPanels();
    return;
  }

  var requestSeq = nextItemDetailWorklogRequestSeq();
  itemDetailModalState.worklogsLoading = true;
  postAction(
    {
      task_action: "get_item_worklogs",
      item_id: id,
    },
    function (res) {
      if (
        Number(itemDetailModalState.itemId || 0) !== id ||
        requestSeq !== Number(itemDetailModalState.worklogRequestSeq || 0)
      ) {
        return;
      }
      itemDetailModalState.worklogs = Array.isArray(res.worklogs)
        ? res.worklogs.slice()
        : [];
      itemDetailModalState.worklogsLoading = false;
      renderItemHistoryPanels();
    },
    function () {
      if (
        Number(itemDetailModalState.itemId || 0) !== id ||
        requestSeq !== Number(itemDetailModalState.worklogRequestSeq || 0)
      ) {
        return;
      }
      itemDetailModalState.worklogsLoading = false;
      renderItemHistoryPanels();
    },
  );
}

function showBoardToast(title, message) {
  var $host = $("#taskBoardToastHost");
  if (!$host.length) {
    return;
  }

  var toastId =
    "taskToast_" + Date.now() + "_" + Math.floor(Math.random() * 9999);
  var html =
    '<div class="task-board-toast" id="' +
    toastId +
    '">' +
    '<span class="task-board-toast-icon"><i class="fa-solid fa-check"></i></span>' +
    '<div class="task-board-toast-body">' +
    '<div class="task-board-toast-title">' +
    escHtml(title || "Success") +
    "</div>" +
    '<div class="task-board-toast-text">' +
    escHtml(message || "") +
    "</div>" +
    "</div>" +
    '<button type="button" class="task-board-toast-close" data-toast-id="' +
    toastId +
    '"><i class="fa-solid fa-xmark"></i></button>' +
    "</div>";

  $host.append(html);
  window.setTimeout(function () {
    $("#" + toastId).fadeOut(160, function () {
      $(this).remove();
    });
  }, 4200);
}

function getWorklogStorageKey(itemId) {
  return worklogStoragePrefix + String(Number(itemId || 0));
}

function readWorklogTimerState(itemId) {
  var id = Number(itemId || 0);
  if (!id) {
    return {
      itemId: 0,
      elapsedSeconds: 0,
      running: false,
      startedAtMs: 0,
      collapsed: false,
    };
  }

  var fallback = {
    itemId: id,
    elapsedSeconds: 0,
    running: false,
    startedAtMs: 0,
    collapsed: false,
  };

  try {
    var raw = window.localStorage.getItem(getWorklogStorageKey(id));
    if (!raw) {
      return fallback;
    }

    var parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== "object") {
      return fallback;
    }

    fallback.elapsedSeconds = Math.max(0, Number(parsed.elapsedSeconds || 0));
    fallback.running = !!parsed.running;
    fallback.startedAtMs = fallback.running
      ? Math.max(0, Number(parsed.startedAtMs || 0))
      : 0;
    if (fallback.running && !fallback.startedAtMs) {
      fallback.running = false;
    }
    fallback.collapsed = !!parsed.collapsed;
  } catch (e) {
    return fallback;
  }

  return fallback;
}

function writeWorklogTimerState(stateData) {
  var data = stateData || worklogTimerState;
  var id = Number(data.itemId || 0);
  if (!id) {
    return;
  }

  try {
    window.localStorage.setItem(
      getWorklogStorageKey(id),
      JSON.stringify({
        itemId: id,
        elapsedSeconds: Math.max(
          0,
          Math.floor(Number(data.elapsedSeconds || 0)),
        ),
        running: !!data.running,
        startedAtMs: !!data.running
          ? Math.max(0, Math.floor(Number(data.startedAtMs || 0)))
          : 0,
        collapsed: !!data.collapsed,
      }),
    );
  } catch (e) { }
}

function currentWorklogSeconds() {
  var base = Math.max(
    0,
    Math.floor(Number(worklogTimerState.elapsedSeconds || 0)),
  );
  if (!worklogTimerState.running) {
    return base;
  }

  var startedAt = Math.max(
    0,
    Math.floor(Number(worklogTimerState.startedAtMs || 0)),
  );
  if (!startedAt) {
    return base;
  }

  return Math.max(0, base + Math.floor((Date.now() - startedAt) / 1000));
}

function formatWorklogSegment(value) {
  var num = Math.max(0, Number(value || 0));
  return num < 10 ? "0" + num : String(num);
}

function renderWorklogDisplay(seconds) {
  var total = Math.max(0, Math.floor(Number(seconds || 0)));
  var days = Math.floor(total / 86400);
  var hours = Math.floor((total % 86400) / 3600);
  var minutes = Math.floor((total % 3600) / 60);
  var remainSeconds = total % 60;

  $("#taskItemWorklogDays").text(formatWorklogSegment(days));
  $("#taskItemWorklogHours").text(formatWorklogSegment(hours));
  $("#taskItemWorklogMinutes").text(formatWorklogSegment(minutes));
  $("#taskItemWorklogSeconds").text(formatWorklogSegment(remainSeconds));
}

function renderWorklogActionButtons() {
  var elapsed = currentWorklogSeconds();
  var isRunning = !!worklogTimerState.running;
  var hasElapsed = elapsed > 0;

  $("#taskItemWorklogStartBtn").toggleClass("d-none", hasElapsed || isRunning);
  $("#taskItemWorklogStopBtn").toggleClass("d-none", !isRunning);
  $("#taskItemWorklogContinueBtn").toggleClass(
    "d-none",
    isRunning || !hasElapsed,
  );
  $("#taskItemWorklogSaveBtn").toggleClass("d-none", !hasElapsed);
  $("#taskItemWorklogResetBtn").toggleClass(
    "d-none",
    !hasElapsed && !isRunning,
  );
}

function applyWorklogCollapsedUi() {
  $("#taskItemWorklogTimerSection").toggleClass(
    "task-item-worklog-collapsed",
    !!worklogTimerState.collapsed,
  );
  $("#taskItemWorklogToggleBtn")
    .attr(
      "title",
      worklogTimerState.collapsed
        ? "Expand worklog timer"
        : "Collapse worklog timer",
    )
    .attr("aria-expanded", worklogTimerState.collapsed ? "false" : "true")
    .find("i")
    .toggleClass("fa-chevron-down", !worklogTimerState.collapsed)
    .toggleClass("fa-chevron-right", !!worklogTimerState.collapsed);
}

function applyWorklogTimerUi() {
  renderWorklogDisplay(currentWorklogSeconds());
  renderWorklogActionButtons();
  applyWorklogCollapsedUi();
}

function startWorklogTicker() {
  if (worklogTickerId !== null) {
    return;
  }

  worklogTickerId = window.setInterval(function () {
    applyWorklogTimerUi();
  }, 1000);
}

function stopWorklogTicker() {
  if (worklogTickerId === null) {
    return;
  }

  window.clearInterval(worklogTickerId);
  worklogTickerId = null;
}

function openWorklogTimerForItem(itemId) {
  worklogTimerState = readWorklogTimerState(itemId);
  applyWorklogTimerUi();
  startWorklogTicker();
}

function persistWorklogTimerState() {
  writeWorklogTimerState(worklogTimerState);
}

function startOrContinueWorklogTimer() {
  if (!Number(worklogTimerState.itemId || 0)) {
    return;
  }

  if (worklogTimerState.running) {
    return;
  }

  worklogTimerState.running = true;
  worklogTimerState.startedAtMs = Date.now();
  persistWorklogTimerState();
  applyWorklogTimerUi();
  startWorklogTicker();
}

function stopWorklogTimer() {
  if (!Number(worklogTimerState.itemId || 0) || !worklogTimerState.running) {
    return;
  }

  worklogTimerState.elapsedSeconds = currentWorklogSeconds();
  worklogTimerState.running = false;
  worklogTimerState.startedAtMs = 0;
  persistWorklogTimerState();
  applyWorklogTimerUi();
}

function resetWorklogTimer() {
  if (!Number(worklogTimerState.itemId || 0)) {
    return;
  }

  worklogTimerState.elapsedSeconds = 0;
  worklogTimerState.running = false;
  worklogTimerState.startedAtMs = 0;
  persistWorklogTimerState();
  applyWorklogTimerUi();
}

function saveWorklogForCurrentItem() {
  var itemId = Number(worklogTimerState.itemId || 0);
  if (!itemId) {
    return;
  }

  var totalSeconds = currentWorklogSeconds();
  if (totalSeconds <= 0) {
    notify("No time to save yet.");
    return;
  }

  postAction(
    {
      task_action: "save_item_worklog",
      item_id: itemId,
      duration_seconds: totalSeconds,
    },
    function (res) {
      if (typeof applyCurrentItemDetailResponse === "function") {
        applyCurrentItemDetailResponse(res || {});
      } else {
        var detail =
          res && res.detail && typeof res.detail === "object" ? res.detail : {};
        renderDetailTimeTracking(
          detail,
          Number($("#taskItemDetailEstimateValueInput").val() || 0),
          String($("#taskItemDetailEstimateUnitInput").val() || "minutes"),
        );

        if (Array.isArray(res && res.worklogs)) {
          itemDetailModalState.worklogs = res.worklogs.slice();
        }
        if (Array.isArray(res && res.history)) {
          itemDetailModalState.history = res.history.slice();
        }
        renderItemHistoryPanels();
      }

      worklogTimerState.elapsedSeconds = 0;
      worklogTimerState.running = false;
      worklogTimerState.startedAtMs = 0;
      persistWorklogTimerState();
      applyWorklogTimerUi();

      showBoardToast(
        "Work log saved",
        "Time tracking was updated successfully.",
      );
    },
  );
}

function normalizeIdList(list) {
  return (Array.isArray(list) ? list : [])
    .map(function (value) {
      return Number(value || 0);
    })
    .filter(function (value) {
      return value > 0;
    })
    .filter(function (value, index, source) {
      return source.indexOf(value) === index;
    })
    .sort(function (a, b) {
      return a - b;
    });
}

function normalizePriorityList(list) {
  var allowed = taskPriorityValues.slice();
  return (Array.isArray(list) ? list : [])
    .map(function (value) {
      return String(value || "").trim();
    })
    .filter(function (value) {
      return allowed.indexOf(value) !== -1;
    })
    .filter(function (value, index, source) {
      return source.indexOf(value) === index;
    });
}

function resetBoardFilterPartA() {
  boardFilterState.partA.assignedToMe = false;
  boardFilterState.partA.dueThisWeek = false;
}

function resetBoardFilterPartB() {
  boardFilterState.partB.dateStart = "";
  boardFilterState.partB.dateDue = "";
  boardFilterState.partB.assigneeIds = [];
  boardFilterState.partB.createdFrom = "";
  boardFilterState.partB.createdTo = "";
  boardFilterState.partB.labelIds = [];
  boardFilterState.partB.parentIds = [];
  boardFilterState.partB.priorityValues = [];
  boardFilterState.partB.reporterIds = [];
  boardFilterState.partB.statusIds = [];
  boardFilterState.partB.updatedFrom = "";
  boardFilterState.partB.updatedTo = "";
  boardFilterState.partB.workTypeIds = [];
  boardFilterState.search.label = "";
  boardFilterState.search.parent = "";
}

function hasPartASelection() {
  return !!(
    boardFilterState.partA.assignedToMe || boardFilterState.partA.dueThisWeek
  );
}

function hasPartBSelection() {
  return !!(
    boardFilterState.partB.dateStart ||
    boardFilterState.partB.dateDue ||
    boardFilterState.partB.assigneeIds.length ||
    boardFilterState.partB.createdFrom ||
    boardFilterState.partB.createdTo ||
    boardFilterState.partB.labelIds.length ||
    boardFilterState.partB.parentIds.length ||
    boardFilterState.partB.priorityValues.length ||
    boardFilterState.partB.reporterIds.length ||
    boardFilterState.partB.statusIds.length ||
    boardFilterState.partB.updatedFrom ||
    boardFilterState.partB.updatedTo ||
    boardFilterState.partB.workTypeIds.length
  );
}

function syncBoardFilterActivePart() {
  if (hasPartASelection()) {
    boardFilterState.activePart = "A";
    resetBoardFilterPartB();
    return;
  }

  if (hasPartBSelection()) {
    boardFilterState.activePart = "B";
    resetBoardFilterPartA();
    return;
  }

  boardFilterState.activePart = "none";
}

function boardFilterCookieValue() {
  return {
    activePart: boardFilterState.activePart,
    partA: {
      assignedToMe: !!boardFilterState.partA.assignedToMe,
      dueThisWeek: !!boardFilterState.partA.dueThisWeek,
    },
    partB: {
      dateStart: String(boardFilterState.partB.dateStart || ""),
      dateDue: String(boardFilterState.partB.dateDue || ""),
      assigneeIds: normalizeIdList(boardFilterState.partB.assigneeIds),
      createdFrom: String(boardFilterState.partB.createdFrom || ""),
      createdTo: String(boardFilterState.partB.createdTo || ""),
      labelIds: normalizeIdList(boardFilterState.partB.labelIds),
      parentIds: normalizeIdList(boardFilterState.partB.parentIds),
      priorityValues: normalizePriorityList(
        boardFilterState.partB.priorityValues,
      ),
      reporterIds: normalizeIdList(boardFilterState.partB.reporterIds),
      statusIds: normalizeIdList(boardFilterState.partB.statusIds),
      updatedFrom: String(boardFilterState.partB.updatedFrom || ""),
      updatedTo: String(boardFilterState.partB.updatedTo || ""),
      workTypeIds: normalizeIdList(boardFilterState.partB.workTypeIds),
    },
  };
}

function setCookieValue(name, value, days) {
  var expireDate = new Date();
  expireDate.setTime(expireDate.getTime() + Number(days || 30) * 86400000);
  document.cookie =
    name +
    "=" +
    encodeURIComponent(value) +
    "; expires=" +
    expireDate.toUTCString() +
    "; path=/";
}

function getCookieValue(name) {
  var source = String(document.cookie || "").split(";");
  for (var i = 0; i < source.length; i++) {
    var part = String(source[i] || "").trim();
    if (!part || part.indexOf(name + "=") !== 0) {
      continue;
    }

    return decodeURIComponent(part.substring(name.length + 1));
  }

  return "";
}

function saveBoardFiltersToCookie() {
  try {
    setCookieValue(
      boardFilterCookieName,
      JSON.stringify(boardFilterCookieValue()),
      30,
    );
  } catch (e) { }
}

function loadBoardFiltersFromCookie() {
  try {
    var raw = getCookieValue(boardFilterCookieName);
    if (!raw) {
      return;
    }

    var parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== "object") {
      return;
    }

    var partA =
      parsed.partA && typeof parsed.partA === "object" ? parsed.partA : {};
    var partB =
      parsed.partB && typeof parsed.partB === "object" ? parsed.partB : {};

    boardFilterState.activePart =
      parsed.activePart === "A" || parsed.activePart === "B"
        ? parsed.activePart
        : "none";

    boardFilterState.partA.assignedToMe = !!partA.assignedToMe;
    boardFilterState.partA.dueThisWeek = !!partA.dueThisWeek;

    boardFilterState.partB.dateStart = String(partB.dateStart || "");
    boardFilterState.partB.dateDue = String(partB.dateDue || "");
    boardFilterState.partB.assigneeIds = normalizeIdList(partB.assigneeIds);
    boardFilterState.partB.createdFrom = String(partB.createdFrom || "");
    boardFilterState.partB.createdTo = String(partB.createdTo || "");
    boardFilterState.partB.labelIds = normalizeIdList(partB.labelIds);
    boardFilterState.partB.parentIds = normalizeIdList(partB.parentIds);
    boardFilterState.partB.priorityValues = normalizePriorityList(
      partB.priorityValues,
    );
    boardFilterState.partB.reporterIds = normalizeIdList(partB.reporterIds);
    boardFilterState.partB.statusIds = normalizeIdList(partB.statusIds);
    boardFilterState.partB.updatedFrom = String(partB.updatedFrom || "");
    boardFilterState.partB.updatedTo = String(partB.updatedTo || "");
    boardFilterState.partB.workTypeIds = normalizeIdList(partB.workTypeIds);
  } catch (e) { }

  syncBoardFilterActivePart();
}

function normalizeBoardViewFieldState(raw) {
  var normalized = $.extend({}, boardViewFieldDefaults);
  if (!raw || typeof raw !== "object") {
    return normalized;
  }

  for (var key in boardViewFieldDefaults) {
    if (!Object.prototype.hasOwnProperty.call(boardViewFieldDefaults, key)) {
      continue;
    }

    if (Object.prototype.hasOwnProperty.call(raw, key)) {
      normalized[key] = !!raw[key];
    }
  }

  return normalized;
}

function saveBoardViewFieldsToCookie() {
  try {
    setCookieValue(
      boardViewFieldCookieName,
      JSON.stringify(normalizeBoardViewFieldState(boardViewFieldState)),
      30,
    );
  } catch (e) { }
}

function loadBoardViewFieldsFromCookie() {
  try {
    var raw = getCookieValue(boardViewFieldCookieName);
    if (!raw) {
      boardViewFieldState = normalizeBoardViewFieldState({});
      return;
    }

    var parsed = JSON.parse(raw);
    boardViewFieldState = normalizeBoardViewFieldState(parsed);
  } catch (e) {
    boardViewFieldState = normalizeBoardViewFieldState({});
  }
}

function normalizeBoardZoomPercent(value) {
  var percent = Number(value || 0);
  if (!isFinite(percent) || percent <= 0) {
    percent = boardZoomDefault;
  }

  percent = Math.round(percent);
  if (percent < boardZoomMin) {
    percent = boardZoomMin;
  }
  if (percent > boardZoomMax) {
    percent = boardZoomMax;
  }

  return percent;
}

function saveBoardZoomToStorage() {
  try {
    window.localStorage.setItem(
      boardZoomStorageKey,
      String(normalizeBoardZoomPercent(boardZoomPercent)),
    );
  } catch (e) {}
}

function loadBoardZoomFromStorage() {
  try {
    boardZoomPercent = normalizeBoardZoomPercent(
      window.localStorage.getItem(boardZoomStorageKey),
    );
  } catch (e) {
    boardZoomPercent = boardZoomDefault;
  }
}

function normalizeBoardGroupBy(value) {
  var mode = String(value || "status")
    .trim()
    .toLowerCase();
  if (mode === "assignee" || mode === "priority") {
    return mode;
  }
  return "status";
}

function boardGroupLabel(mode) {
  var normalized = normalizeBoardGroupBy(mode);
  if (normalized === "assignee") {
    return "Assignee";
  }
  if (normalized === "priority") {
    return "Priority";
  }
  return "Status";
}

function getBoardGroupBy() {
  return normalizeBoardGroupBy(boardGroupBy);
}

function setBoardGroupBy(value, persist) {
  boardGroupBy = normalizeBoardGroupBy(value);
  if (persist !== false) {
    saveBoardGroupToCookie();
  }
}

function isBoardGroupedByStatus() {
  return getBoardGroupBy() === "status";
}

function saveBoardGroupToCookie() {
  try {
    setCookieValue(
      boardGroupCookieName,
      JSON.stringify({ groupBy: getBoardGroupBy() }),
      30,
    );
  } catch (e) { }
}

function loadBoardGroupFromCookie() {
  try {
    var raw = getCookieValue(boardGroupCookieName);
    if (!raw) {
      boardGroupBy = "status";
      return;
    }

    var parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== "object") {
      boardGroupBy = "status";
      return;
    }

    boardGroupBy = normalizeBoardGroupBy(parsed.groupBy);
  } catch (e) {
    boardGroupBy = "status";
  }
}

function normalizeStatusColumnMeta(raw) {
  var item = raw && typeof raw === "object" ? raw : {};
  return {
    id: Number(item.id || 0),
    name: String(item.name || "").trim() || "Untitled",
    color: normalizeHexColorValue(item.color || "", "#DFE1E6"),
  };
}

function setBoardStatusColumns(columns) {
  var source = Array.isArray(columns) ? columns : [];
  var next = [];
  var seen = {};

  for (var i = 0; i < source.length; i++) {
    var normalized = normalizeStatusColumnMeta(source[i]);
    if (normalized.id <= 0 || seen[normalized.id]) {
      continue;
    }
    seen[normalized.id] = true;
    next.push(normalized);
  }

  boardStatusColumns = next;
}

function getBoardStatusColumns() {
  return boardStatusColumns.slice();
}

function getBoardStatusColumnName(columnId) {
  var meta = getBoardStatusColumnMeta(columnId);
  return meta ? String(meta.name || "").trim() : "";
}

function getBoardStatusColumnMeta(columnId) {
  var id = Number(columnId || 0);
  if (id <= 0) {
    return null;
  }

  for (var i = 0; i < boardStatusColumns.length; i++) {
    var col = boardStatusColumns[i] || {};
    if (Number(col.id || 0) === id) {
      return {
        id: Number(col.id || 0),
        name: String(col.name || "").trim(),
        color: normalizeHexColorValue(col.color || "", "#DFE1E6"),
      };
    }
  }

  return null;
}

function captureBoardStatusColumnsFromDom() {
  var cols = [];
  $app.find(".task-column").each(function () {
    var $column = $(this);
    var columnId = Number($column.attr("data-column-id") || 0);
    if (columnId <= 0) {
      return;
    }

    var title = String(
      $column.find(".task-column-title").first().text() || "",
    ).trim();
    var color = String($column.attr("data-column-color") || "").trim();

    cols.push({
      id: columnId,
      name: title || "Untitled",
      color: normalizeHexColorValue(color, "#DFE1E6"),
    });
  });

  if (cols.length === 0) {
    var fallback = [];
    if (typeof window.sheetsConfig !== "undefined" && Array.isArray(window.sheetsConfig.columns)) {
      fallback = window.sheetsConfig.columns;
    } else if (typeof window.summaryConfig !== "undefined" && Array.isArray(window.summaryConfig.columns)) {
      fallback = window.summaryConfig.columns;
    } else if (typeof window.taskBoardConfig !== "undefined" && Array.isArray(window.taskBoardConfig.columns)) {
      fallback = window.taskBoardConfig.columns;
    }

    for (var i = 0; i < fallback.length; i++) {
      var c = fallback[i] || {};
      var cid = Number(c.id || 0);
      if (cid > 0) {
        cols.push({
          id: cid,
          name: String(c.name || "Untitled").trim() || "Untitled",
          color: normalizeHexColorValue(c.color || "", "#DFE1E6"),
        });
      }
    }
  }

  setBoardStatusColumns(cols);
}

function upsertBoardStatusColumn(columnId, columnName, columnColor) {
  var id = Number(columnId || 0);
  if (id <= 0) {
    return;
  }

  var name = String(columnName || "").trim() || "Untitled";
  var color = normalizeHexColorValue(columnColor || "", "#DFE1E6");
  var found = false;

  for (var i = 0; i < boardStatusColumns.length; i++) {
    var col = boardStatusColumns[i] || {};
    if (Number(col.id || 0) !== id) {
      continue;
    }
    boardStatusColumns[i] = { id: id, name: name, color: color };
    found = true;
    break;
  }

  if (!found) {
    boardStatusColumns.push({ id: id, name: name, color: color });
  }
}

function removeBoardStatusColumnById(columnId) {
  var id = Number(columnId || 0);
  if (id <= 0) {
    return;
  }

  boardStatusColumns = boardStatusColumns.filter(function (col) {
    return Number((col && col.id) || 0) !== id;
  });
}

function moveBoardStatusColumn(columnId, direction) {
  var id = Number(columnId || 0);
  var dir = String(direction || "")
    .trim()
    .toLowerCase();
  if (id <= 0 || (dir !== "left" && dir !== "right")) {
    return;
  }

  var index = -1;
  for (var i = 0; i < boardStatusColumns.length; i++) {
    if (
      Number((boardStatusColumns[i] && boardStatusColumns[i].id) || 0) === id
    ) {
      index = i;
      break;
    }
  }

  if (index === -1) {
    return;
  }

  if (dir === "left" && index > 0) {
    var prev = boardStatusColumns[index - 1];
    boardStatusColumns[index - 1] = boardStatusColumns[index];
    boardStatusColumns[index] = prev;
  } else if (dir === "right" && index < boardStatusColumns.length - 1) {
    var next = boardStatusColumns[index + 1];
    boardStatusColumns[index + 1] = boardStatusColumns[index];
    boardStatusColumns[index] = next;
  }
}

function setCardStatusColumn($card, columnId, columnName) {
  if (!$card || !$card.length) {
    return;
  }

  var id = Number(columnId || 0);
  var name = String(columnName || "").trim();

  if (id > 0) {
    $card.attr("data-status-column-id", id);
  }

  if (name) {
    $card.attr("data-status-column-name", name);
  }
}

function setCardStatusColumnMeta($card, columnId, columnName, columnColor) {
  if (!$card || !$card.length) {
    return;
  }

  setCardStatusColumn($card, columnId, columnName);
  var color = normalizeHexColorValue(columnColor || "", "#DFE1E6");
  $card.attr("data-status-column-color", color);
}

function syncCardStatusMeta($card, force) {
  if (!$card || !$card.length) {
    return;
  }

  var existingId = Number($card.attr("data-status-column-id") || 0);
  var shouldSync = !!force || existingId <= 0;
  if (!shouldSync) {
    return;
  }

  var $column = $card.closest(".task-column");
  var columnId = Number($column.attr("data-column-id") || 0);
  if (columnId <= 0) {
    return;
  }

  var columnName = String(
    $column.find(".task-column-title").first().text() || "",
  ).trim();
  var columnColor = String($column.attr("data-column-color") || "").trim();
  setCardStatusColumnMeta($card, columnId, columnName, columnColor);
}

function getCardStatusColumnId($card) {
  if (!$card || !$card.length) {
    return 0;
  }

  var fromAttr = Number($card.attr("data-status-column-id") || 0);
  if (fromAttr > 0) {
    return fromAttr;
  }

  var fromColumn = Number(
    $card.closest(".task-column").attr("data-column-id") || 0,
  );
  if (fromColumn > 0) {
    return fromColumn;
  }

  return 0;
}

function isBoardViewFieldEnabled(fieldKey) {
  var key = String(fieldKey || "").trim();
  if (!key) {
    return false;
  }

  return !!boardViewFieldState[key];
}

function syncBoardViewSettingsCheckboxes() {
  $(".task-board-view-field-checkbox").each(function () {
    var key = String($(this).attr("data-field-key") || "").trim();
    if (!key) {
      return;
    }

    $(this).prop("checked", isBoardViewFieldEnabled(key));
  });
}

function syncBoardZoomControls() {
  var percent = normalizeBoardZoomPercent(boardZoomPercent);
  boardZoomPercent = percent;

  $("#taskBoardZoomRange").val(String(percent));
  $("#taskBoardZoomValue").text(String(percent) + "%");
  $("#taskBoardZoomOutBtn").prop("disabled", percent <= boardZoomMin);
  $("#taskBoardZoomInBtn").prop("disabled", percent >= boardZoomMax);
}

function isTaskBoardCompactViewport() {
  if (typeof window.matchMedia !== "function") {
    return (window.innerWidth || document.documentElement.clientWidth || 0) <= 991.98;
  }

  return window.matchMedia("(max-width: 991.98px)").matches;
}

function applyBoardZoom() {
  var percent = normalizeBoardZoomPercent(boardZoomPercent);
  var scale = percent / 100;
  var zoomAreas = document.querySelectorAll(".task-board-zoom-area");
  var disableZoomForCompactViewport = isTaskBoardCompactViewport();

  boardZoomPercent = percent;
  if (zoomAreas.length && scale > 0) {
    for (var i = 0; i < zoomAreas.length; i++) {
      var zoomArea = zoomAreas[i];
      if (!zoomArea) {
        continue;
      }

      if (disableZoomForCompactViewport) {
        zoomArea.style.zoom = "100%";
        zoomArea.style.width = "100%";
        continue;
      }

      zoomArea.style.zoom = String(percent) + "%";
      zoomArea.style.width = String(100 / scale) + "%";
    }
  }

  syncBoardZoomControls();

  if (typeof syncTaskBoardSettingsPanelZoom === "function") {
    syncTaskBoardSettingsPanelZoom();
  }
  if (
    typeof isTaskBoardSettingsPanelOpen === "function" &&
    typeof updateTaskBoardSettingsPanelPosition === "function" &&
    isTaskBoardSettingsPanelOpen()
  ) {
    updateTaskBoardSettingsPanelPosition();
  }
}

function setBoardZoomPercent(value, persist) {
  boardZoomPercent = normalizeBoardZoomPercent(value);
  applyBoardZoom();

  if (persist !== false) {
    saveBoardZoomToStorage();
  }
}

function parseCardDate(value) {
  var text = String(value || "").trim();
  if (!/^\d{4}-\d{2}-\d{2}$/.test(text)) {
    return 0;
  }

  var parts = text.split("-");
  var year = Number(parts[0] || 0);
  var month = Number(parts[1] || 0);
  var day = Number(parts[2] || 0);
  if (!year || !month || !day) {
    return 0;
  }

  return new Date(year, month - 1, day).getTime();
}

function withinDateRange(valueDate, fromDate, toDate) {
  var valueMs = parseCardDate(valueDate);
  if (!valueMs) {
    return false;
  }

  var fromMs = parseCardDate(fromDate);
  var toMs = parseCardDate(toDate);
  if (fromMs && valueMs < fromMs) {
    return false;
  }
  if (toMs && valueMs > toMs) {
    return false;
  }

  return true;
}

function isDateInCurrentWeek(valueDate) {
  var valueMs = parseCardDate(valueDate);
  if (!valueMs) {
    return false;
  }

  var now = new Date();
  var dayIndex = (now.getDay() + 6) % 7;
  var weekStart = new Date(
    now.getFullYear(),
    now.getMonth(),
    now.getDate() - dayIndex,
  );
  weekStart.setHours(0, 0, 0, 0);

  var weekEnd = new Date(weekStart.getTime());
  weekEnd.setDate(weekEnd.getDate() + 6);
  weekEnd.setHours(23, 59, 59, 999);

  return valueMs >= weekStart.getTime() && valueMs <= weekEnd.getTime();
}

function splitIdCsv(value) {
  return String(value || "")
    .split(",")
    .map(function (part) {
      return Number(String(part || "").trim() || 0);
    })
    .filter(function (id) {
      return id > 0;
    });
}

function collectParentOptionsFromCards() {
  var cardsById = {};
  var parentIds = {};
  var options = [];

  $app.find(".task-item-card").each(function () {
    var $card = $(this);
    var itemId = Number($card.data("itemId") || 0);
    if (!itemId) {
      return;
    }

    cardsById[itemId] = {
      id: itemId,
      key:
        String($card.attr("data-work-item-key") || "").trim() ||
        buildWorkItemKey(itemId),
      title: String($card.find(".task-item-title").text() || "").trim(),
      isEpic:
        String($card.attr("data-work-type-name") || "")
          .trim()
          .toLowerCase() === "epic",
    };

    var parentItemId = Number($card.attr("data-parent-item-id") || 0);
    if (parentItemId > 0) {
      parentIds[parentItemId] = true;
    }
  });

  Object.keys(parentIds).forEach(function (idText) {
    var id = Number(idText || 0);
    var parent = cardsById[id];
    if (!id || !parent || !parent.isEpic) {
      return;
    }

    options.push(parent);
  });

  options.sort(function (a, b) {
    if (a.id === b.id) {
      return 0;
    }
    return a.id < b.id ? -1 : 1;
  });

  return options;
}

function collectBoardStatusOptions() {
  var fromState = getBoardStatusColumns();
  if (fromState.length) {
    return fromState;
  }

  captureBoardStatusColumnsFromDom();
  return getBoardStatusColumns();
}

function filterSectionCountForPartB() {
  var count = 0;
  if (boardFilterState.partB.dateStart || boardFilterState.partB.dateDue) {
    count++;
  }
  if (boardFilterState.partB.assigneeIds.length) {
    count++;
  }
  if (boardFilterState.partB.createdFrom || boardFilterState.partB.createdTo) {
    count++;
  }
  if (boardFilterState.partB.labelIds.length) {
    count++;
  }
  if (boardFilterState.partB.parentIds.length) {
    count++;
  }
  if (boardFilterState.partB.priorityValues.length) {
    count++;
  }
  if (boardFilterState.partB.reporterIds.length) {
    count++;
  }
  if (boardFilterState.partB.statusIds.length) {
    count++;
  }
  if (boardFilterState.partB.updatedFrom || boardFilterState.partB.updatedTo) {
    count++;
  }
  if (boardFilterState.partB.workTypeIds.length) {
    count++;
  }
  return count;
}

function activeBoardFilterCount() {
  if (boardFilterState.activePart === "A") {
    return (
      (boardFilterState.partA.assignedToMe ? 1 : 0) +
      (boardFilterState.partA.dueThisWeek ? 1 : 0)
    );
  }

  if (boardFilterState.activePart === "B") {
    return filterSectionCountForPartB();
  }

  return 0;
}

function boardFilterPriorityIconClass(priority) {
  var value = String(priority || "").trim();
  if (value === "Highest") {
    return "fa-solid fa-angles-up task-priority-highest";
  }
  if (value === "High") {
    return "fa-solid fa-angle-up task-priority-high";
  }
  if (value === "Medium") {
    return "task-priority-medium";
  }
  if (value === "Low") {
    return "fa-solid fa-angle-down task-priority-low";
  }
  return "fa-solid fa-angles-down task-priority-lowest";
}

function boardFilterMenuHtml() {
  return (
    '<div class="task-board-filter-panel">' +
    '<div class="task-board-filter-head"><span class="task-board-filter-head-title">FILTERS</span><button id="taskBoardFilterClearBtn" type="button" class="btn task-board-filter-clear-btn d-none">Clear</button></div>' +
    '<div class="task-board-filter-fixed">' +
    '<button id="taskBoardFilterPartAAssigned" type="button" class="btn task-board-filter-quick-btn"><i class="fa-regular fa-user"></i><span>Assigned to me</span><i class="fa-solid fa-check task-board-filter-quick-check"></i></button>' +
    '<button id="taskBoardFilterPartADueWeek" type="button" class="btn task-board-filter-quick-btn"><i class="fa-regular fa-calendar"></i><span>Due this week</span><i class="fa-solid fa-check task-board-filter-quick-check"></i></button>' +
    "</div>" +
    '<div class="task-board-filter-scroll">' +
    '<div class="task-board-filter-block"><label class="task-board-filter-label">Date range</label><div class="task-board-filter-date-grid"><div class="task-board-filter-date-col"><span>Start date</span><div class="task-board-filter-date-wrap"><input id="taskBoardFilterDateStart" type="date" class="form-control form-control-sm"><button type="button" class="btn task-board-filter-date-clear d-none" data-target-input="#taskBoardFilterDateStart"><i class="fa-solid fa-xmark"></i></button></div></div><div class="task-board-filter-date-arrow"><i class="fa-solid fa-arrow-right"></i></div><div class="task-board-filter-date-col"><span>Due date</span><div class="task-board-filter-date-wrap"><input id="taskBoardFilterDateDue" type="date" class="form-control form-control-sm"><button type="button" class="btn task-board-filter-date-clear d-none" data-target-input="#taskBoardFilterDateDue"><i class="fa-solid fa-xmark"></i></button></div></div></div></div>' +
    '<div class="task-board-filter-block"><label class="task-board-filter-label">Assignee</label><div id="taskBoardFilterAssigneeList" class="task-board-filter-avatar-grid"></div></div>' +
    '<div class="task-board-filter-block"><label class="task-board-filter-label">Created</label><div class="task-board-filter-date-grid task-board-filter-created-grid"><div class="task-board-filter-date-col"><span>From</span><div class="task-board-filter-date-wrap"><input id="taskBoardFilterCreatedFrom" type="date" class="form-control form-control-sm"><button type="button" class="btn task-board-filter-date-clear d-none" data-target-input="#taskBoardFilterCreatedFrom"><i class="fa-solid fa-xmark"></i></button></div></div><div class="task-board-filter-date-arrow"><i class="fa-solid fa-arrow-right"></i></div><div class="task-board-filter-date-col"><span>To</span><div class="task-board-filter-date-wrap"><input id="taskBoardFilterCreatedTo" type="date" class="form-control form-control-sm"><button type="button" class="btn task-board-filter-date-clear d-none" data-target-input="#taskBoardFilterCreatedTo"><i class="fa-solid fa-xmark"></i></button></div></div></div></div>' +
    '<div class="task-board-filter-block task-board-filter-block-labels"><label class="task-board-filter-label">Labels</label><div id="taskBoardFilterLabelChips" class="task-board-filter-chip-list task-board-filter-chip-list-label"></div><button id="taskBoardFilterLabelSearchToggle" type="button" class="btn task-board-filter-search-toggle task-board-filter-overflow-toggle d-none">...</button><div id="taskBoardFilterLabelSearchPanel" class="task-board-filter-search-panel d-none"><div class="task-board-filter-search-input-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="taskBoardFilterLabelSearchInput" type="text" class="form-control form-control-sm" placeholder="Search labels"></div><div id="taskBoardFilterLabelSearchList" class="task-board-filter-search-list"></div></div></div>' +
    '<div class="task-board-filter-block task-board-filter-block-parent"><label class="task-board-filter-label">Parent</label><div id="taskBoardFilterParentChips" class="task-board-filter-chip-list task-board-filter-chip-list-parent"></div><button id="taskBoardFilterParentSearchToggle" type="button" class="btn task-board-filter-search-toggle task-board-filter-overflow-toggle d-none">...</button><div id="taskBoardFilterParentSearchPanel" class="task-board-filter-search-panel d-none"><div class="task-board-filter-search-input-wrap"><i class="fa-solid fa-magnifying-glass"></i><input id="taskBoardFilterParentSearchInput" type="text" class="form-control form-control-sm" placeholder="Search parent"></div><div id="taskBoardFilterParentSearchList" class="task-board-filter-search-list"></div></div></div>' +
    '<div class="task-board-filter-block"><label class="task-board-filter-label">Priority</label><div id="taskBoardFilterPriorityList" class="task-board-filter-priority-row"></div></div>' +
    '<div class="task-board-filter-block"><label class="task-board-filter-label">Reporter</label><div id="taskBoardFilterReporterList" class="task-board-filter-avatar-grid"></div></div>' +
    '<div class="task-board-filter-block"><label class="task-board-filter-label">Status</label><div id="taskBoardFilterStatusList" class="task-board-filter-status-list"></div></div>' +
    '<div class="task-board-filter-block"><label class="task-board-filter-label">Updated</label><div class="task-board-filter-date-grid task-board-filter-updated-grid"><div class="task-board-filter-date-col"><span>From</span><div class="task-board-filter-date-wrap"><input id="taskBoardFilterUpdatedFrom" type="date" class="form-control form-control-sm"><button type="button" class="btn task-board-filter-date-clear d-none" data-target-input="#taskBoardFilterUpdatedFrom"><i class="fa-solid fa-xmark"></i></button></div></div><div class="task-board-filter-date-arrow"><i class="fa-solid fa-arrow-right"></i></div><div class="task-board-filter-date-col"><span>To</span><div class="task-board-filter-date-wrap"><input id="taskBoardFilterUpdatedTo" type="date" class="form-control form-control-sm"><button type="button" class="btn task-board-filter-date-clear d-none" data-target-input="#taskBoardFilterUpdatedTo"><i class="fa-solid fa-xmark"></i></button></div></div></div></div>' +
    '<div class="task-board-filter-block"><label class="task-board-filter-label">Work type</label><div id="taskBoardFilterWorkTypeList" class="task-board-filter-chip-list"></div></div>' +
    "</div></div>"
  );
}

function ensureBoardFilterMenu() {
  var $menu = $("#taskBoardFilterMenu");
  if (!$menu.length || $menu.children().length) {
    return;
  }

  $menu.html(boardFilterMenuHtml());
}

function assigneeById(userId) {
  var id = Number(userId || 0);
  for (var i = 0; i < state.assignees.length; i++) {
    var item = state.assignees[i] || {};
    if (Number(item.id || 0) === id) {
      return item;
    }
  }
  return null;
}

function renderBoardFilterAssigneeSummary() {
  var $wrap = $("#taskBoardFilterSelectedAssignees");
  if (!$wrap.length) {
    return;
  }

  var html = "";
  if (
    boardFilterState.activePart === "A" &&
    boardFilterState.partA.assignedToMe &&
    currentUserId > 0
  ) {
    var me = assigneeById(currentUserId);
    var meName = me && me.name ? String(me.name) : "Me";
    html +=
      '<span class="task-board-filter-avatar-pill" title="Assigned to me">' +
      escHtml(initials(meName)) +
      "</span>";
  }

  if (
    boardFilterState.activePart === "B" &&
    boardFilterState.partB.assigneeIds.length
  ) {
    for (var i = 0; i < boardFilterState.partB.assigneeIds.length; i++) {
      var user = assigneeById(boardFilterState.partB.assigneeIds[i]);
      var name = user && user.name ? String(user.name) : "U";
      html +=
        '<span class="task-board-filter-avatar-pill" title="' +
        escHtml(name) +
        '">' +
        escHtml(initials(name)) +
        "</span>";
    }
  }

  $wrap.html(html).toggleClass("d-none", !html);
}

function renderBoardFilterAvatarGrid(containerSelector, selectedIds, dataRole) {
  var $container = $(containerSelector);
  if (!$container.length) {
    return;
  }

  var selected = normalizeIdList(selectedIds);
  var html =
    '<button type="button" class="btn task-board-filter-avatar-option ' +
    (selected.indexOf(0) !== -1 ? "active" : "") +
    '" data-role="' +
    escHtml(dataRole) +
    '" data-user-id="0" title="Unassigned"><i class="fa-regular fa-user"></i></button>';

  for (var i = 0; i < state.assignees.length; i++) {
    var item = state.assignees[i] || {};
    var userId = Number(item.id || 0);
    var userName = String(item.name || "").trim();
    if (!userId || !userName) {
      continue;
    }

    html +=
      '<button type="button" class="btn task-board-filter-avatar-option ' +
      (selected.indexOf(userId) !== -1 ? "active" : "") +
      '" data-role="' +
      escHtml(dataRole) +
      '" data-user-id="' +
      userId +
      '" title="' +
      escHtml(userName) +
      '">' +
      escHtml(initials(userName)) +
      "</button>";
  }

  $container.html(html);
}

function renderBoardFilterPriorityOptions() {
  var $container = $("#taskBoardFilterPriorityList");
  if (!$container.length) {
    return;
  }

  var selected = normalizePriorityList(boardFilterState.partB.priorityValues);
  var html = "";
  for (var i = 0; i < taskPriorityValues.length; i++) {
    var value = taskPriorityValues[i];
    html +=
      '<button type="button" class="btn task-board-filter-priority-btn ' +
      (selected.indexOf(value) !== -1 ? "active" : "") +
      '" data-priority-value="' +
      escHtml(value) +
      '" title="' +
      escHtml(value) +
      '">' +
      priorityIconGlyphHtml(value) +
      "</button>";
  }

  $container.html(html);
}

function renderBoardFilterStatusOptions() {
  var $container = $("#taskBoardFilterStatusList");
  if (!$container.length) {
    return;
  }

  var selected = normalizeIdList(boardFilterState.partB.statusIds);
  var statusOptions = collectBoardStatusOptions();
  var html = "";
  for (var i = 0; i < statusOptions.length; i++) {
    var item = statusOptions[i] || {};
    var statusId = Number(item.id || 0);
    var statusName = String(item.name || "").trim();
    if (!statusId || !statusName) {
      continue;
    }

    html +=
      '<label class="task-board-filter-status-option ' +
      (selected.indexOf(statusId) !== -1 ? "active" : "") +
      '"><input class="task-board-filter-status-checkbox" type="checkbox" value="' +
      statusId +
      '"' +
      (selected.indexOf(statusId) !== -1 ? " checked" : "") +
      "><span>" +
      escHtml(statusName) +
      "</span></label>";
  }

  $container.html(
    html || '<div class="task-board-filter-empty">No status</div>',
  );
}

function renderBoardFilterWorkTypeOptions() {
  var $container = $("#taskBoardFilterWorkTypeList");
  if (!$container.length) {
    return;
  }

  var selected = normalizeIdList(boardFilterState.partB.workTypeIds);
  var html = "";
  for (var i = 0; i < state.workTypes.length; i++) {
    var item = normalizeWorkTypeEntry(state.workTypes[i]);
    var workTypeId = Number(item.id || 0);
    if (!workTypeId) {
      continue;
    }

    html +=
      '<button type="button" class="btn task-board-filter-chip ' +
      (selected.indexOf(workTypeId) !== -1 ? "active" : "") +
      '" data-work-type-id="' +
      workTypeId +
      '">' +
      workTypeIconHtml(
        item.svg_icon,
        item.name,
        "task-board-filter-work-type-icon",
      ) +
      '<span class="task-board-filter-work-type-name">' +
      escHtml(item.name || "Task") +
      "</span>" +
      "</button>";
  }

  $container.html(html);
}

function renderBoardFilterLabelChips() {
  var $container = $("#taskBoardFilterLabelChips");
  if (!$container.length) {
    return;
  }

  var selected = normalizeIdList(boardFilterState.partB.labelIds);
  var maxVisible = 6;
  var visibleSelected = selected.slice(0, maxVisible);
  var html = "";
  for (var i = 0; i < visibleSelected.length; i++) {
    var selectedId = visibleSelected[i];
    var label = null;
    for (var j = 0; j < state.labels.length; j++) {
      var candidate = state.labels[j] || {};
      if (Number(candidate.id || 0) === selectedId) {
        label = candidate;
        break;
      }
    }
    if (!label) {
      continue;
    }

    html +=
      '<button type="button" class="btn task-board-filter-chip active" data-remove-label-id="' +
      selectedId +
      '">' +
      escHtml(String(label.name || "")) +
      "</button>";
  }

  if (!html) {
    html =
      '<span class="task-board-filter-chip-placeholder">No labels selected</span>';
  }

  $container.html(html);
  $("#taskBoardFilterLabelSearchToggle").toggleClass(
    "d-none",
    selected.length <= maxVisible,
  );
}

function renderBoardFilterParentChips() {
  var $container = $("#taskBoardFilterParentChips");
  if (!$container.length) {
    return;
  }

  var selected = normalizeIdList(boardFilterState.partB.parentIds);
  var options = collectParentOptionsFromCards();
  var maxVisible = 4;
  var visibleSelected = selected.slice(0, maxVisible);
  var html = "";
  for (var i = 0; i < visibleSelected.length; i++) {
    var id = visibleSelected[i];
    var found = null;
    for (var j = 0; j < options.length; j++) {
      if (options[j].id === id) {
        found = options[j];
        break;
      }
    }

    var text = found ? found.key : buildWorkItemKey(id);
    html +=
      '<button type="button" class="btn task-board-filter-chip active" data-remove-parent-id="' +
      id +
      '">' +
      escHtml(text || "Item #" + id) +
      "</button>";
  }

  if (!html) {
    html =
      '<span class="task-board-filter-chip-placeholder">No parent selected</span>';
  }

  $container.html(html);
  $("#taskBoardFilterParentSearchToggle").toggleClass(
    "d-none",
    selected.length <= maxVisible,
  );
}

function renderBoardFilterLabelSearchList() {
  var $list = $("#taskBoardFilterLabelSearchList");
  if (!$list.length) {
    return;
  }

  var keyword = String(boardFilterState.search.label || "")
    .trim()
    .toLowerCase();
  var selected = normalizeIdList(boardFilterState.partB.labelIds);
  var html = "";

  for (var i = 0; i < state.labels.length; i++) {
    var label = state.labels[i] || {};
    var labelId = Number(label.id || 0);
    var labelName = String(label.name || "").trim();
    if (!labelId || !labelName) {
      continue;
    }

    if (keyword && labelName.toLowerCase().indexOf(keyword) === -1) {
      continue;
    }

    html +=
      '<label class="task-board-filter-search-option"><input type="checkbox" class="task-board-filter-label-checkbox" value="' +
      labelId +
      '"' +
      (selected.indexOf(labelId) !== -1 ? " checked" : "") +
      "><span>" +
      escHtml(labelName) +
      "</span></label>";
  }

  $list.html(
    html || '<div class="task-board-filter-empty">No labels found.</div>',
  );
}

function renderBoardFilterParentSearchList() {
  var $list = $("#taskBoardFilterParentSearchList");
  if (!$list.length) {
    return;
  }

  var keyword = String(boardFilterState.search.parent || "")
    .trim()
    .toLowerCase();
  var selected = normalizeIdList(boardFilterState.partB.parentIds);
  var options = collectParentOptionsFromCards();
  var html = "";

  for (var i = 0; i < options.length; i++) {
    var item = options[i] || {};
    var text = String(item.key || "").trim();
    var title = String(item.title || "").trim();
    var searchText = (text + " " + title).toLowerCase();
    if (keyword && searchText.indexOf(keyword) === -1) {
      continue;
    }

    html +=
      '<label class="task-board-filter-search-option"><input type="checkbox" class="task-board-filter-parent-checkbox" value="' +
      Number(item.id || 0) +
      '"' +
      (selected.indexOf(Number(item.id || 0)) !== -1 ? " checked" : "") +
      "><span>" +
      escHtml(text || "Item #" + item.id) +
      "</span></label>";
  }

  $list.html(
    html || '<div class="task-board-filter-empty">No parent items found.</div>',
  );
}

function updateBoardFilterDateInputs() {
  $("#taskBoardFilterDateStart").val(boardFilterState.partB.dateStart || "");
  $("#taskBoardFilterDateDue").val(boardFilterState.partB.dateDue || "");
  $("#taskBoardFilterCreatedFrom").val(
    boardFilterState.partB.createdFrom || "",
  );
  $("#taskBoardFilterCreatedTo").val(boardFilterState.partB.createdTo || "");
  $("#taskBoardFilterUpdatedFrom").val(
    boardFilterState.partB.updatedFrom || "",
  );
  $("#taskBoardFilterUpdatedTo").val(boardFilterState.partB.updatedTo || "");

  $(".task-board-filter-date-clear").each(function () {
    var target = String($(this).data("targetInput") || "");
    if (!target) {
      return;
    }

    var hasValue = String($(target).val() || "").trim() !== "";
    $(this).toggleClass("d-none", !hasValue);
  });
}

function updateBoardFilterButtons() {
  var count = activeBoardFilterCount();
  var $countBadge = $("#taskBoardFilterCountBadge");
  $countBadge.text(String(count)).toggleClass("d-none", count <= 0);

  $("#taskBoardFilterPartAAssigned").toggleClass(
    "active",
    !!boardFilterState.partA.assignedToMe,
  );
  $("#taskBoardFilterPartADueWeek").toggleClass(
    "active",
    !!boardFilterState.partA.dueThisWeek,
  );

  $("#taskBoardFilterClearBtn").toggleClass("d-none", count <= 0);
  $("#taskBoardFilterBtn").toggleClass("active", count > 0);
}

function renderBoardFilterUi() {
  ensureBoardFilterMenu();
  updateBoardFilterDateInputs();
  renderBoardFilterAvatarGrid(
    "#taskBoardFilterAssigneeList",
    boardFilterState.partB.assigneeIds,
    "assignee",
  );
  renderBoardFilterAvatarGrid(
    "#taskBoardFilterReporterList",
    boardFilterState.partB.reporterIds,
    "reporter",
  );
  renderBoardFilterPriorityOptions();
  renderBoardFilterStatusOptions();
  renderBoardFilterWorkTypeOptions();
  renderBoardFilterLabelChips();
  renderBoardFilterParentChips();
  $("#taskBoardFilterLabelSearchInput").val(
    boardFilterState.search.label || "",
  );
  $("#taskBoardFilterParentSearchInput").val(
    boardFilterState.search.parent || "",
  );
  renderBoardFilterLabelSearchList();
  renderBoardFilterParentSearchList();
  updateBoardFilterButtons();
  renderBoardFilterAssigneeSummary();
}

function setBoardFilterPartA(key, enabled) {
  if (key !== "assignedToMe" && key !== "dueThisWeek") {
    return;
  }

  if (enabled) {
    resetBoardFilterPartB();
    boardFilterState.activePart = "A";
  }

  boardFilterState.partA[key] = !!enabled;
  syncBoardFilterActivePart();
}

function enableBoardFilterPartB() {
  resetBoardFilterPartA();
  boardFilterState.activePart = "B";
}

function commitBoardFilters() {
  syncBoardFilterActivePart();
  saveBoardFiltersToCookie();
  renderBoardFilterUi();
  applyBoardFilters();
}

function cardMatchesPartBFilter($card) {
  var partB = boardFilterState.partB;

  var assigneeId = Number($card.attr("data-assignee-user-id") || 0);
  if (
    partB.assigneeIds.length &&
    partB.assigneeIds.indexOf(assigneeId) === -1
  ) {
    return false;
  }

  var startDate = String($card.attr("data-start-date") || "").trim();
  var dueDate = String($card.attr("data-due-date") || "").trim();
  if (partB.dateStart && startDate !== partB.dateStart) {
    return false;
  }
  if (partB.dateDue && dueDate !== partB.dateDue) {
    return false;
  }

  var createdDate = String($card.attr("data-create-date") || "").trim();
  if (
    (partB.createdFrom || partB.createdTo) &&
    !withinDateRange(createdDate, partB.createdFrom, partB.createdTo)
  ) {
    return false;
  }

  var updatedDate = String($card.attr("data-update-date") || "").trim();
  if (
    (partB.updatedFrom || partB.updatedTo) &&
    !withinDateRange(updatedDate, partB.updatedFrom, partB.updatedTo)
  ) {
    return false;
  }

  if (partB.labelIds.length) {
    var labelIds = splitIdCsv($card.attr("data-label-ids"));
    var hasLabel = partB.labelIds.some(function (id) {
      return labelIds.indexOf(id) !== -1;
    });
    if (!hasLabel) {
      return false;
    }
  }

  if (partB.parentIds.length) {
    var parentId = Number($card.attr("data-parent-item-id") || 0);
    if (partB.parentIds.indexOf(parentId) === -1) {
      return false;
    }
  }

  if (partB.priorityValues.length) {
    var priority = String($card.attr("data-priority") || "Medium").trim();
    if (partB.priorityValues.indexOf(priority) === -1) {
      return false;
    }
  }

  if (partB.reporterIds.length) {
    var reporterId = Number($card.attr("data-reporter-user-id") || 0);
    if (partB.reporterIds.indexOf(reporterId) === -1) {
      return false;
    }
  }

  if (partB.statusIds.length) {
    var statusColumnId = getCardStatusColumnId($card);
    if (partB.statusIds.indexOf(statusColumnId) === -1) {
      return false;
    }
  }

  if (partB.workTypeIds.length) {
    var workTypeId = Number($card.attr("data-work-type-id") || 0);
    if (partB.workTypeIds.indexOf(workTypeId) === -1) {
      return false;
    }
  }

  return true;
}

function postAction(payload, onDone, onFail) {
  if (!ajaxUrl) {
    notify("Missing ajax endpoint.");
    if (typeof onFail === "function") {
      onFail();
    }
    return;
  }

  var requestData = payload || {};
  if (state.currentProjectId > 0) {
    if (requestData instanceof FormData) {
      if (!requestData.has("project_id")) {
        requestData.append("project_id", state.currentProjectId);
      }
    } else {
      requestData = $.extend({}, requestData, {
        project_id: state.currentProjectId,
      });
    }
  }
  if (csrfToken) {
    if (requestData instanceof FormData) {
      if (!requestData.has("csrf_token")) {
        requestData.append("csrf_token", csrfToken);
      }
    } else {
      requestData = $.extend({}, requestData, { csrf_token: csrfToken });
    }
  }

  var ajaxOptions = {
    url: ajaxUrl,
    method: "POST",
    dataType: "json",
    data: requestData,
    timeout: 30000,
  };

  if (requestData instanceof FormData) {
    ajaxOptions.processData = false;
    ajaxOptions.contentType = false;
    ajaxOptions.timeout = 60000;
  }

  return $.ajax(ajaxOptions)
    .done(function (res) {
      if (!res || !res.ok) {
        notify(res && res.message ? res.message : "Request failed.");
        if (typeof onFail === "function") {
          onFail(res);
        }
        return;
      }

      if (typeof onDone === "function") {
        onDone(res);
      }
    })
    .fail(function (xhr) {
      var recovered = recoverJsonResponse(xhr);
      if (recovered) {
        if (!recovered.ok) {
          notify(recovered.message ? recovered.message : "Request failed.");
          if (typeof onFail === "function") {
            onFail(recovered);
          }
          return;
        }

        if (typeof onDone === "function") {
          onDone(recovered);
        }
        return;
      }

      var msg = "Request failed.";
      if (xhr && xhr.status) {
        msg += " (" + xhr.status + ")";
      }
      notify(msg);
      if (typeof onFail === "function") {
        onFail();
      }
    });
}

function getCreateProjectModalInstance() {
  var modalEl = document.getElementById("taskCreateProjectModal");
  if (!modalEl || typeof bootstrap === "undefined" || !bootstrap.Modal) {
    return null;
  }
  return bootstrap.Modal.getOrCreateInstance(modalEl);
}

$(document).on("click", "#taskCreateProjectBtn", function (e) {
  e.preventDefault();
  var modal = getCreateProjectModalInstance();
  if (!modal) {
    return;
  }
  $("#taskCreateProjectName").val("");
  modal.show();
  window.setTimeout(function () {
    $("#taskCreateProjectName").trigger("focus");
  }, 120);
});

$(document).on("click", "#taskCreateProjectSubmitBtn", function () {
  var name = $.trim($("#taskCreateProjectName").val() || "");
  if (!name) {
    notify("Project task name is required.");
    $("#taskCreateProjectName").trigger("focus");
    return;
  }

  var $btn = $(this);
  $btn.prop("disabled", true);
  postAction(
    {
      task_action: "create_project",
      project_name: name,
    },
    function (res) {
      var modal = getCreateProjectModalInstance();
      if (modal) {
        modal.hide();
      }
      if (res && res.project && Number(res.project.id || 0) > 0) {
        window.location.href =
          state.siteUrl +
          "/task/summary.php?project_id=" +
          Number(res.project.id || 0);
        return;
      }
      window.location.reload();
    },
    function () {
      $btn.prop("disabled", false);
    },
  ).always(function () {
    $btn.prop("disabled", false);
  });
});

$(document).on("keydown", "#taskCreateProjectName", function (e) {
  if (e.key === "Enter") {
    e.preventDefault();
    $("#taskCreateProjectSubmitBtn").trigger("click");
  }
});

function recoverJsonResponse(xhr) {
  var responseText =
    xhr && typeof xhr.responseText === "string" ? xhr.responseText : "";
  if (!responseText || !(xhr && Number(xhr.status || 0) === 200)) {
    return null;
  }

  var start = responseText.indexOf("{");
  var end = responseText.lastIndexOf("}");
  if (start === -1 || end === -1 || end < start) {
    return null;
  }

  try {
    return JSON.parse(responseText.slice(start, end + 1));
  } catch (err) {
    return null;
  }
}

function workTypeMenuHtml() {
  var html = "";
  for (var i = 0; i < state.workTypes.length; i++) {
    var workType = normalizeWorkTypeEntry(state.workTypes[i]);
    html +=
      '<li><a class="dropdown-item task-work-type-option" href="#" data-work-type-id="' +
      Number(workType.id || 0) +
      '" data-work-type-name="' +
      escHtml(workType.name) +
      '" data-work-type-remark="' +
      escHtml(workType.remark) +
      '" data-work-type-icon="' +
      escHtml(workType.svg_icon) +
      '">' +
      workTypeIconHtml(
        workType.svg_icon,
        workType.name,
        "task-work-type-option-icon",
      ) +
      " " +
      escHtml(workType.name || "Task") +
      "</a></li>";
  }

  if (state.isProjectOwner) {
    html += '<li><hr class="dropdown-divider"></li>';
    html +=
      '<li><a class="dropdown-item task-work-type-action" href="#" data-action="add">Add work type</a></li>';
    html +=
      '<li><a class="dropdown-item task-work-type-action" href="#" data-action="edit">Edit work type</a></li>';
  }

  return html;
}

function assigneeMenuHtml() {
  var html =
    '<li class="task-assignee-search-wrap px-2 pb-2"><input type="text" class="form-control form-control-sm task-assignee-search-input" placeholder="Search assignee"></li>' +
    '<li><a class="dropdown-item task-assignee-option" href="#" data-user-id="0" data-user-name="Unassigned"><span class="task-assignee-option-avatar"><i class="fa-regular fa-user"></i></span><span class="task-assignee-option-text">Unassigned</span></a></li>' +
    '<li><hr class="dropdown-divider"></li>';

  for (var i = 0; i < state.assignees.length; i++) {
    var item = state.assignees[i] || {};
    var assigneeName = String(item.name || "").trim();
    var line =
      '<span class="task-assignee-option-avatar">' +
      escHtml(initials(assigneeName)) +
      '</span><span class="task-assignee-option-text">' +
      escHtml(assigneeName);
    if (item.email) {
      line +=
        '<br><small class="text-muted">' + escHtml(item.email) + "</small>";
    }
    line += "</span>";
    html +=
      '<li><a class="dropdown-item task-assignee-option" href="#" data-user-id="' +
      Number(item.id || 0) +
      '" data-user-name="' +
      escHtml(assigneeName) +
      '">' +
      line +
      "</a></li>";
  }

  return html;
}

function assigneeButtonInner(userId, userName) {
  var id = Number(userId || 0);
  var name = String(userName || "").trim();
  if (id > 0 && name) {
    return escHtml(initials(name));
  }
  return '<i class="fa-regular fa-user"></i>';
}

function labelsBySearch(term) {
  var query = String(term || "")
    .trim()
    .toLowerCase();
  if (!query) {
    return state.labels.slice();
  }

  return state.labels.filter(function (label) {
    return (
      String(label.name || "")
        .toLowerCase()
        .indexOf(query) !== -1
    );
  });
}

function getItemLabelsFromCard($card) {
  var csv = String($card.attr("data-label-ids") || "").trim();
  if (!csv) {
    return [];
  }

  return csv
    .split(",")
    .map(function (value) {
      return Number(String(value).trim() || 0);
    })
    .filter(function (value) {
      return value > 0;
    });
}

function setCardLabels($card, labels) {
  var safeLabels = Array.isArray(labels) ? labels : [];
  var ids = [];
  var html = "";

  for (var i = 0; i < safeLabels.length; i++) {
    var label = safeLabels[i] || {};
    var id = Number(label.id || 0);
    var name = String(label.name || "").trim();
    if (!id || !name) {
      continue;
    }
    ids.push(id);
    html +=
      '<span class="task-label-pill" style="' +
      labelPillStyle(label.color, "#DCE8FF") +
      '">' +
      escHtml(name) +
      "</span>";
  }

  $card.attr("data-label-ids", ids.join(","));
  $card
    .find(".task-item-label-submenu-toggle")
    .html(
      (ids.length ? "Edit label" : "Add labels") +
      ' <i class="fa-solid fa-chevron-right task-submenu-chevron"></i>',
    );
  var $row = $card.find(".task-item-label-row");
  if (!ids.length) {
    $row.remove();
    applyBoardViewSettingsToCard($card);
    return;
  }

  if (!$row.length) {
    $row = $('<div class="task-item-label-row"></div>');
    $card.find(".task-item-meta").before($row);
  }

  $row.html(html);
  applyBoardViewSettingsToCard($card);
}

function getItemStatusLabelIdsFromCard($card) {
  var csv = String($card.attr("data-task-status-label-ids") || "").trim();
  if (!csv) {
    return [];
  }

  return csv
    .split(",")
    .map(function (value) {
      return Number(String(value).trim() || 0);
    })
    .filter(function (value) {
      return value > 0;
    });
}

function setCardTaskStatusLabels($card, statusLabelIds) {
  var ids = normalizeStatusLabelIdList(statusLabelIds);
  $card.attr("data-task-status-label-ids", ids.join(","));

  var $toggle = $card.find(".task-item-status-label-submenu-toggle");
  if ($toggle.length) {
    $toggle.html(
      (ids.length ? "Edit task status labels" : "Add task status labels") +
      ' <i class="fa-solid fa-chevron-right task-submenu-chevron"></i>',
    );
  }

  applyBoardViewSettingsToCard($card);
}

function formatCardDateLabel(value) {
  var text = String(value || "").trim();
  if (!/^\d{4}-\d{2}-\d{2}$/.test(text)) {
    return "";
  }

  var parts = text.split("-");
  var year = Number(parts[0] || 0);
  var month = Number(parts[1] || 0);
  var day = Number(parts[2] || 0);
  if (!year || !month || !day) {
    return "";
  }

  var date = new Date(year, month - 1, day);
  if (Number.isNaN(date.getTime())) {
    return "";
  }

  var monthNames = [
    "Jan",
    "Feb",
    "Mar",
    "Apr",
    "May",
    "Jun",
    "Jul",
    "Aug",
    "Sep",
    "Oct",
    "Nov",
    "Dec",
  ];

  return (
    monthNames[date.getMonth()] +
    " " +
    date.getDate() +
    ", " +
    date.getFullYear()
  );
}

function getCardDueDateState(value) {
  var text = formatCardDateLabel(value);
  var ms = parseCardDate(value);
  if (!text || !ms) {
    return {
      text: text,
      isToday: false,
      isOverdue: false,
      isAlert: false,
    };
  }

  var now = new Date();
  var todayMs = new Date(
    now.getFullYear(),
    now.getMonth(),
    now.getDate(),
  ).getTime();
  var isToday = ms === todayMs;
  var isOverdue = ms < todayMs;

  return {
    text: text,
    isToday: isToday,
    isOverdue: isOverdue,
    isAlert: isToday || isOverdue,
  };
}

function buildCardDueDateFieldValueHtml(value) {
  var due = getCardDueDateState(value);
  if (!due.text) {
    return "";
  }

  return (
    '<span class="task-item-due-date-value' +
    (due.isAlert ? " task-item-due-date-value-alert" : "") +
    '">' +
    escHtml(due.text) +
    (due.isAlert
      ? ' <i class="fa-solid fa-triangle-exclamation task-item-due-date-value-icon" aria-hidden="true"></i>'
      : "") +
    "</span>"
  );
}

function formatRelativeFromCardDate(value) {
  var ms = parseCardDate(value);
  if (!ms) {
    return "";
  }

  var diffMs = Math.max(0, Date.now() - ms);
  var dayMs = 86400000;
  if (diffMs < dayMs) {
    return "Today";
  }

  var days = Math.floor(diffMs / dayMs);
  if (days < 30) {
    return days === 1 ? "1 day ago" : days + " days ago";
  }

  var months = Math.floor(days / 30);
  if (months < 12) {
    return months === 1 ? "1 month ago" : months + " months ago";
  }

  var years = Math.floor(days / 365);
  return years === 1 ? "1 year ago" : years + " years ago";
}

function formatMinutesCompact(value) {
  var minutes = Number(value || 0);
  if (!minutes || minutes <= 0) {
    return "";
  }

  var hours = Math.floor(minutes / 60);
  var remain = minutes % 60;
  if (hours > 0 && remain > 0) {
    return hours + "h " + remain + "m";
  }

  if (hours > 0) {
    return hours + "h";
  }

  return minutes + "m";
}

function formatEstimateCompact(value, unit) {
  var amount = Number(value || 0);
  if (!amount || amount <= 0) {
    return "";
  }

  var normalizedUnit = String(unit || "minutes")
    .trim()
    .toLowerCase();
  if (normalizedUnit === "minutes") {
    return formatMinutesCompact(amount);
  }

  if (normalizedUnit === "hours") {
    return amount + "h";
  }

  if (normalizedUnit === "days") {
    return amount + "d";
  }

  if (normalizedUnit === "weeks") {
    return amount + "w";
  }

  return String(amount);
}

function priorityIconGlyphHtml(priority) {
  var iconClass = boardFilterPriorityIconClass(priority);
  if (iconClass === "task-priority-medium") {
    return '<span class="task-priority-medium-icon" aria-hidden="true"></span>';
  }
  return '<i class="' + escHtml(iconClass) + '"></i>';
}

function resolveParentDisplayForCard($card) {
  var parentId = Number($card.attr("data-parent-item-id") || 0);
  if (!parentId) {
    return "";
  }

  var stored = String($card.attr("data-parent-display") || "").trim();
  if (stored && stored.toLowerCase() !== "none") {
    return stored;
  }

  var $parentCard = $app.find(
    '.task-item-card[data-item-id="' + parentId + '"]',
  );
  if (!$parentCard.length) {
    return buildWorkItemKey(parentId) || "";
  }

  var key = String($parentCard.attr("data-work-item-key") || "").trim();
  var title = String(
    $parentCard.find(".task-item-title").first().text() || "",
  ).trim();
  var display = String((key ? key + " " : "") + title).trim();
  if (display) {
    $card.attr("data-parent-display", display);
  }
  return display;
}

function buildCardFieldRowsHtml($card) {
  var html = "";

  function appendFieldRow(fieldKey, label, valueHtml, isHtml) {
    if (!isBoardViewFieldEnabled(fieldKey)) {
      return;
    }

    var valueText = String(valueHtml || "").trim();
    if (!valueText) {
      return;
    }

    html +=
      '<div class="task-item-field-row" data-view-field="' +
      escHtml(fieldKey) +
      '">' +
      '<span class="task-item-field-label">' +
      escHtml(label) +
      "</span>" +
      '<span class="task-item-field-value">' +
      (isHtml ? valueText : escHtml(valueText)) +
      "</span>" +
      "</div>";
  }

  appendFieldRow(
    "due_date",
    "Due date",
    buildCardDueDateFieldValueHtml($card.attr("data-due-date")),
    true,
  );
  appendFieldRow(
    "created",
    "Created",
    formatRelativeFromCardDate($card.attr("data-create-date")) ||
    formatCardDateLabel($card.attr("data-create-date")),
    false,
  );
  appendFieldRow(
    "start_date",
    "Start date",
    formatCardDateLabel($card.attr("data-start-date")),
    false,
  );
  var itemId = Number($card.data("itemId") || 0);
  var currentParentId = Number($card.attr("data-parent-item-id") || 0);
  var parentDisplay = resolveParentDisplayForCard($card);
  var parentOptions = getBoardEpicParentOptions(itemId);
  var parentOptionsHtml =
    '<option value="0"' +
    (currentParentId <= 0 ? " selected" : "") +
    '">None</option>';
  var hasCurrentParentOption = currentParentId <= 0;

  for (var p = 0; p < parentOptions.length; p++) {
    var parentOption = parentOptions[p] || {};
    var parentOptionId = Number(parentOption.id || 0);
    if (!parentOptionId) {
      continue;
    }

    if (parentOptionId === currentParentId) {
      hasCurrentParentOption = true;
    }

    parentOptionsHtml +=
      '<option value="' +
      parentOptionId +
      '"' +
      (parentOptionId === currentParentId ? " selected" : "") +
      '">' +
      escHtml(String(parentOption.display || parentOption.title || "")) +
      "</option>";
  }

  if (currentParentId > 0 && !hasCurrentParentOption) {
    var fallbackParentDisplay =
      parentDisplay || buildWorkItemKey(currentParentId);
    parentOptionsHtml +=
      '<option value="' +
      currentParentId +
      '" selected>' +
      escHtml(String(fallbackParentDisplay || "").trim()) +
      "</option>";
  }

  appendFieldRow(
    "amendement_date",
    "Amendement date",
    formatCardDateLabel($card.attr("data-amendement-date")),
    false,
  );
  appendFieldRow(
    "amendement_time",
    "Amendement time",
    formatMinutesCompact($card.attr("data-amendement-time-minutes")),
    false,
  );
  appendFieldRow(
    "second_amendement_date",
    "Second amen-date",
    formatCardDateLabel($card.attr("data-second-amendement-date")),
    false,
  );
  appendFieldRow(
    "second_amendement_time",
    "Second amen-time",
    formatMinutesCompact($card.attr("data-second-amendement-time-minutes")),
    false,
  );
  appendFieldRow(
    "parent",
    "Parent",
    '<select class="form-select form-select-sm task-item-parent-select" data-item-id="' +
    itemId +
    '"' +
    (canEdit ? "" : " disabled") +
    ">" +
    parentOptionsHtml +
    "</select>",
    true,
  );

  var reporterName = String($card.attr("data-reporter-name") || "").trim();
  appendFieldRow(
    "reporter",
    "Reporter",
    reporterName
      ? '<span class="task-item-field-reporter"><span class="task-item-field-avatar">' +
      escHtml(initials(reporterName)) +
      "</span><span>" +
      escHtml(reporterName) +
      "</span></span>"
      : "",
    true,
  );

  appendFieldRow(
    "original_estimate",
    "Original estimate",
    formatEstimateCompact(
      Number($card.attr("data-original-estimate-value") || 0),
      $card.attr("data-original-estimate-unit"),
    )
      ? '<span class="task-item-field-badge">' +
      escHtml(
        formatEstimateCompact(
          Number($card.attr("data-original-estimate-value") || 0),
          $card.attr("data-original-estimate-unit"),
        ),
      ) +
      "</span>"
      : "",
    true,
  );
  appendFieldRow(
    "updated",
    "Updated",
    formatRelativeFromCardDate($card.attr("data-update-date")) ||
    formatCardDateLabel($card.attr("data-update-date")),
    false,
  );

  return html;
}
