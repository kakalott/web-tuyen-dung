<template>
  <div class="extraction-page">
    <h1 class="page-title">Kiểm tra thông tin trích xuất CV</h1>

    <div v-if="loading" class="state-panel loading">Đang tải dữ liệu trích xuất...</div>
    <div v-else-if="error" class="state-panel error">{{ error }}</div>

    <div v-else-if="extraction" class="card">
      <!-- Trạng thái Failed -->
      <div v-if="extraction.review_status === 'failed'" class="alert-danger">
        <p><strong>Lỗi:</strong> Hệ thống không thể trích xuất nội dung CV này.</p>
        <button @click="handleRetry" :disabled="retrying" class="btn-warning">
          {{ retrying ? 'Đang thử lại...' : 'Thử trích xuất lại' }}
        </button>
      </div>

      <!-- Trạng thái Confirmed -->
      <div v-else-if="extraction.review_status === 'confirmed'" class="alert-success">
        <p><strong>Đã xác nhận.</strong> CV này đã sẵn sàng để AI Matching phân tích.</p>
        <div class="confirmed-text-preview">
          <label>Nội dung đã xác nhận:</label>
          <pre class="text-preview">{{ extraction.confirmed_text }}</pre>
        </div>
        <router-link to="/candidate/recommendations" class="btn-primary" style="display: inline-block; margin-top: 1rem;">
          Xem gợi ý việc làm (AI) →
        </router-link>
      </div>

      <!-- Form Xác nhận (Confirmed-CV Gate) -->
      <form v-else @submit.prevent="handleConfirm" class="confirm-form">
        <div v-if="confirmError" class="error-banner">{{ confirmError }}</div>

        <div class="form-group">
          <label class="form-label">Văn bản trích xuất (có thể chỉnh sửa lỗi nhận diện):</label>
          <textarea
            v-model="formData.confirmed_text"
            rows="12"
            class="text-area"
            placeholder="Nội dung trích xuất từ CV..."
          ></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Kỹ năng AI nhận diện:</label>
          <div class="skill-tags">
            <span v-for="skill in formData.skills" :key="skill.skill_id ?? skill.id" class="skill-tag">
              {{ skill.name }}
              <button type="button" @click="removeSkill(skill.skill_id ?? skill.id)" class="tag-remove">&times;</button>
            </span>
          </div>
          <p v-if="!formData.skills.length" class="empty-hint">Không có kỹ năng nào được nhận diện tự động.</p>
        </div>

        <button type="submit" class="btn-confirm" :disabled="submitting">
          {{ submitting ? 'Đang xác nhận...' : '✓ Xác nhận dữ liệu CV' }}
        </button>
      </form>
    </div>

    <div class="back-link">
      <router-link to="/candidate/cvs">← Quay lại danh sách CV</router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { cvApi } from '@/services/cvApi';

const route = useRoute();
const router = useRouter();
const cvId = route.params.cvId;

const extraction = ref(null);
const loading = ref(true);
const error = ref('');
const submitting = ref(false);
const retrying = ref(false);
const confirmError = ref('');
const formData = ref({ confirmed_text: '', skills: [] });
const rejectedSkillIds = ref([]);

const loadExtraction = async () => {
  loading.value = true;
  error.value = '';
  try {
    const res = await cvApi.getExtraction(cvId);
    extraction.value = res.data;
    formData.value.confirmed_text = res.data.confirmed_text || res.data.extracted_text || '';
    formData.value.skills = res.data.skills || [];
    rejectedSkillIds.value = [];
  } catch (err) {
    error.value = err.message || 'Không thể tải kết quả trích xuất.';
  } finally {
    loading.value = false;
  }
};

onMounted(loadExtraction);

