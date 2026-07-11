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
    var messageShortcutTable = String(cfg.messageShortcutTable || "");

    var $form = $("#url_form");
    var $alert = $("#url_alert");
    var $list = $("#url_list_container");
    var $loading = $("#url_loading");
    var $pageSize = $("#url_page_size");
    var $pageSizeWrap = $("#url_dataTables_length");
    var $pagination = $("#url_pagination");
    var $pagingSummary = $("#url_paging_summary");
    var $recordId = $("#url_record_id");
    var $summary = $("#url_summary");
    var $summarySubmitBtn = $("#url_summary_submit_btn");
    var $messageShortcutId = $("#url_message_shortcut_id");
    var $messageShortcutLabel = $("#url_message_shortcut_label");
    var $content = $("#url_content");
    var $nextFollowUpDate = $("#url_next_follow_up_date");
    var $followUpTimes = $("#url_follow_up_times");
    var $followUpDay = $("#url_follow_up_day");
    var $existingAttachment = $("#url_existing_attachment");
    var $existingAttachments = $("#url_existing_attachments");
    var $submitBtn = $("#url_submit_btn");
    var $cancelEditBtn = $("#url_cancel_edit_btn");
    var $attachmentModal = $("#url_attachment_preview_modal");
    var $attachmentModalClose = $("#url_attachment_modal_close");
    var $attachmentPreviewContent = $("#url_attachment_preview_content");
    var currentSummaryValue = String(cfg.currentSummary || "");
    var messageShortcutOptions = Array.isArray(cfg.messageShortcuts)
      ? cfg.messageShortcuts
      : [];
    var currentAttachmentObjectUrls = [];
    var currentPage = 1;
    var currentPageSize = parseInt($pageSize.val() || "10", 10) || 10;
    var formFieldIds = [
      "url_record_id",
      "url_existing_attachment",
      "url_existing_attachments",
      "url_message_shortcut_id",
      "url_message_shortcut_label",
      "url_summary",
      "url_content",
      "url_next_follow_up_date",
      "url_follow_up_times",
      "url_follow_up_day",
      "url_attachment",
    ];
    var filterFieldIds = [
      "url_keyword",
      "url_filter_date",
      "url_filter_user",
      "url_filter_attachment",
    ];

    if (!$form.length || !$alert.length || !$list.length || !$loading.length) {
      return;
    }

    function clearStoredFieldValues(fieldIds) {
      if (
        !fieldIds ||
        !fieldIds.length ||
        typeof window.localStorage === "undefined"
      ) {
        return;
      }

      try {
        for (var i = 0; i < fieldIds.length; i++) {
          if (fieldIds[i]) {
            window.localStorage.removeItem(fieldIds[i]);
          }
        }
      } catch (err) {
        // Ignore storage access issues and continue with in-memory reset only.
      }
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

    function fallbackCopyText(text) {
      var $temp = $('<textarea readonly></textarea>');
      var copied = false;

      $temp.css({
        position: "fixed",
        top: "-9999px",
        left: "-9999px",
        opacity: "0",
      });
      $temp.val(String(text || ""));
      $("body").append($temp);
      $temp.trigger("focus").trigger("select");

      try {
        copied = document.execCommand("copy");
      } catch (err) {
        copied = false;
      }

      $temp.remove();
      return copied;
    }

    function fallbackCopyHtml(html, text) {
      var $temp = $('<div contenteditable="true" aria-hidden="true"></div>');
      var copied = false;
      var selection = window.getSelection ? window.getSelection() : null;
      var range = document.createRange ? document.createRange() : null;
      var fallbackHtml = String(html || "");

      if (!fallbackHtml) {
        fallbackHtml = $("<div>")
          .text(String(text || ""))
          .html()
          .replace(/\r\n|\r|\n/g, "<br>");
      }

      if (!fallbackHtml) {
        return fallbackCopyText(text);
      }

      $temp.css({
        position: "fixed",
        top: "-9999px",
        left: "-9999px",
        opacity: "0",
        whiteSpace: "normal",
      });
      $temp.html(fallbackHtml);
      $("body").append($temp);

      try {
        if (selection && range) {
          range.selectNodeContents($temp[0]);
          selection.removeAllRanges();
          selection.addRange(range);
        }
        copied = document.execCommand("copy");
      } catch (err) {
        copied = false;
      }

      if (selection) {
        selection.removeAllRanges();
      }

      $temp.remove();

      if (!copied) {
        return fallbackCopyText(text);
      }

      return true;
    }

    function copyUserRecordLogToClipboard(html, text) {
      var copyHtml = String(html || "");
      var copyText = String(text || "");

      return new Promise(function (resolve, reject) {
        if (
          navigator.clipboard &&
          typeof navigator.clipboard.write === "function" &&
          typeof window.ClipboardItem === "function" &&
          (copyHtml || copyText)
        ) {
          var clipboardItems = {};

          if (copyHtml) {
            clipboardItems["text/html"] = new Blob([copyHtml], {
              type: "text/html",
            });
          }

          if (copyText) {
            clipboardItems["text/plain"] = new Blob([copyText], {
              type: "text/plain",
            });
          }

          navigator.clipboard
            .write([new window.ClipboardItem(clipboardItems)])
            .then(resolve)
            .catch(function () {
              if (fallbackCopyHtml(copyHtml, copyText)) {
                resolve();
                return;
              }
              reject(new Error("Clipboard write failed."));
            });
          return;
        }

        if (
          navigator.clipboard &&
          typeof navigator.clipboard.writeText === "function" &&
          !copyHtml &&
          copyText
        ) {
          navigator.clipboard
            .writeText(copyText)
            .then(resolve)
            .catch(function () {
              if (fallbackCopyText(copyText)) {
                resolve();
                return;
              }
              reject(new Error("Clipboard write failed."));
            });
          return;
        }

        if (fallbackCopyHtml(copyHtml, copyText)) {
          resolve();
          return;
        }

        reject(new Error("Clipboard write failed."));
      });
    }

    function setCopyButtonState($button, state) {
      var originalHtml = String($button.data("original-html") || "");
      var originalTitle = String($button.data("original-title") || "");
      var resetTimer = parseInt($button.data("reset-timer") || "0", 10) || 0;

      if (!originalHtml) {
        originalHtml = String($button.html() || "Copy");
        $button.data("original-html", originalHtml);
      }
      if (!originalTitle) {
        originalTitle = String($button.attr("title") || "Copy User Log");
        $button.data("original-title", originalTitle);
      }
      if (resetTimer) {
        window.clearTimeout(resetTimer);
      }

      $button.removeClass("btn-secondary btn-success btn-danger");

      if (state === "success") {
        $button
          .addClass("btn-success")
          .html("Copied")
          .attr("title", "Copied");
      } else if (state === "error") {
        $button
          .addClass("btn-danger")
          .html("Copy Failed")
          .attr("title", "Copy Failed");
      } else {
        $button
          .addClass("btn-secondary")
          .html(originalHtml)
          .attr("title", originalTitle);
      }

      if (state === "success" || state === "error") {
        $button.data(
          "reset-timer",
          window.setTimeout(function () {
            setCopyButtonState($button, "default");
          }, 1400),
        );
      }
    }

    function showSuccessPopup(text) {
      var message = String(text || "Record added successfully.");

      if (window.bootstrap && typeof window.bootstrap.Modal === "function") {
        var $modal = $("#url_success_popup_modal");
        if (!$modal.length) {
          $("body").append(
            '<div class="modal fade" id="url_success_popup_modal" tabindex="-1" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="url_success_popup_title">' +
              '<div class="modal-dialog modal-dialog-centered">' +
              '<div class="modal-content">' +
              '<div class="modal-header">' +
              '<h5 class="modal-title" id="url_success_popup_title">Success</h5>' +
              '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
              "</div>" +
              '<div class="modal-body" id="url_success_popup_message"></div>' +
              '<div class="modal-footer">' +
              '<button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>' +
              "</div>" +
              "</div>" +
              "</div>" +
              "</div>",
          );
          $modal = $("#url_success_popup_modal");
        }

        $("#url_success_popup_message").text(message);
        window.bootstrap.Modal.getOrCreateInstance($modal.get(0)).show();
        return;
      }

      showNotification(message, "success");
    }

    function setLoading(on) {
      if (on) {
        $loading.removeClass("d-none");
        $submitBtn.prop("disabled", true);
        $summarySubmitBtn.prop("disabled", true);
      } else {
        $loading.addClass("d-none");
        $submitBtn.prop("disabled", false);
        $summarySubmitBtn.prop("disabled", false);
      }
    }

    function closeAttachmentModal() {
      if (!$attachmentModal.length) {
        return;
      }
      $attachmentPreviewContent.empty();
      $attachmentModal.addClass("d-none");
      $("body").removeClass("url-modal-open");
    }

    function escHtml(text) {
      return $("<div>").text(String(text || "")).html();
    }

    function buildMessageShortcutMaps() {
      var byId = {};
      var byLabel = {};

      messageShortcutOptions.forEach(function (option) {
        var id = parseInt(option && option.id ? option.id : "0", 10) || 0;
        var label = $.trim(String(option && option.label ? option.label : ""));
        if (!id || !label) {
          return;
        }

        byId[id] = option;
        byLabel[label.toLowerCase()] = option;
      });

      return {
        byId: byId,
        byLabel: byLabel,
      };
    }

    var messageShortcutMaps = buildMessageShortcutMaps();

    function getFileExtension(filePath) {
      var cleanPath = String(filePath || "").split("?")[0].split("#")[0];
      var idx = cleanPath.lastIndexOf(".");
      if (idx < 0) {
        return "";
      }
      return cleanPath.substring(idx + 1).toLowerCase();
    }

    function isImageExtension(ext) {
      return {
        png: true,
        jpg: true,
        jpeg: true,
        webp: true,
      }[String(ext || "").toLowerCase()] === true;
    }

    function isPdfExtension(ext) {
      return String(ext || "").toLowerCase() === "pdf";
    }

    function buildAttachmentUrl(attachmentPath) {
      var cleanPath = $.trim(String(attachmentPath || ""));
      if (!cleanPath) {
        return "";
      }
      if (/^https?:\/\//i.test(cleanPath)) {
        return cleanPath;
      }
      cleanPath = cleanPath.replace(/\\/g, "/").replace(/^\/+/, "");
      if (cleanPath.indexOf("/") === -1) {
        var uploadWebDir = String(cfg.uploadWebDir || "")
          .replace(/^\/+/, "")
          .replace(/\/+$/, "");
        if (uploadWebDir) {
          cleanPath = uploadWebDir + "/" + cleanPath;
        }
      }
      return (
        String(cfg.siteUrl || "").replace(/\/$/, "") +
        "/" +
        cleanPath
      );
    }

    function buildAttachmentPreviewHtml(fileUrl, filePath) {
      var safeUrl = escHtml(fileUrl);
      var ext = getFileExtension(filePath || fileUrl);

      if (isImageExtension(ext)) {
        return '<img src="' + safeUrl + '" alt="Attachment preview">';
      }

      if (isPdfExtension(ext)) {
        return '<iframe src="' + safeUrl + '" title="Attachment PDF preview"></iframe>';
      }

      return (
        '<div style="width:100%;height:100%;display:flex;flex-direction:column;">' +
        '<iframe src="' +
        safeUrl +
        '" title="Attachment preview"></iframe>' +
        '<div class="small text-muted text-center mt-2">Preview depends on browser support for this file type.</div>' +
        "</div>"
      );
    }

    function openAttachmentModal(fileUrl, filePath) {
      if (!$attachmentModal.length || !fileUrl) {
        return;
      }
      $attachmentPreviewContent.html(buildAttachmentPreviewHtml(fileUrl, filePath));
      $attachmentModal.removeClass("d-none");
      $("body").addClass("url-modal-open");
    }

    function clearAttachmentObjectUrls() {
      if (!currentAttachmentObjectUrls.length) {
        return;
      }

      currentAttachmentObjectUrls.forEach(function (objectUrl) {
        if (objectUrl) {
          URL.revokeObjectURL(objectUrl);
        }
      });
      currentAttachmentObjectUrls = [];
    }

    function createAttachmentPreviewTrigger(fileUrl, filePath) {
      var fileName = String(filePath || "").split(/[\\/]/).pop() || "Attachment";
      var ext = getFileExtension(filePath || fileUrl);
      var trigger = document.createElement("button");
      trigger.type = "button";
      trigger.className = "url-attachment-thumb url-edit-preview-open-btn";
      trigger.setAttribute("data-url", fileUrl);
      trigger.setAttribute("data-file", filePath || fileName);
      trigger.setAttribute("title", fileName);
      trigger.setAttribute("aria-label", "Preview attachment " + fileName);

      if (isImageExtension(ext)) {
        var img = document.createElement("img");
        img.src = fileUrl;
        img.alt = fileName;
        trigger.appendChild(img);
        return trigger;
      }

      trigger.classList.add("url-attachment-thumb-file");

      var extTag = document.createElement("span");
      extTag.className = "url-attachment-file-ext";
      extTag.textContent = ext ? ext.toUpperCase() : "FILE";

      var nameTag = document.createElement("span");
      nameTag.className = "url-attachment-file-name";
      nameTag.textContent = fileName;

      trigger.appendChild(extTag);
      trigger.appendChild(nameTag);
      return trigger;
    }

    function getEditor() {
      if (!window.tinymce || typeof window.tinymce.get !== "function") {
        return null;
      }
      return window.tinymce.get("url_content");
    }

    function initEditor() {
      if (!window.tinymce || typeof window.tinymce.init !== "function") {
        return;
      }

      var existingEditor = getEditor();
      if (existingEditor) {
        return;
      }

      window.tinymce.init({
        selector: "#url_content",
        base_url: cfg.siteUrl ? cfg.siteUrl + "/header/tinymce" : undefined,
        license_key: "gpl",
        menubar: false,
        branding: false,
        promotion: false,
        statusbar: false,
        height: 280,
        resize: true,
        plugins: "lists emoticons autolink paste",
        toolbar:
          "undo redo | blocks | bold italic underline | bullist numlist | emoticons | removeformat",
        browser_spellcheck: true,
        contextmenu: false,
        block_formats:
          "Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4",
        forced_root_block: "p",
        valid_elements:
          "p,br,strong/b,em/i,u,ul,ol,li,blockquote,span,div,h1,h2,h3,h4",
        content_style:
          "body { font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; } p { margin: 0; padding-bottom: 1em; } p + p { padding-top: 0.25em; } h1, h2, h3, h4 { line-height: 1.35; margin: 0; padding-top: 1.25em; padding-bottom: 0.75em; } body > h1:first-child, body > h2:first-child, body > h3:first-child, body > h4:first-child { padding-top: 0; }",
        setup: function (editor) {
          editor.on("init", function () {
            var editorContainer = editor.getContainer();
            if (!editorContainer || editorContainer.querySelector(".url-editor-resize-handle")) {
              return;
            }

            var resizeHandle = document.createElement("span");
            resizeHandle.className = "url-editor-resize-handle";
            resizeHandle.setAttribute("aria-hidden", "true");
            resizeHandle.textContent = "↘";
            editorContainer.appendChild(resizeHandle);
          });

          editor.on("init change keyup undo redo setcontent", function () {
            editor.save();
          });
        },
      });
    }

    function normalizeUserRecordLogEditorContent(content) {
      var html = String(content || "");

      if (html === "") {
        return "";
      }

      if (/<\s*(p|br|div|span|strong|b|em|i|u|ul|ol|li|blockquote|h[1-4])\b/i.test(html)) {
        return html;
      }

      return escHtml(html).replace(/\r\n|\r|\n/g, "<br>");
    }

    function setEditorContent(content) {
      var editor = getEditor();
      var html = normalizeUserRecordLogEditorContent(content);
      if (editor) {
        editor.setContent(html);
        editor.save();
        return;
      }
      $content.val(html);
    }

    function syncEditorToTextarea() {
      var editor = getEditor();
      if (editor) {
        editor.save();
      }
    }

    function getEditorPlainText() {
      var editor = getEditor();
      if (editor) {
        return $.trim(editor.getContent({ format: "text" }) || "");
      }
      return $.trim($content.val() || "");
    }

    function focusEditor() {
      var editor = getEditor();
      if (editor) {
        editor.focus();
        return;
      }
      $content.trigger("focus");
    }

    function parseAttachmentList(rawValue) {
      if (!rawValue) {
        return [];
      }

      try {
        var parsed = JSON.parse(String(rawValue));
        if (!Array.isArray(parsed)) {
          return [];
        }

        var clean = [];
        var seen = {};
        for (var i = 0; i < parsed.length; i++) {
          var item = $.trim(String(parsed[i] || ""));
          if (item && !seen[item]) {
            clean.push(item);
            seen[item] = true;
          }
        }
        return clean;
      } catch (err) {
        return [];
      }
    }

    function setExistingAttachments(attachments) {
      var list = Array.isArray(attachments) ? attachments : [];
      $existingAttachments.val(JSON.stringify(list));
      $existingAttachment.val(list.length ? String(list[0]) : "");
    }

    function removeExistingAttachmentByIndex(index) {
      var list = parseAttachmentList($existingAttachments.val());
      if (index < 0 || index >= list.length) {
        return;
      }

      list.splice(index, 1);
      setExistingAttachments(list);
      renderExistingAttachmentLinks(list);
      refreshAttachmentPreview(list);
    }

    function renderExistingAttachmentLinks(attachments) {
      var $wrap = $("#url_existing_attachment_links");
      if (!$wrap.length) {
        return;
      }

      var list = Array.isArray(attachments) ? attachments : [];
      if (!list.length) {
        $wrap.empty();
        return;
      }

      var html = '<div class="url-existing-attachment-list">';
      for (var i = 0; i < list.length; i++) {
        var href = buildAttachmentUrl(list[i]);
        if (!href) {
          continue;
        }
        html +=
          '<div class="url-existing-attachment-item"><a class="url-existing-attachment-link" href="' +
          escHtml(href) +
          '" target="_blank">Open attachment ' +
          (i + 1) +
          '</a><button type="button" class="url-remove-existing-attachment-btn" data-index="' +
          i +
          '" title="Remove attachment"><i class="fa-regular fa-trash-can"></i></button></div>';
      }
      html += "</div>";
      $wrap.html(html);
    }

    function refreshAttachmentPreview(existingAttachments) {
      var listWrap = document.getElementById("url_attachment_img_list");
      var placeholder = document.getElementById("url_attachment_placeholder");
      if (!listWrap) {
        return;
      }

      clearAttachmentObjectUrls();
      listWrap.innerHTML = "";
      var hasPreview = false;
      var existingList = Array.isArray(existingAttachments)
        ? existingAttachments
        : parseAttachmentList($existingAttachments.val());

      existingList.forEach(function (attachmentPath, index) {
        var ext = getFileExtension(attachmentPath);
        var attachmentUrl = buildAttachmentUrl(attachmentPath);
        if (!attachmentUrl || (!isImageExtension(ext) && !isPdfExtension(ext))) {
          return;
        }

        hasPreview = true;
        var existingWrap = document.createElement("div");
        existingWrap.className = "url-edit-preview-item";
        var previewTrigger = createAttachmentPreviewTrigger(
          attachmentUrl,
          attachmentPath,
        );

        var removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.className = "url-edit-preview-remove-btn";
        removeBtn.setAttribute("data-index", String(index));
        removeBtn.setAttribute("title", "Remove attachment");
        removeBtn.innerHTML = "&times;";

        existingWrap.appendChild(previewTrigger);
        existingWrap.appendChild(removeBtn);
        listWrap.appendChild(existingWrap);
      });

      document
        .querySelectorAll(".user-record-log-attachment-input")
        .forEach(function (input) {
          if (!input.files || input.files.length === 0) {
            return;
          }

          Array.prototype.forEach.call(input.files, function (file) {
            if (!file) {
              return;
            }

            var ext = getFileExtension(file.name || "");
            var fileType = String(file.type || "");
            var isImage = fileType.indexOf("image/") === 0 || isImageExtension(ext);
            var isPdf = fileType === "application/pdf" || isPdfExtension(ext);
            if (!isImage && !isPdf) {
              return;
            }

            hasPreview = true;
            var objectUrl = URL.createObjectURL(file);
            currentAttachmentObjectUrls.push(objectUrl);

            var uploadWrap = document.createElement("div");
            uploadWrap.className = "url-edit-preview-item";
            uploadWrap.appendChild(
              createAttachmentPreviewTrigger(objectUrl, file.name || ""),
            );
            listWrap.appendChild(uploadWrap);
          });
        });

      if (placeholder) {
        placeholder.style.display = hasPreview ? "none" : "inline";
      }
    }

    function resetAttachmentInputs() {
      var wrap = document.getElementById("url_attachment_inputs");
      if (!wrap) {
        return;
      }

      wrap.innerHTML =
        '<div class="mb-2 si-attachment-input-row">' +
        '<input class="form-control user-record-log-attachment-input" type="file" name="attachment[]" id="url_attachment" accept=".png,.jpg,.jpeg,.webp,.pdf,application/pdf">' +
        '<button class="mt-1 add-user-record-log-attachment-btn" id="action_menu_btn" type="button" title="Add another attachment"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>' +
        "</div>";
    }

    function resetForm() {
      $recordId.val("0");
      $messageShortcutId.val("");
      $messageShortcutLabel.val("");
      $summary.val(currentSummaryValue);
      setEditorContent("");
      $nextFollowUpDate.val("");
      $followUpTimes.val("");
      $followUpDay.val("");
      resetAttachmentInputs();
      setExistingAttachments([]);
      $submitBtn.text("Save User Log");
      $cancelEditBtn.hide();
      clearStoredFieldValues(formFieldIds);
      renderExistingAttachmentLinks([]);
      refreshAttachmentPreview([]);
    }

    function resetFilters() {
      $("#url_keyword").val("");
      $("#url_filter_date").val("");
      $("#url_filter_user").val("");
      $("#url_filter_attachment").val("");
      clearStoredFieldValues(filterFieldIds);
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

      var startNo = 1;
      var endNo = total;

      if (pageSize !== -1) {
        startNo = (page - 1) * pageSize + 1;
        endNo = Math.min(total, page * pageSize);
      }

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

          var totalRecords = parseInt(res.total || 0, 10) || 0;
          if ($pageSizeWrap.length) {
            $pageSizeWrap.toggle(totalRecords > 10);
          }

          $list.html(res.html || "");
          renderPagingSummary(
            totalRecords,
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

    function saveRecord(options) {
      var saveMode =
        options && options.saveMode ? String(options.saveMode) : "log";
      hideAlert();
      syncEditorToTextarea();

      if (saveMode === "log" && !getEditorPlainText()) {
        showAlert("danger", "Content is required.");
        focusEditor();
        return;
      }

      var formEl = $form.get(0);
      if (!formEl) {
        showAlert("danger", "Form not found.");
        return;
      }

      var formData = new FormData(formEl);
      formData.set("url_action", saveMode === "summary" ? "save_summary" : "save");
      formData.set("customer_id", String(customerId || 0));
      formData.set("customer_column", customerColumn);
      formData.set("return_url", pathReturn);
      formData.set("summary", String($summary.val() || ""));
      formData.set("message_shortcut_id", String($messageShortcutId.val() || ""));
      formData.set(
        "existing_attachments",
        String($existingAttachments.val() || "[]"),
      );
      formData.set(
        "existing_attachment",
        String($existingAttachment.val() || ""),
      );

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

          var successMessage =
            res && res.message ? res.message : "Record saved successfully.";
          currentSummaryValue = $.trim(String($summary.val() || ""));
          if (saveMode === "log") {
            resetForm();
          } else {
            $summary.val(currentSummaryValue);
          }
          loadList();

          hideAlert();
          showSuccessPopup(successMessage);
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
      saveRecord({ saveMode: "log" });
      return false;
    });

    $submitBtn.off("click.url").on("click.url", function (e) {
      e.preventDefault();
      saveRecord({ saveMode: "log" });
      return false;
    });

    $summarySubmitBtn.off("click.url").on("click.url", function (e) {
      e.preventDefault();
      saveRecord({ saveMode: "summary" });
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
      resetFilters();
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

    $(document).on("change", ".user-record-log-attachment-input", function () {
      refreshAttachmentPreview();
    });

    $(document).on(
      "click",
      ".add-user-record-log-attachment-btn, .remove-user-record-log-attachment-btn",
      function (e) {
        var target = e.target;
        if (!target) {
          return;
        }

        var addBtn = target.closest(".add-user-record-log-attachment-btn");
        var removeBtn = target.closest(
          ".remove-user-record-log-attachment-btn",
        );
        var wrap = document.getElementById("url_attachment_inputs");
        if (!wrap) {
          return;
        }

        if (addBtn) {
          var row = document.createElement("div");
          row.className = "mb-2 si-attachment-input-row";
          row.innerHTML =
            '<input class="form-control user-record-log-attachment-input" type="file" name="attachment[]" accept=".png,.jpg,.jpeg,.webp,.pdf,application/pdf">' +
            '<button class="mt-1 remove-user-record-log-attachment-btn" id="action_menu_btn" type="button" title="Remove attachment row"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000"></i></button>';
          wrap.appendChild(row);
          return;
        }

        if (removeBtn) {
          var rows = wrap.querySelectorAll(".si-attachment-input-row");
          if (rows.length <= 1) {
            var onlyInput =
              rows[0] && rows[0].querySelector(".user-record-log-attachment-input");
            if (onlyInput) {
              onlyInput.value = "";
            }
          } else {
            var removeRow = removeBtn.closest(".si-attachment-input-row");
            if (removeRow) {
              removeRow.remove();
            }
          }
          refreshAttachmentPreview();
        }
      },
    );

    $(document).on("click", ".url-remove-existing-attachment-btn", function () {
      var index = parseInt($(this).data("index"), 10);
      if (isNaN(index)) {
        return;
      }
      removeExistingAttachmentByIndex(index);
    });

    $(document).on("click", ".url-edit-preview-remove-btn", function (e) {
      e.preventDefault();
      e.stopPropagation();
      var index = parseInt($(this).data("index"), 10);
      if (isNaN(index)) {
        return;
      }
      removeExistingAttachmentByIndex(index);
    });

    $(document).on("click", ".url-edit-preview-open-btn", function () {
      var fileUrl = String($(this).data("url") || "");
      var filePath = String($(this).data("file") || "");
      if (!fileUrl) {
        return;
      }
      openAttachmentModal(fileUrl, filePath);
    });

    $list.on("click", ".url-edit-btn", function () {
      var id = String($(this).data("id") || "0");
      var $card = $(this).closest(".card");
      var messageShortcutIdValue = parseInt(
        $card.find(".url-edit-message-shortcut-id").val() || "0",
        10,
      ) || 0;
      var contentValue = String($card.find(".url-edit-content").val() || "");
      var attachmentsValue = String(
        $card.find(".url-edit-attachments").val() || "[]",
      );
      var nextFollowUpDateValue = String(
        $card.find(".url-edit-next-follow-up-date").val() || "",
      );
      var followUpTimesValue = String(
        $card.find(".url-edit-follow-up-times").val() || "",
      );
      var followUpDayValue = String(
        $card.find(".url-edit-follow-up-day").val() || "",
      );
      var attachments = parseAttachmentList(attachmentsValue);

      $recordId.val(id);
      $summary.val(currentSummaryValue);
      if (messageShortcutIdValue && messageShortcutMaps.byId[messageShortcutIdValue]) {
        $messageShortcutId.val(String(messageShortcutIdValue));
        $messageShortcutLabel.val(
          String(messageShortcutMaps.byId[messageShortcutIdValue].label || ""),
        );
      } else {
        $messageShortcutId.val("");
        $messageShortcutLabel.val("");
      }
      setEditorContent(contentValue);
      $nextFollowUpDate.val(nextFollowUpDateValue);
      $followUpTimes.val(followUpTimesValue);
      $followUpDay.val(followUpDayValue);
      resetAttachmentInputs();
      setExistingAttachments(attachments);
      $submitBtn.text("Save User Log");
      $cancelEditBtn.show();
      renderExistingAttachmentLinks(attachments);
      refreshAttachmentPreview(attachments);

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

    $list.on("click", ".url-copy-btn", function () {
      var $button = $(this);
      var $card = $button.closest(".card");
      var copyHtml = String($card.find(".url-copy-html").val() || "");
      var copyText = String($card.find(".url-copy-text").val() || "");

      if (!copyHtml && !copyText) {
        setCopyButtonState($button, "error");
        showAlert("danger", "Unable to copy this user log.");
        return;
      }

      copyUserRecordLogToClipboard(copyHtml, copyText)
        .then(function () {
          hideAlert();
          setCopyButtonState($button, "success");
        })
        .catch(function () {
          setCopyButtonState($button, "error");
          showAlert(
            "danger",
            "Clipboard access failed. Please allow clipboard permission and try again.",
          );
        });
    });

    $("#url_keyword").on("keyup", function (e) {
      if (e.keyCode === 13) {
        currentPage = 1;
        loadList();
      }
    });

    $list.on("click", ".url-view-attachment-btn", function () {
      var fileUrl = String($(this).data("url") || "");
      if (!fileUrl) {
        return;
      }
      openAttachmentModal(fileUrl);
    });

    $attachmentModalClose.on("click", function () {
      closeAttachmentModal();
    });

    $attachmentModal.on("click", function (e) {
      if (e.target === this) {
        closeAttachmentModal();
      }
    });

    function applyMessageShortcutSelection(option, shouldFillContent) {
      var shortcutId =
        parseInt(option && option.id ? option.id : "0", 10) || 0;
      if (!shortcutId) {
        $messageShortcutId.val("");
        return;
      }

      $messageShortcutId.val(String(shortcutId));
      $messageShortcutLabel.val(String(option.label || ""));

      if (shouldFillContent) {
        setEditorContent(String(option.message_html || ""));
      }
    }

    function triggerMessageShortcutAutocomplete() {
      var typedValue = $.trim(String($messageShortcutLabel.val() || ""));
      if (typeof searchInput !== "function" || !messageShortcutTable) {
        return;
      }

      searchInput(
        {
          search: typedValue,
          searchType: "shortcuts_tag",
          elementID: "url_message_shortcut_label",
          hiddenElementID: "url_message_shortcut_id",
          dbTable: messageShortcutTable,
        },
        String(cfg.siteUrl || ""),
      );
    }

    $messageShortcutLabel.on("keyup", function () {
      $messageShortcutId.val("");
      triggerMessageShortcutAutocomplete();
    });

    $messageShortcutId.on("input change", function () {
      var shortcutId = parseInt(String($(this).val() || "0"), 10) || 0;
      if (!shortcutId || !messageShortcutMaps.byId[shortcutId]) {
        return;
      }

      applyMessageShortcutSelection(messageShortcutMaps.byId[shortcutId], true);
    });

    $messageShortcutLabel.on("change blur", function () {
      var typedValue = $.trim(String($(this).val() || ""));
      if (!typedValue) {
        $messageShortcutId.val("");
        return;
      }

      var option = messageShortcutMaps.byLabel[typedValue.toLowerCase()];
      if (!option) {
        $messageShortcutId.val("");
        return;
      }

      applyMessageShortcutSelection(option, true);
    });

    $(document).on("keydown", function (e) {
      if (e.key === "Escape" && !$attachmentModal.hasClass("d-none")) {
        closeAttachmentModal();
      }
    });

    $(window).on("beforeunload", function () {
      clearAttachmentObjectUrls();
    });

    clearStoredFieldValues(formFieldIds.concat(filterFieldIds));
    initEditor();
    resetForm();
    resetFilters();
    loadList();
  });
})();
