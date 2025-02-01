<script setup>
import { onMounted, useSlots, reactive, h, watch } from 'vue';
import { useVuelidate } from '@vuelidate/core';

const props = defineProps({
  form: { type: Object, default: {} },
  rules: { type: Object, default: {} },
});
const slots = useSlots();
const errors = reactive({});

const initFormItems = (children) => {
  for (const childrenKey in children){
    if(children[childrenKey].type?.__name === 'AfrFormItem'){
      errors[children[childrenKey].props?.name] = '';
      children[childrenKey].props.error = errors[children[childrenKey].props?.name];
    }
    if (children[childrenKey].children) {
      children[childrenKey].children = initFormItems(children[childrenKey].children);
    }
  }

  return children;
}

const render = () => {
  const r = initFormItems(slots.default());
  console.log(r);
  return h('div', r);
};

const v$ = useVuelidate(props.rules, props.form);

watch(
  () => v$.value.$errors,
  (newVal) => {
    console.log(newVal);
  },
  { deep: true }
);

onMounted(() => {

});
</script>

<template>
<form>
  <render/>
  {{ errors }}
</form>
</template>

<style scoped>

</style>
