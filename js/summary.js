/**
 * Task Summary – Jira-style project summary page
 * Depends on: jQuery, Chart.js, Bootstrap modal, window.summaryConfig
 */
(function ($) {
  "use strict";

  var cfg = window.summaryConfig || {};
  var ajaxUrl = cfg.ajaxUrl || "summary.php";
  var csrfToken = String(cfg.csrfToken || "");
  var assignees = cfg.assignees || [];
  var workTypes = cfg.workTypes || [];
  var boardColumns = cfg.columns || [];
  var parentOptions = cfg.parentOptions || [];
  var currentUserId = String(cfg.currentUserId || "");
  var currentUserName = String(cfg.currentUserName || "Current User");
  var statusLabels = cfg.statusLabels || [];
  var boardUrl = cfg.boardUrl || "board.php";
  var currentProjectId = Number(cfg.currentProjectId || 0);

  var stats = cfg.initialStats || {};
  var activityData = cfg.initialActivity || {};

  var summaryFilterCookieName =
    "task_summary_filters_v1_project_" + String(currentProjectId > 0 ? currentProjectId : 0);
  var summaryFilterKeys = [
    "assignee",
    "created",
    "due_date",
    "parent",
    "priority",
    "status",
    "updated",
    "work_type",
  ];
  var summaryFilterLabels = {
    assignee: "Assignee",
    created: "Created",
    due_date: "Due date",
    parent: "Parent",
    priority: "Priority",
    status: "Status",
    updated: "Updated",
    work_type: "Work type",
  };
  var summaryPriorityOptions = ["Highest", "High", "Medium", "Low", "Lowest"];
  var activeFilterKeys = []; // enabled filter types
  var filterValues = {}; // filterKey -> value object
  var openedSubFilterKey = "";
  var pieChart = null;
  var activityModalPageSize = "10";
  var activityModalSearchText = "";
  var activityModalSearchTimer = null;

  /* ───── Pie chart color palette (Jira-like) ───── */
  var PIE_COLORS = [
    "#4bce97",
    "#579dff",
    "#f87168",
    "#fea362",
    "#9f8fef",
    "#e774bb",
    "#6cc3e0",
    "#8590a2",
    "#c1c7d0",
    "#b8d4a3",
  ];

  var PRIORITY_COLORS = {
    Highest: "#c9372c",
    High: "#e2483d",
    Medium: "#e97f33",
    Low: "#0c66e4",
    Lowest: "#579dff",
  };

  var PRIORITY_ICONS = {
    Highest: '<i class="fa-solid fa-angles-up" style="color:#c9372c"></i>',
    High: '<i class="fa-solid fa-angle-up" style="color:#e2483d"></i>',
    Medium: '<i class="fa-solid fa-equals" style="color:#e97f33"></i>',
    Low: '<i class="fa-solid fa-angle-down" style="color:#0c66e4"></i>',
    Lowest: '<i class="fa-solid fa-angles-down" style="color:#579dff"></i>',
  };

  /* ───── Cookie helpers ───── */
  function setCookie(name, value, days) {
    var expires = "";
    if (days > 0) {
      var d = new Date();
      d.setTime(d.getTime() + days * 86400000);
      expires = "; expires=" + d.toUTCString();
    }
    document.cookie =
      name +
      "=" +
      encodeURIComponent(value) +
      expires +
      "; path=/; SameSite=Lax";
  }

  function getCookie(name) {
    var key = name + "=";
    var parts = document.cookie ? document.cookie.split(";") : [];
    for (var i = 0; i < parts.length; i++) {
      var c = parts[i].trim();
      if (c.indexOf(key) === 0)
        return decodeURIComponent(c.substring(key.length));
    }
    return "";
  }

  function saveFilters() {
    setCookie(
      summaryFilterCookieName,
      JSON.stringify({ keys: activeFilterKeys, values: filterValues }),
      180,
    );
  }

  function loadFilters() {
    var raw = getCookie(summaryFilterCookieName);
    if (!raw) return;
    try {
      var saved = JSON.parse(raw);
      if (saved && saved.keys && $.isArray(saved.keys)) {
        activeFilterKeys = saved.keys.filter(function (key) {
          return summaryFilterKeys.indexOf(key) !== -1;
        });
      }
      if (saved && saved.values && typeof saved.values === "object") {
        filterValues = saved.values;
      }
    } catch (e) {}
  }

  /* ───── Escape ───── */
  function esc(str) {
    var el = document.createElement("span");
    el.textContent = str;
    return el.innerHTML;
  }

  function escAttr(value) {
    return esc(value).replace(/"/g, "&quot;");
  }

  function normalizeHexColorValueLocal(color, fallback) {
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

  function getReadableTextColorLocal(backgroundColor) {
    return "#292A2E";
  }

  function getColumnMetaByIdLocal(id) {
    var strId = String(id);
    for (var i = 0; i < boardColumns.length; i++) {
      if (String(boardColumns[i].id) === strId) {
        return boardColumns[i];
      }
    }
    return null;
  }

  function buildBoardModalCardMock(item) {
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

  function openSummaryItemDetailModal(item) {
    if (typeof window.openItemDetailModal === "function") {
      window.openItemDetailModal(buildBoardModalCardMock(item));
      return true;
    }
    return false;
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
        '<div class="task-item-activity-rich-value">' +
        normalizedValue +
        "</div>"
      );
    }

    return (
      '<span class="task-item-activity-badge">' +
      esc(normalizedValue) +
      "</span>"
    );
  }

  /* ───── Time formatting ───── */
  function timeAgo(dateStr, timeStr) {
    if (!dateStr) return "";
    var dt = new Date(dateStr + "T" + (timeStr || "00:00:00"));
    var now = new Date();
    var diff = Math.floor((now - dt) / 1000);
    if (diff < 60) return "just now";
    if (diff < 3600) return Math.floor(diff / 60) + " minutes ago";
    if (diff < 86400) return "about " + Math.floor(diff / 3600) + " hours ago";
    if (diff < 172800) return "Yesterday";
    if (diff < 604800) return Math.floor(diff / 86400) + " days ago";
    return dateStr;
  }

  function groupByDate(rows) {
    var groups = {};
    var now = new Date();
    var todayStr = now.toISOString().slice(0, 10);
    var yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);
    var yesterdayStr = yesterday.toISOString().slice(0, 10);

    for (var i = 0; i < rows.length; i++) {
      var r = rows[i];
      var d = r.create_date || "";
      var label;
      if (d === todayStr) label = "Today";
      else if (d === yesterdayStr) label = "Yesterday";
      else label = d;
      if (!groups[label]) groups[label] = [];
      groups[label].push(r);
    }
    return groups;
  }

  /* ───── Render helpers ───── */
  function getInitials(name) {
    if (!name) return "?";
    var parts = name.trim().split(/\s+/);
    return parts.length >= 2
      ? (parts[0][0] + parts[1][0]).toUpperCase()
      : name.substring(0, 2).toUpperCase();
  }

  function renderActivityItem(row) {
    var avatar = getInitials(row.actor_name);
    var recordType = String(row.record_type || "history").toLowerCase();
    var itemLink = "";
    if (row.work_item_key && row.item_id) {
      var statusName = "Unknown";
      for (var i = 0; i < boardColumns.length; i++) {
        if (String(boardColumns[i].id) === String(row.item_task_status)) {
          statusName = boardColumns[i].name;
          break;
        }
      }
      var statusBadge = '<span class="summary-activity-status-badge" style="background:#e8eaf0;color:#44546f;border:1px solid #dcdfe4;margin-left:6px;padding:2px 6px;vertical-align:middle;cursor:pointer;text-transform:none;">' + esc(statusName) + '</span>';

      itemLink =
        ' on <a class="task-item-activity-item-link summary-activity-item-link" href="' +
        boardUrl +
        "?open_item=" +
        row.item_id +
        '" data-item-id="' +
        Number(row.item_id || 0) +
        '" data-item-title="' +
        escAttr(row.item_title || "") +
        '" data-work-item-key="' +
        escAttr(row.work_item_key || "") +
        '" data-work-type-name="' +
        escAttr(row.work_type_name || "Task") +
        '" data-work-type-icon="' +
        escAttr(row.work_type_svg_icon || "") +
        '" data-assignee-name="' +
        escAttr(row.item_assignee_name || "Unassigned") +
        '" data-priority="' +
        escAttr(row.item_priority || "Medium") +
        '" data-status-id="' +
        escAttr(row.item_task_status || "") +
        '" data-description="' +
        escAttr(String(row.to_value || "")) +
        '" data-record-type="' +
        escAttr(recordType) +
        '">' +
        esc(row.work_item_key) +
        ":" +
        esc(row.item_title || "") +
        "</a>" + statusBadge;
    }

    var detail = "";
    var fromValue = String(row.from_value || "").trim();
    var toValue = String(row.to_value || "").trim();
    if (recordType === "history" && (fromValue || toValue)) {
      var fieldName = String(row.field_name || "").trim();
      detail =
        '<div class="task-item-activity-diff">' +
        buildActivityValueHtml(fieldName, fromValue) +
        '<span class="task-item-activity-arrow"><i class="fa-solid fa-arrow-right"></i></span>' +
        buildActivityValueHtml(fieldName, toValue) +
        "</div>";
    } else if (
      (recordType === "comment" || recordType === "reply") &&
      row.comment_html
    ) {
      detail =
        '<div class="task-item-activity-comment-body">' +
        String(row.comment_html) +
        "</div>";
    }

    return (
      '<div class="task-item-activity-entry summary-activity-item">' +
      '<div class="task-item-activity-avatar summary-activity-avatar">' +
      esc(avatar) +
      "</div>" +
      '<div class="task-item-activity-content summary-activity-content">' +
      '<div class="task-item-activity-text summary-activity-text">' +
      "<strong>" +
      esc(row.actor_name) +
      "</strong> " +
      esc(row.remark) +
      itemLink +
      "</div>" +
      '<div class="task-item-activity-meta">' +
      '<div class="task-item-activity-ago summary-activity-time">' +
      esc(timeAgo(row.create_date, row.create_time)) +
      "</div>" +
      "</div>" +
      detail +
      "</div>" +
      "</div>"
    );
  }

  function renderActivityList(container, rows) {
    if (!rows || rows.length === 0) {
      container.html(
        '<div class="task-item-activity-empty">No recent activity.</div>',
      );
      return;
    }
    var groups = groupByDate(rows);
    var html = '<div class="task-item-activity-feed">';
    for (var label in groups) {
      html +=
        '<div class="summary-activity-group-label">' + esc(label) + "</div>";
      for (var i = 0; i < groups[label].length; i++) {
        html += renderActivityItem(groups[label][i]);
      }
    }
    html += "</div>";
    container.html(html);
  }

  function renderPieChart(statusCounts, totalItems, parentCount) {
    var labels = [];
    var data = [];
    var colors = [];
    for (var i = 0; i < statusCounts.length; i++) {
      labels.push(statusCounts[i].name + ": " + statusCounts[i].count);
      data.push(statusCounts[i].count);
      colors.push(PIE_COLORS[i % PIE_COLORS.length]);
    }
    if (parentCount > 0) {
      labels.push("Parent: " + parentCount);
      data.push(parentCount);
      colors.push(PIE_COLORS[statusCounts.length % PIE_COLORS.length]);
    }

    // Render legend
    var legendHtml = "";
    for (var j = 0; j < labels.length; j++) {
      legendHtml +=
        '<div class="summary-pie-legend-item">' +
        '<span class="summary-pie-legend-dot" style="background:' +
        colors[j] +
        '"></span>' +
        "<span>" +
        esc(labels[j]) +
        "</span>" +
        "</div>";
    }
    $("#statusPieLegend").html(legendHtml);

    // Update center
    $("#pieCenterLabel .summary-pie-total").text(totalItems);

    var ctx = document.getElementById("statusPieChart");
    if (!ctx) return;

    if (pieChart) {
      pieChart.destroy();
    }

    pieChart = new Chart(ctx.getContext("2d"), {
      type: "doughnut",
      data: {
        labels: labels,
        datasets: [
          {
            data: data,
            backgroundColor: colors,
            borderWidth: 2,
            borderColor: "#fff",
          },
        ],
      },
      options: {
        responsive: false,
        cutout: "65%",
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                return ctx.label;
              },
            },
          },
        },
      },
    });
  }

  function renderPriorityBreakdown(priorityCounts) {
    var container = $("#priorityBreakdown");
    if (!priorityCounts || priorityCounts.length === 0) {
      container.html(
        '<p style="color:#8993a4;font-size:13px">No data available.</p>',
      );
      return;
    }
    var maxCount = 0;
    for (var i = 0; i < priorityCounts.length; i++) {
      if (priorityCounts[i].count > maxCount)
        maxCount = priorityCounts[i].count;
    }

    var html = "";
    for (var j = 0; j < priorityCounts.length; j++) {
      var p = priorityCounts[j];
      var pct = maxCount > 0 ? (p.count / maxCount) * 100 : 0;
      var icon = PRIORITY_ICONS[p.name] || '<i class="fa-solid fa-minus"></i>';
      var color = PRIORITY_COLORS[p.name] || "#8590a2";
      html +=
        '<div class="summary-priority-item">' +
        '<div class="summary-priority-icon">' +
        icon +
        "</div>" +
        '<div class="summary-priority-name">' +
        esc(p.name) +
        "</div>" +
        '<div class="summary-priority-bar-wrap">' +
        '<div class="summary-priority-bar" style="width:' +
        pct +
        "%;background:" +
        color +
        '"></div>' +
        "</div>" +
        '<div class="summary-priority-count">' +
        p.count +
        "</div>" +
        "</div>";
    }
    container.html(html);
  }

  function renderWorkTypeBreakdown(workTypeCounts) {
    var container = $("#workTypeBreakdown");
    if (!workTypeCounts || workTypeCounts.length === 0) {
      container.html(
        '<p style="color:#8993a4;font-size:13px">No data available.</p>',
      );
      return;
    }
    var maxCount = 0;
    for (var i = 0; i < workTypeCounts.length; i++) {
      if (workTypeCounts[i].count > maxCount)
        maxCount = workTypeCounts[i].count;
    }

    var html = "";
    for (var j = 0; j < workTypeCounts.length; j++) {
      var w = workTypeCounts[j];
      var pct = maxCount > 0 ? (w.count / maxCount) * 100 : 0;
      html +=
        '<div class="summary-worktype-item">' +
        '<div class="summary-worktype-icon"></div>' +
        '<div class="summary-worktype-name">' +
        esc(w.name) +
        "</div>" +
        '<div class="summary-worktype-bar-wrap">' +
        '<div class="summary-worktype-bar" style="width:' +
        pct +
        '%"></div>' +
        "</div>" +
        '<div class="summary-worktype-count">' +
        w.count +
        "</div>" +
        "</div>";
    }
    container.html(html);
  }

  function renderStats(s) {
    $("#statCompleted").text(s.completed_7d || 0);
    $("#statUpdated").text(s.updated_7d || 0);
    $("#statCreated").text(s.created_7d || 0);
    $("#statDueSoon").text(s.due_soon_7d || 0);
    renderPieChart(
      s.status_counts || [],
      s.total_items || 0,
      s.parent_count || 0,
    );
  }

  /* ───── AJAX ───── */
  function getFilterLabel(key) {
    return summaryFilterLabels[key] || key;
  }

  function normalizeFilterKeys() {
    activeFilterKeys = activeFilterKeys.filter(function (key) {
      return summaryFilterKeys.indexOf(key) !== -1;
    });
  }

  function getEnabledFiltersPayload() {
    normalizeFilterKeys();
    var payload = {};
    for (var i = 0; i < activeFilterKeys.length; i++) {
      var key = activeFilterKeys[i];
      if (!filterValues[key]) {
        continue;
      }
      payload[key] = filterValues[key];
    }
    return payload;
  }

  function buildFilterPostData() {
    var data = {};
    var payload = getEnabledFiltersPayload();
    if (Object.keys(payload).length) {
      data.filters_json = JSON.stringify(payload);
    }

    if (
      payload.assignee &&
      payload.assignee.op !== "neq" &&
      $.isArray(payload.assignee.values) &&
      payload.assignee.values.length === 1 &&
      String(payload.assignee.values[0]) !== "0"
    ) {
      data.assignee_id = payload.assignee.values[0];
    }
    return data;
  }

  function refreshAll() {
    var postData = $.extend(
      {
        summary_action: "get_stats",
        csrf_token: csrfToken,
      },
      buildFilterPostData(),
    );

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      data: postData,
      dataType: "json",
      success: function (resp) {
        if (resp && resp.ok) {
          stats = resp.stats || {};
          activityData = resp.activity || {};
          renderStats(stats);
          renderActivityList($("#activityList"), activityData.rows || []);
        }
      },
    });
  }

  function loadActivityPage(
    page,
    perPage,
    container,
    paginationContainer,
    searchText,
  ) {
    var safePerPage = perPage || activityModalPageSize || "10";
    var safeSearch = String(
      searchText !== undefined ? searchText : activityModalSearchText || "",
    ).trim();
    var postData = $.extend(
      {
        summary_action: "get_activity",
        csrf_token: csrfToken,
        page: page,
        per_page: safePerPage,
        search: safeSearch,
      },
      buildFilterPostData(),
    );

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      data: postData,
      dataType: "json",
      success: function (resp) {
        if (resp && resp.ok && resp.data) {
          renderActivityList(container, resp.data.rows || []);
          if (paginationContainer) {
            renderPagination(
              paginationContainer,
              resp.data.page,
              resp.data.total_pages,
              function (p) {
                loadActivityPage(
                  p,
                  safePerPage,
                  container,
                  paginationContainer,
                  safeSearch,
                );
              },
            );
          }
        }
      },
    });
  }

  function renderPagination(container, currentPage, totalPages, onPageClick) {
    if (totalPages <= 1) {
      container.html("");
      return;
    }
    var html = "";
    html +=
      '<button class="pg-prev" ' +
      (currentPage <= 1 ? "disabled" : "") +
      ">&laquo; Prev</button>";
    var start = Math.max(1, currentPage - 2);
    var end = Math.min(totalPages, currentPage + 2);
    for (var i = start; i <= end; i++) {
      html +=
        '<button class="pg-num' +
        (i === currentPage ? " active" : "") +
        '" data-page="' +
        i +
        '">' +
        i +
        "</button>";
    }
    html +=
      '<button class="pg-next" ' +
      (currentPage >= totalPages ? "disabled" : "") +
      ">Next &raquo;</button>";
    html +=
      '<span class="pagination-info">Page ' +
      currentPage +
      " of " +
      totalPages +
      "</span>";

    container.html(html);
    container.find(".pg-prev").on("click", function () {
      if (currentPage > 1) onPageClick(currentPage - 1);
    });
    container.find(".pg-next").on("click", function () {
      if (currentPage < totalPages) onPageClick(currentPage + 1);
    });
    container.find(".pg-num").on("click", function () {
      onPageClick(parseInt($(this).data("page"), 10));
    });
  }

  /* ───── Filter UI ───── */
  function renderActiveFilterChips() {
    var $container = $("#summaryActiveFilters");
    $container.empty();
    normalizeFilterKeys();

    for (var i = 0; i < activeFilterKeys.length; i++) {
      var key = activeFilterKeys[i];
      $container.append(
        '<span class="summary-filter-chip" data-filter="' +
          esc(key) +
          '">' +
          esc(getFilterLabel(key)) +
          ' <span class="chip-remove">&times;</span></span>',
      );
    }

    // Sync checkboxes
    $(".summary-filter-options input[type=checkbox]").each(function () {
      $(this).prop("checked", activeFilterKeys.indexOf($(this).val()) !== -1);
    });
  }

  function getAssigneeFilterOptions() {
    var seen = {};
    var options = [];
    if (currentUserId && !seen[currentUserId]) {
      options.push({
        value: String(currentUserId),
        label: currentUserName || "Current User",
      });
      seen[currentUserId] = true;
    }

    options.push({ value: "0", label: "Unassigned" });
    for (var i = 0; i < assignees.length; i++) {
      var id = String(assignees[i].id || "");
      if (!id || seen[id]) {
        continue;
      }
      options.push({ value: id, label: String(assignees[i].name || "User") });
      seen[id] = true;
    }

    return options;
  }

  function getStatusFilterOptions() {
    var options = [];
    for (var i = 0; i < boardColumns.length; i++) {
      options.push({
        value: String(boardColumns[i].id),
        label: String(boardColumns[i].name || "Unknown"),
      });
    }
    return options;
  }

  function getWorkTypeFilterOptions() {
    var options = [];
    for (var i = 0; i < workTypes.length; i++) {
      options.push({
        value: String(workTypes[i].id),
        label: String(workTypes[i].name || "Task"),
        icon_svg: String(workTypes[i].svg_icon || ""),
      });
    }
    return options;
  }

  function getPriorityFilterOptions() {
    var options = [];
    for (var i = 0; i < summaryPriorityOptions.length; i++) {
      var priority = summaryPriorityOptions[i];
      options.push({
        value: priority,
        label: priority,
        icon_html: priorityIconGlyphHtml(priority),
      });
    }
    return options;
  }

  function getParentFilterOptions() {
    var options = [{ value: "0", label: "None" }];
    for (var i = 0; i < parentOptions.length; i++) {
      var workItemKey = String(parentOptions[i].work_item_key || "").trim();
      var title = String(parentOptions[i].title || "").trim();
      var label = (workItemKey ? workItemKey + " " : "") + title;
      options.push({
        value: String(parentOptions[i].id || ""),
        label: label || "Parent " + String(parentOptions[i].id || ""),
        icon_svg: String(parentOptions[i].work_type_svg_icon || ""),
      });
    }
    return options;
  }

  function priorityIconGlyphHtml(priority) {
    var p = String(priority || "Medium");
    if (p === "Highest") {
      return '<i class="fa-solid fa-angles-up summary-option-priority-icon summary-option-priority-highest" aria-hidden="true"></i>';
    }
    if (p === "High") {
      return '<i class="fa-solid fa-angle-up summary-option-priority-icon summary-option-priority-high" aria-hidden="true"></i>';
    }
    if (p === "Low") {
      return '<i class="fa-solid fa-angle-down summary-option-priority-icon summary-option-priority-low" aria-hidden="true"></i>';
    }
    if (p === "Lowest") {
      return '<i class="fa-solid fa-angles-down summary-option-priority-icon summary-option-priority-lowest" aria-hidden="true"></i>';
    }
    return '<i class="fa-solid fa-equals summary-option-priority-icon summary-option-priority-medium" aria-hidden="true"></i>';
  }

  function buildFilterOptionLabelHtml(option) {
    var text = esc(option.label || "");
    var iconHtml = "";
    if (option.icon_html) {
      iconHtml = option.icon_html;
    } else if (option.icon_svg) {
      iconHtml =
        '<img class="summary-sub-filter-option-icon" src="' +
        escAttr(option.icon_svg) +
        '" alt="">';
    }

    if (!iconHtml) {
      return '<span class="summary-sub-filter-option-text">' + text + "</span>";
    }

    return (
      '<span class="summary-sub-filter-option-icon-wrap">' +
      iconHtml +
      "</span>" +
      '<span class="summary-sub-filter-option-text">' +
      text +
      "</span>"
    );
  }

  function isOptionSelected(selectedValues, value) {
    if (!$.isArray(selectedValues)) {
      return false;
    }
    return selectedValues.indexOf(String(value)) !== -1;
  }

  function buildOperatorSelectHtml(filterKey, selectedOp) {
    var op = selectedOp === "neq" ? "neq" : "eq";
    var label = getFilterLabel(filterKey);
    return (
      '<select class="summary-sub-filter-operator" id="subFilterOp_' +
      esc(filterKey) +
      '">' +
      '<option value="eq"' +
      (op === "eq" ? " selected" : "") +
      ">" +
      esc(label) +
      " = (equals)</option>" +
      '<option value="neq"' +
      (op === "neq" ? " selected" : "") +
      ">" +
      esc(label) +
      " != (not equals)</option>" +
      "</select>"
    );
  }

  function buildListFilterPanel(
    filterKey,
    options,
    searchPlaceholder,
    helperText,
  ) {
    var current = filterValues[filterKey] || {};
    var selectedValues = $.isArray(current.values)
      ? current.values.map(function (v) {
          return String(v);
        })
      : [];

    var listId = "summarySubFilterList_" + filterKey;
    var html =
      '<div class="summary-filter-sub-panel-inner summary-filter-sub-panel-list">';
    html += buildOperatorSelectHtml(filterKey, current.op || "eq");
    html +=
      '<div class="summary-sub-filter-search-wrap">' +
      '<i class="fa-solid fa-magnifying-glass"></i>' +
      '<input type="text" class="summary-sub-filter-search" data-target="' +
      escAttr(listId) +
      '" placeholder="' +
      escAttr(searchPlaceholder || "Search") +
      '">' +
      "</div>";

    if (helperText) {
      html +=
        '<div class="summary-sub-filter-helper">' + esc(helperText) + "</div>";
    }

    html += '<div class="summary-sub-filter-list" id="' + esc(listId) + '">';
    for (var i = 0; i < options.length; i++) {
      var optionValue = String(options[i].value || "");
      if (!optionValue && optionValue !== "0") {
        continue;
      }
      html +=
        '<label class="summary-sub-filter-option">' +
        '<input type="checkbox" class="summary-sub-filter-check" data-filter="' +
        esc(filterKey) +
        '" value="' +
        escAttr(optionValue) +
        '" data-label="' +
        escAttr(options[i].label || "") +
        '"' +
        (isOptionSelected(selectedValues, optionValue) ? " checked" : "") +
        ">" +
        buildFilterOptionLabelHtml(options[i]) +
        "</label>";
    }
    html += "</div>";
    html +=
      '<button class="summary-filter-sub-panel-btn-update" data-filter="' +
      esc(filterKey) +
      '">Update</button>';
    html += "</div>";

    return html;
  }

  function buildDateFilterSubPanel(key, currentVal) {
    var mode = (currentVal && currentVal.mode) || "within";
    var withinUnit = (currentVal && currentVal.unit) || "minutes";
    var moreUnit = (currentVal && currentVal.more_unit) || withinUnit;
    var html =
      '<div class="summary-filter-sub-panel-inner summary-filter-sub-panel-date">';
    html += '<div class="summary-sub-radio-group">';

    html +=
      '<label class="summary-sub-radio-line"><input type="radio" name="dateMode_' +
      key +
      '" value="within"' +
      (mode === "within" ? " checked" : "") +
      "> Within the last</label>";
    html +=
      '<div class="summary-date-mode-row" data-mode="within">' +
      '<input type="number" id="dateWithinVal_' +
      key +
      '" value="' +
      escAttr(currentVal && currentVal.value ? currentVal.value : "") +
      '">' +
      '<select id="dateWithinUnit_' +
      key +
      '">' +
      '<option value="minutes"' +
      (withinUnit === "minutes" ? " selected" : "") +
      ">minutes</option>" +
      '<option value="hours"' +
      (withinUnit === "hours" ? " selected" : "") +
      ">hours</option>" +
      '<option value="days"' +
      (withinUnit === "days" ? " selected" : "") +
      ">days</option>" +
      '<option value="weeks"' +
      (withinUnit === "weeks" ? " selected" : "") +
      ">weeks</option>" +
      "</select>" +
      "</div>";

    html +=
      '<label class="summary-sub-radio-line"><input type="radio" name="dateMode_' +
      key +
      '" value="more"' +
      (mode === "more" ? " checked" : "") +
      "> More than</label>";
    html +=
      '<div class="summary-date-mode-row" data-mode="more">' +
      '<input type="number" id="dateMoreVal_' +
      key +
      '" value="' +
      escAttr(currentVal && currentVal.value ? currentVal.value : "") +
      '">' +
      '<select id="dateMoreUnit_' +
      key +
      '">' +
      '<option value="minutes"' +
      (moreUnit === "minutes" ? " selected" : "") +
      ">minutes</option>" +
      '<option value="hours"' +
      (moreUnit === "hours" ? " selected" : "") +
      ">hours</option>" +
      '<option value="days"' +
      (moreUnit === "days" ? " selected" : "") +
      ">days</option>" +
      '<option value="weeks"' +
      (moreUnit === "weeks" ? " selected" : "") +
      ">weeks</option>" +
      "</select>" +
      "</div>";

    html +=
      '<label class="summary-sub-radio-line"><input type="radio" name="dateMode_' +
      key +
      '" value="between"' +
      (mode === "between" ? " checked" : "") +
      "> Between</label>";
    html +=
      '<div class="summary-date-mode-row" data-mode="between">' +
      '<input type="date" id="dateFrom_' +
      key +
      '" value="' +
      escAttr((currentVal && currentVal.from) || "") +
      '">' +
      '<span class="summary-date-and">and</span>' +
      '<input type="date" id="dateTo_' +
      key +
      '" value="' +
      escAttr((currentVal && currentVal.to) || "") +
      '">' +
      "</div>";

    html +=
      '<label class="summary-sub-radio-line"><input type="radio" name="dateMode_' +
      key +
      '" value="range"' +
      (mode === "range" ? " checked" : "") +
      "> In the range</label>";
    html +=
      '<div class="summary-date-mode-row" data-mode="range">' +
      '<input type="text" id="dateRangeFrom_' +
      key +
      '" value="' +
      escAttr((currentVal && currentVal.range_from) || "") +
      '" placeholder="-3w 4d 12h 30m">' +
      '<span class="summary-date-and">to</span>' +
      '<input type="text" id="dateRangeTo_' +
      key +
      '" value="' +
      escAttr((currentVal && currentVal.range_to) || "") +
      '" placeholder="3w 4d 12h 30m">' +
      "</div>";

    html += "</div>";
    html +=
      '<button class="summary-filter-sub-panel-btn-update" data-filter="' +
      esc(key) +
      '">Update</button>';
    html += "</div>";

    return html;
  }

  function buildDueDateFilterSubPanel(currentVal) {
    var mode = (currentVal && currentVal.mode) || "overdue";
    var unit = (currentVal && currentVal.unit) || "minutes";
    var includeOverdue = !!(currentVal && currentVal.include_overdue);
    var html =
      '<div class="summary-filter-sub-panel-inner summary-filter-sub-panel-date">';
    html += '<div class="summary-sub-radio-group">';

    html +=
      '<label class="summary-sub-radio-line"><input type="radio" name="dateMode_due_date" value="overdue"' +
      (mode === "overdue" ? " checked" : "") +
      "> Now overdue</label>";

    html +=
      '<label class="summary-sub-radio-line"><input type="radio" name="dateMode_due_date" value="more"' +
      (mode === "more" ? " checked" : "") +
      "> More than</label>";
    html +=
      '<div class="summary-date-mode-row" data-mode="more">' +
      '<input type="number" id="dueMoreVal" value="' +
      escAttr(currentVal && currentVal.value ? currentVal.value : "") +
      '">' +
      '<select id="dueMoreUnit">' +
      '<option value="minutes"' +
      (unit === "minutes" ? " selected" : "") +
      ">minutes overdue</option>" +
      '<option value="hours"' +
      (unit === "hours" ? " selected" : "") +
      ">hours overdue</option>" +
      '<option value="days"' +
      (unit === "days" ? " selected" : "") +
      ">days overdue</option>" +
      '<option value="weeks"' +
      (unit === "weeks" ? " selected" : "") +
      ">weeks overdue</option>" +
      "</select>" +
      "</div>";

    html +=
      '<label class="summary-sub-radio-line"><input type="radio" name="dateMode_due_date" value="due_next"' +
      (mode === "due_next" ? " checked" : "") +
      "> Due in next</label>";
    html +=
      '<div class="summary-date-mode-row" data-mode="due_next">' +
      '<input type="number" id="dueNextVal" value="' +
      escAttr(currentVal && currentVal.value ? currentVal.value : "") +
      '">' +
      '<select id="dueNextUnit">' +
      '<option value="minutes"' +
      (unit === "minutes" ? " selected" : "") +
      ">minutes</option>" +
      '<option value="hours"' +
      (unit === "hours" ? " selected" : "") +
      ">hours</option>" +
      '<option value="days"' +
      (unit === "days" ? " selected" : "") +
      ">days</option>" +
      '<option value="weeks"' +
      (unit === "weeks" ? " selected" : "") +
      ">weeks</option>" +
      "</select>" +
      '<select id="dueNextCondition">' +
      '<option value="not_overdue"' +
      (!includeOverdue ? " selected" : "") +
      ">and not overdue</option>" +
      '<option value="include_overdue"' +
      (includeOverdue ? " selected" : "") +
      ">including overdue</option>" +
      "</select>" +
      "</div>";

    html +=
      '<label class="summary-sub-radio-line"><input type="radio" name="dateMode_due_date" value="between"' +
      (mode === "between" ? " checked" : "") +
      "> Between</label>";
    html +=
      '<div class="summary-date-mode-row" data-mode="between">' +
      '<input type="date" id="dueFrom" value="' +
      escAttr((currentVal && currentVal.from) || "") +
      '">' +
      '<span class="summary-date-and">and</span>' +
      '<input type="date" id="dueTo" value="' +
      escAttr((currentVal && currentVal.to) || "") +
      '">' +
      "</div>";

    html +=
      '<label class="summary-sub-radio-line"><input type="radio" name="dateMode_due_date" value="range"' +
      (mode === "range" ? " checked" : "") +
      "> In the range</label>";
    html +=
      '<div class="summary-date-mode-row" data-mode="range">' +
      '<input type="text" id="dueRangeFrom" value="' +
      escAttr((currentVal && currentVal.range_from) || "") +
      '" placeholder="-3w 4d 12h 30m">' +
      '<span class="summary-date-and">to</span>' +
      '<input type="text" id="dueRangeTo" value="' +
      escAttr((currentVal && currentVal.range_to) || "") +
      '" placeholder="3w 4d 12h 30m">' +
      "</div>";

    html += "</div>";
    html +=
      '<button class="summary-filter-sub-panel-btn-update" data-filter="due_date">Update</button>';
    html += "</div>";

    return html;
  }

  function toggleSubPanelModeRows($panel, radioName) {
    if (!$panel || !$panel.length) {
      return;
    }
    var mode = $panel.find('input[name="' + radioName + '"]:checked').val();
    $panel.find(".summary-date-mode-row").hide();
    if (mode) {
      $panel
        .find('.summary-date-mode-row[data-mode="' + mode + '"]')
        .css("display", "grid");
    }
  }

  function openFilterSubPanel(filterKey) {
    var $panel = $("#summaryFilterSubPanel");
    var html = "";

    openedSubFilterKey = String(filterKey || "");

    if (filterKey === "assignee") {
      html = buildListFilterPanel(
        "assignee",
        getAssigneeFilterOptions(),
        "Search assignee",
        "",
      );
    } else if (filterKey === "created") {
      html = buildDateFilterSubPanel("created", filterValues.created || {});
    } else if (filterKey === "due_date") {
      html = buildDueDateFilterSubPanel(filterValues.due_date || {});
    } else if (filterKey === "updated") {
      html = buildDateFilterSubPanel("updated", filterValues.updated || {});
    } else if (filterKey === "status") {
      html = buildListFilterPanel(
        "status",
        getStatusFilterOptions(),
        "Search status",
        "",
      );
    } else if (filterKey === "work_type") {
      html = buildListFilterPanel(
        "work_type",
        getWorkTypeFilterOptions(),
        "Search work type",
        "",
      );
    } else if (filterKey === "priority") {
      html = buildListFilterPanel(
        "priority",
        getPriorityFilterOptions(),
        "Search priority",
        "",
      );
    } else if (filterKey === "parent") {
      html = buildListFilterPanel(
        "parent",
        getParentFilterOptions(),
        "Search parent",
        "Filter by parent items (Epics).",
      );
    } else {
      html =
        '<div class="summary-filter-sub-panel-inner"><p>No options for this filter.</p></div>';
    }

    $panel.html(html).show();
    $("#summaryFilterPanel").hide();

    if (filterKey === "created" || filterKey === "updated") {
      toggleSubPanelModeRows($panel, "dateMode_" + filterKey);
    }
    if (filterKey === "due_date") {
      toggleSubPanelModeRows($panel, "dateMode_due_date");
    }
  }

  function collectListFilterValue(filterKey) {
    var op = $("#subFilterOp_" + filterKey).val() || "eq";
    var values = [];
    var labels = [];
    $(
      '.summary-sub-filter-check[data-filter="' + filterKey + '"]:checked',
    ).each(function () {
      values.push(String($(this).val() || ""));
      labels.push(String($(this).data("label") || ""));
    });

    values = values.filter(function (value) {
      return value !== "";
    });

    if (!values.length) {
      return null;
    }

    return {
      op: op === "neq" ? "neq" : "eq",
      values: values,
      labels: labels,
    };
  }

  function collectDateFilterValue(key) {
    var mode =
      $('input[name="dateMode_' + key + '"]:checked').val() || "within";
    var result = { mode: mode };

    if (mode === "within") {
      result.value = Number($("#dateWithinVal_" + key).val() || 0);
      result.unit = String($("#dateWithinUnit_" + key).val() || "minutes");
    } else if (mode === "more") {
      result.value = Number($("#dateMoreVal_" + key).val() || 0);
      result.unit = String($("#dateMoreUnit_" + key).val() || "minutes");
    } else if (mode === "between") {
      result.from = String($("#dateFrom_" + key).val() || "").trim();
      result.to = String($("#dateTo_" + key).val() || "").trim();
    } else if (mode === "range") {
      result.range_from = String($("#dateRangeFrom_" + key).val() || "").trim();
      result.range_to = String($("#dateRangeTo_" + key).val() || "").trim();
    }

    return result;
  }

  function collectDueDateFilterValue() {
    var mode = $('input[name="dateMode_due_date"]:checked').val() || "overdue";
    var result = { mode: mode };

    if (mode === "more") {
      result.value = Number($("#dueMoreVal").val() || 0);
      result.unit = String($("#dueMoreUnit").val() || "minutes");
    } else if (mode === "due_next") {
      result.value = Number($("#dueNextVal").val() || 0);
      result.unit = String($("#dueNextUnit").val() || "minutes");
      result.include_overdue =
        String($("#dueNextCondition").val() || "not_overdue") ===
        "include_overdue";
    } else if (mode === "between") {
      result.from = String($("#dueFrom").val() || "").trim();
      result.to = String($("#dueTo").val() || "").trim();
    } else if (mode === "range") {
      result.range_from = String($("#dueRangeFrom").val() || "").trim();
      result.range_to = String($("#dueRangeTo").val() || "").trim();
    }

    return result;
  }

  /* ───── Init ───── */
  $(function () {
    loadFilters();
    renderActiveFilterChips();
    renderStats(stats);
    renderActivityList($("#activityList"), activityData.rows || []);

    // Filter button toggle
    $("#summaryFilterBtn").on("click", function (e) {
      e.stopPropagation();
      var $panel = $("#summaryFilterPanel");
      $panel.toggle();
      if ($panel.is(":visible")) {
        openedSubFilterKey = "";
        $("#summaryFilterSubPanel").hide();
      }
    });

    // Filter option checkboxes
    $(document).on(
      "change",
      ".summary-filter-options input[type=checkbox]",
      function () {
        var val = $(this).val();
        if ($(this).is(":checked")) {
          if (activeFilterKeys.indexOf(val) === -1) {
            activeFilterKeys.push(val);
          }
          openFilterSubPanel(val);
        } else {
          activeFilterKeys = activeFilterKeys.filter(function (k) {
            return k !== val;
          });
          delete filterValues[val];
          if (openedSubFilterKey === val) {
            openedSubFilterKey = "";
            $("#summaryFilterSubPanel").hide();
          }
        }
        saveFilters();
        renderActiveFilterChips();
        refreshAll();
      },
    );

    // Filter search
    $("#summaryFilterSearch").on("input", function () {
      var q = $(this).val().toLowerCase();
      $(".summary-filter-options li").each(function () {
        var text = $(this).text().toLowerCase();
        $(this).toggle(text.indexOf(q) !== -1);
      });
      var visible = $(".summary-filter-options li:visible").length;
      var total = $(".summary-filter-options li").length;
      $("#summaryFilterCount").text(visible + " of " + total);
    });

    // Click filter chip to open sub-panel
    $(document).on("click", ".summary-filter-chip", function (e) {
      if ($(e.target).hasClass("chip-remove")) return;
      var key = $(this).data("filter");
      openFilterSubPanel(key);
    });

    $(document).on("input", ".summary-sub-filter-search", function () {
      var targetId = String($(this).data("target") || "");
      if (!targetId) {
        return;
      }
      var keyword = String($(this).val() || "").toLowerCase();
      $("#" + targetId)
        .find(".summary-sub-filter-option")
        .each(function () {
          var text = $(this).text().toLowerCase();
          $(this).toggle(text.indexOf(keyword) !== -1);
        });
    });

    $(document).on("change", 'input[name^="dateMode_"]', function () {
      var radioName = String($(this).attr("name") || "");
      toggleSubPanelModeRows($("#summaryFilterSubPanel"), radioName);
    });

    // Remove filter chip
    $(document).on("click", ".summary-filter-chip .chip-remove", function (e) {
      e.stopPropagation();
      var key = $(this).closest(".summary-filter-chip").data("filter");
      activeFilterKeys = activeFilterKeys.filter(function (k) {
        return k !== key;
      });
      delete filterValues[key];
      saveFilters();
      renderActiveFilterChips();
      $("#summaryFilterSubPanel").hide();
      refreshAll();
    });

    // Sub-panel Update button
    $(document).on(
      "click",
      ".summary-filter-sub-panel-btn-update",
      function () {
        var key = $(this).data("filter");

        if (key === "assignee") {
          filterValues.assignee = collectListFilterValue("assignee");
          if (!filterValues.assignee) delete filterValues.assignee;
        } else if (
          key === "status" ||
          key === "work_type" ||
          key === "priority" ||
          key === "parent"
        ) {
          filterValues[key] = collectListFilterValue(key);
          if (!filterValues[key]) delete filterValues[key];
        } else if (key === "created" || key === "updated") {
          filterValues[key] = collectDateFilterValue(key);
        } else if (key === "due_date") {
          filterValues.due_date = collectDueDateFilterValue();
        }

        saveFilters();
        renderActiveFilterChips();
        openedSubFilterKey = "";
        $("#summaryFilterSubPanel").hide();
        refreshAll();
      },
    );

    // Close panels on outside click
    $(document).on("click", function (e) {
      if (
        !$(e.target).closest(
          "#summaryFilterPanel, #summaryFilterBtn, .summary-filter-chip",
        ).length
      ) {
        $("#summaryFilterPanel").hide();
      }
      if (
        !$(e.target).closest("#summaryFilterSubPanel, .summary-filter-chip")
          .length
      ) {
        openedSubFilterKey = "";
        $("#summaryFilterSubPanel").hide();
      }
    });

    // Expand activity modal
    $("#activityExpandBtn").on("click", function () {
      var modal = new bootstrap.Modal(
        document.getElementById("activityExpandModal"),
      );
      modal.show();
      var $pageSize = $("#activityModalPageSize");
      var $search = $("#activityModalSearch");
      if ($pageSize.length) {
        $pageSize.val(activityModalPageSize || "10");
      }
      if ($search.length) {
        $search.val(activityModalSearchText || "");
      }
      loadActivityPage(
        1,
        activityModalPageSize,
        $("#activityModalList"),
        $("#activityModalPagination"),
        activityModalSearchText,
      );
    });

    $(document).on("change", "#activityModalPageSize", function () {
      activityModalPageSize = String($(this).val() || "10");
      loadActivityPage(
        1,
        activityModalPageSize,
        $("#activityModalList"),
        $("#activityModalPagination"),
        activityModalSearchText,
      );
    });

    $(document).on("input", "#activityModalSearch", function () {
      activityModalSearchText = String($(this).val() || "").trim();
      if (activityModalSearchTimer) {
        clearTimeout(activityModalSearchTimer);
      }
      activityModalSearchTimer = setTimeout(function () {
        loadActivityPage(
          1,
          activityModalPageSize,
          $("#activityModalList"),
          $("#activityModalPagination"),
          activityModalSearchText,
        );
      }, 250);
    });

    $(document).on("click", ".summary-activity-item-link", function (e) {
      var $link = $(this);
      var itemId = Number($link.data("itemId") || 0);
      if (!itemId) {
        return;
      }

      var item = {
        id: itemId,
        column_id: 0,
        work_type_id: 0,
        work_type_name: String($link.data("workTypeName") || "Task"),
        work_type_svg_icon: "",
        work_item_key: String($link.data("workItemKey") || ""),
        description: String($link.data("description") || ""),
        priority: "Medium",
        assignee_user_id: 0,
        assignee_name: "Unassigned",
        reporter_user_id: 0,
        reporter_name: "",
        parent_item_id: 0,
        title: String($link.data("itemTitle") || ""),
      };

      if (openSummaryItemDetailModal(item)) {
        e.preventDefault();
      }
    });

  function getColumnNameByIdLocal(id) {
    var meta = getColumnMetaByIdLocal(id);
    return meta ? meta.name : "Unknown";
  }

  var hoverCardTimeout = null;
  var hoverCardHideTimeout = null;
  var $hoverCard = $('<div class="task-summary-hover-card shadow-sm border rounded bg-white p-3" style="position: absolute; z-index: 1060; display: none; width: 340px; box-shadow: 0 4px 16px rgba(0,0,0,0.15) !important;"></div>');
  $("body").append($hoverCard);

  $(document).on("mouseenter", ".summary-activity-item-link", function() {
    var $link = $(this);
    clearTimeout(hoverCardHideTimeout);
    
    hoverCardTimeout = setTimeout(function() {
      var offset = $link.offset();
      var itemId = $link.data("item-id");
      var title = $link.data("item-title");
      var key = $link.data("work-item-key");
      var workTypeIconData = $link.data("work-type-icon");
      var workTypeIcon = '<i class="fa-solid fa-check-square text-primary"></i>';
      if (workTypeIconData) {
          var normalizedWorkTypeIcon = normalizeWorkTypeIcon(workTypeIconData, $link.data("work-type-name"));
          var safeWorkTypeIconHtml = workTypeIconHtml(normalizedWorkTypeIcon);
          if (safeWorkTypeIconHtml) {
              workTypeIcon = safeWorkTypeIconHtml;
          }
      }
      var assigneeName = $link.data("assignee-name");
      var priority = $link.data("priority");
      var statusId = $link.data("status-id");
      var currentStatusMeta = getColumnMetaByIdLocal(statusId) || {};
      var currentStatusColor = normalizeHexColorValueLocal(
        currentStatusMeta.color || "",
        "#DFE1E6",
      );
      var currentStatusTextColor = getReadableTextColorLocal(currentStatusColor);
      
      var priorityIcon = PRIORITY_ICONS[priority] || '<i class="fa-solid fa-equals"></i>';
      
      var statusDropdownHtml = 
        '<div class="dropdown d-inline-block summary-hover-status-dropdown">' +
          '<button class="btn btn-outline-secondary btn-sm dropdown-toggle summary-hover-status-toggle" type="button" data-bs-toggle="dropdown" style="--summary-status-bg:' + escAttr(currentStatusColor) + ';--summary-status-text:' + escAttr(currentStatusTextColor) + ';">' +
             '<span class="summary-hover-status-text">' + esc(getColumnNameByIdLocal(statusId)) + '</span>' +
             '<i class="fa-solid fa-chevron-down summary-hover-status-caret" aria-hidden="true"></i>' +
          '</button>' +
          '<ul class="dropdown-menu shadow-sm summary-hover-status-menu">';
          
      for (var i = 0; i < boardColumns.length; i++) {
        var c = boardColumns[i];
        var optionColor = normalizeHexColorValueLocal(c.color || "", "#DFE1E6");
        var optionTextColor = getReadableTextColorLocal(optionColor);
        statusDropdownHtml += '<li><a class="dropdown-item task-hover-status-option summary-hover-status-option" href="#" data-item-id="' + itemId + '" data-status-id="' + c.id + '" style="--summary-status-option-bg:' + escAttr(optionColor) + ';--summary-status-option-text:' + escAttr(optionTextColor) + ';"><span class="summary-hover-status-option-pill">' + esc(c.name) + '</span></a></li>';
      }
      statusDropdownHtml += '</ul></div>';
      
      var html = '<div class="d-flex align-items-center gap-2 mb-2">' +
                 '<div style="width:16px;height:16px;display:flex;align-items:center;">' + workTypeIcon + '</div>' +
                 '<div style="flex:1;min-width:0;line-height:1.2;">' +
                 '<a href="' + boardUrl + '?open_item=' + itemId + '" class="summary-hover-card-title-link" data-item-id="' + itemId + '" style="font-size:14px;font-weight:600;color:#0c66e4;text-decoration:none;">' + esc(key) + ': ' + esc(title) + '</a>' +
                 '</div>' +
                 '</div>' +
                 '<div class="d-flex align-items-center gap-2 mt-3">' +
                 '<div class="summary-activity-avatar" style="width:24px;height:24px;font-size:10px;border-radius:50%;background:#0c66e4;color:#fff;display:flex;align-items:center;justify-content:center;" title="' + escAttr(assigneeName) + '">' + esc(getInitials(assigneeName)) + '</div>' +
                 statusDropdownHtml +
                 '<div class="d-flex align-items-center gap-1" style="font-size:12px;color:#44546f;margin-left:auto;" title="' + escAttr(priority) + '">' + priorityIcon + ' ' + esc(priority) + '</div>' +
                 '</div>';
                 
      $hoverCard.html(html);
      
      var topPos = offset.top + $link.outerHeight() + 5;
      var leftPos = offset.left;
      var viewportWidth = $(window).width();
      var minLeft = 8;
      var hoverCardWidth = 340;
      
      if (leftPos + hoverCardWidth + minLeft > viewportWidth) {
         leftPos = viewportWidth - hoverCardWidth - minLeft;
      }
      
      leftPos = Math.max(minLeft, leftPos);
      
      $hoverCard.css({
        top: topPos,
        left: leftPos,
        maxWidth: "calc(100vw - 16px)",
        display: "block"
      });
    }, 400); 
  });

  $(document).on("mouseleave", ".summary-activity-item-link", function() {
    clearTimeout(hoverCardTimeout);
    hoverCardHideTimeout = setTimeout(function() {
      $hoverCard.hide();
    }, 300);
  });

  $hoverCard.on("mouseenter", function() {
    clearTimeout(hoverCardHideTimeout);
  });

  $hoverCard.on("mouseleave", function() {
    hoverCardHideTimeout = setTimeout(function() {
      $hoverCard.hide();
    }, 300);
  });
  
  $hoverCard.on("click", ".task-hover-status-option", function(e) {
    e.preventDefault();
    var $option = $(this);
    var itemId = $option.data("item-id");
    var newStatus = $option.data("status-id");
    
    var $btn = $option.closest(".dropdown").find(".dropdown-toggle");
    var previousStatusLabel = String(
      $btn.find(".summary-hover-status-text").text() || getColumnNameByIdLocal(newStatus),
    );
    $btn.prop("disabled", true).find(".summary-hover-status-text").text("Saving...");
    
    var postData = {
      task_action: "change_item_status",
      item_id: itemId,
      target_column_id: newStatus,
      csrf_token: csrfToken
    };
    
    $.ajax({
      url: boardUrl,
      method: "POST",
      data: postData,
      dataType: "json",
      success: function (resp) {
        if (resp && resp.ok) {
          $hoverCard.hide();
          refreshAll(); 
        } else {
          $btn.prop("disabled", false).find(".summary-hover-status-text").text(previousStatusLabel);
          showNotification(
            (resp && (resp.message || resp.error)) || "Failed to update status",
            "error",
          );
        }
      },
      error: function() {
        $btn.prop("disabled", false).find(".summary-hover-status-text").text(previousStatusLabel);
        showNotification("Server error", "error");
      }
    });
  });
  
  $(document).on("click", ".summary-hover-card-title-link", function (e) {
      e.preventDefault();
      var itemId = $(this).data("item-id");
      var $originalLink = $('.summary-activity-item-link[data-item-id="' + itemId + '"]').first();
      if ($originalLink.length) {
          $originalLink.trigger("click");
      }
      $hoverCard.hide();
  });

  });
})(jQuery);
