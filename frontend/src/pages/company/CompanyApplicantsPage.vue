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
          <option value="passed">Đạt tuyển dụng (passed)</option>
          <option value="rejected">Từ chối (rejected)</option>
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
              <div class="row-actions">
                <button
                  @click="handleViewCv(app.id)"
                  class="btn-row-cv"
                  title="Xem CV gốc & bắt đầu xem xét"
                >
                  📄 CV
                </button>
                <router-link
                  :to="`/company/applications/${app.id}`"
                  class="btn-detail"
                >
                  Chi tiết
                </router-link>
                <button
                  v-if="!isTerminal(app.status)"
                  @click="openRejectModal(app)"
                  class="btn-row-reject"
                  title="Từ chối hồ sơ"
                >
                  ✕
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty State -->
    <EmptyState v-else text="Chưa có hồ sơ ứng viên nào nộp vào tin này." />

    <!-- Modal Từ chối nhanh -->
    <div v-if="rejectTarget" class="modal-backdrop" @click.self="rejectTarget = null">
      <div class="modal-card">
        <h3 class="modal-title">Từ chối đơn ứng tuyển</h3>
        <p class="modal-desc">
          Xác nhận từ chối hồ sơ của <strong>{{ rejectTarget.candidate_name || 'ứng viên' }}</strong>.
        </p>
        <div class="modal-form-group">
          <label>Lý do từ chối (Ghi chú):</label>
          <textarea
            v-model="rejectReason"
            rows="3"
            class="modal-textarea"
            placeholder="Lý do từ chối..."
          ></textarea>
        </div>
        <div class="modal-actions">
          <button type="button" @click="rejectTarget = null" class="btn-modal-cancel">Hủy</button>
          <button
            type="button"
            @click="handleConfirmReject"
            :disabled="rejecting"
            class="btn-modal-danger"
          >
            {{ rejecting ? 'Đang xử lý...' : 'Xác nhận từ chối' }}
          </button>
        </div>
      </div>
    </div>
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

const rejectTarget = ref(null);
const rejectReason = ref('');
const rejecting = ref(false);

const filters = ref({
  status: '',
  sort: 'applied_desc', // Mặc định là mới nhất
});

const isTerminal = (st) => ['passed', 'rejected', 'withdrawn'].includes(st);

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

const handleViewCv = async (appId) => {
  try {
    const blob = await applicationApi.downloadCompanyApplicationCv(appId);
    const url = window.URL.createObjectURL(blob);
    window.open(url, '_blank');
  } catch (err) {
    alert(err.message || 'Không thể mở CV ứng viên.');
  } finally {
    await loadApplicants(); // Nạp lại danh sách để cập nhật status (pending -> reviewing)
  }
};

const openRejectModal = (app) => {
  rejectTarget.value = app;
  rejectReason.value = '';
};

const handleConfirmReject = async () => {
  if (!rejectTarget.value) return;
  rejecting.value = true;
  try {
    await applicationApi.changeStatus(rejectTarget.value.id, {
      new_status: 'rejected',
      note: rejectReason.value || 'Doanh nghiệp từ chối hồ sơ',
    });
    rejectTarget.value = null;
    await loadApplicants();
  } catch (err) {
    alert(err.message || 'Từ chối thất bại.');
  } finally {
    rejecting.value = false;
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

.row-actions {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.btn-row-cv {
  background-color: #f1f5f9;
  border: 1px solid #cbd5e1;
  color: #334155;
  padding: 0.35rem 0.65rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-row-cv:hover {
  background-color: #e2e8f0;
  color: #0f172a;
}

.btn-detail {
  display: inline-block;
  background-color: #e0f2fe;
  color: #0369a1;
  padding: 0.35rem 0.75rem;
  border-radius: 0.375rem;
  text-decoration: none;
  font-size: 0.75rem;
  font-weight: 600;
  transition: all 0.15s;
}

.btn-detail:hover {
  background-color: #0284c7;
  color: #ffffff;
}

.btn-row-reject {
  background-color: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
  padding: 0.35rem 0.55rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-row-reject:hover {
  background-color: #fee2e2;
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

/* Modals */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-card {
  background: #ffffff;
  border-radius: 0.5rem;
  padding: 1.5rem;
  max-width: 440px;
  width: 100%;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.modal-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: #0f172a;
}

.modal-desc {
  font-size: 0.875rem;
  color: #475569;
  line-height: 1.4;
}

.modal-form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.modal-form-group label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #334155;
}

.modal-textarea {
  padding: 0.5rem 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  outline: none;
  font-family: inherit;
}

.modal-textarea:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.btn-modal-cancel {
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  color: #475569;
  padding: 0.45rem 0.9rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
}

.btn-modal-danger {
  background: #dc2626;
  border: 1px solid #dc2626;
  color: #ffffff;
  padding: 0.45rem 0.9rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-modal-danger:hover:not(:disabled) {
  background: #b91c1c;
}
</style>
