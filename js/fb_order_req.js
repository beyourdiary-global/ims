$('#for_attach').on('change', function() {
    previewImage(this, 'for_attach_preview')
})

var fbOrderReqConfig = window.fbOrderReqConfig || {};
var fbOrderReqSiteUrl = fbOrderReqConfig.siteUrl || '';
var fbOrderReqTables = fbOrderReqConfig.tables || {};

//autocomplete
$(document).ready(function() {
    var act = getParameterByName('act');
    var trackOrderBtn = document.getElementById('trackOrderBtn');
    if (act !== 'I' && trackOrderBtn) {
        trackOrderBtn.addEventListener('click', function() {
            // Copy tracking number to clipboard
            var trackingNumber = this.getAttribute('data-tracking-id');
            navigator.clipboard.writeText(trackingNumber);
        });
    }
    if (!($("#for_pic").attr('disabled'))) {
        $("#for_pic").keyup(function() {
            var param = {
                search: $(this).val(),
                searchType: 'name', // column of the table
                elementID: $(this).attr('id'), // id of the input
                hiddenElementID: $(this).attr('id') + '_hidden', // hidden input for storing the value
                dbTable: fbOrderReqTables.user || '', // json filename (generated when login)
            }
            searchInput(param, fbOrderReqSiteUrl);
        });

    }
    //country
    if (!($("#for_country").attr('disabled'))) {
        $("#for_country").keyup(function() {
            var param = {
                search: $(this).val(),
                searchType: 'nicename', // column of the table
                elementID: $(this).attr('id'), // id of the input
                hiddenElementID: $(this).attr('id') + '_hidden', // hidden input for storing the value
                dbTable: fbOrderReqTables.countries || '', // json filename (generated when login)
            }
            searchInput(param, fbOrderReqSiteUrl);
        });

    }
    //brand
    if (!($("#for_brand").attr('disabled'))) {
        $("#for_brand").keyup(function() {
            var param = {
                search: $(this).val(),
                searchType: 'name', // column of the table
                elementID: $(this).attr('id'), // id of the input
                hiddenElementID: $(this).attr('id') + '_hidden', // hidden input for storing the value
                dbTable: fbOrderReqTables.brand || '', // json filename (generated when login)
            }
            searchInput(param, fbOrderReqSiteUrl);
        });

    }
    //series
    if (!($("#for_series").attr('disabled'))) {
        $("#for_series").keyup(function() {
            var param = {
                search: $(this).val(),
                searchType: 'name', // column of the table
                elementID: $(this).attr('id'), // id of the input
                hiddenElementID: $(this).attr('id') + '_hidden', // hidden input for storing the value
                dbTable: fbOrderReqTables.series || '', // json filename (generated when login)
            }
            searchInput(param, fbOrderReqSiteUrl);
        });

    }
    //package
    if (!($("#for_pkg").attr('disabled'))) {
        $("#for_pkg").keyup(function() {
            var param = {
                search: $(this).val(),
                searchType: 'name', // column of the table
                elementID: $(this).attr('id'), // id of the input
                hiddenElementID: $(this).attr('id') + '_hidden', // hidden input for storing the value
                dbTable: fbOrderReqTables.package || '', // json filename (generated when login)
            }
            searchInput(param, fbOrderReqSiteUrl);
        });

    }
    //fb page account
    if (!($("#for_fbpage").attr('disabled'))) {
        $("#for_fbpage").keyup(function() {
            var param = {
                search: $(this).val(),
                searchType: 'name', // column of the table
                elementID: $(this).attr('id'), // id of the input
                hiddenElementID: $(this).attr('id') + '_hidden', // hidden input for storing the value
                dbTable: fbOrderReqTables.facebookPage || '', // json filename (generated when login)
            }
            searchInput(param, fbOrderReqSiteUrl);
        });

    }
    // channel
    if (!($("#for_channel").attr('disabled'))) {
        $("#for_channel").keyup(function() {
            var param = {
                search: $(this).val(),
                searchType: 'name', // column of the table
                elementID: $(this).attr('id'), // id of the input
                hiddenElementID: $(this).attr('id') + '_hidden', // hidden input for storing the value
                dbTable: fbOrderReqTables.channel || '', // json filename (generated when login)
            }
            searchInput(param, fbOrderReqSiteUrl);
        });
    }
    //payment method
    if (!($("#for_pay_meth").attr('disabled'))) {
        $("#for_pay_meth").keyup(function() {
            var param = {
                search: $(this).val(),
                searchType: 'name', // column of the table
                elementID: $(this).attr('id'), // id of the input
                hiddenElementID: $(this).attr('id') + '_hidden', // hidden input for storing the value
                dbTable: fbOrderReqTables.paymentMethod || '', // json filename (generated when login)
            }
            searchInput(param, fbOrderReqSiteUrl);
        });

    }
    $("#for_pkg").keyup(function() {
        // Empty the #for_price field
        $("#for_price").val('');
    });
    $("#for_pkg").change(calculatePrice);
})

