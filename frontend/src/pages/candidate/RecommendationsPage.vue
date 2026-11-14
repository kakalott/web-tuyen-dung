<template>
  <div class="recommendations-page">
    <h1 class="page-title">AI gợi ý việc làm</h1>
    <div class="card empty-container">
      <div class="icon-wrap">🤖</div>
      <h3 class="empty-title">Tính năng đang được cập nhật</h3>
      <p class="empty-desc">
        Tính năng gợi ý việc làm thông minh bằng AI đang được tối ưu hóa mô hình và sẽ sớm ra mắt trong phiên bản tiếp theo.
      </p>
      <router-link to="/jobs" class="btn-explore">
        Khám phá việc làm hiện có →
      </router-link>
    </div>
  </div>
</template>

<script setup>
// Tạm ngắt lệnh gọi API-AI-01 để chờ cập nhật giao diện
</script>

<style scoped>
.recommendations-page {
  max-width: 900px;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 1.5rem;
}

.card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
}

.empty-container {
  padding: 3.5rem 2rem;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
}

.icon-wrap {
  font-size: 3rem;
  margin-bottom: 0.5rem;
}

.empty-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.empty-desc {
  color: #64748b;
  font-size: 0.9375rem;
  max-width: 480px;
  line-height: 1.5;
  margin: 0 0 1rem 0;
}

.btn-explore {
  display: inline-flex;
  align-items: center;
  background-color: #2563eb;
  color: #ffffff;
  font-weight: 500;
  font-size: 0.875rem;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  text-decoration: none;
  transition: background-color 0.15s;
}

.btn-explore:hover {
  background-color: #1d4ed8;
}
</style>
