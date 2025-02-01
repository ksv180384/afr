import dayjs from 'dayjs';
import 'dayjs/locale/ru.js';
import customParseFormat from 'dayjs/plugin/customParseFormat.js';

dayjs.extend(customParseFormat);
dayjs.locale('ru');

export default dayjs;
