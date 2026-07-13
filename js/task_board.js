var taskItemActionModalState = {
  itemId: 0,
};

$app.on("click", ".task-open-composer-btn", function () {
  var $column = $(this).closest(".task-column");
  openComposerForColumn($column);
});

function findCardByItemId(itemId) {
  var id = Number(itemId || 0);
  if (!id) {
    return $();
  }
  return $app.find('.task-item-card[data-item-id="' + id + '"]').first();
}

function findCardByWorkItemKey(workItemKey) {
  var key = String(workItemKey || "").trim().toLowerCase();
  if (!key) {
    return $();
  }

  var $match = $();
  $app.find(".task-item-card").each(function () {
    if (
      String($(this).attr("data-work-item-key") || "").trim().toLowerCase() ===
      key
    ) {
      $match = $(this);
      return false;
    }
  });

  return $match;
}

function isTaskItemHashUrlEnabled() {
  return !!(
    window.taskBoardConfig &&
    window.taskBoardConfig.enableTaskItemHashUrl === true
  );
}

function getSelectedIssueFromUrl() {
  if (!isTaskItemHashUrlEnabled()) {
    return "";
  }

  try {
    return String(
      new URL(window.location.href).searchParams.get("selectedIssue") || "",
    ).trim();
  } catch (error) {
    return "";
  }
}

function replaceTaskItemUrl(itemId) {
  var id = Number(itemId || 0);
  if (
    !isTaskItemHashUrlEnabled() ||
    id <= 0 ||
    !window.history ||
    typeof window.history.replaceState !== "function"
  ) {
    return;
  }

  try {
    var url = new URL(window.location.href);
    url.searchParams.delete("selectedIssue");
    url.hash = "task-item-" + String(id);
    window.history.replaceState(
      window.history.state,
      "",
      url.pathname + url.search + url.hash,
    );
  } catch (error) {
    // Keep modal behavior working if URL state is unavailable in an older browser.
  }
}

function clearTaskItemUrl() {
  if (
    !isTaskItemHashUrlEnabled() ||
    !window.history ||
    typeof window.history.replaceState !== "function"
  ) {
    return;
  }

  try {
    var url = new URL(window.location.href);
    var taskItemHash = /^#task-item-\d+(?:-(?:comment|reply)-\d+)?$/i.test(
      url.hash,
    );
    if (!url.searchParams.has("selectedIssue") && !taskItemHash) {
      return;
    }
    url.searchParams.delete("selectedIssue");
    if (taskItemHash) {
      url.hash = "";
    }
    window.history.replaceState(
      window.history.state,
      "",
      url.pathname + url.search + url.hash,
    );
  } catch (error) {
    // Keep modal behavior working if URL state is unavailable in an older browser.
  }
}

function openWorkItemFromSelectedIssue() {
  var selectedIssue = getSelectedIssueFromUrl();
  if (!selectedIssue) {
    return;
  }

  var $card = findCardByWorkItemKey(selectedIssue);
  if (!$card.length) {
    return;
  }

  openItemDetailModal($card);
}

function getTaskItemActionModalInstance() {
  var modalEl = document.getElementById("taskItemActionModal");
  if (!modalEl || typeof bootstrap === "undefined" || !bootstrap.Modal) {
    return null;
  }
  return bootstrap.Modal.getOrCreateInstance(modalEl);
}

function closeTaskItemActionModal() {
  var modal = getTaskItemActionModalInstance();
  if (modal) {
    modal.hide();
  }
}

function resolveTaskActionCard($context) {
  var $source = $context && $context.length ? $context : $();
  var $card = $source.closest(".task-item-card");
  if ($card.length) {
    return $card;
  }

  var itemId = Number(
    $source.closest("#taskItemActionModal").attr("data-item-id") ||
      taskItemActionModalState.itemId ||
      0,
  );
  if (!itemId) {
    return $();
  }

  return findCardByItemId(itemId);
}

function buildTaskActionModalButton(label, attrs, danger) {
  var options = attrs && typeof attrs === "object" ? attrs : {};
  var htmlAttrs = "";
  for (var key in options) {
    if (!Object.prototype.hasOwnProperty.call(options, key)) {
      continue;
    }
    htmlAttrs +=
      " " +
      key +
      '="' +
      escHtml(String(options[key] == null ? "" : options[key])) +
      '"';
  }

  return (
    '<button type="button" class="btn task-item-action-modal-btn task-item-action' +
    (danger ? " task-item-action-modal-btn-danger" : "") +
    '"' +
    htmlAttrs +
    ">" +
    escHtml(label) +
    "</button>"
  );
}

function buildTaskActionModalSection(title, bodyHtml, expanded, extraClass) {
  var sectionClass = "task-item-action-modal-section";
  if (extraClass) {
    sectionClass += " " + extraClass;
  }

  return (
    '<section class="' +
    sectionClass +
    (expanded ? " is-open" : "") +
    '">' +
    '<button type="button" class="btn task-item-action-modal-section-toggle">' +
    '<span class="task-item-action-modal-section-label">' +
    escHtml(title) +
    '</span><i class="fa-solid fa-chevron-down task-item-action-modal-section-arrow"></i></button>' +
    '<div class="task-item-action-modal-section-body">' +
    bodyHtml +
    "</div></section>"
  );
}

function renderTaskItemActionModal($card) {
  if (!$card || !$card.length) {
    return;
  }

  var itemId = Number($card.data("itemId") || 0);
  if (!itemId) {
    return;
  }

  taskItemActionModalState.itemId = itemId;

  var $modal = $("#taskItemActionModal");
  var groupedByStatus = isBoardGroupedByStatus();
  var $column = $card.closest(".task-column");
  var $cards = groupedByStatus ? $column.find(".task-item-card") : $();
  var total = groupedByStatus ? $cards.length : 0;
  var index = groupedByStatus ? $cards.index($card) : -1;
  var currentColumnId = getCardStatusColumnId($card);
  var currentKey = String($card.attr("data-work-item-key") || "").trim();
  var currentTitle = String($card.find(".task-item-title").text() || "").trim();
  var isEpicCard =
    String($card.attr("data-work-type-name") || "")
      .trim()
      .toLowerCase() === "epic";
  var hasLabels = getItemLabelsFromCard($card).length > 0;
  var hasStatusLabels = getItemStatusLabelIdsFromCard($card).length > 0;
  var parentItemId = Number($card.attr("data-parent-item-id") || 0);

  var moveHtml = "";
  if (total >= 2) {
    if (index > 0) {
      moveHtml += buildTaskActionModalButton("To the top", {
        "data-action": "move_top",
      });
      moveHtml += buildTaskActionModalButton("Up", {
        "data-action": "move_up",
      });
    }
    if (index < total - 1) {
      moveHtml += buildTaskActionModalButton("Down", {
        "data-action": "move_down",
      });
      moveHtml += buildTaskActionModalButton("To the bottom", {
        "data-action": "move_bottom",
      });
    }
  }

  var statusHtml = "";
  var statusColumns = getBoardStatusColumns();
  for (var s = 0; s < statusColumns.length; s++) {
    var statusColumn = statusColumns[s] || {};
    var statusColumnId = Number(statusColumn.id || 0);
    if (!statusColumnId || statusColumnId === currentColumnId) {
      continue;
    }
    if (!canTargetStatusColumn(statusColumnId)) {
      continue;
    }

    var statusName = String(statusColumn.name || "").trim();
    if (!statusName) {
      continue;
    }

    statusHtml += buildTaskActionModalButton(statusName, {
      "data-action": "change_status",
      "data-target-column-id": statusColumnId,
    });
  }

  var sectionsHtml = "";

  if (canEdit) {
    sectionsHtml +=
      buildTaskActionModalSection(
        "Move work item",
        '<div class="task-item-action-modal-button-list">' +
          (moveHtml ||
            '<div class="task-item-action-modal-empty">No move options</div>') +
          "</div>",
        false,
      ) +
      buildTaskActionModalSection(
        "Change status",
        '<div class="task-item-action-modal-button-list">' +
          (statusHtml ||
            '<div class="task-item-action-modal-empty">No other status</div>') +
          "</div>",
        false,
      );
  }

  if (canEdit && !isEpicCard && hasAnyProjectFieldPermission("parent")) {
    sectionsHtml += buildTaskActionModalSection(
      parentItemId > 0 ? "Change parent" : "Change parent",
      '<div class="task-item-parent-submenu task-item-action-modal-panel"><div class="task-item-parent-submenu-content"></div></div>',
      false,
    );
  }

  if (canEdit && hasAnyProjectFieldPermission("labels")) {
    sectionsHtml += buildTaskActionModalSection(
      hasLabels ? "Edit labels" : "Add labels",
      '<div class="task-item-label-submenu task-item-action-modal-panel"><div class="task-item-label-submenu-content"></div></div>',
      false,
    );
  }

  if (canEdit && !isEpicCard && hasAnyProjectFieldPermission("task_status")) {
    sectionsHtml += buildTaskActionModalSection(
      hasStatusLabels ? "Edit task status labels" : "Add task status labels",
      '<div class="task-item-status-label-submenu task-item-action-modal-panel"><div class="task-item-status-label-submenu-content"></div></div>',
      false,
    );
  }

  if (canDelete) {
    sectionsHtml +=
      '<section class="task-item-action-modal-section task-item-action-modal-section-delete">' +
      buildTaskActionModalButton("Delete", { "data-action": "delete" }, true) +
      "</section>";
  }

  $modal.attr("data-item-id", itemId);
  $("#taskItemActionModalTitle").text(currentTitle || "Task options");
  $("#taskItemActionModalMeta")
    .toggleClass("d-none", !currentKey)
    .text(currentKey || "");
  $("#taskItemActionModalSections").html(sectionsHtml);

  if (canEdit && hasAnyProjectFieldPermission("labels")) {
    renderInlineLabelPanel(
      $card,
      $("#taskItemActionModalSections .task-item-label-submenu").first(),
    );
  }

  if (canEdit && !isEpicCard) {
    if (hasAnyProjectFieldPermission("parent")) {
      renderParentSubmenu(
        $card,
        $("#taskItemActionModalSections .task-item-parent-submenu").first(),
      );
    }
    if (hasAnyProjectFieldPermission("task_status")) {
      renderInlineStatusLabelPanel(
        $card,
        $("#taskItemActionModalSections .task-item-status-label-submenu").first(),
      );
    }
  }
}

function openTaskItemActionModal($card) {
  var modal = getTaskItemActionModalInstance();
  if (!modal || !$card || !$card.length) {
    return;
  }

  renderTaskItemActionModal($card);
  modal.show();
}

$(document).on("click", ".task-open-item-actions-btn", function (e) {
  e.preventDefault();
  e.stopPropagation();
  openTaskItemActionModal($(this).closest(".task-item-card"));
});

$(document).on("hidden.bs.modal", "#taskItemActionModal", function () {
  taskItemActionModalState.itemId = 0;
  $(this).removeAttr("data-item-id");
  $("#taskItemActionModalTitle").text("Task options");
  $("#taskItemActionModalMeta").addClass("d-none").text("");
  $("#taskItemActionModalSections").empty();
});

$(document).on("click", ".task-item-action-modal-section-toggle", function (e) {
  e.preventDefault();
  var $section = $(this).closest(".task-item-action-modal-section");
  var willOpen = !$section.hasClass("is-open");

  $("#taskItemActionModalSections .task-item-action-modal-section")
    .not(".task-item-action-modal-section-delete")
    .removeClass("is-open");

  $section.toggleClass("is-open", willOpen);
});

function applyWorkTypeToCardUi($card, workType) {
  if (!$card || !$card.length) {
    return;
  }

  var type = normalizeWorkTypeEntry(workType || {});
  $card
    .attr("data-work-type-id", Number(type.id || 0))
    .attr("data-work-type-name", type.name)
    .attr("data-work-type-icon", type.svg_icon)
    .attr("data-work-type-remark", String(type.remark || ""));

  applyBoardViewSettingsToCard($card);
  applyBoardFilters();
}

function repositionTaskItemMenu($dropdown) {
  var $wrap = $dropdown && $dropdown.length ? $dropdown : $();
  if (!$wrap.length) {
    return;
  }

  var $btn = $wrap.find(".task-item-menu-btn").first();
  var $menu = $wrap.find(".task-item-menu-list").first();
  if (!$btn.length || !$menu.length) {
    return;
  }

  var btnRect = $btn.get(0).getBoundingClientRect();
  if (!btnRect || (btnRect.width === 0 && btnRect.height === 0)) {
    return;
  }

  $menu.attr("data-bs-popper", "static");
  $menu.addClass("task-item-menu-fixed").css({
    visibility: "hidden",
    left: "0px",
    top: "0px",
    right: "auto",
    bottom: "auto",
    transform: "none",
  });

  var menuHeight = $menu.outerHeight() || 0;
  var menuWidth = $menu.outerWidth() || 230;
  var viewportHeight =
    window.innerHeight || document.documentElement.clientHeight || 0;
  var viewportWidth =
    window.innerWidth || document.documentElement.clientWidth || 0;
  var edgePadding = 8;
  var sideGap = 6;
  var preferredLeft = Math.floor(btnRect.right + sideGap);
  var preferredLeftFallback = Math.floor(btnRect.left - menuWidth - sideGap);
  var rightSideFits = preferredLeft + menuWidth <= viewportWidth - edgePadding;
  var left = rightSideFits ? preferredLeft : preferredLeftFallback;

  left = Math.max(
    edgePadding,
    Math.min(
      left,
      Math.max(edgePadding, viewportWidth - menuWidth - edgePadding),
    ),
  );

  var fitsBelow = btnRect.top + menuHeight <= viewportHeight - edgePadding;
  var top = fitsBelow
    ? Math.floor(btnRect.top)
    : Math.floor(btnRect.bottom - menuHeight);

  top = Math.max(
    edgePadding,
    Math.min(
      top,
      Math.max(edgePadding, viewportHeight - menuHeight - edgePadding),
    ),
  );

  $wrap.toggleClass("task-item-menu-open-up", !fitsBelow);
  $menu.css({
    left: String(left) + "px",
    top: String(top) + "px",
    right: "auto",
    bottom: "auto",
    transform: "none",
    visibility: "",
  });
}

function repositionTaskComposerDropdown($dropdown) {
  var $wrap = $dropdown && $dropdown.length ? $dropdown : $();
  if (!$wrap.length || !$wrap.hasClass("show")) {
    return;
  }

  var $toggle = $wrap.children('[data-bs-toggle="dropdown"]').first();
  var $menu = $wrap.children(".dropdown-menu").first();
  if (!$toggle.length || !$menu.length) {
    return;
  }

  var toggleRect = $toggle.get(0).getBoundingClientRect();
  if (!toggleRect || (toggleRect.width === 0 && toggleRect.height === 0)) {
    return;
  }

  $menu
    .attr("data-bs-popper", "static")
    .addClass("task-composer-dropdown-fixed")
    .css({
      visibility: "hidden",
      left: "0px",
      top: "0px",
      right: "auto",
      bottom: "auto",
      transform: "none",
    });

  var menuWidth = $menu.outerWidth() || toggleRect.width || 180;
  var menuHeight = $menu.outerHeight() || 0;
  var viewportWidth =
    window.innerWidth || document.documentElement.clientWidth || 0;
  var viewportHeight =
    window.innerHeight || document.documentElement.clientHeight || 0;
  var edgePadding = 12;
  var verticalGap = 6;
  var preferredLeft = Math.floor(toggleRect.left);
  var preferredTop = Math.floor(toggleRect.bottom + verticalGap);
  var shouldOpenUp =
    preferredTop + menuHeight > viewportHeight - edgePadding &&
    toggleRect.top - menuHeight - verticalGap >= edgePadding;
  var left = Math.max(
    edgePadding,
    Math.min(
      preferredLeft,
      Math.max(edgePadding, viewportWidth - menuWidth - edgePadding),
    ),
  );
  var top = shouldOpenUp
    ? Math.floor(toggleRect.top - menuHeight - verticalGap)
    : preferredTop;

  top = Math.max(
    edgePadding,
    Math.min(top, Math.max(edgePadding, viewportHeight - menuHeight - edgePadding)),
  );

  $wrap.toggleClass("task-composer-dropdown-open-up", shouldOpenUp);
  $menu.css({
    left: String(left) + "px",
    top: String(top) + "px",
    right: "auto",
    bottom: "auto",
    transform: "none",
    visibility: "",
  });
}

function repositionTaskItemSubmenu($submenuWrap) {
  var $wrap = $submenuWrap && $submenuWrap.length ? $submenuWrap : $();
  if (!$wrap.length || !$wrap.hasClass("show")) {
    return;
  }

  var $toggle = $wrap.children(".task-item-submenu-toggle").first();
  var $submenu = $wrap.children(".task-item-submenu-list").first();
  if (!$toggle.length || !$submenu.length) {
    return;
  }

  var toggleRect = $toggle.get(0).getBoundingClientRect();
  if (!toggleRect || (toggleRect.width === 0 && toggleRect.height === 0)) {
    return;
  }

  $submenu.addClass("task-item-submenu-fixed").css({
    visibility: "hidden",
    left: "0px",
    top: "0px",
    right: "auto",
    bottom: "auto",
    transform: "none",
  });

  var submenuWidth = $submenu.outerWidth() || 260;
  var submenuHeight = $submenu.outerHeight() || 320;
  var viewportWidth =
    window.innerWidth || document.documentElement.clientWidth || 0;
  var viewportHeight =
    window.innerHeight || document.documentElement.clientHeight || 0;
  var edgePadding = 8;

  var openLeft =
    toggleRect.right + submenuWidth > viewportWidth - edgePadding &&
    toggleRect.left - submenuWidth >= edgePadding;
  var left = openLeft
    ? Math.floor(toggleRect.left - submenuWidth)
    : Math.floor(toggleRect.right);

  left = Math.max(
    edgePadding,
    Math.min(
      left,
      Math.max(edgePadding, viewportWidth - submenuWidth - edgePadding),
    ),
  );

  var fitsBelow =
    toggleRect.top + submenuHeight <= viewportHeight - edgePadding;
  var top = fitsBelow
    ? Math.floor(toggleRect.top)
    : Math.floor(toggleRect.bottom - submenuHeight);

  top = Math.max(
    edgePadding,
    Math.min(
      top,
      Math.max(edgePadding, viewportHeight - submenuHeight - edgePadding),
    ),
  );

  $wrap.toggleClass("task-item-submenu-open-left", openLeft);
  $submenu.css({
    left: String(left) + "px",
    top: String(top) + "px",
    right: "auto",
    bottom: "auto",
    transform: "none",
    visibility: "",
  });
}

function revealComposerInViewport($column) {
  if (!$column || !$column.length) {
    return;
  }

  var composerEl = $column.find(".task-composer").get(0);
  if (!composerEl) {
    return;
  }

  window.setTimeout(function () {
    var rect = composerEl.getBoundingClientRect();
    var viewportHeight =
      window.innerHeight || document.documentElement.clientHeight || 0;
    var gap = 28;

    if (rect.bottom > viewportHeight - gap) {
      window.scrollBy({
        top: rect.bottom - viewportHeight + gap,
        left: 0,
        behavior: "smooth",
      });
    }
  }, 40);
}

function openComposerForColumn($column) {
  if (!$column || !$column.length) {
    return;
  }

  var columnId = Number($column.data("columnId") || 0);
  var $composer = $column.find(".task-composer");
  if (!$composer.length) {
    return;
  }

  if (!canAdd) {
    notify("You do not have permission to create work items.");
    return;
  }
  if (!canTargetStatusColumn(columnId)) {
    return;
  }

  $composer.removeClass("d-none");
  scrollColumnItemListToBottom($column);
  revealComposerInViewport($column);
  $composer.find(".task-title-input").trigger("focus");
  enableCreateButton($composer);
}

function updateItemWorkType(itemId, workTypeId, fallbackType, onDone) {
  var resolvedItemId = Number(itemId || 0);
  var resolvedWorkTypeId = Number(workTypeId || 0);
  if (!resolvedItemId || !resolvedWorkTypeId) {
    return;
  }

  postAction(
    {
      task_action: "set_item_work_type",
      item_id: resolvedItemId,
      work_type_id: resolvedWorkTypeId,
    },
    function (res) {
      var updatedType = normalizeWorkTypeEntry(
        (res && res.work_type) || fallbackType || {},
      );
      var $card = findCardByItemId(resolvedItemId);
      applyWorkTypeToCardUi($card, updatedType);

      if (Number((res && res.parent_relation_removed) || 0) > 0) {
        updateCardParentSubmenuToggle($card, 0, "");
      }

      if (Number(itemDetailModalState.itemId || 0) === resolvedItemId) {
        itemDetailModalState.workTypeName = updatedType.name;
        itemDetailModalState.workTypeIcon = updatedType.svg_icon;
        if (Number((res && res.parent_relation_removed) || 0) > 0) {
          itemDetailModalState.parentItemId = 0;
          itemDetailModalState.parentWorkItemKey = "";
          itemDetailModalState.parentWorkTypeName = "Task";
          itemDetailModalState.parentWorkTypeIcon = "";
          renderDetailParentDropdown(
            0,
            Array.isArray(itemDetailModalState.parentOptions)
              ? itemDetailModalState.parentOptions
              : [],
          );
        }
        renderDetailKeyTrail();
        applyDetailFieldVisibility();
      }

      if (typeof onDone === "function") {
        onDone(updatedType, res || {});
      }
    },
  );
}

$(document).on("click", function (e) {
  var $target = $(e.target);
  if (
    $target.closest(
      ".task-composer, .task-open-composer-btn, .task-work-type-menu, .task-assignee-menu, .task-assignee-menu-scroll, .task-due-date-btn, .task-due-date-input, .ui-datepicker, .datepicker, .dropdown-menu",
    ).length
  ) {
    return;
  }

  $app.find(".task-composer").each(function () {
    var $composer = $(this);
    if ($composer.hasClass("d-none")) {
      return;
    }

    $composer.addClass("d-none");
    $composer.find(".task-title-input").val("");
    enableCreateButton($composer);
  });
});

$app.on("input", ".task-title-input", function () {
  var $composer = $(this).closest(".task-composer");
  enableCreateButton($composer);
});

$app.on("click", ".task-due-date-btn", function (e) {
  e.preventDefault();
  var $composer = $(this).closest(".task-composer");
  var input = $composer.find(".task-due-date-input").get(0);
  if (!input) {
    return;
  }

  if (typeof input.showPicker === "function") {
    input.showPicker();
  } else {
    input.focus();
    input.click();
  }
});

$(document).on("click", ".task-work-type-option", function (e) {
  e.preventDefault();
  var $option = $(this);
  var workTypeId = Number($option.data("workTypeId") || 0);
  var workTypeName = String($option.data("workTypeName") || "Task").trim();
  var workTypeRemark = String($option.data("workTypeRemark") || "").trim();
  var workTypeIcon = normalizeWorkTypeIcon(
    $option.data("workTypeIcon"),
    workTypeName,
  );
  var selectedType = {
    id: workTypeId,
    name: workTypeName,
    remark: workTypeRemark,
    svg_icon: workTypeIcon,
  };

  var $card = $option.closest(".task-item-card");
  if (!$card.length) {
    var menuItemId = Number(
      $option.closest(".task-item-work-type-menu").attr("data-item-id") || 0,
    );
    if (menuItemId > 0) {
      $card = findCardByItemId(menuItemId);
    }
  }

  if ($card.length) {
    if (!canEdit) {
      notify("You do not have permission to change work type.");
      return;
    }

    var itemId = Number($card.data("itemId") || 0);
    if (!itemId) {
      return;
    }

    updateItemWorkType(itemId, workTypeId, selectedType);
    return;
  }

  var $composer = $option.closest(".task-composer");
  setComposerWorkType($composer.find(".task-work-type-toggle"), {
    id: selectedType.id,
    name: selectedType.name,
    remark: selectedType.remark,
    svg_icon: selectedType.svg_icon,
  });
});

$app.on("click", ".task-assignee-option", function (e) {
  e.preventDefault();
  var $option = $(this);
  var userId = Number($option.data("userId") || 0);
  var userName = String($option.data("userName") || "Unassigned");

  var $card = $option.closest(".task-item-card");
  if ($card.length) {
    if (!canEdit) {
      notify("You do not have permission to assign work items.");
      return;
    }

    var itemId = Number($card.data("itemId") || 0);
    if (!itemId) {
      return;
    }

    postAction(
      {
        task_action: "set_item_assignee",
        item_id: itemId,
        assignee_user_id: userId,
      },
      function (res) {
        var assignee = (res && res.assignee) || {};
        var selectedUserId = Number(assignee.user_id || userId || 0);
        var selectedName =
          String(assignee.name || "").trim() ||
          (selectedUserId > 0 ? userName : "Unassigned");
        var $btn = $card.find(".task-item-assignee-btn");

        $card
          .attr("data-assignee-user-id", selectedUserId)
          .attr("data-assignee-name", selectedName);
        $btn
          .attr("data-user-id", selectedUserId)
          .attr("title", selectedName)
          .toggleClass("task-assignee-pill-unassigned", selectedUserId <= 0)
          .html(assigneeButtonInner(selectedUserId, selectedName));

        applyBoardViewSettingsToCard($card);
        if (isBoardGroupedByStatus()) {
          applyBoardFilters();
          return;
        }

        renderBoardGroupingLayout();
      },
    );

    return;
  }

  var $composer = $option.closest(".task-composer");
  if ($composer.length) {
    var normalizedName =
      String(userName || "Unassigned").trim() || "Unassigned";
    $composer
      .find(".task-assignee-toggle")
      .attr("data-user-id", userId)
      .attr("title", normalizedName)
      .html(assigneeButtonInner(userId, normalizedName));
  }
});

$app.on("show.bs.dropdown", ".dropdown", function () {
  var $menu = $(this).find(".task-assignee-menu").first();
  if (!$menu.length) {
    return;
  }
  $menu.find(".task-assignee-search-input").val("");
  $menu.find(".task-assignee-option").closest("li").show();
  setTimeout(function () {
    $menu.find(".task-assignee-search-input").trigger("focus");
  }, 50);
});

$app.on("shown.bs.dropdown", ".task-composer .dropdown", function () {
  repositionTaskComposerDropdown($(this));
});

$app.on("hidden.bs.dropdown", ".task-composer .dropdown", function () {
  $(this).removeClass("task-composer-dropdown-open-up");
  $(this)
    .children(".dropdown-menu")
    .removeAttr("data-bs-popper")
    .removeClass("task-composer-dropdown-fixed")
    .css({
      left: "",
      top: "",
      right: "",
      bottom: "",
      transform: "",
      visibility: "",
    });
});

$app.on("click keydown", ".task-assignee-search-input", function (e) {
  e.stopPropagation();
});

$app.on("input", ".task-assignee-search-input", function (e) {
  e.stopPropagation();
  var query = String($(this).val() || "")
    .trim()
    .toLowerCase();
  var $menu = $(this).closest(".task-assignee-menu");

  $menu.find(".task-assignee-option").each(function () {
    var $option = $(this);
    var label = String($option.data("userName") || "")
      .trim()
      .toLowerCase();
    var show = !query || label.indexOf(query) !== -1;
    $option.closest("li").toggle(show);
  });
});

