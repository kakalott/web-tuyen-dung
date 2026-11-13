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
        <div class="app-info">
          <h3 class="job-title">{{ app.job?.title || 'N/A' }}</h3>
          <p class="company-name">{{ app.job?.company?.company_name || '' }}</p>
          <div class="app-meta">
            <span class="badge" :class="statusBadge(app.status)">
              {{ statusLabel(app.status) }}
            </span>
            <span class="text-muted text-sm">Nộp lúc: {{ formatDate(app.applied_at) }}</span>
          </div>
        </div>
        <div class="app-actions">
          <!-- Nút Rút hồ sơ: Chỉ bật khi status = pending | reviewing | interview -->
          <button
            v-if="canWithdraw(app.status)"
            @click="promptWithdraw(app.id)"
            class="btn-withdraw"
          >
            Rút hồ sơ
          </button>
          <span v-else-if="app.status === 'withdrawn'" class="text-muted text-sm">Đã rút</span>
        </div>
      </div>
    </div>

    <!-- ConfirmModal rút đơn -->
    <div v-if="showWithdrawModal" class="modal-overlay" @click.self="showWithdrawModal = false">
      <div class="modal-card">
        <h3 class="modal-title">Xác nhận rút đơn ứng tuyển</h3>
        <p>Bạn có chắc chắn muốn rút đơn này? Thao tác này không thể hoàn tác.</p>
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

const applications = ref([]);
const loading = ref(true);
const error = ref('');
const showWithdrawModal = ref(false);
const withdrawing = ref(false);
let withdrawTargetId = null;

// Ma trận 6 trạng thái ứng tuyển
const WITHDRAWABLE_STATUSES = ['pending', 'reviewing', 'interview'];

const loadApplications = async () => {
  loading.value = true;
  error.value = '';
  try {
    const res = await applicationApi.getMyApplications();
    applications.value = res.data || [];
  } catch (err) {
    error.value = err.message || 'Không thể tải danh sách đơn.';
  } finally {
    loading.value = false;
  }
};

onMounted(loadApplications);

const canWithdraw = (status) => WITHDRAWABLE_STATUSES.includes(status);

const statusLabel = (status) => {
  const map = {
    pending: 'Chờ xem xét',
    reviewing: 'Đang xem xét',
    interview: 'Phỏng vấn',
    offered: 'Đã chào việc',
    hired: 'Đã tuyển',
    withdrawn: 'Đã rút',
  };
  return map[status] || status;
};

const statusBadge = (status) => ({
  'badge-info': status === 'pending' || status === 'reviewing',
  'badge-warning': status === 'interview',
  'badge-success': status === 'offered' || status === 'hired',
  'badge-muted': status === 'withdrawn',
});

const formatDate = (d) => (d ? new Date(d).toLocaleString('vi-VN') : '');

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
.applications-page { max-width: 800px; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 1.5rem; }
.app-list { display: flex; flex-direction: column; gap: 0.75rem; }
.app-card { display: flex; justify-content: space-between; align-items: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1rem 1.25rem; }
.app-info { display: flex; flex-direction: column; gap: 0.25rem; }
.job-title { font-size: 1rem; font-weight: 600; color: #0f172a; margin: 0; }
.company-name { font-size: 0.875rem; color: #475569; margin: 0; }
.app-meta { display: flex; gap: 0.75rem; align-items: center; margin-top: 0.375rem; }
.app-actions { flex-shrink: 0; }
.badge { padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
.badge-info { background: #dbeafe; color: #1d4ed8; }
.badge-warning { background: #fef3c7; color: #b45309; }
.badge-success { background: #dcfce7; color: #16a34a; }
.badge-muted { background: #f1f5f9; color: #64748b; }
.btn-withdraw { background: transparent; color: #dc2626; border: 1px solid #fecaca; padding: 0.375rem 0.75rem; border-radius: 0.375rem; cursor: pointer; font-size: 0.8125rem; font-weight: 500; }
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
.modal-title { font-size: 1.125rem; font-weight: 600; margin-bottom: 0.75rem; }
.modal-actions { display: flex; gap: 0.5rem; margin-top: 1.25rem; justify-content: flex-end; }
</style>
