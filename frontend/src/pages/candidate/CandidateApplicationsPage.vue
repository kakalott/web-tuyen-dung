<template>
  <div class="applications-page">
    <h1 class="page-title">Đơn ứng tuyển của tôi</h1>

    <div v-if="loading" class="state-panel loading">Đang tải danh sách đơn ứng tuyển...</div>
    <div v-else-if="error" class="state-panel error">{{ error }}</div>
    <div v-else-if="!applications.length" class="state-panel empty">
      Bạn chưa nộp đơn ứng tuyển nào.
      <router-link to="/jobs" class="empty-action">Tìm việc làm ngay →</router-link>
    </div>

    <div v-else class="app-list">
      <div v-for="app in applications" :key="app.id" class="app-card">
        <div class="app-main">
          <div class="app-header-row">
            <h3 class="job-title">{{ app.job_title || app.job?.title || 'Vị trí công việc' }}</h3>
            <span class="badge" :class="statusBadge(app.status)">
              {{ statusLabel(app.status) }}
            </span>
          </div>

          <p class="company-name">{{ app.company_name || app.job?.company?.company_name || 'Doanh nghiệp' }}</p>

          <!-- Hiển thị thông tin CV đã nộp -->
          <div class="submitted-cv-row">
            <span class="cv-label">CV đã nộp:</span>
            <button
              v-if="app.cv_id"
              @click="handleViewSubmittedCv(app.cv_id)"
              class="btn-view-cv"
              title="Bấm để xem file CV bạn đã dùng lúc nộp đơn"
            >
              📄 {{ app.cv_filename || `CV #${app.cv_id}` }}
            </button>
            <span v-else class="text-muted text-sm">Chưa có thông tin file CV</span>
          </div>

          <div class="app-meta">
            <span class="text-muted text-sm">Thời gian nộp: {{ formatDate(app.applied_at) }}</span>
          </div>

          <!-- Thông tin các buổi phỏng vấn (BR-INT-01..05) -->
          <div v-if="app.interviews && app.interviews.length" class="interviews-panel">
            <div class="interviews-title">📅 Thông tin buổi phỏng vấn</div>
            <div
              v-for="iv in app.interviews"
              :key="iv.id"
              class="interview-item"
            >
              <div class="iv-top">
                <span class="iv-time"><strong>Thời gian:</strong> {{ formatDatetime(iv.scheduled_at) }}</span>
                <StatusBadge :status="iv.status" domain="interview" />
              </div>
              <div class="iv-row"><strong>Địa điểm / Hình thức:</strong> {{ iv.location_or_mode }}</div>
              <div v-if="iv.notes" class="iv-row text-notes"><strong>Ghi chú:</strong> {{ iv.notes }}</div>
              <div v-if="iv.cancel_reason" class="iv-row text-danger"><strong>Lý do hủy:</strong> {{ iv.cancel_reason }}</div>
            </div>
          </div>
        </div>

        <div class="app-actions">
          <!-- Nút Rút hồ sơ: Chỉ bật khi status = pending | reviewing | interview -->
          <button
            v-if="canWithdraw(app.status)"
            @click="promptWithdraw(app.id)"
            class="btn-withdraw"
            title="Rút hồ sơ ứng tuyển"
          >
            Rút hồ sơ
          </button>
          <span v-else-if="app.status === 'withdrawn'" class="status-tag tag-withdrawn">Đã rút</span>
          <span v-else-if="app.status === 'passed'" class="status-tag tag-passed">✓ Đã trúng tuyển</span>
          <span v-else-if="app.status === 'rejected'" class="status-tag tag-rejected">✕ Chưa phù hợp</span>
        </div>
      </div>
    </div>

    <!-- ConfirmModal rút đơn -->
    <div v-if="showWithdrawModal" class="modal-overlay" @click.self="showWithdrawModal = false">
      <div class="modal-card">
        <h3 class="modal-title">Xác nhận rút đơn ứng tuyển</h3>
        <p>Bạn có chắc chắn muốn rút đơn này? Thao tác này không thể hoàn tác và doanh nghiệp sẽ không thể tiếp tục xử lý hồ sơ.</p>
        <div class="modal-actions">
          <button @click="confirmWithdraw" :disabled="withdrawing" class="btn-danger">
            {{ withdrawing ? 'Đang xử lý...' : 'Xác nhận rút đơn' }}
          </button>
          <button @click="showWithdrawModal = false" class="btn-secondary">Hủy</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { applicationApi } from '@/services/applicationApi';
import { apiClient } from '@/services/apiClient';
import { cvApi } from '@/services/cvApi';
import StatusBadge from '@/components/common/StatusBadge.vue';

const applications = ref([]);
const loading = ref(true);
const error = ref('');
const showWithdrawModal = ref(false);
const withdrawing = ref(false);
let withdrawTargetId = null;

// Ma trận 6 trạng thái ứng tuyển: Chỉ rút được khi đang pending | reviewing | interview
const WITHDRAWABLE_STATUSES = ['pending', 'reviewing', 'interview'];

const loadApplications = async () => {
  loading.value = true;
  error.value = '';
  try {
    const res = await applicationApi.getMyApplications();
    applications.value = res.data?.items ?? (Array.isArray(res.data) ? res.data : []);
  } catch (err) {
    error.value = err.message || 'Không thể tải danh sách đơn ứng tuyển.';
  } finally {
    loading.value = false;
  }
};

onMounted(loadApplications);

