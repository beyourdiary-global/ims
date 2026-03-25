var sorCfg = window.__SOR_IMPORT_CONFIG || {};
var brandToCompanyMap = sorCfg.brandToCompanyMap || {};
var brandNameMap = sorCfg.brandNameMap || {};
var companyNameMap = sorCfg.companyNameMap || {};
var productOptions = Array.isArray(sorCfg.products) ? sorCfg.products : [];
var packageOptions = Array.isArray(sorCfg.packages) ? sorCfg.packages : [];

var page = "Stock Order Request";
var action = "";
checkCurrentPage(page, action);
dropdownMenuDispFix();

function applyServerRenderedValues() {
  var previewForm = document.getElementById("sorImportPreviewForm");
  if (!previewForm) return;

  previewForm.querySelectorAll(".sor-server-value").forEach(function (el) {
    var serverValue = el.getAttribute("data-server-value");
    if (serverValue === null) return;

    if (el.tagName === "SELECT") {
      var matched = false;
      Array.prototype.forEach.call(el.options, function (opt) {
        if (!matched && String(opt.value) === String(serverValue)) {
          el.value = String(serverValue);
          matched = true;
        }
      });
      if (!matched) {
        el.value = "";
      }
    } else {
      el.value = String(serverValue);
    }
  });
}

// Override browser-restored stale values with authoritative values from server output.
applyServerRenderedValues();
window.addEventListener("pageshow", function () {
  setTimeout(applyServerRenderedValues, 0);
});
setTimeout(applyServerRenderedValues, 60);

function syncReceiptField(receiptKey, field, value) {
  document
    .querySelectorAll(".receipt-hidden-" + field + "-" + receiptKey)
    .forEach(function (el) {
      el.value = value;
    });
}

function normalizeText(text) {
  return String(text || "")
    .toLowerCase()
    .replace(/\s+/g, " ")
    .trim();
}

function closeAutocomplete(input) {
  if (!input) return;
  var listId = input.getAttribute("data-list-id");
  if (!listId) return;
  var list = document.getElementById(listId);
  if (list) list.remove();
}

function renderAutocompleteList(input, options, onSelect) {
  closeAutocomplete(input);
  if (!input) return;

  var wrapper = input.closest(".autocomplete");
  if (!wrapper || input.hasAttribute("readonly")) return;

  var keyword = normalizeText(input.value);
  if (keyword === "") return;

  var filtered = (options || [])
    .filter(function (opt) {
      return normalizeText(opt.name).indexOf(keyword) !== -1;
    })
    .slice(0, 20);

  if (filtered.length === 0) return;

  var listId = "searchResult_" + input.id;
  input.setAttribute("data-list-id", listId);

  var ul = document.createElement("ul");
  ul.className = "searchResult";
  ul.id = listId;
  ul.style.width = input.offsetWidth + "px";

  filtered.forEach(function (opt) {
    var li = document.createElement("li");
    li.setAttribute("value", String(opt.id));
    li.textContent = opt.name;
    li.addEventListener("mousedown", function (e) {
      e.preventDefault();
      input.value = opt.name;
      closeAutocomplete(input);
      if (typeof onSelect === "function") onSelect(opt);
    });
    ul.appendChild(li);
  });

  input.after(ul);
}

function clearInlineError(el) {
  if (!el) return;
  el.classList.remove("sor-invalid");
  var receipt = el.getAttribute("data-receipt");
  var field = el.getAttribute("data-field");
  if (!receipt || !field) return;
  var box = document.querySelector(
    '.sor-inline-error[data-receipt="' +
      receipt +
      '"][data-field-err="' +
      field +
      '"]',
  );
  if (box) {
    box.textContent = "";
    box.style.display = "none";
  }
}

function setItemInlineError(input, message) {
  if (!input) return;
  var td = input.closest("td");
  var box = td ? td.querySelector(".sor-item-inline-error") : null;
  if (!box) return;
  box.textContent = String(message || "");
}

function clearItemInlineError(input) {
  if (!input) return;
  input.classList.remove("sor-invalid");
  setItemInlineError(input, "");
}

