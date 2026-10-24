{{--
    Deliberately plain: whatever broke may be the database or the record files,
    so this page must not depend on either. No blocks, no config lookups beyond
    the site name, no queries.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Something went wrong</title>
    <style>
        body { margin: 0; display: grid; min-height: 100vh; place-items: center;
               background: #f8f5f1; color: #0d0d0d; font-family: system-ui, sans-serif; }
        main { max-width: 32rem; padding: 2rem; text-align: center; }
        h1 { margin: 0 0 1rem; font-size: 1.75rem; line-height: 1.2; }
        p { margin: 0 0 1.5rem; color: #4a4a4a; line-height: 1.6; }
        a { color: #0d0d0d; font-weight: 700; }
    </style>
</head>
<body>
    <main>
        <h1>Something went wrong at our end.</h1>
        <p>
            This is our problem, not yours, and we have been told about it.
            Please try again in a few minutes.
        </p>
        <p><a href="/">Back to the homepage</a></p>
    </main>
</body>
</html>
