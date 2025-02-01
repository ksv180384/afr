import { post } from '@/App/Services/Api/query';
// import { objToUrlParams } from '@/helpers/helper';

const searchHints = async (params) => {
    return await post(`search`, params);
}

export default {
  searchHints,
};
