<template>
  <div class="application-detail-page">
    <div class="page-header">
      <router-link
        v-if="application.job_id"
        :to="`/company/jobs/${application.job_id}/applications`"
        class="back-link"
      >
        &larr; Quay lại danh sách hồ sơ
      </router-link>
      <h1 class="page-title">Chi tiết Đơn ứng tuyển</h1>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="state-panel loading">Đang tải chi tiết đơn ứng tuyển...</div>

    <!-- Error State -->
    <div v-else-if="error" class="state-panel error">{{ error }}</div>

    <!-- Content Layout -->
    <div v-else class="detail-grid">
      <!-- Cột trái: Thông tin ứng viên, Điểm AI, Lịch phỏng vấn -->
      <div class="left-column">
        <!-- Candidate Card -->
        <div class="card candidate-hero">
          <div class="hero-info">
            <h2 class="candidate-name">{{ application.candidate_name || 'Hồ sơ Ứng viên' }}</h2>
            <p class="job-ref">
              Ứng tuyển vị trí: <strong>{{ application.job?.title || 'Việc làm' }}</strong>
            </p>
            <div class="status-meta">
              <span class="meta-label">Trạng thái hồ sơ:</span>
              <StatusBadge :status="application.status" domain="application" />
              <span class="applied-date">Nộp ngày: {{ formatDate(application.applied_at) }}</span>
            </div>
          </div>

          <!-- Download CV Button Component (Private stream blob) -->
          <CVAccessButtons :application-id="application.id" @downloaded="loadData" />
        </div>

        <!-- ScoreBreakdown Component giải thích điểm AI -->
        <ScoreBreakdown v-if="matchResult" :match-result="matchResult" />

        <!-- Component Quản lý Lịch phỏng vấn -->
        <InterviewTimeline
          ref="interviewTimelineRef"
          :interviews="interviews"
          :application-id="application.id"
          @refresh="loadData"
        />
      </div>

      <!-- Cột phải: Bộ nút thao tác xử lý hồ sơ trực tiếp -->
      <div class="right-column">
        <div class="card sticky-card action-panel">
          <h3 class="side-title">Thao tác xử lý hồ sơ</h3>

          <div v-if="actionError" class="action-error-banner">{{ actionError }}</div>

          <!-- 1. Khi đơn đang Chờ xử lý (pending) -->
          <div v-if="application.status === 'pending'" class="action-group">
            <p class="step-guide">Hồ sơ mới nộp. Xem CV để hệ thống chuyển sang bước xem xét.</p>
            <button
              @click="handleViewCvAndReview"
              :disabled="actionLoading"
              class="btn-flow btn-primary-flow"
            >
              {{ actionLoading ? 'Đang mở CV...' : '📄 Xem CV & Bắt đầu xem xét' }}
            </button>
            <button
              @click="openRejectModal"
              :disabled="actionLoading"
              class="btn-flow btn-danger-outline-flow"
            >
              ✕ Từ chối đơn
            </button>
          </div>

          <!-- 2. Khi đơn đang Xem xét (reviewing) -->
          <div v-else-if="application.status === 'reviewing'" class="action-group">
            <p class="step-guide">Hồ sơ đang trong vòng xem xét. Bạn có thể lên lịch phỏng vấn, tuyển thẳng hoặc từ chối.</p>
            <button
              @click="openScheduleModal"
              :disabled="actionLoading"
              class="btn-flow btn-indigo-flow"
            >
              📅 Duyệt &amp; Lên lịch phỏng vấn
            </button>
            <button
              @click="changeAppStatus('passed')"
              :disabled="actionLoading"
              class="btn-flow btn-success-flow"
            >
              ✓ Tuyển thẳng (Đạt)
            </button>
            <button
              @click="openRejectModal"
              :disabled="actionLoading"
              class="btn-flow btn-danger-outline-flow"
            >
              ✕ Từ chối đơn
            </button>
          </div>

          <!-- 3. Khi đơn đang Phỏng vấn (interview) -->
          <div v-else-if="application.status === 'interview'" class="action-group">
            <p class="step-guide">Đang trong giai đoạn phỏng vấn. Đánh giá kết quả tuyển dụng sau buổi phỏng vấn.</p>
            <button
              @click="changeAppStatus('passed')"
              :disabled="actionLoading"
              class="btn-flow btn-success-flow"
            >
              ✓ Đánh giá Đạt
            </button>
            <button
              @click="openRejectModal"
              :disabled="actionLoading"
              class="btn-flow btn-danger-outline-flow"
            >
              ✕ Từ chối đơn
            </button>
          </div>

          <!-- 4. Các trạng thái kết thúc (passed / rejected / withdrawn) -->
          <div v-else class="terminal-panel">
            <p class="terminal-hint">
              Hồ sơ đã ở trạng thái kết thúc (<strong>{{ statusLabel(application.status) }}</strong>), không thể thay đổi thêm.
            </p>
          </div>

          <!-- Lịch sử trạng thái -->
          <div v-if="application.history && application.history.length" class="history-block">
            <h4 class="history-title">Lịch sử thay đổi</h4>
            <div class="history-list">
              <div
                v-for="(hist, idx) in application.history"
                :key="idx"
                class="history-item"
              >
                <div class="hist-dot"></div>
                <div class="hist-content">
                  <div class="hist-status">
                    <span class="hist-badge">{{ statusLabel(hist.to_status) }}</span>
                    <span class="hist-time">{{ formatDate(hist.changed_at) }}</span>
                  </div>
                  <p v-if="hist.note" class="hist-note">"{{ hist.note }}"</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Từ chối đơn ứng tuyển -->
    <div v-if="showRejectModal" class="modal-backdrop" @click.self="showRejectModal = false">
      <div class="modal-card">
        <h3 class="modal-title">Từ chối đơn ứng tuyển</h3>
        <p class="modal-desc">
          Xác nhận từ chối hồ sơ của <strong>{{ application.candidate_name || 'ứng viên' }}</strong> cho vị trí
          <strong>{{ application.job?.title }}</strong>.
        </p>

        <div class="modal-form-group">
          <label>Lý do từ chối (Ghi chú nội bộ):</label>
          <textarea
            v-model="rejectNote"
            rows="3"
            class="modal-textarea"
            placeholder="Ví dụ: Chưa phù hợp kinh nghiệm chuyên môn với yêu cầu dự án..."
          ></textarea>
        </div>

        <div class="modal-actions">
          <button
            type="button"
            @click="showRejectModal = false"
            :disabled="actionLoading"
            class="btn-modal-cancel"
          >
            Hủy
          </button>
          <button
            type="button"
            @click="handleConfirmReject"
            :disabled="actionLoading"
            class="btn-modal-danger"
          >
            {{ actionLoading ? 'Đang xử lý...' : 'Xác nhận từ chối' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Duyệt & Lên lịch phỏng vấn -->
    <div v-if="showScheduleModal" class="modal-backdrop" @click.self="showScheduleModal = false">
      <div class="modal-card">
        <h3 class="modal-title">Duyệt &amp; Lên lịch phỏng vấn</h3>
        <p class="modal-desc">
          Hệ thống sẽ tự động chuyển đơn sang trạng thái <strong>Phỏng vấn</strong> và gửi thông báo cho ứng viên.
        </p>

        <form @submit.prevent="handleConfirmSchedule" class="modal-form">
          <div class="modal-form-group">
            <label>Thời gian phỏng vấn <span class="text-danger">*</span></label>
            <input
              v-model="scheduleForm.scheduled_at"
              type="datetime-local"
              required
              class="modal-input"
            />
          </div>

          <div class="modal-form-group">
            <label>Địa điểm / Hình thức phỏng vấn <span class="text-danger">*</span></label>
            <input
              v-model="scheduleForm.location_or_mode"
              type="text"
              required
              placeholder="Ví dụ: Google Meet (link) hoặc Văn phòng Tầng 5, Tòa nhà ABC"
              class="modal-input"
            />
          </div>

          <div class="modal-form-group">
            <label>Ghi chú cho ứng viên (tùy chọn)</label>
            <textarea
              v-model="scheduleForm.notes"
              rows="2"
              placeholder="Thông tin chuẩn bị hoặc số điện thoại liên hệ khi cần..."
              class="modal-textarea"
            ></textarea>
          </div>

          <div v-if="scheduleError" class="modal-error">{{ scheduleError }}</div>

          <div class="modal-actions">
            <button
              type="button"
              @click="showScheduleModal = false"
              :disabled="actionLoading"
              class="btn-modal-cancel"
            >
              Hủy
            </button>
            <button
              type="submit"
              :disabled="actionLoading"
              class="btn-modal-indigo"
            >
              {{ actionLoading ? 'Đang tạo lịch...' : 'Xác nhận lên lịch' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { applicationApi } from '@/services/applicationApi';
import { interviewApi } from '@/services/interviewApi';
import CVAccessButtons from '@/components/company/CVAccessButtons.vue';
import InterviewTimeline from '@/components/company/InterviewTimeline.vue';
import ScoreBreakdown from '@/components/common/ScoreBreakdown.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';

const route = useRoute();
const applicationId = route.params.id;

const application = ref({});
const interviews = ref([]);
const matchResult = ref(null);
const loading = ref(true);
const error = ref('');

const actionLoading = ref(false);
const actionError = ref('');

// Reject Modal State
const showRejectModal = ref(false);
const rejectNote = ref('');

// Schedule Modal State
const showScheduleModal = ref(false);
const scheduleError = ref('');
const scheduleForm = ref({
  scheduled_at: '',
  location_or_mode: '',
  notes: '',
});

const interviewTimelineRef = ref(null);

const loadData = async () => {
  loading.value = true;
  error.value = '';
  try {
    const res = await applicationApi.getCompanyApplicationDetail(applicationId);
    application.value = res.data?.application || res.data || {};
    interviews.value = res.data?.interviews || [];
    matchResult.value = res.data?.match_result || null;
  } catch (err) {
    error.value = err.message || 'Không thể tải thông tin đơn ứng tuyển.';
  } finally {
    loading.value = false;
  }
};

// 1. Xem CV & Bắt đầu xem xét (pending -> reviewing)
const handleViewCvAndReview = async () => {
  actionLoading.value = true;
  actionError.value = '';
  try {
    const blob = await applicationApi.downloadCompanyApplicationCv(application.value.id);
    const url = window.URL.createObjectURL(blob);
    window.open(url, '_blank');
  } catch (err) {
    actionError.value = err.message || 'Không thể mở CV ứng viên.';
  } finally {
    await loadData(); // Tải lại để cập nhật trạng thái mới (reviewing)
    actionLoading.value = false;
  }
};

// 2. Chuyển trạng thái trực tiếp (passed / rejected)
const changeAppStatus = async (newStatus, note = null) => {
  if (newStatus === 'passed') {
    if (!confirm('Xác nhận tuyển dụng (Đạt) cho ứng viên này?')) return;
  }
  actionLoading.value = true;
  actionError.value = '';
  try {
    await applicationApi.changeStatus(application.value.id, {
      new_status: newStatus,
      note,
    });
    showRejectModal.value = false;
    rejectNote.value = '';
    await loadData();
  } catch (err) {
    actionError.value = err.message || 'Cập nhật trạng thái thất bại.';
  } finally {
    actionLoading.value = false;
  }
};

// 3. Mở modal từ chối
const openRejectModal = () => {
  rejectNote.value = '';
  actionError.value = '';
  showRejectModal.value = true;
};

const handleConfirmReject = async () => {
  await changeAppStatus('rejected', rejectNote.value || 'Doanh nghiệp từ chối hồ sơ');
};

// 4. Mở modal lên lịch phỏng vấn
const openScheduleModal = () => {
  scheduleError.value = '';
  scheduleForm.value = {
    scheduled_at: '',
    location_or_mode: '',
    notes: '',
  };
  showScheduleModal.value = true;
};

const handleConfirmSchedule = async () => {
  actionLoading.value = true;
  scheduleError.value = '';
  try {
    await interviewApi.schedule(application.value.id, {
      scheduled_at: scheduleForm.value.scheduled_at,
      location_or_mode: scheduleForm.value.location_or_mode,
      notes: scheduleForm.value.notes,
    });
    showScheduleModal.value = false;
    await loadData();
  } catch (err) {
    scheduleError.value = err.message || 'Lên lịch phỏng vấn thất bại.';
  } finally {
    actionLoading.value = false;
  }
};

const statusLabel = (st) => {
  const map = {
    pending: 'Chờ xử lý',
    reviewing: 'Đang xem xét',
    interview: 'Phỏng vấn',
    passed: 'Đạt tuyển dụng',
    rejected: 'Đã từ chối',
    withdrawn: 'Đã rút đơn',
  };
  return map[st] || st;
};

const formatDate = (ds) => {
  return ds ? new Date(ds).toLocaleString('vi-VN') : '—';
};

onMounted(loadData);
</script>

<style scoped>
.application-detail-page {
  max-width: 1100px;
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
  font-weight: 700;
  color: #0f172a;
}

.detail-grid {
  display: grid;
  grid-template-columns: 2fr 1.15fr;
  gap: 1.5rem;
}

.left-column {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.right-column {
  display: flex;
  flex-direction: column;
}

.card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.candidate-hero {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.candidate-name {
  font-size: 1.35rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 0.25rem;
}

.job-ref {
  font-size: 0.875rem;
  color: #475569;
  margin-bottom: 0.75rem;
}

.status-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.meta-label {
  font-size: 0.8125rem;
  color: #64748b;
}

.applied-date {
  font-size: 0.75rem;
  color: #94a3b8;
  margin-left: 0.5rem;
}

.sticky-card {
  position: sticky;
  top: 1.5rem;
}

.action-panel {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.side-title {
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid #f1f5f9;
}

.action-group {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.step-guide {
  font-size: 0.8125rem;
  color: #64748b;
  margin-bottom: 0.25rem;
  line-height: 1.4;
}

.btn-flow {
  width: 100%;
  padding: 0.65rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
}

.btn-flow:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary-flow {
  background-color: #2563eb;
  color: #ffffff;
  border: 1px solid #2563eb;
}
.btn-primary-flow:hover:not(:disabled) {
  background-color: #1d4ed8;
}

.btn-indigo-flow {
  background-color: #4f46e5;
  color: #ffffff;
  border: 1px solid #4f46e5;
}
.btn-indigo-flow:hover:not(:disabled) {
  background-color: #4338ca;
}

.btn-success-flow {
  background-color: #16a34a;
  color: #ffffff;
  border: 1px solid #16a34a;
}
.btn-success-flow:hover:not(:disabled) {
  background-color: #15803d;
}

.btn-danger-outline-flow {
  background-color: transparent;
  color: #dc2626;
  border: 1px solid #fca5a5;
}
.btn-danger-outline-flow:hover:not(:disabled) {
  background-color: #fef2f2;
  border-color: #dc2626;
}

.terminal-panel {
  background-color: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 0.375rem;
  padding: 0.875rem;
}

.terminal-hint {
  font-size: 0.8125rem;
  color: #64748b;
  line-height: 1.4;
}

.action-error-banner {
  background-color: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
  padding: 0.5rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
}

.history-block {
  margin-top: 0.75rem;
  padding-top: 0.75rem;
  border-top: 1px solid #f1f5f9;
}

.history-title {
  font-size: 0.8125rem;
  font-weight: 700;
  color: #475569;
  margin-bottom: 0.5rem;
}

.history-list {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.history-item {
  display: flex;
  gap: 0.5rem;
  align-items: flex-start;
  font-size: 0.75rem;
}

.hist-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #94a3b8;
  margin-top: 5px;
  flex-shrink: 0;
}

.hist-content {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.hist-status {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.hist-badge {
  font-weight: 600;
  color: #334155;
}

.hist-time {
  color: #94a3b8;
  font-size: 0.7rem;
}

.hist-note {
  color: #64748b;
  font-style: italic;
  margin: 0;
}

/* Modals */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-card {
  background: #ffffff;
  border-radius: 0.5rem;
  padding: 1.5rem;
  max-width: 480px;
  width: 100%;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.modal-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: #0f172a;
}

.modal-desc {
  font-size: 0.875rem;
  color: #475569;
  line-height: 1.4;
}

.modal-form {
  display: flex;
  flex-direction: column;
  gap: 0.875rem;
}

.modal-form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.modal-form-group label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #334155;
}

.modal-input,
.modal-textarea {
  padding: 0.5rem 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  outline: none;
  font-family: inherit;
}

.modal-input:focus,
.modal-textarea:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.btn-modal-cancel {
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  color: #475569;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
}

.btn-modal-cancel:hover {
  background: #e2e8f0;
}

.btn-modal-danger {
  background: #dc2626;
  border: 1px solid #dc2626;
  color: #ffffff;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-modal-danger:hover:not(:disabled) {
  background: #b91c1c;
}

.btn-modal-indigo {
  background: #4f46e5;
  border: 1px solid #4f46e5;
  color: #ffffff;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-modal-indigo:hover:not(:disabled) {
  background: #4338ca;
}

.modal-error {
  color: #dc2626;
  font-size: 0.8125rem;
}

.text-danger {
  color: #dc2626;
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
