<template>
  <div class="admin-jobs-page">
    <div class="page-header">
      <div>
        <h1 class="page-title">Kiểm duyệt Tin tuyển dụng</h1>
        <p class="page-subtitle">Danh sách các tin tuyển dụng đang ở trạng thái Chờ duyệt (pending) - API-ADM-04</p>
      </div>
      <button @click="loadPendingJobs" class="btn-refresh">🔄 Làm mới</button>
    </div>

    <div v-if="loading" class="state-panel loading">Đang tải danh sách tin chờ duyệt...</div>
    <div v-else-if="error" class="state-panel error">{{ error }}</div>

    <div v-else-if="jobs.length" class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Tiêu đề công việc</th>
            <th>Doanh nghiệp đăng</th>
            <th>Trạng thái duyệt</th>
            <th>Ngày gửi</th>
            <th class="text-right">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="job in jobs" :key="job.id">
            <td class="font-bold text-dark">
              {{ job.title }}
            </td>
            <td>
              <span class="company-name">{{ job.company?.company_name || 'Doanh nghiệp' }}</span>
            </td>
            <td>
              <StatusBadge :status="job.review_status" domain="review" />
            </td>
            <td class="text-sm text-muted">
              {{ formatDate(job.created_at) }}
            </td>
            <td class="text-right">
              <router-link :to="`/admin/jobs/${job.id}`" class="btn-review">
                Kiểm duyệt nội dung →
              </router-link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <EmptyState v-else text="Hiện tại không có tin tuyển dụng nào đang chờ duyệt." />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { adminApi } from '@/services/adminApi';
import StatusBadge from '@/components/common/StatusBadge.vue';
import EmptyState from '@/components/common/EmptyState.vue';

const jobs = ref([]);
const loading = ref(true);
const error = ref('');

const loadPendingJobs = async () => {
  loading.value = true;
  error.value = '';
  try {
    const res = await adminApi.getPendingJobs();
    jobs.value = res.data?.items || res.data || [];
  } catch (err) {
    error.value = err.message || 'Không thể tải danh sách tin chờ duyệt.';
  } finally {
    loading.value = false;
  }
};

const formatDate = (ds) => {
  return ds ? new Date(ds).toLocaleDateString('vi-VN') : '—';
};

onMounted(loadPendingJobs);
</script>

<style scoped>
.admin-jobs-page {
  max-width: 1050px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: #0f172a;
}

.page-subtitle {
  font-size: 0.875rem;
  color: #64748b;
  margin-top: 0.25rem;
}

.btn-refresh {
  background-color: #f1f5f9;
  border: 1px solid #cbd5e1;
  padding: 0.45rem 0.85rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.table-wrap {
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.data-table th {
  background-color: #f8fafc;
  padding: 0.75rem 1rem;
  font-size: 0.8125rem;
  font-weight: 700;
  color: #475569;
  border-bottom: 1px solid #e2e8f0;
}

.data-table td {
  padding: 0.875rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.875rem;
  vertical-align: middle;
}

.font-bold {
  font-weight: 700;
}

.text-dark {
  color: #0f172a;
}

.company-name {
  color: #334155;
  font-weight: 600;
}

.text-muted {
  color: #64748b;
}

.text-sm {
  font-size: 0.8125rem;
}

.text-right {
  text-align: right;
}

.btn-review {
  background-color: #0284c7;
  color: #ffffff;
  padding: 0.45rem 0.85rem;
  border-radius: 0.375rem;
  text-decoration: none;
  font-size: 0.8125rem;
  font-weight: 600;
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
