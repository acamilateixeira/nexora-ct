<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#004b89">

<title>{{ isset($title) ? $title . ' · ' : '' }}camila / carteira</title>

<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='6' fill='%23004b89'/><text x='16' y='22' font-family='Arial' font-size='16' font-weight='700' fill='%23ffe500' text-anchor='middle'>ct</text></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap">

@vite(['resources/css/app.css', 'resources/js/app.js'])
