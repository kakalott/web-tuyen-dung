<template>
  <div class="company-applicants-page">
    <div class="page-header">
      <div>
        <router-link :to="`/company/jobs/${jobId}`" class="back-link">
          &larr; Quay lại tin tuyển dụng
        </router-link>
        <h1 class="page-title">Danh sách Ứng viên nộp hồ sơ</h1>
      </div>
    </div>

    <!-- Toolbar: Lọc và Sắp xếp -->
    <div class="filter-card">
      <div class="filter-group">
        <label>Trạng thái hồ sơ:</label>
        <select v-model="filters.status" @change="loadApplicants" class="select-filter">
          <option value="">Tất cả trạng thái</option>
          <option value="pending">Chờ xử lý (pending)</option>
          <option value="reviewing">Đang xem xét (reviewing)</option>
          <option value="interview">Phỏng vấn (interview)</option>
          <option value="offered">Chào việc (offered)</option>
          <option value="hired">Đã tuyển (hired)</option>
          <option value="withdrawn">Đã rút (withdrawn)</option>
        </select>
      </div>

      <div class="filter-group">
        <label>Sắp xếp theo:</label>
        <!-- AI-004: Sort MatchScore desc (BR-AI-07) -->
        <select v-model="filters.sort" @change="loadApplicants" class="select-filter sort-select">
          <option value="applied_desc">Mới nhất (Ngày nộp giảm dần)</option>
          <option value="match_desc">Điểm AI MatchScore (Cao → Thấp)</option>
        </select>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="state-panel loading">Đang tải danh sách hồ sơ...</div>

    <!-- Error State -->
    <div v-else-if="error" class="state-panel error">{{ error }}</div>

    <!-- Applicants Table -->
    <div v-else-if="applicants.length" class="table-wrap">
      <table class="applicants-table">
        <thead>
          <tr>
            <th>Ứng viên</th>
            <th>Ngày nộp</th>
            <th>Trạng thái</th>
            <th>Match Score (AI)</th>
            <th class="col-actions">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="app in applicants" :key="app.id">
            <td class="col-candidate">
              <span class="candidate-name">{{ app.candidate_name || 'Ứng viên' }}</span>
              <span v-if="app.candidate_email" class="candidate-email">{{ app.candidate_email }}</span>
            </td>
            <td class="text-sm text-muted">
              {{ formatDate(app.applied_at) }}
            </td>
            <td>
              <StatusBadge :status="app.status" domain="application" />
            </td>
            <td>
              <div class="score-cell">
                <span v-if="app.match_score !== null && app.match_score !== undefined" class="score-val">
                  {{ app.match_score }}%
                </span>
                <span v-else class="score-na">Chưa tính</span>

                <!-- Cảnh báo nếu JD hoặc CV thay đổi khiến điểm lạc hậu -->
                <span
                  v-if="app.score_stale"
                  class="stale-badge"
                  title="Điểm có thể chưa cập nhật do tin tuyển dụng hoặc CV vừa thay đổi"
                >
                  Cũ
                </span>
              </div>
            </td>
            <td class="col-actions">
              <router-link
                :to="`/company/applications/${app.id}`"
                class="btn-detail"
              >
                Xem chi tiết &amp; Xử lý
              </router-link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty State -->
    <EmptyState v-else text="Chưa có hồ sơ ứng viên nào nộp vào tin này." />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { applicationApi } from '@/services/applicationApi';
import StatusBadge from '@/components/common/StatusBadge.vue';
import EmptyState from '@/components/common/EmptyState.vue';

const route = useRoute();
const jobId = route.params.jobId;

const applicants = ref([]);
const loading = ref(true);
const error = ref('');

const filters = ref({
  status: '',
  sort: 'applied_desc', // Mặc định là mới nhất
});

const loadApplicants = async () => {
  loading.value = true;
  error.value = '';
  try {
    const params = {};
    if (filters.value.status) params.status = filters.value.status;
    if (filters.value.sort) params.sort = filters.value.sort;

    const res = await applicationApi.getCompanyApplications(jobId, params);
    applicants.value = res.data?.items || res.data || [];
  } catch (err) {
    error.value = err.message || 'Không thể tải danh sách ứng viên.';
  } finally {
    loading.value = false;
  }
};

const formatDate = (ds) => {
  return ds ? new Date(ds).toLocaleString('vi-VN') : '—';
};

onMounted(loadApplicants);
</script>

<style scoped>
.company-applicants-page {
  max-width: 1050px;
}

.page-header {
  margin-bottom: 1.25rem;
}

.back-link {
  color: #0284c7;
  text-decoration: none;
  font-size: 0.8125rem;
  font-weight: 500;
  display: inline-block;
  margin-bottom: 0.25rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
}

.filter-card {
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1rem 1.25rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1.5rem;
  margin-bottom: 1.25rem;
}

.filter-group {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.filter-group label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #475569;
}

.select-filter {
  padding: 0.45rem 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  background-color: #ffffff;
  outline: none;
}

.sort-select {
  background-color: #f0f9ff;
  border-color: #bae6fd;
  color: #0369a1;
  font-weight: 600;
}

.table-wrap {
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.applicants-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.applicants-table th {
  background-color: #f8fafc;
  padding: 0.75rem 1rem;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #475569;
  border-bottom: 1px solid #e2e8f0;
}

.applicants-table td {
  padding: 0.875rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.875rem;
  vertical-align: middle;
}

.col-candidate {
  display: flex;
  flex-direction: column;
}

.candidate-name {
  font-weight: 600;
  color: #0f172a;
}

.candidate-email {
  font-size: 0.75rem;
  color: #64748b;
}

.score-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.score-val {
  font-weight: 800;
  font-size: 1.1rem;
  color: #0284c7;
}

.score-na {
  color: #94a3b8;
  font-size: 0.8125rem;
  font-style: italic;
}

.stale-badge {
  background-color: #fef3c7;
  color: #92400e;
  padding: 0.1rem 0.4rem;
  border-radius: 0.25rem;
  font-size: 0.7rem;
  font-weight: 600;
}

.col-actions {
  text-align: right;
}

.btn-detail {
  display: inline-block;
  background-color: #e0f2fe;
  color: #0369a1;
  padding: 0.4rem 0.8rem;
  border-radius: 0.375rem;
  text-decoration: none;
  font-size: 0.8125rem;
  font-weight: 600;
  transition: all 0.15s;
}

.btn-detail:hover {
  background-color: #0284c7;
  color: #ffffff;
}

.text-muted {
  color: #64748b;
}

.text-sm {
  font-size: 0.8125rem;
}

.state-panel {
  padding: 3rem;
  text-align: center;
  border-radius: 0.5rem;
}

.state-panel.loading {
  color: #0284c7;
  background: #f0f9ff;
}

.state-panel.error {
  color: #b91c1c;
  background: #fef2f2;
}
</style>
