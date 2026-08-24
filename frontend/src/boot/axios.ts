import { defineBoot } from '#q-app';
import axios from 'axios';
import { Notify } from 'quasar';

export const api = axios.create({
  baseURL: '/api'
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status;

    if (status === 401 && !window.location.pathname.startsWith('/login')) {
      localStorage.removeItem('token');
      window.location.href = '/login';
    } else if (status && status >= 500) {
      Notify.create({
        type: 'negative',
        message: 'Ошибка сервера, попробуйте позже'
      });
    }

    return Promise.reject(error);
  }
);

export default defineBoot(({ app }) => {
  app.provide('api', api);
});
