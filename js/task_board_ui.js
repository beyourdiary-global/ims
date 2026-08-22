function applyBoardViewSettingsToCard($card) {
  if (!$card || !$card.length) {
    return;
  }

  var labelIds = getItemLabelsFromCard($card);
  var $labelRow = $card.find(".task-item-label-row").first();
  if (labelIds.length && $labelRow.length) {
    var labelHtml = "";
    for (var i = 0; i < labelIds.length; i++) {
      var id = labelIds[i];
      for (var j = 0; j < state.labels.length; j++) {
        var item = state.labels[j] || {};
        if (Number(item.id || 0) === id) {
          labelHtml +=
            '<span class="task-label-pill" style="' +
            labelPillStyle(item.color, "#DCE8FF") +
            '">' +
            escHtml(String(item.name || "")) +
            "</span>";
          break;
        }
      }
    }
    if (labelHtml) {
      $labelRow.html(labelHtml);
    }
  }
  if ($labelRow.length) {
    $labelRow.toggleClass(
      "d-none",
      !isBoardViewFieldEnabled("labels") || !labelIds.length,
    );
  }

  var $meta = $card.find(".task-item-meta").first();
  if (!$meta.length) {
    $meta = $('<div class="task-item-meta"></div>');
    $card.append($meta);
  }

  $card.find(".task-item-due-date").remove();

  var $metaLeft = $meta.find(".task-item-meta-left").first();
  if (!$metaLeft.length) {
    $metaLeft = $('<div class="task-item-meta-left"></div>');
    $meta.prepend($metaLeft);
  }

  var workTypeName = String($card.attr("data-work-type-name") || "Task").trim();
  var workTypeIcon = normalizeWorkTypeIcon(
    $card.attr("data-work-type-icon") || "",
    workTypeName,
  );

  var workTypeId = Number($card.attr("data-work-type-id") || 0);
  var $typeWrap = $metaLeft.find(".task-item-type-wrap").first();
  if (!$typeWrap.length) {
    $typeWrap = $(
      '<div class="dropdown task-item-type-wrap"><button class="btn task-item-type-btn task-work-type-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"></button><ul class="dropdown-menu task-work-type-menu task-item-work-type-menu"></ul></div>',
    );
    $metaLeft.prepend($typeWrap);
  }

  $metaLeft.children(".task-type-icon").remove();

  $typeWrap
    .find(".task-item-type-btn")
    .attr("title", workTypeName)
    .attr("data-work-type-id", workTypeId)
    .attr("data-work-type-name", workTypeName)
    .attr(
      "data-work-type-remark",
      String($card.attr("data-work-type-remark") || ""),
    )
    .attr("data-work-type-icon", workTypeIcon)
    .attr("title", workTypeName)
    .html(workTypeIconHtml(workTypeIcon, workTypeName, "task-type-pill-icon"));

  $typeWrap.find(".task-item-work-type-menu").html(workTypeMenuHtml());

  $typeWrap.toggleClass("d-none", !isBoardViewFieldEnabled("work_type"));

  var key = String($card.attr("data-work-item-key") || "").trim();
  var $key = $metaLeft.find(".task-item-key").first();
  if (!$key.length) {
    $key = $('<span class="task-item-key"></span>');
    $metaLeft.append($key);
  }
  $key
    .text(key)
    .toggleClass("d-none", !isBoardViewFieldEnabled("work_item_key") || !key);

  var $metaRight = $meta.find(".task-item-meta-right").first();
  if (!$metaRight.length) {
    $metaRight = $('<div class="task-item-meta-right"></div>');
    $meta.append($metaRight);
  }

  var $assigneeWrap = $meta.find(".task-item-assignee-wrap").first();
  if ($assigneeWrap.length) {
    var assigneeUserId = Number($card.attr("data-assignee-user-id") || 0);
    var assigneeName = String(
      $card.attr("data-assignee-name") ||
        $assigneeWrap.find(".task-item-assignee-btn").attr("title") ||
        "Unassigned",
    ).trim();
    if (!assigneeName) {
      assigneeName = "Unassigned";
    }

    $card.attr("data-assignee-name", assigneeName);
    $assigneeWrap
      .find(".task-item-assignee-btn")
      .attr("title", assigneeName)
      .attr("data-user-id", assigneeUserId)
      .toggleClass("task-assignee-pill-unassigned", assigneeUserId <= 0)
      .html(assigneeButtonInner(assigneeUserId, assigneeName));

    $assigneeWrap.toggleClass("d-none", !isBoardViewFieldEnabled("assignee"));

    if (!$assigneeWrap.parent().is($metaRight)) {
      $metaRight.append($assigneeWrap);
    }
  }

  var $priorityWrap = $meta.find(".task-item-priority-wrap").first();
  if (!$priorityWrap.length) {
    $priorityWrap = $('<span class="task-item-priority-wrap"></span>');
  }

  if (!$priorityWrap.parent().is($metaRight)) {
    if ($assigneeWrap.length) {
      $priorityWrap.insertBefore($assigneeWrap);
    } else {
      $metaRight.append($priorityWrap);
    }
  }

  $priorityWrap
    .html(
      priorityIconGlyphHtml(String($card.attr("data-priority") || "Medium")),
    )
    .toggleClass("d-none", !isBoardViewFieldEnabled("priority"));

  var rowsHtml = buildCardFieldRowsHtml($card);
  var $fieldList = $card.find(".task-item-field-list").first();
  if (!$fieldList.length) {
    $fieldList = $('<div class="task-item-field-list"></div>');
  }

  var $labelRowForFieldList = $card.find(".task-item-label-row").first();
  if ($labelRowForFieldList.length) {
    $fieldList.insertAfter($labelRowForFieldList);
  } else {
    var $head = $card.find(".task-item-head").first();
    if ($head.length) {
      $fieldList.insertAfter($head);
    } else {
      $card.prepend($fieldList);
    }
  }

  if (rowsHtml) {
    $fieldList.html(rowsHtml).removeClass("d-none");

    // Ensure parent dropdown reflects the card's current parent relation.
    var currentParentId = Number($card.attr("data-parent-item-id") || 0);
    var $parentSelect = $fieldList.find(".task-item-parent-select").first();
    if ($parentSelect.length) {
      $parentSelect.val(String(currentParentId));

      // If current parent option is missing, inject a selected fallback option.
      if (String($parentSelect.val() || "") !== String(currentParentId)) {
        var parentDisplay = String(
          $card.attr("data-parent-display") || "",
        ).trim();
        if (currentParentId > 0) {
          if (!parentDisplay) {
            parentDisplay = buildWorkItemKey(currentParentId);
          }
          if (parentDisplay) {
            $parentSelect.append(
              $("<option></option>")
                .val(String(currentParentId))
                .text(parentDisplay),
            );
            $parentSelect.val(String(currentParentId));
          }
        } else {
          $parentSelect.val("0");
        }
      }
    }
  } else {
    $fieldList.addClass("d-none").empty();
  }

  $meta.insertAfter($fieldList);
  $meta.toggleClass(
    "d-none",
    !isBoardViewFieldEnabled("work_type") &&
      !isBoardViewFieldEnabled("work_item_key") &&
      !isBoardViewFieldEnabled("priority") &&
      !isBoardViewFieldEnabled("assignee"),
  );
}

function applyBoardViewSettingsToAllCards() {
  $app.find(".task-item-card").each(function () {
    applyBoardViewSettingsToCard($(this));
  });
}

function statusLabelsBySearch(term) {
  var query = String(term || "")
    .trim()
    .toLowerCase();
  if (!query) {
    return state.statusLabels.slice();
  }

  return state.statusLabels.filter(function (label) {
    return (
      String(label.name || "")
        .toLowerCase()
        .indexOf(query) !== -1
    );
  });
}

function syncKnownLabels(newLabels) {
  var source = Array.isArray(newLabels) ? newLabels : [];
  if (!source.length) {
    return;
  }

  var byId = {};
  for (var i = 0; i < state.labels.length; i++) {
    var existing = state.labels[i] || {};
    var existingId = Number(existing.id || 0);
    if (existingId > 0) {
      byId[existingId] = {
        id: existingId,
        name: String(existing.name || ""),
      };
    }
  }

  for (var j = 0; j < source.length; j++) {
    var incoming = source[j] || {};
    var incomingId = Number(incoming.id || 0);
    var incomingName = String(incoming.name || "").trim();
    if (incomingId > 0 && incomingName) {
      byId[incomingId] = { id: incomingId, name: incomingName };
    }
  }

  state.labels = Object.keys(byId)
    .map(function (key) {
      return byId[Number(key)];
    })
    .sort(function (a, b) {
      return String(a.name || "").localeCompare(String(b.name || ""));
    });
}

function renderInlineLabelPanel($card, $submenu) {
  var selected = getItemLabelsFromCard($card);
  var selectedNames = [];
  for (var i = 0; i < state.labels.length; i++) {
    var label = state.labels[i] || {};
    var labelId = Number(label.id || 0);
    if (selected.indexOf(labelId) !== -1) {
      selectedNames.push(String(label.name || ""));
    }
  }

  var chipsHtml = "";
  for (var c = 0; c < selectedNames.length; c++) {
    if (!selectedNames[c]) {
      continue;
    }
    chipsHtml +=
      '<span class="task-inline-label-chip">' +
      escHtml(selectedNames[c]) +
      "</span>";
  }

  var panelHtml =
    '<div class="task-inline-label-panel" data-item-id="' +
    Number($card.data("itemId") || 0) +
    '">' +
    '<div class="task-inline-label-selected">' +
    (chipsHtml ||
      '<span class="task-inline-label-empty">No labels selected</span>') +
    "</div>" +
    '<input type="text" class="form-control form-control-sm task-inline-label-search" placeholder="Type label name">' +
    '<div class="task-inline-label-create-row d-none"><button type="button" class="btn btn-sm btn-outline-primary task-inline-label-create-btn">Create</button> <span class="task-inline-label-create-name"></span></div>' +
    '<div class="task-inline-label-list"></div>' +
    '<div class="text-end mt-2"><button type="button" class="btn btn-sm btn-primary task-inline-label-save">Save</button></div>' +
    "</div>";

  $submenu.find(".task-item-label-submenu-content").html(panelHtml);
  labelsPanelState.itemId = Number($card.data("itemId") || 0);
  labelsPanelState.selected = selected.slice();
  refreshInlineLabelList($submenu);
}

function refreshInlineLabelList($submenu) {
  var $panel = $submenu.find(".task-inline-label-panel");
  if (!$panel.length) {
    return;
  }

  var search = String(
    $panel.find(".task-inline-label-search").val() || "",
  ).trim();
  var matches = labelsBySearch(search);
  var listHtml = "";

  for (var i = 0; i < matches.length; i++) {
    var label = matches[i] || {};
    var labelId = Number(label.id || 0);
    var labelName = String(label.name || "").trim();
    if (!labelId || !labelName) {
      continue;
    }

    var checked = labelsPanelState.selected.indexOf(labelId) !== -1;
    listHtml +=
      '<label class="task-inline-label-option">' +
      '<input class="form-check-input task-inline-label-checkbox" type="checkbox" value="' +
      labelId +
      '" ' +
      (checked ? "checked" : "") +
      ">" +
      '<span class="task-inline-label-option-name task-label-pill" style="' +
      labelPillStyle(label.color, "#DCE8FF") +
      '">' +
      escHtml(labelName) +
      "</span>" +
      (state.isProjectOwner
        ? '<button type="button" class="btn task-inline-label-delete-btn" data-label-id="' +
      labelId +
      '" title="Delete label"><i class="fa-regular fa-trash-can"></i></button>'
        : "") +
      "</label>";
  }

  $panel
    .find(".task-inline-label-list")
    .html(listHtml || '<div class="task-label-empty">No labels found.</div>');

  var canCreate =
    search.length > 0 &&
    !state.labels.some(function (label) {
      return String(label.name || "").toLowerCase() === search.toLowerCase();
    });

  $panel
    .find(".task-inline-label-create-row")
    .toggleClass("d-none", !canCreate || !state.isProjectOwner);
  $panel
    .find(".task-inline-label-create-name")
    .text(canCreate ? '"' + search + '"' : "");
}

function renderInlineStatusLabelPanel($card, $submenu) {
  var selected = getItemStatusLabelIdsFromCard($card);
  var selectedNames = [];
  for (var i = 0; i < state.statusLabels.length; i++) {
    var statusLabel = state.statusLabels[i] || {};
    var statusLabelId = Number(statusLabel.id || 0);
    if (selected.indexOf(statusLabelId) !== -1) {
      selectedNames.push(String(statusLabel.name || ""));
    }
  }

  var chipsHtml = "";
  for (var c = 0; c < selectedNames.length; c++) {
    if (!selectedNames[c]) {
      continue;
    }
    var statusLabelInfo = getStatusLabelById(selected[c]) || {};
    chipsHtml +=
      '<span class="task-inline-label-chip task-label-pill" style="' +
      labelPillStyle(statusLabelInfo.color, "#DCE8FF") +
      '">' +
      escHtml(selectedNames[c]) +
      "</span>";
  }

  var panelHtml =
    '<div class="task-inline-label-panel task-inline-status-panel" data-item-id="' +
    Number($card.data("itemId") || 0) +
    '">' +
    '<div class="task-inline-label-selected">' +
    (chipsHtml ||
      '<span class="task-inline-label-empty">No task status selected</span>') +
    "</div>" +
    '<input type="text" class="form-control form-control-sm task-inline-status-search" placeholder="Type task status name">' +
    '<div class="task-inline-label-create-row d-none"><button type="button" class="btn btn-sm btn-outline-primary task-inline-status-create-btn">Create</button> <span class="task-inline-label-create-name"></span></div>' +
    '<div class="task-inline-label-list task-inline-status-list"></div>' +
    '<div class="text-end mt-2"><button type="button" class="btn btn-sm btn-primary task-inline-status-save">Save</button></div>' +
    "</div>";

  $submenu.find(".task-item-status-label-submenu-content").html(panelHtml);
  statusLabelsPanelState.itemId = Number($card.data("itemId") || 0);
  statusLabelsPanelState.selected = selected.slice();
  refreshInlineStatusLabelList($submenu);
}

function refreshInlineStatusLabelList($submenu) {
  var $panel = $submenu.find(".task-inline-status-panel");
  if (!$panel.length) {
    return;
  }

  var search = String(
    $panel.find(".task-inline-status-search").val() || "",
  ).trim();
  var matches = statusLabelsBySearch(search);
  var listHtml = "";

  for (var i = 0; i < matches.length; i++) {
    var label = matches[i] || {};
    var labelId = Number(label.id || 0);
    var labelName = String(label.name || "").trim();
    if (!labelId || !labelName) {
      continue;
    }

    var checked = statusLabelsPanelState.selected.indexOf(labelId) !== -1;
    listHtml +=
      '<label class="task-inline-label-option">' +
      '<input class="form-check-input task-inline-status-checkbox" type="checkbox" value="' +
      labelId +
      '" ' +
      (checked ? "checked" : "") +
      ">" +
      '<span class="task-inline-label-option-name task-label-pill" style="' +
      labelPillStyle(label.color, "#DCE8FF") +
      '">' +
      escHtml(labelName) +
      "</span>" +
      (state.isProjectOwner
        ? '<button type="button" class="btn task-inline-label-delete-btn task-inline-status-delete-btn" data-status-label-id="' +
      labelId +
      '" title="Delete task status"><i class="fa-regular fa-trash-can"></i></button>'
        : "") +
      "</label>";
  }

  $panel
    .find(".task-inline-status-list")
    .html(
      listHtml || '<div class="task-label-empty">No task status found.</div>',
    );

  var canCreate =
    search.length > 0 &&
    !state.statusLabels.some(function (label) {
      return String(label.name || "").toLowerCase() === search.toLowerCase();
    });

  $panel
    .find(".task-inline-label-create-row")
    .toggleClass("d-none", !canCreate || !state.isProjectOwner);
  $panel
    .find(".task-inline-label-create-name")
    .text(canCreate ? '"' + search + '"' : "");
}

function removeLabelFromState(labelId) {
  var targetId = Number(labelId || 0);
  if (!targetId) {
    return;
  }

  state.labels = (Array.isArray(state.labels) ? state.labels : []).filter(
    function (item) {
      return Number((item && item.id) || 0) !== targetId;
    },
  );

  labelsPanelState.selected = (
    Array.isArray(labelsPanelState.selected) ? labelsPanelState.selected : []
  ).filter(function (id) {
    return Number(id || 0) !== targetId;
  });

  itemDetailModalState.selectedLabelIds = (
    Array.isArray(itemDetailModalState.selectedLabelIds)
      ? itemDetailModalState.selectedLabelIds
      : []
  ).filter(function (id) {
    return Number(id || 0) !== targetId;
  });

  $app.find(".task-item-card").each(function () {
    var $card = $(this);
    var ids = getItemLabelsFromCard($card).filter(function (id) {
      return Number(id || 0) !== targetId;
    });

    var labels = [];
    for (var i = 0; i < state.labels.length; i++) {
      var item = state.labels[i] || {};
      var id = Number(item.id || 0);
      if (id > 0 && ids.indexOf(id) !== -1) {
        labels.push({ id: id, name: String(item.name || "") });
      }
    }

    setCardLabels($card, labels);
  });
}

function removeTaskStatusLabelFromCards(statusLabelId) {
  var targetId = Number(statusLabelId || 0);
  if (!targetId) {
    return;
  }

  $app.find(".task-item-card").each(function () {
    var $card = $(this);
    var ids = getItemStatusLabelIdsFromCard($card).filter(function (id) {
      return Number(id || 0) !== targetId;
    });
    setCardTaskStatusLabels($card, ids);
  });

  removeStatusLabelFromSelection(targetId);
}

function normalizeStatusLabels(list) {
  var source = Array.isArray(list) ? list : [];
  var byId = {};
  for (var i = 0; i < source.length; i++) {
    var item = source[i] || {};
    var id = Number(item.id || 0);
    var name = String(item.name || "").trim();
    if (!id || !name) {
      continue;
    }
    byId[id] = { id: id, name: name, color: item.color };
  }

  state.statusLabels = Object.keys(byId)
    .map(function (key) {
      return byId[Number(key)];
    })
    .sort(function (a, b) {
      return String(a.name || "").localeCompare(String(b.name || ""));
    });

  var allowedIds = state.statusLabels.map(function (item) {
    return Number(item.id || 0);
  });
  itemDetailModalState.selectedStatusLabelIds = normalizeStatusLabelIdList(
    itemDetailModalState.selectedStatusLabelIds,
  ).filter(function (id) {
    return allowedIds.indexOf(id) !== -1;
  });
  renderStatusLabelChips();
}

function normalizeParentOptions(list) {
  var source = Array.isArray(list) ? list : [];
  var out = [];
  var seen = {};
  for (var i = 0; i < source.length; i++) {
    var item = source[i] || {};
    var id = Number(item.id || 0);
    if (!id || seen[id]) {
      continue;
    }

    var title = String(item.title || "").trim();
    var key = String(item.work_item_key || "").trim();
    var workTypeName = String(item.work_type_name || "Epic").trim() || "Epic";
    out.push({
      id: id,
      title: title,
      work_item_key: key,
      work_type_name: workTypeName,
      work_type_svg_icon: normalizeWorkTypeIcon(
        item.work_type_svg_icon,
        workTypeName,
      ),
      display: String((key ? key + " " : "") + title).trim(),
    });
    seen[id] = true;
  }

  return out.sort(function (a, b) {
    return Number(b.id || 0) - Number(a.id || 0);
  });
}

function getBoardEpicParentOptions(excludeItemId) {
  var excludeId = Number(excludeItemId || 0);
  var items = [];
  $app.find(".task-item-card").each(function () {
    var $card = $(this);
    var itemId = Number($card.data("itemId") || 0);
    var workTypeName = String($card.attr("data-work-type-name") || "")
      .trim()
      .toLowerCase();
    if (!itemId || itemId === excludeId || workTypeName !== "epic") {
      return;
    }

    var key = String($card.attr("data-work-item-key") || "").trim();
    if (!key) {
      key = buildWorkItemKey(itemId);
    }

    items.push({
      id: itemId,
      title: String($card.find(".task-item-title").text() || "").trim(),
      work_item_key: key,
      work_type_name: String(
        $card.attr("data-work-type-name") || "Epic",
      ).trim(),
      work_type_svg_icon: String($card.attr("data-work-type-icon") || ""),
    });
  });

  return normalizeParentOptions(items);
}

function updateCardParentSubmenuToggle($card, parentItemId, parentDisplay) {
  var hasParent = Number(parentItemId || 0) > 0;
  $card.attr("data-parent-item-id", Number(parentItemId || 0));

  if (typeof parentDisplay === "string") {
    $card.attr("data-parent-display", String(parentDisplay || "").trim());
  } else if (!hasParent) {
    $card.attr("data-parent-display", "");
  }

  var $toggle = $card.find(".task-item-parent-submenu-toggle").first();
  if (!$toggle.length) {
    applyBoardViewSettingsToCard($card);
    return;
  }

  $toggle
    .attr("data-has-parent", hasParent ? "1" : "0")
    .html(
      (hasParent ? "Change parent" : "Link parent") +
        ' <i class="fa-solid fa-chevron-right task-submenu-chevron"></i>',
    );

  applyBoardViewSettingsToCard($card);
}

function renderParentSubmenu($card, $submenu) {
  var itemId = Number($card.data("itemId") || 0);
  var currentParentId = Number($card.attr("data-parent-item-id") || 0);

  var html =
    '<div class="task-item-parent-submenu-panel" data-item-id="' +
    itemId +
    '" data-current-parent-id="' +
    currentParentId +
    '">' +
    '<div class="task-item-parent-search-wrap"><i class="fa-solid fa-magnifying-glass"></i><input type="text" class="form-control form-control-sm task-item-parent-search-input" placeholder="Search parent name"></div>' +
    '<div class="task-item-parent-option-list"></div>' +
    "</div>";

  $submenu.find(".task-item-parent-submenu-content").html(html);
  refreshParentSubmenuList($submenu);
}

