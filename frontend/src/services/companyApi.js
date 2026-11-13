import { apiClient } from './apiClient';

export const companyApi = {
  // API-COM-01: Xem hồ sơ công ty
  getProfile: () => apiClient.request('GET', '/company/profile'),

  // API-COM-02: Cập nhật hồ sơ công ty
  updateProfile: (data) => apiClient.request('PATCH', '/company/profile', data),
};
