import { apiClient } from './apiClient';

export const cvApi = {
  // API-CV-01: Danh sách CV của ứng viên
  getCvs: () => apiClient.request('GET', '/candidate/cvs'),

  // API-CV-02: Upload CV mới (FormData với file PDF)
  uploadCv: (formData) => apiClient.request('POST', '/candidate/cvs', formData),

  // API-CV-03: Xem chi tiết CV
  getCv: (cvId) => apiClient.request('GET', `/candidate/cvs/${cvId}`),

  // API-CV-04: Tải file CV gốc (stream)
  downloadCv: (cvId) => `${import.meta.env.VITE_API_BASE_URL || '/api/v1'}/candidate/cvs/${cvId}/file`,

  // API-CV-05: Xem kết quả trích xuất
  getExtraction: (cvId) => apiClient.request('GET', `/candidate/cvs/${cvId}/extraction`),

  // API-CV-06: Xác nhận trích xuất (Confirmed-CV Gate)
  confirmExtraction: (cvId, data) => apiClient.request('PATCH', `/candidate/cvs/${cvId}/extraction`, data),

  // API-CV-07: Thử trích xuất lại
  retryExtraction: (cvId) => apiClient.request('POST', `/candidate/cvs/${cvId}/retry-extraction`),
};
