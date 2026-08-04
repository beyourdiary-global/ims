(function () {
    var config = window.projectDashboardConfig || {};

    document.addEventListener('DOMContentLoaded', function () {
        var createBtn = document.getElementById('projectDashboardCreateBtn');
        var createInput = document.getElementById('projectDashboardCreateInput');

        if (createBtn && createInput) {
            createBtn.addEventListener('click', function () {
                var projectName = (createInput.value || '').trim();
                if (!projectName) {
                    createInput.focus();
                    return;
                }
                createBtn.disabled = true;

                var payload = new URLSearchParams();
                payload.append('task_action', 'create_project');
                payload.append('project_name', projectName);
                payload.append('csrf_token', config.csrfToken || '');

                fetch(config.ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: payload.toString()
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data && data.ok) {
                            window.location.reload();
                            return;
                        }
                        createBtn.disabled = false;
                        if (typeof showNotification === 'function') {
                            showNotification((data && data.message) || 'Failed to create project task.', 'error');
                        }
                    })
                    .catch(function () {
                        createBtn.disabled = false;
                        if (typeof showNotification === 'function') {
                            showNotification('Failed to create project task.', 'error');
                        }
                    });
            });

            createInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    createBtn.click();
                }
            });
        }

        document.querySelectorAll('.project-dashboard-delete-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var projectId = btn.getAttribute('data-project-id');
                var projectName = btn.getAttribute('data-project-name') || 'this project task';

                if (!window.confirm('Delete "' + projectName + '"? No one will be able to access it afterwards.')) {
                    return;
                }
                btn.disabled = true;

                var payload = new URLSearchParams();
                payload.append('task_action', 'delete_project_task');
                payload.append('project_id', projectId);
                payload.append('csrf_token', config.csrfToken || '');

                fetch(config.ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: payload.toString()
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data && data.ok) {
                            var card = btn.closest('.project-dashboard-card');
                            if (card) {
                                card.remove();
                            }
                            if (typeof showNotification === 'function') {
                                showNotification('Project task deleted.', 'success');
                            }
                            return;
                        }
                        btn.disabled = false;
                        if (typeof showNotification === 'function') {
                            showNotification((data && data.message) || 'Failed to delete project task.', 'error');
                        }
                    })
                    .catch(function () {
                        btn.disabled = false;
                        if (typeof showNotification === 'function') {
                            showNotification('Failed to delete project task.', 'error');
                        }
                    });
            });
        });
    });
})();