function refreshParentSubmenuList($submenu) {
  var $panel = $submenu.find(".task-item-parent-submenu-panel").first();
  if (!$panel.length) {
    return;
  }

  var itemId = Number($panel.attr("data-item-id") || 0);
  var currentParentId = Number($panel.attr("data-current-parent-id") || 0);
  var search = String($panel.find(".task-item-parent-search-input").val() || "")
    .trim()
    .toLowerCase();
  var options = getBoardEpicParentOptions(itemId);
  var html = "";
  var visibleCount = 0;
  var parentVisibleCount = 0;
  var hasSelectedOption = currentParentId <= 0;

  if (!search || "none".indexOf(search) !== -1) {
    visibleCount++;
    html +=
      '<button type="button" class="dropdown-item task-item-parent-option' +
      (currentParentId === 0 ? " active" : "") +
      '" data-parent-item-id="0">None</button>';
  }

  for (var i = 0; i < options.length; i++) {
    var option = options[i] || {};
    var parentId = Number(option.id || 0);
    var label = String(option.display || option.title || "").trim();
    if (!parentId || !label) {
      continue;
    }

    if (parentId === currentParentId) {
      hasSelectedOption = true;
    }

    if (search && label.toLowerCase().indexOf(search) === -1) {
      continue;
    }

    visibleCount++;
    parentVisibleCount++;
    html +=
      '<button type="button" class="dropdown-item task-item-parent-option' +
      (currentParentId === parentId ? " active" : "") +
      '" data-parent-item-id="' +
      parentId +
      '">' +
      escHtml(label) +
      "</button>";
  }

  if (currentParentId > 0 && !hasSelectedOption) {
    var fallbackDisplay = String(
      $(".task-item-card[data-item-id='" + itemId + "']").attr(
        "data-parent-display",
      ) || "",
    ).trim();
    if (!fallbackDisplay) {
      fallbackDisplay = "Parent #" + String(currentParentId);
    }

    if (!search || fallbackDisplay.toLowerCase().indexOf(search) !== -1) {
      visibleCount++;
      parentVisibleCount++;
      html +=
        '<button type="button" class="dropdown-item task-item-parent-option active" data-parent-item-id="' +
        currentParentId +
        '">' +
        escHtml(fallbackDisplay) +
        "</button>";
    }
  }

  if (!visibleCount) {
    html =
      '<div class="task-item-parent-empty">No matching parent found.</div>';
  }

  $panel
    .find(".task-item-parent-option-list")
    .toggleClass("is-scrollable", parentVisibleCount > 5)
    .html(html);
}

function renderDetailParentOptions(filterText) {
  var query = String(filterText || "")
    .trim()
    .toLowerCase();
  var selectedId = Number(itemDetailModalState.parentItemId || 0);
  var options = normalizeParentOptions(itemDetailModalState.parentOptions);
  var normalizeParentDisplayLabel = function (value) {
    var label = String(value || "").trim();
    if (!label) {
      return "None";
    }
    if (label.toLowerCase() === "none") {
      return "None";
    }
    return label;
  };
  var html =
    '<button type="button" class="dropdown-item task-item-detail-parent-option' +
    (selectedId === 0 ? " active" : "") +
    '" data-parent-item-id="0"><span class="task-item-detail-parent-option-name">None</span></button>';
  var visibleCount = 1;
  if (!options.length) {
    html += '<div class="task-label-empty">No Epic items available.</div>';
  }

  for (var i = 0; i < options.length; i++) {
    var option = options[i] || {};
    var parentId = Number(option.id || 0);
    var label = normalizeParentDisplayLabel(option.display || option.title);
    var workTypeName = String(option.work_type_name || "Epic").trim() || "Epic";
    var workTypeIcon = normalizeWorkTypeIcon(
      option.work_type_svg_icon,
      workTypeName,
    );
    if (!parentId || !label) {
      continue;
    }

    if (query && label.toLowerCase().indexOf(query) === -1) {
      continue;
    }

    visibleCount++;
    html +=
      '<button type="button" class="dropdown-item task-item-detail-parent-option' +
      (selectedId === parentId ? " active" : "") +
      '" data-parent-item-id="' +
      parentId +
      '"><span class="task-item-detail-parent-option-content">' +
      workTypeIconHtml(
        workTypeIcon,
        workTypeName,
        "task-item-detail-parent-option-icon",
      ) +
      '<span class="task-item-detail-parent-option-name">' +
      escHtml(label) +
      "</span></span></button>";
  }

  $("#taskItemDetailParentOptionList")
    .toggleClass("is-scrollable", visibleCount > 5)
    .html(
      html || '<div class="task-label-empty">No matching parent found.</div>',
    );
}

function renderDetailParentDropdown(selectedParentId, parentOptions) {
  var selectedId = Number(selectedParentId || 0);
  var options = normalizeParentOptions(parentOptions);
  var normalizeParentDisplayLabel = function (value) {
    var label = String(value || "").trim();
    if (!label) {
      return "None";
    }
    if (label.toLowerCase() === "none") {
      return "None";
    }
    return label;
  };
  var selectedDisplay = "None";
  var selectedWorkTypeName = "Epic";
  var selectedWorkTypeIcon = normalizeWorkTypeIcon("", selectedWorkTypeName);
  var hasSelectedOption = selectedId === 0;

  itemDetailModalState.parentOptions = options.slice();

  for (var i = 0; i < options.length; i++) {
    var opt = options[i] || {};
    if (Number(opt.id || 0) !== selectedId) {
      continue;
    }

    selectedDisplay = normalizeParentDisplayLabel(opt.display || opt.title);
    selectedWorkTypeName = String(opt.work_type_name || "Epic").trim() || "Epic";
    selectedWorkTypeIcon = normalizeWorkTypeIcon(
      opt.work_type_svg_icon,
      selectedWorkTypeName,
    );
    hasSelectedOption = true;
    break;
  }

  if (!hasSelectedOption) {
    selectedId = 0;
  }

  itemDetailModalState.parentItemId = selectedId;
  $("#taskItemDetailParentSelectedText").html(
    selectedId > 0
      ? '<span class="task-item-detail-parent-selected-value">' +
          workTypeIconHtml(
            selectedWorkTypeIcon,
            selectedWorkTypeName,
            "task-item-detail-parent-option-icon",
          ) +
          '<span class="task-item-detail-parent-selected-label">' +
          escHtml(selectedDisplay) +
          "</span></span>"
      : escHtml(selectedDisplay),
  );
  $("#taskItemDetailParentSearchInput").val("");
  renderDetailParentOptions("");

  var $card = $(itemDetailModalState.cardEl || null);
  if ($card.length) {
    updateCardParentSubmenuToggle($card, selectedId, selectedDisplay);
  }
}

function normalizeWebLinks(list) {
  var source = Array.isArray(list) ? list : [];
  var rows = [];
  for (var i = 0; i < source.length; i++) {
    var item = source[i] || {};
    var id = Number(item.id || 0);
    var url = String(item.url || "").trim();
    if (!id || !url) {
      continue;
    }

    rows.push({
      id: id,
      url: url,
      link_text: String(item.link_text || item.title || "").trim(),
    });
  }
  return rows;
}

function webLinkHostname(rawUrl) {
  var value = String(rawUrl || "").trim();
  if (!value) {
    return "";
  }

  try {
    return String(new URL(value).hostname || "").trim();
  } catch (e) {
    try {
      return String(new URL("https://" + value).hostname || "").trim();
    } catch (err) {
      return "";
    }
  }
}

function normalizeWebLinkHref(rawUrl) {
  var value = String(rawUrl || "").trim();
  if (!value) {
    return "";
  }
  if (/^https?:\/\//i.test(value)) {
    return value;
  }
  return "https://" + value;
}

function webLinkFaviconUrl(rawUrl) {
  var hostname = webLinkHostname(rawUrl);
  if (!hostname) {
    return "";
  }
  return (
    "https://icons.duckduckgo.com/ip3/" + encodeURIComponent(hostname) + ".ico"
  );
}

function webLinkFaviconFallbackUrl(rawUrl) {
  var href = normalizeWebLinkHref(rawUrl);
  if (!href) {
    return "";
  }
  return (
    "https://www.google.com/s2/favicons?sz=64&domain_url=" +
    encodeURIComponent(href)
  );
}

function renderWebLinksSection() {
  var links = normalizeWebLinks(itemDetailModalState.webLinks);
  itemDetailModalState.webLinks = links;

  var hasLinks = links.length > 0;
  var showSection = hasLinks || !!itemDetailModalState.webLinkEditorOpen;
  $("#taskItemWebLinksSection").toggleClass("d-none", !showSection);
  $("#taskItemWebLinkEditor").toggleClass(
    "d-none",
    !itemDetailModalState.webLinkEditorOpen,
  );

  var html = "";
  for (var i = 0; i < links.length; i++) {
    var row = links[i] || {};
    var label = String(row.link_text || row.url || "").trim() || row.url;
    var href = normalizeWebLinkHref(row.url);
    var favicon = webLinkFaviconUrl(row.url);
    var faviconFallback = webLinkFaviconFallbackUrl(row.url);
    html +=
      '<div class="task-item-web-link-row">' +
      '<div class="task-item-web-link-main">' +
      '<a class="task-item-web-link-icon-link" href="' +
      escHtml(href || "#") +
      '" target="_blank" rel="noopener noreferrer" title="Open link">' +
      '<span class="task-item-web-link-favicon-wrap">' +
      (favicon
        ? '<img class="task-item-web-link-favicon" src="' +
          escHtml(favicon) +
          '" data-fallback-src="' +
          escHtml(faviconFallback) +
          '" alt="">'
        : "") +
      '<i class="fa-solid fa-link task-item-web-link-fallback-icon"' +
      (favicon ? ' style="display:none;"' : "") +
      "></i>" +
      "</span>" +
      "</a>" +
      '<span class="task-item-web-link-anchor">' +
      escHtml(label) +
      "</span>" +
      "</div>" +
      '<button type="button" class="btn task-item-web-link-delete-btn" data-url-id="' +
      Number(row.id || 0) +
      '" title="Delete web link"><i class="fa-regular fa-trash-can"></i></button>' +
      "</div>";
  }

  if (!html) {
    html = '<div class="task-item-web-link-empty">No web links yet.</div>';
  }

  $("#taskItemWebLinkList").html(html);
  $("#taskItemWebLinkList .task-item-web-link-favicon")
    .off("error.webLinkFavicon")
    .on("error.webLinkFavicon", function () {
      var $img = $(this);
      var fallbackSrc = String($img.attr("data-fallback-src") || "").trim();
      var alreadyUsed = String($img.attr("data-fallback-used") || "0") === "1";

      if (!alreadyUsed && fallbackSrc) {
        $img.attr("data-fallback-used", "1");
        $img.attr("src", fallbackSrc);
        return;
      }

      $img.hide();
      $img
        .siblings(".task-item-web-link-fallback-icon")
        .css("display", "inline-flex");
    });
}

function openWebLinkEditor() {
  itemDetailModalState.webLinkEditorOpen = true;
  $("#taskItemWebLinkUrlInput").val("");
  $("#taskItemWebLinkTextInput").val("");
  renderWebLinksSection();
  setTimeout(function () {
    $("#taskItemWebLinkUrlInput").trigger("focus");
  }, 40);
}

function closeWebLinkEditor() {
  itemDetailModalState.webLinkEditorOpen = false;
  $("#taskItemWebLinkUrlInput").val("");
  $("#taskItemWebLinkTextInput").val("");
  renderWebLinksSection();
}

function getLinkRelationTypes() {
  if (Array.isArray(state.linkRelationTypes) && state.linkRelationTypes.length) {
    return state.linkRelationTypes.slice();
  }

  return [
    "is blocked by",
    "blocks",
    "is cloned by",
    "is connected to",
    "connects to",
    "is duplicated by",
    "duplicates",
    "add to idea",
    "is idea for",
    "merged into",
    "merged from",
    "is implemented by",
    "implements",
    "is caused by",
    "causes",
    "relates to",
  ];
}

function isParentTypeWorkItem() {
  if (itemDetailModalState.isParentType) {
    return true;
  }

  return String(itemDetailModalState.workTypeName || "")
    .trim()
    .toLowerCase() === "epic";
}

function availableChildWorkTypes() {
  return (Array.isArray(state.workTypes) ? state.workTypes : [])
    .map(function (workType) {
      return normalizeWorkTypeEntry(workType || {});
    })
    .filter(function (workType) {
      return (
        Number(workType.id || 0) > 0 &&
        String(workType.name || "").trim().toLowerCase() !== "epic"
      );
    });
}

function normalizeItemSearchResults(list) {
  return (Array.isArray(list) ? list : []).map(function (item) {
    var row = item && typeof item === "object" ? item : {};
    return {
      id: Number(row.id || 0),
      title: String(row.title || "").trim(),
      work_item_key: String(row.work_item_key || "").trim(),
      work_type_name: String(row.work_type_name || "Task").trim() || "Task",
      work_type_svg_icon: normalizeWorkTypeIcon(
        row.work_type_svg_icon,
        row.work_type_name || "Task",
      ),
      status_name: String(row.status_name || "").trim(),
      status_color: String(row.status_color || "").trim(),
      assignee_name: String(row.assignee_name || "").trim(),
    };
  });
}

function normalizeItemLinks(raw) {
  var info = raw && typeof raw === "object" ? raw : {};
  var groups = Array.isArray(info.groups) ? info.groups : [];
  var normalizedGroups = groups.map(function (group) {
    var rows = Array.isArray(group && group.items) ? group.items : [];
    return {
      relation_type: String(group && group.relation_type ? group.relation_type : "relates to").trim() || "relates to",
      items: rows.map(function (item) {
        var row = item && typeof item === "object" ? item : {};
        return {
          link_id: Number(row.link_id || 0),
          id: Number(row.id || 0),
          relation_type: String(row.relation_type || "").trim(),
          display_relation_type: String(
            row.display_relation_type || group.relation_type || "relates to",
          ).trim() || "relates to",
          work_item_key: String(row.work_item_key || "").trim(),
          title: String(row.title || "").trim(),
          work_type_name: String(row.work_type_name || "Task").trim() || "Task",
          work_type_svg_icon: normalizeWorkTypeIcon(
            row.work_type_svg_icon,
            row.work_type_name || "Task",
          ),
          column_id: Number(row.column_id || 0),
          status_name: String(row.status_name || "").trim(),
          status_color: String(row.status_color || "").trim(),
          assignee_user_id: Number(row.assignee_user_id || 0),
          assignee_name: String(row.assignee_name || "").trim(),
        };
      }),
    };
  });

  var total = Number(info.total || 0);
  if (total <= 0) {
    total = normalizedGroups.reduce(function (sum, group) {
      return sum + (Array.isArray(group.items) ? group.items.length : 0);
    }, 0);
  }

  return {
    groups: normalizedGroups,
    total: total,
  };
}

function itemSearchResultHtml(item, selected, extraClass) {
  var row = item && typeof item === "object" ? item : {};
  var itemId = Number(row.id || 0);
  var workTypeName = String(row.work_type_name || "Task").trim() || "Task";
  var workTypeIcon = normalizeWorkTypeIcon(
    row.work_type_svg_icon,
    workTypeName,
  );
  var fullName = String(row.work_item_key || "").trim();
  var title = String(row.title || "").trim();
  if (title) {
    fullName += (fullName ? " " : "") + title;
  }
  if (!fullName) {
    fullName = "Work item";
  }

  return (
    '<button type="button" class="btn task-item-search-result' +
    (extraClass ? " " + extraClass : "") +
    (selected ? " active" : "") +
    '" data-result-item-id="' +
    itemId +
    '">' +
    workTypeIconHtml(workTypeIcon, workTypeName, "task-item-search-result-icon") +
    '<span class="task-item-search-result-name">' +
    escHtml(fullName) +
    "</span>" +
    "</button>"
  );
}

function renderChildCreateWorkTypeOptions() {
  var workTypes = availableChildWorkTypes();
  var selectedId = Number($("#taskItemChildCreateWorkTypeSelect").val() || 0);
  if (!selectedId && workTypes.length) {
    for (var index = 0; index < workTypes.length; index++) {
      var candidate = workTypes[index] || {};
      if (
        String(candidate.name || "")
          .trim()
          .toLowerCase() === "task"
      ) {
        selectedId = Number(candidate.id || 0);
        break;
      }
    }
  }
  if (!selectedId && workTypes.length) {
    selectedId = Number(workTypes[0].id || 0);
  }

  var html = "";
  for (var i = 0; i < workTypes.length; i++) {
    var workType = workTypes[i] || {};
    var workTypeId = Number(workType.id || 0);
    if (!workTypeId) {
      continue;
    }

    html +=
      '<option value="' +
      workTypeId +
      '"' +
      (workTypeId === selectedId ? " selected" : "") +
      ">" +
      escHtml(String(workType.name || "Task")) +
      "</option>";
  }

  $("#taskItemChildCreateWorkTypeSelect").html(html);
}

function renderChildCreateSearchResults() {
  var results = normalizeItemSearchResults(
    itemDetailModalState.childCreateSearchResults,
  );
  itemDetailModalState.childCreateSearchResults = results;

  var isSearchMode = itemDetailModalState.childCreateMode === "search";
  var keyword = String($("#taskItemChildCreateInput").val() || "").trim();
  var showResults = isSearchMode && keyword !== "";
  var html = "";

  for (var i = 0; i < results.length; i++) {
    var item = results[i] || {};
    if (!Number(item.id || 0)) {
      continue;
    }
    html += itemSearchResultHtml(
      item,
      Number(item.id || 0) === Number(itemDetailModalState.childCreateSelectedItemId || 0),
      "task-item-search-result-child",
    );
  }

  if (!html && showResults) {
    html =
      '<div class="task-item-search-results-empty">No matching work items found.</div>';
  }

  $("#taskItemChildCreateSearchResults")
    .toggleClass("d-none", !showResults)
    .html(html);
}

function renderChildCreatePanel() {
  var isParentType = isParentTypeWorkItem();
  var isOpen = !!itemDetailModalState.childCreatePanelOpen && isParentType;
  var isSearchMode = itemDetailModalState.childCreateMode === "search";
  var inputValue = String($("#taskItemChildCreateInput").val() || "").trim();

  $("#taskItemChildCreatePanel").toggleClass("d-none", !isOpen);
  $("#taskItemDetailCreateChildActionWrap").toggleClass("d-none", !isParentType);
  if (!isOpen) {
    $("#taskItemChildCreateSearchResults").addClass("d-none").empty();
    return;
  }

  renderChildCreateWorkTypeOptions();
  $("#taskItemChildCreateWorkTypeSelect").toggleClass("d-none", isSearchMode);
  $("#taskItemChildCreateInput").attr(
    "placeholder",
    isSearchMode ? "Type, search or paste URL" : "Name this task",
  );
  $("#taskItemChildCreateSubmitBtn").text(isSearchMode ? "Add" : "Create");
  $("#taskItemChildCreateChooseExistingBtn").text(
    isSearchMode ? "Create new instead" : "Choose existing",
  );
  $("#taskItemChildCreateSubmitBtn").prop("disabled", inputValue === "");
  renderChildCreateSearchResults();
}

function resolveStatusColor(columnId, fallbackColor) {
  var selectedColumnId = Number(columnId || 0);
  var fallback = String(fallbackColor || "").trim() || "#DFE1E6";
  var columns = Array.isArray(state.columns) ? state.columns : [];

  for (var i = 0; i < columns.length; i++) {
    var column = columns[i] || {};
    if (Number(column.id || 0) === selectedColumnId) {
      return normalizeHexColorValue(column.color || fallback, fallback);
    }
  }

  return normalizeHexColorValue(fallback, "#DFE1E6");
}

function linkedItemAssigneeHtml(item) {
  var row = item && typeof item === "object" ? item : {};
  var userId = Number(row.assignee_user_id || 0);
  var userName = String(row.assignee_name || "").trim() || "Unassigned";

  if (userId > 0) {
    return (
      '<span class="task-item-linked-assignee-avatar" title="' +
      escHtml(userName) +
      '">' +
      escHtml(initials(userName)) +
      "</span>"
    );
  }

  return '<span class="task-item-linked-assignee-avatar task-item-linked-assignee-avatar-unassigned" title="Unassigned"><i class="fa-regular fa-user"></i></span>';
}

function linkedItemStatusHtml(item) {
  var row = item && typeof item === "object" ? item : {};
  var statusName = String(row.status_name || "").trim() || "No status";
  var statusColor = resolveStatusColor(row.column_id, row.status_color);
  var textColor = labelTextColor(statusColor);

  return (
    '<span class="task-item-linked-status-badge" style="background:' +
    escHtml(statusColor) +
    ";color:" +
    escHtml(textColor) +
    ';">' +
    escHtml(statusName) +
    "</span>"
  );
}

function renderLinkRelationOptions() {
  var selectedType = String(itemDetailModalState.linkRelationType || "").trim();
  var types = getLinkRelationTypes();
  if (!selectedType && types.length) {
    selectedType = String(types[0] || "");
    itemDetailModalState.linkRelationType = selectedType;
  }

  var html = "";
  for (var i = 0; i < types.length; i++) {
    var relationType = String(types[i] || "").trim();
    if (!relationType) {
      continue;
    }
    html +=
      '<option value="' +
      escHtml(relationType) +
      '"' +
      (relationType === selectedType ? " selected" : "") +
      ">" +
      escHtml(relationType) +
      "</option>";
  }

  $("#taskItemLinkRelationTypeSelect").html(html);
}

function renderLinkSearchResults() {
  var results = normalizeItemSearchResults(itemDetailModalState.linkSearchResults);
  itemDetailModalState.linkSearchResults = results;

  var keyword = String($("#taskItemLinkSearchInput").val() || "").trim();
  var html = "";
  for (var i = 0; i < results.length; i++) {
    var item = results[i] || {};
    if (!Number(item.id || 0)) {
      continue;
    }
    html += itemSearchResultHtml(
      item,
      Number(item.id || 0) === Number(itemDetailModalState.linkSelectedItemId || 0),
      "task-item-search-result-link",
    );
  }

  if (!html && keyword !== "") {
    html =
      '<div class="task-item-search-results-empty">No matching work items found.</div>';
  }

  $("#taskItemLinkSearchResults")
    .toggleClass("d-none", keyword === "")
    .html(html);
}

