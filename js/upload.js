// ═══════════════════════════════════════════════════════════════
// UPLOAD ENGINE PRO - Chunked Upload avec queue parallèle
// ═══════════════════════════════════════════════════════════════

const CHUNK_SIZE   = 10 * 1024 * 1024; // 10 Mo
const MAX_FILE     = 100 * 1024 * 1024 * 1024; // 100 Go
const CONCURRENT   = 2; // fichiers uploadés en parallèle
const MAX_RETRIES  = 3;
const ENDPOINT     = '/admin/upload_chunk.php';
const BLOCKED_EXT  = ['php','phtml','php3','php4','php5','php7','phar','exe','bat','cmd','sh','bash','com','msi','scr','vbs','wsf','ps1'];

// ── Helpers ───────────────────────────────────────────────────
function fmtBytes(b) {
    if (b === 0) return '0 o';
    const u = ['o','Ko','Mo','Go','To'];
    const i = Math.floor(Math.log(b) / Math.log(1024));
    return (b / Math.pow(1024, i)).toFixed(1) + ' ' + u[i];
}

function fmtSpeed(bps) {
    if (bps < 1024)    return bps.toFixed(0) + ' o/s';
    if (bps < 1048576) return (bps / 1024).toFixed(1) + ' Ko/s';
    return (bps / 1048576).toFixed(1) + ' Mo/s';
}

function fmtETA(sec) {
    if (!isFinite(sec) || sec <= 0) return '';
    if (sec < 60)  return Math.ceil(sec) + 's';
    if (sec < 3600) return Math.floor(sec / 60) + ' min ' + Math.ceil(sec % 60) + 's';
    return Math.floor(sec / 3600) + 'h ' + Math.floor((sec % 3600) / 60) + 'min';
}

function fileExt(name) {
    return name.split('.').pop().toLowerCase();
}

function fileIcon(ext) {
    const map = {
        pdf:'bi-file-earmark-pdf-fill', doc:'bi-file-earmark-word-fill', docx:'bi-file-earmark-word-fill',
        xls:'bi-file-earmark-excel-fill', xlsx:'bi-file-earmark-excel-fill',
        ppt:'bi-file-earmark-ppt-fill', pptx:'bi-file-earmark-ppt-fill',
        txt:'bi-file-earmark-text-fill', csv:'bi-file-earmark-text-fill',
        jpg:'bi-file-earmark-image-fill', jpeg:'bi-file-earmark-image-fill',
        png:'bi-file-earmark-image-fill', gif:'bi-file-earmark-image-fill',
        svg:'bi-file-earmark-image-fill', webp:'bi-file-earmark-image-fill',
        mp3:'bi-file-earmark-music-fill', wav:'bi-file-earmark-music-fill', flac:'bi-file-earmark-music-fill',
        mp4:'bi-file-earmark-play-fill', avi:'bi-file-earmark-play-fill', mkv:'bi-file-earmark-play-fill',
        zip:'bi-file-earmark-zip-fill', rar:'bi-file-earmark-zip-fill', '7z':'bi-file-earmark-zip-fill',
        json:'bi-file-earmark-code-fill', xml:'bi-file-earmark-code-fill',
        html:'bi-file-earmark-code-fill', css:'bi-file-earmark-code-fill', js:'bi-file-earmark-code-fill',
        py:'bi-file-earmark-code-fill', java:'bi-file-earmark-code-fill',
    };
    return map[ext] || 'bi-file-earmark-fill';
}

function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

// ── UploadItem (un fichier) ──────────────────────────────────
class UploadItem {
    constructor(file, index, folderId) {
        this.file = file;
        this.index = index;
        this.folderId = folderId || 0;
        this.id = Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        this.totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        this.uploaded = 0;
        this.status = 'pending';
        this.error = null;
        this.speed = 0;
        this.eta = 0;
        this.progress = 0;
        this.startTime = 0;
        this.retries = 0;
    }

