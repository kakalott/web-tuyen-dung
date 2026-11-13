import { apiClient } from './apiClient';

export const applicationApi = {
  // API-APP-01: Ứng viên nộp hồ sơ vào tin tuyển dụng
  apply: (jobId, data) => apiClient.request('POST', `/jobs/${jobId}/applications`, data),

  // API-APP-02: Danh sách đơn ứng tuyển của ứng viên
  getMyApplications: (params = '') => apiClient.request('GET', `/candidate/applications${params ? `?${params}` : ''}`),

  // API-APP-03: Chi tiết đơn ứng tuyển
  getApplication: (id) => apiClient.request('GET', `/candidate/applications/${id}`),

  // API-APP-04: Rút đơn ứng tuyển
  withdraw: (id) => apiClient.request('POST', `/candidate/applications/${id}/withdraw`),
};
