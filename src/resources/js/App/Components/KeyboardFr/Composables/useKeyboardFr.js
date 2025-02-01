import { ref } from 'vue';

export function useKeyboardFr(){
  const refInputWord = ref(null);
  const wordInputText = ref('');

  const addLetter = (letter) => {
    const position = refInputWord.value.getCursorPosition();

    // текст до + вставка + текст после
    wordInputText.value = wordInputText.value.substring(0, position.start) + letter + wordInputText.value.substring(position.end);
    const focusPosition = ( position.start === position.end )? (position.end + letter.length) : position.start ;
    refInputWord.value.focus(focusPosition);
  }

  return {
    refInputWord,
    wordInputText,
    addLetter,
  }
}