const canWithdraw = (status) => WITHDRAWABLE_STATUSES.includes(status);

const statusLabel = (status) => {
  const map = {
    pending: 'Chờ xử lý',
    reviewing: 'Đang xem xét',
    interview: 'Phỏng vấn',
    passed: 'Đạt tuyển dụng',
    rejected: 'Không phù hợp',
    withdrawn: 'Đã rút hồ sơ',
  };
  return map[status] || status;
};

const statusBadge = (status) => ({
  'badge-info': status === 'pending' || status === 'reviewing',
  'badge-warning': status === 'interview',
  'badge-success': status === 'passed',
  'badge-danger': status === 'rejected',
  'badge-muted': status === 'withdrawn',
});

const formatDate = (d) => (d ? new Date(d).toLocaleString('vi-VN') : '');
const formatDatetime = (d) => (d ? new Date(d).toLocaleString('vi-VN') : '');

const handleViewSubmittedCv = async (cvId) => {
  try {
    const blob = await apiClient.downloadBlob(`/candidate/cvs/${cvId}/file`);
    const url = window.URL.createObjectURL(blob);
    window.open(url, '_blank');
  } catch (err) {
    try {
      await cvApi.downloadOwn(cvId);
    } catch (e) {
      alert(err.message || 'Không thể tải file CV.');
    }
  }
};

const promptWithdraw = (id) => {
  withdrawTargetId = id;
  showWithdrawModal.value = true;
};

const confirmWithdraw = async () => {
  withdrawing.value = true;
  try {
    await applicationApi.withdraw(withdrawTargetId);
    // Cập nhật trạng thái trong danh sách cục bộ
    const target = applications.value.find((a) => a.id === withdrawTargetId);
    if (target) target.status = 'withdrawn';
    showWithdrawModal.value = false;
  } catch (err) {
    alert(err.message || 'Rút đơn thất bại.');
  } finally {
    withdrawing.value = false;
  }
};
</script>

<style scoped>
.applications-page { max-width: 850px; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 1.5rem; }
.app-list { display: flex; flex-direction: column; gap: 0.85rem; }
.app-card { display: flex; justify-content: space-between; align-items: flex-start; background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1.15rem 1.25rem; gap: 1rem; }
.app-main { display: flex; flex-direction: column; gap: 0.35rem; flex: 1; }
.app-header-row { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
.job-title { font-size: 1.05rem; font-weight: 700; color: #0f172a; margin: 0; }
.company-name { font-size: 0.875rem; color: #475569; margin: 0; font-weight: 500; }
.submitted-cv-row { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem; font-size: 0.8125rem; }
.cv-label { color: #64748b; font-weight: 500; }
.btn-view-cv { background: none; border: none; color: #2563eb; font-weight: 600; font-size: 0.8125rem; cursor: pointer; padding: 0; text-decoration: underline; display: inline-flex; align-items: center; gap: 0.25rem; }
.btn-view-cv:hover { color: #1d4ed8; }
.interviews-panel { margin-top: 0.75rem; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.375rem; padding: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem; }
.interviews-title { font-size: 0.8125rem; font-weight: 700; color: #1e293b; }
.interview-item { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.25rem; padding: 0.5rem 0.65rem; display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.8125rem; }
.iv-top { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
.iv-time { color: #0f172a; }
.iv-row { color: #334155; line-height: 1.35; }
.text-notes { color: #64748b; font-style: italic; }
.app-meta { display: flex; gap: 0.75rem; align-items: center; margin-top: 0.25rem; }
.app-actions { flex-shrink: 0; display: flex; align-items: center; }
.badge { padding: 0.125rem 0.55rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
.badge-info { background: #dbeafe; color: #1d4ed8; }
.badge-warning { background: #fef3c7; color: #b45309; }
.badge-success { background: #dcfce7; color: #16a34a; }
.badge-danger { background: #fef2f2; color: #dc2626; }
.badge-muted { background: #f1f5f9; color: #64748b; }
.status-tag { font-size: 0.75rem; font-weight: 600; }
.tag-withdrawn { color: #94a3b8; }
.tag-passed { color: #16a34a; }
.tag-rejected { color: #dc2626; }
.btn-withdraw { background: transparent; color: #dc2626; border: 1px solid #fecaca; padding: 0.35rem 0.75rem; border-radius: 0.375rem; cursor: pointer; font-size: 0.8125rem; font-weight: 500; transition: all 0.15s; }
.btn-withdraw:hover { background: #fef2f2; }
.btn-danger { background: #dc2626; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; font-weight: 500; }
.btn-danger:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; }
.text-muted { color: #64748b; }
.text-sm { font-size: 0.8125rem; }
.empty-action { display: block; margin-top: 0.75rem; color: #2563eb; text-decoration: none; font-weight: 500; }
.state-panel { padding: 2rem; text-align: center; border-radius: 0.5rem; }
.state-panel.loading { color: #2563eb; background: #eff6ff; }
.state-panel.error { color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; }
.state-panel.empty { color: #64748b; background: #f8fafc; border: 1px dashed #cbd5e1; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 50; }
.modal-card { background: #fff; padding: 1.5rem; border-radius: 0.5rem; min-width: 380px; max-width: 480px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
.modal-title { font-size: 1.125rem; font-weight: 600; margin-bottom: 0.75rem; color: #0f172a; }
.modal-actions { display: flex; gap: 0.5rem; margin-top: 1.25rem; justify-content: flex-end; }
</style>
