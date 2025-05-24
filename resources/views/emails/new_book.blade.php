<!doctype html>
<html lang="en-US">

<head>
    <meta charset="utf-8">
    <title>New Book Added</title>
    <style>
        body {
            margin: 0;
            background-color: #f2f3f8;
            font-family: 'Open Sans', sans-serif;
        }

        .container {
            max-width: 670px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
            padding: 40px;
            text-align: left;
        }

        .title {
            font-size: 26px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
        }

        .label {
            font-weight: bold;
            color: #444;
        }

        .value {
            color: #555;
            margin-bottom: 10px;
        }

        .footer {
            text-align: center;
            font-size: 13px;
            color: #888;
            margin-top: 30px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="title">📚 A New Book Has Been Added</div>

        <div class="value"><span class="label">Title:</span> {{ $title }}</div>
        <div class="value"><span class="label">Author:</span> {{ $author }}</div>
        <div class="value"><span class="label">ISBN:</span> {{ $isbn }}</div>
        <div class="value"><span class="label">Published Year:</span> {{ $publish_year }}</div>
        <div class="value"><span class="label">Pages:</span> {{ $pages_count }}</div>
        <div class="value"><span class="label">Language:</span> {{ $language }}</div>
        <div class="value"><span class="label">Description:</span><br> {{ $description }}</div>

        <div class="footer">&copy; {{ date('Y') }} YourLibrary.com — All rights reserved.</div>
    </div>
</body>

</html>
