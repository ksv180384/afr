import { push } from 'notivue';

/**
 * Формируем строку get параметров из объекта
 * @param params
 * @returns {string}
 */
export const objToUrlParams = (params) => {
    if(!params || !Object.keys(params).length){
        return '';
    }
    const p = {};
    Object.keys(params).forEach(item => {
        // Если значение параметра является массивом, то формируем объект вида { 'key[0]': val, 'key[1]': val2 }
        if(Array.isArray(params[item])){
            for (const arrKey in params[item]){
                p[`${item}[${arrKey}]`] = `${params[item][arrKey]}`;
            }
        } else {
            p[item] = params[item];
        }
    });
    return '?' + new URLSearchParams(p).toString();
}

export const speak = (word, lang = 'fr-FR') => {
  const speech = new SpeechSynthesisUtterance();

  speech.lang = lang;
  speech.rate = .8;
  speech.pitch = 1;
  speech.volume = 1;
  speech.text = word;
  window.speechSynthesis.cancel();
  window.speechSynthesis.speak(speech);
};

export const getErrorsInResponse = (res) => {
  const errorsResponse = res.response.data?.errors;
  const errors = {};
  if(!errorsResponse){
    return errors;
  }

  for (const errorsInput in errorsResponse){
    const errorMessage = Array.isArray(errorsResponse[errorsInput]) ? errorsResponse[errorsInput][0] : errorsResponse[errorsInput];
    errors[errorsInput] = errorMessage;
  }

  return errors;
}

export const getFirstErrorMessage = (res) => {
  const messagesList = getErrorsInResponse(res);
  return Object.values(messagesList)?.[0] || 'Неизвестная ошибка.';
}

export const showErrorNotification = (title, response) => {
  const message = getFirstErrorMessage(response);
  push.error({ title, message });
}

export const getRandomElement = (arr) => {
  const randomIndex = Math.floor(Math.random() * arr.length);
  return arr[randomIndex];
}

export const getRandomId = (arr) => {
  return Math.floor(Math.random() * arr.length);
}

// Узнаем ширину скролл бара
export const getScrollbarWidth = () => {
  // Создаем временный элемент
  const div = document.createElement('div');

  // Применяем стили, чтобы элемент имел прокрутку
  div.style.overflowY = 'scroll';
  div.style.width = '50px';
  div.style.height = '50px';
  div.style.visibility = 'hidden'; // Скрываем элемент

  // Добавляем элемент в документ
  document.body.appendChild(div);

  // Вычисляем ширину полосы прокрутки
  const scrollbarWidth = div.offsetWidth - div.clientWidth;

  // Удаляем временный элемент
  document.body.removeChild(div);

  return scrollbarWidth;
}

export const declensionYears = (number) => {
  const titles = ['год', 'года', 'лет']; // массив с формами слова
  const mod10 = number % 10; // остаток от деления на 10
  const mod100 = number % 100; // остаток от деления на 100

  if (mod10 === 1 && mod100 !== 11) {
    return `${number} ${titles[0]}`; // 1 год
  } else if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
    return `${number} ${titles[1]}`; // 2 года, 3 года, 4 года
  } else {
    return `${number} ${titles[2]}`; // 5 лет, 0 лет, 11 лет и т.д.
  }
}

export const isImageUrl = (url) => {
  const imageRegex = /\.(jpeg|jpg|gif|png|webp|svg)$/i; // Регулярное выражение для проверки расширений изображений
  try {
    const parsedUrl = new URL(url); // Проверяем, является ли строка валидным URL
    return imageRegex.test(parsedUrl.pathname); // Проверяем, заканчивается ли путь на расширение изображения
  } catch (e) {
    return false; // Если строка не является валидным URL, возвращаем false
  }
};

/**
 * Получаем нужный параметр из url
 * @param paramName
 * @returns {string|null}
 */
export const getUrlParam = (paramName) => {
  const param = new URLSearchParams(window.location.search).get(paramName);
  return param || null;
};

