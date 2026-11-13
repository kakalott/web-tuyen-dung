<template>
  <div class="verification-review bg-white p-6 shadow rounded border">
    <h3 class="font-bold text-lg mb-4">Kết quả Xét duyệt</h3>
    
    <form @submit.prevent="submitReview">
      <div class="mb-4">
        <label class="block font-bold mb-2 text-sm text-gray-700">Trạng thái xác minh:</label>
        <select v-model="form.verification_status" class="w-full border p-2 rounded text-sm bg-white">
          <option value="unverified">Chưa xác minh (Yêu cầu bổ sung)</option>
          <option value="verified">Đã xác minh (Hợp lệ)</option>
        </select>
      </div>
      
      <div class="mb-4">
        <label class="block font-bold mb-2 text-sm text-gray-700">
          Ghi chú
          <span v-if="form.verification_status === 'unverified'" class="text-red-500">* (Bắt buộc nếu chưa xác minh)</span>:
        </label>
        <textarea
          v-model="form.note"
          class="w-full border p-2 h-24 rounded text-sm"
          :required="form.verification_status === 'unverified'"
          placeholder="Lý do từ chối hoặc yêu cầu bổ sung giấy tờ..."
        ></textarea>
      </div>
      
      <button
        type="submit"
        class="w-full bg-blue-600 text-white py-2 rounded font-bold text-sm cursor-pointer hover:bg-blue-700 transition"
        :disabled="submitting"
      >
        {{ submitting ? 'Đang lưu...' : 'Lưu kết quả' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  company: { type: Object, required: true },
  submitting: { type: Boolean, default: false }
});
const emit = defineEmits(['verify']);

const form = ref({
  verification_status: props.company.verification_status || 'unverified',
  note: props.company.verification_note || ''
});

// Reset form khi props thay đổi
watch(() => props.company, (newVal) => {
  if (newVal) {
    form.value.verification_status = newVal.verification_status || 'unverified';
    form.value.note = newVal.verification_note || '';
  }
}, { deep: true });

const submitReview = () => {
  emit('verify', { ...form.value });
};
</script>
