@extends('layouts.app')

@section('title', 'New Translation')

@section('styles')
    @vite(['resources/css/views/translation.css'])
@endsection

@section('content')
<div class="translation-layout">
    <div class="translation-panel translation-panel--source">
        <div class="translation-panel__header">
            <select id="source-lang">
                <option value="English" selected>English</option>
                <option value="Cebuano">Cebuano</option>
                <option value="Filipino">Filipino</option>
            </select>
            <button class="translation-swap" aria-label="Swap languages">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M7 16V4m0 0L3 8m4-4l4 4"/>
                    <path d="M17 8v12m0 0l4-4m-4 4l-4-4"/>
                </svg>
            </button>
            <select id="target-lang">
                <option value="English">English</option>
                <option value="Cebuano" selected>Cebuano</option>
                <option value="Filipino">Filipino</option>
            </select>
        </div>
        <div class="translation-panel__body">
            <textarea id="source-text" maxlength="5000" placeholder="Enter text to translate..."></textarea>
            <div class="translation-panel__file-info" style="display:none">
                <span id="file-name"></span>
                <button id="remove-file" type="button">Remove</button>
            </div>
            <div class="translation-panel__footer">
                <button id="speak-source-btn" type="button" class="speak-btn" aria-label="Read source text aloud">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                        <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
                        <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
                    </svg>
                </button>
                <button id="attach-btn" type="button">Attach file</button>
                <input id="file-input" type="file" hidden accept=".docx,.pdf,.txt,.md,.rtf,.odt,.csv">
                <select id="pdf-column-mode" style="display:none">
                    <option value="auto" selected>Auto (detect columns)</option>
                    <option value="single">Single column</option>
                    <option value="left">Left column only</option>
                    <option value="right">Right column only</option>
                </select>
                <span id="char-counter">0/5000</span>
            </div>
        </div>
        <div class="translation-panel__error"></div>
    </div>
    <div class="translation-panel translation-panel--output">
        <div class="translation-panel__header">
            <span>Translation</span>
        </div>
        <div class="translation-panel__body">
            <div id="output-text"></div>
            <div id="output-download" hidden>
                <a id="download-link" href="#">Download translated file</a>
            </div>
        </div>
        <div class="translation-panel__footer">
            <button id="speak-output-btn" type="button" class="speak-btn" aria-label="Read translation aloud">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                    <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
                    <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
                </svg>
            </button>
            <button id="copy-btn" type="button" aria-disabled="true">Copy</button>
            <button id="save-btn" type="button" aria-disabled="true">Save</button>
        </div>
        <div class="translation-panel__error"></div>
    </div>

    <div class="translation-actions">
        <button id="translate-btn" type="button" class="btn primary">Translate</button>
    </div>
</div>

