<template>
  <span :class="['status-badge', badgeClass]">
    {{ statusLabel }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: {
    type: String,
    required: true,
  },
  domain: {
    type: String,
    default: 'application', // 'application', 'review', 'publication', 'verification', 'interview'
  },
});

const statusLabel = computed(() => {
  const map = {
    // Application statuses
    pending: 'Chờ xử lý',
    reviewing: 'Đang xem xét',
    interview: 'Phỏng vấn',
    offered: 'Đã mời việc',
    hired: 'Đã tuyển',
    withdrawn: 'Đã rút đơn',

    // Review statuses
    approved: 'Đã duyệt',
    rejected: 'Từ chối',

    // Publication statuses
    not_public: 'Chưa công khai',
    active: 'Đang hiển thị',
    closed: 'Đã đóng',
    hidden: 'Bị ẩn',

    // Verification statuses
    unverified: 'Chưa xác minh',
    verified: 'Đã xác minh',

    // Interview statuses
    scheduled: 'Đã lên lịch',
    completed: 'Đã hoàn tất',
    cancelled: 'Đã hủy',
    no_show: 'Vắng mặt',
  };
  return map[props.status] || props.status;
});

const badgeClass = computed(() => {
  const s = props.status;
  if (['verified', 'approved', 'active', 'hired', 'offered', 'completed'].includes(s)) {
    return 'badge-success';
  }
  if (['rejected', 'hidden', 'no_show', 'cancelled'].includes(s)) {
    return 'badge-danger';
  }
  if (['interview', 'unverified', 'reviewing'].includes(s)) {
    return 'badge-warning';
  }
  if (['pending', 'scheduled'].includes(s)) {
    return 'badge-info';
  }
  return 'badge-muted';
});
</script>

<style scoped>
.status-badge {
  display: inline-block;
  padding: 0.2rem 0.6rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.25;
}

.badge-success {
  background-color: #dcfce7;
  color: #166534;
}

.badge-danger {
  background-color: #fee2e2;
  color: #991b1b;
}

.badge-warning {
  background-color: #fef3c7;
  color: #92400e;
}

.badge-info {
  background-color: #e0f2fe;
  color: #075985;
}

.badge-muted {
  background-color: #f1f5f9;
  color: #475569;
}
</style>
