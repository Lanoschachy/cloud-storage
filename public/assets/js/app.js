/**
 * Main Application Hub
 * Handles page state, routing between Views (files, starred, recent, trash, activity, settings),
 * authentication states, theme toggling, and global search.
 */
const App = {
  currentView: 'files',
  currentFolderId: 'root',
  currentUser: null,
  // True while a modal form submit (rename/create) is awaiting API.
  // While busy: duplicate submits and Esc/close are ignored so the modal
  // only closes AFTER success, never mid-request.
  modalBusy: false,

  async init() {
    UI.init();
    Uploader.init();
    PreviewManager.init();

    this.bindEvents();
    await this.checkAuth();
  },

  bindEvents() {
    // Listen for session expiry
    window.addEventListener('auth:expired', () => {
      this.showLoginView();
      UI.toast('Sesi telah berakhir.', 'error');
    });

    // Theme Toggle
    const themeBtn = document.getElementById('themeToggleBtn');
    if (themeBtn) {
      themeBtn.addEventListener('click', () => this.toggleTheme());
    }

    // Mobile Hamburger
    const menuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (menuBtn && sidebar && backdrop) {
      menuBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
      backdrop.addEventListener('click', () => sidebar.classList.remove('open'));
    }

    // Search Input
    const searchInput = document.getElementById('globalSearchInput');
    let searchTimeout = null;
    if (searchInput) {
      searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        searchTimeout = setTimeout(() => this.performSearch(query), 300);
      });
    }

    // New Folder Modal & Form
    const newFolderBtn = document.getElementById('newFolderBtn');
    const newFolderModal = document.getElementById('newFolderModal');
    const newFolderForm = document.getElementById('newFolderForm');
    const newFolderInput = document.getElementById('newFolderInput');

    if (newFolderBtn && newFolderModal) {
      newFolderBtn.addEventListener('click', () => {
        newFolderInput.value = '';
        newFolderModal.classList.add('open');
        setTimeout(() => newFolderInput.focus(), 50);
      });

      if (newFolderForm) {
        newFolderForm.onsubmit = async (e) => {
          e.preventDefault();
          if (this.modalBusy) return; // block duplicate Enter submits
          const errEl = document.getElementById('newFolderError');
          if (errEl) {
            errEl.style.display = 'none';
            errEl.textContent = '';
          }
          const name = newFolderInput.value.trim();
          if (!name) {
            if (errEl) {
              errEl.textContent = 'Nama folder tidak boleh kosong.';
              errEl.style.display = 'block';
            }
            newFolderInput.focus();
            return;
          }
          const submitBtn = document.getElementById('newFolderSubmitBtn');
          this.modalBusy = true;
          newFolderInput.disabled = true;
          if (submitBtn) submitBtn.disabled = true;
          let result;
          try {
            result = await this.createFolder(name);
          } finally {
            this.modalBusy = false;
            newFolderInput.disabled = false;
            if (submitBtn) submitBtn.disabled = false;
          }
          // Close ONLY after SUCCESS; on failure keep modal open with inline error.
          if (result === true) {
            newFolderModal.classList.remove('open');
            newFolderInput.value = '';
          } else if (errEl) {
            errEl.textContent = typeof result === 'string' ? result : 'Gagal membuat folder.';
            errEl.style.display = 'block';
            newFolderInput.focus();
          }
        };
      }
    }

    // Universal ESC Key Listener for all Modals
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        // Never cancel an in-flight modal submit.
        if (this.modalBusy) return;
        // Check if any modal backdrop is open
        const openModal = document.querySelector('.modal-backdrop.open');
        if (openModal) {
          e.preventDefault();
          openModal.classList.remove('open');
          return;
        }

        // Check if bottom sheet is open
        if (UI.bottomSheet && UI.bottomSheet.classList.contains('open')) {
          e.preventDefault();
          UI.closeBottomSheet();
          return;
        }

        // Preview open → its own Esc handler closes it; leave selection alone.
        if (typeof PreviewManager !== 'undefined' && PreviewManager.modal && PreviewManager.modal.classList.contains('open')) {
          return;
        }

        // Active selection → clear it (no modal involved).
        if (UI.clearSelectionIfAny()) {
          e.preventDefault();
          return;
        }
      }
    });

    // Ctrl/Cmd+A: select all CURRENTLY VISIBLE items. Never hijack text inputs.
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && (e.key === 'a' || e.key === 'A')) {
        const t = e.target;
        if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) {
          return; // native text selection keeps working
        }
        if (document.querySelector('.modal-backdrop.open')) return;
        if (typeof PreviewManager !== 'undefined' && PreviewManager.modal && PreviewManager.modal.classList.contains('open')) return;
        if (UI.selectAllVisible()) {
          e.preventDefault();
        }
      }
    });

    // Modal Cancel/Close buttons
    document.querySelectorAll('[data-close-modal]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        // Do not abandon an in-flight submit; it closes itself on success.
        if (this.modalBusy) return;
        btn.closest('.modal-backdrop').classList.remove('open');
      });
    });

    // View mode switch (Grid vs List)
    const viewGridBtn = document.getElementById('viewGridBtn');
    const viewListBtn = document.getElementById('viewListBtn');
    if (viewGridBtn && viewListBtn) {
      viewGridBtn.addEventListener('click', () => {
        UI.viewMode = 'grid';
        viewGridBtn.style.color = 'var(--accent)';
        viewListBtn.style.color = 'var(--text-secondary)';
        this.refresh();
      });
      viewListBtn.addEventListener('click', () => {
        UI.viewMode = 'list';
        viewListBtn.style.color = 'var(--accent)';
        viewGridBtn.style.color = 'var(--text-secondary)';
        this.refresh();
      });
    }
  },

  async checkAuth() {
    try {
      const res = await Api.get('/api/auth/status');
      if (res.authenticated) {
        this.currentUser = res.username;
        Api.setCsrfToken(res.csrf_token);
        this.showDashboard();
        await this.loadView('files', 'root');
      } else {
        this.showLoginView();
      }
    } catch (e) {
      this.showLoginView();
    }
  },

  showLoginView() {
    document.getElementById('loginScreen').style.display = 'flex';
    document.getElementById('appContainer').style.display = 'none';

    const form = document.getElementById('loginForm');
    form.onsubmit = async (e) => {
      e.preventDefault();
      const username = form.username.value.trim();
      const password = form.password.value.trim();
      const errEl = document.getElementById('loginError');
      errEl.style.display = 'none';

      try {
        const res = await Api.post('/api/auth/login', { username, password });
        Api.setCsrfToken(res.data.csrf_token);
        this.currentUser = res.data.username;
        this.showDashboard();
        await this.loadView('files', 'root');
        UI.toast(`Selamat datang, ${this.currentUser}!`, 'success');
      } catch (err) {
        errEl.textContent = err.message;
        errEl.style.display = 'block';
      }
    };
  },

  showDashboard() {
    document.getElementById('loginScreen').style.display = 'none';
    document.getElementById('appContainer').style.display = 'flex';
    document.getElementById('usernameDisplay').textContent = this.currentUser || 'Owner';
  },

  async logout() {
    try {
      await Api.post('/api/auth/logout', {});
    } catch (e) {
      console.error(e);
    }
    this.showLoginView();
    UI.toast('Berhasil keluar.', 'info');
  },

  async loadView(viewType, folderId = 'root', silent = false) {
    this.currentView = viewType;
    this.currentFolderId = folderId;

    // Update active state in navigation
    document.querySelectorAll('.nav-item').forEach((item) => {
      item.classList.toggle('active', item.dataset.view === viewType);
    });

    const contentArea = document.getElementById('mainContentBody');
    const breadcrumbsWrap = document.getElementById('breadcrumbsWrap');
    
    // Only show loading placeholder if not silent refresh to prevent UI blink
    if (!silent) {
      contentArea.innerHTML = '<div style="color:var(--text-muted);padding:20px;">Memuat data...</div>';
    }

    // Close mobile sidebar if open
    document.getElementById('sidebar').classList.remove('open');

    if (viewType === 'trash') {
      UI.clearSelection();
      await this.loadTrashView(contentArea, breadcrumbsWrap);
      return;
    }

    if (viewType === 'activity') {
      UI.clearSelection();
      await this.loadActivityView(contentArea, breadcrumbsWrap);
      return;
    }

    if (viewType === 'settings') {
      UI.clearSelection();
      await this.loadSettingsView(contentArea, breadcrumbsWrap);
      return;
    }

    // Default: files, starred, recent
    try {
      const res = await Api.get(`/api/files/list?view=${viewType}&folder_id=${folderId}`);
      const data = res.data;

      // Update storage status card
      this.updateStorageDisplay(data.storage);

      // Render Breadcrumbs
      this.renderBreadcrumbs(data.breadcrumbs || [], breadcrumbsWrap);

      contentArea.innerHTML = '';
      UI.beginViewRender();

      if ((!data.folders || data.folders.length === 0) && (!data.files || data.files.length === 0)) {
        contentArea.innerHTML = `
          <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            <h3>Direktori Kosong</h3>
            <p>Tarik & lepas file di sini atau klik tombol "Unggah Berkas".</p>
          </div>
        `;
        UI.endViewRender();
        return;
      }

      UI.renderFolders(data.folders, contentArea);
      UI.renderFiles(data.files, contentArea);
      UI.endViewRender();
    } catch (err) {
      UI.endViewRender();
      contentArea.innerHTML = `<div style="color:var(--danger)">Gagal memuat berkas: ${UI.escape(err.message)}</div>`;
    }
  },

  async loadTrashView(contentArea, breadcrumbsWrap) {
    breadcrumbsWrap.innerHTML = '<span class="breadcrumb-item active">🗑️ Tong Sampah</span>';
    try {
      const res = await Api.get('/api/trash/list');
      const items = res.data || [];
      contentArea.innerHTML = '';

      const toolbar = document.createElement('div');
      toolbar.style.display = 'flex';
      toolbar.style.justifyContent = 'space-between';
      toolbar.style.alignItems = 'center';
      toolbar.style.marginBottom = '16px';
      toolbar.innerHTML = `
        <div style="font-size:0.88rem;color:var(--text-secondary)">Item di tong sampah otomatis dihapus permanen setelah 30 hari.</div>
        <button class="btn btn-danger" id="emptyTrashBtn" ${items.length === 0 ? 'disabled' : ''}>Kosongkan Sampah</button>
      `;
      contentArea.appendChild(toolbar);

      if (items.length === 0) {
        contentArea.innerHTML += `
          <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            <h3>Tong Sampah Kosong</h3>
            <p>Tidak ada berkas atau folder yang dihapus.</p>
          </div>
        `;
        return;
      }

      document.getElementById('emptyTrashBtn').onclick = async () => {
        if (confirm('Yakin ingin mengosongkan seluruh tong sampah? Tindakan ini tidak dapat dibatalkan.')) {
          await Api.post('/api/trash/empty', {});
          UI.toast('Tong sampah telah dikosongkan.', 'success');
          await this.refresh();
        }
      };

      const table = document.createElement('table');
      table.className = 'items-table';
      table.innerHTML = `
        <thead>
          <tr>
            <th>Nama</th>
            <th>Tipe</th>
            <th>Dihapus Pada</th>
            <th>Kedaluwarsa</th>
            <th style="text-align:right">Aksi</th>
          </tr>
        </thead>
        <tbody></tbody>
      `;

      const tbody = table.querySelector('tbody');
      items.forEach((item) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${UI.escape(item.name)}</td>
          <td>${item.item_type.toUpperCase()}</td>
          <td>${item.deleted_at}</td>
          <td>${item.expires_at}</td>
          <td style="text-align:right">
            <button class="btn btn-secondary" style="padding:4px 10px;font-size:0.8rem" id="restore_${item.id}">Pulihkan</button>
            <button class="btn btn-danger" style="padding:4px 10px;font-size:0.8rem;margin-left:6px" id="purge_${item.id}">Hapus Permanen</button>
          </td>
        `;

        tr.querySelector(`#restore_${item.id}`).onclick = async () => {
          await Api.post('/api/trash/restore', { id: item.id });
          UI.toast('Item berhasil dipulihkan.', 'success');
          await this.refresh();
        };

        tr.querySelector(`#purge_${item.id}`).onclick = async () => {
          if (confirm(`Hapus permanen "${item.name}"?`)) {
            await Api.post('/api/trash/delete', { id: item.id });
            UI.toast('Item dihapus permanen.', 'success');
            await this.refresh();
          }
        };

        tbody.appendChild(tr);
      });

      contentArea.appendChild(table);
    } catch (e) {
      contentArea.innerHTML = `<div style="color:var(--danger)">Gagal memuat sampah: ${UI.escape(e.message)}</div>`;
    }
  },

  async loadActivityView(contentArea, breadcrumbsWrap) {
    breadcrumbsWrap.innerHTML = '<span class="breadcrumb-item active">🕒 Log Aktivitas</span>';
    try {
      const res = await Api.get('/api/activity/list');
      const logs = res.data || [];
      contentArea.innerHTML = '';

      if (logs.length === 0) {
        contentArea.innerHTML = `
          <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
            <h3>Belum Ada Aktivitas</h3>
          </div>
        `;
        return;
      }

      const list = document.createElement('div');
      list.style.display = 'flex';
      list.style.flexDirection = 'column';
      list.style.gap = '8px';

      logs.forEach((log) => {
        const item = document.createElement('div');
        item.style.padding = '12px 16px';
        item.style.background = 'var(--bg-surface)';
        item.style.border = '1px solid var(--border-color)';
        item.style.borderRadius = 'var(--radius-md)';
        item.style.display = 'flex';
        item.style.justifyContent = 'space-between';
        item.style.alignItems = 'center';

        item.innerHTML = `
          <div>
            <div style="font-weight:600;color:var(--text-primary);font-size:0.92rem">
              [${log.action.toUpperCase()}] ${UI.escape(log.item_name)}
            </div>
            <div style="font-size:0.8rem;color:var(--text-muted)">${UI.escape(log.details || '')}</div>
          </div>
          <div style="font-size:0.78rem;color:var(--text-secondary)">${log.timestamp}</div>
        `;
        list.appendChild(item);
      });

      contentArea.appendChild(list);
    } catch (e) {
      contentArea.innerHTML = `<div style="color:var(--danger)">Gagal memuat log aktivitas.</div>`;
    }
  },

  async loadSettingsView(contentArea, breadcrumbsWrap) {
    breadcrumbsWrap.innerHTML = '<span class="breadcrumb-item active">⚙️ Pengaturan</span>';
    try {
      const res = await Api.get('/api/settings');
      const settings = res.data;

      contentArea.innerHTML = `
        <div style="max-width:600px;display:flex;flex-direction:column;gap:20px;">
          <div class="storage-status-card" style="margin:0">
            <div class="title">Status Batas Server Hosting (PHP Limits)</div>
            <div style="font-size:0.85rem;color:var(--text-secondary);display:flex;flex-direction:column;gap:4px">
              <div>Upload Max Filesize: <strong>${settings.php_limits.upload_max_filesize}</strong></div>
              <div>POST Max Size: <strong>${settings.php_limits.post_max_size}</strong></div>
              <div>Memory Limit: <strong>${settings.php_limits.memory_limit}</strong></div>
              <div>Max Execution Time: <strong>${settings.php_limits.max_execution_time}s</strong></div>
            </div>
          </div>

          <div style="background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:20px;display:flex;flex-direction:column;gap:16px;">
            <h4 style="color:var(--text-primary)">Ubah Password Pemilik</h4>
            <form id="changePasswordForm" style="display:flex;flex-direction:column;gap:12px">
              <div class="form-group">
                <label>Password Saat Ini</label>
                <input type="password" name="current_password" class="form-control" required>
              </div>
              <div class="form-group">
                <label>Password Baru (min 8 karakter)</label>
                <input type="password" name="new_password" class="form-control" required minlength="8">
              </div>
              <button type="submit" class="btn btn-primary" style="align-self:flex-start">Simpan Password Baru</button>
            </form>
          </div>

          <div style="background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:20px;display:flex;flex-direction:column;gap:12px;">
            <h4 style="color:var(--text-primary)">Informasi Kapasitas Penyimpanan</h4>
            <div style="font-size:0.85rem;color:var(--text-secondary);line-height:1.6">
              Aplikasi secara otomatis menggunakan kapasitas hosting yang tersedia (<strong>Server Disk / Filesystem</strong>).
            </div>
            <div style="background:rgba(245, 158, 11, 0.1);border:1px solid var(--warning);padding:10px 14px;border-radius:var(--radius-sm);font-size:0.82rem;color:var(--text-primary)">
              ⚠️ <strong>Catatan Hosting:</strong> Kapasitas yang dilaporkan berasal dari partisi fisik server. Pada akun cPanel/shared hosting tertentu, kuota akun Anda mungkin dibatasi terpisah oleh penyedia hosting.
            </div>
            <details style="margin-top:6px;font-size:0.85rem;color:var(--text-muted)">
              <summary style="cursor:pointer;color:var(--text-secondary);font-weight:500">Konfigurasi Lanjutan (Batas Internal Opsional)</summary>
              <form id="quotaSettingForm" style="display:flex;flex-direction:column;gap:12px;margin-top:12px">
                <div class="form-group">
                  <label>Mode Penyimpanan</label>
                  <select name="storage_quota_mode" class="form-control">
                    <option value="disk_free" ${settings.storage_quota_mode !== 'custom' ? 'selected' : ''}>Otomatis Kapasitas Hosting (Direkomendasikan)</option>
                    <option value="custom" ${settings.storage_quota_mode === 'custom' ? 'selected' : ''}>Batas Kustom Internal</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Batas Internal (GB)</label>
                  <input type="number" name="custom_quota_gb" class="form-control" value="${Math.round((settings.custom_quota_bytes || 10737418240) / 1073741824)}" min="1" step="0.5">
                </div>
                <button type="submit" class="btn btn-secondary" style="align-self:flex-start">Simpan Konfigurasi</button>
              </form>
            </details>
          </div>
        </div>
      `;

      const form = document.getElementById('changePasswordForm');
      form.onsubmit = async (e) => {
        e.preventDefault();
        try {
          await Api.post('/api/auth/change-password', {
            current_password: form.current_password.value,
            new_password: form.new_password.value,
          });
          form.reset();
          UI.toast('Password berhasil diperbarui.', 'success');
        } catch (err) {
          UI.toast(err.message, 'error');
        }
      };

      const quotaForm = document.getElementById('quotaSettingForm');
      if (quotaForm) {
        quotaForm.onsubmit = async (e) => {
          e.preventDefault();
          try {
            const mode = quotaForm.storage_quota_mode.value;
            const gb = parseFloat(quotaForm.custom_quota_gb.value) || 10;
            await Api.post('/api/settings/update', {
              storage_quota_mode: mode,
              custom_quota_bytes: Math.round(gb * 1073741824),
            });
            UI.toast('Konfigurasi berhasil disimpan.', 'success');
            await this.refresh();
          } catch (err) {
            UI.toast(err.message, 'error');
          }
        };
      }
    } catch (e) {
      contentArea.innerHTML = `<div style="color:var(--danger)">Gagal memuat pengaturan.</div>`;
    }
  },

  async performSearch(query) {
    if (!query) {
      this.refresh();
      return;
    }

    const contentArea = document.getElementById('mainContentBody');
    const breadcrumbsWrap = document.getElementById('breadcrumbsWrap');
    breadcrumbsWrap.innerHTML = `<span class="breadcrumb-item active">Pencarian: "${UI.escape(query)}"</span>`;

    try {
      const res = await Api.get(`/api/files/search?q=${encodeURIComponent(query)}`);
      const { files, folders } = res.data;

      contentArea.innerHTML = '';
      UI.beginViewRender();
      if (files.length === 0 && folders.length === 0) {
        contentArea.innerHTML = `
          <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <h3>Tidak Ada Hasil</h3>
            <p>Tidak ditemukan file atau folder dengan kata kunci "${UI.escape(query)}".</p>
          </div>
        `;
        UI.endViewRender();
        return;
      }

      UI.renderFolders(folders, contentArea);
      UI.renderFiles(files, contentArea);
      UI.endViewRender();
    } catch (e) {
      UI.endViewRender();
      console.error(e);
    }
  },

  renderBreadcrumbs(breadcrumbs, container) {
    container.innerHTML = '';
    breadcrumbs.forEach((crumb, idx) => {
      const isLast = idx === breadcrumbs.length - 1;
      const span = document.createElement('span');
      span.className = `breadcrumb-item ${isLast ? 'active' : ''}`;
      span.textContent = crumb.name;

      if (!isLast) {
        span.addEventListener('click', () => this.navigateToFolder(crumb.id));
      }

      container.appendChild(span);

      if (!isLast) {
        const sep = document.createElement('span');
        sep.textContent = '/';
        sep.style.color = 'var(--text-muted)';
        container.appendChild(sep);
      }
    });
  },

  updateStorageDisplay(storage) {
    if (!storage) return;
    const fill = document.getElementById('storageBarFill');
    const usedText = document.getElementById('storageUsedText');
    const availText = document.getElementById('storageAvailText');
    const sourceText = document.getElementById('storageSourceText');

    if (fill) fill.style.width = `${storage.used_percent}%`;
    if (usedText) usedText.textContent = `${UI.formatBytes(storage.used_bytes)} terpakai`;
    if (availText) availText.textContent = `${UI.formatBytes(storage.available_bytes)} sisa`;
    if (sourceText && storage.quota_source) {
      sourceText.textContent = storage.quota_source;
      sourceText.title = `${storage.quota_source}: ${storage.quota_warning || 'Kapasitas hosting'}`;
    }
  },

  navigateToFolder(folderId) {
    this.loadView('files', folderId);
  },

  refresh() {
    return this.loadView(this.currentView, this.currentFolderId);
  },

  refreshCurrentView() {
    // Reusable silent refresh: takes latest metadata, updates current folder & storage without reloading full page
    return this.loadView(this.currentView, this.currentFolderId, true);
  },

  async createFolder(name) {
    try {
      await Api.post('/api/folders/create', {
        name: name,
        parent_id: this.currentFolderId,
      });
      UI.toast('Folder berhasil dibuat.', 'success');
      await this.refresh();
      return true;
    } catch (err) {
      UI.toast(err.message, 'error');
      return err.message || 'Gagal membuat folder.';
    }
  },

  async renameItem(id, type, newName) {
    try {
      const endpoint = type === 'folder' ? '/api/folders/rename' : '/api/files/rename';
      await Api.post(endpoint, { id, name: newName });
      UI.toast('Berhasil mengubah nama.', 'success');
      await this.refresh();
      return true;
    } catch (err) {
      UI.toast(err.message, 'error');
      return err.message || 'Gagal mengubah nama.';
    }
  },

  async moveItem(id, type, targetFolderId) {
    try {
      const endpoint = type === 'folder' ? '/api/folders/move' : '/api/files/move';
      const payload = type === 'folder' ? { id, target_parent_id: targetFolderId } : { id, target_folder_id: targetFolderId };
      await Api.post(endpoint, payload);
      UI.toast('Berhasil memindahkan item.', 'success');
      await this.refresh();
    } catch (err) {
      UI.toast(err.message, 'error');
    }
  },

  async toggleStar(fileId) {
    try {
      const res = await Api.post('/api/files/star', { id: fileId });
      UI.toast(res.data.is_starred ? 'Ditambahkan ke berbintang ⭐' : 'Dihapus dari berbintang', 'info');
      await this.refresh();
    } catch (err) {
      UI.toast(err.message, 'error');
    }
  },

  async deleteItem(id, type) {
    try {
      const endpoint = type === 'folder' ? '/api/folders/delete' : '/api/files/delete';
      await Api.post(endpoint, { id });
      UI.toast('Item dipindahkan ke tempat sampah.', 'info');
      await this.refresh();
    } catch (err) {
      UI.toast(err.message, 'error');
    }
  },

  toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'dark';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
  }
};

// Bridge for cross-file calls (ui.js, uploader.js use window.App.*).
// Top-level `const` in a classic script does NOT attach to window,
// so without this line every window.App.* call throws TypeError.
window.App = App;

window.addEventListener('DOMContentLoaded', () => {
  App.init();
});
