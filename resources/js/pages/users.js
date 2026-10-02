/* ============================================================
   USERS - DATA + LOGIC
   Pulls real data from the Laravel backend.
   ============================================================ */

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

let users = [];
let activeRoleTab = 'all';

function escapeHtml(value = '') {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

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

function renderUserStats(){
    document.getElementById('statTotalUsers').textContent = users.length;
    document.getElementById('statActiveUsers').textContent = users.filter(u => u.status === 'active').length;
    document.getElementById('statAdmins').textContent = users.filter(u => u.role === 'admin').length;
}

function badgeForRole(role){
    const safeRole = (role || 'staff').toLowerCase();
    return `<span class="badge role-${safeRole}">${safeRole.toUpperCase()}</span>`;
}

function badgeForStatus(status){
    const safeStatus = (status || 'active').toLowerCase();
    return `<span class="badge status-${safeStatus}">${safeStatus.toUpperCase()}</span>`;
}

function initials(name){
    const source = (name || '').trim();
    if(!source) return 'U';
    return source.split(/\s+/).filter(Boolean).map(p => p[0]).join('').slice(0, 2).toUpperCase();
}

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
                        <div class="u-name">${escapeHtml(u.name || 'Unknown User')}</div>
                        <div class="u-email">${escapeHtml(u.email ?? '')}</div>
                    </div>
                </div>
            </td>
            <td>${badgeForRole(u.role)}</td>
            <td>${badgeForStatus(u.status)}</td>
            <td class="cell-dim">${escapeHtml(u.lastLogin ?? 'NEVER')}</td>
            <td class="cell-dim">&mdash;</td>
            <td>
                <div class="row-actions">
                    <button class="btn-ghost" data-view-activity="${u.id}">ACTIVITY</button>
                    <button class="btn-ghost" data-edit-user="${u.id}">EDIT</button>
                    <button class="btn-ghost danger" data-delete-user="${u.id}">DELETE</button>
                </div>
            </td>
        </tr>`).join('');

    body.querySelectorAll('[data-view-activity]').forEach(btn => {
        btn.addEventListener('click', () => openActivityModal(btn.dataset.viewActivity));
    });

    body.querySelectorAll('[data-edit-user]').forEach(btn => {
        btn.addEventListener('click', () => openUserModal('edit', users.find(u => String(u.id) === String(btn.dataset.editUser))));
    });

    body.querySelectorAll('[data-delete-user]').forEach(btn => {
        btn.addEventListener('click', () => deleteUser(btn.dataset.deleteUser));
    });
}

async function openActivityModal(userId){
    const user = users.find(u => String(u.id) === String(userId));
    if(!user) return;

    document.getElementById('activityHeadName').textContent = user.name;
    document.getElementById('activityHeadRole').textContent = `${(user.role || 'staff').toUpperCase()} · ${user.email ?? ''}`;

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

function openUserModal(mode = 'create', user = null){
    const isEdit = mode === 'edit' && !!user;
    const overlay = document.createElement('div');
    overlay.className = 'user-modal-overlay';
    overlay.innerHTML = `
        <div class="user-modal" role="dialog" aria-modal="true" aria-labelledby="userModalTitle">
            <div class="user-modal-head">
                <div id="userModalTitle">${isEdit ? 'EDIT USER' : 'ADD USER'}</div>
                <button type="button" class="user-modal-close" aria-label="Close user modal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>
            <form id="userForm" class="user-modal-body">
                <div class="user-field">
                    <label for="userName">NAME</label>
                    <input id="userName" name="Name" type="text" value="${escapeHtml(user?.name ?? '')}" required>
                </div>

                <div class="user-field">
                    <label for="userUsername">USERNAME</label>
                    <input id="userUsername" name="Username" type="text" value="${escapeHtml(user?.username ?? '')}" required>
                </div>

                <div class="user-field">
                    <label for="userEmail">EMAIL</label>
                    <input id="userEmail" name="Email" type="email" value="${escapeHtml(user?.email ?? '')}">
                </div>

                <div class="user-field">
                    <label for="userPhone">PHONE NUMBER</label>
                    <input id="userPhone" name="PhoneNumber" type="text" value="${escapeHtml(user?.phoneNumber ?? user?.phone ?? '')}">
                </div>

                <div class="user-field">
                    <label for="userRole">ROLE</label>
                    <select id="userRole" name="Role">
                        <option value="Admin" ${((user?.role || 'staff') === 'admin') ? 'selected' : ''}>Admin</option>
                        <option value="Staff" ${((user?.role || 'staff') === 'staff') ? 'selected' : ''}>Staff</option>
                        <option value="Driver" ${((user?.role || 'staff') === 'driver') ? 'selected' : ''}>Driver</option>
                    </select>
                </div>

                <div class="user-field">
                    <label for="userStatus">STATUS</label>
                    <select id="userStatus" name="Status">
                        <option value="Active" ${(user?.status || 'active') === 'active' ? 'selected' : ''}>Active</option>
                        <option value="Inactive" ${(user?.status || 'active') === 'inactive' ? 'selected' : ''}>Inactive</option>
                    </select>
                </div>

                <div class="user-field">
                    <label for="userPassword">${isEdit ? 'NEW PASSWORD' : 'PASSWORD'}</label>
                    <input id="userPassword" name="Password" type="password" autocomplete="new-password" ${isEdit ? '' : 'required'}>
                    ${isEdit ? '<small class="user-helper">Leave blank to keep the current password.</small>' : ''}
                </div>

                <div class="user-modal-actions">
                    <button type="button" class="btn-ghost" id="cancelUserBtn">CANCEL</button>
                    <button type="submit" class="btn-add-user">${isEdit ? 'SAVE CHANGES' : 'CREATE USER'}</button>
                </div>
            </form>
        </div>
    `;

    document.body.appendChild(overlay);

    const close = () => overlay.remove();
    overlay.querySelector('.user-modal-close').addEventListener('click', close);
    overlay.querySelector('#cancelUserBtn').addEventListener('click', close);
    overlay.addEventListener('click', event => {
        if (event.target === overlay) close();
    });

    document.getElementById('userForm').addEventListener('submit', async (event) => {
        event.preventDefault();

        const form = event.currentTarget;
        const formData = new FormData(form);
        const payload = {
            Name: String(formData.get('Name') || '').trim(),
            Username: String(formData.get('Username') || '').trim(),
            Email: String(formData.get('Email') || '').trim(),
            PhoneNumber: String(formData.get('PhoneNumber') || '').trim(),
            Role: String(formData.get('Role') || 'Staff'),
            Status: String(formData.get('Status') || 'Active'),
        };

        const password = String(formData.get('Password') || '').trim();
        if (password) payload.Password = password;

        if (!payload.Name) {
            alert('Please enter a user name.');
            return;
        }

        if (!payload.Username) {
            alert('Please enter a username.');
            return;
        }

        const method = isEdit ? 'PUT' : 'POST';
        const url = isEdit ? `/users/${user.id}` : '/users';

        try {
            const res = await fetch(url, {
                method,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: JSON.stringify(payload),
            });

            if (!res.ok) {
                const errorData = await res.json().catch(() => ({}));
                const errorMessage = (errorData.errors && Object.values(errorData.errors).flat().join(' ')) || errorData.message || 'Unable to save user.';
                throw new Error(errorMessage);
            }

            close();
            await loadUsers();
        } catch (err) {
            console.error(err);
            alert(err.message || 'Unable to save user.');
        }
    });
}

async function deleteUser(userId){
    const user = users.find(u => String(u.id) === String(userId));
    if (!user) return;

    const confirmed = confirm(`Delete ${user.name}? This action cannot be undone.`);
    if (!confirmed) return;

    try {
        const res = await fetch(`/users/${userId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
            },
        });

        if (!res.ok) {
            const errorData = await res.json().catch(() => ({}));
            const errorMessage = errorData.message || 'Unable to delete user.';
            throw new Error(errorMessage);
        }

        await loadUsers();
    } catch (err) {
        console.error(err);
        alert(err.message || 'Unable to delete user.');
    }
}

document.getElementById('activityCloseBtn').addEventListener('click', () => {
    document.getElementById('activityOverlay').classList.remove('open');
});

document.getElementById('activityOverlay').addEventListener('click', e => {
    if(e.target.id === 'activityOverlay') e.target.classList.remove('open');
});

document.getElementById('addUserBtn').addEventListener('click', () => openUserModal('create'));

document.getElementById('userTabs').addEventListener('click', e => {
    const tab = e.target.closest('.tab');
    if(!tab) return;
    activeRoleTab = tab.dataset.tab;
    document.querySelectorAll('#userTabs .tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    renderUserTable();
});

loadUsers();