const handleConfirm = async () => {
  if (!formData.value.confirmed_text || !formData.value.confirmed_text.trim()) {
    confirmError.value = 'Nội dung trích xuất không được để trống.';
    return;
  }

  submitting.value = true;
  confirmError.value = '';
  try {
    const confirmedIds = formData.value.skills
      .map((s) => Number(s.skill_id ?? s.id))
      .filter((id) => !isNaN(id) && id > 0);

    const finalRejectedIds = rejectedSkillIds.value
      .map((id) => Number(id))
      .filter((id) => !isNaN(id) && id > 0 && !confirmedIds.includes(id));

    const payload = {
      confirmed_text: formData.value.confirmed_text.trim(),
      confirmed_skill_ids: confirmedIds,
      rejected_skill_ids: finalRejectedIds,
    };

    await cvApi.confirmExtraction(cvId, payload);
    router.push('/candidate/cvs');
  } catch (err) {
    confirmError.value = err.message || 'Xác nhận thất bại.';
  } finally {
    submitting.value = false;
  }
};

const handleRetry = async () => {
  retrying.value = true;
  try {
    await cvApi.retryExtraction(cvId);
    await loadExtraction();
  } catch (err) {
    alert(err.message || 'Thử lại thất bại.');
  } finally {
    retrying.value = false;
  }
};

const removeSkill = (skillId) => {
  const targetId = Number(skillId);
  formData.value.skills = formData.value.skills.filter(
    (s) => Number(s.skill_id ?? s.id) !== targetId
  );
  if (!rejectedSkillIds.value.includes(targetId)) {
    rejectedSkillIds.value.push(targetId);
  }
};
</script>

<style scoped>
.extraction-page { max-width: 800px; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 1.5rem; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1.5rem; }
.confirm-form { display: flex; flex-direction: column; gap: 1.25rem; }
.form-group { display: flex; flex-direction: column; gap: 0.375rem; }
.form-label { font-weight: 600; color: #1e293b; font-size: 0.875rem; }
.text-area { padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size: 0.875rem; font-family: monospace; resize: vertical; outline: none; }
.text-area:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,0.1); }
.text-preview { background: #f8fafc; padding: 0.75rem; border-radius: 0.375rem; border: 1px solid #e2e8f0; font-family: monospace; font-size: 0.8125rem; white-space: pre-wrap; max-height: 300px; overflow-y: auto; }
.skill-tags { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.skill-tag { display: inline-flex; align-items: center; gap: 0.375rem; background: #dbeafe; color: #1e40af; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.8125rem; }
.tag-remove { background: none; border: none; color: #3b82f6; cursor: pointer; font-size: 1rem; line-height: 1; padding: 0; }
.btn-confirm { background: #16a34a; color: #fff; border: none; padding: 0.75rem 1.5rem; border-radius: 0.375rem; cursor: pointer; font-weight: 600; font-size: 0.9375rem; }
.btn-confirm:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-primary { background: #2563eb; color: #fff; padding: 0.5rem 1rem; border-radius: 0.375rem; text-decoration: none; font-weight: 500; font-size: 0.875rem; }
.btn-warning { background: #f59e0b; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; font-weight: 500; margin-top: 0.75rem; }
.btn-warning:disabled { opacity: 0.6; cursor: not-allowed; }
.alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 1rem; border-radius: 0.375rem; }
.alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 1rem; border-radius: 0.375rem; }
.confirmed-text-preview { margin-top: 1rem; }
.confirmed-text-preview label { font-weight: 500; display: block; margin-bottom: 0.5rem; }
.error-banner { background: #fef2f2; color: #b91c1c; padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-size: 0.875rem; border: 1px solid #fecaca; }
.empty-hint { color: #94a3b8; font-size: 0.8125rem; }
.back-link { margin-top: 1.5rem; }
.back-link a { color: #2563eb; text-decoration: none; font-size: 0.875rem; }
.back-link a:hover { text-decoration: underline; }
.state-panel { padding: 2rem; text-align: center; border-radius: 0.5rem; }
.state-panel.loading { color: #2563eb; background: #eff6ff; }
.state-panel.error { color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; }
</style>
