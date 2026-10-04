const formatTime = (value) => {
    const seconds = Math.max(0, Math.floor(Number(value) || 0));
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
};

const readJson = (value) => {
    try {
        return JSON.parse(value);
    } catch {
        return null;
    }
};

// Strings come from lang/<locale>/listen_client.php via the layout, so the
// player speaks the same language as the page around it.
const messages = { locale: 'vi', strings: {} };

const loadMessages = (root = document) => {
    const source = root.getElementById('ih-messages');
    if (!source) return false;
    const strings = readJson(source.textContent);
    if (!strings) return false;
    const changed = source.dataset.locale !== messages.locale;
    messages.locale = source.dataset.locale || messages.locale;
    messages.strings = strings;
    return changed;
};

const t = (key, params = {}) => Object.entries(params).reduce(
    (text, [name, value]) => text.replaceAll(`:${name}`, String(value)),
    messages.strings[key] ?? key,
);

loadMessages();

// Browser storage can be unavailable (private mode, blocked site data); the
// player must keep working without it.
const store = {
    get(key) {
        try { return localStorage.getItem(key); } catch { return null; }
    },
    set(key, value) {
        try { localStorage.setItem(key, value); } catch { /* Ignore unavailable storage. */ }
    },
    remove(key) {
        try { localStorage.removeItem(key); } catch { /* Ignore unavailable storage. */ }
    },
};

const svgPaths = {
    eye: '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
    eyeOff: '<path d="m3 3 18 18"/><path d="M10.6 5.6c.46-.07.92-.1 1.4-.1 6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-2.9 3.7M6.6 6.6C4 8.3 2.5 12 2.5 12S6 18.5 12 18.5c1.8 0 3.3-.5 4.6-1.2"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
    back: '<path d="m14.5 5-7 7 7 7"/>',
    forward: '<path d="m9.5 5 7 7-7 7"/>',
    check: '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
    note: '<path d="M9 17.5V6.2l10-2.2v11.5"/><circle cx="6.5" cy="17.5" r="2.5"/><circle cx="16.5" cy="15.5" r="2.5"/>',
};

const icon = (paths, className = '') => `<svg class="icon ${className}" viewBox="0 0 24 24" aria-hidden="true" focusable="false">${paths}</svg>`;

// Deterministic placeholder tone for artwork built in the browser.
const toneFor = (seed = '') => [...String(seed)].reduce((sum, char) => (sum * 31 + char.charCodeAt(0)) >>> 0, 7) % 6;

const artElement = (artwork, seed, className = '') => {
    const frame = document.createElement('span');
    frame.className = `art ${className}`.trim();
    frame.dataset.tone = String(toneFor(seed));
    if (artwork) {
        const image = document.createElement('img');
        image.src = artwork;
        image.alt = '';
        image.loading = 'lazy';
        frame.append(image);
    } else {
        frame.insertAdjacentHTML('beforeend', icon(svgPaths.note, 'art__icon'));
    }
    return frame;
};

const isMobile = () => window.matchMedia('(max-width: 767px)').matches;

const isTyping = (element) => element instanceof HTMLElement
    && (element.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(element.tagName));

/* -------------------------------------------------------------------------
   Toasts
   ------------------------------------------------------------------------- */

const dismissToast = (toast, delay = 4500) => {
    window.setTimeout(() => {
        toast.classList.add('is-leaving');
        window.setTimeout(() => toast.remove(), 300);
    }, delay);
};

const showToast = (message, kind = 'success') => {
    document.querySelectorAll('[data-toast]').forEach((toast) => toast.remove());
    const toast = document.createElement('div');
    toast.className = `toast ${kind === 'error' ? 'toast--error' : ''}`.trim();
    toast.dataset.toast = '';
    toast.setAttribute('role', 'status');
    toast.innerHTML = icon(kind === 'error' ? '<path d="M12 7.5v5.5M12 16.5v.01"/>' : svgPaths.check);
    const text = document.createElement('span');
    text.textContent = message;
    toast.append(text);
    document.body.append(toast);
    dismissToast(toast);
};

/* -------------------------------------------------------------------------
   Page-level behaviour (runs on load and after every in-app visit)
   ------------------------------------------------------------------------- */

const pageCleanups = [];

const initialiseGreeting = () => {
    const greeting = document.querySelector('[data-greeting]');
    if (greeting) {
        const hour = new Date().getHours();
        const part = hour >= 5 && hour < 11 ? 'morning'
            : hour >= 11 && hour < 13 ? 'noon'
                : hour >= 13 && hour < 18 ? 'afternoon'
                    : 'evening';
        const phrase = t(`greeting_${part}`);
        const name = greeting.dataset.name?.trim();
        greeting.textContent = name ? t('greeting_named', { greeting: phrase, name }) : phrase;
    }
};

const initialiseStickyHead = () => {
    const head = document.querySelector('[data-sticky-head]');
    const target = document.querySelector('.action-bar');
    if (!head || !target || !('IntersectionObserver' in window)) return;

    const offset = parseFloat(getComputedStyle(head).top) + head.offsetHeight;
    const observer = new IntersectionObserver(([entry]) => {
        const visible = !entry.isIntersecting && entry.boundingClientRect.top < offset;
        head.classList.toggle('is-visible', visible);
        head.inert = !visible;
    }, { rootMargin: `-${Math.round(offset)}px 0px 0px 0px` });
    observer.observe(target);
    pageCleanups.push(() => observer.disconnect());
};

const initialiseShelves = () => {
    document.querySelectorAll('[data-shelf]').forEach((shelf) => {
        const heading = shelf.closest('section')?.querySelector('.section-heading');
        if (!heading || heading.querySelector('.shelf-nav')) return;

        const tools = document.createElement('div');
        tools.className = 'section-heading__tools';
        [...heading.children].filter((child) => child.tagName !== 'H2').forEach((child) => tools.append(child));
        const nav = document.createElement('div');
        nav.className = 'shelf-nav';
        nav.innerHTML = `<button class="icon-button icon-button--sm icon-button--dim" type="button" data-shelf-step="-1" aria-label="${t('scroll_left')}">${icon(svgPaths.back)}</button>`
            + `<button class="icon-button icon-button--sm icon-button--dim" type="button" data-shelf-step="1" aria-label="${t('scroll_right')}">${icon(svgPaths.forward)}</button>`;
        tools.prepend(nav);
        heading.append(tools);

        const [previous, next] = nav.querySelectorAll('button');
        const update = () => {
            const overflow = shelf.scrollWidth - shelf.clientWidth > 4;
            nav.hidden = !overflow;
            previous.disabled = shelf.scrollLeft <= 4;
            next.disabled = shelf.scrollLeft + shelf.clientWidth >= shelf.scrollWidth - 4;
        };
        nav.addEventListener('click', (event) => {
            const button = event.target.closest('[data-shelf-step]');
            if (!button) return;
            shelf.scrollBy({ left: Number(button.dataset.shelfStep) * shelf.clientWidth * .9, behavior: 'smooth' });
        });
        shelf.addEventListener('scroll', update, { passive: true });
        const observer = new ResizeObserver(update);
        observer.observe(shelf);
        pageCleanups.push(() => observer.disconnect());
        update();
    });
};