document.querySelectorAll(".receipt-sync").forEach(function (el) {
  var receiptKey = el.getAttribute("data-receipt");
  var field = el.getAttribute("data-field");
  if (!receiptKey || !field) return;

  var pushValue = function () {
    syncReceiptField(receiptKey, field, el.value || "");
  };

  el.addEventListener("change", pushValue);
  el.addEventListener("input", pushValue);
  el.addEventListener("change", function () {
    clearInlineError(el);
  });
  el.addEventListener("input", function () {
    clearInlineError(el);
  });
  pushValue();
});

function reindexPreviewRows(tbody) {
  if (!tbody) return;
  var groupCounter = {};
  tbody.querySelectorAll("tr.preview-row").forEach(function (row) {
    var groupKey = row.getAttribute("data-package-group") || "";
    var isPackageRow = row.classList.contains("preview-package-row");
    var rowNoCell = row.querySelector(".row-no");
    if (!rowNoCell) return;

    if (isPackageRow) {
      rowNoCell.textContent = "";
      groupCounter[groupKey] = 0;
      return;
    }

    if (!groupCounter.hasOwnProperty(groupKey)) {
      groupCounter[groupKey] = 0;
    }
    groupCounter[groupKey] += 1;
    rowNoCell.textContent = String(groupCounter[groupKey]);
  });
}

function applyGroupQty(groupKey) {
  if (!groupKey) return;
  var qtyField = document.querySelector(
    '.group-qty-field[data-group="' + groupKey + '"]',
  );
  if (!qtyField) return;

  var packageQty = parseInt(qtyField.value || "0", 10);
  if (isNaN(packageQty) || packageQty <= 0) {
    packageQty = 1;
    qtyField.value = "1";
  }

  document
    .querySelectorAll('.group-package-qty[data-group="' + groupKey + '"]')
    .forEach(function (el) {
      el.value = String(packageQty);
    });

  document
    .querySelectorAll('.group-product-qty[data-group="' + groupKey + '"]')
    .forEach(function (qtyInput) {
      var row = qtyInput.closest("tr.preview-product-row");
      if (!row) return;
      var baseInput = row.querySelector(
        '.group-product-base-qty[data-group="' + groupKey + '"]',
      );
      var baseQty = baseInput ? parseInt(baseInput.value || "0", 10) : 1;
      if (isNaN(baseQty) || baseQty <= 0) baseQty = 1;
      qtyInput.value = String(baseQty * packageQty);
    });
}

document.querySelectorAll(".group-qty-field").forEach(function (el) {
  var groupKey = el.getAttribute("data-group") || "";
  var sync = function () {
    applyGroupQty(groupKey);
  };
  el.addEventListener("input", sync);
  el.addEventListener("change", sync);
  sync();
});

document.querySelectorAll(".group-product-qty").forEach(function (el) {
  var groupKey = el.getAttribute("data-group") || "";
  var updateBaseQty = function () {
    var packageQtyField = document.querySelector(
      '.group-qty-field[data-group="' + groupKey + '"]',
    );
    var packageQty = packageQtyField
      ? parseInt(packageQtyField.value || "0", 10)
      : 1;
    if (isNaN(packageQty) || packageQty <= 0) packageQty = 1;

    var productQty = parseInt(el.value || "0", 10);
    if (isNaN(productQty) || productQty <= 0) productQty = packageQty;

    var row = el.closest("tr.preview-product-row");
    if (!row) return;
    var baseInput = row.querySelector(
      '.group-product-base-qty[data-group="' + groupKey + '"]',
    );
    if (baseInput) {
      baseInput.value = String(
        Math.max(1, Math.round(productQty / packageQty)),
      );
    }
  };
  el.addEventListener("input", updateBaseQty);
  el.addEventListener("change", updateBaseQty);
});

