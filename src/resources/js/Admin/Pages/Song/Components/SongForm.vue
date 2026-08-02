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
  artists: (props.song?.artists ?? []).map((artist) => ({ id: Number(artist.id), name: null })),
  title: props.song?.title || '',
  duration: props.song?.duration ?? null,
  text_fr: props.song?.text_fr || '',
  text_ru: props.song?.text_ru || '',
  text_transcription: props.song?.text_transcription || '',
  hidden: props.song?.hidden ?? true,
  lyrics_versions: (props.song?.lyrics_versions ?? []).map((version) => ({ ...version })),
});

/** Формирует служебное значение для существующего исполнителя в el-select. */
const artistToken = (artist) => `id:${artist.id}`;
const artistValues = ref((props.song?.artists ?? []).map(artistToken));

/**
 * Преобразует значения мультивыбора в массив существующих и новых исполнителей,
 * который ожидает серверная валидация.
 */
const artistsFromValues = (values) => {
  return values.map((value) => {
    const token = String(value).trim();
    if (token.startsWith('id:')) {
      return { id: Number(token.slice(3)), name: null };
    }
    return { id: null, name: token };
  }).filter((artist) => artist.id || artist.name);
};

watch(
  artistValues,
  (values) => {
    form.artists = artistsFromValues(values);
  },
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

const addLyricsVersion = () => {
  form.lyrics_versions.push({
    id: null,
    duration: '',
    text_fr: '',
    text_ru: '',
    text_transcription: '',
  });
};

const removeLyricsVersion = (index) => {
  form.lyrics_versions.splice(index, 1);
};

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
  artists: [
    {
      validator: (_, value, cb) => {
        Array.isArray(value) && value.length > 0
          ? cb()
          : cb(new Error('Выберите хотя бы одного исполнителя или введите имя нового'));
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

/** Проверяет форму и передаёт подготовленные данные родительской странице. */
const submit = async () => {
  const isFormValid = await refForm.value.validate((valid) => valid);

  if(!isFormValid){
    return true;
  }

  emits('submit', form);
}

watch(
  () => form,
  (newVal) => {
    emits('change', {
      artists: newVal.artists,
      title: newVal.title,
      duration: newVal.duration,
      text_fr: newVal.text_fr,
      text_ru: newVal.text_ru,
      text_transcription: newVal.text_transcription,
      hidden: newVal.hidden,
      lyrics_versions: newVal.lyrics_versions,
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
      artistValues.value = (s.artists ?? []).map(artistToken);
      form.artists = artistsFromValues(artistValues.value);
    }
    form.title = s.title ?? '';
    form.duration = s.duration ?? null;
    form.hidden = s.hidden ?? true;
    form.lyrics_versions = (s.lyrics_versions ?? []).map((version) => ({ ...version }));
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
          <el-form-item label="Исполнители" label-position="top" prop="artists">
            <el-select
              v-model="artistValues"
              class="w-full"
              multiple
              filterable
              :allow-create="allowCreateArtist"
              default-first-option
              placeholder="Выберите или введите исполнителей"
              clearable
            >
              <el-option
                v-for="artist in artists"
                :key="artist.id"
                :label="artist.name"
                :value="artistToken(artist)"
              />
            </el-select>
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

      <div class="mt-5 border-t border-slate-200 pt-4">
        <div class="mb-3 flex items-center justify-between gap-3">
          <div>
            <h3 class="text-base font-semibold text-slate-800">Дополнительные версии текста</h3>
            <p class="text-xs text-slate-500">Для записей песни с другой продолжительностью и таймингом.</p>
          </div>
          <el-button type="primary" plain @click="addLyricsVersion">
            Добавить текст
          </el-button>
        </div>

        <el-empty
          v-if="form.lyrics_versions.length === 0"
          description="Дополнительных версий пока нет"
          :image-size="64"
        />

        <div
          v-for="(version, index) in form.lyrics_versions"
          :key="version.id ?? `new-${index}`"
          class="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-4"
        >
          <div class="mb-3 flex items-end justify-between gap-3">
            <el-form-item
              :label="`Версия ${index + 2}: продолжительность`"
              :prop="`lyrics_versions.${index}.duration`"
              class="mb-0 w-64"
            >
              <el-input v-model="version.duration" placeholder="Например: 3:42" />
            </el-form-item>
            <el-button type="danger" plain @click="removeLyricsVersion(index)">
              Удалить этот текст
            </el-button>
          </div>

          <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
            <el-form-item label="Текст песни" :prop="`lyrics_versions.${index}.text_fr`" label-position="top">
              <el-input
                v-model="version.text_fr"
                :rows="14"
                type="textarea"
                placeholder="Введите текст с временными метками"
              />
            </el-form-item>
            <el-form-item label="Перевод песни" :prop="`lyrics_versions.${index}.text_ru`" label-position="top">
              <el-input
                v-model="version.text_ru"
                :rows="14"
                type="textarea"
                placeholder="Введите перевод с временными метками"
              />
            </el-form-item>
            <el-form-item label="Транскрипция песни" :prop="`lyrics_versions.${index}.text_transcription`" label-position="top">
              <el-input
                v-model="version.text_transcription"
                :rows="14"
                type="textarea"
                placeholder="Введите транскрипцию с временными метками"
              />
            </el-form-item>
          </div>
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
      <afr-input-error-message
        v-for="(message, field) in form.errors"
        :key="field"
      >
        {{ message }}
      </afr-input-error-message>
    </el-form>
  </div>
</template>

<style scoped>

</style>
