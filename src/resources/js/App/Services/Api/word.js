import { get } from '@/App/Services/Api/query';
// import { objToUrlParams } from '@/helpers/helper';

const getWordsLearningWrite = async () => {
  return await get(`/word/learning-write`);
}

const getWordsTestYourself = async () => {
  return await get(`word/test-yourself`);
}

export default {
  getWordsLearningWrite,
  getWordsTestYourself,
};
