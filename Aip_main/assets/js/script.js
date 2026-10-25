(function () {
    'use strict';

    /* ------------------------------------------------------------
     * Helpers
     * ---------------------------------------------------------- */
    function $(selector, root) { return (root || document).querySelector(selector); }
    function $$(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }

    function peso(amount) {
        return '₱' + Number(amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function showToast(message) {
        var toast = document.createElement('div');
        toast.className = 'toast';
        toast.setAttribute('role', 'status');
        toast.textContent = message;
        document.body.appendChild(toast);
        window.setTimeout(function () { toast.classList.add('visible'); }, 20);
        window.setTimeout(function () {
            toast.classList.remove('visible');
            window.setTimeout(function () { toast.remove(); }, 250);
        }, 2800);
    }
    window.aipToast = showToast;

    /* ------------------------------------------------------------
     * Sidebar (mobile)
     * ---------------------------------------------------------- */
    var sidebar = $('[data-sidebar]');
    function setSidebar(open) {
        if (!sidebar) return;
        sidebar.classList.toggle('open', open);
        document.body.classList.toggle('menu-open', open);
    }
    $$('[data-sidebar-toggle]').forEach(function (button) { button.addEventListener('click', function () { setSidebar(true); }); });
    $$('[data-sidebar-close]').forEach(function (button) { button.addEventListener('click', function () { setSidebar(false); }); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') setSidebar(false); });

    /* ------------------------------------------------------------
     * Login page
     * ---------------------------------------------------------- */
    var passwordToggle = $('[data-password-toggle]');
    if (passwordToggle) {
        passwordToggle.addEventListener('click', function () {
            var field = document.getElementById('password');
            var visible = field.type === 'text';
            field.type = visible ? 'password' : 'text';
            passwordToggle.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
            passwordToggle.textContent = visible ? '◉' : '◌';
        });
    }
    var forgot = $('[data-forgot]');
    if (forgot) {
        forgot.addEventListener('click', function (event) {
            event.preventDefault();
            showToast('Please contact the MPDO system administrator to reset your password.');
        });
    }

    // DEV-ONLY: click a test account to fill the login form
    $$('[data-fill-login]').forEach(function (button) {
        button.addEventListener('click', function () {
            var identity = document.getElementById('identity');
            var password = document.getElementById('password');
            if (identity) identity.value = button.getAttribute('data-fill-login');
            if (password) { password.value = ''; password.focus(); }
        });
    });

    // Ask before submitting forms that change an account
    $$('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) event.preventDefault();
        });
    });

    /* ------------------------------------------------------------
     * Toast buttons
     * ---------------------------------------------------------- */
    $$('[data-toast]').forEach(function (button) {
        button.addEventListener('click', function () { showToast(button.getAttribute('data-toast')); });
    });

    /* ------------------------------------------------------------
     * Show / hide targets (filter bars, alternate views)
     * ---------------------------------------------------------- */
    $$('[data-toggle-target]').forEach(function (button) {
        var target = $(button.getAttribute('data-toggle-target'));
        if (!target) return;
        button.setAttribute('aria-expanded', String(!target.hidden));
        button.addEventListener('click', function () {
            target.hidden = !target.hidden;
            button.setAttribute('aria-expanded', String(!target.hidden));
            button.classList.toggle('active', !target.hidden);
            if (!target.hidden) {
                var first = $('select, input', target);
                if (first) first.focus();
            }
        });
    });

    // Groups of buttons that switch between views: data-view-group="name" data-view-show="#id"
    $$('[data-view-show]').forEach(function (button) {
        button.addEventListener('click', function () {
            var group = button.getAttribute('data-view-group');
            $$('[data-view-group="' + group + '"]').forEach(function (other) {
                var view = $(other.getAttribute('data-view-show'));
                var isCurrent = other === button;
                other.classList.toggle('active', isCurrent);
                other.setAttribute('aria-pressed', String(isCurrent));
                if (view) view.hidden = !isCurrent;
            });
        });
    });

    /* ------------------------------------------------------------
     * Data tables
     *
     * <section id="x" data-table-panel data-page-size="5"> with rows
     * <tr data-row data-state=".." data-office=".." data-cost="..">.
     * Controls anywhere on the page point at the panel with data-for="x":
     *   data-tab="pending"           tab buttons (filter on data-state)
     *   data-filter="office"         select, '' means all
     *   data-filter-multi="status"   checkboxes, checked values allowed
     *   data-search                  text search on the row's text
     *   data-density="dense"         density buttons
     *   data-count / data-sum="cost" / data-distinct="office"   live summaries
     *   data-export-csv              CSV of all filtered rows
     * ---------------------------------------------------------- */
    var tables = {};

    function TablePanel(panel) {
        this.panel = panel;
        this.id = panel.id;
        this.rows = $$('tbody tr[data-row]', panel);
        this.emptyRow = $('tbody tr[data-empty]', panel);
        this.pager = $('[data-pager]', panel);
        this.pageSize = parseInt(panel.getAttribute('data-page-size') || '0', 10);
        this.page = 1;
        this.tab = null;
        this.filters = {};
        this.multi = {};
        this.query = '';
        this.matched = this.rows.slice();

        var activeTab = $('[data-tab].active[data-for="' + this.id + '"]');
        if (activeTab) this.tab = activeTab.getAttribute('data-tab');

        var initialSearch = $('[data-search][data-for="' + this.id + '"]');
        if (initialSearch && initialSearch.value) this.query = initialSearch.value.trim().toLowerCase();

        var self = this;
        $$('[data-filter][data-for="' + this.id + '"]').forEach(function (select) {
            if (select.value) self.filters[select.getAttribute('data-filter')] = select.value;
        });
        this.readMulti();
    }

    TablePanel.prototype.readMulti = function () {
        var self = this;
        self.multi = {};
        $$('[data-filter-multi][data-for="' + this.id + '"]').forEach(function (box) {
            var key = box.getAttribute('data-filter-multi');
            self.multi[key] = self.multi[key] || [];
            if (box.checked) self.multi[key].push(box.value);
        });
    };

    TablePanel.prototype.apply = function () {
        var self = this;
        self.matched = self.rows.filter(function (row) {
            if (self.tab && row.getAttribute('data-state') !== self.tab) return false;
            for (var key in self.filters) {
                if (self.filters[key] && row.getAttribute('data-' + key) !== self.filters[key]) return false;
            }
            for (var multiKey in self.multi) {
                if (self.multi[multiKey].indexOf(row.getAttribute('data-' + multiKey)) === -1) return false;
            }
            if (self.query && row.textContent.toLowerCase().indexOf(self.query) === -1) return false;
            return true;
        });

        var pages = self.pageSize ? Math.max(1, Math.ceil(self.matched.length / self.pageSize)) : 1;
        if (self.page > pages) self.page = pages;
        var start = self.pageSize ? (self.page - 1) * self.pageSize : 0;
        var end = self.pageSize ? start + self.pageSize : self.matched.length;

        self.rows.forEach(function (row) { row.hidden = true; });
        self.matched.slice(start, end).forEach(function (row) { row.hidden = false; });
        if (self.emptyRow) self.emptyRow.hidden = self.matched.length > 0;

        self.renderPager(start, Math.min(end, self.matched.length), pages);
        self.renderSummaries();
    };

    TablePanel.prototype.renderPager = function (start, end, pages) {
        var self = this;
        if (!self.pager) return;
        var noun = self.pager.getAttribute('data-noun') || 'entries';
        var total = self.matched.length;
        var info = total === 0 ? 'No ' + noun + ' to show' : 'Showing ' + (start + 1) + ' to ' + end + ' of ' + total + ' ' + noun;
        var html = '<span>' + info + '</span><div class="pager-buttons">';
        html += '<button type="button" data-page="prev" aria-label="Previous page"' + (self.page <= 1 ? ' disabled' : '') + '>‹</button>';
        for (var i = 1; i <= pages; i++) {
            html += '<button type="button" data-page="' + i + '"' + (i === self.page ? ' class="current-page" aria-current="page"' : '') + '>' + i + '</button>';
        }
        html += '<button type="button" data-page="next" aria-label="Next page"' + (self.page >= pages ? ' disabled' : '') + '>›</button></div>';
        self.pager.innerHTML = html;
        $$('[data-page]', self.pager).forEach(function (button) {
            button.addEventListener('click', function () {
                var value = button.getAttribute('data-page');
                if (value === 'prev') self.page = Math.max(1, self.page - 1);
                else if (value === 'next') self.page = Math.min(pages, self.page + 1);
                else self.page = parseInt(value, 10);
                self.apply();
            });
        });
    };

    TablePanel.prototype.renderSummaries = function () {
        var self = this;
        $$('[data-count][data-for="' + self.id + '"]').forEach(function (el) { el.textContent = self.matched.length; });
        $$('[data-sum][data-for="' + self.id + '"]').forEach(function (el) {
            var key = el.getAttribute('data-sum');
            var total = self.matched.reduce(function (sum, row) { return sum + parseFloat(row.getAttribute('data-' + key) || '0'); }, 0);
            el.textContent = peso(total);
        });
        $$('[data-distinct][data-for="' + self.id + '"]').forEach(function (el) {
            var key = el.getAttribute('data-distinct');
            var seen = {};
            self.matched.forEach(function (row) { seen[row.getAttribute('data-' + key)] = true; });
            el.textContent = Object.keys(seen).length;
        });
        $$('[data-ceiling-meter][data-for="' + self.id + '"]').forEach(function (el) {
            var ceilings = JSON.parse(el.getAttribute('data-ceiling-meter') || '{}');
            var fund = self.filters.fund;
            var ceiling = fund ? (ceilings[fund] || 0) : Object.keys(ceilings).reduce(function (s, k) { return s + ceilings[k]; }, 0);
            var total = self.matched.reduce(function (sum, row) { return sum + parseFloat(row.getAttribute('data-cost') || '0'); }, 0);
            var pct = ceiling ? Math.round((total / ceiling) * 1000) / 10 : 0;
            var label = $('[data-meter-label]', el);
            var bar = $('[data-meter-bar]', el);
            if (label) label.textContent = pct + '%';
            if (bar) bar.style.width = Math.min(100, pct) + '%';
        });
        $$('[data-tab-heading][data-for="' + self.id + '"]').forEach(function (el) {
            var text = el.getAttribute('data-label-' + (self.tab || 'all'));
            if (text) el.textContent = text;
        });
    };

    TablePanel.prototype.toCsv = function () {
        var headers = $$('thead th', this.panel);
        var keep = headers.map(function (th) { return !th.hasAttribute('data-no-export'); });
        function clean(text) { return text.replace(/\s+/g, ' ').trim(); }
        function cell(value) { return '"' + String(value).replace(/"/g, '""') + '"'; }
        var lines = [headers.filter(function (_, i) { return keep[i]; }).map(function (th) { return cell(clean(th.textContent)); }).join(',')];
        this.matched.forEach(function (row) {
            var cells = $$('td', row).filter(function (_, i) { return keep[i]; });
            lines.push(cells.map(function (td) {
                return cell(td.hasAttribute('data-export') ? td.getAttribute('data-export') : clean(td.textContent));
            }).join(','));
        });
        return '﻿' + lines.join('\r\n');
    };

    $$('[data-table-panel]').forEach(function (panel) {
        if (!panel.id) return;
        tables[panel.id] = new TablePanel(panel);
    });

    function tableFor(el) { return tables[el.getAttribute('data-for')]; }

    $$('[data-tab][data-for]').forEach(function (button) {
        button.addEventListener('click', function () {
            var table = tableFor(button);
            if (!table) return;
            $$('[data-tab][data-for="' + table.id + '"]').forEach(function (other) {
                other.classList.toggle('active', other === button);
                other.setAttribute('aria-pressed', String(other === button));
            });
            table.tab = button.getAttribute('data-tab');
            table.page = 1;
            table.apply();
        });
    });

    $$('[data-filter][data-for]').forEach(function (select) {
        select.addEventListener('change', function () {
            var table = tableFor(select);
            if (!table) return;
            table.filters[select.getAttribute('data-filter')] = select.value;
            table.page = 1;
            table.apply();
        });
    });

    $$('[data-filter-multi][data-for]').forEach(function (box) {
        box.addEventListener('change', function () {
            var table = tableFor(box);
            if (!table) return;
            table.readMulti();
            table.page = 1;
            table.apply();
        });
    });

    $$('[data-search][data-for]').forEach(function (input) {
        input.addEventListener('input', function () {
            var table = tableFor(input);
            if (!table) return;
            table.query = input.value.trim().toLowerCase();
            table.page = 1;
            table.apply();
        });
    });

    $$('[data-filter-reset][data-for]').forEach(function (button) {
        button.addEventListener('click', function () {
            var table = tableFor(button);
            if (!table) return;
            $$('[data-filter][data-for="' + table.id + '"]').forEach(function (select) { select.value = ''; });
            $$('[data-search][data-for="' + table.id + '"]').forEach(function (input) { input.value = ''; });
            $$('[data-filter-multi][data-for="' + table.id + '"]').forEach(function (box) { box.checked = box.defaultChecked; });
            table.filters = {};
            table.query = '';
            table.readMulti();
            table.page = 1;
            table.apply();
        });
    });

    $$('[data-density][data-for]').forEach(function (button) {
        button.addEventListener('click', function () {
            var table = tableFor(button);
            if (!table) return;
            $$('[data-density][data-for="' + table.id + '"]').forEach(function (other) { other.classList.toggle('active', other === button); });
            table.panel.classList.toggle('dense', button.getAttribute('data-density') === 'dense');
        });
    });

    $$('[data-export-csv][data-for]').forEach(function (button) {
        button.addEventListener('click', function () {
            var table = tableFor(button);
            if (!table) return;
            if (!table.matched.length) { showToast('There are no rows to export.'); return; }
            var blob = new Blob([table.toCsv()], { type: 'text/csv;charset=utf-8' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = button.getAttribute('data-export-csv') || 'export.csv';
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
            showToast('Exported ' + table.matched.length + ' rows to ' + link.download + '.');
        });
    });

    // Traceability graph: show the chain for the selected PPA
    $$('[data-trace-select]').forEach(function (select) {
        select.addEventListener('change', function () {
            $$('[data-trace]').forEach(function (graph) {
                graph.hidden = graph.getAttribute('data-trace') !== select.value;
            });
        });
    });

    $$('[data-print]').forEach(function (button) {
        button.addEventListener('click', function () { window.print(); });
    });

    // Select-all checkbox: data-select-all data-for="x"
    $$('[data-select-all][data-for]').forEach(function (box) {
        box.addEventListener('change', function () {
            var table = tableFor(box);
            if (!table) return;
            table.rows.forEach(function (row) {
                if (row.hidden) return;
                var rowBox = $('input[type=checkbox]', row);
                if (rowBox) rowBox.checked = box.checked;
            });
        });
    });

    Object.keys(tables).forEach(function (id) { tables[id].apply(); });

    // When printing, show every filtered row instead of only the current page.
    window.addEventListener('beforeprint', function () {
        Object.keys(tables).forEach(function (id) {
            tables[id].matched.forEach(function (row) { row.hidden = false; });
        });
    });
    window.addEventListener('afterprint', function () {
        Object.keys(tables).forEach(function (id) { tables[id].apply(); });
    });

    /* ------------------------------------------------------------
     * Detail dialog: <button data-detail='{"title":"","eyebrow":"","fields":[["Label","Value"]],"note":""}'>
     * ---------------------------------------------------------- */
    var dialog = $('[data-detail-dialog]');
    function openDialog(el) {
        if (el.showModal) el.showModal(); else el.setAttribute('open', '');
    }
    function closeDialog(el) {
        if (el.close) el.close(); else el.removeAttribute('open');
    }
    if (dialog) {
        $$('[data-detail]').forEach(function (button) {
            button.addEventListener('click', function () {
                var data;
                try { data = JSON.parse(button.getAttribute('data-detail')); } catch (err) { return; }
                $('[data-detail-title]', dialog).textContent = data.title || '';
                $('[data-detail-eyebrow]', dialog).textContent = data.eyebrow || '';
                var body = $('[data-detail-body]', dialog);
                body.innerHTML = '';
                (data.fields || []).forEach(function (pair) {
                    var dt = document.createElement('dt');
                    var dd = document.createElement('dd');
                    dt.textContent = pair[0];
                    dd.textContent = pair[1];
                    body.appendChild(dt);
                    body.appendChild(dd);
                });
                var note = $('[data-detail-note]', dialog);
                note.textContent = data.note || '';
                note.hidden = !data.note;
                openDialog(dialog);
            });
        });
    }
    $$('dialog').forEach(function (el) {
        $$('[data-dialog-close]', el).forEach(function (button) {
            button.addEventListener('click', function () { closeDialog(el); });
        });
        el.addEventListener('click', function (event) { if (event.target === el) closeDialog(el); });
    });
    $$('[data-dialog-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = $(button.getAttribute('data-dialog-open'));
            if (target) openDialog(target);
        });
    });
    var autoOpen = $('dialog[data-open-on-load]');
    if (autoOpen) openDialog(autoOpen);

    /* ------------------------------------------------------------
     * Form validation (mirrors the PHP rules)
     *
     * <form data-validate novalidate>, fields with:
     *   required, maxlength, data-rule="money" | "email",
     *   data-after="other_field_name" (date must not be earlier)
     * errors go into <small class="field-error" data-error-for="name">
     * ---------------------------------------------------------- */
    function fieldLabel(field) {
        var label = field.closest('label');
        if (!label) return 'This field';
        var text = '';
        label.childNodes.forEach(function (node) { if (node.nodeType === 3) text += node.textContent; });
        return text.trim() || 'This field';
    }

    function validateField(field, form, isDraft) {
        var value = (field.value || '').trim();
        var message = '';
        var rule = field.getAttribute('data-rule');
        var draftRequired = (form.getAttribute('data-draft-required') || '').split(/\s+/);
        var required = field.required && (!isDraft || draftRequired.indexOf(field.name) !== -1);

        if (field.disabled) {
            return true;
        }
        if (field.type === 'radio') {
            var group = $$('input[type=radio][name="' + field.name + '"]', form);
            var picked = group.some(function (radio) { return radio.checked; });
            var radioHolder = $('[data-error-for="' + field.name + '"]', form);
            var legend = field.closest('fieldset') ? $('legend', field.closest('fieldset')) : null;
            var groupName = legend ? legend.textContent.replace('*', '').trim() : 'an option';
            var radioMessage = required && !picked ? 'Choose ' + (legend ? 'a ' + groupName.toLowerCase() : groupName) + '.' : '';
            if (radioHolder) radioHolder.textContent = radioMessage;
            return radioMessage === '';
        }

        if (required && value === '') {
            message = fieldLabel(field) + ' is required.';
        } else if (value !== '' && rule === 'money-optional') {
            if (!/^\d{1,3}(,\d{3})*(\.\d{1,2})?$|^\d+(\.\d{1,2})?$/.test(value)) {
                message = 'Enter a valid amount, e.g. 1,250,000.00';
            }
        } else if (value !== '' && rule === 'money') {
            var number = parseFloat(value.replace(/,/g, ''));
            if (!/^\d{1,3}(,\d{3})*(\.\d{1,2})?$|^\d+(\.\d{1,2})?$/.test(value) || isNaN(number)) {
                message = 'Enter a valid amount, e.g. 1,250,000.00';
            } else if (number <= 0) {
                message = 'Amount must be greater than zero.';
            } else if (number > 999999999999.99) {
                message = 'Amount is too large.';
            }
        } else if (value !== '' && rule === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            message = 'Enter a valid email address.';
        } else if (value !== '' && rule === 'username' && !/^[a-z0-9._-]{3,30}$/.test(value.toLowerCase())) {
            message = 'Use 3–30 lowercase letters, numbers, dots, dashes or underscores.';
        } else if (value !== '' && rule === 'password' && (value.length < 8 || !/[A-Za-z]/.test(value) || !/\d/.test(value))) {
            message = 'Use at least 8 characters with at least one letter and one number.';
        } else if (field.getAttribute('data-same-as') && value !== '' && form.elements[field.getAttribute('data-same-as')] && value !== form.elements[field.getAttribute('data-same-as')].value) {
            message = 'The passwords do not match.';
        } else if (field.maxLength > 0 && value.length > field.maxLength) {
            message = 'Use ' + field.maxLength + ' characters or fewer.';
        } else if (value !== '' && field.type === 'date' && ((field.min && value < field.min) || (field.max && value > field.max))) {
            message = 'Date must fall within FY ' + field.min.slice(0, 4) + '.';
        }

        var afterName = field.getAttribute('data-after');
        if (!message && afterName && value !== '') {
            var other = form.elements[afterName];
            if (other && other.value && value < other.value) {
                message = fieldLabel(field) + ' cannot be earlier than ' + fieldLabel(other).toLowerCase() + '.';
            }
        }

        var holder = $('[data-error-for="' + field.name + '"]', form);
        if (holder) holder.textContent = message;
        field.classList.toggle('invalid', message !== '');
        field.setAttribute('aria-invalid', String(message !== ''));
        return message === '';
    }

    $$('form[data-validate]').forEach(function (form) {
        var fields = $$('input[name], select[name], textarea[name]', form).filter(function (f) { return f.type !== 'hidden'; });
        fields.forEach(function (field) {
            field.addEventListener('blur', function () { validateField(field, form); });
            field.addEventListener('change', function () { if (field.classList.contains('invalid')) validateField(field, form); });
        });
        form.addEventListener('submit', function (event) {
            var ok = true;
            var firstBad = null;
            var isDraft = !!(event.submitter && event.submitter.hasAttribute('data-draft'));
            fields.forEach(function (field) {
                if (!validateField(field, form, isDraft)) { ok = false; if (!firstBad) firstBad = field; }
            });
            if (!ok) {
                event.preventDefault();
                firstBad.focus();
                showToast('Please correct the highlighted fields.');
                return;
            }
            var confirmText = event.submitter && event.submitter.getAttribute('data-confirm-submit');
            if (confirmText && !window.confirm(confirmText)) event.preventDefault();
        });
    });

    // Live character counter: <small data-counter-for="description">
    $$('[data-counter-for]').forEach(function (counter) {
        var field = document.getElementsByName(counter.getAttribute('data-counter-for'))[0];
        if (!field) return;
        function update() { counter.textContent = field.value.length + ' / ' + field.maxLength + ' characters'; }
        field.addEventListener('input', update);
        update();
    });

    // Money fields: format with thousands separators on blur
    $$('input[data-rule="money"], input[data-rule="money-optional"]').forEach(function (input) {
        input.addEventListener('blur', function () {
            var raw = input.value.replace(/,/g, '').trim();
            if (/^\d+(\.\d{1,2})?$/.test(raw)) {
                input.value = Number(raw).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        });
    });

    // Live total of PS + MOOE + CO on the encoding form
    $$('form').forEach(function (form) {
        var parts = $$('[data-total-part]', form);
        var display = $('[data-total-display]', form);
        if (!parts.length || !display) return;
        function update() {
            var total = parts.reduce(function (sum, input) {
                var n = parseFloat((input.value || '').replace(/,/g, ''));
                return sum + (isNaN(n) ? 0 : n);
            }, 0);
            display.textContent = peso(total);
        }
        parts.forEach(function (input) { input.addEventListener('input', update); input.addEventListener('blur', update); });
        update();
    });

    // Reset button that also clears validation messages
    $$('[data-reset-form]').forEach(function (button) {
        button.addEventListener('click', function () {
            var form = button.closest('form');
            if (!form) return;
            $$('input, select, textarea', form).forEach(function (field) {
                if (field.type === 'hidden' || field.type === 'submit' || field.type === 'button') return;
                if (field.type === 'checkbox' || field.type === 'radio') field.checked = false;
                else if (field.tagName === 'SELECT') field.selectedIndex = 0;
                else field.value = '';
            });
            $$('.invalid', form).forEach(function (f) { f.classList.remove('invalid'); f.removeAttribute('aria-invalid'); });
            $$('[data-error-for]', form).forEach(function (holder) { holder.textContent = ''; });
            $$('[data-counter-for]', form).forEach(function (counter) {
                var field = document.getElementsByName(counter.getAttribute('data-counter-for'))[0];
                if (field) counter.textContent = field.value.length + ' / ' + field.maxLength + ' characters';
            });
        });
    });
})();
