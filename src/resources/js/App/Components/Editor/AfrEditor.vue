<script setup>
import { ref, watch, nextTick } from 'vue';
import { useEditor, EditorContent, Node } from '@tiptap/vue-3';
import { Icon } from '@iconify/vue'; // IconPark Outline
import { isImageUrl } from '@/Helpers/helper.js'; // IconPark Outline
import StarterKit from '@tiptap/starter-kit';
import { Color } from '@tiptap/extension-color';
import ListItem from '@tiptap/extension-list-item';
import TextStyle from '@tiptap/extension-text-style';
import Highlight from '@tiptap/extension-highlight';
import Underline from '@tiptap/extension-underline';
import Placeholder from '@tiptap/extension-placeholder';
import HardBreak from '@tiptap/extension-hard-break';
import Table from '@tiptap/extension-table';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import TableRow from '@tiptap/extension-table-row';
import Image from '@tiptap/extension-image';
import TextAlign from '@tiptap/extension-text-align';
import ImageResize from 'tiptap-extension-resize-image';
import { FontSize } from './FontSize';
import AfrSelect from '@/App/Components/Form/Select/AfrSelect.vue';
import AfrOption from '@/App/Components/Form/Select/AfrOption.vue';

const model = defineModel();
const refInputImageLink = ref(null);
const isShowAddImageField = ref(false);
const imageLink = ref('');
const selectFontSize = ref(16);

const CustomTable = Table.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      class: {
        default: null,
        parseHTML: (element) => {
          return element.getAttribute('class');
        },
        renderHTML: (attributes) => {
          if (attributes.class) {
            return {
              class: attributes.class,
            };
          }
        },
      },
    };
  },
});

const editor = useEditor({
  editorProps: {
    attributes: {
      class: 'focus:outline-none bg-white p-3 rounded-lg border min-h-[200px]',
    },
  },
  content: model.value,
  extensions: [
    Placeholder.configure({
      // Use a placeholder:
      placeholder: 'Используйте Shift + Enter для перехода на новую строку без отступа',
      // Use different placeholders depending on the node type:
      // placeholder: ({ node }) => {
      //   if (node.type.name === 'heading') {
      //     return 'What’s the title?'
      //   }

      //   return 'Can you add some further context?'
      // },
    }),
    // Table.configure({
    //   resizable: true,
    // }),
    CustomTable.configure({
      resizable: false,
    }),
    TableRow,
    TableHeader,
    TableCell,
    Image.configure({
      HTMLAttributes: {
        class: 'content-image',
      },
    }),
    ImageResize,
    Color.configure({ types: [TextStyle.name, ListItem.name] }),
    TextStyle.configure({ types: [ListItem.name] }),
    TextAlign.configure({
      types: ['heading', 'paragraph'],
    }),
    StarterKit,
    Highlight,
    Underline,
    FontSize,
    HardBreak.extend({
      addKeyboardShortcuts () {
        return {
          'Shift-Enter': () => this.editor.commands.setHardBreak()
        }
      }
    }),
  ],
  onUpdate: ({ editor }) => {
    const newContent = editor.getHTML();
    if (model.value !== newContent) {
      model.value = newContent;
    }
  },
});

const clearMarks = (editor) => {
  editor.chain().focus().unsetAllMarks().run();
  editor.chain().focus().clearNodes().run();
}

const toggleImg = () => {
  isShowAddImageField.value = !isShowAddImageField.value;
  if(isShowAddImageField.value){
    nextTick(() => {
      refInputImageLink.value.focus();
    });
  }
  // editor.value.chain().focus().redo().run();
}

const addLink = () => {
  if(!isImageUrl(imageLink.value)){
    imageLink.value = '';
    return;
  }
  editor.value.commands.setImage({ src: imageLink.value })
  imageLink.value = '';
  isShowAddImageField.value = false;
  editor.value.chain().focus().redo().run();
}

