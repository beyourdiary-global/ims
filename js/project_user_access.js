$(function () {
  var columnState = window.projectUserAccessColumnState || {};
  var config = window.projectUserAccessConfig || {};
  var $form = $("#projectUserAccessForm");
  var currentColumnKey = $(".project-access-column-section.active").data("column-key") || "";
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
      $(this).prop("checked", row.add == 1 && row.edit == 1 && row.delete == 1);
    });
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
    }).done(function (res) {
      if (!res || !res.ok) {
        window.alert(res && res.message ? res.message : "Failed to update project user access.");
      }
    }).fail(function () {
      window.alert("Failed to update project user access.");
    }).always(function () {
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

  $(".project-access-row-toggle").on("change", function () {
    var checked = $(this).is(":checked");
    $(this).closest("tr").find(".project-access-checkbox").not(this).prop("checked", checked);
    queueSave();
  });

  $(".project-access-checkbox").not(".project-access-column-action, .project-access-column-row-toggle, .project-access-row-toggle").on("change", function () {
    queueSave();
  });

  $(".project-access-tab").on("click", function () {
    var tab = $(this).data("project-access-tab");
    $(".project-access-tab").removeClass("active");
    $(this).addClass("active");
    $(".project-access-panel").removeClass("active");
    $('.project-access-panel[data-project-access-panel="' + tab + '"]').addClass("active");
  });

  $(".project-access-form").on("submit", function (event) {
    event.preventDefault();
  });

  renderColumnCheckboxes();
});
