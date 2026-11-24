/* assets/js/content.js - Manage content page and the content form page.
   Everything posts to admin/api/content.php (PHP does the real validation and
   permission checks; the checks here only give faster feedback). After a successful
   save the page reloads or returns to the list, where the flash message is shown. */

(function () {
    'use strict';

    var API = 'admin/api/content.php';
    var listUrl = RC.url('admin/content/index.php');

    // Keep the two content areas compact and allow activity links to open the right tab.
    var contentTabs = Array.prototype.slice.call(document.querySelectorAll('[data-content-tab]'));
    var contentPanels = Array.prototype.slice.call(document.querySelectorAll('[data-content-panel]'));
    if (contentTabs.length && contentPanels.length) {
        var contentTabStorageKey = 'rc2000-admin-content-tab';

        function selectContentTab(name, remember) {
            contentTabs.forEach(function (tab) {
                var selected = tab.getAttribute('data-content-tab') === name;
                tab.classList.toggle('is-active', selected);
                tab.setAttribute('aria-selected', selected ? 'true' : 'false');
                tab.tabIndex = selected ? 0 : -1;
            });
            contentPanels.forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-content-panel') !== name;
            });
            if (remember) {
                try { window.sessionStorage.setItem(contentTabStorageKey, name); } catch (e) {}
            }
        }
        contentTabs.forEach(function (tab) {
            tab.addEventListener('click', function (event) {
                event.preventDefault();
                selectContentTab(tab.getAttribute('data-content-tab'), true);
                window.history.replaceState(null, '', tab.href);
            });
        });

        var currentUrl = new URL(window.location.href);
        var activityLink = ['challenge', 'new_challenge', 'quiz', 'new_quiz'].some(function (key) {
            return currentUrl.searchParams.has(key);
        }) || currentUrl.hash === '#weekly-challenge' || currentUrl.hash === '#daily-quiz';
        var preferredTab = !activityLink && currentUrl.searchParams.get('section') === 'website' ? 'website' : 'activities';
        if (!activityLink && !currentUrl.searchParams.has('section')) {
            try {
                var savedTab = window.sessionStorage.getItem(contentTabStorageKey);
                if (savedTab === 'website' || savedTab === 'activities') { preferredTab = savedTab; }
            } catch (e) {}
        }
        selectContentTab(preferredTab, false);
    }

    var quizMaker = document.querySelector('[data-quiz-question-maker]');
    if (quizMaker) {
        function renumberOptions(questionEditor) {
            var questionIndex = Array.prototype.indexOf.call(quizMaker.querySelectorAll('[data-question-editor]'), questionEditor);
            var rows = Array.prototype.slice.call(questionEditor.querySelectorAll('[data-option-row]'));
            rows.forEach(function (row, optionIndex) {
                var radio = row.querySelector('input[type="radio"]');
                var option = row.querySelector('input[type="text"]');
                var remove = row.querySelector('[data-option-remove]');
                radio.name = 'questions[' + questionIndex + '][correct]';
                radio.value = String(optionIndex);
                radio.setAttribute('aria-label', 'Mark option ' + (optionIndex + 1) + ' as correct');
                option.name = 'questions[' + questionIndex + '][options][]';
                option.placeholder = 'Answer option ' + (optionIndex + 1);
                remove.setAttribute('aria-label', 'Remove option ' + (optionIndex + 1));
                remove.disabled = rows.length <= 2;
            });
            var add = questionEditor.querySelector('[data-option-add]');
            add.disabled = rows.length >= 5;
        }

        quizMaker.addEventListener('click', function (event) {
            var add = event.target.closest('[data-option-add]');
            if (add) {
                var questionEditor = add.closest('[data-question-editor]');
                var list = questionEditor.querySelector('[data-option-list]');
                var count = list.querySelectorAll('[data-option-row]').length;
                if (count >= 5) { return; }
                var row = document.createElement('div');
                row.className = 'quiz-option-maker';
                row.setAttribute('data-option-row', '');
                row.innerHTML = '<label class="quiz-correct-choice"><input type="radio" required><span>Correct</span></label><input type="text" maxlength="500" required><button type="button" class="quiz-option-remove" data-option-remove>Remove</button>';
                list.appendChild(row);
                renumberOptions(questionEditor);
                row.querySelector('input[type="text"]').focus();
                return;
            }
            var remove = event.target.closest('[data-option-remove]');
            if (remove) {
                var editor = remove.closest('[data-question-editor]');
                var optionList = remove.closest('[data-option-list]');
                var currentRows = optionList.querySelectorAll('[data-option-row]');
                if (currentRows.length <= 2) { return; }
                var removedWasCorrect = !!remove.closest('[data-option-row]').querySelector('input[type="radio"]').checked;
                remove.closest('[data-option-row]').remove();
                renumberOptions(editor);
                if (removedWasCorrect) { editor.querySelector('[data-option-list] input[type="radio"]').checked = true; }
            }
        });
    }

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-editor-toggle]');
        if (!toggle) { return; }
        var editor = document.getElementById(toggle.getAttribute('data-editor-toggle'));
        if (!editor) { return; }
        var expanded = toggle.getAttribute('aria-expanded') !== 'true';
        editor.hidden = !expanded;
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        toggle.textContent = expanded ? toggle.getAttribute('data-close-label') : toggle.getAttribute('data-open-label');
    });

    function showMessage(el, text) {
        if (!el) { return; }
        el.textContent = text;
        el.hidden = false;
    }

    /** Show a preview when the user picks an image file. */
    function wirePreview(input, img) {
        if (!input || !img) { return; }
        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            if (!f) { img.hidden = true; return; }
            if (f.size > 5 * 1024 * 1024) {
                alert('That image is larger than 5 MB. Please choose a smaller one.');
                input.value = '';
                img.hidden = true;
                return;
            }
            img.src = URL.createObjectURL(f);
            img.hidden = false;
        });
    }

    function post(data, onSuccess, onError) {
        RC.api(API, { method: 'POST', body: data })
            .then(function (res) { res.success ? onSuccess(res) : onError(res.message); })
            .catch(function () { onError('Could not reach the server. Please try again.'); });
    }

    // ---------- Publish / Archive buttons in the lists ----------
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-set-status]');
        if (!btn) { return; }
        var data = new FormData();
        data.append('action', 'set_status');
        data.append('entity', btn.getAttribute('data-entity'));
        data.append('id', btn.getAttribute('data-id'));
        data.append('status', btn.getAttribute('data-status'));
        btn.disabled = true;
        post(data, function () { window.location.reload(); }, function (msg) {
            btn.disabled = false;
            alert(msg);
        });
    });

    // ---------- Gallery popup ----------
    var dialog = document.getElementById('gallery-dialog');
    if (dialog) {
        var form = document.getElementById('gallery-form');
        var title = document.getElementById('gallery-dialog-title');
        var preview = document.getElementById('gallery-preview');
        var message = document.getElementById('gallery-message');
        var saveBtn = document.getElementById('gallery-save');
        var delBtn = document.getElementById('gallery-delete');
        var imageLabel = document.getElementById('gal-image-label');
        wirePreview(form.elements.image, preview);

        function openDialog(tile) {
            form.reset();
            message.hidden = true;
            preview.hidden = true;
            var editing = !!tile;
            title.textContent = editing ? 'Edit image' : 'Add image';
            imageLabel.textContent = editing
                ? 'Replace image (optional · JPG, PNG or WebP, max 5 MB)'
                : 'Image (JPG, PNG or WebP, max 5 MB)';
            form.elements.id.value = editing ? tile.getAttribute('data-id') : '0';
            form.elements.version.value = editing ? tile.getAttribute('data-version') : '';
            form.elements.title.value = editing ? tile.getAttribute('data-title') : '';
            form.elements.caption.value = editing ? tile.getAttribute('data-caption') : '';
            form.elements.status.value = editing ? tile.getAttribute('data-status') : 'published';
            delBtn.hidden = !editing;
            if (editing) {
                preview.src = tile.getAttribute('data-src');
                preview.hidden = false;
            }
            dialog.showModal();
        }

        document.addEventListener('click', function (e) {
            var edit = e.target.closest('[data-gallery-edit]');
            if (edit) { openDialog(edit); return; }
            if (e.target.closest('[data-gallery-new]')) { openDialog(null); }
        });
        document.getElementById('gallery-cancel').addEventListener('click', function () { dialog.close(); });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            message.hidden = true;
            if (form.elements.title.value.trim() === '') {
                showMessage(message, 'Please enter a title.');
                return;
            }
            if (form.elements.id.value === '0' && !form.elements.image.files.length) {
                showMessage(message, 'Please choose an image to upload.');
                return;
            }
            var data = new FormData(form);
            data.append('action', 'gallery_save');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving…';
            post(data, function () { window.location.reload(); }, function (msg) {
                showMessage(message, msg);
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save';
            });
        });

        delBtn.addEventListener('click', function () {
            if (!window.confirm('Delete this image permanently? It will disappear from the public gallery.')) { return; }
            var data = new FormData();
            data.append('action', 'delete');
            data.append('entity', 'gallery');
            data.append('id', form.elements.id.value);
            delBtn.disabled = true;
            post(data, function () { window.location.reload(); }, function (msg) {
                showMessage(message, msg);
                delBtn.disabled = false;
            });
        });
    }

    // ---------- Story / announcement / trivia form page ----------
    var cform = document.getElementById('content-form');
    if (cform) {
        var cmsg = document.getElementById('form-message');
        var csave = document.getElementById('content-save');
        var cdel = document.getElementById('content-delete');
        wirePreview(document.getElementById('f-image'), document.getElementById('f-preview'));

        cform.addEventListener('submit', function (e) {
            e.preventDefault();
            cmsg.hidden = true;
            var data = new FormData(cform);
            data.append('action', cform.getAttribute('data-action'));
            csave.disabled = true;
            csave.textContent = 'Saving…';
            post(data, function () { window.location.href = listUrl; }, function (msg) {
                showMessage(cmsg, msg);
                csave.disabled = false;
                csave.textContent = 'Save';
            });
        });

        if (cdel) {
            cdel.addEventListener('click', function () {
                if (!window.confirm('Delete this item permanently? This cannot be undone. (To just hide it, set the status to Draft or Archived instead.)')) { return; }
                var data = new FormData();
                data.append('action', 'delete');
                data.append('entity', cdel.getAttribute('data-entity'));
                data.append('id', cdel.getAttribute('data-id'));
                cdel.disabled = true;
                post(data, function () { window.location.href = listUrl; }, function (msg) {
                    showMessage(cmsg, msg);
                    cdel.disabled = false;
                });
            });
        }
    }
})();
