<template>
  <div class="admin-companies-page">
    <div class="page-header">
      <div>
        <h1 class="page-title">Duyệt Doanh nghiệp</h1>
        <p class="page-subtitle">Danh sách các doanh nghiệp đang chờ quản trị viên xác minh pháp lý (API-ADM-01)</p>
      </div>
      <button @click="loadCompanies" class="btn-refresh">🔄 Làm mới</button>
    </div>

    <div v-if="loading" class="state-panel loading">Đang tải danh sách công ty...</div>
    <div v-else-if="error" class="state-panel error">{{ error }}</div>

    <div v-else-if="companies.length" class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Tên Doanh nghiệp</th>
            <th>Website</th>
            <th>Trạng thái</th>
            <th>Ngày đăng ký</th>
            <th class="text-right">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in companies" :key="c.id">
            <td class="font-bold text-dark">
              {{ c.company_name }}
            </td>
            <td>
              <a v-if="c.website_url" :href="c.website_url" target="_blank" class="link-site">
                {{ c.website_url }} ↗
              </a>
              <span v-else class="text-muted">—</span>
            </td>
            <td>
              <StatusBadge :status="c.verification_status" domain="verification" />
            </td>
            <td class="text-sm text-muted">
              {{ formatDate(c.created_at) }}
            </td>
            <td class="text-right">
              <router-link :to="`/admin/companies/${c.id}`" class="btn-inspect">
                Kiểm duyệt &amp; Xét duyệt →
              </router-link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <EmptyState v-else text="Hiện tại không có doanh nghiệp nào đang chờ xác minh." />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { adminApi } from '@/services/adminApi';
import StatusBadge from '@/components/common/StatusBadge.vue';
import EmptyState from '@/components/common/EmptyState.vue';

const companies = ref([]);
const loading = ref(true);
const error = ref('');

const loadCompanies = async () => {
  loading.value = true;
  error.value = '';
  try {
    const res = await adminApi.getUnverifiedCompanies();
    companies.value = res.data?.items || res.data || [];
  } catch (err) {
    error.value = err.message || 'Không thể tải danh sách doanh nghiệp.';
  } finally {
    loading.value = false;
  }
};

const formatDate = (ds) => {
  return ds ? new Date(ds).toLocaleDateString('vi-VN') : '—';
};

onMounted(loadCompanies);
</script>

<style scoped>
.admin-companies-page {
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

.link-site {
  color: #0284c7;
  text-decoration: none;
  font-size: 0.8125rem;
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

.btn-inspect {
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
