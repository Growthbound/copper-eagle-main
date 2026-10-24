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

document.addEventListener('DOMContentLoaded', () => {
    initializeBeforeAfter();
    initializePhotoGalleries();
    initializeLazyHeroVideos();
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
