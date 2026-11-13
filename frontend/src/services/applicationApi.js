import { apiClient } from './apiClient';

export const applicationApi = {
  // Candidate methods
  apply: (jobId, data) => apiClient.request('POST', `/jobs/${jobId}/applications`, data),
  getMyApplications: (params = '') => apiClient.request('GET', `/candidate/applications${params ? `?${params}` : ''}`),
  getApplication: (id) => apiClient.request('GET', `/candidate/applications/${id}`),
  withdraw: (id) => apiClient.request('POST', `/candidate/applications/${id}/withdraw`),

  // Company methods
  // API-APP-05: Danh sách đơn theo việc làm
  getCompanyApplications: (jobId, params = {}) => {
    const queryString = new URLSearchParams(params).toString();
    return apiClient.request('GET', `/company/jobs/${jobId}/applications${queryString ? `?${queryString}` : ''}`);
  },

  // API-APP-06: Chi tiết đơn ứng tuyển phía công ty
  getCompanyApplicationDetail: (id) => apiClient.request('GET', `/company/applications/${id}`),

  // API-APP-08: Thay đổi trạng thái đơn
  changeStatus: (id, payload) => apiClient.request('PATCH', `/company/applications/${id}/status`, payload),

  // API-APP-07: Download CV private stream
  downloadCv: (id) => {
    return apiClient.download(`/company/applications/${id}/cv`, `application-${id}-cv.pdf`);
  },
};
