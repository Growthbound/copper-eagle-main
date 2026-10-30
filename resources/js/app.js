const initializeBeforeAfter = () => {
    document.querySelectorAll('[data-before-after]').forEach((comparison) => {
        const control = comparison.querySelector('[data-before-after-control]');

        if (!(control instanceof HTMLInputElement)) {
            return;
        }

        comparison.classList.add('is-enhanced');

        const update = () => {
            comparison.style.setProperty('--comparison-position', `${control.value}%`);
        };

        control.addEventListener('input', update);
        update();
    });
};

const initializePhotoGalleries = () => {
    document.querySelectorAll('[data-gallery-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const dialog = document.getElementById(trigger.dataset.galleryOpen ?? '');

            if (dialog instanceof HTMLDialogElement) {
                dialog.showModal();
            }
        });
    });

    document.querySelectorAll('.photo-gallery-dialog').forEach((dialog) => {
        if (!(dialog instanceof HTMLDialogElement)) {
            return;
        }

        dialog.querySelector('[data-gallery-close]')?.addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    });
};

const initializeLazyHeroVideos = () => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const connection = navigator.connection;

    if (reducedMotion || connection?.saveData || connection?.effectiveType === '2g') {
        return;
    }

    document.querySelectorAll('[data-lazy-hero-video]').forEach((video) => {
        if (!(video instanceof HTMLVideoElement)) {
            return;
        }

        const control = video.closest('.hero-media')?.querySelector('[data-hero-video-control]');
        const playIcon = control?.querySelector('[data-video-play-icon]');
        const pauseIcon = control?.querySelector('[data-video-pause-icon]');
        let revealTimer;

        const updateControl = () => {
            if (!(control instanceof HTMLButtonElement)) {
                return;
            }

            const isPaused = video.paused;

            control.ariaLabel = isPaused ? 'Play background video' : 'Pause background video';
            playIcon?.toggleAttribute('hidden', !isPaused);
            pauseIcon?.toggleAttribute('hidden', isPaused);
        };

        const scheduleReveal = () => {
            if (video.dataset.revealed === 'true' || revealTimer) {
                return;
            }

            revealTimer = window.setTimeout(() => {
                revealTimer = undefined;

                if (video.paused) {
                    return;
                }

                video.dataset.revealed = 'true';
                video.classList.add('is-ready');
            }, 3000);
        };

        control?.addEventListener('click', () => {
            if (video.paused) {
                video.play().catch(() => {});
            } else {
                video.pause();
            }
        });

        video.addEventListener('play', updateControl);
        video.addEventListener('playing', scheduleReveal);
        video.addEventListener('pause', () => {
            updateControl();

            if (video.dataset.revealed !== 'true' && revealTimer) {
                window.clearTimeout(revealTimer);
                revealTimer = undefined;
            }
        });

        const hydrate = () => {
            if (video.dataset.hydrated === 'true') {
                return;
            }

            const src = video.dataset.src;
            const type = video.dataset.type;

            if (!src) {
                return;
            }

            video.dataset.hydrated = 'true';

            const source = document.createElement('source');
            source.src = src;

            if (type) {
                source.type = type;
            }

            video.append(source);

            if (control instanceof HTMLButtonElement) {
                control.disabled = false;
            }

            video.load();
            video.play().catch(() => {
                // The poster remains visible when autoplay is unavailable.
                updateControl();
            });
        };

        const scheduleHydration = () => {
            if ('requestIdleCallback' in window) {
                window.requestIdleCallback(hydrate, { timeout: 1500 });
            } else {
                window.setTimeout(hydrate, 250);
            }
        };

        if (!('IntersectionObserver' in window)) {
            scheduleHydration();

            return;
        }

        const observer = new IntersectionObserver((entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) {
                return;
            }

            observer.disconnect();
            scheduleHydration();
        }, { rootMargin: '200px' });

        observer.observe(video);
    });
};

/*
 * Decorative hero background videos (hero/photo, hero/interior). The markup
 * ships without a <source>, so nothing heavy loads with the page. Once the
 * page is idle, and only when the visitor has not asked for reduced motion,
 * the source is attached and the muted loop starts; otherwise the poster
 * still stays. The pause control appears once the video is playing.
 */
