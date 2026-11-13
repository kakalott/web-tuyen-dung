import { apiClient } from './apiClient';

export const adminApi = {
  // API-ADM-08: Thống kê tổng quan Dashboard
  getDashboard: () => apiClient.request('GET', '/admin/dashboard'),

  // API-ADM-01: Danh sách công ty chờ xác minh
  getUnverifiedCompanies: () => apiClient.request('GET', '/admin/companies/unverified'),

  // API-ADM-02: Chi tiết doanh nghiệp
  getCompanyDetail: (companyId) => apiClient.request('GET', `/admin/companies/${companyId}`),

  // API-ADM-03: Xét duyệt doanh nghiệp (verify)
  verifyCompany: (companyId, payload) => apiClient.request('PATCH', `/admin/companies/${companyId}/verification`, payload),

  // API-ADM-04: Danh sách tin tuyển dụng chờ duyệt
  getPendingJobs: () => apiClient.request('GET', '/admin/jobs/pending'),

  // API-ADM-05: Chi tiết tin tuyển dụng phía Admin
  getJobDetail: (id) => apiClient.request('GET', `/admin/jobs/${id}`),

  // API-ADM-06: Phê duyệt / từ chối tin tuyển dụng
  reviewJob: (id, payload) => apiClient.request('PATCH', `/admin/jobs/${id}/review`, payload),

  // API-ADM-07: Ẩn tin vi phạm
  hideJob: (id, payload) => apiClient.request('PATCH', `/admin/jobs/${id}/hide`, payload),

  // API-CAT-01: Danh sách kỹ năng chuẩn có phân trang & search
  getSkills: (params = {}) => {
    const q = new URLSearchParams(params).toString();
    return apiClient.request('GET', `/admin/catalog/skills${q ? `?${q}` : ''}`);
  },

  // API-CAT-02: Tạo mới kỹ năng chuẩn
  addSkill: (payload) => apiClient.request('POST', '/admin/catalog/skills', payload),

  // API-CAT-03: Sửa kỹ năng chuẩn (đổi tên, toggle is_active)
  updateSkill: (id, payload) => apiClient.request('PATCH', `/admin/catalog/skills/${id}`, payload),

  // API-CAT-04: Thêm alias cho kỹ năng
  addSkillAlias: (skillId, payload) => apiClient.request('POST', `/admin/catalog/skills/${skillId}/aliases`, payload),

  // API-CAT-05: Cập nhật alias (đổi tên, toggle is_active)
  updateSkillAlias: (aliasId, payload) => apiClient.request('PATCH', `/admin/catalog/aliases/${aliasId}`, payload),

  // API-CAT-07: Danh sách danh mục động (job-groups, employment-types, work-modes, seniority-levels)
  getLookups: (catalog, params = {}) => {
    const q = new URLSearchParams(params).toString();
    return apiClient.request('GET', `/admin/catalog/${catalog}${q ? `?${q}` : ''}`);
  },

  // API-CAT-08: Thêm mục danh mục
  addLookup: (catalog, payload) => apiClient.request('POST', `/admin/catalog/${catalog}`, payload),

  // API-CAT-09: Cập nhật mục danh mục (sửa tên, toggle is_active)
  updateLookup: (catalog, id, payload) => apiClient.request('PATCH', `/admin/catalog/${catalog}/${id}`, payload),
};