function calculatePrice() {
    var paramPkg = {
        search: $("#for_pkg_hidden").val(),
        searchCol: 'id',
        searchType: '*',
        dbTable: fbOrderReqTables.package || '',
        isFin: 0,
    };

    retrieveDBData(paramPkg, fbOrderReqSiteUrl, function (result) {
        if (result && result.length > 0) {
            var pkg_price = parseFloat(result[0]['price']);
            if (memberPointGetUseType() === 'cashback') {
                memberPointSetOriginalPrice(pkg_price);
                memberPointPriceState.cashbackActive = true;
                memberPointUpdateCashbackPriceSummary();
            } else {
                $("#for_price").val(pkg_price.toFixed(2));
            }
            
        } else {
            console.error('Error retrieving Courier data');
        }
    });
}

function fbOrderReqShowNotification(message, type) {
    if (typeof showNotification === 'function') {
        showNotification(message, type || 'error');
        return;
    }
    console.log((type || 'info') + ': ' + message);
}

function fbOrderReqSetAutocompleteField(fieldId, hiddenFieldId, label, value) {
    $('#' + fieldId).val(label || '');
    $('#' + hiddenFieldId).val(value || '');
}

var fbOrderReqCustomerSearchState = {
    requestId: 0
};

function fbOrderReqRemoveCustomerSuggestions() {
    $('#searchResult_for_name').empty().remove();
    $('#clear_for_name').remove();
}

function fbOrderReqRenderCustomerSuggestions(results) {
    var elementID = 'for_name';
    var currentValue = $.trim($('#for_name').val());
    var selectedName = $.trim(String($('#for_name').data('selectedName') || ''));
    var selectedCustomerId = parseInt($('#for_customer_id').val(), 10) || 0;

    if (selectedCustomerId > 0 && selectedName !== '' && currentValue === selectedName) {
        fbOrderReqRemoveCustomerSuggestions();
        return;
    }

    ensureAutocompleteResultShell(elementID);
    var $resultList = $('#searchResult_' + elementID);
    setWidth(elementID, 'searchResult_' + elementID);
    positionAutocompleteResult(elementID);

    var resultRows = Array.isArray(results) ? results : [];
    $resultList.empty();

    if (resultRows.length === 0) {
        fbOrderReqRemoveCustomerSuggestions();
        return;
    }

    resultRows.forEach(function(row) {
        $resultList.append(
            $('<li></li>')
                .attr('value', String(row.id || ''))
                .attr('data-name', String(row.name || ''))
                .text(String(row.name || ''))
        );
    });

    $resultList
        .find('li')
        .off('click.fbCustomerSearch')
        .on('click.fbCustomerSearch', function() {
            setText(this, '#for_name', '#for_customer_id');
            var selectedId = parseInt($('#for_customer_id').val(), 10) || 0;
            $('#for_name').data('selectedName', $.trim($('#for_name').val()));
            fbOrderReqCustomerSearchState.requestId++;
            if (selectedId > 0) {
                fbOrderReqFetchCustomerProfile(selectedId);
            }
            fbOrderReqRemoveCustomerSuggestions();
        });
}

function fbOrderReqSearchCustomerSuggestions(keyword) {
    keyword = $.trim(keyword || '');
    if (keyword === '') {
        fbOrderReqRemoveCustomerSuggestions();
        return;
    }

    if ((parseInt($('#for_customer_id').val(), 10) || 0) > 0 && keyword === $.trim(String($('#for_name').data('selectedName') || ''))) {
        fbOrderReqRemoveCustomerSuggestions();
        return;
    }

    var requestId = ++fbOrderReqCustomerSearchState.requestId;

    $.ajax({
        url: window.location.href.split('#')[0],
        type: 'post',
        dataType: 'json',
        data: {
            fb_customer_search_ajax: 1,
            search: keyword
        },
        success: function(response) {
            if (requestId !== fbOrderReqCustomerSearchState.requestId) {
                return;
            }

            if ($.trim($('#for_name').val()) !== keyword) {
                return;
            }

            fbOrderReqRenderCustomerSuggestions(response && response.results ? response.results : []);
        },
        error: function() {
            if (requestId !== fbOrderReqCustomerSearchState.requestId) {
                return;
            }
            fbOrderReqRemoveCustomerSuggestions();
        }
    });
}

function fbOrderReqClearCustomerAutofill() {
    $('#for_link').val('');
    $('#for_contact').val('');
    fbOrderReqSetAutocompleteField('for_pic', 'for_pic_hidden', '', '');
    fbOrderReqSetAutocompleteField('for_country', 'for_country_hidden', '', '');
    fbOrderReqSetAutocompleteField('for_brand', 'for_brand_hidden', '', '');
    fbOrderReqSetAutocompleteField('for_series', 'for_series_hidden', '', '');
    fbOrderReqSetAutocompleteField('for_fbpage', 'for_fbpage_hidden', '', '');
    fbOrderReqSetAutocompleteField('for_channel', 'for_channel_hidden', '', '');
    $('#for_rec_name').val('');
    $('#for_rec_ctc').val('');
    $('#for_rec_add').val('');
    $('#for_remark').val('');
}