document.querySelectorAll(".remove-preview-row").forEach(function (btn) {
  btn.addEventListener("click", function () {
    var row = btn.closest("tr.preview-row");
    if (!row) return;
    var tbody = row.parentElement;
    var removeScope = btn.getAttribute("data-remove-scope") || "product";
    var groupKey = row.getAttribute("data-package-group") || "";

    if (removeScope === "package") {
      if (groupKey !== "") {
        tbody
          .querySelectorAll(
            'tr.preview-row[data-package-group="' + groupKey + '"]',
          )
          .forEach(function (gr) {
            gr.remove();
          });
      } else {
        row.remove();
      }
    } else {
      row.remove();
      if (groupKey !== "") {
        var remainingProducts = tbody.querySelectorAll(
          'tr.preview-product-row[data-package-group="' + groupKey + '"]',
        );
        if (remainingProducts.length === 0) {
          tbody
            .querySelectorAll(
              'tr.preview-package-row[data-package-group="' + groupKey + '"]',
            )
            .forEach(function (headerRow) {
              headerRow.remove();
            });
        }
      }
    }

    reindexPreviewRows(tbody);
  });
});

document.querySelectorAll(".sor-pkg-name-input").forEach(function (input) {
  var groupKey = input.getAttribute("data-group") || "";
  var wrapper = input.closest(".autocomplete");

  var syncPackageGroup = function () {
    if (!groupKey) return;
    var packageName = String(input.value || "").trim();

    document
      .querySelectorAll('.pkg-hidden-name[data-group="' + groupKey + '"]')
      .forEach(function (el) {
        el.value = packageName;
      });

    // Clear package/product linkage so backend can re-resolve from typed names.
    document
      .querySelectorAll('.pkg-hidden-id[data-group="' + groupKey + '"]')
      .forEach(function (el) {
        el.value = "0";
      });
  };

  input.addEventListener("input", syncPackageGroup);
  input.addEventListener("change", syncPackageGroup);
  input.addEventListener("input", function () {
    clearItemInlineError(input);
  });
  input.addEventListener("change", function () {
    clearItemInlineError(input);
  });

  input.addEventListener("input", function () {
    renderAutocompleteList(input, packageOptions, function (opt) {
      document
        .querySelectorAll('.pkg-hidden-id[data-group="' + groupKey + '"]')
        .forEach(function (el) {
          el.value = String(opt.id || 0);
        });
      document
        .querySelectorAll('.pkg-hidden-name[data-group="' + groupKey + '"]')
        .forEach(function (el) {
          el.value = String(opt.name || "");
        });

      var desc = String(opt.item_description || "");
      document
        .querySelectorAll('.group-desc-field[data-group="' + groupKey + '"]')
        .forEach(function (el) {
          el.value = desc;
        });
      document
        .querySelectorAll('.pkg-hidden-desc[data-group="' + groupKey + '"]')
        .forEach(function (el) {
          el.value = desc;
        });

      var brandId = parseInt(opt.brand_id || 0, 10);
      var companyId =
        brandId > 0 && brandToCompanyMap.hasOwnProperty(String(brandId))
          ? parseInt(brandToCompanyMap[String(brandId)] || 0, 10)
          : 0;
      document
        .querySelectorAll('.pkg-brand-hidden[data-group="' + groupKey + '"]')
        .forEach(function (el) {
          el.value = String(brandId > 0 ? brandId : 0);
        });
      document
        .querySelectorAll('.pkg-company-hidden[data-group="' + groupKey + '"]')
        .forEach(function (el) {
          el.value = String(companyId > 0 ? companyId : 0);
        });
    });
  });

  input.addEventListener("blur", function () {
    setTimeout(function () {
      closeAutocomplete(input);
    }, 120);
  });

  document.addEventListener("click", function (e) {
    if (wrapper && !wrapper.contains(e.target)) {
      closeAutocomplete(input);
    }
  });

  syncPackageGroup();
});

