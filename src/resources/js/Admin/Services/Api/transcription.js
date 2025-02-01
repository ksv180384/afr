import { post } from '@/App/Services/Api/query';
// import { objToUrlParams } from '@/helpers/helper';

const train = async () => {
  return await post(`admin/transcription/train`);
}

const transcribe = async (params) => {
  return await post(`admin/transcription/transcribe`, params);
}

export default {
  train,
  transcribe,
};
