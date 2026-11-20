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

  /* Shortest-column masonry */

  const grid = document.getElementById('masonry-grid');
  const cards = grid ? Array.from(grid.querySelectorAll('.porto-card')) : [];

  let lastWidth = -1;
  let lastColumns = -1;

  const columnCount = () => {
    const w = window.innerWidth;
    if (w >= 1100) return 4;
    if (w >= 768) return 3;
    return 2;
  };

  function layout() {
    if (!grid) return;
    const K = columnCount();
    const gap = parseFloat(
      getComputedStyle(document.documentElement).getPropertyValue('--grid-gap')
    ) || 16;
    const containerWidth = grid.clientWidth;
    const colWidth = Math.floor((containerWidth - (K - 1) * gap) / K);
    const colHeights = new Array(K).fill(0);

    for (const card of cards) {
      if (card.classList.contains('is-hidden')) continue;
      const ratio = parseFloat(card.dataset.ratio) || 1.0;
      const height = Math.round(colWidth * ratio);

      let col = 0;
      for (let c = 1; c < K; c++) {
        if (colHeights[c] < colHeights[col]) col = c;
      }

      const x = col * (colWidth + gap);
      const y = colHeights[col];
      card.style.transform = `translate3d(${x}px, ${y}px, 0)`;
      card.style.width = `${colWidth}px`;
      card.style.height = `${height}px`;
      colHeights[col] += height + gap;
    }

    const tallest = Math.max(...colHeights, 0);
    grid.style.height = `${Math.max(0, tallest - gap)}px`;
    lastWidth = containerWidth;
    lastColumns = K;
  }

  /* Media playback and video scheduler */

  const scheduler = (() => {
    const autoVideos = grid
      ? Array.from(grid.querySelectorAll('.porto-card[data-mode="autoplay_video"] video'))
      : [];
    const visible = new Map();
    let suspended = false;

    const load = (video) => {
      if (!video.getAttribute('src') && video.dataset.src) video.src = video.dataset.src;
    };

    const eligible = (video) => {
      const card = video.closest('.porto-card');
      return !(card && card.classList.contains('is-hidden'));
    };

    function apply() {
      const playing = new Set();
      if (!suspended && !document.hidden) {
        Array.from(visible.entries())
          .filter(([video]) => eligible(video))
          .sort((a, b) => b[1] - a[1])
          .slice(0, MAX_PLAYING)
          .forEach(([video]) => playing.add(video));
      }
      for (const video of autoVideos) {
        if (playing.has(video)) {
          load(video);
          if (video.paused) video.play().catch(() => { });
        } else if (!video.paused) {
          video.pause();
        }
      }
    }

    if (autoVideos.length && 'IntersectionObserver' in window) {
      const observer = new IntersectionObserver(
        (entries) => {
          for (const entry of entries) {
            if (entry.isIntersecting) visible.set(entry.target, entry.intersectionRatio);
            else visible.delete(entry.target);
          }
          apply();
        },
        { rootMargin: '150px 0px', threshold: [0, 0.25, 0.6, 0.9] }
      );
      autoVideos.forEach((video) => observer.observe(video));
    }

    document.addEventListener('visibilitychange', apply);

    return {
      refresh: apply,
      suspend() {
        suspended = true;
        apply();
        cards.forEach(stopHover);
      },
      resume() {
        suspended = false;
        apply();
      },
    };
  })();

  function startHover(card) {
    if (card.dataset.mode !== 'hover_video') return;
    const video = card.querySelector('video');
    if (!video) return;
    if (!video.getAttribute('src') && video.dataset.src) video.src = video.dataset.src;
    card.classList.add('is-playing');
    video.play().catch(() => { });
  }

  function stopHover(card) {
    if (card.dataset.mode !== 'hover_video') return;
    const video = card.querySelector('video');
    if (!video) return;
    video.pause();
    video.currentTime = 0;
    card.classList.remove('is-playing');
  }

  for (const card of cards) {
    if (card.dataset.mode !== 'hover_video') continue;
    card.addEventListener('mouseenter', () => startHover(card));
    card.addEventListener('mouseleave', () => stopHover(card));
    card.addEventListener('focusin', () => startHover(card));
    card.addEventListener('focusout', () => stopHover(card));
  }

  /* Multi-facet portfolio filter */

  const filters = document.getElementById('filters');
  const emptyState = document.getElementById('empty-state');
  const resultCount = document.getElementById('result-count');
  const resultLabel = document.getElementById('result-label');
  const state = { category: 'All', tag: null, query: '' };

  function applyFilter() {
    let shown = 0;
    for (const card of cards) {
      const tags = card.dataset.tags ? card.dataset.tags.split(',') : [];
      const matchCategory = state.category === 'All' || card.dataset.category === state.category;
      const matchTag = !state.tag || tags.includes(state.tag);
      const matchSearch =
        !state.query || card.dataset.title.includes(state.query) || card.dataset.tags.includes(state.query);
      const visible = matchCategory && matchTag && matchSearch;
      card.classList.toggle('is-hidden', !visible);
      if (visible) shown++;
    }
    if (emptyState) emptyState.hidden = shown > 0;
    if (resultCount) resultCount.textContent = String(shown);
    if (resultLabel) resultLabel.textContent = shown === 1 ? 'work' : 'works';
    layout();
    scheduler.refresh();
  }

  if (filters) {
    const tabs = Array.from(filters.querySelectorAll('[data-category]'));
    const chips = Array.from(filters.querySelectorAll('[data-tag]'));
    const strip = document.getElementById('tag-strip');
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
      for (const chip of chips) {
        const active = chip.dataset.tag === state.tag;
        chip.classList.toggle('is-active', active);
        chip.setAttribute('aria-pressed', String(active));
      }
      if (strip) strip.hidden = state.category !== 'Software Development';
      if (searchClear) searchClear.hidden = state.query === '';
    };

    tabs.forEach((tab) =>
      tab.addEventListener('click', () => {
        state.category = tab.dataset.category;
        if (state.category !== 'Software Development') state.tag = null;
        sync();
        applyFilter();
      })
    );

    chips.forEach((chip) =>
      chip.addEventListener('click', () => {
        state.tag = state.tag === chip.dataset.tag ? null : chip.dataset.tag;
        sync();
        applyFilter();
      })
    );

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
        state.category = 'All';
        state.tag = null;
        state.query = '';
        if (search) search.value = '';
        sync();
        applyFilter();
      });
    }

    sync();
    if (state.category !== 'All') {
      applyFilter();
    }
  }

  /* Initial layout and reflow */

  if (grid) {
    grid.classList.add('is-masonry');
    layout();
    if (!reducedMotion.matches) {
      requestAnimationFrame(() => requestAnimationFrame(() => grid.classList.add('is-animated')));
    }
    if ('ResizeObserver' in window) {
      new ResizeObserver(() => {
        if (grid.clientWidth !== lastWidth || columnCount() !== lastColumns) layout();
      }).observe(grid);
    } else {
      window.addEventListener('resize', layout);
    }
  }

  /* Detail gallery masonry and lightbox */

  const detailGrid = document.getElementById('detail-masonry-grid');
  if (detailGrid) {
    const tiles = Array.from(detailGrid.querySelectorAll('.detail-tile'));
    let lastDetailWidth = -1;
    let lastDetailCols = -1;

    const detailColumns = () => {
      const w = window.innerWidth;
      const cap = w >= 1100 ? 4 : w >= 768 ? 3 : 2;
      return Math.max(1, Math.min(tiles.length, cap));
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
            tile.dataset.ratio = String(img.naturalHeight / img.naturalWidth);
            layoutDetail();
          }
        };
        if (img.complete) syncImg();
        else img.addEventListener('load', syncImg, { once: true });
      }
      const video = tile.querySelector('video');
      if (video) {
        video.addEventListener('loadedmetadata', () => {
          if (video.videoWidth > 0 && video.videoHeight > 0) {
            tile.dataset.ratio = String(video.videoHeight / video.videoWidth);
            layoutDetail();
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
      if (e.target === lightbox || e.target === lightboxImg || e.target.closest('[data-close]')) {
        lightbox.close();
      }
    });
    lightbox.addEventListener('close', () => {
      lightboxImg.removeAttribute('src');
      if (lightboxCaption) lightboxCaption.textContent = '';
    });
  }

  window.portfolioEngine = { layout };
})();
