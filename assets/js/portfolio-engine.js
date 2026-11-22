(() => {
  'use strict';

  const MAX_PLAYING = 3;
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  /* Floating pill header and mobile navigation */

  const header = document.getElementById('site-header');
  const navToggle = document.querySelector('.site-nav_toggle');
  const navMenu = document.getElementById('site-menu');

  if (header) {
    let ticking = false;
    const updateFloating = () => {
      ticking = false;
      header.classList.toggle('is-floating', window.scrollY > 0);
    };
    const onScroll = () => {
      if (!ticking) {
        ticking = true;
        requestAnimationFrame(updateFloating);
      }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
    updateFloating();
  }

  if (navToggle && navMenu) {
    const setNav = (open) => {
      navToggle.setAttribute('aria-expanded', String(open));
      navMenu.classList.toggle('is-open', open);
      if (header) header.classList.toggle('is-menu-open', open);
    };
    navToggle.addEventListener('click', () => setNav(navToggle.getAttribute('aria-expanded') !== 'true'));
    navMenu.addEventListener('click', (e) => {
      if (e.target.closest('a')) setNav(false);
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && navMenu.classList.contains('is-open')) {
        setNav(false);
        navToggle.focus();
      }
    });
    document.addEventListener('click', (e) => {
      if (
        navMenu.classList.contains('is-open') &&
        !navMenu.contains(e.target) &&
        !navToggle.contains(e.target)
      ) {
        setNav(false);
      }
    });
    window.addEventListener('resize', () => {
      if (window.innerWidth > 767) setNav(false);
    });
  }

  /* Section scroll-spy */

  const sectionLinks = Array.from(document.querySelectorAll('[data-section-link]'));
  if (sectionLinks.length && 'IntersectionObserver' in window) {
    const targets = sectionLinks
      .map((link) => ({ link, el: document.getElementById(link.dataset.sectionLink) }))
      .filter((item) => item.el);

    if (targets.length) {
      const visibleSections = new Map();
      const spy = new IntersectionObserver(
        (entries) => {
          for (const entry of entries) {
            if (entry.isIntersecting) visibleSections.set(entry.target.id, entry.intersectionRatio);
            else visibleSections.delete(entry.target.id);
          }
          let activeId = null;
          let bestRatio = 0;
          for (const [id, ratio] of visibleSections.entries()) {
            if (ratio > bestRatio) {
              bestRatio = ratio;
              activeId = id;
            }
          }
          for (const { link, el } of targets) {
            link.classList.toggle('is-section-active', el.id === activeId);
          }
        },
        { rootMargin: '-25% 0px -45% 0px', threshold: [0, 0.2, 0.5] }
      );
      targets.forEach(({ el }) => spy.observe(el));
    }
  }

  /* Shared virtual card factory & lazy video lifecycle */

  const createPortoCard = (className) => {
    const article = document.createElement('article');
    article.className = `${className} is-pooled`;

    const mediaWrap = document.createElement('div');
    mediaWrap.className = 'porto-card_media';

    const img = document.createElement('img');
    img.loading = 'eager';
    img.draggable = false;

    const badge = document.createElement('span');
    badge.className = 'porto-card_badge';
    badge.setAttribute('aria-hidden', 'true');
    badge.innerHTML = '<svg viewBox="0 0 16 16" width="12" height="12"><path d="M5 3.5v9l7-4.5-7-4.5Z" fill="currentColor"/></svg>';

    mediaWrap.append(img, badge);

    const shade = document.createElement('div');
    shade.className = 'porto-card_shade';
    const meta = document.createElement('span');
    meta.className = 'porto-card_meta';
    const title = document.createElement('h3');
    title.className = 'porto-card_title';
    shade.append(meta, title);

    const hit = document.createElement('a');
    hit.className = 'porto-card_hit';
    hit.draggable = false;

    article.append(mediaWrap, shade, hit);
    article._refs = { mediaWrap, img, badge, meta, title, hit, video: null };
    return article;
  };

  const stopCardVideo = (card) => {
    const refs = card._refs;
    if (!refs || !refs.video) return;
    refs.video.pause();
    refs.video.removeAttribute('src');
    refs.video.remove();
    refs.video = null;
    card.classList.remove('is-playing');
  };

  const startCardVideo = (card, preloadMode = 'metadata') => {
    const vidSrc = card.dataset.videoSrc;
    if (!vidSrc || !card._refs) return;
    if (!card._refs.video) {
      const video = document.createElement('video');
      video.muted = true;
      video.loop = true;
      video.playsInline = true;
      video.preload = preloadMode;
      video.disablePictureInPicture = true;
      video.setAttribute('controlsList', 'nodownload');
      video.setAttribute('aria-hidden', 'true');
      if (card._refs.img && card._refs.img.src) video.poster = card._refs.img.src;
      video.src = vidSrc;
      card._refs.mediaWrap.appendChild(video);
      card._refs.video = video;
    }
    card.classList.add('is-playing');
    if (card._refs.video.paused) {
      card._refs.video.play().catch(() => { });
    }
  };

  /* Virtualized archive masonry (frustum culling + object pooling) */

  const grid = document.getElementById('masonry-grid');
  const archiveDataEl = document.getElementById('archive-data');
  const emptyState = document.getElementById('empty-state');
  const resultCount = document.getElementById('result-count');
  const resultLabel = document.getElementById('result-label');
  const state = { category: 'All', tag: null, query: '' };

  const columnCount = () => {
    const w = window.innerWidth;
    if (w >= 1100) return 4;
    if (w >= 768) return 3;
    return 2;
  };

  const virtualMasonry = (() => {
    if (!grid || !archiveDataEl) return null;
    let allItems = [];
    try {
      const parsed = JSON.parse(archiveDataEl.textContent || '[]');
      if (Array.isArray(parsed)) allItems = parsed;
    } catch {
      return null;
    }

    const BUFFER = 520;
    let filteredItems = allItems;
    let slots = [];
    let slotById = new Map();
    const mounted = new Map();
    const pool = [];
    let lastWidth = -1;
    let lastColumns = -1;
    let rafId = 0;

    const releaseCard = (id, entry) => {
      stopCardVideo(entry.el);
      entry.el.classList.remove('is-spawning');
      entry.el.classList.add('is-pooled');
      entry.el._boundId = null;
      pool.push(entry.el);
      mounted.delete(id);
    };

    const bindArchiveCard = (el, slot) => {
      const { item, width, height } = slot;
      const refs = el._refs;
      if (el._boundId !== item.i) {
        el._boundId = item.i;
        stopCardVideo(el);
        el.dataset.id = String(item.i);
        el.dataset.mode = item.m;
        const ratio = item.r > 0 ? item.r : 1.0;
        el.dataset.ratio = String(ratio);
        el.style.setProperty('--ratio', String(ratio));
        el.dataset.category = item.c;
        el.dataset.videoSrc = item.vid || '';
        if (refs.img.getAttribute('src') !== item.img) {
          refs.img.src = item.img;
        }
        refs.img.alt = item.t;
        refs.badge.hidden = !(item.m !== 'image_only' && (item.vid || item.ext));
        refs.meta.textContent = item.c;
        refs.title.textContent = item.t;
        refs.hit.href = `detail.php?id=${item.i}`;
        refs.hit.setAttribute('aria-label', `Open ${item.t} (${item.c})`);
      }
      refs.img.width = width;
      refs.img.height = height;
      el.style.width = `${width}px`;
      el.style.height = `${height}px`;
      el.style.transform = `translate3d(${slot.x}px, ${slot.y}px, 0)`;
      el.classList.remove('is-pooled');
    };

    const syncAutoVideos = () => {
      if (document.hidden) {
        for (const { el } of mounted.values()) {
          if (el.dataset.mode === 'autoplay_video' && el._refs?.video) {
            el._refs.video.pause();
          }
        }
        return;
      }
      const gridTop = grid.getBoundingClientRect().top;
      const vh = window.innerHeight;
      const center = vh / 2;
      const candidates = [];

      for (const { el, slot } of mounted.values()) {
        if (el.dataset.mode !== 'autoplay_video' || !el.dataset.videoSrc) continue;
        const top = gridTop + slot.y;
        const bottom = top + slot.height;
        if (bottom < -120 || top > vh + 120) continue;
        const dist = Math.abs((top + bottom) / 2 - center);
        candidates.push({ el, dist });
      }

      candidates.sort((a, b) => a.dist - b.dist);
      const activeSet = new Set(candidates.slice(0, MAX_PLAYING).map((c) => c.el));

      for (const { el } of mounted.values()) {
        if (el.dataset.mode !== 'autoplay_video') continue;
        if (activeSet.has(el)) startCardVideo(el);
        else if (el._refs?.video && !el._refs.video.paused) el._refs.video.pause();
      }
    };

    const renderWindow = () => {
      rafId = 0;
      const rect = grid.getBoundingClientRect();
      const viewTop = -rect.top - BUFFER;
      const viewBottom = window.innerHeight - rect.top + BUFFER;

      for (const [id, entry] of mounted.entries()) {
        const nextSlot = slotById.get(id);
        if (!nextSlot || nextSlot.y + nextSlot.height < viewTop || nextSlot.y > viewBottom) {
          releaseCard(id, entry);
        }
      }

      const spawned = [];
      for (const slot of slots) {
        if (slot.y + slot.height < viewTop || slot.y > viewBottom) continue;
        const existing = mounted.get(slot.item.i);
        if (existing) {
          existing.slot = slot;
          bindArchiveCard(existing.el, slot);
        } else {
          const el = pool.pop() || createPortoCard('porto-card');
          if (!el.parentNode) grid.appendChild(el);
          el.classList.add('is-spawning');
          bindArchiveCard(el, slot);
          mounted.set(slot.item.i, { el, slot });
          spawned.push(el);
        }
      }

      if (spawned.length) {
        void grid.offsetHeight;
        for (const el of spawned) el.classList.remove('is-spawning');
      }

      syncAutoVideos();
    };

    const scheduleRender = () => {
      if (!rafId) rafId = requestAnimationFrame(renderWindow);
    };

    const recomputeSlots = () => {
      const K = columnCount();
      const gap = parseFloat(
        getComputedStyle(document.documentElement).getPropertyValue('--grid-gap')
      ) || 16;
      const containerWidth = grid.clientWidth;
      if (containerWidth <= 0) return;
      const colWidth = Math.floor((containerWidth - (K - 1) * gap) / K);
      const colHeights = new Array(K).fill(0);

      slots = [];
      slotById = new Map();

      for (const item of filteredItems) {
        const ratio = item.r > 0 ? item.r : 1.0;
        const height = Math.round(colWidth * ratio);
        let col = 0;
        for (let c = 1; c < K; c++) {
          if (colHeights[c] < colHeights[col]) col = c;
        }
        const x = col * (colWidth + gap);
        const y = colHeights[col];
        const slot = { item, x, y, width: colWidth, height };
        slots.push(slot);
        slotById.set(item.i, slot);
        colHeights[col] += height + gap;
      }

      const tallest = Math.max(...colHeights, 0);
      grid.style.height = `${Math.max(0, tallest - gap)}px`;
      lastWidth = containerWidth;
      lastColumns = K;
      renderWindow();
    };

    const filterItems = (categoryName, tagName, queryText) => {
      filteredItems = allItems.filter((item) => {
        if (categoryName !== 'All' && item.c !== categoryName) return false;
        if (tagName && !item.tags.includes(tagName)) return false;
        if (queryText && !item.tl.includes(queryText) && !item.tagsStr.includes(queryText)) return false;
        return true;
      });
      const shown = filteredItems.length;
      grid.hidden = shown === 0;
      if (emptyState) emptyState.hidden = shown > 0;
      if (resultCount) resultCount.textContent = String(shown);
      if (resultLabel) resultLabel.textContent = shown === 1 ? 'work' : 'works';
      const pagerWrap = document.querySelector('.archive-pager-wrap');
      if (pagerWrap) pagerWrap.hidden = Boolean(tagName || queryText);
      recomputeSlots();
    };

    grid.addEventListener('pointerover', (e) => {
      const card = e.target.closest('.porto-card');
      if (!card || !grid.contains(card)) return;
      if (card.dataset.mode === 'hover_video' && card.dataset.videoSrc) startCardVideo(card);
    });
    grid.addEventListener('pointerout', (e) => {
      const card = e.target.closest('.porto-card');
      if (!card || !grid.contains(card)) return;
      if (e.relatedTarget && card.contains(e.relatedTarget)) return;
      if (card.dataset.mode === 'hover_video') stopCardVideo(card);
    });
    grid.addEventListener('focusin', (e) => {
      const card = e.target.closest('.porto-card');
      if (card && card.dataset.mode === 'hover_video' && card.dataset.videoSrc) startCardVideo(card);
    });
    grid.addEventListener('focusout', (e) => {
      const card = e.target.closest('.porto-card');
      if (card && card.dataset.mode === 'hover_video') stopCardVideo(card);
    });

    window.addEventListener('scroll', scheduleRender, { passive: true });
    document.addEventListener('visibilitychange', syncAutoVideos);

    if ('ResizeObserver' in window) {
      new ResizeObserver(() => {
        if (grid.clientWidth !== lastWidth || columnCount() !== lastColumns) {
          recomputeSlots();
        } else {
          scheduleRender();
        }
      }).observe(grid);
    } else {
      window.addEventListener('resize', () => {
        if (grid.clientWidth !== lastWidth || columnCount() !== lastColumns) {
          recomputeSlots();
        } else {
          scheduleRender();
        }
      });
    }

    recomputeSlots();
    if (!reducedMotion.matches) {
      requestAnimationFrame(() => requestAnimationFrame(() => grid.classList.add('is-animated')));
    }

    return {
      layout: recomputeSlots,
      filter: filterItems,
    };
  })();

  function layout() {
    if (virtualMasonry) virtualMasonry.layout();
  }

  /* Multi-facet portfolio filter */

  const filters = document.getElementById('filters');

  /* Virtualized object-pooled 3-row marquee */

  const marqueeRoot = document.getElementById('work-marquee');
  const worksDataEl = document.getElementById('works-data');

  const workMarquee = (() => {
    if (!marqueeRoot || !worksDataEl) return null;
    let allWorks = [];
    try {
      const parsed = JSON.parse(worksDataEl.textContent || '[]');
      if (Array.isArray(parsed)) allWorks = parsed;
    } catch {
      return null;
    }

    const shuffle = (arr) => {
      for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
      }
      return arr;
    };

    shuffle(allWorks);

    const rowEls = Array.from(marqueeRoot.querySelectorAll('.marquee-row'));
    const BUFFER = 260;

    const getGap = () =>
      parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--grid-gap')) || 16;

    const cardWidthFor = (item, rowHeight) => {
      const invRatio = item.r > 0 ? 1 / item.r : 1;
      const clampedAspect = Math.max(0.68, Math.min(1.85, invRatio));
      return Math.max(110, Math.round(rowHeight * clampedAspect));
    };

    const bindCard = (el, item, width, height) => {
      const refs = el._refs;
      const itemKey = item.k || `${item.i}:${item.img}`;
      if (el._boundKey !== itemKey) {
        el._boundKey = itemKey;
        stopCardVideo(el);
        el.dataset.id = String(item.i);
        el.dataset.mode = item.m;
        const ratio = item.r > 0 ? item.r : 1.0;
        el.dataset.ratio = String(ratio);
        el.style.setProperty('--ratio', String(ratio));
        el.dataset.category = item.c;
        el.dataset.videoSrc = item.vid || '';
        refs.img.src = item.img;
        refs.img.alt = item.t;
        refs.badge.hidden = !(item.m !== 'image_only' && (item.vid || item.ext));
        refs.meta.textContent = item.c;
        refs.title.textContent = item.t;
        refs.hit.href = `detail.php?id=${item.i}`;
        refs.hit.setAttribute('aria-label', `Open ${item.t} (${item.c})`);
      }
      refs.img.width = width;
      refs.img.height = height;
      el.style.width = `${width}px`;
      el.classList.remove('is-pooled');
    };

    const rows = rowEls.map((el, idx) => ({
      el,
      direction: Number(el.dataset.direction) || (idx % 2 === 1 ? 1 : -1),
      baseSpeed: Number(el.dataset.speed) || 38,
      items: [],
      active: [],
      pool: [],
      cameraX: 0,
      factor: 1,
      rowHover: false,
      cardHover: false,
      dragging: false,
      suppressClick: false,
      lastWidth: 0,
      lastHeight: 0,
    }));

    const acquireCard = (row) => {
      const el = row.pool.pop() || createPortoCard('porto-card marquee-card');
      if (!el.parentNode) row.el.appendChild(el);
      return el;
    };

    const releaseCard = (row, slot) => {
      stopCardVideo(slot.el);
      slot.el._boundKey = null;
      slot.el.classList.add('is-pooled');
      row.pool.push(slot.el);
    };

    const clearRow = (row) => {
      while (row.active.length) {
        releaseCard(row, row.active.pop());
      }
      row.cameraX = 0;
    };

    const syncRowCards = (row, dt) => {
      if (!row.items.length) return;
      const rowWidth = row.el.clientWidth;
      const rowHeight = row.el.clientHeight;
      if (rowWidth <= 0 || rowHeight <= 0) return;
      const gap = getGap();

      const targetFactor = row.dragging || row.cardHover ? 0 : (row.rowHover ? 0.14 : 1);
      row.factor += (targetFactor - row.factor) * (1 - Math.exp(-8 * dt));

      if (!reducedMotion.matches && !row.dragging) {
        row.cameraX += row.direction * row.baseSpeed * row.factor * dt;
      }

      if (Math.abs(row.cameraX) > 100000) {
        for (const slot of row.active) slot.worldX += row.cameraX;
        row.cameraX = 0;
      }

      if (row.lastHeight && row.lastHeight !== rowHeight) {
        clearRow(row);
      }
      row.lastWidth = rowWidth;
      row.lastHeight = rowHeight;

      while (row.active.length && row.active[0].worldX + row.active[0].width + row.cameraX < -BUFFER) {
        releaseCard(row, row.active.shift());
      }
      while (row.active.length && row.active[row.active.length - 1].worldX + row.cameraX > rowWidth + BUFFER) {
        releaseCard(row, row.active.pop());
      }

      if (row.active.length === 0) {
        const item = row.items[0];
        const width = cardWidthFor(item, rowHeight);
        const el = acquireCard(row);
        bindCard(el, item, width, rowHeight);
        row.active.push({
          el,
          dataIndex: 0,
          worldX: -row.cameraX - BUFFER * 0.4,
          width,
        });
      }

      while (true) {
        const tail = row.active[row.active.length - 1];
        if (tail.worldX + tail.width + row.cameraX >= rowWidth + BUFFER) break;
        const nextIdx = (tail.dataIndex + 1) % row.items.length;
        const item = row.items[nextIdx];
        const width = cardWidthFor(item, rowHeight);
        const el = acquireCard(row);
        bindCard(el, item, width, rowHeight);
        row.active.push({
          el,
          dataIndex: nextIdx,
          worldX: tail.worldX + tail.width + gap,
          width,
        });
      }

      while (true) {
        const head = row.active[0];
        if (head.worldX + row.cameraX <= -BUFFER) break;
        const prevIdx = (head.dataIndex - 1 + row.items.length) % row.items.length;
        const item = row.items[prevIdx];
        const width = cardWidthFor(item, rowHeight);
        const el = acquireCard(row);
        bindCard(el, item, width, rowHeight);
        row.active.unshift({
          el,
          dataIndex: prevIdx,
          worldX: head.worldX - gap - width,
          width,
        });
      }

      for (const slot of row.active) {
        const screenX = (slot.worldX + row.cameraX).toFixed(2);
        slot.el.style.transform = `translate3d(${screenX}px, 0, 0)`;
      }
    };

    rows.forEach((row) => {
      row.el.addEventListener('mouseenter', () => {
        row.rowHover = true;
      });
      row.el.addEventListener('mouseleave', () => {
        row.rowHover = false;
        row.cardHover = false;
      });

      row.el.addEventListener('pointerover', (e) => {
        const card = e.target.closest('.marquee-card');
        if (!card || !row.el.contains(card)) return;
        row.cardHover = true;
        if (card.dataset.videoSrc && card.dataset.mode !== 'autoplay_video') {
          startCardVideo(card);
        }
      });
      row.el.addEventListener('pointerout', (e) => {
        const card = e.target.closest('.marquee-card');
        if (!card || !row.el.contains(card)) return;
        if (e.relatedTarget && card.contains(e.relatedTarget)) return;
        row.cardHover = false;
        if (card.dataset.mode !== 'autoplay_video') {
          stopCardVideo(card);
        }
      });

      row.el.addEventListener('pointerdown', (e) => {
        if (e.button > 0) return;
        if (e.pointerType !== 'touch') e.preventDefault();
        row.dragging = true;
        row.suppressClick = false;
        row.el.classList.add('is-dragging');
        const pointerId = e.pointerId;
        const pointerType = e.pointerType;
        const startX = e.clientX;
        let prevX = startX;
        let captured = false;

        const onMove = (moveEv) => {
          if (!row.dragging || moveEv.pointerId !== pointerId) return;
          const dx = moveEv.clientX - prevX;
          prevX = moveEv.clientX;
          if (!captured && Math.abs(moveEv.clientX - startX) > 6) {
            row.suppressClick = true;
            captured = true;
            try {
              row.el.setPointerCapture(pointerId);
            } catch { /* ignore unsupported capture */ }
          }
          row.cameraX += dx;
          syncRowCards(row, 0);
        };

        const onEnd = (endEv) => {
          if (endEv.pointerId !== pointerId) return;
          row.dragging = false;
          row.el.classList.remove('is-dragging');
          if (pointerType === 'touch') {
            row.cardHover = false;
            row.rowHover = false;
          }
          if (captured) {
            try {
              row.el.releasePointerCapture(pointerId);
            } catch { /* ignore */ }
          }
          window.removeEventListener('pointermove', onMove);
          window.removeEventListener('pointerup', onEnd);
          window.removeEventListener('pointercancel', onEnd);
        };

        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onEnd);
        window.addEventListener('pointercancel', onEnd);
      });

      row.el.addEventListener(
        'click',
        (e) => {
          if (row.suppressClick) {
            e.preventDefault();
            e.stopPropagation();
            row.suppressClick = false;
          }
        },
        true
      );
    });

    let rafId = 0;
    let lastTime = 0;
    let lastVideoSync = 0;
    let inView = true;

    const syncAutoVideos = (active) => {
      if (!active || !inView || document.hidden) {
        for (const row of rows) {
          for (const slot of row.active) {
            if (slot.el.dataset.mode === 'autoplay_video' && slot.el._refs?.video) {
              stopCardVideo(slot.el);
            }
          }
        }
        return;
      }

      const candidates = [];
      for (const row of rows) {
        if (row.el.hidden) continue;
        const rowWidth = row.lastWidth || row.el.clientWidth;
        const center = rowWidth / 2;
        for (const slot of row.active) {
          if (slot.el.dataset.mode !== 'autoplay_video' || !slot.el.dataset.videoSrc) continue;
          const left = slot.worldX + row.cameraX;
          const right = left + slot.width;
          if (right < -40 || left > rowWidth + 40) {
            if (slot.el._refs?.video) stopCardVideo(slot.el);
            continue;
          }
          const dist = Math.abs((left + right) / 2 - center);
          candidates.push({ el: slot.el, dist });
        }
      }

      candidates.sort((a, b) => a.dist - b.dist);
      const activeSet = new Set(candidates.slice(0, MAX_PLAYING).map((c) => c.el));

      for (const row of rows) {
        for (const slot of row.active) {
          if (slot.el.dataset.mode !== 'autoplay_video') continue;
          if (activeSet.has(slot.el)) {
            startCardVideo(slot.el, 'metadata');
          } else if (slot.el._refs?.video) {
            stopCardVideo(slot.el);
          }
        }
      }
    };

    const tick = (now) => {
      rafId = 0;
      if (!inView || document.hidden) return;
      const dt = lastTime ? Math.min(0.05, (now - lastTime) / 1000) : 0.016;
      lastTime = now;
      for (const row of rows) {
        if (!row.el.hidden) syncRowCards(row, dt);
      }
      if (now - lastVideoSync >= 240) {
        lastVideoSync = now;
        syncAutoVideos(true);
      }
      rafId = requestAnimationFrame(tick);
    };

    const ensureRunning = () => {
      if (!rafId && inView && !document.hidden) {
        lastTime = 0;
        syncAutoVideos(true);
        rafId = requestAnimationFrame(tick);
      }
    };

    if ('IntersectionObserver' in window) {
      const obs = new IntersectionObserver(
        (entries) => {
          inView = entries.some((e) => e.isIntersecting);
          if (inView) ensureRunning();
          else {
            syncAutoVideos(false);
            if (rafId) {
              cancelAnimationFrame(rafId);
              rafId = 0;
            }
          }
        },
        { rootMargin: '120px 0px' }
      );
      obs.observe(marqueeRoot);
    }

    document.addEventListener('visibilitychange', () => {
      if (document.hidden) syncAutoVideos(false);
      else ensureRunning();
    });

    if ('ResizeObserver' in window) {
      new ResizeObserver(() => {
        for (const row of rows) {
          if (!row.el.hidden) syncRowCards(row, 0);
        }
      }).observe(marqueeRoot);
    }

    const setCategory = (categoryName) => {
      const filtered = categoryName === 'All'
        ? allWorks
        : allWorks.filter((w) => w.c === categoryName);

      marqueeRoot.hidden = filtered.length === 0;
      if (emptyState) emptyState.hidden = filtered.length > 0;

      const buckets = [[], [], []];
      const activeRowCount = Math.min(3, filtered.length);
      filtered.forEach((item, idx) => {
        if (activeRowCount > 0) buckets[idx % activeRowCount].push(item);
      });

      rows.forEach((row, i) => {
        clearRow(row);
        row.items = buckets[i];
        row.el.hidden = buckets[i].length === 0;
        if (buckets[i].length) syncRowCards(row, 0);
      });
      ensureRunning();
    };

    setCategory('All');
    return { setCategory };
  })();

  function applyFilter() {
    if (workMarquee) {
      workMarquee.setCategory(state.category);
      return;
    }
    if (virtualMasonry) {
      virtualMasonry.filter(state.category, state.tag, state.query);
    }
  }

  if (filters) {
    const tabs = Array.from(filters.querySelectorAll('[data-category]'));
    const strip = document.getElementById('tag-strip');
    const tagSelect = document.getElementById('tag-select');
    const search = document.getElementById('search');
    const searchClear = document.getElementById('search-clear');

    const initialCategory = new URLSearchParams(window.location.search).get('category');
    if (initialCategory && tabs.some((t) => t.dataset.category === initialCategory)) {
      state.category = initialCategory;
    }

    const sync = () => {
      for (const tab of tabs) {
        const active = tab.dataset.category === state.category;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-pressed', String(active));
      }
      if (tagSelect) {
        tagSelect.value = state.tag || '';
        tagSelect.classList.toggle('is-filtered', Boolean(state.tag));
      }
      if (strip) strip.hidden = state.category !== 'Software Development';
      if (searchClear) searchClear.hidden = state.query === '';
    };

    tabs.forEach((tab) =>
      tab.addEventListener('click', () => {
        const nextCat = tab.dataset.category;
        if (grid && grid.dataset.paginated === '1') {
          window.location.href = nextCat === 'All'
            ? 'portfolio.php'
            : `portfolio.php?category=${encodeURIComponent(nextCat)}`;
          return;
        }
        state.category = nextCat;
        if (state.category !== 'Software Development') state.tag = null;
        sync();
        applyFilter();
      })
    );

    if (tagSelect) {
      tagSelect.addEventListener('change', () => {
        state.tag = tagSelect.value || null;
        sync();
        applyFilter();
      });
    }

    if (search) {
      search.addEventListener('input', () => {
        state.query = search.value.trim().toLowerCase();
        sync();
        applyFilter();
      });

      document.addEventListener('keydown', (e) => {
        if (
          e.key === '/' &&
          document.activeElement !== search &&
          !e.ctrlKey &&
          !e.metaKey &&
          !e.altKey &&
          !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)
        ) {
          e.preventDefault();
          search.focus();
        }
      });
    }

    if (searchClear && search) {
      searchClear.addEventListener('click', () => {
        state.query = '';
        search.value = '';
        sync();
        applyFilter();
        search.focus();
      });
    }

    const reset = document.getElementById('reset-filters');
    if (reset) {
      reset.addEventListener('click', () => {
        if (grid && grid.dataset.paginated === '1' && state.category !== 'All') {
          window.location.href = 'portfolio.php';
          return;
        }
        state.category = 'All';
        state.tag = null;
        state.query = '';
        if (search) search.value = '';
        sync();
        applyFilter();
      });
    }

    sync();
    if (state.category !== 'All' && !(grid && grid.dataset.paginated === '1')) {
      applyFilter();
    }
  }

  /* Detail showcase & gallery masonry and lightbox */

  document.querySelectorAll('.porto-card[data-ratio]:not(.marquee-card), .detail_media[data-ratio], .detail-tile[data-ratio]').forEach((el) => {
    if (el.dataset.ratio) el.style.setProperty('--ratio', el.dataset.ratio);
  });

  const detailGrid = document.getElementById('detail-masonry-grid');
  if (detailGrid) {
    const tiles = Array.from(detailGrid.querySelectorAll('.detail-tile'));
    let lastDetailWidth = -1;
    let lastDetailCols = -1;
    let detailDebounceId = 0;

    const detailColumns = () => {
      const w = window.innerWidth;
      if (w >= 1100) return 4;
      if (w >= 768) return 3;
      return 2;
    };

    const layoutDetail = () => {
      if (!tiles.length) return;
      const cols = detailColumns();
      const gap = parseFloat(
        getComputedStyle(document.documentElement).getPropertyValue('--grid-gap')
      ) || 16;
      const width = detailGrid.clientWidth;
      if (width <= 0) return;
      const colWidth = Math.floor((width - (cols - 1) * gap) / cols);
      const colHeights = new Array(cols).fill(0);

      for (const tile of tiles) {
        const ratio = parseFloat(tile.dataset.ratio) || 1.0;
        const mediaHeight = Math.round(colWidth * ratio);
        const mediaWrap = tile.querySelector('.detail-tile_zoom, .detail-tile_media');
        const caption = tile.querySelector('.detail-tile_caption');
        tile.style.width = `${colWidth}px`;
        if (mediaWrap) mediaWrap.style.height = `${mediaHeight}px`;
        const captionHeight = caption ? caption.offsetHeight : 0;
        const height = mediaHeight + captionHeight;

        let col = 0;
        for (let c = 1; c < cols; c++) {
          if (colHeights[c] < colHeights[col]) col = c;
        }

        const x = col * (colWidth + gap);
        const y = colHeights[col];
        tile.style.transform = `translate3d(${x}px, ${y}px, 0)`;
        tile.style.height = `${height}px`;
        colHeights[col] += height + gap;
      }

      const tallest = Math.max(...colHeights, 0);
      detailGrid.style.height = `${Math.max(0, tallest - gap)}px`;
      lastDetailWidth = width;
      lastDetailCols = cols;
    };

    const scheduleDetailLayout = () => {
      if (detailDebounceId) clearTimeout(detailDebounceId);
      detailDebounceId = setTimeout(() => {
        detailDebounceId = 0;
        layoutDetail();
      }, 150);
    };

    detailGrid.classList.add('is-masonry');
    layoutDetail();
    if (!reducedMotion.matches) {
      requestAnimationFrame(() => requestAnimationFrame(() => detailGrid.classList.add('is-animated')));
    }

    tiles.forEach((tile) => {
      const img = tile.querySelector('img');
      if (img) {
        const syncImg = () => {
          if (img.naturalWidth > 0 && img.naturalHeight > 0) {
            const actualRatio = img.naturalHeight / img.naturalWidth;
            const prevRatio = parseFloat(tile.dataset.ratio) || 0;
            if (Math.abs(actualRatio - prevRatio) >= 0.005) {
              tile.dataset.ratio = String(actualRatio);
              tile.style.setProperty('--ratio', String(actualRatio));
              scheduleDetailLayout();
            }
          }
        };
        if (img.complete) syncImg();
        else img.addEventListener('load', syncImg, { once: true });
      }
      const video = tile.querySelector('video');
      if (video) {
        video.addEventListener('loadedmetadata', () => {
          if (video.videoWidth > 0 && video.videoHeight > 0) {
            const actualRatio = video.videoHeight / video.videoWidth;
            const prevRatio = parseFloat(tile.dataset.ratio) || 0;
            if (Math.abs(actualRatio - prevRatio) >= 0.005) {
              tile.dataset.ratio = String(actualRatio);
              tile.style.setProperty('--ratio', String(actualRatio));
              scheduleDetailLayout();
            }
          }
        }, { once: true });
      }
    });

    if ('ResizeObserver' in window) {
      new ResizeObserver(() => {
        if (detailGrid.clientWidth !== lastDetailWidth || detailColumns() !== lastDetailCols) {
          layoutDetail();
        }
      }).observe(detailGrid);
    } else {
      window.addEventListener('resize', layoutDetail);
    }
  }

  const lightbox = document.getElementById('lightbox');
  const lightboxStage = document.getElementById('lightbox-stage');
  const lightboxImg = document.getElementById('lightbox-img');
  const lightboxCaption = document.getElementById('lightbox-caption');

  if (lightbox && lightboxImg) {
    document.addEventListener('click', (e) => {
      const trigger = e.target.closest('[data-lightbox-src]');
      if (!trigger) return;
      lightboxImg.src = trigger.dataset.lightboxSrc || '';
      lightboxImg.alt = trigger.dataset.lightboxAlt || '';
      if (lightboxCaption) lightboxCaption.textContent = trigger.dataset.lightboxCaption || '';
      lightbox.showModal();
    });

    lightbox.addEventListener('click', (e) => {
      if (
        e.target === lightbox ||
        e.target === lightboxStage ||
        e.target === lightboxImg ||
        e.target.closest('[data-close]')
      ) {
        lightbox.close();
      }
    });
    lightbox.addEventListener('close', () => {
      lightboxImg.removeAttribute('src');
      if (lightboxCaption) lightboxCaption.textContent = '';
    });
  }

  /* Asset download and context-menu protection */

  const PROTECTED_MEDIA_SELECTOR = [
    'img',
    'video',
    '.site-nav_brand',
    '.site-footer_brand',
    '.tool-item',
    '.porto-card',
    '.detail_media',
    '.detail-tile',
    '#lightbox',
  ].join(', ');

  document.addEventListener('contextmenu', (e) => {
    if (e.target.closest(PROTECTED_MEDIA_SELECTOR)) {
      e.preventDefault();
    }
  });

  document.addEventListener('dragstart', (e) => {
    if (e.target.closest(PROTECTED_MEDIA_SELECTOR)) {
      e.preventDefault();
    }
  });

  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
    }
  });

  window.portfolioEngine = { layout };
})();