function renderLinkedWorkItemsSection() {
  var info = normalizeItemLinks(itemDetailModalState.itemLinks);
  itemDetailModalState.itemLinks = info;
  var keyword = String($("#taskItemLinkSearchInput").val() || "").trim();

  $("#taskItemDetailLinkWorkItemActionWrap").toggleClass(
    "d-none",
    !isParentTypeWorkItem(),
  );
  $("#taskItemLinkEditor").toggleClass("d-none", !itemDetailModalState.linkEditorOpen);
  $("#taskItemLinkedWorkItemsEmptyAction").toggleClass(
    "d-none",
    info.total > 0 || itemDetailModalState.linkEditorOpen,
  );
  $("#taskItemLinkSaveBtn").prop(
    "disabled",
    !itemDetailModalState.linkEditorOpen ||
      String(itemDetailModalState.linkRelationType || "").trim() === "" ||
      keyword === "",
  );

  renderLinkRelationOptions();
  renderLinkSearchResults();

  var html = "";
  var groups = Array.isArray(info.groups) ? info.groups : [];
  for (var g = 0; g < groups.length; g++) {
    var group = groups[g] || {};
    var relationType = String(group.relation_type || "").trim();
    var items = Array.isArray(group.items) ? group.items : [];
    if (!relationType || !items.length) {
      continue;
    }

    html +=
      '<div class="task-item-linked-group">' +
      '<div class="task-item-linked-group-title">' +
      escHtml(relationType) +
      "</div>";

    for (var i = 0; i < items.length; i++) {
      var item = items[i] || {};
      var itemId = Number(item.id || 0);
      var workTypeName = String(item.work_type_name || "Task");
      var workTypeIcon = normalizeWorkTypeIcon(
        item.work_type_svg_icon,
        workTypeName,
      );
      var workKey = String(item.work_item_key || "").trim();
      var title = String(item.title || "").trim() || "Work item";

      html +=
        '<div class="task-item-linked-row">' +
        '<div class="task-item-linked-main">' +
        workTypeIconHtml(workTypeIcon, workTypeName, "task-item-linked-work-type-icon") +
        '<button type="button" class="btn task-item-linked-open-btn" data-linked-item-id="' +
        itemId +
        '" title="' +
        escHtml((workKey ? workKey + " " : "") + title) +
        '">' +
        '<span class="task-item-linked-open-key">' +
        escHtml(workKey || ("Item #" + itemId)) +
        "</span>" +
        '<span class="task-item-linked-open-title">' +
        escHtml(title) +
        "</span>" +
        "</button>" +
        "</div>" +
        '<div class="task-item-linked-meta">' +
        linkedItemStatusHtml(item) +
        linkedItemAssigneeHtml(item) +
        (canEdit
          ? '<button type="button" class="btn task-item-linked-remove-btn" data-link-id="' +
            Number(item.link_id || 0) +
            '" title="Remove linked work item"><i class="fa-regular fa-trash-can"></i></button>'
          : "") +
        "</div>" +
        "</div>";
    }

    html += "</div>";
  }

  if (!html && info.total > 0) {
    html = '<div class="task-item-linked-empty">No linked work items yet.</div>';
  }

  $("#taskItemLinkedWorkItemsList").html(html);
}

function priorityIconHtml(priority) {
  var value = String(priority || "Medium");
  var iconClass = "task-priority-medium";
  if (value === "Highest") {
    iconClass = "fa-angles-up task-priority-highest";
  } else if (value === "High") {
    iconClass = "fa-angle-up task-priority-high";
  } else if (value === "Low") {
    iconClass = "fa-angle-down task-priority-low";
  } else if (value === "Lowest") {
    iconClass = "fa-angles-down task-priority-lowest";
  }

  var iconHtml =
    iconClass === "task-priority-medium"
      ? '<span class="task-priority-icon task-priority-medium-icon" aria-hidden="true"></span>'
      : '<i class="fa-solid task-priority-icon ' +
        escHtml(iconClass) +
        '"></i>';

  return iconHtml + " " + escHtml(value);
}

function setDetailPriority(priority) {
  var normalized = String(priority || "").trim();
  var allowed = ["Highest", "High", "Medium", "Low", "Lowest"];
  if (allowed.indexOf(normalized) === -1) {
    normalized = "Medium";
  }

  itemDetailModalState.selectedPriority = normalized;
  $("#taskItemDetailPriorityBtn").html(priorityIconHtml(normalized));
}

function renderDetailAssigneeSelect(selectedUserId) {
  var selectedId = Number(selectedUserId || 0);
  var html = '<option value="0">Unassigned</option>';
  for (var i = 0; i < state.assignees.length; i++) {
    var item = state.assignees[i] || {};
    var id = Number(item.id || 0);
    var name = String(item.name || "").trim();
    if (!id || !name) {
      continue;
    }
    html += '<option value="' + id + '">' + escHtml(name) + "</option>";
  }

  $("#taskItemDetailAssigneeSelect")
    .html(html)
    .val(String(selectedId))
    .prop("disabled", !(canEdit && hasAnyProjectFieldPermission("assignee")));
}

function renderDetailReporterSelect(selectedUserId) {
  var selectedId = Number(selectedUserId || 0);
  var html = '<option value="0">Unassigned</option>';
  for (var i = 0; i < state.assignees.length; i++) {
    var item = state.assignees[i] || {};
    var id = Number(item.id || 0);
    var name = String(item.name || "").trim();
    if (!id || !name) {
      continue;
    }
    html += '<option value="' + id + '">' + escHtml(name) + "</option>";
  }

  $("#taskItemDetailReporterSelect")
    .html(html)
    .val(String(selectedId))
    .prop("disabled", !(canEdit && hasAnyProjectFieldPermission("reporter")));
}

function normalizeStatusLabelIdList(rawList) {
  var source = Array.isArray(rawList) ? rawList : [];
  var ids = [];
  for (var i = 0; i < source.length; i++) {
    var id = Number(source[i] || 0);
    if (id > 0 && ids.indexOf(id) === -1) {
      ids.push(id);
    }
  }
  return ids;
}

function parseStatusLabelCsv(rawValue) {
  var value = String(rawValue || "").trim();
  if (!value) {
    return [];
  }

  var parts = value.split(",");
  var ids = [];
  for (var i = 0; i < parts.length; i++) {
    var id = Number(String(parts[i] || "").trim() || 0);
    if (id > 0 && ids.indexOf(id) === -1) {
      ids.push(id);
    }
  }

  return ids;
}

function parseStatusLabelIdsFromRaw(rawValue) {
  var ids = parseStatusLabelCsv(rawValue);
  if (ids.length) {
    return ids;
  }

  var text = String(rawValue || "").trim();
  if (!text) {
    return [];
  }

  var parts = text.split(",");
  for (var i = 0; i < parts.length; i++) {
    var target = String(parts[i] || "")
      .trim()
      .toLowerCase();
    if (!target) {
      continue;
    }

    for (var j = 0; j < state.statusLabels.length; j++) {
      var item = state.statusLabels[j] || {};
      var id = Number(item.id || 0);
      var name = String(item.name || "")
        .trim()
        .toLowerCase();
      if (id > 0 && name && name === target && ids.indexOf(id) === -1) {
        ids.push(id);
      }
    }
  }

  return ids;
}

function statusLabelNameById(statusLabelId) {
  var targetId = Number(statusLabelId || 0);
  if (!targetId) {
    return "";
  }

  for (var i = 0; i < state.statusLabels.length; i++) {
    var item = state.statusLabels[i] || {};
    if (Number(item.id || 0) === targetId) {
      return String(item.name || "").trim();
    }
  }

  return "";
}

function renderStatusLabelChips() {
  var ids = normalizeStatusLabelIdList(
    itemDetailModalState.selectedStatusLabelIds,
  );
  itemDetailModalState.selectedStatusLabelIds = ids.slice();

  var html = "";
  for (var i = 0; i < ids.length; i++) {
    var id = Number(ids[i] || 0);
    var statusLabel = getStatusLabelById(id) || {};
    var name = String(statusLabel.name || "").trim();
    if (!id || !name) {
      continue;
    }

    html +=
      '<span class="task-label-pill task-item-detail-status-chip" style="' +
      labelPillStyle(statusLabel.color, "#DCE8FF") +
      '">' +
      escHtml(name) +
      '<button type="button" class="btn task-item-detail-status-chip-remove" data-status-label-id="' +
      id +
      '" title="Remove task status"><i class="fa-solid fa-xmark"></i></button>' +
      "</span>";
  }

  $("#taskItemDetailStatusChips").html(
    html || '<span class="task-item-detail-empty-text">Select labels</span>',
  );
}

function setSelectedStatusLabels(ids) {
  itemDetailModalState.selectedStatusLabelIds = normalizeStatusLabelIdList(ids);
  renderStatusLabelChips();
  if (!$("#taskItemDetailStatusSearchInput").is(":focus")) {
    $("#taskItemDetailStatusSearchInput").val("");
  }
}

function setDetailBoardStatus(columnId) {
  var id = Number(columnId || 0);
  var meta = getBoardStatusColumnMeta(id);
  var name = meta ? String(meta.name || "").trim() : "";
  var color = meta ? normalizeHexColorValue(meta.color || "", "#DFE1E6") : "";
  if (!name && id > 0) {
    var $card = $(itemDetailModalState.cardEl || null);
    if ($card.length) {
      name = String($card.attr("data-status-column-name") || "").trim();
      color = normalizeHexColorValue(
        $card.attr("data-status-column-color") || "",
        "#DFE1E6",
      );
    }
  }

  if (!name) {
    name = "Select status";
  }

  itemDetailModalState.detailStatusColumnId = id > 0 ? id : 0;
  $("#taskItemDetailBoardStatusBtn")
    .text(name)
    .css({
      "--task-detail-status-bg": color || "#669DF1",
      "--task-detail-status-text": color
        ? getReadableTextColor(color)
        : "#FFFFFF",
    })
    .prop("disabled", !canEdit);
}

function renderDetailBoardStatusOptions() {
  var columns = getBoardStatusColumns();
  var currentId = Number(itemDetailModalState.detailStatusColumnId || 0);
  var html = "";

  for (var i = 0; i < columns.length; i++) {
    var col = columns[i] || {};
    var colId = Number(col.id || 0);
    var colName = String(col.name || "").trim();
    if (!colId || !colName) {
      continue;
    }
    if (colId !== currentId && !canTargetStatusColumn(colId)) {
      continue;
    }

    html +=
      '<button type="button" class="dropdown-item task-item-detail-board-status-option' +
      (colId === currentId ? " active" : "") +
      '" data-target-column-id="' +
      colId +
      '" style="--task-detail-status-option-bg:' +
      escHtml(normalizeHexColorValue(col.color || "", "#DFE1E6")) +
      ";--task-detail-status-option-text:" +
      escHtml(
        getReadableTextColor(normalizeHexColorValue(col.color || "", "#DFE1E6")),
      ) +
      '"><span class="task-item-detail-board-status-option-pill">' +
      escHtml(colName) +
      "</span>" +
      "</button>";
  }

  $("#taskItemDetailBoardStatusOptionList").html(
    html || '<div class="task-label-empty">No status available.</div>',
  );
}

function removeStatusLabelFromSelection(statusLabelId) {
  var targetId = Number(statusLabelId || 0);
  if (!targetId) {
    return;
  }
  itemDetailModalState.selectedStatusLabelIds = normalizeStatusLabelIdList(
    itemDetailModalState.selectedStatusLabelIds,
  ).filter(function (id) {
    return id !== targetId;
  });
  renderStatusLabelChips();
}

function renderStatusLabelOptions(keyword) {
  console.log('[renderStatusLabelOptions] canEdit=' + canEdit + ', statusLabels.length=' + state.statusLabels.length);
  var query = String(keyword || "")
    .trim()
    .toLowerCase();

  var html = "";
  for (var i = 0; i < state.statusLabels.length; i++) {
    var item = state.statusLabels[i] || {};
    var id = Number(item.id || 0);
    var name = String(item.name || "").trim();
    if (!id || !name) {
      continue;
    }

    if (query && name.toLowerCase().indexOf(query) === -1) {
      continue;
    }

    var checked =
      itemDetailModalState.selectedStatusLabelIds.indexOf(id) !== -1
        ? " checked"
        : "";

    var disabled = !canEdit ? ' disabled' : '';
    console.log('[renderStatusLabelOptions] item ' + i + ' (' + name + '): disabled=' + (disabled ? 'YES' : 'NO'));
    var itemHtml = '<label class="task-item-detail-status-option">' +
      '<input class="form-check-input task-item-detail-status-checkbox" type="checkbox" value="' +
      id +
      '"' +
      checked +
      disabled +
      ">" +
      '<span class="task-item-detail-status-option-name task-label-pill" style="' +
      labelPillStyle(item.color, "#DCE8FF") +
      '">' +
      escHtml(name) +
      "</span>" +
      (state.isProjectOwner
        ? '<button type="button" class="btn task-item-detail-status-option-delete-btn" data-status-label-id="' +
      id +
      '" title="Delete status label"><i class="fa-regular fa-trash-can"></i></button>'
        : "") +
      "</label>";
    html += itemHtml;
  }

  var finalHtml = html || '<div class="task-label-empty">No task status found.</div>';
  console.log('[renderStatusLabelOptions] Final HTML length:', finalHtml.length, ', contains "disabled":', finalHtml.indexOf('disabled') > -1);
  $("#taskItemDetailStatusOptionList").html(finalHtml);

  // Check what's in the DOM after insertion
  setTimeout(function() {
    var checkboxes = $("#taskItemDetailStatusOptionList input[type='checkbox']");
    console.log('[renderStatusLabelOptions] After DOM insertion: ' + checkboxes.length + ' checkboxes found');
    checkboxes.each(function(idx) {
      console.log('  Checkbox ' + idx + ' disabled=' + ($(this).prop('disabled') ? 'YES' : 'NO') + ', attr=' + ($(this).attr('disabled') ? 'YES' : 'NO'));
    });

    // Watch for mutations on disabled attribute
    checkboxes.each(function(idx) {
      var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
          if (mutation.attributeName === 'disabled') {
            console.log('[MUTATION] Checkbox ' + idx + ' disabled attribute changed to:', $(mutation.target).attr('disabled'));
            console.trace('[MUTATION TRACE]');
          }
        });
      });
      observer.observe(this, { attributes: true, attributeFilter: ['disabled'] });
    });
  }, 50);
}

function normalizeChildWorkItems(raw) {
  var info = raw && typeof raw === "object" ? raw : {};
  var rows = Array.isArray(info.items) ? info.items : [];
  var normalizedRows = rows.map(function (item) {
    var row = item && typeof item === "object" ? item : {};
    return {
      id: Number(row.id || 0),
      work_item_key: String(row.work_item_key || "").trim(),
      title: String(row.title || "").trim(),
      priority: String(row.priority || "Medium").trim() || "Medium",
      original_estimate: String(row.original_estimate || "").trim(),
      original_estimate_value: Math.max(0, Number(row.original_estimate_value || 0)),
      original_estimate_unit: String(row.original_estimate_unit || "minutes").trim() || "minutes",
      labels: Array.isArray(row.labels) ? row.labels : [],
      task_status_labels: Array.isArray(row.task_status_labels) ? row.task_status_labels : [],
      work_type_id: Number(row.work_type_id || 0),
      work_type_name: String(row.work_type_name || "Task").trim() || "Task",
      parent_item_id: Number(row.parent_item_id || 0),
      parent_display: String(row.parent_display || "").trim(),
      assignee_user_id: Number(row.assignee_user_id || 0),
      assignee_name: String(row.assignee_name || "").trim(),
      reporter_user_id: Number(row.reporter_user_id || 0),
      reporter_name: String(row.reporter_name || "").trim(),
      column_id: Number(row.column_id || 0),
      status_name: String(row.status_name || "").trim(),
      is_done: Number(row.is_done || 0) > 0 ? 1 : 0,
      time_tracking: String(row.time_tracking || "").trim() || "No time logged",
      remaining_estimate_seconds: row.remaining_estimate_seconds === null || row.remaining_estimate_seconds === undefined ? null : Number(row.remaining_estimate_seconds || 0),
      due_date: String(row.due_date || "").trim(),
      start_date: String(row.start_date || "").trim(),
      amendement_date: String(row.amendement_date || "").trim(),
      amendement_time: String(row.amendement_time || "").trim(),
      second_amendement_date: String(row.second_amendement_date || "").trim(),
      second_amendement_time: String(row.second_amendement_time || "").trim(),
    };
  });

  var total = Number(info.total || normalizedRows.length || 0);
  if (total <= 0) {
    total = normalizedRows.length;
  }

  var done = Number(info.done || 0);
  if (done < 0) {
    done = 0;
  }
  if (!done && normalizedRows.length) {
    done = normalizedRows.filter(function (item) {
      return Number(item.is_done || 0) > 0;
    }).length;
  }

  var progress = Number(info.progress_percent || 0);
  if ((!progress || progress < 0) && total > 0) {
    progress = Math.round((done * 100) / total);
  }

  return {
    items: normalizedRows,
    total: total,
    done: done,
    progress_percent: Math.max(0, Math.min(100, progress)),
  };
}

function childWorkItemStatusName(columnId, fallbackName) {
  var selectedColumnId = Number(columnId || 0);
  var fallback = String(fallbackName || "").trim();
  var columns = getBoardStatusColumns();

  for (var index = 0; index < columns.length; index++) {
    var column = columns[index] || {};
    if (Number(column.id || 0) === selectedColumnId) {
      var statusName = String(column.name || "").trim();
      return statusName || fallback || "-";
    }
  }

  return fallback || "-";
}

function childWorkItemPriorityPreviewHtml(priority, iconOnly) {
  var value = String(priority || "Medium").trim() || "Medium";
  var html =
    '<span class="task-item-child-priority-preview-icon">' +
    priorityIconGlyphHtml(value) +
    "</span>";

  if (!iconOnly) {
    html +=
      '<span class="task-item-child-priority-preview-label">' +
      escHtml(value) +
      "</span>";
  }

  return html;
}

function childWorkItemPriorityOptionsHtml(selectedPriority) {
  var value = String(selectedPriority || "Medium").trim() || "Medium";
  var html = "";

  for (var index = 0; index < taskPriorityValues.length; index++) {
    var option = String(taskPriorityValues[index] || "Medium").trim();
    if (!option) {
      continue;
    }

    html +=
      '<option value="' +
      escHtml(option) +
      '"' +
      (option === value ? " selected" : "") +
      ">" +
      escHtml(option) +
      "</option>";
  }

  return html;
}

function childWorkItemEstimateHtml(value, unit, storedValue) {
  var rawEstimate = String(storedValue || "").trim();
  if (rawEstimate) {
    return (
      '<span class="task-item-child-estimate-value">' +
      escHtml(rawEstimate) +
      "</span>"
    );
  }

  var estimateValue = Math.max(0, Number(value || 0));
  if (!estimateValue) {
    return '<span class="task-item-child-estimate-empty">—</span>';
  }

  var estimateUnit = String(unit || "minutes").trim().toLowerCase();
  var unitMap = {
    minute: "minute",
    minutes: "minute",
    hour: "hour",
    hours: "hour",
    day: "day",
    days: "day",
    week: "week",
    weeks: "week",
  };
  var unitLabel = unitMap[estimateUnit] || "minute";
  if (estimateValue !== 1) {
    unitLabel += "s";
  }

  return (
    '<span class="task-item-child-estimate-value">' +
    escHtml(String(estimateValue) + " " + unitLabel) +
    "</span>"
  );
}

function childWorkItemLabelsHtml(labels) {
  var items = Array.isArray(labels) ? labels : [];
  var validLabels = [];
  for (var index = 0; index < items.length; index++) {
    var label = items[index] || {};
    var labelName = String(label.name || "").trim();
    if (labelName) {
      validLabels.push(label);
    }
  }

  if (!validLabels.length) {
    return '<span class="task-item-child-labels-empty">—</span>';
  }

  var html =
    '<span class="task-item-child-labels">' +
    '<span class="task-label-pill task-item-child-label-pill" style="' +
    labelPillStyle(validLabels[0].color, "#DCE8FF") +
    '">' +
    escHtml(String(validLabels[0].name || "")) +
    "</span>";

  for (var extraIndex = 1; extraIndex < validLabels.length; extraIndex++) {
    html +=
      '<span class="task-label-pill task-item-child-label-pill task-item-child-label-extra" style="' +
      labelPillStyle(validLabels[extraIndex].color, "#DCE8FF") +
      '">' +
      escHtml(String(validLabels[extraIndex].name || "")) +
      "</span>";
  }

  if (validLabels.length > 1) {
    html +=
      '<button type="button" class="btn task-item-child-label-more" data-label-count="' +
      (validLabels.length - 1) +
      '" aria-expanded="false" aria-label="Show all labels" title="Show all labels">+' +
      (validLabels.length - 1) +
      "</button>";
  }

  return html + "</span>";
}

function childWorkItemAssigneePreviewHtml(userId, userName) {
  var id = Number(userId || 0);
  var name = String(userName || "").trim() || "Unassigned";
  var avatarHtml =
    id > 0
      ? '<span class="task-item-child-assignee-avatar">' +
        escHtml(initials(name)) +
        "</span>"
      : '<span class="task-item-child-assignee-avatar task-item-child-assignee-avatar-unassigned"><i class="fa-regular fa-user"></i></span>';

  return (
    '<span class="task-item-child-assignee-preview">' +
    avatarHtml +
    '<span class="task-item-child-assignee-name">' +
    escHtml(name) +
    "</span>" +
    "</span>"
  );
}

function childWorkItemAssigneeOptionsHtml(selectedUserId) {
  var value = Number(selectedUserId || 0);
  var html =
    '<option value="0"' +
    (value <= 0 ? " selected" : "") +
    ">Unassigned</option>";

  for (var index = 0; index < state.assignees.length; index++) {
    var item = state.assignees[index] || {};
    var userId = Number(item.id || 0);
    var userName = String(item.name || "").trim();
    if (!userId || !userName) {
      continue;
    }

    html +=
      '<option value="' +
      userId +
      '"' +
      (userId === value ? " selected" : "") +
      ">" +
      escHtml(userName) +
      "</option>";
  }

  return html;
}

