import { post } from '@/App/Services/Api/query';
// import { objToUrlParams } from '@/helpers/helper';

const uploadAvatar = async (avatar) => {
  return await post(`profile/avatar-upload`, avatar, { headers: { "Content-Type": "multipart/form-data" } });
}

const removeAvatar = async () => {
  return await post(`profile/avatar-remove`);
}

export default {
  uploadAvatar,
  removeAvatar,
};
