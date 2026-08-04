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
      return;
    }
    var $row = $(this).closest("tr.view-my-task-item-row");
    if (!$row.length) {
      return;
    }
    window.openItemDetailModal(buildCardMock($row));
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
})();
