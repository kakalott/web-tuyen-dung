<template>
  <div class="admin-dashboard-page">
    <div class="page-header">
      <h1 class="page-title">Dashboard Quản trị Hệ thống</h1>
      <p class="page-subtitle">Tổng quan các chỉ số vận hành nền tảng theo thời gian thực (API-ADM-08)</p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="state-panel loading">Đang tải số liệu thống kê...</div>

    <!-- Error State -->
    <ErrorBanner v-else-if="error" :message="error" />

    <!-- 4 Dashboard Cards -->
    <div v-else class="dashboard-cards-grid">
      <!-- Card 1: Tổng người dùng -->
      <div class="stat-card blue">
        <div class="card-icon">👥</div>
        <div class="card-info">
          <span class="card-label">Tổng người dùng hệ thống</span>
          <span class="card-value">{{ stats.users_total }}</span>
          <span class="card-sub">Bao gồm Candidate, Company, Admin</span>
        </div>
      </div>

      <!-- Card 2: Doanh nghiệp chờ xác minh -->
      <div
        class="stat-card yellow clickable"
        @click="$router.push('/admin/companies')"
        title="Nhấn để xem danh sách chờ xác minh"
      >
        <div class="card-icon">🏢</div>
        <div class="card-info">
          <span class="card-label">Doanh nghiệp chờ xác minh</span>
          <span class="card-value highlight-yellow">{{ stats.companies_unverified }}</span>
          <span class="card-sub link-sub">Xem danh sách duyệt →</span>
        </div>
      </div>

      <!-- Card 3: Tin tuyển dụng chờ duyệt -->
      <div
        class="stat-card orange clickable"
        @click="$router.push('/admin/jobs')"
        title="Nhấn để xem tin tuyển dụng chờ duyệt"
      >
        <div class="card-icon">📑</div>
        <div class="card-info">
          <span class="card-label">Tin tuyển dụng chờ duyệt</span>
          <span class="card-value highlight-orange">{{ stats.jobs_pending }}</span>
          <span class="card-sub link-sub">Xem danh sách kiểm duyệt →</span>
        </div>
      </div>

      <!-- Card 4: Tổng đơn ứng tuyển -->
      <div class="stat-card green">
        <div class="card-icon">📨</div>
        <div class="card-info">
          <span class="card-label">Tổng hồ sơ ứng tuyển</span>
          <span class="card-value highlight-green">{{ stats.applications_total }}</span>
          <span class="card-sub">Tổng số lượt ứng tuyển trên toàn sàn</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { adminApi } from '@/services/adminApi';
import ErrorBanner from '@/components/common/ErrorBanner.vue';

const stats = ref({
  users_total: 0,
  companies_unverified: 0,
  jobs_pending: 0,
  applications_total: 0,
});
const loading = ref(true);
const error = ref('');

onMounted(async () => {
  try {
    const res = await adminApi.getDashboard();
    stats.value = res.data || {};
  } catch (err) {
    error.value = err.message || 'Không thể tải thống kê Dashboard.';
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.admin-dashboard-page {
  max-width: 1100px;
}

.page-header {
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

.dashboard-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1.25rem;
}

.stat-card {
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1.5rem;
  display: flex;
  gap: 1.25rem;
  align-items: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  border-left: 5px solid;
  transition: transform 0.15s, box-shadow 0.15s;
}

.stat-card.clickable {
  cursor: pointer;
}

.stat-card.clickable:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.stat-card.blue {
  border-left-color: #2563eb;
}

.stat-card.yellow {
  border-left-color: #f59e0b;
}

.stat-card.orange {
  border-left-color: #ea580c;
}

.stat-card.green {
  border-left-color: #16a34a;
}

.card-icon {
  font-size: 2.25rem;
  line-height: 1;
}

.card-info {
  display: flex;
  flex-direction: column;
}

.card-label {
  font-size: 0.8125rem;
  font-weight: 700;
  color: #64748b;
}

.card-value {
  font-size: 2rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.2;
  margin: 0.25rem 0;
}

.highlight-yellow {
  color: #d97706;
}

.highlight-orange {
  color: #ea580c;
}

.highlight-green {
  color: #16a34a;
}

.card-sub {
  font-size: 0.75rem;
  color: #94a3b8;
}

.link-sub {
  color: #0284c7;
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
</style>
