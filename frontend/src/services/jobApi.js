import { apiClient } from './apiClient';

export const jobApi = {
  // API-JOB-03: Danh sách tin tuyển dụng của công ty
  indexOwn: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return apiClient.request('GET', `/company/jobs${query ? `?${query}` : ''}`);
  },

  // API-JOB-04: Đăng tin tuyển dụng mới
  store: (payload) => apiClient.request('POST', '/company/jobs', payload),

  // API-JOB-05: Xem chi tiết tin của chính mình
  showOwn: (jobId) => apiClient.request('GET', `/company/jobs/${jobId}`),

  // API-JOB-06: Cập nhật tin tuyển dụng
  update: (jobId, payload) => apiClient.request('PATCH', `/company/jobs/${jobId}`, payload),

  // API-JOB-07: Đóng tin tuyển dụng
  close: (jobId) => apiClient.request('POST', `/company/jobs/${jobId}/close`),
};
