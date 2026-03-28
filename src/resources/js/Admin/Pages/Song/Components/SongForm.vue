<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AfrInputErrorMessage from "@/App/Components/Form/AfrInputErrorMessage.vue";

const props = defineProps({
  artists: { type: Array, default: [] },
  song: { type: Object, default: null },
  errors: { type: Object, default: null },
  allowCreateArtist: { type: Boolean, default: false },
});
const emits = defineEmits(['submit', 'change']);

const refForm = ref(null);
const form = useForm({
  artist_id: props.song?.artist?.id ?? props.song?.artist_id ?? null,
  artist_name: '',
  title: props.song?.title || '',
  duration: props.song?.duration ?? null,
  text_fr: props.song?.text_fr || '',
  text_ru: props.song?.text_ru || '',
  text_transcription: props.song?.text_transcription || '',
  hidden: props.song?.hidden ?? true,
});

const artistFreeText = ref('');

const resolveArtistFromText = () => {
  const q = String(artistFreeText.value ?? '').trim();
  if (!q) {
    form.artist_id = null;
    form.artist_name = '';
    return;
  }
  const match = props.artists.find((a) => a.name === q);
  if (match) {
    form.artist_id = match.id;
    form.artist_name = '';
  } else if (props.allowCreateArtist) {
    form.artist_id = null;
    form.artist_name = q;
  }
};

const fetchArtistSuggestions = (queryString, cb) => {
  const q = String(queryString ?? '').trim().toLowerCase();
  cb(
    props.artists
      .filter((a) => !q || a.name.toLowerCase().includes(q))
      .map((a) => ({ value: a.name, id: a.id })),
  );
};

const onArtistAutocompleteSelect = (item) => {
  form.artist_id = item.id;
  form.artist_name = '';
};

const onArtistAutocompleteClear = () => {
  form.artist_id = null;
  form.artist_name = '';
  artistFreeText.value = '';
};

const syncArtistFreeTextFromForm = () => {
  if (form.artist_name) {
    artistFreeText.value = form.artist_name;
    return;
  }
  if (form.artist_id != null && form.artist_id !== '') {
    const a = props.artists.find((x) => x.id === form.artist_id);
    if (a) {
      artistFreeText.value = a.name;
    }
    return;
  }
  artistFreeText.value = '';
};

watch(
  () => [form.artist_id, form.artist_name],
  () => syncArtistFreeTextFromForm(),
  { immediate: true },
);

/** В БД hidden=true — скрыта; в UI переключатель «видна». */
const songVisible = computed({
  get() {
    return !form.hidden;
  },
  set(value) {
    form.hidden = !value;
  },
});

const textFields = ['text_fr', 'text_ru', 'text_transcription'];

const isTextFocused = ref(false);
const onTextFocus = () => { isTextFocused.value = true; };
const onTextBlur = () => { isTextFocused.value = false; };

/**
 * Определяет номер строки по позиции курсора в тексте.
 */
const getLineIndex = (text, cursorPos) => {
  return text.substring(0, cursorPos).split('\n').length - 1;
};

/**
 * Вставляет новую строку [00:00] после указанной строки в тексте.
 */
const insertLineAt = (text, lineIndex) => {
  const lines = text ? text.split('\n') : [''];
  lines.splice(lineIndex + 1, 0, '[00:00]');
  return lines.join('\n');
};