const addTableClass = (className) => {
  const { state, commands } = editor.value;
  const { from } = state.selection;

  // Обновляем содержимое контента, которое будет храниться в переменной
  // Проверяем, находится ли курсор внутри таблицы
  const isInTable = editor.value.isActive('table');
  if (isInTable) {
    const currentClass = editor.value.getAttributes('table').class || '';
    const newClass = currentClass.includes(className)
      ? currentClass.replace(className, '').trim()
      : `${currentClass} ${className}`.trim();

    // Обновляем атрибуты таблицы
    commands.updateAttributes('table', { class: newClass });
  }
};

const changeFontSize = () => {
  console.log(selectFontSize.value)
  editor.value.chain().focus().setFontSize(`${selectFontSize.value}px`).run();
}

watch(
  () => model.value,
  (newVal) => {
    if (!editor) return;
    // HTML
    // const isSame = editor.getHTML() === value;
    // JSON
    // const isSame = JSON.stringify(this.editor.getJSON()) === JSON.stringify(value)
    // if (isSame) {
    //   return
    // }
    const currentContent = editor.value.getHTML();
    if (currentContent !== newVal) {
      editor.value.commands.setContent(newVal, false);
    }
  }
);
watch(editor, (newEditor) => {
  if (!newEditor) return

  // Обновляем выбранный размер при изменении выделения
  newEditor.on('selectionUpdate', () => {
    const fontSize = newEditor.getAttributes('textStyle').fontSize || 16;
    if (fontSize) {
      selectFontSize.value = parseInt(fontSize)
    }
  })
}, { immediate: true })
</script>

