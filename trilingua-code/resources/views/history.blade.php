@extends('layouts.app')

@section('title', 'Translation History')

@section('styles')
    @vite(['resources/css/views/history.css'])
@endsection

@section('content')
<div class="stack">

    @if ($error)
        <p class="error-message">Unable to load history. Please try again later.</p>
    @elseif (empty($records))
        <p class="empty-state">You have no translation history yet.</p>
    @else
        <div class="table-card">
            <h2 class="card-title">Translation History</h2>
            <div style="margin-top:12px;overflow-x:auto">
                <table class="table">
                    <thead style="color:var(--muted);font-size:0.78rem;text-transform:uppercase">
                        <tr>
                            <th>Type</th>
                            <th>Content</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Date (UTC)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $record)
                            @php $isDoc = ($record['translation_type'] ?? 'document') === 'document'; @endphp
                            <tr class="border-top">
                                <td>
                                    <span class="type-badge type-badge--{{ $isDoc ? 'doc' : 'text' }}">
                                        {{ $isDoc ? 'Document' : 'Text' }}
                                    </span>
                                </td>
                                <td class="content-cell">
                                    @if ($isDoc)
                                        <span title="{{ $record['original_filename'] }}">{{ $record['original_filename'] }}</span>
                                        <span style="color:var(--muted);font-size:0.8rem;display:block">→ {{ $record['translated_filename'] }}</span>
                                    @else
                                        <span class="text-preview" title="{{ $record['source_text'] }}">
                                            {{ \Illuminate\Support\Str::limit($record['source_text'] ?? '', 60) }}
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $record['source_language'] }}</td>
                                <td>{{ $record['target_language'] }}</td>
                                <td>{{ \Carbon\Carbon::parse($record['created_at'])->utc()->format('Y-m-d H:i') }} UTC</td>
                                <td>
                                    @if ($isDoc)
                                        <button class="btn secondary redownload-btn"
                                                data-id="{{ $record['id'] }}"
                                                data-filename="{{ $record['translated_filename'] }}">
                                            Re-download
                                        </button>
                                    @else
                                        <button class="btn secondary view-text-btn"
                                                data-source="{{ htmlspecialchars($record['source_text'] ?? '', ENT_QUOTES) }}"
                                                data-translated="{{ htmlspecialchars($record['translated_text'] ?? '', ENT_QUOTES) }}"
                                                data-from="{{ $record['source_language'] }}"
                                                data-to="{{ $record['target_language'] }}">
                                            View
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div id="redownload-error" class="error-message" style="display:none"></div>

</div>

{{-- Text translation modal --}}
<div id="text-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--card-bg);border-radius:12px;padding:28px;max-width:640px;width:90%;max-height:80vh;overflow-y:auto;position:relative">
        <button id="text-modal-close" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--muted)">&times;</button>
        <p style="font-size:0.75rem;text-transform:uppercase;color:var(--muted);margin-bottom:4px" id="modal-langs"></p>
        <h3 style="margin:0 0 12px">Original</h3>
        <p id="modal-source" style="white-space:pre-wrap;background:var(--input-bg,#f8f9fa);padding:12px;border-radius:8px;font-size:0.9rem"></p>
        <h3 style="margin:16px 0 12px">Translation</h3>
        <p id="modal-translated" style="white-space:pre-wrap;background:var(--input-bg,#f8f9fa);padding:12px;border-radius:8px;font-size:0.9rem"></p>
    </div>
</div>

<script>
(function () {
    'use strict';

    // ── Re-download ──────────────────────────────────────────────────────────
    var errorEl = document.getElementById('redownload-error');

    function showError(message) { errorEl.textContent = message; errorEl.style.display = ''; }
    function clearError()       { errorEl.textContent = ''; errorEl.style.display = 'none'; }

    document.querySelectorAll('.redownload-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            clearError();
            var id        = btn.getAttribute('data-id');
            var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            btn.disabled    = true;
            var orig        = btn.textContent;
            btn.textContent = 'Loading...';

            fetch('/history/redownload/' + encodeURIComponent(id), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' }
            })
            .then(function (response) {
                return response.text().then(function (raw) {
                    var data = null;
                    try { data = JSON.parse(raw); } catch (e) {}
                    if (response.ok && data && data.download_url) {
                        window.location.href = data.download_url;
                    } else {
                        showError((data && data.error) || 'Unable to generate download link. Please try again later.');
                    }
                });
            })
            .catch(function () { showError('Network error. Please try again.'); })
            .finally(function () { btn.disabled = false; btn.textContent = orig; });
        });
    });

    // ── Text modal ───────────────────────────────────────────────────────────
    var modal      = document.getElementById('text-modal');
    var modalClose = document.getElementById('text-modal-close');
    var modalLangs = document.getElementById('modal-langs');
    var modalSrc   = document.getElementById('modal-source');
    var modalTrans = document.getElementById('modal-translated');

    document.querySelectorAll('.view-text-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            modalLangs.textContent  = btn.getAttribute('data-from') + ' → ' + btn.getAttribute('data-to');
            modalSrc.textContent    = btn.getAttribute('data-source');
            modalTrans.textContent  = btn.getAttribute('data-translated');
            modal.style.display     = 'flex';
        });
    });

    modalClose.addEventListener('click', function () { modal.style.display = 'none'; });
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });

})();
</script>
@endsection
