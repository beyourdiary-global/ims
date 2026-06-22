if (!window.shopeeOmsAirbillPdfAutofill || !window.shopeeOmsAirbillPdfAutofill.__sgEnhanced) {
    window.shopeeOmsAirbillPdfAutofill = (function () {
        function getPdfTextItemX(item) {
            return item && item.transform ? Number(item.transform[4]) || 0 : 0;
        }

        function getPdfTextItemY(item) {
            return item && item.transform ? Number(item.transform[5]) || 0 : 0;
        }

        function normalizePdfTextItem(item) {
            return String(item && item.str ? item.str : '').trim();
        }

        function normalizePlainText(value) {
            return String(value || '').replace(/\s+/g, ' ').trim();
        }

        function normalizeLookup(value) {
            return normalizePlainText(value).toLowerCase().replace(/[^a-z0-9]+/g, '');
        }

        function sortPdfItemsForReading(items) {
            return items.slice().sort(function (a, b) {
                var yDiff = getPdfTextItemY(b) - getPdfTextItemY(a);
                if (Math.abs(yDiff) > 2) {
                    return yDiff;
                }
                return getPdfTextItemX(a) - getPdfTextItemX(b);
            });
        }

        function groupPdfItemsIntoLines(items) {
            var sortedItems = sortPdfItemsForReading(items);
            var lines = [];

            sortedItems.forEach(function (item) {
                var text = normalizePdfTextItem(item);
                if (text === '') {
                    return;
                }

                var itemY = getPdfTextItemY(item);
                var currentLine = lines.length > 0 ? lines[lines.length - 1] : null;
                if (!currentLine || Math.abs(currentLine.y - itemY) > 2) {
                    currentLine = { y: itemY, items: [] };
                    lines.push(currentLine);
                }

                currentLine.items.push(item);
            });

            return lines.map(function (line) {
                return line.items
                    .slice()
                    .sort(function (a, b) { return getPdfTextItemX(a) - getPdfTextItemX(b); })
                    .map(function (item) { return normalizePdfTextItem(item); })
                    .filter(function (text) { return text !== ''; })
                    .join(' ')
                    .replace(/\s+,/g, ',')
                    .replace(/\s+:/g, ':')
                    .trim();
            }).filter(function (line) { return line !== ''; });
        }

        function isLikelyAirbillCode(text) {
            var normalized = String(text || '').replace(/\s+/g, '').replace(/[^A-Z0-9]/gi, '').toUpperCase();
            if (normalized.length < 10 || normalized.length > 40) {
                return false;
            }
            if (!/[A-Z]/.test(normalized) || !/\d/.test(normalized)) {
                return false;
            }

            return /^(?:SPXSG|SPXMY|GDSP|GDEX|NVMY|NVSG|NJV|JNT|JT|DHL|BEST|FLASH|MY|SG)[A-Z0-9]{6,}$/.test(normalized) || /^[A-Z]{2,8}[0-9][A-Z0-9]{8,}$/.test(normalized);
        }

        function cleanAirbillCode(text) {
            var normalized = String(text || '').replace(/\s+/g, '').replace(/[^A-Z0-9]/gi, '').toUpperCase();
            return isLikelyAirbillCode(normalized) ? normalized : '';
        }

        function extractAirbillCodeFromLines(lines) {
            var bestCode = '';
            var bestScore = -1;
            var codePattern = /\b((?:SPXSG|SPXMY|GDSP|GDEX|NVMY|NVSG|NJV|JNT|JT|DHL|BEST|FLASH|MY|SG)[A-Z0-9]{6,}|[A-Z]{2,8}[0-9][A-Z0-9]{8,})\b/gi;

            lines.forEach(function (line) {
                var matches = line.match(codePattern) || [];
                matches.forEach(function (match) {
                    var code = cleanAirbillCode(match);
                    if (code === '') {
                        return;
                    }

                    var score = 1;
                    if (/SPXSG|SPXMY|GDSP|GDEX|NVMY|NVSG/i.test(code)) {
                        score += 8;
                    }
                    if (/airbill|waybill|tracking|awb|shipment|consignment|package|parcel|#/i.test(line)) {
                        score += 4;
                    }
                    if (score > bestScore) {
                        bestScore = score;
                        bestCode = code;
                    }
                });
            });

            return bestCode;
        }

        function extractAirbillCodeFromPdfItems(items, pageHeight) {
            var candidates = items
                .map(function (item) {
                    return {
                        text: cleanAirbillCode(normalizePdfTextItem(item)),
                        x: getPdfTextItemX(item),
                        y: getPdfTextItemY(item)
                    };
                })
                .filter(function (item) {
                    return item.text !== '' && (item.y >= (pageHeight * 0.55) || /^(?:SPXSG|SPXMY|GDSP|GDEX)/.test(item.text));
                })
                .sort(function (a, b) {
                    var aScore = /^(?:SPXSG|SPXMY|GDSP|GDEX)/.test(a.text) ? 1 : 0;
                    var bScore = /^(?:SPXSG|SPXMY|GDSP|GDEX)/.test(b.text) ? 1 : 0;
                    if (aScore !== bScore) {
                        return bScore - aScore;
                    }
                    if (Math.abs(b.y - a.y) > 2) {
                        return b.y - a.y;
                    }
                    return a.x - b.x;
                });

            return candidates.length > 0 ? candidates[0].text : '';
        }

        function isSenderLine(line) {
            return /\b(sender|shipper|from|pickup|return\s+to|consignor)\b/i.test(String(line || ''));
        }

        function cleanNameValue(value) {
            value = normalizePlainText(value)
                .replace(/^(?:recipient|receiver|consignee|customer|buyer|ship\s*to|deliver\s*to|to|name)\s*[:：-]?\s*/i, '')
                .replace(/\b(?:phone|tel|mobile|contact|address|postcode|postal\s*code)\b.*$/i, '')
                .replace(/\s{2,}/g, ' ')
                .trim();

            if (value === '' || value.length > 80) {
                return '';
            }
            if (isLikelyAirbillCode(value) || /\b(address|postcode|postal|phone|tel|mobile|tracking|waybill|airbill|parcel|order)\b/i.test(value)) {
                return '';
            }
            if (/^[0-9\s+\-()]{6,}$/.test(value)) {
                return '';
            }

            return value;
        }

        function extractTextToRightOfLabel(items, labelItem, pageWidth) {
            var labelX = getPdfTextItemX(labelItem);
            var labelY = getPdfTextItemY(labelItem);
            var minX = labelX + Number(labelItem.width || 0) - 1;
            var maxX = pageWidth * 0.78;

            var sameLineItems = items.filter(function (item) {
                var text = normalizePdfTextItem(item);
                if (text === '' || /^(?:name|address|phone|tel|postcode|postal\s*code)\s*:?$/i.test(text)) {
                    return false;
                }
                var itemX = getPdfTextItemX(item);
                var itemY = getPdfTextItemY(item);
                return itemX >= minX && itemX <= maxX && Math.abs(itemY - labelY) <= 2;
            });

            return groupPdfItemsIntoLines(sameLineItems).join(' ').trim();
        }

        function extractRecipientNameFromPdfItems(items, pageWidth) {
            var nameLabels = items
                .filter(function (item) {
                    var text = normalizePdfTextItem(item);
                    return /^(?:name|recipient\s*name|receiver\s*name|consignee\s*name)\s*:?$/i.test(text) && getPdfTextItemX(item) <= (pageWidth * 0.35);
                })
                .sort(function (a, b) { return getPdfTextItemY(b) - getPdfTextItemY(a); });

            for (var i = nameLabels.length - 1; i >= 0; i--) {
                var name = cleanNameValue(extractTextToRightOfLabel(items, nameLabels[i], pageWidth));
                if (name !== '') {
                    return name;
                }
            }

            return '';
        }

        function extractRecipientNameFromLines(lines) {
            var preferredPatterns = [
                /\b(?:recipient|receiver|consignee|customer|buyer)\s*(?:name)?\s*[:：-]\s*(.+)$/i,
                /\b(?:ship\s*to|deliver\s*to)\s*[:：-]\s*(.+)$/i,
                /^to\s*[:：-]\s*(.+)$/i
            ];

            for (var i = 0; i < lines.length; i++) {
                var line = lines[i];
                if (isSenderLine(line)) {
                    continue;
                }

                for (var p = 0; p < preferredPatterns.length; p++) {
                    var match = line.match(preferredPatterns[p]);
                    if (match && match[1]) {
                        var inlineName = cleanNameValue(match[1]);
                        if (inlineName !== '') {
                            return inlineName;
                        }
                    }
                }

                if (/^(?:recipient|receiver|consignee|ship\s*to|deliver\s*to|to|name)\s*[:：-]?$/i.test(line)) {
                    for (var j = i + 1; j < Math.min(lines.length, i + 4); j++) {
                        var nextName = cleanNameValue(lines[j]);
                        if (nextName !== '') {
                            return nextName;
                        }
                    }
                }

                var genericName = line.match(/\bname\s*[:：-]\s*(.+)$/i);
                if (genericName && genericName[1]) {
                    var cleaned = cleanNameValue(genericName[1]);
                    if (cleaned !== '') {
                        return cleaned;
                    }
                }
            }

            return '';
        }

        function cleanAddressLine(line) {
            line = normalizePlainText(line)
                .replace(/^(?:delivery|recipient|receiver|consignee|shipping|ship\s*to|deliver\s*to)?\s*address\s*[:：-]?\s*/i, '')
                .trim();

            if (line === '') {
                return '';
            }

            if (/^(?:postcode|postal\s*code)\s*[:：-]?/i.test(line)) {
                return '';
            }

            if (/^\d{6}$/.test(line)) {
                return '';
            }

            if (/^(?:name|phone|tel|mobile|contact|sender|shipper|from|pickup|return\s+to)\s*[:：-]?/i.test(line)) {
                return '';
            }

            if (/\b(?:tracking|waybill|airbill|awb|parcel|order\s*id|product|weight|cod|insurance)\b/i.test(line)) {
                return '';
            }

            if (isLikelyAirbillCode(line)) {
                return '';
            }

            return line;
        }

        function extractRecipientAddressFromPdfItems(items, pageWidth) {
            var addressLabels = items
                .filter(function (item) {
                    return /^(?:address|recipient\s*address|receiver\s*address|delivery\s*address|shipping\s*address)\s*:?$/i.test(normalizePdfTextItem(item)) && getPdfTextItemX(item) <= (pageWidth * 0.35);
                })
                .sort(function (a, b) { return getPdfTextItemY(b) - getPdfTextItemY(a); });

            if (addressLabels.length === 0) {
                return '';
            }

            var recipientAddressLabel = addressLabels[addressLabels.length - 1];
            var recipientPostcodeLabel = items
                .filter(function (item) {
                    return /^(?:postcode|postal\s*code)\s*:?$/i.test(normalizePdfTextItem(item)) &&
                        getPdfTextItemX(item) <= (pageWidth * 0.35) &&
                        getPdfTextItemY(item) < getPdfTextItemY(recipientAddressLabel) - 6;
                })
                .sort(function (a, b) { return getPdfTextItemY(b) - getPdfTextItemY(a); })[0] || null;

            var minX = getPdfTextItemX(recipientAddressLabel) + Number(recipientAddressLabel.width || 0) - 1;
            var minY = recipientPostcodeLabel ? getPdfTextItemY(recipientPostcodeLabel) - 2 : getPdfTextItemY(recipientAddressLabel) - 85;
            var maxY = getPdfTextItemY(recipientAddressLabel) + 2;
            var maxX = pageWidth * 0.78;
            var addressItems = items.filter(function (item) {
                var text = normalizePdfTextItem(item);
                if (text === '' || /^(?:address|phone|tel|name|postcode|postal\s*code)\s*:?$/i.test(text)) {
                    return false;
                }

                var itemX = getPdfTextItemX(item);
                var itemY = getPdfTextItemY(item);
                return itemX >= minX && itemX <= maxX && itemY <= maxY && itemY >= minY;
            });

            return groupPdfItemsIntoLines(addressItems).map(cleanAddressLine).filter(function (line) { return line !== ''; }).join('\n').trim();
        }

        function extractRecipientAddressFromLines(lines) {
            var startIndex = -1;
            var inlineValue = '';

            for (var i = 0; i < lines.length; i++) {
                var line = lines[i];
                if (isSenderLine(line)) {
                    continue;
                }

                var addressMatch = line.match(/\b(?:delivery|recipient|receiver|consignee|shipping)?\s*address\s*[:：-]?\s*(.*)$/i);
                if (addressMatch) {
                    startIndex = i;
                    inlineValue = cleanAddressLine(addressMatch[1] || '');
                    break;
                }

                if (/^(?:ship\s*to|deliver\s*to)\s*[:：-]?$/i.test(line)) {
                    startIndex = i + 1;
                    inlineValue = '';
                    break;
                }
            }

            if (startIndex < 0) {
                return '';
            }

            var addressLines = [];
            if (inlineValue !== '') {
                addressLines.push(inlineValue);
            }

            for (var j = startIndex + (inlineValue !== '' ? 1 : 0); j < Math.min(lines.length, startIndex + 8); j++) {
                var currentLine = lines[j];
                if (/^(?:phone|tel|mobile|contact|sender|shipper|from|pickup|return\s+to|tracking|waybill|airbill|awb|parcel|package|order\s*id|product|weight|cod)\b/i.test(currentLine)) {
                    break;
                }

                var cleanedLine = cleanAddressLine(currentLine);
                if (cleanedLine !== '') {
                    addressLines.push(cleanedLine);
                }
            }

            return addressLines.join('\n').trim();
        }

        function removeCustomerNameFromAddress(address, customerName) {
            var lines = String(address || '').split(/\n+/).map(function (line) { return normalizePlainText(line); }).filter(function (line) { return line !== ''; });
            if (lines.length === 0 || customerName === '') {
                return address;
            }
            if (normalizeLookup(lines[0]) === normalizeLookup(customerName)) {
                lines.shift();
            }
            return lines.join('\n').trim();
        }

        function extractShopeeAirbillDataFromPdfItems(items, pageWidth, pageHeight) {
            var lines = groupPdfItemsIntoLines(items);
            var airbillNo = extractAirbillCodeFromLines(lines) || extractAirbillCodeFromPdfItems(items, pageHeight);
            var customerName = extractRecipientNameFromPdfItems(items, pageWidth) || extractRecipientNameFromLines(lines);
            var customerAddress = extractRecipientAddressFromPdfItems(items, pageWidth) || extractRecipientAddressFromLines(lines);
            customerAddress = removeCustomerNameFromAddress(customerAddress, customerName);

            return {
                airbillNo: airbillNo || '',
                customerName: customerName || '',
                customerAddress: customerAddress || ''
            };
        }

        function dispatchInputEvent(element) {
            if (!element) {
                return;
            }

            try {
                element.dispatchEvent(new Event('input', { bubbles: true }));
                element.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (error) {
            }
        }

        function bind(config) {
            config = config || {};
            var fileInput = document.querySelector(config.fileInputSelector || '');
            var airbillNo = document.querySelector(config.airbillNoSelector || '');
            var customerName = config.customerNameSelector ? document.querySelector(config.customerNameSelector) : null;
            var customerAddress = document.querySelector(config.customerAddressSelector || '');
            var statusNode = document.querySelector(config.statusSelector || '');
            if (!fileInput || !airbillNo || !customerAddress || !statusNode) {
                return false;
            }

            function setStatus(message, isError) {
                statusNode.textContent = message;
                if (config.errorClass) {
                    statusNode.classList.toggle(config.errorClass, !!isError);
                }
                if (config.normalClass) {
                    statusNode.classList.toggle(config.normalClass, !isError);
                }
            }

            if (typeof pdfjsLib === 'undefined') {
                setStatus('PDF extraction library failed to load on this page.', true);
                return false;
            }

            if (config.workerSrc) {
                pdfjsLib.GlobalWorkerOptions.workerSrc = config.workerSrc;
            }

            if (fileInput.dataset.airbillPdfAutofillBound === '1') {
                return true;
            }

            if (config.localStorageKey) {
                try {
                    var storedDeliveryInfo = JSON.parse(localStorage.getItem(config.localStorageKey) || 'null');
                    if (storedDeliveryInfo) {
                        if (airbillNo && !String(airbillNo.value || '').trim() && String(storedDeliveryInfo.airbillNo || '').trim()) {
                            airbillNo.value = String(storedDeliveryInfo.airbillNo || '').trim();
                            dispatchInputEvent(airbillNo);
                        }
                        if (customerName && !String(customerName.value || '').trim() && String(storedDeliveryInfo.customerName || '').trim()) {
                            customerName.value = String(storedDeliveryInfo.customerName || '').trim();
                            dispatchInputEvent(customerName);
                        }
                        if (customerAddress && !String(customerAddress.value || '').trim() && String(storedDeliveryInfo.customerAddress || '').trim()) {
                            customerAddress.value = String(storedDeliveryInfo.customerAddress || '').trim();
                            dispatchInputEvent(customerAddress);
                        }
                    }
                } catch (error) {
                }
            }

            function readFileAsArrayBuffer(file) {
                return new Promise(function (resolve, reject) {
                    var reader = new FileReader();
                    reader.onload = function (event) { resolve(event.target.result); };
                    reader.onerror = reject;
                    reader.readAsArrayBuffer(file);
                });
            }

            function loadPdfTextItems(file) {
                return readFileAsArrayBuffer(file).then(function (buffer) {
                    return pdfjsLib.getDocument({ data: new Uint8Array(buffer) }).promise;
                }).then(function (pdfDoc) {
                    var maxPages = Math.min(Number(pdfDoc.numPages) || 1, 2);
                    var pagePromises = [];
                    for (var pageNo = 1; pageNo <= maxPages; pageNo++) {
                        pagePromises.push(pdfDoc.getPage(pageNo).then(function (page) {
                            var viewport = page.getViewport({ scale: 1 });
                            return page.getTextContent().then(function (textContent) {
                                return {
                                    items: (textContent.items || []).filter(function (item) { return normalizePdfTextItem(item) !== ''; }),
                                    pageWidth: Number(viewport.width) || 0,
                                    pageHeight: Number(viewport.height) || 0
                                };
                            });
                        }));
                    }

                    return Promise.all(pagePromises).then(function (pages) {
                        var combinedItems = [];
                        var pageWidth = 0;
                        var pageHeight = 0;
                        pages.forEach(function (pageData) {
                            combinedItems = combinedItems.concat(pageData.items || []);
                            pageWidth = Math.max(pageWidth, Number(pageData.pageWidth) || 0);
                            pageHeight = Math.max(pageHeight, Number(pageData.pageHeight) || 0);
                        });

                        return {
                            items: combinedItems,
                            pageWidth: pageWidth,
                            pageHeight: pageHeight
                        };
                    });
                });
            }

            fileInput.addEventListener('change', function () {
                setStatus('', false);
                if (!this.files || !this.files[0]) {
                    return;
                }

                var selectedFile = this.files[0];
                if (!/\.pdf$/i.test(String(selectedFile.name || ''))) {
                    return;
                }

                setStatus('Extracting airbill number, customer name and address from PDF...', false);

                loadPdfTextItems(selectedFile).then(function (pdfData) {
                    var extractedData = extractShopeeAirbillDataFromPdfItems(pdfData.items, pdfData.pageWidth, pdfData.pageHeight);

                    if (extractedData.airbillNo !== '') {
                        airbillNo.value = extractedData.airbillNo;
                        dispatchInputEvent(airbillNo);
                    }
                    if (customerName && extractedData.customerName !== '') {
                        customerName.value = extractedData.customerName;
                        dispatchInputEvent(customerName);
                    }
                    if (extractedData.customerAddress !== '') {
                        customerAddress.value = extractedData.customerAddress;
                        dispatchInputEvent(customerAddress);
                    }

                    if (config.localStorageKey && (extractedData.airbillNo !== '' || extractedData.customerName !== '' || extractedData.customerAddress !== '')) {
                        try {
                            localStorage.setItem(config.localStorageKey, JSON.stringify({
                                airbillNo: extractedData.airbillNo || '',
                                customerName: extractedData.customerName || '',
                                customerAddress: extractedData.customerAddress || ''
                            }));
                        } catch (error) {
                        }
                    }

                    if (extractedData.airbillNo !== '' || extractedData.customerName !== '' || extractedData.customerAddress !== '') {
                        setStatus('Airbill PDF extracted successfully.', false);
                    } else {
                        setStatus('Unable to detect the airbill number, customer name or address from this PDF. Please fill them manually.', true);
                    }
                }).catch(function () {
                    setStatus('Unable to read this PDF. Please fill the airbill number, customer name and address manually.', true);
                });
            });

            fileInput.dataset.airbillPdfAutofillBound = '1';
            return true;
        }

        var module = {
            __sgEnhanced: true,
            bind: bind,
            extractShopeeAirbillDataFromPdfItems: extractShopeeAirbillDataFromPdfItems
        };

        return module;
    })();
}