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
    const FONT_OPTIONS = [
        { label: 'Default', value: '' },
        { label: 'Tahoma', value: 'Tahoma, Arial, sans-serif' },
        { label: 'Arial', value: 'Arial, Helvetica, sans-serif' },
        { label: 'Segoe UI', value: '"Segoe UI", Tahoma, Arial, sans-serif' },
        { label: 'Georgia', value: 'Georgia, "Times New Roman", serif' },
        { label: 'Times New Roman', value: '"Times New Roman", Times, serif' },
        { label: 'Courier New', value: '"Courier New", Courier, monospace' }
    ];

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

    function templateBlock(type, content, settings) {
        return {
            id: tempId(),
            block_type: type,
            content: content || {},
            settings: settings || {},
            media: null,
            temp: true
        };
    }

    function pantryTemplateSections() {
        const placeholder = app.appUrl + '/assets/img/placeholder.svg';
        return [
            {
                id: tempId(),
                title: '',
                section_type: 'content',
                settings: { background: '#ffffff', accent: '#1faaaa' },
                blocks: [
                    templateBlock('headline', { text: 'يوليــــو ٢٠٢٦م', size: 24, align: 'center', url: '' }),
                    templateBlock('text', { align: 'center', html: '<p><strong>للناس اللي تحب بانتري عُمق وتجتمع حوله لتبادل أطراف الحديث وشرب القهوة أو الشاي وتناول ما لذ وطاب.</strong></p><p>كُتبت النشرة بواسطة: فريق عُمق الإبداعي</p>' }),
                    templateBlock('image', { image_id: null, image_url: placeholder, alt: 'صورة افتتاحية للنشرة' })
                ]
            },
            {
                id: tempId(),
                title: '',
                section_type: 'content',
                settings: { background: '#fbf8cc', accent: '#d4ce7c' },
                blocks: [
                    templateBlock('headline', { text: 'كلٌ منـــا بطل قصته الخاصة', size: 27, align: 'center', url: '' }),
                    templateBlock('text', { align: 'center', html: '<p><strong>اكتب هنا مقدمة قصيرة تفتح مساحة للحكايات والتجارب والمعرفة التي تريد مشاركتها في هذا العدد.</strong></p><p>بانتظار قصتك لتكون جزءًا من نشراتنا القادمة.</p>' }),
                    templateBlock('button', { text: 'شارك قصتك', url: '' })
                ]
            },
            {
                id: tempId(),
                title: '🧠 عادات العقل',
                section_type: 'content',
                settings: { background: '#ffffff', accent: '#1faaaa' },
                blocks: [
                    templateBlock('headline', { text: 'عنوان المقال الرئيسي', size: 30, align: 'start', url: '' }),
                    templateBlock('text', { align: 'start', html: '<p><strong>اكتب السؤال أو الجملة التي تفتح المقال هنا.</strong></p><p>ابدأ المقال بفقرة قريبة وواضحة، ثم رتّب الأفكار في فقرات قصيرة تجعل القراءة سهلة داخل البريد وعلى الهاتف.</p><p>كُتب المقال بواسطة: اسم الكاتب</p>' })
                ]
            },
            {
                id: tempId(),
                title: '🧠 فسحة للتعبير',
                section_type: 'content',
                settings: { background: '#f3e2d2', accent: '#b82023' },
                blocks: [
                    templateBlock('headline', { text: 'عنوان النص أو القصة', size: 28, align: 'start', url: '' }),
                    templateBlock('text', { align: 'start', html: '<p>اكتب هنا قصة أو تجربة شخصية بنبرة إنسانية. استخدم فقرات قصيرة حتى تبقى القراءة مريحة.</p><p>اسم المشارك</p>' })
                ]
            },
            {
                id: tempId(),
                title: '📝 من عجائب اللغة',
                section_type: 'content',
                settings: { background: '#ffffff', accent: '#5211a9' },
                blocks: [
                    templateBlock('text', { align: 'center', html: '<p><strong>ضع هنا اقتباسًا لغويًا، أبياتًا قصيرة، أو معلومة خفيفة.</strong></p><p>مشاركة من: اسم المشارك</p>' })
                ]
            },
            {
                id: tempId(),
                title: 'نتشارك المعرفة 📚',
                section_type: 'content',
                settings: { background: '#cdf7df', accent: '#01a4c6' },
                blocks: [
                    templateBlock('article', { category: 'مقال / رابط', headline: 'عنوان المادة المقترحة', description: 'اكتب وصفًا قصيرًا للمقال أو الرابط أو المصدر الذي تريد مشاركته.', url: '', button_text: 'اقرأ المزيد', image_id: null, image_url: placeholder, image_alt: 'صورة المادة' })
                ]
            }
        ];
    }

    async function applyPantryTemplate() {
        const hasContent = state.sections.some(section => section.title || (section.blocks && section.blocks.length));
        if (hasContent && !(await showConfirm({
            title: 'Apply template?',
            message: 'This will replace the current sections with the Pantry template.',
            confirmText: 'Apply template'
        }))) return;
        state.meta.direction = 'rtl';
        state.meta.title = state.meta.title && state.meta.title !== 'New Newsletter' ? state.meta.title : 'نشرة البانتري';
        state.meta.subject = state.meta.subject && state.meta.subject !== 'New Newsletter' ? state.meta.subject : 'نشرة البانتري';
        state.meta.preview_text = state.meta.preview_text || 'عدد جديد من نشرة البانتري.';
        titleInput.value = state.meta.title;
        document.querySelectorAll('.meta-field').forEach(field => {
            const key = field.dataset.meta;
            if (Object.prototype.hasOwnProperty.call(state.meta, key)) field.value = state.meta[key] || '';
        });
        state.sections = pantryTemplateSections();
        selected = null;
        scheduleSave();
        render();
        renderSettings();
    }

    function sectionTitlePlaceholder() {
        return (state.meta.direction || 'rtl') === 'rtl' ? 'عنوان القسم' : 'Section title';
    }

    function safeColor(value, fallback) {
        const color = String(value || '');
        return /^#[0-9a-fA-F]{6}$/.test(color) ? color : fallback;
    }

    function cloneSection(section) {
        const clone = JSON.parse(JSON.stringify(section));
        clone.id = tempId();
        clone.temp = true;
        clone.blocks = (clone.blocks || []).map(block => Object.assign({}, block, { id: tempId(), temp: true }));
        return clone;
    }

    function duplicateSection(sectionIndex) {
        state.sections.splice(sectionIndex + 1, 0, cloneSection(state.sections[sectionIndex]));
        selected = null;
        scheduleSave();
        render();
        renderSettings();
    }

    async function deleteSection(sectionIndex) {
        if (!(await showConfirm({
            title: 'Delete section?',
            message: 'This section and all blocks inside it will be removed from the newsletter.',
            confirmText: 'Delete section',
            danger: true
        }))) return;
        state.sections.splice(sectionIndex, 1);
        selected = null;
        scheduleSave();
        render();
        renderSettings();
    }

    function render() {
        canvas.innerHTML = '';
        canvas.setAttribute('dir', state.meta.direction || 'rtl');
        state.sections.forEach((section, sectionIndex) => {
            section.settings = section.settings || {};
            const background = safeColor(section.settings.background, '#ffffff');
            const accent = safeColor(section.settings.accent, '#dfe5ee');
            const sectionEl = document.createElement('section');
            sectionEl.className = 'canvas-section';
            sectionEl.dataset.sectionIndex = String(sectionIndex);
            sectionEl.style.background = background;
            sectionEl.style.borderTop = `4px solid ${accent}`;
            sectionEl.innerHTML = `<div class="section-header">
                <button class="btn btn-sm btn-light icon-btn section-drag-handle" type="button" title="Move section" aria-label="Move section">${moveIcon()}</button>
                <input class="section-title-input" value="${escapeAttr(section.title)}" placeholder="${escapeAttr(sectionTitlePlaceholder())}" aria-label="Section title">
                <div class="section-style-tools">
                    <label title="Section background"><span>Bg</span><input type="color" data-section-style="background" value="${escapeAttr(background)}" aria-label="Section background"></label>
                    <label title="Section accent"><span>Accent</span><input type="color" data-section-style="accent" value="${escapeAttr(accent)}" aria-label="Section accent"></label>
                    <button class="btn btn-sm btn-light icon-btn" data-section-action="duplicate" type="button" title="Duplicate section" aria-label="Duplicate section">${duplicateIcon()}</button>
                    <button class="btn btn-sm btn-outline-danger icon-btn" data-section-action="delete" type="button" title="Delete section" aria-label="Delete section">${trashIcon()}</button>
                </div>
            </div><div class="section-blocks"></div>`;
            sectionEl.querySelector('.section-title-input').addEventListener('input', event => {
                section.title = event.target.value;
                scheduleSave();
            });
            sectionEl.querySelector('[data-section-style="background"]').addEventListener('input', event => {
                section.settings.background = event.target.value;
                sectionEl.style.background = event.target.value;
                scheduleSave();
            });
            sectionEl.querySelector('[data-section-style="accent"]').addEventListener('input', event => {
                section.settings.accent = event.target.value;
                sectionEl.style.borderTop = `4px solid ${event.target.value}`;
                scheduleSave();
            });
            sectionEl.querySelector('[data-section-action="duplicate"]').addEventListener('click', () => duplicateSection(sectionIndex));
            sectionEl.querySelector('[data-section-action="delete"]').addEventListener('click', () => deleteSection(sectionIndex));

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
            handle: '.section-drag-handle',
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
        const align = escapeAttr(block.content.align || 'start');
        const font = escapeAttr(fontStack(block.content.font || ''));
        body.style.textAlign = block.content.align || 'start';
        body.style.fontFamily = fontStack(block.content.font || '');
        if (block.block_type === 'headline') {
            body.innerHTML = `<h2 class="editable-text" dir="auto" contenteditable="true" style="text-align:${align};font-size:${Number(block.content.size || 28)}px;font-family:${font}">${escapeHtml(block.content.text || '')}</h2>`;
            bindText(body.querySelector('.editable-text'), value => block.content.text = value);
        } else if (block.block_type === 'text') {
            body.innerHTML = `<div class="editable-text" dir="auto" contenteditable="true" style="text-align:${align};font-size:${Number(block.content.size || 16)}px;font-family:${font}">${block.content.html || ''}</div>`;
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
            body.innerHTML = `<a class="btn btn-primary editable-text" contenteditable="true" href="${escapeAttr(block.content.url || '#')}" style="font-size:${Number(block.content.size || 16)}px;font-family:${font}">${escapeHtml(block.content.text || 'Read More')}</a>`;
            bindText(body.querySelector('.editable-text'), value => block.content.text = value);
        } else if (block.block_type === 'divider') {
            body.innerHTML = '<hr>';
        } else {
            const url = block.content.image_url || mediaUrl(block.media) || app.appUrl + '/assets/img/placeholder.svg';
            body.innerHTML = `<article style="text-align:${align};font-family:${font}">
                <img class="article-image ${url.includes('placeholder.svg') ? 'image-placeholder' : ''}" src="${escapeAttr(url)}" alt="${escapeAttr(block.content.image_alt || '')}" ${imageAttrs(block)}>
                <div class="article-category editable-text" dir="auto" contenteditable="true" data-field="category" style="font-size:${Number(block.content.category_size || 12)}px">${escapeHtml(block.content.category || '')}</div>
                <h3 class="article-headline editable-text" dir="auto" contenteditable="true" data-field="headline" style="font-size:${Number(block.content.headline_size || 24)}px">${escapeHtml(block.content.headline || '')}</h3>
                <p class="article-description editable-text" dir="auto" contenteditable="true" data-field="description" style="font-size:${Number(block.content.description_size || 15)}px">${escapeHtml(block.content.description || '')}</p>
                <a href="${escapeAttr(block.content.url || '#')}" class="fw-bold" style="font-size:${Number(block.content.button_size || 15)}px">${escapeHtml(block.content.button_text || 'Read More')}</a>
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
        let html = `<div class="text-muted small">Editing: ${escapeHtml(blockLabel(block.block_type))}</div>`;
        if (block.block_type === 'headline') {
            html += input('Headline', 'text', c.text || '', 'text') + input('Link', 'url', c.url || '', 'url') + fontControl(c.font || '') + numberInput('Font size', c.size || 28, 'size', 10, 72) + alignmentControl(c.align || 'start');
        } else if (block.block_type === 'text') {
            html += `<label>Text<textarea class="form-control" data-field="html" data-format="plain-text" rows="7">${escapeHtml(htmlToPlainText(c.html || ''))}</textarea></label>` + fontControl(c.font || '') + numberInput('Text size', c.size || 16, 'size', 10, 48) + alignmentControl(c.align || 'start');
        } else if (block.block_type === 'article') {
            html += input('Category', 'text', c.category || '', 'category') + input('Headline', 'text', c.headline || '', 'headline') + textarea('Description', c.description || '', 'description') + input('Article URL', 'url', c.url || '', 'url') + input('Button text', 'text', c.button_text || 'Read More', 'button_text') + fontControl(c.font || '') + numberInput('Category size', c.category_size || 12, 'category_size', 9, 24) + numberInput('Headline size', c.headline_size || 24, 'headline_size', 14, 56) + numberInput('Description size', c.description_size || 15, 'description_size', 10, 36) + numberInput('Link size', c.button_size || 15, 'button_size', 10, 32) + alignmentControl(c.align || 'start') + imageControls();
        } else if (block.block_type === 'image') {
            html += imageControls() + alignmentControl(c.align || 'start');
        } else if (block.block_type === 'button') {
            html += input('Button text', 'text', c.text || '', 'text') + input('Button URL', 'url', c.url || '', 'url') + fontControl(c.font || '') + numberInput('Button text size', c.size || 16, 'size', 10, 36) + alignmentControl(c.align || 'start');
        } else {
            html += alignmentControl(c.align || 'start');
        }
        settingsForm.innerHTML = html;
        settingsForm.querySelectorAll('[data-field]').forEach(inputEl => {
            inputEl.addEventListener('input', () => {
                c[inputEl.dataset.field] = settingValue(inputEl);
                scheduleSave();
            });
            inputEl.addEventListener('change', () => {
                c[inputEl.dataset.field] = settingValue(inputEl);
                scheduleSave();
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

    function numberInput(label, value, field, min, max) {
        return `<label>${label}<input class="form-control" type="number" min="${min}" max="${max}" step="1" data-field="${field}" value="${escapeAttr(value)}"></label>`;
    }

    function textarea(label, value, field) {
        return `<label>${label}<textarea class="form-control" data-field="${field}" rows="4">${escapeHtml(value)}</textarea></label>`;
    }

    function select(label, field, value, options) {
        return `<label>${label}<select class="form-select" data-field="${field}">${options.map(option => {
            const optionValue = typeof option === 'string' ? option : option.value;
            const optionLabel = typeof option === 'string' ? option : option.label;
            return `<option value="${escapeAttr(optionValue)}" ${optionValue === value ? 'selected' : ''}>${escapeHtml(optionLabel)}</option>`;
        }).join('')}</select></label>`;
    }

    function fontControl(value) {
        return select('Font', 'font', value || '', FONT_OPTIONS);
    }

    function fontStack(value) {
        return FONT_OPTIONS.some(option => option.value === value) && value ? value : 'Arial, Helvetica, sans-serif';
    }

    function settingValue(inputEl) {
        return inputEl.dataset.format === 'plain-text' ? plainTextToHtml(inputEl.value) : inputEl.value;
    }

    function htmlToPlainText(html) {
        const div = document.createElement('div');
        div.innerHTML = html || '';
        div.querySelectorAll('br').forEach(br => br.replaceWith('\n'));
        div.querySelectorAll('p,h1,h2,h3,li,blockquote').forEach(el => {
            el.appendChild(document.createTextNode('\n\n'));
        });
        return div.textContent.replace(/\n{3,}/g, '\n\n').trim();
    }

    function plainTextToHtml(text) {
        const paragraphs = String(text || '').split(/\n{2,}/).map(item => item.trim()).filter(Boolean);
        if (!paragraphs.length) return '';
        return paragraphs.map(paragraph => `<p>${escapeHtml(paragraph).replace(/\n/g, '<br>')}</p>`).join('');
    }

    function blockLabel(type) {
        const labels = {
            headline: 'Headline',
            text: 'Text',
            article: 'Article',
            image: 'Image',
            button: 'Button',
            divider: 'Divider'
        };
        return labels[type] || 'Block';
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

    async function deleteBlock(sectionIndex, blockIndex) {
        if (!(await showConfirm({
            title: 'Delete block?',
            message: 'This block will be removed from the newsletter.',
            confirmText: 'Delete block',
            danger: true
        }))) return;
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
            await showNotice({
                title: 'Crop failed',
                message: 'The image could not be cropped.'
            });
            return;
        }
        const blob = await new Promise(resolve => croppedCanvas.toBlob(resolve, 'image/jpeg', 0.88));
        if (!blob) {
            await showNotice({
                title: 'Crop failed',
                message: 'The image could not be cropped.'
            });
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
            await showNotice({
                title: 'Crop failed',
                message: data.message || 'The image could not be cropped.'
            });
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
            await showNotice({
                title: 'Upload failed',
                message: data.message || 'The image could not be uploaded.'
            });
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
    document.getElementById('pantryTemplateBtn').addEventListener('click', applyPantryTemplate);
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
        const to = await showPrompt({
            title: 'Send test email',
            message: 'Enter the email address that should receive this test.',
            label: 'Recipient email',
            inputType: 'email',
            placeholder: 'name@example.com',
            confirmText: 'Send test'
        });
        if (!to) return;
        const response = await fetch(app.appUrl + '/api/email/send-test.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': app.csrf },
            body: JSON.stringify({ newsletter_id: app.newsletterId, to })
        });
        const data = await response.json();
        await showNotice({
            title: data.ok ? 'Test sent' : 'Test failed',
            message: data.message || (data.ok ? 'Test sent.' : 'Test failed.')
        });
    });

    document.getElementById('queueSendBtn').addEventListener('click', async () => {
        await saveNow();
        if (!(await showConfirm({
            title: 'Publish newsletter?',
            message: 'This will publish the newsletter and queue it for all active subscribers.',
            confirmText: 'Publish / Send'
        }))) return;
        const response = await fetch(app.appUrl + '/api/email/queue.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': app.csrf },
            body: JSON.stringify({ newsletter_id: app.newsletterId })
        });
        const data = await response.json();
        await showNotice({
            title: data.ok === false ? 'Publish failed' : 'Newsletter queued',
            message: data.message || 'Queued.'
        });
    });

    function showConfirm(options) {
        return showDialog(Object.assign({ type: 'confirm' }, options));
    }

    function showNotice(options) {
        return showDialog(Object.assign({ type: 'notice', confirmText: 'OK' }, options));
    }

    function showPrompt(options) {
        return showDialog(Object.assign({ type: 'prompt' }, options));
    }

    function showDialog(options) {
        return new Promise(resolve => {
            const overlay = document.createElement('div');
            overlay.className = 'app-dialog-backdrop';
            overlay.innerHTML = `<div class="app-dialog" role="dialog" aria-modal="true" aria-labelledby="appDialogTitle">
                <button class="app-dialog-close" type="button" aria-label="Close">${closeIcon()}</button>
                <div class="app-dialog-icon ${options.danger ? 'danger' : ''}">${options.danger ? trashIcon() : dialogIcon()}</div>
                <h2 id="appDialogTitle">${escapeHtml(options.title || 'Confirm action')}</h2>
                <p>${escapeHtml(options.message || '')}</p>
                ${options.type === 'prompt' ? `<label class="app-dialog-field">${escapeHtml(options.label || 'Value')}<input class="form-control" type="${escapeAttr(options.inputType || 'text')}" placeholder="${escapeAttr(options.placeholder || '')}" value="${escapeAttr(options.value || '')}"></label>` : ''}
                <div class="app-dialog-actions">
                    ${options.type === 'notice' ? '' : '<button class="btn btn-outline-secondary" type="button" data-dialog-cancel>Cancel</button>'}
                    <button class="btn ${options.danger ? 'btn-danger' : 'btn-primary'}" type="button" data-dialog-confirm>${escapeHtml(options.confirmText || 'Confirm')}</button>
                </div>
            </div>`;
            document.body.appendChild(overlay);
            const panel = overlay.querySelector('.app-dialog');
            const input = overlay.querySelector('input');
            const confirmButton = overlay.querySelector('[data-dialog-confirm]');
            const cancelButton = overlay.querySelector('[data-dialog-cancel]');
            const closeButton = overlay.querySelector('.app-dialog-close');
            const previousFocus = document.activeElement;

            function close(value) {
                overlay.remove();
                document.removeEventListener('keydown', onKeydown);
                if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
                resolve(value);
            }

            function acceptDialog() {
                if (options.type === 'prompt') {
                    const value = input ? input.value.trim() : '';
                    if (!value) {
                        if (input) input.focus();
                        return;
                    }
                    close(value);
                    return;
                }
                close(true);
            }

            function onKeydown(event) {
                if (event.key === 'Escape') close(options.type === 'notice' ? true : false);
                if (event.key === 'Enter' && (event.target === input || event.target === confirmButton)) {
                    event.preventDefault();
                    acceptDialog();
                }
            }

            confirmButton.addEventListener('click', acceptDialog);
            if (cancelButton) cancelButton.addEventListener('click', () => close(false));
            closeButton.addEventListener('click', () => close(options.type === 'notice' ? true : false));
            overlay.addEventListener('click', event => {
                if (event.target === overlay) close(options.type === 'notice' ? true : false);
            });
            document.addEventListener('keydown', onKeydown);
            setTimeout(() => (input || confirmButton).focus(), 0);
        });
    }

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

    function closeIcon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
    }

    function dialogIcon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z"/><path d="M12 8v5"/><path d="M12 16h.01"/></svg>';
    }

    render();
})();
