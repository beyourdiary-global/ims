(function () {
  "use strict";

  function esc(value) {
    return String(value === null || value === undefined ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function buildCardMock($row) {
    var title = $row.find("td").eq(2).text().trim();
    var cardHtml =
      '<article class="task-item-card" data-item-id="' +
      esc($row.attr("data-item-id") || "0") +
      '" data-status-column-id="' +
      esc($row.attr("data-status-column-id") || "0") +
      '" data-work-type-id="' +
      esc($row.attr("data-work-type-id") || "0") +
      '" data-work-type-name="' +
      esc($row.attr("data-work-type-name") || "Task") +
      '" data-work-type-icon="' +
      esc($row.attr("data-work-type-icon") || "") +
      '" data-work-item-key="' +
      esc($row.attr("data-work-item-key") || "") +
      '" data-item-description="' +
      esc($row.attr("data-item-description") || "") +
      '" data-priority="' +
      esc($row.attr("data-priority") || "Medium") +
      '" data-assignee-user-id="' +
      esc($row.attr("data-assignee-user-id") || "0") +
      '" data-assignee-name="' +
      esc($row.attr("data-assignee-name") || "Unassigned") +
      '" data-reporter-user-id="' +
      esc($row.attr("data-reporter-user-id") || "0") +
      '" data-reporter-name="' +
      esc($row.attr("data-reporter-name") || "") +
      '" data-parent-item-id="' +
      esc($row.attr("data-parent-item-id") || "0") +
      '">' +
      '<div class="task-item-title">' +
      esc(title) +
      "</div></article>";

    return $(cardHtml);
  }

  $(document).on("click", ".view-my-task-item-row .sheets-cell-key", function (e) {
    e.preventDefault();
    e.stopPropagation();
    if (typeof window.openItemDetailModal !== "function") {
      if (typeof window.notify === "function") {
        window.notify("Unable to open item: openItemDetailModal is not available on this page.");
      }
      return;
    }
    var $row = $(this).closest("tr.view-my-task-item-row");
    if (!$row.length) {
      if (typeof window.notify === "function") {
        window.notify("Unable to open item: could not find the row for this key.");
      }
      return;
    }
    try {
      window.openItemDetailModal(buildCardMock($row));
    } catch (error) {
      var errMessage = error && error.message ? error.message : String(error);
      if (typeof window.notify === "function") {
        window.notify("Unable to open item: " + errMessage);
      }
      if (window.console && typeof window.console.error === "function") {
        window.console.error("[view_my_task] openItemDetailModal failed:", error);
      }
    }
  });

  $(document).on("click", "[data-group-toggle]", function () {
    var $row = $(this);
    var collapsing = !$row.hasClass("is-collapsed");
    $row.toggleClass("is-collapsed", collapsing);

    var stopSelector = $row.hasClass("view-my-task-status-row")
      ? "tr.view-my-task-status-row"
      : "tr.view-my-task-status-row, tr.view-my-task-date-row";
    $row.nextUntil(stopSelector).toggleClass("is-hidden", collapsing);
  });

  $(document).on("click", "#viewMyTaskRefreshBtn", function () {
    var $btn = $(this);
    if ($btn.hasClass("is-spinning")) {
      return;
    }
    $btn.addClass("is-spinning");
    window.location.reload();
  });

  var FILTER_COLUMN_MAP = {
    type: "data-work-type-name",
    priority: "data-priority",
    assignee: "data-assignee-name",
  };

  var activeViewMyTaskFilters = {};

  function closeAllViewMyTaskFilterPopups() {
    $(".view-my-task-filter-popup").remove();
  }

  function getViewMyTaskUniqueValues(colKey) {
    var attr = FILTER_COLUMN_MAP[colKey];
    if (!attr) {
      return [];
    }
    var seen = {};
    $(".view-my-task-item-row").each(function () {
      var value = String($(this).attr(attr) || "").trim();
      if (value !== "") {
        seen[value] = true;
      }
    });
    return Object.keys(seen).sort();
  }

  function updateViewMyTaskGroupVisibility() {
    $(".view-my-task-date-row").each(function () {
      var $dateRow = $(this);
      var $items = $dateRow
        .nextUntil("tr.view-my-task-status-row, tr.view-my-task-date-row")
        .filter(".view-my-task-item-row");
      var visibleCount = $items.filter(function () {
        return !$(this).hasClass("is-filtered-out");
      }).length;
      $dateRow.find(".view-my-task-date-count").text(visibleCount);
      $dateRow.toggleClass("is-filtered-empty", visibleCount === 0);
    });

    $(".view-my-task-status-row").each(function () {
      var $statusRow = $(this);
      var $items = $statusRow
        .nextUntil("tr.view-my-task-status-row")
        .filter(".view-my-task-item-row");
      var visibleCount = $items.filter(function () {
        return !$(this).hasClass("is-filtered-out");
      }).length;
      $statusRow.find(".view-my-task-status-count").text(visibleCount);
      $statusRow.toggleClass("is-filtered-empty", visibleCount === 0);
    });

    $(".view-my-task-date-row, .view-my-task-status-row").toggleClass(
      "is-hidden-by-filter",
      false,
    );
    $(".view-my-task-date-row.is-filtered-empty, .view-my-task-status-row.is-filtered-empty").each(
      function () {
        $(this).addClass("is-hidden-by-filter");
      },
    );
  }

  function applyViewMyTaskFilters() {
    var colKeys = Object.keys(activeViewMyTaskFilters);

    $(".view-my-task-item-row").each(function () {
      var $row = $(this);
      var matches = colKeys.every(function (colKey) {
        var attr = FILTER_COLUMN_MAP[colKey];
        var values = activeViewMyTaskFilters[colKey];
        var rowValue = String($row.attr(attr) || "").trim();
        return values.indexOf(rowValue) !== -1;
      });
      $row.toggleClass("is-filtered-out", !matches);
    });

    updateViewMyTaskGroupVisibility();

    $("th[data-filter-col]").each(function () {
      var colKey = $(this).attr("data-filter-col");
      var isActive = !!activeViewMyTaskFilters[colKey];
      $(this).toggleClass("has-active-filter", isActive);
      $(this)
        .find(".btn-filter")
        .toggleClass("filter-active", isActive)
        .attr("title", isActive ? "Filter (active)" : "Filter");
    });

    var activeCount = colKeys.length;
    var $resetBtn = $("#viewMyTaskResetFilterBtn");
    if (activeCount > 0) {
      $resetBtn
        .show()
        .attr("title", "Clear " + activeCount + " active column filter" + (activeCount > 1 ? "s" : ""))
        .html(
          '<i class="fa-solid fa-filter-circle-xmark"></i> Reset Filter (' +
            activeCount +
            ")",
        );
    } else {
      $resetBtn.hide();
    }
  }

  function buildViewMyTaskFilterPopup(colKey) {
    var $popup = $('<div class="sheets-filter-popup view-my-task-filter-popup"></div>');
    var existing = activeViewMyTaskFilters[colKey] || null;

    $popup.append('<div class="filter-title">Option filter</div>');
    $popup.append(
      '<input type="text" class="filter-search" placeholder="Find...">',
    );

    var $opts = $('<div class="filter-options"></div>');
    getViewMyTaskUniqueValues(colKey).forEach(function (opt) {
      var checked = existing && existing.indexOf(opt) !== -1 ? " checked" : "";
      $opts.append(
        '<label class="filter-option"><input type="checkbox" value="' +
          esc(opt) +
          '"' +
          checked +
          "> " +
          esc(opt) +
          "</label>",
      );
    });
    $popup.append($opts);
    $popup.append(
      '<div class="filter-actions"><span><a href="#" class="filter-clear">Clear</a> <a href="#" class="filter-select-all">Select all</a></span><button class="btn-apply" data-filter-col="' +
        colKey +
        '">Apply</button></div>',
    );

    return $popup;
  }

  $(document).on("click", ".btn-filter", function (e) {
    e.stopPropagation();
    closeAllViewMyTaskFilterPopups();
    var colKey = $(this).attr("data-filter-col");
    if (!colKey) {
      return;
    }
    var $th = $(this).closest("th");
    var $popup = buildViewMyTaskFilterPopup(colKey);
    $th.append($popup);
    $popup.addClass("show");
  });

  $(document).on("click", function (e) {
    if (
      !$(e.target).closest(".view-my-task-filter-popup, .btn-filter").length
    ) {
      closeAllViewMyTaskFilterPopups();
    }
  });

  $(document).on("input", ".view-my-task-filter-popup .filter-search", function () {
    var q = String($(this).val() || "").toLowerCase();
    $(this)
      .closest(".view-my-task-filter-popup")
      .find(".filter-option")
      .each(function () {
        $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
      });
  });

  $(document).on("click", ".view-my-task-filter-popup .filter-select-all", function (e) {
    e.preventDefault();
    $(this)
      .closest(".view-my-task-filter-popup")
      .find(".filter-option input[type='checkbox']")
      .prop("checked", true);
  });

  $(document).on("click", ".view-my-task-filter-popup .filter-clear", function (e) {
    e.preventDefault();
    var colKey = $(this)
      .closest(".view-my-task-filter-popup")
      .find(".btn-apply")
      .attr("data-filter-col");
    delete activeViewMyTaskFilters[colKey];
    applyViewMyTaskFilters();
    closeAllViewMyTaskFilterPopups();
  });

  $(document).on("click", ".view-my-task-filter-popup .btn-apply", function (e) {
    e.preventDefault();
    var colKey = $(this).attr("data-filter-col");
    var $popup = $(this).closest(".view-my-task-filter-popup");
    var values = $popup
      .find(".filter-option input[type='checkbox']:checked")
      .map(function () {
        return $(this).val();
      })
      .get();

    if (values.length > 0) {
      activeViewMyTaskFilters[colKey] = values;
    } else {
      delete activeViewMyTaskFilters[colKey];
    }
    applyViewMyTaskFilters();
    closeAllViewMyTaskFilterPopups();
  });

  $(document).on("click", "#viewMyTaskResetFilterBtn", function () {
    activeViewMyTaskFilters = {};
    applyViewMyTaskFilters();
    closeAllViewMyTaskFilterPopups();
  });
})();
