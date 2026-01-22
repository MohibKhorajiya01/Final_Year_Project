<style>
    :root {
        --primary: #5a2ca0;
        --primary-dark: #431f75;
        --accent: #ffb347;
        --bg: #f5f3ff;
    }
    * {
        box-sizing: border-box;
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }
    body {
        margin: 0;
        background: var(--bg);
        min-height: 100vh;
    }
    .sidebar {
        width: 250px;
        background: #fff;
        box-shadow: 4px 0 20px rgba(0,0,0,0.05);
        padding: 24px 20px;
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        overflow-y: auto;
    }
    .brand {
        font-size: 22px;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 32px;
    }
    .nav-link-custom {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        border-radius: 12px;
        color: #6c6c6c;
        text-decoration: none;
        margin-bottom: 8px;
        transition: background 0.2s, color 0.2s;
    }
    .nav-link-custom.active {
        background: rgba(90,44,160,0.1);
        color: var(--primary);
    }
    .nav-link-custom:hover {
        background: rgba(90,44,160,0.1);
        color: var(--primary);
    }
    .main-content {
        margin-left: 250px;
        padding: 30px 40px;
        min-height: 100vh;
    }
    .top-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    @media (max-width: 992px) {
        .sidebar {
            position: relative;
            width: 100%;
            height: auto;
        }
        .main-content {
            margin-left: 0;
            padding: 20px;
        }
    }
</style>

