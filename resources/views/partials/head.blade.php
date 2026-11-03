<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' — TALMA Media' : 'TALMA Media' }}
</title>

<link rel="icon" href="{{ asset('brand/blue-a.png') }}" type="image/png">
<link rel="apple-touch-icon" href="{{ asset('brand/blue-a.png') }}">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
