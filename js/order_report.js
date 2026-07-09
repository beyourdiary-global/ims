(function () {
  var pageState = (window.__orderReportPageRuntime =
    window.__orderReportPageRuntime || {
      initialized: false,
      documentMouseDownBound: false,
      trendChart: null,
      rankingChart: null,
    });

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function parseJsonAttribute(element, attrName, fallbackValue) {
    if (!element) {
      return fallbackValue;
    }

    var rawValue = element.getAttribute(attrName);
    if (!rawValue) {
      return fallbackValue;
    }

    try {
      return JSON.parse(rawValue);
    } catch (error) {
      return fallbackValue;
    }
  }

  function formatAmount(value) {
    var numericValue = Number(value || 0);
    if (Number.isNaN(numericValue)) {
      numericValue = 0;
    }

    return numericValue.toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  function formatQuantity(value) {
    var numericValue = Number(value || 0);
    if (Number.isNaN(numericValue)) {
      numericValue = 0;
    }

    if (Math.abs(numericValue % 1) < 0.0000001) {
      return numericValue.toLocaleString(undefined, {
        maximumFractionDigits: 0,
      });
    }

    return numericValue.toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  function getPackageMetricDimensions(config) {
    var dimensions = (config && config.package_metric_dimensions) || [
      "package",
      "brand",
      "warehouse",
    ];
    return Array.isArray(dimensions) ? dimensions : ["package", "brand", "warehouse"];
  }

  function getBreakdownColumns(selectedKey, config) {
    if (getPackageMetricDimensions(config).indexOf(selectedKey) !== -1) {
      return [
        { key: "group", label: "Group", formatter: null },
        { key: "order_count", label: "Total Orders", formatter: null },
        { key: "total_price", label: "Total Price", formatter: formatAmount },
        { key: "total_cost", label: "Total Cost", formatter: formatAmount },
        {
          key: "total_agent_cost",
          label: "Total Agent Cost",
          formatter: formatAmount,
        },
        {
          key: "total_product_quantity",
          label: "Total Product Quantity",
          formatter: formatQuantity,
        },
      ];
    }

    return [
      { key: "group", label: "Group", formatter: null },
      { key: "order_count", label: "Total Orders", formatter: null },
      {
        key: "final_amount",
        label: "Total Sales / Final Amount",
        formatter: formatAmount,
      },
      { key: "voucher", label: "Total Voucher", formatter: formatAmount },
      {
        key: "service_fee",
        label: "Total Service Fee",
        formatter: formatAmount,
      },
      {
        key: "transaction_fee",
        label: "Total Transaction Fee",
        formatter: formatAmount,
      },
      {
        key: "aws_commission_fee",
        label: "Total AWS Commission Fee",
        formatter: formatAmount,
      },
      {
        key: "charges_and_fees",
        label: "Total Charges & Fees",
        formatter: formatAmount,
      },
      {
        key: "final_commission_fees",
        label: "Total Final Commission Fees",
        formatter: formatAmount,
      },
    ];
  }

  function destroyChart(chartRef) {
    if (chartRef && typeof chartRef.destroy === "function") {
      chartRef.destroy();
    }

    return null;
  }

  function getChartOptions(extraOptions) {
    return Object.assign(
      {
        responsive: true,
        maintainAspectRatio: false,
        resizeDelay: 150,
        animation: false,
        transitions: {
          active: {
            animation: {
              duration: 0,
            },
          },
          resize: {
            animation: {
              duration: 0,
            },
          },
          show: {
            animation: {
              duration: 0,
            },
          },
          hide: {
            animation: {
              duration: 0,
            },
          },
        },
      },
      extraOptions || {},
    );
  }

  function wrapChartLabel(value, maxLineLength) {
    var text = String(value == null ? "" : value).trim();
    var limit = Number(maxLineLength || 28);
    if (!text || text.length <= limit) {
      return text;
    }

    var words = text.split(/\s+/);
    var lines = [];
    var currentLine = "";

    words.forEach(function (word) {
      if (!currentLine) {
        currentLine = word;
        return;
      }

      if ((currentLine + " " + word).length <= limit) {
        currentLine += " " + word;
        return;
      }

      lines.push(currentLine);
      currentLine = word;
    });

    if (currentLine) {
      lines.push(currentLine);
    }

    return lines.length > 1 ? lines : text;
  }

  function createOrderReportMultiSelect(container) {
    if (!container || container.getAttribute("data-order-report-ready") === "1") {
      return;
    }

    var options = parseJsonAttribute(container, "data-options", []);
    var selectedValues = parseJsonAttribute(container, "data-selected", []);
    var fieldName = container.getAttribute("data-name") || "";
    var placeholderLabel =
      container.getAttribute("data-placeholder") || "All";
    var placeholderValue = "";

    container.innerHTML = "";
    container.classList.add("customer-record-multiselect");
    container.setAttribute("data-order-report-ready", "1");

    var toggle = document.createElement("button");
    toggle.type = "button";
    toggle.className =
      "campaign-filter-toggle customer-record-filter-multiselect-toggle";
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("data-placeholder", placeholderLabel);

    var toggleText = document.createElement("span");
    toggleText.className = "customer-record-filter-multiselect-text";
    toggle.appendChild(toggleText);

    var menu = document.createElement("div");
    menu.className = "dropdown-menu customer-record-filter-multiselect-menu";

    var list = document.createElement("div");
    list.className = "customer-record-filter-multiselect-list";

    function closeMenu() {
      container.classList.remove("is-open");
      toggle.setAttribute("aria-expanded", "false");
    }

    function openMenu() {
      document
        .querySelectorAll(".customer-record-multiselect.is-open")
        .forEach(function (openContainer) {
          if (openContainer !== container) {
            openContainer.classList.remove("is-open");
            var openToggle = openContainer.querySelector(
              ".customer-record-filter-multiselect-toggle",
            );
            if (openToggle) {
              openToggle.setAttribute("aria-expanded", "false");
            }
          }
        });

      container.classList.add("is-open");
      toggle.setAttribute("aria-expanded", "true");
    }

    function getCheckboxes() {
      return Array.prototype.slice.call(
        list.querySelectorAll(".customer-record-filter-multiselect-checkbox"),
      );
    }

    function syncAllPlaceholderState() {
      var selected = getCheckboxes()
        .filter(function (checkbox) {
          return checkbox.checked && checkbox.value !== placeholderValue;
        })
        .map(function (checkbox) {
          return checkbox.value;
        });

      getCheckboxes().forEach(function (checkbox) {
        if (checkbox.value === placeholderValue) {
          checkbox.checked = selected.length === 0;
        }
      });
    }

    function updateToggleLabel() {
      var labels = getCheckboxes()
        .filter(function (checkbox) {
          return checkbox.checked && checkbox.value !== placeholderValue;
        })
        .map(function (checkbox) {
          return checkbox.getAttribute("data-label") || "";
        })
        .filter(function (value) {
          return value !== "";
        });

      var displayText = labels.length ? labels.join(", ") : placeholderLabel;
      toggleText.textContent = displayText;
      toggleText.classList.toggle("is-placeholder", labels.length === 0);
      toggle.title = displayText;
    }

    function setSelectedValues(values) {
      var valueLookup = {};
      (Array.isArray(values) ? values : []).forEach(function (value) {
        valueLookup[String(value)] = true;
      });

      getCheckboxes().forEach(function (checkbox) {
        checkbox.checked = !!valueLookup[String(checkbox.value)];
      });

      syncAllPlaceholderState();
      updateToggleLabel();
    }

    function appendOption(option) {
      var optionId =
        (fieldName || "order_report") +
        "_" +
        String(option.value === "" ? "all" : option.value)
          .replace(/[^a-z0-9_-]+/gi, "_")
          .toLowerCase();

      var label = document.createElement("label");
      label.className = "form-check customer-record-filter-multiselect-option";
      label.setAttribute("for", optionId);

      var checkbox = document.createElement("input");
      checkbox.type = "checkbox";
      checkbox.className =
        "form-check-input customer-record-filter-multiselect-checkbox";
      checkbox.id = optionId;
      checkbox.name = fieldName;
      checkbox.value = option.value;
      checkbox.setAttribute("data-label", option.label);

      var textNode = document.createElement("span");
      textNode.className =
        "form-check-label customer-record-filter-multiselect-option-text";
      textNode.textContent = option.label;

      label.appendChild(checkbox);
      label.appendChild(textNode);
      list.appendChild(label);
    }

    appendOption({ value: placeholderValue, label: placeholderLabel });
    (Array.isArray(options) ? options : []).forEach(function (option) {
      if (!option || typeof option !== "object") {
        return;
      }

      appendOption({
        value: String(option.value == null ? "" : option.value),
        label: String(option.label == null ? "" : option.label),
      });
    });

    list.addEventListener("change", function (event) {
      var checkbox = event.target;
      if (
        !checkbox ||
        !checkbox.classList.contains(
          "customer-record-filter-multiselect-checkbox",
        )
      ) {
        return;
      }

      if (checkbox.value === placeholderValue && checkbox.checked) {
        getCheckboxes().forEach(function (item) {
          if (item !== checkbox) {
            item.checked = false;
          }
        });
      } else if (checkbox.value !== placeholderValue && checkbox.checked) {
        getCheckboxes().forEach(function (item) {
          if (item.value === placeholderValue) {
            item.checked = false;
          }
        });
      }

      syncAllPlaceholderState();
      updateToggleLabel();
    });

    toggle.addEventListener("click", function (event) {
      event.preventDefault();
      event.stopPropagation();
      if (container.classList.contains("is-open")) {
        closeMenu();
      } else {
        openMenu();
      }
    });

    menu.appendChild(list);
    container.appendChild(toggle);
    container.appendChild(menu);

    setSelectedValues(selectedValues);
  }

    function getSelectedMultiSelectValues(container) {
    if (!container) {
      return [];
    }

    return Array.prototype.slice
      .call(container.querySelectorAll(".customer-record-filter-multiselect-checkbox"))
      .filter(function (checkbox) {
        return checkbox.checked && checkbox.value !== "";
      })
      .map(function (checkbox) {
        return checkbox.value;
      });
  }

  function refreshOrderReportMultiSelect(container, options) {
    if (!container) {
      return;
    }

    var selectedValues = getSelectedMultiSelectValues(container);
    var optionLookup = {};

    (Array.isArray(options) ? options : []).forEach(function (option) {
      optionLookup[String(option.value == null ? "" : option.value)] = true;
    });

    selectedValues = selectedValues.filter(function (value) {
      return optionLookup[String(value)];
    });

    container.setAttribute("data-options", JSON.stringify(Array.isArray(options) ? options : []));
    container.setAttribute("data-selected", JSON.stringify(selectedValues));
    container.removeAttribute("data-order-report-ready");
    container.classList.remove("is-open");
    container.innerHTML = "";

    createOrderReportMultiSelect(container);
  }

  function getCurrentOrderReportPeriodKey(reportType) {
    if (reportType === "monthly") {
      var monthInput = document.getElementById("report_month");
      return monthInput ? monthInput.value || "" : "";
    }

    if (reportType === "yearly") {
      var yearInput = document.getElementById("report_year");
      return yearInput ? yearInput.value || "" : "";
    }

    var dateInput = document.getElementById("report_date");
    return dateInput ? dateInput.value || "" : "";
  }

  function getCurrentOrderReportPeriodParams(reportType) {
    return {
      report_type: reportType || "daily",
      report_date:
        (document.getElementById("report_date") || {}).value || "",
      report_month:
        (document.getElementById("report_month") || {}).value || "",
      report_year:
        (document.getElementById("report_year") || {}).value || "",
    };
  }

  function applyOrderReportFilterOptions(optionSets) {
    var config = window.orderReportPageConfig || {};
    var filterKeys = Array.isArray(config.filter_keys) && config.filter_keys.length
      ? config.filter_keys
      : [
          "package",
          "brand",
          "warehouse",
          "payment",
          "customer_label",
          "segmentation",
          "level",
          "repeat",
        ];

    filterKeys.forEach(function (filterKey) {
      var container = document.getElementById("order_report_" + filterKey);
      refreshOrderReportMultiSelect(container, optionSets[filterKey] || []);
    });
  }

  function fetchOrderReportFilterOptions(reportType, periodKey) {
    var config = window.orderReportPageConfig || {};
    var endpoint = config.option_sets_endpoint || "";
    if (
      !endpoint ||
      !periodKey ||
      typeof window.fetch !== "function" ||
      typeof window.URL !== "function"
    ) {
      return Promise.resolve(null);
    }

    config.option_sets_by_period = config.option_sets_by_period || {};
    config.option_sets_by_period[reportType] =
      config.option_sets_by_period[reportType] || {};

    if (config.option_sets_by_period[reportType][periodKey]) {
      return Promise.resolve(config.option_sets_by_period[reportType][periodKey]);
    }

    pageState.optionSetRequests = pageState.optionSetRequests || {};
    var cacheKey = reportType + ":" + periodKey;
    if (pageState.optionSetRequests[cacheKey]) {
      return pageState.optionSetRequests[cacheKey];
    }

    var params = getCurrentOrderReportPeriodParams(reportType);
    var url = new URL(endpoint, window.location.origin);
    url.searchParams.set("order_report_option_sets", "1");
    Object.keys(params).forEach(function (key) {
      url.searchParams.set(key, params[key] || "");
    });

    pageState.optionSetRequests[cacheKey] = window
      .fetch(url.toString(), {
        credentials: "same-origin",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      })
      .then(function (response) {
        if (!response.ok) {
          throw new Error("Failed to load order report filters.");
        }

        return response.json();
      })
      .then(function (payload) {
        var payloadReportType = payload && payload.report_type ? payload.report_type : reportType;
        var payloadPeriodKey = payload && payload.period_key ? payload.period_key : periodKey;
        var optionSets = payload && payload.option_sets ? payload.option_sets : {};

        config.option_sets_by_period[payloadReportType] =
          config.option_sets_by_period[payloadReportType] || {};
        config.option_sets_by_period[payloadReportType][payloadPeriodKey] = optionSets;
        delete pageState.optionSetRequests[cacheKey];
        return optionSets;
      })
      .catch(function () {
        delete pageState.optionSetRequests[cacheKey];
        return null;
      });

    return pageState.optionSetRequests[cacheKey];
  }

  function syncOrderReportFilterOptions(reportType) {
    var config = window.orderReportPageConfig || {};
    var optionSetsByPeriod = config.option_sets_by_period || {};
    var periodKey = getCurrentOrderReportPeriodKey(reportType);
    var optionSets =
      optionSetsByPeriod[reportType] && optionSetsByPeriod[reportType][periodKey]
        ? optionSetsByPeriod[reportType][periodKey]
        : {};

    if (periodKey && Object.keys(optionSets).length === 0) {
      fetchOrderReportFilterOptions(reportType, periodKey).then(function (
        loadedOptionSets,
      ) {
        var reportTypeInput = document.getElementById("report_type");
        var currentReportType = reportTypeInput
          ? reportTypeInput.value || "daily"
          : "daily";
        if (
          currentReportType !== reportType ||
          getCurrentOrderReportPeriodKey(reportType) !== periodKey ||
          !loadedOptionSets
        ) {
          return;
        }

        applyOrderReportFilterOptions(loadedOptionSets);
      });
      return;
    }

    applyOrderReportFilterOptions(optionSets);
  }

  function initOrderReportMultiSelects() {
    document
      .querySelectorAll(".js-order-report-multiselect")
      .forEach(createOrderReportMultiSelect);

    if (!pageState.documentMouseDownBound) {
      document.addEventListener("mousedown", function (event) {
        document
          .querySelectorAll(".customer-record-multiselect.is-open")
          .forEach(function (container) {
            if (!container.contains(event.target)) {
              container.classList.remove("is-open");
              var toggle = container.querySelector(
                ".customer-record-filter-multiselect-toggle",
              );
              if (toggle) {
                toggle.setAttribute("aria-expanded", "false");
              }
            }
          });
      });

      pageState.documentMouseDownBound = true;
    }
  }

  function initFilterToggle() {
    var toggle = document.getElementById("orderReportFilterToggle");
    var panel = document.getElementById("orderReportFilterPanel");
    if (
      !toggle ||
      !panel ||
      toggle.getAttribute("data-order-report-bound") === "1"
    ) {
      return;
    }

    function syncToggleState() {
      var isVisible = panel.classList.contains("is-open");
      toggle.textContent = isVisible ? "Hide Filters" : "Show Filters";
      toggle.setAttribute("aria-expanded", isVisible ? "true" : "false");
    }

    toggle.addEventListener("click", function () {
      panel.classList.toggle("is-open");
      syncToggleState();
    });

    toggle.setAttribute("data-order-report-bound", "1");
    syncToggleState();
  }

  function initReportTypeFields() {
    var reportTypeInput = document.getElementById("report_type");
    if (!reportTypeInput) {
      return;
    }

    function syncFields() {
      var reportType = reportTypeInput.value || "daily";
      document
        .querySelectorAll(".js-order-report-date-field")
        .forEach(function (field) {
          field.style.display =
            field.getAttribute("data-report-type") === reportType ? "" : "none";
        });

      syncOrderReportFilterOptions(reportType);
    }

    if (reportTypeInput.getAttribute("data-order-report-bound") !== "1") {
      reportTypeInput.addEventListener("change", syncFields);
      reportTypeInput.setAttribute("data-order-report-bound", "1");
    }

    ["report_date", "report_month", "report_year"].forEach(function (fieldId) {
      var field = document.getElementById(fieldId);
      if (!field || field.getAttribute("data-order-report-date-bound") === "1") {
        return;
      }

      field.addEventListener("change", syncFields);
      field.setAttribute("data-order-report-date-bound", "1");
    });

    syncFields();
  }

  function initCharts(config) {
    var trendCanvas = document.getElementById("orderReportTrendChart");
    var trendEmpty = document.getElementById("orderReportTrendEmpty");
    var rankingCanvas = document.getElementById("orderReportRankingChart");
    var rankingEmpty = document.getElementById("orderReportRankingEmpty");
    var toolbar = document.getElementById("orderReportRankingToolbar");
    var breakdownSelect = document.getElementById("orderReportBreakdownDimension");
    var breakdownTable = document.getElementById("orderReportBreakdownTable");
    var breakdownHeadRow = document.getElementById("orderReportBreakdownHeadRow");
    var breakdownEmpty = document.getElementById("orderReportBreakdownEmpty");
    var rankingData = (config && config.ranking) || {};
    var rankingKey =
      (config && config.default_ranking_dimension) || "package";
    var salesLabel = (config && config.sales_label) || "Final Amount";

    pageState.trendChart = destroyChart(pageState.trendChart);
    pageState.rankingChart = destroyChart(pageState.rankingChart);

    if (
      typeof window.Chart === "undefined" ||
      !config ||
      typeof config !== "object"
    ) {
      return;
    }

    function renderTrendChart() {
      var trend = config.trend || {};
      var labels = Array.isArray(trend.labels) ? trend.labels : [];
      var sales = Array.isArray(trend.sales) ? trend.sales : [];
      var orders = Array.isArray(trend.orders) ? trend.orders : [];

      pageState.trendChart = destroyChart(pageState.trendChart);
      if (!trendCanvas) {
        return;
      }

      if (!labels.length) {
        trendCanvas.classList.add("d-none");
        if (trendEmpty) {
          trendEmpty.classList.remove("d-none");
        }
        return;
      }

      trendCanvas.classList.remove("d-none");
      if (trendEmpty) {
        trendEmpty.classList.add("d-none");
      }

      pageState.trendChart = new window.Chart(trendCanvas, {
        type: "bar",
        data: {
          labels: labels,
          datasets: [
            {
              label: salesLabel,
              data: sales,
              yAxisID: "yAmount",
              backgroundColor: "rgba(37, 99, 235, 0.78)",
              borderColor: "rgba(37, 99, 235, 1)",
              borderWidth: 1,
              borderRadius: 6,
            },
            {
              label: "Orders",
              data: orders,
              yAxisID: "yOrders",
              backgroundColor: "rgba(16, 185, 129, 0.68)",
              borderColor: "rgba(16, 185, 129, 1)",
              borderWidth: 1,
              borderRadius: 6,
            },
          ],
        },
        options: getChartOptions({
          interaction: {
            mode: "index",
            intersect: false,
          },
          scales: {
            yAmount: {
              beginAtZero: true,
              position: "left",
              title: {
                display: true,
                text: salesLabel,
              },
            },
            yOrders: {
              beginAtZero: true,
              position: "right",
              grid: {
                drawOnChartArea: false,
              },
              ticks: {
                precision: 0,
              },
              title: {
                display: true,
                text: "Orders",
              },
            },
          },
        }),
      });
    }

    function setActiveRankingButton(selectedKey) {
      if (!toolbar) {
        return;
      }

      toolbar
        .querySelectorAll(".order-report-ranking-btn")
        .forEach(function (button) {
          var isActive = button.getAttribute("data-dimension") === selectedKey;
          button.classList.toggle("active", isActive);
          button.classList.toggle("btn-primary", isActive);
          button.classList.toggle("btn-outline-primary", !isActive);
        });
    }

    function renderRankingChart(selectedKey) {
      rankingKey = selectedKey || rankingKey;
      pageState.rankingChart = destroyChart(pageState.rankingChart);

      if (!rankingCanvas) {
        return;
      }

      var dataset = rankingData[rankingKey] || {
        labels: [],
        sales: [],
        orders: [],
      };
      var labels = Array.isArray(dataset.labels) ? dataset.labels : [];
      var sales = Array.isArray(dataset.sales) ? dataset.sales : [];
      var orders = Array.isArray(dataset.orders) ? dataset.orders : [];
      var salesLabel = dataset.sales_label || "Final Amount";

      if (!labels.length) {
        rankingCanvas.classList.add("d-none");
        if (rankingEmpty) {
          rankingEmpty.classList.remove("d-none");
        }
        setActiveRankingButton(rankingKey);
        return;
      }

      rankingCanvas.classList.remove("d-none");
      if (rankingEmpty) {
        rankingEmpty.classList.add("d-none");
      }

      pageState.rankingChart = new window.Chart(rankingCanvas, {
        type: "bar",
        data: {
          labels: labels.map(function (label) {
            return wrapChartLabel(label, 30);
          }),
          datasets: [
            {
              label: salesLabel,
              data: sales,
              xAxisID: "xAmount",
              backgroundColor: "rgba(234, 88, 12, 0.72)",
              borderColor: "rgba(234, 88, 12, 1)",
              borderWidth: 1,
              borderRadius: 6,
            },
            {
              label: "Orders",
              data: orders,
              xAxisID: "xOrders",
              backgroundColor: "rgba(14, 165, 233, 0.68)",
              borderColor: "rgba(14, 165, 233, 1)",
              borderWidth: 1,
              borderRadius: 6,
            },
          ],
        },
        options: getChartOptions({
          indexAxis: "y",
          scales: {
            xAmount: {
              beginAtZero: true,
              position: "bottom",
              title: {
                display: true,
                text: salesLabel,
              },
            },
            xOrders: {
              beginAtZero: true,
              position: "top",
              grid: {
                drawOnChartArea: false,
              },
              ticks: {
                precision: 0,
              },
              title: {
                display: true,
                text: "Orders",
              },
            },
          },
        }),
      });

      setActiveRankingButton(rankingKey);
    }

    function renderBreakdownTable(selectedKey) {
      if (!breakdownTable) {
        return;
      }

      var tbody = breakdownTable.querySelector("tbody");
      if (!tbody) {
        return;
      }

      var rows = (config.breakdowns && config.breakdowns[selectedKey]) || [];
      var columns = getBreakdownColumns(selectedKey, config);
      tbody.innerHTML = "";

      if (breakdownHeadRow) {
        breakdownHeadRow.innerHTML = columns
          .map(function (column) {
            return "<th>" + escapeHtml(column.label) + "</th>";
          })
          .join("");
      }

      if (!rows.length) {
        breakdownTable.classList.add("d-none");
        if (breakdownEmpty) {
          breakdownEmpty.classList.remove("d-none");
        }
        return;
      }

      breakdownTable.classList.remove("d-none");
      if (breakdownEmpty) {
        breakdownEmpty.classList.add("d-none");
      }

      rows.forEach(function (row) {
        var tr = document.createElement("tr");
        tr.innerHTML = columns
          .map(function (column) {
            var rawValue =
              column.key === "group"
                ? row.group || "Unassigned"
                : row[column.key] != null
                  ? row[column.key]
                  : 0;
            var displayValue = column.formatter
              ? column.formatter(rawValue)
              : rawValue;

            return "<td>" + escapeHtml(displayValue) + "</td>";
          })
          .join("");
        tbody.appendChild(tr);
      });
    }

    if (toolbar && toolbar.getAttribute("data-order-report-bound") !== "1") {
      toolbar.addEventListener("click", function (event) {
        var button = event.target.closest(".order-report-ranking-btn");
        if (!button) {
          return;
        }

        renderRankingChart(button.getAttribute("data-dimension") || "package");
      });
      toolbar.setAttribute("data-order-report-bound", "1");
    }

    if (
      breakdownSelect &&
      breakdownSelect.getAttribute("data-order-report-bound") !== "1"
    ) {
      breakdownSelect.addEventListener("change", function () {
        renderBreakdownTable(breakdownSelect.value || "package");
      });
      breakdownSelect.setAttribute("data-order-report-bound", "1");
    }

    renderTrendChart();
    renderRankingChart(rankingKey);
    renderBreakdownTable(
      breakdownSelect ? breakdownSelect.value || "package" : "package",
    );
  }

  function initDetailTable(config) {
    if (
      typeof window.jQuery === "undefined" ||
      typeof window.createSortingTable !== "function" ||
      !config ||
      !config.table_id
    ) {
      return;
    }

    var tableElement = document.getElementById(config.table_id);
    if (!tableElement || tableElement.getAttribute("data-order-report-table") === "1") {
      return;
    }

    tableElement.setAttribute("data-order-report-table", "1");
    window.createSortingTable(config.table_id, {
      searching: true,
      order: [[0, "asc"]],
    });
    if (typeof window.datatableAlignment === "function") {
      window.datatableAlignment(config.table_id);
    }
  }

  function bootOrderReportPage() {
    if (pageState.initialized) {
      return;
    }

    pageState.initialized = true;
    initFilterToggle();
    initOrderReportMultiSelects();
    initReportTypeFields();
    initCharts(window.orderReportPageConfig || {});
    initDetailTable(window.orderReportPageConfig || {});
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootOrderReportPage);
  } else {
    bootOrderReportPage();
  }
})();
