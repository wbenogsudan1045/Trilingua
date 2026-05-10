<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TriLingua</title>
    @vite(['resources/css/base.css', 'resources/css/views/welcome.css', 'resources/js/app.js'])
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <div class="guest-center">
        <div class="hero">
            <div class="left-hero">
                <h1>Translate with TriLingua</h1>
                <p>Fast, accurate translation between English, Cebuano, and Filipino.</p>
                <div class="actions">
                    <a href="{{ route('register') }}" class="btn primary">Get started</a>
                    <a href="{{ route('login') }}" class="btn secondary">Sign in</a>
                </div>
            </div>
            <div class="hero-right">
                <div class="icon-wrap">
                    <span class="icon-dot"></span>
                </div>
            </div>
        </div>

        <div class="features">
            <div class="feature-item">
                <div class="icon-wrap">
                    <span class="icon">✦</span>
                </div>
                <h3>Text Translation</h3>
                <p>Translate up to 8,000 characters instantly.</p>
            </div>
            <div class="feature-item">
                <div class="icon-wrap">
                    <span class="icon">📄</span>
                </div>
                <h3>Document Translation</h3>
                <p>Upload and translate DOCX, PDF, TXT, and more.</p>
            </div>
            <div class="feature-item">
                <div class="icon-wrap">
                    <span class="icon">🌐</span>
                </div>
                <h3>Three Languages</h3>
                <p>English, Cebuano, and Filipino — all supported.</p>
            </div>
        </div>
    </div>
</body>
</html>