function childWorkItemStatusOptionsHtml(selectedColumnId) {
  var value = Number(selectedColumnId || 0);
  var columns = getBoardStatusColumns();
  var html = "";

  for (var index = 0; index < columns.length; index++) {
    var column = columns[index] || {};
    var columnId = Number(column.id || 0);
    var columnName = String(column.name || "").trim();
    if (!columnId || !columnName) {
      continue;
    }

    html +=
      '<option value="' +
      columnId +
      '"' +
      (columnId === value ? " selected" : "") +
      ">" +
      escHtml(columnName) +
      "</option>";
  }

  return html;
}

function setDetailSideCollapsed(collapsed) {
  var isCollapsed = !!collapsed;
  itemDetailModalState.detailsCollapsed = isCollapsed;
  $("#taskItemDetailSideCard").toggleClass(
    "task-item-detail-side-card-collapsed",
    isCollapsed,
  );
  $("#taskItemDetailFieldRowsWrap").toggleClass("d-none", isCollapsed);
  $("#taskItemDetailSideSummary").toggleClass("d-none", !isCollapsed);

  var $btn = $("#taskItemDetailSideCollapseBtn");
  $btn.attr("aria-expanded", isCollapsed ? "false" : "true");
  $btn
    .find("i")
    .attr(
      "class",
      "fa-solid " + (isCollapsed ? "fa-chevron-right" : "fa-chevron-down"),
    );
  $btn.attr("title", isCollapsed ? "Expand details" : "Collapse details");
}

function setChildWorkItemsCollapsed(collapsed) {
  var isCollapsed = !!collapsed;
  itemDetailModalState.childWorkItemsCollapsed = isCollapsed;
  $("#taskItemChildWorkItemsBody").toggleClass("d-none", isCollapsed);

  var $btn = $("#taskItemChildWorkItemsCollapseBtn");
  $btn.attr("aria-expanded", isCollapsed ? "false" : "true");
  $btn
    .find("i")
    .attr(
      "class",
      "fa-solid " + (isCollapsed ? "fa-chevron-right" : "fa-chevron-down"),
    );
  $btn.attr(
    "title",
    isCollapsed ? "Expand child work items" : "Collapse child work items",
  );
}

function applyDetailFieldVisibility() {
  var workType = String(itemDetailModalState.workTypeName || "")
    .trim()
    .toLowerCase();
  var isEpic = workType === "epic";
  var childInfo = normalizeChildWorkItems(itemDetailModalState.childWorkItems);
  var hasChildItems = Number(childInfo.total || 0) > 0;
  var epicFields = {
    time_tracking: true,
    assignee: true,
    labels: true,
    due_date: true,
    start_date: true,
    reporter: true,
  };

  $("#taskItemDetailFieldRowsWrap .task-item-detail-field-row").each(
    function () {
      var field = String($(this).attr("data-detail-field") || "")
        .trim()
        .toLowerCase();
      if (!field) {
        return;
      }

      if (!isEpic) {
        $(this).removeClass("d-none");
      } else {
        $(this).toggleClass(
          "d-none",
          !Object.prototype.hasOwnProperty.call(epicFields, field),
        );
      }

      var hasPermission = hasAnyProjectFieldPermission(field);
      $(this).toggleClass("task-item-detail-field-row-readonly", !hasPermission);
      $(this)
        .find("input, select, textarea, button")
        .not(".task-item-detail-collapse-btn")
        .prop("disabled", !hasPermission || !canEdit);
    },
  );

  // Show child section only for items that actually have children.
  // This includes Epic parent items with child records.
  var showChildSection = hasChildItems;
  $("#taskItemChildWorkItemsSection").toggleClass("d-none", !showChildSection);
  if (showChildSection) {
    setChildWorkItemsCollapsed(false);
  }

  $("#taskItemDetailCreateChildActionWrap").toggleClass("d-none", !isEpic);
  $("#taskItemChildWorkItemsAddBtn").toggleClass("d-none", !isEpic);
  $("#taskItemChildWorkItemsBulkEditMenuWrap").toggleClass(
    "d-none",
    !isEpic || (!canEdit && !canDelete),
  );
  $("#taskItemChildWorkItemsColumnConfigMenuWrap").toggleClass(
    "d-none",
    !isEpic,
  );
  $("#taskItemDetailLinkWorkItemActionWrap").toggleClass("d-none", !isEpic);
  if (!isEpic) {
    itemDetailModalState.childCreatePanelOpen = false;
    itemDetailModalState.childCreateMode = "create";
    itemDetailModalState.childCreateSelectedItemId = 0;
    itemDetailModalState.childCreateSearchResults = [];
  }
  renderChildCreatePanel();
  renderLinkedWorkItemsSection();
}

var taskChildWorkItemColumnDefinitions = [
  { key: "work", label: "Work", defaultWidth: "minmax(260px, 1.35fr)" },
  { key: "work_type", label: "Work Type", defaultWidth: "minmax(125px, .7fr)" },
  { key: "original_estimate", label: "Original Estimate", defaultWidth: "minmax(125px, .65fr)" },
  { key: "labels", label: "Labels", defaultWidth: "minmax(150px, .85fr)" },
  { key: "task_status", label: "Task Status labels", defaultWidth: "minmax(160px, .9fr)" },
  { key: "parent", label: "Parent", defaultWidth: "minmax(160px, .9fr)" },
  { key: "priority", label: "Priority", defaultWidth: "minmax(132px, .72fr)" },
  { key: "time_tracking", label: "Time Tracking", defaultWidth: "minmax(150px, .8fr)" },
  { key: "assignee", label: "Assignee", defaultWidth: "minmax(160px, .82fr)" },
  { key: "due_date", label: "Due date", defaultWidth: "minmax(120px, .7fr)" },
  { key: "start_date", label: "Start date", defaultWidth: "minmax(120px, .7fr)" },
  { key: "reporter", label: "Reporter", defaultWidth: "minmax(150px, .8fr)" },
  { key: "amendement_date", label: "Amendment Date", defaultWidth: "minmax(135px, .75fr)" },
  { key: "amendement_time", label: "Amendment Time", defaultWidth: "minmax(135px, .75fr)" },
  { key: "second_amendement_date", label: "Second Amen-Date", defaultWidth: "minmax(145px, .8fr)" },
  { key: "second_amendement_time", label: "Second Amen-Time", defaultWidth: "minmax(145px, .8fr)" },
  { key: "status", label: "Status", defaultWidth: "minmax(220px, max-content)" },
];

function taskChildWorkItemColumnDefinition(key) {
  for (var i = 0; i < taskChildWorkItemColumnDefinitions.length; i++) {
    if (taskChildWorkItemColumnDefinitions[i].key === key) {
      return taskChildWorkItemColumnDefinitions[i];
    }
  }
  return null;
}

function taskChildWorkItemColumnStorageKey() {
  var currentUserId = Number(state.currentUserId || (window.taskBoardConfig || {}).currentUserId || 0);
  return "task_child_work_item_columns_v1_project_" +
    String(Number(state.currentProjectId || 0)) +
    "_user_" + String(currentUserId) +
    "_parent_" + String(Number(itemDetailModalState.itemId || 0));
}

function taskChildWorkItemDefaultColumnOrder() {
  return taskChildWorkItemColumnDefinitions.map(function (column) {
    return column.key;
  }).filter(function (key) {
    return ["work", "priority", "assignee", "status"].indexOf(key) !== -1;
  });
}

function taskChildWorkItemColumnSettings() {
  var defaultOrder = taskChildWorkItemDefaultColumnOrder();
  var settings = { order: defaultOrder, widths: {}, sortKey: "", sortDirection: "asc" };
  try {
    var saved = JSON.parse(window.localStorage.getItem(taskChildWorkItemColumnStorageKey()) || "null");
    if (saved && Array.isArray(saved.order)) {
      var validOrder = [];
      saved.order.forEach(function (key) {
        if (taskChildWorkItemColumnDefinition(key) && validOrder.indexOf(key) === -1) {
          validOrder.push(key);
        }
      });
      if (validOrder.length) settings.order = validOrder;
    }
    if (saved && saved.widths && typeof saved.widths === "object") settings.widths = saved.widths;
    if (saved && taskChildWorkItemColumnDefinition(saved.sortKey) && settings.order.indexOf(saved.sortKey) !== -1) {
      settings.sortKey = saved.sortKey;
      settings.sortDirection = saved.sortDirection === "desc" ? "desc" : "asc";
    }
  } catch (error) {
    // Use the defaults when browser storage is unavailable.
  }
  return settings;
}

function saveTaskChildWorkItemColumnSettings(settings) {
  try {
    window.localStorage.setItem(taskChildWorkItemColumnStorageKey(), JSON.stringify(settings));
  } catch (error) {
    // Keep the current layout working when browser storage is unavailable.
  }
}

function taskChildWorkItemColumnGridTemplate(settings) {
  return settings.order.map(function (key) {
    var definition = taskChildWorkItemColumnDefinition(key);
    var width = settings.widths && settings.widths[key];
    if (key === "status") {
      var savedStatusWidth = Number.parseInt(String(width || "").replace(/px$/, ""), 10);
      var statusMinimum = Number.isFinite(savedStatusWidth) ? Math.max(220, savedStatusWidth) : 220;
      return "minmax(" + statusMinimum + "px, max-content)";
    }
    return /^\d{2,4}px$/.test(String(width || "")) ? width : definition.defaultWidth;
  }).join(" ");
}

function applyTaskChildWorkItemColumnTemplate($elements, template) {
  if (!$elements || !$elements.length) return;
  $elements.each(function () {
    this.style.setProperty("--task-item-child-column-template", template);
  });
}

function taskChildWorkItemDateDisplay(value) {
  var text = String(value || "").trim();
  if (!text) return "—";
  var match = /^(\d{4})-(\d{2})-(\d{2})/.exec(text);
  return match ? match[3] + "/" + match[2] + "/" + match[1] : text;
}

function taskChildWorkItemTimeDisplay(value) {
  var text = String(value || "").trim();
  if (!text) return "—";
  var match = /^(\d{2}:\d{2})/.exec(text);
  return match ? match[1] : text;
}

function taskChildWorkItemTimeTrackingHtml(row) {
  var logged = String(row.time_tracking || "").trim() || "No time logged";
  var remainingSeconds = Number(row.remaining_estimate_seconds || 0);
  var remaining = remainingSeconds > 0 && typeof formatDurationBrief === "function"
    ? formatDurationBrief(remainingSeconds) + " remaining"
    : "";
  return '<span class="task-item-child-time-tracking"><span>' + escHtml(logged) + '</span>' +
    (remaining ? '<span class="task-item-child-time-tracking-remaining">' + escHtml(remaining) + '</span>' : "") +
    '</span>';
}

function renderTaskChildWorkItemColumnConfigMenu(keyword) {
  var settings = taskChildWorkItemColumnSettings();
  var filter = String(keyword === undefined ? $("#taskItemChildWorkItemsColumnSearch").val() || "" : keyword).trim().toLowerCase();
  var html = "";
  taskChildWorkItemColumnDefinitions.forEach(function (definition) {
    if (filter && definition.label.toLowerCase().indexOf(filter) === -1) return;
    var checked = settings.order.indexOf(definition.key) !== -1 ? " checked" : "";
    html += '<label class="task-item-child-column-option"><input type="checkbox" data-child-column-toggle="' + definition.key + '"' + checked + '><span>' + escHtml(definition.label) + '</span></label>';
  });
  $("#taskItemChildWorkItemsColumnOptions").html(html || '<div class="task-item-child-column-option-empty">No columns found.</div>');
  $("#taskItemChildWorkItemsColumnCount").text(settings.order.length + " of " + taskChildWorkItemColumnDefinitions.length);
}

function taskChildWorkItemColumnHeaderHtml(definition) {
  var key = definition.key;
  return '<div class="task-item-child-column-head" data-child-column-head="' + key + '">' +
    '<span class="task-item-child-column-head-label">' + escHtml(definition.label) + '</span>' +
    '<div class="dropdown task-item-child-column-head-menu">' +
      '<button type="button" class="task-item-child-column-head-menu-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="' + escHtml(definition.label) + ' column options"><i class="fa-solid fa-ellipsis"></i></button>' +
      '<ul class="dropdown-menu dropdown-menu-end">' +
        '<li><button type="button" class="dropdown-item" data-child-column-action="sort-asc" data-child-column-key="' + key + '">Sort in ascending order</button></li>' +
        '<li><button type="button" class="dropdown-item" data-child-column-action="sort-desc" data-child-column-key="' + key + '">Sort in descending order</button></li>' +
        '<li><hr class="dropdown-divider"></li>' +
        '<li><button type="button" class="dropdown-item" data-child-column-action="move-first" data-child-column-key="' + key + '">Move column to first position</button></li>' +
        '<li><button type="button" class="dropdown-item" data-child-column-action="move-left" data-child-column-key="' + key + '">Move column to left</button></li>' +
        '<li><button type="button" class="dropdown-item" data-child-column-action="move-right" data-child-column-key="' + key + '">Move column to right</button></li>' +
        '<li><button type="button" class="dropdown-item" data-child-column-action="move-last" data-child-column-key="' + key + '">Move column to last position</button></li>' +
        '<li><button type="button" class="dropdown-item" data-child-column-action="remove" data-child-column-key="' + key + '">Remove column</button></li>' +
        '<li><hr class="dropdown-divider"></li>' +
        '<li><button type="button" class="dropdown-item" data-child-column-action="resize" data-child-column-key="' + key + '">Resize column</button></li>' +
      '</ul>' +
    '</div>' +
  '</div>';
}

function taskChildWorkItemSortValue(row, key) {
  if (key === "work") return String((row.work_item_key || "") + " " + (row.title || "")).toLowerCase();
  if (key === "work_type") return String(row.work_type_name || "Task").toLowerCase();
  if (key === "original_estimate") return Number(row.original_estimate_value || 0);
  if (key === "labels") return String((row.labels || []).map(function (label) { return label.name || ""; }).join(", ")).toLowerCase();
  if (key === "task_status") return String((row.task_status_labels || []).map(function (label) { return label.name || ""; }).join(", ")).toLowerCase();
  if (key === "parent") return String(row.parent_display || "").toLowerCase();
  if (key === "priority") return ["Highest", "High", "Medium", "Low", "Lowest"].indexOf(String(row.priority || "Medium"));
  if (key === "time_tracking") return String(row.time_tracking || "").toLowerCase();
  if (key === "assignee") return String(row.assignee_name || "Unassigned").toLowerCase();
  if (key === "due_date") return String(row.due_date || "");
  if (key === "start_date") return String(row.start_date || "");
  if (key === "reporter") return String(row.reporter_name || "Unassigned").toLowerCase();
  if (key === "amendement_date") return String(row.amendement_date || "");
  if (key === "amendement_time") return String(row.amendement_time || "");
  if (key === "second_amendement_date") return String(row.second_amendement_date || "");
  if (key === "second_amendement_time") return String(row.second_amendement_time || "");
  if (key === "status") return String(row.status_name || "").toLowerCase();
  return "";
}

function taskChildWorkItemColumnCellHtml(key, row, context) {
  var childItemId = context.childItemId;
  if (key === "work") {
    return '<div class="task-item-child-col-work">' + (context.canEdit
      ? (context.isTitleEditing
        ? '<div class="task-item-child-title-editor"><button type="button" class="btn task-item-child-open-trigger task-item-child-open-btn" data-child-item-id="' + childItemId + '" title="' + escHtml(context.workText || context.workKeyText) + '"><span class="task-item-child-open-key">' + escHtml(context.workKeyText) + '</span></button><input type="text" class="form-control form-control-sm task-item-child-title-input" data-child-item-id="' + childItemId + '" value="' + escHtml(context.workTitle) + '" maxlength="255" placeholder="Work item title"><div class="task-item-child-title-editor-actions"><button type="button" class="btn task-item-child-title-save-btn" data-child-item-id="' + childItemId + '" title="Save title"><i class="fa-solid fa-check"></i></button><button type="button" class="btn task-item-child-title-cancel-btn" data-child-item-id="' + childItemId + '" title="Cancel title edit"><i class="fa-solid fa-xmark"></i></button></div></div>'
        : '<div class="task-item-child-work-display"><button type="button" class="btn task-item-child-open-trigger task-item-child-open-btn" data-child-item-id="' + childItemId + '" title="' + escHtml(context.workText || context.workKeyText) + '"><span class="task-item-child-open-key">' + escHtml(context.workKeyText) + '</span></button><button type="button" class="btn task-item-child-open-trigger task-item-child-title-link" data-child-item-id="' + childItemId + '" title="' + escHtml(context.workText || context.workKeyText) + '">' + escHtml(context.workTitle || "Open work item") + '</button><button type="button" class="btn task-item-child-title-edit-btn" data-child-item-id="' + childItemId + '" title="Edit title"><i class="fa-regular fa-pen-to-square"></i></button></div>')
      : '<button type="button" class="btn task-item-child-open-btn" data-child-item-id="' + childItemId + '" title="' + escHtml(context.workText || context.workKeyText) + '"><span class="task-item-child-open-key">' + escHtml(context.workKeyText) + '</span><span class="task-item-child-open-title">' + escHtml(context.workTitle || "Open work item") + '</span></button>') + '</div>';
  }
  if (key === "work_type") return '<div class="task-item-child-col-work-type">' + escHtml(context.workTypeName || "Task") + '</div>';
  if (key === "original_estimate") return '<div class="task-item-child-col-estimate">' + context.estimateHtml + '</div>';
  if (key === "labels") return '<div class="task-item-child-col-labels">' + context.labelsHtml + '</div>';
  if (key === "task_status") return '<div class="task-item-child-col-task-status">' + context.taskStatusLabelsHtml + '</div>';
  if (key === "parent") return '<div class="task-item-child-col-parent">' + escHtml(context.parentDisplay || "—") + '</div>';
  if (key === "priority") return '<div class="task-item-child-col-priority">' + (context.canEdit && context.activeField === "priority" ? '<select class="form-select form-select-sm task-item-child-picker-select task-item-child-priority-select" data-child-item-id="' + childItemId + '" data-child-field="priority">' + childWorkItemPriorityOptionsHtml(context.priority) + '</select>' : context.canEdit ? '<button type="button" class="btn task-item-child-display-btn task-item-child-picker-trigger" data-child-item-id="' + childItemId + '" data-child-field="priority">' + childWorkItemPriorityPreviewHtml(context.priority, false) + '</button>' : '<span class="task-item-child-readonly-pill">' + childWorkItemPriorityPreviewHtml(context.priority, false) + '</span>') + '</div>';
  if (key === "time_tracking") return '<div class="task-item-child-col-time-tracking">' + context.timeTrackingHtml + '</div>';
  if (key === "assignee") return '<div class="task-item-child-col-assignee">' + (context.canEdit && context.activeField === "assignee" ? '<select class="form-select form-select-sm task-item-child-picker-select task-item-child-assignee-select" data-child-item-id="' + childItemId + '" data-child-field="assignee">' + childWorkItemAssigneeOptionsHtml(Number(row.assignee_user_id || 0)) + '</select>' : context.canEdit ? '<button type="button" class="btn task-item-child-display-btn task-item-child-picker-trigger" data-child-item-id="' + childItemId + '" data-child-field="assignee">' + childWorkItemAssigneePreviewHtml(Number(row.assignee_user_id || 0), context.assignee) + '</button>' : escHtml(context.assignee)) + '</div>';
  if (key === "due_date") return '<div class="task-item-child-col-date">' + escHtml(taskChildWorkItemDateDisplay(row.due_date)) + '</div>';
  if (key === "start_date") return '<div class="task-item-child-col-date">' + escHtml(taskChildWorkItemDateDisplay(row.start_date)) + '</div>';
  if (key === "reporter") return '<div class="task-item-child-col-reporter">' + escHtml(context.reporter || "Unassigned") + '</div>';
  if (key === "amendement_date") return '<div class="task-item-child-col-date">' + escHtml(taskChildWorkItemDateDisplay(row.amendement_date)) + '</div>';
  if (key === "amendement_time") return '<div class="task-item-child-col-time">' + escHtml(taskChildWorkItemTimeDisplay(row.amendement_time)) + '</div>';
  if (key === "second_amendement_date") return '<div class="task-item-child-col-date">' + escHtml(taskChildWorkItemDateDisplay(row.second_amendement_date)) + '</div>';
  if (key === "second_amendement_time") return '<div class="task-item-child-col-time">' + escHtml(taskChildWorkItemTimeDisplay(row.second_amendement_time)) + '</div>';
  return '<div class="task-item-child-col-status' + context.statusClass + '">' + (context.canEdit && context.activeField === "status" ? '<select class="form-select form-select-sm task-item-child-picker-select task-item-child-status-select" data-child-item-id="' + childItemId + '" data-child-field="status">' + childWorkItemStatusOptionsHtml(context.statusColumnId) + '</select>' : context.canEdit ? '<button type="button" class="btn task-item-child-display-btn task-item-child-display-btn-status task-item-child-picker-trigger' + context.statusClass + '" data-child-item-id="' + childItemId + '" data-child-field="status">' + escHtml(context.statusName) + '<i class="fa-solid fa-chevron-down"></i></button>' : escHtml(context.statusName)) + '</div>';
}

