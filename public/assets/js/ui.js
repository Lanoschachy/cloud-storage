/**
 * UI Renderer & Interaction Engine
 * Renders Grid, List/Table, Context Menu, Mobile Bottom Sheet, Modals, and Toasts.
 */
const UI = {
  viewMode: 'grid', // 'grid' or 'list'
  sortBy: 'name',
  sortOrder: 'asc',
  // Internal drag-move (desktop HTML5 DnD only). MIME marker keeps it
  // strictly separated from external OS-file upload drops (dataTransfer.files).
  DRAG_MIME: 'application/x-cloud-item',
  lastDragEnd: 0,
  // ---------- Multi-selection state (ID-based, survives re-render) ----------
  // selected: Map key(`${kind}:${id}`) -> {kind, id, name, starred}
  selected: new Map(),
  anchorKey: null,
  // visibleOrder: [{key, snap}] in CURRENT displayed order (sort/filter aware)
  visibleOrder: [],
  // Mobile long-press selection mode
  mobileSelecting: false,
  suppressNextClick: false,
  lastLongPress: 0,

  init() {
    this.contextMenu = document.getElementById('contextMenu');
    this.bottomSheet = document.getElementById('bottomSheet');
    this.sheetBackdrop = document.getElementById('sheetBackdrop');
    this.toastContainer = document.getElementById('toastContainer');

    // Close desktop context menu on document click
    document.addEventListener('click', () => this.hideContextMenu());

    // Close bottom sheet on backdrop click
    if (this.sheetBackdrop) {
      this.sheetBackdrop.addEventListener('click', () => this.closeBottomSheet());
    }

    // Selection toolbar buttons
    const on = (id, fn) => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('click', (e) => { e.stopPropagation(); fn(); });
    };
    on('selDownloadBtn', () => UI.bulkDownload());
    on('selMoveBtn', () => UI.openBulkMove());
    on('selStarBtn', () => UI.bulkStar());
    on('selDeleteBtn', () => UI.bulkDelete());
    on('selClearBtn', () => UI.clearSelection());

    // Click on empty workspace area clears selection (cards/table stop this
    // path by matching closest() below; their own handlers run first).
    const body = document.getElementById('mainContentBody');
    if (body) {
      body.addEventListener('click', (e) => {
        if (e.target.closest('.item-card') || e.target.closest('.items-table')) return;
        UI.clearSelection();
      });
    }
  },

  toast(message, type = 'info') {
    if (!this.toastContainer) return;
    const toast = document.createElement('div');
    toast.className = 'toast';
    let icon = 'ℹ️';
    if (type === 'success') icon = '✓';
    if (type === 'error') icon = '✕';

    toast.innerHTML = `<span style="font-weight:700">${icon}</span> <span>${this.escape(message)}</span>`;
    this.toastContainer.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  },

  renderFolders(folders, container) {
    if (!folders || folders.length === 0) return;

    const title = document.createElement('div');
    title.className = 'section-title';
    title.textContent = 'Folder';
    container.appendChild(title);

    const grid = document.createElement('div');
    grid.className = 'items-grid';

    folders.forEach((folder) => {
      const card = document.createElement('div');
      card.className = 'item-card';
      card.innerHTML = `
        <div class="card-thumb" style="height:70px">
          <svg viewBox="0 0 24 24" fill="currentColor" style="color:var(--accent)"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"></path></svg>
        </div>
        <div class="card-meta">
          <div class="card-title" title="${this.escape(folder.name)}">${this.escape(folder.name)}</div>
          <div class="card-subtitle">Folder</div>
        </div>
        <button class="card-more-btn" title="Opsi Folder">⋮</button>
      `;

      // Internal drag source (MOVE) + drop target for files/folders.
      UI.makeDraggable(card, { id: folder.id, kind: 'folder' });
      UI.makeFolderDropTarget(card, folder);
      UI.attachLongPress(card, { kind: 'folder', id: folder.id, name: folder.name });
      const folderKey = UI.trackVisible('folder', folder.id, folder.name, false);
      card.setAttribute('data-sel-key', folderKey);
      card.setAttribute('role', 'option');
      if (UI.isSelected(folderKey)) {
        card.classList.add('selected');
        card.setAttribute('aria-selected', 'true');
      } else {
        card.setAttribute('aria-selected', 'false');
      }

      // Priority: modifiers/mode first, normal click opens folder.
      card.addEventListener('click', (e) => {
        UI.handleItemClick(e, { kind: 'folder', id: folder.id, name: folder.name }, () => {
          window.App.navigateToFolder(folder.id);
        });
      });

      // Context menu / bottom sheet
      const moreBtn = card.querySelector('.card-more-btn');
      moreBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        this.openItemActions(folder, 'folder', e);
      });

      card.addEventListener('contextmenu', (e) => {
        if (Date.now() - UI.lastLongPress < 600) {
          e.preventDefault();
          return; // touch long-press menu suppression, desktop right-click unaffected
        }
        e.preventDefault();
        this.openItemActions(folder, 'folder', e);
      });

      grid.appendChild(card);
    });

    container.appendChild(grid);
  },

  renderFiles(files, container) {
    if (!files || files.length === 0) return;

    const title = document.createElement('div');
    title.className = 'section-title';
    title.textContent = 'Berkas';
    container.appendChild(title);

    // Sort files
    files.sort((a, b) => {
      let valA = a[this.sortBy] || '';
      let valB = b[this.sortBy] || '';
      if (typeof valA === 'string') valA = valA.toLowerCase();
      if (typeof valB === 'string') valB = valB.toLowerCase();
      
      if (valA < valB) return this.sortOrder === 'asc' ? -1 : 1;
      if (valA > valB) return this.sortOrder === 'asc' ? 1 : -1;
      return 0;
    });

    if (this.viewMode === 'grid') {
      this.renderFilesGrid(files, container);
    } else {
      this.renderFilesTable(files, container);
    }
  },

  renderFilesGrid(files, container) {
    const grid = document.createElement('div');
    grid.className = 'items-grid';

    files.forEach((file) => {
      const card = document.createElement('div');
      card.className = 'item-card';

      let thumbHtml = this.getFileIconSvg(file);
      if (file.preview_type === 'image') {
        thumbHtml = `<img src="/api/files/preview?id=${file.id}" alt="${this.escape(file.name)}" loading="lazy" draggable="false">`;
      }

      card.innerHTML = `
        <div class="card-thumb">${thumbHtml}</div>
        <div class="card-meta">
          <div class="card-title" title="${this.escape(file.name)}">
            ${file.is_starred ? '⭐ ' : ''}${this.escape(file.name)}
          </div>
          <div class="card-subtitle">${this.formatBytes(file.size)}</div>
        </div>
        <button class="card-more-btn" title="Opsi Berkas">⋮</button>
      `;

      // Internal drag source (MOVE). Files are never drop targets.
      UI.makeDraggable(card, { id: file.id, kind: 'file', folderId: file.folder_id || null });
      UI.attachLongPress(card, { kind: 'file', id: file.id, name: file.name, starred: file.is_starred });
      const fileKey = UI.trackVisible('file', file.id, file.name, file.is_starred);
      card.setAttribute('data-sel-key', fileKey);
      card.setAttribute('role', 'option');
      if (UI.isSelected(fileKey)) {
        card.classList.add('selected');
        card.setAttribute('aria-selected', 'true');
      } else {
        card.setAttribute('aria-selected', 'false');
      }

      card.addEventListener('click', (e) => {
        UI.handleItemClick(e, { kind: 'file', id: file.id, name: file.name, starred: file.is_starred }, () => {
          PreviewManager.open(file);
        });
      });

      const moreBtn = card.querySelector('.card-more-btn');
      moreBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        this.openItemActions(file, 'file', e);
      });

      card.addEventListener('contextmenu', (e) => {
        if (Date.now() - UI.lastLongPress < 600) {
          e.preventDefault();
          return; // touch long-press menu suppression, desktop right-click unaffected
        }
        e.preventDefault();
        this.openItemActions(file, 'file', e);
      });

      grid.appendChild(card);
    });

    container.appendChild(grid);
  },

  renderFilesTable(files, container) {
    const table = document.createElement('table');
    table.className = 'items-table';
    table.innerHTML = `
      <thead>
        <tr>
          <th onclick="UI.changeSort('name')">Nama ${this.sortBy === 'name' ? (this.sortOrder === 'asc' ? '▲' : '▼') : ''}</th>
          <th onclick="UI.changeSort('extension')">Tipe</th>
          <th onclick="UI.changeSort('size')">Ukuran ${this.sortBy === 'size' ? (this.sortOrder === 'asc' ? '▲' : '▼') : ''}</th>
          <th onclick="UI.changeSort('updated_at')">Diubah ${this.sortBy === 'updated_at' ? (this.sortOrder === 'asc' ? '▲' : '▼') : ''}</th>
          <th style="width:40px"></th>
        </tr>
      </thead>
      <tbody></tbody>
    `;

    const tbody = table.querySelector('tbody');
    files.forEach((file) => {
      const tr = document.createElement('tr');
      UI.makeDraggable(tr, { id: file.id, kind: 'file', folderId: file.folder_id || null });
      UI.attachLongPress(tr, { kind: 'file', id: file.id, name: file.name, starred: file.is_starred });
      const rowKey = UI.trackVisible('file', file.id, file.name, file.is_starred);
      tr.setAttribute('data-sel-key', rowKey);
      if (UI.isSelected(rowKey)) {
        tr.classList.add('selected');
        tr.setAttribute('aria-selected', 'true');
      } else {
        tr.setAttribute('aria-selected', 'false');
      }
      tr.innerHTML = `
        <td>
          <div class="table-name-cell">
            <div class="table-icon">${this.getFileIconSvg(file, 22)}</div>
            <span>${file.is_starred ? '⭐ ' : ''}${this.escape(file.name)}</span>
          </div>
        </td>
        <td>${(file.extension || 'File').toUpperCase()}</td>
        <td>${this.formatBytes(file.size)}</td>
        <td>${file.updated_at || file.uploaded_at}</td>
        <td><button class="icon-btn" style="border:none">⋮</button></td>
      `;

      tr.addEventListener('click', (e) => {
        UI.handleItemClick(e, { kind: 'file', id: file.id, name: file.name, starred: file.is_starred }, () => {
          PreviewManager.open(file);
        });
      });

      const moreBtn = tr.querySelector('button');
      moreBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        this.openItemActions(file, 'file', e);
      });

      tr.addEventListener('contextmenu', (e) => {
        if (Date.now() - UI.lastLongPress < 600) {
          e.preventDefault();
          return; // touch long-press menu suppression, desktop right-click unaffected
        }
        e.preventDefault();
        this.openItemActions(file, 'file', e);
      });

      tbody.appendChild(tr);
    });

    container.appendChild(table);
  },

  changeSort(field) {
    if (this.sortBy === field) {
      this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortBy = field;
      this.sortOrder = 'asc';
    }
    window.App.refresh();
  },

  // ---------- Internal drag-move (desktop only, HTML5 DnD) ----------
  // Touch devices keep using the bottom-sheet "Pindahkan" action instead.
  isDesktopDnD() {
    return !!(window.matchMedia && window.matchMedia('(pointer: fine)').matches);
  },

  isInternalDrag(e) {
    const types = (e.dataTransfer && e.dataTransfer.types) || [];
    for (let i = 0; i < types.length; i++) {
      if (types[i] === UI.DRAG_MIME) return true;
    }
    return false;
  },

  getInternalPayload(e) {
    try {
      return JSON.parse(e.dataTransfer.getData(UI.DRAG_MIME));
    } catch (err) {
      return null;
    }
  },

  clickedAfterDrag() {
    return Date.now() - UI.lastDragEnd < 350;
  },

  // ---------- Multi-selection core (ID-based, never index-based) ----------
  selKey(kind, id) {
    return `${kind}:${id}`;
  },

  makeSnap(kind, id, name, starred) {
    return { kind, id, name: name || id, starred: !!starred };
  },

  isSelected(key) {
    return UI.selected.has(key);
  },

  // Called at the start of every files-view render.
  beginViewRender() {
    UI.visibleOrder = [];
  },

  // Called after every files-view render: prune stale IDs, repaint, toolbar.
  endViewRender() {
    const visible = new Set(UI.visibleOrder.map((o) => o.key));
    for (const key of [...UI.selected.keys()]) {
      if (!visible.has(key)) UI.selected.delete(key);
    }
    if (UI.anchorKey && !visible.has(UI.anchorKey)) UI.anchorKey = null;
    if (UI.selected.size === 0) UI.mobileSelecting = false;
    UI.paintSelection();
    UI.updateSelectionToolbar();
  },

  trackVisible(kind, id, name, starred) {
    const key = UI.selKey(kind, id);
    UI.visibleOrder.push({ key, snap: UI.makeSnap(kind, id, name, starred) });
    return key;
  },

  paintSelection() {
    const body = document.getElementById('mainContentBody');
    if (!body) return;
    body.querySelectorAll('.item-card.selected, tr.selected').forEach((el) => {
      el.classList.remove('selected');
      el.setAttribute('aria-selected', 'false');
    });
    for (const key of UI.selected.keys()) {
      const esc = key.replace(/"/g, '');
      body.querySelectorAll(`[data-sel-key="${esc}"]`).forEach((el) => {
        el.classList.add('selected');
        el.setAttribute('aria-selected', 'true');
      });
    }
  },

  selectExclusive(payload) {
    const key = UI.selKey(payload.kind, payload.id);
    UI.selected.clear();
    UI.selected.set(key, UI.makeSnap(payload.kind, payload.id, payload.name, payload.starred));
    UI.anchorKey = key;
    UI.paintSelection();
    UI.updateSelectionToolbar();
  },

  selectExclusiveByKey(key) {
    const found = UI.visibleOrder.find((o) => o.key === key);
    UI.selected.clear();
    if (found) UI.selected.set(key, found.snap);
    UI.anchorKey = key;
    UI.paintSelection();
    UI.updateSelectionToolbar();
  },

  toggleSelect(payload) {
    const key = UI.selKey(payload.kind, payload.id);
    if (UI.selected.has(key)) {
      UI.selected.delete(key);
    } else {
      UI.selected.set(key, UI.makeSnap(payload.kind, payload.id, payload.name, payload.starred));
    }
    UI.anchorKey = key;
    if (UI.selected.size === 0) UI.mobileSelecting = false;
    UI.paintSelection();
    UI.updateSelectionToolbar();
  },

  // Range follows CURRENT visible order (sort/filter/search aware).
  // union=false (plain Shift) replaces; union=true (Ctrl+Shift) adds.
  selectRange(clickedKey, union) {
    const order = UI.visibleOrder;
    const clickIdx = order.findIndex((o) => o.key === clickedKey);
    if (clickIdx < 0) return;
    let anchorIdx = order.findIndex((o) => o.key === UI.anchorKey);
    if (anchorIdx < 0) {
      UI.selectExclusiveByKey(clickedKey);
      return;
    }
    const lo = Math.min(anchorIdx, clickIdx);
    const hi = Math.max(anchorIdx, clickIdx);
    if (!union) UI.selected.clear();
    for (let i = lo; i <= hi; i++) {
      UI.selected.set(order[i].key, order[i].snap);
    }
    // Anchor stays (Drive behavior) so repeated Shift-clicks re-range.
    UI.paintSelection();
    UI.updateSelectionToolbar();
  },

  selectAllVisible() {
    if (UI.visibleOrder.length === 0) return false;
    UI.selected.clear();
    for (const o of UI.visibleOrder) UI.selected.set(o.key, o.snap);
    UI.anchorKey = UI.visibleOrder[0].key;
    UI.paintSelection();
    UI.updateSelectionToolbar();
    return true;
  },

  clearSelection() {
    if (UI.selected.size === 0 && !UI.mobileSelecting) return;
    UI.selected.clear();
    UI.anchorKey = null;
    UI.mobileSelecting = false;
    UI.paintSelection();
    UI.updateSelectionToolbar();
  },

  clearSelectionIfAny() {
    if (UI.selected.size === 0) return false;
    UI.clearSelection();
    return true;
  },

  // Single event-priority dispatcher for item activation.
  // Priority: action buttons > post-drag > Shift > Ctrl/Meta > mobile mode > normal.
  handleItemClick(e, payload, openFn) {
    if (e.target.closest('.card-more-btn') || e.target.closest('button')) return;
    if (UI.suppressNextClick) {
      UI.suppressNextClick = false;
      return;
    }
    if (UI.clickedAfterDrag()) return;
    const key = UI.selKey(payload.kind, payload.id);
    if (e.shiftKey) {
      UI.selectRange(key, e.ctrlKey || e.metaKey);
      return;
    }
    if (e.ctrlKey || e.metaKey) {
      UI.toggleSelect(payload);
      return;
    }
    if (UI.mobileSelecting) {
      UI.toggleSelect(payload);
      return;
    }
    UI.selectExclusive(payload);
    openFn();
  },

  // Mobile long-press: enters selection mode WITHOUT opening anything.
  // Desktop (mouse) never reaches here; touch scroll cancels via touchmove.
  attachLongPress(el, payload) {
    if (!('ontouchstart' in window)) return;
    let timer = null;
    let sx = 0;
    let sy = 0;
    el.addEventListener('touchstart', (e) => {
      if (e.touches.length !== 1) return;
      sx = e.touches[0].clientX;
      sy = e.touches[0].clientY;
      timer = setTimeout(() => {
        timer = null;
        UI.lastLongPress = Date.now();
        UI.suppressNextClick = true; // swallow the trailing synthetic click
        UI.mobileSelecting = true;
        const key = UI.selKey(payload.kind, payload.id);
        if (!UI.selected.has(key)) {
          UI.selected.set(key, UI.makeSnap(payload.kind, payload.id, payload.name, payload.starred));
          UI.anchorKey = key;
        }
        UI.paintSelection();
        UI.updateSelectionToolbar();
        if (navigator.vibrate) {
          try { navigator.vibrate(30); } catch (_) { /* noop */ }
        }
      }, 550);
    }, { passive: true });
    el.addEventListener('touchmove', (e) => {
      if (!timer) return;
      const t = e.touches[0];
      if (Math.abs(t.clientX - sx) > 10 || Math.abs(t.clientY - sy) > 10) {
        clearTimeout(timer);
        timer = null;
      }
    }, { passive: true });
    const cancel = () => {
      if (timer) {
        clearTimeout(timer);
        timer = null;
      }
    };
    el.addEventListener('touchend', cancel);
    el.addEventListener('touchcancel', cancel);
  },

  selectedFiles() {
    return [...UI.selected.values()].filter((s) => s.kind === 'file');
  },

  updateSelectionToolbar() {
    const bar = document.getElementById('selectionToolbar');
    if (!bar) return;
    const n = UI.selected.size;
    document.body.classList.toggle('has-selection', n > 0);
    if (n === 0) {
      bar.style.display = 'none';
      return;
    }
    bar.style.display = 'flex';
    const count = document.getElementById('selCount');
    if (count) count.textContent = `${n} dipilih`;
    // Download: honest single-file only (no bulk ZIP yet).
    const dl = document.getElementById('selDownloadBtn');
    if (dl) {
      const files = UI.selectedFiles();
      dl.style.display = (n === 1 && files.length === 1) ? '' : 'none';
    }
    // Star: only when at least one FILE is selected.
    const star = document.getElementById('selStarBtn');
    if (star) {
      const files = UI.selectedFiles();
      if (files.length === 0) {
        star.style.display = 'none';
      } else {
        star.style.display = '';
        const allStarred = files.every((f) => f.starred);
        star.innerHTML = allStarred
          ? '☆ <span class="sel-btn-label">Hapus Bintang</span>'
          : '⭐ <span class="sel-btn-label">Bintang</span>';
        star.title = allStarred ? 'Hapus bintang dari file terpilih' : 'Tambahkan bintang ke file terpilih';
      }
    }
  },

  bulkDownload() {
    const files = UI.selectedFiles();
    if (files.length !== 1) return;
    window.location.href = `/api/files/download?id=${files[0].id}`;
  },

  async bulkStar() {
    const files = UI.selectedFiles();
    if (files.length === 0) return;
    const needStar = files.some((f) => !f.starred);
    const targets = needStar ? files.filter((f) => !f.starred) : files.filter((f) => f.starred);
    let fail = 0;
    for (const f of targets) {
      try {
        await Api.post('/api/files/star', { id: f.id });
      } catch (e) {
        fail++;
      }
    }
    UI.clearSelection();
    try {
      await window.App.refreshCurrentView();
    } catch (e) {
      console.error('Refresh after bulk star failed:', e);
    }
    UI.toast(fail ? `${targets.length - fail} diperbarui, ${fail} gagal.` : 'Status bintang diperbarui.', fail ? 'error' : 'info');
  },

  async bulkDelete() {
    if (UI.selected.size === 0) return;
    const items = [...UI.selected.values()];
    let ok = 0;
    let fail = 0;
    for (const s of items) {
      try {
        const endpoint = s.kind === 'folder' ? '/api/folders/delete' : '/api/files/delete';
        await Api.post(endpoint, { id: s.id });
        ok++;
      } catch (e) {
        fail++;
      }
    }
    UI.clearSelection();
    try {
      await window.App.refreshCurrentView();
    } catch (e) {
      console.error('Refresh after bulk delete failed:', e);
    }
    UI.toast(fail ? `${ok} dipindahkan ke sampah, ${fail} gagal.` : `${ok} item dipindahkan ke sampah.`, fail ? 'error' : 'info');
  },

  openBulkMove() {
    if (UI.selected.size === 0) return;
    UI.showMoveModal(null, null, [...UI.selected.values()]);
  },

  // Marks an element as an internal MOVE drag source.
  makeDraggable(el, payload) {
    if (!UI.isDesktopDnD()) return;
    el.setAttribute('draggable', 'true');
    el.addEventListener('dragstart', (e) => {
      e.stopPropagation();
      try {
        e.dataTransfer.setData(UI.DRAG_MIME, JSON.stringify(payload));
      } catch (err) {
        return;
      }
      e.dataTransfer.effectAllowed = 'move';
      el.classList.add('dragging');
    });
    el.addEventListener('dragend', () => {
      el.classList.remove('dragging');
      document.querySelectorAll('.item-card.drop-target').forEach((t) => {
        t.classList.remove('drop-target');
      });
      UI.lastDragEnd = Date.now();
    });
  },

  // Marks a folder card as a MOVE drop target. External OS files are
  // ignored here and fall through to the Uploader window handler.
  makeFolderDropTarget(el, targetFolder) {
    if (!UI.isDesktopDnD()) return;
    el.addEventListener('dragenter', (e) => {
      if (!UI.isInternalDrag(e)) return;
      e.preventDefault();
      el.classList.add('drop-target');
    });
    el.addEventListener('dragover', (e) => {
      if (!UI.isInternalDrag(e)) return;
      e.preventDefault();
      e.stopPropagation();
      e.dataTransfer.dropEffect = 'move';
      el.classList.add('drop-target');
    });
    el.addEventListener('dragleave', (e) => {
      if (e.relatedTarget && el.contains(e.relatedTarget)) return;
      el.classList.remove('drop-target');
    });
    el.addEventListener('drop', (e) => {
      if (!UI.isInternalDrag(e)) return; // external file -> Uploader
      e.preventDefault();
      e.stopPropagation();
      el.classList.remove('drop-target');
      UI.handleInternalDrop(UI.getInternalPayload(e), targetFolder);
    });
  },

  // Executes the MOVE: sends IDs only, backend re-validates everything.
  async handleInternalDrop(payload, targetFolder) {
    if (!payload || !payload.id || !targetFolder || !targetFolder.id) return;
    try {
      if (payload.kind === 'folder') {
        if (payload.id === targetFolder.id) return; // dropped onto itself
        await Api.post('/api/folders/move', {
          id: payload.id,
          target_parent_id: targetFolder.id,
        });
      } else {
        if (payload.folderId && payload.folderId === targetFolder.id) {
          UI.toast('File sudah berada di folder ini.', 'info');
          return;
        }
        await Api.post('/api/files/move', {
          id: payload.id,
          target_folder_id: targetFolder.id,
        });
      }
      // Single source of truth: re-fetch latest metadata, then render.
      // Only toast success AFTER the view reflects the new state.
      if (window.App && typeof window.App.refreshCurrentView === 'function') {
        await window.App.refreshCurrentView();
      }
      UI.toast(
        payload.kind === 'folder'
          ? `Folder dipindahkan ke "${targetFolder.name}".`
          : `File dipindahkan ke "${targetFolder.name}".`,
        'success'
      );
    } catch (err) {
      UI.toast(err.message || 'Gagal memindahkan item.', 'error');
    }
  },

  openItemActions(item, type, event) {
    const isMobile = window.innerWidth <= 768;
    if (isMobile) {
      this.showBottomSheet(item, type);
    } else {
      this.showContextMenu(item, type, event.clientX, event.clientY);
    }
  },

  showContextMenu(item, type, x, y) {
    if (!this.contextMenu) return;
    this.contextMenu.innerHTML = '';

    const actions = this.getItemActionList(item, type);
    actions.forEach((act) => {
      const btn = document.createElement('div');
      btn.className = `context-menu-item ${act.danger ? 'danger' : ''}`;
      btn.innerHTML = `${act.icon} <span>${act.label}</span>`;
      btn.addEventListener('click', () => {
        this.hideContextMenu();
        act.handler();
      });
      this.contextMenu.appendChild(btn);
    });

    this.contextMenu.style.display = 'flex';
    // Reposition inside bounds with minimum and maximum boundary clamp
    const menuWidth = 210;
    const menuHeight = Math.max(actions.length * 38 + 12, 120);
    const maxPosX = Math.max(10, window.innerWidth - menuWidth - 10);
    const maxPosY = Math.max(10, window.innerHeight - menuHeight - 10);

    const safeX = Math.max(10, Math.min(x, maxPosX));
    const safeY = Math.max(10, Math.min(y, maxPosY));

    this.contextMenu.style.left = `${safeX}px`;
    this.contextMenu.style.top = `${safeY}px`;
  },

  hideContextMenu() {
    if (this.contextMenu) {
      this.contextMenu.style.display = 'none';
    }
  },

  showBottomSheet(item, type) {
    if (!this.bottomSheet || !this.sheetBackdrop) return;
    const header = document.getElementById('sheetHeader');
    const actionsContainer = document.getElementById('sheetActions');

    header.textContent = item.name;
    actionsContainer.innerHTML = '';

    const actions = this.getItemActionList(item, type);
    actions.forEach((act) => {
      const btn = document.createElement('div');
      btn.className = `sheet-action-item ${act.danger ? 'danger' : ''}`;
      btn.innerHTML = `${act.icon} <span>${act.label}</span>`;
      btn.addEventListener('click', () => {
        this.closeBottomSheet();
        act.handler();
      });
      actionsContainer.appendChild(btn);
    });

    this.sheetBackdrop.classList.add('open');
    this.bottomSheet.classList.add('open');
  },

  closeBottomSheet() {
    if (this.bottomSheet) this.bottomSheet.classList.remove('open');
    if (this.sheetBackdrop) this.sheetBackdrop.classList.remove('open');
  },

  getItemActionList(item, type) {
    const list = [];
    if (type === 'file') {
      list.push({
        label: 'Pratinjau',
        icon: '👁️',
        handler: () => PreviewManager.open(item),
      });
      list.push({
        label: 'Unduh',
        icon: '⬇️',
        handler: () => window.location.href = `/api/files/download?id=${item.id}`,
      });
      list.push({
        label: item.is_starred ? 'Hapus Bintang' : 'Tambahkan Bintang',
        icon: '⭐',
        handler: () => window.App.toggleStar(item.id),
      });
      list.push({
        label: 'Detail Berkas',
        icon: 'ℹ️',
        handler: () => this.showDetailsModal(item),
      });
    }

    list.push({
      label: 'Ubah Nama',
      icon: '✏️',
      handler: () => this.showRenameModal(item, type),
    });

    list.push({
      label: 'Pindahkan',
      icon: '📁',
      handler: () => this.showMoveModal(item, type),
    });

    list.push({
      label: 'Hapus ke Tong Sampah',
      icon: '🗑️',
      danger: true,
      handler: () => window.App.deleteItem(item.id, type),
    });

    return list;
  },

  showDetailsModal(file) {
    const modal = document.getElementById('detailsModal');
    const body = document.getElementById('detailsModalBody');
    if (!modal || !body) return;

    body.innerHTML = `
      <div style="display:flex;flex-direction:column;gap:12px;font-size:0.9rem">
        <div><strong>Nama:</strong> ${this.escape(file.name)}</div>
        <div><strong>Tipe MIME:</strong> ${this.escape(file.mime_type || '-')}</div>
        <div><strong>Ukuran:</strong> ${this.formatBytes(file.size)} (${file.size.toLocaleString()} bytes)</div>
        <div><strong>Diunggah:</strong> ${file.uploaded_at || '-'}</div>
        <div><strong>Terakhir Diakses:</strong> ${file.last_accessed_at || '-'}</div>
        <div><strong>Status Bintang:</strong> ${file.is_starred ? 'Ya ⭐' : 'Tidak'}</div>
      </div>
    `;

    modal.classList.add('open');
  },

  showRenameModal(item, type) {
    const modal = document.getElementById('renameModal');
    const form = document.getElementById('renameForm');
    const input = document.getElementById('renameInput');
    if (!modal || !input) return;

    input.value = item.name;
    const errEl = document.getElementById('renameError');
    if (errEl) {
      errEl.style.display = 'none';
      errEl.textContent = '';
    }
    modal.classList.add('open');
    setTimeout(() => {
      input.focus();
      // Select filename without extension for easy renaming
      const dotIndex = item.name.lastIndexOf('.');
      if (dotIndex > 0 && type === 'file') {
        input.setSelectionRange(0, dotIndex);
      } else {
        input.select();
      }
    }, 50);

    if (form) {
      form.onsubmit = async (e) => {
        e.preventDefault();
        if (window.App.modalBusy) return; // block duplicate Enter submits
        const errBox = document.getElementById('renameError');
        if (errBox) {
          errBox.style.display = 'none';
          errBox.textContent = '';
        }
        const newName = input.value.trim();
        if (!newName) {
          if (errBox) {
            errBox.textContent = 'Nama tidak boleh kosong.';
            errBox.style.display = 'block';
          }
          input.focus();
          return;
        }
        if (newName === item.name) {
          modal.classList.remove('open');
          return;
        }
        const submitBtn = document.getElementById('renameSubmitBtn');
        window.App.modalBusy = true;
        input.disabled = true;
        if (submitBtn) submitBtn.disabled = true;
        let result;
        try {
          result = await window.App.renameItem(item.id, type, newName);
        } finally {
          window.App.modalBusy = false;
          input.disabled = false;
          if (submitBtn) submitBtn.disabled = false;
        }
        // Close ONLY after SUCCESS; on failure keep modal open with inline error.
        if (result === true) {
          modal.classList.remove('open');
        } else if (errBox) {
          errBox.textContent = typeof result === 'string' ? result : 'Gagal mengubah nama.';
          errBox.style.display = 'block';
          input.focus();
        }
      };
    }
  },

  async showMoveModal(item, type, bulkItems = null) {
    const isBulk = Array.isArray(bulkItems) && bulkItems.length > 0;
    const modal = document.getElementById('moveModal');
    const select = document.getElementById('moveFolderSelect');
    const submitBtn = document.getElementById('moveSubmitBtn');
    if (!modal || !select || !submitBtn) return;

    select.innerHTML = '<option value="root">My Cloud (Root)</option>';
    try {
      const res = await Api.get('/api/folders/tree');
      const folders = res.data || [];
      folders.forEach((f) => {
        if (f.id === 'root') return;
        if (!isBulk && item && f.id === item.id) return; // single: hide self
        select.innerHTML += `<option value="${f.id}">${this.escape(f.name)}</option>`;
      });
    } catch (e) {
      console.error(e);
    }

    modal.classList.add('open');

    submitBtn.onclick = async () => {
      const targetId = select.value;
      modal.classList.remove('open');
      if (!isBulk) {
        await window.App.moveItem(item.id, type, targetId);
        return;
      }
      // Bulk: one endpoint call per item ID (no paths), single refresh at end.
      let ok = 0;
      let fail = 0;
      for (const s of bulkItems) {
        try {
          if (s.kind === 'folder') {
            await Api.post('/api/folders/move', { id: s.id, target_parent_id: targetId });
          } else {
            await Api.post('/api/files/move', { id: s.id, target_folder_id: targetId });
          }
          ok++;
        } catch (e) {
          fail++;
        }
      }
      UI.clearSelection();
      try {
        await window.App.refreshCurrentView();
      } catch (e) {
        console.error('Refresh after bulk move failed:', e);
      }
      UI.toast(
        fail ? `${ok} item dipindahkan, ${fail} gagal.` : `${ok} item dipindahkan.`,
        fail ? 'error' : 'success'
      );
    };
  },

  getFileIconSvg(file, size = 40) {
    const ext = (file.extension || '').toLowerCase();
    let color = 'var(--text-muted)';

    if (['pdf'].includes(ext)) color = '#ef4444';
    else if (['doc', 'docx'].includes(ext)) color = '#3b82f6';
    else if (['xls', 'xlsx', 'csv'].includes(ext)) color = '#10b981';
    else if (['ppt', 'pptx'].includes(ext)) color = '#f59e0b';
    else if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) color = '#8b5cf6';
    else if (['mp4', 'mkv', 'webm', 'mov'].includes(ext)) color = '#ec4899';
    else if (['mp3', 'wav', 'ogg', 'aac'].includes(ext)) color = '#06b6d4';

    return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>`;
  },

  formatBytes(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  },

  escape(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
  }
};
