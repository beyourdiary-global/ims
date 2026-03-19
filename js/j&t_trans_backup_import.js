checkCurrentPage("<?= $parentPageTitle ?>", "");
dropdownMenuDispFix();

function jtImportCurrencySearch(element) {
  if (!element || !element.id) {
    return;
  }

  var hiddenId =
    "jt_import_currency_hidden_" +
    element.id.replace("jt_import_currency_", "");
  var param = {
    elementID: element.id,
    hiddenElementID: hiddenId,
    search: element.value,
    searchType: "unit",
    dbTable: "<?= CUR_UNIT ?>",
  };
  searchInput(param, "<?= $SITEURL ?>");
}

function toNum(value) {
  var n = parseFloat(value);
  return isNaN(n) ? 0 : n;
}

function recalcPreviewRecord(recordCard) {
  if (!recordCard) {
    return;
  }

  var gstPaidValues = [];
  var totalGst = 0;

  recordCard
    .querySelectorAll(".preview-gst-table tbody tr")
    .forEach(function (row, idx) {
      var rateInput = row.querySelector('input[name*="[gst_rate]"]');
      var amountInput = row.querySelector('input[name*="[gst_amount]"]');
      var paidInput = row.querySelector('input[name*="[gst_paid]"]');

      var rate = toNum(rateInput ? rateInput.value : 0);
      var amount = toNum(amountInput ? amountInput.value : 0);
      var gstPaid = rate > 0 ? amount * (rate / 100) : 0;
      gstPaidValues[idx] = gstPaid;
      totalGst += gstPaid;

      if (paidInput) {
        paidInput.value = gstPaid.toFixed(2);
      }
    });

  var totalAmount = 0;
  recordCard
    .querySelectorAll(".preview-delivery-table tbody tr")
    .forEach(function (row, idx) {
      var standardInput = row.querySelector('input[name*="[standard_charge]"]');
      var nettInput = row.querySelector('input[name*="[nett_charge]"]');

      var standardCharge = toNum(standardInput ? standardInput.value : 0);
      var rowGstPaid = gstPaidValues[idx] || 0;
      var nett = standardCharge + rowGstPaid;
      totalAmount += nett;

      if (nettInput) {
        nettInput.value = nett.toFixed(2);
      }
    });

  var totalGstInput = recordCard.querySelector(".preview-total-gst");
  var totalAmountInput = recordCard.querySelector(".preview-total-amount");
  if (totalGstInput) {
    totalGstInput.value = totalGst.toFixed(2);
  }
  if (totalAmountInput) {
    totalAmountInput.value = totalAmount.toFixed(2);
  }
}

document.querySelectorAll(".record-card").forEach(function (recordCard) {
  recalcPreviewRecord(recordCard);
  recordCard.addEventListener("input", function (e) {
    var target = e.target;
    if (!target) {
      return;
    }
    if (
      target.matches(
        ".preview-gst-rate, .preview-gst-amount, .preview-standard-charge",
      )
    ) {
      recalcPreviewRecord(recordCard);
    }
  });
});

(function () {
  var previewForm = document.getElementById("jtImportPreviewForm");
  if (!previewForm) {
    return;
  }

  function getCurrencySet() {
    var result = {};
    var datalist = document.getElementById("currencyOptionsList");
    if (!datalist) {
      return result;
    }

    datalist.querySelectorAll("option").forEach(function (opt) {
      var value = (opt.value || "").trim().toUpperCase();
      if (value !== "") {
        result[value] = true;
      }
    });

    return result;
  }

  function removeCurrencyError(input) {
    if (!input) {
      return;
    }

    var next = input.nextElementSibling;
    if (
      next &&
      next.classList &&
      next.classList.contains("import-currency-err")
    ) {
      next.remove();
    }
  }

  previewForm.querySelectorAll(".js-import-currency").forEach(function (input) {
    input.addEventListener("input", function () {
      removeCurrencyError(input);
    });
  });

  previewForm.addEventListener("submit", function (e) {
    var valid = true;
    var currencySet = getCurrencySet();

    previewForm
      .querySelectorAll(".js-import-currency")
      .forEach(function (input) {
        removeCurrencyError(input);

        var value = (input.value || "").trim().toUpperCase();
        var message = "";
        if (value === "") {
          message = "Invoice Currency is required.";
        } else if (Object.keys(currencySet).length > 0 && !currencySet[value]) {
          message =
            "Invalid Invoice Currency. Please select a valid currency from the list.";
        }

        if (message !== "") {
          valid = false;
          var err = document.createElement("span");
          err.className = "error-message import-currency-err";
          err.textContent = message;
          input.insertAdjacentElement("afterend", err);
        }
      });

    if (!valid) {
      e.preventDefault();
    }
  });
})();

(function () {
  if (typeof pdfjsLib === "undefined" || typeof Tesseract === "undefined") {
    return;
  }

  pdfjsLib.GlobalWorkerOptions.workerSrc =
    "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";

  var fileInput = document.getElementById("import_file");
  var form = document.getElementById("jtUploadForm");
  var ocrField = document.getElementById("client_ocr_text");
  var ocrMapField = document.getElementById("client_ocr_map");
  var submitBtn = document.getElementById("jtSubmitBtn");
  if (!fileInput || !form || !ocrField || !ocrMapField || !submitBtn) {
    return;
  }

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

    if (!fileInput.files || !fileInput.files[0]) {
      return;
    }

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
    if (!ocrRunning) {
      return;
    }
    e.preventDefault();
    alert("Please wait. PDF text extraction is still processing.");
  });
})();