function renderChildWorkItemsSectionConfigured() {
  var childInfo = normalizeChildWorkItems(itemDetailModalState.childWorkItems);
  itemDetailModalState.childWorkItems = childInfo;
  var settings = taskChildWorkItemColumnSettings();
  var columns = settings.order.map(taskChildWorkItemColumnDefinition).filter(Boolean);
  var template = taskChildWorkItemColumnGridTemplate(settings);
  var $head = $("#taskItemChildWorkItemsSection .task-item-child-table-head");
  $head.html(columns.map(taskChildWorkItemColumnHeaderHtml).join(""));
  applyTaskChildWorkItemColumnTemplate($head, template);
  renderTaskChildWorkItemColumnConfigMenu();

  var rows = Array.isArray(childInfo.items) ? childInfo.items.slice() : [];
  if (settings.sortKey) {
    var direction = settings.sortDirection === "desc" ? -1 : 1;
    rows.sort(function (a, b) {
      var left = taskChildWorkItemSortValue(a, settings.sortKey);
      var right = taskChildWorkItemSortValue(b, settings.sortKey);
      if (left === right) return 0;
      return (left < right ? -1 : 1) * direction;
    });
  }

  var total = Number(childInfo.total || 0);
  var done = Number(childInfo.done || 0);
  var progress = Math.max(0, Math.min(100, Number(childInfo.progress_percent || 0)));
  $("#taskItemChildWorkItemsCount").text(String(total));
  $("#taskItemChildWorkItemsProgressText").text(String(progress) + "% Done (" + done + "/" + total + ")");
  $("#taskItemChildWorkItemsProgressBar").css("width", String(progress) + "%");
  applyTaskChildWorkItemColumnTemplate($("#taskItemChildWorkItemsList"), template);

  var html = "";
  var editingTitleItemId = Number(itemDetailModalState.childTitleEditingItemId || 0);
  var pickerItemId = Number(itemDetailModalState.childPickerItemId || 0);
  var pickerField = String(itemDetailModalState.childPickerField || "").trim();
  rows.forEach(function (row) {
    row = row || {};
    var childItemId = Number(row.id || 0);
    var workKey = String(row.work_item_key || "").trim();
    var workTitle = String(row.title || "").trim();
    var workText = String((workKey ? workKey + " " : "") + workTitle).trim();
    var priority = String(row.priority || "Medium").trim() || "Medium";
    var estimateHtml = childWorkItemEstimateHtml(row.original_estimate_value, row.original_estimate_unit, row.original_estimate);
    var labelsHtml = childWorkItemLabelsHtml(row.labels);
    var taskStatusLabelsHtml = childWorkItemLabelsHtml(row.task_status_labels);
    var assignee = String(row.assignee_name || "").trim() || "Unassigned";
    var reporter = String(row.reporter_name || "").trim() || "Unassigned";
    var statusColumnId = Number(row.column_id || 0);
    var statusName = childWorkItemStatusName(statusColumnId, row.status_name);
    var statusClass = Number(row.is_done || 0) > 0 ? " task-item-child-col-status-done" : "";
    var workKeyText = workKey || (childItemId > 0 ? "Item #" + childItemId : "Work item");
    var activeField = pickerItemId === childItemId ? pickerField : "";
    var context = {
      childItemId: childItemId,
      workTitle: workTitle,
      workText: workText,
      workKeyText: workKeyText,
      estimateHtml: estimateHtml,
      labelsHtml: labelsHtml,
      taskStatusLabelsHtml: taskStatusLabelsHtml,
      workTypeName: String(row.work_type_name || "Task").trim() || "Task",
      parentDisplay: String(row.parent_display || "").trim(),
      timeTrackingHtml: taskChildWorkItemTimeTrackingHtml(row),
      priority: priority,
      assignee: assignee,
      reporter: reporter,
      statusColumnId: statusColumnId,
      statusName: statusName,
      statusClass: statusClass,
      activeField: activeField,
      canEdit: canEdit,
      isTitleEditing: editingTitleItemId === childItemId,
    };
    var rowClass = "task-item-child-row" + (canEdit ? " task-item-child-row-editable" : "") + (context.isTitleEditing ? " task-item-child-row-title-editing" : "");
    var mobileMeta = '<div class="task-item-child-mobile-meta"><span class="task-item-child-mobile-estimate">' + estimateHtml + '</span><span class="task-item-child-mobile-labels">' + labelsHtml + '</span><span class="task-item-child-mobile-priority" aria-hidden="true">' + childWorkItemPriorityPreviewHtml(priority, true) + '</span><span class="task-item-child-mobile-status' + statusClass + '">' + escHtml(statusName) + '</span></div>';
    html += '<div class="' + rowClass + '" data-child-item-id="' + childItemId + '">' + columns.map(function (column) { return taskChildWorkItemColumnCellHtml(column.key, row, context); }).join("") + mobileMeta + '</div>';
  });
  $("#taskItemChildWorkItemsList").html(html || '<div class="task-item-child-empty">No child work items.</div>');
}

function renderChildWorkItemsSection() {
  return renderChildWorkItemsSectionConfigured();
  var childInfo = normalizeChildWorkItems(itemDetailModalState.childWorkItems);
  itemDetailModalState.childWorkItems = childInfo;

  var total = Number(childInfo.total || 0);
  var done = Number(childInfo.done || 0);
  var progress = Math.max(
    0,
    Math.min(100, Number(childInfo.progress_percent || 0)),
  );

  $("#taskItemChildWorkItemsCount").text(String(total));
  $("#taskItemChildWorkItemsProgressText").text(
    String(progress) + "% Done (" + done + "/" + total + ")",
  );
  $("#taskItemChildWorkItemsProgressBar").css("width", String(progress) + "%");

  var rows = Array.isArray(childInfo.items) ? childInfo.items : [];
  var html = "";
  var editingTitleItemId = Number(itemDetailModalState.childTitleEditingItemId || 0);
  var pickerItemId = Number(itemDetailModalState.childPickerItemId || 0);
  var pickerField = String(itemDetailModalState.childPickerField || "").trim();

  for (var i = 0; i < rows.length; i++) {
    var row = rows[i] || {};
    var childItemId = Number(row.id || 0);
    var workKey = String(row.work_item_key || "").trim();
    var workTitle = String(row.title || "").trim();
    var workText = String((workKey ? workKey + " " : "") + workTitle).trim();
    var priority = String(row.priority || "Medium").trim() || "Medium";
    var estimateHtml = childWorkItemEstimateHtml(
      row.original_estimate_value,
      row.original_estimate_unit,
      row.original_estimate,
    );
    var labelsHtml = childWorkItemLabelsHtml(row.labels);
    var assignee = String(row.assignee_name || "").trim() || "Unassigned";
    var statusColumnId = Number(row.column_id || 0);
    var statusName = childWorkItemStatusName(statusColumnId, row.status_name);
    var statusClass =
      Number(row.is_done || 0) > 0 ? " task-item-child-col-status-done" : "";
    var workKeyText = workKey || (childItemId > 0 ? "Item #" + childItemId : "Work item");
    var isTitleEditing = editingTitleItemId === childItemId;
    var activeField = pickerItemId === childItemId ? pickerField : "";

    if (canEdit) {
      html +=
        '<div class="task-item-child-row task-item-child-row-editable' +
        (isTitleEditing ? " task-item-child-row-title-editing" : "") +
        '" data-child-item-id="' +
        childItemId +
        '">' +
        '<div class="task-item-child-col-work">' +
        (isTitleEditing
          ? '<div class="task-item-child-title-editor">' +
            '<button type="button" class="btn task-item-child-open-trigger task-item-child-open-btn" data-child-item-id="' +
            childItemId +
            '" title="' +
            escHtml(workText || workKeyText) +
            '">' +
            '<span class="task-item-child-open-key">' +
            escHtml(workKeyText) +
            "</span>" +
            "</button>" +
            '<input type="text" class="form-control form-control-sm task-item-child-title-input" data-child-item-id="' +
            childItemId +
            '" value="' +
            escHtml(workTitle) +
            '" maxlength="255" placeholder="Work item title">' +
            '<div class="task-item-child-title-editor-actions">' +
            '<button type="button" class="btn task-item-child-title-save-btn" data-child-item-id="' +
            childItemId +
            '" title="Save title"><i class="fa-solid fa-check"></i></button>' +
            '<button type="button" class="btn task-item-child-title-cancel-btn" data-child-item-id="' +
            childItemId +
            '" title="Cancel title edit"><i class="fa-solid fa-xmark"></i></button>' +
            "</div>" +
            "</div>"
          : '<div class="task-item-child-work-display">' +
            '<button type="button" class="btn task-item-child-open-trigger task-item-child-open-btn" data-child-item-id="' +
            childItemId +
            '" title="' +
            escHtml(workText || workKeyText) +
            '">' +
            '<span class="task-item-child-open-key">' +
            escHtml(workKeyText) +
            "</span>" +
            "</button>" +
            '<button type="button" class="btn task-item-child-open-trigger task-item-child-title-link" data-child-item-id="' +
            childItemId +
            '" title="' +
            escHtml(workText || workKeyText) +
            '">' +
            escHtml(workTitle || "Open work item") +
            "</button>" +
            '<button type="button" class="btn task-item-child-title-edit-btn" data-child-item-id="' +
            childItemId +
            '" title="Edit title"><i class="fa-regular fa-pen-to-square"></i></button>' +
            "</div>") +
        "</div>" +
        '<div class="task-item-child-col-estimate">' +
        estimateHtml +
        "</div>" +
        '<div class="task-item-child-col-labels">' +
        labelsHtml +
        "</div>" +
        '<div class="task-item-child-col-priority">' +
        (activeField === "priority"
          ? '<select class="form-select form-select-sm task-item-child-picker-select task-item-child-priority-select" data-child-item-id="' +
            childItemId +
            '" data-child-field="priority">' +
            childWorkItemPriorityOptionsHtml(priority) +
            "</select>"
          : '<button type="button" class="btn task-item-child-display-btn task-item-child-picker-trigger" data-child-item-id="' +
            childItemId +
            '" data-child-field="priority">' +
            childWorkItemPriorityPreviewHtml(priority, false) +
            "</button>") +
        '<span class="task-item-child-mobile-priority" aria-hidden="true">' +
        childWorkItemPriorityPreviewHtml(priority, true) +
        "</span>" +
        "</div>" +
        '<div class="task-item-child-col-assignee">' +
        (activeField === "assignee"
          ? '<select class="form-select form-select-sm task-item-child-picker-select task-item-child-assignee-select" data-child-item-id="' +
            childItemId +
            '" data-child-field="assignee">' +
            childWorkItemAssigneeOptionsHtml(Number(row.assignee_user_id || 0)) +
            "</select>"
          : '<button type="button" class="btn task-item-child-display-btn task-item-child-picker-trigger" data-child-item-id="' +
            childItemId +
            '" data-child-field="assignee">' +
            childWorkItemAssigneePreviewHtml(Number(row.assignee_user_id || 0), assignee) +
            "</button>") +
        '<span class="task-item-child-mobile-assignee d-none">' +
        escHtml(assignee) +
        "</span>" +
        "</div>" +
        '<div class="task-item-child-col-status' +
        statusClass +
        '">' +
        (activeField === "status"
          ? '<select class="form-select form-select-sm task-item-child-picker-select task-item-child-status-select" data-child-item-id="' +
            childItemId +
            '" data-child-field="status">' +
            childWorkItemStatusOptionsHtml(statusColumnId) +
            "</select>"
          : '<button type="button" class="btn task-item-child-display-btn task-item-child-display-btn-status task-item-child-picker-trigger' +
            statusClass +
            '" data-child-item-id="' +
            childItemId +
            '" data-child-field="status">' +
            escHtml(statusName) +
            '<i class="fa-solid fa-chevron-down"></i>' +
            "</button>") +
        "</div>" +
        '<div class="task-item-child-mobile-meta">' +
        '<span class="task-item-child-mobile-estimate">' +
        estimateHtml +
        "</span>" +
        '<span class="task-item-child-mobile-labels">' +
        labelsHtml +
        "</span>" +
        '<span class="task-item-child-mobile-priority" aria-hidden="true">' +
        childWorkItemPriorityPreviewHtml(priority, true) +
        "</span>" +
        '<span class="task-item-child-mobile-status' +
        statusClass +
        '">' +
        escHtml(statusName) +
        "</span>" +
        "</div>" +
        "</div>";
      continue;
    }

    html +=
      '<div class="task-item-child-row">' +
      '<div class="task-item-child-col-work">' +
      '<button type="button" class="btn task-item-child-open-btn" data-child-item-id="' +
      childItemId +
      '" title="' +
      escHtml(workText || workKeyText) +
      '">' +
      '<span class="task-item-child-open-key">' +
      escHtml(workKeyText) +
      "</span>" +
      '<span class="task-item-child-open-title">' +
      escHtml(workTitle || "Open work item") +
      "</span>" +
      "</button>" +
      "</div>" +
      '<div class="task-item-child-col-estimate">' +
      estimateHtml +
      "</div>" +
      '<div class="task-item-child-col-labels">' +
      labelsHtml +
      "</div>" +
      '<div class="task-item-child-col-priority">' +
      '<span class="task-item-child-readonly-pill">' +
      childWorkItemPriorityPreviewHtml(priority, false) +
      "</span>" +
      "</div>" +
      '<div class="task-item-child-col-assignee">' +
      escHtml(assignee) +
      "</div>" +
      '<div class="task-item-child-col-status' +
      statusClass +
      '">' +
      escHtml(statusName) +
      "</div>" +
      '<div class="task-item-child-mobile-meta">' +
      '<span class="task-item-child-mobile-estimate">' +
      estimateHtml +
      "</span>" +
      '<span class="task-item-child-mobile-labels">' +
      labelsHtml +
      "</span>" +
      '<span class="task-item-child-mobile-priority" aria-hidden="true">' +
      childWorkItemPriorityPreviewHtml(priority, true) +
      "</span>" +
      '<span class="task-item-child-mobile-status' +
      statusClass +
      '">' +
      escHtml(statusName) +
      "</span>" +
      "</div>" +
      "</div>";
  }

  $("#taskItemChildWorkItemsList").html(
    html || '<div class="task-item-child-empty">No child work items.</div>',
  );
}

function renderDetailKeyTrail() {
  var workKey = String(itemDetailModalState.workItemKey || "").trim();
  var parentKey = String(itemDetailModalState.parentWorkItemKey || "").trim();
  var workTypeName = String(itemDetailModalState.workTypeName || "Task");
  var workTypeIcon = normalizeWorkTypeIcon(
    itemDetailModalState.workTypeIcon,
    workTypeName,
  );
  var parentTypeName = String(
    itemDetailModalState.parentWorkTypeName || "Task",
  );
  var parentTypeIcon = normalizeWorkTypeIcon(
    itemDetailModalState.parentWorkTypeIcon,
    parentTypeName,
  );

  var trail = "";
  if (parentKey && workKey) {
    trail = parentKey + " / " + workKey;
  } else if (workKey) {
    trail = workKey;
  }

  if (!trail) {
    $("#taskItemDetailKeyTrail").addClass("d-none").empty();
    $("#taskItemDetailModalTitle").text("Work item");
    return;
  }

  var itemId = Number(itemDetailModalState.itemId || 0);
  var currentWorkTypeId = Number(
    itemDetailModalState.cardEl
      ? $(itemDetailModalState.cardEl).attr("data-work-type-id") || 0
      : 0,
  );

  var currentTypeHtml =
    '<span class="dropdown task-item-detail-type-dropdown">' +
    '<button class="btn task-item-detail-type-btn task-work-type-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-work-type-id="' +
    currentWorkTypeId +
    '" data-work-type-name="' +
    escHtml(workTypeName) +
    '" data-work-type-icon="' +
    escHtml(workTypeIcon) +
    '" data-work-type-remark="" data-item-id="' +
    itemId +
    '" title="' +
    escHtml(workTypeName) +
    '">' +
    workTypeIconHtml(workTypeIcon, workTypeName, "task-item-detail-key-icon") +
    "</button>" +
    '<ul class="dropdown-menu task-work-type-menu task-item-work-type-menu" data-item-id="' +
    itemId +
    '">' +
    workTypeMenuHtml() +
    "</ul>" +
    "</span>";

  var currentHtml =
    '<span class="task-item-detail-key-segment">' +
    currentTypeHtml +
    '<span class="task-item-detail-key-text">' +
    escHtml(workKey) +
    "</span>" +
    "</span>";

  var html = currentHtml;
  if (parentKey) {
    html =
      '<span class="task-item-detail-key-segment">' +
      workTypeIconHtml(
        parentTypeIcon,
        parentTypeName,
        "task-item-detail-key-icon",
      ) +
      '<span class="task-item-detail-key-text">' +
      escHtml(parentKey) +
      "</span>" +
      "</span>" +
      '<span class="task-item-detail-key-sep">/</span>' +
      currentHtml;
  }

  $("#taskItemDetailKeyTrail")
    .removeClass("d-none")
    .html('<span class="task-item-detail-key-main">' + html + "</span>");
  $("#taskItemDetailModalTitle").text(trail);
}

function formatDetailMetaTime(value) {
  var text = String(value || "").trim();
  if (!/^\d{2}:\d{2}(:\d{2})?$/.test(text)) {
    return "";
  }

  var parts = text.split(":");
  var hours = Number(parts[0] || 0);
  var minutes = Number(parts[1] || 0);
  if (Number.isNaN(hours) || Number.isNaN(minutes)) {
    return "";
  }

  var suffix = hours >= 12 ? "PM" : "AM";
  var hourValue = hours % 12;
  if (!hourValue) {
    hourValue = 12;
  }

  return hourValue + ":" + String(minutes).padStart(2, "0") + " " + suffix;
}

function formatDetailMetaDateTime(dateValue, timeValue) {
  var dateText = formatCardDateLabel(dateValue);
  var timeText = formatDetailMetaTime(timeValue);
  if (dateText && timeText) {
    return dateText + " at " + timeText;
  }
  if (dateText) {
    return dateText;
  }
  if (timeText) {
    return timeText;
  }
  return "";
}

function renderDetailMeta(info) {
  var detail = info && typeof info === "object" ? info : {};
  var createdText = formatDetailMetaDateTime(
    detail.create_date,
    detail.create_time,
  );
  var updatedExactText = formatDetailMetaDateTime(
    detail.update_date,
    detail.update_time,
  );
  var updatedRelativeText = formatRelativeFromCardDate(detail.update_date);
  var updatedText = "";

  if (updatedRelativeText && /ago/i.test(updatedRelativeText)) {
    updatedText = updatedRelativeText;
  } else {
    updatedText = updatedExactText || updatedRelativeText;
  }

  $("#taskItemDetailCreatedMeta").text(createdText || "Not available");
  $("#taskItemDetailUpdatedMeta").text(updatedText || "Not available");
}

function renderModalLabelChips() {
  var ids = Array.isArray(itemDetailModalState.selectedLabelIds)
    ? itemDetailModalState.selectedLabelIds
    : [];
  var html = "";
  for (var i = 0; i < state.labels.length; i++) {
    var item = state.labels[i] || {};
    var id = Number(item.id || 0);
    var name = String(item.name || "").trim();
    if (!id || !name || ids.indexOf(id) === -1) {
      continue;
    }
    html +=
      '<span class="task-label-pill task-item-detail-label-chip" style="' +
      labelPillStyle(item.color, "#DCE8FF") +
      '">' +
      escHtml(name) +
      '<button type="button" class="btn task-item-detail-label-chip-remove" data-label-id="' +
      id +
      '" title="Remove label"><i class="fa-solid fa-xmark"></i></button>' +
      "</span>";
  }

  $("#taskItemDetailLabelChips").html(
    html || '<span class="task-item-detail-empty-text">Select labels</span>',
  );
}

function renderModalLabelOptions() {
  var keyword = String($("#taskItemDetailLabelSearchInput").val() || "")
    .trim()
    .toLowerCase();

  var html = "";
  for (var i = 0; i < state.labels.length; i++) {
    var item = state.labels[i] || {};
    var id = Number(item.id || 0);
    var name = String(item.name || "").trim();
    if (!id || !name) {
      continue;
    }

    if (keyword && name.toLowerCase().indexOf(keyword) === -1) {
      continue;
    }

    var checked =
      itemDetailModalState.selectedLabelIds.indexOf(id) !== -1
        ? " checked"
        : "";
    html +=
      '<label class="task-item-detail-label-option">' +
      '<input class="form-check-input task-item-detail-label-checkbox" type="checkbox" value="' +
      id +
      '"' +
      checked +
      ">" +
      '<span class="task-item-detail-label-option-name task-label-pill" style="' +
      labelPillStyle(item.color, "#DCE8FF") +
      '">' +
      escHtml(name) +
      "</span>" +
      (state.isProjectOwner
        ? '<button type="button" class="btn task-item-detail-label-option-delete-btn" data-label-id="' +
      id +
      '" title="Delete label"><i class="fa-regular fa-trash-can"></i></button>'
        : "") +
      "</label>";
  }

  $("#taskItemDetailLabelOptionList").html(
    html || '<div class="task-label-empty">No labels found.</div>',
  );
}

function updateTaskCardTitle($card, title) {
  if (!$card || !$card.length) {
    return;
  }

  var $title = $card.find(".task-item-title").first();
  if (!$title.length) {
    return;
  }

  var $editButton = $title.find(".task-item-edit-btn").first().detach();
  var $titleText = $title.find(".task-item-title-text").first();
  if (!$titleText.length) {
    $titleText = $('<span class="task-item-title-text"></span>');
  }
  $titleText.text(String(title || ""));
  $title.empty().append($titleText);

  if (!$editButton.length && typeof canEdit !== "undefined" && canEdit) {
    $editButton = $(
      '<button class="btn task-item-menu-btn task-item-edit-btn" type="button" title="Edit title" aria-label="Edit title"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>',
    );
  }

  if ($editButton.length) {
    $title.append($editButton);
  }
}