function fbOrderReqApplyCustomerData(customer) {
    customer = customer || {};
    $('#for_name').val(customer.name || '');
    $('#for_name').data('selectedName', customer.name || '');
    $('#for_customer_id').val(customer.id || '');
    $('#for_link').val(customer.fb_link || '');
    $('#for_contact').val(customer.contact || '');
    fbOrderReqSetAutocompleteField('for_pic', 'for_pic_hidden', customer.sales_pic_label || '', customer.sales_pic || '');
    fbOrderReqSetAutocompleteField('for_country', 'for_country_hidden', customer.country_label || '', customer.country || '');
    fbOrderReqSetAutocompleteField('for_brand', 'for_brand_hidden', customer.brand_label || '', customer.brand || '');
    fbOrderReqSetAutocompleteField('for_series', 'for_series_hidden', customer.series_label || '', customer.series || '');
    fbOrderReqSetAutocompleteField('for_fbpage', 'for_fbpage_hidden', customer.fb_page_label || '', customer.fb_page || '');
    fbOrderReqSetAutocompleteField('for_channel', 'for_channel_hidden', customer.channel_label || '', customer.channel || '');
    $('#for_rec_name').val(customer.ship_rec_name || '');
    $('#for_rec_ctc').val(customer.ship_rec_contact || '');
    $('#for_rec_add').val(customer.ship_rec_add || '');
    $('#for_remark').val(customer.remark || '');
}

function fbOrderReqFetchCustomerProfile(customerId) {
    customerId = parseInt(customerId, 10) || 0;
    if (customerId <= 0) {
        return;
    }

    $.ajax({
        url: window.location.href.split('#')[0],
        type: 'post',
        dataType: 'json',
        data: {
            fb_customer_ajax: 1,
            customer_id: customerId
        },
        success: function(response) {
            if (!response || response.success !== true || !response.customer) {
                $('#for_customer_id').val('');
                fbOrderReqShowNotification((response && response.message) ? response.message : 'Unable to load Facebook customer details.', 'error');
                return;
            }

            fbOrderReqApplyCustomerData(response.customer);
        },
        error: function() {
            $('#for_customer_id').val('');
            fbOrderReqShowNotification('Unable to load Facebook customer details.', 'error');
        }
    });
}

function fbOrderReqBindCustomerAutocomplete() {
    if ($("#for_name").attr('disabled')) {
        return;
    }

    var act = getParameterByName('act');
    var initialCustomerId = parseInt($('#for_customer_id').val(), 10) || 0;
    $('#for_name').data('selectedName', $.trim($('#for_name').val()));

    if (act === 'I' && initialCustomerId > 0) {
        fbOrderReqFetchCustomerProfile(initialCustomerId);
    }

    $('#for_name').on('keyup', function() {
        fbOrderReqSearchCustomerSuggestions($(this).val());
    });

    $('#for_name').on('input', function() {
        var currentValue = $.trim($(this).val());
        var selectedName = $.trim(String($(this).data('selectedName') || ''));

        if (currentValue === '') {
            $('#for_customer_id').val('');
            $(this).data('selectedName', '');
            fbOrderReqClearCustomerAutofill();
            fbOrderReqCustomerSearchState.requestId++;
            fbOrderReqRemoveCustomerSuggestions();
            return;
        }

        if (selectedName !== '' && currentValue !== selectedName) {
            $('#for_customer_id').val('');
            $(this).data('selectedName', '');
        }
    });

    $('#for_name').on('change', function() {
        var customerId = parseInt($('#for_customer_id').val(), 10) || 0;
        if (customerId > 0) {
            $(this).data('selectedName', $.trim($(this).val()));
            fbOrderReqRemoveCustomerSuggestions();
        }
        if (customerId > 0) {
            fbOrderReqFetchCustomerProfile(customerId);
        }
    });

    $('#for_name').on('blur', function() {
        setTimeout(function() {
            fbOrderReqRemoveCustomerSuggestions();
        }, 150);
    });
}

var memberPointConfig = fbOrderReqConfig.memberPoint || {
    locked: false,
    viewOnly: false,
    initialUseType: 'none',
    initialCashbackPoints: 0,
    initialOriginalPrice: '',
    lookupUrl: window.location.href.split('#')[0],
    platforms: {}
};

var memberPointLookupTimer = null;
var memberPointLastLookupKey = '';
var memberPointPriceState = {
    cashbackActive: false
};

function memberPointShowNotification(message, type) {
    if (typeof showNotification === 'function') {
        showNotification(message, type || 'error');
        return;
    }
    console.log((type || 'info') + ': ' + message);
}

function memberPointGetPlatformLabel(platform) {
    if (platform === 'shopee') {
        return 'Shopee';
    }
    if (platform === 'lazada') {
        return 'Lazada';
    }
    return '';
}

