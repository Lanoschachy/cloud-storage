/**
 * Universal Preview Manager (Client-side)
 * Handles full-screen inline viewing for Images, Videos, Audio, PDF, Text/Code,
 * and graceful download card fallbacks for Office/Unsupported files.
 */
const PreviewManager = {
  activeFile: null,
  zoomScale: 1,
  panX: 0,
  panY: 0,
  isPanning: false,
  startX: 0,
  startY: 0,

  init() {
    this.modal = document.getElementById('previewModal');
    this.stage = document.getElementById('previewStage');
    this.filenameEl = document.getElementById('previewFilename');
    this.filemetaEl = document.getElementById('previewFilemeta');
    this.downloadBtn = document.getElementById('previewDownloadBtn');
    this.closeBtn = document.getElementById('previewCloseBtn');
    this.zoomToolbar = document.getElementById('imageZoomToolbar');
    this.zoomLevelDisplay = document.getElementById('zoomLevelDisplay');
    this.zoomInBtn = document.getElementById('zoomInBtn');
    this.zoomOutBtn = document.getElementById('zoomOutBtn');
    this.zoomResetBtn = document.getElementById('zoomResetBtn');

    if (this.closeBtn) {
      this.closeBtn.addEventListener('click', () => this.close());
    }

    if (this.zoomInBtn) {
      this.zoomInBtn.addEventListener('click', () => this.zoom(0.25));
    }

    if (this.zoomOutBtn) {
      this.zoomOutBtn.addEventListener('click', () => this.zoom(-0.25));
    }

    if (this.zoomResetBtn) {
      this.zoomResetBtn.addEventListener('click', () => this.resetZoom());
    }

    // Close on Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.modal && this.modal.classList.contains('open')) {
        e.preventDefault();
        this.close();
      }
    });
  },

  async open(file) {
    this.activeFile = file;
    this.filenameEl.textContent = file.name;
    this.filemetaEl.textContent = `${this.formatBytes(file.size)} • ${file.mime_type || 'Unknown'}`;
    
    // Set direct download action
    this.downloadBtn.onclick = () => {
      window.location.href = `/api/files/download?id=${file.id}`;
    };

    this.stage.innerHTML = '<div style="color:var(--text-muted)">Memuat pratinjau...</div>';
    if (this.zoomToolbar) this.zoomToolbar.style.display = 'none';
    this.modal.classList.add('open');

    const previewType = file.preview_type || 'unsupported';
    const streamUrl = `/api/files/preview?id=${file.id}`;

    switch (previewType) {
      case 'image':
        this.renderImage(streamUrl, file.name);
        break;
      case 'video':
        this.renderVideo(streamUrl, file.mime_type);
        break;
      case 'audio':
        this.renderAudio(streamUrl, file.name, file.mime_type);
        break;
      case 'pdf':
        this.renderPdf(streamUrl);
        break;
      case 'text':
        await this.renderText(file.id);
        break;
      case 'office':
        this.renderOfficeFallback(file);
        break;
      default:
        this.renderGenericFallback(file);
        break;
    }
  },

  close() {
    if (this.modal) {
      this.modal.classList.remove('open');
      this.stage.innerHTML = '';
      if (this.zoomToolbar) this.zoomToolbar.style.display = 'none';
      this.resetZoom();
      this.activeFile = null;
    }
  },

  renderImage(url, alt) {
    this.resetZoom();
    if (this.zoomToolbar) this.zoomToolbar.style.display = 'flex';

    const container = document.createElement('div');
    container.className = 'image-zoom-viewport';

    const img = document.createElement('img');
    img.src = url;
    img.alt = alt;
    img.id = 'previewActiveImg';
    img.draggable = false;
    img.className = 'zoomable-image';

    // Double click to toggle zoom (fit vs 150%)
    img.addEventListener('dblclick', (e) => {
      e.preventDefault();
      if (this.zoomScale > 1) {
        this.resetZoom();
      } else {
        this.zoomScale = 1.5;
        this.applyZoom();
      }
    });

    // Mouse drag / pan
    img.addEventListener('mousedown', (e) => {
      if (this.zoomScale <= 1) return;
      e.preventDefault();
      this.isPanning = true;
      this.startX = e.clientX - this.panX;
      this.startY = e.clientY - this.panY;
      img.style.cursor = 'grabbing';
    });

    window.addEventListener('mousemove', (e) => {
      if (!this.isPanning) return;
      e.preventDefault();
      this.panX = e.clientX - this.startX;
      this.panY = e.clientY - this.startY;
      this.applyZoom();
    });

    window.addEventListener('mouseup', () => {
      if (this.isPanning) {
        this.isPanning = false;
        const activeImg = document.getElementById('previewActiveImg');
        if (activeImg) activeImg.style.cursor = this.zoomScale > 1 ? 'grab' : 'default';
      }
    });

    // Touch support for mobile pan
    let touchStartX = 0;
    let touchStartY = 0;
    img.addEventListener('touchstart', (e) => {
      if (this.zoomScale <= 1 || e.touches.length !== 1) return;
      this.isPanning = true;
      touchStartX = e.touches[0].clientX - this.panX;
      touchStartY = e.touches[0].clientY - this.panY;
    }, { passive: true });

    window.addEventListener('touchmove', (e) => {
      if (!this.isPanning || e.touches.length !== 1) return;
      this.panX = e.touches[0].clientX - touchStartX;
      this.panY = e.touches[0].clientY - touchStartY;
      this.applyZoom();
    }, { passive: true });

    window.addEventListener('touchend', () => {
      this.isPanning = false;
    });

    container.appendChild(img);
    this.stage.innerHTML = '';
    this.stage.appendChild(container);
    this.applyZoom();
  },

  zoom(delta) {
    const newScale = Math.min(Math.max(0.5, this.zoomScale + delta), 4);
    this.zoomScale = parseFloat(newScale.toFixed(2));
    if (this.zoomScale <= 1) {
      this.panX = 0;
      this.panY = 0;
    }
    this.applyZoom();
  },

  resetZoom() {
    this.zoomScale = 1;
    this.panX = 0;
    this.panY = 0;
    this.applyZoom();
  },

  applyZoom() {
    const img = document.getElementById('previewActiveImg');
    if (img) {
      img.style.transform = `translate(${this.panX}px, ${this.panY}px) scale(${this.zoomScale})`;
      img.style.cursor = this.zoomScale > 1 ? 'grab' : 'default';
    }
    if (this.zoomLevelDisplay) {
      this.zoomLevelDisplay.textContent = `${Math.round(this.zoomScale * 100)}%`;
    }
  },

  renderVideo(url, mimeType) {
    const video = document.createElement('video');
    video.controls = true;
    video.autoplay = true;
    video.playsInline = true;
    
    const source = document.createElement('source');
    source.src = url;
    if (mimeType) source.type = mimeType;
    video.appendChild(source);

    this.stage.innerHTML = '';
    this.stage.appendChild(video);
  },

  renderAudio(url, name, mimeType) {
    const wrapper = document.createElement('div');
    wrapper.className = 'audio-player-wrapper';
    wrapper.innerHTML = `
      <div class="audio-icon-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>
      </div>
      <div style="font-weight:600;font-size:1rem;color:var(--text-primary);text-align:center;word-break:break-all;">${this.escape(name)}</div>
      <audio controls autoplay style="width:100%">
        <source src="${url}" type="${mimeType || 'audio/mpeg'}">
        Browser Anda tidak mendukung pemutaran audio HTML5.
      </audio>
    `;
    this.stage.innerHTML = '';
    this.stage.appendChild(wrapper);
  },

  renderPdf(url) {
    const isMobile = window.innerWidth <= 768;
    this.stage.innerHTML = '';

    if (isMobile) {
      // Mobile fallback card + inline frame container
      const wrapper = document.createElement('div');
      wrapper.style.display = 'flex';
      wrapper.style.flexDirection = 'column';
      wrapper.style.alignItems = 'center';
      wrapper.style.gap = '16px';
      wrapper.style.width = '100%';
      wrapper.style.height = '100%';

      wrapper.innerHTML = `
        <div style="background:var(--bg-surface);padding:12px 16px;border-radius:var(--radius-md);border:1px solid var(--border-color);font-size:0.85rem;color:var(--text-secondary);text-align:center;width:100%;max-width:500px">
          <div>Beberapa browser seluler memerlukan penampil eksternal untuk PDF.</div>
          <div style="margin-top:8px;display:flex;gap:8px;justify-content:center">
            <button class="btn btn-primary" style="padding:6px 14px;font-size:0.85rem" onclick="window.open('${url}', '_blank')">Buka di Tab Baru ↗</button>
            <button class="btn btn-secondary" style="padding:6px 14px;font-size:0.85rem" onclick="window.location.href='${url}&download=1'">Unduh Berkas ⬇</button>
          </div>
        </div>
        <iframe src="${url}#toolbar=0" frameborder="0" style="width:100%;flex:1;min-height:350px;border-radius:var(--radius-md);background:#fff"></iframe>
      `;
      this.stage.appendChild(wrapper);
    } else {
      const frame = document.createElement('iframe');
      frame.src = url;
      frame.setAttribute('frameborder', '0');
      frame.style.width = '100%';
      frame.style.height = '100%';
      frame.style.maxWidth = '1000px';
      this.stage.appendChild(frame);
    }
  },

  async renderText(fileId) {
    try {
      const res = await Api.get(`/api/files/text?id=${fileId}`);
      const textContainer = document.createElement('div');
      textContainer.className = 'text-preview-container';

      const header = document.createElement('div');
      header.className = 'text-preview-header';
      header.textContent = `${res.data.name} (${res.data.extension || 'text'})`;

      const content = document.createElement('div');
      content.className = 'text-preview-content';
      // Use textContent to completely prevent any XSS script execution
      content.textContent = res.data.content;

      textContainer.appendChild(header);
      textContainer.appendChild(content);

      this.stage.innerHTML = '';
      this.stage.appendChild(textContainer);
    } catch (err) {
      this.stage.innerHTML = `<div style="color:var(--danger)">Gagal memuat pratinjau teks: ${this.escape(err.message)}</div>`;
    }
  },

  renderOfficeFallback(file) {
    const card = document.createElement('div');
    card.className = 'fallback-preview-card';
    card.innerHTML = `
      <div class="fallback-icon">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
      </div>
      <div class="fallback-title">${this.escape(file.name)}</div>
      <div class="fallback-desc">
        Dokumen Microsoft Office (${(file.extension || '').toUpperCase()}) memerlukan aplikasi lokal untuk dibuka secara akurat.
      </div>
      <button class="btn btn-primary" onclick="window.location.href='/api/files/download?id=${file.id}'">
        Unduh Dokumen (${this.formatBytes(file.size)})
      </button>
    `;
    this.stage.innerHTML = '';
    this.stage.appendChild(card);
  },

  renderGenericFallback(file) {
    const card = document.createElement('div');
    card.className = 'fallback-preview-card';
    card.innerHTML = `
      <div class="fallback-icon">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
      </div>
      <div class="fallback-title">${this.escape(file.name)}</div>
      <div class="fallback-desc">
        Pratinjau langsung tidak tersedia untuk format berkas ini.
      </div>
      <button class="btn btn-primary" onclick="window.location.href='/api/files/download?id=${file.id}'">
        Unduh Berkas (${this.formatBytes(file.size)})
      </button>
    `;
    this.stage.innerHTML = '';
    this.stage.appendChild(card);
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
