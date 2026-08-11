/* ============================================================
   USERS - DATA + LOGIC
   Mock data below mirrors the shape used in deliveries.js/pos.js.
   Swap for real data from your Laravel User model once wired up.
   ============================================================ */

let users = [
    { id:'U001', name:'Ben Santos',   email:'ben.santos@ironclad.ph',   role:'driver', status:'active',   lastLogin:'Jul 23, 2026, 8:02 AM',
      activity:[
        { action:'Logged in', time:'Jul 23, 2026, 8:02 AM', detail:'Web app · Manila' },
        { action:'Dispatched truck Ironclad 01', time:'Jul 23, 2026, 9:10 AM', detail:'2 orders assigned' },
      ] },
    { id:'U002', name:'Mia Cruz',     email:'mia.cruz@ironclad.ph',     role:'driver', status:'active',   lastLogin:'Jul 22, 2026, 1:15 PM',
      activity:[
        { action:'Marked order delivered', time:'Jul 22, 2026, 2:55 PM', detail:'RC-041956' },
        { action:'Logged in', time:'Jul 22, 2026, 1:15 PM', detail:'Web app · Manila' },
      ] },
    { id:'U003', name:'Jake Reyes',   email:'jake.reyes@ironclad.ph',   role:'driver', status:'inactive', lastLogin:'Jul 15, 2026, 10:40 AM',
      activity:[
        { action:'Logged in', time:'Jul 15, 2026, 10:40 AM', detail:'Web app · Manila' },
      ] },
    { id:'U004', name:'Shan Ocampo',  email:'shan@ironclad.ph',         role:'admin',  status:'active',   lastLogin:'Jul 23, 2026, 7:45 AM',
      activity:[
        { action:'Updated inventory stock levels', time:'Jul 23, 2026, 7:50 AM', detail:'12 items adjusted' },
        { action:'Logged in', time:'Jul 23, 2026, 7:45 AM', detail:'Web app · Manila' },
      ] },
    { id:'U005', name:'Lea Fernandez',email:'lea.fernandez@ironclad.ph',role:'staff',  status:'active',   lastLogin:'Jul 23, 2026, 8:30 AM',
      activity:[
        { action:'Processed checkout', time:'Jul 23, 2026, 8:55 AM', detail:'Order RC-041823' },
        { action:'Logged in', time:'Jul 23, 2026, 8:30 AM', detail:'Web app · Manila' },
      ] },
    { id:'U006', name:'Noel Ramos',   email:'noel.ramos@ironclad.ph',   role:'staff',  status:'inactive', lastLogin:'Jul 10, 2026, 4:20 PM',
      activity:[
        { action:'Logged in', time:'Jul 10, 2026, 4:20 PM', detail:'Web app · Manila' },
      ] },
];

let activeRoleTab = 'all';

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
                        <div class="u-email">${u.email}</div>
                    </div>
                </div>
            </td>
            <td>${badgeForRole(u.role)}</td>
            <td>${badgeForStatus(u.status)}</td>
            <td class="cell-dim">${u.lastLogin}</td>
            <td class="cell-dim">${u.activity.length} entr${u.activity.length === 1 ? 'y' : 'ies'}</td>
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
function openActivityModal(userId){
    const user = users.find(u => u.id === userId);
    if(!user) return;

    document.getElementById('activityHeadName').textContent = user.name;
    document.getElementById('activityHeadRole').textContent = `${user.role.toUpperCase()} &middot; ${user.email}`.replace('&middot;', '·');

    const body = document.getElementById('activityBody');
    body.innerHTML = user.activity.length
        ? user.activity.map(a => `
            <div class="activity-entry">
                <div class="activity-entry-top">
                    <span class="activity-entry-action">${a.action}</span>
                    <span class="activity-entry-time">${a.time}</span>
                </div>
                <div class="activity-entry-detail">${a.detail}</div>
            </div>`).join('')
        : `<div class="empty-state">NO ACTIVITY RECORDED</div>`;

    document.getElementById('activityOverlay').classList.add('open');
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
renderUserStats();
renderUserTable();