const recentSearchKey = 'indie-house.recent-searches';

const initialiseRecentSearches = () => {
    const url = new URL(window.location.href);
    if (url.pathname !== '/search') return;

    const recent = readJson(store.get(recentSearchKey)) ?? [];
    const query = url.searchParams.get('q')?.trim();
    if (query) {
        const next = [query, ...recent.filter((item) => item.toLowerCase() !== query.toLowerCase())].slice(0, 8);
        store.set(recentSearchKey, JSON.stringify(next));
        return;
    }

    const section = document.querySelector('[data-recent-searches]');
    const list = section?.querySelector('[data-recent-list]');
    if (!section || !list || !Array.isArray(recent) || !recent.length) return;

    list.replaceChildren(...recent.map((item) => {
        const link = document.createElement('a');
        link.className = 'chip';
        link.href = `/search?q=${encodeURIComponent(item)}`;
        link.textContent = item;
        return link;
    }));
    section.hidden = false;
    section.querySelector('[data-recent-clear]')?.addEventListener('click', () => {
        store.remove(recentSearchKey);
        section.hidden = true;
    });
};

const initialiseSettingsNav = () => {
    const links = [...document.querySelectorAll('.settings-nav a')];
    if (!links.length || !('IntersectionObserver' in window)) return;

    const observer = new IntersectionObserver((entries) => {
        entries.filter((entry) => entry.isIntersecting).forEach((entry) => {
            links.forEach((link) => link.classList.toggle('is-active', link.hash === `#${entry.target.id}`));
        });
    }, { rootMargin: '-30% 0px -60% 0px' });
    document.querySelectorAll('.settings-section').forEach((section) => observer.observe(section));
    pageCleanups.push(() => observer.disconnect());
};

const openCreatePlaylist = () => {
    if (window.location.hash !== '#create-playlist') return;
    const panel = document.querySelector('[data-create-playlist]');
    if (!panel) return;
    panel.open = true;
    panel.scrollIntoView({ block: 'center', behavior: 'smooth' });
    window.setTimeout(() => panel.querySelector('input[name="title"]')?.focus({ preventScroll: true }), 300);
};

const initialisePage = () => {
    pageCleanups.splice(0).forEach((cleanup) => cleanup());
    initialiseGreeting();
    initialiseStickyHead();
    initialiseShelves();
    initialiseRecentSearches();
    initialiseSettingsNav();
    openCreatePlaylist();
    document.querySelectorAll('[data-toast]').forEach((toast) => dismissToast(toast));
};

/* -------------------------------------------------------------------------
   Menus and small controls (delegated once for every page, auth included)
   ------------------------------------------------------------------------- */

const menuSelector = 'details[data-track-menu], details[data-account-menu], details[data-popover]';

const closeMenus = (except = null) => {
    document.querySelectorAll(`${menuSelector}`).forEach((menu) => {
        if (menu !== except && menu.open) menu.open = false;
    });
};

document.addEventListener('toggle', (event) => {
    const menu = event.target;
    if (!(menu instanceof HTMLDetailsElement) || !menu.matches(menuSelector) || !menu.open) return;

    closeMenus(menu);
    menu.classList.remove('is-up');
    const panel = menu.querySelector('.menu-panel');
    if (!panel) return;
    panel.style.maxHeight = '';
    if (isMobile() && menu.matches('[data-track-menu], [data-popover]')) return;

    // Open upwards when the fixed player or guest bar would cover the panel.
    const bottomLimit = Math.min(window.innerHeight, ...[...document.querySelectorAll('[data-player], .guest-bar')]
        .map((element) => element.getBoundingClientRect())
        .filter((box) => box.height > 0)
        .map((box) => box.top));
    const rect = panel.getBoundingClientRect();
    if (rect.bottom <= bottomLimit) return;

    const top = menu.getBoundingClientRect().top;
    const spaceAbove = top - 72;
    const spaceBelow = bottomLimit - rect.top - 12;
    // Flip when there is more room above; otherwise keep it below and let
    // the panel scroll inside the space that is left.
    if (spaceAbove > spaceBelow) menu.classList.add('is-up');
    if (rect.height > Math.max(spaceAbove, spaceBelow)) {
        panel.style.maxHeight = `${Math.max(200, Math.max(spaceAbove, spaceBelow))}px`;
    }
}, true);

