<?php
if (!defined('APP_INIT')) {
    exit('Direct access not allowed.');
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Private Cloud Storage</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="stylesheet" href="/assets/css/components.css">
  <link rel="stylesheet" href="/assets/css/preview.css">
</head>
<body>

  <!-- Screen: Login -->
  <div id="loginScreen" style="display:none;min-height:100vh;width:100vw;align-items:center;justify-content:center;background:var(--bg-primary);padding:20px;">
    <div style="background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-lg);box-shadow:var(--shadow-lg);width:100%;max-width:400px;padding:36px;display:flex;flex-direction:column;gap:24px;">
      <div style="text-align:center">
        <div style="width:48px;height:48px;background:linear-gradient(135deg, #00D26A, #00945c);border-radius:12px;display:inline-flex;align-items:center;justify-content:center;color:#04120B;margin-bottom:12px">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"></path></svg>
        </div>
        <h2 style="font-size:1.4rem;font-weight:700;color:var(--text-primary)">Private Cloud</h2>
        <p style="font-size:0.85rem;color:var(--text-secondary);margin-top:4px">Akses penyimpanan pribadi terenkripsi</p>
      </div>

      <div id="loginError" style="display:none;background:rgba(239, 68, 68, 0.15);border:1px solid var(--danger);color:var(--danger);padding:10px 14px;border-radius:var(--radius-md);font-size:0.88rem;text-align:center;"></div>

      <form id="loginForm" style="display:flex;flex-direction:column;gap:16px;">
        <div class="form-group">
          <label>Username</label>
          <input type="text" name="username" class="form-control" placeholder="Nama pengguna" required autocomplete="username">
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" class="form-control" placeholder="Kata sandi" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:6px">Masuk ke Penyimpanan</button>
      </form>
    </div>
  </div>

  <!-- Screen: Main Dashboard Container -->
  <div id="appContainer" class="app-container" style="display:none;">
    
    <!-- Sidebar Backdrop (Mobile/Tablet) -->
    <div id="sidebarBackdrop" class="sidebar-backdrop"></div>

    <!-- Sidebar Navigation -->
    <aside id="sidebar" class="sidebar">
      <div class="brand">
        <div class="brand-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"></path></svg>
        </div>
        <span>Private Cloud</span>
      </div>

      <div class="sidebar-actions">
        <button class="btn-upload" onclick="document.getElementById('fileInput').click()">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
          <span>Unggah Berkas</span>
        </button>
        <button class="btn btn-secondary" id="newFolderBtn" style="width:100%">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path><line x1="12" y1="11" x2="12" y2="17"></line><line x1="9" y1="14" x2="15" y2="14"></line></svg>
          <span>Folder Baru</span>
        </button>
      </div>

      <ul class="nav-links">
        <li class="nav-item active" data-view="files" onclick="App.loadView('files', 'root')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
          <span>My Cloud</span>
        </li>
        <li class="nav-item" data-view="starred" onclick="App.loadView('starred')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
          <span>Berbintang</span>
        </li>
        <li class="nav-item" data-view="recent" onclick="App.loadView('recent')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
          <span>Terbaru</span>
        </li>
        <li class="nav-item" data-view="trash" onclick="App.loadView('trash')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
          <span>Tong Sampah</span>
        </li>
        <li class="nav-item" data-view="activity" onclick="App.loadView('activity')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
          <span>Aktivitas</span>
        </li>
        <li class="nav-item" data-view="settings" onclick="App.loadView('settings')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
          <span>Pengaturan</span>
        </li>
      </ul>

      <!-- Storage Widget -->
      <div class="storage-status-card">
        <div class="title">Penyimpanan</div>
        <div class="progress-bar-wrap">
          <div id="storageBarFill" class="progress-bar-fill" style="width: 0%"></div>
        </div>
        <div class="storage-text">
          <span id="storageUsedText">0 B terpakai</span>
          <span id="storageAvailText">0 B sisa</span>
        </div>
        <div id="storageSourceText" style="font-size:0.72rem;color:var(--text-muted);margin-top:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="Sumber perhitungan kuota">
          Menghitung kuota...
        </div>
      </div>
    </aside>

    <!-- Main Workspace -->
    <main class="main-wrapper">
      
      <!-- Topbar -->
      <header class="topbar">
        <div class="topbar-left">
          <button id="mobileMenuBtn" class="mobile-menu-btn" title="Menu">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
          </button>
          
          <div class="search-box">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" id="globalSearchInput" placeholder="Cari berkas & folder...">
          </div>
        </div>

        <div class="topbar-right">
          <button id="themeToggleBtn" class="icon-btn" title="Ganti Tema">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
          </button>
          <div style="display:flex;align-items:center;gap:8px">
            <span id="usernameDisplay" style="font-size:0.9rem;font-weight:600;color:var(--text-primary)">User</span>
            <button class="icon-btn" onclick="App.logout()" title="Keluar">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </button>
          </div>
        </div>
      </header>

      <!-- Action Toolbar (Breadcrumbs & View Options) -->
      <div class="action-toolbar">
        <div id="breadcrumbsWrap" class="breadcrumbs">
          <span class="breadcrumb-item active">My Cloud</span>
        </div>
        <div class="toolbar-controls">
          <button id="viewGridBtn" class="icon-btn" title="Tampilan Grid" style="color:var(--accent)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
          </button>
          <button id="viewListBtn" class="icon-btn" title="Tampilan Daftar">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
          </button>
        </div>
      </div>

      <!-- Main Dynamic Content Body -->
      <div id="mainContentBody" class="content-body"></div>

    </main>
  </div>

  <!-- Selection Toolbar (multi-select bulk actions) -->
  <div id="selectionToolbar" class="selection-toolbar" style="display:none" role="toolbar" aria-label="Aksi item terpilih">
    <span id="selCount" class="sel-count">0 dipilih</span>
    <div class="sel-actions">
      <button id="selDownloadBtn" class="btn btn-secondary sel-btn" title="Unduh file terpilih">⬇ <span class="sel-btn-label">Unduh</span></button>
      <button id="selMoveBtn" class="btn btn-secondary sel-btn" title="Pindahkan item terpilih">📁 <span class="sel-btn-label">Pindah</span></button>
      <button id="selStarBtn" class="btn btn-secondary sel-btn" title="Bintang untuk file terpilih">⭐ <span class="sel-btn-label">Bintang</span></button>
      <button id="selDeleteBtn" class="btn btn-danger sel-btn" title="Hapus item terpilih ke sampah">🗑 <span class="sel-btn-label">Hapus</span></button>
      <button id="selClearBtn" class="btn btn-secondary sel-btn" title="Batalkan pilihan">✕ <span class="sel-btn-label">Batal</span></button>
    </div>
  </div>

  <!-- Mobile Bottom Navigation Bar -->
  <nav class="mobile-nav">
    <button class="mobile-nav-item active" onclick="App.loadView('files', 'root')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
      <span>Cloud</span>
    </button>
    <button class="mobile-nav-item" onclick="App.loadView('starred')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
      <span>Bintang</span>
    </button>
    <button class="mobile-nav-item" onclick="App.loadView('recent')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
      <span>Terbaru</span>
    </button>
    <button class="mobile-nav-item" onclick="App.loadView('trash')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
      <span>Sampah</span>
    </button>
  </nav>

  <!-- Mobile Floating Action Button (FAB) -->
  <button class="fab-btn" onclick="document.getElementById('fileInput').click()" title="Unggah Berkas">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
  </button>

  <!-- Hidden File Input for Multiple Uploads -->
  <input type="file" id="fileInput" multiple style="display:none">

  <!-- Upload Progress Panel (Bottom Right) -->
  <div id="uploadPanel" class="upload-panel">
    <div id="uploadPanelHeader" class="upload-panel-header">
      <div class="upload-panel-title">
        <span>Mengunggah Berkas</span>
        <span id="uploadPanelCount">(0/0)</span>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <span style="font-size:0.8rem;color:var(--text-muted)">▾</span>
        <button id="uploadPanelCloseBtn" class="modal-close-btn" style="padding:2px 6px;font-size:0.85rem" title="Tutup Panel">✕</button>
      </div>
    </div>
    <div id="uploadPanelBody" class="upload-panel-body"></div>
  </div>

  <!-- Desktop Context Menu -->
  <div id="contextMenu" class="context-menu"></div>

  <!-- Mobile Bottom Sheet Actions -->
  <div id="sheetBackdrop" class="bottom-sheet-backdrop"></div>
  <div id="bottomSheet" class="bottom-sheet">
    <div class="bottom-sheet-handle"></div>
    <div id="sheetHeader" class="bottom-sheet-header">Opsi Berkas</div>
    <div id="sheetActions" style="display:flex;flex-direction:column;gap:4px"></div>
  </div>

  <!-- Universal Preview Modal -->
  <div id="previewModal" class="preview-modal">
    <div class="preview-header">
      <div class="preview-title-wrap">
        <button id="previewCloseBtn" class="preview-btn" style="padding:6px 10px" title="Tutup (Esc)">←</button>
        <div>
          <div id="previewFilename" class="preview-filename">Nama Berkas</div>
          <div id="previewFilemeta" class="preview-filemeta">0 KB • application/octet-stream</div>
        </div>
      </div>
      <div class="preview-actions">
        <button id="previewDownloadBtn" class="preview-btn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
          <span>Unduh</span>
        </button>
      </div>
    </div>
    <div id="previewStage" class="preview-stage"></div>

    <!-- Floating Zoom Toolbar for Images -->
    <div id="imageZoomToolbar" class="image-zoom-toolbar" style="display:none">
      <button class="zoom-btn" id="zoomOutBtn" title="Zoom Out (–)">−</button>
      <span class="zoom-level" id="zoomLevelDisplay">100%</span>
      <button class="zoom-btn" id="zoomInBtn" title="Zoom In (+)">+</button>
      <button class="zoom-btn zoom-btn-fit" id="zoomResetBtn" title="Fit to screen">Fit</button>
    </div>
  </div>

  <!-- Modal: Buat Folder Baru -->
  <div id="newFolderModal" class="modal-backdrop">
    <div class="modal-box">
      <div class="modal-header">
        <h3>Folder Baru</h3>
        <button class="modal-close-btn" data-close-modal>✕</button>
      </div>
      <form id="newFolderForm" onsubmit="event.preventDefault();">
        <div class="modal-content">
          <div class="form-group">
            <label>Nama Folder</label>
            <input type="text" id="newFolderInput" class="form-control" placeholder="Contoh: Dokumen Kerja" required autocomplete="off">
          </div>
          <div id="newFolderError" class="form-error" style="display:none"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
          <button type="submit" id="newFolderSubmitBtn" class="btn btn-primary">Buat Folder</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal: Ubah Nama -->
  <div id="renameModal" class="modal-backdrop">
    <div class="modal-box">
      <div class="modal-header">
        <h3>Ubah Nama</h3>
        <button class="modal-close-btn" data-close-modal>✕</button>
      </div>
      <form id="renameForm" onsubmit="event.preventDefault();">
        <div class="modal-content">
          <div class="form-group">
            <label>Nama Baru</label>
            <input type="text" id="renameInput" class="form-control" required autocomplete="off">
          </div>
          <div id="renameError" class="form-error" style="display:none"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
          <button type="submit" id="renameSubmitBtn" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal: Pindahkan Item -->
  <div id="moveModal" class="modal-backdrop">
    <div class="modal-box">
      <div class="modal-header">
        <h3>Pindahkan ke Folder</h3>
        <button class="modal-close-btn" data-close-modal>✕</button>
      </div>
      <div class="modal-content">
        <div class="form-group">
          <label>Pilih Folder Tujuan</label>
          <select id="moveFolderSelect" class="form-control"></select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-close-modal>Batal</button>
        <button id="moveSubmitBtn" class="btn btn-primary">Pindahkan</button>
      </div>
    </div>
  </div>

  <!-- Modal: Detail Berkas -->
  <div id="detailsModal" class="modal-backdrop">
    <div class="modal-box">
      <div class="modal-header">
        <h3>Detail Berkas</h3>
        <button class="modal-close-btn" data-close-modal>✕</button>
      </div>
      <div id="detailsModalBody" class="modal-content"></div>
      <div class="modal-footer">
        <button class="btn btn-primary" data-close-modal>Tutup</button>
      </div>
    </div>
  </div>

  <!-- Toast Notification Container -->
  <div id="toastContainer" class="toast-container"></div>

  <!-- JavaScript Modules -->
  <script src="/assets/js/api.js"></script>
  <script src="/assets/js/preview.js"></script>
  <script src="/assets/js/uploader.js"></script>
  <script src="/assets/js/ui.js"></script>
  <script src="/assets/js/app.js"></script>
</body>
</html>