<script>
(function () {
    'use strict';

    // ─── Element references ───────────────────────────────────────────────────
    var sourceText   = document.getElementById('source-text');
    var charCounter  = document.getElementById('char-counter');
    var sourceLang   = document.getElementById('source-lang');
    var targetLang   = document.getElementById('target-lang');
    var swapBtn      = document.querySelector('.translation-swap');
    var outputText   = document.getElementById('output-text');
    var attachBtn    = document.getElementById('attach-btn');
    var fileInput    = document.getElementById('file-input');
    var fileNameSpan = document.getElementById('file-name');
    var removeFile   = document.getElementById('remove-file');
    var fileInfo     = document.querySelector('.translation-panel__file-info');
    var sourcePanel  = document.querySelector('.translation-panel--source');
    var outputPanel  = document.querySelector('.translation-panel--output');
    var pdfColumnMode = document.getElementById('pdf-column-mode');

    // Helper: get the error element for a given panel
    function getError(panel) {
        return panel.querySelector('.translation-panel__error');
    }

    function showError(panel, message) {
        var el = getError(panel);
        if (el) { el.textContent = message; }
    }

    function clearError(panel) {
        var el = getError(panel);
        if (el) { el.textContent = ''; }
    }

    // ─── Task 12.1 — Character counter and paste-truncation ──────────────────

    var MAX_CHARS = 5000;
    var WARN_THRESHOLD = 4500;

    function updateCounter() {
        var len = sourceText.value.length;
        charCounter.textContent = len + '/' + MAX_CHARS;
        if (len > WARN_THRESHOLD) {
            charCounter.classList.add('counter-warning');
        } else {
            charCounter.classList.remove('counter-warning');
        }
    }

    sourceText.addEventListener('input', updateCounter);

    sourceText.addEventListener('paste', function (e) {
        e.preventDefault();
        var pasted = (e.clipboardData || window.clipboardData).getData('text');
        var current = sourceText.value;
        var selStart = sourceText.selectionStart;
        var selEnd   = sourceText.selectionEnd;
        // Replace selected range with pasted text, then cap at MAX_CHARS
        var before = current.substring(0, selStart);
        var after  = current.substring(selEnd);
        var combined = (before + pasted + after).substring(0, MAX_CHARS);
        sourceText.value = combined;
        // Place cursor after the inserted (possibly truncated) text
        var newCursor = Math.min(selStart + pasted.length, MAX_CHARS);
        sourceText.setSelectionRange(newCursor, newCursor);
        updateCounter();
    });

    // ─── Task 12.2 — Swap button logic ───────────────────────────────────────

    swapBtn.addEventListener('click', function () {
        var src = sourceLang.value;
        var tgt = targetLang.value;

        if (src === tgt) {
            showError(sourcePanel, 'Source and target languages must be different.');
            return;
        }

        // Swap the select values
        sourceLang.value = tgt;
        targetLang.value = src;

        // Clear source panel error
        clearError(sourcePanel);

        // Clear output (text and error) — file attachment is preserved as-is
        outputText.textContent = '';
        clearError(outputPanel);
    });

    // ─── Task 12.3 — File attachment, validation, and removal ────────────────

    var ALLOWED_EXTENSIONS = ['.docx', '.pdf', '.txt', '.md', '.rtf', '.odt', '.csv'];
    var MAX_FILE_SIZE = 10485760; // 10 MB

    attachBtn.addEventListener('click', function () {
        fileInput.click();
    });

    fileInput.addEventListener('change', function () {
        var file = fileInput.files[0];
        if (!file) { return; }

        // Validate extension
        var name = file.name;
        var dotIndex = name.lastIndexOf('.');
        var ext = dotIndex !== -1 ? name.substring(dotIndex).toLowerCase() : '';
        if (ALLOWED_EXTENSIONS.indexOf(ext) === -1) {
            showError(sourcePanel, 'Unsupported file type. Allowed: ' + ALLOWED_EXTENSIONS.join(', '));
            fileInput.value = '';
            return;
        }

        // Validate size
        if (file.size > MAX_FILE_SIZE) {
            showError(sourcePanel, 'File is too large. Maximum size is 10 MB.');
            fileInput.value = '';
            return;
        }

        // Success — show file info, hide textarea and counter
        clearError(sourcePanel);
        fileNameSpan.textContent = file.name;
        sourceText.style.display = 'none';
        charCounter.style.display = 'none';
        fileInfo.style.display = '';

        // Show PDF column mode selector only for PDF files
        var isPdf = ext === '.pdf';
        pdfColumnMode.style.display = isPdf ? '' : 'none';
        if (!isPdf) { pdfColumnMode.value = 'auto'; }
    });

    removeFile.addEventListener('click', function () {
        fileInput.value = '';
        fileInfo.style.display = 'none';
        sourceText.style.display = '';
        charCounter.style.display = '';
        clearError(sourcePanel);
        pdfColumnMode.style.display = 'none';
        pdfColumnMode.value = 'auto';
    });

    // ─── Task 12.4 & 12.5 — Translate button submit flow ─────────────────────

    var translateBtn = document.getElementById('translate-btn');
    var copyBtn      = document.getElementById('copy-btn');
    var saveBtn      = document.getElementById('save-btn');
    var outputDownload = document.getElementById('output-download');
    var downloadLink   = document.getElementById('download-link');

    var TRANSLATE_BTN_DEFAULT_TEXT = 'Translate';
    var TRANSLATE_BTN_LOADING_TEXT = 'Translating...';

    function setTranslating(isTranslating) {
        translateBtn.disabled = isTranslating;
        translateBtn.textContent = isTranslating ? TRANSLATE_BTN_LOADING_TEXT : TRANSLATE_BTN_DEFAULT_TEXT;
    }

    translateBtn.addEventListener('click', function () {
        // Clear previous errors and output
        clearError(sourcePanel);
        clearError(outputPanel);

        var file = fileInput.files[0];

        if (file) {
            // ── Task 12.5 — Document translation ──────────────────────────────
            setTranslating(true);

            var formData = new FormData();
            formData.append('source_lang', sourceLang.value);
            formData.append('target_lang', targetLang.value);
            formData.append('document', file);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            // Task 5.3 — include pdf_column_mode only for PDF files
            var ext = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();
            if (ext === '.pdf') {
                formData.append('pdf_column_mode', pdfColumnMode.value);
            }

            fetch('/translate', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(function (response) {
                // Task 6.1 — read as text first, then attempt JSON parse
                return response.text().then(function (rawText) {
                    var data = null;
                    try { data = JSON.parse(rawText); } catch (e) { /* not JSON */ }

                    if (response.ok && data && data.download_url) {
                        // Document translation success — show a proper download button
                        var friendlyName = data.download_filename || 'translated_document';

                        outputText.textContent = '';
                        outputDownload.removeAttribute('hidden');
                        downloadLink.href = data.download_url;
                        downloadLink.download = friendlyName;
                        downloadLink.textContent = 'Download: ' + friendlyName;

                        if (data.signed_url_expires_at) {
                            var expiresAt = new Date(data.signed_url_expires_at);
                            var expiryNote = document.createElement('small');
                            expiryNote.textContent = 'Link expires: ' + expiresAt.toUTCString();
                            expiryNote.style.display = 'block';
                            outputDownload.appendChild(expiryNote);
                        }

                        copyBtn.setAttribute('aria-disabled', 'true');
                        saveBtn.setAttribute('aria-disabled', 'true');
                    } else if (response.status === 422 && data && data.errors) {
                        var firstField = Object.keys(data.errors)[0];
                        showError(sourcePanel, data.errors[firstField][0]);
                    } else if (data && (data.error || data.detail)) {
                        showError(outputPanel, data.error || data.detail);
                    } else {
                        showError(outputPanel, 'Error (' + response.status + '). Please try again.');
                    }
                });
            })
            .catch(function () {
                showError(outputPanel, 'Network error. Please try again.');
            })
            .finally(function () {
                setTranslating(false);
            });

        } else {
            // ── Task 12.4 — Text translation (AJAX) ───────────────────────────
            var text = sourceText.value;

            setTranslating(true);

            var formData = new FormData();
            formData.append('source_lang', sourceLang.value);
            formData.append('target_lang', targetLang.value);
            formData.append('text', text);

            fetch('/translate', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(function (response) {
                // Task 6.2 — read as text first, then attempt JSON parse
                return response.text().then(function (rawText) {
                    var data = null;
                    try { data = JSON.parse(rawText); } catch (e) { /* not JSON */ }

                    if (response.ok) {
                        // 200 — populate output and enable copy/save
                        outputText.textContent = data && data.translated ? data.translated : rawText;
                        copyBtn.removeAttribute('aria-disabled');
                        saveBtn.removeAttribute('aria-disabled');
                        // Hide download area (in case it was shown from a previous document translation)
                        outputDownload.setAttribute('hidden', '');
                    } else if (response.status === 422 && data && data.errors) {
                        // 422 — show first validation error in source panel
                        var firstField = Object.keys(data.errors)[0];
                        showError(sourcePanel, data.errors[firstField][0]);
                    } else if (data && (data.error || data.detail)) {
                        showError(outputPanel, data.error || data.detail);
                    } else {
                        showError(outputPanel, 'Error (' + response.status + '). Please try again.');
                    }
                });
            })
            .catch(function () {
                showError(outputPanel, 'Network error. Please try again.');
            })
            .finally(function () {
                setTranslating(false);
            });
        }
    });

    // ─── Task 12.6 — Copy and Save button actions ─────────────────────────────

    copyBtn.addEventListener('click', function () {
        // Do nothing if aria-disabled
        if (copyBtn.getAttribute('aria-disabled') === 'true') { return; }

        navigator.clipboard.writeText(outputText.textContent).then(function () {
            var original = copyBtn.textContent;
            copyBtn.textContent = 'Copied!';
            setTimeout(function () {
                copyBtn.textContent = original;
            }, 2000);
        }).catch(function () {
            showError(outputPanel, 'Failed to copy text to clipboard.');
        });
    });

    saveBtn.addEventListener('click', function () {
        // Do nothing if aria-disabled
        if (saveBtn.getAttribute('aria-disabled') === 'true') { return; }

        var now = new Date();
        var pad = function (n) { return String(n).padStart(2, '0'); };
        var timestamp =
            now.getUTCFullYear() +
            pad(now.getUTCMonth() + 1) +
            pad(now.getUTCDate()) +
            '_' +
            pad(now.getUTCHours()) +
            pad(now.getUTCMinutes()) +
            pad(now.getUTCSeconds());

        var blob = new Blob([outputText.textContent], { type: 'text/plain' });
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement('a');
        a.href     = url;
        a.download = 'translation_' + timestamp + '.txt';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });

    // ─── Speaker buttons (Text-to-Speech) ────────────────────────────────────
    // Hide buttons if the Web Speech API is unavailable in this browser.

    var speakSourceBtn = document.getElementById('speak-source-btn');
    var speakOutputBtn = document.getElementById('speak-output-btn');

    if (!window.speechSynthesis) {
        if (speakSourceBtn) { speakSourceBtn.style.display = 'none'; }
        if (speakOutputBtn) { speakOutputBtn.style.display = 'none'; }
    } else {
        speakSourceBtn.addEventListener('click', function () {
            window.speechSynthesis.cancel();
            var text = sourceText.value.trim();
            if (!text) { return; }
            var utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = sourceLang.value === 'English' ? 'en-US'
                           : sourceLang.value === 'Filipino' ? 'fil-PH'
                           : 'ceb'; // Cebuano
            window.speechSynthesis.speak(utterance);
        });

        speakOutputBtn.addEventListener('click', function () {
            window.speechSynthesis.cancel();
            var text = outputText.textContent.trim();
            if (!text) { return; }
            var utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = targetLang.value === 'English' ? 'en-US'
                           : targetLang.value === 'Filipino' ? 'fil-PH'
                           : 'ceb'; // Cebuano
            window.speechSynthesis.speak(utterance);
        });
    }

})();
</script>
@endsection
