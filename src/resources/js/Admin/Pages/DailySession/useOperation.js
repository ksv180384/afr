import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { push } from 'notivue';
export const useOperation = () => {
  const busy = ref(false);
  const failure = ref(null);
  const run = async (operation, refresh = false) => {
    if (busy.value) return null;
    busy.value = true;
    failure.value = null;
    try {
      const result = await operation();
      push.success({ title: 'Готово', message: 'Действие выполнено.' });
      if (refresh) router.reload({ preserveScroll: true });
      return result;
    } catch (error) {
      failure.value = error.response?.data ?? { message: error.message || 'Не удалось выполнить запрос.' };
      return null;
    } finally { busy.value = false; }
  };
  return { busy, failure, run };
};