const initializeBackgroundVideos = () => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    document.querySelectorAll('[data-background-video]').forEach((video) => {
        if (!(video instanceof HTMLVideoElement)) {
            return;
        }

        const control = video.parentElement?.querySelector('[data-background-video-control]');

        const updateControl = () => {
            if (!(control instanceof HTMLButtonElement)) {
                return;
            }

            control.hidden = false;
            control.classList.toggle('is-paused', video.paused);
            control.setAttribute('aria-label', video.paused ? 'Play background video' : 'Pause background video');
        };

        const start = () => {
            if (reducedMotion.matches || video.dataset.hydrated === 'true' || !video.dataset.src) {
                return;
            }

            video.dataset.hydrated = 'true';

            const source = document.createElement('source');
            source.src = video.dataset.src;

            if (video.dataset.type) {
                source.type = video.dataset.type;
            }

            video.append(source);
            video.load();
            video.play().catch(() => {
                // Autoplay was refused; the poster stays.
            });
        };

        video.addEventListener('playing', updateControl);
        video.addEventListener('pause', updateControl);
        control?.addEventListener('click', () => {
            if (video.paused) {
                video.play().catch(() => {});
            } else {
                video.pause();
            }
        });
        reducedMotion.addEventListener?.('change', () => {
            if (reducedMotion.matches) {
                video.pause();
            } else {
                start();
            }
        });

        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(start, { timeout: 2000 });
        } else {
            window.setTimeout(start, 300);
        }
    });
};

// Job check-ins: show a batch at a time behind "Load more check-ins", and let
// the square buttons under a photo strip pick a photo. Without JavaScript every
// check-in shows and the strip scrolls by touch or trackpad.
const initializeCheckIns = () => {
    const smooth = () => (window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth');

    document.querySelectorAll('[data-check-ins]').forEach((section) => {
        const items = [...section.querySelectorAll('[data-check-in]')];
        const batch = Number.parseInt(section.dataset.checkInsBatch ?? '2', 10) || 2;
        const more = section.querySelector('[data-check-ins-more]');
        const button = section.querySelector('[data-check-ins-more-button]');
        let shown = Math.min(batch, items.length);

        items.slice(shown).forEach((item) => {
            item.hidden = true;
        });

        if (!more || !button || shown >= items.length) {
            return;
        }

        more.hidden = false;
        button.addEventListener('click', () => {
            const next = items.slice(shown, shown + batch);

            next.forEach((item) => {
                item.hidden = false;
            });
            shown += next.length;
            next[0]?.focus({ preventScroll: true });

            if (shown >= items.length) {
                more.hidden = true;
            }
        });
    });

    document.querySelectorAll('[data-check-in-photos]').forEach((photos) => {
        const track = photos.querySelector('[data-check-in-photo-track]');
        const dots = photos.querySelector('[data-check-in-photo-dots]');

        if (!track || !dots) {
            return;
        }

        const buttons = [...dots.querySelectorAll('[data-check-in-photo-dot]')];
        const mark = (current) => {
            buttons.forEach((button, index) => {
                if (index === current) {
                    button.setAttribute('aria-current', 'true');
                } else {
                    button.removeAttribute('aria-current');
                }
            });
        };

        dots.hidden = false;
        buttons.forEach((button, index) => {
            button.addEventListener('click', () => {
                track.scrollTo({ left: index * track.clientWidth, behavior: smooth() });
                mark(index);
            });
        });

        let frame = 0;
        track.addEventListener('scroll', () => {
            window.cancelAnimationFrame(frame);
            frame = window.requestAnimationFrame(() => {
                mark(Math.round(track.scrollLeft / Math.max(track.clientWidth, 1)));
            });
        }, { passive: true });
    });
};

document.addEventListener('DOMContentLoaded', () => {
    initializeBeforeAfter();
    initializePhotoGalleries();
    initializeLazyHeroVideos();
    initializeBackgroundVideos();
    initializeCheckIns();
});

// Legacy campaigns target /#contact-us. Keep managed/native anchors unchanged.
const scrollToLegacyContactForm = () => {
    if (window.location.pathname === '/' && window.location.hash === '#contact-us'
        && !document.getElementById('contact-us')) {
        document.querySelector('[data-website-form-section]')?.scrollIntoView({ behavior: 'instant' });
    }
};
document.addEventListener('DOMContentLoaded', scrollToLegacyContactForm);
window.addEventListener('hashchange', scrollToLegacyContactForm);
