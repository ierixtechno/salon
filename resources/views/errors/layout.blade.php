{{--
    Shared shell for every branded error page (resources/views/errors/{code}.blade.php).

    Deliberately standalone: inline CSS only — no @vite, no layout component, nothing
    that needs a session, the database, or a built asset. An error page that itself
    errors (a missing manifest, a broken session store) is worse than no error page.

    The reference ID is the request's correlation ID (AttachRequestId): quote it to
    support and the exact failure can be found in Platform > Error log, without the
    page ever showing a stack trace, SQL or file path (CLAUDE.md §40).
--}}
@php
    $requestId = request()->attributes->get('request_id');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} &middot; {{ $title }} &middot; {{ config('platform.brand_name', 'StyloBiz') }}</title>
    <link rel="icon" href="/favicon.ico">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center;
               background: #fdf2f8; color: #1f2937; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; padding: 1.5rem; }
        .card { background: #fff; border-radius: 1rem; padding: 2.25rem 2rem; max-width: 30rem; width: 100%; text-align: center;
                box-shadow: 0 10px 30px rgba(131, 24, 67, .10); }
        .code { font-size: 3.5rem; font-weight: 700; color: #ec4899; line-height: 1; margin: 0; }
        h1 { font-size: 1.25rem; margin: .75rem 0 .5rem; }
        p { margin: 0 0 1rem; color: #4b5563; line-height: 1.55; font-size: .95rem; }
        .actions { display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap; margin-top: 1.25rem; }
        a.btn, button.btn { display: inline-block; border: 0; cursor: pointer; padding: .6rem 1.1rem; border-radius: .5rem; font-size: .9rem;
                            font-weight: 600; text-decoration: none; background: #4f46e5; color: #fff; }
        a.btn.secondary { background: #f3f4f6; color: #374151; }
        .ref { margin-top: 1.5rem; font-size: .75rem; color: #9ca3af; word-break: break-all; }
        .ref code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: #6b7280; }
        footer { margin-top: 1.5rem; font-size: .75rem; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="card">
        <p class="code">{{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>

        <div class="actions">
            @if (! empty($retry))
                <button class="btn" type="button" onclick="location.reload()">Try again</button>
            @endif
            <a class="btn {{ ! empty($retry) ? 'secondary' : '' }}" href="{{ url('/') }}">Go to home</a>
        </div>

        @if ($requestId && ! empty($showReference))
            <p class="ref">If you contact support, please quote this reference:<br><code>{{ $requestId }}</code></p>
        @endif
    </div>
    <footer>&copy; {{ now()->year }} NexBiz Technology. All rights reserved.</footer>
</body>
</html>
