import { apiClient } from './apiClient';

export const notificationApi = {
  // API-NOTI-01: Danh sách thông báo (hỗ trợ filter ?is_read=0|1)
  getNotifications: (params = '') => apiClient.request('GET', `/notifications${params ? `?${params}` : ''}`),

  // API-NOTI-02: Đánh dấu 1 thông báo đã đọc
  markRead: (id) => apiClient.request('PATCH', `/notifications/${id}/read`),

  // API-NOTI-03: Đánh dấu tất cả đã đọc
  markAllRead: () => apiClient.request('POST', '/notifications/read-all'),
};