function memberPointParseAmount(value) {
    var numeric = parseFloat(value);
    return isNaN(numeric) ? 0 : numeric;
}

function memberPointGetAvailablePoints() {
    return parseInt($('#member_point_available_points').text() || '0', 10) || 0;
}

function memberPointGetUseType() {
    return $('#member_point_use_type').val() || 'none';
}

function memberPointGetOriginalPrice() {
    return memberPointParseAmount($('#member_point_original_price').val());
}

function memberPointSetOriginalPrice(value) {
    var amount = memberPointParseAmount(value);
    $('#member_point_original_price').val(amount > 0 ? amount.toFixed(2) : '');
}

function memberPointGetCashbackCap(originalPrice) {
    originalPrice = memberPointParseAmount(originalPrice);
    if (originalPrice <= 0) {
        return 0;
    }
    return Math.max(0, Math.min(Math.floor(originalPrice * 0.30), memberPointGetAvailablePoints()));
}

function memberPointRestoreOriginalPrice() {
    if (memberPointConfig.locked || memberPointConfig.viewOnly) {
        return;
    }
    var originalPrice = memberPointGetOriginalPrice();
    if (originalPrice > 0) {
        $('#for_price').val(originalPrice.toFixed(2));
    }
    $('#for_price').prop('readonly', false);
}

function memberPointUpdateCashbackPriceSummary() {
    var useType = memberPointGetUseType();
    var $giftWrap = $('#member_point_gift_wrap');
    var $cashbackWrap = $('#member_point_cashback_wrap');
    var $summary = $('#member_point_cashback_price_summary');
    var $help = $('#member_point_cashback_help');
    var $limitHint = $('#member_point_cashback_limit_hint');
    var $cashbackInput = $('#member_point_cashback_points');
    var isCashback = useType === 'cashback';

    if ($giftWrap.length) {
        $giftWrap.toggle(useType === 'gift');
    }
    if ($cashbackWrap.length) {
        $cashbackWrap.toggle(isCashback);
    }

    if (!isCashback) {
        if (memberPointPriceState.cashbackActive) {
            memberPointRestoreOriginalPrice();
        }
        memberPointPriceState.cashbackActive = false;
        $summary.hide();
        $help.text('');
        $limitHint.text('');
        if (useType === 'none' && !memberPointConfig.locked && !memberPointConfig.viewOnly) {
            $('#member_point_redeem_id').val('');
            $cashbackInput.val('');
            $('#member_point_original_price').val('');
        } else if (useType === 'gift' && !memberPointConfig.locked && !memberPointConfig.viewOnly) {
            $cashbackInput.val('');
            $('#member_point_original_price').val('');
        }
        return;
    }

    if (!memberPointPriceState.cashbackActive) {
        if (memberPointGetOriginalPrice() <= 0) {
            memberPointSetOriginalPrice($('#for_price').val());
        }
        memberPointPriceState.cashbackActive = true;
    }

    var originalPrice = memberPointGetOriginalPrice();
    var cashbackCap = memberPointGetCashbackCap(originalPrice);
    var cashbackPoints = parseInt($cashbackInput.val() || '0', 10) || 0;

    if (!memberPointConfig.locked && !memberPointConfig.viewOnly && cashbackPoints > cashbackCap) {
        cashbackPoints = cashbackCap;
        $cashbackInput.val(cashbackPoints > 0 ? cashbackPoints : '');
    }

    var finalPrice = originalPrice > 0 ? Math.max(0, originalPrice - cashbackPoints) : 0;

    if (!memberPointConfig.viewOnly) {
        $('#for_price').prop('readonly', true);
    }
    $('#for_price').val(finalPrice > 0 || originalPrice > 0 ? finalPrice.toFixed(2) : '');

    $('#member_point_original_price_display').text('RM ' + originalPrice.toFixed(2));
    $('#member_point_cashback_deduction_display').text('- RM ' + cashbackPoints.toFixed(2));
    $('#member_point_cashback_final_price_display').text('RM ' + finalPrice.toFixed(2));
    $help.text('Maximum cashback now: ' + cashbackCap + ' points. Available balance: ' + memberPointGetAvailablePoints() + ' points.');
    $limitHint.text('Cashback is capped at 30% of the original order amount.');
    $summary.show();
}

function memberPointRenderRewardSummary(rewards) {
    var $list = $('#member_point_reward_list');
    var $empty = $('#member_point_reward_empty');
    rewards = Array.isArray(rewards) ? rewards : [];

    if (!$list.length) {
        return;
    }

    $list.empty();
    if (rewards.length === 0) {
        if ($empty.length === 0) {
            $('<div id="member_point_reward_empty">No redeemable gift.</div>').insertBefore($list);
            $empty = $('#member_point_reward_empty');
        }
        $empty.show();
        $list.hide();
        return;
    }

    $empty.hide();
    rewards.forEach(function (reward) {
        var displayText = reward && reward.display_text ? reward.display_text : '';
        if (displayText) {
            $list.append($('<li></li>').text(displayText));
        }
    });
    $list.show();
}

