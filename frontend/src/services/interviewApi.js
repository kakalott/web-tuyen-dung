import { apiClient } from './apiClient';

export const interviewApi = {
  // API-INT-01: Lên lịch phỏng vấn mới
  schedule: (applicationId, payload) => apiClient.request('POST', `/company/applications/${applicationId}/interviews`, payload),

  // API-INT-02: Cập nhật lịch phỏng vấn
  update: (interviewId, payload) => apiClient.request('PATCH', `/company/interviews/${interviewId}`, payload),

  // API-INT-03: Hủy lịch phỏng vấn
  cancel: (interviewId, payload) => apiClient.request('POST', `/company/interviews/${interviewId}/cancel`, payload),

  // API-INT-04: Đánh dấu phỏng vấn hoàn tất
  complete: (interviewId) => apiClient.request('POST', `/company/interviews/${interviewId}/complete`),

  // API-INT-05: Đánh dấu ứng viên vắng mặt
  noShow: (interviewId) => apiClient.request('POST', `/company/interviews/${interviewId}/no-show`),
};