document.querySelectorAll(".sor-product-name-input").forEach(function (input) {
  var row = input.closest("tr.preview-product-row");
  if (!row) return;
  var wrapper = input.closest(".autocomplete");
  var productIdHidden = row.querySelector('input[name$="[product_id]"]');
  if (!productIdHidden) return;

  var clearProductId = function () {
    productIdHidden.value = "0";
  };

  input.addEventListener("input", clearProductId);
  input.addEventListener("change", clearProductId);
  input.addEventListener("input", function () {
    clearItemInlineError(input);
  });
  input.addEventListener("change", function () {
    clearItemInlineError(input);
  });

  input.addEventListener("input", function () {
    var groupKey = row.getAttribute("data-package-group") || "";
    var pkgIdInput = row.querySelector(
      '.pkg-hidden-id[data-group="' + groupKey + '"]',
    );
    var pkgId = pkgIdInput ? parseInt(pkgIdInput.value || "0", 10) : 0;

    var allowedIds = null;
    if (pkgId > 0) {
      var pkg = packageOptions.find(function (p) {
        return parseInt(p.id || 0, 10) === pkgId;
      });
      if (pkg && Array.isArray(pkg.product_ids) && pkg.product_ids.length > 0) {
        allowedIds = pkg.product_ids.map(function (id) {
          return parseInt(id, 10);
        });
      }
    }

    var options = productOptions;
    if (Array.isArray(allowedIds) && allowedIds.length > 0) {
      options = productOptions.filter(function (p) {
        return allowedIds.indexOf(parseInt(p.id || 0, 10)) !== -1;
      });
    }

    renderAutocompleteList(input, options, function (opt) {
      productIdHidden.value = String(opt.id || 0);
      input.value = String(opt.name || "");
    });
  });

  input.addEventListener("blur", function () {
    setTimeout(function () {
      closeAutocomplete(input);
    }, 120);
  });

  document.addEventListener("click", function (e) {
    if (wrapper && !wrapper.contains(e.target)) {
      closeAutocomplete(input);
    }
  });
});

var previewForm = document.getElementById("sorImportPreviewForm");
if (previewForm) {
  previewForm.addEventListener("submit", function (e) {
    var submitter = e.submitter || null;
    var actionBtnValue = submitter ? String(submitter.value || "") : "";
    var submitterName = submitter ? String(submitter.name || "") : "";

    // Cancel should always be allowed and should not be blocked by validation.
    if (
      submitterName === "cancelImportBtn" ||
      actionBtnValue === "cancelImport"
    ) {
      return;
    }

    if (actionBtnValue !== "insertStockOrderPdf") {
      return;
    }

    var hasError = false;
    var requiredMsg = {
      warehouse_id: "Warehouse is required.",
      courier_id: "Courier is required.",
      invoice_no: "Invoice is required.",
      invoice_date: "Invoices Date is required.",
      total_price: "Total Price is required.",
    };

    previewForm.querySelectorAll(".receipt-sync").forEach(function (el) {
      var receipt = el.getAttribute("data-receipt");
      var field = el.getAttribute("data-field");
      if (!receipt || !field || !requiredMsg.hasOwnProperty(field)) return;

      var val = String(el.value || "").trim();
      var invalid =
        val === "" || (field === "total_price" && parseFloat(val || "0") <= 0);
      var box = previewForm.querySelector(
        '.sor-inline-error[data-receipt="' +
          receipt +
          '"][data-field-err="' +
          field +
          '"]',
      );

      if (invalid) {
        hasError = true;
        el.classList.add("sor-invalid");
        if (box) {
          box.textContent = requiredMsg[field];
          box.style.display = "block";
        }
      } else {
        el.classList.remove("sor-invalid");
        if (box) {
          box.textContent = "";
          box.style.display = "none";
        }
      }
    });

    previewForm.querySelectorAll(".sor-pkg-name-input").forEach(function (el) {
      var val = String(el.value || "").trim();
      if (val === "") {
        hasError = true;
        el.classList.add("sor-invalid");
        setItemInlineError(el, "Package is required.");
      } else {
        clearItemInlineError(el);
      }
    });

    previewForm
      .querySelectorAll(".sor-product-name-input")
      .forEach(function (el) {
        var val = String(el.value || "").trim();
        if (val === "") {
          hasError = true;
          el.classList.add("sor-invalid");
          setItemInlineError(el, "Product is required.");
        } else {
          clearItemInlineError(el);
        }
      });

    if (hasError) {
      e.preventDefault();
    }
  });
}