$(document).on("click", ".task-work-type-action", function (e) {
  e.preventDefault();
  if (!canEdit) {
    notify("You do not have permission to manage work types.");
    return;
  }

  var action = String($(this).data("action") || "");
  var $context = $(this).closest(
    ".task-composer, .task-item-card, .task-item-detail-type-dropdown",
  );
  openWorkTypeModal(action === "edit" ? "edit" : "add", $context);
});

$(document).on("input", "#taskWorkTypeNameInput", function () {
  updateWorkTypeModalSaveState();
});

$(document).on("show.bs.modal", "#taskWorkTypeModal", function () {
  var $modal = $(this);
  var zIndex = 1080;
  suspendTaskItemDetailModalInteraction();
  $modal.css("z-index", String(zIndex));

  window.setTimeout(function () {
    var $backdrop = $(".modal-backdrop").last();
    if ($backdrop.length) {
      $backdrop
        .addClass("task-work-type-modal-backdrop")
        .css("z-index", String(zIndex - 5));
    }
  }, 0);
});

$(document).on("hidden.bs.modal", "#taskWorkTypeModal", function () {
  $(this).css("z-index", "");
  $(".modal-backdrop.task-work-type-modal-backdrop")
    .last()
    .removeClass("task-work-type-modal-backdrop")
    .css("z-index", "");
  resumeTaskItemDetailModalInteraction();

  if ($("#taskItemDetailModal").hasClass("show")) {
    $("body").addClass("modal-open").css("overflow", "hidden");
  }
});

$(document).on("show.bs.modal", "#taskItemWorklogModal, #taskItemWorklogDeleteModal", function () {
  var $modal = $(this);
  var zIndex = 1085;
  suspendTaskItemDetailModalInteraction();
  $modal.css("z-index", String(zIndex));

  window.setTimeout(function () {
    var $backdrop = $(".modal-backdrop").last();
    if ($backdrop.length) {
      $backdrop
        .addClass("task-item-worklog-modal-backdrop")
        .css("z-index", String(zIndex - 5));
    }
  }, 0);
});

$(document).on("shown.bs.modal", "#taskItemWorklogModal", function () {
  var initialHtml = String($(this).attr("data-editor-html") || "");
  if (typeof window.ensureWorklogEditorReady === "function") {
    window.ensureWorklogEditorReady().then(function () {
      if (typeof window.setWorklogEditorContent === "function") {
        window.setWorklogEditorContent(initialHtml);
      }
      if (window.tinymce && typeof window.tinymce.get === "function") {
        var editor = window.tinymce.get("taskItemWorklogDescriptionInput");
        if (editor) {
          editor.focus();
          return;
        }
      }
      $("#taskItemWorklogDescriptionInput").trigger("focus");
    });
    return;
  }

  $("#taskItemWorklogDescriptionInput").trigger("focus");
});

$(document).on("hidden.bs.modal", "#taskItemWorklogModal, #taskItemWorklogDeleteModal", function () {
  $(this).css("z-index", "");
  $(this).removeAttr("data-editor-html");
  $(".modal-backdrop.task-item-worklog-modal-backdrop")
    .last()
    .removeClass("task-item-worklog-modal-backdrop")
    .css("z-index", "");
  resumeTaskItemDetailModalInteraction();

  if ($("#taskItemDetailModal").hasClass("show")) {
    $("body").addClass("modal-open").css("overflow", "hidden");
  }
});

$(document).on("change", "#taskWorkTypeChangeIcon", function () {
  setWorkTypeIconPickerEnabled($(this).is(":checked"));
});

$(document).on("click", ".task-work-type-icon-option", function () {
  if (!$("#taskWorkTypeChangeIcon").is(":checked")) {
    return;
  }

  var iconPath = normalizeWorkTypeIcon($(this).data("iconPath"), "Task");
  workTypeModalState.iconPath = iconPath;
  $(".task-work-type-icon-option").removeClass("active");
  $(this).addClass("active");

  var btn = document.getElementById("taskWorkTypeIconDropdownBtn");
  if (btn && typeof bootstrap !== "undefined" && bootstrap.Dropdown) {
    bootstrap.Dropdown.getOrCreateInstance(btn).hide();
  }
});

$(document).on("click", "#taskWorkTypeSaveBtn", function () {
  if (!canEdit) {
    notify("You do not have permission to manage work types.");
    return;
  }

  var mode = workTypeModalState.mode === "edit" ? "edit" : "add";
  var name = String($("#taskWorkTypeNameInput").val() || "").trim();
  var remark = String($("#taskWorkTypeDescriptionInput").val() || "").trim();
  var keepPickedIcon = $("#taskWorkTypeChangeIcon").is(":checked");
  var iconPath = keepPickedIcon
    ? normalizeWorkTypeIcon(workTypeModalState.iconPath, name || "Task")
    : normalizeWorkTypeIcon(workTypeModalState.initialIconPath, name || "Task");

  if (!name) {
    updateWorkTypeModalSaveState();
    return;
  }

  var payload = {
    task_action: mode === "edit" ? "update_work_type" : "create_work_type",
    work_type_name: name,
    work_type_remark: remark,
    work_type_svg_icon: iconPath,
  };

  if (mode === "edit") {
    payload.work_type_id = Number(workTypeModalState.workTypeId || 0);
  }

  postAction(payload, function (res) {
    state.workTypes = Array.isArray(res.workTypes)
      ? res.workTypes.slice()
      : state.workTypes;
    normalizeAllWorkTypes();
    refreshWorkTypeMenus();

    var selected = null;
    if (mode === "edit") {
      selected = findWorkTypeById(Number(workTypeModalState.workTypeId || 0));
    }
    if (!selected) {
      selected = getWorkTypeByName(name);
    }
    if (!selected) {
      selected = normalizeWorkTypeEntry({
        id: Number(payload.work_type_id || 0),
        name: name,
        remark: remark,
        svg_icon: iconPath,
      });
    }

    var $context = $(workTypeModalState.composerEl || null);
    if ($context.length) {
      var $composer = $context.closest(".task-composer");
      if ($composer.length || $context.hasClass("task-composer")) {
        var $composerTarget = $context.hasClass("task-composer")
          ? $context
          : $composer;
        setComposerWorkType(
          $composerTarget.find(".task-work-type-toggle"),
          selected,
        );
      } else {
        var targetItemId = Number(
          $context.closest(".task-item-card").data("itemId") ||
            $context.find(".task-work-type-toggle").attr("data-item-id") ||
            $context.attr("data-item-id") ||
            0,
        );
        if (targetItemId > 0) {
          updateItemWorkType(targetItemId, Number(selected.id || 0), selected);
        }
      }
    }

    var modal = getWorkTypeModalInstance();
    if (modal) {
      modal.hide();
    }
  });
});

$(document).on("input", "#taskProjectKeyInput", function () {
  var value = normalizeProjectKey($(this).val());
  $(this).val(value);
});

function closeProjectKeyEditor() {
  var $input = $("#taskProjectKeyInput");
  $input.trigger("blur");
  $("#taskProjectKeySaveBtn, #taskProjectKeyClearBtn").trigger("blur");

  if (document.activeElement && typeof document.activeElement.blur === "function") {
    document.activeElement.blur();
  }
}

$(document).on("click", "#taskProjectKeySaveBtn", function () {
  if (!canEdit) {
    notify("You do not have permission to change project key.");
    return;
  }

  var key = normalizeProjectKey($("#taskProjectKeyInput").val());
  $("#taskProjectKeyInput").val(key);

  postAction(
    {
      task_action: "save_project_key",
      project_key: key,
    },
    function (res) {
      state.projectKey =
        res && res.projectKey && typeof res.projectKey === "object"
          ? res.projectKey
          : { id: 0, project_key: key };
      refreshCardItemKeys();
      closeProjectKeyEditor();
    },
  );
});

$(document).on("click", "#taskProjectKeyClearBtn", function () {
  closeProjectKeyEditor();
});

$app.on("click", ".task-create-item-btn", function () {
  if (!canAdd) {
    notify("You do not have permission to create work items.");
    return;
  }

  var $composer = $(this).closest(".task-composer");
  var $column = $composer.closest(".task-column");
  var columnId = Number($column.data("columnId") || 0);
  if (!canTargetStatusColumn(columnId)) {
    return;
  }
  var title = String($composer.find(".task-title-input").val() || "").trim();
  var workTypeId = Number(
    $composer.find(".task-work-type-toggle").attr("data-work-type-id") || 0,
  );
  var assigneeUserId = Number(
    $composer.find(".task-assignee-toggle").attr("data-user-id") || 0,
  );
  var dueDate = String(
    $composer.find(".task-due-date-input").val() || "",
  ).trim();

  if (!title) {
    notify("Please enter task title before creating.");
    return;
  }

  postAction(
    {
      task_action: "create_item",
      column_id: columnId,
      title: title,
      work_type_id: workTypeId,
      assignee_user_id: assigneeUserId,
      due_date: dueDate,
    },
    function (res) {
      var item = res.item || {};
      var $newCard = $(buildTaskCardHtml(item));
      setCardStatusColumn(
        $newCard,
        columnId,
        String($column.find(".task-column-title").first().text() || "").trim(),
      );
      $column.find(".task-item-list").append($newCard);
      applyBoardViewSettingsToCard($newCard);
      scrollColumnItemListToBottom($column);
      updateColumnCount($column);
      applyBoardFilters();
      refreshCardItemKeys();
      showTaskSuccess("Work item created successfully.");

      $composer.find(".task-title-input").val("");
      $composer.find(".task-due-date-input").val("");
      $composer
        .find(".task-assignee-toggle")
        .attr("data-user-id", 0)
        .attr("title", "Unassigned")
        .html('<i class="fa-regular fa-user"></i>');
      setComposerWorkType(
        $composer.find(".task-work-type-toggle"),
        defaultWorkType(),
      );
      $composer.find(".task-title-input").trigger("focus");
      enableCreateButton($composer);
    },
  );
});

$(document).on("show.bs.dropdown", ".task-item-menu-dropdown", function () {
  var $dropdown = $(this);
  var $btn = $dropdown.find(".task-item-menu-btn").first();
  var $card = $dropdown.closest(".task-item-card");
  var $column = $dropdown.closest(".task-column");
  var $menu = $dropdown.find(".task-item-menu-list").first();

  syncCardStatusMeta($card, true);
  var groupedByStatus = isBoardGroupedByStatus();

  var $cards = groupedByStatus ? $column.find(".task-item-card") : $();
  var total = groupedByStatus ? $cards.length : 0;
  var index = groupedByStatus ? $cards.index($card) : -1;

  var moveHtml = "";
  if (total >= 2) {
    if (index > 0) {
      moveHtml +=
        '<li><a class="dropdown-item task-item-action" href="#" data-action="move_top">To the top</a></li>';
      moveHtml +=
        '<li><a class="dropdown-item task-item-action" href="#" data-action="move_up">Up</a></li>';
    }
    if (index < total - 1) {
      moveHtml +=
        '<li><a class="dropdown-item task-item-action" href="#" data-action="move_down">Down</a></li>';
      moveHtml +=
        '<li><a class="dropdown-item task-item-action" href="#" data-action="move_bottom">To the bottom</a></li>';
    }
  }
  if (!moveHtml) {
    moveHtml =
      '<li><span class="dropdown-item-text text-muted">No move options</span></li>';
  }
  $menu.find(".task-item-move-options").html(moveHtml);

  var currentColumnId = getCardStatusColumnId($card);
  var statusHtml = "";
  var statusColumns = getBoardStatusColumns();
  for (var s = 0; s < statusColumns.length; s++) {
    var statusColumn = statusColumns[s] || {};
    var statusColumnId = Number(statusColumn.id || 0);
    if (!statusColumnId || statusColumnId === currentColumnId) {
      continue;
    }
    if (!canTargetStatusColumn(statusColumnId)) {
      continue;
    }

    var statusName = String(statusColumn.name || "").trim();
    if (!statusName) {
      continue;
    }

    statusHtml +=
      '<li><a class="dropdown-item task-item-action" href="#" data-action="change_status" data-target-column-id="' +
      statusColumnId +
      '">' +
      escHtml(statusName) +
      "</a></li>";
  }

  if (!statusHtml) {
    statusHtml =
      '<li><span class="dropdown-item-text text-muted">No other status</span></li>';
  }
  $menu.find(".task-item-status-options").html(statusHtml);

  var hasLabels = getItemLabelsFromCard($card).length > 0;
  $menu
    .find(".task-item-label-submenu-toggle")
    .html(
      (hasLabels ? "Edit label" : "Add labels") +
        ' <i class="fa-solid fa-chevron-right task-submenu-chevron"></i>',
    );

  var statusLabelIds = getItemStatusLabelIdsFromCard($card);
  $menu
    .find(".task-item-status-label-submenu-toggle")
    .html(
      (statusLabelIds.length
        ? "Edit task status labels"
        : "Add task status labels") +
        ' <i class="fa-solid fa-chevron-right task-submenu-chevron"></i>',
    );

  var isEpicCard =
    String($card.attr("data-work-type-name") || "")
      .trim()
      .toLowerCase() === "epic";
  $menu
    .find(".task-item-status-label-submenu-toggle")
    .closest(".task-item-submenu-wrap")
    .toggleClass("d-none", isEpicCard);

  var $parentWrap = $menu
    .find(".task-item-parent-submenu-toggle")
    .closest(".task-item-submenu-wrap");
  if ($parentWrap.length) {
    $parentWrap.toggleClass("d-none", isEpicCard);
    if (!isEpicCard) {
      var $parentSubmenu = $parentWrap.find(".task-item-parent-submenu");
      renderParentSubmenu($card, $parentSubmenu);
      updateCardParentSubmenuToggle(
        $card,
        Number($card.attr("data-parent-item-id") || 0),
      );
    }
  }

  $menu.find(".task-item-submenu-list").removeClass("show");
  $menu.find(".task-item-submenu-wrap").removeClass("show");

  setTimeout(function () {
    repositionTaskItemMenu($dropdown);
  }, 0);
});

$(document).on("hidden.bs.dropdown", ".task-item-menu-dropdown", function () {
  $(this).removeClass("task-item-menu-open-up");
  $(this)
    .find(".task-item-menu-list")
    .removeAttr("data-bs-popper")
    .removeClass("task-item-menu-fixed")
    .css({
      left: "",
      top: "",
      right: "",
      bottom: "",
      transform: "",
      visibility: "",
    });
});

$(window).on("resize scroll", function () {
  $(".task-composer .dropdown.show").each(function () {
    repositionTaskComposerDropdown($(this));
  });
});

function repositionVisibleTaskItemMenus() {
  $(".task-item-menu-dropdown.show").each(function () {
    repositionTaskItemMenu($(this));
  });

  $(".task-item-submenu-wrap.show").each(function () {
    repositionTaskItemSubmenu($(this));
  });
}

function bindTaskItemMenuRepositionListeners() {
  $(window)
    .off("scroll.taskItemMenu resize.taskItemMenu")
    .on("scroll.taskItemMenu resize.taskItemMenu", function () {
      repositionVisibleTaskItemMenus();
    });

  $app
    .add($app.find(".task-item-list"))
    .off("scroll.taskItemMenu")
    .on("scroll.taskItemMenu", function () {
      repositionVisibleTaskItemMenus();
    });
}

bindTaskItemMenuRepositionListeners();

function openItemSubmenu($toggle) {
  var action = String($toggle.data("action") || "");
  var $submenuWrap = $toggle.closest(".task-item-submenu-wrap");
  var $submenu = $submenuWrap.children(".task-item-submenu-list");
  var $menu = $toggle.closest(".task-item-menu-list");
  var $card = $toggle.closest(".task-item-card");

  if (!$submenu.length || !$menu.length) {
    return;
  }

  $menu.find(".task-item-submenu-list").not($submenu).removeClass("show");
  $menu.find(".task-item-submenu-wrap").not($submenuWrap).removeClass("show");

  if (action === "submenu_labels") {
    renderInlineLabelPanel($card, $submenu);
  } else if (action === "submenu_task_status_labels") {
    renderInlineStatusLabelPanel($card, $submenu);
  } else if (action === "submenu_parent") {
    renderParentSubmenu($card, $submenu);
  }

  $submenu.addClass("show");
  $submenuWrap.addClass("show");
  repositionTaskItemSubmenu($submenuWrap);
}

function closeItemSubmenu($wrap) {
  $wrap.removeClass("show");
  $wrap.removeClass("task-item-submenu-open-left");
  $wrap.children(".task-item-submenu-list").removeClass("show").css({
    left: "",
    top: "",
    right: "",
    bottom: "",
    transform: "",
    visibility: "",
  });
}

$app.on("mouseenter", ".task-item-submenu-wrap", function () {
  openItemSubmenu($(this).children(".task-item-submenu-toggle"));
});

$app.on("focusin", ".task-item-submenu-toggle", function () {
  openItemSubmenu($(this));
});

$app.on("click", ".task-item-submenu-toggle", function (e) {
  e.preventDefault();
  e.stopPropagation();

  var $toggle = $(this);
  var $wrap = $toggle.closest(".task-item-submenu-wrap");
  var $submenu = $wrap.children(".task-item-submenu-list");

  if ($submenu.hasClass("show")) {
    closeItemSubmenu($wrap);
  } else {
    openItemSubmenu($toggle);
  }
});

$(document).on("input", ".task-inline-label-search", function (e) {
  e.stopPropagation();
  var $submenu = $(this).closest(".task-item-label-submenu");
  refreshInlineLabelList($submenu);
});

$(document).on("input", ".task-item-parent-search-input", function (e) {
  e.stopPropagation();
  var $submenu = $(this).closest(".task-item-parent-submenu");
  refreshParentSubmenuList($submenu);
});

$(document).on("change", ".task-inline-label-checkbox", function (e) {
  e.stopPropagation();
  var labelId = Number($(this).val() || 0);
  if (!labelId) {
    return;
  }

  if ($(this).is(":checked")) {
    if (labelsPanelState.selected.indexOf(labelId) === -1) {
      labelsPanelState.selected.push(labelId);
    }
  } else {
    labelsPanelState.selected = labelsPanelState.selected.filter(function (id) {
      return id !== labelId;
    });
  }
});

$(document).on("click", ".task-inline-label-create-btn", function (e) {
  e.preventDefault();
  e.stopPropagation();

  if (!canEdit) {
    notify("You do not have permission to create labels.");
    return;
  }

  var $submenu = $(this).closest(".task-item-label-submenu");
  var $panel = $submenu.find(".task-inline-label-panel");
  var labelName = String(
    $panel.find(".task-inline-label-search").val() || "",
  ).trim();
  if (!labelName) {
    return;
  }

  postAction(
    {
      task_action: "create_label",
      label_name: labelName,
    },
    function (res) {
      syncKnownLabels(res.labels || (res.label ? [res.label] : []));
      if (res.label && Number(res.label.id || 0) > 0) {
        var createdId = Number(res.label.id || 0);
        if (labelsPanelState.selected.indexOf(createdId) === -1) {
          labelsPanelState.selected.push(createdId);
        }
      }
      refreshInlineLabelList($submenu);
    },
  );
});

$(document).on("click", ".task-inline-label-delete-btn", function (e) {
  e.preventDefault();
  e.stopPropagation();

  if (!canEdit) {
    notify("You do not have permission to delete labels.");
    return;
  }

  var labelId = Number($(this).data("labelId") || 0);
  if (!labelId) {
    return;
  }

  if (!window.confirm("Delete this label?")) {
    return;
  }

  var $submenu = $(this).closest(".task-item-label-submenu");
  postAction(
    {
      task_action: "delete_label",
      label_id: labelId,
    },
    function (res) {
      syncKnownLabels(Array.isArray(res.labels) ? res.labels : []);
      removeLabelFromState(labelId);
      refreshInlineLabelList($submenu);
      renderModalLabelChips();
      renderModalLabelOptions();
    },
  );
});

$(document).on("click", ".task-inline-label-save", function (e) {
  e.preventDefault();
  e.stopPropagation();

  var closeActionModal = $(this).closest("#taskItemActionModal").length > 0;
  var $submenu = $(this).closest(".task-item-label-submenu");
  var $card = resolveTaskActionCard($(this));
  var itemId = Number($card.data("itemId") || 0);
  if (!itemId) {
    return;
  }

  var selectedIds = labelsPanelState.selected
    .map(function (id) {
      return Number(id || 0);
    })
    .filter(function (id) {
      return id > 0;
    });

  postAction(
    {
      task_action: "set_item_labels",
      item_id: itemId,
      label_ids: selectedIds.join(","),
    },
    function (res) {
      syncKnownLabels(res.allLabels || []);
      if ($card.length) {
        setCardLabels($card, Array.isArray(res.labels) ? res.labels : []);
      }
      $submenu.removeClass("show");
      $submenu.closest(".task-item-submenu-wrap").removeClass("show");
      if (closeActionModal) {
        closeTaskItemActionModal();
      }
    },
  );
});

$(document).on("input", ".task-inline-status-search", function (e) {
  e.stopPropagation();
  var $submenu = $(this).closest(".task-item-status-label-submenu");
  refreshInlineStatusLabelList($submenu);
});

$(document).on("change", ".task-inline-status-checkbox", function (e) {
  e.stopPropagation();
  var statusLabelId = Number($(this).val() || 0);
  if (!statusLabelId) {
    return;
  }

  if ($(this).is(":checked")) {
    if (statusLabelsPanelState.selected.indexOf(statusLabelId) === -1) {
      statusLabelsPanelState.selected.push(statusLabelId);
    }
  } else {
    statusLabelsPanelState.selected = statusLabelsPanelState.selected.filter(
      function (id) {
        return id !== statusLabelId;
      },
    );
  }
});

$(document).on("click", ".task-inline-status-create-btn", function (e) {
  e.preventDefault();
  e.stopPropagation();

  if (!canEdit) {
    notify("You do not have permission to manage task status labels.");
    return;
  }

  var $submenu = $(this).closest(".task-item-status-label-submenu");
  var $panel = $submenu.find(".task-inline-status-panel");
  var statusLabelName = String(
    $panel.find(".task-inline-status-search").val() || "",
  ).trim();
  if (!statusLabelName) {
    return;
  }

  postAction(
    {
      task_action: "create_status_label",
      status_label_name: statusLabelName,
    },
    function (res) {
      normalizeStatusLabels(
        Array.isArray(res.statusLabels) ? res.statusLabels : [],
      );

      if (res.statusLabel && Number(res.statusLabel.id || 0) > 0) {
        var createdId = Number(res.statusLabel.id || 0);
        if (statusLabelsPanelState.selected.indexOf(createdId) === -1) {
          statusLabelsPanelState.selected.push(createdId);
        }
      }

      refreshInlineStatusLabelList($submenu);
      renderStatusLabelOptions(
        $("#taskItemDetailStatusSearchInput").val() || "",
      );
    },
  );
});

$(document).on("click", ".task-inline-status-delete-btn", function (e) {
  e.preventDefault();
  e.stopPropagation();

  if (!canEdit) {
    notify("You do not have permission to manage task status labels.");
    return;
  }

  var statusLabelId = Number($(this).data("statusLabelId") || 0);
  if (!statusLabelId) {
    return;
  }

  if (!window.confirm("Delete this task status label?")) {
    return;
  }

  var $submenu = $(this).closest(".task-item-status-label-submenu");
  postAction(
    {
      task_action: "delete_status_label",
      status_label_id: statusLabelId,
    },
    function (res) {
      normalizeStatusLabels(
        Array.isArray(res.statusLabels) ? res.statusLabels : [],
      );
      removeTaskStatusLabelFromCards(statusLabelId);
      statusLabelsPanelState.selected = statusLabelsPanelState.selected.filter(
        function (id) {
          return Number(id || 0) !== statusLabelId;
        },
      );
      refreshInlineStatusLabelList($submenu);
      renderStatusLabelOptions(
        $("#taskItemDetailStatusSearchInput").val() || "",
      );
    },
  );
});

$(document).on("click", ".task-inline-status-save", function (e) {
  e.preventDefault();
  e.stopPropagation();

  var closeActionModal = $(this).closest("#taskItemActionModal").length > 0;
  if (!canEdit) {
    notify("You do not have permission to manage task status labels.");
    return;
  }

  var $submenu = $(this).closest(".task-item-status-label-submenu");
  var $card = resolveTaskActionCard($(this));
  var itemId = Number($card.data("itemId") || 0);
  if (!itemId) {
    return;
  }

  var selectedIds = normalizeStatusLabelIdList(statusLabelsPanelState.selected);

  postAction(
    {
      task_action: "get_item_detail",
      item_id: itemId,
    },
    function (detailRes) {
      var detail =
        detailRes && detailRes.detail && typeof detailRes.detail === "object"
          ? detailRes.detail
          : {};

      postAction(
        {
          task_action: "update_item_detail",
          item_id: itemId,
          assignee_user_id: Number(detail.assignee_user_id || 0),
          reporter_user_id: Number(detail.reporter_user_id || 0),
          priority: String(detail.priority || "Medium"),
          original_estimate_value: Number(detail.original_estimate_value || 0),
          original_estimate_unit: String(
            detail.original_estimate_unit || "minutes",
          ),
          task_status_label_ids: selectedIds.join(","),
          start_date: String(detail.start_date || ""),
          due_date: String(detail.due_date || ""),
          amendement_date: String(detail.amendement_date || ""),
          amendement_time_minutes: Number(detail.amendement_time_minutes || 0),
          second_amendement_date: String(detail.second_amendement_date || ""),
          second_amendement_time_minutes: Number(
            detail.second_amendement_time_minutes || 0,
          ),
        },
        function (updateRes) {
          normalizeStatusLabels(
            Array.isArray(updateRes.statusLabels) ? updateRes.statusLabels : [],
          );
          var resolvedIds = normalizeStatusLabelIdList(
            updateRes &&
              updateRes.detail &&
              Array.isArray(updateRes.detail.task_status_label_ids)
              ? updateRes.detail.task_status_label_ids
              : selectedIds,
          );
          setCardTaskStatusLabels($card, resolvedIds);

          $submenu.removeClass("show");
          $submenu.closest(".task-item-submenu-wrap").removeClass("show");

          if (closeActionModal) {
            closeTaskItemActionModal();
          }

          if (Number(itemDetailModalState.itemId || 0) === itemId) {
            itemDetailModalState.selectedStatusLabelIds = resolvedIds.slice();
            renderStatusLabelChips();
            renderStatusLabelOptions(
              $("#taskItemDetailStatusSearchInput").val() || "",
            );
          }
        },
      );
    },
  );
});

