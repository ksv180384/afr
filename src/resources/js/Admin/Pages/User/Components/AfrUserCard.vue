<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  user: { type: Object, default: {} },
  updatedBan: { type: Boolean, default: false },
});
const emits = defineEmits(['ban']);

const ban = (id) => {
  emits('ban', id);
}
</script>

<template>
<div class="flex flex-row">
  <div class="rounded-full drop-shadow-lg p-1 bg-blue-200">
    <img
      :src="user.avatar_link"
      class="w-[80px] h-[80px] object-cover bg-white rounded-full"
      :alt="user.name"
    />
  </div>
  <div class="bg-white rounded-r-xl ps-14 -ms-10 py-2 pe-4 border border-blue-100 flex-1">
    <div>
      {{ user.name }}
    </div>
    <div class="text-xs">{{ user.rang.title }}</div>
    <div>
      <el-popconfirm
        class="box-item"
        title="Вы уверены?"
        hide-icon
        confirm-button-text="Да"
        cancel-button-text="Нет"
        placement="bottom-start"
        @confirm="ban(user)"
      >
        <template #reference>
          <template v-if="user.is_ban">
            <el-button
              type="success"
              plain
              size="small"
              :loading="updatedBan"
            >
              Разбанить
            </el-button>
          </template>
          <template v-else>
            <el-button
              type="danger"
              plain
              size="small"
              :loading="updatedBan"
            >
              Забанить
            </el-button>
          </template>
        </template>
      </el-popconfirm>
    </div>
  </div>
</div>
</template>

<style scoped>

</style>
