<template>
  <div class="job-public-detail-page">
    <div v-if="loading" class="state-panel loading">Đang tải thông tin việc làm...</div>
    <div v-else-if="error" class="state-panel error">{{ error }}</div>

    <div v-else-if="job" class="job-container">
      <!-- Header Banner -->
      <div class="job-header-card">
        <div class="header-main">
          <router-link to="/jobs" class="back-link">← Quay lại danh sách việc làm</router-link>
          <h1 class="job-title">{{ job.title }}</h1>
          <p class="company-sub">
            🏢 <strong class="company-name">{{ job.company?.company_name || 'Doanh nghiệp' }}</strong>
            <span v-if="job.company?.website_url" class="website-link">
              (<a :href="job.company.website_url" target="_blank">{{ job.company.website_url }} ↗</a>)
            </span>
          </p>

          <div class="tags-bar">
            <span v-if="job.job_group?.name" class="tag">{{ job.job_group.name }}</span>
            <span v-if="job.employment_type?.name" class="tag">{{ job.employment_type.name }}</span>
            <span v-if="job.work_mode?.name" class="tag highlight">{{ job.work_mode.name }}</span>
            <span v-if="job.seniority_level?.name" class="tag">{{ job.seniority_level.name }}</span>
            <span class="expiry-tag">Hạn nộp: {{ formatDate(job.expires_at) }}</span>
          </div>
        </div>

        <div class="apply-action">
          <button
            @click="openApplyModal"
            class="btn-apply-now"
          >
            🚀 Nộp hồ sơ ứng tuyển
          </button>
        </div>
      </div>

      <!-- Main details -->
      <div class="job-body-grid">
        <div class="left-col">
          <section class="card">
            <h3 class="section-title">Mô tả công việc</h3>
            <p class="pre-text">{{ job.description }}</p>
          </section>

          <section class="card">
            <h3 class="section-title">Yêu cầu ứng viên</h3>
            <p class="pre-text">{{ job.requirements_text }}</p>
          </section>
        </div>

        <div class="right-col">
          <section class="card">
            <h3 class="section-title">Kỹ năng yêu cầu</h3>
            <div class="skills-block">
              <div class="skill-sub">
                <span class="sub-label req">Bắt buộc:</span>
                <div class="pills">
                  <span v-for="s in requiredSkills" :key="s.id" class="skill-pill req">
                    {{ s.name }}
                  </span>
                  <span v-if="!requiredSkills.length" class="empty-txt">Không có</span>
                </div>
              </div>

              <div class="skill-sub">
                <span class="sub-label pref">Ưu tiên:</span>
                <div class="pills">
                  <span v-for="s in preferredSkills" :key="s.id" class="skill-pill pref">
                    {{ s.name }}
                  </span>
                  <span v-if="!preferredSkills.length" class="empty-txt">Không có</span>
                </div>
              </div>
            </div>
          </section>

          <section v-if="job.company?.description" class="card">
            <h3 class="section-title">Về công ty</h3>
            <p class="pre-text">{{ job.company.description }}</p>
          </section>
        </div>
      </div>
    </div>

    <!-- Modal Nộp đơn Ứng tuyển (API-APP-01) -->
    <div v-if="isApplyModalOpen" class="modal-overlay" @click.self="isApplyModalOpen = false">
      <div class="modal-card">
        <h3 class="modal-title">Nộp hồ sơ: {{ job?.title }}</h3>

        <!-- Nếu chưa login hoặc không phải candidate -->
        <div v-if="!authStore.isAuthenticated" class="auth-notice">
          <p>Bạn cần đăng nhập bằng tài khoản <strong>Ứng viên (Candidate)</strong> để nộp hồ sơ.</p>
          <div class="modal-actions">
            <router-link :to="`/login?redirect=/jobs/${jobId}`" class="btn-primary">Đăng nhập ngay</router-link>
            <button @click="isApplyModalOpen = false" class="btn-cancel">Đóng</button>
          </div>
        </div>

        <div v-else-if="authStore.role !== 'candidate'" class="auth-notice">
          <p class="text-danger">Tài khoản hiện tại có vai trò <strong>{{ authStore.role }}</strong>, không thể nộp hồ sơ ứng tuyển.</p>
          <button @click="isApplyModalOpen = false" class="btn-cancel">Đóng</button>
        </div>

        <!-- Form chọn CV nộp -->
        <form v-else @submit.prevent="submitApplication" class="apply-form">
          <div v-if="applyError" class="modal-error">{{ applyError }}</div>
          <div v-if="applySuccess" class="modal-success">{{ applySuccess }}</div>

          <div class="form-group">
            <label>Chọn CV đã xác nhận của bạn <span class="required">*</span>:</label>
            <select v-model="selectedCvId" class="select-cv" required>
              <option disabled value="">-- Chọn CV đã xác nhận trích xuất --</option>
              <option v-for="cv in confirmedCvs" :key="cv.id" :value="cv.id">
                {{ cv.original_filename }} ({{ formatDate(cv.uploaded_at) }})
              </option>
            </select>
          </div>

          <div v-if="!confirmedCvs.length" class="no-cv-alert">
            <p class="no-cv-msg">Bạn chưa có CV nào ở trạng thái đã xác nhận.</p>
            <router-link to="/candidate/cvs" class="btn-cta-cv">
              📄 Tải lên hoặc xác nhận CV ngay →
            </router-link>
          </div>

          <div class="modal-actions">
            <button type="button" @click="isApplyModalOpen = false" class="btn-cancel">Hủy</button>
            <button
              type="submit"
              :disabled="submitting || !selectedCvId || !confirmedCvs.length"
              class="btn-primary"
            >
              {{ submitting ? 'Đang gửi...' : 'Xác nhận Nộp đơn' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiClient } from '@/services/apiClient';
import { applicationApi } from '@/services/applicationApi';
import { cvApi } from '@/services/cvApi';
import { useAuthStore } from '@/stores/authStore';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const jobId = route.params.jobId;

const job = ref(null);
const loading = ref(true);
const error = ref('');

const isApplyModalOpen = ref(false);
const confirmedCvs = ref([]);
const selectedCvId = ref('');
const submitting = ref(false);
const applyError = ref('');
const applySuccess = ref('');

const requiredSkills = computed(() => {
  return (job.value?.skills || []).filter((s) => s.pivot?.requirement_type === 'required');
});

const preferredSkills = computed(() => {
  return (job.value?.skills || []).filter((s) => s.pivot?.requirement_type === 'preferred');
});

const formatDate = (ds) => {
  return ds ? new Date(ds).toLocaleDateString('vi-VN') : 'Không giới hạn';
};

const loadJobDetail = async () => {
  loading.value = true;
  error.value = '';
  try {
    const res = await apiClient.request('GET', `/jobs/${jobId}`);
    job.value = res.data?.job || res.data;
  } catch (err) {
    error.value = err.message || 'Không thể tải chi tiết công việc.';
  } finally {
    loading.value = false;
  }
};

const openApplyModal = async () => {
  applyError.value = '';
  applySuccess.value = '';
  isApplyModalOpen.value = true;

  if (authStore.isAuthenticated && authStore.role === 'candidate') {
    try {
      const res = await cvApi.getCvs();
      // Lọc các CV processed
      confirmedCvs.value = (res.data || []).filter((c) => c.parse_status === 'processed');
      if (confirmedCvs.value.length > 0) {
        selectedCvId.value = confirmedCvs.value[0].id;
      }
    } catch {
      confirmedCvs.value = [];
    }
  }
};

const submitApplication = async () => {
  if (!selectedCvId.value) return;
  submitting.value = true;
  applyError.value = '';
  applySuccess.value = '';
  try {
    await applicationApi.apply(jobId, {
      cv_id: selectedCvId.value,
    });
    applySuccess.value = 'Ứng tuyển thành công! Nhà tuyển dụng sẽ xem xét hồ sơ của bạn.';
    setTimeout(() => {
      isApplyModalOpen.value = false;
      router.push('/candidate/applications');
    }, 1500);
  } catch (err) {
    applyError.value = err.message || 'Nộp đơn không thành công.';
  } finally {
    submitting.value = false;
  }
};

onMounted(loadJobDetail);
</script>

<style scoped>
.job-public-detail-page {
  max-width: 1050px;
  margin: 0 auto;
}

.job-header-card {
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1.75rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.back-link {
  color: #0284c7;
  text-decoration: none;
  font-size: 0.8125rem;
  font-weight: 500;
  display: inline-block;
  margin-bottom: 0.5rem;
}

.job-title {
  font-size: 1.65rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 0.35rem;
}

.company-sub {
  font-size: 0.95rem;
  color: #475569;
  margin-bottom: 0.85rem;
}

.company-name {
  color: #1e293b;
}

.website-link a {
  color: #0284c7;
  text-decoration: none;
}

.tags-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
}

.tag {
  background-color: #f1f5f9;
  color: #475569;
  padding: 0.25rem 0.6rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.tag.highlight {
  background-color: #e0f2fe;
  color: #0369a1;
}

.expiry-tag {
  font-size: 0.75rem;
  color: #94a3b8;
  margin-left: 0.5rem;
}

.btn-apply-now {
  background-color: #0284c7;
  color: #ffffff;
  padding: 0.85rem 1.75rem;
  border-radius: 0.375rem;
  border: none;
  font-weight: 700;
  font-size: 1rem;
  cursor: pointer;
  box-shadow: 0 4px 6px -1px rgba(2, 132, 199, 0.3);
  transition: background-color 0.15s;
}

.btn-apply-now:hover {
  background-color: #0369a1;
}

.job-body-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 1.5rem;
}

.card {
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.section-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 0.85rem;
  padding-bottom: 0.4rem;
  border-bottom: 1px solid #f1f5f9;
}

.pre-text {
  font-size: 0.875rem;
  color: #334155;
  line-height: 1.6;
  white-space: pre-wrap;
}

.skills-block {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.sub-label {
  display: block;
  font-size: 0.75rem;
  font-weight: 700;
  margin-bottom: 0.3rem;
}

.sub-label.req {
  color: #dc2626;
}

.sub-label.pref {
  color: #0284c7;
}

.pills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.skill-pill {
  padding: 0.2rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.skill-pill.req {
  background-color: #fee2e2;
  color: #991b1b;
}

.skill-pill.pref {
  background-color: #e0f2fe;
  color: #0369a1;
}

.empty-txt {
  font-size: 0.75rem;
  color: #94a3b8;
  font-style: italic;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
}

.modal-card {
  background-color: #ffffff;
  border-radius: 0.5rem;
  padding: 1.5rem;
  max-width: 480px;
  width: 90%;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.modal-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 1rem;
}

.auth-notice {
  text-align: center;
  padding: 1rem 0;
  font-size: 0.875rem;
  color: #334155;
}

.apply-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.form-group label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #334155;
}

.required {
  color: #dc2626;
}

.select-cv {
  padding: 0.55rem 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  outline: none;
}

.select-cv:focus {
  border-color: #0284c7;
}

.no-cv-alert {
  background-color: #fffbeb;
  border: 1px solid #fde68a;
  color: #92400e;
  padding: 0.85rem 1rem;
  border-radius: 0.5rem;
  font-size: 0.875rem;
}

.no-cv-msg {
  margin: 0 0 0.5rem 0;
  font-weight: 500;
}

.btn-cta-cv {
  display: inline-flex;
  align-items: center;
  background-color: #2563eb;
  color: #ffffff !important;
  font-weight: 600;
  font-size: 0.8125rem;
  padding: 0.45rem 0.85rem;
  border-radius: 0.375rem;
  text-decoration: none;
  transition: background-color 0.15s;
}

.btn-cta-cv:hover {
  background-color: #1d4ed8;
}

.link-cvs {
  color: #0284c7;
  font-weight: 700;
  display: block;
  margin-top: 0.25rem;
}

.modal-error {
  background-color: #fef2f2;
  color: #b91c1c;
  padding: 0.5rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
}

.modal-success {
  background-color: #f0fdf4;
  color: #166534;
  padding: 0.5rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 0.5rem;
}

.btn-primary {
  background-color: #0284c7;
  color: #ffffff;
  padding: 0.55rem 1.25rem;
  border-radius: 0.375rem;
  border: none;
  font-weight: 700;
  font-size: 0.875rem;
  cursor: pointer;
  text-decoration: none;
}

.btn-cancel {
  background-color: #e2e8f0;
  color: #475569;
  border: none;
  padding: 0.55rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  cursor: pointer;
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
