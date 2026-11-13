<template>
  <div class="job-editor-page">
    <div class="page-header">
      <h1 class="page-title">{{ isEditMode ? 'Chỉnh sửa Tin tuyển dụng' : 'Tạo Tin tuyển dụng mới' }}</h1>
      <router-link to="/company/jobs" class="back-link">← Quay lại danh sách tin</router-link>
    </div>

    <div v-if="loadingJob" class="state-panel loading">Đang tải thông tin tin tuyển dụng...</div>
    <div v-else-if="pageError" class="state-panel error">{{ pageError }}</div>

    <form v-else @submit.prevent="promptWarningBeforeSubmit" class="editor-form">
      <!-- Thông tin chung -->
      <div class="card">
        <h3 class="card-title">1. Thông tin chung</h3>

        <div class="form-group">
          <label>Tiêu đề công việc <span class="required">*</span></label>
          <input
            type="text"
            v-model="form.title"
            required
            class="input-field"
            :class="{ 'input-error': formErrors.title }"
            placeholder="Ví dụ: Senior Backend Laravel Engineer"
          />
          <span v-if="formErrors.title" class="field-error">{{ formErrors.title }}</span>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Nhóm ngành (Job Group)</label>
            <select v-model="form.job_group_id" class="input-field">
              <option :value="null">-- Chọn nhóm ngành --</option>
              <option v-for="jg in lookups.job_groups" :key="jg.id" :value="jg.id">{{ jg.name }}</option>
            </select>
          </div>

          <div class="form-group">
            <label>Hình thức làm việc</label>
            <select v-model="form.employment_type_id" class="input-field">
              <option :value="null">-- Chọn hình thức --</option>
              <option v-for="et in lookups.employment_types" :key="et.id" :value="et.id">{{ et.name }}</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Chế độ làm việc (Work Mode)</label>
            <select v-model="form.work_mode_id" class="input-field">
              <option :value="null">-- Chọn chế độ --</option>
              <option v-for="wm in lookups.work_modes" :key="wm.id" :value="wm.id">{{ wm.name }}</option>
            </select>
          </div>

          <div class="form-group">
            <label>Cấp bậc (Seniority Level)</label>
            <select v-model="form.seniority_level_id" class="input-field">
              <option :value="null">-- Chọn cấp bậc --</option>
              <option v-for="sl in lookups.seniority_levels" :key="sl.id" :value="sl.id">{{ sl.name }}</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Hạn nộp hồ sơ</label>
          <input type="date" v-model="form.expires_at" class="input-field" />
        </div>
      </div>

      <!-- Nội dung chi tiết -->
      <div class="card">
        <h3 class="card-title">2. Mô tả & Yêu cầu công việc</h3>

        <div class="form-group">
          <label>Mô tả công việc <span class="required">*</span></label>
          <textarea
            v-model="form.description"
            rows="6"
            required
            class="input-field"
            :class="{ 'input-error': formErrors.description }"
            placeholder="Mô tả trách nhiệm, nhiệm vụ hàng ngày..."
          ></textarea>
          <span v-if="formErrors.description" class="field-error">{{ formErrors.description }}</span>
        </div>

        <div class="form-group">
          <label>Yêu cầu ứng viên <span class="required">*</span></label>
          <textarea
            v-model="form.requirements_text"
            rows="6"
            required
            class="input-field"
            :class="{ 'input-error': formErrors.requirements_text }"
            placeholder="Yêu cầu về kinh nghiệm, kỹ năng, thái độ..."
          ></textarea>
          <span v-if="formErrors.requirements_text" class="field-error">{{ formErrors.requirements_text }}</span>
        </div>
      </div>

      <!-- Kỹ năng -->
      <div class="card">
        <h3 class="card-title">3. Kỹ năng yêu cầu & ưu tiên</h3>
        <p class="section-hint">AI Matching sẽ sử dụng các kỹ năng này để chấm điểm phù hợp của ứng viên.</p>

        <div class="skills-grid">
          <div class="skill-column">
            <label class="skill-label required-label">Kỹ năng BẮT BUỘC (Required)</label>
            <SkillSelector v-model:selected="form.required_skill_ids" />
          </div>

          <div class="skill-column">
            <label class="skill-label preferred-label">Kỹ năng ƯU TIÊN (Preferred)</label>
            <SkillSelector v-model:selected="form.preferred_skill_ids" />
          </div>
        </div>

        <!-- Validate trùng lặp kỹ năng (Quy tắc TC-JOB-02) -->
        <p v-if="skillOverlapError" class="overlap-error">
          ⚠️ Kỹ năng ưu tiên không được trùng với kỹ năng bắt buộc.
        </p>
      </div>

      <!-- Submit button -->
      <div class="form-footer">
        <router-link to="/company/jobs" class="btn-cancel">Hủy bỏ</router-link>
        <button
          type="submit"
          class="btn-primary"
          :disabled="submitting || skillOverlapError"
        >
          {{ submitting ? 'Đang lưu tin...' : isEditMode ? 'Lưu thay đổi' : 'Đăng tin tuyển dụng' }}
        </button>
      </div>
    </form>

    <!-- Modal Cảnh báo khi sửa tin đã approved (BR-JOB-04) -->
    <div v-if="showWarningModal" class="modal-overlay">
      <div class="modal-card">
        <h3 class="modal-title">Cảnh báo phê duyệt lại</h3>
        <p class="modal-body">
          Tin này hiện đang ở trạng thái <strong>Đã duyệt (approved)</strong>. Nếu bạn cập nhật các thông tin chính (tiêu đề, mô tả, yêu cầu, kỹ năng), tin sẽ <strong>tự động chuyển về trạng thái Chờ duyệt (pending)</strong> và tạm ngừng hiển thị công khai cho đến khi Admin kiểm duyệt lại.
        </p>
        <div class="modal-actions">
          <button @click="showWarningModal = false" class="btn-cancel">Hủy</button>
          <button @click="submitJobForm" class="btn-confirm-danger">Vẫn tiếp tục lưu</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { jobApi } from '@/services/jobApi';
