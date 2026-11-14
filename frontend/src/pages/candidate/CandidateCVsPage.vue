<template>
  <div class="cvs-page">
    <h1 class="page-title">Quản lý CV</h1>

    <!-- Upload Section -->
    <section class="card upload-section">
      <h3 class="card-title">Tải lên CV mới</h3>
      <div v-if="uploadError" class="error-banner">{{ uploadError }}</div>
      <div class="upload-row">
        <input
          ref="fileInput"
          type="file"
          accept=".pdf"
          @change="handleFileSelect"
          class="file-input"
        />
        <button
          @click="handleUpload"
          :disabled="!selectedFile || uploading"
          class="btn-primary"
        >
          {{ uploading ? 'Đang tải lên...' : 'Upload CV (PDF)' }}
        </button>
      </div>
      <p class="hint">Chỉ chấp nhận file PDF, tối đa 5MB.</p>
    </section>

    <!-- CV List -->
    <div v-if="loading" class="state-panel loading">Đang tải danh sách CV...</div>
    <div v-else-if="error" class="state-panel error">{{ error }}</div>
    <div v-else-if="!cvs.length" class="state-panel empty">
      Bạn chưa tải lên CV nào. Hãy upload CV đầu tiên để bắt đầu.
    </div>

    <div v-else class="cv-list">
      <div v-for="cv in cvs" :key="cv.id" class="cv-card">
        <div class="cv-main">
          <div class="cv-info">
            <div class="cv-title-row">
              <strong class="cv-name">{{ cv.original_filename }}</strong>
              <span class="badge" :class="badgeClass(cv.parse_status)">
                {{ statusLabel(cv.parse_status) }}
              </span>
              <span
                v-if="cv.review_status === 'confirmed'"
                class="badge badge-confirmed"
              >
                ✓ Đã xác nhận kỹ năng
              </span>
              <span
                v-else-if="cv.parse_status === 'processed'"
                class="badge badge-warning"
              >
                Chờ xác nhận
              </span>
            </div>
            <div class="cv-meta">
              <span class="text-muted text-sm">Tải lên: {{ formatDate(cv.uploaded_at) }}</span>
            </div>
          </div>

          <!-- Danh sách kỹ năng trích xuất được -->
          <div v-if="cv.skills && cv.skills.length" class="cv-skills">
            <span class="skills-label">Kỹ năng:</span>
            <div class="skills-wrap">
              <span
                v-for="skill in cv.skills"
                :key="skill.id"
                class="skill-chip"
                :class="{ 'chip-confirmed': skill.review_status === 'confirmed' }"
              >
                {{ skill.name }}
              </span>
            </div>
          </div>
        </div>

        <div class="cv-actions">
          <button
            @click="handleDownload(cv.id)"
            class="btn-outline-sm"
            title="Tải file PDF gốc"
          >
            Tải PDF
          </button>

          <template v-if="cv.parse_status === 'processed'">
            <router-link
              v-if="cv.review_status === 'confirmed'"
              :to="`/candidate/cvs/${cv.id}/extraction`"
              class="btn-outline-sm btn-action"
            >
              Xem / Sửa trích xuất
            </router-link>
            <router-link
              v-else
              :to="`/candidate/cvs/${cv.id}/extraction`"
              class="btn-primary-sm btn-action"
            >
              Kiểm tra &amp; Xác nhận
            </router-link>
          </template>

          <span v-else-if="cv.parse_status === 'processing'" class="text-muted text-sm">
            Đang xử lý...
          </span>

          <template v-else-if="cv.parse_status === 'failed'">
            <span class="text-danger text-sm">Trích xuất lỗi</span>
            <button
              @click="handleRetry(cv.id)"
              :disabled="retryingId === cv.id"
              class="btn-retry-sm"
              title="Thử trích xuất lại với AI"
            >
              {{ retryingId === cv.id ? 'Đang xử lý...' : '🔄 Thử lại' }}
            </button>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { cvApi } from '@/services/cvApi';

const cvs = ref([]);
const loading = ref(true);
const error = ref('');
const selectedFile = ref(null);
const uploading = ref(false);
const uploadError = ref('');
const fileInput = ref(null);
const retryingId = ref(null);

const loadCvs = async () => {
  loading.value = true;
  error.value = '';
  try {
    const res = await cvApi.getCvs();
    cvs.value = res.data || [];
  } catch (err) {
    error.value = err.message || 'Không thể tải danh sách CV.';
  } finally {
    loading.value = false;
  }
};

onMounted(loadCvs);

const handleFileSelect = (e) => {
  const file = e.target.files[0];
  if (file && file.type === 'application/pdf' && file.size <= 5 * 1024 * 1024) {
    selectedFile.value = file;
    uploadError.value = '';
  } else {
    selectedFile.value = null;
    uploadError.value = 'Vui lòng chọn file PDF hợp lệ (tối đa 5MB).';
  }
};

const handleUpload = async () => {
  if (!selectedFile.value) return;
  uploading.value = true;
  uploadError.value = '';
  try {
    await cvApi.upload(selectedFile.value);
    selectedFile.value = null;
    if (fileInput.value) fileInput.value.value = '';
    await loadCvs();
  } catch (err) {
    if (err.status === 502 || err.code === 'AI_SERVICE_ERROR') {
      uploadError.value = 'Dịch vụ AI trích xuất tạm thời gián đoạn (502). Vui lòng bấm Upload để thử lại.';
    } else {
      uploadError.value = err.message || 'Upload thất bại.';
    }
  } finally {
    uploading.value = false;
  }
};

const handleDownload = async (cvId) => {
  try {
    await cvApi.downloadOwn(cvId);
  } catch (err) {
    alert(err.message || 'Không thể tải file CV.');
  }
};

const handleRetry = async (cvId) => {
  retryingId.value = cvId;
  uploadError.value = '';
  try {
    await cvApi.retryExtraction(cvId);
    await loadCvs();
  } catch (err) {
    alert(err.message || 'Thử lại trích xuất thất bại. Vui lòng kiểm tra Python AI Service đã khởi động.');
  } finally {
    retryingId.value = null;
  }
};

const badgeClass = (status) => ({
  'badge-info': status === 'uploaded' || status === 'processing',
  'badge-success': status === 'processed',
  'badge-danger': status === 'failed',
});

const statusLabel = (status) => {
  const map = { uploaded: 'Đã tải lên', processing: 'Đang xử lý', processed: 'Đã trích xuất', failed: 'Lỗi' };
  return map[status] || status;
};

const formatDate = (d) => (d ? new Date(d).toLocaleDateString('vi-VN') : '');
</script>

<style scoped>
.cvs-page { max-width: 800px; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 1.5rem; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1.25rem; margin-bottom: 1.5rem; }
.card-title { font-size: 1rem; font-weight: 600; color: #1e293b; margin-bottom: 0.75rem; }
.upload-section { border-style: dashed; }
.upload-row { display: flex; gap: 0.75rem; align-items: center; }
.file-input { font-size: 0.875rem; }
.hint { font-size: 0.75rem; color: #94a3b8; margin-top: 0.5rem; }
.btn-primary { background: #2563eb; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; font-size: 0.875rem; font-weight: 500; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-outline-sm { background: transparent; border: 1px solid #cbd5e1; color: #334155; padding: 0.25rem 0.6rem; border-radius: 0.375rem; font-size: 0.8rem; cursor: pointer; display: inline-flex; align-items: center; }
.btn-outline-sm:hover { background: #f1f5f9; }
.btn-primary-sm { background: #2563eb; color: #fff; border: 1px solid #2563eb; padding: 0.25rem 0.65rem; border-radius: 0.375rem; font-size: 0.8rem; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; }
.btn-primary-sm:hover { background: #1d4ed8; }
.btn-action { text-decoration: none; }
.btn-retry-sm { background-color: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 0.25rem 0.6rem; border-radius: 0.375rem; font-size: 0.8rem; font-weight: 500; cursor: pointer; transition: all 0.15s; }
.btn-retry-sm:hover:not(:disabled) { background-color: #fee2e2; }
.btn-retry-sm:disabled { opacity: 0.6; cursor: not-allowed; }
.cv-list { display: flex; flex-direction: column; gap: 0.75rem; }
.cv-card { display: flex; justify-content: space-between; align-items: flex-start; background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1rem 1.25rem; gap: 1rem; }
.cv-main { display: flex; flex-direction: column; gap: 0.5rem; flex: 1; }
.cv-info { display: flex; flex-direction: column; gap: 0.375rem; }
.cv-title-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
.cv-name { font-size: 0.95rem; color: #0f172a; word-break: break-all; }
.cv-meta { display: flex; gap: 0.75rem; align-items: center; }
.cv-skills { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.125rem; }
.skills-label { font-size: 0.75rem; color: #64748b; font-weight: 500; }
.skills-wrap { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.skill-chip { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
.skill-chip.chip-confirmed { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; font-weight: 500; }
.cv-actions { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; margin-top: 0.25rem; }
.badge { padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
.badge-info { background: #dbeafe; color: #1d4ed8; }
.badge-success { background: #dcfce7; color: #16a34a; }
.badge-danger { background: #fef2f2; color: #dc2626; }
.badge-confirmed { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; font-weight: 600; }
.badge-warning { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.text-muted { color: #64748b; }
.text-sm { font-size: 0.8125rem; }
.text-danger { color: #dc2626; }
.state-panel { padding: 2rem; text-align: center; border-radius: 0.5rem; }
.state-panel.loading { color: #2563eb; background: #eff6ff; }
.state-panel.error { color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; }
.state-panel.empty { color: #64748b; background: #f8fafc; border: 1px dashed #cbd5e1; }
.error-banner { background: #fef2f2; color: #b91c1c; padding: 0.5rem 0.75rem; border-radius: 0.375rem; margin-bottom: 0.75rem; font-size: 0.875rem; border: 1px solid #fecaca; }
</style>
