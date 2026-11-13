import { apiClient } from './apiClient';

export const authApi = {
  register: (payload) => apiClient.request('POST', '/auth/register', payload),
  login: (payload) => apiClient.request('POST', '/auth/login', payload),
  logout: () => apiClient.request('POST', '/auth/logout'),
  me: () => apiClient.request('GET', '/me'),
};
