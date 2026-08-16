(function () {
    const app = window.NEWSLETTER_APP;
    const canvas = document.getElementById('newsletterCanvas');
    const titleInput = document.getElementById('newsletterTitle');
    const saveState = document.getElementById('saveState');
    const settingsForm = document.getElementById('settingsForm');
    const settingsEmpty = document.getElementById('settingsEmpty');
    const mediaModal = new bootstrap.Modal(document.getElementById('mediaModal'));
    const cropModalEl = document.getElementById('cropModal');
    const cropModal = new bootstrap.Modal(cropModalEl);
    const cropImage = document.getElementById('cropImage');
    const zoomSlider = document.getElementById('zoomSlider');
    let cropper = null;
    let selected = null;
    let selectedImageTarget = null;
    let saveTimer = null;

    const state = {
        meta: Object.assign({}, app.payload.newsletter),
        sections: (app.payload.sections || []).map(section => ({
            id: Number(section.id),
            title: section.title || '',
            section_type: section.section_type || 'content',
            settings: section.settings || {},
            blocks: (section.blocks || []).map(block => normalizeBlock(block))
        }))
    };

    function normalizeBlock(block) {
        return {
            id: block.id ? Number(block.id) : tempId(),
            block_type: block.block_type || 'text',
            content: block.content || {},
            settings: block.settings || {},
            media: block.media || null,
            temp: !block.id
        };
    }

    function tempId() {
        return -Math.floor(Math.random() * 1000000000);
    }

    function defaultBlock(type) {
        const arabic = (state.meta.direction || 'rtl') === 'rtl';
        const base = { id: tempId(), block_type: type, content: {}, settings: {}, temp: true };
        if (type === 'headline') base.content = { text: arabic ? 'عنوان النشرة' : 'Write a strong headline', size: 28, align: 'start', url: '' };
        if (type === 'text') base.content = { html: arabic ? '<p>اكتب النص هنا.</p>' : '<p>Write your paragraph here.</p>', align: 'start' };
        if (type === 'article') base.content = { category: arabic ? 'أخبار البانتري' : 'Company News', headline: arabic ? 'عنوان المقال' : 'Article headline', description: arabic ? 'وصف قصير للمقال.' : 'Short description that summarizes the article.', url: '', button_text: arabic ? 'اقرأ المزيد' : 'Read More', image_id: null, image_url: '', image_alt: '' };
        if (type === 'image') base.content = { image_id: null, image_url: app.appUrl + '/assets/img/placeholder.svg', alt: '' };
        if (type === 'button') base.content = { text: arabic ? 'اقرأ المزيد' : 'Read More', url: '' };
        if (type === 'divider') base.content = {};
        return base;
    }

    function render() {
        canvas.innerHTML = '';
        canvas.setAttribute('dir', state.meta.direction || 'rtl');
        state.sections.forEach((section, sectionIndex) => {
            const sectionEl = document.createElement('section');
            sectionEl.className = 'canvas-section';
            sectionEl.dataset.sectionIndex = String(sectionIndex);
            sectionEl.innerHTML = `<input class="section-title-input" value="${escapeAttr(section.title)}" aria-label="Section title"><div class="section-blocks"></div>`;
            sectionEl.querySelector('.section-title-input').addEventListener('input', event => {
                section.title = event.target.value;
                scheduleSave();
            });

            const list = sectionEl.querySelector('.section-blocks');
            section.blocks.forEach((block, blockIndex) => list.appendChild(renderBlock(block, sectionIndex, blockIndex)));
            canvas.appendChild(sectionEl);
            new Sortable(list, {
                group: 'blocks',
                handle: '.drag-handle',
                animation: 150,
                onEnd: syncSortFromDom
            });
        });

        new Sortable(canvas, {
            handle: '.section-title-input',
            animation: 150,
            onEnd: syncSectionsFromDom
        });
        renderFooter();
    }

    function renderFooter() {
        const footer = document.createElement('footer');
        footer.className = 'canvas-footer';
        footer.innerHTML = app.footerHtml || '';
        canvas.appendChild(footer);
    }

    function renderBlock(block, sectionIndex, blockIndex) {
        const el = document.createElement('div');
        el.className = 'canvas-block';
        if (selected && selected.block === block) el.classList.add('selected');
        el.dataset.sectionIndex = String(sectionIndex);
        el.dataset.blockIndex = String(blockIndex);
        el.innerHTML = `<div class="block-actions">
            <button class="btn btn-sm btn-light icon-btn drag-handle" type="button" title="Drag to reorder" aria-label="Drag to reorder">${moveIcon()}</button>
            <button class="btn btn-sm btn-light icon-btn" data-action="duplicate" type="button" title="Duplicate block" aria-label="Duplicate block">${duplicateIcon()}</button>
            <button class="btn btn-sm btn-outline-danger icon-btn" data-action="delete" type="button" title="Delete block" aria-label="Delete block">${trashIcon()}</button>
        </div><div class="block-body"></div>`;
        el.addEventListener('click', event => {
            if (!event.target.closest('.block-actions')) selectBlock(sectionIndex, blockIndex);
        });
        el.querySelector('[data-action="duplicate"]').addEventListener('click', () => duplicateBlock(sectionIndex, blockIndex));
        el.querySelector('[data-action="delete"]').addEventListener('click', () => deleteBlock(sectionIndex, blockIndex));

        const body = el.querySelector('.block-body');
        if (block.block_type === 'headline') {
            body.innerHTML = `<h2 class="editable-text" dir="auto" contenteditable="true" style="text-align:${escapeAttr(block.content.align || 'start')};font-size:${Number(block.content.size || 28)}px">${escapeHtml(block.content.text || '')}</h2>`;
            bindText(body.querySelector('.editable-text'), value => block.content.text = value);
        } else if (block.block_type === 'text') {
            body.innerHTML = `<div class="editable-text" dir="auto" contenteditable="true" style="text-align:${escapeAttr(block.content.align || 'start')}">${block.content.html || ''}</div>`;
            bindText(body.querySelector('.editable-text'), value => block.content.html = value, true);
        } else if (block.block_type === 'image') {
            const url = block.content.image_url || mediaUrl(block.media) || app.appUrl + '/assets/img/placeholder.svg';
            body.innerHTML = `<img class="image-block-img ${url.includes('placeholder.svg') ? 'image-placeholder' : ''}" src="${escapeAttr(url)}" alt="${escapeAttr(block.content.alt || '')}" ${imageAttrs(block)}>`;
            body.querySelector('img').addEventListener('click', event => {
                event.stopPropagation();
                selectBlock(sectionIndex, blockIndex);
                selectedImageTarget = { block, contentKey: 'image_id', urlKey: 'image_url', altKey: 'alt' };
                openImageSettings();
            });
        } else if (block.block_type === 'button') {
            body.innerHTML = `<a class="btn btn-primary editable-text" contenteditable="true" href="${escapeAttr(block.content.url || '#')}">${escapeHtml(block.content.text || 'Read More')}</a>`;
            bindText(body.querySelector('.editable-text'), value => block.content.text = value);
        } else if (block.block_type === 'divider') {
            body.innerHTML = '<hr>';
        } else {
            const url = block.content.image_url || mediaUrl(block.media) || app.appUrl + '/assets/img/placeholder.svg';
            body.innerHTML = `<article>
                <img class="article-image ${url.includes('placeholder.svg') ? 'image-placeholder' : ''}" src="${escapeAttr(url)}" alt="${escapeAttr(block.content.image_alt || '')}" ${imageAttrs(block)}>
                <div class="article-category editable-text" dir="auto" contenteditable="true" data-field="category">${escapeHtml(block.content.category || '')}</div>
                <h3 class="article-headline editable-text" dir="auto" contenteditable="true" data-field="headline">${escapeHtml(block.content.headline || '')}</h3>
                <p class="article-description editable-text" dir="auto" contenteditable="true" data-field="description">${escapeHtml(block.content.description || '')}</p>
                <a href="${escapeAttr(block.content.url || '#')}" class="fw-bold">${escapeHtml(block.content.button_text || 'Read More')}</a>
            </article>`;
            body.querySelector('img').addEventListener('click', event => {
                event.stopPropagation();
                selectBlock(sectionIndex, blockIndex);
                selectedImageTarget = { block, contentKey: 'image_id', urlKey: 'image_url', altKey: 'image_alt' };
                openImageSettings();
            });
            body.querySelectorAll('[data-field]').forEach(item => {
                bindText(item, value => block.content[item.dataset.field] = value);
            });
        }

        return el;
    }

    function mediaUrl(media) {
        return media && media.file_path ? app.appUrl + '/' + media.file_path : '';
    }

    function imageAttrs(block) {
        const width = Number(block.content.image_width || (block.media && block.media.width) || 0);
        const height = Number(block.content.image_height || (block.media && block.media.height) || 0);
        if (!width || !height) return '';
        return `width="${width}" height="${height}" style="aspect-ratio:${width} / ${height}"`;
    }

    function bindText(el, setter, html) {
        el.addEventListener('input', () => {
            setter(html ? el.innerHTML : el.textContent);
            scheduleSave();
            renderSettings();
        });
    }

    function selectBlock(sectionIndex, blockIndex) {
        selected = { sectionIndex, blockIndex, block: state.sections[sectionIndex].blocks[blockIndex] };
        document.querySelectorAll('.canvas-block').forEach(el => el.classList.remove('selected'));
        const el = canvas.querySelector(`[data-section-index="${sectionIndex}"][data-block-index="${blockIndex}"]`);
        if (el) el.classList.add('selected');
        renderSettings();
    }

    function renderSettings() {
        if (!selected) {
            settingsEmpty.classList.remove('d-none');
            settingsForm.classList.add('d-none');
            return;
        }
        settingsEmpty.classList.add('d-none');
        settingsForm.classList.remove('d-none');
        const block = selected.block;
        const c = block.content;
        let html = `<div class="text-muted small">Block type: ${escapeHtml(block.block_type)}</div>`;
        if (block.block_type === 'headline') {
            html += input('Headline', 'text', c.text || '', 'text') + input('Link', 'url', c.url || '', 'url') + input('Font size', 'number', c.size || 28, 'size') + alignmentControl(c.align || 'start');
        } else if (block.block_type === 'text') {
            html += `<label>Text HTML<textarea class="form-control" data-field="html" rows="7">${escapeHtml(c.html || '')}</textarea></label>` + alignmentControl(c.align || 'start');
        } else if (block.block_type === 'article') {
            html += input('Category', 'text', c.category || '', 'category') + input('Headline', 'text', c.headline || '', 'headline') + textarea('Description', c.description || '', 'description') + input('Article URL', 'url', c.url || '', 'url') + input('Button text', 'text', c.button_text || 'Read More', 'button_text') + imageControls();
        } else if (block.block_type === 'image') {
            html += imageControls();
        } else if (block.block_type === 'button') {
            html += input('Button text', 'text', c.text || '', 'text') + input('Button URL', 'url', c.url || '', 'url');
        } else {
            html += '<p class="text-muted">No settings for this block.</p>';
        }
        settingsForm.innerHTML = html;
        settingsForm.querySelectorAll('[data-field]').forEach(inputEl => {
            inputEl.addEventListener('input', () => {
                c[inputEl.dataset.field] = inputEl.value;
                scheduleSave();
            });
            inputEl.addEventListener('change', () => {
                render();
                keepSelection();
            });
        });
        const choose = document.getElementById('chooseImageBtn');
        if (choose) choose.addEventListener('click', () => {
            selectedImageTarget = imageTargetFor(block);
            mediaModal.show();
        });
        const crop = document.getElementById('cropImageBtn');
        if (crop) crop.addEventListener('click', openCrop);
        const remove = document.getElementById('removeImageBtn');
        if (remove) remove.addEventListener('click', () => {
            const target = imageTargetFor(block);
            block.content[target.contentKey] = null;
            block.content[target.urlKey] = app.appUrl + '/assets/img/placeholder.svg';
            block.media = null;
            scheduleSave();
            render();
            keepSelection();
        });
    }

    function keepSelection() {
        if (selected) selectBlock(selected.sectionIndex, selected.blockIndex);
    }

    function imageTargetFor(block) {
        return block.block_type === 'article'
            ? { block, contentKey: 'image_id', urlKey: 'image_url', altKey: 'image_alt' }
            : { block, contentKey: 'image_id', urlKey: 'image_url', altKey: 'alt' };
    }

    function imageControls() {
        const block = selected.block;
        const target = imageTargetFor(block);
        return `<label>Alt text <input class="form-control" data-field="${target.altKey}" value="${escapeAttr(block.content[target.altKey] || '')}"></label>
            <div class="d-grid gap-2">
                <button class="btn btn-outline-primary" id="chooseImageBtn" type="button">Replace / Choose Image</button>
                <button class="btn btn-outline-secondary" id="cropImageBtn" type="button">Crop / Rotate</button>
                <button class="btn btn-outline-danger" id="removeImageBtn" type="button">Remove Image</button>
            </div>`;
    }

    function openImageSettings() {
        renderSettings();
    }

    function input(label, type, value, field) {
        return `<label>${label}<input class="form-control" type="${type}" data-field="${field}" value="${escapeAttr(value)}"></label>`;
    }

    function textarea(label, value, field) {
        return `<label>${label}<textarea class="form-control" data-field="${field}" rows="4">${escapeHtml(value)}</textarea></label>`;
    }

    function select(label, field, value, options) {
        return `<label>${label}<select class="form-select" data-field="${field}">${options.map(option => `<option value="${option}" ${option === value ? 'selected' : ''}>${option}</option>`).join('')}</select></label>`;
    }

    function alignmentControl(value) {
        return `<div><label class="form-label">Alignment</label><div class="align-control" data-field="align">
            ${alignButton('start', value, alignLeftIcon(), 'Start')}
            ${alignButton('center', value, alignCenterIcon(), 'Center')}
            ${alignButton('end', value, alignRightIcon(), 'End')}
        </div></div>`;
    }

    function alignButton(value, active, icon, label) {
        return `<button class="btn btn-outline-secondary align-button ${value === active ? 'active' : ''}" type="button" data-align="${value}" title="${label}" aria-label="${label}">${icon}</button>`;
    }

    function duplicateBlock(sectionIndex, blockIndex) {
        const clone = JSON.parse(JSON.stringify(state.sections[sectionIndex].blocks[blockIndex]));
        clone.id = tempId();
        clone.temp = true;
        state.sections[sectionIndex].blocks.splice(blockIndex + 1, 0, clone);
        scheduleSave();
        render();
    }

    function deleteBlock(sectionIndex, blockIndex) {
        if (!confirm('Delete this block?')) return;
        state.sections[sectionIndex].blocks.splice(blockIndex, 1);
        selected = null;
        scheduleSave();
        render();
        renderSettings();
    }

    function syncSortFromDom() {
        const newSections = [];
        canvas.querySelectorAll('.canvas-section').forEach(sectionEl => {
            const oldSection = state.sections[Number(sectionEl.dataset.sectionIndex)];
            const blocks = [];
            sectionEl.querySelectorAll('.canvas-block').forEach(blockEl => {
                blocks.push(state.sections[Number(blockEl.dataset.sectionIndex)].blocks[Number(blockEl.dataset.blockIndex)]);
            });
            newSections.push(Object.assign({}, oldSection, { blocks }));
        });
        state.sections = newSections;
        scheduleSave();
        render();
    }

    function syncSectionsFromDom() {
        const sections = [];
        canvas.querySelectorAll('.canvas-section').forEach(sectionEl => {
            sections.push(state.sections[Number(sectionEl.dataset.sectionIndex)]);
        });
        state.sections = sections;
        scheduleSave();
        render();
    }

    function scheduleSave() {
        saveState.textContent = 'Saving...';
        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveNow, 1000);
    }

    async function saveNow() {
        state.meta.title = titleInput.value;
        document.querySelectorAll('.meta-field').forEach(field => {
            state.meta[field.dataset.meta] = field.value;
        });
        try {
            const response = await fetch(app.appUrl + '/api/newsletters/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': app.csrf },
                body: JSON.stringify({ newsletter_id: app.newsletterId, meta: state.meta, sections: state.sections })
            });
            const data = await response.json();
            if (!data.ok) throw new Error(data.message || 'Save failed');
            saveState.textContent = 'Saved';
        } catch (error) {
            saveState.textContent = 'Save failed';
            console.error(error);
        }
    }

    function openCrop() {
        if (!selected) return;
        selectedImageTarget = imageTargetFor(selected.block);
        const url = selected.block.content[selectedImageTarget.urlKey] || mediaUrl(selected.block.media);
        if (!url || url.includes('placeholder.svg')) {
            mediaModal.show();
            return;
        }
        cropImage.src = url;
        cropModal.show();
    }

    cropModalEl.addEventListener('shown.bs.modal', () => {
        if (cropper) cropper.destroy();
        cropper = new Cropper(cropImage, {
            viewMode: 1,
            autoCropArea: 1,
            responsive: true,
            background: false
        });
        zoomSlider.value = '1';
    });
    cropModalEl.addEventListener('hidden.bs.modal', () => {
        if (cropper) cropper.destroy();
        cropper = null;
    });

    document.querySelectorAll('[data-ratio]').forEach(button => {
        button.addEventListener('click', () => {
            if (!cropper) return;
            const ratio = Number(button.dataset.ratio);
            cropper.setAspectRatio(Number.isNaN(ratio) ? NaN : ratio);
        });
    });
    document.getElementById('rotateLeftBtn').addEventListener('click', () => cropper && cropper.rotate(-90));
    document.getElementById('rotateRightBtn').addEventListener('click', () => cropper && cropper.rotate(90));
    document.getElementById('resetCropBtn').addEventListener('click', () => cropper && cropper.reset());
    zoomSlider.addEventListener('input', () => {
        if (!cropper) return;
        cropper.zoomTo(Number(zoomSlider.value));
    });
    document.getElementById('applyCropBtn').addEventListener('click', async () => {
        if (!cropper || !selectedImageTarget) return;
        const block = selectedImageTarget.block;
        const croppedCanvas = cropper.getCroppedCanvas({
            maxWidth: 2400,
            maxHeight: 2400,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });
        if (!croppedCanvas) {
            alert('The image could not be cropped.');
            return;
        }
        const blob = await new Promise(resolve => croppedCanvas.toBlob(resolve, 'image/jpeg', 0.88));
        if (!blob) {
            alert('The image could not be cropped.');
            return;
        }
        const formData = new FormData();
        formData.append('csrf', app.csrf);
        formData.append('source_media_id', block.content[selectedImageTarget.contentKey] || '');
        formData.append('image', blob, 'crop.jpg');
        const response = await fetch(app.appUrl + '/api/media/crop-canvas.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (!data.ok) {
            alert(data.message || 'The image could not be cropped.');
            return;
        }
        applyMediaToBlock(data.media);
        cropModal.hide();
    });

    document.getElementById('uploadForm').addEventListener('submit', async event => {
        event.preventDefault();
        const formData = new FormData(event.target);
        formData.append('csrf', app.csrf);
        const response = await fetch(app.appUrl + '/api/media/upload.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (!data.ok) {
            alert(data.message || 'The image could not be uploaded.');
            return;
        }
        prependMedia(data.media);
        applyMediaToBlock(data.media);
        event.target.reset();
        mediaModal.hide();
    });

    document.getElementById('mediaGrid').addEventListener('click', event => {
        const card = event.target.closest('.media-card');
        if (!card) return;
        applyMediaToBlock({
            id: Number(card.dataset.mediaId),
            file_path: card.dataset.url.replace(app.appUrl + '/', ''),
            alt_text: card.dataset.alt || '',
            width: Number(card.dataset.width || 0),
            height: Number(card.dataset.height || 0)
        });
        mediaModal.hide();
    });

    function prependMedia(media) {
        const card = document.createElement('button');
        card.className = 'media-card text-start p-0';
        card.type = 'button';
        card.dataset.mediaId = media.id;
        card.dataset.url = app.appUrl + '/' + media.file_path;
        card.dataset.alt = media.alt_text || '';
        card.dataset.width = media.width || '';
        card.dataset.height = media.height || '';
        card.innerHTML = `<img src="${escapeAttr(app.appUrl + '/' + media.file_path)}" alt="${escapeAttr(media.alt_text || '')}"><span class="meta d-block text-truncate">${escapeHtml(media.file_name || 'Image')}</span>`;
        document.getElementById('mediaGrid').prepend(card);
    }

    function applyMediaToBlock(media) {
        if (!selectedImageTarget) return;
        const block = selectedImageTarget.block;
        block.media = media;
        block.content[selectedImageTarget.contentKey] = media.id;
        block.content[selectedImageTarget.urlKey] = media.file_path ? app.appUrl + '/' + media.file_path : '';
        block.content.image_width = Number(media.width || 0);
        block.content.image_height = Number(media.height || 0);
        if (!block.content[selectedImageTarget.altKey]) block.content[selectedImageTarget.altKey] = media.alt_text || '';
        scheduleSave();
        render();
        keepSelection();
    }

    document.querySelectorAll('[data-add-block]').forEach(button => {
        button.addEventListener('click', () => {
            if (!state.sections.length) state.sections.push({ id: tempId(), title: app.defaultSectionTitle || 'نشرة البانتري', section_type: 'content', settings: {}, blocks: [] });
            const sectionIndex = selected ? selected.sectionIndex : state.sections.length - 1;
            state.sections[sectionIndex].blocks.push(defaultBlock(button.dataset.addBlock));
            scheduleSave();
            render();
        });
    });

    document.getElementById('addSectionBtn').addEventListener('click', () => {
            state.sections.push({ id: tempId(), title: '', section_type: 'content', settings: {}, blocks: [] });
        scheduleSave();
        render();
    });
    document.getElementById('manualSaveBtn').addEventListener('click', saveNow);
    document.getElementById('desktopPreview').addEventListener('click', () => canvas.classList.remove('mobile'));
    document.getElementById('mobilePreview').addEventListener('click', () => canvas.classList.add('mobile'));
    titleInput.addEventListener('input', scheduleSave);
    document.querySelectorAll('.meta-field').forEach(field => {
        field.addEventListener('input', scheduleSave);
        field.addEventListener('change', () => {
            state.meta[field.dataset.meta] = field.value;
            render();
        });
    });

    document.getElementById('testEmailBtn').addEventListener('click', async () => {
        await saveNow();
        const to = prompt('Send test email to:');
        if (!to) return;
        const response = await fetch(app.appUrl + '/api/email/send-test.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': app.csrf },
            body: JSON.stringify({ newsletter_id: app.newsletterId, to })
        });
        const data = await response.json();
        alert(data.message || (data.ok ? 'Test sent.' : 'Test failed.'));
    });

    document.getElementById('queueSendBtn').addEventListener('click', async () => {
        await saveNow();
        if (!confirm('Publish this newsletter and queue it for all active subscribers?')) return;
        const response = await fetch(app.appUrl + '/api/email/queue.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': app.csrf },
            body: JSON.stringify({ newsletter_id: app.newsletterId })
        });
        const data = await response.json();
        alert(data.message || 'Queued.');
    });

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
    }

    settingsForm.addEventListener('click', event => {
        const button = event.target.closest('[data-align]');
        if (!button || !selected) return;
        selected.block.content.align = button.dataset.align;
        scheduleSave();
        render();
        keepSelection();
    });

    function moveIcon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8 7l4-4 4 4"/><path d="M12 3v18"/><path d="M8 17l4 4 4-4"/></svg>';
    }

    function duplicateIcon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"/><rect x="4" y="4" width="12" height="12" rx="2"/></svg>';
    }

    function trashIcon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 15h10l1-15"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>';
    }

    function alignLeftIcon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16"/><path d="M4 12h10"/><path d="M4 18h14"/></svg>';
    }

    function alignCenterIcon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16"/><path d="M7 12h10"/><path d="M5 18h14"/></svg>';
    }

    function alignRightIcon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16"/><path d="M10 12h10"/><path d="M6 18h14"/></svg>';
    }

    render();
})();
