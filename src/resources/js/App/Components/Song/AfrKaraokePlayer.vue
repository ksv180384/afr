<script setup>
import { ref, computed, nextTick, onBeforeUnmount, watch } from 'vue';
import { Icon } from '@iconify/vue';
import axios from 'axios';
import Modal from '@/App/Components/Modal.vue';
import AfrProgressBar from '@/App/Components/Player/AfrProgressBar.vue';

const props = defineProps({
  songId: { type: Number, default: null },
  songTitle: { type: String, default: '' },
  songArtist: { type: String, default: '' },
  textRawFr: { type: String, default: '' },
  textRawRu: { type: String, default: '' },
  textRawTranscription: { type: String, default: '' },
  songDurationSeconds: { type: Number, default: null },
  songDurationFormatted: { type: String, default: null },
});

const showModal = ref(false);
const audioUrl = ref(null);
const fileName = ref('');
const fileInputRef = ref(null);
const audioRef = ref(null);
const isPlaying = ref(false);
const currentTime = ref(0);
const duration = ref(0);
const activeLanguage = ref('fr');
const currentLineIndex = ref(-1);
const lyricsRef = ref(null);
const lyricsContentRef = ref(null);
const highlightVisible = ref(false);
const highlightTop = ref(0);
const scrollOffset = ref(0);
const durationMismatch = ref(false);
let rafId = null;

const DURATION_TOLERANCE_SEC = 2;

const STORAGE_KEY = 'player-volume';
const savedVolume = () => {
  const v = localStorage.getItem(STORAGE_KEY);
  if (v == null) return 1;
  const n = parseFloat(v);
  return Number.isFinite(n) && n >= 0 && n <= 1 ? n : 1;
};
const volume = ref(savedVolume());
const setVolume = (v) => {
  const val = Math.max(0, Math.min(1, v));
  volume.value = val;
  localStorage.setItem(STORAGE_KEY, String(val));
  if (audioRef.value) audioRef.value.volume = val;
};
watch(volume, (v) => {
  if (audioRef.value) audioRef.value.volume = v;
}, { immediate: true });

watch(activeLanguage, () => nextTick(updateHighlightPosition));

function parseLrc(text) {
  if (!text) return [];
  const result = [];
  const timeTagRegex = /\[(\d+):(\d+\.?\d*)\]/g;

  for (const line of text.split('\n')) {
    const trimmed = line.trim();
    if (!trimmed) continue;

    const times = [];
    let match;
    timeTagRegex.lastIndex = 0;
    while ((match = timeTagRegex.exec(trimmed)) !== null) {
      const minutes = parseInt(match[1], 10);
      const seconds = parseFloat(match[2]);
      times.push(minutes * 60 + seconds);
    }
    if (times.length === 0) continue;

    const textPart = trimmed.replace(/^(\[\d+:\d+\.?\d*\])+/, '').trim();

    for (const time of times) {
      result.push({ time, text: textPart });
    }
  }
  return result.sort((a, b) => a.time - b.time);
}

const activeRawText = computed(() => {
  const map = {
    fr: props.textRawFr,
    ru: props.textRawRu,
    tr: props.textRawTranscription,
  };
  return map[activeLanguage.value] || '';
});

const lrcLines = computed(() => parseLrc(activeRawText.value));

const lineProgressForTime = (index, t) => {
  const lines = lrcLines.value;
  if (index < 0 || index >= lines.length) return 0;
  const lineTime = lines[index].time;
  const nextTime = index < lines.length - 1 ? lines[index + 1].time : lineTime + 5;
  if (t < lineTime) return 0;
  if (t >= nextTime) return 1;
  return (t - lineTime) / (nextTime - lineTime);
};

const progressPercent = computed(() =>
  duration.value ? (currentTime.value / duration.value) * 100 : 0,
);

const formatTime = (sec) => {
  if (isNaN(sec)) return '0:00';
  return `${Math.floor(sec / 60)}:${Math.floor(sec % 60).toString().padStart(2, '0')}`;
};

const isDragging = ref(false);

const processFile = (file) => {
  if (!file) return;
  const fileType = file.type || '';
  if (!fileType.startsWith('audio/') && !/\.(mp3|m4a|ogg|wav|webm)$/i.test(file.name)) {
    return;
  }
  fileName.value = file.name;
  if (audioUrl.value) URL.revokeObjectURL(audioUrl.value);
  audioUrl.value = URL.createObjectURL(file);
  currentTime.value = 0;
  currentLineIndex.value = -1;
  highlightVisible.value = false;
  highlightTop.value = 0;
  scrollOffset.value = 0;
  isPlaying.value = false;
  durationMismatch.value = false;
};

const onFileSelected = (e) => {
  const file = e.target.files[0];
  processFile(file);
};