import { apiClient } from '@/services/apiClient';
import { useFormErrors } from '@/composables/useFormErrors';
import SkillSelector from '@/components/common/SkillSelector.vue';

const route = useRoute();
const router = useRouter();
const { errors: formErrors, fromApi422, clearErrors } = useFormErrors();

const isEditMode = computed(() => !!route.params.jobId);
const originalStatus = ref('');
const loadingJob = ref(false);
const submitting = ref(false);
const pageError = ref('');
const showWarningModal = ref(false);

const lookups = ref({
  job_groups: [],
  employment_types: [],
  work_modes: [],
  seniority_levels: [],
});

const form = ref({
  title: '',
  description: '',
  requirements_text: '',
  job_group_id: null,
  employment_type_id: null,
  work_mode_id: null,
  seniority_level_id: null,
  expires_at: '',
  required_skill_ids: [],
  preferred_skill_ids: [],
});

// Validate trùng lặp kỹ năng (Quy tắc TC-JOB-02)
const skillOverlapError = computed(() => {
  return form.value.required_skill_ids.some((id) =>
    form.value.preferred_skill_ids.includes(id)
  );
});

onMounted(async () => {
  // Tải danh mục lookup (API-CAT-06)
  try {
    const lRes = await apiClient.request('GET', '/catalog/lookups');
    lookups.value = lRes.data || {};
  } catch {
    // fallback
  }

  // Nếu là edit mode, tải chi tiết tin
  if (isEditMode.value) {
    loadingJob.value = true;
    try {
      const res = await jobApi.showOwn(route.params.jobId);
      const j = res.data?.job || res.data;
      originalStatus.value = j.review_status;

      // Map skills sang 2 mảng ID
      const reqIds = [];
      const prefIds = [];
      (j.skills || []).forEach((s) => {
        if (s.pivot?.requirement_type === 'required') {
          reqIds.push(s.id);
        } else {
          prefIds.push(s.id);
        }
      });

      form.value = {
        title: j.title || '',
        description: j.description || '',
        requirements_text: j.requirements_text || '',
        job_group_id: j.job_group_id || null,
        employment_type_id: j.employment_type_id || null,
        work_mode_id: j.work_mode_id || null,
        seniority_level_id: j.seniority_level_id || null,
        expires_at: j.expires_at ? j.expires_at.substring(0, 10) : '',
        required_skill_ids: reqIds,
        preferred_skill_ids: prefIds,
      };
    } catch (err) {
      pageError.value = err.message || 'Không thể tải tin tuyển dụng.';
    } finally {
      loadingJob.value = false;
    }
  }
});

