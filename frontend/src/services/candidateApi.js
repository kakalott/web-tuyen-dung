import { apiClient } from './apiClient';

export const candidateApi = {
  // API-CAN-01: Xem hồ sơ ứng viên (profile + educations + experiences)
  getProfile: () => apiClient.request('GET', '/candidate/profile'),

  // API-CAN-02: Cập nhật hồ sơ ứng viên
  updateProfile: (data) => apiClient.request('PATCH', '/candidate/profile', data),

  // API-CAN-03: Thêm học vấn
  addEducation: (data) => apiClient.request('POST', '/candidate/educations', data),

  // API-CAN-04: Cập nhật học vấn
  updateEducation: (id, data) => apiClient.request('PATCH', `/candidate/educations/${id}`, data),

  // API-CAN-05: Xóa học vấn
  deleteEducation: (id) => apiClient.request('DELETE', `/candidate/educations/${id}`),

  // API-CAN-06: Thêm kinh nghiệm
  addExperience: (data) => apiClient.request('POST', '/candidate/experiences', data),

  // API-CAN-07: Cập nhật kinh nghiệm
  updateExperience: (id, data) => apiClient.request('PATCH', `/candidate/experiences/${id}`, data),

  // API-CAN-08: Xóa kinh nghiệm
  deleteExperience: (id) => apiClient.request('DELETE', `/candidate/experiences/${id}`),
};