<template>
  <div class="bg-blue-100 rounded-lg">
    <div class="text-controls-container">
      <div class="flex items-center text-sky-800 gap-2 border border-sky-800 ps-2 pe-0.5 text-xs rounded">
        Размер шрифта
        <afr-select
          v-model="selectFontSize"
          @change="changeFontSize"
        >
          <afr-option label="13" :value="13"/>
          <afr-option label="14" :value="14"/>
          <afr-option label="15" :value="15"/>
          <afr-option label="16" :value="16"/>
          <afr-option label="17" :value="17"/>
          <afr-option label="17" :value="17"/>
          <afr-option label="18" :value="18"/>
          <afr-option label="19" :value="19"/>
          <afr-option label="20" :value="20"/>
          <afr-option label="22" :value="22"/>
          <afr-option label="24" :value="24"/>
          <afr-option label="26" :value="26"/>
        </afr-select>
      </div>
      <div class="flex items-center gap-1 border border-sky-800 px-0.5 rounded">
        <input
          type="color"
          @input="editor && editor.chain().focus().setColor($event.target.value).run()"
          :value="editor && editor.getAttributes('textStyle').color"
        >
        <button
          class="btn-preset-color !bg-[#16a34a]"
          :class="{ 'is-active': editor && editor.isActive('textStyle', { color: '#16a34a' })}"
          @click="editor.chain().focus().setColor('#16a34a').run()"
        ></button>
        <button
          class="btn-preset-color !bg-[#ea580c]"
          :class="{ 'is-active': editor && editor.isActive('textStyle', { color: '#ea580c' })}"
          @click="editor.chain().focus().setColor('#ea580c').run()"
        ></button>
      </div>
      <button
        title="Заголовок"
        :class="{ 'is-active': editor && editor.isActive('heading', { level: 3 }) }"
        @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
      >
        <Icon icon="icon-park-outline:h" width="16" height="16" />
      </button>
      <button
        title="Жирный текст"
        :class="{ 'is-active': editor && editor.isActive('bold') }"
        :disabled="editor && !editor.can().chain().focus().toggleBold().run()"
        @click="editor.chain().focus().toggleBold().run()"
      >
        <Icon icon="icon-park-outline:text-bold" width="16" height="16" />
      </button>
      <button
        title="Наклонный шрифт"
        :class="{ 'is-active': editor && editor.isActive('italic') }"
        :disabled="editor && !editor.can().chain().focus().toggleItalic().run()"
        @click="editor.chain().focus().toggleItalic().run()"
      >
        <Icon icon="icon-park-outline:text-italic" width="16" height="16" />
      </button>
      <button
        title="Зачеркнуть текст"
        :class="{ 'is-active': editor && editor.isActive('strike') }"
        :disabled="editor && !editor.can().chain().focus().toggleStrike().run()"
        @click="editor.chain().focus().toggleStrike().run()"
      >
        <Icon icon="icon-park-outline:strikethrough" width="16" height="16" />
      </button>
      <button
        title="Подчеркнуть текст"
        @click="editor.chain().focus().toggleUnderline().run()" :class="{ 'is-active': editor && editor.isActive('underline') }"
      >
        <Icon icon="icon-park-outline:text-underline" width="16" height="16" />
      </button>
      <button
        title="К левому краю"
        :class="{ 'is-active': editor && editor.isActive({ textAlign: 'left' }) }"
        type="button"
        @click="editor.chain().focus().setTextAlign('left').run()"
      >
        <Icon icon="quill:text-left" width="16" height="16" />
      </button>
      <button
        title="По центру"
        :class="{ 'is-active': editor && editor.isActive({ textAlign: 'center' }) }"
        type="button"
        @click="editor.chain().focus().setTextAlign('center').run()"
      >
        <Icon icon="quill:text-center" width="16" height="16" />
      </button>
      <button
        title="К правому краю"
        :class="{ 'is-active': editor && editor.isActive({ textAlign: 'right' }) }"
        type="button"
        @click="editor.chain().focus().setTextAlign('right').run()"
      >
        <Icon icon="quill:text-right" width="16" height="16" />
      </button>
      <button
        title="Подсветить текст"
        @click="editor.chain().focus().toggleHighlight().run()" :class="{ 'is-active': editor && editor.isActive('highlight') }"
      >
        <Icon icon="icon-park-outline:write" width="16" height="16" />
      </button>
      <button
        title="Добавить список"
        :class="{ 'is-active': editor && editor.isActive('bulletList') }"
        @click="editor.chain().focus().toggleBulletList().run()"
      >
        <Icon icon="icon-park-outline:list-two" width="16" height="16" />
      </button>
      <button
        title="Добавить нумерованный список"
        :class="{ 'is-active': editor && editor.isActive('orderedList') }"
        @click="editor.chain().focus().toggleOrderedList().run()"
      >
        <Icon icon="icon-park-outline:list-numbers" width="16" height="16" />
      </button>
      <button
        title="Добавить цитату"
        :class="{ 'is-active': editor && editor.isActive('blockquote') }"
        @click="editor.chain().focus().toggleBlockquote().run()"
      >
        <Icon icon="icon-park-outline:quote" width="16" height="16" />
      </button>
      <button
        title="Добавить разделитель"
        @click="editor.chain().focus().setHorizontalRule().run()"
      >
        <Icon icon="icon-park-outline:dividing-line" width="16" height="16" />
      </button>
      <button
        title="Перейти на строку ниже"
        @click="editor.chain().focus().setHardBreak().run()"
      >
        <Icon icon="icon-park-outline:paragraph-break-two" width="16" height="16" />
      </button>
      <button
        title="Добавить таблицу"
        @click="editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()"
      >
        <Icon icon="icon-park-outline:insert-table" width="16" height="16" />
      </button>
      <div
        title="Добавить изображение"
        class="btn-add-image" :class="{ 'is-show-add-image-field': isShowAddImageField }" @click.prevent="toggleImg"
      >
        <button>
          <Icon icon="icon-park-outline:picture-one" width="16" height="16" />
        </button>
        <div class="input-add-link" @click.stop="() => false">
          <input ref="refInputImageLink" v-model="imageLink" @keydown.enter="addLink"/>
          <button class="btn-add-link" @click="addLink">
            <Icon icon="lets-icons:done-round" width="16" height="16" />
          </button>
        </div>
      </div>
      <button
        title="Отменить действие"
        :disabled="editor && !editor.can().chain().focus().undo().run()"
        @click="editor.chain().focus().undo().run()"
      >
        <Icon icon="icon-park-outline:back" width="16" height="16" />
      </button>
      <button
        title="Повторить действие"
        :disabled="editor && !editor.can().chain().focus().redo().run()"
        @click="editor.chain().focus().redo().run()"
      >
        <Icon icon="icon-park-outline:next" width="16" height="16" />
      </button>
      <button
        title="Удалить стилизацию"
        @click="clearMarks(editor)"
      >
        <Icon icon="icon-park-outline:clear-format" width="16" height="16" />
      </button>
    </div>
    <div v-if="editor && editor.isActive('table')" class="table-controls-container">
      <!--      <button-->
      <!--        @click="editor.chain().focus().insertContent(tableHTML, { parseOptions: { preserveWhitespace: false }}).run()"-->
      <!--      >-->
      <!--        Insert HTML table-->
      <!--      </button>-->
      <button
        title="Добавить колонку слева"
        @click="editor.chain().focus().addColumnBefore().run()" :disabled="editor && !editor.can().addColumnBefore()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-width="0.2" d="M13,2A2,2 0 0,0 11,4V20A2,2 0 0,0 13,22H22V2H13M20,10V14H13V10H20M20,16V20H13V16H20M20,4V8H13V4H20M9,11H6V8H4V11H1V13H4V16H6V13H9V11Z" />
        </svg>
      </button>
      <button
        title="Добавить колонку справа"
        @click="editor.chain().focus().addColumnAfter().run()" :disabled="editor && !editor.can().addColumnAfter()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-width="0.2" d="M11,2A2,2 0 0,1 13,4V20A2,2 0 0,1 11,22H2V2H11M4,10V14H11V10H4M4,16V20H11V16H4M4,4V8H11V4H4M15,11H18V8H20V11H23V13H20V16H18V13H15V11Z" />
        </svg>
      </button>
      <button
        title="Удалить колонку"
        @click="editor.chain().focus().deleteColumn().run()" :disabled="editor && !editor.can().deleteColumn()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-width="0.2" d="M4,2H11A2,2 0 0,1 13,4V20A2,2 0 0,1 11,22H4A2,2 0 0,1 2,20V4A2,2 0 0,1 4,2M4,10V14H11V10H4M4,16V20H11V16H4M4,4V8H11V4H4M17.59,12L15,9.41L16.41,8L19,10.59L21.59,8L23,9.41L20.41,12L23,14.59L21.59,16L19,13.41L16.41,16L15,14.59L17.59,12Z" />
        </svg>
      </button>
      <button
        title="Добавить строку ниже"
        @click="editor.chain().focus().addRowBefore().run()" :disabled="editor && !editor.can().addRowBefore()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-width="0.2" d="M22,14A2,2 0 0,0 20,12H4A2,2 0 0,0 2,14V21H4V19H8V21H10V19H14V21H16V19H20V21H22V14M4,14H8V17H4V14M10,14H14V17H10V14M20,14V17H16V14H20M11,10H13V7H16V5H13V2H11V5H8V7H11V10Z" />
        </svg>
      </button>
      <button
        title="Добавить строку выше"
        @click="editor.chain().focus().addRowAfter().run()" :disabled="editor && !editor.can().addRowAfter()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-width="0.2" d="M22,10A2,2 0 0,1 20,12H4A2,2 0 0,1 2,10V3H4V5H8V3H10V5H14V3H16V5H20V3H22V10M4,10H8V7H4V10M10,10H14V7H10V10M20,10V7H16V10H20M11,14H13V17H16V19H13V22H11V19H8V17H11V14Z" />
        </svg>
      </button>
      <button
        title="Удалить строку"
        @click="editor.chain().focus().deleteRow().run()" :disabled="editor && !editor.can().deleteRow()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-width="0.2" d="M9.41,13L12,15.59L14.59,13L16,14.41L13.41,17L16,19.59L14.59,21L12,18.41L9.41,21L8,19.59L10.59,17L8,14.41L9.41,13M22,9A2,2 0 0,1 20,11H4A2,2 0 0,1 2,9V6A2,2 0 0,1 4,4H20A2,2 0 0,1 22,6V9M4,9H8V6H4V9M10,9H14V6H10V9M16,9H20V6H16V9Z" />
        </svg>
      </button>
      <button
        title="Удалить таблицу"
        @click="editor.chain().focus().deleteTable().run()" :disabled="editor && !editor.can().deleteTable()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-width="0.2" d="M3 3H17C18.11 3 19 3.9 19 5V12.08C17.45 11.82 15.92 12.18 14.68 13H11V17H12.08C11.97 17.68 11.97 18.35 12.08 19H3C1.9 19 1 18.11 1 17V5C1 3.9 1.9 3 3 3M3 7V11H9V7H3M11 7V11H17V7H11M3 13V17H9V13H3M18.5 14C16 14 14 16 14 18.5S16 23 18.5 23 23 21 23 18.5 21 14 18.5 14M18.5 21.5C16.84 21.5 15.5 20.16 15.5 18.5C15.5 17.94 15.65 17.42 15.92 17L20 21.08C19.58 21.35 19.06 21.5 18.5 21.5M21.08 20L17 15.92C17.42 15.65 17.94 15.5 18.5 15.5C20.16 15.5 21.5 16.84 21.5 18.5C21.5 19.06 21.35 19.58 21.08 20Z" />
        </svg>
      </button>
      <button
        title="Разделить ячейку"
        @click="editor.chain().focus().mergeCells().run()" :disabled="editor && !editor.can().mergeCells()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-width="0.2" d="M5,10H3V4H11V6H5V10M19,18H13V20H21V14H19V18M5,18V14H3V20H11V18H5M21,4H13V6H19V10H21V4M8,13V15L11,12L8,9V11H3V13H8M16,11V9L13,12L16,15V13H21V11H16Z" />
        </svg>
      </button>
      <button
        title="Объединить ячейки"
        @click="editor.chain().focus().splitCell().run()" :disabled="editor && !editor.can().splitCell()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-width="0.2" d="M19 14H21V20H3V14H5V18H19V14M3 4V10H5V6H19V10H21V4H3M11 11V13H8V15L5 12L8 9V11H11M16 11V9L19 12L16 15V13H13V11H16Z" />
        </svg>
      </button>
      <button
        title="Сделать колонку заголовком"
        @click="editor.chain().focus().toggleHeaderColumn().run()" :disabled="editor && !editor.can().toggleHeaderColumn()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
          <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v14m6-8h-6m6 4h-6m-9-3h1.99M4 19h16a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1m8-7a2 2 0 1 1-4 0a2 2 0 0 1 4 0" />
        </svg>

      </button>
      <button
        title="Сделать строку заголовком"
        @click="editor.chain().focus().toggleHeaderRow().run()" :disabled="editor && !editor.can().toggleHeaderRow()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
          <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15v3a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-3M3 15V6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v9M3 15h18M8 15v4m4-4v4m4-4v4m-7-9h1.99M15 10a2 2 0 1 1-4 0a2 2 0 0 1 4 0" />
        </svg>
      </button>
      <button
        title="Сделать ячейку заголовком"
        @click="editor.chain().focus().toggleHeaderCell().run()" :disabled="editor && !editor.can().toggleHeaderCell()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
          <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15v3a1 1 0 0 0 1 1h10M3 15v-4m0 4h9m-9-4V6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v3M3 11h11m-2-.208V19m3-4h1.99M21 15a2 2 0 1 1-4 0a2 2 0 0 1 4 0" />
        </svg>
      </button>
      <button @click="addTableClass('w-full')" title="Растянуть по ширине на все свободное пространство">
        <Icon icon="fluent:arrow-fit-16-regular" width="24" height="24" />
      </button>
      <button @click="addTableClass('table-border')" title="Добавить/удалить границы">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
          <path fill="currentColor" d="M21 19a1 1 0 0 1-1 1h-1v-2h2zm-6 1v-2h2v2zm-4 0v-2h2v2zm-4 0v-2h2v2zm-3 0a1 1 0 0 1-1-1v-1h2v2zM19 4H5a2 2 0 0 0-2 2v2h18V6c0-1.11-.89-2-2-2M5 14H3v2h2zm0-4H3v2h2zm16 0h-2v2h2zm0 4h-2v2h2zm-10 2v-2h2v2zm0-4v-2h2v2z"/>
        </svg>
      </button>
      <!--      <button @click="editor.chain().focus().mergeOrSplit().run()" :disabled="editor && !editor.can().mergeOrSplit()">-->
      <!--        Merge or split-->
      <!--      </button>-->
      <!--      <button @click="editor.chain().focus().setCellAttribute('backgroundColor', '#FAF594').run()" :disabled="editor && !editor.can().setCellAttribute('backgroundColor', '#FAF594')">-->
      <!--        Set cell attribute-->
      <!--      </button>-->
      <!--      <button @click="editor.chain().focus().fixTables().run()" :disabled="editor && !editor.can().fixTables()">-->
      <!--        Fix tables-->
      <!--      </button>-->
      <!--      <button @click="editor.chain().focus().goToNextCell().run()" :disabled="editor && !editor.can().goToNextCell()">-->
      <!--        Go to next cell-->
      <!--      </button>-->
      <!--      <button @click="editor.chain().focus().goToPreviousCell().run()" :disabled="editor && !editor.can().goToPreviousCell()">-->
      <!--        Go to previous cell-->
      <!--      </button>-->
    </div>
    <editor-content :editor="editor" />
  </div>
