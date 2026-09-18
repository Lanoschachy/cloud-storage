/**
 * Upload Manager
 * Manages drag & drop, file selection, queueing, progress tracking,
 * and the floating bottom-right upload panel.
 */
const Uploader = {
  queue: [],
  isUploading: false,
  panel: null,
  bodyEl: null,
  countEl: null,

  init() {
    this.panel = document.getElementById('uploadPanel');
    this.bodyEl = document.getElementById('uploadPanelBody');
    this.countEl = document.getElementById('uploadPanelCount');
    this.fileInput = document.getElementById('fileInput');

    // Panel collapse/expand toggle
    const header = document.getElementById('uploadPanelHeader');
    if (header) {
      header.addEventListener('click', (e) => {
        if (e.target.closest('#uploadPanelCloseBtn')) {
          this.hidePanel();
          return;
        }
        this.bodyEl.style.display = this.bodyEl.style.display === 'none' ? 'flex' : 'none';
      });
    }

    // Drag and drop setup on window
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
      window.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
      }, false);
    });

    window.addEventListener('drop', (e) => {
      const dt = e.dataTransfer;
      if (!dt) return;
      // Internal cloud-item MOVE drags carry our MIME marker and NO os files.
      // They are handled by folder drop targets — never treat them as uploads.
      const types = dt.types || [];
      for (let i = 0; i < types.length; i++) {
        if (types[i] === 'application/x-cloud-item') return;
      }
      const files = dt.files;
      if (files && files.length > 0) {
        this.addFiles(Array.from(files));
      }
    });

    if (this.fileInput) {
      this.fileInput.addEventListener('change', (e) => {
        if (e.target.files && e.target.files.length > 0) {
          this.addFiles(Array.from(e.target.files));
          e.target.value = '';
        }
      });
    }
  },

  addFiles(files) {
    const currentFolderId = window.App ? window.App.currentFolderId : 'root';
    
    files.forEach((file) => {
      const item = {
        id: 'up_' + Math.random().toString(36).substr(2, 9),
        file: file,
        name: file.name,
        size: file.size,
        folderId: currentFolderId,
        status: 'pending', // pending, uploading, success, error
        progress: 0,
        error: null,
      };
      this.queue.push(item);
      this.renderQueueItem(item);
    });

    this.showPanel();
    this.processQueue();
  },

  showPanel() {
    if (this.panel) {
      this.panel.classList.add('open');
      this.bodyEl.style.display = 'flex';
      this.updateCount();

      // Mobile: hide or shift FAB so it doesn't collide with upload panel
      const fab = document.querySelector('.fab-btn');
      if (fab && window.innerWidth <= 768) {
        fab.style.opacity = '0';
        fab.style.pointerEvents = 'none';
        fab.style.transform = 'scale(0.8)';
      }
    }
  },

  hidePanel() {
    if (this.panel) {
      this.panel.classList.remove('open');
      const fab = document.querySelector('.fab-btn');
      if (fab) {
        fab.style.opacity = '1';
        fab.style.pointerEvents = 'auto';
        fab.style.transform = 'scale(1)';
      }
    }
  },

  updateCount() {
    if (!this.countEl) return;
    const completed = this.queue.filter((i) => i.status === 'success').length;
    this.countEl.textContent = `(${completed}/${this.queue.length})`;
  },

  renderQueueItem(item) {
    const el = document.createElement('div');
    el.id = item.id;
    el.className = 'upload-queue-item';
    el.innerHTML = `
      <div class="upload-item-name" title="${item.name}">${item.name}</div>
      <div class="upload-item-status progress" id="${item.id}_status">Menunggu...</div>
    `;
    this.bodyEl.prepend(el);
  },

  updateItemStatus(item) {
    const statusEl = document.getElementById(`${item.id}_status`);
    if (!statusEl) return;

    if (item.status === 'uploading') {
      statusEl.className = 'upload-item-status progress';
      statusEl.textContent = `${item.progress}%`;
    } else if (item.status === 'success') {
      statusEl.className = 'upload-item-status success';
      statusEl.textContent = '100% ✓';
    } else if (item.status === 'error') {
      statusEl.className = 'upload-item-status error';
      statusEl.textContent = 'Gagal ✕';
      statusEl.title = item.error || 'Terjadi kesalahan';
    }
    this.updateCount();
  },

  async processQueue() {
    if (this.isUploading) return;

    const nextItem = this.queue.find((i) => i.status === 'pending');
    if (!nextItem) {
      this.isUploading = false;
      return;
    }

    this.isUploading = true;
    nextItem.status = 'uploading';
    this.updateItemStatus(nextItem);

    const formData = new FormData();
    formData.append('file', nextItem.file);
    formData.append('folder_id', nextItem.folderId);

    try {
      await Api.upload('/api/files/upload', formData, (progress) => {
        nextItem.progress = progress;
        this.updateItemStatus(nextItem);
      });

      nextItem.status = 'success';
      this.updateItemStatus(nextItem);

      // Single source of truth: re-fetch latest folder data so the new
      // file appears instantly. Refresh failure must not mark upload failed.
      if (window.App && typeof window.App.refreshCurrentView === 'function') {
        try {
          await window.App.refreshCurrentView();
        } catch (refreshErr) {
          console.error('Refresh after upload failed:', refreshErr);
        }
      }
    } catch (err) {
      nextItem.status = 'error';
      nextItem.error = err.message;
      this.updateItemStatus(nextItem);
    } finally {
      this.isUploading = false;
      this.processQueue();
    }
  }
};
