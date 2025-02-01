import { get } from '@/App/Services/Api/query';
import { objToUrlParams } from '@/Helpers/helper';

const search = async (params) => {
  const paramPage = objToUrlParams(params);

  return await get(`/song/search-hints${paramPage}`);
}

const  searchByArtistTitle = async (params) => {
  const paramPage = objToUrlParams(params);

  return await get(`/song/search-by-artist-and-title${paramPage}`);
}

const  getById = async (id) => {
  return await get(`/song/show/${id}`);
}

export default {
  search,
  searchByArtistTitle,
  getById,
};
