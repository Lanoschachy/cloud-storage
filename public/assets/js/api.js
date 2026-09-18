/**
 * API Wrapper for Cloud Storage
 * Automatically handles JSON parsing, error checking, and CSRF token injection.
 */
const Api = {
  csrfToken: null,

  setCsrfToken(token) {
    this.csrfToken = token;
  },

  async request(url, options = {}) {
    options.headers = options.headers || {};
    
    // Inject CSRF Token for mutation calls
    if (this.csrfToken && ['POST', 'PUT', 'DELETE', 'PATCH'].includes((options.method || 'GET').toUpperCase())) {
      options.headers['X-CSRF-Token'] = this.csrfToken;
    }

    try {
      const response = await fetch(url, options);
      
      // Handle Unauthenticated
      if (response.status === 401 && !url.includes('/api/auth/login')) {
        window.dispatchEvent(new CustomEvent('auth:expired'));
        throw new Error('Sesi telah berakhir, silakan login kembali.');
      }

      const data = await response.json();
      
      if (!response.ok || data.success === false) {
        throw new Error(data.error || 'Terjadi kesalahan sistem.');
      }

      return data;
    } catch (err) {
      console.error('API Error:', err);
      throw err;
    }
  },

  get(url) {
    // no-store: list/metadata GETs must never serve a stale heuristic-cache
    // entry, otherwise mutations appear to "not update the UI".
    return this.request(url, { method: 'GET', cache: 'no-store' });
  },

  post(url, body) {
    return this.request(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
  },

  upload(url, formData, onProgress) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', url);

      if (this.csrfToken) {
        xhr.setRequestHeader('X-CSRF-Token', this.csrfToken);
      }

      if (xhr.upload && onProgress) {
        xhr.upload.addEventListener('progress', (e) => {
          if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            onProgress(percent);
          }
        });
      }

      xhr.onload = () => {
        try {
          const data = JSON.parse(xhr.responseText);
          if (xhr.status >= 200 && xhr.status < 300 && data.success !== false) {
            resolve(data);
          } else {
            reject(new Error(data.error || 'Gagal mengunggah file.'));
          }
        } catch (e) {
          reject(new Error('Format respon server tidak valid.'));
        }
      };

      xhr.onerror = () => reject(new Error('Koneksi jaringan terputus.'));
      xhr.send(formData);
    });
  }
};
