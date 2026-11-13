<template>
  <div class="cv-access-buttons">
    <button
      @click="handleDownload"
      :disabled="downloading"
      class="btn-download"
    >
      {{ downloading ? 'Đang chuẩn bị...' : '📄 Xem & Tải CV gốc' }}
    </button>
    <p v-if="error" class="error-text">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { applicationApi } from '@/services/applicationApi';

const props = defineProps({
  applicationId: {
    type: [Number, String],
    required: true,
  },
});

const downloading = ref(false);
const error = ref('');

const handleDownload = async () => {
  downloading.value = true;
  error.value = '';
  try {
    await applicationApi.downloadCv(props.applicationId);
  } catch (err) {
    error.value = err.message || 'Không thể tải file CV.';
  } finally {
    downloading.value = false;
  }
};
</script>

<style scoped>
.cv-access-buttons {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.btn-download {
  background-color: #2563eb;
  color: #ffffff;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  border: none;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s;
}

.btn-download:hover:not(:disabled) {
  background-color: #1d4ed8;
}

.btn-download:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.error-text {
  color: #dc2626;
  font-size: 0.75rem;
  margin-top: 0.25rem;
}
</style>
