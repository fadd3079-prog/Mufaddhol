(() => {
  'use strict';

  const confirmDialog = document.getElementById('confirm-dialog');
  const confirmTitle = document.getElementById('confirm-dialog-title');
  const confirmCancel = confirmDialog?.querySelector('[data-confirm-cancel]');
  const confirmOk = confirmDialog?.querySelector('[data-confirm-ok]');
  let pendingConfirmForm = null;
  let lastConfirmTrigger = null;

  if (confirmDialog && typeof confirmDialog.showModal === 'function') {
    const closeConfirm = () => {
      if (confirmDialog.open) confirmDialog.close();
      pendingConfirmForm = null;
      if (lastConfirmTrigger && typeof lastConfirmTrigger.focus === 'function') {
        lastConfirmTrigger.focus();
      }
      lastConfirmTrigger = null;
    };

    confirmCancel?.addEventListener('click', closeConfirm);
    confirmDialog.addEventListener('cancel', (e) => {
      e.preventDefault();
      closeConfirm();
    });
    confirmDialog.addEventListener('click', (e) => {
      if (e.target === confirmDialog) closeConfirm();
    });
    confirmOk?.addEventListener('click', () => {
      const form = pendingConfirmForm;
      pendingConfirmForm = null;
      lastConfirmTrigger = null;
      if (confirmDialog.open) confirmDialog.close();
      if (form) form.submit();
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        pendingConfirmForm = form;
        lastConfirmTrigger = document.activeElement;
        if (confirmTitle) confirmTitle.textContent = form.dataset.confirm || 'Confirm delete?';
        confirmDialog.showModal();
        confirmCancel?.focus();
      });
    });
  } else {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
      form.addEventListener('submit', (e) => {
        if (!window.confirm(form.dataset.confirm)) e.preventDefault();
      });
    });
  }

  /* Custom select dropdown */

  const customSelects = [];

  function closeAllSelects(except) {
    customSelects.forEach((inst) => {
      if (inst !== except) inst.close(false);
    });
  }

  document.querySelectorAll('select.input').forEach((select, index) => {
    if (select.dataset.enhanced === '1') return;
    select.dataset.enhanced = '1';

    const wrap = document.createElement('div');
    wrap.className = 'cselect';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);

    select.classList.add('cselect_native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    const menuId = `${select.id || 'select-' + index}-listbox`;
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'input cselect_trigger';
    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', menuId);
    if (select.disabled) trigger.disabled = true;

    const label = select.id ? document.querySelector(`label[for="${CSS.escape(select.id)}"]`) : null;
    if (label) {
      if (!label.id) label.id = `${select.id}-label`;
      trigger.setAttribute('aria-labelledby', label.id);
      label.addEventListener('click', (e) => {
        e.preventDefault();
        if (!trigger.disabled) {
          trigger.focus();
          toggle();
        }
      });
    }

    const valueEl = document.createElement('span');
    valueEl.className = 'cselect_value';
    trigger.appendChild(valueEl);

    const arrow = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    arrow.setAttribute('class', 'cselect_chevron');
    arrow.setAttribute('viewBox', '0 0 16 16');
    arrow.setAttribute('width', '16');
    arrow.setAttribute('height', '16');
    arrow.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', 'M4 6l4 4 4-4');
    path.setAttribute('fill', 'none');
    path.setAttribute('stroke', 'currentColor');
    path.setAttribute('stroke-width', '1.5');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    arrow.appendChild(path);
    trigger.appendChild(arrow);

    const menu = document.createElement('ul');
    menu.className = 'cselect_menu';
    menu.id = menuId;
    menu.setAttribute('role', 'listbox');
    menu.hidden = true;

    const options = Array.from(select.options);
    const items = options.map((opt, i) => {
      const li = document.createElement('li');
      li.className = 'cselect_option';
      li.id = `${menuId}-opt-${i}`;
      li.setAttribute('role', 'option');
      li.dataset.value = opt.value;

      const text = document.createElement('span');
      text.textContent = opt.textContent;
      li.appendChild(text);

      const check = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      check.setAttribute('class', 'cselect_check');
      check.setAttribute('viewBox', '0 0 16 16');
      check.setAttribute('width', '14');
      check.setAttribute('height', '14');
      check.setAttribute('aria-hidden', 'true');
      const checkPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      checkPath.setAttribute('d', 'M3.5 8.5 6.5 11.5 12.5 4.5');
      checkPath.setAttribute('fill', 'none');
      checkPath.setAttribute('stroke', 'currentColor');
      checkPath.setAttribute('stroke-width', '1.6');
      checkPath.setAttribute('stroke-linecap', 'round');
      checkPath.setAttribute('stroke-linejoin', 'round');
      check.appendChild(checkPath);
      li.appendChild(check);

      li.addEventListener('mouseenter', () => setActive(i));
      li.addEventListener('click', () => choose(i));
      menu.appendChild(li);
      return li;
    });

    wrap.appendChild(trigger);
    wrap.appendChild(menu);

    let isOpen = false;
    let activeIndex = Math.max(0, select.selectedIndex);

    function syncUI() {
      const idx = Math.max(0, select.selectedIndex);
      const current = options[idx];
      valueEl.textContent = current ? current.textContent : '';
      items.forEach((li, i) => {
        li.setAttribute('aria-selected', String(i === idx));
      });
    }

    function setActive(i) {
      if (i < 0 || i >= items.length) return;
      activeIndex = i;
      items.forEach((li, idx) => li.classList.toggle('is-highlighted', idx === i));
      trigger.setAttribute('aria-activedescendant', items[i].id);
      items[i].scrollIntoView({ block: 'nearest' });
    }

    function open() {
      if (isOpen || trigger.disabled) return;
      closeAllSelects(inst);
      isOpen = true;
      wrap.classList.add('is-open');
      menu.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');

      const rect = wrap.getBoundingClientRect();
      const spaceBelow = window.innerHeight - rect.bottom;
      const menuHeight = Math.min(260, menu.scrollHeight + 16);
      wrap.classList.toggle('is-up', spaceBelow < menuHeight && rect.top > spaceBelow);

      setActive(Math.max(0, select.selectedIndex));
    }

    function close(restoreFocus = false) {
      if (!isOpen) return;
      isOpen = false;
      wrap.classList.remove('is-open', 'is-up');
      menu.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
      trigger.removeAttribute('aria-activedescendant');
      items.forEach((li) => li.classList.remove('is-highlighted'));
      if (restoreFocus) trigger.focus();
    }

    function toggle() {
      if (isOpen) close(false);
      else open();
    }

    function choose(i) {
      if (i < 0 || i >= options.length) return;
      const changed = select.selectedIndex !== i;
      select.selectedIndex = i;
      syncUI();
      close(true);
      if (changed) {
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }

    trigger.addEventListener('click', toggle);

    trigger.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (!isOpen) open();
        else setActive(Math.min(items.length - 1, activeIndex + 1));
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (!isOpen) open();
        else setActive(Math.max(0, activeIndex - 1));
      } else if (e.key === 'Home') {
        if (isOpen) {
          e.preventDefault();
          setActive(0);
        }
      } else if (e.key === 'End') {
        if (isOpen) {
          e.preventDefault();
          setActive(items.length - 1);
        }
      } else if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        if (!isOpen) open();
        else choose(activeIndex);
      } else if (e.key === 'Escape') {
        if (isOpen) {
          e.preventDefault();
          close(true);
        }
      } else if (e.key === 'Tab') {
        close(false);
      } else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
        const ch = e.key.toLowerCase();
        const start = isOpen ? activeIndex + 1 : 0;
        const found = options.findIndex((o, idx) => idx >= start && o.textContent.trim().toLowerCase().startsWith(ch));
        const match = found !== -1
          ? found
          : options.findIndex((o) => o.textContent.trim().toLowerCase().startsWith(ch));
        if (match !== -1) {
          if (!isOpen) open();
          setActive(match);
        }
      }
    });

    select.addEventListener('change', syncUI);
    if (select.form) {
      select.form.addEventListener('reset', () => requestAnimationFrame(syncUI));
    }

    const inst = { wrap, close };
    customSelects.push(inst);
    syncUI();
  });

  if (customSelects.length) {
    document.addEventListener('click', (e) => {
      if (!customSelects.some((inst) => inst.wrap.contains(e.target))) {
        closeAllSelects(null);
      }
    });
  }

  /* Row drag-and-drop reordering */

  const portfolioTable = document.getElementById('portfolio-table');
  const tableStatus = document.getElementById('table-status');
  if (portfolioTable) {
    const tbody = portfolioTable.querySelector('tbody');
    const csrfToken = portfolioTable.dataset.csrf || '';
    let statusTimer = null;

    const showTableStatus = (message, isError = false) => {
      if (!tableStatus) return;
      clearTimeout(statusTimer);
      tableStatus.textContent = message;
      tableStatus.classList.toggle('is-error', isError);
      tableStatus.hidden = false;
      if (!isError) {
        statusTimer = setTimeout(() => {
          tableStatus.hidden = true;
        }, 2200);
      }
    };

    const currentIds = () =>
      Array.from(tbody.querySelectorAll('tr[data-id]')).map((tr) => tr.dataset.id);

    async function persistOrder() {
      const ids = currentIds();
      if (!ids.length) return;
      showTableStatus('Saving order...');
      const body = new URLSearchParams();
      body.set('action', 'reorder');
      body.set('csrf_token', csrfToken);
      ids.forEach((id) => body.append('order[]', id));

      try {
        const res = await fetch('index.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString(),
        });
        const payload = await res.json().catch(() => null);
        if (!res.ok || !payload || payload.status !== 'ok') {
          throw new Error((payload && payload.message) || 'Could not save order.');
        }
        showTableStatus('Order updated.');
      } catch (err) {
        showTableStatus(err.message || 'Could not save order.', true);
      }
    }

    let draggingRow = null;
    let initialOrder = '';

    tbody.addEventListener('pointerdown', (e) => {
      const handle = e.target.closest('.sort-handle');
      if (!handle || e.button > 0) return;
      const row = handle.closest('tr[data-id]');
      if (!row) return;

      e.preventDefault();
      draggingRow = row;
      initialOrder = currentIds().join(',');
      draggingRow.classList.add('is-dragging');
      tbody.classList.add('is-sorting');

      const onMove = (moveEvent) => {
        if (!draggingRow) return;
        const rows = Array.from(tbody.querySelectorAll('tr[data-id]'));
        const y = moveEvent.clientY;
        for (const candidate of rows) {
          if (candidate === draggingRow) continue;
          const rect = candidate.getBoundingClientRect();
          const mid = rect.top + rect.height / 2;
          if (y < mid && draggingRow.compareDocumentPosition(candidate) & Node.DOCUMENT_POSITION_PRECEDING) {
            tbody.insertBefore(draggingRow, candidate);
            break;
          }
          if (y > mid && draggingRow.compareDocumentPosition(candidate) & Node.DOCUMENT_POSITION_FOLLOWING) {
            tbody.insertBefore(draggingRow, candidate.nextSibling);
            break;
          }
        }
      };

      const onEnd = () => {
        if (!draggingRow) return;
        draggingRow.classList.remove('is-dragging');
        tbody.classList.remove('is-sorting');
        const nextOrder = currentIds().join(',');
        draggingRow = null;
        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onEnd);
        window.removeEventListener('pointercancel', onEnd);
        if (nextOrder !== initialOrder) {
          persistOrder();
        }
      };

      window.addEventListener('pointermove', onMove);
      window.addEventListener('pointerup', onEnd);
      window.addEventListener('pointercancel', onEnd);
    });

    tbody.addEventListener('keydown', (e) => {
      const handle = e.target.closest('.sort-handle');
      if (!handle) return;
      const row = handle.closest('tr[data-id]');
      if (!row) return;
      if (e.key === 'ArrowUp' && row.previousElementSibling) {
        e.preventDefault();
        tbody.insertBefore(row, row.previousElementSibling);
        handle.focus();
        persistOrder();
      } else if (e.key === 'ArrowDown' && row.nextElementSibling) {
        e.preventDefault();
        tbody.insertBefore(row, row.nextElementSibling.nextSibling);
        handle.focus();
        persistOrder();
      }
    });
  }

  /* Draft persistence across accidental refresh (sessionStorage + IndexedDB for files) */

  const DRAFT_DB_NAME = 'mufaddhol_admin_drafts';
  const DRAFT_STORE = 'files';

  function openDraftDb() {
    if (!('indexedDB' in window)) return Promise.resolve(null);
    return new Promise((resolve) => {
      try {
        const req = indexedDB.open(DRAFT_DB_NAME, 1);
        req.onupgradeneeded = () => {
          if (!req.result.objectStoreNames.contains(DRAFT_STORE)) {
            req.result.createObjectStore(DRAFT_STORE);
          }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => resolve(null);
      } catch {
        resolve(null);
      }
    });
  }

  async function idbGet(key) {
    const db = await openDraftDb();
    if (!db) return null;
    return new Promise((resolve) => {
      try {
        const tx = db.transaction(DRAFT_STORE, 'readonly');
        const req = tx.objectStore(DRAFT_STORE).get(key);
        req.onsuccess = () => resolve(req.result || null);
        req.onerror = () => resolve(null);
      } catch {
        resolve(null);
      }
    });
  }

  async function idbSet(key, value) {
    const db = await openDraftDb();
    if (!db) return;
    return new Promise((resolve) => {
      try {
        const tx = db.transaction(DRAFT_STORE, 'readwrite');
        tx.objectStore(DRAFT_STORE).put(value, key);
        tx.oncomplete = () => resolve();
        tx.onerror = () => resolve();
      } catch {
        resolve();
      }
    });
  }

  async function idbDelete(key) {
    const db = await openDraftDb();
    if (!db) return;
    return new Promise((resolve) => {
      try {
        const tx = db.transaction(DRAFT_STORE, 'readwrite');
        tx.objectStore(DRAFT_STORE).delete(key);
        tx.oncomplete = () => resolve();
        tx.onerror = () => resolve();
      } catch {
        resolve();
      }
    });
  }

  function clearDraftStorage(key) {
    if (!key) return;
    try {
      sessionStorage.removeItem(`portfolio-draft:${key}`);
    } catch { /* ignore storage restrictions */ }
    idbDelete(key);
  }

  document.querySelectorAll('[data-clear-draft]').forEach((el) => {
    clearDraftStorage(el.dataset.clearDraft);
  });

  const form = document.getElementById('portfolio-form');
  if (!form) return;

  const category = form.elements.category;
  const modes = Array.from(form.querySelectorAll('input[name="media_mode"]'));
  const mainImageInput = document.getElementById('main_image');
  const coverPreview = document.getElementById('cover-preview');
  const video = document.getElementById('main_video');
  const videoField = document.getElementById('field-video');
  const videoPreview = document.getElementById('video-preview');
  const videoUrlField = document.getElementById('field-video-url');
  const videoUrlInput = document.getElementById('video_url');
  const tagsField = document.getElementById('field-tags');
  const tagsInput = document.getElementById('tech_tags');
  const tagPreview = document.getElementById('tag-preview');
  const linksGroup = document.getElementById('links-group');
  const detailsInput = document.getElementById('details');
  const detailPreview = document.getElementById('detail-preview');
  const hasExistingVideo = form.dataset.hasVideo === '1';

  const currentMode = () => (modes.find((m) => m.checked) || {}).value || 'image_only';

  const formatBytes = (bytes) => {
    if (!Number.isFinite(bytes) || bytes <= 0) return '';
    if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  };

  const acceptsFile = (file, acceptAttr) => {
    if (!acceptAttr) return true;
    const allowed = acceptAttr.split(',').map((s) => s.trim().toLowerCase()).filter(Boolean);
    return allowed.includes((file.type || '').toLowerCase());
  };

  const bindDropzone = (zone, input, onFiles) => {
    if (!zone || !input) return;
    let dragDepth = 0;

    zone.addEventListener('dragenter', (e) => {
      e.preventDefault();
      dragDepth++;
      zone.classList.add('is-dragover');
    });
    zone.addEventListener('dragover', (e) => {
      e.preventDefault();
      if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
      zone.classList.add('is-dragover');
    });
    zone.addEventListener('dragleave', () => {
      dragDepth = Math.max(0, dragDepth - 1);
      if (dragDepth === 0) zone.classList.remove('is-dragover');
    });
    zone.addEventListener('drop', (e) => {
      e.preventDefault();
      dragDepth = 0;
      zone.classList.remove('is-dragover');
      const dropped = Array.from(e.dataTransfer?.files || []).filter((f) => acceptsFile(f, input.accept));
      if (!dropped.length) return;
      onFiles(dropped);
    });
  };

  function syncVideoRequirement() {
    const wantsVideo = currentMode() !== 'image_only';
    const hasLink = videoUrlInput && videoUrlInput.value.trim() !== '';
    const hasFile = (video.files && video.files.length > 0) || hasExistingVideo;
    video.required = wantsVideo && !hasFile && !hasLink;
  }

  function syncMode() {
    const wantsVideo = currentMode() !== 'image_only';
    videoField.hidden = !wantsVideo;
    if (videoUrlField) videoUrlField.hidden = !wantsVideo;
    syncVideoRequirement();
    const mediaGrid = videoField.closest('.form_media-grid');
    if (mediaGrid) mediaGrid.classList.toggle('has-video', wantsVideo);
  }

  function syncCategory() {
    const software = category.value === 'Software Development';
    tagsField.hidden = !software;
    if (software) linksGroup.open = true;
  }

  function renderTags() {
    const seen = new Set();
    const tags = tagsInput.value
      .split(',')
      .map((t) => t.trim())
      .filter((t) => {
        const key = t.toLowerCase();
        if (!t || seen.has(key)) return false;
        seen.add(key);
        return true;
      });
    tagPreview.replaceChildren(
      ...tags.map((tag) => {
        const li = document.createElement('li');
        li.className = 'stack-chip';
        li.textContent = tag;
        return li;
      })
    );
  }

  let coverObjectUrl = null;
  function renderSinglePreview(file, previewEl, isVideo) {
    if (!previewEl || !file) return;
    if (isVideo && videoObjectUrl) URL.revokeObjectURL(videoObjectUrl);
    if (!isVideo && coverObjectUrl) URL.revokeObjectURL(coverObjectUrl);

    const url = URL.createObjectURL(file);
    if (isVideo) videoObjectUrl = url;
    else coverObjectUrl = url;

    previewEl.replaceChildren();
    const media = document.createElement(isVideo ? 'video' : 'img');
    media.className = 'dropzone_thumb';
    media.src = url;
    media.width = 80;
    media.height = 80;
    if (isVideo) {
      media.muted = true;
      media.preload = 'metadata';
      media.playsInline = true;
    } else {
      media.alt = file.name;
    }
    previewEl.appendChild(media);

    const info = document.createElement('span');
    info.className = 'dropzone_file-info';
    const name = document.createElement('span');
    name.className = 'dropzone_file-name';
    name.textContent = file.name;
    const meta = document.createElement('span');
    meta.className = 'dropzone_meta';
    meta.textContent = formatBytes(file.size);
    info.append(name, meta);
    previewEl.appendChild(info);
    previewEl.hidden = false;
  }

  let videoObjectUrl = null;

  const setSingleInputFile = (input, file, previewEl, isVideo) => {
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    renderSinglePreview(file, previewEl, isVideo);
  };

  if (mainImageInput && coverPreview) {
    const imageZone = mainImageInput.closest('.dropzone');
    mainImageInput.addEventListener('change', () => {
      if (mainImageInput.files[0]) renderSinglePreview(mainImageInput.files[0], coverPreview, false);
      saveDraftFiles();
    });
    bindDropzone(imageZone, mainImageInput, (files) => {
      setSingleInputFile(mainImageInput, files[0], coverPreview, false);
      saveDraftFiles();
    });
  }

  if (video && videoPreview) {
    const videoZone = video.closest('.dropzone');
    video.addEventListener('change', () => {
      if (video.files[0]) renderSinglePreview(video.files[0], videoPreview, true);
      syncVideoRequirement();
      saveDraftFiles();
    });
    bindDropzone(videoZone, video, (files) => {
      setSingleInputFile(video, files[0], videoPreview, true);
      syncVideoRequirement();
      saveDraftFiles();
    });
  }

  if (videoUrlInput) {
    videoUrlInput.addEventListener('input', syncVideoRequirement);
  }

  let previewUrls = [];
  let queuedDetails = [];

  function syncDetailsInputFiles() {
    const dt = new DataTransfer();
    queuedDetails.forEach((entry) => dt.items.add(entry.file));
    detailsInput.files = dt.files;
  }

  function captureCaptions() {
    const inputs = detailPreview.querySelectorAll('input[name="detail_captions[]"]');
    inputs.forEach((inp, idx) => {
      if (queuedDetails[idx]) queuedDetails[idx].caption = inp.value;
    });
  }

  function renderDetailPreview() {
    previewUrls.forEach((url) => URL.revokeObjectURL(url));
    previewUrls = [];
    detailPreview.replaceChildren();

    queuedDetails.forEach((entry, i) => {
      const { file, caption: savedCaption } = entry;
      const card = document.createElement('div');
      card.className = 'detail-card';

      const url = URL.createObjectURL(file);
      previewUrls.push(url);
      const isVideo = file.type.startsWith('video/');
      const media = document.createElement(isVideo ? 'video' : 'img');
      media.src = url;
      media.width = 160;
      media.height = 120;
      if (isVideo) {
        media.muted = true;
        media.preload = 'metadata';
        media.playsInline = true;
      } else {
        media.alt = file.name;
      }
      card.appendChild(media);

      const head = document.createElement('div');
      head.className = 'detail-card_head';
      const name = document.createElement('p');
      name.className = 'hint';
      name.textContent = file.name;
      head.appendChild(name);

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'detail-card_remove';
      removeBtn.setAttribute('aria-label', `Remove ${file.name}`);
      removeBtn.textContent = '×';
      removeBtn.addEventListener('click', () => {
        captureCaptions();
        queuedDetails.splice(i, 1);
        syncDetailsInputFiles();
        renderDetailPreview();
        saveDraftFiles();
      });
      head.appendChild(removeBtn);
      card.appendChild(head);

      const label = document.createElement('label');
      label.className = 'visually-hidden';
      label.htmlFor = `detail-caption-${i}`;
      label.textContent = `Caption for ${file.name}`;
      card.appendChild(label);

      const caption = document.createElement('input');
      caption.type = 'text';
      caption.className = 'input';
      caption.id = `detail-caption-${i}`;
      caption.name = 'detail_captions[]';
      caption.maxLength = 150;
      caption.placeholder = 'Caption';
      caption.value = savedCaption || '';
      caption.addEventListener('input', () => {
        captureCaptions();
        saveDraftFiles();
      });
      card.appendChild(caption);

      detailPreview.appendChild(card);
    });
  }

  function addDetailFiles(files) {
    captureCaptions();
    files.forEach((file) => queuedDetails.push({ file, caption: '' }));
    syncDetailsInputFiles();
    renderDetailPreview();
    saveDraftFiles();
  }

  if (detailsInput && detailPreview) {
    const detailsZone = detailsInput.closest('.dropzone');
    detailsInput.addEventListener('change', () => {
      if (!detailsInput.files.length) return;
      addDetailFiles(Array.from(detailsInput.files));
    });
    bindDropzone(detailsZone, detailsInput, addDetailFiles);
  }

  const draftKey = form.dataset.draftKey || '';
  const draftStorageKey = draftKey ? `portfolio-draft:${draftKey}` : '';

  function saveDraftFields() {
    if (!draftStorageKey) return;
    const existingCaptions = {};
    form.querySelectorAll('input[name^="existing_captions["]').forEach((inp) => {
      existingCaptions[inp.name] = inp.value;
    });
    const deleteDetails = Array.from(form.querySelectorAll('input[name="delete_details[]"]:checked'))
      .map((cb) => cb.value);

    const payload = {
      title: form.elements.title?.value ?? '',
      category: category?.value ?? '',
      media_mode: currentMode(),
      video_url: videoUrlInput?.value ?? '',
      description: form.elements.description?.value ?? '',
      tech_tags: tagsInput?.value ?? '',
      demo_url: form.elements.demo_url?.value ?? '',
      github_url: form.elements.github_url?.value ?? '',
      existing_captions: existingCaptions,
      delete_details: deleteDetails,
    };
    try {
      sessionStorage.setItem(draftStorageKey, JSON.stringify(payload));
    } catch { /* ignore quota/private mode */ }
  }

  function saveDraftFiles() {
    if (!draftKey) return;
    captureCaptions();
    const payload = {
      main_image: mainImageInput?.files?.[0] || null,
      main_video: video?.files?.[0] || null,
      details: queuedDetails.map((entry) => ({
        file: entry.file,
        caption: entry.caption || '',
      })),
    };
    if (!payload.main_image && !payload.main_video && payload.details.length === 0) {
      idbDelete(draftKey);
      return;
    }
    idbSet(draftKey, payload);
  }

  function restoreDraftFields() {
    if (!draftStorageKey) return;
    let raw = null;
    try {
      raw = sessionStorage.getItem(draftStorageKey);
    } catch {
      return;
    }
    if (!raw) return;
    let draft;
    try {
      draft = JSON.parse(raw);
    } catch {
      return;
    }
    if (!draft || typeof draft !== 'object') return;

    if (typeof draft.title === 'string' && form.elements.title) form.elements.title.value = draft.title;
    if (typeof draft.category === 'string' && category) {
      category.value = draft.category;
      category.dispatchEvent(new Event('change', { bubbles: true }));
    }
    if (typeof draft.media_mode === 'string') {
      modes.forEach((m) => {
        m.checked = m.value === draft.media_mode;
      });
    }
    if (typeof draft.video_url === 'string' && videoUrlInput) videoUrlInput.value = draft.video_url;
    if (typeof draft.description === 'string' && form.elements.description) {
      form.elements.description.value = draft.description;
    }
    if (typeof draft.tech_tags === 'string' && tagsInput) tagsInput.value = draft.tech_tags;
    if (typeof draft.demo_url === 'string' && form.elements.demo_url) form.elements.demo_url.value = draft.demo_url;
    if (typeof draft.github_url === 'string' && form.elements.github_url) form.elements.github_url.value = draft.github_url;

    if (draft.existing_captions && typeof draft.existing_captions === 'object') {
      form.querySelectorAll('input[name^="existing_captions["]').forEach((inp) => {
        if (Object.prototype.hasOwnProperty.call(draft.existing_captions, inp.name)) {
          inp.value = String(draft.existing_captions[inp.name] ?? '');
        }
      });
    }
    if (Array.isArray(draft.delete_details)) {
      const delSet = new Set(draft.delete_details.map(String));
      form.querySelectorAll('input[name="delete_details[]"]').forEach((cb) => {
        cb.checked = delSet.has(String(cb.value));
      });
    }
  }

  async function restoreDraftFiles() {
    if (!draftKey) return;
    const saved = await idbGet(draftKey);
    if (!saved || typeof saved !== 'object') return;

    if (saved.main_image instanceof Blob && mainImageInput && coverPreview) {
      setSingleInputFile(mainImageInput, saved.main_image, coverPreview, false);
      mainImageInput.required = false;
    }
    if (saved.main_video instanceof Blob && video && videoPreview) {
      setSingleInputFile(video, saved.main_video, videoPreview, true);
      syncVideoRequirement();
    }
    if (Array.isArray(saved.details) && saved.details.length && detailsInput && detailPreview) {
      queuedDetails = saved.details
        .filter((d) => d && d.file instanceof Blob)
        .map((d) => ({ file: d.file, caption: String(d.caption || '') }));
      if (queuedDetails.length) {
        syncDetailsInputFiles();
        renderDetailPreview();
      }
    }
  }

  form.addEventListener('input', (e) => {
    if (e.target.type === 'file') return;
    saveDraftFields();
  });
  form.addEventListener('change', (e) => {
    if (e.target.type === 'file') return;
    saveDraftFields();
  });

  form.querySelectorAll('[data-draft-clear]').forEach((el) => {
    el.addEventListener('click', () => clearDraftStorage(draftKey));
  });

  modes.forEach((m) => m.addEventListener('change', syncMode));
  category.addEventListener('change', syncCategory);
  tagsInput.addEventListener('input', renderTags);

  restoreDraftFields();
  syncMode();
  syncCategory();
  renderTags();
  restoreDraftFiles();
})();
