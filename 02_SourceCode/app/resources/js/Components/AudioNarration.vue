<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import Icon from '@/Components/Icon.vue';
import ProvenanceBadge from '@/Components/ProvenanceBadge.vue';
import { useT } from '@/Composables/useT';

/**
 * Thuyết minh âm thanh: mode "file" phát <audio>; mode "tts" đọc transcript
 * bằng Web Speech API với giọng khớp locale (vi-VN / en-US).
 */
const props = defineProps({
    audios: { type: Array, default: () => [] },
    locale: { type: String, default: null },
});
const { t, locale: pageLocale } = useT();

const lang = computed(() => props.locale || pageLocale.value);
const audio = computed(
    () => props.audios.find((a) => a.locale === lang.value) || props.audios.find((a) => !a.locale) || null,
);
const mode = computed(() => (audio.value?.url && audio.value?.mode !== 'tts' ? 'file' : 'tts'));

const supported = ref(true);
const speaking = ref(false);
const paused = ref(false);
const showTranscript = ref(false);
let voices = [];

const bcp47 = computed(() => (lang.value === 'en' ? 'en-US' : 'vi-VN'));

function loadVoices() {
    voices = window.speechSynthesis?.getVoices?.() || [];
}

function pickVoice() {
    const prefix = lang.value === 'en' ? 'en' : 'vi';
    return voices.find((v) => v.lang?.toLowerCase().startsWith(prefix)) || null;
}

const hasVoice = ref(true);

function play() {
    const synth = window.speechSynthesis;
    if (!synth || !audio.value?.transcript) return;
    if (paused.value) {
        synth.resume();
        paused.value = false;
        return;
    }
    synth.cancel();
    const u = new SpeechSynthesisUtterance(audio.value.transcript);
    u.lang = bcp47.value;
    const voice = pickVoice();
    hasVoice.value = !!voice;
    if (voice) u.voice = voice;
    u.rate = 0.95;
    u.onend = () => {
        speaking.value = false;
        paused.value = false;
    };
    u.onerror = () => {
        speaking.value = false;
        paused.value = false;
    };
    synth.speak(u);
    speaking.value = true;
}

function pause() {
    window.speechSynthesis?.pause();
    paused.value = true;
}

function stop() {
    window.speechSynthesis?.cancel();
    speaking.value = false;
    paused.value = false;
}

onMounted(() => {
    supported.value = typeof window !== 'undefined' && 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
    if (supported.value) {
        loadVoices();
        window.speechSynthesis.addEventListener?.('voiceschanged', loadVoices);
    }
});
onBeforeUnmount(() => {
    if (supported.value) {
        window.speechSynthesis.removeEventListener?.('voiceschanged', loadVoices);
        stop();
    }
});
watch(audio, stop);
</script>

<template>
    <section v-if="audio" class="card p-4" :aria-label="t('audio.title')">
        <div class="flex flex-wrap items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-accent-soft text-accent-strong">
                <Icon name="volume" />
            </span>
            <div class="min-w-0 flex-1">
                <h2 class="font-body text-base font-bold">{{ t('audio.title') }}</h2>
                <p class="text-xs text-muted">
                    {{ mode === 'file' ? t('audio.mode_file') : t('audio.mode_tts') }}
                    <span v-if="audio.duration_sec"> · {{ Math.round(audio.duration_sec / 60) || 1 }} {{ t('common.minutes_short') }}</span>
                </p>
            </div>
        </div>

        <div class="mt-3">
            <audio v-if="mode === 'file'" :src="audio.url" controls preload="none" class="w-full" :aria-label="t('audio.title')">
                {{ t('audio.not_supported') }}
            </audio>

            <template v-else>
                <p v-if="!supported" class="rounded-lg bg-warning-soft p-3 text-sm text-warning" role="alert">
                    {{ t('audio.tts_not_supported') }}
                </p>
                <div v-else class="flex flex-wrap items-center gap-2">
                    <button v-if="!speaking || paused" type="button" class="btn btn-primary" :disabled="!audio.transcript" @click="play">
                        <Icon name="play" :size="18" /> {{ paused ? t('audio.resume') : t('audio.play') }}
                    </button>
                    <button v-else type="button" class="btn" @click="pause">
                        <Icon name="pause" :size="18" /> {{ t('audio.pause') }}
                    </button>
                    <button type="button" class="btn" :disabled="!speaking" @click="stop">
                        <Icon name="stop" :size="18" /> {{ t('audio.stop') }}
                    </button>
                    <span v-if="speaking && !paused" class="text-xs text-muted" aria-live="polite">{{ t('audio.speaking') }}</span>
                </div>
                <p v-if="supported && !hasVoice" class="mt-2 text-xs text-muted">{{ t('audio.no_voice', { lang: bcp47 }) }}</p>
            </template>
        </div>

        <div v-if="audio.transcript" class="mt-3">
            <button type="button" class="link text-sm" :aria-expanded="showTranscript" @click="showTranscript = !showTranscript">
                {{ showTranscript ? t('audio.hide_transcript') : t('audio.show_transcript') }}
            </button>
            <p v-show="showTranscript" class="mt-2 whitespace-pre-line rounded-lg bg-surface-2 p-3 text-sm leading-relaxed">{{ audio.transcript }}</p>
        </div>
        <ProvenanceBadge v-if="audio.license" :item="audio" compact class="mt-3" />
    </section>
</template>
