(function () {
    'use strict';

    var sidebar = document.querySelector('[data-sidebar]');
    var toggle = document.querySelector('[data-sidebar-toggle]');
    var closeButtons = document.querySelectorAll('[data-sidebar-close]');

    function setSidebar(open) {
        if (!sidebar) return;
        sidebar.classList.toggle('open', open);
        document.body.classList.toggle('menu-open', open);
    }

    if (toggle) toggle.addEventListener('click', function () { setSidebar(true); });
    closeButtons.forEach(function (button) { button.addEventListener('click', function () { setSidebar(false); }); });

    var passwordToggle = document.querySelector('[data-password-toggle]');
    if (passwordToggle) {
        passwordToggle.addEventListener('click', function () {
            var field = document.getElementById('password');
            var visible = field.type === 'text';
            field.type = visible ? 'password' : 'text';
            passwordToggle.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
            passwordToggle.textContent = visible ? '◉' : '◌';
        });
    }

    var tableSearch = document.querySelector('[data-table-search]');
    if (tableSearch) {
        tableSearch.addEventListener('input', function () {
            var query = tableSearch.value.toLowerCase();
            document.querySelectorAll('.searchable-table tbody tr').forEach(function (row) {
                row.hidden = query && !row.textContent.toLowerCase().includes(query);
            });
        });
    }

    document.querySelectorAll('[data-global-search]').forEach(function (input) {
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && input.value.trim()) {
                var prefix = document.body.getAttribute('data-root-prefix') || '';
                window.location.href = prefix + 'pages/projects.php';
            }
        });
    });

    document.querySelectorAll('[data-reset-form]').forEach(function (button) {
        button.addEventListener('click', function () {
            var form = button.closest('form');
            if (form) form.reset();
        });
    });

    var linkStandard = document.querySelector('[data-link-standard]');
    var linkGraph = document.querySelector('[data-link-graph]');
    document.querySelectorAll('[data-link-view]').forEach(function (button) {
        button.addEventListener('click', function () {
            var graph = button.getAttribute('data-link-view') === 'graph';
            document.querySelectorAll('[data-link-view]').forEach(function (item) { item.classList.toggle('active', item === button); });
            if (linkStandard) linkStandard.hidden = graph;
            if (linkGraph) linkGraph.hidden = !graph;
        });
    });

    document.querySelectorAll('[data-toast]').forEach(function (button) {
        button.addEventListener('click', function () {
            var toast = document.createElement('div');
            toast.className = 'toast';
            toast.textContent = button.getAttribute('data-toast');
            document.body.appendChild(toast);
            window.setTimeout(function () { toast.classList.add('visible'); }, 20);
            window.setTimeout(function () { toast.classList.remove('visible'); window.setTimeout(function () { toast.remove(); }, 250); }, 2800);
        });
    });

    var forgot = document.querySelector('[data-forgot]');
    if (forgot) {
        forgot.addEventListener('click', function (event) {
            event.preventDefault();
            window.alert('Please contact IT support to reset your password.');
        });
    }
})();
