<template>
  <div class="interview-timeline-card">
    <div class="card-header">
      <h3 class="card-title">Lịch Phỏng vấn (Interviews)</h3>
      <button
        v-if="!showScheduleForm && canSchedule"
        @click="showScheduleForm = true"
        class="btn-new-interview"
      >
        + Lên lịch phỏng vấn
      </button>
    </div>

    <!-- Form lên lịch mới (API-INT-01) -->
    <div v-if="showScheduleForm" class="schedule-form-wrap">
      <h4 class="form-title">Tạo lịch phỏng vấn mới</h4>
      <form @submit.prevent="handleCreateSchedule" class="schedule-form">
        <div class="form-group">
          <label>Thời gian phỏng vấn <span class="required">*</span></label>
          <input
            v-model="newForm.scheduled_at"
            type="datetime-local"
            required
            class="input-field"
          />
        </div>

        <div class="form-group">
          <label>Địa điểm / Hình thức (Google Meet/Zoom/Trực tiếp) <span class="required">*</span></label>
          <input
            v-model="newForm.location_or_mode"
            type="text"
            placeholder="Ví dụ: Google Meet https://meet.google.com/..."
            required
            class="input-field"
          />
        </div>

        <div class="form-group">
          <label>Ghi chú cho ứng viên</label>
          <textarea
            v-model="newForm.notes"
            rows="2"
            placeholder="Ghi chú bổ sung..."
            class="input-field"
          ></textarea>
        </div>

        <div v-if="formError" class="error-msg">{{ formError }}</div>

        <div class="form-actions">
          <button type="button" @click="showScheduleForm = false" class="btn-cancel">
            Hủy
          </button>
          <button type="submit" :disabled="submitting" class="btn-primary">
            {{ submitting ? 'Đang tạo...' : 'Xác nhận lên lịch' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Danh sách lịch phỏng vấn -->
    <div v-if="!interviews.length" class="empty-hint">
      Chưa có buổi phỏng vấn nào được lên lịch cho đơn này.
    </div>

    <div v-else class="timeline-list">
      <div
        v-for="item in interviews"
        :key="item.id"
        class="timeline-item"
      >
        <div class="item-head">
          <span class="item-time">📅 {{ formatDatetime(item.scheduled_at) }}</span>
          <StatusBadge :status="item.status" domain="interview" />
        </div>

        <div class="item-body">
          <p class="location"><strong>Địa điểm/Hình thức:</strong> {{ item.location_or_mode }}</p>
          <p v-if="item.notes" class="notes"><strong>Ghi chú:</strong> {{ item.notes }}</p>
          <p v-if="item.cancel_reason" class="cancel-reason">
            <strong>Lý do hủy:</strong> {{ item.cancel_reason }}
          </p>
        </div>

        <!-- Thao tác nếu status = 'scheduled' -->
        <div v-if="item.status === 'scheduled'" class="item-actions">
          <button
            @click="handleComplete(item.id)"
            class="btn-action complete"
            title="Đánh dấu đã phỏng vấn thành công"
          >
            ✓ Hoàn tất
          </button>
          <button
            @click="handleNoShow(item.id)"
            class="btn-action no-show"
            title="Đánh dấu ứng viên vắng mặt"
          >
            Ứng viên vắng
          </button>
          <button
            @click="promptCancel(item.id)"
            class="btn-action cancel"
          >
            Hủy lịch
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { interviewApi } from '@/services/interviewApi';
import StatusBadge from '@/components/common/StatusBadge.vue';

const props = defineProps({
  applicationId: {
    type: [Number, String],
    required: true,
  },
  interviews: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['refresh']);

const showScheduleForm = ref(false);
const submitting = ref(false);
const formError = ref('');

const newForm = ref({
  scheduled_at: '',
  location_or_mode: '',
  notes: '',
});

// Chỉ cho phép lên lịch nếu chưa có buổi nào đang ở trạng thái 'scheduled'
const canSchedule = computed(() => {
  return !props.interviews.some((item) => item.status === 'scheduled');
});

const handleCreateSchedule = async () => {
  submitting.value = true;
  formError.value = '';
  try {
    await interviewApi.schedule(props.applicationId, {
      scheduled_at: newForm.value.scheduled_at,
      location_or_mode: newForm.value.location_or_mode,
      notes: newForm.value.notes,
    });
    showScheduleForm.value = false;
    newForm.value = { scheduled_at: '', location_or_mode: '', notes: '' };
    emit('refresh');
  } catch (err) {
    formError.value = err.message || 'Lên lịch phỏng vấn thất bại.';
  } finally {
    submitting.value = false;
  }
};

const handleComplete = async (id) => {
  if (!confirm('Xác nhận đánh dấu buổi phỏng vấn này là Đã hoàn tất?')) return;
  try {
    await interviewApi.complete(id);
    emit('refresh');
  } catch (err) {
    alert(err.message || 'Thao tác thất bại.');
  }
};

const handleNoShow = async (id) => {
  if (!confirm('Xác nhận đánh dấu ứng viên Vắng mặt (No-show)?')) return;
  try {
    await interviewApi.noShow(id);
    emit('refresh');
  } catch (err) {
    alert(err.message || 'Thao tác thất bại.');
  }
};

const promptCancel = async (id) => {
  const reason = prompt('Nhập lý do hủy lịch phỏng vấn:');
  if (reason === null) return;
  if (!reason.trim()) {
    alert('Vui lòng nhập lý do hủy lịch.');
    return;
  }
  try {
    await interviewApi.cancel(id, { cancel_reason: reason.trim() });
    emit('refresh');
  } catch (err) {
    alert(err.message || 'Hủy lịch thất bại.');
  }
};

const formatDatetime = (dt) => {
  if (!dt) return '';
  return new Date(dt).toLocaleString('vi-VN');
};
</script>

<style scoped>
.interview-timeline-card {
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.card-title {
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
}

.btn-new-interview {
  background-color: #2563eb;
  color: #ffffff;
  border: none;
  padding: 0.35rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.schedule-form-wrap {
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 0.375rem;
  padding: 1rem;
  margin-bottom: 1rem;
}

.form-title {
  font-size: 0.875rem;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 0.75rem;
}

.schedule-form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.form-group label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #475569;
}

.required {
  color: #dc2626;
}

.input-field {
  padding: 0.5rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  outline: none;
}

.input-field:focus {
  border-color: #2563eb;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.btn-primary {
  background-color: #2563eb;
  color: #ffffff;
  border: none;
  padding: 0.45rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-cancel {
  background-color: #e2e8f0;
  color: #475569;
  border: none;
  padding: 0.45rem 0.85rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  cursor: pointer;
}

.error-msg {
  color: #dc2626;
  font-size: 0.75rem;
}

.empty-hint {
  font-size: 0.85rem;
  color: #94a3b8;
  font-style: italic;
  padding: 0.5rem 0;
}

.timeline-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.timeline-item {
  border: 1px solid #e2e8f0;
  border-radius: 0.375rem;
  padding: 0.875rem;
  background-color: #f8fafc;
}

.item-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.item-time {
  font-weight: 700;
  font-size: 0.875rem;
  color: #0f172a;
}

.item-body {
  font-size: 0.8125rem;
  color: #334155;
  line-height: 1.4;
}

.cancel-reason {
  color: #dc2626;
  margin-top: 0.25rem;
}

.item-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.75rem;
  padding-top: 0.5rem;
  border-top: 1px dashed #cbd5e1;
}

.btn-action {
  border: none;
  padding: 0.25rem 0.6rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-action.complete {
  background-color: #dcfce7;
  color: #166534;
}

.btn-action.no-show {
  background-color: #fef3c7;
  color: #92400e;
}

.btn-action.cancel {
  background-color: #fee2e2;
  color: #991b1b;
}
</style>