$(document).on("click", ".task-item-action", function (e) {
  e.preventDefault();

  var $action = $(this);
  var closeActionModal = $action.closest("#taskItemActionModal").length > 0;
  var action = String($action.data("action") || "");
  var $card = resolveTaskActionCard($action);
  var $column = $action.closest(".task-column");
  if (!$column.length) {
    $column = $card.closest(".task-column");
  }
  var itemId = Number($card.data("itemId") || 0);

  if (!itemId) {
    return;
  }

  if (action === "delete") {
    if (!canDelete) {
      notify("You do not have permission to delete work items.");
      return;
    }
  } else if (!canEdit) {
    notify("You do not have permission to manage work items.");
    return;
  }

  if (
    action === "move_top" ||
    action === "move_up" ||
    action === "move_down" ||
    action === "move_bottom"
  ) {
    var moveMap = {
      move_top: "top",
      move_up: "up",
      move_down: "down",
      move_bottom: "bottom",
    };
    postAction(
      {
        task_action: "move_item",
        item_id: itemId,
        move_to: moveMap[action],
      },
      function () {
        var $cards = $column.find(".task-item-card");
        var currentIndex = $cards.index($card);
        if (action === "move_top") {
          $column.find(".task-item-list").prepend($card);
        } else if (action === "move_up" && currentIndex > 0) {
          $card.insertBefore($cards.eq(currentIndex - 1));
        } else if (action === "move_down" && currentIndex < $cards.length - 1) {
          $card.insertAfter($cards.eq(currentIndex + 1));
        } else if (action === "move_bottom") {
          $column.find(".task-item-list").append($card);
        }
        updateColumnCount($column);
        applyBoardFilters();
        if (closeActionModal) {
          closeTaskItemActionModal();
        }
      },
    );
    return;
  }

  if (action === "change_status") {
    var targetColumnId = Number($action.data("targetColumnId") || 0);
    if (!targetColumnId || !canTargetStatusColumn(targetColumnId)) {
      return;
    }

    postAction(
      {
        task_action: "change_item_status",
        item_id: itemId,
        target_column_id: targetColumnId,
      },
      function () {
        applyStatusChangeToBoard(
          $card,
          $column,
          targetColumnId,
          closeActionModal,
        );
      },
    );
    return;
  }

  if (action === "delete") {
    if (!window.confirm("Delete this work item?")) {
      return;
    }

    postAction(
      {
        task_action: "delete_item",
        item_id: itemId,
      },
      function () {
        $card.remove();
        updateColumnCount($column);
        applyBoardFilters();
        showTaskSuccess("Work item deleted successfully.");
        if (closeActionModal) {
          closeTaskItemActionModal();
        }
      },
    );
  }
});

function applyStatusChangeToBoard(
  $card,
  $sourceColumn,
  targetColumnId,
  closeActionModal,
) {
  var $source =
    $sourceColumn && $sourceColumn.length
      ? $sourceColumn
      : $card.closest(".task-column");
  var targetId = Number(targetColumnId || 0);
  if (!$card || !$card.length || targetId <= 0) {
    return;
  }

  var targetMeta = getBoardStatusColumnMeta(targetId);
  setCardStatusColumnMeta(
    $card,
    targetId,
    getBoardStatusColumnName(targetId),
    targetMeta ? targetMeta.color : "#DFE1E6",
  );

  if (!isBoardGroupedByStatus()) {
    if (closeActionModal) {
      closeTaskItemActionModal();
    }
    renderBoardGroupingLayout();
    return;
  }

  var $targetColumn = $app.find(
    '.task-column[data-column-id="' + targetId + '"]',
  );
  if (!$targetColumn.length) {
    if (closeActionModal) {
      closeTaskItemActionModal();
    }
    return;
  }

  $targetColumn.find(".task-item-list").append($card);
  if ($source.length) {
    updateColumnCount($source);
  }
  updateColumnCount($targetColumn);
  applyBoardFilters();
  if (closeActionModal) {
    closeTaskItemActionModal();
  }
}

function setCardParentRelation($card, parentItemId, onDone) {
  if (!canEdit) {
    notify("You do not have permission to link parent.");
    return;
  }

  var itemId = Number($card.data("itemId") || 0);
  var resolvedParentItemId = Number(parentItemId || 0);
  if (!itemId) {
    return;
  }

  postAction(
    {
      task_action: "set_item_parent",
      item_id: itemId,
      parent_item_id: resolvedParentItemId,
    },
    function (res) {
      var resolvedParentId = Number(
        (res && res.parent_item_id) || resolvedParentItemId || 0,
      );
      updateCardParentSubmenuToggle(
        $card,
        resolvedParentId,
        res && typeof res.parent_display === "string" ? res.parent_display : "",
      );

      if (typeof onDone === "function") {
        onDone(resolvedParentId, res || {});
      }

      if (Number(itemDetailModalState.itemId || 0) === itemId) {
        renderDetailParentSelect(
          resolvedParentId,
          Array.isArray(res.parentOptions)
            ? res.parentOptions
            : getBoardEpicParentOptions(itemId),
        );
      }
    },
  );
}

$(document).on("click", ".task-item-parent-option", function (e) {
  e.preventDefault();
  e.stopPropagation();

  var $option = $(this);
  var closeActionModal = $option.closest("#taskItemActionModal").length > 0;
  var $card = resolveTaskActionCard($option);
  var parentItemId = Number($option.data("parentItemId") || 0);

  setCardParentRelation($card, parentItemId, function () {
    var $submenu = $option.closest(".task-item-parent-submenu");
    renderParentSubmenu($card, $submenu);
    $submenu.removeClass("show");
    $submenu.closest(".task-item-submenu-wrap").removeClass("show");
    if (closeActionModal) {
      closeTaskItemActionModal();
    }
  });
});

$(document).on("change", ".task-item-parent-select", function (e) {
  e.stopPropagation();
  var $select = $(this);
  var closeActionModal = $select.closest("#taskItemActionModal").length > 0;
  var $card = resolveTaskActionCard($select);
  var parentItemId = Number($select.val() || 0);

  setCardParentRelation($card, parentItemId, function (resolvedParentId) {
    $select.val(String(resolvedParentId || 0));
    applyBoardViewSettingsToCard($card);
    if (closeActionModal) {
      closeTaskItemActionModal();
    }
  });
});

function getItemDetailModalInstance() {
  var modalEl = document.getElementById("taskItemDetailModal");
  if (!modalEl || typeof bootstrap === "undefined" || !bootstrap.Modal) {
    return null;
  }
  return bootstrap.Modal.getOrCreateInstance(modalEl);
}

function getTaskItemDetailFocusTrap(instance) {
  if (!instance || typeof instance !== "object") {
    return null;
  }

  return instance._focustrap || instance._focusTrap || null;
}

function suspendTaskItemDetailModalInteraction() {
  var modalEl = document.getElementById("taskItemDetailModal");
  if (!modalEl || !modalEl.classList.contains("show")) {
    return;
  }

  var modal = getItemDetailModalInstance();
  var focusTrap = getTaskItemDetailFocusTrap(modal);
  if (focusTrap && typeof focusTrap.deactivate === "function") {
    focusTrap.deactivate();
  }
}

function resumeTaskItemDetailModalInteraction() {
  var modalEl = document.getElementById("taskItemDetailModal");
  if (!modalEl) {
    return;
  }

  var hasChildModalOpen =
    $("#taskWorkTypeModal.show, #taskItemWorklogModal.show, #taskItemWorklogDeleteModal.show")
      .length > 0;
  if (hasChildModalOpen) {
    return;
  }

  if (!modalEl.classList.contains("show")) {
    return;
  }

  var modal = getItemDetailModalInstance();
  var focusTrap = getTaskItemDetailFocusTrap(modal);
  if (focusTrap && typeof focusTrap.activate === "function") {
    window.setTimeout(function () {
      focusTrap.activate();
    }, 0);
  }
}

var worklogModalMode = "create";
var worklogSaveInFlight = false;
var worklogDeleteInFlight = false;

function getItemWorklogModalInstance() {
  var modalEl = document.getElementById("taskItemWorklogModal");
  if (!modalEl || typeof bootstrap === "undefined" || !bootstrap.Modal) {
    return null;
  }
  return bootstrap.Modal.getOrCreateInstance(modalEl);
}

function getItemWorklogDeleteModalInstance() {
  var modalEl = document.getElementById("taskItemWorklogDeleteModal");
  if (!modalEl || typeof bootstrap === "undefined" || !bootstrap.Modal) {
    return null;
  }
  return bootstrap.Modal.getOrCreateInstance(modalEl);
}

function getCurrentWorklogRemainingSeconds() {
  return Math.max(
    0,
    Number((itemDetailModalState.timeTracking || {}).ownRemainingSeconds || 0),
  );
}

function getTaskWorklogById(worklogId) {
  var id = Number(worklogId || 0);
  var rows = Array.isArray(itemDetailModalState.worklogs)
    ? itemDetailModalState.worklogs
    : [];

  for (var i = 0; i < rows.length; i++) {
    if (Number((rows[i] || {}).id || 0) === id) {
      return rows[i] || null;
    }
  }

  return null;
}

function localDateInputValue(dateObj) {
  var dt = dateObj instanceof Date ? dateObj : new Date();
  var year = dt.getFullYear();
  var month = String(dt.getMonth() + 1).padStart(2, "0");
  var day = String(dt.getDate()).padStart(2, "0");
  return year + "-" + month + "-" + day;
}

function localTimeInputValue(dateObj) {
  var dt = dateObj instanceof Date ? dateObj : new Date();
  var hours = String(dt.getHours()).padStart(2, "0");
  var minutes = String(dt.getMinutes()).padStart(2, "0");
  return hours + ":" + minutes;
}

function normalizeTimeInputValue(value, fallback) {
  var text = String(value || "").trim();
  if (!text) {
    return String(fallback || "").trim();
  }
  if (/^\d{2}:\d{2}:\d{2}$/.test(text)) {
    return text.substring(0, 5);
  }
  if (/^\d{2}:\d{2}$/.test(text)) {
    return text;
  }
  return String(fallback || "").trim();
}

function setTaskWorklogModalBusy(isBusy) {
  worklogSaveInFlight = !!isBusy;
  $("#taskItemWorklogModal")
    .find("input, textarea, button")
    .prop("disabled", !!isBusy);
}

function setTaskWorklogDeleteModalBusy(isBusy) {
  worklogDeleteInFlight = !!isBusy;
  $("#taskItemWorklogDeleteModal")
    .find("input, button")
    .prop("disabled", !!isBusy);
}

function refreshWorklogDeleteRemainingPanel() {
  var checked = $("#taskItemWorklogDeleteAdjustRemainingInput").is(":checked");
  $("#taskItemWorklogDeleteRemainingPanel").toggleClass("d-none", !checked);
}

function updateWorklogDeleteDefaults() {
  var deletedSeconds = Number($("#taskItemWorklogDeleteDurationSeconds").val() || 0);
  var currentRemaining = getCurrentWorklogRemainingSeconds();
  var nextRemaining = currentRemaining + Math.max(0, deletedSeconds);

  $("#taskItemWorklogDeleteCurrentRemainingText").text(
    formatDurationInputText(currentRemaining),
  );
  $("#taskItemWorklogDeleteRemainingInput").val(
    formatDurationInputText(nextRemaining),
  );
  $("#taskItemWorklogDeleteHelpText").text(
    "By default, deleted time (" +
      formatDurationInputText(deletedSeconds) +
      ") is added to the time remaining.",
  );
}

function openTaskWorklogModal(mode, worklogRow, prefillDurationSeconds) {
  var itemId = Number(itemDetailModalState.itemId || 0);
  if (!itemId) {
    return;
  }
  if (!canEdit) {
    notify("You do not have permission to manage work logs.");
    return;
  }

  var row = worklogRow && typeof worklogRow === "object" ? worklogRow : {};
  var now = new Date();
  var isEdit = String(mode || "create") === "edit";
  var durationSeconds = isEdit
    ? Number(row.duration_seconds || 0)
    : Math.max(0, Number(prefillDurationSeconds || currentWorklogSeconds()));
  var remainingSeconds = isEdit
    ? Number(
        row.remaining_seconds_snapshot != null
          ? row.remaining_seconds_snapshot
          : getCurrentWorklogRemainingSeconds(),
      )
    : Math.max(0, getCurrentWorklogRemainingSeconds() - durationSeconds);
  var startedDate = isEdit
    ? String(row.started_date || "").trim()
    : localDateInputValue(now);
  var startedTime = isEdit
    ? normalizeTimeInputValue(row.started_time, localTimeInputValue(now))
    : localTimeInputValue(now);
  var descriptionHtml = isEdit
    ? String(row.work_description_html || "").trim()
    : "";

  worklogModalMode = isEdit ? "edit" : "create";
  $("#taskItemWorklogEntryId").val(isEdit ? Number(row.id || 0) : 0);
  $("#taskItemWorklogModalTitle").text(isEdit ? "Edit work log" : "Log work");
  $("#taskItemWorklogModalSummary")
    .toggleClass("d-none", !isEdit)
    .text(isEdit ? "Update the selected worklog entry." : "");
  $("#taskItemWorklogDurationInput").val(
    formatDurationInputText(durationSeconds),
  );
  $("#taskItemWorklogRemainingInput").val(
    formatDurationInputText(remainingSeconds),
  );
  $("#taskItemWorklogStartedDateInput").val(startedDate);
  $("#taskItemWorklogStartedTimeInput").val(startedTime);
  $("#taskItemWorklogDescriptionInput").val(descriptionHtml);
  $("#taskItemWorklogModal").attr("data-editor-html", descriptionHtml);
  if (typeof window.setWorklogEditorContent === "function") {
    window.setWorklogEditorContent(descriptionHtml);
  }

  setTaskWorklogModalBusy(false);
  var modal = getItemWorklogModalInstance();
  if (modal) {
    modal.show();
  }
}

window.openTaskWorklogCreateModal = function (prefillDurationSeconds) {
  openTaskWorklogModal("create", null, prefillDurationSeconds);
};

function openTaskWorklogDeleteModal(worklogRow) {
  var row = worklogRow && typeof worklogRow === "object" ? worklogRow : null;
  if (!row || !Number(row.id || 0)) {
    return;
  }
  if (!canEdit) {
    notify("You do not have permission to manage work logs.");
    return;
  }

  $("#taskItemWorklogDeleteEntryId").val(Number(row.id || 0));
  $("#taskItemWorklogDeleteDurationSeconds").val(
    Number(row.duration_seconds || 0),
  );
  $("#taskItemWorklogDeleteAdjustRemainingInput").prop("checked", true);
  updateWorklogDeleteDefaults();
  refreshWorklogDeleteRemainingPanel();
  setTaskWorklogDeleteModalBusy(false);

  var modal = getItemWorklogDeleteModalInstance();
  if (modal) {
    modal.show();
  }
}

function syncWorklogResponse(res, options) {
  var settings = options && typeof options === "object" ? options : {};
  if (Array.isArray(res && res.worklogs)) {
    itemDetailModalState.worklogs = res.worklogs.slice();
  }
  if (Array.isArray(res && res.history)) {
    itemDetailModalState.history = res.history.slice();
  }
  if (res && res.detail && typeof res.detail === "object") {
    updateCardFromDetail(res.detail);
    renderDetailTimeTracking(
      res.detail,
      Number($("#taskItemDetailEstimateValueInput").val() || 0),
      String($("#taskItemDetailEstimateUnitInput").val() || "minutes"),
    );
    if (typeof renderDetailMeta === "function") {
      renderDetailMeta(res.detail);
    }
  } else if (!settings.skipDetailReload) {
    loadItemDetail(Number(itemDetailModalState.itemId || 0));
  }
  renderItemHistoryPanels();
}

function getTaskWorklogEditorHtml() {
  if (typeof window.getWorklogEditorContent === "function") {
    return String(window.getWorklogEditorContent() || "");
  }
  return String($("#taskItemWorklogDescriptionInput").val() || "");
}

function submitTaskWorklogModal() {
  if (worklogSaveInFlight) {
    return;
  }

  var itemId = Number(itemDetailModalState.itemId || 0);
  var worklogId = Number($("#taskItemWorklogEntryId").val() || 0);
  var durationText = String($("#taskItemWorklogDurationInput").val() || "").trim();
  var remainingText = String($("#taskItemWorklogRemainingInput").val() || "").trim();
  var startedDate = String($("#taskItemWorklogStartedDateInput").val() || "").trim();
  var startedTime = String($("#taskItemWorklogStartedTimeInput").val() || "").trim();
  var durationSeconds = parseDurationTextToSeconds(durationText);
  var remainingSeconds = remainingText
    ? parseDurationTextToSeconds(remainingText)
    : 0;

  if (!itemId) {
    return;
  }
  if (durationSeconds <= 0) {
    notify("Time spent must be greater than 0.");
    return;
  }
  if (!startedDate) {
    notify("Date started is required.");
    return;
  }
  if (!startedTime) {
    notify("Time started is required.");
    return;
  }

  setTaskWorklogModalBusy(true);
  postAction(
    {
      task_action:
        worklogModalMode === "edit"
          ? "update_item_worklog"
          : "save_item_worklog",
      item_id: itemId,
      worklog_id: worklogId,
      duration_seconds: durationSeconds,
      remaining_seconds: Math.max(0, remainingSeconds),
      started_date: startedDate,
      started_time: startedTime,
      work_description_html: getTaskWorklogEditorHtml(),
    },
    function (res) {
      setTaskWorklogModalBusy(false);
      syncWorklogResponse(res);
      setItemActivityTab("worklog");

      if (worklogModalMode === "create") {
        worklogTimerState.elapsedSeconds = 0;
        worklogTimerState.running = false;
        worklogTimerState.startedAtMs = 0;
        persistWorklogTimerState();
        applyWorklogTimerUi();
      }

      var modal = getItemWorklogModalInstance();
      if (modal) {
        modal.hide();
      }

      showBoardToast(
        worklogModalMode === "edit" ? "Work log updated" : "Work log saved",
        worklogModalMode === "edit"
          ? "The work log entry was updated successfully."
          : "The work log entry was saved successfully.",
      );
    },
    function () {
      setTaskWorklogModalBusy(false);
    },
  );
}

function submitTaskWorklogDelete() {
  if (worklogDeleteInFlight) {
    return;
  }

  var itemId = Number(itemDetailModalState.itemId || 0);
  var worklogId = Number($("#taskItemWorklogDeleteEntryId").val() || 0);
  var adjustRemaining = $("#taskItemWorklogDeleteAdjustRemainingInput").is(":checked");
  var remainingText = String($("#taskItemWorklogDeleteRemainingInput").val() || "").trim();
  var remainingSeconds = remainingText
    ? parseDurationTextToSeconds(remainingText)
    : 0;

  if (!itemId || !worklogId) {
    return;
  }

  setTaskWorklogDeleteModalBusy(true);
  postAction(
    {
      task_action: "delete_item_worklog",
      item_id: itemId,
      worklog_id: worklogId,
      adjust_remaining: adjustRemaining ? 1 : 0,
      remaining_seconds: Math.max(0, remainingSeconds),
    },
    function (res) {
      setTaskWorklogDeleteModalBusy(false);
      syncWorklogResponse(res);
      setItemActivityTab("worklog");

      var modal = getItemWorklogDeleteModalInstance();
      if (modal) {
        modal.hide();
      }

      showBoardToast(
        "Work log deleted",
        "The work log entry was deleted successfully.",
      );
    },
    function () {
      setTaskWorklogDeleteModalBusy(false);
    },
  );
}

function buildAttachmentUrl(filePath) {
  var path = String(filePath || "")
    .trim()
    .replace(/\\/g, "/");
  if (!path) {
    return "#";
  }
  if (/^https?:\/\//i.test(path)) {
    return path;
  }
  if (path.charAt(0) === "/") {
    return path;
  }
  if (state.siteUrl) {
    return state.siteUrl + "/" + path.replace(/^\/+/, "");
  }
  return "../" + path.replace(/^\/+/, "");
}

function formatAttachmentSize(bytes) {
  var size = Number(bytes || 0);
  if (!size || size < 1024) {
    return (size || 0) + " B";
  }
  if (size < 1024 * 1024) {
    return Math.max(1, Math.round(size / 1024)) + " KB";
  }
  if (size < 1024 * 1024 * 1024) {
    return (size / (1024 * 1024)).toFixed(1).replace(/\.0$/, "") + " MB";
  }
  return (size / (1024 * 1024 * 1024)).toFixed(2).replace(/\.00$/, "") + " GB";
}

function isTaskAttachmentVideoFile(item) {
  var mimeType = String((item && item.mime_type) || "").toLowerCase();
  var fileName = String((item && item.file_name) || "").toLowerCase();
  return (
    mimeType.indexOf("video/") === 0 ||
    /\.(mp4|mov|webm|avi|mkv)$/i.test(fileName)
  );
}

function getTaskAttachmentIconHtml(item) {
  if (isTaskAttachmentVideoFile(item)) {
    return '<i class="fa-regular fa-file-video"></i>';
  }

  return '<i class="fa-regular fa-file-lines"></i>';
}

function attachmentTimestamp(item) {
  var date = String((item && item.create_date) || "").trim();
  var time = String((item && item.create_time) || "").trim();
  if (!date) {
    return 0;
  }
  var ts = Date.parse(date + (time ? " " + time : ""));
  return Number.isNaN(ts) ? 0 : ts;
}

function sortedAttachments(list) {
  var items = Array.isArray(list) ? list.slice() : [];
  var field = String(itemDetailModalState.attachmentSort.field || "date");
  var direction =
    String(itemDetailModalState.attachmentSort.direction || "desc") === "asc"
      ? "asc"
      : "desc";

  items.sort(function (a, b) {
    var valueA;
    var valueB;

    if (field === "name") {
      valueA = String((a && a.file_name) || "").toLowerCase();
      valueB = String((b && b.file_name) || "").toLowerCase();
      if (valueA < valueB) {
        return direction === "asc" ? -1 : 1;
      }
      if (valueA > valueB) {
        return direction === "asc" ? 1 : -1;
      }
      return 0;
    }

    if (field === "size") {
      valueA = Number((a && a.file_size) || 0);
      valueB = Number((b && b.file_size) || 0);
    } else {
      valueA = attachmentTimestamp(a);
      valueB = attachmentTimestamp(b);
    }

    if (valueA === valueB) {
      return 0;
    }
    return direction === "asc" ? valueA - valueB : valueB - valueA;
  });

  return items;
}

function updateAttachmentSortHeaders() {
  var field = String(itemDetailModalState.attachmentSort.field || "date");
  var direction =
    String(itemDetailModalState.attachmentSort.direction || "desc") === "asc"
      ? "asc"
      : "desc";

  $(".task-item-attachment-sort-btn").each(function () {
    var $btn = $(this);
    var btnField = String($btn.data("sortField") || "");
    var isActive = btnField === field;
    $btn.toggleClass("active", isActive);
    var iconClass =
      isActive && direction === "asc"
        ? "fa-arrow-up-long"
        : "fa-arrow-down-long";
    $btn.find("i").attr("class", "fa-solid " + iconClass);
  });
}

function setAttachmentPanelCollapsed(collapsed) {
  var isCollapsed = !!collapsed;
  itemDetailModalState.attachmentsCollapsed = isCollapsed;
  var $panel = $("#taskItemAttachmentsPanel");
  $panel.toggleClass("task-item-attachments-collapsed", isCollapsed);
  var $btn = $("#taskItemAttachmentCollapseBtn");
  $btn.attr("aria-expanded", isCollapsed ? "false" : "true");
  $btn
    .find("i")
    .attr(
      "class",
      "fa-solid " + (isCollapsed ? "fa-chevron-right" : "fa-chevron-down"),
    );
  $btn.attr(
    "title",
    isCollapsed ? "Expand attachments" : "Collapse attachments",
  );
}

function setDescriptionPanelCollapsed(collapsed) {
  var isCollapsed = !!collapsed;
  itemDetailModalState.descriptionCollapsed = isCollapsed;
  var $section = $("#taskItemDetailDescriptionSection");
  $section.toggleClass("task-item-detail-description-collapsed", isCollapsed);
  var $btn = $("#taskItemDetailDescriptionCollapseBtn");
  $btn.attr("aria-expanded", isCollapsed ? "false" : "true");
  $btn
    .find("i")
    .attr(
      "class",
      "fa-solid " + (isCollapsed ? "fa-chevron-right" : "fa-chevron-down"),
    );
  $btn.attr(
    "title",
    isCollapsed ? "Expand description" : "Collapse description",
  );
}

function setActivitySectionCollapsed(collapsed) {
  var isCollapsed = !!collapsed;
  itemDetailModalState.activityCollapsed = isCollapsed;
  var $section = $("#taskItemActivitySection");
  $section.toggleClass("task-item-activity-collapsed", isCollapsed);
  $("#taskItemActivityBody").toggleClass("d-none", isCollapsed);

  var $btn = $("#taskItemActivityCollapseBtn");
  $btn.attr("aria-expanded", isCollapsed ? "false" : "true");
  $btn
    .find("i")
    .attr(
      "class",
      "fa-solid " + (isCollapsed ? "fa-chevron-right" : "fa-chevron-down"),
    );
  $btn.attr("title", isCollapsed ? "Expand activity" : "Collapse activity");
}

