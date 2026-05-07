(function () {
    var config = window.projectSettingsConfig || {};
    var canEdit = !!config.canEdit;
    var canManageTaxonomy = !!config.canManageTaxonomy;
    var ajaxUrl = typeof config.ajaxUrl === 'string' ? config.ajaxUrl : '';
    var csrfToken = typeof config.csrfToken === 'string' ? config.csrfToken : '';
    var iconOptions = Array.isArray(config.iconOptions) ? config.iconOptions : [];
    var form = document.getElementById('taskProjectSettingsForm');

    if (!form || !canEdit) {
        return;
    }

    function escHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function bindResetColorButton(btn) {
        btn.addEventListener('click', function () {
            var confirmText = String(btn.getAttribute('data-confirm-text') || 'Reset to default color?');
            if (!window.confirm(confirmText)) {
                return;
            }
            var input = null;
            var selector = btn.getAttribute('data-color-target') || '';
            if (selector) {
                input = document.querySelector(selector);
            } else if (btn.getAttribute('data-color-input') === 'closest') {
                var row = btn.closest('.task-project-color-control');
                input = row ? row.querySelector('input[type="color"]') : null;
            }
            if (!input) {
                return;
            }
            var defaultColor = String(btn.getAttribute('data-default-color') || input.getAttribute('data-default-color') || '#Dfe1e6').trim();
            input.value = defaultColor;
            saveSettings();
        });
    }

    function renderIconPickerMenu(menu, selectedValue) {
        if (!menu) {
            return;
        }

        var html = '';
        iconOptions.forEach(function (option) {
            html += '<button type="button" class="dropdown-item task-project-icon-option' + (String(option.value) === String(selectedValue) ? ' active' : '') + '" data-icon-value="' + String(option.value) + '" data-icon-src="' + String(option.src) + '">' +
                '<img src="' + String(option.src) + '" alt="">' +
                '</button>';
        });
        menu.innerHTML = html;
    }

    function syncWorkTypePreview(row, iconValue, iconSrc) {
        var previewImg = row.querySelector('.task-project-worktype-icon-preview img');
        var hiddenInput = row.querySelector('input[name="work_type_icons[]"]');
        var pickerBtn = row.querySelector('.task-project-icon-picker-btn');
        var pickerBtnImg = pickerBtn ? pickerBtn.querySelector('img') : null;

        if (hiddenInput) {
            hiddenInput.value = iconValue;
        }
        if (previewImg) {
            previewImg.src = iconSrc;
        }
        if (pickerBtnImg) {
            pickerBtnImg.src = iconSrc;
        }

        var menu = row.querySelector('.task-project-icon-picker-menu');
        renderIconPickerMenu(menu, iconValue);
    }

    function closeAllIconPickerMenus(exceptMenu) {
        form.querySelectorAll('.task-project-worktype-row.task-project-icon-picker-open').forEach(function (openRow) {
            openRow.classList.remove('task-project-icon-picker-open');
        });

        form.querySelectorAll('.task-project-icon-picker-menu.show').forEach(function (openMenu) {
            if (exceptMenu && openMenu === exceptMenu) {
                var activeRow = openMenu.closest('.task-project-worktype-row');
                if (activeRow) {
                    activeRow.classList.add('task-project-icon-picker-open');
                }
                return;
            }
            openMenu.classList.remove('show');
            var openBtn = openMenu.parentElement ? openMenu.parentElement.querySelector('.task-project-icon-picker-btn') : null;
            if (openBtn) {
                openBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function bindIconPicker(row) {
        if (!row || row.getAttribute('data-icon-picker-bound') === '1') {
            return;
        }
        row.setAttribute('data-icon-picker-bound', '1');

        var hiddenInput = row.querySelector('input[name="work_type_icons[]"]');
        var menu = row.querySelector('.task-project-icon-picker-menu');
        var pickerBtn = row.querySelector('.task-project-icon-picker-btn');
        var initialValue = hiddenInput ? hiddenInput.value : '';
        renderIconPickerMenu(menu, initialValue);
        if (!menu || !pickerBtn) {
            return;
        }

        // This picker uses custom show/hide handling, so disable Bootstrap's
        // delegated dropdown toggle to avoid double-open state on repeat clicks.
        pickerBtn.removeAttribute('data-bs-toggle');

        pickerBtn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            var isOpen = menu.classList.contains('show');
            closeAllIconPickerMenus(isOpen ? null : menu);

            if (isOpen) {
                menu.classList.remove('show');
                row.classList.remove('task-project-icon-picker-open');
                pickerBtn.setAttribute('aria-expanded', 'false');
            } else {
                menu.classList.add('show');
                row.classList.add('task-project-icon-picker-open');
                pickerBtn.setAttribute('aria-expanded', 'true');
            }
        });

        menu.addEventListener('click', function (event) {
            var optionBtn = event.target.closest('.task-project-icon-option');
            if (!optionBtn) {
                return;
            }
            syncWorkTypePreview(
                row,
                optionBtn.getAttribute('data-icon-value') || '',
                optionBtn.getAttribute('data-icon-src') || ''
            );
            menu.classList.remove('show');
            row.classList.remove('task-project-icon-picker-open');
            pickerBtn.setAttribute('aria-expanded', 'false');
            saveSettings();
        });
    }

    function appendDeleteInput(bucketId, inputName, id) {
        if (!id) {
            return;
        }
        var bucket = document.getElementById(bucketId);
        if (!bucket) {
            return;
        }
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = inputName;
        input.value = String(id);
        bucket.appendChild(input);
    }

    function removeRow(btn, bucketId, inputName) {
        var existingId = Number(btn.getAttribute('data-existing-id') || 0);
        if (existingId > 0) {
            appendDeleteInput(bucketId, inputName, existingId);
        }
        var row = btn.closest('.task-project-settings-row');
        if (row) {
            row.remove();
        }
        saveSettings();
    }

    function bindRemoveButtons(root) {
        root.querySelectorAll('.task-project-row-remove-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var deleteType = String(btn.getAttribute('data-delete-type') || '');
                var confirmMap = {
                    status: 'Delete this status? Work items under it will also be deleted.',
                    work_type: 'Delete this task type?',
                    label: 'Delete this label?',
                    status_label: 'Delete this task status label?'
                };
                if (!window.confirm(confirmMap[deleteType] || 'Delete this row?')) {
                    return;
                }
                if (deleteType === 'status') {
                    removeRow(btn, 'projectStatusDeleteBucket', 'status_delete_ids[]');
                } else if (deleteType === 'work_type') {
                    removeRow(btn, 'projectWorkTypeDeleteBucket', 'work_type_delete_ids[]');
                } else if (deleteType === 'label') {
                    removeRow(btn, 'projectLabelDeleteBucket', 'label_delete_ids[]');
                } else if (deleteType === 'status_label') {
                    removeRow(btn, 'projectStatusLabelDeleteBucket', 'status_label_delete_ids[]');
                }
            });
        });
    }

    function bindDynamicControls(root) {
        root.querySelectorAll('.task-project-reset-color-btn').forEach(bindResetColorButton);
        root.querySelectorAll('.task-project-worktype-row').forEach(bindIconPicker);
        bindRemoveButtons(root);
    }

    function buildStatusRow(status, canWrite) {
        var color = String((status && status.color) || '#DFE1E6');
        return '<div class="task-project-settings-row task-project-status-row">' +
            '<input type="hidden" name="status_ids[]" value="' + Number((status && status.id) || 0) + '">' +
            '<input type="text" class="form-control" name="status_names[]" maxlength="150" value="' + escHtml((status && status.name) || '') + '"' + (canWrite ? '' : ' disabled') + '>' +
            '<div class="task-project-color-control">' +
            '<input type="color" class="form-control form-control-color" name="status_colors[]" value="' + escHtml(color) + '" data-default-color="' + escHtml(color) + '"' + (canWrite ? '' : ' disabled') + '>' +
            (canWrite ? '<button type="button" class="btn btn-outline-secondary task-project-reset-color-btn" data-confirm-text="Reset this status color to default?" data-color-input="closest" data-default-color="' + escHtml(color) + '">Reset Default</button>' : '') +
            '</div>' +
            (canWrite ? '<button type="button" class="btn btn-outline-danger task-project-row-remove-btn" data-delete-type="status" data-existing-id="' + Number((status && status.id) || 0) + '">Remove</button>' : '') +
            '</div>';
    }

    function buildWorkTypeRow(workType, canWrite) {
        var iconValue = String((workType && workType.svg_icon) || 'svg_icon/10318.svg');
        var iconSrc = iconValue.replace(/^\/+/, '');
        return '<div class="task-project-settings-row task-project-worktype-row">' +
            '<input type="hidden" name="work_type_ids[]" value="' + Number((workType && workType.id) || 0) + '">' +
            '<div class="task-project-worktype-name-wrap">' +
            '<span class="task-project-worktype-icon-preview"><img src="' + escHtml(iconSrc) + '" alt=""></span>' +
            '<input type="text" class="form-control" name="work_type_names[]" maxlength="80" value="' + escHtml((workType && workType.name) || '') + '"' + (canWrite ? '' : ' disabled') + '>' +
            '</div>' +
            '<div class="dropdown task-project-icon-picker">' +
            '<input type="hidden" name="work_type_icons[]" value="' + escHtml(iconValue) + '">' +
            '<button type="button" class="btn btn-light task-project-icon-picker-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"' + (canWrite ? '' : ' disabled') + '>' +
            '<img src="' + escHtml(iconSrc) + '" alt="">' +
            '</button>' +
            '<div class="dropdown-menu task-project-icon-picker-menu"></div>' +
            '</div>' +
            (canWrite ? '<button type="button" class="btn btn-outline-danger task-project-row-remove-btn" data-delete-type="work_type" data-existing-id="' + Number((workType && workType.id) || 0) + '">Remove</button>' : '') +
            '</div>';
    }

    function buildLabelRow(label, canWrite, type) {
        var color = String((label && label.color) || '#DCE8FF');
        var inputPrefix = type === 'status_label' ? 'status_label' : 'label';
        var buttonText = type === 'status_label' ? 'Reset this task status color to default?' : 'Reset this label color to default?';
        return '<div class="task-project-settings-row task-project-' + (type === 'status_label' ? 'status-label' : 'label') + '-row">' +
            '<input type="hidden" name="' + inputPrefix + '_ids[]" value="' + Number((label && label.id) || 0) + '">' +
            '<input type="text" class="form-control" name="' + inputPrefix + '_names[]" maxlength="120" value="' + escHtml((label && label.name) || '') + '"' + (canWrite ? '' : ' disabled') + '>' +
            '<div class="task-project-color-control">' +
            '<input type="color" class="form-control form-control-color" name="' + inputPrefix + '_colors[]" value="' + escHtml(color) + '" data-default-color="' + escHtml(color) + '"' + (canWrite ? '' : ' disabled') + '>' +
            (canWrite ? '<button type="button" class="btn btn-outline-secondary task-project-reset-color-btn" data-confirm-text="' + escHtml(buttonText) + '" data-color-input="closest" data-default-color="' + escHtml(color) + '">Reset Default</button>' : '') +
            '</div>' +
            (canWrite ? '<button type="button" class="btn btn-outline-danger task-project-row-remove-btn" data-delete-type="' + type + '" data-existing-id="' + Number((label && label.id) || 0) + '">Remove</button>' : '') +
            '</div>';
    }

    function renderSettingsData(data) {
        if (!data || typeof data !== 'object') {
            return;
        }

        if (data.project && typeof data.project === 'object') {
            if (typeof data.project.name === 'string') {
                document.getElementById('project_name').value = data.project.name;
            }
            if (typeof data.project.board_background_color === 'string') {
                document.getElementById('board_background_color').value = data.project.board_background_color;
            }
        }
        if (data.projectKey && typeof data.projectKey === 'object' && typeof data.projectKey.project_key === 'string') {
            document.getElementById('project_key').value = data.projectKey.project_key;
        }

        document.getElementById('projectStatusRows').innerHTML = (Array.isArray(data.statuses) ? data.statuses : []).map(function (row) {
            return buildStatusRow(row, canEdit);
        }).join('');
        document.getElementById('projectStatusDeleteBucket').innerHTML = '';

        document.getElementById('projectWorkTypeRows').innerHTML = (Array.isArray(data.workTypes) ? data.workTypes : []).map(function (row) {
            return buildWorkTypeRow(row, canEdit);
        }).join('');
        document.getElementById('projectWorkTypeDeleteBucket').innerHTML = '';

        document.getElementById('projectLabelRows').innerHTML = (Array.isArray(data.labels) ? data.labels : []).map(function (row) {
            return buildLabelRow(row, canManageTaxonomy, 'label');
        }).join('');
        document.getElementById('projectLabelDeleteBucket').innerHTML = '';

        document.getElementById('projectStatusLabelRows').innerHTML = (Array.isArray(data.statusLabels) ? data.statusLabels : []).map(function (row) {
            return buildLabelRow(row, canManageTaxonomy, 'status_label');
        }).join('');
        document.getElementById('projectStatusLabelDeleteBucket').innerHTML = '';

        bindDynamicControls(form);
    }

    function saveSettings() {
        var formData = new FormData(form);
        formData.append('task_action', 'save_project_settings_ajax');
        formData.set('csrf_token', csrfToken);

        window.jQuery.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.ok) {
                window.alert(res && res.message ? res.message : 'Failed to update project settings.');
                return;
            }
            renderSettingsData(res);
        }).fail(function () {
            window.alert('Failed to update project settings.');
        });
    }

    var addStatusBtn = document.getElementById('addProjectStatusRowBtn');
    if (addStatusBtn) {
        addStatusBtn.addEventListener('click', function () {
            var container = document.getElementById('projectStatusRows');
            if (!container) {
                return;
            }
            var row = document.createElement('div');
            row.className = 'task-project-settings-row task-project-status-row';
            row.innerHTML = '<input type="hidden" name="status_ids[]" value="0">' +
                '<input type="text" class="form-control" name="status_names[]" maxlength="150" value="">' +
                '<div class="task-project-color-control">' +
                '<input type="color" class="form-control form-control-color" name="status_colors[]" value="#dfe1e6" data-default-color="#dfe1e6">' +
                '<button type="button" class="btn btn-outline-secondary task-project-reset-color-btn" data-confirm-text="Reset this status color to default?" data-color-input="closest" data-default-color="#dfe1e6">Reset Default</button>' +
                '</div>' +
                '<button type="button" class="btn btn-outline-danger task-project-row-remove-btn" data-delete-type="status" data-existing-id="0">Remove</button>';
            container.appendChild(row);
            bindDynamicControls(row);
            var input = row.querySelector('input[name="status_names[]"]');
            if (input) {
                input.focus();
            }
        });
    }

    var addWorkTypeBtn = document.getElementById('addProjectWorkTypeRowBtn');
    if (addWorkTypeBtn) {
        addWorkTypeBtn.addEventListener('click', function () {
            var container = document.getElementById('projectWorkTypeRows');
            if (!container) {
                return;
            }
            var row = document.createElement('div');
            row.className = 'task-project-settings-row task-project-worktype-row';
            var defaultIcon = iconOptions.length ? iconOptions[0] : { value: 'svg_icon/10318.svg', src: 'svg_icon/10318.svg', label: '' };
            row.innerHTML = '<input type="hidden" name="work_type_ids[]" value="0">' +
                '<div class="task-project-worktype-name-wrap">' +
                '<span class="task-project-worktype-icon-preview"><img src="' + String(defaultIcon.src) + '" alt=""></span>' +
                '<input type="text" class="form-control" name="work_type_names[]" maxlength="80" value="">' +
                '</div>' +
                '<div class="dropdown task-project-icon-picker">' +
                '<input type="hidden" name="work_type_icons[]" value="' + String(defaultIcon.value) + '">' +
                '<button type="button" class="btn btn-light task-project-icon-picker-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">' +
                '<img src="' + String(defaultIcon.src) + '" alt="">' +
                '</button>' +
                '<div class="dropdown-menu task-project-icon-picker-menu"></div>' +
                '</div>' +
                '<button type="button" class="btn btn-outline-danger task-project-row-remove-btn" data-delete-type="work_type" data-existing-id="0">Remove</button>';
            container.appendChild(row);
            bindDynamicControls(row);
            var input = row.querySelector('input[name="work_type_names[]"]');
            if (input) {
                input.focus();
            }
        });
    }

    var addLabelBtn = document.getElementById('addProjectLabelRowBtn');
    if (addLabelBtn) {
        addLabelBtn.addEventListener('click', function () {
            var container = document.getElementById('projectLabelRows');
            if (!container) {
                return;
            }
            var row = document.createElement('div');
            row.innerHTML = buildLabelRow({ id: 0, name: '', color: '#DCE8FF' }, true, 'label');
            container.appendChild(row.firstChild);
            bindDynamicControls(container);
            var input = container.lastElementChild ? container.lastElementChild.querySelector('input[name="label_names[]"]') : null;
            if (input) {
                input.focus();
            }
        });
    }

    var addStatusLabelBtn = document.getElementById('addProjectStatusLabelRowBtn');
    if (addStatusLabelBtn) {
        addStatusLabelBtn.addEventListener('click', function () {
            var container = document.getElementById('projectStatusLabelRows');
            if (!container) {
                return;
            }
            var row = document.createElement('div');
            row.innerHTML = buildLabelRow({ id: 0, name: '', color: '#DCE8FF' }, true, 'status_label');
            container.appendChild(row.firstChild);
            bindDynamicControls(container);
            var input = container.lastElementChild ? container.lastElementChild.querySelector('input[name="status_label_names[]"]') : null;
            if (input) {
                input.focus();
            }
        });
    }

    form.addEventListener('change', function (event) {
        if (!event.target || !event.target.name) {
            return;
        }
        saveSettings();
    });

    document.addEventListener('click', function (event) {
        if (!event.target || !event.target.closest('.task-project-icon-picker')) {
            closeAllIconPickerMenus(null);
        }
    });

    bindDynamicControls(form);
})();