document.querySelectorAll(".sor-pkg-select").forEach(function (sel) {
  sel.addEventListener("change", function () {
    var groupKey = sel.getAttribute("data-group") || "";
    var targetId = sel.getAttribute("data-brand-target");
    var target = targetId ? document.getElementById(targetId) : null;
    if (!target) return;

    var option = sel.options[sel.selectedIndex];
    var brandId = option ? option.getAttribute("data-brand-id") || "" : "";
    target.value = brandId !== "" ? brandId : "0";

    var idx = (targetId || "").replace("brand_", "");
    var companyField = document.getElementById("company_" + idx);
    var brandHidden = document.getElementById("brand_hidden_" + idx);
    var companyHidden = document.getElementById("company_hidden_" + idx);
    var descField = document.getElementById("desc_" + idx);

    if (brandHidden) {
      brandHidden.value = brandId !== "" ? brandId : "0";
    }

    var companyId =
      brandId !== "" && brandToCompanyMap.hasOwnProperty(brandId)
        ? String(brandToCompanyMap[brandId])
        : "";
    if (companyHidden) {
      companyHidden.value = companyId !== "" ? companyId : "0";
    }
    if (companyField) {
      if (companyId !== "") {
        var label = companyNameMap.hasOwnProperty(companyId)
          ? companyNameMap[companyId]
          : "";
        companyField.value = companyId + (label ? " - " + label : "");
      } else {
        companyField.value = "";
      }
    }

    if (descField) {
      var itemDesc = option ? option.getAttribute("data-item-desc") || "" : "";
      descField.value = itemDesc;

      if (groupKey !== "") {
        document
          .querySelectorAll('.group-desc-field[data-group="' + groupKey + '"]')
          .forEach(function (el) {
            el.value = itemDesc;
          });
      }
    }

    if (groupKey !== "") {
      var selectedPkgId = sel.value || "";
      var selectedPkgName = option
        ? (option.textContent || "").split(" - ")[0]
        : "";
      var selectedDesc = option
        ? option.getAttribute("data-item-desc") || ""
        : "";

      document
        .querySelectorAll('.pkg-hidden-id[data-group="' + groupKey + '"]')
        .forEach(function (el) {
          el.value = selectedPkgId;
        });
      document
        .querySelectorAll('.pkg-hidden-name[data-group="' + groupKey + '"]')
        .forEach(function (el) {
          el.value = selectedPkgName;
        });
      document
        .querySelectorAll('.pkg-hidden-desc[data-group="' + groupKey + '"]')
        .forEach(function (el) {
          el.value = selectedDesc;
        });
      document
        .querySelectorAll('.pkg-brand-hidden[data-group="' + groupKey + '"]')
        .forEach(function (el) {
          el.value = brandId !== "" ? brandId : "0";
        });
      document
        .querySelectorAll('.pkg-company-hidden[data-group="' + groupKey + '"]')
        .forEach(function (el) {
          el.value = companyId !== "" ? companyId : "0";
        });
    }
  });
});