const onDragOver = (e) => {
  e.preventDefault();
  e.stopPropagation();
  e.dataTransfer.dropEffect = 'copy';
  isDragging.value = true;
};

const onDragLeave = (e) => {
  e.preventDefault();
  e.stopPropagation();
  if (!e.currentTarget.contains(e.relatedTarget)) {
    isDragging.value = false;
  }
};

const onDrop = (e) => {
  e.preventDefault();
  e.stopPropagation();
  isDragging.value = false;
  const file = e.dataTransfer?.files?.[0];
  processFile(file);
};

const removeFile = () => {
  if (audioRef.value && isPlaying.value) {
    audioRef.value.pause();
  }
  if (rafId) {
    cancelAnimationFrame(rafId);
    rafId = null;
  }
  if (audioUrl.value) {
    URL.revokeObjectURL(audioUrl.value);
  }
  if (fileInputRef.value) {
    fileInputRef.value.value = '';
  }
  fileName.value = '';
  audioUrl.value = null;
  currentTime.value = 0;
  duration.value = 0;
  currentLineIndex.value = -1;
  highlightVisible.value = false;
  highlightTop.value = 0;
  scrollOffset.value = 0;
  isPlaying.value = false;
  durationMismatch.value = false;
};

const togglePlay = () => {
  if (!audioRef.value) return;
  isPlaying.value ? audioRef.value.pause() : audioRef.value.play();
};

const updateHighlightPosition = (t = null, smoothScroll = true) => {
  const container = lyricsRef.value;
  if (!container) return;
  const lines = lrcLines.value;
  const time = t ?? currentTime.value;
  let idx = -1;
  for (let i = lines.length - 1; i >= 0; i--) {
    if (time >= lines[i].time) {
      idx = i;
      break;
    }
  }
  if (idx < 0 || idx >= lines.length) {
    highlightVisible.value = false;
    return;
  }
  highlightVisible.value = true;
  const contentEl = lyricsContentRef.value || container;
  const currentEl = contentEl.querySelector(`[data-line="${idx}"]`);
  const nextEl = idx < lines.length - 1 ? contentEl.querySelector(`[data-line="${idx + 1}"]`) : null;
  if (!currentEl) return;
  const progress = lineProgressForTime(idx, time);
  const top1 = currentEl.offsetTop;
  const h1 = currentEl.offsetHeight;
  const top2 = nextEl ? nextEl.offsetTop : top1 + h1;
  const h2 = nextEl ? nextEl.offsetHeight : h1;
  const centerY = top1 + (top2 - top1) * progress + (h1 + (h2 - h1) * progress) / 2;
  const viewportCenter = container.clientHeight / 2;

  if (smoothScroll) {
    const targetOffset = Math.max(0, centerY - viewportCenter);
    const maxOffset = contentEl ? Math.max(0, contentEl.offsetHeight - container.clientHeight) : 0;
    scrollOffset.value = Math.min(targetOffset, maxOffset);
    highlightTop.value = centerY < viewportCenter ? centerY : viewportCenter;
  }
};

const highlightLoop = () => {
  if (!audioRef.value || !isPlaying.value) return;
  const t = audioRef.value.currentTime;
  currentTime.value = t;
  let idx = -1;
  const lines = lrcLines.value;
  for (let i = lines.length - 1; i >= 0; i--) {
    if (t >= lines[i].time) {
      idx = i;
      break;
    }
  }
  if (idx !== currentLineIndex.value) currentLineIndex.value = idx;
  updateHighlightPosition(t, true);
  rafId = requestAnimationFrame(highlightLoop);
};

const onTimeUpdate = () => {
  if (!audioRef.value) return;
  currentTime.value = audioRef.value.currentTime;
};

const seekAudio = (position) => {
  if (!audioRef.value || !duration.value) return;
  const t = position;
  audioRef.value.currentTime = t;
  currentTime.value = t;
  let idx = -1;
  for (let i = lrcLines.value.length - 1; i >= 0; i--) {
    if (t >= lrcLines.value[i].time) {
      idx = i;
      break;
    }
  }
  currentLineIndex.value = idx;
  nextTick(() => updateHighlightPosition(t, true));
};

const changeVolume = (val) => {
  setVolume(val / 100);
};

const onLoadedMetadata = () => {
  if (audioRef.value) {
    duration.value = audioRef.value.duration;
    audioRef.value.volume = volume.value;
    const dbSec = props.songDurationSeconds;
    let matched = false;
    if (dbSec != null && typeof dbSec === 'number') {
      const audioSec = audioRef.value.duration;
      const diff = Math.abs(audioSec - dbSec);
      durationMismatch.value = diff > DURATION_TOLERANCE_SEC;
      matched = diff <= DURATION_TOLERANCE_SEC;
    } else {
      durationMismatch.value = false;
      matched = true;
    }
    logKaraokeUpload(matched);
  }
};

