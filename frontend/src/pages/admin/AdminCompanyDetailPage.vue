<template>
  <div class="admin-company-detail-page">
    <div class="page-header">
      <router-link to="/admin/companies" class="back-link">← Quay lại danh sách</router-link>
      <h1 class="page-title">Chi tiết Hồ sơ Doanh nghiệp</h1>
    </div>

    <div v-if="loading" class="state-panel loading">Đang tải thông tin doanh nghiệp...</div>
    <div v-else-if="error" class="state-panel error">{{ error }}</div>

    <div v-else-if="company" class="detail-grid">
      <!-- Cột trái: Thông tin doanh nghiệp -->
      <div class="main-column">
        <div class="card">
          <div class="company-head">
            <h2 class="company-name">{{ company.company_name }}</h2>
            <StatusBadge :status="company.verification_status" domain="verification" />
          </div>

          <ul class="meta-list">
            <li>
              <span class="meta-label">Người đại diện (User):</span>
              <span class="meta-val">{{ company.user?.name || '—' }} ({{ company.user?.email || '—' }})</span>
            </li>
            <li>
              <span class="meta-label">Website công ty:</span>
              <span class="meta-val">
                <a v-if="company.website_url" :href="company.website_url" target="_blank" class="link-site">
                  {{ company.website_url }} ↗
                </a>
                <span v-else>Chưa cung cấp</span>
              </span>
            </li>
            <li>
              <span class="meta-label">Ngày tạo hồ sơ:</span>
              <span class="meta-val">{{ formatDate(company.created_at) }}</span>
            </li>
            <li v-if="company.verified_at">
              <span class="meta-label">Thời điểm xác minh:</span>
              <span class="meta-val">{{ formatDate(company.verified_at) }}</span>
            </li>
          </ul>

          <div class="desc-section">
            <h4 class="desc-title">Mô tả giới thiệu công ty:</h4>
            <p class="desc-content">{{ company.description || 'Chưa có thông tin mô tả.' }}</p>
          </div>
        </div>
      </div>

      <!-- Cột phải: Form xét duyệt (API-ADM-03) -->
      <div class="side-column">
        <VerificationReview
          :company="company"
          :submitting="submitting"
          @verify="handleVerify"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { adminApi } from '@/services/adminApi';
import StatusBadge from '@/components/common/StatusBadge.vue';
import VerificationReview from '@/components/admin/VerificationReview.vue';

const route = useRoute();
const router = useRouter();
const companyId = route.params.id;

const company = ref(null);
const loading = ref(true);
const submitting = ref(false);
const error = ref('');

const loadCompany = async () => {
  loading.value = true;
  error.value = '';
  try {
    const res = await adminApi.getCompanyDetail(companyId);
    company.value = res.data?.company || res.data;
  } catch (err) {
    error.value = err.message || 'Không thể tải chi tiết doanh nghiệp.';
  } finally {
    loading.value = false;
  }
};

const handleVerify = async (payload) => {
  submitting.value = true;
  try {
    await adminApi.verifyCompany(companyId, payload);
    alert('Đã cập nhật trạng thái xác minh doanh nghiệp thành công!');
    router.push('/admin/companies');
  } catch (err) {
    alert(err.message || 'Cập nhật xác minh thất bại.');
  } finally {
    submitting.value = false;
  }
};

const formatDate = (ds) => {
  return ds ? new Date(ds).toLocaleString('vi-VN') : '—';
};

onMounted(loadCompany);
</script>

<style scoped>
.admin-company-detail-page {
  max-width: 1050px;
}

.page-header {
  margin-bottom: 1.5rem;
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
  font-weight: 800;
  color: #0f172a;
}

.detail-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 1.5rem;
}

.card {
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.company-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #f1f5f9;
}

.company-name {
  font-size: 1.35rem;
  font-weight: 800;
  color: #0f172a;
}

.meta-list {
  list-style: none;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  margin-bottom: 1.5rem;
}

.meta-list li {
  display: flex;
  font-size: 0.875rem;
}

.meta-label {
  width: 180px;
  color: #64748b;
  flex-shrink: 0;
}

.meta-val {
  font-weight: 600;
  color: #1e293b;
}

.link-site {
  color: #0284c7;
  text-decoration: none;
}

.desc-section {
  border-top: 1px dashed #e2e8f0;
  padding-top: 1rem;
}

.desc-title {
  font-size: 0.875rem;
  font-weight: 700;
  color: #334155;
  margin-bottom: 0.5rem;
}

.desc-content {
  font-size: 0.875rem;
  color: #475569;
  line-height: 1.6;
  white-space: pre-wrap;
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