function memberPointPopulateRedeemSelect(rewards, selectedId) {
    var $select = $('#member_point_redeem_id');
    var isDisabled = $select.prop('disabled');
    rewards = Array.isArray(rewards) ? rewards : [];
    selectedId = String(selectedId || '');

    if (!$select.length || memberPointConfig.locked || memberPointConfig.viewOnly || isDisabled) {
        return;
    }

    $select.empty().append($('<option></option>').val('').text('No Redeem'));
    rewards.forEach(function (reward) {
        var rewardId = reward && reward.id ? String(reward.id) : '';
        var displayText = reward && reward.display_text ? reward.display_text : '';
        if (!rewardId || !displayText) {
            return;
        }
        var $option = $('<option></option>').val(rewardId).text(displayText);
        if (rewardId === selectedId) {
            $option.prop('selected', true);
        }
        $select.append($option);
    });
}

function memberPointRenderLookup(payload, options) {
    options = options || {};
    payload = payload || {};

    var platform = $('#member_point_platform').val();
    var customerLabel = payload.customer_label || $('#member_point_customer_label').val() || '';
    var linkedLabel = customerLabel;
    var platformLabel = memberPointGetPlatformLabel(platform);

    if (platformLabel && customerLabel) {
        linkedLabel = platformLabel + ' | ' + customerLabel;
    } else if (!customerLabel) {
        linkedLabel = 'No member linked';
    }

    $('#member_point_available_points').text(parseInt(payload.available_points || 0, 10));
    $('#member_point_customer_summary').text(linkedLabel);
    $('#member_point_customer_search').val(customerLabel);
    $('#member_point_customer_label').val(customerLabel);
    memberPointRenderRewardSummary(payload.rewards || []);
    memberPointPopulateRedeemSelect(payload.rewards || [], options.selectedRedeemId || $('#member_point_redeem_id').val());
    memberPointUpdateCashbackPriceSummary();
}

function memberPointClearState(clearInput) {
    if (clearInput) {
        $('#member_point_customer_search').val('');
    }
    $('#member_point_customer_id').val('');
    $('#member_point_customer_label').val('');
    $('#member_point_available_points').text('0');
    $('#member_point_customer_summary').text('No member linked');
    memberPointRenderRewardSummary([]);
    if (!memberPointConfig.locked && !memberPointConfig.viewOnly) {
        $('#member_point_redeem_id').empty().append($('<option></option>').val('').text('No Redeem'));
    }
    memberPointUpdateCashbackPriceSummary();
}

function memberPointFetchLookup(forceSelectedRedeemId) {
    var platform = $('#member_point_platform').val();
    var customerId = $('#member_point_customer_id').val();
    var lookupKey = platform + ':' + customerId;

    if (!platform || !customerId) {
        memberPointClearState(false);
        return;
    }

    if (!forceSelectedRedeemId && memberPointLastLookupKey === lookupKey) {
        return;
    }

    memberPointLastLookupKey = lookupKey;

    $.ajax({
        url: memberPointConfig.lookupUrl,
        type: 'POST',
        dataType: 'json',
        data: {
            member_point_ajax: 1,
            member_point_platform: platform,
            member_point_customer_id: customerId
        }
    }).done(function (response) {
        if (!response || !response.success) {
            memberPointClearState(false);
            memberPointShowNotification(response && response.message ? response.message : 'Unable to load member point details.', 'error');
            return;
        }

        memberPointRenderLookup(response, {
            selectedRedeemId: forceSelectedRedeemId || $('#member_point_redeem_id').val()
        });
    }).fail(function () {
        memberPointClearState(false);
        memberPointShowNotification('Unable to load member point details. Please try again.', 'error');
    });
}

function memberPointQueueLookup() {
    clearTimeout(memberPointLookupTimer);
    memberPointLookupTimer = setTimeout(function () {
        var customerId = $('#member_point_customer_id').val();
        var customerText = $.trim($('#member_point_customer_search').val());
        if (!customerText) {
            memberPointLastLookupKey = '';
            memberPointClearState(false);
            return;
        }

        if (customerId) {
            memberPointFetchLookup();
        }
    }, 220);
}

