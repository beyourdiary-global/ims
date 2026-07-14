(function () {
  const uploadInput = document.getElementById("import_file");
  const insertBtn = document.getElementById("fbInsertAllBtn");

  if (uploadInput && insertBtn) {
    uploadInput.addEventListener("change", function () {
      if (!uploadInput.files || uploadInput.files.length === 0) {
        return;
      }

      // The preview belongs to the file from the previous scan. Require the
      // latest selected file to be analyzed before the old preview can be inserted.
      insertBtn.disabled = true;
      insertBtn.title = "Load And Analyze the latest selected file first.";

      const previewForm = document.getElementById("fbPreviewForm");
      const previewCard = previewForm ? previewForm.closest(".card") : null;
      if (previewCard) {
        previewCard.hidden = true;
      }

      document.querySelectorAll(".alert-danger").forEach(function (alert) {
        alert.hidden = true;
      });
    });
  }

  function renumberReceipts() {
    const cards = document.querySelectorAll(".fb-receipt-card");
    cards.forEach(function (card, idx) {
      const title = card.querySelector(".fb-receipt-title");
      if (!title) {
        return;
      }
      const text = title.textContent || "";
      const fileName = text.replace(/^Receipt\s+\d+\s*:\s*/, "").trim();
      title.textContent = "Receipt " + (idx + 1) + ": " + fileName;
    });

    const insertBtn = document.getElementById("fbInsertAllBtn");
    if (insertBtn) {
      insertBtn.disabled = cards.length === 0;
    }

    // Keep hidden preview summary in sync with current receipts.
    const previewInput = document.querySelector(
      'input[name="fb_import_summary[preview_records]"]',
    );
    if (previewInput) {
      previewInput.value = cards.length;
    }
  }

  document.querySelectorAll(".js-remove-fb-receipt").forEach(function (btn) {
    btn.addEventListener("click", function () {
      const card = btn.closest(".fb-receipt-card");
      if (!card) {
        return;
      }
      card.remove();
      renumberReceipts();
    });
  });

  renumberReceipts();
})();