function updateCardFromDetail(detail) {
  var $card = $(itemDetailModalState.cardEl || null);
  if (!$card.length || !detail || typeof detail !== "object") {
    return;
  }

  function hasDetailValue(key) {
    return Object.prototype.hasOwnProperty.call(detail, key);
  }

  var assigneeUserId = hasDetailValue("assignee_user_id")
    ? Number(detail.assignee_user_id || 0)
    : Number($card.attr("data-assignee-user-id") || 0);
  var assigneeName = hasDetailValue("assignee_name")
    ? String(detail.assignee_name || "").trim() || "Unassigned"
    : String($card.attr("data-assignee-name") || "").trim() || "Unassigned";
  var reporterUserId = hasDetailValue("reporter_user_id")
    ? Number(detail.reporter_user_id || 0)
    : Number($card.attr("data-reporter-user-id") || 0);
  var reporterName = hasDetailValue("reporter_name")
    ? String(detail.reporter_name || "").trim()
    : String($card.attr("data-reporter-name") || "").trim();
  var priority = hasDetailValue("priority")
    ? String(detail.priority || "Medium").trim() || "Medium"
    : String($card.attr("data-priority") || "Medium").trim() || "Medium";
  var dueDate = hasDetailValue("due_date")
    ? String(detail.due_date || "").trim()
    : String($card.attr("data-due-date") || "").trim();
  var startDate = hasDetailValue("start_date")
    ? String(detail.start_date || "").trim()
    : String($card.attr("data-start-date") || "").trim();
  var createDate = String(
    hasDetailValue("create_date")
      ? detail.create_date
      : $card.attr("data-create-date") || "",
  ).trim();
  var updateDate = String(
    hasDetailValue("update_date")
      ? detail.update_date
      : $card.attr("data-update-date") || "",
  ).trim();
  var parentDisplay = hasDetailValue("parent_display")
    ? String(detail.parent_display || "").trim()
    : String($card.attr("data-parent-display") || "").trim();

  if (hasDetailValue("title")) {
    updateTaskCardTitle($card, String(detail.title || "").trim());
  }

  var workTypeId = hasDetailValue("work_type_id")
    ? Number(detail.work_type_id || 0)
    : Number($card.attr("data-work-type-id") || 0);
  var workTypeName = hasDetailValue("work_type_name")
    ? String(detail.work_type_name || "Task").trim() || "Task"
    : String($card.attr("data-work-type-name") || "Task").trim() || "Task";
  var workTypeIcon = normalizeWorkTypeIcon(
    hasDetailValue("work_type_svg_icon")
      ? detail.work_type_svg_icon
      : $card.attr("data-work-type-icon") || "",
    workTypeName,
  );

  var estimateValue = hasDetailValue("original_estimate_value")
    ? Number(detail.original_estimate_value || 0)
    : Number($card.attr("data-original-estimate-value") || 0);
  var estimateUnit = hasDetailValue("original_estimate_unit")
    ? String(detail.original_estimate_unit || "minutes")
    : String($card.attr("data-original-estimate-unit") || "minutes");

  var amendementDate = hasDetailValue("amendement_date")
    ? String(detail.amendement_date || "").trim()
    : String($card.attr("data-amendement-date") || "").trim();
  var amendementTimeMinutes = hasDetailValue("amendement_time_minutes")
    ? Number(detail.amendement_time_minutes || 0)
    : Number($card.attr("data-amendement-time-minutes") || 0);
  var secondAmendementDate = hasDetailValue("second_amendement_date")
    ? String(detail.second_amendement_date || "").trim()
    : String($card.attr("data-second-amendement-date") || "").trim();
  var secondAmendementTimeMinutes = hasDetailValue(
    "second_amendement_time_minutes",
  )
    ? Number(detail.second_amendement_time_minutes || 0)
    : Number($card.attr("data-second-amendement-time-minutes") || 0);

  $card
    .attr("data-work-type-id", workTypeId)
    .attr("data-assignee-user-id", assigneeUserId)
    .attr("data-assignee-name", assigneeName)
    .attr("data-reporter-user-id", reporterUserId)
    .attr("data-reporter-name", reporterName)
    .attr("data-work-type-name", workTypeName)
    .attr("data-work-type-icon", workTypeIcon)
    .attr("data-priority", priority)
    .attr("data-start-date", startDate)
    .attr("data-due-date", dueDate)
    .attr("data-create-date", createDate)
    .attr("data-update-date", updateDate)
    .attr("data-original-estimate-value", estimateValue)
    .attr("data-original-estimate-unit", estimateUnit)
    .attr("data-amendement-date", amendementDate)
    .attr("data-amendement-time-minutes", amendementTimeMinutes)
    .attr("data-second-amendement-date", secondAmendementDate)
    .attr("data-second-amendement-time-minutes", secondAmendementTimeMinutes);

  var $assigneeBtn = $card.find(".task-item-assignee-btn");
  if ($assigneeBtn.length) {
    $assigneeBtn
      .attr("data-user-id", assigneeUserId)
      .attr("title", assigneeName)
      .toggleClass("task-assignee-pill-unassigned", assigneeUserId <= 0)
      .html(assigneeButtonInner(assigneeUserId, assigneeName));
  }

  if (Array.isArray(detail.labels)) {
    setCardLabels($card, detail.labels);
  }

  if (Array.isArray(detail.task_status_label_ids)) {
    setCardTaskStatusLabels($card, detail.task_status_label_ids);
  }

  if (Object.prototype.hasOwnProperty.call(detail, "parent_item_id")) {
    updateCardParentSubmenuToggle(
      $card,
      Number(detail.parent_item_id || 0),
      parentDisplay,
    );
  }

  applyBoardViewSettingsToCard($card);
  syncCardStatusMeta($card, false);

  if (isBoardGroupedByStatus()) {
    updateColumnInnerScroll($card.closest(".task-column"));
    applyBoardFilters();
    return;
  }

  renderBoardGroupingLayout();
}

function applyItemDetailToModal(
  detail,
  statusLabels,
  parentOptions,
  webLinks,
  itemLinks,
) {
  var info = detail && typeof detail === "object" ? detail : {};
  if (Array.isArray(statusLabels)) {
    normalizeStatusLabels(statusLabels);
  }

  var title = String(info.title || "").trim();
  var description = String(info.description || "").trim();
  var previousTitle = String($("#taskItemDetailTitleInput").val() || "").trim();
  $("#taskItemDetailTitleInput").val(title);
  $("#taskItemDetailDescriptionInput").val(description);
  itemDetailModalState.initialTitle = title;
  itemDetailModalState.initialDescription = description;
  itemDetailModalState.titleEditing = false;
  $(".task-item-detail-title-row").removeClass("is-editing");
  if (
    previousTitle !== title &&
    $("#taskItemDetailModal").hasClass("show") &&
    typeof resizeItemDetailTitleInput === "function"
  ) {
    resizeItemDetailTitleInput({ fitFont: false });
  }

  if (typeof window.setDescriptionEditorContent === "function") {
    window.setDescriptionEditorContent(description);
  }
  if (typeof window.renderItemDetailDescriptionPreview === "function") {
    window.renderItemDetailDescriptionPreview(description);
  }
  if (typeof window.setItemDetailDescriptionEditMode === "function") {
    window.setItemDetailDescriptionEditMode(false, {
      keepDraftNotice: true,
      skipEditorSync: true,
    });
  }

  $("#taskItemDetailEstimateValueInput").val(
    Number(info.original_estimate_value || 0),
  );
  $("#taskItemDetailEstimateUnitInput").val(
    String(info.original_estimate_unit || "minutes"),
  );
  $("#taskItemDetailEstimateMinutesInput").val(0);
  if (typeof toggleEstimateExtraMinutesInput === "function") {
    toggleEstimateExtraMinutesInput();
  }

  var statusIds = Array.isArray(info.task_status_label_ids)
    ? info.task_status_label_ids
    : parseStatusLabelIdsFromRaw(info.task_status || "");
  setSelectedStatusLabels(statusIds);
  $("#taskItemDetailStatusSearchInput").val("");
  renderStatusLabelOptions("");

  // Initialize Bootstrap dropdown AFTER DOM is fully updated
  setTimeout(function() {
    var statusDropdownBtn = document.getElementById('taskItemDetailStatusDropdownBtn');
    if (statusDropdownBtn && typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
      var dropdownInstance = bootstrap.Dropdown.getOrCreateInstance(statusDropdownBtn);
    }
  }, 200);


  renderDetailAssigneeSelect(Number(info.assignee_user_id || 0));
  renderDetailReporterSelect(Number(info.reporter_user_id || 0));
  var effectiveParentOptions = Array.isArray(parentOptions)
    ? parentOptions
    : getBoardEpicParentOptions(itemDetailModalState.itemId);
  renderDetailParentDropdown(
    Number(info.parent_item_id || 0),
    effectiveParentOptions,
  );
  renderDetailTimeTracking(
    info,
    Number(info.original_estimate_value || 0),
    String(info.original_estimate_unit || "minutes"),
  );
  setDetailBoardStatus(
    Number(
      info.column_id ||
        getCardStatusColumnId($(itemDetailModalState.cardEl || null)) ||
        0,
    ),
  );

  itemDetailModalState.webLinks = Array.isArray(webLinks)
    ? webLinks.slice()
    : [];
  itemDetailModalState.webLinkEditorOpen = false;
  renderWebLinksSection();

  itemDetailModalState.workTypeName = String(
    info.work_type_name || itemDetailModalState.workTypeName || "Task",
  );
  itemDetailModalState.workTypeIcon = String(
    info.work_type_svg_icon || itemDetailModalState.workTypeIcon || "",
  );
  itemDetailModalState.workItemKey = String(
    info.work_item_key || itemDetailModalState.workItemKey || "",
  );
  itemDetailModalState.parentWorkItemKey = String(
    info.parent_work_item_key || itemDetailModalState.parentWorkItemKey || "",
  );
  itemDetailModalState.parentWorkTypeName = String(
    info.parent_work_type_name ||
      itemDetailModalState.parentWorkTypeName ||
      "Task",
  );
  itemDetailModalState.parentWorkTypeIcon = String(
    info.parent_work_type_svg_icon ||
      itemDetailModalState.parentWorkTypeIcon ||
      "",
  );
  itemDetailModalState.isParentType =
    Number(info.is_parent_type || 0) > 0 || isParentTypeWorkItem();
  itemDetailModalState.itemLinks = normalizeItemLinks(
    itemLinks || info.item_links,
  );
  renderDetailKeyTrail();
  renderDetailMeta(info);
  itemDetailModalState.childWorkItems = normalizeChildWorkItems(
    info.child_work_items,
  );
  renderChildWorkItemsSection();
  renderChildCreatePanel();
  renderLinkedWorkItemsSection();
  applyDetailFieldVisibility();

  $("#taskItemDetailDueDateInput").val(String(info.due_date || ""));
  $("#taskItemDetailStartDateInput").val(String(info.start_date || ""));
  $("#taskItemDetailAmendDateInput").val(String(info.amendement_date || ""));
  $("#taskItemDetailAmendTimeInput").val(
    info.amendement_time_minutes ? String(info.amendement_time_minutes) : "",
  );
  $("#taskItemDetailSecondAmendDateInput").val(
    String(info.second_amendement_date || ""),
  );
  $("#taskItemDetailSecondAmendTimeInput").val(
    info.second_amendement_time_minutes
      ? String(info.second_amendement_time_minutes)
      : "",
  );

  setDetailPriority(String(info.priority || "Medium"));

  var labels = Array.isArray(info.labels) ? info.labels : [];
  itemDetailModalState.selectedLabelIds = labels
    .map(function (item) {
      return Number(item.id || 0);
    })
    .filter(function (id) {
      return id > 0;
    });
  renderModalLabelChips();
  renderModalLabelOptions();
  updateCardFromDetail(info);
  itemDetailModalState.lastSavedCoreSnapshot = buildModalCoreSnapshot({
    title: title,
    description: description,
  });
  itemDetailModalState.lastSavedDetailSnapshot = buildModalDetailSnapshot({
    assignee_user_id: Number(info.assignee_user_id || 0),
    reporter_user_id: Number(info.reporter_user_id || 0),
    priority: String(info.priority || "Medium"),
    original_estimate_value: Number(info.original_estimate_value || 0),
    original_estimate_unit: String(info.original_estimate_unit || "minutes"),
    task_status_label_ids: statusIds,
    start_date: String(info.start_date || ""),
    due_date: String(info.due_date || ""),
    amendement_date: String(info.amendement_date || ""),
    amendement_time_minutes: Number(info.amendement_time_minutes || 0),
    second_amendement_date: String(info.second_amendement_date || ""),
    second_amendement_time_minutes: Number(
      info.second_amendement_time_minutes || 0,
    ),
  });
  itemDetailModalState.lastSavedLabelsSnapshot = buildModalLabelsSnapshot(
    itemDetailModalState.selectedLabelIds,
  );
  setItemDetailAutosaveStatus("", "");
}

function setItemDetailAutosaveStatus(stateName, message) {
  var $status = $("#taskItemDetailAutosaveStatus");
  if (!$status.length) {
    return;
  }

  if (stateName !== "error") {
    $status
      .addClass("d-none")
      .removeClass("is-saving is-saved is-error")
      .text("");
    return;
  }

  var text = String(message || "").trim();
  $status.removeClass("is-saving is-saved is-error");

  if (!text) {
    $status.addClass("d-none").text("");
    return;
  }

  $status.removeClass("d-none");
  if (stateName === "saving") {
    $status.addClass("is-saving");
  } else if (stateName === "saved") {
    $status.addClass("is-saved");
  } else if (stateName === "error") {
    $status.addClass("is-error");
  }

  $status.text(text);
}

function isPermissionDeniedResponse(res) {
  var message =
    res && typeof res.message === "string" ? String(res.message) : "";
  return message.toLowerCase().indexOf("permission") !== -1;
}

function restoreItemDetailStateAfterDeniedSave() {
  var itemId = Number(itemDetailModalState.itemId || 0);
  setItemDetailAutosaveStatus("", "");
  if (!itemId) {
    return;
  }
  loadItemDetail(itemId);
}

function getModalCoreValues() {
  var descriptionValue = String(
    $("#taskItemDetailDescriptionInput").val() || "",
  );
  if (typeof window.getDescriptionEditorContent === "function") {
    descriptionValue = String(window.getDescriptionEditorContent() || "");
  }

  if (!descriptionHasRenderableContent(descriptionValue)) {
    descriptionValue = "";
  }

  return {
    title: String($("#taskItemDetailTitleInput").val() || "").trim(),
    description: descriptionValue,
  };
}

function buildModalCoreSnapshot(source) {
  var values =
    source && typeof source === "object" ? source : getModalCoreValues();

  var descriptionValue = String(values.description || "");
  if (!descriptionHasRenderableContent(descriptionValue)) {
    descriptionValue = "";
  }

  return JSON.stringify({
    title: String(values.title || "").trim(),
    description: descriptionValue,
  });
}

function getModalDetailValues() {
  return {
    assignee_user_id: Number($("#taskItemDetailAssigneeSelect").val() || 0),
    reporter_user_id: Number($("#taskItemDetailReporterSelect").val() || 0),
    priority: String(itemDetailModalState.selectedPriority || "Medium"),
    original_estimate_value: Number(
      $("#taskItemDetailEstimateValueInput").val() || 0,
    ),
    original_estimate_unit: String(
      $("#taskItemDetailEstimateUnitInput").val() || "minutes",
    ),
    task_status_label_ids: normalizeStatusLabelIdList(
      itemDetailModalState.selectedStatusLabelIds,
    ),
    start_date: String($("#taskItemDetailStartDateInput").val() || ""),
    due_date: String($("#taskItemDetailDueDateInput").val() || ""),
    amendement_date: String($("#taskItemDetailAmendDateInput").val() || ""),
    amendement_time_minutes: Number(
      $("#taskItemDetailAmendTimeInput").val() || 0,
    ),
    second_amendement_date: String(
      $("#taskItemDetailSecondAmendDateInput").val() || "",
    ),
    second_amendement_time_minutes: Number(
      $("#taskItemDetailSecondAmendTimeInput").val() || 0,
    ),
  };
}

function buildModalDetailSnapshot(source) {
  var values =
    source && typeof source === "object" ? source : getModalDetailValues();
  return JSON.stringify({
    assignee_user_id: Number(values.assignee_user_id || 0),
    reporter_user_id: Number(values.reporter_user_id || 0),
    priority: String(values.priority || "Medium"),
    original_estimate_value: Number(values.original_estimate_value || 0),
    original_estimate_unit: String(values.original_estimate_unit || "minutes"),
    task_status_label_ids: normalizeStatusLabelIdList(
      values.task_status_label_ids,
    ),
    start_date: String(values.start_date || ""),
    due_date: String(values.due_date || ""),
    amendement_date: String(values.amendement_date || ""),
    amendement_time_minutes: Number(values.amendement_time_minutes || 0),
    second_amendement_date: String(values.second_amendement_date || ""),
    second_amendement_time_minutes: Number(
      values.second_amendement_time_minutes || 0,
    ),
  });
}

function buildModalLabelsSnapshot(labelIds) {
  return JSON.stringify(normalizeNumericIdList(labelIds));
}

function persistModalLabels(onDone, options) {
  var settings = options && typeof options === "object" ? options : {};
  var itemId = Number(itemDetailModalState.itemId || 0);
  if (!itemId) {
    if (typeof onDone === "function") {
      onDone();
    }
    return;
  }

  var selectedLabelIds = normalizeNumericIdList(
    itemDetailModalState.selectedLabelIds,
  );
  var currentSnapshot = buildModalLabelsSnapshot(selectedLabelIds);
  if (currentSnapshot === itemDetailModalState.lastSavedLabelsSnapshot) {
    if (typeof onDone === "function") {
      onDone();
    }
    return;
  }

  if (itemDetailModalState.labelsSaveInFlight) {
    itemDetailModalState.queuedLabelsSave = true;
    return;
  }

  itemDetailModalState.labelsSaveInFlight = true;
  setItemDetailAutosaveStatus("saving", "Saving changes...");

  postAction(
    {
      task_action: "set_item_labels",
      item_id: itemId,
      label_ids: selectedLabelIds.join(","),
    },
    function (res) {
      syncKnownLabels(res.allLabels || []);
      var hasPendingLocalChange =
        buildModalLabelsSnapshot(itemDetailModalState.selectedLabelIds) !==
        currentSnapshot;
      var detail = {
        labels: Array.isArray(res.labels) ? res.labels : [],
        assignee_user_id: Number($("#taskItemDetailAssigneeSelect").val() || 0),
        assignee_name: String(
          $("#taskItemDetailAssigneeSelect option:selected").text() || "",
        ),
        due_date: String($("#taskItemDetailDueDateInput").val() || ""),
      };
      updateCardFromDetail(detail);
      var savedLabelIds = detail.labels
        .map(function (item) {
          return Number(item.id || 0);
        })
        .filter(function (id) {
          return id > 0;
        });
      if (!hasPendingLocalChange) {
        itemDetailModalState.selectedLabelIds = savedLabelIds.slice();
      }
      itemDetailModalState.lastSavedLabelsSnapshot =
        buildModalLabelsSnapshot(savedLabelIds);
      renderModalLabelChips();
      renderModalLabelOptions();
      loadItemHistory(itemId);
      itemDetailModalState.labelsSaveInFlight = false;
      if (typeof onDone === "function") {
        onDone();
      }

      if (
        itemDetailModalState.queuedLabelsSave ||
        buildModalLabelsSnapshot(itemDetailModalState.selectedLabelIds) !==
          itemDetailModalState.lastSavedLabelsSnapshot
      ) {
        itemDetailModalState.queuedLabelsSave = false;
        persistModalLabels(null, settings);
        return;
      }

      if (!settings.silentSuccess) {
        setItemDetailAutosaveStatus("saved", "All changes saved");
      }
    },
    function (res) {
      itemDetailModalState.labelsSaveInFlight = false;
      if (isPermissionDeniedResponse(res)) {
        itemDetailModalState.queuedLabelsSave = false;
        restoreItemDetailStateAfterDeniedSave();
        return;
      }
      setItemDetailAutosaveStatus("error", "Failed to save changes");
    },
  );
}

function normalizeNumericIdList(list) {
  return (Array.isArray(list) ? list : [])
    .map(function (id) {
      return Number(id || 0);
    })
    .filter(function (id) {
      return id > 0;
    })
    .sort(function (a, b) {
      return a - b;
    });
}

function saveItemDetailsFromModal(closeAfterSave, options) {
  var settings = options && typeof options === "object" ? options : {};
  if (!canEdit) {
    notify("You do not have permission to update work item.");
    return;
  }

  var itemId = Number(itemDetailModalState.itemId || 0);
  if (!itemId) {
    return;
  }

  var detailValues = getModalDetailValues();
  var currentSnapshot = buildModalDetailSnapshot(detailValues);
  if (currentSnapshot === itemDetailModalState.lastSavedDetailSnapshot) {
    if (!settings.suppressNoChangeMessage) {
      showNoChangeMessage();
    }
    return;
  }

  if (itemDetailModalState.detailSaveInFlight) {
    itemDetailModalState.queuedDetailSave = true;
    return;
  }

  itemDetailModalState.detailSaveInFlight = true;
  setItemDetailAutosaveStatus("saving", "Saving changes...");

  var payload = {
    task_action: "update_item_detail",
    item_id: itemId,
    assignee_user_id: detailValues.assignee_user_id,
    reporter_user_id: detailValues.reporter_user_id,
    priority: detailValues.priority,
    original_estimate_value: detailValues.original_estimate_value,
    original_estimate_unit: detailValues.original_estimate_unit,
    task_status_label_ids: detailValues.task_status_label_ids.join(","),
    start_date: detailValues.start_date,
    due_date: detailValues.due_date,
    amendement_date: detailValues.amendement_date,
    amendement_time_minutes: detailValues.amendement_time_minutes,
    second_amendement_date: detailValues.second_amendement_date,
    second_amendement_time_minutes: detailValues.second_amendement_time_minutes,
  };

  postAction(
    payload,
    function (res) {
      var detail =
        res && res.detail && typeof res.detail === "object" ? res.detail : {};

      itemDetailModalState.lastSavedDetailSnapshot =
        buildModalDetailSnapshot(detailValues);
      itemDetailModalState.detailSaveInFlight = false;

      if (Array.isArray(res.statusLabels)) {
        normalizeStatusLabels(res.statusLabels);
        renderStatusLabelOptions(
          $("#taskItemDetailStatusSearchInput").val() || "",
        );
      }

      if (Array.isArray(res.parentOptions)) {
        itemDetailModalState.parentOptions = normalizeParentOptions(
          res.parentOptions,
        );
        renderDetailParentOptions(
          $("#taskItemDetailParentSearchInput").val() || "",
        );
      }

      if (detail && typeof detail === "object") {
        updateCardFromDetail(detail);
        itemDetailModalState.childWorkItems = normalizeChildWorkItems(
          detail.child_work_items,
        );
        renderChildWorkItemsSection();
        applyDetailFieldVisibility();
        renderDetailTimeTracking(
          detail,
          Number($("#taskItemDetailEstimateValueInput").val() || 0),
          String($("#taskItemDetailEstimateUnitInput").val() || "minutes"),
        );
      }

      loadItemHistory(itemId);

      if (
        itemDetailModalState.queuedDetailSave ||
        buildModalDetailSnapshot() !==
          itemDetailModalState.lastSavedDetailSnapshot
      ) {
        itemDetailModalState.queuedDetailSave = false;
        saveItemDetailsFromModal(false, {
          autosave: true,
          suppressNoChangeMessage: true,
          silentSuccess: true,
        });
        return;
      }

      if (!settings.silentSuccess) {
        setItemDetailAutosaveStatus("saved", "All changes saved");
      }

      if (closeAfterSave) {
        var modal = getItemDetailModalInstance();
        if (modal) {
          modal.hide();
        }
      }
    },
    function (res) {
      itemDetailModalState.detailSaveInFlight = false;
      if (isPermissionDeniedResponse(res)) {
        itemDetailModalState.queuedDetailSave = false;
        restoreItemDetailStateAfterDeniedSave();
        return;
      }
      setItemDetailAutosaveStatus("error", "Failed to save changes");
    },
  );
}