function memberPointBindAutocomplete() {
    if (memberPointConfig.locked || memberPointConfig.viewOnly) {
        return;
    }

    $('#member_point_customer_search').on('keyup', function () {
        var platform = $('#member_point_platform').val();
        var config = memberPointConfig.platforms[platform];
        if (!config) {
            return;
        }

        var param = {
            search: $(this).val(),
            searchType: config.searchType,
            elementID: $(this).attr('id'),
            hiddenElementID: 'member_point_customer_id',
            dbTable: config.dbTable
        };
        searchInput(param, fbOrderReqSiteUrl);
        memberPointQueueLookup();
    });

    $('#member_point_customer_search').on('input', function () {
        $('#member_point_customer_label').val('');
        if ($.trim($(this).val()) === '') {
            $('#member_point_use_type').val('none');
            $('#member_point_cashback_points').val('');
            memberPointLastLookupKey = '';
            memberPointClearState(false);
        } else {
            $('#member_point_customer_id').val('');
            $('#member_point_redeem_id').val('');
        }
        $('.member-point-customer-err, .member-point-platform-err, .member-point-redeem-err').remove();
    });

    $('#member_point_customer_search').on('change blur', function () {
        memberPointQueueLookup();
    });

    $('#member_point_platform').on('change', function () {
        $('#member_point_use_type').val('none');
        $('#member_point_cashback_points').val('');
        memberPointLastLookupKey = '';
        memberPointClearState(true);
        $('.member-point-customer-err, .member-point-platform-err, .member-point-redeem-err').remove();
    });
}

//jQuery form validation
$("#for_name").on("input", function() {
    $(".for-name-err").remove();
});

$("#for_link").on("input", function() {
    $(".for-link-err").remove();
});

$("#for_contact").on("input", function() {
    $(".for-contact-err").remove();
});

$("#for_pic").on("input", function() {
    $(".for-pic-err").remove();
});

$("#for_country").on("input", function() {
    $(".for-country-err").remove();
});

$("#for_brand").on("input", function() {
    $(".for-brand-err").remove();
});

$("#for_series").on("input", function() {
    $(".for-series-err").remove();
});

$("#for_pkg").on("input", function() {
    $(".for-pkg-err").remove();
});

$("#for_fbpage").on("input", function() {
    $(".for-fbpage-err").remove();
});

$("#for_channel").on("input", function() {
    $(".for-channel-err").remove();
});

$("#for_price").on("input", function() {
    $(".for-price-err").remove();
    if (memberPointGetUseType() !== 'cashback') {
        $('#member_point_original_price').val('');
        memberPointPriceState.cashbackActive = false;
    }
});

$("#for_pay_meth").on("input", function() {
    $(".for-pay-err").remove();
});

$("#for_rec_name").on("input", function() {
    $(".for-rec-name-err").remove();
});

$("#for_rec_ctc").on("input", function() {
    $(".for-rec-ctc-err").remove();
});

$("#for_rec_add").on("input", function() {
    $(".for-rec-add-err").remove();
});

$("#for_attach").on("input", function() {
    $(".for-attach-err").remove();
});

$("#member_point_platform").on("input change", function() {
    $(".member-point-platform-err").remove();
});

$("#member_point_customer_search").on("input", function() {
    $(".member-point-customer-err").remove();
});

$("#member_point_redeem_id").on("input change", function() {
    $(".member-point-redeem-err").remove();
});

$("#member_point_use_type").on("input change", function() {
    $(".member-point-redeem-err").remove();
    memberPointUpdateCashbackPriceSummary();
});

$("#member_point_cashback_points").on("input change", function() {
    $(".member-point-redeem-err").remove();
    memberPointUpdateCashbackPriceSummary();
});

memberPointBindAutocomplete();
fbOrderReqBindCustomerAutocomplete();
memberPointUpdateCashbackPriceSummary();

