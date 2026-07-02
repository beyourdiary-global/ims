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

        function splitTextIntoLines(value) {
            return String(value || '')
                .replace(/\r/g, '\n')
                .split(/\n+/)
                .map(normalizePlainText)
                .filter(function (line) { return line !== ''; });
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

            if (/^\d+$/.test(normalized)) {
                return normalized.length >= 12 && normalized.length <= 20 && !/^(\d)\1{8,}$/.test(normalized);
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

        function isLikelyAirbillContextLine(line) {
            return /\b(?:tracking(?:\s*(?:no|number))?|air\s*waybill|waybill|airbill|awb|shipment|consignment|parcel|courier)\b|tracking\s*#/i.test(String(line || ''));
        }

        function isLikelyOrderContextLine(line) {
            return /\b(?:o\s*\/?\s*n|oin|0in|order\s*(?:creation|id|no|number)|no\.?\s*[lI1]tem)\b/i.test(String(line || ''));
        }

        function extractAirbillCandidatesFromLine(line) {
            var candidates = [];
            var alphaPattern = /\b((?:SPXSG|SPXMY|GDSP|GDEX|NVMY|NVSG|NJV|JNT|JT|DHL|BEST|FLASH|MY|SG)[A-Z0-9]{6,}|[A-Z]{2,8}[0-9][A-Z0-9]{8,})\b/gi;
            var numericPattern = /(?:^|[^0-9])(\d(?:[\d\s-]{10,22}\d))(?![0-9])/g;
            var match = null;

            while ((match = alphaPattern.exec(line)) !== null) {
                var alphaCode = cleanAirbillCode(match[1]);
                if (alphaCode !== '') {
                    candidates.push(alphaCode);
                }
            }

            while ((match = numericPattern.exec(line)) !== null) {
                var numericCode = cleanAirbillCode(match[1]);
                if (numericCode !== '') {
                    candidates.push(numericCode);
                }
            }

            return candidates;
        }

        function extractAirbillCodeFromFilename(fileName) {
            var match = String(fileName || '').match(/(?:^|[^0-9])(\d{14,20})(?![0-9])/);
            return match && match[1] ? cleanAirbillCode(match[1]) : '';
        }

        function pushDebugEntry(list, entry, limit) {
            if (!Array.isArray(list)) {
                return;
            }

            if (list.length >= (limit || 50)) {
                return;
            }

            list.push(entry);
        }

        function buildScoreboard(codeScores, limit) {
            return Object.keys(codeScores || {}).map(function (code) {
                return {
                    code: code,
                    score: codeScores[code]
                };
            }).sort(function (a, b) {
                return b.score - a.score;
            }).slice(0, limit || 10);
        }

        function buildRelevantLineList(lines, limit) {
            return (lines || []).filter(function (line) {
                return isLikelyAirbillContextLine(line)
                    || isLikelyOrderContextLine(line)
                    || extractAirbillCandidatesFromLine(line).length > 0;
            }).slice(0, limit || 40);
        }

        function extractAirbillCodeFromLines(lines, debug) {
            var codeScores = {};
            var bestCode = '';
            var bestScore = -1;

            if (debug) {
                debug.lineEvaluations = debug.lineEvaluations || [];
            }

            lines.forEach(function (line) {
                var airbillContext = isLikelyAirbillContextLine(line);
                var orderContext = isLikelyOrderContextLine(line);
                extractAirbillCandidatesFromLine(line).forEach(function (code) {
                    if (code === '') {
                        return;
                    }

                    if (orderContext && !airbillContext) {
                        if (debug) {
                            pushDebugEntry(debug.lineEvaluations, {
                                line: line,
                                code: code,
                                airbillContext: airbillContext,
                                orderContext: orderContext,
                                accepted: false,
                                reason: 'order_context_without_airbill_context'
                            }, 80);
                        }
                        return;
                    }

                    var score = 1;
                    if (/SPXSG|SPXMY|GDSP|GDEX|NVMY|NVSG/i.test(code)) {
                        score += 8;
                    }
                    if (airbillContext) {
                        score += 8;
                    }
                    if (/^\d+$/.test(code)) {
                        var normalizedLine = String(line || '').replace(/[^A-Z0-9]+/gi, '').toUpperCase();
                        if (!airbillContext && normalizedLine !== code) {
                            if (debug) {
                                pushDebugEntry(debug.lineEvaluations, {
                                    line: line,
                                    code: code,
                                    airbillContext: airbillContext,
                                    orderContext: orderContext,
                                    accepted: false,
                                    reason: 'numeric_code_embedded_in_non_airbill_line'
                                }, 80);
                            }
                            return;
                        }
                        score += 2;
                    }
                    codeScores[code] = (codeScores[code] || 0) + score;

                    if (debug) {
                        pushDebugEntry(debug.lineEvaluations, {
                            line: line,
                            code: code,
                            airbillContext: airbillContext,
                            orderContext: orderContext,
                            accepted: true,
                            addedScore: score,
                            totalScore: codeScores[code]
                        }, 80);
                    }
                });
            });

            Object.keys(codeScores).forEach(function (code) {
                if (codeScores[code] > bestScore) {
                    bestScore = codeScores[code];
                    bestCode = code;
                }
            });

            if (debug) {
                debug.lineScoreboard = buildScoreboard(codeScores, 12);
                debug.lineBestCode = bestCode;
            }

            return bestCode;
        }

        function extractAirbillCodeNearTrackingLabelFromPdfItems(items, debug) {
            var trackingLabelItems = items.filter(function (item) {
                return /tracking(?:\s*(?:no|number))?/i.test(normalizePdfTextItem(item));
            });

            if (debug) {
                debug.labelMatches = debug.labelMatches || [];
            }

            for (var i = 0; i < trackingLabelItems.length; i++) {
                var labelItem = trackingLabelItems[i];
                var labelX = getPdfTextItemX(labelItem);
                var labelY = getPdfTextItemY(labelItem);
                var nearbyItems = items.filter(function (item) {
                    var text = normalizePdfTextItem(item);
                    if (text === '') {
                        return false;
                    }

                    var itemX = getPdfTextItemX(item);
                    var itemY = getPdfTextItemY(item);
                    return Math.abs(itemY - labelY) <= 6 && itemX >= (labelX - 10);
                });

                var nearbyLines = groupPdfItemsIntoLines(nearbyItems);
                for (var j = 0; j < nearbyLines.length; j++) {
                    var lineDebug = {};
                    var code = extractAirbillCodeFromLines([nearbyLines[j]], lineDebug);
                    if (debug) {
                        pushDebugEntry(debug.labelMatches, {
                            labelText: normalizePdfTextItem(labelItem),
                            labelX: labelX,
                            labelY: labelY,
                            nearbyLine: nearbyLines[j],
                            lineScoreboard: lineDebug.lineScoreboard || [],
                            detectedCode: code
                        }, 30);
                    }
                    if (code !== '') {
                        if (debug) {
                            debug.bestLabelCode = code;
                        }
                        return code;
                    }
                }
            }

            return '';
        }

        function extractAirbillCodeFromPdfItems(items, pageHeight, debug) {
            if (debug) {
                debug.itemEvaluations = debug.itemEvaluations || [];
            }

            var trackingLabelDebug = debug ? {} : null;
            var codeNearTrackingLabel = extractAirbillCodeNearTrackingLabelFromPdfItems(items, trackingLabelDebug);
            if (debug) {
                debug.trackingLabel = trackingLabelDebug;
            }
            if (codeNearTrackingLabel !== '') {
                if (debug) {
                    debug.itemBestCode = codeNearTrackingLabel;
                    debug.itemStrategy = 'tracking_label';
                }
                return codeNearTrackingLabel;
            }

            var codeScores = {};
            items.forEach(function (item) {
                var code = cleanAirbillCode(normalizePdfTextItem(item));
                if (code === '') {
                    return;
                }

                var score = 1;
                if (/^(?:SPXSG|SPXMY|GDSP|GDEX)/.test(code)) {
                    score += 8;
                }

                var itemY = getPdfTextItemY(item);
                if (/^\d+$/.test(code)) {
                    if (itemY >= (pageHeight * 0.82)) {
                        score -= 1;
                    } else {
                        score += 1;
                    }
                } else if (itemY >= (pageHeight * 0.55)) {
                    score += 1;
                }

                codeScores[code] = (codeScores[code] || 0) + score;
                if (debug) {
                    pushDebugEntry(debug.itemEvaluations, {
                        rawText: normalizePdfTextItem(item),
                        code: code,
                        itemY: itemY,
                        addedScore: score,
                        totalScore: codeScores[code]
                    }, 80);
                }
            });

            var bestCode = '';
            var bestScore = -1;
            Object.keys(codeScores).forEach(function (code) {
                if (codeScores[code] > bestScore) {
                    bestScore = codeScores[code];
                    bestCode = code;
                }
            });

            if (debug) {
                debug.itemScoreboard = buildScoreboard(codeScores, 12);
                debug.itemBestCode = bestCode;
                debug.itemStrategy = 'scored_items';
            }

            return bestCode;
        }

        function isSenderLine(line) {
            return /\b(sender|shipper|from|pickup|return\s+to|consignor)\b/i.test(String(line || ''));
        }

        function cleanNameValue(value) {
            value = normalizePlainText(value)
                .replace(/^(?:recipient|receiver|consignee|customer|buyer|ship\s*to|deliver\s*to|to|name)\s*[:\uFF1A-]?\s*/i, '')
                .replace(/\b(?:phone|tel|mobile|contact|address|postcode|postal\s*code)\b.*$/i, '')
                .replace(/\s{2,}/g, ' ')
                .trim();

            if (value === '' || value.length > 80) {
                return '';
            }

            if (/[A-Za-z]/.test(value)) {
                value = value
                    .replace(/[\u3400-\u9FFF]+/g, ' ')
                    .replace(/[^A-Za-z\s.'-]/g, ' ')
                    .replace(/\s{2,}/g, ' ')
                    .trim()
                    .replace(/\b([A-Za-z])([A-Za-z]*)\b/g, function (match, first, rest) {
                        return first.toUpperCase() + String(rest || '').toLowerCase();
                    });
            } else {
                value = value
                    .replace(/[^\u3400-\u9FFFA-Za-z\s.'-]/g, ' ')
                    .replace(/\s{2,}/g, ' ')
                    .trim();
            }

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
                /\b(?:recipient|receiver|consignee|customer|buyer)\s*(?:name)?\s*[:\uFF1A-]?\s*(.+)$/i,
                /\b(?:ship\s*to|deliver\s*to)\s*[:\uFF1A-]?\s*(.+)$/i,
                /^to\s*[:\uFF1A-]\s*(.+)$/i
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

                if (/^(?:recipient|receiver|consignee|ship\s*to|deliver\s*to|to|name)\s*[:\uFF1A-]?$/i.test(line)) {
                    for (var j = i + 1; j < Math.min(lines.length, i + 4); j++) {
                        var nextName = cleanNameValue(lines[j]);
                        if (nextName !== '') {
                            return nextName;
                        }
                    }
                }

                var genericName = line.match(/\bname\s*[:\uFF1A-]\s*(.+)$/i);
                if (genericName && genericName[1]) {
                    var cleaned = cleanNameValue(genericName[1]);
                    if (cleaned !== '') {
                        return cleaned;
                    }
                }
            }

            return '';
        }

        function cropJntAddressNoise(value) {
            return normalizePlainText(value)
                .replace(/\b(?:O\/?N|OIN|0IN|ON)\s*[:\uFF1A]?\s*\d+.*$/i, '')
                .replace(/\bNo\.?\s*[lI1]tem\s*\d+.*$/i, '')
                .replace(/\[[^\]]*$/i, '')
                .replace(/\|.*$/i, '')
                .replace(/\b(?:2g|EEE|c3)\b.*$/i, '')
                .trim();
        }

        function cleanAddressLine(line) {
            line = normalizePlainText(line)
                .replace(/[，]/g, ',')
                .replace(/[：]/g, ':');

            line = cropJntAddressNoise(line);

            line = normalizePlainText(line)
                .replace(/^(?:delivery|recipient|receiver|consignee|shipping|ship\s*to|deliver\s*to)?\s*address\s*[:\uFF1A-]?\s*/i, '')
                .replace(/(?:\b500\s*[-–—]?\s*E80\s*[-–—]?|\bJH\s*7401\b|\bM15\b)/gi, ' ')
                .replace(/\b(?:non\s*[-–—]?\s*cod|cod|home|standard)\b/gi, ' ')
                .replace(/[\u3400-\u9FFF]+/g, ' ')
                .replace(/[^\w\s,./#()-]/g, ' ')
                .replace(/\b(Mas)(skudai)\b/ig, '$1 $2')
                .replace(/\s{2,}/g, ' ')
                .replace(/\s+,/g, ',')
                .replace(/,\s*,/g, ',')
                .replace(/^[,\s\-:]+/, '')
                .replace(/[,\s\-:]+$/, '')
                .trim();

            if (line === '') {
                return '';
            }

            if (/^(?:postcode|postal\s*code)\s*[:\uFF1A-]?/i.test(line)) {
                return '';
            }

            if (/^(?:name|phone|tel|mobile|contact|customer|sender|seller|shipper|from|pickup|return\s+to)\s*[:\uFF1A-]?/i.test(line)) {
                return '';
            }

            if (/^(?:tracking|waybill|airbill|awb|parcel|package|order\s*(?:id|no|number)|product|weight|cod|non-cod|home|standard)\b/i.test(line)) {
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

        function cleanAddressValue(value) {
            var cleanedValue = String(value || '')
                .replace(/\r/g, '\n')
                .replace(/\n+/g, ' ');

            cleanedValue = cropJntAddressNoise(cleanedValue);
            cleanedValue = cleanAddressLine(cleanedValue);

            cleanedValue = cleanedValue
                .replace(/\b(Mas)(skudai)\b/ig, '$1 $2')
                .replace(/\s{2,}/g, ' ')
                .replace(/\s*,\s*/g, ', ')
                .replace(/\b(\d{5})(?:\s+\d+)?(?:\s+[A-Za-z0-9]{1,4}){0,8}\s*$/i, '$1')
                .replace(/,\s*(\d{5})\d*\b.*$/i, ',$1')
                .replace(/\s+(\d{5})$/i, ',$1')
                .replace(/[,\s\-:]+$/, '')
                .trim();

            return cleanedValue;
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

                var addressMatch = line.match(/\b(?:delivery|recipient|receiver|consignee|shipping)?\s*address\s*[:\uFF1A-]?\s*(.*)$/i);
                if (addressMatch) {
                    startIndex = i;
                    inlineValue = cleanAddressLine(addressMatch[1] || '');
                    break;
                }

                if (/^(?:ship\s*to|deliver\s*to|to|recipient|receiver|consignee)\s*[:\uFF1A-]?$/i.test(line)) {
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

        function extractJntAddressAfterCustomerLine(lines) {
            for (var i = 0; i < lines.length; i++) {
                var line = normalizePlainText(lines[i]);
                if (!/\bcustomer\b/i.test(line)) {
                    continue;
                }

                var addressLines = [];
                var inlineValue = line.replace(/^.*?\bcustomer\s*[:\uFF1A-]?\s*/i, '').trim();
                inlineValue = inlineValue.replace(/^[A-Za-z\s.'-]{2,80}\s*/, '').trim();

                var cleanedInlineValue = cleanAddressLine(inlineValue);
                if (cleanedInlineValue !== '') {
                    addressLines.push(cleanedInlineValue);
                }

                for (var j = i + 1; j < Math.min(lines.length, i + 8); j++) {
                    var currentLine = normalizePlainText(lines[j]);

                    if (/^(?:seller|package|order\s*(?:creation|id|no|number)|o\/n|oin|0in|on\s*:|no\.?\s*[lI1]tem|non-cod|cod|home|standard|tracking|waybill|airbill|awb|parcel|product|weight)\b/i.test(currentLine)) {
                        break;
                    }

                    var cleanedLine = cleanAddressLine(currentLine);
                    if (cleanedLine !== '') {
                        addressLines.push(cleanedLine);
                    }
                }

                if (addressLines.length > 0) {
                    return cleanAddressValue(addressLines.join(' '));
                }
            }

            return '';
        }

        function extractRecipientAddressNearCustomerName(lines, customerName) {
            var targetName = normalizeLookup(customerName);
            if (targetName === '') {
                return '';
            }

            for (var i = 0; i < lines.length; i++) {
                var comparableLine = cleanNameValue(lines[i]);
                var normalizedLine = normalizeLookup(comparableLine);
                if (normalizedLine === '' || (normalizedLine !== targetName && targetName.indexOf(normalizedLine) === -1 && normalizedLine.indexOf(targetName) === -1)) {
                    continue;
                }

                var addressLines = [];
                for (var j = i + 1; j < Math.min(lines.length, i + 6); j++) {
                    var currentLine = lines[j];
                    if (/^(?:phone|tel|mobile|contact|sender|shipper|from|pickup|return\s+to|tracking|waybill|airbill|awb|parcel|package|order\s*(?:id|no|number)|product|weight|cod)\b/i.test(currentLine)) {
                        break;
                    }

                    var cleanedLine = cleanAddressLine(currentLine);
                    if (cleanedLine !== '') {
                        addressLines.push(cleanedLine);
                    }
                }

                if (addressLines.length > 0) {
                    return addressLines.join('\n').trim();
                }
            }

            return '';
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

        function extractShopeeAirbillDataFromPdfItems(items, pageWidth, pageHeight, debug) {
            var lines = groupPdfItemsIntoLines(items);
            var pdfItemDebug = debug ? {} : null;
            var pdfLineDebug = debug ? {} : null;
            var airbillNo = extractAirbillCodeFromPdfItems(items, pageHeight, pdfItemDebug) || extractAirbillCodeFromLines(lines, pdfLineDebug);
            var customerName = cleanNameValue(extractRecipientNameFromPdfItems(items, pageWidth) || extractRecipientNameFromLines(lines));
            var customerAddress = extractRecipientAddressFromPdfItems(items, pageWidth) || extractRecipientAddressFromLines(lines);
            customerAddress = cleanAddressValue(removeCustomerNameFromAddress(customerAddress, customerName));

            if (debug) {
                debug.relevantLines = buildRelevantLineList(lines, 40);
                debug.pdfItemDebug = pdfItemDebug;
                debug.pdfLineDebug = pdfLineDebug;
                debug.pdfResult = {
                    airbillNo: airbillNo || '',
                    customerName: customerName || '',
                    customerAddress: customerAddress || ''
                };
            }

            return {
                airbillNo: airbillNo || '',
                customerName: customerName || '',
                customerAddress: customerAddress || ''
            };
        }

        function extractShopeeAirbillDataFromOcrText(text, fileName, debug) {
            var lines = splitTextIntoLines(text);
            var ocrLineDebug = debug ? {} : null;
            var airbillNo = extractAirbillCodeFromLines(lines, ocrLineDebug) || extractAirbillCodeFromFilename(fileName);
            var customerName = cleanNameValue(extractRecipientNameFromLines(lines));
            var customerAddress = extractJntAddressAfterCustomerLine(lines) || extractRecipientAddressFromLines(lines) || extractRecipientAddressNearCustomerName(lines, customerName);
            customerAddress = cleanAddressValue(removeCustomerNameFromAddress(customerAddress, customerName));

            if (debug) {
                debug.relevantLines = buildRelevantLineList(lines, 40);
                debug.ocrLineDebug = ocrLineDebug;
                debug.filenameFallback = extractAirbillCodeFromFilename(fileName);
                debug.ocrResult = {
                    airbillNo: airbillNo || '',
                    customerName: customerName || '',
                    customerAddress: customerAddress || ''
                };
            }

            return {
                airbillNo: airbillNo || '',
                customerName: customerName || '',
                customerAddress: customerAddress || ''
            };
        }

        function mergeExtractedAirbillData(primaryData, fallbackData, fileName, options) {
            options = options || {};
            var merged = {
                airbillNo: String(primaryData && primaryData.airbillNo ? primaryData.airbillNo : '').trim(),
                customerName: String(primaryData && primaryData.customerName ? primaryData.customerName : '').trim(),
                customerAddress: String(primaryData && primaryData.customerAddress ? primaryData.customerAddress : '').trim()
            };

            if (merged.airbillNo === '') {
                merged.airbillNo = String(fallbackData && fallbackData.airbillNo ? fallbackData.airbillNo : '').trim();
            }
            if (merged.airbillNo === '' && !options.skipFilenameFallback) {
                merged.airbillNo = extractAirbillCodeFromFilename(fileName);
            }
            if (merged.customerName === '') {
                merged.customerName = String(fallbackData && fallbackData.customerName ? fallbackData.customerName : '').trim();
            }
            if (merged.customerAddress === '') {
                merged.customerAddress = String(fallbackData && fallbackData.customerAddress ? fallbackData.customerAddress : '').trim();
            }

            merged.customerName = cleanNameValue(merged.customerName);
            merged.customerAddress = cleanAddressValue(removeCustomerNameFromAddress(merged.customerAddress, merged.customerName));

            return merged;
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
            var customerAddress = config.customerAddressSelector ? document.querySelector(config.customerAddressSelector) : null;
            var statusNode = document.querySelector(config.statusSelector || '');
            var debugPanel = config.debugPanelSelector ? document.querySelector(config.debugPanelSelector) : null;
            var debugWrap = config.debugWrapSelector ? document.querySelector(config.debugWrapSelector) : null;
            var expectsAddress = !!customerAddress;
            var currentDebugState = null;
            if (!fileInput || !airbillNo || !statusNode) {
                return false;
            }

            function renderDebugPanel() {
                if (!debugPanel) {
                    return;
                }

                if (!currentDebugState) {
                    debugPanel.textContent = 'No airbill debug data yet.';
                    return;
                }

                try {
                    debugPanel.textContent = JSON.stringify(currentDebugState, null, 2);
                } catch (error) {
                    debugPanel.textContent = String(error && error.message ? error.message : error);
                }

                if (debugWrap && typeof debugWrap.open !== 'undefined') {
                    debugWrap.open = true;
                }
            }

            function setStatus(message, isError) {
                statusNode.textContent = message;
                if (config.errorClass) {
                    statusNode.classList.toggle(config.errorClass, !!isError);
                }
                if (config.normalClass) {
                    statusNode.classList.toggle(config.normalClass, !isError);
                }
                if (currentDebugState) {
                    currentDebugState.status = message;
                    currentDebugState.statusIsError = !!isError;
                    currentDebugState.statusHistory = currentDebugState.statusHistory || [];
                    pushDebugEntry(currentDebugState.statusHistory, {
                        message: message,
                        isError: !!isError
                    }, 20);
                    renderDebugPanel();
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

            function recognizeCanvasText(canvas) {
                if (typeof Tesseract === 'undefined') {
                    return Promise.resolve('');
                }

                var languages = ['eng+chi_sim', 'chi_sim', 'eng'];
                var index = 0;

                function runAttempt() {
                    if (index >= languages.length) {
                        return Promise.resolve('');
                    }

                    var language = languages[index];
                    index += 1;
                    return Tesseract.recognize(canvas, language).then(function (result) {
                        return result && result.data && result.data.text ? String(result.data.text) : '';
                    }).catch(function () {
                        return runAttempt();
                    });
                }

                return runAttempt();
            }

            function extractOcrTextFromPdfDocument(pdfDoc) {
                if (!pdfDoc || typeof Tesseract === 'undefined') {
                    return Promise.resolve('');
                }

                var maxPages = Math.min(Number(pdfDoc.numPages) || 1, 2);
                var texts = [];
                var queue = Promise.resolve();

                for (var pageNo = 1; pageNo <= maxPages; pageNo++) {
                    (function (currentPageNo) {
                        queue = queue.then(function () {
                            return pdfDoc.getPage(currentPageNo).then(function (page) {
                                var viewport = page.getViewport({ scale: 2.0 });
                                var canvas = document.createElement('canvas');
                                canvas.width = viewport.width;
                                canvas.height = viewport.height;

                                var context = canvas.getContext('2d');
                                if (!context) {
                                    return '';
                                }

                                return page.render({ canvasContext: context, viewport: viewport }).promise.then(function () {
                                    return recognizeCanvasText(canvas);
                                }).then(function (text) {
                                    texts.push(String(text || '').trim());
                                    canvas.width = 0;
                                    canvas.height = 0;
                                    return '';
                                }).catch(function () {
                                    canvas.width = 0;
                                    canvas.height = 0;
                                    return '';
                                });
                            });
                        });
                    })(pageNo);
                }

                return queue.then(function () {
                    return texts.join('\n').trim();
                });
            }

            function loadPdfData(file) {
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
                            pdfDoc: pdfDoc,
                            items: combinedItems,
                            pageWidth: pageWidth,
                            pageHeight: pageHeight
                        };
                    });
                });
            }

            fileInput.addEventListener('change', function () {
                currentDebugState = null;
                renderDebugPanel();
                setStatus('', false);
                if (!this.files || !this.files[0]) {
                    return;
                }

                var selectedFile = this.files[0];
                currentDebugState = {
                    fileName: String(selectedFile.name || ''),
                    fileSize: Number(selectedFile.size) || 0,
                    fileType: String(selectedFile.type || ''),
                    parser: 'shopeeOmsAirbillPdfAutofill'
                };
                renderDebugPanel();
                if (!/\.pdf$/i.test(String(selectedFile.name || ''))) {
                    return;
                }

                setStatus(expectsAddress ? 'Extracting airbill number, customer name and address from PDF...' : 'Extracting airbill number from PDF...', false);

                var loadedPdfDoc = null;
                loadPdfData(selectedFile).then(function (pdfData) {
                    loadedPdfDoc = pdfData && pdfData.pdfDoc ? pdfData.pdfDoc : null;
                    currentDebugState.pdfSummary = {
                        itemCount: Array.isArray(pdfData.items) ? pdfData.items.length : 0,
                        pageWidth: Number(pdfData.pageWidth) || 0,
                        pageHeight: Number(pdfData.pageHeight) || 0
                    };
                    currentDebugState.pdfExtraction = {};
                    renderDebugPanel();

                    var extractedData = extractShopeeAirbillDataFromPdfItems(pdfData.items, pdfData.pageWidth, pdfData.pageHeight, currentDebugState.pdfExtraction);
                    extractedData = mergeExtractedAirbillData(extractedData, null, selectedFile.name || '', {
                        skipFilenameFallback: true
                    });
                    currentDebugState.pdfMergedResult = extractedData;
                    renderDebugPanel();

                    if (
                        (extractedData.airbillNo !== '' && (!expectsAddress || extractedData.customerAddress !== '') && (!customerName || extractedData.customerName !== ''))
                        || typeof Tesseract === 'undefined'
                    ) {
                        return extractedData;
                    }

                    setStatus(expectsAddress ? 'Scanning airbill PDF image for address and airbill number...' : 'Scanning airbill PDF image for airbill number...', false);
                    return extractOcrTextFromPdfDocument(loadedPdfDoc).then(function (ocrText) {
                        currentDebugState.ocrTextPreview = splitTextIntoLines(ocrText).slice(0, 40);
                        currentDebugState.ocrExtraction = {};
                        var mergedData = mergeExtractedAirbillData(
                            extractedData,
                            extractShopeeAirbillDataFromOcrText(ocrText, selectedFile.name || '', currentDebugState.ocrExtraction),
                            selectedFile.name || ''
                        );
                        currentDebugState.finalMergedResult = mergedData;
                        renderDebugPanel();
                        return mergedData;
                    }).catch(function () {
                        return mergeExtractedAirbillData(extractedData, null, selectedFile.name || '');
                    });
                }).then(function (extractedData) {
                    currentDebugState.finalAppliedResult = extractedData;
                    renderDebugPanel();

                    if (extractedData.airbillNo !== '') {
                        airbillNo.value = extractedData.airbillNo;
                        dispatchInputEvent(airbillNo);
                    }
                    if (customerName && extractedData.customerName !== '') {
                        customerName.value = extractedData.customerName;
                        dispatchInputEvent(customerName);
                    }
                    if (customerAddress && extractedData.customerAddress !== '') {
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

                    if (extractedData.airbillNo !== '' && (!expectsAddress || extractedData.customerAddress !== '')) {
                        setStatus('Airbill PDF extracted successfully.', false);
                    } else if (extractedData.airbillNo !== '') {
                        setStatus(expectsAddress ? 'Airbill number extracted, but address was not detected. Please fill the shipping receiver address manually.' : 'Airbill number extracted successfully.', false);
                    } else {
                        setStatus(expectsAddress ? 'Unable to detect the airbill number, customer name or address from this PDF. Please fill them manually.' : 'Unable to detect the airbill number from this PDF. Please fill it manually.', true);
                    }
                }).catch(function (error) {
                    if (currentDebugState) {
                        currentDebugState.error = String(error && error.message ? error.message : error);
                        renderDebugPanel();
                    }
                    setStatus(expectsAddress ? 'Unable to read this PDF. Please fill the airbill number, customer name and address manually.' : 'Unable to read this PDF. Please fill the airbill number manually.', true);
                }).finally(function () {
                    if (loadedPdfDoc && typeof loadedPdfDoc.destroy === 'function') {
                        try {
                            loadedPdfDoc.destroy();
                        } catch (error) {
                        }
                    }
                });
            });

            fileInput.dataset.airbillPdfAutofillBound = '1';
            return true;
        }

        var module = {
            __sgEnhanced: true,
            bind: bind,
            extractShopeeAirbillDataFromPdfItems: extractShopeeAirbillDataFromPdfItems,
            extractShopeeAirbillDataFromOcrText: extractShopeeAirbillDataFromOcrText
        };

        return module;
    })();
}
