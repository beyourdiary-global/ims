(function () {
  "use strict";

  if (typeof jQuery === "undefined") {
    return;
  }

  function escHtml(text) {
    return String(text || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
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

  jQuery(function ($) {
    var cfg = window.taskBoardConfig || {};
    var ajaxUrl = cfg.ajaxUrl || "";
    var canAdd = !!cfg.canAdd;
    var canEdit = !!cfg.canEdit;
    var state = {
      siteUrl: String(cfg.siteUrl || "").replace(/\/+$/, ""),
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
      statusLabels: Array.isArray(cfg.statusLabels)
        ? cfg.statusLabels.slice()
        : [],
      filters: {
        query: "",
        assignee: "all",
      },
    };

    var dragState = {
      $item: null,
      $sourceList: null,
      $sourceNext: null,
    };

    var $layout = $("#taskModuleLayout");
    var $sidebarToggle = $("#taskSidebarToggle");
    var $sidebarBackdrop = $("#taskSidebarBackdrop");
    var $taskTopMenuTrigger = $(".task-top-menu-trigger");
    var $createStatusSlot = $("#taskCreateStatusSlot");
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
      $("body").toggleClass(
        "task-local-sidebar-open",
        !!open && canShiftTopMenu,
      );

      try {
        window.localStorage.setItem(sidebarStorageKey, open ? "1" : "0");
      } catch (e) {}
    }

    if ($layout.length) {
      var hasGlobalTaskSidebar = $("#taskGlobalSidebar").length > 0;
      var shouldOpen = true;
      if (hasGlobalTaskSidebar) {
        shouldOpen = false;
      }
      try {
        shouldOpen = window.localStorage.getItem(sidebarStorageKey) !== "0";
      } catch (e) {}

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
      return;
    }

    var labelsPanelState = {
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
      attachments: [],
      attachmentSort: {
        field: "date",
        direction: "desc",
      },
      attachmentsCollapsed: false,
      attachmentView: "list",
      showAttachmentPanelWhenEmpty: false,
      pendingAttachmentPicker: false,
      selectedPriority: "Medium",
      selectedStatusLabelIds: [],
      selectedLabelIds: [],
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
      childWorkItems: {
        items: [],
        total: 0,
        done: 0,
        progress_percent: 0,
      },
      childWorkItemsCollapsed: false,
    };

    function normalizeProjectKey(projectKey) {
      return String(projectKey || "")
        .trim()
        .toUpperCase()
        .replace(/\s+/g, "")
        .replace(/[^A-Z0-9\-]/g, "")
        .slice(0, 20);
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
      state.workTypes = (
        Array.isArray(state.workTypes) ? state.workTypes : []
      ).map(function (item) {
        return normalizeWorkTypeEntry(item);
      });
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
          workTypeIconHtml(
            type.svg_icon,
            type.name,
            "task-work-type-toggle-icon",
          ),
        );
    }

    normalizeAllWorkTypes();

    function notify(message) {
      window.alert(message || "Operation completed.");
    }

    function postAction(payload, onDone, onFail) {
      if (!ajaxUrl) {
        notify("Missing ajax endpoint.");
        if (typeof onFail === "function") {
          onFail();
        }
        return;
      }

      $.ajax({
        url: ajaxUrl,
        method: "POST",
        dataType: "json",
        data: payload,
        timeout: 30000,
      })
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

      html += '<li><hr class="dropdown-divider"></li>';
      html +=
        '<li><a class="dropdown-item task-work-type-action" href="#" data-action="add">Add work type</a></li>';
      html +=
        '<li><a class="dropdown-item task-work-type-action" href="#" data-action="edit">Edit work type</a></li>';

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
        html += '<span class="task-label-pill">' + escHtml(name) + "</span>";
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
        return;
      }

      if (!$row.length) {
        $row = $('<div class="task-item-label-row"></div>');
        $card.find(".task-item-meta").before($row);
      }

      $row.html(html);
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
          '<span class="task-inline-label-option-name">' +
          escHtml(labelName) +
          "</span>" +
          '<button type="button" class="btn task-inline-label-delete-btn" data-label-id="' +
          labelId +
          '" title="Delete label"><i class="fa-regular fa-trash-can"></i></button>' +
          "</label>";
      }

      $panel
        .find(".task-inline-label-list")
        .html(
          listHtml || '<div class="task-label-empty">No labels found.</div>',
        );

      var canCreate =
        search.length > 0 &&
        !state.labels.some(function (label) {
          return (
            String(label.name || "").toLowerCase() === search.toLowerCase()
          );
        });

      $panel
        .find(".task-inline-label-create-row")
        .toggleClass("d-none", !canCreate);
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
        Array.isArray(labelsPanelState.selected)
          ? labelsPanelState.selected
          : []
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
        byId[id] = { id: id, name: name };
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

      renderManageStatusList();
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
        out.push({
          id: id,
          title: title,
          work_item_key: key,
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
        });
      });

      return normalizeParentOptions(items);
    }

    function updateCardParentSubmenuToggle($card, parentItemId) {
      var hasParent = Number(parentItemId || 0) > 0;
      $card.attr("data-parent-item-id", Number(parentItemId || 0));

      var $toggle = $card.find(".task-item-parent-submenu-toggle").first();
      if (!$toggle.length) {
        return;
      }

      $toggle
        .attr("data-has-parent", hasParent ? "1" : "0")
        .html(
          (hasParent ? "Change parent" : "Link parent") +
            ' <i class="fa-solid fa-chevron-right task-submenu-chevron"></i>',
        );
    }

    function renderParentSubmenu($card, $submenu) {
      var itemId = Number($card.data("itemId") || 0);
      var currentParentId = Number($card.attr("data-parent-item-id") || 0);
      var options = getBoardEpicParentOptions(itemId);

      var html = '<div class="task-item-parent-submenu-panel">';
      html +=
        '<button type="button" class="btn task-item-parent-option' +
        (currentParentId === 0 ? " active" : "") +
        '" data-parent-item-id="0">None</button>';

      if (!options.length) {
        html +=
          '<div class="task-item-parent-empty">No Epic items available.</div>';
      } else {
        for (var i = 0; i < options.length; i++) {
          var option = options[i] || {};
          var parentId = Number(option.id || 0);
          if (!parentId) {
            continue;
          }

          var label = String(option.display || option.title || "").trim();
          html +=
            '<button type="button" class="btn task-item-parent-option' +
            (currentParentId === parentId ? " active" : "") +
            '" data-parent-item-id="' +
            parentId +
            '">' +
            escHtml(label) +
            "</button>";
        }
      }

      html += "</div>";
      $submenu.find(".task-item-parent-submenu-content").html(html);
    }

    function renderDetailParentSelect(selectedParentId, parentOptions) {
      var selectedId = Number(selectedParentId || 0);
      var options = normalizeParentOptions(parentOptions);
      itemDetailModalState.parentOptions = options.slice();

      var html = '<option value="0">None</option>';
      for (var i = 0; i < options.length; i++) {
        var item = options[i] || {};
        html +=
          '<option value="' +
          Number(item.id || 0) +
          '">' +
          escHtml(item.display || item.title || "") +
          "</option>";
      }

      $("#taskItemDetailParentSelect").html(html).val(String(selectedId));

      if (Number($("#taskItemDetailParentSelect").val() || 0) !== selectedId) {
        $("#taskItemDetailParentSelect").val("0");
        selectedId = 0;
      }

      itemDetailModalState.parentItemId = selectedId;
      var $card = $(itemDetailModalState.cardEl || null);
      if ($card.length) {
        updateCardParentSubmenuToggle($card, selectedId);
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
        "https://icons.duckduckgo.com/ip3/" +
        encodeURIComponent(hostname) +
        ".ico"
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
          var alreadyUsed =
            String($img.attr("data-fallback-used") || "0") === "1";

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

    function renderManageStatusList() {
      var query = String($("#taskManageStatusInput").val() || "")
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

        html +=
          '<div class="task-manage-status-row">' +
          '<span class="task-manage-status-name">' +
          escHtml(name) +
          "</span>" +
          '<button type="button" class="btn task-manage-status-delete-btn" data-status-label-id="' +
          id +
          '" title="Delete status"><i class="fa-regular fa-trash-can"></i></button>' +
          "</div>";
      }

      $("#taskManageStatusList").html(
        html ||
          '<div class="task-manage-status-empty">No task status found.</div>',
      );
    }

    function priorityIconHtml(priority) {
      var value = String(priority || "Medium");
      var iconClass = "fa-minus task-priority-medium";
      if (value === "Highest") {
        iconClass = "fa-angles-up task-priority-highest";
      } else if (value === "High") {
        iconClass = "fa-angle-up task-priority-high";
      } else if (value === "Low") {
        iconClass = "fa-angle-down task-priority-low";
      } else if (value === "Lowest") {
        iconClass = "fa-angles-down task-priority-lowest";
      }

      return (
        '<i class="fa-solid task-priority-icon ' +
        escHtml(iconClass) +
        '"></i> ' +
        escHtml(value)
      );
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

      $("#taskItemDetailAssigneeSelect").html(html).val(String(selectedId));
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

      $("#taskItemDetailReporterSelect").html(html).val(String(selectedId));
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
        var name = statusLabelNameById(id);
        if (!id || !name) {
          continue;
        }

        html +=
          '<span class="task-label-pill task-item-detail-status-chip">' +
          escHtml(name) +
          '<button type="button" class="btn task-item-detail-status-chip-remove" data-status-label-id="' +
          id +
          '" title="Remove task status"><i class="fa-solid fa-xmark"></i></button>' +
          "</span>";
      }

      $("#taskItemDetailStatusChips").html(
        html ||
          '<span class="task-item-detail-empty-text">Select labels</span>',
      );
    }

    function setSelectedStatusLabels(ids) {
      itemDetailModalState.selectedStatusLabelIds =
        normalizeStatusLabelIdList(ids);
      renderStatusLabelChips();
      if (!$("#taskItemDetailStatusSearchInput").is(":focus")) {
        $("#taskItemDetailStatusSearchInput").val("");
      }
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

        html +=
          '<label class="task-item-detail-status-option">' +
          '<input class="form-check-input task-item-detail-status-checkbox" type="checkbox" value="' +
          id +
          '"' +
          checked +
          ">" +
          '<span class="task-item-detail-status-option-name">' +
          escHtml(name) +
          "</span>" +
          '<button type="button" class="btn task-item-detail-status-option-delete-btn" data-status-label-id="' +
          id +
          '" title="Delete status label"><i class="fa-regular fa-trash-can"></i></button>' +
          "</label>";
      }

      $("#taskItemDetailStatusOptionList").html(
        html || '<div class="task-label-empty">No task status found.</div>',
      );
    }

    function normalizeChildWorkItems(raw) {
      var info = raw && typeof raw === "object" ? raw : {};
      var rows = Array.isArray(info.items) ? info.items : [];
      return {
        items: rows,
        total: Number(info.total || rows.length || 0),
        done: Number(info.done || 0),
        progress_percent: Math.max(
          0,
          Math.min(100, Number(info.progress_percent || 0)),
        ),
      };
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
            return;
          }

          $(this).toggleClass(
            "d-none",
            !Object.prototype.hasOwnProperty.call(epicFields, field),
          );
        },
      );

      $("#taskItemChildWorkItemsSection").toggleClass("d-none", !isEpic);
      if (!isEpic) {
        setChildWorkItemsCollapsed(false);
      }
    }

    function renderChildWorkItemsSection() {
      var childInfo = normalizeChildWorkItems(
        itemDetailModalState.childWorkItems,
      );
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
      $("#taskItemChildWorkItemsProgressBar").css(
        "width",
        String(progress) + "%",
      );

      var rows = Array.isArray(childInfo.items) ? childInfo.items : [];
      var html = "";
      for (var i = 0; i < rows.length; i++) {
        var row = rows[i] || {};
        var workKey = String(row.work_item_key || "").trim();
        var workTitle = String(row.title || "").trim();
        var workText = String(
          (workKey ? workKey + " " : "") + workTitle,
        ).trim();
        var priority = String(row.priority || "Medium").trim() || "Medium";
        var assignee = String(row.assignee_name || "").trim() || "Unassigned";
        var statusName = String(row.status_name || "").trim() || "-";
        var statusClass =
          Number(row.is_done || 0) > 0
            ? " task-item-child-col-status-done"
            : "";

        html +=
          '<div class="task-item-child-row">' +
          '<span class="task-item-child-col-work" title="' +
          escHtml(workText) +
          '">' +
          escHtml(workText) +
          "</span>" +
          '<span class="task-item-child-col-priority">' +
          escHtml(priority) +
          "</span>" +
          '<span class="task-item-child-col-assignee">' +
          escHtml(assignee) +
          "</span>" +
          '<span class="task-item-child-col-status' +
          statusClass +
          '">' +
          escHtml(statusName) +
          "</span>" +
          "</div>";
      }

      $("#taskItemChildWorkItemsList").html(
        html || '<div class="task-item-child-empty">No child work items.</div>',
      );
    }

    function renderDetailKeyTrail() {
      var workKey = String(itemDetailModalState.workItemKey || "").trim();
      var parentKey = String(
        itemDetailModalState.parentWorkItemKey || "",
      ).trim();
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

      var currentHtml =
        '<span class="task-item-detail-key-segment">' +
        workTypeIconHtml(
          workTypeIcon,
          workTypeName,
          "task-item-detail-key-icon",
        ) +
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

      $("#taskItemDetailModalTitle").html(
        '<span class="task-item-detail-key-main">' + html + "</span>",
      );
      $("#taskItemDetailModalTitle").html(html);
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
          '<span class="task-label-pill task-item-detail-label-chip">' +
          escHtml(name) +
          '<button type="button" class="btn task-item-detail-label-chip-remove" data-label-id="' +
          id +
          '" title="Remove label"><i class="fa-solid fa-xmark"></i></button>' +
          "</span>";
      }

      $("#taskItemDetailLabelChips").html(
        html ||
          '<span class="task-item-detail-empty-text">Select labels</span>',
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
          '<span class="task-item-detail-label-option-name">' +
          escHtml(name) +
          "</span>" +
          '<button type="button" class="btn task-item-detail-label-option-delete-btn" data-label-id="' +
          id +
          '" title="Delete label"><i class="fa-regular fa-trash-can"></i></button>' +
          "</label>";
      }

      $("#taskItemDetailLabelOptionList").html(
        html || '<div class="task-label-empty">No labels found.</div>',
      );
    }

    function updateCardFromDetail(detail) {
      var $card = $(itemDetailModalState.cardEl || null);
      if (!$card.length || !detail || typeof detail !== "object") {
        return;
      }

      var assigneeUserId = Number(detail.assignee_user_id || 0);
      var assigneeName =
        String(detail.assignee_name || "").trim() || "Unassigned";

      $card.attr("data-assignee-user-id", assigneeUserId);
      var $assigneeBtn = $card.find(".task-item-assignee-btn");
      if ($assigneeBtn.length) {
        $assigneeBtn
          .attr("data-user-id", assigneeUserId)
          .attr("title", assigneeName)
          .toggleClass("task-assignee-pill-unassigned", assigneeUserId <= 0)
          .html(assigneeButtonInner(assigneeUserId, assigneeName));
      }

      var dueDate = String(detail.due_date || "").trim();
      var $due = $card.find(".task-item-due-date");
      if (dueDate) {
        if (!$due.length) {
          $due = $('<small class="task-item-due-date"></small>');
          $card.append($due);
        }
        $due.text("Due: " + dueDate);
      } else {
        $due.remove();
      }

      if (Array.isArray(detail.labels)) {
        setCardLabels($card, detail.labels);
      }

      if (Object.prototype.hasOwnProperty.call(detail, "parent_item_id")) {
        updateCardParentSubmenuToggle(
          $card,
          Number(detail.parent_item_id || 0),
        );
      }
    }

    function applyItemDetailToModal(
      detail,
      statusLabels,
      parentOptions,
      webLinks,
    ) {
      var info = detail && typeof detail === "object" ? detail : {};
      if (Array.isArray(statusLabels)) {
        normalizeStatusLabels(statusLabels);
      }

      var title = String(info.title || "").trim();
      var description = String(info.description || "").trim();
      $("#taskItemDetailTitleInput").val(title);
      $("#taskItemDetailDescriptionInput").val(description);
      itemDetailModalState.initialTitle = title;
      itemDetailModalState.initialDescription = description;

      $("#taskItemDetailEstimateValueInput").val(
        Number(info.original_estimate_value || 0),
      );
      $("#taskItemDetailEstimateUnitInput").val(
        String(info.original_estimate_unit || "minutes"),
      );

      var statusIds = Array.isArray(info.task_status_label_ids)
        ? info.task_status_label_ids
        : parseStatusLabelIdsFromRaw(info.task_status || "");
      setSelectedStatusLabels(statusIds);
      $("#taskItemDetailStatusSearchInput").val("");
      renderStatusLabelOptions("");
      renderDetailAssigneeSelect(Number(info.assignee_user_id || 0));
      renderDetailReporterSelect(Number(info.reporter_user_id || 0));
      var effectiveParentOptions = Array.isArray(parentOptions)
        ? parentOptions
        : getBoardEpicParentOptions(itemDetailModalState.itemId);
      renderDetailParentSelect(
        Number(info.parent_item_id || 0),
        effectiveParentOptions,
      );
      $("#taskItemDetailTimeTrackingValue").text(
        String(info.time_tracking || "").trim() || "No time logged",
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
        info.parent_work_item_key ||
          itemDetailModalState.parentWorkItemKey ||
          "",
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
      renderDetailKeyTrail();
      itemDetailModalState.childWorkItems = normalizeChildWorkItems(
        info.child_work_items,
      );
      renderChildWorkItemsSection();
      applyDetailFieldVisibility();

      $("#taskItemDetailDueDateInput").val(String(info.due_date || ""));
      $("#taskItemDetailStartDateInput").val(String(info.start_date || ""));
      $("#taskItemDetailAmendDateInput").val(
        String(info.amendement_date || ""),
      );
      $("#taskItemDetailAmendTimeInput").val(
        info.amendement_time_minutes
          ? String(info.amendement_time_minutes)
          : "",
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
    }

    function persistModalLabels(onDone) {
      var itemId = Number(itemDetailModalState.itemId || 0);
      if (!itemId) {
        if (typeof onDone === "function") {
          onDone();
        }
        return;
      }

      postAction(
        {
          task_action: "set_item_labels",
          item_id: itemId,
          label_ids: itemDetailModalState.selectedLabelIds.join(","),
        },
        function (res) {
          syncKnownLabels(res.allLabels || []);
          var detail = {
            labels: Array.isArray(res.labels) ? res.labels : [],
            assignee_user_id: Number(
              $("#taskItemDetailAssigneeSelect").val() || 0,
            ),
            assignee_name: String(
              $("#taskItemDetailAssigneeSelect option:selected").text() || "",
            ),
            due_date: String($("#taskItemDetailDueDateInput").val() || ""),
          };
          updateCardFromDetail(detail);
          itemDetailModalState.selectedLabelIds = detail.labels
            .map(function (item) {
              return Number(item.id || 0);
            })
            .filter(function (id) {
              return id > 0;
            });
          renderModalLabelChips();
          renderModalLabelOptions();
          if (typeof onDone === "function") {
            onDone();
          }
        },
      );
    }

    function saveItemDetailsFromModal(closeAfterSave) {
      if (!canEdit) {
        notify("You do not have permission to update work item.");
        return;
      }

      var itemId = Number(itemDetailModalState.itemId || 0);
      if (!itemId) {
        return;
      }

      var title = String($("#taskItemDetailTitleInput").val() || "").trim();
      var description = String(
        $("#taskItemDetailDescriptionInput").val() || "",
      ).trim();
      if (!title) {
        notify("Work item title is required.");
        return;
      }

      postAction(
        {
          task_action: "update_item_core",
          item_id: itemId,
          title: title,
          description: description,
        },
        function () {
          var payload = {
            task_action: "update_item_detail",
            item_id: itemId,
            assignee_user_id: Number(
              $("#taskItemDetailAssigneeSelect").val() || 0,
            ),
            reporter_user_id: Number(
              $("#taskItemDetailReporterSelect").val() || 0,
            ),
            priority: itemDetailModalState.selectedPriority || "Medium",
            original_estimate_value: Number(
              $("#taskItemDetailEstimateValueInput").val() || 0,
            ),
            original_estimate_unit: String(
              $("#taskItemDetailEstimateUnitInput").val() || "minutes",
            ),
            task_status_label_ids: normalizeStatusLabelIdList(
              itemDetailModalState.selectedStatusLabelIds,
            ).join(","),
            start_date: String($("#taskItemDetailStartDateInput").val() || ""),
            due_date: String($("#taskItemDetailDueDateInput").val() || ""),
            amendement_date: String(
              $("#taskItemDetailAmendDateInput").val() || "",
            ),
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

          postAction(payload, function (res) {
            var detail =
              res && res.detail && typeof res.detail === "object"
                ? res.detail
                : {};
            detail.title = title;
            detail.description = description;

            var $card = $(itemDetailModalState.cardEl || null);
            if ($card.length) {
              $card.find(".task-item-title").text(title);
              $card.attr("data-item-description", description);
            }

            itemDetailModalState.initialTitle = title;
            itemDetailModalState.initialDescription = description;
            applyItemDetailToModal(
              detail,
              res && res.statusLabels ? res.statusLabels : null,
              res && Array.isArray(res.parentOptions)
                ? res.parentOptions
                : null,
              res && Array.isArray(res.webLinks) ? res.webLinks : null,
            );

            persistModalLabels(function () {
              if (closeAfterSave) {
                var modal = getItemDetailModalInstance();
                if (modal) {
                  modal.hide();
                }
              }
            });
          });
        },
      );
    }

    function loadItemDetail(itemId) {
      var id = Number(itemId || 0);
      if (!id) {
        return;
      }

      postAction(
        {
          task_action: "get_item_detail",
          item_id: id,
        },
        function (res) {
          if (!res || !res.ok) {
            return;
          }
          applyItemDetailToModal(
            res.detail && typeof res.detail === "object" ? res.detail : {},
            Array.isArray(res.statusLabels) ? res.statusLabels : null,
            Array.isArray(res.parentOptions) ? res.parentOptions : null,
            Array.isArray(res.webLinks) ? res.webLinks : null,
          );
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
      var workItemKey = String(item.work_item_key || "").trim();
      if (!workItemKey) {
        workItemKey = buildWorkItemKey(item.id || 0);
      }
      var description = String(item.description || "").trim();
      var assigneeUserId = Number(item.assignee_user_id || 0);
      var assigneeName =
        String(item.assignee_name || "").trim() || "Unassigned";
      var dueDate = item.due_date || "";
      var parentItemId = Number(item.parent_item_id || 0);
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
          '<span class="task-label-pill">' + escHtml(labelName) + "</span>";
      }

      var labelActionText = labelIds.length ? "Edit label" : "Add labels";
      var isEpic =
        String(workTypeName || "")
          .trim()
          .toLowerCase() === "epic";
      var parentMenuHtml = "";
      if (!isEpic) {
        parentMenuHtml =
          '<li class="dropend task-item-submenu-wrap">' +
          '<a class="dropdown-item task-item-submenu-toggle task-item-parent-submenu-toggle" href="#" data-action="submenu_parent" data-has-parent="' +
          (parentItemId > 0 ? "1" : "0") +
          '">' +
          (parentItemId > 0 ? "Change parent" : "Link parent") +
          ' <i class="fa-solid fa-chevron-right task-submenu-chevron"></i></a>' +
          '<ul class="dropdown-menu task-item-submenu-list task-item-parent-submenu"><li class="task-item-parent-submenu-content"></li></ul>' +
          "</li>" +
          '<li><hr class="dropdown-divider"></li>';
      }

      return (
        '<article class="task-item-card" data-item-id="' +
        Number(item.id || 0) +
        '" data-label-ids="' +
        escHtml(labelIds.join(",")) +
        '" data-assignee-user-id="' +
        assigneeUserId +
        '" data-item-description="' +
        escHtml(description) +
        '" data-work-type-name="' +
        escHtml(workTypeName) +
        '" data-work-item-key="' +
        escHtml(workItemKey) +
        '" data-parent-item-id="' +
        parentItemId +
        '" draggable="true">' +
        '<div class="task-item-head">' +
        '<h6 class="task-item-title">' +
        escHtml(item.title || "") +
        "</h6>" +
        '<div class="dropdown task-item-menu-dropdown">' +
        '<button class="btn task-item-menu-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Task options"><i class="fa-solid fa-ellipsis"></i></button>' +
        '<ul class="dropdown-menu task-item-menu-list">' +
        '<li class="dropend task-item-submenu-wrap">' +
        '<a class="dropdown-item task-item-submenu-toggle" href="#" data-action="submenu_move">Move work item <i class="fa-solid fa-chevron-right task-submenu-chevron"></i></a>' +
        '<ul class="dropdown-menu task-item-submenu-list task-item-move-options"></ul>' +
        "</li>" +
        '<li class="dropend task-item-submenu-wrap">' +
        '<a class="dropdown-item task-item-submenu-toggle" href="#" data-action="submenu_status">Change status <i class="fa-solid fa-chevron-right task-submenu-chevron"></i></a>' +
        '<ul class="dropdown-menu task-item-submenu-list task-item-status-options"></ul>' +
        "</li>" +
        '<li><hr class="dropdown-divider"></li>' +
        '<li class="dropend task-item-submenu-wrap">' +
        '<a class="dropdown-item task-item-submenu-toggle task-item-label-submenu-toggle" href="#" data-action="submenu_labels">' +
        escHtml(labelActionText) +
        ' <i class="fa-solid fa-chevron-right task-submenu-chevron"></i></a>' +
        '<ul class="dropdown-menu task-item-submenu-list task-item-label-submenu"><li class="task-item-label-submenu-content"></li></ul>' +
        "</li>" +
        parentMenuHtml +
        '<li><a class="dropdown-item task-item-action text-danger" href="#" data-action="delete">Delete</a></li>' +
        "</ul>" +
        "</div>" +
        "</div>" +
        (labelsHtml
          ? '<div class="task-item-label-row">' + labelsHtml + "</div>"
          : "") +
        '<div class="task-item-meta">' +
        '<div class="task-item-meta-left">' +
        '<span class="task-type-icon" title="' +
        escHtml(workTypeName) +
        '">' +
        workTypeIconHtml(workTypeIcon, workTypeName, "task-type-pill-icon") +
        "</span>" +
        '<span class="task-item-key ' +
        (workItemKey ? "" : "d-none") +
        '">' +
        escHtml(workItemKey) +
        "</span>" +
        "</div>" +
        '<div class="dropdown task-item-assignee-wrap">' +
        '<button class="btn task-assignee-pill task-item-assignee-btn dropdown-toggle ' +
        (assigneeUserId > 0 ? "" : "task-assignee-pill-unassigned") +
        '" type="button" data-bs-toggle="dropdown" data-user-id="' +
        assigneeUserId +
        '" title="' +
        escHtml(assigneeName) +
        '">' +
        assigneeButtonInner(assigneeUserId, assigneeName) +
        "</button>" +
        '<ul class="dropdown-menu task-assignee-menu task-assignee-menu-scroll task-item-assignee-menu">' +
        assigneeMenuHtml() +
        "</ul>" +
        "</div>" +
        "</div>" +
        (dueDate
          ? '<small class="task-item-due-date">Due: ' +
            escHtml(dueDate) +
            "</small>"
          : "") +
        "</article>"
      );
    }

    function buildColumnHtml(column) {
      var def = defaultWorkType();

      return (
        '<section class="task-column" data-column-id="' +
        Number(column.id || 0) +
        '">' +
        '<div class="task-column-header">' +
        '<div class="task-column-title-wrap">' +
        '<h5 class="task-column-title">' +
        escHtml(column.name || "New Status") +
        '</h5><span class="task-column-count">0</span>' +
        "</div>" +
        '<div class="task-column-header-actions">' +
        '<button class="btn task-column-collapse-btn" type="button" title="Collapse status"><i class="fa-solid fa-left-right"></i></button>' +
        '<div class="dropdown">' +
        '<button class="btn task-column-menu-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-ellipsis"></i></button>' +
        '<ul class="dropdown-menu task-column-menu-list">' +
        '<li><a class="dropdown-item task-column-action" href="#" data-action="rename">Rename status</a></li>' +
        '<li><a class="dropdown-item task-column-action" href="#" data-action="move_left">Move status left</a></li>' +
        '<li><a class="dropdown-item task-column-action" href="#" data-action="move_right">Move status right</a></li>' +
        '<li><hr class="dropdown-divider"></li>' +
        '<li><a class="dropdown-item task-column-action text-danger" href="#" data-action="delete">Delete status</a></li>' +
        "</ul>" +
        "</div>" +
        "</div>" +
        "</div>" +
        '<div class="task-item-list"></div>' +
        '<button class="btn task-open-composer-btn" type="button"><i class="fa-solid fa-plus"></i> Create</button>' +
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
        '<button class="btn task-create-item-btn" type="button" disabled title="Create work item"><span class="mdi mdi-keyboard-return"></span></button>' +
        "</div>" +
        "</div>" +
        "</div>" +
        "</section>"
      );
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

    function openWorkTypeModal(mode, $composer) {
      var modal = getWorkTypeModalInstance();
      if (!modal) {
        notify("Work type modal is unavailable.");
        return;
      }

      var selected = defaultWorkType();
      var $toggle = $composer.find(".task-work-type-toggle").first();
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
      workTypeModalState.composerEl = $composer.get(0) || null;

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
      var count = $column.find(".task-item-card").length;
      $column.find(".task-column-count").text(count);
    }

    function updateAllColumnCounts() {
      $app.find(".task-column").each(function () {
        updateColumnCount($(this));
      });
    }

    function refreshCardItemKeys() {
      $app.find(".task-item-card").each(function () {
        var $card = $(this);
        var itemId = Number($card.data("itemId") || 0);
        var key = buildWorkItemKey(itemId);
        var $keyEl = $card.find(".task-item-key");
        if (!$keyEl.length) {
          $keyEl = $('<span class="task-item-key d-none"></span>');
          $card.find(".task-item-meta-left").append($keyEl);
        }
        $keyEl.text(key).toggleClass("d-none", !key);
      });
    }

    function hasAnyStatusColumn() {
      return $app.find(".task-column").length > 0;
    }

    function refreshEmptyBoardState() {
      $("#taskBoardEmpty").toggleClass("d-none", hasAnyStatusColumn());
    }

    function applyBoardFilters() {
      var query = String(state.filters.query || "")
        .trim()
        .toLowerCase();
      var assigneeFilter = String(state.filters.assignee || "all");
      var hasFilter = query !== "" || assigneeFilter !== "all";

      $app.find(".task-column").each(function () {
        var $column = $(this);
        var visibleCount = 0;

        $column.find(".task-item-card").each(function () {
          var $card = $(this);
          var title = String($card.find(".task-item-title").text() || "")
            .trim()
            .toLowerCase();
          var cardAssigneeId = Number($card.attr("data-assignee-user-id") || 0);

          var matchQuery = !query || title.indexOf(query) !== -1;
          var matchAssignee =
            assigneeFilter === "all" ||
            cardAssigneeId === Number(assigneeFilter || 0);

          var show = matchQuery && matchAssignee;
          $card.toggle(show);
          if (show) {
            visibleCount++;
          }
        });

        $column
          .find(".task-column-count")
          .text(
            hasFilter ? visibleCount : $column.find(".task-item-card").length,
          );
      });
    }

    function isMobileCreateStatusView() {
      return window.matchMedia("(max-width: 768px)").matches;
    }

    function resetCreateStatusInline() {
      $("#taskStatusName").val("");
      $("#taskCreateStatusInlineForm").addClass("d-none");
      $("#taskOpenCreateStatusBtn").removeClass("d-none");
      $createStatusSlot.removeClass("task-create-status-slot-expanded");
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
          var $newColumn = $(buildColumnHtml(column));
          $("#taskCreateStatusSlot").before($newColumn);
          updateColumnCount($newColumn);
          refreshEmptyBoardState();

          $("#taskStatusNameMobile").val("");
          resetCreateStatusInline();
        },
      );
    }

    function enableCreateButton($composer) {
      var title = String(
        $composer.find(".task-title-input").val() || "",
      ).trim();
      $composer
        .find(".task-create-item-btn")
        .prop("disabled", title.length === 0 || !canAdd);
    }

    $("#taskOpenCreateStatusBtn").on("click", function () {
      if (!canAdd) {
        notify("You do not have permission to create status.");
        return;
      }

      if (isMobileCreateStatusView()) {
        var modalEl = document.getElementById("taskCreateStatusMobileModal");
        if (modalEl && typeof bootstrap !== "undefined" && bootstrap.Modal) {
          var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
          modal.show();
          setTimeout(function () {
            $("#taskStatusNameMobile").trigger("focus");
          }, 120);
        }
        return;
      }

      $("#taskOpenCreateStatusBtn").addClass("d-none");
      $createStatusSlot.addClass("task-create-status-slot-expanded");
      $("#taskCreateStatusInlineForm").removeClass("d-none");
      $("#taskStatusName").trigger("focus");
    });

    $("#taskCreateStatusInlineForm").on("submit", function (e) {
      e.preventDefault();
      createStatus($("#taskStatusName").val());
    });

    $("#taskCreateStatusCancel").on("click", function () {
      resetCreateStatusInline();
    });

    $("#taskCreateStatusSubmitMobile").on("click", function () {
      createStatus($("#taskStatusNameMobile").val());
      var modalEl = document.getElementById("taskCreateStatusMobileModal");
      if (modalEl && typeof bootstrap !== "undefined" && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      }
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
        var newName = window.prompt("Rename status:", currentName);
        if (!newName) {
          return;
        }

        postAction(
          {
            task_action: "rename_status",
            column_id: columnId,
            column_name: newName,
          },
          function () {
            $column.find(".task-column-title").text(String(newName).trim());
          },
        );
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
            $column.remove();
            refreshEmptyBoardState();
          },
        );
      }
    });

    $app.on("click", ".task-open-composer-btn", function () {
      if (!canAdd) {
        notify("You do not have permission to create work items.");
        return;
      }

      var $column = $(this).closest(".task-column");
      var $composer = $column.find(".task-composer");
      $composer.removeClass("d-none");
      $composer.find(".task-title-input").trigger("focus");
      enableCreateButton($composer);
    });

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

    $app.on("click", ".task-work-type-option", function (e) {
      e.preventDefault();
      var $option = $(this);
      var workTypeId = Number($option.data("workTypeId") || 0);
      var workTypeName = String($option.data("workTypeName") || "Task").trim();
      var workTypeRemark = String($option.data("workTypeRemark") || "").trim();
      var workTypeIcon = normalizeWorkTypeIcon(
        $option.data("workTypeIcon"),
        workTypeName,
      );
      var $composer = $option.closest(".task-composer");
      setComposerWorkType($composer.find(".task-work-type-toggle"), {
        id: workTypeId,
        name: workTypeName,
        remark: workTypeRemark,
        svg_icon: workTypeIcon,
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

            $card.attr("data-assignee-user-id", selectedUserId);
            $btn
              .attr("data-user-id", selectedUserId)
              .attr("title", selectedName)
              .toggleClass("task-assignee-pill-unassigned", selectedUserId <= 0)
              .html(assigneeButtonInner(selectedUserId, selectedName));

            applyBoardFilters();
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

    $app.on("click", ".task-work-type-action", function (e) {
      e.preventDefault();
      if (!canEdit) {
        notify("You do not have permission to manage work types.");
        return;
      }

      var action = String($(this).data("action") || "");
      var $composer = $(this).closest(".task-composer");
      openWorkTypeModal(action === "edit" ? "edit" : "add", $composer);
    });

    $(document).on("input", "#taskWorkTypeNameInput", function () {
      updateWorkTypeModalSaveState();
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
      var remark = String(
        $("#taskWorkTypeDescriptionInput").val() || "",
      ).trim();
      var keepPickedIcon = $("#taskWorkTypeChangeIcon").is(":checked");
      var iconPath = keepPickedIcon
        ? normalizeWorkTypeIcon(workTypeModalState.iconPath, name || "Task")
        : normalizeWorkTypeIcon(
            workTypeModalState.initialIconPath,
            name || "Task",
          );

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

        var $composer = $(workTypeModalState.composerEl || null);
        if ($composer.length) {
          var selected = null;
          if (mode === "edit") {
            selected = findWorkTypeById(
              Number(workTypeModalState.workTypeId || 0),
            );
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
          setComposerWorkType(
            $composer.find(".task-work-type-toggle"),
            selected,
          );
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
        },
      );
    });

    $(document).on("click", "#taskProjectKeyClearBtn", function () {
      $("#taskProjectKeyInput").val("").trigger("focus");
    });

    $(document).on("click", "#taskManageStatusBtn", function (e) {
      e.preventDefault();
      var $panel = $("#taskManageStatusPanel");
      var show = $panel.hasClass("d-none");
      $panel.toggleClass("d-none", !show);
      if (show) {
        renderManageStatusList();
        setTimeout(function () {
          $("#taskManageStatusInput").trigger("focus");
        }, 50);
      }
    });

    $(document).on("input", "#taskManageStatusInput", function () {
      renderManageStatusList();
    });

    $(document).on("keydown", "#taskManageStatusInput", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        $("#taskManageStatusCreateBtn").trigger("click");
      }
    });

    $(document).on("click", "#taskManageStatusCreateBtn", function () {
      if (!canEdit) {
        notify("You do not have permission to manage task status labels.");
        return;
      }

      var labelName = String($("#taskManageStatusInput").val() || "").trim();
      if (!labelName) {
        notify("Task status name is required.");
        return;
      }

      postAction(
        {
          task_action: "create_status_label",
          status_label_name: labelName,
        },
        function (res) {
          normalizeStatusLabels(
            Array.isArray(res.statusLabels) ? res.statusLabels : [],
          );
          $("#taskManageStatusInput").val("");
          renderManageStatusList();
          renderStatusLabelOptions(
            $("#taskItemDetailStatusSearchInput").val() || "",
          );
        },
      );
    });

    $(document).on("click", ".task-manage-status-delete-btn", function () {
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
        },
      );
    });

    $app.on("click", ".task-create-item-btn", function () {
      if (!canAdd) {
        notify("You do not have permission to create work items.");
        return;
      }

      var $composer = $(this).closest(".task-composer");
      var $column = $composer.closest(".task-column");
      var columnId = Number($column.data("columnId") || 0);
      var title = String(
        $composer.find(".task-title-input").val() || "",
      ).trim();
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
          $column.find(".task-item-list").append(buildTaskCardHtml(item));
          updateColumnCount($column);
          applyBoardFilters();
          refreshCardItemKeys();

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

    $app.on("show.bs.dropdown", ".task-item-menu-dropdown", function () {
      var $dropdown = $(this);
      var $btn = $dropdown.find(".task-item-menu-btn").first();
      var $card = $dropdown.closest(".task-item-card");
      var $column = $dropdown.closest(".task-column");
      var $menu = $dropdown.find(".task-item-menu-list").first();

      var $cards = $column.find(".task-item-card");
      var total = $cards.length;
      var index = $cards.index($card);

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

      var currentColumnId = Number($column.data("columnId") || 0);
      var statusHtml = "";
      $app.find(".task-column").each(function () {
        var $statusColumn = $(this);
        var statusColumnId = Number($statusColumn.data("columnId") || 0);
        if (!statusColumnId || statusColumnId === currentColumnId) {
          return;
        }

        var statusName = String(
          $statusColumn.find(".task-column-title").first().text() || "",
        ).trim();
        if (!statusName) {
          return;
        }

        statusHtml +=
          '<li><a class="dropdown-item task-item-action" href="#" data-action="change_status" data-target-column-id="' +
          statusColumnId +
          '">' +
          escHtml(statusName) +
          "</a></li>";
      });

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

      var $parentWrap = $menu
        .find(".task-item-parent-submenu-toggle")
        .closest(".task-item-submenu-wrap");
      if ($parentWrap.length) {
        var isEpicCard =
          String($card.attr("data-work-type-name") || "")
            .trim()
            .toLowerCase() === "epic";
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
        var menuHeight = $menu.outerHeight() || 0;
        var btnRect = $btn.length ? $btn.get(0).getBoundingClientRect() : null;
        var viewportHeight =
          window.innerHeight || document.documentElement.clientHeight || 0;
        var boardRect =
          $app.length && $app.get(0)
            ? $app.get(0).getBoundingClientRect()
            : null;
        var boundaryTop = boardRect ? boardRect.top + 10 : 10;
        var boundaryBottom = boardRect
          ? Math.min(boardRect.bottom - 10, viewportHeight - 10)
          : viewportHeight - 10;
        var spaceBelow = btnRect ? boundaryBottom - btnRect.bottom : 0;
        var spaceAbove = btnRect ? btnRect.top - boundaryTop : 0;
        var shouldOpenUp =
          !!btnRect && menuHeight > spaceBelow && spaceAbove > spaceBelow;
        $dropdown.toggleClass("task-item-menu-open-up", shouldOpenUp);
      }, 0);
    });

    $app.on("hidden.bs.dropdown", ".task-item-menu-dropdown", function () {
      $(this).removeClass("task-item-menu-open-up");
    });

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
      $menu
        .find(".task-item-submenu-wrap")
        .not($submenuWrap)
        .removeClass("show");

      if (action === "submenu_labels") {
        renderInlineLabelPanel($card, $submenu);
      } else if (action === "submenu_parent") {
        renderParentSubmenu($card, $submenu);
      }

      $submenu.addClass("show");
      $submenuWrap.addClass("show");
    }

    function closeItemSubmenu($wrap) {
      $wrap.removeClass("show");
      $wrap.children(".task-item-submenu-list").removeClass("show");
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

    $app.on("input", ".task-inline-label-search", function (e) {
      e.stopPropagation();
      var $submenu = $(this).closest(".task-item-label-submenu");
      refreshInlineLabelList($submenu);
    });

    $app.on("change", ".task-inline-label-checkbox", function (e) {
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
        labelsPanelState.selected = labelsPanelState.selected.filter(
          function (id) {
            return id !== labelId;
          },
        );
      }
    });

    $app.on("click", ".task-inline-label-create-btn", function (e) {
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

    $app.on("click", ".task-inline-label-delete-btn", function (e) {
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

    $app.on("click", ".task-inline-label-save", function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $submenu = $(this).closest(".task-item-label-submenu");
      var $card = $(this).closest(".task-item-card");
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
        },
      );
    });

    $app.on("click", ".task-item-action", function (e) {
      e.preventDefault();

      if (!canEdit) {
        notify("You do not have permission to manage work items.");
        return;
      }

      var $action = $(this);
      var action = String($action.data("action") || "");
      var $card = $action.closest(".task-item-card");
      var $column = $action.closest(".task-column");
      var itemId = Number($card.data("itemId") || 0);

      if (!itemId) {
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
            } else if (
              action === "move_down" &&
              currentIndex < $cards.length - 1
            ) {
              $card.insertAfter($cards.eq(currentIndex + 1));
            } else if (action === "move_bottom") {
              $column.find(".task-item-list").append($card);
            }
            updateColumnCount($column);
            applyBoardFilters();
          },
        );
        return;
      }

      if (action === "change_status") {
        var targetColumnId = Number($action.data("targetColumnId") || 0);
        if (!targetColumnId) {
          return;
        }

        postAction(
          {
            task_action: "change_item_status",
            item_id: itemId,
            target_column_id: targetColumnId,
          },
          function () {
            var $targetColumn = $app.find(
              '.task-column[data-column-id="' + targetColumnId + '"]',
            );
            if (!$targetColumn.length) {
              return;
            }

            $targetColumn.find(".task-item-list").append($card);
            updateColumnCount($column);
            updateColumnCount($targetColumn);
            applyBoardFilters();
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
          },
        );
      }
    });

    $app.on("click", ".task-item-parent-option", function (e) {
      e.preventDefault();
      e.stopPropagation();

      if (!canEdit) {
        notify("You do not have permission to link parent.");
        return;
      }

      var $option = $(this);
      var $card = $option.closest(".task-item-card");
      var itemId = Number($card.data("itemId") || 0);
      var parentItemId = Number($option.data("parentItemId") || 0);
      if (!itemId) {
        return;
      }

      postAction(
        {
          task_action: "set_item_parent",
          item_id: itemId,
          parent_item_id: parentItemId,
        },
        function (res) {
          var resolvedParentId = Number(
            (res && res.parent_item_id) || parentItemId || 0,
          );
          updateCardParentSubmenuToggle($card, resolvedParentId);

          var $submenu = $option.closest(".task-item-parent-submenu");
          renderParentSubmenu($card, $submenu);
          $submenu.removeClass("show");
          $submenu.closest(".task-item-submenu-wrap").removeClass("show");

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
    });

    function getItemDetailModalInstance() {
      var modalEl = document.getElementById("taskItemDetailModal");
      if (!modalEl || typeof bootstrap === "undefined" || !bootstrap.Modal) {
        return null;
      }
      return bootstrap.Modal.getOrCreateInstance(modalEl);
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
      return (size / (1024 * 1024)).toFixed(1).replace(/\.0$/, "") + " MB";
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
        String(itemDetailModalState.attachmentSort.direction || "desc") ===
        "asc"
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
        String(itemDetailModalState.attachmentSort.direction || "desc") ===
        "asc"
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
            '<span class="task-item-attachment-tile-preview"><i class="fa-regular fa-file-lines"></i></span>' +
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
        html ||
          '<div class="task-item-attachment-empty">No attachments yet.</div>',
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

    function saveItemCoreFromModal(closeAfterSave) {
      if (!canEdit) {
        notify("You do not have permission to update work item.");
        return;
      }

      var itemId = Number(itemDetailModalState.itemId || 0);
      if (!itemId) {
        return;
      }

      var title = String($("#taskItemDetailTitleInput").val() || "").trim();
      var description = String(
        $("#taskItemDetailDescriptionInput").val() || "",
      ).trim();
      if (!title) {
        notify("Work item title is required.");
        return;
      }

      postAction(
        {
          task_action: "update_item_core",
          item_id: itemId,
          title: title,
          description: description,
        },
        function () {
          var $card = $(itemDetailModalState.cardEl || null);
          if ($card.length) {
            $card.find(".task-item-title").text(title);
            $card.attr("data-item-description", description);
          }

          itemDetailModalState.initialTitle = title;
          itemDetailModalState.initialDescription = description;

          if (closeAfterSave) {
            var modal = getItemDetailModalInstance();
            if (modal) {
              modal.hide();
            }
          }
        },
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
      var description = String(
        $card.attr("data-item-description") || "",
      ).trim();

      itemDetailModalState.itemId = itemId;
      itemDetailModalState.cardEl = $card.get(0) || null;
      itemDetailModalState.initialTitle = title;
      itemDetailModalState.initialDescription = description;
      itemDetailModalState.workTypeName = String(
        $card.attr("data-work-type-name") || "Task",
      );
      itemDetailModalState.workTypeIcon = String(
        $card.attr("data-work-type-icon") || "",
      );
      itemDetailModalState.workItemKey = String(
        $card.attr("data-work-item-key") || buildWorkItemKey(itemId),
      );
      itemDetailModalState.parentWorkItemKey = "";
      itemDetailModalState.parentWorkTypeName = "Task";
      itemDetailModalState.parentWorkTypeIcon = "";

      $("#taskItemDetailTitleInput").val(title);
      $("#taskItemDetailDescriptionInput").val(description);
      itemDetailModalState.attachmentSort = {
        field: "date",
        direction: "desc",
      };
      itemDetailModalState.attachmentView = "list";
      itemDetailModalState.showAttachmentPanelWhenEmpty = false;
      itemDetailModalState.pendingAttachmentPicker = false;
      itemDetailModalState.selectedPriority = "Medium";
      itemDetailModalState.selectedStatusLabelIds = [];
      itemDetailModalState.selectedLabelIds = [];
      itemDetailModalState.childWorkItems = {
        items: [],
        total: 0,
        done: 0,
        progress_percent: 0,
      };
      itemDetailModalState.childWorkItemsCollapsed = false;
      itemDetailModalState.detailsCollapsed = false;
      setAttachmentPanelCollapsed(false);
      renderItemAttachments([]);
      setSelectedStatusLabels([]);
      renderStatusLabelOptions("");
      renderDetailAssigneeSelect(0);
      renderDetailReporterSelect(0);
      renderModalLabelChips();
      renderModalLabelOptions();
      renderChildWorkItemsSection();
      setChildWorkItemsCollapsed(false);
      applyDetailFieldVisibility();
      setDetailSideCollapsed(false);
      renderDetailKeyTrail();
      setDetailPriority("Medium");
      $("#taskItemDetailParentValue").text("None");
      $("#taskItemDetailTimeTrackingValue").text("No time logged");
      $("#taskItemDetailEstimateValueInput").val("0");
      $("#taskItemDetailEstimateUnitInput").val("minutes");
      $("#taskItemDetailStartDateInput").val("");
      $("#taskItemDetailDueDateInput").val("");
      $("#taskItemDetailAmendDateInput").val("");
      $("#taskItemDetailAmendTimeInput").val("");
      $("#taskItemDetailSecondAmendDateInput").val("");
      $("#taskItemDetailSecondAmendTimeInput").val("");
      loadItemAttachments(itemId);
      loadItemDetail(itemId);
      modal.show();
    }

    $app.on("click", ".task-item-card", function (e) {
      if (
        $(e.target).closest(
          ".task-item-menu-dropdown, .task-item-assignee-wrap, .task-assignee-menu, .task-inline-label-panel, .task-item-submenu-list, .task-item-submenu-toggle, .task-item-action, .dropdown-menu",
        ).length
      ) {
        return;
      }

      openItemDetailModal($(this));
    });

    $(document).on("click", "#taskItemDetailTitleSaveBtn", function () {
      saveItemCoreFromModal(false);
    });

    $(document).on("click", "#taskItemDetailTitleResetBtn", function () {
      $("#taskItemDetailTitleInput").val("").trigger("focus");
    });

    $(document).on("click", "#taskItemDetailSaveBtn", function () {
      saveItemDetailsFromModal(true);
    });

    $(document).on("click", ".task-item-detail-priority-option", function (e) {
      e.preventDefault();
      setDetailPriority(String($(this).data("priority") || "Medium"));
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
      renderStatusLabelOptions(
        $("#taskItemDetailStatusSearchInput").val() || "",
      );
    });

    $(document).on(
      "click",
      ".task-item-detail-status-chip-remove",
      function (e) {
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
        renderStatusLabelOptions(
          $("#taskItemDetailStatusSearchInput").val() || "",
        );
      },
    );

    $(document).on("click", "#taskItemDetailSideCollapseBtn", function () {
      setDetailSideCollapsed(!itemDetailModalState.detailsCollapsed);
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

    $(document).on(
      "click",
      ".task-item-detail-label-chip-remove",
      function (e) {
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
      },
    );

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
        },
      );
    });

    $(document).on("change", "#taskItemDetailParentSelect", function () {
      if (!canEdit) {
        notify("You do not have permission to link parent.");
        $(this).val(String(itemDetailModalState.parentItemId || 0));
        return;
      }

      var itemId = Number(itemDetailModalState.itemId || 0);
      if (!itemId) {
        return;
      }

      var previousParentId = Number(itemDetailModalState.parentItemId || 0);
      var selectedParentId = Number($(this).val() || 0);

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
          renderDetailParentSelect(
            resolvedParentId,
            Array.isArray(res.parentOptions)
              ? res.parentOptions
              : getBoardEpicParentOptions(itemId),
          );
          var $card = $(itemDetailModalState.cardEl || null);
          if ($card.length) {
            updateCardParentSubmenuToggle($card, resolvedParentId);
          }
        },
        function () {
          $("#taskItemDetailParentSelect").val(String(previousParentId));
        },
      );
    });

    $(document).on("click", "#taskItemAttachmentCollapseBtn", function () {
      setAttachmentPanelCollapsed(!itemDetailModalState.attachmentsCollapsed);
    });

    $(document).on(
      "click",
      "#taskItemAttachmentToggleViewAction",
      function (e) {
        e.preventDefault();
        itemDetailModalState.attachmentView =
          itemDetailModalState.attachmentView === "strip" ? "list" : "strip";
        renderItemAttachments(itemDetailModalState.attachments);
      },
    );

    $(document).on(
      "click",
      "#taskItemAttachmentDownloadAllAction",
      function (e) {
        e.preventDefault();
        triggerAttachmentDownloads(
          sortedAttachments(itemDetailModalState.attachments),
        );
      },
    );

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
          itemDetailModalState.attachmentSort.direction === "asc"
            ? "desc"
            : "asc";
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
          var attachments = Array.isArray(res.attachments)
            ? res.attachments
            : [];
          if (!attachments.length) {
            itemDetailModalState.showAttachmentPanelWhenEmpty = false;
          }
          renderItemAttachments(attachments);
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

        $.ajax({
          url: ajaxUrl,
          method: "POST",
          dataType: "json",
          data: formData,
          processData: false,
          contentType: false,
          timeout: 60000,
        })
          .done(function (res) {
            if (!res || !res.ok) {
              notify(
                (res && res.message) ||
                  "Failed uploading one or more attachments.",
              );
              return;
            }

            itemDetailModalState.showAttachmentPanelWhenEmpty = true;
            renderItemAttachments(
              Array.isArray(res.attachments) ? res.attachments : [],
            );
          })
          .fail(function () {
            notify("Attachment upload request failed.");
          })
          .always(function () {
            uploadNext(index + 1);
          });
      };

      uploadNext(0);
    });

    $(document).on("hidden.bs.modal", "#taskItemDetailModal", function () {
      itemDetailModalState.itemId = 0;
      itemDetailModalState.cardEl = null;
      itemDetailModalState.initialTitle = "";
      itemDetailModalState.initialDescription = "";
      itemDetailModalState.attachments = [];
      itemDetailModalState.attachmentSort = {
        field: "date",
        direction: "desc",
      };
      itemDetailModalState.attachmentsCollapsed = false;
      itemDetailModalState.attachmentView = "list";
      itemDetailModalState.showAttachmentPanelWhenEmpty = false;
      itemDetailModalState.pendingAttachmentPicker = false;
      itemDetailModalState.selectedPriority = "Medium";
      itemDetailModalState.selectedStatusLabelIds = [];
      itemDetailModalState.selectedLabelIds = [];
      itemDetailModalState.detailsCollapsed = false;
      itemDetailModalState.workTypeName = "Task";
      itemDetailModalState.workTypeIcon = "";
      itemDetailModalState.workItemKey = "";
      itemDetailModalState.parentWorkItemKey = "";
      itemDetailModalState.parentWorkTypeName = "Task";
      itemDetailModalState.parentWorkTypeIcon = "";
      itemDetailModalState.childWorkItems = {
        items: [],
        total: 0,
        done: 0,
        progress_percent: 0,
      };
      itemDetailModalState.childWorkItemsCollapsed = false;
      $("#taskItemDetailKeyTrail").addClass("d-none").empty();
      $("#taskItemDetailModalTitle").text("Work item");
      $("#taskItemAttachmentInput").val("");
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

      e.preventDefault();
      var $list = $(this);
      var target = getDropTargetElement(
        $list,
        e.originalEvent && e.originalEvent.clientY
          ? e.originalEvent.clientY
          : 0,
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

      e.preventDefault();

      var $targetList = $(this);
      var $item = dragState.$item;
      var itemId = Number($item.data("itemId") || 0);
      var targetColumnId = Number(
        $targetList.closest(".task-column").data("columnId") || 0,
      );
      var targetIndex =
        $targetList.children(".task-item-card").index($item) + 1;
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
        updateAllColumnCounts();
        applyBoardFilters();
      };

      postAction(
        {
          task_action: "move_item_drop",
          item_id: itemId,
          target_column_id: targetColumnId,
          target_index: targetIndex,
        },
        function () {
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
      state.filters.query = String($(this).val() || "");
      applyBoardFilters();
    });

    $("#taskBoardAssigneeFilterMenu").on(
      "click",
      ".task-board-assignee-filter-option",
      function (e) {
        e.preventDefault();
        var $option = $(this);
        var filterValue = String($option.data("assigneeFilter") || "all");
        var filterName = String($option.data("assigneeName") || "All");

        state.filters.assignee = filterValue;
        $("#taskBoardAssigneeFilterBtn").text("Filter: " + filterName);
        $(
          "#taskBoardAssigneeFilterMenu .task-board-assignee-filter-option",
        ).removeClass("active");
        $option.addClass("active");
        applyBoardFilters();
      },
    );

    // Labels are handled in task item submenu panel.

    if (!canAdd) {
      $app.find(".task-open-composer-btn").prop("disabled", true);
      $("#taskOpenCreateStatusBtn").prop("disabled", true);
      $("#taskCreateStatusSubmit").prop("disabled", true);
      $("#taskCreateStatusSubmitMobile").prop("disabled", true);
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

    updateAllColumnCounts();
    refreshEmptyBoardState();
    applyBoardFilters();
    refreshCardItemKeys();
  });
})();