const promptWarningBeforeSubmit = () => {
  // BR-JOB-04: Sửa tin đã approved thì hiển thị cảnh báo chuyển về pending
  if (isEditMode.value && originalStatus.value === 'approved') {
    showWarningModal.value = true;
  } else {
    submitJobForm();
  }
};

const submitJobForm = async () => {
  showWarningModal.value = false;
  clearErrors();
  submitting.value = true;
  try {
    const payload = {
      ...form.value,
      required_skill_ids: form.value.required_skill_ids,
      preferred_skill_ids: form.value.preferred_skill_ids,
    };
    if (!payload.expires_at) delete payload.expires_at;

    if (isEditMode.value) {
      await jobApi.update(route.params.jobId, payload);
    } else {
      await jobApi.store(payload);
    }
    router.push('/company/jobs');
  } catch (err) {
    if (err.status === 422 && err.fields) {
      fromApi422(err.fields);
    } else {
      alert('Lưu tin thất bại: ' + (err.message || 'Dữ liệu không hợp lệ.'));
    }
  } finally {
    submitting.value = false;
  }
};
</script>

<style scoped>
.job-editor-page {
  max-width: 900px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
}

.back-link {
  color: #0284c7;
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
}

.card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.card-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid #f1f5f9;
}

.section-hint {
  font-size: 0.8125rem;
  color: #64748b;
  margin-bottom: 1rem;
}

.editor-form {
  display: flex;
  flex-direction: column;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-bottom: 1rem;
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
  background-color: #ffffff;
  outline: none;
}

.input-field:focus {
  border-color: #0284c7;
  box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.1);
}

.input-field.input-error {
  border-color: #dc2626 !important;
}

.field-error {
  color: #dc2626;
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.skills-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

.skill-column {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.skill-label {
  font-size: 0.875rem;
  font-weight: 700;
}

.skill-label.required-label {
  color: #dc2626;
}

.skill-label.preferred-label {
  color: #0284c7;
}

.overlap-error {
  margin-top: 1rem;
  color: #dc2626;
  font-size: 0.85rem;
  font-weight: 600;
  background-color: #fee2e2;
  padding: 0.5rem 0.75rem;
  border-radius: 0.375rem;
}

.form-footer {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1rem;
}

.btn-primary {
  background-color: #0284c7;
  color: #ffffff;
  padding: 0.75rem 1.75rem;
  border-radius: 0.375rem;
  border: none;
  font-weight: 700;
  font-size: 0.95rem;
  cursor: pointer;
  transition: background-color 0.15s;
}

.btn-primary:hover:not(:disabled) {
  background-color: #0369a1;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-cancel {
  background-color: #e2e8f0;
  color: #475569;
  padding: 0.75rem 1.25rem;
  border-radius: 0.375rem;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.95rem;
  border: none;
  cursor: pointer;
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
  max-width: 500px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.modal-title {
  color: #dc2626;
  font-size: 1.15rem;
  font-weight: 700;
  margin-bottom: 0.75rem;
}

.modal-body {
  font-size: 0.875rem;
  color: #334155;
  line-height: 1.5;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 1.25rem;
}

.btn-confirm-danger {
  background-color: #dc2626;
  color: #ffffff;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  border: none;
  font-weight: 600;
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