function loadItemDetail(itemId, onComplete) {
  var id = Number(itemId || 0);
  if (!id) {
    if (typeof onComplete === "function") {
      onComplete(false);
    }
    return;
  }

  postAction(
    {
      task_action: "get_item_detail",
      item_id: id,
    },
    function (res) {
      if (!res || !res.ok) {
        if (typeof onComplete === "function") {
          onComplete(false);
        }
        return;
      }
      applyItemDetailToModal(
        res.detail && typeof res.detail === "object" ? res.detail : {},
        Array.isArray(res.statusLabels) ? res.statusLabels : null,
        Array.isArray(res.parentOptions) ? res.parentOptions : null,
        Array.isArray(res.webLinks) ? res.webLinks : null,
        res.itemLinks && typeof res.itemLinks === "object" ? res.itemLinks : null,
      );
      if (typeof onComplete === "function") {
        onComplete(true);
      }
    },
    function () {
      if (typeof onComplete === "function") {
        onComplete(false);
      }
    },
  );
}

function defaultWorkType() {
  if (state.workTypes.length > 0) {
    return normalizeWorkTypeEntry(state.workTypes[0]);
  }
  return normalizeWorkTypeEntry({ id: 0, name: "Task", svg_icon: "" });
}

function buildTaskCardHtml(item) {
  var workTypeName = item.work_type_name || "Task";
  var workTypeIcon = normalizeWorkTypeIcon(
    item.work_type_svg_icon,
    workTypeName,
  );
  var workTypeId = Number(item.work_type_id || 0);
  var workItemKey = String(item.work_item_key || "").trim();
  if (!workItemKey) {
    workItemKey = buildWorkItemKey(item.id || 0);
  }
  var description = String(item.description || "").trim();
  var assigneeUserId = Number(item.assignee_user_id || 0);
  var assigneeName = String(item.assignee_name || "").trim() || "Unassigned";
  var reporterUserId = Number(item.reporter_user_id || 0);
  var reporterName = String(item.reporter_name || "").trim();
  var priorityValue = String(item.priority || "Medium").trim() || "Medium";
  var createDate = String(item.create_date || "").trim();
  var updateDate = String(item.update_date || "").trim();
  var startDate = String(item.start_date || "").trim();
  var dueDate = item.due_date || "";
  var dueDateValue = String(dueDate || "").trim();
  var estimateValue = Number(item.original_estimate_value || 0);
  var estimateUnit = String(item.original_estimate_unit || "minutes").trim();
  var amendementDate = String(item.amendement_date || "").trim();
  var amendementTimeMinutes = Number(item.amendement_time_minutes || 0);
  var secondAmendementDate = String(item.second_amendement_date || "").trim();
  var secondAmendementTimeMinutes = Number(
    item.second_amendement_time_minutes || 0,
  );
  var parentItemId = Number(item.parent_item_id || 0);
  var parentDisplay = String(item.parent_display || "").trim();
  var labels = Array.isArray(item.labels) ? item.labels : [];
  var labelIds = [];
  var labelsHtml = "";
  for (var i = 0; i < labels.length; i++) {
    var label = labels[i] || {};
    var labelId = Number(label.id || 0);
    var labelName = String(label.name || "").trim();
    if (!labelId || !labelName) {
      continue;
    }
    labelIds.push(labelId);
    labelsHtml +=
      '<span class="task-label-pill" style="' +
      labelPillStyle(label.color, "#DCE8FF") +
      '">' +
      escHtml(labelName) +
      "</span>";
  }

  var labelActionText = labelIds.length ? "Edit label" : "Add labels";
  var isEpic =
    String(workTypeName || "")
      .trim()
      .toLowerCase() === "epic";
  var statusLabelIds = Array.isArray(item.task_status_label_ids)
    ? item.task_status_label_ids
    : parseStatusLabelIdsFromRaw(item.task_status || "");
  statusLabelIds = normalizeStatusLabelIdList(statusLabelIds);
  var statusLabelActionText = statusLabelIds.length
    ? "Edit task status labels"
    : "Add task status labels";
  var canManageWorkType = canEdit && state.workTypes.length > 0;
  var canManageAssignee = canEdit && hasAnyProjectFieldPermission("assignee");
  var canOpenActions = canEdit || canDelete;

  var statusLabelMenuHtml = "";
  var parentMenuHtml = "";
  if (!isEpic) {
    statusLabelMenuHtml =
      '<li class="dropend task-item-submenu-wrap">' +
      '<a class="dropdown-item task-item-submenu-toggle task-item-status-label-submenu-toggle" href="#" data-action="submenu_task_status_labels">' +
      escHtml(statusLabelActionText) +
      ' <i class="fa-solid fa-chevron-right task-submenu-chevron"></i></a>' +
      '<ul class="dropdown-menu task-item-submenu-list task-item-status-label-submenu"><li class="task-item-status-label-submenu-content"></li></ul>' +
      "</li>";

    parentMenuHtml =
      '<li class="dropend task-item-submenu-wrap">' +
      '<a class="dropdown-item task-item-submenu-toggle task-item-parent-submenu-toggle" href="#" data-action="submenu_parent" data-has-parent="' +
      (parentItemId > 0 ? "1" : "0") +
      '">' +
      (parentItemId > 0 ? "Change parent" : "Link parent") +
      ' <i class="fa-solid fa-chevron-right task-submenu-chevron"></i></a>' +
      '<ul class="dropdown-menu task-item-submenu-list task-item-parent-submenu"><li class="task-item-parent-submenu-content"></li></ul>' +
      "</li>";
  }

  return (
    '<article class="task-item-card" data-item-id="' +
    Number(item.id || 0) +
    '" data-work-type-id="' +
    workTypeId +
    '" data-work-type-icon="' +
    escHtml(workTypeIcon) +
    '" data-assignee-name="' +
    escHtml(assigneeName) +
    '" data-reporter-user-id="' +
    reporterUserId +
    '" data-reporter-name="' +
    escHtml(reporterName) +
    '" data-priority="' +
    escHtml(priorityValue) +
    '" data-start-date="' +
    escHtml(startDate) +
    '" data-due-date="' +
    escHtml(String(dueDate || "").trim()) +
    '" data-create-date="' +
    escHtml(createDate) +
    '" data-update-date="' +
    escHtml(updateDate) +
    '" data-original-estimate-value="' +
    Number(estimateValue || 0) +
    '" data-original-estimate-unit="' +
    escHtml(estimateUnit || "minutes") +
    '" data-amendement-date="' +
    escHtml(amendementDate) +
    '" data-amendement-time-minutes="' +
    Number(amendementTimeMinutes || 0) +
    '" data-second-amendement-date="' +
    escHtml(secondAmendementDate) +
    '" data-second-amendement-time-minutes="' +
    Number(secondAmendementTimeMinutes || 0) +
    '" data-label-ids="' +
    escHtml(labelIds.join(",")) +
    '" data-assignee-user-id="' +
    assigneeUserId +
    '" data-item-description="' +
    escHtml(description) +
    '" data-work-type-name="' +
    escHtml(workTypeName) +
    '" data-work-type-remark="' +
    escHtml(String(item.work_type_remark || "")) +
    '" data-work-item-key="' +
    escHtml(workItemKey) +
    '" data-parent-item-id="' +
    parentItemId +
    '" data-parent-display="' +
    escHtml(parentDisplay) +
    '" data-status-column-id="' +
    Number(item.column_id || item.columnId || item.status_column_id || 0) +
    '" data-status-column-name="' +
    escHtml(String(item.column_name || item.status_name || "")) +
    '" data-task-status-label-ids="' +
    escHtml(statusLabelIds.join(",")) +
    '" draggable="' +
    (typeof isTouchBoardViewport === "function" && isTouchBoardViewport()
      ? "false"
      : "true") +
    '">' +
    '<div class="task-item-head">' +
    '<div class="task-item-title-actions">' +
    '<h6 class="task-item-title">' +
    '<span class="task-item-title-text">' +
    escHtml(item.title || "") +
    '</span>' +
    (canEdit
      ? '<button class="btn task-item-menu-btn task-item-edit-btn" type="button" title="Edit title" aria-label="Edit title"><i class="fa-solid fa-pen"></i></button>'
      : "") +
    "</h6>" +
    "</div>" +
    '<div class="dropdown task-item-menu-dropdown">' +
    (canOpenActions
      ? '<button class="btn task-item-menu-btn task-open-item-actions-btn" type="button" title="Task options" aria-label="Task options"><i class="fa-solid fa-ellipsis"></i></button>'
      : "") +
    "</div>" +
    "</div>" +
    (labelsHtml
      ? '<div class="task-item-label-row">' + labelsHtml + "</div>"
      : "") +
    '<div class="task-item-field-list"></div>' +
    '<div class="task-item-meta">' +
    '<div class="task-item-meta-left">' +
    '<div class="dropdown task-item-type-wrap">' +
    '<button class="btn task-item-type-btn task-work-type-toggle' +
    (canManageWorkType ? " dropdown-toggle" : "") +
    '" type="button"' +
    (canManageWorkType ? ' data-bs-toggle="dropdown" aria-expanded="false"' : ' disabled aria-disabled="true"') +
    ' data-work-type-id="' +
    workTypeId +
    '" data-work-type-name="' +
    escHtml(workTypeName) +
    '" data-work-type-remark="' +
    escHtml(String(item.work_type_remark || "")) +
    '" data-work-type-icon="' +
    escHtml(workTypeIcon) +
    '" title="' +
    escHtml(workTypeName) +
    '">' +
    workTypeIconHtml(workTypeIcon, workTypeName, "task-type-pill-icon") +
    "</button>" +
    (canManageWorkType
      ? '<ul class="dropdown-menu task-work-type-menu task-item-work-type-menu">' +
        workTypeMenuHtml() +
        "</ul>"
      : "") +
    "</div>" +
    '<span class="task-item-key ' +
    (workItemKey ? "" : "d-none") +
    '">' +
    escHtml(workItemKey) +
    "</span>" +
    "</div>" +
    '<div class="task-item-meta-right">' +
    '<span class="task-item-priority-wrap">' +
    priorityIconGlyphHtml(priorityValue) +
    "</span>" +
    '<div class="dropdown task-item-assignee-wrap">' +
    '<button class="btn task-assignee-pill task-item-assignee-btn' +
    (canManageAssignee ? " dropdown-toggle " : " ") +
    (assigneeUserId > 0 ? "" : "task-assignee-pill-unassigned") +
    '" type="button"' +
    (canManageAssignee ? ' data-bs-toggle="dropdown"' : ' disabled aria-disabled="true"') +
    ' data-user-id="' +
    assigneeUserId +
    '" title="' +
    escHtml(assigneeName) +
    '">' +
    assigneeButtonInner(assigneeUserId, assigneeName) +
    "</button>" +
    (canManageAssignee
      ? '<ul class="dropdown-menu task-assignee-menu task-assignee-menu-scroll task-item-assignee-menu">' +
        assigneeMenuHtml() +
        "</ul>"
      : "") +
    "</div>" +
    "</div>" +
    "</div>" +
    "</article>"
  );
}

function buildColumnHtml(column) {
  var def = defaultWorkType();
  var columnColor = normalizeHexColorValue(column.color || "", "#DFE1E6");
  var columnId = Number(column.id || 0);
  var totalItemCount = Number(state.boardItemCounts[String(columnId)] || 0);
  var canCreateInColumn =
    canAdd && state.workTypes.length > 0 && canTargetStatusColumn(column.id);

  return (
    '<section class="task-column" data-column-id="' +
    columnId +
    '" data-column-color="' +
    escHtml(columnColor) +
    '" data-total-item-count="' +
    totalItemCount +
    '" data-loaded-item-count="0"' +
    ' style="--task-column-color:' +
    escHtml(columnColor) +
    '">' +
    '<div class="task-column-header">' +
    '<div class="task-column-title-wrap">' +
    '<h5 class="task-column-title">' +
    escHtml(column.name || "New Status") +
    '</h5><span class="task-column-count">0</span>' +
    "</div>" +
    '<div class="task-column-header-actions">' +
    '<button class="btn task-column-collapse-btn" type="button" title="Collapse status"><i class="fa-solid fa-left-right"></i></button>' +
    (state.isProjectOwner
      ? '<div class="dropdown">' +
        '<button class="btn task-column-menu-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-ellipsis"></i></button>' +
        '<ul class="dropdown-menu dropdown-menu-end task-column-menu-list">' +
        '<li><a class="dropdown-item task-column-action" href="#" data-action="rename">Rename status</a></li>' +
        '<li><a class="dropdown-item task-column-action" href="#" data-action="move_left">Move status left</a></li>' +
        '<li><a class="dropdown-item task-column-action" href="#" data-action="move_right">Move status right</a></li>' +
        '<li><hr class="dropdown-divider"></li>' +
        '<li><a class="dropdown-item task-column-action text-danger" href="#" data-action="delete">Delete status</a></li>' +
        "</ul>" +
        "</div>"
      : "") +
    "</div>" +
    "</div>" +
    '<div class="task-item-list"></div>' +
    (canCreateInColumn
      ? '<button class="btn task-open-composer-btn" type="button"><i class="fa-solid fa-plus"></i> Create</button>'
      : "") +
    '<div class="task-composer d-none" data-column-id="' +
    Number(column.id || 0) +
    '">' +
    '<textarea class="form-control task-title-input" rows="2" maxlength="255" placeholder="What needs to be done?"></textarea>' +
    '<div class="task-composer-controls">' +
    '<div class="task-composer-controls-left">' +
    '<div class="dropdown">' +
    '<button class="btn task-icon-btn task-icon-btn-compact task-work-type-toggle" type="button" data-bs-toggle="dropdown" data-work-type-id="' +
    Number(def.id || 0) +
    '" data-work-type-name="' +
    escHtml(def.name || "Task") +
    '" data-work-type-remark="' +
    escHtml(def.remark || "") +
    '" data-work-type-icon="' +
    escHtml(def.svg_icon || normalizeWorkTypeIcon("", def.name || "Task")) +
    '" title="' +
    escHtml(def.name || "Task") +
    '">' +
    workTypeIconHtml(def.svg_icon, def.name, "task-work-type-toggle-icon") +
    "</button>" +
    '<ul class="dropdown-menu task-work-type-menu">' +
    workTypeMenuHtml() +
    "</ul>" +
    "</div>" +
    '<button class="btn task-icon-btn task-due-date-btn" type="button" title="Select due date"><i class="fa-regular fa-calendar"></i></button>' +
    '<input class="task-due-date-input" type="date">' +
    '<div class="dropdown">' +
    '<button class="btn task-icon-btn task-icon-btn-compact dropdown-toggle task-assignee-toggle task-assignee-icon-toggle" type="button" data-bs-toggle="dropdown" data-user-id="0" title="Unassigned"><i class="fa-regular fa-user"></i></button>' +
    '<ul class="dropdown-menu task-assignee-menu task-assignee-menu-scroll">' +
    assigneeMenuHtml() +
    "</ul>" +
    "</div>" +
    "</div>" +
    '<div class="task-composer-controls-right">' +
    '<button class="btn task-create-item-btn" type="button" disabled title="Create work item"' +
    (canCreateInColumn ? "" : ' style="display:none;"') +
    '><span class="mdi mdi-keyboard-return"></span></button>' +
    "</div>" +
    "</div>" +
    "</div>" +
    "</section>"
  );
}

function buildBoardGroupedColumnHtml(groupTitle, groupType, groupKey) {
  return (
    '<section class="task-column task-column-grouped-view" data-column-id="0" data-group-type="' +
    escHtml(groupType || "") +
    '" data-group-key="' +
    escHtml(groupKey || "") +
    '">' +
    '<div class="task-column-header">' +
    '<div class="task-column-title-wrap">' +
    '<h5 class="task-column-title">' +
    escHtml(groupTitle || "Untitled") +
    '</h5><span class="task-column-count">0</span>' +
    "</div>" +
    "</div>" +
    '<div class="task-item-list"></div>' +
    "</section>"
  );
}

function buildCreateStatusSlotHtml() {
  return (
    '<section id="taskCreateStatusSlot" class="task-create-status-slot task-create-status-slot-board">' +
    '<button id="taskOpenCreateStatusBtn" class="btn task-create-status-icon-btn" type="button" ' +
    (canAdd ? "" : "disabled") +
    ' title="Add status"><i class="fa-solid fa-plus"></i></button>' +
    "</section>"
  );
}

function syncBoardGroupControlUi() {
  var mode = getBoardGroupBy();
  var label = boardGroupLabel(mode);

  $("#taskBoardGroupLabel").text("Group: " + label);
  $(".task-board-group-option").each(function () {
    var $option = $(this);
    var optionMode = normalizeBoardGroupBy($option.attr("data-group-by") || "");
    var isActive = optionMode === mode;

    $option.toggleClass("active", isActive);
    $option.find(".task-board-group-check").toggleClass("d-none", !isActive);
  });
}

function buildBoardGroupsByStatus(cards) {
  var statusColumns = getBoardStatusColumns();
  if (!statusColumns.length) {
    captureBoardStatusColumnsFromDom();
    statusColumns = getBoardStatusColumns();
  }

  var groups = [];
  var lookup = {};

  for (var i = 0; i < statusColumns.length; i++) {
    var statusCol = statusColumns[i] || {};
    var statusId = Number(statusCol.id || 0);
    if (statusId <= 0) {
      continue;
    }

    var base = {
      key: "status_" + String(statusId),
      title: String(statusCol.name || "").trim() || "Untitled",
      statusId: statusId,
      color: normalizeHexColorValue(statusCol.color || "", "#DFE1E6"),
      cards: [],
    };
    groups.push(base);
    lookup[String(statusId)] = base;
  }

  for (var c = 0; c < cards.length; c++) {
    var $card = cards[c];
    var currentStatusId = getCardStatusColumnId($card);
    if (currentStatusId <= 0 && statusColumns.length) {
      currentStatusId = Number((statusColumns[0] && statusColumns[0].id) || 0);
      if (currentStatusId > 0) {
        var fallbackMeta = getBoardStatusColumnMeta(currentStatusId);
        setCardStatusColumnMeta(
          $card,
          currentStatusId,
          getBoardStatusColumnName(currentStatusId),
          fallbackMeta ? fallbackMeta.color : "#DFE1E6",
        );
      }
    }
    if (currentStatusId <= 0) {
      continue;
    }

    var key = String(currentStatusId);

    if (!lookup[key]) {
      var statusName =
        String($card.attr("data-status-column-name") || "").trim() ||
        getBoardStatusColumnName(currentStatusId) ||
        (currentStatusId > 0
          ? "Status " + String(currentStatusId)
          : "Untitled");

      lookup[key] = {
        key: "status_" + key,
        title: statusName,
        statusId: currentStatusId,
        color: (getBoardStatusColumnMeta(currentStatusId) || {}).color || "#DFE1E6",
        cards: [],
      };
      groups.push(lookup[key]);
    }

    lookup[key].cards.push($card);
  }

  return groups;
}

function buildBoardGroupsByAssignee(cards) {
  var groups = [];
  var lookup = {};

  for (var i = 0; i < cards.length; i++) {
    var $card = cards[i];
    var assigneeId = Number($card.attr("data-assignee-user-id") || 0);
    var assigneeName =
      String($card.attr("data-assignee-name") || "").trim() || "Unassigned";
    var key = String(assigneeId);

    if (!lookup[key]) {
      lookup[key] = {
        key: "assignee_" + key,
        title: assigneeName,
        assigneeId: assigneeId,
        cards: [],
      };
      groups.push(lookup[key]);
    }

    lookup[key].cards.push($card);
  }

  groups.sort(function (a, b) {
    if (a.assigneeId === 0 && b.assigneeId !== 0) {
      return -1;
    }
    if (b.assigneeId === 0 && a.assigneeId !== 0) {
      return 1;
    }

    return String(a.title || "").localeCompare(String(b.title || ""));
  });

  return groups;
}

function buildBoardGroupsByPriority(cards) {
  var groups = [];
  var lookup = {};

  for (var i = 0; i < taskPriorityValues.length; i++) {
    var priority = String(taskPriorityValues[i] || "").trim();
    if (!priority) {
      continue;
    }

    var base = {
      key: "priority_" + priority,
      title: priority,
      priority: priority,
      cards: [],
    };
    groups.push(base);
    lookup[priority] = base;
  }

  for (var c = 0; c < cards.length; c++) {
    var $card = cards[c];
    var priorityValue = String($card.attr("data-priority") || "Medium").trim();
    if (!lookup[priorityValue]) {
      lookup[priorityValue] = {
        key: "priority_" + priorityValue,
        title: priorityValue || "Medium",
        priority: priorityValue || "Medium",
        cards: [],
      };
      groups.push(lookup[priorityValue]);
    }

    lookup[priorityValue].cards.push($card);
  }

  groups.sort(function (a, b) {
    var ai = taskPriorityValues.indexOf(String(a.priority || "").trim());
    var bi = taskPriorityValues.indexOf(String(b.priority || "").trim());
    if (ai === -1) {
      ai = taskPriorityValues.length;
    }
    if (bi === -1) {
      bi = taskPriorityValues.length;
    }
    return ai - bi;
  });

  return groups;
}