    async upload(onProgress) {
        this.status = 'uploading';
        this.startTime = Date.now();
        let chunk = 0;

        while (chunk < this.totalChunks && this.status === 'uploading') {
            const result = await this._sendChunk(chunk);
            if (!result.ok) {
                // Erreur serveur définitive (pas de retry sur les erreurs logiques)
                if (result.fatal) {
                    this.status = 'error';
                    this.error = result.error;
                    onProgress(this);
                    return;
                }
                // Erreur réseau → retry
                if (this.retries < MAX_RETRIES) {
                    this.retries++;
                    await new Promise(r => setTimeout(r, 1000 * this.retries));
                    continue;
                }
                this.status = 'error';
                this.error = result.error || ('Échec après ' + MAX_RETRIES + ' tentatives');
                onProgress(this);
                return;
            }
            this.retries = 0;
            chunk++;
            this.uploaded = Math.min(chunk * CHUNK_SIZE, this.file.size);
            this.progress = (this.uploaded / this.file.size) * 100;
            const elapsed = (Date.now() - this.startTime) / 1000;
            this.speed = this.uploaded / elapsed;
            this.eta = (this.file.size - this.uploaded) / this.speed;
            onProgress(this);
        }

        if (this.status === 'uploading') {
            this.status = 'done';
            this.progress = 100;
            onProgress(this);
        }
    }

    async _sendChunk(index) {
        const start = index * CHUNK_SIZE;
        const end   = Math.min(start + CHUNK_SIZE, this.file.size);
        const chunk = this.file.slice(start, end);

        const fd = new FormData();
        fd.append('chunk', chunk);
        fd.append('chunkIndex', index);
        fd.append('totalChunks', this.totalChunks);
        fd.append('fileName', this.file.name);
        fd.append('fileId', this.id);
        fd.append('folder_id', this.folderId);

        try {
            const res = await fetch(ENDPOINT, { method: 'POST', body: fd });

            if (!res.ok) {
                let msg = 'Erreur serveur (' + res.status + ')';
                try {
                    const errData = await res.json();
                    if (errData.error) msg = errData.error;
                } catch {}
                return { ok: false, error: msg, fatal: res.status === 403 || res.status === 400 };
            }

            const data = await res.json();

            if (!data.success) {
                return { ok: false, error: data.error || 'Erreur inconnue', fatal: true };
            }

            return { ok: true, data };
        } catch (e) {
            return { ok: false, error: 'Réseau : ' + e.message, fatal: false };
        }
    }

    cancel() { this.status = 'cancelled'; }
}

// ── Queue Manager ─────────────────────────────────────────────
class UploadQueue {
    constructor(concurrent = CONCURRENT) {
        this.items = [];
        this.concurrent = concurrent;
        this.running = 0;
        this.onUpdate = null;
    }

    add(file, folderId) {
        if (file.size > MAX_FILE) {
            showToast(file.name + ' dépasse 100 Go', 'error');
            return null;
        }
        const ext = fileExt(file.name);
        if (BLOCKED_EXT.includes(ext)) {
            showToast(file.name + ' : type .' + ext + ' non autorisé', 'error');
            return null;
        }
        if (file.size === 0) {
            showToast(file.name + ' : fichier vide', 'error');
            return null;
        }
        const item = new UploadItem(file, this.items.length, folderId);
        this.items.push(item);
        return item;
    }

    remove(index) {
        this.items.splice(index, 1);
        this.items.forEach((it, i) => it.index = i);
    }

    clear() {
        this.items = [];
    }

    async startAll(onUpdate) {
        this.onUpdate = onUpdate;
        const pending = this.items.filter(i => i.status === 'pending' || i.status === 'error');
        for (const item of pending) {
            item.status = 'pending';
            item.uploaded = 0;
            item.progress = 0;
            item.retries = 0;
        }
        await this._runNext();
    }

