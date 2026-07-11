(function (window) {
  "use strict";

  function amountValue(value) {
    const normalized = String(value || "").replace(/,/g, "").replace(/[^0-9.-]/g, "");
    const parsed = Number.parseFloat(normalized);
    return Number.isFinite(parsed) ? parsed : null;
  }

  function createTextElement(tagName, text, className) {
    const element = document.createElement(tagName);
    if (className) element.className = className;
    element.textContent = text;
    return element;
  }

  window.createSupplierInvoiceQrVerifier = function (options) {
    const endpoint = options.endpoint;
    const qrUrlInput = options.qrUrlInput;
    const amountInput = options.amountInput;
    const detailContainer = options.detailContainer;
    const statusElement = options.statusElement;
    const amountErrorElement = options.amountErrorElement;
    const state = { url: "", details: null, promise: null, hasMismatch: false };

    function setStatus(message, className) {
      if (!statusElement) return;
      statusElement.className = "mt-2 " + className;
      statusElement.textContent = message;
    }

    function setMismatch(message) {
      state.hasMismatch = Boolean(message);
      if (!amountErrorElement) return;
      amountErrorElement.textContent = message || "";
      amountErrorElement.classList.toggle("d-none", !message);
    }

    function compareAmount() {
      if (!state.details || state.details.total_payable_numeric === null || state.details.total_payable_numeric === undefined) {
        setMismatch("");
        return true;
      }

      const totalPayable = amountValue(state.details.total_payable_numeric);
      const enteredAmount = amountValue(amountInput ? amountInput.value : "");
      if (totalPayable === null || enteredAmount === null) {
        setMismatch("");
        return true;
      }
      if (Math.abs(totalPayable - enteredAmount) > 0.009) {
        setMismatch("QR Total Payable Amount (" + state.details.total_payable_amount + ") does not match Total Amount (" + enteredAmount.toFixed(2) + ").");
        return false;
      }

      setMismatch("");
      return true;
    }

    function renderDetails(details) {
      if (!detailContainer) return;
      detailContainer.textContent = "";
      const card = createTextElement("div", "", "border rounded p-3 mt-2 bg-light");
      card.appendChild(createTextElement("div", "QR E-Invoice Details", "fw-bold mb-2"));
      const values = [
        ["e-Invoice No", details.e_invoice_no],
        ["UUID", details.uuid],
        ["Supplier Name", details.supplier_name],
        ["Total Payable Amount", details.total_payable_amount]
      ];
      values.forEach(function (value) {
        const row = document.createElement("div");
        row.className = "mb-1";
        row.appendChild(createTextElement("span", value[0] + ": ", "fw-semibold"));
        row.appendChild(document.createTextNode(value[1] || "-"));
        card.appendChild(row);
      });
      detailContainer.appendChild(card);
    }

    function verify(url) {
      url = String(url || "").trim();
      if (!url) {
        state.url = "";
        state.details = null;
        setMismatch("");
        if (detailContainer) detailContainer.textContent = "";
        return Promise.resolve(false);
      }
      if (state.promise && state.url === url) return state.promise;
      if (state.details && state.url === url) {
        compareAmount();
        return Promise.resolve(true);
      }

      state.url = url;
      state.details = null;
      setMismatch("");
      if (detailContainer) detailContainer.textContent = "";
      setStatus("Retrieving e-invoice details from the QR link...", "mt-2 text-info");
      state.promise = fetch(endpoint, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({ qr_url: url }).toString()
      }).then(function (response) {
        return response.json().catch(function () { return {}; });
      }).then(function (payload) {
        if (!payload || !payload.success || !payload.data) {
          throw new Error((payload && payload.message) || "Unable to retrieve e-invoice details from the QR link.");
        }
        state.details = payload.data;
        renderDetails(state.details);
        compareAmount();
        setStatus("QR e-invoice details retrieved successfully.", "mt-2 text-success");
        return true;
      }).catch(function (error) {
        state.details = null;
        setMismatch("");
        if (detailContainer) detailContainer.textContent = "";
        setStatus((error && error.message) || "Unable to retrieve e-invoice details from the QR link.", "mt-2 text-danger");
        return false;
      }).finally(function () {
        state.promise = null;
      });
      return state.promise;
    }

    function ensureReadyForSubmit() {
      const url = qrUrlInput ? qrUrlInput.value : "";
      if (!url) return Promise.resolve(true);
      if (state.promise) return state.promise.then(function () { return !state.hasMismatch; });
      if (!state.details || state.url !== url) {
        return verify(url).then(function () { return !state.hasMismatch; });
      }
      return Promise.resolve(compareAmount());
    }

    if (amountInput) amountInput.addEventListener("input", compareAmount);
    return { verify: verify, ensureReadyForSubmit: ensureReadyForSubmit, hasMismatch: function () { return state.hasMismatch; } };
  };
})(window);