function renderBoardGroupingLayout() {
  var mode = getBoardGroupBy();
  var $grid = $("#taskBoardGrid");
  if (!$grid.length) {
    return;
  }

  var cards = [];
  $app.find(".task-item-card").each(function () {
    var $card = $(this);
    syncCardStatusMeta($card, true);
    cards.push($card);
  });

  for (var i = 0; i < cards.length; i++) {
    cards[i].detach();
  }

  var groups = [];
  if (mode === "assignee") {
    groups = buildBoardGroupsByAssignee(cards);
  } else if (mode === "priority") {
    groups = buildBoardGroupsByPriority(cards);
  } else {
    groups = buildBoardGroupsByStatus(cards);
  }

  $grid.empty();

  for (var g = 0; g < groups.length; g++) {
    var group = groups[g] || {};
    var $column;

    if (mode === "status") {
      $column = $(
        buildColumnHtml({
          id: Number(group.statusId || 0),
          name: String(group.title || "Untitled"),
          color: String(group.color || "#DFE1E6"),
        }),
      );
    } else {
      $column = $(
        buildBoardGroupedColumnHtml(group.title, mode, String(group.key || "")),
      );
    }

    $grid.append($column);

    var $list = $column.find(".task-item-list").first();
    var groupCards = Array.isArray(group.cards) ? group.cards : [];
    for (var c = 0; c < groupCards.length; c++) {
      var $card = groupCards[c];
      $card.attr(
        "draggable",
        mode === "status" &&
          !(typeof isTouchBoardViewport === "function" && isTouchBoardViewport())
          ? "true"
          : "false",
      );
      $list.append($card);
    }
  }

  $grid.append($(buildCreateStatusSlotHtml()));

  syncBoardGroupControlUi();
  if (typeof bindTaskItemMenuRepositionListeners === "function") {
    bindTaskItemMenuRepositionListeners();
  }
  applyBoardViewSettingsToAllCards();
  updateAllColumnCounts();
  refreshEmptyBoardState();
  applyBoardFilters();
  if (typeof dropdownMenuDispFix === "function") {
    dropdownMenuDispFix();
  }
}

function refreshBoardGroupingLayout() {
  if (isBoardGroupedByStatus()) {
    return;
  }
  renderBoardGroupingLayout();
}

function refreshWorkTypeMenus() {
  normalizeAllWorkTypes();
  var menu = workTypeMenuHtml();
  $app.find(".task-work-type-menu").html(menu);
}

function getWorkTypeModalInstance() {
  var modalEl = document.getElementById("taskWorkTypeModal");
  if (!modalEl || typeof bootstrap === "undefined" || !bootstrap.Modal) {
    return null;
  }
  return bootstrap.Modal.getOrCreateInstance(modalEl);
}

function updateWorkTypeModalSaveState() {
  var name = String($("#taskWorkTypeNameInput").val() || "").trim();
  $("#taskWorkTypeSaveBtn").prop("disabled", name.length === 0);
}

function renderWorkTypeIconPicker(selectedIcon) {
  var selected = normalizeWorkTypeIcon(selectedIcon, "Task");
  var html = "";
  var icons = Array.isArray(state.workTypeIcons) ? state.workTypeIcons : [];

  for (var i = 0; i < icons.length; i++) {
    var iconPath = String(icons[i] || "");
    if (!iconPath) {
      continue;
    }
    html +=
      '<button type="button" class="task-work-type-icon-option' +
      (iconPath === selected ? " active" : "") +
      '" data-icon-path="' +
      escHtml(iconPath) +
      '" title="' +
      escHtml(iconPath.split("/").pop()) +
      '"><img src="' +
      escHtml(iconPath) +
      '" alt=""></button>';
  }

  $("#taskWorkTypeIconPicker").html(html);
  workTypeModalState.iconPath = selected;
}

function setWorkTypeIconPickerEnabled(enabled) {
  var canUse = !!enabled;
  $("#taskWorkTypeIconDropdownBtn").prop("disabled", !canUse);
  $("#taskWorkTypeIconPicker").toggleClass(
    "task-work-type-icon-picker-disabled",
    !canUse,
  );
}

function getWorkTypeByName(name) {
  var target = String(name || "")
    .trim()
    .toLowerCase();
  if (!target) {
    return null;
  }

  for (var i = 0; i < state.workTypes.length; i++) {
    var item = normalizeWorkTypeEntry(state.workTypes[i]);
    if (String(item.name || "").toLowerCase() === target) {
      return item;
    }
  }

  return null;
}

function openWorkTypeModal(mode, $context) {
  var modal = getWorkTypeModalInstance();
  if (!modal) {
    notify("Work type modal is unavailable.");
    return;
  }

  var selected = defaultWorkType();
  var $scope = $context && $context.length ? $context : $();
  var $toggle = $scope.find(".task-work-type-toggle").first();

  if (!$toggle.length && $scope.is(".task-work-type-toggle")) {
    $toggle = $scope;
  }

  if (!$toggle.length && $scope.is(".task-item-card")) {
    $toggle = $scope.find(".task-item-type-btn").first();
  }

  if (!$toggle.length) {
    selected = defaultWorkType();
  }

  var selectedId = Number($toggle.attr("data-work-type-id") || 0);
  var fromList = findWorkTypeById(selectedId);
  if (fromList) {
    selected = fromList;
  } else {
    selected = normalizeWorkTypeEntry({
      id: selectedId,
      name: $toggle.attr("data-work-type-name") || "Task",
      remark: $toggle.attr("data-work-type-remark") || "",
      svg_icon: $toggle.attr("data-work-type-icon") || "",
    });
  }

  var isEdit = mode === "edit";
  if (isEdit && Number(selected.id || 0) <= 0) {
    notify("Please select a work type first.");
    return;
  }

  workTypeModalState.mode = isEdit ? "edit" : "add";
  workTypeModalState.workTypeId = isEdit ? Number(selected.id || 0) : 0;
  workTypeModalState.iconPath = normalizeWorkTypeIcon(
    selected.svg_icon,
    selected.name,
  );
  workTypeModalState.initialIconPath = workTypeModalState.iconPath;
  workTypeModalState.composerEl = $scope.get(0) || null;

  $("#taskWorkTypeModalTitle").text(
    isEdit ? "Edit work type" : "Add work type",
  );
  $("#taskWorkTypeSaveBtn").text(isEdit ? "Save" : "Add");
  $("#taskWorkTypeNameInput").val(isEdit ? selected.name : "");
  $("#taskWorkTypeDescriptionInput").val(isEdit ? selected.remark : "");
  $("#taskWorkTypeChangeIcon").prop("checked", true);
  renderWorkTypeIconPicker(workTypeModalState.iconPath);
  setWorkTypeIconPickerEnabled(true);
  updateWorkTypeModalSaveState();
  $("#taskWorkTypeIconPicker").removeClass("show");

  modal.show();
  setTimeout(function () {
    $("#taskWorkTypeNameInput").trigger("focus");
  }, 100);
}

function updateColumnCount($column) {
  var loadedCount = $column.find(".task-item-card").length;
  var totalCount = Number($column.attr("data-total-item-count"));
  var count = Number.isFinite(totalCount) && totalCount >= loadedCount
    ? totalCount
    : loadedCount;
  $column.attr("data-loaded-item-count", loadedCount);
  $column.find(".task-column-count").text(count);
}

function getBoardColumnTotalCount(columnId) {
  var id = Number(columnId || 0);
  if (id <= 0) {
    return 0;
  }

  var configured = Number(state.boardItemCounts[String(id)] || 0);
  if (configured > 0) {
    return configured;
  }

  return Number(
    $app
      .find('.task-column[data-column-id="' + id + '"]')
      .first()
      .attr("data-total-item-count") || 0,
  );
}

function syncBoardLoadMoreControls() {
  if (!isBoardGroupedByStatus()) {
    $app.find(".task-board-load-more-btn").remove();
    return;
  }

  $app.find('.task-column[data-column-id]').each(function () {
    var $column = $(this);
    var columnId = Number($column.attr("data-column-id") || 0);
    if (columnId <= 0) {
      return;
    }

    var $list = $column.find(".task-item-list").first();
    var loadedCount = $list.find(".task-item-card").length;
    var totalCount = getBoardColumnTotalCount(columnId);
    $column.attr("data-total-item-count", totalCount);
    $column.attr("data-loaded-item-count", loadedCount);

    var $button = $column.find(".task-board-load-more-btn").first();
    if (loadedCount >= totalCount || totalCount <= 0) {
      $button.remove();
      return;
    }

    if (!$button.length) {
      $button = $(
        '<button class="btn task-board-load-more-btn" type="button"></button>',
      );
      var $composer = $column.find(".task-open-composer-btn").first();
      if ($composer.length) {
        $composer.before($button);
      } else {
        $column.append($button);
      }
    }

    $button
      .attr("data-column-id", columnId)
      .attr("data-offset", loadedCount)
      .attr("data-limit", state.boardPageSize)
      .prop("disabled", false)
      .text("Load more (" + String(totalCount - loadedCount) + " remaining)");
  });
}

function updateColumnInnerScroll($column) {
  if (!$column || !$column.length) {
    return;
  }

  var $list = $column.find(".task-item-list").first();
  if (!$list.length) {
    return;
  }

  var $visibleCards = $list.find(".task-item-card:visible");
  var viewportHeight =
    window.innerHeight || document.documentElement.clientHeight || 900;
  var fallbackMaxHeight = Math.max(
    260,
    Math.min(560, Math.floor(viewportHeight * 0.56)),
  );

  if ($visibleCards.length <= 2) {
    $list.css({
      maxHeight: "none",
      overflowY: "visible",
    });
    return;
  }

  var maxHeight = 0;
  for (var i = 0; i < 2; i++) {
    maxHeight += Math.ceil(
      Number($($visibleCards.get(i)).outerHeight(true) || 0),
    );
  }

  if (maxHeight < 220) {
    maxHeight = 220;
  }
  if (maxHeight > fallbackMaxHeight) {
    maxHeight = fallbackMaxHeight;
  }

  $list.css({
    maxHeight: String(maxHeight + 8) + "px",
    overflowY: "auto",
  });
}

function updateAllColumnCounts() {
  $app.find(".task-column").each(function () {
    updateColumnCount($(this));
    updateColumnInnerScroll($(this));
  });
  syncBoardLoadMoreControls();
}

function scrollColumnItemListToBottom($column) {
  if (!$column || !$column.length) {
    return;
  }

  updateColumnInnerScroll($column);

  var listEl = $column.find(".task-item-list").get(0);
  if (!listEl) {
    return;
  }

  listEl.scrollTop = listEl.scrollHeight;
}

function refreshCardItemKeys() {
  $app.find(".task-item-card").each(function () {
    var $card = $(this);
    var itemId = Number($card.data("itemId") || 0);
    var key = buildWorkItemKey(itemId);
    $card.attr("data-work-item-key", key);
    var $keyEl = $card.find(".task-item-key");
    if (!$keyEl.length) {
      $keyEl = $('<span class="task-item-key d-none"></span>');
      $card.find(".task-item-meta-left").append($keyEl);
    }
    $keyEl.text(key).toggleClass("d-none", !key);
    applyBoardViewSettingsToCard($card);
  });
}

function hasAnyStatusColumn() {
  return $app.find(".task-column").length > 0;
}

function refreshEmptyBoardState() {
  $("#taskBoardEmpty").toggleClass("d-none", hasAnyStatusColumn());
}

function updateBoardNoResultState(show) {
  $("#taskBoardNoResult").toggleClass("d-none", !show);
}

function applyBoardFilters() {
  syncBoardFilterActivePart();
  var query = String(boardSearchQuery || "")
    .trim()
    .toLowerCase();
  var hasFilter = activeBoardFilterCount() > 0 || query !== "";
  var totalVisibleCount = 0;

  $app.find(".task-column").each(function () {
    var $column = $(this);
    var visibleCount = 0;

    $column.find(".task-item-card").each(function () {
      var $card = $(this);
      var title = String($card.find(".task-item-title").text() || "")
        .trim()
        .toLowerCase();
      var key = String($card.attr("data-work-item-key") || "")
        .trim()
        .toLowerCase();
      var description = String($card.attr("data-item-description") || "")
        .trim()
        .toLowerCase();
      var matchQuery =
        !query ||
        title.indexOf(query) !== -1 ||
        key.indexOf(query) !== -1 ||
        description.indexOf(query) !== -1;

      var show = true;

      if (boardFilterState.activePart === "A") {
        if (boardFilterState.partA.assignedToMe) {
          var assigneeId = Number($card.attr("data-assignee-user-id") || 0);
          show = show && currentUserId > 0 && assigneeId === currentUserId;
        }

        if (show && boardFilterState.partA.dueThisWeek) {
          show = isDateInCurrentWeek($card.attr("data-due-date"));
        }
      } else if (boardFilterState.activePart === "B") {
        show = cardMatchesPartBFilter($card);
      }

      show = show && matchQuery;

      $card.toggle(show);
      if (show) {
        visibleCount++;
        totalVisibleCount++;
      }
    });

    $column
      .find(".task-column-count")
      .text(hasFilter ? visibleCount : $column.find(".task-item-card").length);

    updateColumnInnerScroll($column);
  });

  updateBoardNoResultState(hasFilter && totalVisibleCount <= 0);
}

function isMobileCreateStatusView() {
  return window.matchMedia("(max-width: 768px)").matches;
}

function getCreateStatusModalElements() {
  return {
    modalEl: document.getElementById("taskCreateStatusMobileModal"),
    $title: $("#taskCreateStatusMobileModal .modal-title"),
    $input: $("#taskStatusNameMobile"),
    $submit: $("#taskCreateStatusSubmitMobile"),
  };
}

function getCreateStatusModalInstance() {
  var elems = getCreateStatusModalElements();
  if (!elems.modalEl || typeof bootstrap === "undefined" || !bootstrap.Modal) {
    return null;
  }

  return bootstrap.Modal.getOrCreateInstance(elems.modalEl);
}

function openStatusModal(config) {
  var opts = config || {};
  var mode = String(opts.mode || "create").toLowerCase();
  var elems = getCreateStatusModalElements();
  if (!elems.modalEl) {
    return;
  }

  statusModalState.mode = mode === "rename" ? "rename" : "create";
  statusModalState.columnId = Number(opts.columnId || 0);
  statusModalState.initialName = String(opts.currentName || "").trim();

  if (statusModalState.mode === "rename") {
    elems.$title.text("Rename status");
    elems.$submit.text("Save");
    elems.$input.val(statusModalState.initialName);
  } else {
    elems.$title.text("Add status");
    elems.$submit.text("Add");
    elems.$input.val("");
  }

  elems.$submit.prop(
    "disabled",
    statusModalState.mode === "rename" ? !canEdit : !canAdd,
  );

  var modal = getCreateStatusModalInstance();
  if (modal) {
    modal.show();
    setTimeout(function () {
      elems.$input.trigger("focus").trigger("select");
    }, 120);
  }
}

function resetCreateStatusInline() {
  $("#taskStatusName").val("");
  $("#taskCreateStatusInlineForm").addClass("d-none");
  $("#taskOpenCreateStatusBtn").removeClass("d-none");
  $("#taskCreateStatusSlot").removeClass("task-create-status-slot-expanded");
}

function createStatus(columnName) {
  var statusName = String(columnName || "").trim();
  if (!statusName) {
    notify("Status name is required.");
    return;
  }

  postAction(
    {
      task_action: "create_status",
      column_name: statusName,
    },
    function (res) {
      var column = res.column || {};
      upsertBoardStatusColumn(
        Number(column.id || 0),
        String(column.name || ""),
        String(column.color || "#DFE1E6"),
      );
      renderBoardGroupingLayout();
      refreshEmptyBoardState();
      showTaskSuccess("Status created successfully.");

      $("#taskStatusNameMobile").val("");
      resetCreateStatusInline();
    },
  );
}

function renameStatus(columnId, newName, $column) {
  var id = Number(columnId || 0);
  var nextName = String(newName || "").trim();
  if (!id || !nextName) {
    return;
  }

  postAction(
    {
      task_action: "rename_status",
      column_id: id,
      column_name: nextName,
    },
    function (res) {
      var resolvedName = String(
        (res && res.column_name) || nextName || "",
      ).trim();
      var statusMeta = getBoardStatusColumnMeta(id);
      var $targetColumn =
        $column && $column.length
          ? $column
          : $app.find('.task-column[data-column-id="' + id + '"]');
      var currentColor = String(
        ($targetColumn.length
          ? $targetColumn.attr("data-column-color")
          : statusMeta && statusMeta.color) || "#DFE1E6",
      );

      upsertBoardStatusColumn(id, resolvedName, currentColor);

      if ($targetColumn.length) {
        $targetColumn.find(".task-column-title").text(resolvedName);
        $targetColumn.find(".task-item-card").each(function () {
          setCardStatusColumnMeta(
            $(this),
            id,
            resolvedName,
            String($targetColumn.attr("data-column-color") || "#DFE1E6"),
          );
        });
      }

      if (!isBoardGroupedByStatus()) {
        renderBoardGroupingLayout();
      }

      showTaskSuccess("Status renamed successfully.");
    },
  );
}

function enableCreateButton($composer) {
  var title = String($composer.find(".task-title-input").val() || "").trim();
  $composer
    .find(".task-create-item-btn")
    .prop("disabled", title.length === 0 || !canAdd);
}

$(document).on("click", "#taskOpenCreateStatusBtn", function () {
  if (!canAdd) {
    notify("You do not have permission to create status.");
    return;
  }

  openStatusModal({ mode: "create" });
});

$("#taskCreateStatusInlineForm").on("submit", function (e) {
  e.preventDefault();
  createStatus($("#taskStatusName").val());
});

$("#taskCreateStatusCancel").on("click", function () {
  resetCreateStatusInline();
});

$("#taskCreateStatusSubmitMobile").on("click", function () {
  var inputName = $("#taskStatusNameMobile").val();
  if (statusModalState.mode === "rename") {
    if (!canEdit) {
      notify("You do not have permission to manage statuses.");
      return;
    }

    var newName = String(inputName || "").trim();
    if (!newName) {
      notify("Status name is required.");
      return;
    }

    if (newName === statusModalState.initialName) {
      showNoChangeMessage();
      return;
    }

    renameStatus(statusModalState.columnId, newName);
  } else {
    createStatus(inputName);
  }

  var modal = getCreateStatusModalInstance();
  if (modal) {
    modal.hide();
  }
});

$("#taskCreateStatusMobileModal").on("hidden.bs.modal", function () {
  statusModalState.mode = "create";
  statusModalState.columnId = 0;
  statusModalState.initialName = "";

  var elems = getCreateStatusModalElements();
  elems.$title.text("Add status");
  elems.$submit.text("Add");
  elems.$submit.prop("disabled", !canAdd);
  elems.$input.val("");
});

$app.on("click", ".task-column-collapse-btn", function () {
  var $column = $(this).closest(".task-column");
  $column.toggleClass("task-column-collapsed");
  var isCollapsed = $column.hasClass("task-column-collapsed");
  $(this).attr("title", isCollapsed ? "Expand status" : "Collapse status");
});

$app.on(
  "show.bs.dropdown",
  ".task-column-header-actions .dropdown",
  function () {
    var $dropdown = $(this);
    var $column = $dropdown.closest(".task-column");
    var $columns = $app.find(".task-column");
    var index = $columns.index($column);
    var lastIndex = $columns.length - 1;

    var canMoveLeft = index > 0;
    var canMoveRight = index >= 0 && index < lastIndex;

    $dropdown
      .find('.task-column-action[data-action="move_left"]')
      .closest("li")
      .toggle(canMoveLeft);
    $dropdown
      .find('.task-column-action[data-action="move_right"]')
      .closest("li")
      .toggle(canMoveRight);
  },
);

$app.on("click", ".task-column-action", function (e) {
  e.preventDefault();

  if (!canEdit) {
    notify("You do not have permission to manage statuses.");
    return;
  }

  var action = String($(this).data("action") || "");
  var $column = $(this).closest(".task-column");
  var columnId = Number($column.data("columnId") || 0);
  var currentName = String(
    $column.find(".task-column-title").text() || "",
  ).trim();

  if (!columnId) {
    return;
  }

  if (action === "rename") {
    openStatusModal({
      mode: "rename",
      columnId: columnId,
      currentName: currentName,
    });
    return;
  }

  if (action === "move_left" || action === "move_right") {
    postAction(
      {
        task_action: "move_status",
        column_id: columnId,
        direction: action === "move_left" ? "left" : "right",
      },
      function () {
        moveBoardStatusColumn(
          columnId,
          action === "move_left" ? "left" : "right",
        );
        if (action === "move_left") {
          var $prev = $column.prevAll(".task-column").first();
          if ($prev.length) {
            $column.insertBefore($prev);
          }
        } else {
          var $next = $column.nextAll(".task-column").first();
          if ($next.length) {
            $column.insertAfter($next);
          }
        }

        if (!isBoardGroupedByStatus()) {
          renderBoardGroupingLayout();
        }
      },
    );
    return;
  }

  if (action === "delete") {
    if (!window.confirm("Delete this status and all items in it?")) {
      return;
    }

    postAction(
      {
        task_action: "delete_status",
        column_id: columnId,
      },
      function () {
        removeBoardStatusColumnById(columnId);
        $app.find(".task-item-card").each(function () {
          if (getCardStatusColumnId($(this)) === columnId) {
            $(this).remove();
          }
        });

        $column.remove();
        if (!isBoardGroupedByStatus()) {
          renderBoardGroupingLayout();
        }
        refreshEmptyBoardState();
        showTaskSuccess("Status deleted successfully.");
      },
    );
  }
});
