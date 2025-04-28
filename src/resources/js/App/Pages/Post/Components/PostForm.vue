<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

import AfrInputErrorMessage from '@/App/Components/Form/AfrInputErrorMessage.vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrInput from '@/App/Components/Form/AfrInput.vue';
import AfrSelect from '@/App/Components/Form/Select/AfrSelect.vue';
import AfrOption from '@/App/Components/Form/Select/AfrOption.vue';
import AfrEditor from '@/App/Components/Editor/AfrEditor.vue';

const props = defineProps({
  authUser: { type: Object, default: null },
  post: { type: Object, default: null },
  statuses: { type: Array, default: [] },
  errors: { type: Object, default: {} },
});
const emits = defineEmits(['submit']);

const form = useForm({
  title: props.post?.title ?? '',
  // content: '<p>I\'m running Tiptap with Vue.js. 🎉</p>',
  content: props.post?.content ?? '',
  status_id: props.post?.status?.id ?? props.statuses?.find(item => item.alias === 'draft')?.id ?? null,
});

const btnTitleSubmit = computed(() => {
  const statusActive = props.statuses.find(item => item.id === parseInt(form.status_id));
  if(statusActive?.alias === 'draft'){
    return 'Добавить черновик';
  }

  return 'Опубликовать';
});

const isEdit = computed(() => {
  return !(props.post?.status?.alias === 'hidden-no-publishing');
});

const isNotStatus = computed(() => {
  return !props.statuses.find(item => item.id === props.post?.status.id);
});

const submit = () => {
  if(!isEdit){
    return true;
  }
  emits('submit', form);
}
</script>

<template>
  <div class="min-h-full flex flex-col px-4 py-2">
    <div class="flex my-4 gap-2">
      <div class="flex-1">
        <afr-input
          v-model="form.title"
          placeholder="Заголовок поста"
          type="text"
        />
        <afr-input-error-message v-if="errors.title">{{ errors.title }}</afr-input-error-message>
      </div>
    </div>
    <div v-if="isNotStatus" class="mb-4">
      Текущий статус: <span class="font-semibold">{{ post?.status.title }}</span>
    </div>
    <afr-editor v-model="form.content"/>
    <afr-input-error-message v-if="errors.content">{{ errors.content }}</afr-input-error-message>
    <div class="flex justify-between gap-2 mt-4">
      <div>
        <afr-button
          :disabled="!isEdit"
          type="success"
          @click="submit"
        >
          {{ btnTitleSubmit }}
        </afr-button>
      </div>
      <div v-if="isEdit" class="w-full">
        <afr-select
          v-model="form.status_id"
          placeholder="Статус"
        >
          <afr-option
            v-for="status in statuses"
            :key="status.id"
            :label="status.title"
            :value="status.id"
          />
        </afr-select>
        <afr-input-error-message v-if="errors.status_id">{{ errors.status_id }}</afr-input-error-message>
      </div>
      <div v-else class="px-2 rounded py-1 text-nowrap bg-red-100">
        {{ post.status.title }}
      </div>
    </div>

    <afr-input-error-message v-if="errors.ban">{{ errors.ban }}</afr-input-error-message>
  </div>
</template>

<style scoped>

</style>
