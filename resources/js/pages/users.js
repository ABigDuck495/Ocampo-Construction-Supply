/* ============================================================
   USERS - DATA + LOGIC
   Pulls real data from the Laravel backend.
   ============================================================ */

let users = [];
let activeRoleTab = 'all';

/* ---------------- FETCH ---------------- */
async function loadUsers(){
    try {
        const res = await fetch('/users', {
            headers: { 'Accept': 'application/json' },
        });
        if(!res.ok) throw new Error('Failed to load users');
        users = await res.json();
    } catch (err) {
        console.error(err);
        users = [];
    }
    renderUserStats();
    renderUserTable();
}

/* ---------------- HEADER STATS ---------------- */
function renderUserStats(){
    document.getElementById('statTotalUsers').textContent = users.length;
    document.getElementById('statActiveUsers').textContent = users.filter(u => u.status === 'active').length;
    document.getElementById('statAdmins').textContent = users.filter(u => u.role === 'admin').length;
}

/* ---------------- BADGES ---------------- */
function badgeForRole(role){
    return `<span class="badge role-${role}">${role.toUpperCase()}</span>`;
}
function badgeForStatus(status){
    return `<span class="badge status-${status}">${status.toUpperCase()}</span>`;
}
function initials(name){
    return name.split(' ').map(p => p[0]).join('').slice(0, 2).toUpperCase();
}

/* ---------------- TABLE ---------------- */
function renderUserTable(){
    const body = document.getElementById('usersBody');
    const filtered = activeRoleTab === 'all' ? users : users.filter(u => u.role === activeRoleTab);

    if(!filtered.length){
        body.innerHTML = `<tr><td colspan="6" class="empty-state">NO USERS IN THIS VIEW</td></tr>`;
        return;
    }

    body.innerHTML = filtered.map(u => `
        <tr data-user="${u.id}">
            <td>
                <div class="user-cell-name">
                    <div class="user-avatar">${initials(u.name)}</div>
                    <div class="user-name-block">
                        <div class="u-name">${u.name}</div>
                        <div class="u-email">${u.email ?? ''}</div>
                    </div>
                </div>
            </td>
            <td>${badgeForRole(u.role)}</td>
            <td>${badgeForStatus(u.status)}</td>
            <td class="cell-dim">${u.lastLogin ?? 'NEVER'}</td>
            <td class="cell-dim">&mdash;</td>
            <td>
                <div class="row-actions">
                    <button class="btn-ghost" data-view-activity="${u.id}">ACTIVITY</button>
                </div>
            </td>
        </tr>`).join('');

    body.querySelectorAll('[data-view-activity]').forEach(btn => {
        btn.addEventListener('click', () => openActivityModal(btn.dataset.viewActivity));
    });
}

/* ---------------- ACTIVITY MODAL ---------------- */
async function openActivityModal(userId){
    const user = users.find(u => String(u.id) === String(userId));
    if(!user) return;

    document.getElementById('activityHeadName').textContent = user.name;
    document.getElementById('activityHeadRole').textContent = `${user.role.toUpperCase()} · ${user.email ?? ''}`;

    const body = document.getElementById('activityBody');
    body.innerHTML = `<div class="empty-state">LOADING&hellip;</div>`;
    document.getElementById('activityOverlay').classList.add('open');

    try {
        const res = await fetch(`/users/${userId}/activity`, {
            headers: { 'Accept': 'application/json' },
        });
        if(!res.ok) throw new Error('Failed to load activity');
        const data = await res.json();

        body.innerHTML = `
            <div class="activity-entry">
                <div class="activity-entry-top">
                    <span class="activity-entry-action">Orders Created</span>
                    <span class="activity-entry-time">${data.ordersCreated}</span>
                </div>
            </div>
            <div class="activity-entry">
                <div class="activity-entry-top">
                    <span class="activity-entry-action">Transactions Processed</span>
                    <span class="activity-entry-time">${data.transactionsProcessed}</span>
                </div>
            </div>`;
    } catch (err) {
        console.error(err);
        body.innerHTML = `<div class="empty-state">FAILED TO LOAD ACTIVITY</div>`;
    }
}

document.getElementById('activityCloseBtn').addEventListener('click', () => {
    document.getElementById('activityOverlay').classList.remove('open');
});
document.getElementById('activityOverlay').addEventListener('click', e => {
    if(e.target.id === 'activityOverlay') e.target.classList.remove('open');
});

/* ---------------- ROLE TABS ---------------- */
document.getElementById('userTabs').addEventListener('click', e => {
    const tab = e.target.closest('.tab');
    if(!tab) return;
    activeRoleTab = tab.dataset.tab;
    document.querySelectorAll('#userTabs .tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    renderUserTable();
});

/* ---------------- INIT ---------------- */
loadUsers();