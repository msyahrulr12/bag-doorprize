<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Locked | Bag Doorprize</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --bg: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg);
            color: var(--text-primary);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: radial-gradient(circle at top right, #1e1b4b, #0f172a);
        }

        .container {
            position: relative;
            z-index: 1;
            max-width: 500px;
            width: 90%;
            text-align: center;
            padding: 40px;
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .icon-wrapper {
            width: 80px;
            height: 80px;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: var(--primary);
        }

        h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 12px;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .btn {
            display: inline-block;
            background: var(--primary);
            color: white;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(99, 102, 241, 0.4);
        }

        .footer-note {
            margin-top: 24px;
            font-size: 0.875rem;
            color: #475569;
        }

        /* Decorative elements */
        .blob {
            position: absolute;
            width: 300px;
            height: 300px;
            background: var(--primary);
            filter: blur(80px);
            opacity: 0.15;
            border-radius: 50%;
            z-index: 0;
        }

        .blob-1 { top: -100px; right: -100px; }
        .blob-2 { bottom: -100px; left: -100px; }
    </style>
</head>
<body>
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div class="container">
        <div class="icon-wrapper">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        </div>
        <h1>System Locked</h1>
        <p>
            The application is currently locked to prioritize a heavy system process. 
            Only authorized administrators can access the system at this time.
        </p>
        
        @auth
            <form action="{{ route('filament.admin.auth.logout') }}" method="POST">
                @csrf
                <button type="submit" class="btn">Return to Login</button>
            </form>
        @else
            <a href="{{ route('filament.admin.auth.login') }}" class="btn">Authorized Login</a>
        @endauth

        <div class="footer-note">
            If you believe you should have access, please contact the system administrator.
        </div>
    </div>
</body>
</html>