// Browser OCR (no server setup, no API key). No progress bar UI.
(function () {
  if (typeof pdfjsLib === "undefined" || typeof Tesseract === "undefined")
    return;

  var workerSrc =
    (typeof sorCfg !== "undefined" && sorCfg.pdfjsWorkerSrc) ||
    "/finance/header/js/pdf.worker.min.js";
  if (workerSrc) {
    pdfjsLib.GlobalWorkerOptions.workerSrc = workerSrc;
  }

  var fileInput = document.getElementById("import_file");
  var form = document.getElementById("sorUploadForm");
  var ocrField = document.getElementById("client_ocr_text");
  var ocrMapField = document.getElementById("client_ocr_map");
  var submitBtn = document.getElementById("sorSubmitBtn");
  if (!fileInput || !form || !ocrField || !ocrMapField || !submitBtn) return;

  var ocrRunning = false;

  function setProcessingState(isProcessing, label) {
    ocrRunning = isProcessing;
    submitBtn.disabled = isProcessing;
    submitBtn.innerHTML = isProcessing
      ? '<i class="fa-solid fa-spinner fa-spin"></i> ' + label
      : '<i class="fa-solid fa-wand-magic-sparkles"></i> Load And Analyze';
  }

  function readAsArrayBuffer(file) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onload = function (e) {
        resolve(e.target.result);
      };
      reader.onerror = reject;
      reader.readAsArrayBuffer(file);
    });
  }

  function basenameLower(path) {
    return String(path || "")
      .split("/")
      .pop()
      .split("\\\\")
      .pop()
      .toLowerCase();
  }

  function extractTextFromPdfBytes(pdfBytes) {
    return pdfjsLib
      .getDocument({ data: pdfBytes })
      .promise.then(function (pdfDoc) {
        var tasks = [];
        for (var i = 1; i <= pdfDoc.numPages; i++) {
          tasks.push(
            pdfDoc.getPage(i).then(function (page) {
              var viewport = page.getViewport({ scale: 2.0 });
              var canvas = document.createElement("canvas");
              canvas.width = viewport.width;
              canvas.height = viewport.height;
              var ctx = canvas.getContext("2d");

              return page
                .render({ canvasContext: ctx, viewport: viewport })
                .promise.then(function () {
                  return Tesseract.recognize(canvas, "eng")
                    .then(function (result) {
                      return result && result.data && result.data.text
                        ? result.data.text
                        : "";
                    })
                    .catch(function () {
                      return "";
                    });
                });
            }),
          );
        }

        return Promise.all(tasks).then(function (texts) {
          return texts.join("\n").trim();
        });
      })
      .catch(function () {
        return "";
      });
  }

  function processSinglePdf(file) {
    setProcessingState(true, "Processing PDF...");
    readAsArrayBuffer(file)
      .then(function (buffer) {
        return extractTextFromPdfBytes(new Uint8Array(buffer));
      })
      .then(function (text) {
        ocrField.value = text;
        ocrMapField.value = "";
        setProcessingState(false, "");
      })
      .catch(function () {
        ocrField.value = "";
        ocrMapField.value = "";
        setProcessingState(false, "");
      });
  }

  function processZip(file) {
    if (typeof JSZip === "undefined") {
      ocrField.value = "";
      ocrMapField.value = "";
      return;
    }

    setProcessingState(true, "Processing ZIP...");
    readAsArrayBuffer(file)
      .then(function (buffer) {
        return JSZip.loadAsync(buffer);
      })
      .then(function (zip) {
        var entryNames = Object.keys(zip.files).filter(function (name) {
          var zf = zip.files[name];
          return zf && !zf.dir && /\.pdf$/i.test(name);
        });

        var ocrMap = {};
        var chain = Promise.resolve();

        entryNames.forEach(function (name, idx) {
          chain = chain.then(function () {
            setProcessingState(
              true,
              "Processing ZIP (" + (idx + 1) + "/" + entryNames.length + ")...",
            );
            return zip.files[name]
              .async("uint8array")
              .then(function (pdfBytes) {
                return extractTextFromPdfBytes(pdfBytes).then(function (text) {
                  ocrMap[name.toLowerCase()] = text;
                  ocrMap[basenameLower(name)] = text;
                });
              });
          });
        });

        return chain.then(function () {
          ocrField.value = "";
          ocrMapField.value = JSON.stringify(ocrMap);
          setProcessingState(false, "");
        });
      })
      .catch(function () {
        ocrField.value = "";
        ocrMapField.value = "";
        setProcessingState(false, "");
      });
  }

  fileInput.addEventListener("change", function () {
    ocrField.value = "";
    ocrMapField.value = "";
    setProcessingState(false, "");

    if (!fileInput.files || !fileInput.files[0]) return;

    var file = fileInput.files[0];
    var ext = (file.name || "").split(".").pop().toLowerCase();

    if (ext === "pdf") {
      processSinglePdf(file);
      return;
    }

    if (ext === "zip") {
      processZip(file);
    }
  });

  form.addEventListener("submit", function (e) {
    if (!ocrRunning) return;
    e.preventDefault();
    alert("Please wait. PDF text extraction is still processing.");
  });
})();
