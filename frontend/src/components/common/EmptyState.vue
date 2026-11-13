<template>
  <div class="empty-state">
    <div class="empty-icon">📭</div>
    <p class="empty-text">{{ text }}</p>
  </div>
</template>

<script setup>
defineProps({
  text: {
    type: String,
    default: 'Không có dữ liệu hiển thị.',
  },
});
</script>

<style scoped>
.empty-state {
  padding: 3rem 1rem;
  text-align: center;
  color: #64748b;
  background-color: #ffffff;
  border: 1px dashed #cbd5e1;
  border-radius: 0.5rem;
  margin: 1rem 0;
}

.empty-icon {
  font-size: 2.5rem;
  margin-bottom: 0.5rem;
}

.empty-text {
  font-size: 0.95rem;
}
</style>
