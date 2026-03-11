import Alpine from 'alpinejs';
window.Alpine = Alpine;

Alpine.data('albumPage', (uploadUrl, csrfToken, defaultCols = 3, saveColsUrl = '') => {
    return {
        dragging: false,
        uploading: false,
        progress: 0,
        statusText: '',
        cols: defaultCols,

        setCols(n) {
            this.cols = n;
            // Sauvegarder en base via AJAX
            if (saveColsUrl) {
                fetch(saveColsUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ cols: n }),
                }).catch(() => {}); // Silencieux en cas d'erreur réseau
            }
        },

        handleDrop(event) {
            this.dragging = false;
            const files = event.dataTransfer.files;
            if (files.length) this.upload(files);
        },

        handleFileInput(event) {
            const files = event.target.files;
            if (files.length) this.upload(files);
        },

        upload(files) {
            const formData = new FormData();
            Array.from(files).forEach(f => formData.append('files[]', f));
            formData.append('_token', csrfToken);
            this.uploading = true;
            this.progress = 0;
            this.statusText = `Upload de ${files.length} fichier(s)…`;

            const xhr = new XMLHttpRequest();
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    this.progress = Math.round((e.loaded / e.total) * 100);
                    this.statusText = `${this.progress}% envoyé…`;
                }
            });
            xhr.addEventListener('load', () => {
                this.uploading = false;
                if (xhr.status === 302 || xhr.status === 200) window.location.reload();
            });
            xhr.addEventListener('error', () => {
                this.uploading = false;
                this.statusText = "Erreur lors de l'upload.";
            });
            xhr.open('POST', uploadUrl);
            xhr.send(formData);
        }
    };
});

Alpine.start();