</template>

<style scoped>
.text-controls-container{
  @apply flex flex-row flex-wrap gap-1 px-1 py-2 max-w-full sticky top-[56px] bg-blue-100 rounded-t-lg;
  z-index: 1;
}

.text-controls-container button{
  @apply flex w-[32px] h-[32px] items-center justify-center rounded-md bg-blue-100 border border-blue-100 text-sky-800 hover:text-sky-600;
}

.text-controls-container button[disabled]{
  @apply text-blue-300;
}

.text-controls-container button.is-active{
  @apply border border-sky-800;
}

:deep(.tiptap p.is-editor-empty:first-child::before) {
  color: #adb5bd;
  content: attr(data-placeholder);
  float: left;
  height: 0;
  pointer-events: none;
}

.table-controls-container{
  @apply flex flex-row flex-wrap gap-1;
}

.table-controls-container button{
  @apply w-[36px] h-[36px] flex items-center justify-center rounded bg-blue-100;
}

.table-controls-container button[disabled]{
  @apply text-gray-500;
}

.table-controls-container button.is-active{

}

.table-controls-container button svg{
  @apply w-[20px] h-[20px] text-sky-800 fill-sky-800;
}

.tiptap table{

}

.tiptap table tbody{

}

.tiptap table tbody tr{

}