const logKaraokeUpload = (durationMatched) => {
  if (props.songId == null) return;
  axios.post(route('lyrics.karaoke-upload-log'), {
    song_id: props.songId,
    song_title: props.songTitle || '',
    song_artist: props.songArtist || '',
    file_name: fileName.value || '',
    file_duration_seconds: duration.value || 0,
    db_duration_seconds: props.songDurationSeconds ?? null,
    duration_matched: durationMatched,
  }).catch(() => {});
};
const onPlay = () => {
  isPlaying.value = true;
  rafId = requestAnimationFrame(highlightLoop);
};
const onPause = () => {
  isPlaying.value = false;
  if (rafId) cancelAnimationFrame(rafId);
  rafId = null;
};
const onEnded = () => {
  isPlaying.value = false;
  if (rafId) cancelAnimationFrame(rafId);
  rafId = null;
  currentLineIndex.value = -1;
  highlightVisible.value = false;
  highlightTop.value = 0;
  scrollOffset.value = 0;
};

const stopPlayback = () => {
  if (!audioRef.value) return;
  isPlaying.value = false;
  if (rafId) cancelAnimationFrame(rafId);
  rafId = null;
  audioRef.value.pause();
  audioRef.value.currentTime = 0;
  currentTime.value = 0;
  currentLineIndex.value = -1;
  highlightVisible.value = false;
  highlightTop.value = 0;
  scrollOffset.value = 0;
};

const closeModal = () => {
  showModal.value = false;
  if (audioRef.value && isPlaying.value) audioRef.value.pause();
};

onBeforeUnmount(() => {
  if (rafId) cancelAnimationFrame(rafId);
  if (audioUrl.value) URL.revokeObjectURL(audioUrl.value);
});
</script>

<template>
  <button class="karaoke-btn" @click="showModal = true">
    <Icon icon="mdi:music-note-eighth" class="mr-1.5 text-lg" />
    Караоке
  </button>

  <Modal :show="showModal" max-width="2xl" @close="closeModal">
    <div class="p-5 sm:p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
          <Icon icon="mdi:music-note-eighth" class="text-sky-600" />
          Караоке
        </h2>
        <button class="text-gray-400 hover:text-gray-600 transition cursor-pointer" @click="closeModal">
          <Icon icon="mdi:close" class="text-2xl" />
        </button>
      </div>

      <div
        v-if="!fileName"
        :class="['afr-player-load-file', { 'afr-player-load-file--dragging': isDragging }]"
        @click="fileInputRef?.click()"
        @dragover="onDragOver"
        @dragleave="onDragLeave"
        @drop="onDrop"
      >
        <div class="afr-player-info">
          Перетащите mp3 файл сюда или нажмите для выбора. Текст песни будет появляться синхронно со словами.
          <div class="text-center py-1 text-lg">mp3</div>
        </div>
      </div>
      <div v-else class="afr-player-file-row">
        <span class="afr-player-file-name truncate">{{ fileName }}</span>
        <button
          type="button"
          class="afr-player-remove-file"
          title="Удалить файл и загрузить другой"
          @click.stop="removeFile"
        >
          <Icon icon="mingcute:close-circle-line" class="text-lg" />
          Загрузить другой
        </button>
      </div>
      <input ref="fileInputRef" type="file" accept="audio/*" class="hidden" @change="onFileSelected" />

      <div
        v-if="durationMismatch && fileName"
        class="afr-karaoke-duration-warning"
      >
        Версия загруженной песни не совпадает с той, по которой выставлялись временные метки для караоке.
        Вам нужна версия песни продолжительностью <strong>{{ songDurationFormatted ?? (songDurationSeconds != null ? formatTime(songDurationSeconds) : '') }}</strong>.
        Загружено: <strong>{{ formatTime(duration) }}</strong>.
      </div>

      <template v-if="audioUrl">
        <audio
          ref="audioRef"
          :src="audioUrl"
          @timeupdate="onTimeUpdate"
          @loadedmetadata="onLoadedMetadata"
          @play="onPlay"
          @pause="onPause"
          @ended="onEnded"
          @seeked="() => audioRef && updateHighlightPosition(audioRef.currentTime)"
        />

        <div class="afr-player-bar mt-4">
          <AfrProgressBar
            :progress-line-value="progressPercent"
            :duration="duration"
            @changePosition="seekAudio"
          />

          <div class="afr-player-controls">
            <div v-if="!isPlaying" class="afr-player-btn" title="Play" @click="togglePlay">
              <Icon icon="mingcute:play-line" />
            </div>
            <div v-else class="afr-player-btn" title="Pause" @click="togglePlay">
              <Icon icon="mingcute:pause-fill" />
            </div>
            <div class="afr-player-btn" title="Stop" @click="stopPlayback">
              <Icon icon="mingcute:stop-line" />
            </div>

            <div class="flex flex-col flex-1 lg:flex-row">
              <div class="afr-track-time">
                {{ formatTime(currentTime) }} / {{ formatTime(duration) }}
              </div>
            </div>

            <div class="afr-karaoke-volume">
              <Icon icon="mingcute:volume-line" class="text-[22px] shrink-0" />
              <input
                type="range"
                min="0"
                max="100"
                :value="volume * 100"
                class="afr-karaoke-volume-slider"
                @input="changeVolume(Number($event.target.value))"
              >
            </div>
          </div>
        </div>

        <div class="lang-tabs mt-4">
          <button
            v-for="lang in [{ key: 'fr', label: 'FR' }, { key: 'ru', label: 'RU' }, { key: 'tr', label: 'TR' }]"
            :key="lang.key"
            :class="['lang-tab', { active: activeLanguage === lang.key }]"
            @click="activeLanguage = lang.key"
          >
            {{ lang.label }}
          </button>
        </div>

        <div ref="lyricsRef" class="lyrics-container mt-3">
          <div
            v-if="highlightVisible"
            class="lyric-highlight"
            :style="{ top: highlightTop + 'px' }"
          />
          <div
            ref="lyricsContentRef"
            class="lyrics-content"
            :style="{ transform: `translateY(-${scrollOffset}px)` }"
          >
            <div
              v-for="(line, index) in lrcLines"
              :key="`${activeLanguage}-${index}`"
              :data-line="index"
              :class="['lyric-line', { empty: !line.text }]"
            >
              {{ line.text || '♪' }}
            </div>
          </div>
        </div>
      </template>
    </div>
  </Modal>
