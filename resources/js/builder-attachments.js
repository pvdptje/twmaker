(function () {
    window.builderImageAttachments = function builderImageAttachments() {
        return {
            modalities: [],
            visionAvailable: false,
            attachments: [],
            attachError: '',
            dragOver: false,
            maxAttachments: 3,
            allowedAttachMimes: ['image/png', 'image/jpeg', 'image/webp', 'image/gif'],
            updateAttachmentModalities(modalities) {
                this.modalities = Array.isArray(modalities) ? modalities : ['text'];
                this.visionAvailable = this.modalities.includes('image');
                if (!this.visionAvailable && this.attachments.length > 0) {
                    this.attachments = [];
                    this.attachError = 'Selected model does not accept images. Attachments cleared.';
                }
            },
            clearAttachments() {
                this.attachments = [];
                this.attachError = '';
                this.dragOver = false;
            },
            removeAttachment(index) {
                this.attachments.splice(index, 1);
            },
            async attachFile(file) {
                this.attachError = '';
                if (!file) return;
                if (!this.visionAvailable) {
                    this.attachError = 'Pick a vision-capable model to attach images.';
                    return;
                }
                if (this.attachments.length >= this.maxAttachments) {
                    this.attachError = `Up to ${this.maxAttachments} images per request.`;
                    return;
                }
                if (!file.type || !this.allowedAttachMimes.includes(file.type)) {
                    this.attachError = 'Only PNG, JPEG, GIF, or WebP images are supported.';
                    return;
                }
                try {
                    this.attachments.push(await this.downsizeImage(file));
                } catch (error) {
                    this.attachError = 'Could not read that image.';
                }
            },
            downsizeImage(file) {
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onerror = () => reject(reader.error);
                    reader.onload = () => {
                        const img = new Image();
                        img.onerror = () => reject(new Error('decode failed'));
                        img.onload = () => {
                            try {
                                const maxSide = 1568;
                                let { width, height } = img;
                                if (width > maxSide || height > maxSide) {
                                    if (width >= height) {
                                        height = Math.round(height * (maxSide / width));
                                        width = maxSide;
                                    } else {
                                        width = Math.round(width * (maxSide / height));
                                        height = maxSide;
                                    }
                                }
                                const canvas = document.createElement('canvas');
                                canvas.width = width;
                                canvas.height = height;
                                canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                                const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
                                const base64 = dataUrl.split(',')[1] || '';
                                resolve({ dataUrl, base64, mimeType: 'image/jpeg', name: file.name || 'screenshot.jpg' });
                            } catch (error) {
                                reject(error);
                            }
                        };
                        img.src = reader.result;
                    };
                    reader.readAsDataURL(file);
                });
            },
            handlePaste(event) {
                const items = event.clipboardData?.items;
                if (!items) return;
                for (const item of items) {
                    if (item.kind === 'file' && item.type && item.type.startsWith('image/')) {
                        event.preventDefault();
                        this.attachFile(item.getAsFile());
                        return;
                    }
                }
            },
            handleDrop(event) {
                const files = event.dataTransfer?.files;
                if (!files) return;
                for (const file of files) {
                    if (file.type && file.type.startsWith('image/')) {
                        this.attachFile(file);
                    }
                }
            },
            serializedAttachments() {
                return this.attachments.map((item) => ({ base64: item.base64, mime_type: item.mimeType }));
            },
        };
    };
})();
