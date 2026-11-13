import { apiClient } from './apiClient';

export const recommendationApi = {
  // API-AI-01: Gợi ý việc làm theo CV đã confirmed
  getRecommendations: (cvId) => apiClient.request('GET', `/candidate/recommendations?cv_id=${cvId}`),
};