    async _runNext() {
        while (this.running < this.concurrent) {
            const next = this.items.find(i => i.status === 'pending');
            if (!next) break;
            this.running++;
            next.upload((item) => this.onUpdate && this.onUpdate(this)).then(() => {
                this.running--;
                this._runNext();
            });
        }
    }

    cancelAll() {
        this.items.forEach(i => { if (i.status === 'uploading' || i.status === 'pending') i.cancel(); });
    }

    get globalProgress() {
        const active = this.items.filter(i => i.status !== 'cancelled');
        if (active.length === 0) return { pct: 0, uploaded: 0, total: 0, speed: 0, done: 0, errors: 0 };
        const total = active.reduce((s, i) => s + i.file.size, 0);
        const uploaded = active.reduce((s, i) => s + i.uploaded, 0);
        const speed = active.filter(i => i.status === 'uploading').reduce((s, i) => s + i.speed, 0);
        return {
            pct: total > 0 ? (uploaded / total) * 100 : 0,
            uploaded, total, speed,
            done: active.filter(i => i.status === 'done').length,
            errors: active.filter(i => i.status === 'error').length,
            total_files: active.length,
        };
    }
}

// ── DOM ───────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const dropZone       = document.getElementById('dropZone');
    const fileInput      = document.getElementById('fileInput');
    const fileQueue      = document.getElementById('fileQueue');
    const uploadBtn      = document.getElementById('uploadBtn');
    const cancelBtn      = document.getElementById('cancelBtn');
    const clearBtn       = document.getElementById('clearBtn');
    const globalProg     = document.getElementById('globalProgress');
    const globalBar      = document.getElementById('globalProgressBar');
    const globalPercent  = document.getElementById('globalProgressPercent');
    const globalLabel    = document.getElementById('globalProgressLabel');
    const globalInfo     = document.getElementById('globalProgressInfo');

    const queue = new UploadQueue();
    let isUploading = false;

    // ── Drag & Drop ──────────────────────────────────────────
    ['dragenter','dragover'].forEach(e => dropZone.addEventListener(e, ev => {
        ev.preventDefault(); dropZone.classList.add('dragover');
    }));
    ['dragleave','drop'].forEach(e => dropZone.addEventListener(e, ev => {
        ev.preventDefault(); dropZone.classList.remove('dragover');
    }));
    dropZone.addEventListener('drop', e => { addFiles(Array.from(e.dataTransfer.files)); });
    fileInput.addEventListener('change', () => { addFiles(Array.from(fileInput.files)); fileInput.value = ''; });

    function addFiles(files) {
        files.forEach(f => queue.add(f, 0));
        render();
    }

    // ── Render queue ─────────────────────────────────────────
    function render() {
        if (queue.items.length === 0) {
            fileQueue.innerHTML = '';
            clearBtn.style.display = 'none';
            uploadBtn.disabled = true;
            return;
        }
        clearBtn.style.display = isUploading ? 'none' : '';
        uploadBtn.disabled = isUploading;

        fileQueue.innerHTML = queue.items.map((item, i) => {
            const ext = fileExt(item.file.name);
            const icon = fileIcon(ext);
            const statusBadge = statusHTML(item);
            const progressBar = item.status === 'uploading' || item.status === 'done'
                ? `<div class="progress-modern" style="height:4px;margin-top:6px;"><div class="progress-bar-modern" style="width:${item.progress.toFixed(1)}%"></div></div>`
                : '';
            const meta = item.status === 'uploading'
                ? `<span class="upload-meta">${fmtBytes(item.uploaded)} / ${fmtBytes(item.file.size)} · ${fmtSpeed(item.speed)} · ${fmtETA(item.eta)}</span>`
                : item.status === 'done'
                ? `<span class="upload-meta" style="color:var(--success);">${fmtBytes(item.file.size)} · Terminé</span>`
                : item.status === 'error'
                ? `<span class="upload-meta" style="color:var(--danger);">${escapeHtml(item.error)}</span>`
                : `<span class="upload-meta">${fmtBytes(item.file.size)}</span>`;

            const removeBtn = (item.status === 'pending' || item.status === 'error') && !isUploading
                ? `<button class="btn-modern btn-ghost btn-sm" onclick="window._removeFile(${i})" title="Retirer"><i class="bi bi-x-lg"></i></button>`
                : '';

            return `
                <div class="upload-queue-item status-${item.status}">
                    <div class="upload-queue-icon"><i class="bi ${icon}"></i></div>
                    <div class="upload-queue-info">
                        <div class="upload-queue-name">${escapeHtml(item.file.name)}</div>
                        <div class="upload-queue-meta">${meta}${progressBar}</div>
                    </div>
                    <div class="upload-queue-status">${statusBadge}</div>
                    <div class="upload-queue-actions">${removeBtn}</div>
                </div>
            `;
        }).join('');
    }

    function statusHTML(item) {
        switch (item.status) {
            case 'pending':    return '<span class="badge-modern badge-primary"><i class="bi bi-clock"></i></span>';
            case 'uploading':  return '<span class="badge-modern badge-warning"><i class="bi bi-arrow-repeat spin"></i></span>';
            case 'done':       return '<span class="badge-modern badge-success"><i class="bi bi-check-lg"></i></span>';
            case 'error':      return '<span class="badge-modern badge-danger"><i class="bi bi-exclamation-triangle"></i></span>';
            case 'cancelled':  return '<span class="badge-modern" style="background:var(--light-3);color:var(--text-muted);"><i class="bi bi-slash-circle"></i></span>';
            default: return '';
        }
    }

    window._removeFile = function(i) { queue.remove(i); render(); };

    // ── Upload ───────────────────────────────────────────────
    uploadBtn.addEventListener('click', async () => {
        if (queue.items.length === 0) return;

        // Lire le dossier sélectionné avant de commencer
        const folderEl = document.getElementById('folderSelect');
        const folderId = folderEl ? parseInt(folderEl.value) || 0 : 0;
        queue.items.forEach(item => { item.folderId = folderId; });

        isUploading = true;
        uploadBtn.style.display = 'none';
        cancelBtn.style.display = '';
        clearBtn.style.display = 'none';
        globalProg.style.display = '';

        await queue.startAll(() => {
            const g = queue.globalProgress;
            const pct = g.pct.toFixed(1) + '%';
            globalBar.style.width = pct;
            globalPercent.textContent = pct;
            globalLabel.textContent = g.done + g.errors < g.total_files
                ? `Envoi en cours (${g.done + g.errors}/${g.total_files})...`
                : 'Finalisation...';
            globalInfo.textContent = `${fmtBytes(g.uploaded)} / ${fmtBytes(g.total)} · ${fmtSpeed(g.speed)}`;
            render();
        });

        // Fini
        const g = queue.globalProgress;
        isUploading = false;
        cancelBtn.style.display = 'none';
        uploadBtn.style.display = '';
        uploadBtn.disabled = false;

        globalBar.style.width = '100%';
        globalPercent.textContent = '100%';
        globalLabel.textContent = `Terminé : ${g.done} réussi(s), ${g.errors} erreur(s)`;

        if (g.done > 0) showToast(`${g.done} fichier(s) uploadé(s) avec succès`, 'success');
        if (g.errors > 0) showToast(`${g.errors} fichier(s) en erreur`, 'error');

        render();
    });

    cancelBtn.addEventListener('click', () => {
        queue.cancelAll();
        isUploading = false;
        cancelBtn.style.display = 'none';
        uploadBtn.style.display = '';
        globalLabel.textContent = 'Annulé';
        showToast('Upload annulé', 'info');
        render();
    });

    clearBtn.addEventListener('click', () => {
        queue.clear();
        globalProg.style.display = 'none';
        globalBar.style.width = '0%';
        render();
    });

    render();
});
