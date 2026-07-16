// resources/js/sound-manager.js
// Uses HTML Audio elements — immune to CORS restrictions for playback.
class SoundManager {
    constructor() {
        this.sounds = {};
        this.isInitialized = false;
    }

    init() {
        this.isInitialized = true;
    }

    /**
     * Preload a single sound by key + URL.
     * Returns a promise that resolves when the audio is ready to play.
     */
    loadSound(url, key) {
        if (!url) return Promise.resolve(false);

        return new Promise((resolve) => {
            try {
                const audio = new Audio();
                audio.preload = 'auto';

                audio.addEventListener('canplaythrough', () => resolve(true), { once: true });
                audio.addEventListener('error', (e) => {
                    console.error(`[SoundManager] Failed to load "${key}":`, e);
                    resolve(false);
                }, { once: true });

                audio.src = url;
                audio.load();

                this.sounds[key] = { url, audio, looping: false };
            } catch (error) {
                console.error(`[SoundManager] Error creating audio for "${key}":`, error);
                resolve(false);
            }
        });
    }

    /**
     * Preload multiple sounds from a { key: url } map.
     */
    async preloadSounds(soundMap) {
        if (!this.isInitialized) this.init();
        const entries = Object.entries(soundMap).filter(([, url]) => !!url);
        const results = await Promise.allSettled(
            entries.map(([key, url]) => this.loadSound(url, key))
        );
        results.forEach((r, i) => {
            if (r.status === 'fulfilled' && r.value) {
                console.log(`[SoundManager] Preloaded: ${entries[i][0]}`);
            }
        });
    }

    /**
     * Play a sound once (fire-and-forget).
     */
    play(key, volume = 1.0) {
        const entry = this.sounds[key];
        if (!entry) return false;

        try {
            // Clone so overlapping plays don't conflict
            const clone = entry.audio.cloneNode();
            clone.volume = Math.max(0, Math.min(1, volume));
            clone.play().catch(() => {});
            return true;
        } catch (e) {
            console.error('[SoundManager] Play error:', e);
            return false;
        }
    }

    /**
     * Play a sound in a loop. Stop later with stop(key).
     */
    playLoop(key, volume = 1.0) {
        const entry = this.sounds[key];
        if (!entry) return false;

        this.stop(key); // stop any existing loop first

        try {
            const audio = entry.audio;
            audio.currentTime = 0;
            audio.loop = true;
            audio.volume = Math.max(0, Math.min(1, volume));
            audio.play().catch(() => {});
            entry.looping = true;
            return true;
        } catch (e) {
            console.error('[SoundManager] PlayLoop error:', e);
            return false;
        }
    }

    /**
     * Stop a specific looping sound.
     */
    stop(key) {
        const entry = this.sounds[key];
        if (!entry || !entry.looping) return;

        try {
            entry.audio.pause();
            entry.audio.currentTime = 0;
            entry.audio.loop = false;
            entry.looping = false;
        } catch (e) {
            // ignore
        }
    }

    /**
     * Stop all currently playing looped sounds.
     */
    stopAll() {
        Object.keys(this.sounds).forEach(key => this.stop(key));
    }

    /**
     * Check if a sound is loaded and ready to play.
     */
    isLoaded(key) {
        return !!this.sounds[key];
    }
}

// Singleton
window.soundManager = new SoundManager();