<template>
  <div class="company-profile-page">
    <h1 class="page-title">Hồ sơ Doanh nghiệp</h1>

    <div v-if="loading" class="state-panel loading">Đang tải hồ sơ...</div>
    <div v-else-if="error" class="state-panel error">{{ error }}</div>

    <div v-else class="page-content">
      <!-- Banner Cảnh báo Xác minh -->
      <CompanyVerificationBanner
        :status="profile.verification_status"
        :note="profile.verification_note"
      />

      <div class="card">
        <div v-if="successMsg" class="success-banner">{{ successMsg }}</div>

        <form @submit.prevent="handleUpdate" class="profile-form">
          <div class="form-group">
            <label>Tên Công ty <span class="required">*</span></label>
            <input
              type="text"
              v-model="form.company_name"
              required
              class="input-field"
              placeholder="Ví dụ: Công ty TNHH Công nghệ Alpha"
            />
          </div>

          <div class="form-group">
            <label>Website</label>
            <input
              type="url"
              v-model="form.website_url"
              class="input-field"
              placeholder="https://example.com"
            />
          </div>

          <div class="form-group">
            <label>Mô tả Công ty</label>
            <textarea
              v-model="form.description"
              rows="6"
              class="input-field"
              placeholder="Giới thiệu về lĩnh vực hoạt động, văn hóa công ty, chế độ đãi ngộ..."
            ></textarea>
          </div>

          <!-- Trạng thái xác minh: KHÔNG được phép cho user tự sửa trong form -->
          <div class="form-group status-readonly">
            <label>Trạng thái xác minh hiện tại:</label>
            <div class="status-display">
              <StatusBadge :status="profile.verification_status || 'unverified'" domain="verification" />
              <span class="status-hint">(Chỉ Admin hệ thống mới có quyền thay đổi trạng thái này)</span>
            </div>
          </div>

          <button
            type="submit"
            class="btn-primary"
            :disabled="submitting"
          >
            {{ submitting ? 'Đang lưu...' : 'Lưu thay đổi' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { companyApi } from '@/services/companyApi';
import CompanyVerificationBanner from '@/components/company/CompanyVerificationBanner.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';

const profile = ref({});
const form = ref({ company_name: '', website_url: '', description: '' });
const loading = ref(true);
const submitting = ref(false);
const error = ref('');
const successMsg = ref('');

onMounted(async () => {
  try {
    const res = await companyApi.getProfile();
    profile.value = res.data?.company || res.data || {};
    form.value = {
      company_name: profile.value.company_name || '',
      website_url: profile.value.website_url || '',
      description: profile.value.description || '',
    };
  } catch (err) {
    error.value = err.message || 'Lỗi tải hồ sơ doanh nghiệp.';
  } finally {
    loading.value = false;
  }
});

const handleUpdate = async () => {
  submitting.value = true;
  successMsg.value = '';
  try {
    const res = await companyApi.updateProfile(form.value);
    const updated = res.data?.company || res.data || {};
    profile.value.verification_status = updated.verification_status;
    successMsg.value = 'Cập nhật hồ sơ doanh nghiệp thành công!';
    setTimeout(() => (successMsg.value = ''), 3500);
  } catch (err) {
    alert(err.message || 'Cập nhật thất bại. Vui lòng kiểm tra lại.');
  } finally {
    submitting.value = false;
  }
};
</script>

<style scoped>
.company-profile-page {
  max-width: 800px;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 1.25rem;
}

.card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.profile-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.form-group label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #334155;
}

.required {
  color: #dc2626;
}

.input-field {
  padding: 0.625rem 0.875rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  outline: none;
}

.input-field:focus {
  border-color: #0284c7;
  box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.1);
}

.status-readonly {
  background-color: #f8fafc;
  padding: 0.75rem;
  border-radius: 0.375rem;
  border: 1px dashed #cbd5e1;
}

.status-display {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.status-hint {
  font-size: 0.75rem;
  color: #64748b;
  font-style: italic;
}

.btn-primary {
  background-color: #0284c7;
  color: #ffffff;
  padding: 0.65rem 1.5rem;
  border-radius: 0.375rem;
  border: none;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  align-self: flex-start;
  transition: background-color 0.15s;
}

.btn-primary:hover:not(:disabled) {
  background-color: #0369a1;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.success-banner {
  background-color: #dcfce7;
  border: 1px solid #86efac;
  color: #166534;
  padding: 0.75rem 1rem;
  border-radius: 0.375rem;
  margin-bottom: 1.25rem;
  font-size: 0.875rem;
}

.state-panel {
  padding: 2rem;
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