function renderItemAttachments(attachments) {
  var list = Array.isArray(attachments) ? attachments : [];
  itemDetailModalState.attachments = list.slice();
  var showPanel =
    list.length > 0 || itemDetailModalState.showAttachmentPanelWhenEmpty;
  $("#taskItemAttachmentsPanel").toggleClass("d-none", !showPanel);
  $("#taskItemAttachmentCount").text(String(list.length));
  $("#taskItemAttachmentDownloadAllCount").text(String(list.length));
  $("#taskItemAttachmentToggleViewAction").text(
    itemDetailModalState.attachmentView === "strip"
      ? "Switch to list view"
      : "Switch to strip view",
  );
  updateAttachmentSortHeaders();

  var $list = $("#taskItemAttachmentList");
  if (!$list.length) {
    return;
  }

  if (!showPanel) {
    return;
  }

  var sortedList = sortedAttachments(list);
  var isStripView = itemDetailModalState.attachmentView === "strip";
  $("#taskItemAttachmentDetails").toggleClass(
    "task-item-attachment-strip-view",
    isStripView,
  );
  $(".task-item-attachment-table-head").toggleClass("d-none", isStripView);

  if (!sortedList.length) {
    $list.html(
      '<div class="task-item-attachment-empty">No attachments yet.</div>',
    );
    return;
  }

  var html = "";
  for (var i = 0; i < sortedList.length; i++) {
    var item = sortedList[i] || {};
    var fileName = String(item.file_name || "").trim();
    if (!fileName) {
      continue;
    }

    var sizeText = formatAttachmentSize(item.file_size);
    var dateText = String(item.create_date || "").trim();
    if (dateText) {
      var timeText = String(item.create_time || "").trim();
      if (timeText) {
        dateText += " " + timeText;
      }
    }

    var attachmentId = Number(item.id || 0);

    if (isStripView) {
      html +=
        '<div class="task-item-attachment-tile" data-attachment-id="' +
        attachmentId +
        '">' +
        '<a class="task-item-attachment-tile-link" href="' +
        escHtml(buildAttachmentUrl(item.file_path || "")) +
        '" target="_blank" rel="noopener" title="' +
        escHtml(fileName) +
        '">' +
        '<span class="task-item-attachment-tile-preview">' +
        getTaskAttachmentIconHtml(item) +
        "</span>" +
        '<span class="task-item-attachment-tile-name">' +
        escHtml(fileName) +
        "</span>" +
        '<span class="task-item-attachment-tile-meta">' +
        escHtml(dateText || sizeText) +
        "</span>" +
        "</a>" +
        '<div class="task-item-attachment-tile-actions">' +
        '<a class="btn task-item-attachment-action-btn" href="' +
        escHtml(buildAttachmentUrl(item.file_path || "")) +
        '" download title="Download"><i class="fa-solid fa-download"></i></a>' +
        '<button class="btn task-item-attachment-action-btn task-item-attachment-remove-btn" type="button" data-attachment-id="' +
        attachmentId +
        '" title="Remove"><i class="fa-regular fa-trash-can"></i></button>' +
        "</div>" +
        "</div>";
      continue;
    }

    html +=
      '<div class="task-item-attachment-row" data-attachment-id="' +
      attachmentId +
      '">' +
      '<div class="task-item-attachment-col task-item-attachment-col-name"><a href="' +
      escHtml(buildAttachmentUrl(item.file_path || "")) +
      '" target="_blank" rel="noopener">' +
      escHtml(fileName) +
      "</a></div>" +
      '<div class="task-item-attachment-col task-item-attachment-col-size">' +
      escHtml(sizeText) +
      "</div>" +
      '<div class="task-item-attachment-col task-item-attachment-col-date">' +
      escHtml(dateText) +
      "</div>" +
      '<div class="task-item-attachment-col task-item-attachment-col-actions">' +
      '<a class="btn task-item-attachment-action-btn" href="' +
      escHtml(buildAttachmentUrl(item.file_path || "")) +
      '" download title="Download"><i class="fa-solid fa-download"></i></a>' +
      '<button class="btn task-item-attachment-action-btn task-item-attachment-remove-btn" type="button" data-attachment-id="' +
      attachmentId +
      '" title="Remove"><i class="fa-regular fa-trash-can"></i></button>' +
      "</div>" +
      "</div>";
  }

  $list.html(
    html || '<div class="task-item-attachment-empty">No attachments yet.</div>',
  );
}

function triggerAttachmentDownloads(items) {
  var list = Array.isArray(items) ? items : [];
  if (!list.length) {
    notify("No attachments available to download.");
    return;
  }

  for (var i = 0; i < list.length; i++) {
    var item = list[i] || {};
    var fileUrl = buildAttachmentUrl(item.file_path || "");
    if (!fileUrl || fileUrl === "#") {
      continue;
    }

    var link = document.createElement("a");
    link.href = fileUrl;
    link.setAttribute("download", item.file_name || "attachment");
    link.style.display = "none";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }
}

function loadItemAttachments(itemId) {
  var id = Number(itemId || 0);
  if (id <= 0) {
    renderItemAttachments([]);
    return;
  }

  postAction(
    {
      task_action: "get_item_attachments",
      item_id: id,
    },
    function (res) {
      renderItemAttachments(
        res && Array.isArray(res.attachments) ? res.attachments : [],
      );
    },
    function () {
      renderItemAttachments([]);
    },
  );
}

function readDescriptionFromInputField() {
  return String($("#taskItemDetailDescriptionInput").val() || "").trim();
}

function writeDescriptionToInputField(html) {
  var value = String(html || "");
  $("#taskItemDetailDescriptionInput").val(value);
}

function descriptionHasRenderableContent(html) {
  var $tmp = $("<div>").html(String(html || ""));
  var text = String($tmp.text() || "")
    .replace(/\u00a0/g, " ")
    .replace(/\s+/g, " ")
    .trim();

  if (text) {
    return true;
  }

  return $tmp.find("img,video,audio,iframe,object,embed,table,a").length > 0;
}

function renderItemDetailDescriptionPreview(sourceHtml) {
  var html =
    typeof sourceHtml === "string"
      ? sourceHtml
      : readDescriptionFromInputField();

  function normalizeDescriptionChecklistHtml(inputHtml) {
    var value = String(inputHtml || "");
    if (!value) {
      return value;
    }

    var wrapper = document.createElement("div");
    wrapper.innerHTML = value;

    var lists = wrapper.querySelectorAll("ul");
    for (var i = 0; i < lists.length; i += 1) {
      var list = lists[i];
      if (
        list.querySelector(
          'input.task-editor-checkbox,input[type="checkbox"][data-task-editor="1"]',
        )
      ) {
        list.classList.add("task-editor-checklist");
      }
    }

    return wrapper.innerHTML;
  }

  html = normalizeDescriptionChecklistHtml(html);

  var $view = $("#taskItemDetailDescriptionView");
  var $text = $("#taskItemDetailDescriptionViewText");
  var $content = $("#taskItemDetailDescriptionViewContent");
  if (!$view.length || !$text.length || !$content.length) {
    return;
  }

  if (descriptionHasRenderableContent(html)) {
    $view.removeClass("is-empty");
    $text.addClass("d-none");
    $content.html(String(html || "")).removeClass("d-none");
  } else {
    $view.addClass("is-empty");
    $content.addClass("d-none").empty();
    $text.removeClass("d-none");
    $text.text("Add a description...");
  }

  if (
    window.taskBoardDescriptionDraft &&
    typeof window.taskBoardDescriptionDraft.updateNotice === "function"
  ) {
    window.taskBoardDescriptionDraft.updateNotice();
  }
}

function setItemDetailDescriptionEditMode(isEditing, options) {
  var settings = options && typeof options === "object" ? options : {};
  var editing = !!isEditing && !!canEdit;
  itemDetailModalState.descriptionEditing = editing;

  $("#taskItemDetailDescriptionViewWrap").toggleClass("d-none", editing);
  $("#taskItemDetailDescriptionEditWrap").toggleClass("d-none", !editing);
  if (typeof window.setTaskItemDetailMobileOverlayState === "function") {
    window.setTaskItemDetailMobileOverlayState("description", editing);
  }

  if (editing) {
    if (!settings.skipEditorSync) {
      var html =
        typeof settings.descriptionHtml === "string"
          ? settings.descriptionHtml
          : readDescriptionFromInputField();
      writeDescriptionToInputField(html);
      if (typeof window.setDescriptionEditorContent === "function") {
        window.setDescriptionEditorContent(html);
      }
    }

    if (typeof window.ensureDescriptionEditorReady === "function") {
      window.ensureDescriptionEditorReady().then(function () {
        if (settings.focus === false) {
          return;
        }
        if (window.tinymce && typeof window.tinymce.get === "function") {
          var editor = window.tinymce.get("taskItemDetailDescriptionInput");
          if (editor) {
            editor.focus();
            return;
          }
        }
        $("#taskItemDetailDescriptionInput").trigger("focus");
      });
    } else if (settings.focus !== false) {
      $("#taskItemDetailDescriptionInput").trigger("focus");
    }

    return;
  }

  if (!settings.skipPreviewUpdate) {
    renderItemDetailDescriptionPreview();
  }
}

window.renderItemDetailDescriptionPreview = renderItemDetailDescriptionPreview;
window.setItemDetailDescriptionEditMode = setItemDetailDescriptionEditMode;

function saveItemCoreFromModal(closeAfterSave) {
  var settings =
    arguments.length > 1 && arguments[1] && typeof arguments[1] === "object"
      ? arguments[1]
      : {};
  if (!canEdit) {
    notify("You do not have permission to update work item.");
    return;
  }

  var itemId = Number(itemDetailModalState.itemId || 0);
  if (!itemId) {
    return;
  }

  var coreValues = getModalCoreValues();
  var title = String(coreValues.title || "").trim();
  var description = String(coreValues.description || "").trim();

  if (!title) {
    if (!settings.autosave) {
      notify("Work item title is required.");
    }
    setItemDetailAutosaveStatus("error", "Title is required before saving");
    return;
  }

  var currentSnapshot = buildModalCoreSnapshot(coreValues);
  if (currentSnapshot === itemDetailModalState.lastSavedCoreSnapshot) {
    if (settings.exitDescriptionEditModeOnNoChange) {
      setItemDetailDescriptionEditMode(false, {
        skipPreviewUpdate: false,
      });
    }

    if (!settings.suppressNoChangeMessage) {
      showNoChangeMessage();
    } else if (!settings.autosave) {
      setItemDetailAutosaveStatus("saved", "No changes to save");
    }
    return;
  }

  if (itemDetailModalState.coreSaveInFlight) {
    itemDetailModalState.queuedCoreSave = true;
    return;
  }

  clearItemDetailAutosaveTimer("coreAutosaveTimer");
  itemDetailModalState.coreSaveInFlight = true;
  setItemDetailAutosaveStatus("saving", "Saving changes...");

  postAction(
    {
      task_action: "update_item_core",
      item_id: itemId,
      title: title,
      description: description,
      description_attachment_paths:
        typeof window.getDescriptionUploadedAttachmentPaths === "function"
          ? window.getDescriptionUploadedAttachmentPaths().join(",")
          : "",
    },
    function () {
      var $card = $(itemDetailModalState.cardEl || null);
      if ($card.length) {
        $card.find(".task-item-title").text(title);
        $card.attr("data-item-description", description);
      }

      itemDetailModalState.lastSavedCoreSnapshot = currentSnapshot;
      itemDetailModalState.initialTitle = title;
      itemDetailModalState.initialDescription = description;
      if (typeof window.setDescriptionEditorContent === "function") {
        window.setDescriptionEditorContent(description);
      }
      renderItemDetailDescriptionPreview(description);
      itemDetailModalState.coreSaveInFlight = false;
      loadItemHistory(itemId);

      if (
        itemDetailModalState.queuedCoreSave ||
        buildModalCoreSnapshot() !== itemDetailModalState.lastSavedCoreSnapshot
      ) {
        itemDetailModalState.queuedCoreSave = false;
        saveItemCoreFromModal(false, {
          autosave: true,
          suppressNoChangeMessage: true,
          silentSuccess: true,
        });
        return;
      }

      if (!settings.silentSuccess) {
        setItemDetailAutosaveStatus("saved", "All changes saved");
      }

      if (settings.exitTitleEditModeOnSuccess) {
        itemDetailModalState.titleEditing = false;
        $(".task-item-detail-title-row").removeClass("is-editing");
      }

      if (settings.exitDescriptionEditModeOnSuccess) {
        setItemDetailDescriptionEditMode(false, {
          skipPreviewUpdate: true,
        });
      }

      if (
        settings.clearDescriptionDraftOnSuccess &&
        window.taskBoardDescriptionDraft &&
        typeof window.taskBoardDescriptionDraft.clear === "function"
      ) {
        window.taskBoardDescriptionDraft.clear();
      }

      if (typeof window.clearDescriptionUploadedAttachmentPaths === "function") {
        window.clearDescriptionUploadedAttachmentPaths();
      }

      if (closeAfterSave) {
        var modal = getItemDetailModalInstance();
        if (modal) {
          modal.hide();
        }
      }
    },
    function (res) {
      itemDetailModalState.coreSaveInFlight = false;
      if (isPermissionDeniedResponse(res)) {
        itemDetailModalState.queuedCoreSave = false;
        restoreItemDetailStateAfterDeniedSave();
        return;
      }
      setItemDetailAutosaveStatus("error", "Failed to save changes");
    },
  );
}

function clearItemDetailAutosaveTimer(timerKey) {
  var timerId = Number(itemDetailModalState[timerKey] || 0);
  if (!timerId) {
    return;
  }

  window.clearTimeout(timerId);
  itemDetailModalState[timerKey] = 0;
}

function setItemDetailTitleEditMode(isEditing) {
  var editing = !!isEditing && canEdit;
  itemDetailModalState.titleEditing = editing;
  $(".task-item-detail-title-row").toggleClass("is-editing", editing);
}

function resetItemDetailTitleEdit() {
  clearItemDetailAutosaveTimer("coreAutosaveTimer");
  $("#taskItemDetailTitleInput").val(
    String(itemDetailModalState.initialTitle || ""),
  );
  resizeItemDetailTitleInput();
  setItemDetailTitleEditMode(false);
}

function scheduleItemDetailCoreAutosave(delay) {
  if (!canEdit || !Number(itemDetailModalState.itemId || 0)) {
    return;
  }

  clearItemDetailAutosaveTimer("coreAutosaveTimer");
  itemDetailModalState.coreAutosaveTimer = window.setTimeout(
    function () {
      itemDetailModalState.coreAutosaveTimer = 0;
      saveItemCoreFromModal(false, {
        autosave: true,
        suppressNoChangeMessage: true,
        silentSuccess: true,
      });
    },
    Math.max(0, Number(delay || 0)),
  );
}

function scheduleItemDetailFieldAutosave(delay) {
  if (!canEdit || !Number(itemDetailModalState.itemId || 0)) {
    return;
  }

  clearItemDetailAutosaveTimer("detailAutosaveTimer");
  itemDetailModalState.detailAutosaveTimer = window.setTimeout(
    function () {
      itemDetailModalState.detailAutosaveTimer = 0;
      saveItemDetailsFromModal(false, {
        autosave: true,
        suppressNoChangeMessage: true,
        silentSuccess: true,
      });
    },
    Math.max(0, Number(delay || 0)),
  );
}

function openItemDetailModal($card) {
  var modal = getItemDetailModalInstance();
  if (!modal) {
    notify("Work item modal is unavailable.");
    return;
  }

  var itemId = Number($card.data("itemId") || 0);
  if (!itemId) {
    return;
  }

  var title = String($card.find(".task-item-title").text() || "").trim();
  var description = String($card.attr("data-item-description") || "").trim();

  itemDetailModalState.itemId = itemId;
  itemDetailModalState.cardEl = $card.get(0) || null;
  itemDetailModalState.initialTitle = title;
  itemDetailModalState.initialDescription = description;
  itemDetailModalState.descriptionEditing = false;
  itemDetailModalState.descriptionCollapsed = false;
  itemDetailModalState.workTypeName = String(
    $card.attr("data-work-type-name") || "Task",
  );
  itemDetailModalState.workTypeIcon = String(
    $card.attr("data-work-type-icon") || "",
  );
  itemDetailModalState.workItemKey = String(
    $card.attr("data-work-item-key") || buildWorkItemKey(itemId),
  );
  replaceTaskItemUrl(itemId);
  itemDetailModalState.parentWorkItemKey = "";
  itemDetailModalState.parentWorkTypeName = "Task";
  itemDetailModalState.parentWorkTypeIcon = "";
  itemDetailModalState.timeTracking = {
    ownText: "No time logged",
    ownSeconds: 0,
    ownRemainingSeconds: 0,
    ownEstimateSeconds: 0,
    childText: "No time logged",
    childSeconds: 0,
    childRemainingSeconds: 0,
    canIncludeChild: false,
    includeChild: false,
  };

  $("#taskItemDetailTitleInput").val(title);
  resizeItemDetailTitleInput();
  $("#taskItemDetailDescriptionInput").val(description);
  setItemDetailTitleEditMode(false);
  setDescriptionPanelCollapsed(false);
  if (typeof window.clearDescriptionUploadedAttachmentPaths === "function") {
    window.clearDescriptionUploadedAttachmentPaths();
  }
  renderItemDetailDescriptionPreview(description);
  setItemDetailDescriptionEditMode(false, {
    skipPreviewUpdate: true,
  });
  itemDetailModalState.attachmentSort = {
    field: "date",
    direction: "desc",
  };
  itemDetailModalState.attachmentView = "list";
  itemDetailModalState.showAttachmentPanelWhenEmpty = false;
  itemDetailModalState.pendingAttachmentPicker = false;
  itemDetailModalState.selectedPriority = "Medium";
  itemDetailModalState.detailStatusColumnId = Number(
    getCardStatusColumnId($card) || 0,
  );
  itemDetailModalState.selectedStatusLabelIds = [];
  itemDetailModalState.selectedLabelIds = [];
  itemDetailModalState.comments = [];
  itemDetailModalState.commentsLoading = false;
  itemDetailModalState.worklogs = [];
  itemDetailModalState.worklogsLoading = false;
  itemDetailModalState.historyRequestSeq = 0;
  itemDetailModalState.worklogRequestSeq = 0;
  itemDetailModalState.childWorkItems = {
    items: [],
    total: 0,
    done: 0,
    progress_percent: 0,
  };
  itemDetailModalState.isParentType = false;
  itemDetailModalState.childCreatePanelOpen = false;
  itemDetailModalState.childCreateMode = "create";
  itemDetailModalState.childCreateSelectedItemId = 0;
  itemDetailModalState.childCreateSearchResults = [];
  clearChildCreateSearchTimer();
  itemDetailModalState.itemLinks = {
    groups: [],
    total: 0,
  };
  itemDetailModalState.linkEditorOpen = false;
  itemDetailModalState.linkRelationType = "";
  itemDetailModalState.linkSelectedItemId = 0;
  itemDetailModalState.linkSearchResults = [];
  clearLinkSearchTimer();
  itemDetailModalState.childTitleEditingItemId = 0;
  itemDetailModalState.childPickerItemId = 0;
  itemDetailModalState.childPickerField = "";
  itemDetailModalState.childWorkItemsCollapsed = false;
  itemDetailModalState.history = [];
  itemDetailModalState.activityCollapsed = false;
  itemDetailModalState.activityTab = "all";
  itemDetailModalState.activitySortDirection = "desc";
  itemDetailModalState.detailsCollapsed = false;
  itemDetailModalState.initialSaveSnapshot = "";
  itemDetailModalState.lastSavedCoreSnapshot = "";
  itemDetailModalState.lastSavedDetailSnapshot = "";
  itemDetailModalState.lastSavedLabelsSnapshot = "";
  itemDetailModalState.coreSaveInFlight = false;
  itemDetailModalState.detailSaveInFlight = false;
  itemDetailModalState.labelsSaveInFlight = false;
  itemDetailModalState.queuedCoreSave = false;
  itemDetailModalState.queuedDetailSave = false;
  itemDetailModalState.queuedLabelsSave = false;
  clearItemDetailAutosaveTimer("coreAutosaveTimer");
  clearItemDetailAutosaveTimer("detailAutosaveTimer");
  setDescriptionPanelCollapsed(false);
  setAttachmentPanelCollapsed(false);
  renderItemAttachments([]);
  renderItemHistoryPanels();
  setItemActivityTab("all");
  setSelectedStatusLabels([]);
  renderStatusLabelOptions("");
  renderDetailAssigneeSelect(0);
  renderDetailReporterSelect(0);
  renderModalLabelChips();
  renderModalLabelOptions();
  renderChildWorkItemsSection();
  renderChildCreatePanel();
  renderLinkedWorkItemsSection();
  setChildWorkItemsCollapsed(false);
  setActivitySectionCollapsed(false);
  applyDetailFieldVisibility();
  setDetailSideCollapsed(false);
  renderDetailKeyTrail();
  $("#taskItemDetailCreatedMeta").text("-");
  $("#taskItemDetailUpdatedMeta").text("-");
  setDetailPriority("Medium");
  renderDetailParentDropdown(0, []);
  setItemDetailAutosaveStatus("", "");
  renderDetailTimeTracking(
    {
      own_time_tracking: "No time logged",
      own_time_tracking_seconds: 0,
      own_remaining_seconds: 0,
      own_estimate_seconds: 0,
      child_time_tracking: "No time logged",
      child_time_tracking_seconds: 0,
      child_remaining_seconds: 0,
      can_include_child_time_tracking: 0,
      include_child_time_tracking: 0,
    },
    0,
    "minutes",
  );
  $("#taskItemDetailEstimateValueInput").val("0");
  $("#taskItemDetailEstimateUnitInput").val("minutes");
  $("#taskItemDetailStartDateInput").val("");
  $("#taskItemDetailDueDateInput").val("");
  $("#taskItemDetailAmendDateInput").val("");
  $("#taskItemDetailAmendTimeInput").val("");
  $("#taskItemDetailSecondAmendDateInput").val("");
  $("#taskItemDetailSecondAmendTimeInput").val("");
  openWorklogTimerForItem(itemId);
  loadItemAttachments(itemId);
  loadItemDetail(itemId);
  loadItemHistory(itemId);
  loadItemWorklogs(itemId);
  if (typeof loadItemComments === "function") {
    loadItemComments(itemId);
  }
  modal.show();
}

function buildWorkItemPermalink(itemId) {
  var baseUrl = String(window.location.href || "").split("#")[0];
  var id = Number(itemId || 0);
  try {
    var url = new URL(baseUrl);
    url.searchParams.delete("selectedIssue");
    url.hash = "task-item-" + String(id);
    return url.href;
  } catch (error) {
    return baseUrl + "#task-item-" + String(id);
  }
}

function copyTextToClipboard(text, successMessage) {
  var value = String(text || "").trim();
  if (!value) {
    return;
  }

  var fallbackCopy = function () {
    var $temp = $('<input type="text" readonly>');
    $("body").append($temp);
    $temp.val(value).trigger("focus").trigger("select");
    document.execCommand("copy");
    $temp.remove();
    notify(successMessage || "Copied.");
  };

  if (
    navigator.clipboard &&
    typeof navigator.clipboard.writeText === "function"
  ) {
    navigator.clipboard
      .writeText(value)
      .then(function () {
        notify(successMessage || "Copied.");
      })
      .catch(function () {
        fallbackCopy();
      });
    return;
  }

  fallbackCopy();
}

function copyCurrentWorkItemUrl() {
  var itemId = Number(itemDetailModalState.itemId || 0);
  if (itemId <= 0) {
    return;
  }

  copyTextToClipboard(
    buildWorkItemPermalink(itemId),
    "Work item URL copied.",
  );
}

function hideItemDetailAddDropdown() {
  var dropdownBtn = document.getElementById("taskItemDetailAddBtn");
  if (
    !dropdownBtn ||
    typeof bootstrap === "undefined" ||
    !bootstrap.Dropdown
  ) {
    return;
  }

  bootstrap.Dropdown.getOrCreateInstance(dropdownBtn).hide();
}

function createFallbackWorkItemCard(itemId, fallbackData) {
  var info = fallbackData && typeof fallbackData === "object" ? fallbackData : {};
  var workTypeName = String(info.work_type_name || "Task").trim() || "Task";
  var workTypeIcon = normalizeWorkTypeIcon(
    info.work_type_svg_icon || info.work_type_icon || "",
    workTypeName,
  );
  var $card = $(
    '<article class="task-item-card"><span class="task-item-title"></span></article>',
  );

  $card
    .attr("data-item-id", Number(itemId || 0))
    .attr("data-item-description", "")
    .attr("data-work-item-key", String(info.work_item_key || "").trim())
    .attr("data-work-type-name", workTypeName)
    .attr("data-work-type-icon", workTypeIcon)
    .attr("data-status-column-id", Number(info.column_id || 0))
    .attr("data-status-column-name", String(info.status_name || "").trim());
  $card.find(".task-item-title").text(String(info.title || "").trim());

  return $card;
}

function openReferencedWorkItemModal(itemId, fallbackData) {
  var targetItemId = Number(itemId || 0);
  if (targetItemId <= 0) {
    return;
  }

  var $card = findCardByItemId(targetItemId);
  if (!$card.length) {
    $card = createFallbackWorkItemCard(targetItemId, fallbackData);
  }

  var modal = getItemDetailModalInstance();
  var $modal = $("#taskItemDetailModal");
  if (
    modal &&
    $modal.hasClass("show") &&
    Number(itemDetailModalState.itemId || 0) === targetItemId
  ) {
    if (typeof focusCommentFromHash === "function") {
      focusCommentFromHash();
    }
    return;
  }

  if (!modal || !$modal.hasClass("show")) {
    openItemDetailModal($card);
    return;
  }

  $modal
    .off("hidden.bs.modal.taskOpenRelated")
    .one("hidden.bs.modal.taskOpenRelated", function () {
      openItemDetailModal($card);
    });
  modal.hide();
}

function isTaskItemDetailMobileViewport() {
  return window.matchMedia("(max-width: 767.98px)").matches;
}

function syncTaskItemDetailActivityPlacement() {
  var $section = $("#taskItemActivitySection");
  var $desktopMount = $("#taskItemActivityDesktopMount");
  var $mobileMount = $("#taskItemActivityMobileMount");
  if (!$section.length || !$desktopMount.length || !$mobileMount.length) {
    return;
  }

  var $target = isTaskItemDetailMobileViewport() ? $mobileMount : $desktopMount;
  if (!$section.parent().is($target)) {
    $target.append($section);
  }
}

function syncTaskItemDetailMobileViewportMetrics() {
  var modal = document.getElementById("taskItemDetailModal");
  if (!modal) {
    return;
  }

  var layoutViewportHeight =
    window.innerHeight || document.documentElement.clientHeight || 0;
  var visualViewportHeight = layoutViewportHeight;
  var visualViewportOffsetTop = 0;

  if (window.visualViewport) {
    visualViewportHeight = Math.round(
      Number(window.visualViewport.height || layoutViewportHeight),
    );
    visualViewportOffsetTop = Math.round(
      Number(window.visualViewport.offsetTop || 0),
    );
  }

  var keyboardOffset = Math.max(
    0,
    layoutViewportHeight - visualViewportHeight - visualViewportOffsetTop,
  );

  if (visualViewportHeight > 0) {
    modal.style.setProperty(
      "--task-item-detail-mobile-viewport-height",
      String(visualViewportHeight) + "px",
    );
  }
  modal.style.setProperty(
    "--task-item-detail-mobile-keyboard-offset",
    String(keyboardOffset) + "px",
  );
}

function syncTaskItemDetailMobileLayout() {
  syncTaskItemDetailActivityPlacement();
  syncTaskItemDetailMobileViewportMetrics();
}

window.syncTaskItemDetailMobileLayout = syncTaskItemDetailMobileLayout;

function setTaskItemDetailMobileOverlayState(mode, enabled) {
  var $modal = $("#taskItemDetailModal");
  var normalizedMode = String(mode || "").trim().toLowerCase();
  if (!$modal.length) {
    return;
  }

  if (normalizedMode === "description") {
    $modal.toggleClass("task-item-detail-mobile-description-editing", !!enabled);
    if (enabled) {
      $modal.removeClass("task-item-detail-mobile-comment-editing");
    }
    return;
  }

  if (normalizedMode === "comment") {
    $modal.toggleClass("task-item-detail-mobile-comment-editing", !!enabled);
    if (enabled) {
      $modal.removeClass("task-item-detail-mobile-description-editing");
    }
    return;
  }

  $modal.removeClass(
    "task-item-detail-mobile-description-editing task-item-detail-mobile-comment-editing",
  );

  window.setTimeout(syncTaskItemDetailMobileLayout, 0);
}

window.setTaskItemDetailMobileOverlayState = setTaskItemDetailMobileOverlayState;

$(window).on(
  "resize.taskItemDetailModalLayout orientationchange.taskItemDetailModalLayout",
  function () {
    syncTaskItemDetailMobileLayout();
  },
);