document.addEventListener('click', (event) => {
    const openMenu = event.target.closest(menuSelector);
    closeMenus(openMenu);
    // On phones a click on the menu's own scrim (the details element itself) closes it.
    if (openMenu?.open && event.target === openMenu) {
        openMenu.open = false;
        return;
    }

    const toggle = event.target.closest('[data-password-toggle]');
    if (toggle) {
        const input = toggle.closest('.password-field')?.querySelector('input');
        if (!input) return;
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        toggle.setAttribute('aria-label', reveal ? toggle.dataset.labelHide : toggle.dataset.labelShow);
        toggle.setAttribute('aria-pressed', String(reveal));
        toggle.innerHTML = icon(reveal ? svgPaths.eyeOff : svgPaths.eye);
        return;
    }

    const closer = event.target.closest('[data-close-details]');
    if (closer) {
        const details = closer.closest('details');
        if (details) details.open = false;
        return;
    }

    const copy = event.target.closest('[data-copy-link]');
    if (copy) {
        navigator.clipboard?.writeText(window.location.href.split('#')[0])
            .then(() => showToast(t('link_copied')))
            .catch(() => showToast(t('link_copy_failed'), 'error'));
        return;
    }

    const expand = event.target.closest('[data-expand-toggle]');
    if (expand) {
        const container = expand.closest('[data-expandable]');
        const expanded = !container.classList.contains('is-expanded');
        container.classList.toggle('is-expanded', expanded);
        expand.setAttribute('aria-expanded', String(expanded));
        expand.textContent = expanded ? expand.dataset.labelLess : expand.dataset.labelMore;
        return;
    }

    const filter = event.target.closest('[data-search-filter]');
    if (filter) {
        const results = document.querySelector('[data-search-results]');
        if (results) results.dataset.filter = filter.dataset.searchFilter;
        filter.parentElement.querySelectorAll('[data-search-filter]').forEach((chip) => {
            const active = chip === filter;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-pressed', String(active));
        });
        return;
    }

    if (event.target.closest('[data-history-back]')) {
        window.history.back();
        return;
    }
    if (event.target.closest('[data-history-forward]')) {
        window.history.forward();
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;

    // Destructive forms need a second, deliberate submit within four seconds.
    if (form.dataset.armed === 'true') return;
    event.preventDefault();
    const button = form.querySelector('[type="submit"]');
    const label = form.querySelector('[data-confirm-label]');
    const original = label?.textContent;
    form.dataset.armed = 'true';
    button?.classList.add('is-armed');
    if (label) label.textContent = form.dataset.confirm;
    window.setTimeout(() => {
        form.dataset.armed = 'false';
        button?.classList.remove('is-armed');
        if (label) label.textContent = original;
    }, 4000);
}, true);

document.addEventListener('change', (event) => {
    const input = event.target.closest?.('[data-avatar-input]');
    const file = input?.files?.[0];
    const preview = input?.closest('[data-avatar-field]')?.querySelector('[data-avatar-preview]');
    if (!file || !preview || !file.type.startsWith('image/')) return;

    const image = document.createElement('img');
    image.src = URL.createObjectURL(file);
    image.alt = '';
    preview.replaceChildren(image);
});

// A broken or expired artwork URL falls back to the tinted placeholder.
document.addEventListener('error', (event) => {
    const image = event.target;
    if (image instanceof HTMLImageElement && image.closest('.art, .avatar')) image.hidden = true;
}, true);

window.addEventListener('hashchange', openCreatePlaylist);

/* -------------------------------------------------------------------------
   Player, search and in-app navigation
   ------------------------------------------------------------------------- */

document.addEventListener('DOMContentLoaded', () => {
    initialisePage();

    const player = document.querySelector('[data-player]');

    if (!player) return;

    const audio = player.querySelector('[data-player-audio]');
    const title = player.querySelector('[data-player-title]');
    const artist = player.querySelector('[data-player-artist]');
    const art = player.querySelector('[data-player-art]');
    const backdrop = player.querySelector('[data-player-backdrop]');
    const contextLabel = player.querySelector('[data-player-context]');
    const toggle = player.querySelector('[data-player-toggle]');
    const toggleIcon = player.querySelector('[data-player-toggle-icon]');
    const previous = player.querySelector('[data-player-previous]');
    const next = player.querySelector('[data-player-next]');
    const shuffleButton = player.querySelector('[data-player-shuffle]');
    const repeatButton = player.querySelector('[data-player-repeat]');
    const repeatOne = player.querySelector('[data-player-repeat-one]');
    const expand = player.querySelector('[data-player-expand]');
    const collapse = player.querySelector('[data-player-collapse]');
    const errorMessage = player.querySelector('[data-player-error]');
    const seek = player.querySelector('[data-player-seek]');
    const current = player.querySelector('[data-player-current]');
    const duration = player.querySelector('[data-player-duration]');
    const miniProgress = player.querySelector('[data-player-mini-progress]');
    const volume = player.querySelector('[data-player-volume]');
    const muteButton = player.querySelector('[data-player-mute]');
    const volumeIcon = player.querySelector('[data-player-volume-icon]');
    const queueToggle = player.querySelector('[data-queue-toggle]');
    const queuePanel = document.querySelector('[data-queue-panel]');
    const historyUrl = player.dataset.historyUrl;
    const storageKey = 'indie-house.player.track';
    const modeStorageKey = 'indie-house.player.modes';
    const queueStorageKey = 'indie-house.queue.open';
    const state = {
        track: null,
        queue: [],
        queueIndex: -1,
        history: [],
        shuffleBag: [],
        shuffle: false,
        repeat: 'off',
        context: null,
        loading: false,
        playRequest: 0,
        playbackId: null,
        historyLogged: false,
        historyInFlight: false,
        visiting: false,
        lastVolume: 1,
        resumeAt: 0,
        resumeTried: false,
        flacNoticeShown: false,
    };
    let activeSearchController = null;
    let errorTimer = null;

    const newPlaybackId = () => window.crypto?.randomUUID?.()
        ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`;

    const setError = (message = '') => {
        window.clearTimeout(errorTimer);
        errorMessage.textContent = message;
        errorMessage.hidden = !message;
        if (message) errorTimer = window.setTimeout(() => { errorMessage.hidden = true; }, 9000);
    };

    const isPlaying = () => !audio.paused && !audio.ended;

    const syncBodyState = () => {
        document.body.classList.toggle('has-player', !player.hidden);
        document.body.classList.toggle('queue-open', !queuePanel.hidden);
        document.body.classList.toggle('player-open', player.classList.contains('is-expanded'));
    };

    // Reflects the playing track and context on rows, play buttons, the
    // library panel and the queue, wherever they are on the current page.
    const syncNowPlaying = () => {
        const playing = isPlaying();
        const trackId = state.track?.id;
        const contextId = state.context?.id;

        document.querySelectorAll('[data-track-row]').forEach((row) => {
            const isCurrent = Boolean(trackId) && row.dataset.trackId === trackId;
            row.classList.toggle('is-current', isCurrent);
            row.classList.toggle('is-playing', isCurrent && playing);
        });

        document.querySelectorAll('.play-button[data-play-context], .play-fab[data-play-context]').forEach((button) => {
            const isCurrent = Boolean(contextId) && button.dataset.playContext === contextId;
            const label = button.dataset.playContextLabel ?? '';
            button.classList.toggle('is-current', isCurrent);
            button.classList.toggle('is-playing', isCurrent && playing);
            button.setAttribute('aria-label', t(isCurrent && playing ? 'pause_label' : 'play_label', { label }));
        });

        document.querySelectorAll('[data-context-link]').forEach((link) => {
            link.classList.toggle('is-live', Boolean(contextId) && link.dataset.contextLink === contextId && playing);
        });

        renderQueue();
    };

    const updateControls = () => {
        const playing = isPlaying();
        const label = t(state.loading ? 'loading' : playing ? 'pause' : 'play');
        toggle.setAttribute('aria-label', label);
        toggle.title = label;
        toggle.disabled = state.loading;
        toggle.classList.toggle('is-loading', state.loading);
        toggle.classList.toggle('is-playing', playing);
        player.classList.toggle('is-playing', playing);
        const iconState = playing ? 'pause' : 'play';
        if (toggle.dataset.iconState !== iconState) {
            toggleIcon.innerHTML = playing ? player.dataset.iconPause : player.dataset.iconPlay;
            toggle.dataset.iconState = iconState;
        }
        shuffleButton.setAttribute('aria-pressed', String(state.shuffle));
        shuffleButton.setAttribute('aria-label', t(state.shuffle ? 'shuffle_off' : 'shuffle_on'));
        shuffleButton.title = t('shuffle');
        shuffleButton.classList.toggle('is-active', state.shuffle);
        repeatButton.setAttribute('aria-pressed', String(state.repeat !== 'off'));
        repeatButton.setAttribute('aria-label', t(state.repeat === 'off' ? 'repeat_turn_on' : state.repeat === 'all' ? 'repeat_turn_one' : 'repeat_turn_off'));
        repeatButton.title = t(`repeat_${state.repeat}`);
        repeatButton.classList.toggle('is-active', state.repeat !== 'off');
        repeatOne.hidden = state.repeat !== 'one';
        previous.disabled = !state.track || (audio.currentTime <= 3 && !state.history.length);
        next.disabled = !state.track || state.queue.length < 2
            || (state.shuffle && !state.shuffleBag.length && state.repeat === 'off')
            || (!state.shuffle && state.repeat === 'off' && state.queueIndex >= state.queue.length - 1);
        previous.title = t(previous.disabled ? 'no_previous' : 'previous');
        previous.setAttribute('aria-label', t('previous'));
        next.title = t(next.disabled ? 'no_next' : 'next');
        next.setAttribute('aria-label', t('next'));
    };

    const refreshPlaybackUi = () => {
        updateControls();
        syncNowPlaying();
    };

    const saveModes = () => store.set(modeStorageKey, JSON.stringify({ shuffle: state.shuffle, repeat: state.repeat }));
    const playWithTimeout = async () => {
        let timeout;
        try {
            await Promise.race([
                audio.play(),
                new Promise((_, reject) => {
                    timeout = window.setTimeout(() => reject(new Error('Audio startup timed out.')), 12000);
                }),
            ]);
        } finally {
            window.clearTimeout(timeout);
        }
    };

    const setRangeFill = (range, ratio) => {
        range.style.setProperty('--fill', `${Math.max(0, Math.min(1, ratio)) * 100}%`);
    };

    const updateVolumeUi = () => {
        const value = audio.muted ? 0 : audio.volume;
        volume.value = String(value);
        setRangeFill(volume, value);
        const glyph = value === 0 ? 'iconMute' : value < .5 ? 'iconLow' : 'iconVolume';
        volumeIcon.innerHTML = volumeIcon.dataset[glyph];
        const label = t(value === 0 ? 'unmute' : 'mute');
        muteButton.setAttribute('aria-label', label);
        muteButton.title = label;
    };

    const updateArt = (track) => {
        art.replaceChildren(artElement(track.artwork, track.album ?? track.title));
        if (track.artwork) backdrop.src = track.artwork;
        else backdrop.removeAttribute('src');
    };

    const updateMetadata = (track) => {
        player.hidden = false;
        title.textContent = track.title;
        title.href = track.albumUrl ?? track.artistUrl ?? '/';
        artist.textContent = track.artist;
        artist.href = track.artistUrl ?? '/catalogue';
        contextLabel.textContent = state.context?.label ?? track.album ?? 'Indie House';
        duration.textContent = formatTime(track.duration);
        current.textContent = '0:00';
        seek.value = '0';
        setRangeFill(seek, 0);
        miniProgress.style.setProperty('--progress', '0');
        updateArt(track);
        setError();
        syncBodyState();
        refreshPlaybackUi();

        if ('mediaSession' in navigator) {
            navigator.mediaSession.metadata = new MediaMetadata({
                title: track.title,
                artist: track.artist,
                album: track.album ?? '',
                artwork: track.artwork ? [{ src: track.artwork }] : [],
            });
        }
    };

    const saveTrack = (track) => {
        const persisted = { ...track };
        delete persisted.source;
        store.set(storageKey, JSON.stringify(persisted));
    };

    const recordListening = (completed = false) => {
        const playedDuration = Math.floor(audio.currentTime);

        if (
            !historyUrl
            || player.dataset.recordHistory === '0'
            || !state.track
            || !state.playbackId
            || state.historyLogged
            || state.historyInFlight
            || (completed ? playedDuration < 1 : playedDuration < 10)
        ) {
            return Promise.resolve();
        }

        // Both `ended` and a browser's pagehide can run during one navigation.
        // Marking first ensures one playback creates no more than one log.
        state.historyInFlight = true;
        state.historyLogged = true;

        return fetch(historyUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            keepalive: true,
            body: JSON.stringify({
                track_id: state.track.id,
                played_duration_seconds: playedDuration,
                completed,
                playback_id: state.playbackId,
            }),
        }).catch(() => {
            // Let a later lifecycle event retry a transient failure.
            state.historyLogged = false;
        }).finally(() => {
            state.historyInFlight = false;
        });
    };

    const refillShuffleBag = () => {
        state.shuffleBag = state.queue.map((_, index) => index).filter((index) => index !== state.queueIndex);
        for (let index = state.shuffleBag.length - 1; index > 0; index -= 1) {
            const random = Math.floor(Math.random() * (index + 1));
            [state.shuffleBag[index], state.shuffleBag[random]] = [state.shuffleBag[random], state.shuffleBag[index]];
        }
    };

    const setQueue = (tracks, activeTrack) => {
        const queue = tracks.filter((track, index, all) => track?.id && all.findIndex(({ id }) => id === track.id) === index);
        const activeIndex = queue.findIndex(({ id }) => id === activeTrack.id);

        state.queue = activeIndex >= 0 ? queue : [activeTrack];
        state.queueIndex = activeIndex >= 0 ? activeIndex : 0;
        state.history = [];
        state.shuffleBag = [];
        if (state.shuffle) refillShuffleBag();
        updateControls();
    };

    const startPlayback = (track) => {
        state.track = track;
        state.playbackId = newPlaybackId();
        state.historyLogged = false;
        state.historyInFlight = false;
        state.resumeAt = 0;
        state.resumeTried = false;
        updateMetadata(track);
    };

    // Lossless streams FLAC. A browser that cannot play it gets 320 kbps, and
    // the listener is told once per page load.
    const streamQuality = () => {
        const wanted = player.dataset.audioQuality || 'auto';
        if (wanted !== 'lossless' || audio.canPlayType('audio/flac') !== '') return wanted;
        if (!state.flacNoticeShown) {
            state.flacNoticeShown = true;
            showToast(t('lossless_unsupported'));
        }
        return 'high';
    };

    // The same-origin audio URL. Laravel answers it with a redirect to the
    // stream gateway, carrying a playback ticket for this track and quality.
    const audioSourceFor = (track) => {
        const source = new URL(track.sourceUrl.replace(/\/source(?:\?.*)?$/, '/audio'), window.location.origin);
        if (source.origin !== window.location.origin) throw new Error('Invalid audio source origin.');
        if (player.dataset.guest !== '1') source.searchParams.set('quality', streamQuality());
        return source.href;
    };

    // A playback ticket lasts an hour, so after a long pause the gateway
    // refuses the next range request. Loading the URL again signs a new ticket;
    // the song carries on from where it stopped, as the same listen.
    const resumeCurrentTrack = async () => {
        const position = state.resumeAt;
        const request = ++state.playRequest;
        state.resumeTried = true;
        state.loading = true;
        updateControls();
        try {
            audio.src = audioSourceFor(state.track);
            audio.addEventListener('loadedmetadata', () => { audio.currentTime = position; }, { once: true });
            await playWithTimeout();
        } catch (error) {
            if (request === state.playRequest && errorMessage.hidden) {
                setError(t(player.dataset.guest === '1' ? 'preview_failed' : 'audio_failed'));
            }
            console.error(error);
        } finally {
            if (request === state.playRequest) {
                state.loading = false;
                refreshPlaybackUi();
            }
        }
    };

    const playTrack = async (track, queue = null, restart = false, context = undefined) => {
        if (queue) setQueue(queue, track);
        if (context !== undefined) state.context = context;

        // Play after a failure picks the song up where it stopped.
        if (state.track?.id === track.id && audio.error && !restart && state.resumeAt > 0) {
            await resumeCurrentTrack();
            return;
        }

        if (state.track?.id === track.id && audio.src && !restart && !audio.ended && !audio.error) {
            if (audio.paused) {
                try {
                    await playWithTimeout();
                } catch (error) {
                    if (errorMessage.hidden) setError(t('resume_failed'));
                    console.error(error);
                }
            } else audio.pause();
            return;
        }

        recordListening(false);
        startPlayback(track);
        state.loading = true;
        const request = ++state.playRequest;
        updateControls();

        try {
            // Assign the same-origin media URL during the tap gesture, before any
            // asynchronous work, so mobile browsers can authorize audio.play().
            audio.src = audioSourceFor(track);
            await playWithTimeout();
            saveTrack(track);
        } catch (error) {
            if (request === state.playRequest && errorMessage.hidden) {
                setError(t(player.dataset.guest === '1' ? 'preview_failed' : 'play_failed'));
            }
            console.error(error);
        } finally {
            if (request === state.playRequest) {
                state.loading = false;
                refreshPlaybackUi();
            }
        }
    };

    const parsedTracksOnPage = (root = document) => [...root.querySelectorAll('[data-play-track][data-track]')]
        .map((element) => readJson(element.dataset.track))
        .filter(Boolean);

    const cycleTrack = (direction, ended = false) => {
        if (!state.queue.length || state.queueIndex < 0) return;
        if (direction < 0) {
            if (audio.currentTime > 3) {
                audio.currentTime = 0;
                return;
            }
            if (!state.history.length) return;
            const previousIndex = state.history.pop();
            if (state.shuffle && !state.shuffleBag.includes(state.queueIndex)) state.shuffleBag.push(state.queueIndex);
            state.queueIndex = previousIndex;
        } else {
            if (ended && state.repeat === 'one') {
                playTrack(state.track, null, true);
                return;
            }
            let nextIndex;
            if (state.shuffle) {
                if (!state.shuffleBag.length && state.repeat === 'all') refillShuffleBag();
                nextIndex = state.shuffleBag.shift();
            } else if (state.queueIndex < state.queue.length - 1) {
                nextIndex = state.queueIndex + 1;
            } else if (state.repeat === 'all') {
                nextIndex = 0;
            }
            if (nextIndex === undefined) {
                refreshPlaybackUi();
                return;
            }
            state.history.push(state.queueIndex);
            state.queueIndex = nextIndex;
        }
        updateControls();
        playTrack(state.queue[state.queueIndex], null, true);
    };

    const jumpToQueueIndex = (index) => {
        if (!state.queue[index] || index === state.queueIndex) return;
        state.history.push(state.queueIndex);
        state.shuffleBag = state.shuffleBag.filter((item) => item !== index);
        state.queueIndex = index;
        playTrack(state.queue[index], null, true);
    };

    /* Queue panel ------------------------------------------------------- */

    const upcomingIndexes = () => {
        if (!state.queue.length || state.queueIndex < 0) return [];
        if (state.shuffle) return [...state.shuffleBag];
        const indexes = [];
        for (let index = state.queueIndex + 1; index < state.queue.length; index += 1) indexes.push(index);
        if (state.repeat === 'all') for (let index = 0; index < state.queueIndex; index += 1) indexes.push(index);
        return indexes;
    };

    const queueRow = (track, index = null) => {
        const row = document.createElement(index === null ? 'div' : 'button');
        row.className = `queue-item ${index === null ? 'is-current' : ''}`.trim();
        if (index !== null) {
            row.type = 'button';
            row.dataset.queueIndex = String(index);
            row.setAttribute('aria-label', t('play_track', { title: track.title, artist: track.artist }));
        }
        const copy = document.createElement('span');
        copy.className = 'queue-item__copy';
        const name = document.createElement('strong');
        name.textContent = track.title;
        const by = document.createElement('small');
        by.textContent = track.artist;
        copy.append(name, by);
        const time = document.createElement('span');
        time.className = 'queue-item__time';
        time.textContent = formatTime(track.duration);
        row.append(artElement(track.artwork, track.album ?? track.title), copy, time);
        return row;
    };

    function renderQueue() {
        if (!queuePanel || queuePanel.hidden) return;
        const currentSlot = queuePanel.querySelector('[data-queue-current]');
        const list = queuePanel.querySelector('[data-queue-list]');
        const empty = queuePanel.querySelector('[data-queue-empty]');
        const context = queuePanel.querySelector('[data-queue-context]');

        currentSlot.replaceChildren(...(state.track ? [queueRow(state.track)] : []));
        const upcoming = upcomingIndexes().slice(0, 50);
        list.replaceChildren(...upcoming.map((index) => {
            const item = document.createElement('li');
            item.append(queueRow(state.queue[index], index));
            return item;
        }));
        empty.hidden = upcoming.length > 0;
        context.textContent = state.context?.label ?? '';
    }

    const setQueueOpen = (open, persist = true) => {
        queuePanel.hidden = !open;
        queueToggle.setAttribute('aria-pressed', String(open));
        queueToggle.setAttribute('aria-label', t(open ? 'queue_close' : 'queue_open'));
        if (persist && !isMobile()) store.set(queueStorageKey, open ? '1' : '0');
        syncBodyState();
        renderQueue();
        if (open && isMobile()) queuePanel.querySelector('[data-queue-close]')?.focus();
    };

    queueToggle.addEventListener('click', () => setQueueOpen(queuePanel.hidden));
    queuePanel.querySelector('[data-queue-close]').addEventListener('click', () => {
        setQueueOpen(false);
        if (isMobile()) queueToggle.focus();
    });
    queuePanel.addEventListener('click', (event) => {
        const item = event.target.closest('[data-queue-index]');
        if (item) jumpToQueueIndex(Number(item.dataset.queueIndex));
    });

    /* Live search suggestions -------------------------------------------- */

    const initialiseSearch = () => {
        document.querySelectorAll('[data-live-search-form]').forEach((form) => {
            if (form.dataset.searchBound) return;

            form.dataset.searchBound = 'true';
            const input = form.querySelector('[data-live-search]');
            const panel = form.querySelector('[data-search-suggestions]');
            const status = form.querySelector('[data-search-status]');
            const list = form.querySelector('[data-search-list]');
            let debounce;

            const setOpen = (open) => {
                panel.hidden = !open;
                input.setAttribute('aria-expanded', String(open));
            };

            const addGroup = (label, items, kind, queue) => {
                if (!items.length) return;

                const group = document.createElement('div');
                group.className = 'search-suggestions__group';
                const heading = document.createElement('p');
                heading.className = 'search-suggestions__heading';
                heading.textContent = label;
                group.append(heading);

                items.forEach((item) => {
                    const row = document.createElement(kind === 'track' ? 'button' : 'a');
                    row.className = 'search-suggestions__item';
                    if (kind === 'track') {
                        row.type = 'button';
                        row.dataset.playTrack = '';
                        row.dataset.track = JSON.stringify(item);
                        row.dataset.trackQueue = JSON.stringify(queue);
                        row.dataset.playContext = `search:${input.value.trim()}`;
                        row.dataset.playContextLabel = t('search_context', { query: input.value.trim() });
                    } else {
                        row.href = item.url;
                    }

                    const copy = document.createElement('span');
                    copy.className = 'search-suggestions__copy';
                    const name = document.createElement('strong');
                    name.textContent = item.title;
                    const detail = document.createElement('small');
                    detail.textContent = kind === 'artist' ? t('artist') : kind === 'album' ? t('album_by', { artist: item.artist }) : item.artist;
                    copy.append(name, detail);
                    row.append(artElement(item.artwork, item.title, `search-suggestions__art ${kind === 'artist' ? 'art--artist' : ''}`), copy);
                    group.append(row);
                });
                list.append(group);
            };

            const search = async () => {
                const query = input.value.trim();
                activeSearchController?.abort();
                list.replaceChildren();
                if (!query || !form.isConnected) {
                    setOpen(false);
                    return;
                }

                status.textContent = t('searching');
                setOpen(true);
                const controller = new AbortController();
                activeSearchController = controller;
                const url = new URL(form.action, window.location.href);
                url.searchParams.set('q', query);
                url.searchParams.set('suggest', '1');

                try {
                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                        signal: controller.signal,
                    });
                    if (!response.ok) throw new Error(`Search failed with ${response.status}`);
                    const results = await response.json();
                    if (!form.isConnected || input.value.trim() !== query) return;

                    list.replaceChildren();
                    addGroup(t('songs'), results.tracks ?? [], 'track', results.tracks ?? []);
                    addGroup(t('artists'), results.artists ?? [], 'artist');
                    addGroup(t('albums'), results.albums ?? [], 'album');
                    status.textContent = list.childElementCount ? '' : t('no_results', { query });
                } catch (error) {
                    if (error.name === 'AbortError') return;
                    list.replaceChildren();
                    status.textContent = t('suggest_failed');
                }
            };

            input.addEventListener('input', () => {
                window.clearTimeout(debounce);
                debounce = window.setTimeout(search, 180);
            });
            input.addEventListener('focus', () => {
                if (input.value.trim() && panel.hidden) search();
            });
            form.addEventListener('keydown', (event) => {
                const items = [...list.querySelectorAll('.search-suggestions__item'), form.querySelector('.search-suggestions__all')];
                const position = items.indexOf(document.activeElement);
                if (event.key === 'Escape') {
                    setOpen(false);
                    input.focus();
                } else if (event.key === 'ArrowDown' && !panel.hidden) {
                    event.preventDefault();
                    items[Math.min(position + 1, items.length - 1)]?.focus();
                } else if (event.key === 'ArrowUp' && !panel.hidden && position >= 0) {
                    event.preventDefault();
                    (position === 0 ? input : items[position - 1]).focus();
                }
            });
            form.addEventListener('submit', () => setOpen(false));
        });
    };

    /* In-app navigation --------------------------------------------------- */

    const setSheetOpen = (open) => {
        player.classList.toggle('is-expanded', open);
        player.toggleAttribute('aria-modal', open);
        if (open) player.setAttribute('role', 'dialog');
        else player.removeAttribute('role');
        expand.setAttribute('aria-expanded', String(open));
        syncBodyState();
        document.querySelector('.app-shell').inert = open;
        document.querySelector('.mobile-nav').inert = open;
        if (open) collapse.focus(); else expand.focus({ preventScroll: true });
    };

    const scrollAfterVisit = (url) => {
        const hash = new URL(url, window.location.href).hash;
        const target = hash ? document.getElementById(decodeURIComponent(hash.slice(1))) : null;
        // A new page appears at its top at once; `scroll-behavior: smooth`
        // would otherwise animate from the previous page's scroll position.
        if (target) target.scrollIntoView({ behavior: 'instant' });
        else window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
    };

    const visit = async (url, pushState = true, keepScroll = false) => {
        if (state.visiting) return;

        state.visiting = true;
        const scrollTop = window.scrollY;
        document.body.classList.add('is-navigating');
        closeMenus();
        try {
            const response = await fetch(url, {
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) throw new Error(`Navigation failed with ${response.status}`);

            const nextDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
            const nextShell = nextDocument.querySelector('.app-shell');
            const currentShell = document.querySelector('.app-shell');
            const nextMobileNav = nextDocument.querySelector('.mobile-nav');
            const currentMobileNav = document.querySelector('.mobile-nav');

            if (!nextShell || !currentShell || !nextMobileNav || !currentMobileNav) {
                throw new Error('Navigation response has no application shell.');
            }

            if (player.classList.contains('is-expanded')) setSheetOpen(false);
            if (isMobile() && !queuePanel.hidden) setQueueOpen(false, false);

            currentShell.replaceWith(nextShell);
            currentMobileNav.replaceWith(nextMobileNav);
            document.body.className = nextDocument.body.className;
            document.title = nextDocument.title;
            if (loadMessages(nextDocument)) applyLanguage(nextDocument);
            syncBodyState();

            if (pushState) history.pushState({}, '', url);

            if (keepScroll) window.scrollTo({ top: scrollTop, left: 0, behavior: 'instant' });
            else scrollAfterVisit(url);
            initialisePageControls();
            document.getElementById('main')?.focus({ preventScroll: true });
        } catch (error) {
            // Normal browser navigation remains the safe fallback if an HTML
            // response cannot be rendered as an in-app visit.
            console.error(error);
            window.location.assign(url);
        } finally {
            state.visiting = false;
            document.body.classList.remove('is-navigating');
        }
    };

    // Elements outside the page shell stay mounted across visits (the player
    // keeps playing), so their fixed labels are copied from the new page.
    const applyLanguage = (nextDocument) => {
        document.documentElement.lang = nextDocument.documentElement.lang;
        nextDocument.querySelectorAll('[data-i18n]').forEach((source) => {
            const target = document.querySelector(`[data-i18n="${source.dataset.i18n}"]`);
            if (!target) return;
            ['aria-label', 'title'].forEach((name) => {
                if (source.hasAttribute(name)) target.setAttribute(name, source.getAttribute(name));
            });
            if (!source.children.length) target.textContent = source.textContent;
        });
        if (!state.track) title.textContent = nextDocument.querySelector('[data-player-title]')?.textContent ?? title.textContent;
        updateVolumeUi();
        setQueueOpen(!queuePanel.hidden, false);
        refreshPlaybackUi();
    };

    const initialisePageControls = () => {
        initialiseSearch();
        initialisePage();
        syncNowPlaying();
    };

    const isInternalPageLink = (link, event) => {
        if (
            event.defaultPrevented
            || event.button !== 0
            || event.metaKey
            || event.ctrlKey
            || event.shiftKey
            || event.altKey
            || link.target
            || link.hasAttribute('download')
            || link.dataset.playerNavigation === 'false'
        ) return false;

        const url = new URL(link.href, window.location.href);
        return url.origin === window.location.origin
            && !(url.pathname === window.location.pathname && url.search === window.location.search && url.hash);
    };

    // Links, menus and forms inside a clickable row keep their own behaviour.
    const hasOwnAction = (trigger, target) => {
        const own = target.closest('a[href], summary, details, form, input, select, textarea, button, [data-no-play]');
        return Boolean(own) && own !== trigger && trigger.contains(own) && !own.matches('[data-play-track]');
    };

    const contextFor = (trigger) => {
        const source = trigger.dataset.playContext ? trigger : trigger.closest('[data-play-context]');
        return source ? { id: source.dataset.playContext, label: source.dataset.playContextLabel ?? '' } : null;
    };

    const queueFor = (trigger) => {
        const queueSource = trigger.dataset.trackQueue ?? trigger.closest('[data-track-queue]')?.dataset.trackQueue;
        if (queueSource) return readJson(queueSource) ?? [];
        const list = trigger.closest('.track-list');
        return list ? parsedTracksOnPage(list) : parsedTracksOnPage();
    };

    initialisePageControls();

    document.addEventListener('click', (event) => {
        document.querySelectorAll('[data-live-search-form]').forEach((searchForm) => {
            if (searchForm.contains(event.target)) return;
            searchForm.querySelector('[data-search-suggestions]').hidden = true;
            searchForm.querySelector('[data-live-search]').setAttribute('aria-expanded', 'false');
        });

        const shuffleTrigger = event.target.closest('[data-play-shuffle]');
        if (shuffleTrigger) {
            const queue = readJson(shuffleTrigger.dataset.trackQueue) ?? [];
            if (!queue.length) return;
            state.shuffle = true;
            saveModes();
            playTrack(queue[Math.floor(Math.random() * queue.length)], queue, true, contextFor(shuffleTrigger));
            return;
        }

        const trigger = event.target.closest('[data-play-track]');
        if (trigger && !hasOwnAction(trigger, event.target)) {
            const suggestions = trigger.closest('[data-search-suggestions]');
            if (suggestions) {
                suggestions.hidden = true;
                suggestions.closest('form').querySelector('[data-live-search]').setAttribute('aria-expanded', 'false');
            }

            const source = trigger.dataset.track ? trigger : trigger.closest('[data-track]');
            const track = readJson(source?.dataset.track);
            if (!track) {
                console.error('Track data is invalid.');
                return;
            }
            const context = contextFor(trigger);

            // A context button (album, playlist...) pauses and resumes the
            // context that is already playing instead of restarting it.
            if (trigger.dataset.playContext && context?.id === state.context?.id && state.track && audio.src) {
                playTrack(state.track);
                return;
            }

            playTrack(track, queueFor(trigger), false, context);
            return;
        }

        const link = event.target.closest('a[href]');
        if (!link || !isInternalPageLink(link, event)) return;

        event.preventDefault();
        visit(link.href);
    });

    // Switching language re-renders the page in place so music keeps playing.
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (event.defaultPrevented || !(form instanceof HTMLFormElement) || !form.matches('[data-locale-form]')) return;

        event.preventDefault();
        const body = new FormData(form);
        if (event.submitter?.name) body.set(event.submitter.name, event.submitter.value);
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error(`Language change failed with ${response.status}`);
            await visit(window.location.href, false, true);
        } catch (error) {
            console.error(error);
            showToast(t('locale_failed'), 'error');
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (event.defaultPrevented || !(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'get') return;

        const url = new URL(form.action || window.location.href, window.location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        if (url.origin !== window.location.origin) return;

        event.preventDefault();
        visit(url.toString());
    });

    toggle.addEventListener('click', () => {
        if (state.track) playTrack(state.track);
    });

    audio.addEventListener('play', () => { setError(); refreshPlaybackUi(); });
    audio.addEventListener('pause', refreshPlaybackUi);
    audio.addEventListener('ended', () => {
        recordListening(true);
        if (player.dataset.autoplay !== '0') cycleTrack(1, true);
        else refreshPlaybackUi();
    });
    audio.addEventListener('playing', () => { state.resumeTried = false; });
    audio.addEventListener('timeupdate', () => {
        if (audio.currentTime > 0) state.resumeAt = audio.currentTime;
        const total = audio.duration || state.track?.duration || 0;
        const ratio = total ? audio.currentTime / total : 0;
        current.textContent = formatTime(audio.currentTime);
        seek.value = String(ratio * 100);
        seek.setAttribute('aria-valuetext', t('seek_value', { current: formatTime(audio.currentTime), total: formatTime(total) }));
        setRangeFill(seek, ratio);
        miniProgress.style.setProperty('--progress', String(ratio));
        updateControls();

        if ('mediaSession' in navigator && Number.isFinite(audio.duration) && audio.duration > 0) {
            try {
                navigator.mediaSession.setPositionState({ duration: audio.duration, position: Math.min(audio.currentTime, audio.duration), playbackRate: audio.playbackRate });
            } catch { /* Some browsers reject position updates while loading. */ }
        }
    });
    audio.addEventListener('loadedmetadata', () => { duration.textContent = formatTime(audio.duration); });
    audio.addEventListener('error', () => {
        if (!state.track || !audio.src) return;
        // A song that was playing gets one fresh ticket before the player gives up.
        if (!state.resumeTried && state.resumeAt > 0) {
            resumeCurrentTrack();
            return;
        }
        state.loading = false;
        setError(t(player.dataset.guest === '1' ? 'preview_failed' : 'audio_failed'));
        refreshPlaybackUi();
    });

    seek.addEventListener('input', () => {
        const total = audio.duration || state.track?.duration;
        setRangeFill(seek, Number(seek.value) / 100);
        if (total) audio.currentTime = (Number(seek.value) / 100) * total;
    });
    volume.addEventListener('input', () => {
        audio.muted = false;
        audio.volume = Number(volume.value);
        if (audio.volume > 0) state.lastVolume = audio.volume;
        store.set('indie-house.player.volume', volume.value);
        updateVolumeUi();
    });
    muteButton.addEventListener('click', () => {
        if (audio.muted || audio.volume === 0) {
            audio.muted = false;
            audio.volume = state.lastVolume || 1;
        } else {
            audio.muted = true;
        }
        updateVolumeUi();
    });

    previous.addEventListener('click', () => cycleTrack(-1));
    next.addEventListener('click', () => cycleTrack(1));
    shuffleButton.addEventListener('click', () => {
        state.shuffle = !state.shuffle;
        state.shuffleBag = [];
        if (state.shuffle && state.queue.length) refillShuffleBag();
        saveModes();
        refreshPlaybackUi();
    });
    repeatButton.addEventListener('click', () => {
        state.repeat = state.repeat === 'off' ? 'all' : state.repeat === 'all' ? 'one' : 'off';
        saveModes();
        refreshPlaybackUi();
    });
    expand.addEventListener('click', () => {
        if (isMobile()) setSheetOpen(true);
    });
    collapse.addEventListener('click', () => setSheetOpen(false));
    const syncSheetViewport = () => {
        const mobile = isMobile();
        expand.disabled = !mobile;
        if (!mobile && player.classList.contains('is-expanded')) setSheetOpen(false);
    };
    window.addEventListener('resize', syncSheetViewport);
    syncSheetViewport();

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (isMobile() && !queuePanel.hidden) setQueueOpen(false, false);
            else if (player.classList.contains('is-expanded')) setSheetOpen(false);
            closeMenus();
            return;
        }

        if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey || isTyping(document.activeElement)) return;

        if (event.key === '/') {
            const input = [...document.querySelectorAll('[data-live-search]')].find((field) => field.offsetParent !== null);
            event.preventDefault();
            if (input) {
                input.focus();
                input.select();
            } else {
                visit('/search');
            }
        } else if (event.code === 'Space' && state.track && !document.activeElement?.closest('button, a, summary, [role="button"]')) {
            event.preventDefault();
            playTrack(state.track);
        }
    });

    if ('mediaSession' in navigator) {
        const seekBy = (seconds) => {
            audio.currentTime = Math.max(0, Math.min(audio.duration || 0, audio.currentTime + seconds));
        };
        navigator.mediaSession.setActionHandler('play', () => state.track && playTrack(state.track));
        navigator.mediaSession.setActionHandler('pause', () => audio.pause());
        navigator.mediaSession.setActionHandler('previoustrack', () => cycleTrack(-1));
        navigator.mediaSession.setActionHandler('nexttrack', () => cycleTrack(1));
        try {
            navigator.mediaSession.setActionHandler('seekbackward', (details) => seekBy(-(details.seekOffset || 10)));
            navigator.mediaSession.setActionHandler('seekforward', (details) => seekBy(details.seekOffset || 10));
            navigator.mediaSession.setActionHandler('seekto', (details) => { audio.currentTime = details.seekTime; });
        } catch { /* Optional actions are unsupported in some browsers. */ }
    }

    window.addEventListener('popstate', () => visit(window.location.href, false));
    window.addEventListener('pagehide', () => { recordListening(false); });

    const volumeValue = store.get('indie-house.player.volume');
    const savedVolume = volumeValue === null ? 1 : Number(volumeValue);
    if (Number.isFinite(savedVolume) && savedVolume >= 0 && savedVolume <= 1) {
        audio.volume = savedVolume;
        if (savedVolume > 0) state.lastVolume = savedVolume;
    }
    updateVolumeUi();

    const modes = readJson(store.get(modeStorageKey));
    state.shuffle = modes?.shuffle === true;
    state.repeat = ['off', 'all', 'one'].includes(modes?.repeat) ? modes.repeat : 'off';

    const savedTrack = readJson(store.get(storageKey));
    if (savedTrack?.id && savedTrack?.sourceUrl) {
        savedTrack.sourceUrl = savedTrack.sourceUrl.replace(/\/source(?:\?.*)?$/, '/audio');
        state.track = savedTrack;
        state.queue = [savedTrack];
        state.queueIndex = 0;
        updateMetadata(savedTrack);
    } else if (store.get(storageKey) !== null) {
        store.remove(storageKey);
    }

    if (!isMobile() && store.get(queueStorageKey) === '1' && state.track) setQueueOpen(true, false);
    refreshPlaybackUi();
});
