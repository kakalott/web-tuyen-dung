<template>
  <div class="company-jobs-page">
    <div class="page-header">
      <div>
        <h1 class="page-title">Quản lý Tin tuyển dụng</h1>
        <p class="page-subtitle">Xem, quản lý và theo dõi các tin đăng tuyển của doanh nghiệp</p>
      </div>

      <!-- Chặn nút tạo tin nếu chưa verified (BR-JOB-01) -->
      <button
        @click="goToCreateJob"
        :disabled="!isVerified"
        :class="['btn-create-job', { disabled: !isVerified }]"
        :title="!isVerified ? 'Tài khoản cần được xác minh để tạo tin' : 'Tạo tin mới'"
      >
        + Tạo tin mới
      </button>
    </div>

    <!-- Cảnh báo nếu chưa verified -->
    <div v-if="!isVerified" class="unverified-alert">
      ⚠️ <strong>Lưu ý:</strong> Tài khoản của bạn đang ở trạng thái <em>Chưa xác minh</em>. Bạn cần được Admin phê duyệt trước khi có thể đăng tin tuyển dụng mới.
    </div>

    <!-- Bộ lọc trạng thái -->
    <div class="filter-card">
      <div class="filter-group">
        <label>Trạng thái duyệt:</label>
        <select v-model="filters.review_status" @change="loadJobs" class="select-filter">
          <option value="">Tất cả</option>
          <option value="pending">Chờ duyệt (pending)</option>
          <option value="approved">Đã duyệt (approved)</option>
          <option value="rejected">Bị từ chối (rejected)</option>
        </select>
      </div>

      <div class="filter-group">
        <label>Trạng thái hiển thị:</label>
        <select v-model="filters.publication_status" @change="loadJobs" class="select-filter">
          <option value="">Tất cả</option>
          <option value="not_public">Chưa công khai (not_public)</option>
          <option value="active">Đang hoạt động (active)</option>
          <option value="closed">Đã đóng (closed)</option>
          <option value="hidden">Bị ẩn bởi Admin (hidden)</option>
        </select>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="state-panel loading">Đang tải danh sách tin...</div>

    <!-- Error State -->
    <div v-else-if="error" class="state-panel error">{{ error }}</div>

    <!-- Table Danh sách -->
    <div v-else-if="jobs.length" class="table-wrap">
      <table class="jobs-table">
        <thead>
          <tr>
            <th class="col-title">Tiêu đề công việc</th>
            <th>Trạng thái duyệt</th>
            <th>Hiển thị</th>
            <th>Hạn nộp</th>
            <th class="col-actions">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="job in jobs" :key="job.id">
            <td class="col-title">
              <router-link :to="`/company/jobs/${job.id}`" class="job-link">
                {{ job.title }}
              </router-link>
            </td>
            <td>
              <StatusBadge :status="job.review_status" domain="review" />
            </td>
            <td>
              <StatusBadge :status="job.publication_status" domain="publication" />
            </td>
            <td class="text-muted text-sm">
              {{ formatDate(job.expires_at) }}
            </td>
            <td class="col-actions">
              <div class="action-buttons">
                <router-link :to="`/company/jobs/${job.id}`" class="btn-action view">
                  Chi tiết
                </router-link>
                <router-link :to="`/company/jobs/${job.id}/edit`" class="btn-action edit">
                  Sửa
                </router-link>
                <router-link :to="`/company/jobs/${job.id}/applications`" class="btn-action apps">
                  Ứng viên
                </router-link>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty State -->
    <EmptyState v-else text="Không tìm thấy tin tuyển dụng nào phù hợp với bộ lọc." />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { jobApi } from '@/services/jobApi';
import { useAuthStore } from '@/stores/authStore';
import StatusBadge from '@/components/common/StatusBadge.vue';
import EmptyState from '@/components/common/EmptyState.vue';

const router = useRouter();
const authStore = useAuthStore();

const jobs = ref([]);
const loading = ref(true);
const error = ref('');
const filters = ref({ review_status: '', publication_status: '' });

// Kiểm tra quyền theo auth context
const isVerified = computed(() => {
  return authStore.user?.company?.verification_status === 'verified';
});

const loadJobs = async () => {
  loading.value = true;
  error.value = '';
  try {
    const cleanFilters = {};
    if (filters.value.review_status) cleanFilters.review_status = filters.value.review_status;
    if (filters.value.publication_status) cleanFilters.publication_status = filters.value.publication_status;

    const res = await jobApi.indexOwn(cleanFilters);
    jobs.value = res.data?.items || res.data || [];
  } catch (err) {
    error.value = err.message || 'Không thể tải danh sách tin tuyển dụng.';
  } finally {
    loading.value = false;
  }
};

const goToCreateJob = () => {
  if (isVerified.value) {
    router.push('/company/jobs/new');
  }
};

const formatDate = (dateString) => {
  return dateString ? new Date(dateString).toLocaleDateString('vi-VN') : 'Không thời hạn';
};

onMounted(loadJobs);
</script>

<style scoped>
.company-jobs-page {
  max-width: 1100px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
}

.page-subtitle {
  font-size: 0.875rem;
  color: #64748b;
  margin-top: 0.25rem;
}

.btn-create-job {
  background-color: #0284c7;
  color: #ffffff;
  padding: 0.6rem 1.25rem;
  border-radius: 0.375rem;
  border: none;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  transition: background-color 0.15s;
}

.btn-create-job:hover:not(.disabled) {
  background-color: #0369a1;
}

.btn-create-job.disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background-color: #94a3b8;
}

.unverified-alert {
  background-color: #fffbeb;
  border: 1px solid #fde68a;
  color: #92400e;
  padding: 0.75rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  margin-bottom: 1rem;
}

.filter-card {
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1rem 1.25rem;
  display: flex;
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
  padding: 0.4rem 0.6rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  background-color: #ffffff;
  outline: none;
}

.table-wrap {
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.jobs-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.jobs-table th {
  background-color: #f8fafc;
  padding: 0.75rem 1rem;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #475569;
  border-bottom: 1px solid #e2e8f0;
}

.jobs-table td {
  padding: 0.875rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.875rem;
  vertical-align: middle;
}

.col-title {
  max-width: 320px;
}

.job-link {
  color: #0f172a;
  text-decoration: none;
  font-weight: 600;
}

.job-link:hover {
  color: #0284c7;
  text-decoration: underline;
}

.col-actions {
  text-align: right;
}

.action-buttons {
  display: flex;
  justify-content: flex-end;
  gap: 0.35rem;
}

.btn-action {
  padding: 0.25rem 0.55rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
  text-decoration: none;
  transition: background-color 0.15s;
}

.btn-action.view {
  color: #0284c7;
  background-color: #e0f2fe;
}

.btn-action.edit {
  color: #166534;
  background-color: #dcfce7;
}

.btn-action.apps {
  color: #9333ea;
  background-color: #f3e8ff;
}

.text-muted {
  color: #64748b;
}

.text-sm {
  font-size: 0.8125rem;
}

.state-panel {
  padding: 2.5rem;
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
  border: 1px solid #fecaca;
}
</style>