if (window.visualViewport && typeof window.visualViewport.addEventListener === "function") {
  window.visualViewport.addEventListener(
    "resize",
    syncTaskItemDetailMobileLayout,
    { passive: true },
  );
  window.visualViewport.addEventListener(
    "scroll",
    syncTaskItemDetailMobileLayout,
    { passive: true },
  );
}

function isChildWorkItemDoneStatus(columnId, statusName) {
  var normalizedName = String(statusName || "")
    .trim()
    .toLowerCase();
  if (/(^|\b)(done|closed|complete|completed|resolved)(\b|$)/.test(normalizedName)) {
    return true;
  }

  var columns = getBoardStatusColumns();
  if (!columns.length) {
    return false;
  }

  var lastColumnId = Number((columns[columns.length - 1] || {}).id || 0);
  return lastColumnId > 0 && lastColumnId === Number(columnId || 0);
}

function updateChildWorkItemsState(items) {
  var rows = Array.isArray(items) ? items : [];
  var done = rows.filter(function (item) {
    return Number(item && item.is_done ? item.is_done : 0) > 0;
  }).length;

  var editingItemId = Number(itemDetailModalState.childTitleEditingItemId || 0);
  var pickerItemId = Number(itemDetailModalState.childPickerItemId || 0);
  var hasEditingRow = false;
  var hasPickerRow = false;

  for (var index = 0; index < rows.length; index++) {
    var row = rows[index] || {};
    var rowId = Number(row.id || 0);
    if (rowId && rowId === editingItemId) {
      hasEditingRow = true;
    }
    if (rowId && rowId === pickerItemId) {
      hasPickerRow = true;
    }
  }

  if (!hasEditingRow) {
    itemDetailModalState.childTitleEditingItemId = 0;
  }
  if (!hasPickerRow) {
    itemDetailModalState.childPickerItemId = 0;
    itemDetailModalState.childPickerField = "";
  }

  itemDetailModalState.childWorkItems = normalizeChildWorkItems({
    items: rows,
    total: rows.length,
    done: done,
    progress_percent: rows.length ? Math.round((done * 100) / rows.length) : 0,
  });
  renderChildWorkItemsSection();
}

function updateSingleChildWorkItem(itemId, mutator) {
  var targetItemId = Number(itemId || 0);
  if (targetItemId <= 0 || typeof mutator !== "function") {
    return false;
  }

  var childInfo = normalizeChildWorkItems(itemDetailModalState.childWorkItems);
  var rows = Array.isArray(childInfo.items) ? childInfo.items : [];
  var changed = false;
  var nextRows = rows.map(function (item) {
    if (Number(item && item.id ? item.id : 0) !== targetItemId) {
      return item;
    }

    var nextItem = $.extend({}, item);
    mutator(nextItem);
    changed = true;
    return nextItem;
  });

  if (!changed) {
    return false;
  }

  updateChildWorkItemsState(nextRows);
  return true;
}

function childWorkItemById(itemId) {
  var targetItemId = Number(itemId || 0);
  var childInfo = normalizeChildWorkItems(itemDetailModalState.childWorkItems);
  var rows = Array.isArray(childInfo.items) ? childInfo.items : [];

  for (var index = 0; index < rows.length; index++) {
    var item = rows[index] || {};
    if (Number(item.id || 0) === targetItemId) {
      return item;
    }
  }

  return null;
}

function setChildWorkItemTitleEditState(itemId, enabled) {
  var targetItemId = Number(itemId || 0);
  itemDetailModalState.childTitleEditingItemId = enabled ? targetItemId : 0;
  if (enabled) {
    itemDetailModalState.childPickerItemId = 0;
    itemDetailModalState.childPickerField = "";
  }

  renderChildWorkItemsSection();

  if (!enabled || targetItemId <= 0) {
    return;
  }

  window.setTimeout(function () {
    var $input = $(
      '.task-item-child-title-input[data-child-item-id="' + targetItemId + '"]',
    );
    if (!$input.length) {
      return;
    }
    $input.trigger("focus");
    var input = $input.get(0);
    if (input && typeof input.setSelectionRange === "function") {
      var value = String($input.val() || "");
      input.setSelectionRange(value.length, value.length);
    }
  }, 0);
}

function setChildWorkItemPickerState(itemId, fieldName, shouldOpen) {
  var targetItemId = Number(itemId || 0);
  var field = String(fieldName || "").trim().toLowerCase();

  itemDetailModalState.childPickerItemId = targetItemId > 0 ? targetItemId : 0;
  itemDetailModalState.childPickerField = targetItemId > 0 ? field : "";
  if (targetItemId > 0) {
    itemDetailModalState.childTitleEditingItemId = 0;
  }

  renderChildWorkItemsSection();

  if (!shouldOpen || targetItemId <= 0 || !field) {
    return;
  }

  window.setTimeout(function () {
    var $select = $(
      '.task-item-child-picker-select[data-child-item-id="' +
        targetItemId +
        '"][data-child-field="' +
        field +
        '"]',
    );
    if (!$select.length) {
      return;
    }

    var select = $select.get(0);
    $select.trigger("focus");
    if (select && typeof select.showPicker === "function") {
      try {
        select.showPicker();
      } catch (error) {
        // Ignore unsupported picker invocation.
      }
    }
  }, 0);
}

function openChildWorkItemModal(itemId) {
  openReferencedWorkItemModal(itemId, childWorkItemById(itemId) || {});
}

function buildChildDetailUpdatePayload(detail, overrides) {
  var info = detail && typeof detail === "object" ? detail : {};
  var next = overrides && typeof overrides === "object" ? overrides : {};
  return {
    task_action: "update_item_detail",
    item_id: Number(info.id || 0),
    assignee_user_id: Number(
      Object.prototype.hasOwnProperty.call(next, "assignee_user_id")
        ? next.assignee_user_id
        : info.assignee_user_id || 0,
    ),
    reporter_user_id: Number(info.reporter_user_id || 0),
    priority: String(
      Object.prototype.hasOwnProperty.call(next, "priority")
        ? next.priority
        : info.priority || "Medium",
    ),
    original_estimate_value: Number(info.original_estimate_value || 0),
    original_estimate_unit: String(info.original_estimate_unit || "minutes"),
    task_status_label_ids: Array.isArray(info.task_status_label_ids)
      ? info.task_status_label_ids.join(",")
      : String(info.task_status || ""),
    start_date: String(info.start_date || ""),
    due_date: String(info.due_date || ""),
    amendement_date: String(info.amendement_date || ""),
    amendement_time_minutes: Number(info.amendement_time_minutes || 0),
    second_amendement_date: String(info.second_amendement_date || ""),
    second_amendement_time_minutes: Number(
      info.second_amendement_time_minutes || 0,
    ),
  };
}

function updateChildWorkItemCardTitle(itemId, title) {
  var $card = findCardByItemId(itemId);
  if (!$card.length) {
    return;
  }

  $card.find(".task-item-title").text(String(title || ""));
}

function updateChildWorkItemCardAssignee(itemId, userId, userName) {
  var $card = findCardByItemId(itemId);
  if (!$card.length) {
    return;
  }

  var assigneeUserId = Number(userId || 0);
  var assigneeName = String(userName || "").trim();
  var $btn = $card.find(".task-item-assignee-btn");

  $card
    .attr("data-assignee-user-id", assigneeUserId)
    .attr("data-assignee-name", assigneeName);

  if ($btn.length) {
    $btn
      .attr("data-user-id", assigneeUserId)
      .attr("title", assigneeName)
      .toggleClass("task-assignee-pill-unassigned", assigneeUserId <= 0)
      .html(assigneeButtonInner(assigneeUserId, assigneeName));
  }
}

function resolveBoardStatusColumnName(columnId) {
  var targetColumnId = Number(columnId || 0);
  if (targetColumnId <= 0) {
    return "";
  }

  var columns = getBoardStatusColumns();
  for (var index = 0; index < columns.length; index++) {
    var column = columns[index] || {};
    if (Number(column.id || 0) === targetColumnId) {
      return String(column.name || "").trim();
    }
  }

  columns = Array.isArray(state.columns) ? state.columns : [];
  for (var i = 0; i < columns.length; i++) {
    var item = columns[i] || {};
    if (Number(item.id || 0) === targetColumnId) {
      return String(item.name || "").trim();
    }
  }

  return "";
}

function insertWorkItemCardIntoBoard(item) {
  var info = item && typeof item === "object" ? item : {};
  var itemId = Number(info.id || 0);
  if (itemId <= 0) {
    return $();
  }

  var $existingCard = findCardByItemId(itemId);
  if ($existingCard.length) {
    return $existingCard;
  }

  var $newCard = $(buildTaskCardHtml(info));
  var columnId = Number(
    info.column_id || info.columnId || info.status_column_id || 0,
  );
  var columnName =
    String(info.column_name || info.status_name || "").trim() ||
    resolveBoardStatusColumnName(columnId);

  setCardStatusColumn($newCard, columnId, columnName);

  if (isBoardGroupedByStatus()) {
    var $column = $app.find(
      '.task-column[data-column-id="' + columnId + '"]',
    ).first();
    if ($column.length) {
      $column.find(".task-item-list").append($newCard);
      applyBoardViewSettingsToCard($newCard);
      scrollColumnItemListToBottom($column);
      updateColumnCount($column);
      applyBoardFilters();
      refreshCardItemKeys();
      return $newCard;
    }
  }

  var $fallbackList = $("#taskBoardGrid .task-item-list").first();
  if ($fallbackList.length) {
    $fallbackList.append($newCard);
  } else {
    $("#taskBoardGrid").append($newCard);
  }

  renderBoardGroupingLayout();
  applyBoardFilters();
  refreshCardItemKeys();
  return findCardByItemId(itemId);
}