const handleTextEnter = (event, field) => {
  event.preventDefault();
  const el = event.target;
  const start = el.selectionStart;
  const end = el.selectionEnd;
  const text = form[field];

  const before = text.substring(0, start);
  const lastNl = before.lastIndexOf('\n');
  const linePart = before.substring(lastNl + 1);

  let lineIndex;
  let cursorPos;

  if (linePart === '') {
    lineIndex = Math.max(getLineIndex(text, start) - 1, -1);
    const head = before.substring(0, lastNl + 1);
    const newField = head + '[00:00]\n' + text.substring(end);
    form[field] = newField;
    cursorPos = head.length + 8;
  } else {
    lineIndex = getLineIndex(text, start);
    const after = text.substring(end);
    const afterFirstLine = after.split('\n')[0];
    const hasTs = /^\[\d{2}:\d{2}/.test(afterFirstLine);
    const prefix = hasTs ? '' : '[00:00]';
    form[field] = before + '\n' + prefix + after;
    cursorPos = before.length + 1 + prefix.length;
  }

  for (const f of textFields) {
    if (f === field) continue;
    form[f] = insertLineAt(form[f], lineIndex);
  }

  nextTick(() => {
    el.selectionStart = el.selectionEnd = cursorPos;
  });
};

const durationPattern = /^\d+:(?:[0-5]\d|[0-9])$/;
const rules = reactive({
  artist_id: [
    {
      validator: (_, __, cb) => {
        const hasId = form.artist_id !== null && form.artist_id !== undefined && form.artist_id !== '';
        const hasName = Boolean(form.artist_name && String(form.artist_name).trim());
        if (props.allowCreateArtist) {
          (hasId || hasName) ? cb() : cb(new Error('Выберите исполнителя или введите имя нового'));
        } else {
          hasId ? cb() : cb(new Error('Выберите исполнителя из списка'));
        }
      },
      trigger: 'change',
    },
  ],
  title: [
    { required: true, message: 'Название не должно быть пустым', trigger: 'change' },
    { min: 2, message: 'Название должно быть не менее 2-х символов', trigger: 'change' },
  ],
  duration: [
    {
      validator: (_, value, cb) => {
        if (!value) return cb();
        if (!durationPattern.test(String(value).trim())) {
          return cb(new Error('Формат: минуты:секунды (например, 2:36)'));
        }
        cb();
      },
      trigger: 'change',
    },
  ],
  text_fr: [
    { required: true, message: 'Текст песни не должен быть пустым', trigger: 'change' },
  ],
  text_ru: [
    { required: true, message: 'Перевод не должен быть пустым', trigger: 'change' },
  ],
  text_transcription: [
    { required: true, message: 'Транскрипция не должна быть пуста', trigger: 'change' },
  ],
});

const submit = async () => {
  resolveArtistFromText();

  const isFormValid = await refForm.value.validate((valid) => valid);

  if(!isFormValid){
    return true;
  }

  const artistText = String(artistFreeText.value ?? '').trim();
  const matched = artistText ? props.artists.find((a) => a.name === artistText) : null;

  form.transform((data) => ({
    ...data,
    artist_id: matched ? matched.id : null,
    artist_name: (!matched && artistText) ? artistText : null,
  }));

  emits('submit', form);
}

watch(
  () => form,
  (newVal) => {
    emits('change', {
      artist_id: newVal.artist_id,
      artist_name: newVal.artist_name,
      title: newVal.title,
      duration: newVal.duration,
      text_fr: newVal.text_fr,
      text_ru: newVal.text_ru,
      text_transcription: newVal.text_transcription,
      hidden: newVal.hidden,
    });
  },
  { deep: true },
);

const applySongTextsFromProps = (s) => {
  if (!s || isTextFocused.value) {
    return;
  }
  const fr = s.text_fr ?? '';
  const ru = s.text_ru ?? '';
  const tr = s.text_transcription ?? '';
  if (form.text_fr !== fr) form.text_fr = fr;
  if (form.text_ru !== ru) form.text_ru = ru;
  if (form.text_transcription !== tr) form.text_transcription = tr;
};

/**
 * Полная подстановка полей с сервера — только при смене песни (id).
 * Нельзя вешать deep-watch на song: родитель обновляет songData при каждом @change формы,
 * а artist в songData остаётся старым с бэкенда — исполнитель сбрасывался при blur/любом поле.
 */
watch(
  () => props.song?.id,
  () => {
    const s = props.song;
    if (!s) {
      return;
    }
    if (s.id != null && s.id !== '') {
      const rawId = s.artist?.id ?? s.artist_id ?? null;
      form.artist_id = rawId != null ? Number(rawId) : null;
      form.artist_name = '';
    }
    form.title = s.title ?? '';
    form.duration = s.duration ?? null;
    form.hidden = s.hidden ?? true;
    applySongTextsFromProps(s);
  },
  { immediate: true },
);

/** Синхронизация текстов, когда их меняет конструктор субтитров (songData без смены id). */
watch(
  () => [props.song?.text_fr, props.song?.text_ru, props.song?.text_transcription],
  () => applySongTextsFromProps(props.song),
);
</script>

<template>
  <div class="p-4">
    <el-form
      ref="refForm"
      :model="form"
      :rules="rules"
      class="flex flex-col"
      @submit.prevent="submit"
    >
      <div class="flex flex-row gap-1">
        <div class="flex-1">
          <el-form-item label="Название песни" label-position="top" prop="title">
            <el-input
              v-model="form.title"
              placeholder="Введите название песни"
            />
          </el-form-item>
        </div>
        <div class="flex-1">
          <el-form-item label="Исполнитель" label-position="top" prop="artist_id">
            <el-autocomplete
              v-model="artistFreeText"
              class="w-full"
              :fetch-suggestions="fetchArtistSuggestions"
              :placeholder="allowCreateArtist ? 'Выберите или введите нового' : 'Выберите исполнителя'"
              clearable
              @select="onArtistAutocompleteSelect"
              @clear="onArtistAutocompleteClear"
              @blur="resolveArtistFromText"
            />
          </el-form-item>
        </div>
        <div class="flex-1">
          <el-form-item label="Продолжительность" label-position="top" prop="duration">
            <el-input
              v-model="form.duration"
              placeholder="Например: 2:36"
              class="w-full"
            />
          </el-form-item>
        </div>
      </div>
      <div class="flex flex-row gap-1">
        <div class="flex-1">
          <el-form-item label="Текст песни" label-position="top" prop="text_fr">
            <el-input
              v-model="form.text_fr"
              :rows="20"
              type="textarea"
              placeholder="Введите текст песни"
              @focus="onTextFocus"
              @blur="onTextBlur"
              @keydown.enter="handleTextEnter($event, 'text_fr')"
            />
          </el-form-item>
        </div>

        <div class="flex-1">
          <el-form-item label="Перевод песни" label-position="top" prop="text_ru">
            <el-input
              v-model="form.text_ru"
              :rows="20"
              type="textarea"
              placeholder="Введите перевод песни"
              @focus="onTextFocus"
              @blur="onTextBlur"
              @keydown.enter="handleTextEnter($event, 'text_ru')"
            />
          </el-form-item>
        </div>

        <div class="flex-1">
          <el-form-item label="Транскрипция песни" label-position="top" prop="text_transcription">
            <el-input
              v-model="form.text_transcription"
              :rows="20"
              type="textarea"
              placeholder="Введите транскрипцию песни"
              @focus="onTextFocus"
              @blur="onTextBlur"
              @keydown.enter="handleTextEnter($event, 'text_transcription')"
            />
          </el-form-item>
        </div>
      </div>
      <div class="flex flex-row flex-wrap items-center justify-between gap-3">
        <el-form-item
          label="Песня видна пользователям"
          label-position="left"
          class="mb-0"
        >
          <el-switch
            v-model="songVisible"
            inline-prompt
            active-text="Да"
            inactive-text="Нет"
          />
        </el-form-item>
        <div>
          <el-button native-type="submit">Сохранить</el-button>
        </div>
      </div>

      <afr-input-error-message v-if="errors.error">
        {{ errors.error }}
      </afr-input-error-message>
    </el-form>
  </div>
</template>

<style scoped>

</style>
