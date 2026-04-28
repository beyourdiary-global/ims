"use strict";

(function () {
  if (typeof window.jQuery === "undefined") {
    return;
  }

  var $ = window.jQuery;
  var COMMENT_EDITOR_ID = "taskItemCommentEditor";
  var DESCRIPTION_EDITOR_ID = "taskItemDetailDescriptionInput";
  var REPLY_EDITOR_PREFIX = "taskItemReplyEditor_";
  var REPLY_EDIT_EDITOR_PREFIX = "taskItemReplyEditEditor_";
  var EDIT_EDITOR_PREFIX = "taskItemEditEditor_";
  var MENTION_AUTOCOMPLETE_NAME = "taskMentions";
  var DRAFT_COOKIE_PREFIX = "task_board_draft_v1_";
  var DRAFT_COOKIE_DAYS = 7;

  var commentEditorInitPromise = null;
  var commentSaving = false;
  var commentLoadToken = 0;
  var openReplyCommentId = 0;
  var openEditCommentId = 0;
  var openEditReplyId = 0;
  var openReplyEntryRef = null;
  var openEditEntryRef = null;
  var openEditReplyEntryRef = null;
  var replyEditorInitMap = {};
  var editEditorInitMap = {};
  var replyEditEditorInitMap = {};
  var commentDraftTimer = 0;
  var replyDraftTimerByCommentId = {};
  var editCommentDraftTimerByCommentId = {};
  var editReplyDraftTimerByReplyId = {};
  var descriptionDraftTimer = 0;
  var draftItemContextId = 0;
  var descriptionDraftClearedByUser = false; // set when user explicitly saves or cancels
  var capturedInitialDescription = ""; // captured at modal open, before task_board.js clears it
  var simpleLinkDialogState = {
    editor: null,
    defaultText: "",
    restoreFocusTrap: null,
  };

  function normalizeListType(listType) {
    var value = String(listType || "")
      .trim()
      .toLowerCase();
    if (value === "number" || value === "ordered") {
      return "number";
    }
    if (value === "task" || value === "checklist") {
      return "task";
    }
    return "bullet";
  }

  function listTypeIconName(listType) {
    var value = normalizeListType(listType);
    if (value === "number") {
      return "ordered-list";
    }
    if (value === "task") {
      return "checklist";
    }
    return "unordered-list";
  }

  function detectEditorListType(editor) {
    if (
      !editor ||
      !editor.selection ||
      typeof editor.selection.getNode !== "function"
    ) {
      return "";
    }

    var node = editor.selection.getNode();
    if (!node) {
      return "";
    }

    var $list = $(node).closest("ul,ol");
    if (!$list.length) {
      return "";
    }

    if ($list.is("ol")) {
      return "number";
    }

    if (
      $list.hasClass("tox-checklist") ||
      $list.hasClass("task-editor-checklist") ||
      $list.find('input.task-editor-checkbox,input[type="checkbox"]').length > 0
    ) {
      return "task";
    }

    return "bullet";
  }

  function normalizeCurrentListToStandard(editor) {
    if (!editor || !editor.selection) {
      return;
    }

    var node = editor.selection.getNode();
    if (!node) {
      return;
    }

    var $list = $(node).closest("ul,ol");
    if (!$list.length) {
      return;
    }

    $list.removeClass("task-editor-checklist tox-checklist");
    $list.find("li").each(function () {
      var $li = $(this);
      $li.children("input.task-editor-checkbox").remove();
      $li.find("input.task-editor-checkbox").remove();
      $li.find('input[type="checkbox"][data-task-editor="1"]').remove();
      $li.find("label.task-editor-checkbox-wrap").each(function () {
        $(this).replaceWith($(this).contents());
      });
    });
  }

  function convertCurrentListToTaskChecklist(editor) {
    if (!editor || !editor.selection) {
      return;
    }

    editor.undoManager.transact(function () {
      var node = editor.selection.getNode();
      if (!node) {
        return;
      }

      var $list = $(node).closest("ul,ol");
      if (!$list.length) {
        editor.execCommand("InsertUnorderedList");
        node = editor.selection.getNode();
        $list = $(node).closest("ul,ol");
      }

      if (!$list.length) {
        return;
      }

      if ($list.is("ol")) {
        var $newList = $("<ul></ul>");
        $newList.append($list.children("li"));
        $list.replaceWith($newList);
        $list = $newList;
      }

      $list.addClass("task-editor-checklist");
      $list.find("li").each(function () {
        var $li = $(this);
        var hasCheckbox =
          $li.children("input.task-editor-checkbox").length > 0 ||
          $li.find('input[type="checkbox"][data-task-editor="1"]').length > 0;
        if (hasCheckbox) {
          return;
        }

        var $checkbox = $(
          '<input type="checkbox" class="task-editor-checkbox" data-task-editor="1" contenteditable="false" />',
        );
        $li.prepend($checkbox).prepend(" ");
      });
    });
  }

  function applyEditorListType(editor, listType) {
    if (!editor) {
      return;
    }

    var value = normalizeListType(listType);
    editor.taskListTypeState = value;

    if (value === "number") {
      normalizeCurrentListToStandard(editor);
      editor.execCommand("InsertOrderedList");
      return;
    }

    if (value === "task") {
      convertCurrentListToTaskChecklist(editor);
      return;
    }

    normalizeCurrentListToStandard(editor);
    editor.execCommand("InsertUnorderedList");
  }

  function findCommentEntry(commentId, triggerEl) {
    var id = Number(commentId || 0);
    if (id <= 0) {
      return $();
    }

    var $trigger = $(triggerEl || null);
    if ($trigger.length) {
      var $fromTrigger = $trigger.closest(".task-item-comment-entry");
      if ($fromTrigger.length) {
        return $fromTrigger;
      }
    }

    var selector = '.task-item-comment-entry[data-comment-id="' + id + '"]';
    var $activeEntry = $(
      ".task-item-activity-tab-panel.is-active " + selector,
    ).first();
    if ($activeEntry.length) {
      return $activeEntry;
    }

    return $(selector + ":visible").first();
  }

  function findReplyEntry(replyId, triggerEl) {
    var id = Number(replyId || 0);
    if (id <= 0) {
      return $();
    }

    var $trigger = $(triggerEl || null);
    if ($trigger.length) {
      var $fromTrigger = $trigger.closest(".task-item-comment-reply-entry");
      if ($fromTrigger.length) {
        return $fromTrigger;
      }
    }

    var selector = '.task-item-comment-reply-entry[data-reply-id="' + id + '"]';
    var $activeEntry = $(
      ".task-item-activity-tab-panel.is-active " + selector,
    ).first();
    if ($activeEntry.length) {
      return $activeEntry;
    }

    return $(selector + ":visible").first();
  }

  function getAssigneeOptions() {
    if (window.state && Array.isArray(window.state.assignees)) {
      return window.state.assignees;
    }
    if (typeof state !== "undefined" && Array.isArray(state.assignees)) {
      return state.assignees;
    }
    return [];
  }

  function buildMentionSuggestions(pattern, maxResults) {
    var keyword = String(pattern || "")
      .trim()
      .toLowerCase();
    var limit = Math.max(1, Number(maxResults || 8));
    var source = getAssigneeOptions();
    var result = [];
    var seen = {};

    for (var i = 0; i < source.length; i++) {
      var row = source[i] || {};
      var displayName = String(row.name || row.username || "").trim();
      if (!displayName) {
        continue;
      }

      var key = displayName.toLowerCase();
      if (seen[key]) {
        continue;
      }

      if (keyword && key.indexOf(keyword) === -1) {
        continue;
      }

      seen[key] = true;
      result.push({
        type: "autocompleteitem",
        value: "@" + displayName,
        text: displayName,
      });

      if (result.length >= limit) {
        break;
      }
    }

    return result;
  }

  function registerMentionAutocompleter(editor) {
    if (!editor || !editor.ui || !editor.ui.registry) {
      return;
    }

    try {
      editor.ui.registry.addAutocompleter(MENTION_AUTOCOMPLETE_NAME, {
        trigger: "@",
        minChars: 0,
        columns: 1,
        fetch: function (pattern, maxResults) {
          return Promise.resolve(buildMentionSuggestions(pattern, maxResults));
        },
        onAction: function (autocompleteApi, rng, value) {
          editor.selection.setRng(rng);
          editor.insertContent(String(value || "") + "&nbsp;");
          autocompleteApi.hide();
        },
      });
    } catch (err) {
      // Ignore duplicate registration/runtime autocompleter issues.
    }
  }

  function escAttr(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function getCurrentBoardUserId() {
    if (typeof currentUserId !== "undefined") {
      return Number(currentUserId || 0);
    }
    if (window.taskBoardConfig && window.taskBoardConfig.currentUserId) {
      return Number(window.taskBoardConfig.currentUserId || 0);
    }
    return 0;
  }

  function getCurrentBoardItemId() {
    return Number((window.itemDetailModalState || {}).itemId || 0);
  }

  function buildDraftCookieKey(kind, itemId, commentId) {
    var userId = getCurrentBoardUserId();
    var item = Number(itemId || 0);
    var comment = Number(commentId || 0);
    return (
      DRAFT_COOKIE_PREFIX +
      String(kind || "") +
      "_u" +
      String(userId > 0 ? userId : 0) +
      "_i" +
      String(item > 0 ? item : 0) +
      "_c" +
      String(comment > 0 ? comment : 0)
    );
  }

  function writeCookie(name, value, days) {
    var expires = "";
    var ttlDays = Math.max(1, Number(days || DRAFT_COOKIE_DAYS));
    var dt = new Date();
    dt.setTime(dt.getTime() + ttlDays * 24 * 60 * 60 * 1000);
    expires = "; expires=" + dt.toUTCString();
    document.cookie =
      encodeURIComponent(String(name || "")) +
      "=" +
      encodeURIComponent(String(value || "")) +
      expires +
      "; path=/; SameSite=Lax";
  }

  function readCookie(name) {
    var key = encodeURIComponent(String(name || "")) + "=";
    var parts = String(document.cookie || "").split(";");
    for (var i = 0; i < parts.length; i++) {
      var part = String(parts[i] || "").trim();
      if (part.indexOf(key) === 0) {
        return decodeURIComponent(part.substring(key.length));
      }
    }
    return "";
  }

  function deleteCookie(name) {
    document.cookie =
      encodeURIComponent(String(name || "")) +
      "=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax";
  }

  function setDraftCookie(key, html) {
    var value = String(html || "").trim();
    if (!value) {
      deleteCookie(key);
      return;
    }

    // Keep inside a conservative cookie size to avoid silent truncation.
    if (value.length > 3400) {
      value = value.substring(0, 3400);
    }
    writeCookie(key, value, DRAFT_COOKIE_DAYS);
  }

  function getDraftCookie(key) {
    return String(readCookie(key) || "");
  }

  function clearDraftCookie(key) {
    deleteCookie(key);
  }

  function isImageMimeType(mimeType) {
    return (
      String(mimeType || "")
        .toLowerCase()
        .indexOf("image/") === 0
    );
  }

  function normalizeAttachmentUrl(filePath, fallbackUrl) {
    var direct = String(fallbackUrl || "").trim();
    if (/^https?:\/\//i.test(direct)) {
      return direct;
    }

    var normalizedPath = String(filePath || "")
      .trim()
      .replace(/\\/g, "/")
      .replace(/^\/+/, "");
    if (!normalizedPath) {
      return "";
    }

    var base = String((window.taskBoardConfig || {}).siteUrl || "")
      .trim()
      .replace(/\/+$/, "");

    return base ? base + "/" + normalizedPath : normalizedPath;
  }

  function uploadCommentAttachment(file, opts) {
    var itemId = Number(itemDetailModalState.itemId || 0);
    if (itemId <= 0) {
      return Promise.reject(new Error("Missing work item id."));
    }

    var isDescription = opts && opts.isDescription;
    var isReply = opts && opts.isReply;
    var formData = new FormData();
    if (isDescription) {
      formData.append("task_action", "upload_item_description_attachment");
    } else if (isReply) {
      formData.append("task_action", "upload_item_reply_attachment");
    } else {
      formData.append("task_action", "upload_item_comment_attachment");
    }
    formData.append("item_id", String(itemId));
    formData.append("attachment", file);

    return new Promise(function (resolve, reject) {
      postAction(
        formData,
        function (res) {
          var attachment =
            res && res.attachment && typeof res.attachment === "object"
              ? res.attachment
              : {};
          var filePath = String(attachment.file_path || "").trim();
          var fileUrl = normalizeAttachmentUrl(
            filePath,
            String(attachment.file_url || "").trim(),
          );
          if (!fileUrl) {
            reject(new Error("Attachment URL is empty."));
            return;
          }

          resolve({
            fileUrl: fileUrl,
            filePath: filePath,
            fileName: String(
              attachment.file_name || file.name || "file",
            ).trim(),
            mimeType: String(attachment.mime_type || file.type || "").trim(),
          });
        },
        function () {
          reject(new Error("Attachment upload failed."));
        },
      );
    });
  }

  function insertAttachmentIntoEditor(editor, attachment) {
    if (!editor || !attachment || !attachment.fileUrl) {
      return;
    }

    var fileUrl = String(attachment.fileUrl || "").trim();
    var fileName =
      String(attachment.fileName || "attachment").trim() || "attachment";
    var mimeType = String(attachment.mimeType || "").trim();

    if (isImageMimeType(mimeType)) {
      editor.insertContent(
        '<p><img src="' +
          escAttr(fileUrl) +
          '" alt="' +
          escAttr(fileName) +
          '" /></p>',
      );
      return;
    }

    editor.insertContent(
      '<p><a href="' +
        escAttr(fileUrl) +
        '" target="_blank" rel="noopener noreferrer">' +
        escHtml(fileName) +
        "</a></p>",
    );
  }

  function openAttachmentFilePicker(editor) {
    if (!editor) {
      return;
    }

    var editorContext = editor._taskEditorContext || {};

    var input = document.createElement("input");
    input.type = "file";
    input.accept =
      ".jpg,.jpeg,.png,.gif,.webp,.bmp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip";

    input.onchange = function () {
      var file = input.files && input.files[0] ? input.files[0] : null;
      if (!file) {
        return;
      }

      uploadCommentAttachment(file, editorContext)
        .then(function (attachment) {
          insertAttachmentIntoEditor(editor, attachment);
          editor.focus();
        })
        .catch(function () {
          // postAction already notifies.
        });
    };

    input.click();
  }

  function normalizeSimpleLinkUrl(rawUrl) {
    var value = String(rawUrl || "").trim();
    if (!value) {
      return "";
    }

    if (/^(https?:\/\/|mailto:|tel:|\/|#)/i.test(value)) {
      return value;
    }

    return "https://" + value;
  }

  function ensureSimpleLinkPopup() {
    if ($("#taskSimpleLinkPopup").length) {
      return;
    }

    var html =
      '<div id="taskSimpleLinkBackdrop" class="task-simple-link-backdrop d-none"></div>' +
      '<div id="taskSimpleLinkPopup" class="task-simple-link-popup d-none" role="dialog" aria-modal="true" aria-labelledby="taskSimpleLinkTitle">' +
      '<div class="task-simple-link-popup-head">' +
      '<div id="taskSimpleLinkTitle" class="task-simple-link-popup-title">Insert link</div>' +
      '<button type="button" id="taskSimpleLinkCloseBtn" class="btn task-simple-link-popup-close" aria-label="Close">' +
      '<i class="fa-solid fa-xmark"></i>' +
      "</button>" +
      "</div>" +
      '<div class="task-simple-link-popup-body">' +
      '<label class="form-label mb-1" for="taskSimpleLinkUrlInput">URL</label>' +
      '<input id="taskSimpleLinkUrlInput" type="text" class="form-control form-control-sm mb-2" autocomplete="off" />' +
      '<label class="form-label mb-1" for="taskSimpleLinkTextInput">Text to display</label>' +
      '<input id="taskSimpleLinkTextInput" type="text" class="form-control form-control-sm" autocomplete="off" />' +
      "</div>" +
      '<div class="task-simple-link-popup-actions">' +
      '<button type="button" id="taskSimpleLinkCancelBtn" class="btn btn-light btn-sm">Cancel</button>' +
      '<button type="button" id="taskSimpleLinkSaveBtn" class="btn btn-primary btn-sm">Save</button>' +
      "</div>" +
      "</div>";

    var $mountRoot = $("#taskItemDetailModal");
    if (!$mountRoot.length) {
      $mountRoot = $("body");
    }
    $mountRoot.append(html);
  }

  function closeSimpleLinkPopup() {
    if (typeof simpleLinkDialogState.restoreFocusTrap === "function") {
      simpleLinkDialogState.restoreFocusTrap();
    }

    $("#taskSimpleLinkBackdrop").addClass("d-none");
    $("#taskSimpleLinkPopup").addClass("d-none");
    simpleLinkDialogState.editor = null;
    simpleLinkDialogState.defaultText = "";
    simpleLinkDialogState.restoreFocusTrap = null;
  }

  function openSimpleLinkPopup(editor, initialUrl, initialText) {
    ensureSimpleLinkPopup();

    if (typeof simpleLinkDialogState.restoreFocusTrap === "function") {
      simpleLinkDialogState.restoreFocusTrap();
      simpleLinkDialogState.restoreFocusTrap = null;
    }

    simpleLinkDialogState.editor = editor || null;
    simpleLinkDialogState.defaultText = String(initialText || "").trim();
    simpleLinkDialogState.restoreFocusTrap = suspendDetailModalFocusTrap();

    $("#taskSimpleLinkUrlInput").val(String(initialUrl || "").trim());
    $("#taskSimpleLinkTextInput").val(simpleLinkDialogState.defaultText);
    $("#taskSimpleLinkBackdrop").removeClass("d-none");
    $("#taskSimpleLinkPopup").removeClass("d-none");

    window.setTimeout(function () {
      $("#taskSimpleLinkUrlInput").trigger("focus");
    }, 20);
  }

  function saveSimpleLinkPopup() {
    var editor = simpleLinkDialogState.editor;
    if (!editor) {
      closeSimpleLinkPopup();
      return;
    }

    var url = normalizeSimpleLinkUrl($("#taskSimpleLinkUrlInput").val());
    if (!url) {
      notify("URL is required.");
      $("#taskSimpleLinkUrlInput").trigger("focus");
      return;
    }

    var linkText = String($("#taskSimpleLinkTextInput").val() || "").trim();
    if (!linkText) {
      linkText = simpleLinkDialogState.defaultText || url;
    }

    editor.insertContent(
      '<a href="' +
        escAttr(url) +
        '" target="_blank" rel="noopener noreferrer">' +
        escHtml(linkText) +
        "</a>",
    );

    closeSimpleLinkPopup();
    editor.focus();
  }

  function suspendDetailModalFocusTrap() {
    if (typeof window.bootstrap === "undefined") {
      return function () {};
    }

    var modalEl = document.getElementById("taskItemDetailModal");
    if (!modalEl || typeof window.bootstrap.Modal.getInstance !== "function") {
      return function () {};
    }

    var instance = window.bootstrap.Modal.getInstance(modalEl);
    if (!instance || !instance._focustrap) {
      return function () {};
    }

    try {
      instance._focustrap.deactivate();
    } catch (e) {
      return function () {};
    }

    return function restoreFocusTrap() {
      try {
        instance._focustrap.activate();
      } catch (e) {
        // Ignore focus trap restore errors to keep popup close resilient.
      }
    };
  }

  function openSimpleLinkDialog(editor) {
    if (!editor || !editor.windowManager) {
      return;
    }

    var selectedText = String(
      editor.selection && typeof editor.selection.getContent === "function"
        ? editor.selection.getContent({ format: "text" })
        : "",
    )
      .replace(/\s+/g, " ")
      .trim();
    var selectedUrl = "";

    var selectedNode =
      editor.selection && typeof editor.selection.getNode === "function"
        ? editor.selection.getNode()
        : null;
    var $anchor = selectedNode ? $(selectedNode).closest("a") : $();
    if ($anchor.length) {
      selectedUrl = String($anchor.attr("href") || "").trim();
      if (!selectedText) {
        selectedText = String($anchor.text() || "").trim();
      }
    }

    openSimpleLinkPopup(editor, selectedUrl, selectedText);
  }

  function registerEditorToolbarControls(editor) {
    if (!editor || !editor.ui || !editor.ui.registry) {
      return;
    }

    var listButtonApi = null;

    function updateListControlIcon() {
      if (!listButtonApi) {
        return;
      }

      var iconName = listTypeIconName(editor.taskListTypeState || "bullet");
      try {
        if (typeof listButtonApi.setIcon === "function") {
          listButtonApi.setIcon(iconName);
          return;
        }
      } catch (e) {
        // Fall through to text fallback.
      }

      if (typeof listButtonApi.setText === "function") {
        if (iconName === "ordered-list") {
          listButtonApi.setText("1.");
        } else if (iconName === "checklist") {
          listButtonApi.setText("[ ]");
        } else {
          listButtonApi.setText("-");
        }
      }
    }

    try {
      editor.ui.registry.addButton("taskFileUpload", {
        icon: "image",
        tooltip: "Upload file",
        onAction: function () {
          openAttachmentFilePicker(editor);
        },
      });
    } catch (err) {
      // Ignore runtime issues.
    }

    try {
      editor.ui.registry.addButton("taskSimpleLink", {
        icon: "link",
        tooltip: "Insert link",
        onAction: function () {
          openSimpleLinkDialog(editor);
        },
      });
    } catch (err2) {
      // Ignore runtime issues.
    }

    try {
      editor.taskListTypeState = normalizeListType(
        editor.taskListTypeState || "bullet",
      );

      editor.ui.registry.addMenuButton("taskListControl", {
        icon: "unordered-list",
        tooltip: "List",
        onSetup: function (api) {
          listButtonApi = api;

          var syncFromSelection = function () {
            var detectedType = detectEditorListType(editor);
            if (detectedType) {
              editor.taskListTypeState = normalizeListType(detectedType);
            }
            updateListControlIcon();
          };

          syncFromSelection();
          editor.on("NodeChange SetContent", syncFromSelection);

          return function () {
            editor.off("NodeChange SetContent", syncFromSelection);
            if (listButtonApi === api) {
              listButtonApi = null;
            }
          };
        },
        fetch: function (callback) {
          callback([
            {
              type: "menuitem",
              text: "Bulleted list",
              onAction: function () {
                applyEditorListType(editor, "bullet");
                updateListControlIcon();
              },
            },
            {
              type: "menuitem",
              text: "Numbered list",
              onAction: function () {
                applyEditorListType(editor, "number");
                updateListControlIcon();
              },
            },
            {
              type: "menuitem",
              text: "Task list",
              onAction: function () {
                applyEditorListType(editor, "task");
                updateListControlIcon();
              },
            },
          ]);
        },
      });
    } catch (err3) {
      // Ignore runtime issues.
    }
  }

  function isCommentComposerVisible() {
    return !$("#taskItemCommentComposeWrap").hasClass("d-none");
  }

  function setCommentComposerVisible(visible, shouldReset, shouldFocus) {
    var canCompose = !!canEdit;
    var showCompose = canCompose && !!visible;

    if (!canCompose) {
      if (typeof window.setTaskItemDetailMobileOverlayState === "function") {
        window.setTaskItemDetailMobileOverlayState("comment", false);
      }
      $("#taskItemCommentComposeLauncherWrap").addClass("d-none");
      $("#taskItemCommentComposeWrap").addClass("d-none");
      updateCommentActionButtons();
      return;
    }

    if (shouldReset) {
      clearCommentEditor();
    }

    $("#taskItemCommentComposeLauncherWrap").toggleClass("d-none", showCompose);
    $("#taskItemCommentComposeWrap").toggleClass("d-none", !showCompose);
    if (typeof window.setTaskItemDetailMobileOverlayState === "function") {
      window.setTaskItemDetailMobileOverlayState("comment", showCompose);
    }
    updateCommentActionButtons();

    if (showCompose && shouldFocus) {
      ensureCommentEditorReady().then(function (editor) {
        if (editor) {
          editor.focus();
        }
      });
    }
  }

  function getCommentEditorInstance() {
    if (!window.tinymce || typeof window.tinymce.get !== "function") {
      return null;
    }
    return window.tinymce.get(COMMENT_EDITOR_ID);
  }

  function getReplyEditorId(commentId) {
    return REPLY_EDITOR_PREFIX + String(Number(commentId || 0));
  }

  function getReplyEditEditorId(replyId) {
    return REPLY_EDIT_EDITOR_PREFIX + String(Number(replyId || 0));
  }

  function getEditEditorId(commentId) {
    return EDIT_EDITOR_PREFIX + String(Number(commentId || 0));
  }

  function getReplyEditorInstance(commentId) {
    if (!window.tinymce || typeof window.tinymce.get !== "function") {
      return null;
    }
    return window.tinymce.get(getReplyEditorId(commentId));
  }

  function getEditEditorInstance(commentId) {
    if (!window.tinymce || typeof window.tinymce.get !== "function") {
      return null;
    }
    return window.tinymce.get(getEditEditorId(commentId));
  }

  function getReplyEditEditorInstance(replyId) {
    if (!window.tinymce || typeof window.tinymce.get !== "function") {
      return null;
    }
    return window.tinymce.get(getReplyEditEditorId(replyId));
  }

  function getCommentEditorHtml() {
    var editor = getCommentEditorInstance();
    if (editor) {
      return String(editor.getContent() || "");
    }
    return String($("#" + COMMENT_EDITOR_ID).val() || "");
  }

  function hasCommentContent(html) {
    var value = String(html || "");
    var $doc = $("<div>").html(value);
    var text =
      $doc
        .text()
        .replace(/\u00a0/g, " ")
        .replace(/\s+/g, " ")
        .trim() || "";

    if (text.length > 0) {
      return true;
    }

    return $doc.find("img,video,audio,iframe,object,embed").length > 0;
  }

  function getDescriptionEditorInstance() {
    if (!window.tinymce || typeof window.tinymce.get !== "function") {
      return null;
    }
    return window.tinymce.get(DESCRIPTION_EDITOR_ID);
  }

  function getDescriptionEditorHtml() {
    var editor = getDescriptionEditorInstance();
    if (editor) {
      return String(editor.getContent() || "");
    }
    return String($("#" + DESCRIPTION_EDITOR_ID).val() || "");
  }

  function getDraftContextItemId() {
    var activeId = Number(itemDetailModalState.itemId || 0);
    if (activeId > 0) {
      draftItemContextId = activeId;
      return activeId;
    }
    return Number(draftItemContextId || 0);
  }

  function setDescriptionEditorHtml(html) {
    var value = String(html || "");
    $("#" + DESCRIPTION_EDITOR_ID).val(value);
    var editor = getDescriptionEditorInstance();
    if (editor) {
      editor.setContent(value);
    }
  }

  function getCommentDraftKey() {
    return buildDraftCookieKey("comment", getDraftContextItemId(), 0);
  }

  function getCommentDraftNoticeKey() {
    return getCommentDraftKey() + "_notice";
  }

  function getReplyDraftKey(commentId) {
    return buildDraftCookieKey(
      "reply",
      getDraftContextItemId(),
      Number(commentId || 0),
    );
  }

  function getReplyDraftNoticeKey(commentId) {
    return getReplyDraftKey(commentId) + "_notice";
  }

  function getEditCommentDraftKey(commentId) {
    return buildDraftCookieKey(
      "comment_edit",
      getDraftContextItemId(),
      Number(commentId || 0),
    );
  }

  function getEditCommentDraftNoticeKey(commentId) {
    return getEditCommentDraftKey(commentId) + "_notice";
  }

  function getEditReplyDraftKey(replyId) {
    return buildDraftCookieKey(
      "reply_edit",
      getDraftContextItemId(),
      Number(replyId || 0),
    );
  }

  function getEditReplyDraftNoticeKey(replyId) {
    return getEditReplyDraftKey(replyId) + "_notice";
  }

  function getDescriptionDraftKey() {
    return buildDraftCookieKey("description", getDraftContextItemId(), 0);
  }

  function getDescriptionDraftNoticeKey() {
    return getDescriptionDraftKey() + "_notice";
  }

  function setDraftNoticeFlag(key) {
    writeCookie(key, "1", DRAFT_COOKIE_DAYS);
  }

  function hasDraftNoticeFlag(key) {
    return String(readCookie(key) || "") === "1";
  }

  function clearDraftNoticeFlag(key) {
    deleteCookie(key);
  }

  function flushCommentDraftNow(options) {
    var settings = options && typeof options === "object" ? options : {};
    if (
      settings.preserveExistingNotice &&
      hasDraftNoticeFlag(getCommentDraftNoticeKey()) &&
      !isCommentComposerVisible()
    ) {
      return !!String(getDraftCookie(getCommentDraftKey()) || "").trim();
    }

    var html = getCommentEditorHtml();
    if (hasCommentContent(html)) {
      setDraftCookie(getCommentDraftKey(), html);
      return true;
    }

    clearDraftCookie(getCommentDraftKey());
    clearDraftNoticeFlag(getCommentDraftNoticeKey());
    return false;
  }

  function flushReplyDraftNow(commentId) {
    var id = Number(commentId || 0);
    if (id <= 0) {
      return false;
    }

    var editor = getReplyEditorInstance(id);
    var html = editor ? String(editor.getContent() || "") : "";
    if (hasCommentContent(html)) {
      setDraftCookie(getReplyDraftKey(id), html);
      return true;
    }

    clearDraftCookie(getReplyDraftKey(id));
    clearDraftNoticeFlag(getReplyDraftNoticeKey(id));
    return false;
  }

  function getCommentOriginalHtml(commentId) {
    var id = Number(commentId || 0);
    var rows = Array.isArray(itemDetailModalState.comments)
      ? itemDetailModalState.comments
      : [];
    for (var i = 0; i < rows.length; i++) {
      var row = rows[i] || {};
      if (Number(row.id || 0) !== id) {
        continue;
      }
      var html = String(row.comment_html || "").trim();
      if (html) {
        return html;
      }
      return "<p>" + escHtml(String(row.comment_text || "")) + "</p>";
    }
    return "";
  }

  function flushEditCommentDraftNow(commentId) {
    var id = Number(commentId || 0);
    if (id <= 0) {
      return false;
    }

    var editor = getEditEditorInstance(id);
    var html = editor ? String(editor.getContent() || "") : "";
    var original = getCommentOriginalHtml(id);
    if (
      hasCommentContent(html) &&
      normalizeHtmlForComparison(html) !== normalizeHtmlForComparison(original)
    ) {
      setDraftCookie(getEditCommentDraftKey(id), html);
      return true;
    }

    clearDraftCookie(getEditCommentDraftKey(id));
    clearDraftNoticeFlag(getEditCommentDraftNoticeKey(id));
    return false;
  }

  function getReplyOriginalHtml(replyId) {
    var id = Number(replyId || 0);
    var record = findReplyRecord(id);
    if (!record || !record.reply) {
      return "";
    }

    var row = record.reply || {};
    var html = String(row.reply_html || "").trim();
    if (html) {
      return html;
    }
    return "<p>" + escHtml(String(row.reply_text || "")) + "</p>";
  }

  function flushEditReplyDraftNow(replyId) {
    var id = Number(replyId || 0);
    if (id <= 0) {
      return false;
    }

    var editor = getReplyEditEditorInstance(id);
    var html = editor ? String(editor.getContent() || "") : "";
    var original = getReplyOriginalHtml(id);
    if (
      hasCommentContent(html) &&
      normalizeHtmlForComparison(html) !== normalizeHtmlForComparison(original)
    ) {
      setDraftCookie(getEditReplyDraftKey(id), html);
      return true;
    }

    clearDraftCookie(getEditReplyDraftKey(id));
    clearDraftNoticeFlag(getEditReplyDraftNoticeKey(id));
    return false;
  }

  function flushDescriptionDraftNow(options) {
    var settings = options && typeof options === "object" ? options : {};

    if (
      settings.preserveExistingNotice &&
      hasDraftNoticeFlag(getDescriptionDraftNoticeKey()) &&
      !itemDetailModalState.descriptionEditing
    ) {
      return !!String(getDraftCookie(getDescriptionDraftKey()) || "").trim();
    }

    // If the user explicitly saved or cancelled, never re-save the draft on close
    if (descriptionDraftClearedByUser) {
      clearDraftCookie(getDescriptionDraftKey());
      clearDraftNoticeFlag(getDescriptionDraftNoticeKey());
      return false;
    }

    var html = getDescriptionEditorHtml();
    if (hasCommentContent(html)) {
      // Don't save draft if content matches the original description captured at modal open
      if (
        normalizeHtmlForComparison(html) ===
        normalizeHtmlForComparison(capturedInitialDescription)
      ) {
        clearDraftCookie(getDescriptionDraftKey());
        clearDraftNoticeFlag(getDescriptionDraftNoticeKey());
        return false;
      }
      setDraftCookie(getDescriptionDraftKey(), html);
      return true;
    }

    clearDraftCookie(getDescriptionDraftKey());
    clearDraftNoticeFlag(getDescriptionDraftNoticeKey());
    return false;
  }

  function normalizeHtmlForComparison(html) {
    if (!html) return "";
    var tmp = document.createElement("div");
    tmp.innerHTML = String(html);
    return (tmp.innerHTML || "").replace(/\s+/g, " ").trim();
  }

  function scheduleCommentDraftSave() {
    if (commentDraftTimer) {
      window.clearTimeout(commentDraftTimer);
    }
    commentDraftTimer = window.setTimeout(function () {
      commentDraftTimer = 0;
      var html = getCommentEditorHtml();
      if (hasCommentContent(html)) {
        setDraftCookie(getCommentDraftKey(), html);
        clearDraftNoticeFlag(getCommentDraftNoticeKey());
      } else {
        clearDraftCookie(getCommentDraftKey());
        clearDraftNoticeFlag(getCommentDraftNoticeKey());
      }
      updateCommentDraftNotice();
    }, 220);
  }

  function clearCommentDraft() {
    clearDraftCookie(getCommentDraftKey());
    clearDraftNoticeFlag(getCommentDraftNoticeKey());
    updateCommentDraftNotice();
  }

  function updateCommentDraftNotice() {
    var hasDraft = !!String(getDraftCookie(getCommentDraftKey()) || "").trim();
    var shouldShow = hasDraft && hasDraftNoticeFlag(getCommentDraftNoticeKey());
    $("#taskItemCommentDraftNotice").toggleClass("d-none", !shouldShow);
  }

  function restoreCommentDraft() {
    var draft = String(getDraftCookie(getCommentDraftKey()) || "");
    if (!draft) {
      return;
    }

    setCommentComposerVisible(true, false, true);
    clearDraftNoticeFlag(getCommentDraftNoticeKey());
    ensureCommentEditorReady().then(function (editor) {
      if (editor) {
        editor.setContent(draft);
        editor.focus();
      } else {
        $("#" + COMMENT_EDITOR_ID).val(draft);
      }
      updateCommentActionButtons();
    });
    updateCommentDraftNotice();
  }

  function scheduleReplyDraftSave(commentId) {
    var id = Number(commentId || 0);
    if (id <= 0) {
      return;
    }

    if (replyDraftTimerByCommentId[id]) {
      window.clearTimeout(replyDraftTimerByCommentId[id]);
    }

    replyDraftTimerByCommentId[id] = window.setTimeout(function () {
      replyDraftTimerByCommentId[id] = 0;
      var editor = getReplyEditorInstance(id);
      var html = editor ? String(editor.getContent() || "") : "";
      if (hasCommentContent(html)) {
        setDraftCookie(getReplyDraftKey(id), html);
        clearDraftNoticeFlag(getReplyDraftNoticeKey(id));
      } else {
        clearDraftCookie(getReplyDraftKey(id));
        clearDraftNoticeFlag(getReplyDraftNoticeKey(id));
      }
      updateReplyDraftNotice(id);
    }, 220);
  }

  function clearReplyDraft(commentId) {
    var id = Number(commentId || 0);
    if (id <= 0) {
      return;
    }
    clearDraftCookie(getReplyDraftKey(id));
    clearDraftNoticeFlag(getReplyDraftNoticeKey(id));
    updateReplyDraftNotice(id);
  }

  function updateReplyDraftNotice(commentId) {
    var id = Number(commentId || 0);
    if (id <= 0) {
      return;
    }

    var hasDraft = !!String(getDraftCookie(getReplyDraftKey(id)) || "").trim();
    var shouldShow = hasDraft && hasDraftNoticeFlag(getReplyDraftNoticeKey(id));
    $(
      '.task-item-reply-draft-notice[data-comment-id="' + id + '"]',
    ).toggleClass("d-none", !shouldShow);
  }

  function updateEditCommentDraftNotice(commentId) {
    var id = Number(commentId || 0);
    if (id <= 0) {
      return;
    }

    var hasDraft = !!String(
      getDraftCookie(getEditCommentDraftKey(id)) || "",
    ).trim();
    var shouldShow =
      hasDraft && hasDraftNoticeFlag(getEditCommentDraftNoticeKey(id));
    $(
      '.task-item-comment-edit-draft-notice[data-comment-id="' + id + '"]',
    ).toggleClass("d-none", !shouldShow);
  }

  function updateEditReplyDraftNotice(replyId) {
    var id = Number(replyId || 0);
    if (id <= 0) {
      return;
    }

    var hasDraft = !!String(getDraftCookie(getEditReplyDraftKey(id)) || "").trim();
    var shouldShow = hasDraft && hasDraftNoticeFlag(getEditReplyDraftNoticeKey(id));
    $(
      '.task-item-reply-edit-draft-notice[data-reply-id="' + id + '"]',
    ).toggleClass("d-none", !shouldShow);
  }

  function restoreReplyDraft(commentId) {
    var id = Number(commentId || 0);
    if (id <= 0) {
      return;
    }
    var draft = String(getDraftCookie(getReplyDraftKey(id)) || "");
    if (!draft) {
      return;
    }

    clearDraftNoticeFlag(getReplyDraftNoticeKey(id));
    var editor = getReplyEditorInstance(id);
    if (editor) {
      editor.setContent(draft);
      editor.focus();
    }
    updateReplyDraftNotice(id);
  }

  function restoreEditCommentDraft(commentId, triggerEl) {
    var id = Number(commentId || 0);
    if (id <= 0) {
      return;
    }

    var draft = String(getDraftCookie(getEditCommentDraftKey(id)) || "");
    if (!draft) {
      return;
    }

    clearDraftNoticeFlag(getEditCommentDraftNoticeKey(id));
    openEditComposer(id, triggerEl, {
      draftHtml: draft,
    });
    updateEditCommentDraftNotice(id);
  }

  function scheduleDescriptionDraftSave() {
    if (descriptionDraftTimer) {
      window.clearTimeout(descriptionDraftTimer);
    }

    descriptionDraftTimer = window.setTimeout(function () {
      descriptionDraftTimer = 0;
      var html = getDescriptionEditorHtml();
      if (hasCommentContent(html)) {
        // Don't save draft if content matches the original description (captured at modal open)
        var initial = capturedInitialDescription;
        if (
          normalizeHtmlForComparison(html) ===
          normalizeHtmlForComparison(initial)
        ) {
          clearDraftCookie(getDescriptionDraftKey());
          clearDraftNoticeFlag(getDescriptionDraftNoticeKey());
        } else {
          setDraftCookie(getDescriptionDraftKey(), html);
          clearDraftNoticeFlag(getDescriptionDraftNoticeKey());
        }
      } else {
        clearDraftCookie(getDescriptionDraftKey());
        clearDraftNoticeFlag(getDescriptionDraftNoticeKey());
      }
      updateDescriptionDraftNotice();
    }, 220);
  }

  function clearDescriptionDraft() {
    descriptionDraftClearedByUser = true;
    clearDraftCookie(getDescriptionDraftKey());
    clearDraftNoticeFlag(getDescriptionDraftNoticeKey());
    updateDescriptionDraftNotice();
  }

  function updateDescriptionDraftNotice() {
    var hasDraft = !!String(
      getDraftCookie(getDescriptionDraftKey()) || "",
    ).trim();
    var shouldShow =
      hasDraft && hasDraftNoticeFlag(getDescriptionDraftNoticeKey());
    $("#taskItemDetailDescriptionDraftNotice").toggleClass(
      "d-none",
      !shouldShow,
    );
  }

  function restoreDescriptionDraft() {
    var draft = String(getDraftCookie(getDescriptionDraftKey()) || "");
    if (!draft) {
      return;
    }

    if (typeof window.setItemDetailDescriptionEditMode === "function") {
      window.setItemDetailDescriptionEditMode(true, {
        focus: true,
      });
    }

    clearDraftNoticeFlag(getDescriptionDraftNoticeKey());
    setDescriptionEditorHtml(draft);

    if (typeof window.renderItemDetailDescriptionPreview === "function") {
      window.renderItemDetailDescriptionPreview(draft);
    }
    updateDescriptionDraftNotice();
  }

  window.getDescriptionEditorContent = getDescriptionEditorHtml;
  window.setDescriptionEditorContent = setDescriptionEditorHtml;
  window.taskBoardDescriptionDraft = {
    updateNotice: updateDescriptionDraftNotice,
    restore: restoreDescriptionDraft,
    clear: clearDescriptionDraft,
    save: scheduleDescriptionDraftSave,
  };

  function updateCommentActionButtons() {
    var hasItemId = Number(itemDetailModalState.itemId || 0) > 0;
    var isOpen = isCommentComposerVisible();
    var hasContent = hasCommentContent(getCommentEditorHtml());
    var canSave =
      !commentSaving && !!canEdit && hasItemId && isOpen && hasContent;
    $("#taskItemCommentSaveBtn").prop("disabled", !canSave);
    $("#taskItemCommentCancelBtn").prop("disabled", !!commentSaving);
  }

  function clearCommentEditor() {
    var editor = getCommentEditorInstance();
    if (editor) {
      editor.setContent("");
      editor.undoManager.clear();
      editor.setDirty(false);
    } else {
      $("#" + COMMENT_EDITOR_ID).val("");
    }
    updateCommentActionButtons();
  }

  function createBaseEditorConfig(selector, setupCallback, opts) {
    opts = opts || {};
    var isDescription = !!opts.isDescription;
    var isReply = !!opts.isReply;
    var isCompactMobile = window.matchMedia("(max-width: 767.98px)").matches;
    return {
      selector: selector,
      base_url: window.taskBoardConfig.siteUrl + "/header/tinymce",
      license_key: "gpl",
      menubar: false,
      branding: false,
      statusbar: false,
      resize: false,
      promotion: false,
      plugins: "autolink advlist lists link code",
      toolbar_mode: isCompactMobile ? "scrolling" : "floating",
      contextmenu: false,
      convert_urls: false,
      relative_urls: false,
      paste_data_images: true,
      automatic_uploads: true,
      style_formats: [
        { title: "Bold", format: "bold" },
        { title: "Italic", format: "italic" },
        { title: "Underline", format: "underline" },
        { title: "Strikethrough", format: "strikethrough" },
        { title: "Code", format: "code" },
        { title: "Subscript", format: "subscript" },
        { title: "Superscript", format: "superscript" },
        { title: "Clear formatting", format: "clearformat" },
      ],
      formats: {
        clearformat: [
          {
            selector: "*",
            remove: "all",
            split: true,
            expand: false,
            deep: true,
          },
        ],
      },
      link_title: false,
      target_list: false,
      images_upload_handler: function (blobInfo) {
        return uploadCommentAttachment(blobInfo.blob(), {
          isDescription: isDescription,
          isReply: isReply,
        }).then(function (attachment) {
          return String(attachment.fileUrl || "");
        });
      },
      content_style:
        "body { font-family: Segoe UI, Arial, sans-serif; font-size: 14px; line-height: 1.45; color: #24364d; } ul.task-editor-checklist { list-style: none; margin-left: 0; padding-left: 0; } ul.task-editor-checklist li { list-style: none; display: flex; align-items: flex-start; gap: 0.4rem; } ul.task-editor-checklist li input.task-editor-checkbox { margin-top: 0.28rem; flex: 0 0 auto; cursor: pointer; pointer-events: auto; }",
      setup: function (editor) {
        editor._taskEditorContext = { isDescription: isDescription, isReply: isReply };
        function findChecklistRowCheckbox(target) {
          if (!target || !target.closest) {
            return null;
          }

          var row = target.closest("li");
          if (!row || !row.closest) {
            return null;
          }

          var list = row.closest("ul.task-editor-checklist, ul.tox-checklist");
          if (!list) {
            return null;
          }

          return row.querySelector(
            'input.task-editor-checkbox,input[type="checkbox"][data-task-editor="1"]',
          );
        }

        function isTaskEditorCheckbox(target) {
          if (!target) {
            return false;
          }

          return (
            (target.classList &&
              target.classList.contains("task-editor-checkbox")) ||
            (target.tagName === "INPUT" &&
              target.getAttribute("type") === "checkbox" &&
              target.getAttribute("data-task-editor") === "1")
          );
        }

        function resolveTaskCheckboxTarget(target) {
          if (isTaskEditorCheckbox(target)) {
            return target;
          }
          return findChecklistRowCheckbox(target);
        }

        function syncTaskEditorCheckboxState(target) {
          if (!isTaskEditorCheckbox(target)) {
            return;
          }

          if (target.checked) {
            target.setAttribute("checked", "checked");
          } else {
            target.removeAttribute("checked");
          }
          editor.nodeChanged();
          editor.setDirty(true);
        }

        function handleCheckboxPointerEvent(evt, source) {
          var rawTarget = evt && evt.target ? evt.target : null;
          var target = resolveTaskCheckboxTarget(rawTarget);
          if (!target) {
            return;
          }

          if (source === "mousedown" || source === "pointerdown") {
            evt.preventDefault();
            evt.stopPropagation();
            target.checked = !target.checked;
            target.setAttribute("data-task-editor-mousedown-toggle", "1");
            syncTaskEditorCheckboxState(target);
            return;
          }

          if (
            target.getAttribute("data-task-editor-mousedown-toggle") === "1"
          ) {
            target.removeAttribute("data-task-editor-mousedown-toggle");
            evt.preventDefault();
            evt.stopPropagation();
            return;
          }

          window.setTimeout(function () {
            syncTaskEditorCheckboxState(target);
          }, 0);
        }

        if (typeof setupCallback === "function") {
          setupCallback(editor);
        }

        editor.on("mousedown", function (evt) {
          handleCheckboxPointerEvent(evt, "mousedown");
        });

        editor.on("click", function (evt) {
          handleCheckboxPointerEvent(evt, "click");
        });

        editor.on("init", function () {
          var body = editor.getBody ? editor.getBody() : null;
          if (!body || !body.addEventListener) {
            return;
          }

          body.addEventListener(
            "mousedown",
            function (evt) {
              handleCheckboxPointerEvent(evt, "native-mousedown");
            },
            true,
          );

          body.addEventListener(
            "click",
            function (evt) {
              handleCheckboxPointerEvent(evt, "native-click");
            },
            true,
          );
        });

        editor.on("keyup", function (evt) {
          var key = String((evt && evt.key) || "").toLowerCase();
          var code = String((evt && evt.code) || "").toLowerCase();
          if (key !== " " && key !== "spacebar" && code !== "space") {
            return;
          }

          var target = resolveTaskCheckboxTarget(
            evt && evt.target ? evt.target : null,
          );
          if (!target) {
            return;
          }

          window.setTimeout(function () {
            syncTaskEditorCheckboxState(target);
          }, 0);
        });
      },
    };
  }
  // Description field TinyMCE integration
  window.ensureDescriptionEditorReady = function () {
    if (!window.tinymce || typeof window.tinymce.init !== "function") {
      return Promise.resolve(null);
    }
    var selector = "#taskItemDetailDescriptionInput";
    var existing = window.tinymce.get("taskItemDetailDescriptionInput");
    if (existing) {
      return Promise.resolve(existing);
    }
    return window.tinymce
      .init(
        Object.assign(
          createBaseEditorConfig(
            selector,
            function (editor) {
              registerEditorToolbarControls(editor);
              editor.on("init", function () {
                updateDescriptionDraftNotice();
              });
              editor.on("keyup input undo redo paste", function () {
                scheduleDescriptionDraftSave();
              });
            },
            { isDescription: true },
          ),
          {
            height: 220,
            toolbar:
              "blocks bold styles taskListControl forecolor taskFileUpload taskSimpleLink undo redo",
          },
        ),
      )
      .then(function (editors) {
        return editors && editors.length ? editors[0] : null;
      });
  };

  function ensureCommentEditorReady() {
    if (commentEditorInitPromise) {
      return commentEditorInitPromise;
    }

    if (!window.tinymce || typeof window.tinymce.init !== "function") {
      commentEditorInitPromise = Promise.resolve(null);
      return commentEditorInitPromise;
    }

    commentEditorInitPromise = new Promise(function (resolve) {
      var existing = getCommentEditorInstance();
      if (existing) {
        resolve(existing);
        return;
      }

      var config = createBaseEditorConfig(
        "#" + COMMENT_EDITOR_ID,
        function (editor) {
          registerMentionAutocompleter(editor);
          registerEditorToolbarControls(editor);

          editor.on("init", function () {
            updateCommentDraftNotice();
            updateCommentActionButtons();
          });
          editor.on("keyup input undo redo paste", function () {
            scheduleCommentDraftSave();
            updateCommentActionButtons();
          });
        },
      );

      config.height = 150;
      config.toolbar =
        "blocks bold styles taskListControl forecolor taskFileUpload taskSimpleLink undo redo";

      window.tinymce
        .init(config)
        .then(function (editors) {
          resolve(editors && editors.length ? editors[0] : null);
        })
        .catch(function () {
          resolve(null);
        });
    });

    return commentEditorInitPromise;
  }

  function ensureReplyEditorReady(commentId, actorName) {
    var id = Number(commentId || 0);
    if (id <= 0) {
      return Promise.resolve(null);
    }

    if (!window.tinymce || typeof window.tinymce.init !== "function") {
      return Promise.resolve(null);
    }

    if (replyEditorInitMap[id]) {
      return replyEditorInitMap[id];
    }

    replyEditorInitMap[id] = new Promise(function (resolve) {
      var existing = getReplyEditorInstance(id);
      if (existing) {
        resolve(existing);
        return;
      }

      var selector = "#" + getReplyEditorId(id);
      var config = createBaseEditorConfig(selector, function (editor) {
        registerMentionAutocompleter(editor);
        registerEditorToolbarControls(editor);
        editor.on("init", function () {
          updateReplyDraftNotice(id);
        });
        editor.on("keyup input undo redo paste", function () {
          scheduleReplyDraftSave(id);
        });
      }, { isReply: true });
      config.height = 120;
      config.toolbar =
        "blocks bold styles taskListControl forecolor taskFileUpload taskSimpleLink undo redo";

      window.tinymce
        .init(config)
        .then(function (editors) {
          var editor = editors && editors.length ? editors[0] : null;
          if (editor) {
            var prefillName = String(actorName || "").trim();
            if (prefillName) {
              editor.setContent("<p>@" + escHtml(prefillName) + "&nbsp;</p>");
              editor.selection.select(editor.getBody(), true);
              editor.selection.collapse(false);
            }
          }
          resolve(editor);
        })
        .catch(function () {
          resolve(null);
        });
    });

    return replyEditorInitMap[id];
  }

  function ensureEditEditorReady(commentId, initialHtml) {
    var id = Number(commentId || 0);
    if (id <= 0) {
      return Promise.resolve(null);
    }

    if (!window.tinymce || typeof window.tinymce.init !== "function") {
      return Promise.resolve(null);
    }

    if (editEditorInitMap[id]) {
      return editEditorInitMap[id].then(function (editor) {
        if (editor) {
          editor.setContent(String(initialHtml || ""));
        }
        return editor;
      });
    }

    editEditorInitMap[id] = new Promise(function (resolve) {
      var existing = getEditEditorInstance(id);
      if (existing) {
        resolve(existing);
        return;
      }

      var selector = "#" + getEditEditorId(id);
      var config = createBaseEditorConfig(selector, function (editor) {
        registerMentionAutocompleter(editor);
        registerEditorToolbarControls(editor);
        editor.on("keyup input undo redo paste", function () {
          scheduleEditCommentDraftSave(id);
        });
      });
      config.height = 140;
      config.toolbar =
        "blocks bold styles taskListControl forecolor taskFileUpload taskSimpleLink undo redo";

      window.tinymce
        .init(config)
        .then(function (editors) {
          var editor = editors && editors.length ? editors[0] : null;
          if (editor) {
            editor.setContent(String(initialHtml || ""));
            editor.selection.select(editor.getBody(), true);
            editor.selection.collapse(false);
          }
          resolve(editor);
        })
        .catch(function () {
          resolve(null);
        });
    });

    return editEditorInitMap[id];
  }

  function ensureReplyEditEditorReady(replyId, initialHtml) {
    var id = Number(replyId || 0);
    if (id <= 0) {
      return Promise.resolve(null);
    }

    if (!window.tinymce || typeof window.tinymce.init !== "function") {
      return Promise.resolve(null);
    }

    if (replyEditEditorInitMap[id]) {
      return replyEditEditorInitMap[id].then(function (editor) {
        if (editor) {
          editor.setContent(String(initialHtml || ""));
        }
        return editor;
      });
    }

    replyEditEditorInitMap[id] = new Promise(function (resolve) {
      var existing = getReplyEditEditorInstance(id);
      if (existing) {
        resolve(existing);
        return;
      }

      var selector = "#" + getReplyEditEditorId(id);
      var config = createBaseEditorConfig(selector, function (editor) {
        registerMentionAutocompleter(editor);
        registerEditorToolbarControls(editor);
        editor.on("init", function () {
          updateEditReplyDraftNotice(id);
        });
        editor.on("keyup input undo redo paste", function () {
          scheduleEditReplyDraftSave(id);
        });
      }, { isReply: true });
      config.height = 130;
      config.toolbar =
        "blocks bold styles taskListControl forecolor taskFileUpload taskSimpleLink undo redo";

      window.tinymce
        .init(config)
        .then(function (editors) {
          var editor = editors && editors.length ? editors[0] : null;
          if (editor) {
            editor.setContent(String(initialHtml || ""));
            editor.selection.select(editor.getBody(), true);
            editor.selection.collapse(false);
          }
          resolve(editor);
        })
        .catch(function () {
          resolve(null);
        });
    });

    return replyEditEditorInitMap[id];
  }

  function destroyReplyEditor(commentId) {
    var id = Number(commentId || 0);
    if (id <= 0) {
      return;
    }

    var editor = getReplyEditorInstance(id);
    if (editor) {
      editor.remove();
    }

    delete replyEditorInitMap[id];
  }

  function destroyEditEditor(commentId) {
    var id = Number(commentId || 0);
    if (id <= 0) {
      return;
    }

    var editor = getEditEditorInstance(id);
    if (editor) {
      editor.remove();
    }

    delete editEditorInitMap[id];
  }

  function destroyReplyEditEditor(replyId) {
    var id = Number(replyId || 0);
    if (id <= 0) {
      return;
    }

    var editor = getReplyEditEditorInstance(id);
    if (editor) {
      editor.remove();
    }

    delete replyEditEditorInitMap[id];
  }

  function closeReplyComposer() {
    if (Number(openReplyCommentId || 0) <= 0) {
      return;
    }

    var commentId = Number(openReplyCommentId || 0);
    destroyReplyEditor(commentId);
    $(
      '.task-item-comment-reply-box[data-comment-id="' + commentId + '"]',
    ).remove();
    $("#taskItemCommentReplyBox_" + commentId).remove();
    openReplyCommentId = 0;
    openReplyEntryRef = null;
  }

  function closeEditComposer() {
    if (Number(openEditCommentId || 0) <= 0) {
      return;
    }

    var commentId = Number(openEditCommentId || 0);
    if (flushEditCommentDraftNow(commentId)) {
      setDraftNoticeFlag(getEditCommentDraftNoticeKey(commentId));
    }
    destroyEditEditor(commentId);
    $(
      '.task-item-comment-edit-box[data-comment-id="' + commentId + '"]',
    ).remove();
    $("#taskItemCommentEditBox_" + commentId).remove();
    openEditCommentId = 0;
    openEditEntryRef = null;
  }

  function closeReplyEditComposer() {
    if (Number(openEditReplyId || 0) <= 0) {
      return;
    }

    var replyId = Number(openEditReplyId || 0);
    if (flushEditReplyDraftNow(replyId)) {
      setDraftNoticeFlag(getEditReplyDraftNoticeKey(replyId));
    }
    destroyReplyEditEditor(replyId);
    $('.task-item-comment-edit-box[data-reply-id="' + replyId + '"]').remove();
    $("#taskItemReplyEditBox_" + replyId).remove();
    openEditReplyId = 0;
    openEditReplyEntryRef = null;
    updateEditReplyDraftNotice(replyId);
  }

  function destroyAllReplyEditors() {
    var keys = Object.keys(replyEditorInitMap || {});
    for (var i = 0; i < keys.length; i++) {
      destroyReplyEditor(Number(keys[i] || 0));
    }
    replyEditorInitMap = {};
    openReplyCommentId = 0;
    openReplyEntryRef = null;
    $(".task-item-comment-reply-box").remove();
  }

  function destroyAllEditEditors() {
    var keys = Object.keys(editEditorInitMap || {});
    for (var i = 0; i < keys.length; i++) {
      destroyEditEditor(Number(keys[i] || 0));
    }
    editEditorInitMap = {};
    openEditCommentId = 0;
    openEditEntryRef = null;
    $(".task-item-comment-edit-box").remove();
  }

  function destroyAllReplyEditEditors() {
    var keys = Object.keys(replyEditEditorInitMap || {});
    for (var i = 0; i < keys.length; i++) {
      destroyReplyEditEditor(Number(keys[i] || 0));
    }
    replyEditEditorInitMap = {};
    openEditReplyId = 0;
    openEditReplyEntryRef = null;
    $(".task-item-comment-edit-box[data-reply-id]").remove();
  }

  function buildCommentPermalink(itemId, commentId) {
    var baseUrl = String(window.location.href || "").split("#")[0];
    return (
      baseUrl +
      "#task-item-" +
      String(Number(itemId || 0)) +
      "-comment-" +
      String(Number(commentId || 0))
    );
  }

  function buildReplyPermalink(itemId, replyId) {
    var baseUrl = String(window.location.href || "").split("#")[0];
    return (
      baseUrl +
      "#task-item-" +
      String(Number(itemId || 0)) +
      "-reply-" +
      String(Number(replyId || 0))
    );
  }

  function closeCommentActionMenus() {
    $(".task-item-comment-more-menu").addClass("d-none");
    $(".task-item-reply-more-menu").addClass("d-none");
    $(".task-item-comment-more-btn").removeClass("is-open");
    $(".task-item-reply-more-btn").removeClass("is-open");
  }

  function setCommentListLoading() {
    if (!itemDetailModalState.commentsLoading) {
      return;
    }
    $("#taskItemActivityCommentList").html(
      '<div class="task-item-activity-empty">Loading comments...</div>',
    );
  }

  function loadItemComments(itemId) {
    var id = Number(itemId || 0);
    var currentToken = ++commentLoadToken;

    if (id <= 0) {
      itemDetailModalState.comments = [];
      itemDetailModalState.commentsLoading = false;
      renderItemHistoryPanels();
      return;
    }

    itemDetailModalState.commentsLoading = true;
    setCommentListLoading();

    postAction(
      {
        task_action: "get_item_comments",
        item_id: id,
      },
      function (res) {
        if (currentToken !== commentLoadToken) {
          return;
        }

        itemDetailModalState.comments = Array.isArray(res.comments)
          ? res.comments.slice()
          : [];
        itemDetailModalState.commentsLoading = false;
        closeReplyComposer();
        closeEditComposer();
        closeReplyEditComposer();
        closeCommentActionMenus();
        renderItemHistoryPanels();
        refreshVisibleReplyDraftNotices();
        refreshVisibleEditCommentDraftNotices();
        refreshVisibleEditReplyDraftNotices();
        updateCommentDraftNotice();
      },
      function () {
        if (currentToken !== commentLoadToken) {
          return;
        }
        itemDetailModalState.commentsLoading = false;
        renderItemHistoryPanels();
        refreshVisibleReplyDraftNotices();
        refreshVisibleEditCommentDraftNotices();
        refreshVisibleEditReplyDraftNotices();
        updateCommentDraftNotice();
      },
    );
  }

  function refreshVisibleReplyDraftNotices() {
    $(".task-item-reply-draft-notice[data-comment-id]").each(function () {
      var id = Number($(this).data("commentId") || 0);
      if (id > 0) {
        updateReplyDraftNotice(id);
      }
    });
  }

  function refreshVisibleEditCommentDraftNotices() {
    $(".task-item-comment-edit-draft-notice[data-comment-id]").each(
      function () {
        var id = Number($(this).data("commentId") || 0);
        if (id > 0) {
          updateEditCommentDraftNotice(id);
        }
      },
    );
  }

  function refreshVisibleEditReplyDraftNotices() {
    $(".task-item-reply-edit-draft-notice[data-reply-id]").each(function () {
      var id = Number($(this).data("replyId") || 0);
      if (id > 0) {
        updateEditReplyDraftNotice(id);
      }
    });
  }

  function scheduleEditCommentDraftSave(commentId) {
    var id = Number(commentId || 0);
    if (id <= 0) {
      return;
    }

    if (editCommentDraftTimerByCommentId[id]) {
      window.clearTimeout(editCommentDraftTimerByCommentId[id]);
    }

    editCommentDraftTimerByCommentId[id] = window.setTimeout(function () {
      editCommentDraftTimerByCommentId[id] = 0;
      flushEditCommentDraftNow(id);
      clearDraftNoticeFlag(getEditCommentDraftNoticeKey(id));
      updateEditCommentDraftNotice(id);
    }, 220);
  }

  function scheduleEditReplyDraftSave(replyId) {
    var id = Number(replyId || 0);
    if (id <= 0) {
      return;
    }

    if (editReplyDraftTimerByReplyId[id]) {
      window.clearTimeout(editReplyDraftTimerByReplyId[id]);
    }

    editReplyDraftTimerByReplyId[id] = window.setTimeout(function () {
      editReplyDraftTimerByReplyId[id] = 0;
      flushEditReplyDraftNow(id);
      clearDraftNoticeFlag(getEditReplyDraftNoticeKey(id));
      updateEditReplyDraftNotice(id);
    }, 220);
  }

  function submitCommentHtml(commentHtml, afterDone) {
    if (commentSaving) {
      return;
    }

    if (!canEdit) {
      notify("You do not have permission to add comments.");
      return;
    }

    var itemId = Number(itemDetailModalState.itemId || 0);
    if (itemId <= 0) {
      return;
    }

    if (!hasCommentContent(commentHtml)) {
      notify("Comment cannot be empty.");
      updateCommentActionButtons();
      return;
    }

    commentSaving = true;
    updateCommentActionButtons();

    postAction(
      {
        task_action: "create_item_comment",
        item_id: itemId,
        comment_html: commentHtml,
      },
      function (res) {
        commentSaving = false;
        itemDetailModalState.comments = Array.isArray(res.comments)
          ? res.comments.slice()
          : itemDetailModalState.comments;
        clearCommentDraft();
        closeCommentActionMenus();
        renderItemHistoryPanels();
        refreshVisibleReplyDraftNotices();
        refreshVisibleEditCommentDraftNotices();
        refreshVisibleEditReplyDraftNotices();
        setCommentComposerVisible(false, true, false);
        setItemActivityTab("comment");
        loadItemHistory(itemId);

        if (typeof afterDone === "function") {
          afterDone();
        }
      },
      function () {
        commentSaving = false;
        updateCommentActionButtons();
      },
    );
  }

  function submitItemComment() {
    submitCommentHtml(getCommentEditorHtml());
  }

  function submitReplyComment(commentId, replyHtml, afterDone) {
    if (commentSaving) {
      return;
    }

    if (!canEdit) {
      notify("You do not have permission to add replies.");
      return;
    }

    var itemId = Number(itemDetailModalState.itemId || 0);
    var id = Number(commentId || 0);
    if (itemId <= 0 || id <= 0) {
      return;
    }

    if (!hasCommentContent(replyHtml)) {
      notify("Reply cannot be empty.");
      return;
    }

    commentSaving = true;
    updateCommentActionButtons();

    postAction(
      {
        task_action: "create_item_comment_reply",
        item_id: itemId,
        comment_id: id,
        reply_html: replyHtml,
      },
      function (res) {
        commentSaving = false;
        itemDetailModalState.comments = Array.isArray(res.comments)
          ? res.comments.slice()
          : itemDetailModalState.comments;
        clearReplyDraft(id);
        closeCommentActionMenus();
        renderItemHistoryPanels();
        refreshVisibleReplyDraftNotices();
        refreshVisibleEditCommentDraftNotices();
        refreshVisibleEditReplyDraftNotices();
        setItemActivityTab("comment");
        loadItemHistory(itemId);

        if (typeof afterDone === "function") {
          afterDone();
        }
      },
      function () {
        commentSaving = false;
        updateCommentActionButtons();
      },
    );
  }

  function submitEditedComment(commentId, commentHtml, afterDone) {
    if (commentSaving) {
      return;
    }

    if (!canEdit) {
      notify("You do not have permission to edit comments.");
      return;
    }

    var itemId = Number(itemDetailModalState.itemId || 0);
    var id = Number(commentId || 0);
    if (itemId <= 0 || id <= 0) {
      return;
    }

    if (!hasCommentContent(commentHtml)) {
      notify("Comment cannot be empty.");
      return;
    }

    commentSaving = true;
    updateCommentActionButtons();

    postAction(
      {
        task_action: "update_item_comment",
        item_id: itemId,
        comment_id: id,
        comment_html: commentHtml,
      },
      function (res) {
        commentSaving = false;
        itemDetailModalState.comments = Array.isArray(res.comments)
          ? res.comments.slice()
          : itemDetailModalState.comments;
        clearDraftCookie(getEditCommentDraftKey(id));
        clearDraftNoticeFlag(getEditCommentDraftNoticeKey(id));
        closeCommentActionMenus();
        renderItemHistoryPanels();
        setItemActivityTab("comment");

        if (typeof afterDone === "function") {
          afterDone();
        }
      },
      function () {
        commentSaving = false;
        updateCommentActionButtons();
      },
    );
  }

  function findReplyRecord(replyId) {
    var id = Number(replyId || 0);
    if (id <= 0) {
      return null;
    }

    var commentRows = Array.isArray(itemDetailModalState.comments)
      ? itemDetailModalState.comments
      : [];
    for (var i = 0; i < commentRows.length; i++) {
      var commentRow = commentRows[i] || {};
      var replies = Array.isArray(commentRow.replies) ? commentRow.replies : [];
      for (var j = 0; j < replies.length; j++) {
        var replyRow = replies[j] || {};
        if (Number(replyRow.id || 0) === id) {
          return {
            reply: replyRow,
            commentId: Number(commentRow.id || 0),
            comment: commentRow,
          };
        }
      }
    }

    return null;
  }

  function submitEditedReply(replyId, replyHtml, afterDone) {
    if (commentSaving) {
      return;
    }

    if (!canEdit) {
      notify("You do not have permission to edit replies.");
      return;
    }

    var itemId = Number(itemDetailModalState.itemId || 0);
    var id = Number(replyId || 0);
    if (itemId <= 0 || id <= 0) {
      return;
    }

    if (!hasCommentContent(replyHtml)) {
      notify("Reply cannot be empty.");
      return;
    }

    commentSaving = true;
    updateCommentActionButtons();

    postAction(
      {
        task_action: "update_item_comment_reply",
        item_id: itemId,
        reply_id: id,
        reply_html: replyHtml,
      },
      function (res) {
        commentSaving = false;
        itemDetailModalState.comments = Array.isArray(res.comments)
          ? res.comments.slice()
          : itemDetailModalState.comments;
        clearDraftCookie(getEditReplyDraftKey(id));
        clearDraftNoticeFlag(getEditReplyDraftNoticeKey(id));
        closeCommentActionMenus();
        renderItemHistoryPanels();
        setItemActivityTab("comment");
        loadItemHistory(itemId);

        if (typeof afterDone === "function") {
          afterDone();
        }
      },
      function () {
        commentSaving = false;
        updateCommentActionButtons();
      },
    );
  }

  function openReplyEditComposer(replyId, triggerEl, options) {
    var id = Number(replyId || 0);
    var settings = options && typeof options === "object" ? options : {};
    if (id <= 0) {
      return;
    }

    var $entry = findReplyEntry(id, triggerEl);
    if (!$entry.length) {
      return;
    }

    var currentOpen = Number(openEditReplyId || 0);
    if (
      currentOpen === id &&
      openEditReplyEntryRef &&
      openEditReplyEntryRef.is($entry)
    ) {
      var existingEditor = getReplyEditEditorInstance(id);
      if (existingEditor) {
        existingEditor.focus();
      }
      return;
    }

    closeReplyComposer();
    closeEditComposer();
    closeReplyEditComposer();

    var record = findReplyRecord(id);
    if (!record || !record.reply) {
      notify("Reply not found.");
      return;
    }

    var $slot = $entry
      .find('.task-item-comment-reply-edit-slot[data-reply-id="' + id + '"]')
      .first();
    if (!$slot.length) {
      $slot = $entry.find(".task-item-comment-reply-edit-slot").first();
    }
    if (!$slot.length) {
      return;
    }

    var replyRow = record.reply || {};
    var initialHtml = String(replyRow.reply_html || "").trim();
    if (!initialHtml) {
      initialHtml = "<p>" + escHtml(String(replyRow.reply_text || "")) + "</p>";
    }
    if (typeof settings.draftHtml === "string") {
      initialHtml = settings.draftHtml;
    } else {
      var storedEditReplyDraft = String(
        getDraftCookie(getEditReplyDraftKey(id)) || "",
      );
      var shouldRecoverEditReplyDraft =
        !!storedEditReplyDraft.trim() &&
        hasDraftNoticeFlag(getEditReplyDraftNoticeKey(id));
      if (shouldRecoverEditReplyDraft) {
        initialHtml = storedEditReplyDraft;
        clearDraftNoticeFlag(getEditReplyDraftNoticeKey(id));
      }
    }

    var replyEditEditorId = getReplyEditEditorId(id);
    var html =
      '<div id="taskItemReplyEditBox_' +
      id +
      '" class="task-item-comment-edit-box" data-reply-id="' +
      id +
      '">' +
      '<textarea id="' +
      replyEditEditorId +
      '" rows="3" placeholder="Edit your reply"></textarea>' +
      '<div class="task-item-comment-edit-actions mt-2">' +
      '<button type="button" class="btn btn-primary btn-sm task-item-reply-edit-save-btn" data-reply-id="' +
      id +
      '">Save</button>' +
      '<button type="button" class="btn btn-light btn-sm task-item-reply-edit-cancel-btn" data-reply-id="' +
      id +
      '">Cancel</button>' +
      "</div>" +
      "</div>";

    $slot.html(html);
    $("#" + replyEditEditorId).val(initialHtml);
    openEditReplyId = id;
    openEditReplyEntryRef = $entry;

    ensureReplyEditEditorReady(id, initialHtml).then(function (editor) {
      if (editor) {
        editor.setContent(initialHtml);
        editor.focus();
      }
      updateEditReplyDraftNotice(id);
    });
  }

  function openEditComposer(commentId, triggerEl, options) {
    var id = Number(commentId || 0);
    var settings = options && typeof options === "object" ? options : {};
    if (id <= 0) {
      return;
    }

    var $entry = findCommentEntry(id, triggerEl);
    if (!$entry.length) {
      return;
    }

    var currentOpen = Number(openEditCommentId || 0);
    if (currentOpen === id && openEditEntryRef && openEditEntryRef.is($entry)) {
      var existingEditor = getEditEditorInstance(id);
      if (existingEditor) {
        existingEditor.focus();
      }
      return;
    }

    closeReplyComposer();
    closeEditComposer();
    closeReplyEditComposer();

    var commentRows = Array.isArray(itemDetailModalState.comments)
      ? itemDetailModalState.comments
      : [];
    var current = null;
    for (var i = 0; i < commentRows.length; i++) {
      var row = commentRows[i] || {};
      if (Number(row.id || 0) === id) {
        current = row;
        break;
      }
    }

    if (!current) {
      notify("Comment not found.");
      return;
    }

    var $slot = $entry
      .find('.task-item-comment-edit-slot[data-comment-id="' + id + '"]')
      .first();
    if (!$slot.length) {
      $slot = $entry.find(".task-item-comment-edit-slot").first();
    }
    if (!$slot.length) {
      return;
    }

    var initialHtml = String(current.comment_html || "").trim();
    if (!initialHtml) {
      initialHtml =
        "<p>" + escHtml(String(current.comment_text || "")) + "</p>";
    }
    if (typeof settings.draftHtml === "string") {
      initialHtml = settings.draftHtml;
    } else {
      var storedEditDraft = String(getDraftCookie(getEditCommentDraftKey(id)) || "");
      var shouldRecoverEditDraft =
        !!storedEditDraft.trim() &&
        hasDraftNoticeFlag(getEditCommentDraftNoticeKey(id));
      if (shouldRecoverEditDraft) {
        initialHtml = storedEditDraft;
        clearDraftNoticeFlag(getEditCommentDraftNoticeKey(id));
        updateEditCommentDraftNotice(id);
      }
    }

    var editEditorId = getEditEditorId(id);
    var html =
      '<div id="taskItemCommentEditBox_' +
      id +
      '" class="task-item-comment-edit-box" data-comment-id="' +
      id +
      '">' +
      '<textarea id="' +
      editEditorId +
      '" rows="4" placeholder="Edit your comment"></textarea>' +
      '<div class="task-item-comment-edit-actions mt-2">' +
      '<button type="button" class="btn btn-primary btn-sm task-item-comment-edit-save-btn" data-comment-id="' +
      id +
      '">Save</button>' +
      '<button type="button" class="btn btn-light btn-sm task-item-comment-edit-cancel-btn" data-comment-id="' +
      id +
      '">Cancel</button>' +
      "</div>" +
      "</div>";

    $slot.html(html);
    $("#" + editEditorId).val(initialHtml);
    openEditCommentId = id;
    openEditEntryRef = $entry;

    ensureEditEditorReady(id, initialHtml).then(function (editor) {
      if (editor) {
        editor.setContent(initialHtml);
        editor.focus();
      }
    });
  }

  function openReplyComposer(commentId, actorName, triggerEl) {
    var id = Number(commentId || 0);
    if (id <= 0) {
      return;
    }

    var $entry = findCommentEntry(id, triggerEl);
    if (!$entry.length) {
      return;
    }

    var currentOpen = Number(openReplyCommentId || 0);
    if (
      currentOpen === id &&
      openReplyEntryRef &&
      openReplyEntryRef.is($entry)
    ) {
      var existingEditor = getReplyEditorInstance(id);
      if (existingEditor) {
        existingEditor.focus();
      }
      return;
    }

    closeEditComposer();
    closeReplyComposer();
    closeReplyEditComposer();

    var $slot = $entry
      .find('.task-item-comment-reply-slot[data-comment-id="' + id + '"]')
      .first();
    if (!$slot.length) {
      $slot = $entry.find(".task-item-comment-reply-slot").first();
    }
    if (!$slot.length) {
      return;
    }

    var storedReplyDraft = String(getDraftCookie(getReplyDraftKey(id)) || "");
    var shouldRecoverReplyDraft =
      !!storedReplyDraft.trim() && hasDraftNoticeFlag(getReplyDraftNoticeKey(id));
    if (shouldRecoverReplyDraft) {
      clearDraftNoticeFlag(getReplyDraftNoticeKey(id));
    }

    var replyEditorId = getReplyEditorId(id);
    var html =
      '<div id="taskItemCommentReplyBox_' +
      id +
      '" class="task-item-comment-reply-box" data-comment-id="' +
      id +
      '">' +
      '<div class="task-item-comment-reply-title">Replying to ' +
      escHtml(actorName || "User") +
      "</div>" +
      '<textarea id="' +
      replyEditorId +
      '" rows="3" placeholder="Type @ to mention and notify someone."></textarea>' +
      '<div class="task-item-comment-reply-actions mt-2">' +
      '<button type="button" class="btn btn-primary btn-sm task-item-comment-reply-save-btn" data-comment-id="' +
      id +
      '">Save</button>' +
      '<button type="button" class="btn btn-light btn-sm task-item-comment-reply-cancel-btn" data-comment-id="' +
      id +
      '">Cancel</button>' +
      "</div>" +
      "</div>";

    $slot.html(html);
    openReplyCommentId = id;
    openReplyEntryRef = $entry;

    ensureReplyEditorReady(id, actorName).then(function (editor) {
      if (editor) {
        if (shouldRecoverReplyDraft) {
          editor.setContent(storedReplyDraft);
        }
        editor.focus();
      }
      updateReplyDraftNotice(id);
    });
  }

  function copyCommentPermalink(commentId) {
    var itemId = Number(itemDetailModalState.itemId || 0);
    var id = Number(commentId || 0);
    if (itemId <= 0 || id <= 0) {
      return;
    }

    var permalink = buildCommentPermalink(itemId, id);
    var fallbackCopy = function () {
      var $temp = $('<input type="text" readonly>');
      $("body").append($temp);
      $temp.val(permalink).trigger("focus").trigger("select");
      document.execCommand("copy");
      $temp.remove();
      notify("Comment link copied.");
    };

    if (
      navigator.clipboard &&
      typeof navigator.clipboard.writeText === "function"
    ) {
      navigator.clipboard
        .writeText(permalink)
        .then(function () {
          notify("Comment link copied.");
        })
        .catch(function () {
          fallbackCopy();
        });
      return;
    }

    fallbackCopy();
  }

  function copyReplyPermalink(replyId) {
    var itemId = Number(itemDetailModalState.itemId || 0);
    var id = Number(replyId || 0);
    if (itemId <= 0 || id <= 0) {
      return;
    }

    var permalink = buildReplyPermalink(itemId, id);
    var fallbackCopy = function () {
      var $temp = $('<input type="text" readonly>');
      $("body").append($temp);
      $temp.val(permalink).trigger("focus").trigger("select");
      document.execCommand("copy");
      $temp.remove();
      notify("Reply link copied.");
    };

    if (
      navigator.clipboard &&
      typeof navigator.clipboard.writeText === "function"
    ) {
      navigator.clipboard
        .writeText(permalink)
        .then(function () {
          notify("Reply link copied.");
        })
        .catch(function () {
          fallbackCopy();
        });
      return;
    }

    fallbackCopy();
  }

  function deleteComment(commentId) {
    if (!canEdit) {
      notify("You do not have permission to delete comments.");
      return;
    }

    var itemId = Number(itemDetailModalState.itemId || 0);
    var id = Number(commentId || 0);
    if (itemId <= 0 || id <= 0) {
      return;
    }

    if (!window.confirm("Delete this comment?")) {
      return;
    }

    postAction(
      {
        task_action: "delete_item_comment",
        item_id: itemId,
        comment_id: id,
      },
      function (res) {
        itemDetailModalState.comments = Array.isArray(res.comments)
          ? res.comments.slice()
          : [];
        closeReplyEditComposer();
        closeEditComposer();
        closeReplyComposer();
        closeCommentActionMenus();
        renderItemHistoryPanels();
        loadItemHistory(itemId);
      },
    );
  }

  function deleteReply(replyId) {
    if (!canEdit) {
      notify("You do not have permission to delete replies.");
      return;
    }

    var itemId = Number(itemDetailModalState.itemId || 0);
    var id = Number(replyId || 0);
    if (itemId <= 0 || id <= 0) {
      return;
    }

    if (!window.confirm("Delete this reply?")) {
      return;
    }

    postAction(
      {
        task_action: "delete_item_comment_reply",
        item_id: itemId,
        reply_id: id,
      },
      function (res) {
        itemDetailModalState.comments = Array.isArray(res.comments)
          ? res.comments.slice()
          : [];
        closeReplyEditComposer();
        closeEditComposer();
        closeReplyComposer();
        closeCommentActionMenus();
        renderItemHistoryPanels();
        loadItemHistory(itemId);
      },
    );
  }

  $(document).on("click", "#taskItemCommentSaveBtn", function () {
    submitItemComment();
  });

  $(document).on("click", "#taskItemCommentComposeLauncher", function () {
    setCommentComposerVisible(true, false, true);
  });

  $(document).on("keydown", "#taskItemCommentComposeLauncher", function (e) {
    var key = String(e.key || "").toLowerCase();
    if (key === "enter" || key === " ") {
      e.preventDefault();
      setCommentComposerVisible(true, false, true);
    }
  });

  $(document).on("click", "#taskItemCommentCancelBtn", function () {
    clearCommentDraft();
    setCommentComposerVisible(false, true, false);
  });

  $(document).on("click", "#taskItemCommentDraftRestoreBtn", function () {
    restoreCommentDraft();
  });

  $(document).on("keydown", "#taskItemCommentDraftRestoreBtn", function (e) {
    var key = String(e.key || "").toLowerCase();
    if (key === "enter" || key === " ") {
      e.preventDefault();
      restoreCommentDraft();
    }
  });

  $(document).on("click", ".task-item-comment-reply-btn", function () {
    var commentId = Number($(this).data("commentId") || 0);
    var actorName = String(
      $(this).closest(".task-item-comment-entry").data("actorName") || "User",
    );
    openReplyComposer(commentId, actorName, this);
  });

  $(document).on("click", ".task-item-comment-edit-btn", function () {
    openEditComposer(Number($(this).data("commentId") || 0), this);
  });

  $(document).on("click", ".task-item-reply-edit-btn", function () {
    openReplyEditComposer(Number($(this).data("replyId") || 0), this);
  });

  $(document).on("click", ".task-item-comment-reply-cancel-btn", function () {
    var commentId = Number($(this).data("commentId") || 0);
    if (commentId > 0) {
      clearReplyDraft(commentId);
    }
    closeReplyComposer();
  });

  $(document).on("click", ".task-item-comment-edit-cancel-btn", function () {
    closeEditComposer();
  });

  $(document).on("click", ".task-item-reply-edit-cancel-btn", function () {
    closeReplyEditComposer();
  });

  $(document).on("click", ".task-item-comment-reply-save-btn", function () {
    var commentId = Number($(this).data("commentId") || 0);
    if (commentId <= 0) {
      return;
    }

    var editor = getReplyEditorInstance(commentId);
    var html = editor ? String(editor.getContent() || "") : "";
    submitReplyComment(commentId, html, function () {
      closeReplyComposer();
    });
  });

  $(document).on("click", ".task-item-comment-edit-save-btn", function () {
    var commentId = Number($(this).data("commentId") || 0);
    if (commentId <= 0) {
      return;
    }

    var editor = getEditEditorInstance(commentId);
    var html = editor ? String(editor.getContent() || "") : "";
    submitEditedComment(commentId, html, function () {
      closeEditComposer();
    });
  });

  $(document).on("click", ".task-item-reply-edit-save-btn", function () {
    var replyId = Number($(this).data("replyId") || 0);
    if (replyId <= 0) {
      return;
    }

    var editor = getReplyEditEditorInstance(replyId);
    var html = editor ? String(editor.getContent() || "") : "";
    submitEditedReply(replyId, html, function () {
      closeReplyEditComposer();
    });
  });

  $(document).on("click", ".task-item-comment-more-btn", function (e) {
    e.preventDefault();
    e.stopPropagation();

    var $btn = $(this);
    var $menu = $btn.siblings(".task-item-comment-more-menu");
    if (!$menu.length) {
      return;
    }

    var shouldOpen = $menu.hasClass("d-none");
    closeCommentActionMenus();
    if (shouldOpen) {
      $menu.removeClass("d-none");
      $btn.addClass("is-open");
    }
  });

  $(document).on("click", ".task-item-reply-more-btn", function (e) {
    e.preventDefault();
    e.stopPropagation();

    var $btn = $(this);
    var $menu = $btn.siblings(".task-item-reply-more-menu");
    if (!$menu.length) {
      return;
    }

    var shouldOpen = $menu.hasClass("d-none");
    closeCommentActionMenus();
    if (shouldOpen) {
      $menu.removeClass("d-none");
      $btn.addClass("is-open");
    }
  });

  $(document).on("click", ".task-item-comment-copy-link-btn", function (e) {
    e.preventDefault();
    e.stopPropagation();
    copyCommentPermalink($(this).data("commentId"));
    closeCommentActionMenus();
  });

  $(document).on("click", ".task-item-comment-delete-btn", function (e) {
    e.preventDefault();
    e.stopPropagation();
    deleteComment($(this).data("commentId"));
    closeCommentActionMenus();
  });

  $(document).on("click", ".task-item-reply-copy-link-btn", function (e) {
    e.preventDefault();
    e.stopPropagation();
    copyReplyPermalink($(this).data("replyId"));
    closeCommentActionMenus();
  });

  $(document).on("click", ".task-item-reply-delete-btn", function (e) {
    e.preventDefault();
    e.stopPropagation();
    deleteReply($(this).data("replyId"));
    closeCommentActionMenus();
  });

  $(document).on("click", function (e) {
    if ($(e.target).closest(".task-item-comment-more-wrap").length) {
      return;
    }
    closeCommentActionMenus();
  });

  $(document).on(
    "click",
    "#taskSimpleLinkCancelBtn, #taskSimpleLinkCloseBtn, #taskSimpleLinkBackdrop",
    function () {
      closeSimpleLinkPopup();
    },
  );

  $(document).on("click", "#taskSimpleLinkSaveBtn", function () {
    saveSimpleLinkPopup();
  });

  $(document).on(
    "keydown",
    "#taskSimpleLinkUrlInput, #taskSimpleLinkTextInput",
    function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        saveSimpleLinkPopup();
        return;
      }

      if (e.key === "Escape") {
        e.preventDefault();
        closeSimpleLinkPopup();
      }
    },
  );

  $(document).on("task:historyPanelsRendered", function () {
    refreshVisibleReplyDraftNotices();
    refreshVisibleEditCommentDraftNotices();
    refreshVisibleEditReplyDraftNotices();
    updateCommentDraftNotice();
    updateDescriptionDraftNotice();
  });

  $(document).on("shown.bs.modal", "#taskItemDetailModal", function () {
    if (typeof window.syncTaskItemDetailMobileLayout === "function") {
      window.syncTaskItemDetailMobileLayout();
    }

    draftItemContextId = Number(itemDetailModalState.itemId || 0);
    descriptionDraftClearedByUser = false; // reset on each new modal open
    capturedInitialDescription = String(
      itemDetailModalState.initialDescription || ""
    );
    ensureCommentEditorReady().then(function () {
      setCommentComposerVisible(false, true, false);
      updateCommentActionButtons();
      updateCommentDraftNotice();
    });

    updateDescriptionDraftNotice();
    refreshVisibleReplyDraftNotices();
    refreshVisibleEditCommentDraftNotices();
    refreshVisibleEditReplyDraftNotices();

    if (
      Number(itemDetailModalState.itemId || 0) > 0 &&
      !itemDetailModalState.commentsLoading &&
      (!Array.isArray(itemDetailModalState.comments) ||
        itemDetailModalState.comments.length === 0)
    ) {
      loadItemComments(Number(itemDetailModalState.itemId || 0));
    }
  });

  $(document).on(
    "click",
    '.task-item-activity-tab[data-tab-target="comment"]',
    function () {
      ensureCommentEditorReady().then(function (editor) {
        updateCommentActionButtons();
        updateCommentDraftNotice();
        refreshVisibleReplyDraftNotices();
        refreshVisibleEditCommentDraftNotices();
        refreshVisibleEditReplyDraftNotices();
        if (editor && isCommentComposerVisible()) {
          editor.focus();
        }
      });
    },
  );

  $(document).on("input", "#taskItemCommentEditor", function () {
    scheduleCommentDraftSave();
    updateCommentActionButtons();
  });

  $(document).on("input", "#taskItemDetailDescriptionInput", function () {
    scheduleDescriptionDraftSave();
  });

  $(document).on("hidden.bs.modal", "#taskItemDetailModal", function () {
    commentSaving = false;
    commentLoadToken += 1;
    var commentHasDraft = flushCommentDraftNow({
      preserveExistingNotice: true,
    });
    var descriptionHasDraft = flushDescriptionDraftNow({
      preserveExistingNotice: true,
    });
    var openReplyId = Number(openReplyCommentId || 0);
    var replyHasDraft =
      openReplyId > 0 ? flushReplyDraftNow(openReplyId) : false;
    var openEditId = Number(openEditCommentId || 0);
    var editCommentHasDraft =
      openEditId > 0 ? flushEditCommentDraftNow(openEditId) : false;
    var openEditReplyIdValue = Number(openEditReplyId || 0);
    var editReplyHasDraft =
      openEditReplyIdValue > 0
        ? flushEditReplyDraftNow(openEditReplyIdValue)
        : false;

    if (commentHasDraft) {
      setDraftNoticeFlag(getCommentDraftNoticeKey());
    }
    if (descriptionHasDraft) {
      setDraftNoticeFlag(getDescriptionDraftNoticeKey());
    }
    if (replyHasDraft && openReplyId > 0) {
      setDraftNoticeFlag(getReplyDraftNoticeKey(openReplyId));
    }
    if (editCommentHasDraft && openEditId > 0) {
      setDraftNoticeFlag(getEditCommentDraftNoticeKey(openEditId));
    }
    if (editReplyHasDraft && openEditReplyIdValue > 0) {
      setDraftNoticeFlag(getEditReplyDraftNoticeKey(openEditReplyIdValue));
    }

    if (commentDraftTimer) {
      window.clearTimeout(commentDraftTimer);
      commentDraftTimer = 0;
    }
    if (descriptionDraftTimer) {
      window.clearTimeout(descriptionDraftTimer);
      descriptionDraftTimer = 0;
    }
    var replyTimerKeys = Object.keys(replyDraftTimerByCommentId || {});
    for (var i = 0; i < replyTimerKeys.length; i++) {
      var replyTimerId = Number(
        replyDraftTimerByCommentId[replyTimerKeys[i]] || 0,
      );
      if (replyTimerId) {
        window.clearTimeout(replyTimerId);
      }
      replyDraftTimerByCommentId[replyTimerKeys[i]] = 0;
    }
    var editTimerKeys = Object.keys(editCommentDraftTimerByCommentId || {});
    for (var j = 0; j < editTimerKeys.length; j++) {
      var editTimerId = Number(
        editCommentDraftTimerByCommentId[editTimerKeys[j]] || 0,
      );
      if (editTimerId) {
        window.clearTimeout(editTimerId);
      }
      editCommentDraftTimerByCommentId[editTimerKeys[j]] = 0;
    }
    var editReplyTimerKeys = Object.keys(editReplyDraftTimerByReplyId || {});
    for (var k = 0; k < editReplyTimerKeys.length; k++) {
      var editReplyTimerId = Number(
        editReplyDraftTimerByReplyId[editReplyTimerKeys[k]] || 0,
      );
      if (editReplyTimerId) {
        window.clearTimeout(editReplyTimerId);
      }
      editReplyDraftTimerByReplyId[editReplyTimerKeys[k]] = 0;
    }
    setCommentComposerVisible(false, true, false);
    closeCommentActionMenus();
    destroyAllReplyEditEditors();
    destroyAllEditEditors();
    destroyAllReplyEditors();
    updateCommentActionButtons();
    updateCommentDraftNotice();
    updateDescriptionDraftNotice();
    refreshVisibleReplyDraftNotices();
    refreshVisibleEditCommentDraftNotices();
    refreshVisibleEditReplyDraftNotices();
  });

  window.addEventListener("beforeunload", function () {
    if (
      flushCommentDraftNow({
        preserveExistingNotice: true,
      })
    ) {
      setDraftNoticeFlag(getCommentDraftNoticeKey());
    }
    if (
      flushDescriptionDraftNow({
        preserveExistingNotice: true,
      })
    ) {
      setDraftNoticeFlag(getDescriptionDraftNoticeKey());
    }
    if (
      Number(openReplyCommentId || 0) > 0 &&
      flushReplyDraftNow(openReplyCommentId)
    ) {
      setDraftNoticeFlag(getReplyDraftNoticeKey(openReplyCommentId));
    }
    if (
      Number(openEditCommentId || 0) > 0 &&
      flushEditCommentDraftNow(openEditCommentId)
    ) {
      setDraftNoticeFlag(getEditCommentDraftNoticeKey(openEditCommentId));
    }
    if (
      Number(openEditReplyId || 0) > 0 &&
      flushEditReplyDraftNow(openEditReplyId)
    ) {
      setDraftNoticeFlag(getEditReplyDraftNoticeKey(openEditReplyId));
    }
  });

  window.loadItemComments = loadItemComments;
  window.resetTaskCommentEditor = function () {
    setCommentComposerVisible(false, true, false);
    closeCommentActionMenus();
    destroyAllReplyEditEditors();
    destroyAllEditEditors();
    destroyAllReplyEditors();
    updateCommentDraftNotice();
    refreshVisibleReplyDraftNotices();
    refreshVisibleEditCommentDraftNotices();
    refreshVisibleEditReplyDraftNotices();
    updateDescriptionDraftNotice();
  };

  $(document).on("focusin", function (e) {
    if (
      $(e.target).closest(
        ".tox-tinymce-aux, .tox-dialog, .moxman-window, .tam-assetmanager-root",
      ).length
    ) {
      e.stopImmediatePropagation();
    }
  });
})();