function parseWorkItemReferenceFromText(value) {
  var text = String(value || "").trim();
  var result = {
    itemId: 0,
    key: "",
  };
  var match = null;

  if (!text) {
    return result;
  }

  match = text.match(/(?:^|[^0-9])task-item-(\d+)(?:$|[^0-9])/i);
  if (match) {
    result.itemId = Number(match[1] || 0);
  }

  if (!result.itemId) {
    match = text.match(/(?:^|[?&#])(item_id|id)=(\d+)(?:$|[&#])/i);
    if (match) {
      result.itemId = Number(match[2] || 0);
    }
  }

  match = text.match(/([A-Z][A-Z0-9\-]{0,19}-\d+)/i);
  if (match) {
    result.key = String(match[1] || "")
      .trim()
      .replace(/\s+/g, "")
      .toUpperCase();
    if (!result.itemId) {
      var idMatch = result.key.match(/-(\d+)$/);
      if (idMatch) {
        result.itemId = Number(idMatch[1] || 0);
      }
    }
  }

  if (!result.itemId && /^\d+$/.test(text)) {
    result.itemId = Number(text || 0);
  }

  return result;
}

function buildBoardSearchItems() {
  var items = [];

  $app.find(".task-item-card").each(function () {
    var $card = $(this);
    var itemId = Number($card.data("itemId") || 0);
    if (itemId <= 0) {
      return;
    }

    items.push({
      id: itemId,
      title: String($card.find(".task-item-title").text() || "").trim(),
      work_item_key: String(
        $card.attr("data-work-item-key") || buildWorkItemKey(itemId),
      ).trim(),
      work_type_name: String($card.attr("data-work-type-name") || "Task").trim(),
      work_type_svg_icon: String($card.attr("data-work-type-icon") || "").trim(),
      column_id: Number($card.attr("data-status-column-id") || 0),
      status_name: String($card.attr("data-status-column-name") || "").trim(),
      status_color: "",
      assignee_user_id: Number($card.attr("data-assignee-user-id") || 0),
      assignee_name: String($card.attr("data-assignee-name") || "").trim(),
      parent_item_id: Number($card.attr("data-parent-item-id") || 0),
    });
  });

  return items;
}

function buildBoardSearchItemMap() {
  var items = buildBoardSearchItems();
  var map = {};

  for (var index = 0; index < items.length; index++) {
    var item = items[index] || {};
    var itemId = Number(item.id || 0);
    if (itemId > 0) {
      map[itemId] = item;
    }
  }

  return map;
}

function isParentTypeName(name) {
  return String(name || "")
    .trim()
    .toLowerCase() === "epic";
}

function wouldCreateParentChildCycleLocal(parentItemId, childItemId, itemMap) {
  var targetParentId = Number(parentItemId || 0);
  var targetChildId = Number(childItemId || 0);
  var visited = {};

  if (targetParentId <= 0 || targetChildId <= 0) {
    return false;
  }
  if (targetParentId === targetChildId) {
    return true;
  }

  while (targetParentId > 0) {
    if (targetParentId === targetChildId) {
      return true;
    }
    if (visited[targetParentId]) {
      break;
    }
    visited[targetParentId] = true;

    var row = itemMap[targetParentId] || {};
    targetParentId = Number(row.parent_item_id || 0);
  }

  return false;
}

function findLocalBoardItemReference(value, itemMap) {
  var reference = parseWorkItemReferenceFromText(value);
  var result = null;
  var key = String(reference.key || "").trim();
  var itemId = Number(reference.itemId || 0);
  var keys = Object.keys(itemMap || {});

  if (itemId > 0 && itemMap && itemMap[itemId]) {
    return itemMap[itemId];
  }

  if (!key) {
    return null;
  }

  for (var index = 0; index < keys.length; index++) {
    var row = itemMap[keys[index]] || {};
    if (
      String(row.work_item_key || "")
        .trim()
        .toUpperCase() === key
    ) {
      result = row;
      break;
    }
  }

  return result;
}

function localSearchChildWorkItems(parentItemId, keyword) {
  var normalizedKeyword = String(keyword || "").trim();
  var itemMap = buildBoardSearchItemMap();
  var excludedIds = {};
  var results = [];
  var keys = Object.keys(itemMap);
  var resolvedItem = null;
  var searchText = normalizedKeyword.toLowerCase();
  var childInfo = normalizeChildWorkItems(itemDetailModalState.childWorkItems);
  var childRows = Array.isArray(childInfo.items) ? childInfo.items : [];

  excludedIds[Number(parentItemId || 0)] = true;
  for (var index = 0; index < childRows.length; index++) {
    var childRow = childRows[index] || {};
    var childId = Number(childRow.id || 0);
    if (childId > 0) {
      excludedIds[childId] = true;
    }
  }

  resolvedItem = findLocalBoardItemReference(normalizedKeyword, itemMap);
  if (
    resolvedItem &&
    !excludedIds[Number(resolvedItem.id || 0)] &&
    !isParentTypeName(resolvedItem.work_type_name) &&
    !wouldCreateParentChildCycleLocal(
      parentItemId,
      resolvedItem.id,
      itemMap,
    )
  ) {
    return [resolvedItem];
  }

  for (var itemIndex = 0; itemIndex < keys.length; itemIndex++) {
    var item = itemMap[keys[itemIndex]] || {};
    var itemId = Number(item.id || 0);
    var haystack = (
      String(item.work_item_key || "") +
      " " +
      String(item.title || "")
    )
      .trim()
      .toLowerCase();

    if (
      itemId <= 0 ||
      excludedIds[itemId] ||
      isParentTypeName(item.work_type_name) ||
      wouldCreateParentChildCycleLocal(parentItemId, itemId, itemMap) ||
      (searchText && haystack.indexOf(searchText) === -1)
    ) {
      continue;
    }

    results.push(item);
  }

  results.sort(function (left, right) {
    return Number(right.id || 0) - Number(left.id || 0);
  });

  return results.slice(0, 20);
}

function localSearchLinkedWorkItems(currentItemId, keyword) {
  var normalizedKeyword = String(keyword || "").trim();
  var itemMap = buildBoardSearchItemMap();
  var results = [];
  var keys = Object.keys(itemMap);
  var resolvedItem = findLocalBoardItemReference(normalizedKeyword, itemMap);
  var searchText = normalizedKeyword.toLowerCase();

  if (resolvedItem && Number(resolvedItem.id || 0) !== Number(currentItemId || 0)) {
    return [resolvedItem];
  }

  for (var index = 0; index < keys.length; index++) {
    var item = itemMap[keys[index]] || {};
    var itemId = Number(item.id || 0);
    var haystack = (
      String(item.work_item_key || "") +
      " " +
      String(item.title || "")
    )
      .trim()
      .toLowerCase();

    if (
      itemId <= 0 ||
      itemId === Number(currentItemId || 0) ||
      (searchText && haystack.indexOf(searchText) === -1)
    ) {
      continue;
    }

    results.push(item);
  }

  results.sort(function (left, right) {
    return Number(right.id || 0) - Number(left.id || 0);
  });

  return results.slice(0, 20);
}

function applyCurrentItemDetailResponse(res) {
  var itemId = Number(itemDetailModalState.itemId || 0);
  var shouldRenderActivity = false;
  if (!itemId) {
    return;
  }

  if (Array.isArray(res && res.worklogs)) {
    itemDetailModalState.worklogs = res.worklogs.slice();
    itemDetailModalState.worklogsLoading = false;
    if (typeof nextItemDetailWorklogRequestSeq === "function") {
      nextItemDetailWorklogRequestSeq();
    }
    shouldRenderActivity = true;
  }
  if (Array.isArray(res && res.history)) {
    itemDetailModalState.history = res.history.slice();
    if (typeof nextItemDetailHistoryRequestSeq === "function") {
      nextItemDetailHistoryRequestSeq();
    }
    shouldRenderActivity = true;
  }

  if (shouldRenderActivity && typeof renderItemHistoryPanels === "function") {
    renderItemHistoryPanels();
  }

  if (res && res.detail && typeof res.detail === "object") {
    applyItemDetailToModal(
      res.detail,
      Array.isArray(state.statusLabels) ? state.statusLabels : [],
      Array.isArray(itemDetailModalState.parentOptions)
        ? itemDetailModalState.parentOptions
        : [],
      Array.isArray(itemDetailModalState.webLinks)
        ? itemDetailModalState.webLinks
        : [],
      res.itemLinks && typeof res.itemLinks === "object"
        ? res.itemLinks
        : itemDetailModalState.itemLinks,
    );
    return;
  }

  loadItemDetail(itemId);
}

function clearChildCreateSearchTimer() {
  if (Number(itemDetailModalState.childCreateSearchTimer || 0) > 0) {
    window.clearTimeout(itemDetailModalState.childCreateSearchTimer);
    itemDetailModalState.childCreateSearchTimer = 0;
  }
}

function clearLinkSearchTimer() {
  if (Number(itemDetailModalState.linkSearchTimer || 0) > 0) {
    window.clearTimeout(itemDetailModalState.linkSearchTimer);
    itemDetailModalState.linkSearchTimer = 0;
  }
}

function resetChildCreateComposer(closePanel) {
  clearChildCreateSearchTimer();
  itemDetailModalState.childCreateSelectedItemId = 0;
  itemDetailModalState.childCreateSearchResults = [];
  itemDetailModalState.childCreateMode = "create";
  if (closePanel) {
    itemDetailModalState.childCreatePanelOpen = false;
  }

  $("#taskItemChildCreateInput").val("");
  $("#taskItemChildCreateWorkTypeSelect").val("");
  renderChildCreatePanel();
}

function openChildCreateComposer(mode) {
  var nextMode =
    String(mode || "").trim().toLowerCase() === "search" ? "search" : "create";

  itemDetailModalState.childCreatePanelOpen = true;
  itemDetailModalState.childCreateMode = nextMode;
  itemDetailModalState.childCreateSelectedItemId = 0;
  itemDetailModalState.childCreateSearchResults = [];
  itemDetailModalState.linkEditorOpen = false;
  clearChildCreateSearchTimer();
  clearLinkSearchTimer();
  $("#taskItemChildCreateInput").val("");
  if (nextMode === "create") {
    $("#taskItemChildCreateWorkTypeSelect").val("");
  }
  $("#taskItemLinkSearchInput").val("");
  itemDetailModalState.linkSelectedItemId = 0;
  itemDetailModalState.linkSearchResults = [];
  renderLinkedWorkItemsSection();
  renderChildCreatePanel();

  window.setTimeout(function () {
    $("#taskItemChildCreateInput").trigger("focus");
  }, 40);
}

function searchChildWorkItems(keyword) {
  var parentItemId = Number(itemDetailModalState.itemId || 0);
  var requestKeyword = String(keyword || "").trim();
  if (
    parentItemId <= 0 ||
    itemDetailModalState.childCreateMode !== "search"
  ) {
    return;
  }

  if (!requestKeyword) {
    itemDetailModalState.childCreateSearchResults = [];
    itemDetailModalState.childCreateSelectedItemId = 0;
    renderChildCreatePanel();
    return;
  }

  itemDetailModalState.childCreateSearchResults = localSearchChildWorkItems(
    parentItemId,
    requestKeyword,
  );
  var selectedItemId = Number(itemDetailModalState.childCreateSelectedItemId || 0);
  if (selectedItemId > 0) {
    var hasSelected = itemDetailModalState.childCreateSearchResults.some(function (
      item,
    ) {
      return Number(item && item.id ? item.id : 0) === selectedItemId;
    });
    if (!hasSelected) {
      itemDetailModalState.childCreateSelectedItemId = 0;
    }
  }
  renderChildCreatePanel();
}

function queueChildWorkItemSearch() {
  clearChildCreateSearchTimer();
  if (itemDetailModalState.childCreateMode !== "search") {
    return;
  }

  var keyword = String($("#taskItemChildCreateInput").val() || "").trim();
  if (!keyword) {
    itemDetailModalState.childCreateSearchResults = [];
    renderChildCreatePanel();
    return;
  }

  itemDetailModalState.childCreateSearchTimer = window.setTimeout(function () {
    itemDetailModalState.childCreateSearchTimer = 0;
    searchChildWorkItems($("#taskItemChildCreateInput").val() || "");
  }, 220);
}

function submitChildWorkItemCreate() {
  var parentItemId = Number(itemDetailModalState.itemId || 0);
  var isSearchMode = itemDetailModalState.childCreateMode === "search";
  var inputValue = String($("#taskItemChildCreateInput").val() || "").trim();
  if (parentItemId <= 0) {
    return;
  }
  if (!canEdit) {
    notify("You do not have permission to update work item.");
    return;
  }
  if (!inputValue) {
    notify(
      isSearchMode
        ? "Please enter or search a work item."
        : "Work item title is required.",
    );
    return;
  }
  if (!isSearchMode && !canAdd) {
    notify("You do not have permission to create work items.");
    return;
  }

  var payload = isSearchMode
    ? {
        task_action: "link_existing_child_work_item",
        parent_item_id: parentItemId,
        child_item_id: Number(itemDetailModalState.childCreateSelectedItemId || 0),
        child_value: inputValue,
      }
    : {
        task_action: "create_child_work_item",
        parent_item_id: parentItemId,
        title: inputValue,
        work_type_id: Number($("#taskItemChildCreateWorkTypeSelect").val() || 0),
      };

  $("#taskItemChildCreateSubmitBtn").prop("disabled", true);
  postAction(
    payload,
    function (res) {
      if (res && res.item && typeof res.item === "object") {
        insertWorkItemCardIntoBoard(res.item);
      }
      resetChildCreateComposer(true);
      applyCurrentItemDetailResponse(res);
      loadItemHistory(parentItemId);
    },
    function () {
      renderChildCreatePanel();
    },
  );
}

function resetLinkWorkItemEditor(closeEditor) {
  clearLinkSearchTimer();
  itemDetailModalState.linkSelectedItemId = 0;
  itemDetailModalState.linkSearchResults = [];
  if (closeEditor) {
    itemDetailModalState.linkEditorOpen = false;
  }
  itemDetailModalState.linkRelationType = "";
  $("#taskItemLinkSearchInput").val("");
  $("#taskItemLinkRelationTypeSelect").val("");
  renderLinkedWorkItemsSection();
}

function openLinkWorkItemEditor(relationType) {
  itemDetailModalState.linkEditorOpen = true;
  itemDetailModalState.linkSelectedItemId = 0;
  itemDetailModalState.linkSearchResults = [];
  itemDetailModalState.childCreatePanelOpen = false;
  itemDetailModalState.childCreateMode = "create";
  itemDetailModalState.childCreateSelectedItemId = 0;
  itemDetailModalState.childCreateSearchResults = [];
  clearChildCreateSearchTimer();
  clearLinkSearchTimer();

  $("#taskItemChildCreateInput").val("");
  $("#taskItemLinkSearchInput").val("");
  if (String(relationType || "").trim()) {
    itemDetailModalState.linkRelationType = String(relationType || "").trim();
  }

  renderChildCreatePanel();
  renderLinkedWorkItemsSection();

  window.setTimeout(function () {
    $("#taskItemLinkSearchInput").trigger("focus");
  }, 40);
}

function searchLinkedWorkItems(keyword) {
  var itemId = Number(itemDetailModalState.itemId || 0);
  var requestKeyword = String(keyword || "").trim();
  if (itemId <= 0 || !itemDetailModalState.linkEditorOpen) {
    return;
  }

  if (!requestKeyword) {
    itemDetailModalState.linkSearchResults = [];
    itemDetailModalState.linkSelectedItemId = 0;
    renderLinkedWorkItemsSection();
    return;
  }

  itemDetailModalState.linkSearchResults = localSearchLinkedWorkItems(
    itemId,
    requestKeyword,
  );
  var selectedItemId = Number(itemDetailModalState.linkSelectedItemId || 0);
  if (selectedItemId > 0) {
    var hasSelected = itemDetailModalState.linkSearchResults.some(function (item) {
      return Number(item && item.id ? item.id : 0) === selectedItemId;
    });
    if (!hasSelected) {
      itemDetailModalState.linkSelectedItemId = 0;
    }
  }
  renderLinkedWorkItemsSection();
}

function queueLinkedWorkItemSearch() {
  clearLinkSearchTimer();
  if (!itemDetailModalState.linkEditorOpen) {
    return;
  }

  var keyword = String($("#taskItemLinkSearchInput").val() || "").trim();
  if (!keyword) {
    itemDetailModalState.linkSearchResults = [];
    renderLinkedWorkItemsSection();
    return;
  }

  itemDetailModalState.linkSearchTimer = window.setTimeout(function () {
    itemDetailModalState.linkSearchTimer = 0;
    searchLinkedWorkItems($("#taskItemLinkSearchInput").val() || "");
  }, 220);
}

function linkedWorkItemById(itemId) {
  var targetItemId = Number(itemId || 0);
  if (targetItemId <= 0) {
    return null;
  }

  var groups = normalizeItemLinks(itemDetailModalState.itemLinks).groups;
  for (var groupIndex = 0; groupIndex < groups.length; groupIndex++) {
    var group = groups[groupIndex] || {};
    var items = Array.isArray(group.items) ? group.items : [];
    for (var itemIndex = 0; itemIndex < items.length; itemIndex++) {
      var item = items[itemIndex] || {};
      if (Number(item.id || 0) === targetItemId) {
        return item;
      }
    }
  }

  return null;
}

function submitLinkedWorkItem() {
  var itemId = Number(itemDetailModalState.itemId || 0);
  var relationType = String(itemDetailModalState.linkRelationType || "").trim();
  var inputValue = String($("#taskItemLinkSearchInput").val() || "").trim();
  if (itemId <= 0) {
    return;
  }
  if (!canEdit) {
    notify("You do not have permission to add linked work items.");
    return;
  }
  if (!relationType) {
    notify("Please select a link type.");
    return;
  }
  if (!inputValue) {
    notify("Please enter or search a work item.");
    return;
  }

  $("#taskItemLinkSaveBtn").prop("disabled", true);
  postAction(
    {
      task_action: "create_item_link",
      item_id: itemId,
      relation_type: relationType,
      target_item_id: Number(itemDetailModalState.linkSelectedItemId || 0),
      target_value: inputValue,
    },
    function (res) {
      resetLinkWorkItemEditor(true);
      applyCurrentItemDetailResponse(res);
      loadItemHistory(itemId);
    },
    function () {
      renderLinkedWorkItemsSection();
    },
  );
}

function commitChildWorkItemTitle($input, options) {
  if (!canEdit || !$input || !$input.length) {
    return;
  }

  var settings = options && typeof options === "object" ? options : {};

  var itemId = Number($input.data("childItemId") || 0);
  var row = childWorkItemById(itemId);
  var previousTitle = String(row && row.title ? row.title : "").trim();
  var nextTitle = String($input.val() || "").trim();

  if (!itemId) {
    return;
  }

  if (!nextTitle) {
    notify("Work item title is required.");
    $input.val(previousTitle);
    return;
  }

  if (nextTitle === previousTitle) {
    if (settings.closeOnNoChange) {
      setChildWorkItemTitleEditState(itemId, false);
    }
    return;
  }

  $input.prop("disabled", true);
  postAction(
    {
      task_action: "update_item_core",
      item_id: itemId,
      title: nextTitle,
    },
    function () {
      updateSingleChildWorkItem(itemId, function (item) {
        item.title = nextTitle;
      });
      updateChildWorkItemCardTitle(itemId, nextTitle);
      setChildWorkItemTitleEditState(itemId, false);
    },
    function () {
      $input.val(previousTitle);
      $input.prop("disabled", false);
    },
  );
}

function saveChildWorkItemPriority($select) {
  if (!canEdit || !$select || !$select.length) {
    return;
  }

  var itemId = Number($select.data("childItemId") || 0);
  var row = childWorkItemById(itemId);
  var previousPriority = String(row && row.priority ? row.priority : "Medium");
  var nextPriority = String($select.val() || "Medium").trim() || "Medium";

  if (!itemId || nextPriority === previousPriority) {
    return;
  }

  $select.prop("disabled", true);
  postAction(
    {
      task_action: "get_item_detail",
      item_id: itemId,
    },
    function (detailRes) {
      var detail = detailRes && detailRes.detail ? detailRes.detail : {};
      postAction(
        buildChildDetailUpdatePayload(detail, {
          priority: nextPriority,
        }),
        function (res) {
          setChildWorkItemPickerState(0, "", false);
          updateSingleChildWorkItem(itemId, function (item) {
            item.priority = nextPriority;
          });
          if (res && res.detail && typeof updateCardFromDetail === "function") {
            updateCardFromDetail(res.detail);
          }
        },
        function () {
          setChildWorkItemPickerState(0, "", false);
          $select.val(previousPriority).prop("disabled", false);
        },
      );
    },
    function () {
      setChildWorkItemPickerState(0, "", false);
      $select.val(previousPriority).prop("disabled", false);
    },
  );
}

function saveChildWorkItemAssignee($select) {
  if (!canEdit || !$select || !$select.length) {
    return;
  }

  var itemId = Number($select.data("childItemId") || 0);
  var row = childWorkItemById(itemId);
  var previousUserId = Number(row && row.assignee_user_id ? row.assignee_user_id : 0);
  var nextUserId = Number($select.val() || 0);

  if (!itemId || nextUserId === previousUserId) {
    return;
  }

  var previousName = String(row && row.assignee_name ? row.assignee_name : "").trim();
  $select.prop("disabled", true);
  postAction(
    {
      task_action: "set_item_assignee",
      item_id: itemId,
      assignee_user_id: nextUserId,
    },
    function (res) {
      setChildWorkItemPickerState(0, "", false);
      var assignee = res && res.assignee ? res.assignee : {};
      var resolvedUserId = Number(assignee.user_id || nextUserId || 0);
      var resolvedName =
        String(assignee.name || "").trim() || (resolvedUserId > 0 ? String($select.find("option:selected").text() || "").trim() : "");

      updateSingleChildWorkItem(itemId, function (item) {
        item.assignee_user_id = resolvedUserId;
        item.assignee_name = resolvedName;
      });
      updateChildWorkItemCardAssignee(itemId, resolvedUserId, resolvedName);
    },
    function () {
      setChildWorkItemPickerState(0, "", false);
      $select.val(String(previousUserId)).prop("disabled", false);
      updateSingleChildWorkItem(itemId, function (item) {
        item.assignee_user_id = previousUserId;
        item.assignee_name = previousName;
      });
    },
  );
}

function saveChildWorkItemStatus($select) {
  if (!canEdit || !$select || !$select.length) {
    return;
  }

  var itemId = Number($select.data("childItemId") || 0);
  var row = childWorkItemById(itemId);
  var previousColumnId = Number(row && row.column_id ? row.column_id : 0);
  var nextColumnId = Number($select.val() || 0);
  var nextStatusName = String($select.find("option:selected").text() || "").trim();

  if (!itemId || !nextColumnId || nextColumnId === previousColumnId) {
    return;
  }

  $select.prop("disabled", true);
  postAction(
    {
      task_action: "change_item_status",
      item_id: itemId,
      target_column_id: nextColumnId,
    },
    function () {
      setChildWorkItemPickerState(0, "", false);
      updateSingleChildWorkItem(itemId, function (item) {
        item.column_id = nextColumnId;
        item.status_name = nextStatusName;
        item.is_done = isChildWorkItemDoneStatus(nextColumnId, nextStatusName)
          ? 1
          : 0;
      });

      var $card = findCardByItemId(itemId);
      if ($card.length) {
        applyStatusChangeToBoard(
          $card,
          $card.closest(".task-column"),
          nextColumnId,
          false,
        );
      }
    },
    function () {
      setChildWorkItemPickerState(0, "", false);
      $select.val(String(previousColumnId)).prop("disabled", false);
    },
  );
}

function parseTaskItemPermalinkHash() {
  var hash = String(window.location.hash || "").trim();
  var matched = hash.match(/^#task-item-(\d+)(?:-(?:comment|reply)-\d+)?$/i);
  if (!matched) {
    return null;
  }

  return {
    itemId: Number(matched[1] || 0),
  };
}

function openWorkItemFromPermalinkHash() {
  var target = parseTaskItemPermalinkHash();
  if (!target || Number(target.itemId || 0) <= 0) {
    return;
  }

  var activeItemId = Number(itemDetailModalState.itemId || 0);
  if (
    $("#taskItemDetailModal").hasClass("show") &&
    activeItemId > 0 &&
    activeItemId === Number(target.itemId || 0)
  ) {
    if (typeof focusCommentFromHash === "function") {
      focusCommentFromHash();
    }
    return;
  }

  openReferencedWorkItemModal(target.itemId, {
    work_item_key: buildWorkItemKey(target.itemId),
  });
}

window.setTimeout(function () {
  openWorkItemFromPermalinkHash();
  openWorkItemFromSelectedIssue();
}, 150);

$(window).on("hashchange", function () {
  openWorkItemFromPermalinkHash();
  if (typeof focusCommentFromHash === "function") {
    window.setTimeout(focusCommentFromHash, 0);
  }
});

$app.on("click", ".task-item-card", function (e) {
  if (
    $(e.target).closest(
      ".task-item-menu-dropdown, .task-item-assignee-wrap, .task-assignee-menu, .task-inline-label-panel, .task-item-submenu-list, .task-item-submenu-toggle, .task-item-action, .task-item-parent-select, .task-item-type-wrap, .task-item-work-type-menu, .dropdown-menu",
    ).length
  ) {
    return;
  }

  openItemDetailModal($(this));
});

$(document).on("click", ".task-open-composer-btn", function (e) {
  e.preventDefault();
  var $column = $(this).closest(".task-column");
  openComposerForColumn($column);
});

$(document).on("click", ".task-item-title, .task-item-key", function (e) {
  var $card = $(this).closest(".task-item-card");
  if (!$card.length) {
    return;
  }
  e.preventDefault();
  e.stopPropagation();
  openItemDetailModal($card);
});

$(document).on("click", ".task-item-edit-btn", function(e) {
  e.preventDefault();
  e.stopPropagation();
  if (!canEdit) {
    notify("You do not have permission to update work item.");
    return;
  }
  var $card = $(this).closest(".task-item-card");
  var $title = $card.find(".task-item-title");
  
  if ($card.find(".task-item-inline-edit").length > 0) {
    return;
  }
  
  var currentTitle = $title.text();
  $title.hide();
  
  var $editWrap = $('<div class="task-item-inline-edit mt-1 mb-2 d-flex align-items-center gap-1" style="flex: 1; min-width: 0;"></div>');
  var $input = $('<input type="text" class="form-control form-control-sm" maxlength="255">').val(currentTitle);
  var $actions = $('<div class="d-flex gap-1"></div>');
  var $saveBtn = $('<button class="btn btn-light border btn-sm" type="button" aria-label="Save title" title="Save title" style="width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center;"><i class="fa-solid fa-check" aria-hidden="true"></i></button>');
  var $cancelBtn = $('<button class="btn btn-light border btn-sm" type="button" aria-label="Cancel editing" title="Cancel editing" style="width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center;"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>');
  
  $actions.append($saveBtn, $cancelBtn);
  $editWrap.append($input, $actions);
  $title.after($editWrap);
  
  $editWrap.on("click", function(e) {
    e.stopPropagation();
  });
  
  $input.trigger("focus");
  
  var closeEdit = function() {
    $editWrap.remove();
    $title.show();
  };
  
  $cancelBtn.on("click", function(e) {
    e.preventDefault();
    e.stopPropagation();
    closeEdit();
  });
  
  $saveBtn.on("click", function(e) {
    e.preventDefault();
    e.stopPropagation();
    var newTitle = $input.val().trim();
    if (!newTitle) {
      if (typeof window.notify === "function") {
        window.notify("Title cannot be empty.");
      }
      $input.trigger("focus");
      return;
    }
    
    var itemId = $card.data("item-id");
    if (!itemId) {
      closeEdit();
      return;
    }
    
    $saveBtn.prop("disabled", true);
    $input.prop("disabled", true);
    
    if (typeof window.postAction === "function") {
      window.postAction({
        task_action: "update_item_core",
        item_id: itemId,
        title: newTitle
      }, function() {
        $title.text(newTitle);
        closeEdit();
      }, function() {
        $saveBtn.prop("disabled", false);
        $input.prop("disabled", false);
        $input.trigger("focus");
      });
    } else {
      $title.text(newTitle);
      closeEdit();
    }
  });
  
  $input.on("keydown", function(e) {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      $saveBtn.trigger("click");
    } else if (e.key === "Escape") {
      e.preventDefault();
      closeEdit();
    }
  });
});

$(document).on("click", "#taskItemDetailDescriptionView", function (e) {
  if ($(e.target).closest("a").length) {
    return;
  }
  if (
    !$("#taskItemDetailDescriptionDraftNotice").hasClass("d-none") &&
    window.taskBoardDescriptionDraft &&
    typeof window.taskBoardDescriptionDraft.restore === "function"
  ) {
    window.taskBoardDescriptionDraft.restore();
    return;
  }
  setItemDetailDescriptionEditMode(true, {
    focus: true,
  });
});

$(document).on("keydown", "#taskItemDetailDescriptionView", function (e) {
  if ($(e.target).closest("a").length) {
    return;
  }
  var key = String(e.key || "").toLowerCase();
  if (key === "enter" || key === " ") {
    e.preventDefault();
    if (
      !$("#taskItemDetailDescriptionDraftNotice").hasClass("d-none") &&
      window.taskBoardDescriptionDraft &&
      typeof window.taskBoardDescriptionDraft.restore === "function"
    ) {
      window.taskBoardDescriptionDraft.restore();
      return;
    }
    setItemDetailDescriptionEditMode(true, {
      focus: true,
    });
  }
});


$(document).on("click", "#taskItemDetailDescriptionSaveBtn", function () {
  saveItemCoreFromModal(false, {
    suppressNoChangeMessage: false,
    exitDescriptionEditModeOnSuccess: true,
    exitDescriptionEditModeOnNoChange: true,
    clearDescriptionDraftOnSuccess: true,
  });
});

$(document).on("click", "#taskItemDetailDescriptionCancelBtn", function () {
  var originalDescription = String(
    itemDetailModalState.initialDescription || ""
  );
  writeDescriptionToInputField(originalDescription);
  if (typeof window.setDescriptionEditorContent === "function") {
    window.setDescriptionEditorContent(originalDescription);
  }
  if (
    window.taskBoardDescriptionDraft &&
    typeof window.taskBoardDescriptionDraft.clear === "function"
  ) {
    window.taskBoardDescriptionDraft.clear();
  }
  if (typeof window.clearDescriptionUploadedAttachmentPaths === "function") {
    window.clearDescriptionUploadedAttachmentPaths();
  }
  setItemDetailDescriptionEditMode(false, {
    skipPreviewUpdate: false,
  });
});

$(document).on("click", "#taskItemDetailDescriptionCollapseBtn", function () {
  setDescriptionPanelCollapsed(!itemDetailModalState.descriptionCollapsed);
});

$(document).on("click", "#taskItemDetailCopyUrlBtn", function () {
  copyCurrentWorkItemUrl();
});

$(document).on(
  "click",
  "#taskItemDetailCreateChildAction, #taskItemChildWorkItemsAddBtn",
  function (e) {
  e.preventDefault();
  if (!canEdit) {
    notify("You do not have permission to update work item.");
    return;
  }

  hideItemDetailAddDropdown();
  openChildCreateComposer("create");
  },
);

$(document).on("click", "#taskItemChildCreateChooseExistingBtn", function () {
  if (!canEdit) {
    notify("You do not have permission to update work item.");
    return;
  }

  openChildCreateComposer(
    itemDetailModalState.childCreateMode === "search" ? "create" : "search",
  );
});

$(document).on("click", "#taskItemChildCreateCancelBtn", function () {
  resetChildCreateComposer(true);
});

$(document).on("click", "#taskItemChildCreateSubmitBtn", function () {
  submitChildWorkItemCreate();
});

$(document).on("input", "#taskItemChildCreateInput", function () {
  itemDetailModalState.childCreateSelectedItemId = 0;
  renderChildCreatePanel();
  queueChildWorkItemSearch();
});

$(document).on("change", "#taskItemChildCreateWorkTypeSelect", function () {
  renderChildCreatePanel();
});

$(document).on("keydown", "#taskItemChildCreateInput", function (e) {
  if (e.key === "Enter") {
    e.preventDefault();
    submitChildWorkItemCreate();
    return;
  }

  if (e.key === "Escape") {
    e.preventDefault();
    resetChildCreateComposer(true);
  }
});

$(document).on("click", ".task-item-search-result-child", function (e) {
  e.preventDefault();
  e.stopPropagation();

  var resultItemId = Number($(this).attr("data-result-item-id") || 0);
  var results = normalizeItemSearchResults(
    itemDetailModalState.childCreateSearchResults,
  );

  itemDetailModalState.childCreateSelectedItemId = resultItemId;
  for (var index = 0; index < results.length; index++) {
    var result = results[index] || {};
    if (Number(result.id || 0) !== resultItemId) {
      continue;
    }

    $("#taskItemChildCreateInput").val(
      $.trim(
        (result.work_item_key ? result.work_item_key + " " : "") +
          String(result.title || ""),
      ),
    );
    break;
  }

  renderChildCreatePanel();
});

$(document).on("click", ".task-item-child-open-trigger", function (e) {
  e.preventDefault();
  e.stopPropagation();
  openChildWorkItemModal($(this).data("childItemId"));
});

$(document).on("click", ".task-item-child-title-edit-btn", function (e) {
  e.preventDefault();
  e.stopPropagation();
  setChildWorkItemTitleEditState($(this).data("childItemId"), true);
});

$(document).on("click", ".task-item-child-title-save-btn", function (e) {
  e.preventDefault();
  e.stopPropagation();
  var itemId = Number($(this).data("childItemId") || 0);
  var $input = $(
    '.task-item-child-title-input[data-child-item-id="' + itemId + '"]',
  );
  commitChildWorkItemTitle($input, {
    closeOnNoChange: true,
  });
});

$(document).on("click", ".task-item-child-title-cancel-btn", function (e) {
  e.preventDefault();
  e.stopPropagation();
  setChildWorkItemTitleEditState($(this).data("childItemId"), false);
});

$(document).on("click", ".task-item-child-picker-trigger", function (e) {
  e.preventDefault();
  e.stopPropagation();
  setChildWorkItemPickerState(
    $(this).data("childItemId"),
    $(this).data("childField"),
    true,
  );
});

$(document).on("keydown", ".task-item-child-title-input", function (e) {
  if (e.key === "Enter") {
    e.preventDefault();
    commitChildWorkItemTitle($(this), {
      closeOnNoChange: true,
    });
    return;
  }

  if (e.key === "Escape") {
    e.preventDefault();
    setChildWorkItemTitleEditState($(this).data("childItemId"), false);
  }
});

$(document).on("blur", ".task-item-child-picker-select", function () {
  var $select = $(this);
  var itemId = Number($select.data("childItemId") || 0);
  var field = String($select.data("childField") || "").trim();

  window.setTimeout(function () {
    if (
      Number(itemDetailModalState.childPickerItemId || 0) !== itemId ||
      String(itemDetailModalState.childPickerField || "").trim() !== field
    ) {
      return;
    }
    setChildWorkItemPickerState(0, "", false);
  }, 120);
});

$(document).on("keydown", ".task-item-child-picker-select", function (e) {
  if (e.key === "Escape") {
    e.preventDefault();
    e.stopPropagation();
    setChildWorkItemPickerState(0, "", false);
    return;
  }
});

$(document).on("change", ".task-item-child-priority-select", function () {
  saveChildWorkItemPriority($(this));
});

$(document).on("change", ".task-item-child-assignee-select", function () {
  saveChildWorkItemAssignee($(this));
});

$(document).on("change", ".task-item-child-status-select", function () {
  saveChildWorkItemStatus($(this));
});

$(document).on("click", "#taskItemActivityCollapseBtn", function () {
  setActivitySectionCollapsed(!itemDetailModalState.activityCollapsed);
});

$(document).on("focus click", "#taskItemDetailTitleInput", function () {
  setItemDetailTitleEditMode(true);
});

$(document).on("keydown", "#taskItemDetailTitleInput", function (e) {
  if (e.key === "Enter") {
    e.preventDefault();
    saveItemCoreFromModal(false, {
      suppressNoChangeMessage: true,
      exitTitleEditModeOnSuccess: true,
    });
    return;
  }

  if (e.key === "Escape") {
    e.preventDefault();
    resetItemDetailTitleEdit();
  }
});

$(document).on("click", "#taskItemDetailTitleSaveBtn", function () {
  saveItemCoreFromModal(false, {
    suppressNoChangeMessage: true,
    exitTitleEditModeOnSuccess: true,
  });
});

$(document).on("click", "#taskItemDetailTitleResetBtn", function () {
  resetItemDetailTitleEdit();
});

$(document).on("input", "#taskItemDetailEstimateValueInput", function () {
  scheduleItemDetailFieldAutosave(350);
});

$(document).on(
  "change",
  "#taskItemDetailEstimateUnitInput, #taskItemDetailAssigneeSelect, #taskItemDetailReporterSelect, #taskItemDetailStartDateInput, #taskItemDetailDueDateInput, #taskItemDetailAmendDateInput, #taskItemDetailAmendTimeInput, #taskItemDetailSecondAmendDateInput, #taskItemDetailSecondAmendTimeInput",
  function () {
    saveItemDetailsFromModal(false, {
      autosave: true,
      suppressNoChangeMessage: true,
      silentSuccess: true,
    });
  },
);

$(document).on("blur", "#taskItemDetailEstimateValueInput", function () {
  saveItemDetailsFromModal(false, {
    autosave: true,
    suppressNoChangeMessage: true,
    silentSuccess: true,
  });
});

$(document).on(
  "show.bs.dropdown",
  ".task-item-detail-parent-dropdown",
  function () {
    $("#taskItemDetailParentSearchInput").val("");
    renderDetailParentOptions("");
    setTimeout(function () {
      $("#taskItemDetailParentSearchInput").trigger("focus");
    }, 40);
  },
);

$(document).on("input", "#taskItemDetailParentSearchInput", function () {
  renderDetailParentOptions($(this).val() || "");
});

$(document).on("click", ".task-item-detail-priority-option", function (e) {
  e.preventDefault();
  setDetailPriority(String($(this).data("priority") || "Medium"));
  saveItemDetailsFromModal(false, {
    autosave: true,
    suppressNoChangeMessage: true,
    silentSuccess: true,
  });
});

$(document).on(
  "show.bs.dropdown",
  ".task-item-detail-board-status-dropdown",
  function () {
    renderDetailBoardStatusOptions();
  },
);

$(document).on("click", ".task-item-detail-board-status-option", function (e) {
  e.preventDefault();

  if (!canEdit) {
    notify("You do not have permission to change status.");
    return;
  }

  var itemId = Number(itemDetailModalState.itemId || 0);
  var targetColumnId = Number($(this).data("targetColumnId") || 0);
  if (!itemId || !targetColumnId) {
    return;
  }
  if (!canTargetStatusColumn(targetColumnId)) {
    return;
  }

  var currentColumnId = Number(itemDetailModalState.detailStatusColumnId || 0);
  if (currentColumnId === targetColumnId) {
    return;
  }

  postAction(
    {
      task_action: "change_item_status",
      item_id: itemId,
      target_column_id: targetColumnId,
    },
    function () {
      var $card = $(itemDetailModalState.cardEl || null);
      if (!$card.length) {
        $card = findCardByItemId(itemId);
      }

      applyStatusChangeToBoard(
        $card,
        $card.closest(".task-column"),
        targetColumnId,
        false,
      );
      setDetailBoardStatus(targetColumnId);
      loadItemHistory(itemId);
    },
  );
});

$(document).on(
  "show.bs.dropdown",
  ".task-item-detail-status-dropdown",
  function () {
    $("#taskItemDetailStatusSearchInput").val("");
    renderStatusLabelOptions("");
    setTimeout(function () {
      $("#taskItemDetailStatusSearchInput").trigger("focus");
    }, 40);
  },
);

$(document).on("input", "#taskItemDetailStatusSearchInput", function () {
  renderStatusLabelOptions($(this).val() || "");
});

$(document).on("change", ".task-item-detail-status-checkbox", function () {
  if (!canEdit) {
    $(this).prop("checked", !$(this).is(":checked"));
    notify("You do not have permission to update task status.");
    return;
  }

  var statusLabelId = Number($(this).val() || 0);
  if (!statusLabelId) {
    return;
  }

  var selected = normalizeStatusLabelIdList(
    itemDetailModalState.selectedStatusLabelIds,
  );
  if ($(this).is(":checked")) {
    if (selected.indexOf(statusLabelId) === -1) {
      selected.push(statusLabelId);
    }
  } else {
    selected = selected.filter(function (id) {
      return id !== statusLabelId;
    });
  }

  setSelectedStatusLabels(selected);
  renderStatusLabelOptions($("#taskItemDetailStatusSearchInput").val() || "");
  saveItemDetailsFromModal(false, {
    autosave: true,
    suppressNoChangeMessage: true,
    silentSuccess: true,
  });
});

$(document).on("click", ".task-item-detail-status-chip-remove", function (e) {
  e.preventDefault();
  e.stopPropagation();

  if (!canEdit) {
    notify("You do not have permission to update task status.");
    return;
  }

  var statusLabelId = Number($(this).data("statusLabelId") || 0);
  if (!statusLabelId) {
    return;
  }

  removeStatusLabelFromSelection(statusLabelId);
  renderStatusLabelOptions($("#taskItemDetailStatusSearchInput").val() || "");
  saveItemDetailsFromModal(false, {
    autosave: true,
    suppressNoChangeMessage: true,
    silentSuccess: true,
  });
});

$(document).on("click", "#taskItemDetailSideCollapseBtn", function () {
  setDetailSideCollapsed(!itemDetailModalState.detailsCollapsed);
});

$(document).on("click", ".task-item-activity-tab", function (e) {
  e.preventDefault();
  setItemActivityTab($(this).data("tabTarget") || "all");
});

$(document).on("click", ".task-item-activity-sort-btn", function (e) {
  e.preventDefault();
  var currentDirection =
    String(itemDetailModalState.activitySortDirection || "desc") === "asc"
      ? "asc"
      : "desc";
  itemDetailModalState.activitySortDirection =
    currentDirection === "asc" ? "desc" : "asc";
  renderItemHistoryPanels();
});

$(document).on("click", "#taskItemWorklogToggleBtn", function () {
  worklogTimerState.collapsed = !worklogTimerState.collapsed;
  persistWorklogTimerState();
  applyWorklogCollapsedUi();
});

$(document).on("click", "#taskItemWorklogStartBtn", function () {
  startOrContinueWorklogTimer();
});

$(document).on("click", "#taskItemWorklogStopBtn", function () {
  stopWorklogTimer();
});

$(document).on("click", "#taskItemWorklogContinueBtn", function () {
  startOrContinueWorklogTimer();
});

$(document).on("click", "#taskItemWorklogResetBtn", function () {
  resetWorklogTimer();
});

$(document).on("click", "#taskItemWorklogSaveBtn", function () {
  saveWorklogForCurrentItem();
});

$(document).on("click", "#taskItemWorklogModalSaveBtn", function () {
  submitTaskWorklogModal();
});

$(document).on("click", ".task-item-worklog-edit-btn", function () {
  var row = getTaskWorklogById($(this).data("worklogId"));
  if (!row) {
    notify("Work log not found.");
    return;
  }
  openTaskWorklogModal("edit", row, Number(row.duration_seconds || 0));
});

$(document).on("click", ".task-item-worklog-delete-btn", function () {
  var row = getTaskWorklogById($(this).data("worklogId"));
  if (!row) {
    notify("Work log not found.");
    return;
  }
  openTaskWorklogDeleteModal(row);
});

$(document).on(
  "change",
  "#taskItemWorklogDeleteAdjustRemainingInput",
  function () {
    refreshWorklogDeleteRemainingPanel();
  },
);

$(document).on("click", "#taskItemWorklogDeleteConfirmBtn", function () {
  submitTaskWorklogDelete();
});

$(document).on("click", ".task-board-toast-close", function () {
  var toastId = String($(this).data("toastId") || "").trim();
  if (!toastId) {
    return;
  }

  $("#" + toastId)
    .stop(true, true)
    .fadeOut(120, function () {
      $(this).remove();
    });
});

$(document).on("click", "#taskItemChildWorkItemsCollapseBtn", function () {
  setChildWorkItemsCollapsed(!itemDetailModalState.childWorkItemsCollapsed);
});

$(document).on(
  "click",
  ".task-item-detail-status-option-delete-btn",
  function (e) {
    e.preventDefault();
    e.stopPropagation();

    if (!canEdit) {
      notify("You do not have permission to delete task status labels.");
      return;
    }

    var statusLabelId = Number($(this).data("statusLabelId") || 0);
    if (!statusLabelId) {
      return;
    }

    if (!window.confirm("Delete this task status label?")) {
      return;
    }

    postAction(
      {
        task_action: "delete_status_label",
        status_label_id: statusLabelId,
      },
      function (res) {
        normalizeStatusLabels(
          Array.isArray(res.statusLabels) ? res.statusLabels : [],
        );
        removeStatusLabelFromSelection(statusLabelId);
        renderStatusLabelOptions(
          $("#taskItemDetailStatusSearchInput").val() || "",
        );
        saveItemDetailsFromModal(false, {
          autosave: true,
          suppressNoChangeMessage: true,
          silentSuccess: true,
        });
      },
    );
  },
);

$(document).on("input", "#taskItemDetailLabelSearchInput", function () {
  renderModalLabelOptions();
});

$(document).on("change", ".task-item-detail-label-checkbox", function () {
  if (!canEdit) {
    $(this).prop("checked", !$(this).is(":checked"));
    notify("You do not have permission to update labels.");
    return;
  }

  var id = Number($(this).val() || 0);
  if (!id) {
    return;
  }

  if ($(this).is(":checked")) {
    if (itemDetailModalState.selectedLabelIds.indexOf(id) === -1) {
      itemDetailModalState.selectedLabelIds.push(id);
    }
  } else {
    itemDetailModalState.selectedLabelIds =
      itemDetailModalState.selectedLabelIds.filter(function (itemId) {
        return itemId !== id;
      });
  }

  renderModalLabelChips();
  persistModalLabels();
});

$(document).on("click", ".task-item-detail-label-chip-remove", function (e) {
  e.preventDefault();
  e.stopPropagation();

  if (!canEdit) {
    notify("You do not have permission to update labels.");
    return;
  }

  var labelId = Number($(this).data("labelId") || 0);
  if (!labelId) {
    return;
  }

  itemDetailModalState.selectedLabelIds =
    itemDetailModalState.selectedLabelIds.filter(function (itemId) {
      return itemId !== labelId;
    });
  renderModalLabelChips();
  renderModalLabelOptions();
  persistModalLabels();
});

$(document).on(
  "click",
  ".task-item-detail-label-option-delete-btn",
  function (e) {
    e.preventDefault();
    e.stopPropagation();

    if (!canEdit) {
      notify("You do not have permission to delete labels.");
      return;
    }

    var labelId = Number($(this).data("labelId") || 0);
    if (!labelId) {
      return;
    }

    if (!window.confirm("Delete this label?")) {
      return;
    }

    postAction(
      {
        task_action: "delete_label",
        label_id: labelId,
      },
      function (res) {
        syncKnownLabels(Array.isArray(res.labels) ? res.labels : []);
        removeLabelFromState(labelId);
        renderModalLabelChips();
        renderModalLabelOptions();
        persistModalLabels(null, {
          silentSuccess: true,
        });
        $(".task-item-label-submenu").each(function () {
          refreshInlineLabelList($(this));
        });
      },
    );
  },
);

function openAttachmentPickerFromModal() {
  itemDetailModalState.showAttachmentPanelWhenEmpty = true;
  itemDetailModalState.pendingAttachmentPicker = true;
  renderItemAttachments(itemDetailModalState.attachments);
  $("#taskItemAttachmentInput").trigger("click");
}

$(document).on("click", "#taskItemDetailAddAttachmentAction", function (e) {
  e.preventDefault();
  if (!canEdit) {
    notify("You do not have permission to upload attachments.");
    return;
  }
  openAttachmentPickerFromModal();
});

$(document).on("click", "#taskItemAttachmentAddBtn", function () {
  if (!canEdit) {
    notify("You do not have permission to upload attachments.");
    return;
  }

  openAttachmentPickerFromModal();
});

$(window).on("focus.taskAttachmentPicker", function () {
  if (!itemDetailModalState.pendingAttachmentPicker) {
    return;
  }

  setTimeout(function () {
    if (!itemDetailModalState.pendingAttachmentPicker) {
      return;
    }

    itemDetailModalState.pendingAttachmentPicker = false;
    var inputEl = $("#taskItemAttachmentInput").get(0);
    var selectedCount =
      inputEl && inputEl.files && inputEl.files.length
        ? inputEl.files.length
        : 0;
    if (
      selectedCount === 0 &&
      (!Array.isArray(itemDetailModalState.attachments) ||
        itemDetailModalState.attachments.length === 0)
    ) {
      itemDetailModalState.showAttachmentPanelWhenEmpty = false;
      renderItemAttachments(itemDetailModalState.attachments);
    }
  }, 80);
});

$(document).on("click", "#taskItemDetailAddWebLinkAction", function (e) {
  e.preventDefault();
  if (!canEdit) {
    notify("You do not have permission to add web links.");
    return;
  }

  if (!itemDetailModalState.itemId) {
    return;
  }

  openWebLinkEditor();
});

$(document).on(
  "click",
  "#taskItemDetailLinkWorkItemAction, #taskItemLinkedWorkItemAddBtn, #taskItemLinkedWorkItemsEmptyAction",
  function (e) {
    e.preventDefault();
    if (!canEdit) {
      notify("You do not have permission to add linked work items.");
      return;
    }

    hideItemDetailAddDropdown();
    openLinkWorkItemEditor();
  },
);

$(document).on("click", "#taskItemLinkCancelBtn", function () {
  resetLinkWorkItemEditor(true);
});

$(document).on("click", "#taskItemLinkSaveBtn", function () {
  submitLinkedWorkItem();
});

$(document).on("input", "#taskItemLinkSearchInput", function () {
  itemDetailModalState.linkSelectedItemId = 0;
  renderLinkedWorkItemsSection();
  queueLinkedWorkItemSearch();
});

$(document).on("change", "#taskItemLinkRelationTypeSelect", function () {
  itemDetailModalState.linkRelationType = String($(this).val() || "").trim();
  itemDetailModalState.linkSelectedItemId = 0;
  renderLinkedWorkItemsSection();
  queueLinkedWorkItemSearch();
});

$(document).on("keydown", "#taskItemLinkSearchInput", function (e) {
  if (e.key === "Enter") {
    e.preventDefault();
    submitLinkedWorkItem();
    return;
  }

  if (e.key === "Escape") {
    e.preventDefault();
    resetLinkWorkItemEditor(true);
  }
});

$(document).on("click", ".task-item-search-result-link", function (e) {
  e.preventDefault();
  e.stopPropagation();

  var resultItemId = Number($(this).attr("data-result-item-id") || 0);
  var results = normalizeItemSearchResults(itemDetailModalState.linkSearchResults);

  itemDetailModalState.linkSelectedItemId = resultItemId;
  for (var index = 0; index < results.length; index++) {
    var result = results[index] || {};
    if (Number(result.id || 0) !== resultItemId) {
      continue;
    }

    $("#taskItemLinkSearchInput").val(
      $.trim(
        (result.work_item_key ? result.work_item_key + " " : "") +
          String(result.title || ""),
      ),
    );
    break;
  }

  renderLinkedWorkItemsSection();
});

$(document).on("click", ".task-item-linked-open-btn", function (e) {
  e.preventDefault();
  e.stopPropagation();

  var linkedItemId = Number($(this).data("linkedItemId") || 0);
  openReferencedWorkItemModal(
    linkedItemId,
    linkedWorkItemById(linkedItemId) || {},
  );
});

$(document).on("click", ".task-item-linked-remove-btn", function () {
  if (!canEdit) {
    notify("You do not have permission to remove linked work items.");
    return;
  }

  var currentItemId = Number(itemDetailModalState.itemId || 0);
  var linkId = Number($(this).data("linkId") || 0);
  if (!currentItemId || !linkId) {
    return;
  }

  if (!window.confirm("Remove this linked work item?")) {
    return;
  }

  postAction(
    {
      task_action: "delete_item_link",
      item_id: currentItemId,
      link_id: linkId,
    },
    function (res) {
      applyCurrentItemDetailResponse(res);
      loadItemHistory(currentItemId);
    },
  );
});

$(document).on("click", "#taskItemWebLinkAddBtn", function () {
  if (!canEdit) {
    notify("You do not have permission to add web links.");
    return;
  }

  if (!itemDetailModalState.itemId) {
    return;
  }

  openWebLinkEditor();
});

$(document).on("click", "#taskItemWebLinkCancelBtn", function () {
  closeWebLinkEditor();
});

$(document).on("click", "#taskItemWebLinkSaveBtn", function () {
  if (!canEdit) {
    notify("You do not have permission to add web links.");
    return;
  }

  var itemId = Number(itemDetailModalState.itemId || 0);
  var url = String($("#taskItemWebLinkUrlInput").val() || "").trim();
  var linkText = String($("#taskItemWebLinkTextInput").val() || "").trim();

  if (!itemId) {
    return;
  }
  if (!url) {
    notify("URL is required.");
    return;
  }

  postAction(
    {
      task_action: "create_item_web_link",
      item_id: itemId,
      url: url,
      link_text: linkText,
    },
    function (res) {
      itemDetailModalState.webLinks = Array.isArray(res.webLinks)
        ? res.webLinks.slice()
        : itemDetailModalState.webLinks;
      closeWebLinkEditor();
      renderWebLinksSection();
      loadItemHistory(itemId);
    },
  );
});

$(document).on("click", ".task-item-web-link-delete-btn", function () {
  if (!canEdit) {
    notify("You do not have permission to delete web links.");
    return;
  }

  var urlId = Number($(this).data("urlId") || 0);
  if (!urlId) {
    return;
  }

  if (!window.confirm("Delete this web link?")) {
    return;
  }

  postAction(
    {
      task_action: "delete_item_web_link",
      url_id: urlId,
    },
    function (res) {
      itemDetailModalState.webLinks = Array.isArray(res.webLinks)
        ? res.webLinks.slice()
        : [];
      renderWebLinksSection();
      loadItemHistory(Number(itemDetailModalState.itemId || 0));
    },
  );
});

$(document).on(
  "change",
  "#taskItemDetailIncludeChildTimeTrackingInput",
  function () {
    itemDetailModalState.timeTracking.includeChild = $(this).is(":checked");
    renderDetailTimeTracking(
      itemDetailModalState.timeTracking,
      Number($("#taskItemDetailEstimateValueInput").val() || 0),
      String($("#taskItemDetailEstimateUnitInput").val() || "minutes"),
    );
  },
);

$(document).on("click", ".task-item-detail-parent-option", function (e) {
  e.preventDefault();

  if (!canEdit) {
    notify("You do not have permission to link parent.");
    return;
  }

  var itemId = Number(itemDetailModalState.itemId || 0);
  if (!itemId) {
    return;
  }

  var previousParentId = Number(itemDetailModalState.parentItemId || 0);
  var selectedParentId = Number($(this).data("parentItemId") || 0);
  if (selectedParentId === previousParentId) {
    var parentDropdown = bootstrap.Dropdown.getOrCreateInstance(
      document.getElementById("taskItemDetailParentDropdownBtn"),
    );
    parentDropdown.hide();
    return;
  }

  setItemDetailAutosaveStatus("saving", "Saving changes...");

  postAction(
    {
      task_action: "set_item_parent",
      item_id: itemId,
      parent_item_id: selectedParentId,
    },
    function (res) {
      var resolvedParentId = Number(
        (res && res.parent_item_id) || selectedParentId || 0,
      );
      renderDetailParentDropdown(
        resolvedParentId,
        Array.isArray(res.parentOptions)
          ? res.parentOptions
          : getBoardEpicParentOptions(itemId),
      );
      var $card = $(itemDetailModalState.cardEl || null);
      if ($card.length) {
        updateCardParentSubmenuToggle($card, resolvedParentId);
      }
      loadItemHistory(itemId);
      setItemDetailAutosaveStatus("saved", "All changes saved");
      bootstrap.Dropdown.getOrCreateInstance(
        document.getElementById("taskItemDetailParentDropdownBtn"),
      ).hide();
    },
    function (res) {
      renderDetailParentDropdown(
        previousParentId,
        itemDetailModalState.parentOptions,
      );
      if (isPermissionDeniedResponse(res)) {
        restoreItemDetailStateAfterDeniedSave();
        return;
      }
      setItemDetailAutosaveStatus("error", "Failed to save changes");
    },
  );
});

$(document).on("click", "#taskItemAttachmentCollapseBtn", function () {
  setAttachmentPanelCollapsed(!itemDetailModalState.attachmentsCollapsed);
});

$(document).on("click", "#taskItemAttachmentToggleViewAction", function (e) {
  e.preventDefault();
  itemDetailModalState.attachmentView =
    itemDetailModalState.attachmentView === "strip" ? "list" : "strip";
  renderItemAttachments(itemDetailModalState.attachments);
});

$(document).on("click", "#taskItemAttachmentDownloadAllAction", function (e) {
  e.preventDefault();
  triggerAttachmentDownloads(
    sortedAttachments(itemDetailModalState.attachments),
  );
});

$(document).on("click", "#taskItemAttachmentDeleteAllAction", function (e) {
  e.preventDefault();

  if (!canEdit) {
    notify("You do not have permission to remove attachments.");
    return;
  }

  var itemId = Number(itemDetailModalState.itemId || 0);
  if (!itemId) {
    return;
  }

  var count = Array.isArray(itemDetailModalState.attachments)
    ? itemDetailModalState.attachments.length
    : 0;
  if (!count) {
    notify("No attachments to delete.");
    return;
  }

  if (!window.confirm("Delete all attachments for this work item?")) {
    return;
  }

  postAction(
    {
      task_action: "delete_all_item_attachments",
      item_id: itemId,
    },
    function (res) {
      itemDetailModalState.showAttachmentPanelWhenEmpty = false;
      renderItemAttachments(
        Array.isArray(res.attachments) ? res.attachments : [],
      );
      loadItemHistory(itemId);
    },
  );
});

$(document).on("click", ".task-item-attachment-sort-btn", function () {
  var field = String($(this).data("sortField") || "");
  if (!field) {
    return;
  }

  if (itemDetailModalState.attachmentSort.field === field) {
    itemDetailModalState.attachmentSort.direction =
      itemDetailModalState.attachmentSort.direction === "asc" ? "desc" : "asc";
  } else {
    itemDetailModalState.attachmentSort.field = field;
    itemDetailModalState.attachmentSort.direction = "asc";
  }

  renderItemAttachments(itemDetailModalState.attachments);
});

$(document).on("click", ".task-item-attachment-remove-btn", function () {
  if (!canEdit) {
    notify("You do not have permission to remove attachments.");
    return;
  }

  var attachmentId = Number($(this).data("attachmentId") || 0);
  if (!attachmentId) {
    return;
  }

  if (!window.confirm("Delete this attachment?")) {
    return;
  }

  postAction(
    {
      task_action: "delete_item_attachment",
      attachment_id: attachmentId,
    },
    function (res) {
      var attachments = Array.isArray(res.attachments) ? res.attachments : [];
      if (!attachments.length) {
        itemDetailModalState.showAttachmentPanelWhenEmpty = false;
      }
      renderItemAttachments(attachments);
      loadItemHistory(Number(itemDetailModalState.itemId || 0));
    },
  );
});

$(document).on("change", "#taskItemAttachmentInput", function () {
  if (!canEdit) {
    this.value = "";
    notify("You do not have permission to upload attachments.");
    return;
  }

  itemDetailModalState.pendingAttachmentPicker = false;
  var itemId = Number(itemDetailModalState.itemId || 0);
  var files = this.files ? Array.prototype.slice.call(this.files) : [];
  this.value = "";

  if (!itemId || !files.length) {
    if (
      !Array.isArray(itemDetailModalState.attachments) ||
      itemDetailModalState.attachments.length === 0
    ) {
      itemDetailModalState.showAttachmentPanelWhenEmpty = false;
      renderItemAttachments(itemDetailModalState.attachments);
    }
    return;
  }

  var uploadNext = function (index) {
    if (index >= files.length) {
      return;
    }

    var formData = new FormData();
    formData.append("task_action", "upload_item_attachment");
    formData.append("item_id", String(itemId));
    formData.append("attachment", files[index]);

    var uploadRequest = postAction(
      formData,
      function (res) {
        itemDetailModalState.showAttachmentPanelWhenEmpty = true;
        renderItemAttachments(
          Array.isArray(res.attachments) ? res.attachments : [],
        );
        loadItemHistory(itemId);
      },
      function () {},
    );

    if (uploadRequest && typeof uploadRequest.always === "function") {
      uploadRequest.always(function () {
        uploadNext(index + 1);
      });
      return;
    }

    uploadNext(index + 1);
  };

  uploadNext(0);
});

$(document).on("hidden.bs.modal", "#taskItemDetailModal", function () {
  clearTaskItemUrl();
  persistWorklogTimerState();
  stopWorklogTicker();
  var worklogModal = getItemWorklogModalInstance();
  if (worklogModal) {
    worklogModal.hide();
  }
  var worklogDeleteModal = getItemWorklogDeleteModalInstance();
  if (worklogDeleteModal) {
    worklogDeleteModal.hide();
  }
  setTaskItemDetailMobileOverlayState("", false);
  syncTaskItemDetailMobileLayout();

  itemDetailModalState.itemId = 0;
  itemDetailModalState.cardEl = null;
  itemDetailModalState.initialTitle = "";
  itemDetailModalState.initialDescription = "";
  itemDetailModalState.titleEditing = false;
  itemDetailModalState.descriptionEditing = false;
  itemDetailModalState.lastSavedCoreSnapshot = "";
  itemDetailModalState.lastSavedDetailSnapshot = "";
  itemDetailModalState.lastSavedLabelsSnapshot = "";
  itemDetailModalState.attachments = [];
  itemDetailModalState.attachmentSort = {
    field: "date",
    direction: "desc",
  };
  itemDetailModalState.attachmentsCollapsed = false;
  itemDetailModalState.descriptionCollapsed = false;
  itemDetailModalState.attachmentView = "list";
  itemDetailModalState.showAttachmentPanelWhenEmpty = false;
  itemDetailModalState.pendingAttachmentPicker = false;
  clearItemDetailAutosaveTimer("coreAutosaveTimer");
  clearItemDetailAutosaveTimer("detailAutosaveTimer");
  itemDetailModalState.coreSaveInFlight = false;
  itemDetailModalState.detailSaveInFlight = false;
  itemDetailModalState.labelsSaveInFlight = false;
  itemDetailModalState.queuedCoreSave = false;
  itemDetailModalState.queuedDetailSave = false;
  itemDetailModalState.queuedLabelsSave = false;
  itemDetailModalState.selectedPriority = "Medium";
  itemDetailModalState.detailStatusColumnId = 0;
  itemDetailModalState.selectedStatusLabelIds = [];
  itemDetailModalState.selectedLabelIds = [];
  itemDetailModalState.comments = [];
  itemDetailModalState.commentsLoading = false;
  itemDetailModalState.worklogs = [];
  itemDetailModalState.worklogsLoading = false;
  itemDetailModalState.historyRequestSeq = 0;
  itemDetailModalState.worklogRequestSeq = 0;
  itemDetailModalState.parentItemId = 0;
  itemDetailModalState.parentOptions = [];
  itemDetailModalState.detailsCollapsed = false;
  itemDetailModalState.workTypeName = "Task";
  itemDetailModalState.workTypeIcon = "";
  itemDetailModalState.workItemKey = "";
  itemDetailModalState.parentWorkItemKey = "";
  itemDetailModalState.parentWorkTypeName = "Task";
  itemDetailModalState.parentWorkTypeIcon = "";
  itemDetailModalState.timeTracking = {
    ownText: "No time logged",
    ownSeconds: 0,
    ownRemainingSeconds: 0,
    ownEstimateSeconds: 0,
    childText: "No time logged",
    childSeconds: 0,
    childRemainingSeconds: 0,
    canIncludeChild: false,
    includeChild: false,
  };
  itemDetailModalState.childWorkItems = {
    items: [],
    total: 0,
    done: 0,
    progress_percent: 0,
  };
  itemDetailModalState.isParentType = false;
  itemDetailModalState.childCreatePanelOpen = false;
  itemDetailModalState.childCreateMode = "create";
  itemDetailModalState.childCreateSelectedItemId = 0;
  itemDetailModalState.childCreateSearchResults = [];
  clearChildCreateSearchTimer();
  itemDetailModalState.itemLinks = {
    groups: [],
    total: 0,
  };
  itemDetailModalState.linkEditorOpen = false;
  itemDetailModalState.linkRelationType = "";
  itemDetailModalState.linkSelectedItemId = 0;
  itemDetailModalState.linkSearchResults = [];
  clearLinkSearchTimer();
  itemDetailModalState.childTitleEditingItemId = 0;
  itemDetailModalState.childPickerItemId = 0;
  itemDetailModalState.childPickerField = "";
  itemDetailModalState.childWorkItemsCollapsed = false;
  itemDetailModalState.history = [];
  itemDetailModalState.activityCollapsed = false;
  itemDetailModalState.activityTab = "all";
  itemDetailModalState.activitySortDirection = "desc";
  $(".task-item-detail-title-row").removeClass("is-editing");
  $("#taskItemDetailDescriptionInput").val("");
  setDescriptionPanelCollapsed(false);
  setActivitySectionCollapsed(false);
  renderItemDetailDescriptionPreview("");
  setItemDetailDescriptionEditMode(false, {
    skipPreviewUpdate: true,
  });
  setItemDetailAutosaveStatus("", "");
  $("#taskItemDetailKeyTrail").addClass("d-none").empty();
  $("#taskItemDetailModalTitle").text("Work item");
  $("#taskItemAttachmentInput").val("");
  $("#taskItemChildCreateInput").val("");
  $("#taskItemChildCreateWorkTypeSelect").val("");
  $("#taskItemLinkSearchInput").val("");
  $("#taskItemLinkRelationTypeSelect").val("");
  renderChildCreatePanel();
  renderLinkedWorkItemsSection();

  worklogTimerState = {
    itemId: 0,
    elapsedSeconds: 0,
    running: false,
    startedAtMs: 0,
    collapsed: false,
  };

  applyWorklogTimerUi();
  renderItemHistoryPanels();
  setItemActivityTab("all");
});

function getDropTargetElement($list, y, $dragItem) {
  var closest = {
    offset: Number.NEGATIVE_INFINITY,
    element: null,
  };

  $list
    .children(".task-item-card")
    .not($dragItem)
    .each(function () {
      var box = this.getBoundingClientRect();
      var offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) {
        closest = {
          offset: offset,
          element: this,
        };
      }
    });

  return closest.element;
}

$app.on("dragstart", ".task-item-card", function (e) {
  if (!canEdit) {
    e.preventDefault();
    return;
  }

  if (
    typeof isTouchBoardViewport === "function" &&
    isTouchBoardViewport()
  ) {
    e.preventDefault();
    return;
  }

  if (!isBoardGroupedByStatus()) {
    e.preventDefault();
    return;
  }

  var $item = $(this);
  dragState.$item = $item;
  dragState.$sourceList = $item.closest(".task-item-list");
  dragState.$sourceNext = $item.next(".task-item-card");

  if (e.originalEvent && e.originalEvent.dataTransfer) {
    e.originalEvent.dataTransfer.effectAllowed = "move";
    e.originalEvent.dataTransfer.setData(
      "text/plain",
      String($item.data("itemId") || ""),
    );
  }

  setTimeout(function () {
    $item.addClass("task-item-dragging");
  }, 0);
});

$app.on("dragover", ".task-item-list", function (e) {
  if (!dragState.$item || !dragState.$item.length) {
    return;
  }

  var $list = $(this);
  var targetColumnId = Number(
    $list.closest(".task-column").data("columnId") || 0,
  );
  if (!canTargetStatusColumn(targetColumnId)) {
    return;
  }

  e.preventDefault();
  var target = getDropTargetElement(
    $list,
    e.originalEvent && e.originalEvent.clientY ? e.originalEvent.clientY : 0,
    dragState.$item,
  );

  if (target) {
    dragState.$item.insertBefore($(target));
  } else {
    $list.append(dragState.$item);
  }
});

$app.on("drop", ".task-item-list", function (e) {
  if (!dragState.$item || !dragState.$item.length) {
    return;
  }

  if (!isBoardGroupedByStatus()) {
    e.preventDefault();
    return;
  }

  e.preventDefault();

  var $targetList = $(this);
  var $item = dragState.$item;
  var itemId = Number($item.data("itemId") || 0);
  var sourceStatusColumnId = getCardStatusColumnId($item);
  var targetColumnId = Number(
    $targetList.closest(".task-column").data("columnId") || 0,
  );
  var targetIndex = $targetList.children(".task-item-card").index($item) + 1;
  var $sourceList = dragState.$sourceList;
  var $sourceNext = dragState.$sourceNext;

  if (!itemId || !targetColumnId || targetIndex <= 0) {
    return;
  }

  var revertDrop = function () {
    if (!$sourceList || !$sourceList.length) {
      return;
    }
    if ($sourceNext && $sourceNext.length) {
      $item.insertBefore($sourceNext);
    } else {
      $sourceList.append($item);
    }
    var sourceMeta = getBoardStatusColumnMeta(sourceStatusColumnId);
    setCardStatusColumnMeta(
      $item,
      sourceStatusColumnId,
      getBoardStatusColumnName(sourceStatusColumnId),
      sourceMeta ? sourceMeta.color : "#DFE1E6",
    );
    updateAllColumnCounts();
    applyBoardFilters();
  };

  if (!canTargetStatusColumn(targetColumnId)) {
    revertDrop();
    return;
  }

  postAction(
    {
      task_action: "move_item_drop",
      item_id: itemId,
      target_column_id: targetColumnId,
      target_index: targetIndex,
    },
    function () {
      var targetMeta = getBoardStatusColumnMeta(targetColumnId);
      setCardStatusColumnMeta(
        $item,
        targetColumnId,
        getBoardStatusColumnName(targetColumnId),
        targetMeta ? targetMeta.color : "#DFE1E6",
      );
      updateAllColumnCounts();
      applyBoardFilters();
    },
    function () {
      revertDrop();
    },
  );
});

$app.on("dragend", ".task-item-card", function () {
  $(this).removeClass("task-item-dragging");
  dragState.$item = null;
  dragState.$sourceList = null;
  dragState.$sourceNext = null;
});

$("#taskBoardSearchInput").on("input", function () {
  boardSearchQuery = String($(this).val() || "");
  applyBoardFilters();
});

var taskBoardMobileToolbarMode = null;
var taskBoardMobileToolbarSyncTimer = 0;

function isTaskBoardMobileToolbar() {
  if (typeof window.matchMedia !== "function") {
    return (window.innerWidth || document.documentElement.clientWidth || 0) <= 991.98;
  }

  return window.matchMedia("(max-width: 991.98px)").matches;
}

function syncTaskBoardMobileFilterPosition(force) {
  var filterDropdown = document.getElementById("taskBoardFilterDropdown");
  var toolbarLeft = document.querySelector(".task-board-toolbar-left");
  var toolbarActions = document.querySelector(".task-board-toolbar-actions");
  var groupDropdown = document.getElementById("taskBoardGroupDropdown");
  var selectedAssignees = document.getElementById("taskBoardFilterSelectedAssignees");
  var isMobileMode = isTaskBoardMobileToolbar();

  if (!filterDropdown || !toolbarLeft || !toolbarActions) {
    return;
  }

  if (!force && taskBoardMobileToolbarMode === isMobileMode) {
    return;
  }

  if (isMobileMode) {
    if (filterDropdown.parentNode !== toolbarActions) {
      toolbarActions.insertBefore(filterDropdown, groupDropdown || toolbarActions.firstElementChild);
    }
  } else if (filterDropdown.parentNode !== toolbarLeft) {
    if (selectedAssignees && selectedAssignees.parentNode === toolbarLeft) {
      selectedAssignees.insertAdjacentElement("afterend", filterDropdown);
    } else {
      toolbarLeft.appendChild(filterDropdown);
    }
  }

  taskBoardMobileToolbarMode = isMobileMode;

  if (filterDropdown.classList.contains("show")) {
    window.setTimeout(positionTaskBoardFilterMenu, 0);
  }
}

syncTaskBoardMobileFilterPosition(true);

$(window)
  .off("resize.taskBoardMobileFilter orientationchange.taskBoardMobileFilter")
  .on("resize.taskBoardMobileFilter orientationchange.taskBoardMobileFilter", function () {
    window.clearTimeout(taskBoardMobileToolbarSyncTimer);
    taskBoardMobileToolbarSyncTimer = window.setTimeout(function () {
      syncTaskBoardMobileFilterPosition(false);
    }, 120);
  });

function positionTaskBoardFilterMenu() {
  var dropdown = document.getElementById("taskBoardFilterDropdown");
  var button = document.getElementById("taskBoardFilterBtn");
  var menu = document.getElementById("taskBoardFilterMenu");
  if (!dropdown || !button || !menu || !dropdown.classList.contains("show")) {
    return;
  }

  var buttonRect = button.getBoundingClientRect();
  var viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
  var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
  var edgePadding = 9;
  var menuWidth = Math.min(380, Math.max(280, viewportWidth - edgePadding * 2));
  var maxHeight = Math.max(260, viewportHeight - 88);
  var left = Math.max(
    edgePadding,
    Math.min(buttonRect.left, viewportWidth - menuWidth - edgePadding),
  );
  var top = buttonRect.bottom + 8;

  if (top + maxHeight > viewportHeight - edgePadding) {
    top = Math.max(edgePadding, viewportHeight - maxHeight - edgePadding);
  }

  menu.classList.add("task-board-filter-menu-fixed");
  menu.style.width = menuWidth + "px";
  menu.style.left = left + "px";
  menu.style.top = top + "px";
  menu.style.maxHeight = maxHeight + "px";

  var panel = menu.querySelector(".task-board-filter-panel");
  if (panel) {
    panel.style.maxHeight = maxHeight + "px";
  }
}

function attachTaskBoardFilterMenuToBody() {
  var menu = document.getElementById("taskBoardFilterMenu");
  if (!menu || menu.parentNode === document.body) {
    return;
  }

  menu.__taskBoardFilterOriginalParent = menu.parentNode;
  menu.__taskBoardFilterOriginalNextSibling = menu.nextSibling;
  document.body.appendChild(menu);
}

function restoreTaskBoardFilterMenuParent() {
  var menu = document.getElementById("taskBoardFilterMenu");
  if (
    !menu ||
    !menu.__taskBoardFilterOriginalParent ||
    menu.parentNode !== document.body
  ) {
    return;
  }

  var parent = menu.__taskBoardFilterOriginalParent;
  var nextSibling = menu.__taskBoardFilterOriginalNextSibling || null;
  parent.insertBefore(menu, nextSibling);
  menu.__taskBoardFilterOriginalParent = null;
  menu.__taskBoardFilterOriginalNextSibling = null;
}

function resetTaskBoardFilterMenuPosition() {
  var menu = document.getElementById("taskBoardFilterMenu");
  if (!menu) {
    return;
  }

  menu.classList.remove("task-board-filter-menu-fixed");
  menu.style.width = "";
  menu.style.left = "";
  menu.style.top = "";
  menu.style.maxHeight = "";

  var panel = menu.querySelector(".task-board-filter-panel");
  if (panel) {
    panel.style.maxHeight = "";
  }
}

$(document).on("shown.bs.dropdown", "#taskBoardFilterDropdown", function () {
  renderBoardFilterUi();
  attachTaskBoardFilterMenuToBody();
  positionTaskBoardFilterMenu();
});

$(document).on("hidden.bs.dropdown", "#taskBoardFilterDropdown", function () {
  resetTaskBoardFilterMenuPosition();
  restoreTaskBoardFilterMenuParent();
});

$(document).on("show.bs.dropdown", "#taskBoardGroupDropdown", function () {
  syncBoardGroupControlUi();
});

$(window).on("resize scroll", function () {
  positionTaskBoardFilterMenu();
});

$(document).on("click", ".task-board-group-option", function (e) {
  e.preventDefault();

  var nextMode = normalizeBoardGroupBy(
    $(this).attr("data-group-by") || "status",
  );
  if (nextMode === getBoardGroupBy()) {
    return;
  }

  setBoardGroupBy(nextMode, true);
  renderBoardGroupingLayout();

  var dropdownEl = document.getElementById("taskBoardGroupBtn");
  if (dropdownEl && typeof bootstrap !== "undefined" && bootstrap.Dropdown) {
    var instance = bootstrap.Dropdown.getInstance(dropdownEl);
    if (instance) {
      instance.hide();
    }
  }
});

$(document).on("click", "#taskBoardFilterBtn", function () {});

$(document).on("click", "#taskOpenCreateStatusBtn", function () {});

$(document).on("click", "#taskBoardNoResultClearBtn", function (e) {
  e.preventDefault();
  boardSearchQuery = "";
  $("#taskBoardSearchInput").val("");
  resetBoardFilterPartA();
  resetBoardFilterPartB();
  boardFilterState.activePart = "none";
  commitBoardFilters();
});

$(document).on("click", "#taskBoardFilterPartAAssigned", function (e) {
  e.preventDefault();
  setBoardFilterPartA("assignedToMe", !boardFilterState.partA.assignedToMe);
  commitBoardFilters();
});

$(document).on("click", "#taskBoardFilterPartADueWeek", function (e) {
  e.preventDefault();
  setBoardFilterPartA("dueThisWeek", !boardFilterState.partA.dueThisWeek);
  commitBoardFilters();
});

$(document).on("click", "#taskBoardFilterClearBtn", function (e) {
  e.preventDefault();
  resetBoardFilterPartA();
  resetBoardFilterPartB();
  boardFilterState.activePart = "none";
  commitBoardFilters();
});

$(document).on("change", "#taskBoardFilterDateStart", function () {
  enableBoardFilterPartB();
  boardFilterState.partB.dateStart = String($(this).val() || "").trim();
  commitBoardFilters();
});

$(document).on("change", "#taskBoardFilterDateDue", function () {
  enableBoardFilterPartB();
  boardFilterState.partB.dateDue = String($(this).val() || "").trim();
  commitBoardFilters();
});

$(document).on("change", "#taskBoardFilterCreatedFrom", function () {
  enableBoardFilterPartB();
  boardFilterState.partB.createdFrom = String($(this).val() || "").trim();
  commitBoardFilters();
});

$(document).on("change", "#taskBoardFilterCreatedTo", function () {
  enableBoardFilterPartB();
  boardFilterState.partB.createdTo = String($(this).val() || "").trim();
  commitBoardFilters();
});

$(document).on("change", "#taskBoardFilterUpdatedFrom", function () {
  enableBoardFilterPartB();
  boardFilterState.partB.updatedFrom = String($(this).val() || "").trim();
  commitBoardFilters();
});

$(document).on("change", "#taskBoardFilterUpdatedTo", function () {
  enableBoardFilterPartB();
  boardFilterState.partB.updatedTo = String($(this).val() || "").trim();
  commitBoardFilters();
});

$(document).on("click", ".task-board-filter-date-clear", function (e) {
  e.preventDefault();
  var targetSelector = String($(this).data("targetInput") || "");
  if (!targetSelector) {
    return;
  }

  var $input = $(targetSelector);
  if (!$input.length) {
    return;
  }

  $input.val("").trigger("change");
});

$(document).on("click", ".task-board-filter-avatar-option", function (e) {
  e.preventDefault();
  enableBoardFilterPartB();

  var role = String($(this).data("role") || "assignee");
  var userId = Number($(this).data("userId") || 0);
  var source =
    role === "reporter"
      ? boardFilterState.partB.reporterIds
      : boardFilterState.partB.assigneeIds;

  var next = normalizeIdList(source.slice());
  var index = next.indexOf(userId);
  if (index === -1) {
    next.push(userId);
  } else {
    next.splice(index, 1);
  }

  next = normalizeIdList(next);
  if (role === "reporter") {
    boardFilterState.partB.reporterIds = next;
  } else {
    boardFilterState.partB.assigneeIds = next;
  }

  commitBoardFilters();
});

$(document).on("click", ".task-board-filter-priority-btn", function (e) {
  e.preventDefault();
  enableBoardFilterPartB();

  var value = String($(this).data("priorityValue") || "").trim();
  var next = normalizePriorityList(boardFilterState.partB.priorityValues);
  var index = next.indexOf(value);
  if (index === -1) {
    next.push(value);
  } else {
    next.splice(index, 1);
  }
  boardFilterState.partB.priorityValues = normalizePriorityList(next);
  commitBoardFilters();
});

$(document).on("change", ".task-board-filter-status-checkbox", function () {
  enableBoardFilterPartB();
  var next = [];
  $(".task-board-filter-status-checkbox:checked").each(function () {
    var id = Number($(this).val() || 0);
    if (id > 0) {
      next.push(id);
    }
  });
  boardFilterState.partB.statusIds = normalizeIdList(next);
  commitBoardFilters();
});

$(document).on(
  "click",
  "#taskBoardFilterWorkTypeList .task-board-filter-chip",
  function (e) {
    e.preventDefault();
    enableBoardFilterPartB();

    var workTypeId = Number($(this).data("workTypeId") || 0);
    var next = normalizeIdList(boardFilterState.partB.workTypeIds.slice());
    var index = next.indexOf(workTypeId);
    if (index === -1) {
      next.push(workTypeId);
    } else {
      next.splice(index, 1);
    }
    boardFilterState.partB.workTypeIds = normalizeIdList(next);
    commitBoardFilters();
  },
);

$(document).on("click", "#taskBoardFilterLabelSearchToggle", function (e) {
  e.preventDefault();
  $("#taskBoardFilterLabelSearchPanel").toggleClass("d-none");
  if (!$("#taskBoardFilterLabelSearchPanel").hasClass("d-none")) {
    $("#taskBoardFilterLabelSearchInput").trigger("focus");
  }
});

$(document).on("click", "#taskBoardFilterLabelChips", function (e) {
  if ($(e.target).closest("[data-remove-label-id]").length) {
    return;
  }
  $("#taskBoardFilterLabelSearchPanel").removeClass("d-none");
  $("#taskBoardFilterLabelSearchInput").trigger("focus");
});

$(document).on("click", ".task-board-filter-block-labels", function (e) {
  if (
    $(e.target).closest(
      "#taskBoardFilterLabelSearchPanel, #taskBoardFilterLabelSearchToggle, [data-remove-label-id]",
    ).length
  ) {
    return;
  }

  $("#taskBoardFilterLabelSearchPanel").removeClass("d-none");
  $("#taskBoardFilterLabelSearchInput").trigger("focus");
});

$(document).on("input", "#taskBoardFilterLabelSearchInput", function () {
  boardFilterState.search.label = String($(this).val() || "");
  renderBoardFilterLabelSearchList();
});

$(document).on("change", ".task-board-filter-label-checkbox", function () {
  enableBoardFilterPartB();
  var next = [];
  $(".task-board-filter-label-checkbox:checked").each(function () {
    var id = Number($(this).val() || 0);
    if (id > 0) {
      next.push(id);
    }
  });
  boardFilterState.partB.labelIds = normalizeIdList(next);
  commitBoardFilters();
});

$(document).on("click", "[data-remove-label-id]", function (e) {
  e.preventDefault();
  enableBoardFilterPartB();
  var removeId = Number($(this).data("removeLabelId") || 0);
  boardFilterState.partB.labelIds = normalizeIdList(
    boardFilterState.partB.labelIds.filter(function (id) {
      return id !== removeId;
    }),
  );
  commitBoardFilters();
});

$(document).on("click", "#taskBoardFilterParentSearchToggle", function (e) {
  e.preventDefault();
  $("#taskBoardFilterParentSearchPanel").toggleClass("d-none");
  if (!$("#taskBoardFilterParentSearchPanel").hasClass("d-none")) {
    $("#taskBoardFilterParentSearchInput").trigger("focus");
  }
});

$(document).on("click", "#taskBoardFilterParentChips", function (e) {
  if ($(e.target).closest("[data-remove-parent-id]").length) {
    return;
  }
  $("#taskBoardFilterParentSearchPanel").removeClass("d-none");
  $("#taskBoardFilterParentSearchInput").trigger("focus");
});

$(document).on("click", ".task-board-filter-block-parent", function (e) {
  if (
    $(e.target).closest(
      "#taskBoardFilterParentSearchPanel, #taskBoardFilterParentSearchToggle, [data-remove-parent-id]",
    ).length
  ) {
    return;
  }

  $("#taskBoardFilterParentSearchPanel").removeClass("d-none");
  $("#taskBoardFilterParentSearchInput").trigger("focus");
});

$(document).on("input", "#taskBoardFilterParentSearchInput", function () {
  boardFilterState.search.parent = String($(this).val() || "");
  renderBoardFilterParentSearchList();
});

$(document).on("change", ".task-board-filter-parent-checkbox", function () {
  enableBoardFilterPartB();
  var next = [];
  $(".task-board-filter-parent-checkbox:checked").each(function () {
    var id = Number($(this).val() || 0);
    if (id > 0) {
      next.push(id);
    }
  });
  boardFilterState.partB.parentIds = normalizeIdList(next);
  commitBoardFilters();
});

$(document).on("click", "[data-remove-parent-id]", function (e) {
  e.preventDefault();
  enableBoardFilterPartB();
  var removeId = Number($(this).data("removeParentId") || 0);
  boardFilterState.partB.parentIds = normalizeIdList(
    boardFilterState.partB.parentIds.filter(function (id) {
      return id !== removeId;
    }),
  );
  commitBoardFilters();
});

function isTaskBoardSettingsMobilePanel() {
  if (typeof window.matchMedia !== "function") {
    return (window.innerWidth || document.documentElement.clientWidth || 0) <= 767.98;
  }

  return window.matchMedia("(max-width: 767.98px)").matches;
}

function attachTaskBoardSettingsPanelToBody() {
  var panel = document.getElementById("taskBoardSettingsPanel");
  if (!panel || panel.parentNode === document.body) {
    return;
  }

  panel.__taskBoardSettingsOriginalParent = panel.parentNode;
  panel.__taskBoardSettingsOriginalNextSibling = panel.nextSibling;
  document.body.appendChild(panel);
}

function restoreTaskBoardSettingsPanelParent() {
  var panel = document.getElementById("taskBoardSettingsPanel");
  if (
    !panel ||
    !panel.__taskBoardSettingsOriginalParent ||
    panel.parentNode !== document.body
  ) {
    return;
  }

  var parent = panel.__taskBoardSettingsOriginalParent;
  var nextSibling = panel.__taskBoardSettingsOriginalNextSibling || null;
  parent.insertBefore(panel, nextSibling);
  panel.__taskBoardSettingsOriginalParent = null;
  panel.__taskBoardSettingsOriginalNextSibling = null;
}

function updateTaskBoardSettingsPanelPosition() {
  var panel = document.getElementById("taskBoardSettingsPanel");
  var button = document.getElementById("taskBoardSettingsBtn");
  if (!panel || !button) {
    return;
  }

  if (isTaskBoardSettingsMobilePanel()) {
    panel.classList.add("task-board-settings-panel-mobile");
    panel.style.setProperty("--task-board-settings-top", "68px");
    panel.style.setProperty("--task-board-settings-right", "10px");
    return;
  }

  panel.classList.remove("task-board-settings-panel-mobile");

  var buttonRect = button.getBoundingClientRect();
  var viewportWidth =
    window.innerWidth || document.documentElement.clientWidth || 0;
  var top = Math.max(Math.round(buttonRect.top - 18), 70);
  var right = Math.max(Math.round(viewportWidth - buttonRect.right), 8);

  panel.style.setProperty("--task-board-settings-top", String(top) + "px");
  panel.style.setProperty("--task-board-settings-right", String(right) + "px");
}

function syncTaskBoardSettingsPanelZoom() {
  var panel = document.getElementById("taskBoardSettingsPanel");
  if (!panel) {
    return;
  }

  panel.style.setProperty("--task-board-settings-panel-zoom", "100%");
}

function isTaskBoardSettingsPanelOpen() {
  return $("#taskBoardSettingsPanel").hasClass("show");
}

function setTaskBoardSettingsPanelState(isOpen) {
  var $wrap = $(".task-board-settings-wrap").first();
  var $button = $("#taskBoardSettingsBtn");
  var $panel = $("#taskBoardSettingsPanel");

  $wrap.toggleClass("show", isOpen);
  $button.attr("aria-expanded", isOpen ? "true" : "false");
  $panel.toggleClass("show", isOpen);
}

function openTaskBoardSettingsPanel() {
  if (isTaskBoardSettingsPanelOpen()) {
    return;
  }

  syncBoardViewSettingsCheckboxes();
  syncBoardZoomControls();
  attachTaskBoardSettingsPanelToBody();
  syncTaskBoardSettingsPanelZoom();
  setTaskBoardSettingsPanelState(true);
  updateTaskBoardSettingsPanelPosition();
}

function closeTaskBoardSettingsPanel(reason) {
  if (!isTaskBoardSettingsPanelOpen()) {
    return;
  }

  var panel = document.getElementById("taskBoardSettingsPanel");
  if (panel) {
    panel.classList.remove("task-board-settings-panel-mobile");
    panel.style.removeProperty("--task-board-settings-top");
    panel.style.removeProperty("--task-board-settings-right");
  }

  setTaskBoardSettingsPanelState(false);
  restoreTaskBoardSettingsPanelParent();
}

function toggleTaskBoardSettingsPanel() {
  var isOpen = isTaskBoardSettingsPanelOpen();

  if (isOpen) {
    closeTaskBoardSettingsPanel("button-click");
    return;
  }

  openTaskBoardSettingsPanel();
}

$(document).on("click", "#taskBoardSettingsBtn", function (e) {
  e.preventDefault();
  e.stopPropagation();
  toggleTaskBoardSettingsPanel();
});

$(document).on("keydown", "#taskBoardSettingsBtn", function (e) {
  if (e.key !== "Enter" && e.key !== " ") {
    return;
  }

  e.preventDefault();
  e.stopPropagation();
  toggleTaskBoardSettingsPanel();
});

$(document).on("mousedown", function (e) {
  var $panel = $("#taskBoardSettingsPanel");
  if (!$panel.hasClass("show")) {
    return;
  }

  if ($(e.target).closest(".task-board-settings-wrap, #taskBoardSettingsPanel").length) {
    return;
  }

  closeTaskBoardSettingsPanel("outside-click");
});

$(document).on("keydown", function (e) {
  if (e.key !== "Escape") {
    return;
  }

  var $panel = $("#taskBoardSettingsPanel");
  if (!$panel.hasClass("show")) {
    return;
  }

  closeTaskBoardSettingsPanel("escape");
});

$(document).on("change", ".task-board-view-field-checkbox", function () {
  var fieldKey = String($(this).attr("data-field-key") || "").trim();
  if (!fieldKey) {
    return;
  }

  boardViewFieldState[fieldKey] = $(this).is(":checked");
  saveBoardViewFieldsToCookie();
  applyBoardViewSettingsToAllCards();
  updateAllColumnCounts();
});

$(document).on("input change", "#taskBoardZoomRange", function () {
  setBoardZoomPercent($(this).val());
});

$(document).on("click", "#taskBoardZoomOutBtn", function () {
  setBoardZoomPercent(boardZoomPercent - boardZoomStep);
});

$(document).on("click", "#taskBoardZoomInBtn", function () {
  setBoardZoomPercent(boardZoomPercent + boardZoomStep);
});

$(document).on("click", "#taskBoardZoomResetBtn", function () {
  setBoardZoomPercent(boardZoomDefault);
});

$(window).on("resize.taskBoard", function () {
  updateAllColumnCounts();
  syncBoardCardDraggableState();
  if (isTaskBoardSettingsPanelOpen()) {
    updateTaskBoardSettingsPanelPosition();
  }
});

$(window).on("scroll.taskBoardSettings", function () {
  if (isTaskBoardSettingsPanelOpen()) {
    updateTaskBoardSettingsPanelPosition();
  }
});

function safeRefreshBoardUi() {
  try {
    captureBoardStatusColumnsFromDom();
    loadBoardGroupFromCookie();
    renderBoardGroupingLayout();
  } catch (e) {}

  try {
    updateAllColumnCounts();
  } catch (e) {}

  try {
    refreshEmptyBoardState();
  } catch (e) {}

  try {
    refreshCardItemKeys();
  } catch (e) {}

  try {
    loadBoardZoomFromStorage();
    applyBoardZoom();
    syncTaskBoardSettingsPanelZoom();
  } catch (e) {}

  try {
    loadBoardViewFieldsFromCookie();
    syncBoardViewSettingsCheckboxes();
    applyBoardViewSettingsToAllCards();
  } catch (e) {}

  try {
    boardSearchQuery = String($("#taskBoardSearchInput").val() || "");
    ensureBoardFilterMenu();
    loadBoardFiltersFromCookie();
    renderBoardFilterUi();
    applyBoardFilters();
  } catch (e) {}

  try {
    syncBoardCardDraggableState();
  } catch (e) {}
}

function syncBoardCardDraggableState() {
  var allowDrag =
    canEdit &&
    isBoardGroupedByStatus() &&
    !(typeof isTouchBoardViewport === "function" && isTouchBoardViewport());

  $app
    .find(".task-item-card")
    .attr("draggable", allowDrag ? "true" : "false");
}

function bindTaskBoardTouchSwipeScroll() {
  var scroller = document.getElementById("taskBoardApp");
  if (!scroller || scroller.__taskBoardTouchSwipeBound) {
    return;
  }

  var touchState = {
    active: false,
    axis: "",
    startX: 0,
    startY: 0,
    startScrollLeft: 0,
    moved: false,
  };

  function isBoardSwipeViewport() {
    if (typeof isTouchBoardViewport === "function" && isTouchBoardViewport()) {
      return true;
    }

    if (typeof window.matchMedia !== "function") {
      return (window.innerWidth || document.documentElement.clientWidth || 0) <= 991.98;
    }

    return window.matchMedia("(max-width: 991.98px)").matches;
  }

  function isInteractiveSwipeTarget(target) {
    if (!target || typeof target.closest !== "function") {
      return false;
    }

    return !!target.closest(
      "input, textarea, select, option, button, a, label, .dropdown-menu, .modal, .tox, .task-composer, .task-board-toolbar",
    );
  }

  function resetTouchState() {
    touchState.active = false;
    touchState.axis = "";
    touchState.moved = false;
    document.body.classList.remove("task-board-touch-scrolling");
  }

  scroller.addEventListener(
    "touchstart",
    function (event) {
      if (!isBoardSwipeViewport()) {
        resetTouchState();
        return;
      }

      if (!event.touches || event.touches.length !== 1) {
        resetTouchState();
        return;
      }

      if (isInteractiveSwipeTarget(event.target)) {
        resetTouchState();
        return;
      }

      var touch = event.touches[0];
      touchState.active = true;
      touchState.axis = "";
      touchState.startX = Number(touch.clientX || 0);
      touchState.startY = Number(touch.clientY || 0);
      touchState.startScrollLeft = scroller.scrollLeft;
      touchState.moved = false;
    },
    { passive: true, capture: true },
  );

  scroller.addEventListener(
    "touchmove",
    function (event) {
      if (!touchState.active || !event.touches || event.touches.length !== 1) {
        return;
      }

      var touch = event.touches[0];
      var deltaX = Number(touch.clientX || 0) - touchState.startX;
      var deltaY = Number(touch.clientY || 0) - touchState.startY;
      var absX = Math.abs(deltaX);
      var absY = Math.abs(deltaY);

      if (!touchState.axis) {
        if (absX < 5 && absY < 5) {
          return;
        }

        touchState.axis = absX > absY ? "x" : "y";
      }

      if (touchState.axis !== "x") {
        return;
      }

      document.body.classList.add("task-board-touch-scrolling");
      scroller.scrollLeft = touchState.startScrollLeft - deltaX;
      touchState.moved = true;
      event.preventDefault();
      event.stopPropagation();
    },
    { passive: false, capture: true },
  );

  scroller.addEventListener("touchend", resetTouchState, {
    passive: true,
    capture: true,
  });

  scroller.addEventListener("touchcancel", resetTouchState, {
    passive: true,
    capture: true,
  });

  scroller.__taskBoardTouchSwipeBound = true;
}

// Labels are handled in task item submenu panel.

if (!canAdd) {
  $app.find(".task-open-composer-btn").prop("disabled", true);
  $("#taskOpenCreateStatusBtn").prop("disabled", true);
  $("#taskCreateStatusSubmit").prop("disabled", true);
  if (!canEdit) {
    $("#taskCreateStatusSubmitMobile").prop("disabled", true);
  }
}

state.projectKey =
  state.projectKey && typeof state.projectKey === "object"
    ? state.projectKey
    : { id: 0, project_key: "" };
state.projectKey.project_key = normalizeProjectKey(
  state.projectKey.project_key || "",
);
$("#taskProjectKeyInput").val(state.projectKey.project_key);
normalizeStatusLabels(state.statusLabels);
safeRefreshBoardUi();
bindTaskBoardTouchSwipeScroll();
window.setTimeout(safeRefreshBoardUi, 120);
window.setTimeout(safeRefreshBoardUi, 420);

function resizeItemDetailTitleInput() {
  var titleInput = document.getElementById("taskItemDetailTitleInput");
  if (!titleInput) {
    return;
  }

  titleInput.style.height = "auto";
  titleInput.style.height = titleInput.scrollHeight + "px";
}

$(document).on("input", "#taskItemDetailTitleInput", function () {
  resizeItemDetailTitleInput();
});

$(window).on("resize", function () {
  resizeItemDetailTitleInput();
});

$(document).on("shown.bs.modal", "#taskItemDetailModal", function () {
  resizeItemDetailTitleInput();
});
