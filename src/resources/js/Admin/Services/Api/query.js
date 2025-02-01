import axios from 'axios';
import { interceptors } from '@/App/Services/interceptors';

// const userToken = '';//localStorage.getItem('user-token-page');

const query = axios.create({
  baseURL: '/',
  headers: {
    // 'App-User-Token': userToken,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  //withCredentials: true,
});

interceptors(query);

export const get = (path, conf = {}) => {
  return query.get(path, conf);
}
export const post = (path, data, conf = {}) => {
  return query.post(path, data, conf);
}

export const put = (path, data, conf = {}) => {
  return query.put(path, data, conf);
}

export const del = (path, data, conf = {}) => {
  return query.delete(path, data, conf);
}

export const page = async (path, params, conf = {}) => {
  path = path.replace(/^[/]+|[/]+$/g, '').trim();

  params = params || {};
  let paramsStr = '';
  if(params){
    paramsStr = '?';
    for (let param in params){
      paramsStr += `${param}=${params[param]}&`;
    }
    paramsStr = paramsStr.slice(0, -1);
  }
  path = `/page/${path}${paramsStr}`;
  path = path.replace(/^[/]+|[/]+$/g, '')

  return query.get(path, conf);
}

