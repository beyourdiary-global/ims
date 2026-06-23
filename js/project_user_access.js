$(function () {
  var columnState = window.projectUserAccessColumnState || {};
  var config = window.projectUserAccessConfig || {};
  var $form = $("#projectUserAccessForm");
  var $search = $("#projectAccessSearch");
  var $roleFilter = $("#projectAccessRoleFilter");
  var currentColumnKey =
    $(".project-access-column-section.active").data("column-key") || "";
  var saveTimer = null;
  var saveInFlight = false;
  var saveQueued = false;

  function getUserColumn(userId, columnKey) {
    userId = String(userId);
    if (!columnState[userId]) columnState[userId] = {};
    if (!columnState[userId][columnKey]) {
      columnState[userId][columnKey] = { add: 0, edit: 0, delete: 0 };
    }
    return columnState[userId][columnKey];
  }

  function getColumnKeys() {
    var keys = [];

    $(".project-access-column-section").each(function () {
      var columnKey = String($(this).data("column-key") || "").trim();
      if (columnKey && keys.indexOf(columnKey) === -1) {
        keys.push(columnKey);
      }
    });

    return keys;
  }

  function getColumnAccessUserIds() {
    var userIds = [];

    $(".project-access-column-row-toggle").each(function () {
      var userId = String($(this).data("user-id") || "").trim();
      if (userId && userIds.indexOf(userId) === -1) {
        userIds.push(userId);
      }
    });

    return userIds;
  }

  function isAllColumnsActionChecked(action) {
    var bulkAction = String(action || "").trim();
    var columnKeys = getColumnKeys();
    var userIds = getColumnAccessUserIds();

    if (!columnKeys.length || !userIds.length) {
      return false;
    }

    for (var userIndex = 0; userIndex < userIds.length; userIndex++) {
      for (var columnIndex = 0; columnIndex < columnKeys.length; columnIndex++) {
        var row = getUserColumn(userIds[userIndex], columnKeys[columnIndex]);

        if (bulkAction === "access") {
          if (
            Number(row.add || 0) !== 1 ||
            Number(row.edit || 0) !== 1 ||
            Number(row.delete || 0) !== 1
          ) {
            return false;
          }
          continue;
        }

        if (Number(row[bulkAction] || 0) !== 1) {
          return false;
        }
      }
    }

    return true;
  }

  function syncColumnBulkToggles() {
    $(".project-access-column-bulk-toggle").each(function () {
      var action = String($(this).data("column-bulk-action") || "").trim();
      $(this).prop("checked", isAllColumnsActionChecked(action));
    });
  }

  function setAllColumnsAction(action, checked) {
    var bulkAction = String(action || "").trim();
    var value = checked ? 1 : 0;
    var columnKeys = getColumnKeys();
    var userIds = getColumnAccessUserIds();

    if (!columnKeys.length || !userIds.length) {
      return;
    }

    for (var userIndex = 0; userIndex < userIds.length; userIndex++) {
      for (var columnIndex = 0; columnIndex < columnKeys.length; columnIndex++) {
        var row = getUserColumn(userIds[userIndex], columnKeys[columnIndex]);

        if (bulkAction === "access") {
          row.add = value;
          row.edit = value;
          row.delete = value;
          continue;
        }

        if (bulkAction === "add" || bulkAction === "edit" || bulkAction === "delete") {
          row[bulkAction] = value;
        }
      }
    }

    renderColumnCheckboxes();
    queueSave();
  }

  function renderColumnCheckboxes() {
    $(".project-access-column-action").each(function () {
      var $cb = $(this);
      var userId = $cb.data("user-id");
      var action = $cb.data("column-action");
      var row = getUserColumn(userId, currentColumnKey);
      $cb.prop("checked", Number(row[action] || 0) === 1);
    });

    $(".project-access-column-row-toggle").each(function () {
      var userId = $(this).data("user-id");
      var row = getUserColumn(userId, currentColumnKey);
      $(this).prop(
        "checked",
        Number(row.add || 0) === 1 &&
          Number(row.edit || 0) === 1 &&
          Number(row.delete || 0) === 1
      );
    });

    syncColumnBulkToggles();
  }

  function buildHiddenInputs() {
    var $store = $("#projectAccessHiddenColumnStore");
    $store.empty();

    Object.keys(columnState).forEach(function (userId) {
      Object.keys(columnState[userId]).forEach(function (columnKey) {
        ["add", "edit", "delete"].forEach(function (action) {
          if (Number(columnState[userId][columnKey][action] || 0) === 1) {
            $store.append(
              '<input type="hidden" name="column_permissions[' +
                userId +
                "][" +
                columnKey +
                "][" +
                action +
                ']" value="1">'
            );
          }
        });
      });
    });
  }

  function syncStandardRowToggles() {
    $(".project-access-user-row").each(function () {
      var $row = $(this);
      var $toggle = $row.find(".project-access-row-toggle");
      if (!$toggle.length) {
        return;
      }

      var $others = $row
        .find(".project-access-checkbox")
        .not(".project-access-row-toggle, .project-access-column-action, .project-access-column-row-toggle");
      if (!$others.length) {
        return;
      }

      var allChecked = true;
      $others.each(function () {
        if (!$(this).is(":checked")) {
          allChecked = false;
          return false;
        }
      });
      $toggle.prop("checked", allChecked);
    });
  }

  function applyUserFilters() {
    var query = String($search.val() || "")
      .toLowerCase()
      .trim();
    var groupKey = String($roleFilter.val() || "").toLowerCase().trim();

    $(".project-access-user-row").each(function () {
      var $row = $(this);
      var haystack = String($row.data("search") || "");
      var rowGroup = String($row.data("group") || "");
      var visible =
        (!query || haystack.indexOf(query) !== -1) &&
        (!groupKey || rowGroup === groupKey);

      $row.toggleClass("project-access-hidden-row", !visible);
    });
  }

  function saveAccessNow() {
    if (!$form.length) {
      return;
    }

    buildHiddenInputs();

    var formData = new FormData($form.get(0));
    formData.append("task_action", "save_project_user_access_ajax");
    formData.set("csrf_token", String(config.csrfToken || ""));

    saveInFlight = true;
    $.ajax({
      url: String(config.ajaxUrl || window.location.href),
      method: "POST",
      data: formData,
      processData: false,
      contentType: false,
      dataType: "json"
    })
      .done(function (res) {
        if (!res || !res.ok) {
          showNotification(
            res && res.message
              ? res.message
              : "Failed to update project user access.",
            "error",
          );
          return;
        }
      })
      .fail(function () {
        showNotification("Failed to update project user access.", "error");
      })
      .always(function () {
        saveInFlight = false;
        if (saveQueued) {
          saveQueued = false;
          queueSave();
        }
      });
  }

  function queueSave() {
    if (!$form.length) {
      return;
    }

    if (saveInFlight) {
      saveQueued = true;
      return;
    }

    window.clearTimeout(saveTimer);
    saveTimer = window.setTimeout(function () {
      saveAccessNow();
    }, 120);
  }

  $(".project-access-column-section").on("click", function () {
    $(".project-access-column-section").removeClass("active");
    $(this).addClass("active");
    currentColumnKey = $(this).data("column-key");
    renderColumnCheckboxes();
  });

  $(".project-access-column-action").on("change", function () {
    var userId = $(this).data("user-id");
    var action = $(this).data("column-action");
    var row = getUserColumn(userId, currentColumnKey);
    row[action] = $(this).is(":checked") ? 1 : 0;
    renderColumnCheckboxes();
    queueSave();
  });

  $(".project-access-column-row-toggle").on("change", function () {
    var userId = $(this).data("user-id");
    var checked = $(this).is(":checked") ? 1 : 0;
    var row = getUserColumn(userId, currentColumnKey);
    row.add = checked;
    row.edit = checked;
    row.delete = checked;
    renderColumnCheckboxes();
    queueSave();
  });

  $(".project-access-column-bulk-toggle").on("change", function () {
    var action = String($(this).data("column-bulk-action") || "").trim();
    setAllColumnsAction(action, $(this).is(":checked"));
  });

  $(".project-access-row-toggle").on("change", function () {
    var checked = $(this).is(":checked");
    $(this)
      .closest("tr")
      .find(".project-access-checkbox")
      .not(this)
      .not(".project-access-column-action, .project-access-column-row-toggle")
      .prop("checked", checked);
    syncStandardRowToggles();
    queueSave();
  });

  $(".project-access-checkbox")
    .not(
      ".project-access-column-action, .project-access-column-row-toggle, .project-access-row-toggle"
    )
    .on("change", function () {
      syncStandardRowToggles();
      queueSave();
    });

  $(".project-access-tab").on("click", function () {
    var tab = $(this).data("project-access-tab");
    $(".project-access-tab").removeClass("active");
    $(this).addClass("active");
    $(".project-access-panel").removeClass("active");
    $('.project-access-panel[data-project-access-panel="' + tab + '"]').addClass(
      "active"
    );
    applyUserFilters();
  });

  $(".project-access-form").on("submit", function (event) {
    event.preventDefault();
  });

  $(".project-access-row-menu").on("click", function (event) {
    event.preventDefault();
  });

  $search.on("input", applyUserFilters);
  $roleFilter.on("change", applyUserFilters);

  renderColumnCheckboxes();
  syncStandardRowToggles();
  applyUserFilters();
});