</template>

<style scoped>
.karaoke-btn {
  @apply inline-flex items-center px-4 py-2 bg-sky-600 text-white rounded-lg
         hover:bg-sky-700 transition duration-300 cursor-pointer font-medium text-sm;
}

.afr-player-load-file {
  @apply p-2 mx-0 border-blue-500 border rounded-lg border-dashed relative cursor-pointer transition-colors duration-200;
}

.afr-player-load-file--dragging {
  @apply bg-sky-100 border-sky-600;
}

.afr-player-info {
  @apply lg:text-xs text-base lg:leading-5 leading-6;
}

.afr-player-file-row {
  @apply flex items-center justify-center gap-2 flex-wrap;
}

.afr-player-file-name {
  @apply text-lg lg:text-sm font-semibold truncate max-w-[200px];
}

.afr-player-remove-file {
  @apply inline-flex items-center gap-1 px-2 py-1 text-sm text-sky-600 hover:text-sky-800
         hover:bg-sky-100 rounded transition-colors cursor-pointer;
}

.afr-karaoke-duration-warning {
  @apply mt-2 p-3 rounded-lg bg-red-50 border border-red-300 text-red-800 text-sm;
}

.afr-player-bar {
  @apply w-full rounded-t overflow-hidden;
}

.afr-player-controls {
  @apply flex flex-row justify-center items-center px-4 text-[32px] bg-blue-50;
}

.afr-player-btn {
  @apply px-3 lg:px-2 py-4 lg:py-3 cursor-pointer hover:bg-blue-100;
}

.afr-track-time {
  @apply text-xs lg:text-sm flex justify-start items-center flex-1;
}

.afr-karaoke-volume {
  @apply flex items-center gap-2 w-24 shrink-0;
}

.afr-karaoke-volume-slider {
  @apply flex-1 min-w-0 h-1.5 rounded-full cursor-pointer;
  accent-color: #38bdf8;
}

.lang-tabs {
  @apply flex gap-1 bg-blue-100 rounded-lg p-1;
}

.lang-tab {
  @apply flex-1 text-center px-3 py-1.5 rounded-md text-sm font-medium transition duration-200 cursor-pointer
         text-gray-600 hover:text-blue-700;
}

.lang-tab.active {
  @apply bg-blue-500 text-white shadow-sm;
}

.lyrics-container {
  @apply relative h-[350px] overflow-hidden rounded-lg bg-gray-900 p-4;
}

.lyrics-content {
  @apply relative min-h-full;
}

.lyric-highlight {
  @apply absolute left-0 right-0 rounded bg-blue-800/40 pointer-events-none;
  transform: translateY(-50%);
  height: 2.5rem;
}

.lyric-line {
  @apply py-1.5 px-3 text-gray-500 text-sm;
  position: relative;
  z-index: 1;
}

.lyric-line.empty {
  @apply py-3 text-gray-600;
}
</style>
