import axios from 'axios';
import { getToken } from './token';

export const api = axios.create({
  baseURL: process.env.EXPO_PUBLIC_API_URL || 'https://tjb406z56k.execute-api.ap-southeast-1.amazonaws.com/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

api.interceptors.request.use(async (config) => {
  const token = await getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    // Normalise error shape to match the required expected outcome
    if (error.response?.data?.message) {
      return Promise.reject({
        success: false,
        error: {
          code: error.response.status,
          message: error.response.data.message,
        },
      });
    }
    return Promise.reject({
      success: false,
      error: {
        code: 500,
        message: error.message || 'Terjadi kesalahan jaringan',
      },
    });
  }
);
