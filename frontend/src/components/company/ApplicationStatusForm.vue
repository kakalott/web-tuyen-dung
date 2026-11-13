<template>
  <div class="status-form">
    <div class="current-badge-wrap">
      <span class="label">Hiện tại:</span>
      <StatusBadge :status="currentStatus" domain="application" />
    </div>

    <!-- Cannot change status if withdrawn or in terminal states -->
    <div v-if="isTerminal" class="terminal-notice">
      Hồ sơ đã ở trạng thái kết thúc ({{ currentStatus }}), không thể cập nhật thêm.
    </div>

    <form v-else @submit.prevent="handleSubmit" class="form-body">
      <div class="form-group">
        <label for="next-status">Chuyển sang trạng thái:</label>
        <select id="next-status" v-model="newStatus" class="select-input" required>
          <option value="" disabled>-- Chọn trạng thái mới --</option>
          <option
            v-for="opt in availableTransitions"
            :key="opt.value"
            :value="opt.value"
          >
            {{ opt.label }}
          </option>
        </select>
      </div>

      <div v-if="error" class="error-text">{{ error }}</div>

      <button
        type="submit"
        :disabled="submitting || !newStatus"
        class="btn-submit"
      >
        {{ submitting ? 'Đang cập nhật...' : 'Cập nhật trạng thái' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { applicationApi } from '@/services/applicationApi';
import StatusBadge from '@/components/common/StatusBadge.vue';

const props = defineProps({
  applicationId: {
    type: [Number, String],
    required: true,
  },
  currentStatus: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(['status-updated', 'refresh']);

const newStatus = ref('');
const submitting = ref(false);
const error = ref('');

const isTerminal = computed(() => {
  return ['hired', 'withdrawn', 'rejected'].includes(props.currentStatus);
});

// Ma trận chuyển trạng thái hợp lệ theo Business Rule
const availableTransitions = computed(() => {
  const transitions = {
    pending: [
      { value: 'reviewing', label: 'Bắt đầu xem xét (reviewing)' },
      { value: 'offered', label: 'Chào việc trực tiếp (offered)' },
      { value: 'hired', label: 'Tuyển dụng trực tiếp (hired)' },
    ],
    reviewing: [
      { value: 'offered', label: 'Chào việc (offered)' },
      { value: 'hired', label: 'Tuyển dụng (hired)' },
    ],
    interview: [
      { value: 'offered', label: 'Chào việc (offered)' },
      { value: 'hired', label: 'Tuyển dụng (hired)' },
      { value: 'reviewing', label: 'Đưa về xem xét tiếp (reviewing)' },
    ],
    offered: [
      { value: 'hired', label: 'Ứng viên nhận việc (hired)' },
      { value: 'reviewing', label: 'Từ chối / Xem xét lại (reviewing)' },
    ],
  };
  return transitions[props.currentStatus] || [];
});

const handleSubmit = async () => {
  if (!newStatus.value) return;
  submitting.value = true;
  error.value = '';
  try {
    await applicationApi.changeStatus(props.applicationId, {
      new_status: newStatus.value,
    });
    const updated = newStatus.value;
    newStatus.value = '';
    emit('status-updated', updated);
  } catch (err) {
    if (err.status === 409 || err.code === 'STATE_CONFLICT') {
      error.value = 'Dữ liệu đơn ứng tuyển đã bị thay đổi trên máy chủ (Xung đột trạng thái). Đang cập nhật dữ liệu mới nhất...';
      emit('refresh');
    } else {
      error.value = err.message || 'Không thể cập nhật trạng thái đơn.';
    }
  } finally {
    submitting.value = false;
  }
};
</script>

<style scoped>
.status-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.current-badge-wrap {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
}

.label {
  color: #64748b;
  font-weight: 500;
}

.terminal-notice {
  font-size: 0.8125rem;
  color: #64748b;
  background-color: #f8fafc;
  padding: 0.75rem;
  border-radius: 0.375rem;
  border: 1px dashed #cbd5e1;
}

.form-body {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
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

.select-input {
  padding: 0.5rem 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  background-color: #ffffff;
  outline: none;
}

.select-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

.btn-submit {
  background-color: #0f172a;
  color: #ffffff;
  padding: 0.6rem;
  border-radius: 0.375rem;
  border: none;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  transition: background-color 0.15s;
}

.btn-submit:hover:not(:disabled) {
  background-color: #1e293b;
}

.btn-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.error-text {
  color: #dc2626;
  font-size: 0.75rem;
}
</style>
