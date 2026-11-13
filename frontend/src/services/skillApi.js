import { apiClient } from './apiClient';

export const skillApi = {
  // API-SKL-01: Tra cứu danh mục kỹ năng chuẩn (public, đang hoạt động)
  searchSkills: (query = '') => apiClient.request('GET', `/catalog/skills${query ? `?search=${encodeURIComponent(query)}` : ''}`),

  // API-SKL-02: Xem danh sách kỹ năng đã chọn của ứng viên
  getMySkills: () => apiClient.request('GET', '/candidate/skills'),

  // API-SKL-03: Đồng bộ (sync) danh sách kỹ năng đã chọn
  syncSkills: (skillIds) => apiClient.request('PUT', '/candidate/skills', { skill_ids: skillIds }),
};
