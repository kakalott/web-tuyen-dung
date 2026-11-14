import { apiClient } from './apiClient';

export const cvApi = {
  // API-CV-01: Danh sách CV của ứng viên
  getCvs(params = {}) {
    const query = new URLSearchParams(params).toString();
    return apiClient.request('GET', `/candidate/cvs${query ? `?${query}` : ''}`);
  },

  list(params = {}) {
    return this.getCvs(params);
  },

  // API-CV-02: Upload CV mới (FormData với trường 'file')
  upload(file, signal = null) {
    const formData = new FormData();
    formData.append('file', file); // Khớp trường 'file' trong UploadCVRequest

    return apiClient.request('POST', '/candidate/cvs', formData, { signal });
  },

  uploadCv(formData) {
    // Tương thích ngược nếu nhận FormData trực tiếp
    return apiClient.request('POST', '/candidate/cvs', formData);
  },

  // API-CV-03: Xem chi tiết CV
  getCv(cvId) {
    return apiClient.request('GET', `/candidate/cvs/${cvId}`);
  },

  // API-CV-04: Tải file CV gốc qua blob an toàn
  downloadOwn(cvId) {
    return apiClient.download(`/candidate/cvs/${cvId}/file`, `cv-${cvId}.pdf`);
  },

  downloadCv(cvId) {
    return this.downloadOwn(cvId);
  },

  // Tải CV ứng viên cho công ty
  downloadForApplication(applicationId) {
    return apiClient.download(`/company/applications/${applicationId}/cv`, `application-${applicationId}-cv.pdf`);
  },

  // API-CV-05: Xem kết quả trích xuất
  getExtraction(cvId) {
    return apiClient.request('GET', `/candidate/cvs/${cvId}/extraction`);
  },

  // API-CV-06: Xác nhận trích xuất (Confirmed-CV Gate)
  confirmExtraction(cvId, data) {
    return apiClient.request('PATCH', `/candidate/cvs/${cvId}/extraction`, data);
  },

  confirm(cvId, data) {
    return this.confirmExtraction(cvId, data);
  },

  // API-CV-07: Thử trích xuất lại
  retryExtraction(cvId) {
    return apiClient.request('POST', `/candidate/cvs/${cvId}/retry-extraction`);
  },
};
