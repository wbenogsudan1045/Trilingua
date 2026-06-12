@extends('layouts.app')

@section('title','Dashboard')

@section('styles')
    @vite(['resources/css/views/dashboard.css'])
@endsection

@section('content')
    <div class="stack">

        {{-- Error banner --}}
        @if ($error ?? false)
            <div class="alert alert-danger" role="alert" style="background:var(--danger,#fee2e2);color:#991b1b;border:1px solid #fca5a5;border-radius:var(--radius,6px);padding:12px 16px;margin-bottom:8px">
                Unable to load your translation data. Please try refreshing the page.
            </div>
        @endif

        {{-- Greeting --}}
        <h1 class="welcome-greeting">Welcome back, {{ auth()->user()->name ?? 'User' }}</h1>

        {{-- Stat cards --}}
        <div class="cards-grid">
            <div class="stat-card">
                <div class="text-sm" style="color:var(--muted)">Total Documents</div>
                <div style="font-size:1.5rem;font-weight:600;margin-top:8px">{{ $stats['totalDocs'] }}</div>
            </div>
            <div class="stat-card">
                <div class="text-sm" style="color:var(--muted)">Translations This Month</div>
                <div style="font-size:1.5rem;font-weight:600;margin-top:8px">{{ $stats['translationsThisMonth'] }}</div>
            </div>
            <div class="stat-card">
                <div class="text-sm" style="color:var(--muted)">Words Translated</div>
                <div style="font-size:1.5rem;font-weight:600;margin-top:8px">{{ number_format($stats['wordsTranslated']) }}</div>
            </div>
        </div>

        {{-- Quick Actions panel --}}
        <div class="quick-actions">
            <a href="{{ route('translate') }}" class="quick-action-card">
                <span class="quick-action-card__icon">&#9998;</span>
                <span class="quick-action-card__label">New Translation</span>
            </a>
            <a href="{{ route('translate') }}" class="quick-action-card">
                <span class="quick-action-card__icon">&#8679;</span>
                <span class="quick-action-card__label">Upload Document</span>
            </a>
        </div>

        {{-- Recent Translations table --}}
        <div class="table-card">
            <h2 class="card-title">Recent Translations</h2>
            <div style="margin-top:12px;overflow-x:auto">
                <table class="table">
                    <thead style="color:var(--muted);font-size:0.78rem;text-transform:uppercase">
                        <tr>
                            <th>Document</th>
                            <th>Languages</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentRecords as $record)
                            <tr class="border-top" style="cursor:pointer" onclick="window.location='{{ route('history') }}'">
                                <td>
                                    @if (($record['translation_type'] ?? 'document') === 'document')
                                        {{ $record['original_filename'] ?? 'Untitled Document' }}
                                    @else
                                        {{ \Illuminate\Support\Str::limit($record['source_text'] ?? '', 40) }}
                                    @endif
                                </td>
                                <td>
                                    {{ $record['source_language'] ?? '?' }} &rarr; {{ $record['target_language'] ?? '?' }}
                                </td>
                                <td>
                                    {{ isset($record['created_at']) ? \Carbon\Carbon::parse($record['created_at'])->format('M j, Y') : '—' }}
                                </td>
                                <td>
                                    @if (($record['translation_type'] ?? 'document') === 'document')
                                        <span class="recent-table__status recent-table__status--completed">Completed</span>
                                    @else
                                        <span class="recent-table__status recent-table__status--text">Text</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="border-top">
                                <td colspan="4" style="text-align:center;color:var(--muted);padding:24px 0">
                                    No recent translations
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection
