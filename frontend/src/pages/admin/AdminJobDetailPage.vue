<template>
  <div class="admin-job-detail-page">
    <div class="page-header">
      <router-link to="/admin/jobs" class="back-link">← Quay lại danh sách tin chờ duyệt</router-link>
      <h1 class="page-title">Kiểm duyệt Nội dung Tin tuyển dụng</h1>
    </div>

    <div v-if="loading" class="state-panel loading">Đang tải chi tiết tin tuyển dụng...</div>
    <div v-else-if="error" class="state-panel error">{{ error }}</div>

    <div v-else-if="job" class="detail-grid">
      <!-- Cột trái: Nội dung tin tuyển dụng -->
      <div class="main-column">
        <div class="card">
          <div class="job-header">
            <h2 class="job-title">{{ job.title }}</h2>
            <div class="badges-row">
              <StatusBadge :status="job.review_status" domain="review" />
              <StatusBadge :status="job.publication_status" domain="publication" />
            </div>
          </div>

          <p class="company-sub">
            Doanh nghiệp: <strong>{{ job.company?.company_name || '—' }}</strong>
            <span class="created-at">(Đăng ngày: {{ formatDate(job.created_at) }})</span>
          </p>

          <hr class="divider" />

          <div class="content-block">
            <h4 class="block-title">Mô tả công việc:</h4>
            <p class="pre-text">{{ job.description }}</p>
          </div>

          <div class="content-block">
            <h4 class="block-title">Yêu cầu ứng viên:</h4>
            <p class="pre-text">{{ job.requirements_text }}</p>
          </div>

          <!-- Kỹ năng -->
          <div class="content-block">
            <h4 class="block-title">Kỹ năng gắn với tin:</h4>
            <div class="skills-wrap">
              <span v-for="s in job.skills" :key="s.id" :class="['skill-pill', s.pivot?.requirement_type]">
                {{ s.name }} ({{ s.pivot?.requirement_type === 'required' ? 'Bắt buộc' : 'Ưu tiên' }})
              </span>
              <span v-if="!job.skills?.length" class="text-muted text-sm">Không gắn kỹ năng nào.</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Cột phải: Thao tác phê duyệt / từ chối / ẩn -->
      <div class="side-column">
        <div class="card actions-card">
          <h3 class="side-title">Hành động kiểm duyệt</h3>
          <p class="side-desc">Chọn hành động xử lý tin tuyển dụng này theo đúng chính sách nền tảng</p>

          <!-- Nút Phê duyệt -->
          <button
            @click="handleApprove"
            :disabled="submitting"
            class="btn-action approve"
          >
            ✓ Phê duyệt tin (Approved)
          </button>

          <!-- Nút Từ chối -->
          <button
            @click="promptAction('reject')"
            :disabled="submitting"
            class="btn-action reject"
          >
            ✕ Từ chối (Yêu cầu sửa lại)
          </button>

          <!-- Nút Ẩn tin vi phạm -->
          <button
            @click="promptAction('hide')"
            :disabled="submitting"
            class="btn-action hide"
          >
            🔒 Ẩn tin (Vi phạm chính sách)
          </button>
        </div>
      </div>
    </div>

    <!-- Confirm Modal Nhập Lý do (bắt buộc khi từ chối hoặc ẩn) -->
    <div v-if="modalOpen" class="modal-overlay" @click.self="modalOpen = false">
      <div class="modal-card">
        <h3 class="modal-title">
          {{ modalType === 'reject' ? 'Lý do từ chối phê duyệt tin' : 'Lý do ẩn tin tuyển dụng vi phạm' }}
        </h3>
        <p class="modal-subtitle">
          Lý do này sẽ được gửi thông báo trực tiếp tới doanh nghiệp qua hệ thống (API-NOTI-01).
        </p>

        <textarea
          v-model="reasonText"
          rows="4"
          class="modal-textarea"
          placeholder="Bắt buộc nhập lý do chi tiết..."
          required
        ></textarea>

        <div v-if="modalError" class="modal-error">{{ modalError }}</div>

        <div class="modal-actions">
          <button type="button" @click="modalOpen = false" class="btn-cancel">Hủy</button>
          <button
            type="button"
            @click="submitAction"
            :disabled="submitting || !reasonText.trim()"
            :class="['btn-confirm', modalType]"
          >
            {{ submitting ? 'Đang gửi...' : 'Xác nhận xử lý' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { adminApi } from '@/services/adminApi';
import StatusBadge from '@/components/common/StatusBadge.vue';

const route = useRoute();
const router = useRouter();
const jobId = route.params.id;

const job = ref(null);
const loading = ref(true);
const submitting = ref(false);
const error = ref('');

const modalOpen = ref(false);
const modalType = ref('');
const reasonText = ref('');
const modalError = ref('');

const loadJob = async () => {
  loading.value = true;
  error.value = '';
  try {
    const res = await adminApi.getJobDetail(jobId);
    job.value = res.data?.job || res.data;
  } catch (err) {
    error.value = err.message || 'Không thể tải chi tiết tin tuyển dụng.';
  } finally {
    loading.value = false;
  }
};

const handleApprove = async () => {
  if (!confirm('Xác nhận phê duyệt tin tuyển dụng này?')) return;
  submitting.value = true;
  try {
    await adminApi.reviewJob(jobId, { review_status: 'approved' });
    alert('Đã phê duyệt tin thành công!');
    router.push('/admin/jobs');
  } catch (err) {
    alert(err.message || 'Phê duyệt thất bại.');
  } finally {
    submitting.value = false;
  }
};

const promptAction = (type) => {
  modalType.value = type;
  reasonText.value = '';
  modalError.value = '';
  modalOpen.value = true;
};

const submitAction = async () => {
  if (!reasonText.value.trim()) {
    modalError.value = 'Vui lòng nhập lý do cụ thể.';
    return;
  }
  submitting.value = true;
  modalError.value = '';
  try {
    if (modalType.value === 'reject') {
      await adminApi.reviewJob(jobId, {
        review_status: 'rejected',
        rejection_reason: reasonText.value.trim(),
      });
      alert('Đã từ chối tin tuyển dụng.');
    } else {
      await adminApi.hideJob(jobId, {
        hidden_reason: reasonText.value.trim(),
      });
      alert('Đã ẩn tin tuyển dụng vi phạm.');
    }
    modalOpen.value = false;
    router.push('/admin/jobs');
  } catch (err) {
    modalError.value = err.message || 'Thao tác không thành công.';
  } finally {
    submitting.value = false;
  }
};

const formatDate = (ds) => {
  return ds ? new Date(ds).toLocaleString('vi-VN') : '—';
};

onMounted(loadJob);
</script>

<style scoped>
.admin-job-detail-page {
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

.job-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
}

.job-title {
  font-size: 1.35rem;
  font-weight: 800;
  color: #0f172a;
}

.badges-row {
  display: flex;
  gap: 0.5rem;
}

.company-sub {
  font-size: 0.875rem;
  color: #475569;
  margin-top: 0.5rem;
}

.created-at {
  font-size: 0.75rem;
  color: #94a3b8;
  margin-left: 0.5rem;
}

.divider {
  border: none;
  border-top: 1px solid #f1f5f9;
  margin: 1.25rem 0;
}

.content-block {
  margin-bottom: 1.25rem;
}

.block-title {
  font-size: 0.9rem;
  font-weight: 700;
  color: #334155;
  margin-bottom: 0.5rem;
}

.pre-text {
  font-size: 0.875rem;
  color: #475569;
  line-height: 1.6;
  white-space: pre-wrap;
}

.skills-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.skill-pill {
  padding: 0.25rem 0.6rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.skill-pill.required {
  background-color: #fee2e2;
  color: #991b1b;
}

.skill-pill.preferred {
  background-color: #e0f2fe;
  color: #0369a1;
}

.side-title {
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 0.25rem;
}

.side-desc {
  font-size: 0.8125rem;
  color: #64748b;
  margin-bottom: 1.25rem;
}

.actions-card {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.btn-action {
  padding: 0.75rem;
  border-radius: 0.375rem;
  border: none;
  font-weight: 700;
  font-size: 0.875rem;
  cursor: pointer;
  transition: opacity 0.15s;
}

.btn-action:hover:not(:disabled) {
  opacity: 0.9;
}

.btn-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-action.approve {
  background-color: #16a34a;
  color: #ffffff;
}

.btn-action.reject {
  background-color: #ea580c;
  color: #ffffff;
}

.btn-action.hide {
  background-color: #dc2626;
  color: #ffffff;
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
  font-size: 1.1rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 0.25rem;
}

.modal-subtitle {
  font-size: 0.8125rem;
  color: #64748b;
  margin-bottom: 1rem;
}

.modal-textarea {
  width: 100%;
  padding: 0.625rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  outline: none;
  box-sizing: border-box;
}

.modal-textarea:focus {
  border-color: #0284c7;
}

.modal-error {
  color: #dc2626;
  font-size: 0.75rem;
  margin-top: 0.5rem;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 1rem;
}

.btn-cancel {
  background-color: #e2e8f0;
  color: #475569;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  cursor: pointer;
}

.btn-confirm {
  color: #ffffff;
  border: none;
  padding: 0.5rem 1.25rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-confirm.reject {
  background-color: #ea580c;
}

.btn-confirm.hide {
  background-color: #dc2626;
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
