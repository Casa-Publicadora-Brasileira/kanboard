(function () {
    'use strict';

    function getSearchForm() {
        return document.querySelector('.project-header form.search');
    }

    function getSearchInput() {
        var form = getSearchForm();
        return form ? form.querySelector('input[name="search"]') : null;
    }

    function submitFilter(token) {
        var form = getSearchForm();
        var input = getSearchInput();
        if (!form || !input) return;

        var attribute = token.split(':')[0];
        var pattern = new RegExp('(?:^|\\s)' + attribute + ':(?:"[^"]*"|\\S+)', 'gi');
        var current = input.value.replace(pattern, ' ').replace(/\s+/g, ' ').trim();
        input.value = (current + ' ' + token).trim();
        form.submit();
    }

    function replaceFilterGroup(attribute, values) {
        var form = getSearchForm();
        var input = getSearchInput();
        if (!form || !input) return;
        var pattern = new RegExp('(?:^|\\s)' + attribute + ':(?:"[^"]*"|\\S+)', 'gi');
        var current = input.value.replace(pattern, ' ').replace(/\s+/g, ' ').trim();
        var tokens = values.map(function (value) { return attribute + ':"' + String(value).replace(/"/g, '') + '"'; });
        input.value = (current + ' ' + tokens.join(' ')).trim();
        form.submit();
    }

    function getFilterValues(attribute) {
        var input = getSearchInput();
        if (!input) return [];
        var pattern = new RegExp(attribute + ':(?:"([^"]*)"|(\\S+))', 'gi');
        var values = [];
        var match;
        while ((match = pattern.exec(input.value)) !== null) values.push(match[1] || match[2]);
        return values;
    }

    function normalizeSearchText(value) {
        var text = String(value || '').toLocaleLowerCase();
        try { return text.normalize('NFD').replace(/[\u0300-\u036f]/g, ''); } catch (ignore) { return text; }
    }

    function submitWithScrollMemory(form, key) {
        try { sessionStorage.setItem('pm-scroll:' + key, JSON.stringify({path: window.location.pathname, y: window.scrollY})); } catch (ignore) {}
        form.submit();
    }

    function restoreFilterScroll() {
        ['management', 'my-tasks'].some(function (key) {
            var value = null;
            try { value = sessionStorage.getItem('pm-scroll:' + key); } catch (ignore) {}
            if (!value) return false;
            try {
                var state = JSON.parse(value);
                sessionStorage.removeItem('pm-scroll:' + key);
                if (state.path === window.location.pathname && Number.isFinite(state.y)) window.scrollTo(0, state.y);
            } catch (ignore) {}
            return true;
        });
    }

    function initializeCreateRelations(section) {
        if (!section || section._caRelations) return section ? section._caRelations : null;

        var input = section.querySelector('[data-ca-relation-search]');
        var results = section.querySelector('[data-ca-relation-results]');
        var type = section.querySelector('[data-ca-relation-type]');
        var list = section.querySelector('[data-ca-relation-list]');
        var empty = section.querySelector('[data-ca-relation-empty]');
        var count = section.querySelector('[data-ca-relation-count]');
        var timer = 0;
        var request = null;
        var items = [];
        var activeIndex = -1;

        function closeResults() {
            results.hidden = true;
            results.innerHTML = '';
            items = [];
            activeIndex = -1;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        }

        function updateSummary() {
            var total = list.querySelectorAll('.ca-create-relation-row').length;
            count.textContent = String(total);
            count.hidden = total === 0;
            empty.hidden = total !== 0;
            if (total > 0) section.open = true;
        }

        function setActive(index) {
            if (!items.length) return;
            activeIndex = (index + items.length) % items.length;
            items.forEach(function (item, itemIndex) {
                item.classList.toggle('is-active', itemIndex === activeIndex);
                item.setAttribute('aria-selected', itemIndex === activeIndex ? 'true' : 'false');
            });
            input.setAttribute('aria-activedescendant', items[activeIndex].id);
            items[activeIndex].scrollIntoView({block: 'nearest'});
        }

        function addRelation(task) {
            var linkId = type.value;
            if (!linkId || !task || !task.id) return;
            if (list.querySelector('[data-link-id="' + linkId + '"][data-task-id="' + task.id + '"]')) {
                closeResults();
                input.value = '';
                return;
            }

            var row = document.createElement('div');
            row.className = 'ca-create-relation-row';
            row.setAttribute('data-link-id', linkId);
            row.setAttribute('data-task-id', task.id);

            var relationLabel = document.createElement('span');
            relationLabel.className = 'ca-create-relation-type';
            relationLabel.textContent = type.options[type.selectedIndex].textContent;

            var taskLabel = document.createElement('span');
            taskLabel.className = 'ca-create-relation-task';
            taskLabel.textContent = '#' + task.id + ' ' + task.title + (task.project_name ? ' · ' + task.project_name : '');

            var linkField = document.createElement('input');
            linkField.type = 'hidden';
            linkField.name = 'relation_link_ids[]';
            linkField.value = linkId;

            var taskField = document.createElement('input');
            taskField.type = 'hidden';
            taskField.name = 'relation_task_ids[]';
            taskField.value = task.id;

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'ca-create-relation-remove';
            remove.setAttribute('data-ca-relation-remove', 'true');
            remove.setAttribute('aria-label', section.getAttribute('data-remove-label'));
            remove.innerHTML = '<i class="fa fa-times" aria-hidden="true"></i>';

            row.appendChild(relationLabel);
            row.appendChild(taskLabel);
            row.appendChild(linkField);
            row.appendChild(taskField);
            row.appendChild(remove);
            list.appendChild(row);
            input.value = '';
            closeResults();
            updateSummary();
            input.focus();
        }

        function renderResults(tasks) {
            results.innerHTML = '';
            var selectedTaskIds = Array.prototype.map.call(list.querySelectorAll('[data-link-id="' + type.value + '"][data-task-id]'), function (row) { return row.getAttribute('data-task-id'); });
            tasks = tasks.filter(function (task) { return selectedTaskIds.indexOf(String(task.id)) === -1; });

            if (!tasks.length) {
                var noResults = document.createElement('div');
                noResults.className = 'ca-relation-result-empty';
                noResults.textContent = section.getAttribute('data-no-results');
                results.appendChild(noResults);
            } else {
                tasks.forEach(function (task, index) {
                    var option = document.createElement('button');
                    option.type = 'button';
                    option.id = 'ca-relation-result-' + task.id + '-' + index;
                    option.className = 'ca-relation-result';
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    option._caTask = task;

                    var title = document.createElement('strong');
                    title.textContent = '#' + task.id + ' ' + task.title;
                    var project = document.createElement('small');
                    project.textContent = task.project_name || '';
                    option.appendChild(title);
                    option.appendChild(project);
                    results.appendChild(option);
                });
            }

            items = Array.prototype.slice.call(results.querySelectorAll('.ca-relation-result'));
            activeIndex = -1;
            results.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            if (items.length) setActive(0);
        }

        function search() {
            var term = input.value.trim();
            window.clearTimeout(timer);
            if (term.length < 2 && !/^#?\d+$/.test(term)) {
                closeResults();
                return;
            }
            timer = window.setTimeout(function () {
                if (request) request.abort();
                request = typeof AbortController !== 'undefined' ? new AbortController() : null;
                var separator = section.getAttribute('data-search-url').indexOf('?') === -1 ? '?' : '&';
                fetch(section.getAttribute('data-search-url') + separator + 'term=' + encodeURIComponent(term), {
                    credentials: 'same-origin',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    signal: request ? request.signal : undefined
                })
                    .then(function (response) { if (!response.ok) throw new Error(); return response.json(); })
                    .then(renderResults)
                    .catch(function (error) {
                        if (error.name === 'AbortError') return;
                        renderResults([]);
                        var message = results.querySelector('.ca-relation-result-empty');
                        if (message) message.textContent = section.getAttribute('data-search-error');
                    });
            }, 220);
        }

        input.addEventListener('input', search);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown' && items.length) { event.preventDefault(); setActive(activeIndex + 1); }
            else if (event.key === 'ArrowUp' && items.length) { event.preventDefault(); setActive(activeIndex - 1); }
            else if (event.key === 'Enter' && activeIndex >= 0) { event.preventDefault(); addRelation(items[activeIndex]._caTask); }
            else if (event.key === 'Escape' && !results.hidden) { event.preventDefault(); event.stopPropagation(); closeResults(); }
        });
        results.addEventListener('click', function (event) {
            var option = event.target.closest('.ca-relation-result');
            if (option) addRelation(option._caTask);
        });
        list.addEventListener('click', function (event) {
            var remove = event.target.closest('[data-ca-relation-remove]');
            if (!remove) return;
            remove.closest('.ca-create-relation-row').remove();
            updateSummary();
        });
        updateSummary();

        section._caRelations = {
            close: closeResults,
            contains: function (target) { return section.contains(target); },
            reset: function () {
                list.querySelectorAll('.ca-create-relation-row').forEach(function (row) { row.remove(); });
                input.value = '';
                closeResults();
                updateSummary();
            }
        };
        return section._caRelations;
    }

    function initializeManagement() {
        var page = document.querySelector('.ca-management');
        if (!page) {
            var projectLabels = document.querySelector('.ca-project-list-labels');
            if (projectLabels) {
                var main = projectLabels.closest('.page') || document.querySelector('.page');
                if (main) main.classList.add('ca-project-list-page');
                document.querySelectorAll('.page > .page-header a').forEach(function (link) {
                    if (/ProjectUserOverviewController|projects\/managers/.test(link.href)) {
                        var item = link.closest('li');
                        if (item) item.remove();
                    }
                    if (/ProjectCreationController|project\/create/.test(link.href) && !/createPrivate|create\/private/.test(link.href)) {
                        link.classList.add('ca-new-project-button');
                        var label = link.querySelector('span');
                        if (label) label.textContent = projectLabels.getAttribute('data-new-project-label');
                    }
                });
                var projectSearch = document.querySelector('.page > .margin-bottom .search input[type="search"], .page > .margin-bottom .search input[type="text"]');
                if (projectSearch) {
                    projectSearch.placeholder = projectLabels.getAttribute('data-search-placeholder');
                    projectSearch.setAttribute('aria-label', projectLabels.getAttribute('data-search-placeholder'));
                    var projectRows = Array.prototype.slice.call(document.querySelectorAll('.page > .table-list .table-list-row'));
                    var projectCount = document.querySelector('.page > .table-list .table-list-header-count');
                    var projectSearchTimer = null;
                    var applyProjectSearch = function () {
                        var term = normalizeSearchText(projectSearch.value);
                        var visible = 0;
                        projectRows.forEach(function (row) {
                            var matches = normalizeSearchText(row.textContent).indexOf(term) !== -1;
                            row.hidden = !matches;
                            if (matches) visible++;
                        });
                        if (projectCount) projectCount.textContent = projectCount.textContent.replace(/^\s*\d+/, visible);
                    };
                    projectSearch.closest('form').addEventListener('submit', function (event) { event.preventDefault(); applyProjectSearch(); });
                    projectSearch.addEventListener('input', function () {
                        clearTimeout(projectSearchTimer);
                        projectSearchTimer = setTimeout(applyProjectSearch, 140);
                    });
                    projectSearch.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape' && projectSearch.value) { event.preventDefault(); projectSearch.value = ''; applyProjectSearch(); }
                    });
                }
            }
            return;
        }

        var filterForm = page.querySelector('.ca-management-filter-form');
        var openPicker = null;
        var projectMembersCache = {};

        function closePicker(picker, restoreFocus) {
            if (!picker) return;
            picker.querySelector('.ca-management-picker-panel').hidden = true;
            picker.querySelector('.ca-management-picker-trigger').setAttribute('aria-expanded', 'false');
            picker.querySelector('.ca-management-picker-trigger').value = picker.getAttribute('data-selected-label') || '';
            if (restoreFocus) picker.querySelector('.ca-management-picker-trigger').focus();
            if (openPicker === picker) openPicker = null;
        }

        page.querySelectorAll('[data-ca-management-picker]').forEach(function (picker) {
            var trigger = picker.querySelector('.ca-management-picker-trigger');
            var clear = picker.querySelector('.ca-management-picker-clear');
            var panel = picker.querySelector('.ca-management-picker-panel');
            var options = [];
            var activeIndex = 0;
            var remoteUrl = picker.getAttribute('data-remote-url');
            var request = null;
            var searchTimer = null;
            picker.setAttribute('data-selected-label', trigger.value);

            function refreshOptions() {
                options = Array.prototype.slice.call(panel.querySelectorAll('[role="option"]'));
                activeIndex = Math.max(0, options.findIndex(function (option) { return option.getAttribute('aria-selected') === 'true'; }));
            }
            function renderOptions(items) {
                var container = panel.querySelector('.ca-management-picker-options');
                var selected = filterForm.querySelector('[data-ca-management-filter="' + picker.getAttribute('data-filter-name') + '"]').value;
                container.textContent = '';
                items.forEach(function (item) {
                    var option = document.createElement('button');
                    option.type = 'button';
                    option.setAttribute('role', 'option');
                    option.setAttribute('data-value', item.id);
                    option.setAttribute('aria-selected', String(item.id) === selected ? 'true' : 'false');
                    option.textContent = item.label;
                    container.appendChild(option);
                });
                refreshOptions();
                if (options.length) setActive(options[activeIndex]);
            }
            function filterOptions(term) {
                term = normalizeSearchText(term);
                options.forEach(function (option) { option.hidden = normalizeSearchText(option.textContent).indexOf(term) === -1; });
                var visible = visibleOptions();
                if (visible.length) setActive(visible[0]);
            }
            function loadRemote(term) {
                if (!remoteUrl) return;
                var projectsField = filterForm.querySelector('[data-ca-management-filter="project_ids"]');
                var singleProjectField = filterForm.querySelector('[data-ca-management-filter="project_id"]');
                var project = projectsField && projectsField.value ? projectsField.value : (singleProjectField ? singleProjectField.value : '0');
                if (projectMembersCache[project]) {
                    renderOptions(projectMembersCache[project]);
                    filterOptions(term || '');
                    return;
                }
                if (request) request.abort();
                request = new AbortController();
                var separator = remoteUrl.indexOf('?') === -1 ? '?' : '&';
                fetch(remoteUrl + separator + (projectsField && projectsField.value ? 'project_ids=' : 'project_id=') + encodeURIComponent(project), {
                    credentials: 'same-origin',
                    signal: request.signal,
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                }).then(function (response) {
                    if (!response.ok) throw new Error('Unable to load users');
                    return response.json();
                }).then(function (items) {
                    projectMembersCache[project] = items;
                    renderOptions(items);
                    filterOptions(term || '');
                }).catch(function (error) {
                    if (error.name !== 'AbortError') renderOptions([]);
                });
            }

            function visibleOptions() { return options.filter(function (option) { return !option.hidden; }); }
            function setActive(option) {
                if (!option) return;
                options.forEach(function (item) { item.classList.toggle('is-active', item === option); });
                activeIndex = options.indexOf(option);
                option.scrollIntoView({block: 'nearest'});
            }
            function choose(option) {
                var name = picker.getAttribute('data-filter-name');
                var field = filterForm.querySelector('[data-ca-management-filter="' + name + '"]');
                field.value = option.getAttribute('data-value');
                submitWithScrollMemory(filterForm, page.matches('[data-pm-my-tasks]') ? 'my-tasks' : 'management');
            }
            function clearValue() {
                var name = picker.getAttribute('data-filter-name');
                filterForm.querySelector('[data-ca-management-filter="' + name + '"]').value = '0';
                submitWithScrollMemory(filterForm, page.matches('[data-pm-my-tasks]') ? 'my-tasks' : 'management');
            }
            function move(delta) {
                var visible = visibleOptions();
                if (!visible.length) return;
                var current = visible.indexOf(options[activeIndex]);
                setActive(visible[(current + delta + visible.length) % visible.length]);
            }

            function open() {
                if (openPicker && openPicker !== picker) closePicker(openPicker, false);
                panel.hidden = false;
                trigger.setAttribute('aria-expanded', 'true');
                openPicker = picker;
                options.forEach(function (option) { option.hidden = false; });
                if (remoteUrl) loadRemote('');
                else if (options.length) setActive(options[activeIndex]);
            }
            trigger.addEventListener('focus', function () {
                open();
                trigger.select();
            });
            trigger.addEventListener('click', open);
            trigger.addEventListener('input', function () {
                if (panel.hidden) open();
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () { filterOptions(trigger.value); }, 140);
            });
            panel.addEventListener('click', function (event) {
                var option = event.target.closest('[role="option"]');
                if (option) choose(option);
            });
            clear.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                clearValue();
            });
            picker.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); event.stopPropagation(); closePicker(picker, true); }
                else if ((event.key === 'Backspace' || event.key === 'Delete') && panel.hidden && !clear.hidden) { event.preventDefault(); clearValue(); }
                else if (event.key === 'ArrowDown' && !panel.hidden) { event.preventDefault(); move(1); }
                else if (event.key === 'ArrowUp' && !panel.hidden) { event.preventDefault(); move(-1); }
                else if (event.key === 'Enter' && !panel.hidden && options[activeIndex] && !options[activeIndex].hidden) { event.preventDefault(); choose(options[activeIndex]); }
            });
            refreshOptions();
        });

        document.addEventListener('click', function (event) {
            if (openPicker && !openPicker.contains(event.target)) closePicker(openPicker, false);
        });

        var taskSearch = page.querySelector('[data-ca-management-search]');
        if (taskSearch) {
            taskSearch.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                filterForm.querySelector('[data-ca-management-filter="search"]').value = taskSearch.value.trim();
                submitWithScrollMemory(filterForm, 'management');
            });
        }

        page.querySelectorAll('.ca-management-filter-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                var field = filterForm.querySelector('[data-ca-management-filter="' + chip.getAttribute('data-filter-group') + '"]');
                var values = field.value ? field.value.split(',').filter(Boolean) : [];
                var chipValues = (chip.getAttribute('data-filter-values') || chip.getAttribute('data-filter-value') || '').split(',').filter(Boolean);
                var active = chipValues.some(function (value) { return values.indexOf(value) !== -1; });
                chipValues.forEach(function (value) {
                    var index = values.indexOf(value);
                    if (!active && index === -1) values.push(value);
                    else if (active && index !== -1) values.splice(index, 1);
                });
                field.value = values.join(',');
                submitWithScrollMemory(filterForm, 'management');
            });
        });
        var clearQuickFilters = page.querySelector('[data-ca-clear-quick-filters]');
        if (clearQuickFilters) clearQuickFilters.addEventListener('click', function () {
            filterForm.querySelector('[data-ca-management-filter="project_ids"]').value = '';
            filterForm.querySelector('[data-ca-management-filter="column_ids"]').value = '';
            submitWithScrollMemory(filterForm, 'management');
        });
    }

    function initializeDashboardProjectCards() {
        var dashboard = document.querySelector('#dashboard');
        if (!dashboard) return;
        var projectsHeading = dashboard.querySelector('.sidebar-content > .page-header a');
        if (projectsHeading && /DashboardController|dashboard\/.*projects/.test(projectsHeading.href)) {
            dashboard.classList.add('ca-dashboard-projects');
        }
        var projectList = dashboard.querySelector('.sidebar-content > .table-list');
        if (projectList) {
            projectList.classList.add('ca-dashboard-project-list');
            var moreLabel = document.querySelector('.ca-dashboard-hero');
            projectList.querySelectorAll('.table-list-details').forEach(function (details) {
                var metrics = Array.prototype.slice.call(details.children);
                if (metrics.length <= 10 || !moreLabel) return;
                metrics.slice(10).forEach(function (metric) { metric.hidden = true; });
                var more = document.createElement('span');
                more.className = 'ca-more-statuses';
                more.textContent = '+ ' + Math.ceil((metrics.length - 10) / 2) + ' ' + moreLabel.getAttribute('data-more-statuses-label');
                details.appendChild(more);
            });
        }
    }

    function initializeMyTasksDashboard() {
        var page = document.querySelector('[data-pm-my-tasks]');
        if (!page) return;
        var form = page.querySelector('[data-pm-task-filter-form]');
        if (!form) return;
        page.querySelectorAll('[data-pm-filter-group]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                var field = form.querySelector('[data-pm-filter="' + chip.getAttribute('data-pm-filter-group') + '"]');
                var values = field.value ? field.value.split(',').filter(Boolean) : [];
                var chipValues = (chip.getAttribute('data-pm-filter-values') || '').split(',').filter(Boolean);
                var active = chipValues.some(function (value) { return values.indexOf(value) !== -1; });
                chipValues.forEach(function (value) {
                    var index = values.indexOf(value);
                    if (!active && index === -1) values.push(value);
                    if (active && index !== -1) values.splice(index, 1);
                });
                field.value = values.join(',');
                submitWithScrollMemory(form, 'my-tasks');
            });
        });
        page.querySelectorAll('[data-pm-priority]').forEach(function (button) {
            button.addEventListener('click', function () {
                var field = form.querySelector('[data-pm-filter="priority"]');
                field.value = field.value === button.getAttribute('data-pm-priority') ? '' : button.getAttribute('data-pm-priority');
                submitWithScrollMemory(form, 'my-tasks');
            });
        });
        var clear = page.querySelector('[data-pm-clear-task-filters]');
        if (clear) clear.addEventListener('click', function () {
            ['project_ids', 'column_ids', 'priority', 'search'].forEach(function (name) { form.querySelector('[data-pm-filter="' + name + '"]').value = ''; });
            submitWithScrollMemory(form, 'my-tasks');
        });
        var search = page.querySelector('[data-pm-task-search]');
        var searchTimer = null;
        if (search) search.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                if (form.querySelector('[data-pm-filter="search"]').value === search.value.trim()) return;
                form.querySelector('[data-pm-filter="search"]').value = search.value.trim();
                submitWithScrollMemory(form, 'my-tasks');
            }, 320);
        });
    }

    function initializeSettingsSurface() {
        var config = document.querySelector('#config-section');
        if (!config) return;
        initializeInternalNavigation(config);
        var panels = Array.prototype.slice.call(config.querySelectorAll('.sidebar-content > .panel'));
        panels.forEach(function (panel) {
            if (panel.querySelector('ul')) panel.classList.add('ca-config-facts');
        });
        var lastPanel = panels[panels.length - 1];
        if (lastPanel && !lastPanel.querySelector('ul') && lastPanel.textContent.trim().length > 500) {
            lastPanel.classList.add('ca-license-panel');
        }
    }

    function navigationKey(value) {
        var url;
        try { url = new URL(value, window.location.href); } catch (ignore) { return ''; }
        var controller = (url.searchParams.get('controller') || '').toLowerCase();
        var action = (url.searchParams.get('action') || '').toLowerCase();
        return controller ? controller + ':' + action : url.pathname.replace(/\/$/, '').toLowerCase();
    }

    function initializeInternalNavigation(section) {
        var sidebar = section.querySelector(':scope > .sidebar');
        if (!sidebar) return;
        sidebar.classList.add('pm-internal-nav');
        var currentKey = navigationKey(window.location.href);
        var match = null;
        sidebar.querySelectorAll('li').forEach(function (item) {
            item.classList.remove('active', 'menu-selected', 'pm-nav-active');
            var link = item.querySelector(':scope > a');
            if (link && navigationKey(link.href) === currentKey && !match) match = item;
        });
        if (match) {
            match.classList.add('pm-nav-active');
            var activeLink = match.querySelector(':scope > a');
            if (activeLink) activeLink.setAttribute('aria-current', 'page');
        }
    }

    function initializeProfileSurface() {
        var profile = document.querySelector('#user-section');
        if (!profile) return;
        var sidebar = profile.querySelector(':scope > .sidebar');
        if (sidebar) {
            sidebar.querySelectorAll('a').forEach(function (link) {
                if (navigationKey(link.href).indexOf('dashboardcontroller:show') === 0 || /\/dashboard\/\d+\/?$/i.test(link.pathname)) {
                    var item = link.closest('li');
                    if (item) item.remove();
                }
            });
        }
        initializeInternalNavigation(profile);
    }

    function initializeDashboardSubtasks() {
        var dashboard = document.querySelector('#dashboard');
        if (!dashboard) return;
        dashboard.querySelectorAll('.task-list-subtasks').forEach(function (list, index) {
            if (list.getAttribute('data-pm-compact') === 'true') return;
            var rows = list.querySelectorAll('.task-list-subtask');
            if (!rows.length) return;
            list.setAttribute('data-pm-compact', 'true');
            list.id = list.id || 'pm-subtasks-' + index;
            list.hidden = true;
            var trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'pm-subtask-summary';
            trigger.setAttribute('aria-expanded', 'false');
            trigger.setAttribute('aria-controls', list.id);
            trigger.textContent = rows.length + (document.documentElement.lang === 'pt-BR' ? ' subtarefa(s) · Ver' : ' subtask(s) · View');
            trigger.addEventListener('click', function () {
                list.hidden = !list.hidden;
                trigger.setAttribute('aria-expanded', list.hidden ? 'false' : 'true');
                trigger.textContent = rows.length + (document.documentElement.lang === 'pt-BR' ? (list.hidden ? ' subtarefa(s) · Ver' : ' subtarefa(s) · Ocultar') : (list.hidden ? ' subtask(s) · View' : ' subtask(s) · Hide'));
            });
            list.insertAdjacentElement('beforebegin', trigger);
        });
    }

    function initializeAdvancedSearch() {
        var bar = document.querySelector('[data-ca-filter-bar]');
        var nativeInput = getSearchInput();
        var nativeForm = getSearchForm();
        var toggle = document.querySelector('[data-ca-advanced-toggle]');
        if (!bar || !nativeInput || !nativeForm || !toggle) return;

        var panel = document.createElement('form');
        panel.className = 'ca-advanced-search';
        panel.hidden = true;
        panel.innerHTML = '<label></label><div><input type="text"><button type="submit" class="btn btn-blue"></button><button type="button" class="btn" data-ca-advanced-clear></button></div>';
        panel.querySelector('label').textContent = bar.getAttribute('data-ca-advanced-label');
        panel.querySelector('input').value = nativeInput.value;
        panel.querySelector('[type="submit"]').textContent = bar.getAttribute('data-ca-apply-label');
        panel.querySelector('[data-ca-advanced-clear]').textContent = bar.getAttribute('data-ca-clear-label');
        bar.insertAdjacentElement('afterend', panel);

        function syncActiveState() {
            var active = nativeInput.value.trim() !== '' && nativeInput.value.trim() !== 'status:open';
            toggle.classList.toggle('ca-filter-active', active);
            toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
        }
        toggle.setAttribute('aria-controls', 'ca-advanced-search');
        panel.id = 'ca-advanced-search';
        toggle.addEventListener('click', function () {
            panel.hidden = !panel.hidden;
            toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
            if (!panel.hidden) { panel.querySelector('input').value = nativeInput.value; panel.querySelector('input').focus(); }
        });
        panel.addEventListener('submit', function (event) {
            event.preventDefault();
            nativeInput.value = panel.querySelector('input').value.trim();
            nativeForm.submit();
        });
        panel.querySelector('[data-ca-advanced-clear]').addEventListener('click', function () {
            panel.querySelector('input').value = 'status:open';
            nativeInput.value = 'status:open';
            nativeForm.submit();
        });
        syncActiveState();
    }

    function initializeActiveFilterSummary() {
        var bar = document.querySelector('[data-ca-filter-bar]');
        var input = getSearchInput();
        if (!bar || !input) return;
        var tokens = input.value.match(/[a-z_-]+:(?:"[^"]*"|\S+)/gi) || [];
        if (tokens.length === 1 && tokens[0].toLowerCase() === 'status:open') tokens = [];
        if (!tokens.length) return;
        var summary = document.createElement('div');
        summary.className = 'ca-active-filter-summary';
        var label = document.createElement('strong');
        label.textContent = bar.getAttribute('data-ca-active-label');
        summary.appendChild(label);
        tokens.forEach(function (token) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.textContent = token.replace(/^[^:]+:/, '').replace(/^"|"$/g, '') + ' ×';
            chip.addEventListener('click', function () {
                input.value = input.value.replace(token, '').replace(/\s+/g, ' ').trim();
                getSearchForm().submit();
            });
            summary.appendChild(chip);
        });
        bar.insertAdjacentElement('afterend', summary);
    }

    function createOverlayController(config) {
        var dialog = document.querySelector(config.dialog);
        var backdrop = document.querySelector(config.backdrop);
        var body = document.querySelector(config.body);
        var previousFocus = null;
        if (!dialog || !backdrop || !body) return null;

        function close(skipHistory) {
            dialog.hidden = true;
            backdrop.hidden = true;
            document.body.classList.remove(config.openClass);
            body.innerHTML = '';
            if (previousFocus) previousFocus.focus();
            if (!skipHistory && config.history && window.history.state && window.history.state.companyAgilePanel) {
                window.history.back();
            }
        }

        function focusFirst() {
            var target = dialog.querySelector('[autofocus], button, [href], input, select, textarea');
            if (target) target.focus();
        }

        function open(url, fallbackUrl) {
            previousFocus = document.activeElement;
            dialog.hidden = false;
            backdrop.hidden = false;
            document.body.classList.add(config.openClass);
            body.innerHTML = '<div class="ca-panel-skeleton"><span></span><span></span><span></span></div>';

            return fetch(url, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function (response) {
                    if (!response.ok) throw new Error('request_failed');
                    return response.text();
                })
                .then(function (html) {
                    body.innerHTML = html;
                    if (config.history) window.history.pushState({companyAgilePanel: true}, '', fallbackUrl || url);
                    focusFirst();
                })
                .catch(function () { window.location.href = fallbackUrl || url; });
        }

        backdrop.addEventListener('click', close);
        dialog.addEventListener('click', function (event) {
            if (event.target.closest(config.closeSelector)) close();
        });
        dialog.addEventListener('keydown', function (event) {
            if (event.key !== 'Tab') return;
            var focusable = dialog.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
            if (!focusable.length) return;
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
        return {open: open, close: close, dialog: dialog, body: body};
    }

    function initializeNavigation() {
        var current = window.location.pathname + window.location.search;
        var best = null;
        document.querySelectorAll('.ca-sidebar a.ca-nav-item').forEach(function (link) {
            var href = link.getAttribute('href') || '';
            var path = href.replace(/^https?:\/\/[^/]+/, '');
            if (path && current.indexOf(path) === 0 && (!best || path.length > best.path.length)) best = {link: link, path: path};
        });
        if (best) {
            best.link.classList.add('ca-nav-active');
            best.link.setAttribute('aria-current', 'page');
        }
        document.querySelectorAll('.ca-sidebar a.ca-nav-item').forEach(function (link) {
            var label = link.textContent.replace(/\s+/g, ' ').trim();
            if (label) {
                link.setAttribute('title', label);
                if (!link.getAttribute('aria-label')) link.setAttribute('aria-label', label);
            }
        });
    }

    function initializeProjectSettingsNavigation() {
        var group = document.querySelector('[data-ca-project-settings]');
        if (!group) return;
        var summary = group.querySelector(':scope > summary');
        var menu = group.querySelector('.ca-project-settings-menu');
        if (!summary || !menu) return;
        var currentKey = navigationKey(window.location.href);
        var match = null;
        menu.querySelectorAll('li').forEach(function (item) {
            item.classList.remove('active', 'menu-selected', 'pm-nav-active');
            var link = item.querySelector(':scope > a');
            if (link) link.removeAttribute('aria-current');
            if (link && navigationKey(link.href) === currentKey && !match) match = item;
        });
        if (match) {
            match.classList.add('pm-nav-active');
            match.querySelector(':scope > a').setAttribute('aria-current', 'page');
            group.open = true;
            summary.classList.add('ca-nav-active');
        }
        summary.setAttribute('aria-expanded', group.open ? 'true' : 'false');
        group.addEventListener('toggle', function () {
            summary.setAttribute('aria-expanded', group.open ? 'true' : 'false');
        });
    }

    function initializeProjectPicker() {
        var host = document.querySelector('body > header .board-selector-container .js-select-dropdown-autocomplete, body > header .board-selector-container .js-select-dropdown-autocomplete-rendered');
        if (!host || host.getAttribute('data-ca-project-ready') === 'true') return;
        var params;
        try { params = JSON.parse(host.getAttribute('data-params') || '{}'); } catch (ignore) { return; }
        if (!params.items || !Object.keys(params.items).length || !params.redirect) return;
        host.setAttribute('data-ca-project-ready', 'true');
        var sidebar = document.querySelector('.ca-sidebar');
        var currentProjectName = sidebar ? sidebar.getAttribute('data-ca-current-project') : '';
        var currentName = currentProjectName || (document.documentElement.lang === 'pt-BR' ? 'Trocar projeto' : 'Switch project');
        var picker = document.createElement('div');
        picker.className = 'ca-project-picker';
        picker.innerHTML = '<button type="button" class="ca-project-picker-trigger" aria-haspopup="listbox" aria-expanded="false"><span></span><i class="fa fa-angle-down" aria-hidden="true"></i></button><div class="ca-project-picker-panel" hidden><label><i class="fa fa-search" aria-hidden="true"></i><input type="search" autocomplete="off"></label><ul role="listbox"></ul><p hidden></p></div>';
        host.insertAdjacentElement('beforebegin', picker);
        var trigger = picker.querySelector('.ca-project-picker-trigger');
        var panel = picker.querySelector('.ca-project-picker-panel');
        var input = panel.querySelector('input');
        var list = panel.querySelector('ul');
        var empty = panel.querySelector('p');
        trigger.querySelector('span').textContent = currentName;
        input.placeholder = document.documentElement.lang === 'pt-BR' ? 'Pesquisar projeto...' : 'Search project...';
        input.setAttribute('aria-label', input.placeholder);
        empty.textContent = document.documentElement.lang === 'pt-BR' ? 'Nenhum projeto encontrado.' : 'No projects found.';
        Object.keys(params.items).sort(function (left, right) {
            return String(params.items[left]).localeCompare(String(params.items[right]), document.documentElement.lang || undefined, {sensitivity: 'base'});
        }).forEach(function (value) {
            var item = document.createElement('li');
            var button = document.createElement('button');
            button.type = 'button';
            button.setAttribute('role', 'option');
            button.setAttribute('data-ca-project-value', value);
            button.setAttribute('aria-selected', params.items[value] === currentName ? 'true' : 'false');
            button.textContent = params.items[value];
            item.appendChild(button);
            list.appendChild(item);
        });
        function close() { panel.hidden = true; trigger.setAttribute('aria-expanded', 'false'); }
        function visibleOptions() { return Array.prototype.filter.call(list.querySelectorAll('button'), function (button) { return !button.closest('li').hidden; }); }
        trigger.addEventListener('click', function () {
            panel.hidden = !panel.hidden;
            trigger.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
            if (!panel.hidden) { input.value = ''; input.dispatchEvent(new Event('input')); input.focus(); }
        });
        input.addEventListener('input', function () {
            var term = normalizeSearchText(input.value.trim());
            var count = 0;
            list.querySelectorAll('button').forEach(function (button) {
                var matches = normalizeSearchText(button.textContent).indexOf(term) !== -1;
                button.closest('li').hidden = !matches;
                if (matches) count++;
            });
            empty.hidden = count !== 0;
        });
        list.addEventListener('click', function (event) {
            var option = event.target.closest('[data-ca-project-value]');
            if (!option) return;
            window.location.href = params.redirect.url.replace(new RegExp(params.redirect.regex, 'g'), option.getAttribute('data-ca-project-value'));
        });
        picker.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { close(); trigger.focus(); return; }
            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
            var options = visibleOptions();
            if (!options.length) return;
            event.preventDefault();
            var index = options.indexOf(document.activeElement);
            index = event.key === 'ArrowDown' ? Math.min(index + 1, options.length - 1) : Math.max(index - 1, 0);
            options[index < 0 ? 0 : index].focus();
        });
        document.addEventListener('click', function (event) {
            if (!picker.contains(event.target)) close();
        });
        suppressNativeProjectPicker();
    }

    function suppressNativeProjectPicker() {
        document.querySelectorAll('body > header .board-selector-container .js-select-dropdown-autocomplete, body > header .board-selector-container .js-select-dropdown-autocomplete-rendered').forEach(function (host) {
            host.hidden = true;
            host.setAttribute('aria-hidden', 'true');
            host.setAttribute('inert', '');
            host.querySelectorAll('input, button, a, [tabindex]').forEach(function (element) { element.setAttribute('tabindex', '-1'); });
        });
    }

    function initializeCompanyPopovers() {
        var popovers = document.querySelectorAll('[data-ca-popover]');

        function closeAll(except) {
            popovers.forEach(function (popover) {
                if (popover === except) return;
                var panel = popover.querySelector('[data-ca-popover-panel]');
                var trigger = popover.querySelector('[data-ca-popover-trigger]');
                if (panel) panel.hidden = true;
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
            });
        }

        popovers.forEach(function (popover) {
            var trigger = popover.querySelector('[data-ca-popover-trigger]');
            var panel = popover.querySelector('[data-ca-popover-panel]');
            var search = popover.querySelector('[data-ca-option-search]');
            if (!trigger || !panel) return;
            var emptyState = panel.querySelector('[data-ca-empty-state]');
            if (emptyState) emptyState.classList.toggle('ca-is-visible', panel.querySelectorAll('[data-ca-option]').length === 0);
            trigger.setAttribute('aria-haspopup', 'listbox');
            trigger.addEventListener('click', function () {
                var willOpen = panel.hidden;
                closeAll(popover);
                panel.hidden = !willOpen;
                trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                if (willOpen) {
                    var focusTarget = search || panel.querySelector('[data-ca-option]');
                    if (focusTarget) focusTarget.focus();
                }
            });
            popover.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    panel.hidden = true;
                    trigger.setAttribute('aria-expanded', 'false');
                    trigger.focus();
                }
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    var options = Array.prototype.filter.call(panel.querySelectorAll('[data-ca-option]'), function (item) { return !item.hidden; });
                    if (!options.length) return;
                    event.preventDefault();
                    var index = options.indexOf(document.activeElement);
                    index = event.key === 'ArrowDown' ? Math.min(index + 1, options.length - 1) : Math.max(index - 1, 0);
                    options[index < 0 ? 0 : index].focus();
                }
            });
            panel.querySelectorAll('[data-ca-option]').forEach(function (option) {
                option.addEventListener('click', function () {
                    var multi = panel.hasAttribute('data-ca-multi-popover');
                    if (!multi) panel.querySelectorAll('[data-ca-option]').forEach(function (item) { item.setAttribute('aria-selected', 'false'); });
                    option.setAttribute('aria-selected', multi && option.getAttribute('aria-selected') === 'true' ? 'false' : 'true');
                    var checkIcon = option.querySelector('.fa');
                    if (checkIcon && multi) checkIcon.className = option.getAttribute('aria-selected') === 'true' ? 'fa fa-check-square-o' : 'fa fa-square-o';
                    var label = trigger.querySelector('[data-ca-trigger-label]');
                    if (label && !multi) label.textContent = option.textContent.trim() + ' ✓';
                    trigger.classList.add('ca-filter-active');
                    if (!multi) {
                        panel.hidden = true;
                        trigger.setAttribute('aria-expanded', 'false');
                        trigger.focus();
                    }
                });
            });
            var applyMulti = panel.querySelector('[data-ca-apply-multi]');
            if (applyMulti) applyMulti.addEventListener('click', function () {
                var values = Array.prototype.map.call(panel.querySelectorAll('[data-ca-multi-value][aria-selected="true"]'), function (option) { return option.getAttribute('data-ca-multi-value'); });
                replaceFilterGroup(panel.getAttribute('data-ca-multi-popover'), values);
            });
            if (search) search.addEventListener('input', function () {
                var term = normalizeSearchText(search.value.trim());
                var visible = 0;
                panel.querySelectorAll('[data-ca-option]').forEach(function (option) {
                    var matches = normalizeSearchText(option.textContent).indexOf(term) !== -1;
                    option.hidden = !matches;
                    if (matches) visible++;
                });
                var noResults = panel.querySelector('[data-ca-no-results]');
                if (noResults) {
                    noResults.hidden = visible !== 0 || term === '';
                    noResults.textContent = (document.querySelector('[data-ca-filter-bar]').getAttribute('data-ca-no-results-prefix') || 'No results for') + ' “' + search.value.trim() + '”.';
                }
            });
            var currentSearch = getSearchInput();
            if (currentSearch) panel.querySelectorAll('[data-ca-filter]').forEach(function (option) {
                var token = option.getAttribute('data-ca-filter');
                if (token && currentSearch.value.indexOf(token) !== -1) {
                    option.setAttribute('aria-selected', 'true');
                    var label = trigger.querySelector('[data-ca-trigger-label]');
                    if (label) label.textContent = option.textContent.trim() + ' ✓';
                    trigger.classList.add('ca-filter-active');
                }
            });
            if (currentSearch && panel.hasAttribute('data-ca-multi-popover')) {
                var multiAttribute = panel.getAttribute('data-ca-multi-popover');
                var multiPattern = new RegExp(multiAttribute + ':(?:"([^"]*)"|(\\S+))', 'gi');
                var selectedValues = [];
                var match;
                while ((match = multiPattern.exec(currentSearch.value)) !== null) selectedValues.push(match[1] || match[2]);
                panel.querySelectorAll('[data-ca-multi-value]').forEach(function (option) {
                    var selected = selectedValues.indexOf(option.getAttribute('data-ca-multi-value')) !== -1;
                    option.setAttribute('aria-selected', selected ? 'true' : 'false');
                    option.querySelector('.fa').className = selected ? 'fa fa-check-square-o' : 'fa fa-square-o';
                });
                if (selectedValues.length) {
                    var multiLabel = trigger.querySelector('[data-ca-trigger-label]');
                    if (multiLabel) multiLabel.textContent = selectedValues.length + ' ' + multiLabel.textContent + ' ✓';
                    trigger.classList.add('ca-filter-active');
                }
            }
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('[data-ca-popover]')) closeAll();
        });
    }

    function initializeAvatarFallbacks() {
        document.querySelectorAll('.avatar img').forEach(function (image) {
            function fallback() {
                var dropdown = image.closest('.dropdown');
                var fullname = dropdown ? dropdown.querySelector('li.no-hover strong') : null;
                var label = image.getAttribute('alt') || image.getAttribute('title') || (fullname ? fullname.textContent : 'U');
                var initials = label.trim().split(/\s+/).slice(0, 2).map(function (part) { return part.charAt(0).toUpperCase(); }).join('') || 'U';
                var replacement = document.createElement('div');
                replacement.className = 'avatar-letter ca-avatar-fallback';
                replacement.textContent = initials;
                replacement.setAttribute('aria-label', label);
                image.replaceWith(replacement);
            }
            image.addEventListener('error', fallback, {once: true});
            if (image.complete && image.naturalWidth === 0) fallback();
        });
    }

    function initializeTaskInteractions() {
        document.querySelectorAll('.task-board .tooltip, .ca-backlog-task .tooltip, .task-list .tooltip, .table-list-row .tooltip, .activity-event .tooltip, .task-links-table .tooltip').forEach(function (element) {
            element.classList.remove('tooltip');
            element.removeAttribute('data-href');
        });
        document.querySelectorAll('.task-board-title a, .ca-backlog-title, .task-list a[href*="task_id"], .table-list-row a[href*="task_id"], .activity-event a[href*="task_id"], .task-links-table a[href*="task_id"], .ca-relation-item a').forEach(function (link) {
            link.classList.add('ca-task-link');
            if (!link.getAttribute('title')) link.setAttribute('title', link.textContent.trim());
        });
    }

    function initializeContentSurfaces() {
        var advancedForm = document.querySelector('#modal-content .ca-advanced-create-form');
        if (advancedForm) {
            var advancedModal = advancedForm.closest('#modal-box');
            if (advancedModal) advancedModal.classList.add('ca-advanced-create-modal');
        }
        document.querySelectorAll('.comment').forEach(function (comment) {
            if (comment.getAttribute('data-ca-comment-ready') === 'true') return;
            comment.setAttribute('data-ca-comment-ready', 'true');
            var menu = comment.querySelector('.comment-actions .dropdown-menu');
            if (menu) {
                menu.setAttribute('aria-label', document.documentElement.lang === 'pt-BR' ? 'Ações do comentário' : 'Comment actions');
                menu.innerHTML = '<span aria-hidden="true">•••</span>';
            }
        });
        document.querySelectorAll('.ca-advanced-create-form .assign-me, #modal-content form[action*="TaskModificationController"] .assign-me').forEach(function (link) {
            link.textContent = document.documentElement.lang === 'pt-BR' ? 'Atribuir-me' : 'Assign to me';
            link.classList.add('ca-assign-me');
            var select = document.getElementById(link.getAttribute('data-target-id'));
            if (select && String(select.value) === String(link.getAttribute('data-current-id'))) link.hidden = true;
        });
        document.querySelectorAll('.activity-event').forEach(function (event) {
            event.classList.add('ca-activity-timeline-item');
        });
        groupActivityEvents(document);
        compactTaskRelations();
        initializeTagPickers(document);
        initializeMoreActionPopovers(document);
    }

    function initializeTagPickers(root) {
        root.querySelectorAll('.ca-create-tags select[multiple]:not([data-ca-tags-ready])').forEach(function (select) {
            select.setAttribute('data-ca-tags-ready', 'true');
            select.classList.add('ca-native-tag-select');
            select.setAttribute('aria-hidden', 'true');
            select.setAttribute('tabindex', '-1');

            var picker = document.createElement('div');
            picker.className = 'ca-tag-picker';
            picker.innerHTML = '<div class="ca-tag-picker-control"><div class="ca-tag-picker-values"></div><button type="button" class="ca-tag-picker-add" aria-haspopup="listbox" aria-expanded="false"><i class="fa fa-plus" aria-hidden="true"></i><span></span></button></div><div class="ca-tag-picker-panel" hidden><label><i class="fa fa-search" aria-hidden="true"></i><input type="search" autocomplete="off"></label><div class="ca-tag-picker-options" role="listbox" aria-multiselectable="true"></div><p class="ca-tag-picker-empty" hidden></p></div>';
            select.insertAdjacentElement('afterend', picker);
            var values = picker.querySelector('.ca-tag-picker-values');
            var add = picker.querySelector('.ca-tag-picker-add');
            var panel = picker.querySelector('.ca-tag-picker-panel');
            var search = picker.querySelector('input');
            var options = picker.querySelector('.ca-tag-picker-options');
            var empty = picker.querySelector('.ca-tag-picker-empty');
            var isPortuguese = document.documentElement.lang === 'pt-BR';
            add.querySelector('span').textContent = isPortuguese ? 'Adicionar' : 'Add';
            add.setAttribute('aria-label', isPortuguese ? 'Adicionar etiqueta' : 'Add tag');
            search.placeholder = isPortuguese ? 'Pesquisar etiqueta...' : 'Search tag...';
            search.setAttribute('aria-label', search.placeholder);
            empty.textContent = isPortuguese ? 'Nenhuma etiqueta encontrada.' : 'No tags found.';

            Array.prototype.slice.call(select.options).forEach(function (nativeOption) {
                if (!nativeOption.value) return;
                var option = document.createElement('button');
                option.type = 'button';
                option.className = 'ca-tag-picker-option';
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', nativeOption.selected ? 'true' : 'false');
                option.textContent = nativeOption.textContent;
                option.addEventListener('click', function () {
                    nativeOption.selected = !nativeOption.selected;
                    select.dispatchEvent(new Event('change', {bubbles: true}));
                    render();
                });
                options.appendChild(option);
            });

            function render() {
                values.innerHTML = '';
                Array.prototype.slice.call(select.selectedOptions).forEach(function (nativeOption) {
                    if (!nativeOption.value) return;
                    var chip = document.createElement('span');
                    chip.className = 'ca-tag-chip';
                    chip.appendChild(document.createTextNode(nativeOption.textContent));
                    var remove = document.createElement('button');
                    remove.type = 'button';
                    remove.innerHTML = '&times;';
                    remove.setAttribute('aria-label', (isPortuguese ? 'Remover etiqueta ' : 'Remove tag ') + nativeOption.textContent);
                    remove.addEventListener('click', function () {
                        nativeOption.selected = false;
                        select.dispatchEvent(new Event('change', {bubbles: true}));
                        render();
                    });
                    chip.appendChild(remove);
                    values.appendChild(chip);
                });
                Array.prototype.slice.call(options.children).forEach(function (option, index) {
                    var nativeOptions = Array.prototype.filter.call(select.options, function (item) { return item.value; });
                    option.setAttribute('aria-selected', nativeOptions[index] && nativeOptions[index].selected ? 'true' : 'false');
                });
            }
            function close() { panel.hidden = true; add.setAttribute('aria-expanded', 'false'); }
            add.addEventListener('click', function () {
                panel.hidden = !panel.hidden;
                add.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
                if (!panel.hidden) { search.value = ''; search.dispatchEvent(new Event('input')); search.focus(); }
            });
            search.addEventListener('input', function () {
                var term = normalizeSearchText(search.value);
                var visible = 0;
                Array.prototype.slice.call(options.children).forEach(function (option) {
                    var match = normalizeSearchText(option.textContent).indexOf(term) !== -1;
                    option.hidden = !match;
                    if (match) visible++;
                });
                empty.hidden = visible !== 0;
            });
            picker.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { close(); add.focus(); }
            });
            picker.addEventListener('focusout', function () {
                window.setTimeout(function () { if (!picker.contains(document.activeElement)) close(); }, 0);
            });
            render();
        });
    }

    function clearQuickCreateErrors(form) {
        form.querySelectorAll('.ca-field-error').forEach(function (message) { message.remove(); });
        form.querySelectorAll('.ca-is-invalid, [aria-invalid="true"]').forEach(function (field) {
            field.classList.remove('ca-is-invalid');
            field.removeAttribute('aria-invalid');
            field.removeAttribute('aria-describedby');
        });
        var feedback = form.querySelector('.ca-quick-create-feedback');
        if (feedback) feedback.classList.remove('ca-feedback-error');
    }

    function renderQuickCreateErrors(form, result) {
        clearQuickCreateErrors(form);
        var feedback = form.querySelector('.ca-quick-create-feedback');
        if (feedback) {
            feedback.textContent = result.message || '';
            feedback.classList.add('ca-feedback-error');
        }
        var first = null;
        Object.keys(result.errors || {}).forEach(function (name, index) {
            var field = name === 'relations' ? form.querySelector('[data-ca-create-relations]') : form.elements.namedItem(name);
            if (field && typeof field.length === 'number' && !field.tagName) field = field[0];
            if (!field) return;
            var host = name === 'relations' ? field.querySelector('.ca-create-relations-content') : (field.closest('.ca-form-field') || field.parentNode);
            var message = document.createElement('p');
            message.className = 'ca-field-error';
            message.id = 'ca-field-error-' + name.replace(/[^a-z0-9_-]/gi, '-') + '-' + index;
            message.setAttribute('role', 'alert');
            message.textContent = result.errors[name];
            host.appendChild(message);
            field.classList.add('ca-is-invalid');
            field.setAttribute('aria-invalid', 'true');
            field.setAttribute('aria-describedby', message.id);
            if (!first) first = name === 'relations' ? (field.querySelector('[data-ca-relation-search]') || field) : field;
        });
        if (first) {
            if (first.tagName === 'DETAILS') first.open = true;
            first.scrollIntoView({behavior: 'smooth', block: 'center'});
            if (typeof first.focus === 'function') first.focus({preventScroll: true});
        } else if (feedback) {
            feedback.scrollIntoView({behavior: 'smooth', block: 'center'});
        }
    }

    function activityDay(value) {
        var text = (value || '').replace(/\s+/g, ' ').trim();
        var numeric = text.match(/\b\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4}\b/);
        if (numeric) return numeric[0];
        return text.replace(/(?:,?\s+at)?\s+\d{1,2}:\d{2}(?::\d{2})?.*$/i, '').trim();
    }

    function groupActivityEvents(root) {
        var containers = [];
        root.querySelectorAll('.activity-event').forEach(function (event) {
            var parent = event.parentNode;
            if (containers.indexOf(parent) === -1) containers.push(parent);
        });
        containers.forEach(function (container) {
            var days = Array.prototype.map.call(container.querySelectorAll(':scope > .activity-event .activity-date'), function (date) { return activityDay(date.textContent); });
            var signature = days.join('|');
            if (container.getAttribute('data-ca-activity-groups') === signature) return;
            container.setAttribute('data-ca-activity-groups', signature);
            container.querySelectorAll(':scope > .ca-activity-day').forEach(function (heading) { heading.remove(); });
            var lastDay = '';
            Array.prototype.slice.call(container.children).forEach(function (event) {
                if (!event.classList || !event.classList.contains('activity-event')) return;
                var date = event.querySelector('.activity-date');
                var day = activityDay(date ? date.textContent : '');
                if (day && day !== lastDay) {
                    var heading = document.createElement('h3');
                    heading.className = 'ca-activity-day';
                    heading.textContent = day;
                    container.insertBefore(heading, event);
                    lastDay = day;
                }
            });
        });
    }

    function compactTaskRelations() {
        document.querySelectorAll('#task-view .task-links-table:not([data-ca-relations-ready])').forEach(function (table) {
            table.setAttribute('data-ca-relations-ready', 'true');
            var groups = [];
            var activeGroup = null;
            table.querySelectorAll('tr').forEach(function (row) {
                var heading = row.querySelector('th');
                if (heading) {
                    activeGroup = {label: heading.textContent.replace(/\s+/g, ' ').trim(), items: []};
                    groups.push(activeGroup);
                    return;
                }
                var taskLink = row.querySelector('.task-links-table-td > div:last-child > a[href]');
                if (!taskLink || !activeGroup) return;
                var cells = row.querySelectorAll('td');
                activeGroup.items.push({
                    link: taskLink,
                    context: row.querySelector('td:first-child .task-links-table-td > div:last-child'),
                    assignee: cells[1] ? cells[1].textContent.replace(/\s+/g, ' ').trim() : ''
                });
            });
            if (!groups.length) return;
            var section = document.createElement('section');
            section.className = 'ca-relations';
            var total = groups.reduce(function (sum, group) { return sum + group.items.length; }, 0);
            section.innerHTML = '<header><h3></h3><span></span></header><div class="ca-relations-scroll"></div>';
            section.querySelector('h3').textContent = document.documentElement.lang === 'pt-BR' ? 'Relações' : 'Relations';
            section.querySelector('header span').textContent = total;
            var scroll = section.querySelector('.ca-relations-scroll');
            groups.forEach(function (group) {
                var block = document.createElement('section');
                block.className = 'ca-relation-group';
                var title = document.createElement('h4');
                title.textContent = group.label.replace(/\(\d+\)\s*$/, '').trim();
                block.appendChild(title);
                group.items.forEach(function (item) {
                    var entry = document.createElement('div');
                    entry.className = 'ca-relation-item';
                    var clonedLink = item.link.cloneNode(true);
                    clonedLink.classList.add('ca-task-link');
                    var closed = clonedLink.classList.contains('task-link-closed');
                    clonedLink.classList.remove('task-link-closed');
                    var meta = document.createElement('small');
                    meta.textContent = (closed ? '✓ ' + (document.documentElement.lang === 'pt-BR' ? 'Concluído' : 'Completed') : (document.documentElement.lang === 'pt-BR' ? 'Em aberto' : 'Open')) + ' · ' + (item.assignee || (document.documentElement.lang === 'pt-BR' ? 'Não atribuída' : 'Unassigned'));
                    entry.appendChild(clonedLink);
                    entry.appendChild(meta);
                    block.appendChild(entry);
                });
                scroll.appendChild(block);
            });
            var details = table.closest('details.accordion-section');
            if (details) {
                details.removeAttribute('open');
                details.classList.add('ca-relations-details');
                var summary = details.querySelector(':scope > summary');
                if (summary) {
                    summary.innerHTML = '';
                    var label = document.createElement('span');
                    label.textContent = document.documentElement.lang === 'pt-BR' ? 'Relações' : 'Relations';
                    summary.appendChild(label);
                    if (total > 0) {
                        var count = document.createElement('span');
                        count.className = 'ca-relations-summary-count';
                        count.textContent = total;
                        summary.appendChild(count);
                    }
                }
            }
            table.replaceWith(section);
        });
    }

    function initializeMoreActionPopovers(root) {
        document.querySelectorAll('.ca-task-actions-popover').forEach(function (popover) {
            var trigger = document.getElementById(popover.getAttribute('data-ca-trigger-id'));
            if (!trigger || !trigger.isConnected) popover.remove();
        });
        root.querySelectorAll('.ca-task-more-actions:not([data-ca-popover-ready]), .ca-panel-more:not([data-ca-popover-ready])').forEach(function (details, index) {
            var panel = details.closest('.ca-task-panel-content');
            var summary = details.querySelector(':scope > summary');
            var list = details.querySelector(':scope > ul, :scope > .ca-more-actions-list');
            var validItems = list ? Array.prototype.filter.call(list.querySelectorAll('li'), function (item) {
                var link = item.querySelector('a[href]');
                return link && link.textContent.replace(/\s+/g, ' ').trim();
            }) : [];
            details.setAttribute('data-ca-popover-ready', 'true');
            if (!summary || !list || !validItems.length) {
                details.remove();
                return;
            }
            var trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'ca-task-more-trigger';
            trigger.id = 'ca-task-more-trigger-' + Date.now() + '-' + index;
            trigger.setAttribute('aria-haspopup', 'menu');
            trigger.setAttribute('aria-expanded', 'false');
            var moreActionsLabel = document.documentElement.lang === 'pt-BR' ? 'Mais ações' : 'More actions';
            trigger.setAttribute('aria-label', moreActionsLabel);
            trigger.setAttribute('title', moreActionsLabel);
            trigger.innerHTML = '<span aria-hidden="true">•••</span>';
            var popover = document.createElement('div');
            popover.className = 'ca-task-actions-popover';
            popover.setAttribute('data-ca-trigger-id', trigger.id);
            popover.setAttribute('role', 'menu');
            popover.hidden = true;
            Array.prototype.slice.call(list.querySelectorAll('li')).forEach(function (item) {
                if (validItems.indexOf(item) === -1) item.remove();
            });
            validItems.forEach(function (item) { list.appendChild(item); });
            popover.appendChild(list);
            details.replaceWith(trigger);
            if (panel) {
                var panelTitle = panel.querySelector('.ca-task-panel-header h2');
                if (panelTitle) {
                    var panelTitleLine = panelTitle.closest('.ca-task-title-line');
                    if (!panelTitleLine) {
                        panelTitleLine = document.createElement('div');
                        panelTitleLine.className = 'ca-task-title-line';
                        panelTitle.parentNode.insertBefore(panelTitleLine, panelTitle);
                        panelTitleLine.appendChild(panelTitle);
                    }
                    panelTitleLine.appendChild(trigger);
                }
            } else {
                var fullTitle = document.querySelector('#task-summary > h2');
                if (fullTitle) {
                    var fullTitleLine = fullTitle.closest('.ca-task-title-line');
                    if (!fullTitleLine) {
                        fullTitleLine = document.createElement('div');
                        fullTitleLine.className = 'ca-task-title-line ca-full-task-title-line';
                        fullTitle.parentNode.insertBefore(fullTitleLine, fullTitle);
                        fullTitleLine.appendChild(fullTitle);
                    }
                    fullTitleLine.appendChild(trigger);
                }
            }
            document.body.appendChild(popover);
        });

        if (document.documentElement.getAttribute('data-ca-more-actions-events') === 'true') return;
        document.documentElement.setAttribute('data-ca-more-actions-events', 'true');
        function closeAll(except) {
            document.querySelectorAll('.ca-task-actions-popover').forEach(function (popover) {
                if (popover === except) return;
                popover.hidden = true;
                var trigger = document.getElementById(popover.getAttribute('data-ca-trigger-id'));
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
            });
        }
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('.ca-task-more-trigger');
            if (trigger) {
                event.preventDefault();
                var popover = document.querySelector('.ca-task-actions-popover[data-ca-trigger-id="' + trigger.id + '"]');
                if (!popover) return;
                var willOpen = popover.hidden;
                closeAll(popover);
                popover.hidden = !willOpen;
                trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                if (willOpen) {
                    var rect = trigger.getBoundingClientRect();
                    var width = Math.min(320, window.innerWidth - 24);
                    var left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12));
                    popover.style.width = width + 'px';
                    popover.style.left = left + 'px';
                    popover.style.top = Math.max(12, Math.min(rect.bottom + 6, window.innerHeight - Math.min(popover.scrollHeight, 420) - 12)) + 'px';
                    var firstLink = popover.querySelector('a[href]');
                    if (firstLink) firstLink.focus();
                }
                return;
            }
            if (!event.target.closest('.ca-task-actions-popover')) closeAll();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            var openPopover = document.querySelector('.ca-task-actions-popover:not([hidden])');
            if (!openPopover) return;
            var trigger = document.getElementById(openPopover.getAttribute('data-ca-trigger-id'));
            closeAll();
            if (trigger) trigger.focus();
        });
        window.addEventListener('resize', function () { closeAll(); });
        window.addEventListener('scroll', function () { closeAll(); }, true);
    }

    function getTaskIdFromLink(link) {
        var owner = link.closest('[data-task-id]');
        if (owner && owner.getAttribute('data-task-id')) return owner.getAttribute('data-task-id');
        var href = link.getAttribute('href') || '';
        var match = href.match(/[?&]task_id=(\d+)/i) || href.match(/\/task\/(\d+)/i);
        return match ? match[1] : '';
    }

    function initializeLegacySurfaceAdapters() {
        var userTrigger = document.querySelector('body > header .menus-container .dropdown:last-child > .dropdown-menu');
        if (userTrigger) {
            userTrigger.setAttribute('aria-haspopup', 'menu');
            userTrigger.setAttribute('aria-label', document.documentElement.lang === 'pt-BR' ? 'Menu do usuário' : 'User menu');
            userTrigger.addEventListener('click', function () { document.body.classList.add('ca-user-menu-open'); });
        }

        document.querySelectorAll('#dashboard .table-list-row').forEach(function (row) {
            var items = row.querySelectorAll('.task-list-subtask');
            if (!items.length || row.querySelector('.ca-subtask-progress')) return;
            var completed = row.querySelectorAll('.task-list-subtask .fa-check-square-o, .task-list-subtask .fa-check-square').length;
            var progress = document.createElement('span');
            progress.className = 'ca-subtask-progress';
            progress.textContent = completed + ' / ' + items.length + (document.documentElement.lang === 'pt-BR' ? ' concluídas' : ' completed');
            row.appendChild(progress);
        });

        if (document.querySelector('#task-view')) document.body.classList.add('ca-full-task-view');
        var taskSidebar = document.querySelector('#task-view > .sidebar > ul');
        if (taskSidebar && !document.querySelector('.ca-task-action-bar')) {
            var actionBar = document.createElement('nav');
            actionBar.className = 'ca-task-action-bar';
            actionBar.setAttribute('aria-label', document.documentElement.lang === 'pt-BR' ? 'Ações da tarefa' : 'Task actions');
            var more = document.createElement('details');
            more.className = 'ca-task-more-actions';
            more.innerHTML = '<summary></summary><ul></ul>';
            more.querySelector('summary').textContent = document.documentElement.lang === 'pt-BR' ? 'Mais ações' : 'More actions';
            Array.prototype.slice.call(taskSidebar.children).forEach(function (item) {
                var link = item.querySelector('a[href]');
                if (!link || !link.textContent.replace(/\s+/g, '').trim()) return;
                var href = link ? link.getAttribute('href') : '';
                if (/TaskModificationController.*edit|CommentController.*create|TaskFileController.*create|SubtaskController.*create/i.test(href)) actionBar.appendChild(item);
                else more.querySelector('ul').appendChild(item);
            });
            if (more.querySelector('li')) actionBar.appendChild(more);
            var content = document.querySelector('#task-view > .sidebar-content');
            if (content) content.parentNode.insertBefore(actionBar, content);
            taskSidebar.closest('.sidebar').hidden = true;
        }
        if (document.querySelector('.user-view, #user-view') || document.querySelector('form[action*="UserModificationController"]')) document.body.classList.add('ca-user-profile-view');

        var observer = new MutationObserver(function () {
            initializeProjectPicker();
            suppressNativeProjectPicker();
            initializeAvatarFallbacks();
            initializeTaskInteractions();
            initializeContentSurfaces();
            document.querySelectorAll('#modal-content select[name="priority"] option').forEach(function (option) {
                var value = parseInt(option.value, 10);
                if (isNaN(value)) return;
                var label = value > 0 ? (document.documentElement.lang === 'pt-BR' ? 'Alta' : 'High') : (value < 0 ? (document.documentElement.lang === 'pt-BR' ? 'Baixa' : 'Low') : 'Normal');
                if (!option.getAttribute('data-ca-priority-label')) {
                    option.setAttribute('data-ca-priority-label', 'true');
                    option.textContent = option.value + ' — ' + label;
                }
            });
            var projectMenu = document.querySelector('#select-dropdown-menu');
            var projectTrigger = document.querySelector('.ca-project-picker-trigger span');
            if (projectMenu && projectTrigger && !projectMenu.querySelector('.ca-project-current')) {
                var currentProject = document.createElement('li');
                currentProject.className = 'select-dropdown-menu-item ca-project-current';
                currentProject.setAttribute('aria-selected', 'true');
                currentProject.textContent = '✓ ' + projectTrigger.textContent.trim();
                projectMenu.insertBefore(currentProject, projectMenu.firstChild);
            }
            document.querySelectorAll('.file-thumbnail img').forEach(function (image) {
                if (image.getAttribute('data-ca-file-fallback')) return;
                image.setAttribute('data-ca-file-fallback', 'ready');
                image.addEventListener('error', function () {
                    var fallback = document.createElement('div');
                    fallback.className = 'ca-file-unavailable';
                    fallback.innerHTML = '<i class="fa fa-paperclip" aria-hidden="true"></i><span></span><small></small>';
                    var card = image.closest('.file-thumbnail');
                    var title = card ? card.querySelector('.file-thumbnail-title') : null;
                    fallback.querySelector('span').textContent = title ? title.textContent.trim() : 'File';
                    fallback.querySelector('small').textContent = document.documentElement.lang === 'pt-BR' ? 'Arquivo indisponível localmente' : 'File unavailable locally';
                    image.replaceWith(fallback);
                }, {once: true});
            });
            var modal = document.querySelector('#modal-box');
            if (modal) {
                var heading = modal.querySelector('h2');
                if (heading && /activity|atividade/i.test(heading.textContent)) {
                    modal.classList.add('ca-activity-overlay');
                    var closeButton = modal.querySelector('#modal-close-button');
                    if (closeButton) {
                        var closeLabel = document.documentElement.lang === 'pt-BR' ? 'Fechar' : 'Close';
                        closeButton.setAttribute('aria-label', closeLabel);
                        closeButton.setAttribute('title', closeLabel);
                    }
                    groupActivityEvents(modal);
                }
            }
            if (!document.querySelector('#dropdown')) document.body.classList.remove('ca-user-menu-open');
        });
        observer.observe(document.body, {childList: true, subtree: true});
        var adapterMarker = document.createComment('company-agile-adapters');
        document.body.appendChild(adapterMarker);
        adapterMarker.remove();

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                var activeDropdown = document.querySelector('.active-dropdown-menu');
                if (activeDropdown) activeDropdown.click();
                document.querySelectorAll('.ca-task-more-actions[open]').forEach(function (details) { details.open = false; });
            }
            if (event.key !== 'Tab') return;
            var modal = document.querySelector('#modal-box:not([style*="display: none"])');
            if (!modal) return;
            var focusable = modal.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
            if (!focusable.length) return;
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
        document.addEventListener('click', function (event) {
            document.querySelectorAll('.ca-task-more-actions[open]').forEach(function (details) {
                if (!details.contains(event.target)) details.open = false;
            });
        });
    }

    function openPromptPopover(button) {
        document.querySelectorAll('.ca-prompt-popover').forEach(function (popover) { popover.remove(); });
        var popover = document.createElement('form');
        popover.className = 'ca-prompt-popover';
        popover.innerHTML = '<label></label><div><input type="text" autocomplete="off"><button type="submit"><i class="fa fa-check"></i></button></div>';
        popover.querySelector('label').textContent = button.getAttribute('data-ca-prompt-label');
        button.parentNode.insertBefore(popover, button.nextSibling);
        var input = popover.querySelector('input');
        input.focus();
        popover.addEventListener('submit', function (event) {
            event.preventDefault();
            if (input.value.trim()) submitFilter(button.getAttribute('data-ca-prompt-filter') + ':"' + input.value.trim().replace(/"/g, '') + '"');
        });
        popover.addEventListener('keydown', function (event) { if (event.key === 'Escape') { popover.remove(); button.focus(); } });
    }

    function initializeBoardFilters() {
        var state = {type: 'all', sprint: 'all', epic: 'all'};
        var bar = document.querySelector('[data-ca-filter-bar]');
        var searchInput = getSearchInput();

        function refreshButtons(attribute, value) {
            document.querySelectorAll('[data-ca-' + attribute + '-filter]').forEach(function (button) {
                var active = button.getAttribute('data-ca-' + attribute + '-filter') === value && value !== 'all';
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
                var chip = button.closest('.ca-filter-menu');
                if (chip) chip.querySelector('.ca-filter-chip').classList.toggle('ca-filter-active', active);
            });
        }

        function apply() {
            document.querySelectorAll('.task-board').forEach(function (card) {
                var marker = card.querySelector('[data-ca-issue-type]');
                var typeMatch = state.type === 'all' || (marker && marker.getAttribute('data-ca-issue-type') === state.type);
                var epicMatch = state.epic === 'all' || (marker && marker.getAttribute('data-ca-epic-id') === state.epic);
                var sprintMatch = state.sprint === 'all'
                    || (state.sprint === 'active' && marker && marker.getAttribute('data-ca-sprint-status') === 'active')
                    || (state.sprint === 'backlog' && marker && marker.getAttribute('data-ca-sprint-id') === '0')
                    || (marker && marker.getAttribute('data-ca-sprint-id') === state.sprint);
                card.classList.toggle('ca-filtered-out', !(typeMatch && epicMatch && sprintMatch));
            });
            if (bar) bar.classList.toggle('ca-has-active-filters', state.type !== 'all' || state.sprint !== 'all' || state.epic !== 'all');
        }

        ['type', 'sprint', 'epic'].forEach(function (attribute) {
            document.querySelectorAll('[data-ca-' + attribute + '-filter]').forEach(function (button) {
                button.setAttribute('aria-pressed', 'false');
                button.addEventListener('click', function () {
                    state[attribute] = button.getAttribute('data-ca-' + attribute + '-filter');
                    refreshButtons(attribute, state[attribute]);
                    apply();
                });
            });
        });

        if (bar && searchInput && searchInput.value.trim() !== '' && searchInput.value.trim() !== 'status:open') {
            bar.classList.add('ca-has-active-filters');
        }

        return function () {
            state = {type: 'all', sprint: 'all', epic: 'all'};
            ['type', 'sprint', 'epic'].forEach(function (attribute) { refreshButtons(attribute, 'all'); });
            apply();
        };
    }

    function initialize() {
        if (!document.querySelector('.ca-sidebar')) return;
        document.documentElement.classList.add('company-agile');
        initializeNavigation();
        initializeProjectSettingsNavigation();
        initializeProjectPicker();
        suppressNativeProjectPicker();
        initializeAvatarFallbacks();
        initializeTaskInteractions();
        initializeContentSurfaces();
        initializeCompanyPopovers();
        initializeAdvancedSearch();
        initializeActiveFilterSummary();
        initializeLegacySurfaceAdapters();
        initializeManagement();
        initializeMyTasksDashboard();
        initializeDashboardProjectCards();
        initializeSettingsSurface();
        initializeProfileSurface();
        initializeDashboardSubtasks();
        restoreFilterScroll();
        var clearBoardFilters = initializeBoardFilters();
        document.querySelectorAll('.task-board').forEach(function (card) {
            var avatar = card.querySelector('.task-board-header .task-board-avatar');
            var footer = card.querySelector('.task-board-icons-row:last-child');
            if (avatar && footer) {
                avatar.classList.add('ca-card-assignee');
                footer.appendChild(avatar);
            }
        });

        var panelController = createOverlayController({
            dialog: '[data-ca-task-panel]', backdrop: '[data-ca-panel-backdrop]', body: '[data-ca-panel-body]',
            closeSelector: '[data-ca-panel-close]', openClass: 'ca-panel-open', history: true
        });
        var quickController = createOverlayController({
            dialog: '[data-ca-quick-dialog]', backdrop: '[data-ca-quick-backdrop]', body: '[data-ca-quick-body]',
            closeSelector: '[data-ca-quick-close]', openClass: 'ca-quick-open', history: false
        });

        document.addEventListener('click', function (event) {
            var taskLink = event.target.closest('.task-board-title a, .ca-backlog-title, .task-list a[href*="task_id"], .table-list-row a[href*="task_id"], .activity-event a[href*="task_id"], .task-links-table a[href*="task_id"], .ca-relation-item a, .ca-epic-stories a[href*="task_id"], .ca-management-task-link, .pm-task-card, .pm-attention-card');
            if (taskLink && !taskLink.matches('.ca-open-full-task, .js-modal-small, .js-modal-medium, .js-modal-large') && panelController && !event.metaKey && !event.ctrlKey && !event.shiftKey) {
                var taskId = getTaskIdFromLink(taskLink);
                var sidebar = document.querySelector('.ca-sidebar');
                if (taskId && sidebar) {
                    event.preventDefault();
                    panelController.open(sidebar.getAttribute('data-ca-panel-url').replace('__TASK_ID__', taskId), taskLink.href);
                }
            }

            var quickButton = event.target.closest('[data-ca-quick-create-url]');
            if (quickButton && quickController) {
                event.preventDefault();
                quickController.open(quickButton.getAttribute('data-ca-quick-create-url'));
            }

            document.querySelectorAll('[data-ca-create-relations]').forEach(function (section) {
                if (section._caRelations && !section._caRelations.contains(event.target)) section._caRelations.close();
            });

            var createEpic = event.target.closest('[data-ca-create-epic]');
            if (createEpic && quickController) {
                event.preventDefault();
                var boardQuickButton = document.querySelector('[data-ca-quick-create-url]');
                if (boardQuickButton) {
                    var separator = boardQuickButton.getAttribute('data-ca-quick-create-url').indexOf('?') === -1 ? '?' : '&';
                    quickController.open(boardQuickButton.getAttribute('data-ca-quick-create-url') + separator + 'issue_type=epic');
                }
            }
        });

        document.addEventListener('submit', function (event) {
            var tagForm = event.target.closest('.ca-popover-create-form');
            if (tagForm) {
                event.preventDefault();
                var tagInput = tagForm.querySelector('input[name="name"]');
                var tagFeedback = tagForm.querySelector('[role="status"]');
                var tagSubmit = tagForm.querySelector('[type="submit"]');
                tagSubmit.disabled = true;
                fetch(tagForm.action, {method: 'POST', body: new FormData(tagForm), credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(function (response) { return response.json().then(function (data) { return {ok: response.ok, data: data}; }); })
                    .then(function (result) {
                        if (!result.ok || !result.data.success) throw new Error(result.data.message || 'error');
                        var currentTags = getFilterValues('tag');
                        if (currentTags.indexOf(result.data.name) === -1) currentTags.push(result.data.name);
                        replaceFilterGroup('tag', currentTags);
                    })
                    .catch(function (error) { tagFeedback.textContent = error.message; tagSubmit.disabled = false; tagInput.focus(); });
                return;
            }
            var agileForm = event.target.closest('.ca-agile-inline-form');
            if (agileForm) {
                event.preventDefault();
                var agileButton = agileForm.querySelector('[type="submit"]');
                agileForm.classList.remove('ca-is-error', 'ca-is-success');
                agileForm.classList.add('ca-is-saving');
                if (agileButton) agileButton.disabled = true;
                fetch(agileForm.action, {method: 'POST', body: new FormData(agileForm), credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(function (response) { return response.json().then(function (data) { return {ok: response.ok, data: data}; }); })
                    .then(function (result) {
                        if (!result.ok || !result.data.success) throw new Error(result.data.message || 'error');
                        var feedback = document.querySelector('[data-ca-planning-feedback]');
                        if (feedback) feedback.textContent = result.data.message;
                        agileForm.classList.remove('ca-is-saving');
                        agileForm.classList.add('ca-is-success');
                        if (agileButton) agileButton.disabled = false;
                        if (agileForm.closest('[data-ca-task-panel]')) window.setTimeout(function () { window.location.reload(); }, 350);
                    })
                    .catch(function (error) {
                        var feedback = document.querySelector('[data-ca-planning-feedback]') || agileForm.querySelector('.ca-inline-feedback');
                        if (feedback) feedback.textContent = error.message; else window.alert(error.message);
                        agileForm.classList.remove('ca-is-saving');
                        agileForm.classList.add('ca-is-error');
                        if (agileButton) agileButton.disabled = false;
                    });
                return;
            }
            var form = event.target.closest('.ca-quick-create-form');
            if (!form) return;
            event.preventDefault();
            var feedback = form.querySelector('.ca-quick-create-feedback');
            var submit = form.querySelector('[type="submit"]');
            clearQuickCreateErrors(form);
            submit.disabled = true;
            feedback.textContent = submit.getAttribute('data-saving-label') || '';
            fetch(form.action, {method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function (response) { return response.json().then(function (data) { return {ok: response.ok, data: data}; }); })
                .then(function (result) {
                    if (!result.ok || !result.data.success) {
                        renderQuickCreateErrors(form, result.data || {});
                        submit.disabled = false;
                        return;
                    }
                    feedback.textContent = result.data.message;
                    if (result.data.another_task) {
                        var title = form.querySelector('input[name="title"]');
                        var description = form.querySelector('textarea[name="description"]');
                        if (title) title.value = '';
                        if (description) description.value = '';
                        var relationSection = form.querySelector('[data-ca-create-relations]');
                        if (relationSection) initializeCreateRelations(relationSection).reset();
                        submit.disabled = false;
                        if (title) title.focus();
                    } else {
                        window.location.href = result.data.board_url;
                    }
                })
                .catch(function (error) { renderQuickCreateErrors(form, {message: error.message, errors: {}}); submit.disabled = false; });
        });

        document.addEventListener('change', function (event) {
            if (event.target.matches('input[name="duplicate_multiple_projects"]')) {
                var duplicateProjects = event.target.closest('form').querySelector('[data-ca-duplicate-projects]');
                if (duplicateProjects) duplicateProjects.hidden = !event.target.checked;
                return;
            }
            if (!event.target.matches('[data-ca-issue-type-select]')) return;
            var form = event.target.closest('form');
            var option = event.target.options[event.target.selectedIndex];
            var code = option ? option.getAttribute('data-code') : '';
            var storyFields = form.querySelector('[data-ca-story-fields]');
            var pointsField = form.querySelector('[data-ca-points-field]');
            if (storyFields) storyFields.hidden = code !== 'story';
            if (pointsField) pointsField.hidden = code === 'epic';
        });

        document.addEventListener('focusin', function (event) {
            var relationSection = event.target.closest('[data-ca-create-relations]');
            if (relationSection) initializeCreateRelations(relationSection);
        });

        document.addEventListener('click', function (event) {
            var assignButton = event.target.closest('[data-ca-assign-me], .ca-assign-me');
            if (!assignButton) return;
            event.preventDefault();
            var targetId = assignButton.getAttribute('data-target-id');
            var select = targetId ? document.getElementById(targetId) : assignButton.closest('.ca-assignee-field').querySelector('select[name="owner_id"]');
            var currentId = assignButton.getAttribute('data-ca-current-user-id') || assignButton.getAttribute('data-current-id');
            if (select && currentId && select.querySelector('option[value="' + currentId + '"]')) {
                select.value = currentId;
                select.dispatchEvent(new Event('change', {bubbles: true}));
                assignButton.hidden = true;
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            var relationSection = event.target.closest('[data-ca-create-relations]');
            if (relationSection && relationSection._caRelations && event.target.getAttribute('aria-expanded') === 'true') {
                relationSection._caRelations.close();
                return;
            }
            if (quickController && !quickController.dialog.hidden) quickController.close();
            else if (panelController && !panelController.dialog.hidden) panelController.close();
        });
        window.addEventListener('popstate', function () {
            if (panelController && !panelController.dialog.hidden) panelController.close(true);
        });

        document.querySelectorAll('[data-ca-filter]').forEach(function (button) {
            button.addEventListener('click', function () {
                submitFilter(button.getAttribute('data-ca-filter'));
            });
        });

        document.querySelectorAll('[data-ca-prompt-filter]').forEach(function (button) {
            button.addEventListener('click', function () {
                openPromptPopover(button);
            });
        });

        var backlogEpicFilter = document.querySelector('[data-ca-backlog-epic-filter]');
        if (backlogEpicFilter) backlogEpicFilter.addEventListener('change', function () {
            var filter = backlogEpicFilter.value;
            document.querySelectorAll('.ca-backlog-task').forEach(function (row) {
                row.classList.toggle('ca-filtered-out', filter !== 'all' && row.getAttribute('data-ca-epic-id') !== filter);
            });
        });

        var planningPage = document.querySelector('[data-ca-planning-page]');
        if (planningPage) {
            var draggedTask = null;
            planningPage.addEventListener('dragstart', function (event) {
                draggedTask = event.target.closest('.ca-backlog-task');
                if (draggedTask) {
                    draggedTask.classList.add('ca-dragging');
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', draggedTask.getAttribute('data-task-id'));
                }
            });
            planningPage.addEventListener('dragend', function () {
                if (draggedTask) draggedTask.classList.remove('ca-dragging');
                document.querySelectorAll('.ca-drop-active').forEach(function (zone) { zone.classList.remove('ca-drop-active'); });
                draggedTask = null;
            });
            planningPage.addEventListener('dragover', function (event) {
                var zone = event.target.closest('[data-ca-drop-zone]');
                if (!zone || !draggedTask) return;
                event.preventDefault();
                zone.classList.add('ca-drop-active');
            });
            planningPage.addEventListener('dragleave', function (event) {
                var zone = event.target.closest('[data-ca-drop-zone]');
                if (zone) zone.classList.remove('ca-drop-active');
            });
            planningPage.addEventListener('drop', function (event) {
                var zone = event.target.closest('[data-ca-drop-zone]');
                if (!zone || !draggedTask) return;
                event.preventDefault();
                zone.classList.remove('ca-drop-active');
                var list = zone.closest('[data-ca-task-list]');
                var before = event.target.closest('.ca-backlog-task');
                if (before && before !== draggedTask) zone.insertBefore(draggedTask, before); else zone.appendChild(draggedTask);
                var position = Array.prototype.indexOf.call(zone.querySelectorAll('.ca-backlog-task'), draggedTask) + 1;
                var data = new FormData();
                data.append('csrf_token', planningPage.getAttribute('data-ca-csrf'));
                data.append('task_id', draggedTask.getAttribute('data-task-id'));
                data.append('sprint_id', list.getAttribute('data-sprint-id'));
                data.append('position', position);
                var feedback = planningPage.querySelector('[data-ca-planning-feedback]');
                fetch(planningPage.getAttribute('data-ca-move-url'), {method: 'POST', body: data, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(function (response) { return response.json().then(function (json) { return {ok: response.ok, json: json}; }); })
                    .then(function (result) { if (!result.ok) throw new Error(result.json.message); feedback.textContent = result.json.message; })
                    .catch(function (error) { feedback.textContent = error.message; window.setTimeout(function () { window.location.reload(); }, 900); });
            });
        }

        var clear = document.querySelector('[data-ca-filter-clear]');
        if (clear) {
            clear.addEventListener('click', function () {
                clearBoardFilters();
                var form = getSearchForm();
                var input = getSearchInput();
                if (form && input && input.value.trim() !== '' && input.value.trim() !== 'status:open') {
                    input.value = 'status:open';
                    form.submit();
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
}());