:deep(.tiptap table tbody tr th){
  @apply border border-gray-300 border-dashed bg-gray-200;
}

:deep(.tiptap table tbody tr td){
  @apply border border-gray-300 border-dashed;
}

:deep(.tiptap table tbody th p){
  @apply px-2 py-1;
}

:deep(.tiptap table tbody td p){
  @apply px-2 py-1;
}

:deep(.tiptap .selectedCell){
  @apply bg-amber-100;
}

:deep(.tiptap mark){
  background-color: #faf594;
  border-radius: .4rem;
  box-decoration-break: clone;
  padding: .1rem .3rem;
}

.btn-add-image{
  @apply flex bg-blue-100 rounded-lg w-[34px];
  transition: all 0.5s ease;
}

.input-add-link{
  @apply hidden items-center pe-1;
  transition: all 0.2s ease;
  opacity: 0;
}

.btn-add-image.is-show-add-image-field .input-add-link{
  @apply flex;
  opacity: 1;
}

.btn-add-image.is-show-add-image-field{
  @apply w-[240px] overflow-hidden;
}

.input-add-link input{
  @apply border rounded px-2 py-0.5 text-sm;
}

.btn-add-link{

}

img.content-image{
  @apply max-w-full;
}

.btn-preset-color{
  @apply !w-[26px] !h-[26px] !border-0;
}
</style>
