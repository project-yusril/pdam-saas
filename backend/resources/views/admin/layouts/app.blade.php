<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin PDAM')</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: #f5f6fa; color: #1a1a2e; }
        nav { background: #16213e; color: #fff; padding: 1rem 2rem; display: flex; align-items: center; gap: 1rem; }
        nav a { color: #a8b2d8; text-decoration: none; font-size: .9rem; }
        nav a:hover { color: #fff; }
        nav .brand { font-weight: 700; font-size: 1.1rem; color: #fff; margin-right: auto; }
        main { max-width: 1200px; margin: 2rem auto; padding: 0 1.5rem; }
        h1 { font-size: 1.5rem; margin-bottom: 1.5rem; }
        h2 { font-size: 1.2rem; margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        th, td { padding: .75rem 1rem; text-align: left; border-bottom: 1px solid #e8eaf0; font-size: .9rem; }
        th { background: #f0f2f8; font-weight: 600; }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: .2rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 600; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-gray  { background: #e5e7eb; color: #374151; }
        .badge-blue  { background: #dbeafe; color: #1e40af; }
        .btn { display: inline-block; padding: .45rem 1rem; border-radius: 6px; font-size: .85rem; text-decoration: none; font-weight: 600; cursor: pointer; }
        .btn-primary { background: #4f46e5; color: #fff; }
        .btn-primary:hover { background: #4338ca; }
        .card { background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 1.5rem; margin-bottom: 1.5rem; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat { background: #fff; border-radius: 8px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,.07); }
        .stat .label { font-size: .78rem; color: #6b7280; margin-bottom: .3rem; }
        .stat .value { font-size: 1.4rem; font-weight: 700; color: #1a1a2e; }
    </style>
</head>
<body>
<nav>
    <span class="brand">PDAM Admin</span>
    <a href="{{ route('admin.zones.index') }}">Wilayah</a>
</nav>
<main>
    @yield('content')
</main>
</body>
</html>
