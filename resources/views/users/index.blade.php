<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Users - Ocampo Construction and Hardware Supplies</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Press+Start+2P&display=swap" rel="stylesheet">

<script>
    (function() {
        try {
            if (localStorage.getItem('theme') === 'light') {
                document.documentElement.classList.add('light-mode-pending');
            }
        } catch (e) {}
    })();
</script>
<!-- Shared stylesheets (same design system as POS / Delivery Ops / Reports) -->
@vite(['resources/css/deliveries.css', 'resources/css/sidebar.css', 'resources/css/reports.css', 'resources/css/users.css'])
    @include('partials.system_settings_js')
</head>
<body>
    @include('partials.sidebar')

<!-- ============================================================
     MAIN CONTENT (USERS)
     ============================================================ -->
<main class="main">
    <div class="page-toolbar">
    <div class="header">
        <div>
            <h1>USER MANAGEMENT</h1>
            <p>Manage staff accounts, roles &amp; access</p>
        </div>
        <div class="header-stats">
            <div class="hstat"><b id="statTotalUsers">0</b><span>USERS</span></div>
            <div class="hstat"><b id="statActiveUsers">0</b><span>ACTIVE</span></div>
            <div class="hstat"><b id="statAdmins">0</b><span>ADMINS</span></div>
        </div>
    </div>

    <div class="tabs" id="userTabs">
        <div class="tab active" data-tab="all">ALL</div>
        <div class="tab" data-tab="admin">ADMIN</div>
        <div class="tab" data-tab="staff">STAFF</div>
        <div class="tab" data-tab="driver">DRIVER</div>
    </div>
    </div>

    <div class="users-actions">
        <div class="hint">&middot; Click ACTIVITY to view a user's log &middot;</div>
        <div class="users-toolbar">
            <button type="button" class="btn-add-user" id="addUserBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                ADD USER
            </button>
        </div>
    </div>

    <div class="user-panel">
        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>NAME</th>
                        <th>ROLE</th>
                        <th>STATUS</th>
                        <th>LAST LOGIN</th>
                        <th>ACTIVITY</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="usersBody"></tbody>
            </table>
        </div>
    </div>
</main>

<!-- ============================================================
     ACTIVITY LOG MODAL
     ============================================================ -->
<div class="activity-overlay" id="activityOverlay">
    <div class="activity-modal">
        <div class="activity-head">
            <div>
                <div class="activity-head-name" id="activityHeadName">—</div>
                <div class="activity-head-role" id="activityHeadRole">—</div>
            </div>
            <button class="activity-close" id="activityCloseBtn" title="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        <div class="activity-body" id="activityBody"></div>
    </div>
</div>

<!-- Shared + page scripts -->
@vite(['resources/js/pages/sidebar.js', 'resources/js/pages/users.js'])
</body>
</html>
