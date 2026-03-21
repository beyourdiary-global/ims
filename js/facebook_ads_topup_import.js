(function () {
  function renumberReceipts() {
    var cards = document.querySelectorAll(".fb-receipt-card");
    cards.forEach(function (card, idx) {
      var title = card.querySelector(".fb-receipt-title");
      if (!title) {
        return;
      }
      var text = title.textContent || "";
      var fileName = text.replace(/^Receipt\s+\d+\s*:\s*/, "").trim();
      title.textContent = "Receipt " + (idx + 1) + ": " + fileName;
    });

    var insertBtn = document.getElementById("fbInsertAllBtn");
    if (insertBtn) {
      insertBtn.disabled = cards.length === 0;
    }

    // Keep hidden preview summary in sync with current receipts.
    var previewInput = document.querySelector(
      'input[name="fb_import_summary[preview_records]"]',
    );
    if (previewInput) {
      previewInput.value = cards.length;
    }
  }

  document.querySelectorAll(".js-remove-fb-receipt").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var card = btn.closest(".fb-receipt-card");
      if (!card) {
        return;
      }
      card.remove();
      renumberReceipts();
    });
  });

  renumberReceipts();
})();
