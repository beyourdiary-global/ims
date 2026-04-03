(function () {
  "use strict";

  if (typeof jQuery === "undefined") {
    return;
  }

  jQuery(function ($) {
    var cfg = window.__USER_RECORD_LOG_CONFIG || {};
    var ajaxUrl = cfg.ajaxUrl || "user_record_log.php";
    var customerId = parseInt(cfg.customerId || "0", 10) || 0;
    var customerColumn = String(cfg.customerColumn || "");
    var pathReturn = cfg.pathReturn || window.location.href;
    var confirmationPageName = cfg.confirmationPageName || "User Record Log";

    var $form = $("#url_form");
    var $alert = $("#url_alert");
    var $list = $("#url_list_container");
    var $loading = $("#url_loading");
    var $pageSize = $("#url_page_size");
    var $pagination = $("#url_pagination");
    var $pagingSummary = $("#url_paging_summary");
    var $recordId = $("#url_record_id");
    var $content = $("#url_content");
    var $existingAttachment = $("#url_existing_attachment");
    var $submitBtn = $("#url_submit_btn");
    var $cancelEditBtn = $("#url_cancel_edit_btn");
    var currentPage = 1;
    var currentPageSize = parseInt($pageSize.val() || "10", 10) || 10;

    if (!$form.length || !$alert.length || !$list.length || !$loading.length) {
      return;
    }

    function showAlert(type, text) {
      $alert.removeClass(
        "d-none alert-success alert-danger alert-info alert-secondary",
      );
      $alert.addClass("alert-" + type).text(text || "");
    }

    function hideAlert() {
      $alert.addClass("d-none").text("");
    }

    function setLoading(on) {
      if (on) {
        $loading.removeClass("d-none");
        $submitBtn.prop("disabled", true);
      } else {
        $loading.addClass("d-none");
        $submitBtn.prop("disabled", false);
      }
    }

    function resetForm() {
      $recordId.val("0");
      $content.val("");
      $("#url_attachment").val("");
      $existingAttachment.val("");
      $submitBtn.text("Save Record");
      $cancelEditBtn.hide();
    }

    function collectFilters() {
      return {
        keyword: $.trim($("#url_keyword").val() || ""),
        filter_date: $("#url_filter_date").val() || "",
        filter_user: $("#url_filter_user").val() || "",
        filter_attachment: $("#url_filter_attachment").val() || "",
        page: currentPage,
        page_size: currentPageSize,
        customer_id: customerId,
        customer_column: customerColumn,
        return_url: pathReturn,
      };
    }

    function renderPagingSummary(total, page, totalPages, pageSize) {
      if (!$pagingSummary.length) {
        return;
      }

      if (!total || total < 1) {
        $pagingSummary.text("Showing 0 to 0 of 0 entries");
        return;
      }

      var startNo = (page - 1) * pageSize + 1;
      var endNo = Math.min(total, page * pageSize);
      $pagingSummary.text(
        "Showing " + startNo + " to " + endNo + " of " + total + " entries",
      );
    }

    function buildPageButtons(totalPages, page) {
      var pages = [];
      if (totalPages <= 7) {
        for (var i = 1; i <= totalPages; i++) {
          pages.push(i);
        }
        return pages;
      }

      pages.push(1);
      var start = Math.max(2, page - 1);
      var end = Math.min(totalPages - 1, page + 1);
      if (start > 2) {
        pages.push("...");
      }
      for (var j = start; j <= end; j++) {
        pages.push(j);
      }
      if (end < totalPages - 1) {
        pages.push("...");
      }
      pages.push(totalPages);
      return pages;
    }

    function renderPagination(totalPages, page) {
      if (!$pagination.length) {
        return;
      }

      $pagination.empty();
      totalPages = parseInt(totalPages || 1, 10);
      page = parseInt(page || 1, 10);

      if (totalPages <= 1) {
        $pagination.hide();
        return;
      }
      $pagination.show();

      var $ul = $('<ul class="pagination"></ul>');

      var prevDisabled = page <= 1 ? " disabled" : "";
      $ul.append(
        '<li class="paginate_button page-item previous' +
          prevDisabled +
          '" data-page="' +
          (page - 1) +
          '">' +
          '<a href="javascript:void(0)" aria-controls="url_list_container" data-dt-idx="0" tabindex="0" class="page-link">Previous</a>' +
          "</li>",
      );

      var items = buildPageButtons(totalPages, page);
      for (var i = 0; i < items.length; i++) {
        if (items[i] === "...") {
          $ul.append(
            '<li class="paginate_button page-item disabled"><a href="javascript:void(0)" class="page-link">...</a></li>',
          );
        } else {
          var p = parseInt(items[i], 10);
          var cls =
            p === page
              ? "paginate_button page-item active"
              : "paginate_button page-item";
          $ul.append(
            '<li class="' +
              cls +
              '" data-page="' +
              p +
              '">' +
              '<a href="javascript:void(0)" aria-controls="url_list_container" data-dt-idx="' +
              p +
              '" tabindex="0" class="page-link">' +
              p +
              "</a>" +
              "</li>",
          );
        }
      }

      var nextDisabled = page >= totalPages ? " disabled" : "";
      $ul.append(
        '<li class="paginate_button page-item next' +
          nextDisabled +
          '" data-page="' +
          (page + 1) +
          '">' +
          '<a href="javascript:void(0)" aria-controls="url_list_container" data-dt-idx="' +
          (totalPages + 1) +
          '" tabindex="0" class="page-link">Next</a>' +
          "</li>",
      );

      $pagination.append($ul);
    }

    function loadList() {
      hideAlert();
      setLoading(true);

      var data = collectFilters();
      data.url_action = "list";

      $.ajax({
        url: ajaxUrl,
        method: "POST",
        dataType: "json",
        data: data,
        timeout: 25000,
      })
        .done(function (res) {
          if (!res || !res.ok) {
            showAlert(
              "danger",
              res && res.message ? res.message : "Failed to load records.",
            );
            $list.html("");
            $pagination.empty();
            $pagination.hide();
            $pagingSummary.text("");
            return;
          }

          currentPage = parseInt(res.page || 1, 10) || 1;
          currentPageSize =
            parseInt(res.page_size || currentPageSize, 10) || currentPageSize;
          if ($pageSize.length) {
            $pageSize.val(String(currentPageSize));
          }

          $list.html(res.html || "");
          renderPagingSummary(
            parseInt(res.total || 0, 10),
            currentPage,
            parseInt(res.total_pages || 1, 10),
            currentPageSize,
          );
          renderPagination(parseInt(res.total_pages || 1, 10), currentPage);
          hideAlert();
        })
        .fail(function (xhr) {
          showAlert(
            "danger",
            "Request failed while loading records." +
              (xhr && xhr.status ? " (" + xhr.status + ")" : ""),
          );
          $list.html("");
          $pagination.empty();
          $pagination.hide();
          $pagingSummary.text("");
        })
        .always(function () {
          setLoading(false);
        });
    }

    function saveRecord() {
      hideAlert();

      if (!$.trim($content.val() || "")) {
        showAlert("danger", "Content is required.");
        $content.focus();
        return;
      }

      var formEl = $form.get(0);
      if (!formEl) {
        showAlert("danger", "Form not found.");
        return;
      }

      var actionType = String($recordId.val() || "0") !== "0" ? "E" : "I";
      var formData = new FormData(formEl);
      formData.set("url_action", "save");
      formData.set("customer_id", String(customerId || 0));
      formData.set("customer_column", customerColumn);
      formData.set("return_url", pathReturn);

      setLoading(true);
      $.ajax({
        url: ajaxUrl,
        method: "POST",
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
        timeout: 35000,
      })
        .done(function (res) {
          if (!res || !res.ok) {
            showAlert(
              "danger",
              res && res.message ? res.message : "Failed to save record.",
            );
            return;
          }

          resetForm();
          loadList();

          if (typeof confirmationDialog === "function") {
            confirmationDialog(
              "",
              "",
              confirmationPageName,
              "",
              pathReturn,
              actionType,
            );
          } else {
            showAlert("success", res.message || "Saved successfully.");
          }
        })
        .fail(function (xhr) {
          var extra = "";
          if (xhr && xhr.responseText) {
            extra = " " + String(xhr.responseText).substring(0, 120);
          }
          showAlert("danger", "Request failed while saving record." + extra);
        })
        .always(function () {
          setLoading(false);
        });
    }

    $form.off("submit.url").on("submit.url", function (e) {
      e.preventDefault();
      e.stopPropagation();
      saveRecord();
      return false;
    });

    $submitBtn.off("click.url").on("click.url", function (e) {
      e.preventDefault();
      saveRecord();
      return false;
    });

    $cancelEditBtn.on("click", function () {
      resetForm();
      hideAlert();
    });

    $("#url_apply_filter_btn").on("click", function () {
      currentPage = 1;
      loadList();
    });

    $("#url_reset_filter_btn").on("click", function () {
      $("#url_keyword").val("");
      $("#url_filter_date").val("");
      $("#url_filter_user").val("");
      $("#url_filter_attachment").val("");
      currentPage = 1;
      loadList();
    });

    $pageSize.on("change", function () {
      currentPageSize = parseInt($(this).val() || "10", 10) || 10;
      currentPage = 1;
      loadList();
    });

    $pagination.on("click", ".paginate_button[data-page]", function () {
      if ($(this).hasClass("disabled") || $(this).hasClass("active")) {
        return;
      }
      var targetPage = parseInt($(this).data("page") || currentPage, 10);
      if (!targetPage || targetPage < 1 || targetPage === currentPage) {
        return;
      }
      currentPage = targetPage;
      loadList();
    });

    $("#url_expand_all_btn").on("click", function () {
      $("[id^='url-body-']").show();
    });

    $("#url_collapse_all_btn").on("click", function () {
      $("[id^='url-body-']").hide();
    });

    $list.on("click", ".url-edit-btn", function () {
      var id = String($(this).data("id") || "0");
      var $card = $(this).closest(".card");

      $recordId.val(id);
      $content.val($.trim($card.find(".url-edit-content").val() || ""));
      $existingAttachment.val(
        $.trim($card.find(".url-edit-attachment").val() || ""),
      );
      $submitBtn.text("Update Record");
      $cancelEditBtn.show();

      if ($form.offset()) {
        $("html, body").animate({ scrollTop: $form.offset().top - 100 }, 250);
      }
    });

    $list.on("click", ".url-toggle-btn", function () {
      var target = String($(this).data("target") || "");
      if (target) {
        $("#" + target).toggle();
      }
    });

    $("#url_keyword").on("keyup", function (e) {
      if (e.keyCode === 13) {
        currentPage = 1;
        loadList();
      }
    });

    loadList();
  });
})();
