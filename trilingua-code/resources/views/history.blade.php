@extends('layouts.app')

@section('title', 'Translation History')

@section('styles')
    @vite(['resources/css/views/history.css'])
@endsection

@section('content')
<div class="stack">

    @if ($error)
        <p class="error-message">Unable to load history. Please try again later.</p>
    @else

    {{-- Page header --}}
    <div class="history-page-header">
        <div class="history-page-header__title">
            <h2>Saved Translations <span class="count-badge">{{ count($records) }}</span></h2>
        </div>
        <div class="history-page-header__controls">
            <input type="search" id="history-search" class="history-search" placeholder="Search translations…" aria-label="Search translations">
            <select id="history-group" class="history-select" aria-label="Group by">
                <option value="language">Group by Language Pair</option>
                <option value="none">No Grouping</option>
            </select>
            <select id="history-sort" class="history-select" aria-label="Sort order">
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
                <option value="lang-az">Language A–Z</option>
            </select>
        </div>
    </div>

    @if (empty($records))
        <p class="empty-state">You have no translation history yet.</p>
    @else

    @php
        // Group records by language pair
        $grouped = [];
        foreach ($records as $r) {
            $key = ($r['source_language'] ?? '?') . ' → ' . ($r['target_language'] ?? '?');
            $grouped[$key][] = $r;
        }
    @endphp

    {{-- Grouped card sections --}}
    <div id="history-content">
        @foreach ($grouped as $langPair => $groupRecords)
        <section class="history-group" data-lang-pair="{{ $langPair }}">
            <h3 class="history-group__title">
                {{ $langPair }}
                <span class="count-badge">{{ count($groupRecords) }}</span>
            </h3>
            <div class="history-cards">
                @foreach ($groupRecords as $record)
                @php
                    $isDoc    = ($record['translation_type'] ?? 'document') === 'document';
                    $matchPct = $isDoc
                        ? 100
                        : min(100, (int)(
                            mb_strlen($record['translated_text'] ?? '')
                            / max(1, mb_strlen($record['source_text'] ?? ''))
                            * 100
                          ));
                    $preview  = $isDoc
                        ? ($record['original_filename'] ?? 'Document')
                        : \Illuminate\Support\Str::limit($record['source_text'] ?? '', 80);
                    $dateStr  = \Carbon\Carbon::parse($record['created_at'])->utc()->format('Y-m-d H:i') . ' UTC';
                @endphp
                <div class="history-card"
                     data-search="{{ strtolower($preview . ' ' . ($record['source_language'] ?? '') . ' ' . ($record['target_language'] ?? '')) }}"
                     data-date="{{ $record['created_at'] ?? '' }}"
                     data-lang="{{ $langPair }}">

                    <div class="history-card__header">
                        <span class="match-badge">{{ $matchPct }}%</span>
                        <span class="type-badge type-badge--{{ $isDoc ? 'doc' : 'text' }}">
                            {{ $isDoc ? 'Document' : 'Text' }}
                        </span>
                    </div>

                    <div class="history-card__body">
                        <p class="history-card__preview" title="{{ $preview }}">{{ $preview }}</p>
                        @if ($isDoc)
                            <p class="history-card__sub">→ {{ $record['translated_filename'] ?? '' }}</p>
                        @endif
                    </div>

                    <div class="history-card__footer">
                        <span class="history-card__date">{{ $dateStr }}</span>
                        <div class="history-card__actions">
                            {{-- Copy button (text records) --}}
                            @if (!$isDoc)
                            <button class="history-card__action-btn view-text-btn"
                                    title="View translation"
                                    data-source="{{ htmlspecialchars($record['source_text'] ?? '', ENT_QUOTES) }}"
                                    data-translated="{{ htmlspecialchars($record['translated_text'] ?? '', ENT_QUOTES) }}"
                                    data-from="{{ $record['source_language'] ?? '' }}"
                                    data-to="{{ $record['target_language'] ?? '' }}"
                                    aria-label="View translation">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                            <button class="history-card__action-btn copy-text-btn"
                                    title="Copy translation"
                                    data-text="{{ htmlspecialchars($record['translated_text'] ?? '', ENT_QUOTES) }}"
                                    aria-label="Copy translation">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            </button>
                            @endif

                            {{-- Download button --}}
                            @if ($isDoc)
                            <button class="history-card__action-btn redownload-btn"
                                    title="Re-download"
                                    data-id="{{ $record['id'] }}"
                                    data-filename="{{ $record['translated_filename'] ?? '' }}"
                                    aria-label="Re-download document">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            </button>
                            @else
                            <button class="history-card__action-btn download-text-btn"
                                    title="Save as text"
                                    data-text="{{ htmlspecialchars($record['translated_text'] ?? '', ENT_QUOTES) }}"
                                    data-filename="translation-{{ $record['id'] ?? 'export' }}.txt"
                                    aria-label="Save translation as text file">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            </button>
                            @endif

                            {{-- Share button --}}
                            <button class="history-card__action-btn share-btn"
                                    title="Share"
                                    aria-label="Share translation">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                            </button>

                            {{-- Bookmark button --}}
                            <button class="history-card__action-btn bookmark-btn"
                                    title="Bookmark"
                                    aria-label="Bookmark translation">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </section>
        @endforeach
    </div>

    @endif {{-- empty($records) --}}
    @endif {{-- $error --}}

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
            var orig        = btn.innerHTML;
            btn.textContent = 'Loading…';

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
            .finally(function () { btn.disabled = false; btn.innerHTML = orig; });
        });
    });

    // ── Copy text ────────────────────────────────────────────────────────────
    document.querySelectorAll('.copy-text-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-text');
            if (navigator.clipboard && text) {
                navigator.clipboard.writeText(text).catch(function () {});
            }
        });
    });

    // ── Download as .txt ─────────────────────────────────────────────────────
    document.querySelectorAll('.download-text-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text     = btn.getAttribute('data-text') || '';
            var filename = btn.getAttribute('data-filename') || 'translation.txt';
            var blob     = new Blob([text], { type: 'text/plain' });
            var url      = URL.createObjectURL(blob);
            var a        = document.createElement('a');
            a.href       = url;
            a.download   = filename;
            a.click();
            URL.revokeObjectURL(url);
        });
    });

    // ── Share (copy page URL to clipboard) ───────────────────────────────────
    document.querySelectorAll('.share-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(window.location.href).catch(function () {});
            }
        });
    });

    // ── Bookmark (visual toggle only) ────────────────────────────────────────
    document.querySelectorAll('.bookmark-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.classList.toggle('bookmark-btn--active');
            var svg = btn.querySelector('svg');
            if (svg) {
                svg.style.fill = btn.classList.contains('bookmark-btn--active') ? 'currentColor' : 'none';
            }
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

    if (modalClose) {
        modalClose.addEventListener('click', function () { modal.style.display = 'none'; });
    }
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });

    // ── Client-side search, group-by, and sort ───────────────────────────────
    var searchInput  = document.getElementById('history-search');
    var groupSelect  = document.getElementById('history-group');
    var sortSelect   = document.getElementById('history-sort');
    var contentEl    = document.getElementById('history-content');

    if (!contentEl) return; // no records — nothing to filter

    // Collect all cards and groups for manipulation
    function getAllCards() {
        return Array.from(contentEl.querySelectorAll('.history-card'));
    }

    function getAllGroups() {
        return Array.from(contentEl.querySelectorAll('.history-group'));
    }

    // Apply search filter: hide cards whose data-search doesn't match query
    function applySearch(query) {
        var q = query.trim().toLowerCase();
        getAllCards().forEach(function (card) {
            var haystack = (card.getAttribute('data-search') || '').toLowerCase();
            card.style.display = (!q || haystack.indexOf(q) !== -1) ? '' : 'none';
        });
        // Hide groups that have no visible cards
        getAllGroups().forEach(function (group) {
            var visibleCards = group.querySelectorAll('.history-card:not([style*="display: none"])');
            group.style.display = visibleCards.length > 0 ? '' : 'none';
        });
    }

    // Apply group-by toggle: when "none", flatten all cards into a single pseudo-group
    function applyGroupBy(mode) {
        var groups = getAllGroups();
        if (mode === 'none') {
            // Show all group headings hidden, merge visually by hiding h3
            groups.forEach(function (g) {
                var heading = g.querySelector('.history-group__title');
                if (heading) heading.style.display = 'none';
            });
        } else {
            groups.forEach(function (g) {
                var heading = g.querySelector('.history-group__title');
                if (heading) heading.style.display = '';
            });
        }
    }

    // Apply sort: reorder cards within each group (or across all if no grouping)
    function applySort(order) {
        getAllGroups().forEach(function (group) {
            var grid  = group.querySelector('.history-cards');
            if (!grid) return;
            var cards = Array.from(grid.querySelectorAll('.history-card'));

            cards.sort(function (a, b) {
                if (order === 'newest' || order === 'oldest') {
                    var da = new Date(a.getAttribute('data-date') || 0);
                    var db = new Date(b.getAttribute('data-date') || 0);
                    return order === 'newest' ? db - da : da - db;
                } else if (order === 'lang-az') {
                    var la = (a.getAttribute('data-lang') || '').toLowerCase();
                    var lb = (b.getAttribute('data-lang') || '').toLowerCase();
                    return la < lb ? -1 : la > lb ? 1 : 0;
                }
                return 0;
            });

            cards.forEach(function (card) { grid.appendChild(card); });
        });
    }

    function applyAll() {
        applySearch(searchInput ? searchInput.value : '');
        applyGroupBy(groupSelect ? groupSelect.value : 'language');
        applySort(sortSelect ? sortSelect.value : 'newest');
    }

    if (searchInput) searchInput.addEventListener('input', applyAll);
    if (groupSelect) groupSelect.addEventListener('change', applyAll);
    if (sortSelect)  sortSelect.addEventListener('change', applyAll);

    // Initial sort (newest first by default)
    applyAll();

})();
</script>
@endsection