$('.submitBtn').on('click', function (event) {
    event.preventDefault();
    $(".error-message").remove();
    var name_chk = 0;
    var link_chk = 0;
    var ctc_chk = 0;
    var pic_chk = 0;
    var country_chk = 0;
    var brand_chk = 0;
    var series_chk = 0;
    var pkg_chk = 0;
    var fbpage_chk = 0;
    var channel_chk = 0;
    var price_chk = 0;
    var pay_chk = 0;
    var rec_name_chk = 0;
    var rec_ctc_chk = 0;
    var rec_add_chk = 0;
    var attach_chk = 0;
    var member_point_chk = 1;

    if ($('#for_name').val() === '' || $('#for_name').val() === null || $('#for_name')
        .val() === undefined) {
        name_chk = 0;
        $("#for_name").after(
            '<span class="error-message for-name-err">Name is required!</span>');
    } else {
        $(".for-name-err").remove();
        name_chk = 1;
    }

    if (($('#for_link').val() === '' || $('#for_link').val() === null || $('#for_link')
            .val() === undefined)) {
        link_chk = 0;
        $("#for_link").after(
            '<span class="error-message for-link-err">Facebook Link is required!</span>');
    } else {
        $(".for-link-err").remove();
        link_chk = 1;
    }

    if (($('#for_contact').val() === '' || $('#for_contact').val() === null || $('#for_contact')
            .val() === undefined)) {
        ctc_chk = 0;
        $("#for_contact").after(
            '<span class="error-message for-contact-err">Contact is required!</span>');
    } else {
        $(".for-contact-err").remove();
        ctc_chk = 1;
    }


    if (($('#for_pic').val() === ''  || $('#for_pic').val() == '0' || $('#for_pic').val() === null || $('#for_pic')
            .val() === undefined)) {
        pic_chk = 0;
        $("#for_pic").after(
            '<span class="error-message for-pic-err">Sales Person-In-Charge is required!</span>');
    } else {
        $(".for-pic-err").remove();
        pic_chk = 1;
    }


    if (($('#for_country').val() == '' || $('#for_country').val() == '0' || $('#for_country').val() === null || $('#for_country')
            .val() === undefined)) {
        country_chk = 0;
        $("#for_country").after(
            '<span class="error-message for-country-err">Country is required!</span>');
    } else {
        $(".for-country-err").remove();
        country_chk = 1;
    }

    if (($('#for_brand').val() == '' || $('#for_brand').val() == '0' || $('#for_brand').val() === null || $('#for_brand')
            .val() === undefined)) {
        brand_chk = 0;
        $("#for_brand").after(
            '<span class="error-message for-brand-err">Brand is required!</span>');
    } else {
        $(".for-brand-err").remove();
        brand_chk = 1;
    }

    if (($('#for_series').val() == '' || $('#for_series').val() == '0' || $('#for_series').val() === null || $('#for_series')
            .val() === undefined)) {
        series_chk = 0;
        $("#for_series").after(
            '<span class="error-message for-series-err">Series is required!</span>');
    } else {
        $(".for-series-err").remove();
        series_chk = 1;
    }

    if (($('#for_pkg').val() == '' || $('#for_pkg').val() == '0' || $('#for_pkg').val() === null || $('#for_pkg')
            .val() === undefined)) {
        pkg_chk = 0;
        $("#for_pkg").after(
            '<span class="error-message for-pkg-err">Package is required!</span>');
    } else {
        $(".for-pkg-err").remove();
        pkg_chk = 1;
    }

    if (($('#for_fbpage').val() == '' || $('#for_fbpage').val() == '0' || $('#for_fbpage').val() === null || $('#for_fbpage')
            .val() === undefined)) {
        fbpage_chk = 0;
        $("#for_fbpage").after(
            '<span class="error-message for-fbpage-err">Facebook Page is required!</span>');
    } else {
        $(".for-fbpage-err").remove();
        fbpage_chk = 1;
    }

    if (($('#for_channel').val() == '' || $('#for_channel').val() == '0' || $('#for_channel').val() === null || $('#for_channel')
            .val() === undefined)) {
        channel_chk = 0;
        $("#for_channel").after(
            '<span class="error-message for-channel-err">Channel is required!</span>');
    } else {
        $(".for-channel-err").remove();
        channel_chk = 1;
    }

    if (($('#for_price').val() == '' || $('#for_price').val() == '0' || $('#for_price').val() === null || $('#for_price')
            .val() === undefined)) {
        price_chk = 0;
        $("#for_price").after(
            '<span class="error-message for-price-err">Price is required!</span>');
    } else {
        $(".for-price-err").remove();
        price_chk = 1;
    }

    if (($('#for_pay_meth').val() == '' || $('#for_pay_meth').val() == '0' || $('#for_pay_meth').val() === null || $('#for_pay_meth')
            .val() === undefined)) {
        pay_chk = 0;
        $("#for_pay_meth").after(
            '<span class="error-message for-pay-err">Payment Method is required!</span>');
    } else {
        $(".for-pay-err").remove();
        pay_chk = 1;
    }

    if (($('#for_rec_name').val() == '' || $('#for_rec_name').val() === null || $('#for_rec_name')
            .val() === undefined)) {
        rec_name_chk = 0;
        $("#for_rec_name").after(
            '<span class="error-message for-rec-name-err">Shipping Receiver Name is required!</span>');
    } else {
        $(".for-rec-name-err").remove();
        rec_name_chk = 1;
    }

    if (($('#for_rec_ctc').val() == '' || $('#for_rec_ctc').val() === null || $('#for_rec_ctc')
            .val() === undefined)) {
        rec_ctc_chk = 0;
        $("#for_rec_ctc").after(
            '<span class="error-message for-rec-ctc-err">Shipping Receiver Contact is required!</span>');
    } else {
        $(".for-rec-ctc-err").remove();
        rec_ctc_chk = 1;
    }

    if (($('#for_rec_add').val() == '' || $('#for_rec_add').val() === null || $('#for_rec_add')
            .val() === undefined)) {
        rec_add_chk = 0;
        $("#for_rec_add").after(
            '<span class="error-message for-rec-ctc-err">Shipping Receiver Address is required!</span>');
    } else {
        $(".for-rec-add-err").remove();
        rec_add_chk = 1;
    }


    var fileInput = $('#for_attach')[0];
    var hasSelectedAttachment = fileInput && fileInput.files && fileInput.files.length > 0;

    // Check if a new file is selected or if there is an existing attachment
    if ((!hasSelectedAttachment) && ($('#for_attachmentValue').val() == '' || $('#for_attachmentValue').val() == '0' || $('#for_attachmentValue').val() === null || $('#for_attachmentValue')
    .val() === undefined)) {
        // No file selected and no existing attachment
        attach_chk = 0;
        $("#for_attach").after('<span class="error-message for-attach-err">Attachment is required!</span>');
    } else {
        // File selected or existing attachment present
        attach_chk = 1;
    }

    var memberPointPlatform = $('#member_point_platform').val();
    var memberPointCustomerSearch = $.trim($('#member_point_customer_search').val());
    var memberPointCustomerId = $('#member_point_customer_id').val();
    var memberPointUseType = memberPointGetUseType();
    var memberPointRedeemId = $('#member_point_redeem_id').val();
    var memberPointCashbackPoints = parseInt($('#member_point_cashback_points').val() || '0', 10) || 0;
    var memberPointOriginalPrice = memberPointGetOriginalPrice();
    var memberPointAvailablePoints = memberPointGetAvailablePoints();

    if (!memberPointConfig.locked && !memberPointConfig.viewOnly) {
        if (memberPointCustomerSearch !== '' && !memberPointPlatform) {
            member_point_chk = 0;
            $("#member_point_platform").after('<span class="error-message member-point-platform-err">Platform is required when linking a member.</span>');
            memberPointShowNotification('Please select Shopee or Lazada before linking a member.', 'error');
        } else if (memberPointPlatform && !memberPointCustomerId) {
            member_point_chk = 0;
            $("#member_point_customer_search").after('<span class="error-message member-point-customer-err">Please select a valid linked member.</span>');
            memberPointShowNotification('Please select a valid linked member.', 'error');
        } else if (memberPointUseType !== 'none' && (!memberPointPlatform || !memberPointCustomerId)) {
            member_point_chk = 0;
            $("#member_point_use_type").after('<span class="error-message member-point-redeem-err">Please link a member before applying member points.</span>');
            memberPointShowNotification('Please link a member before applying member points.', 'error');
        } else if (memberPointUseType === 'gift' && !memberPointRedeemId) {
            member_point_chk = 0;
            $("#member_point_redeem_id").after('<span class="error-message member-point-redeem-err">Please select a redeem item.</span>');
            memberPointShowNotification('Please select a redeem item.', 'error');
        } else if (memberPointUseType === 'cashback') {
            var cashbackLimit = memberPointGetCashbackCap(memberPointOriginalPrice);
            var finalPrice = memberPointParseAmount($('#for_price').val());

            if (memberPointOriginalPrice <= 0) {
                member_point_chk = 0;
                $("#member_point_cashback_points").after('<span class="error-message member-point-redeem-err">Original order amount is required before applying cashback.</span>');
                memberPointShowNotification('Original order amount is required before applying cashback.', 'error');
            } else if (memberPointCashbackPoints <= 0) {
                member_point_chk = 0;
                $("#member_point_cashback_points").after('<span class="error-message member-point-redeem-err">Cashback points must be greater than zero.</span>');
                memberPointShowNotification('Cashback points must be greater than zero.', 'error');
            } else if (memberPointCashbackPoints > memberPointAvailablePoints) {
                member_point_chk = 0;
                $("#member_point_cashback_points").after('<span class="error-message member-point-redeem-err">The linked member does not have enough available points.</span>');
                memberPointShowNotification('The linked member does not have enough available points.', 'error');
            } else if (memberPointCashbackPoints > cashbackLimit) {
                member_point_chk = 0;
                $("#member_point_cashback_points").after('<span class="error-message member-point-redeem-err">Cashback cannot exceed 30% of the order amount.</span>');
                memberPointShowNotification('Cashback cannot exceed 30% of the order amount.', 'error');
            } else if (Math.abs((memberPointOriginalPrice - memberPointCashbackPoints) - finalPrice) > 0.01) {
                member_point_chk = 0;
                $("#member_point_cashback_points").after('<span class="error-message member-point-redeem-err">Cashback deduction does not match the final order price.</span>');
                memberPointShowNotification('Cashback deduction does not match the final order price.', 'error');
            }
        }
    }

    if (name_chk == 1 && link_chk == 1 && ctc_chk == 1 && pic_chk == 1 && country_chk == 1 && brand_chk == 1 && series_chk == 1 && pkg_chk == 1 && fbpage_chk == 1 && channel_chk == 1 && price_chk == 1 && pay_chk == 1 && rec_name_chk == 1 && rec_add_chk == 1 && rec_ctc_chk == 1 && attach_chk == 1 && member_point_chk == 1) {
        var form = document.getElementById('FORForm');

        if (!form) {
            if (typeof showNotification === 'function') {
                showNotification('Form is not found. Please refresh the page and try again.', 'error');
            } else {
                console.error('Form is not found. Please refresh the page and try again.');
            }
            return false;
        }

        if (this.name) {
            $(form).find('input.js-submit-action-value[name="' + this.name + '"]').remove();

            $('<input>')
                .attr('type', 'hidden')
                .attr('name', this.name)
                .attr('class', 'js-submit-action-value')
                .val(this.value)
                .appendTo(form);
        }

        HTMLFormElement.prototype.submit.call(form);
        return true;
    }

    return false;
});
