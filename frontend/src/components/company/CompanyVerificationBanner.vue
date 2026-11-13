<template>
  <div :class="['verification-banner', bannerClass]">
    <div class="banner-icon">{{ icon }}</div>
    <div class="banner-content">
      <h4 class="banner-title">{{ title }}</h4>
      <p class="banner-text">{{ message }}</p>
      <p v-if="note" class="banner-note">
        <strong>Ghi chú từ quản trị viên:</strong> {{ note }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: {
    type: String,
    default: 'unverified',
  },
  note: {
    type: String,
    default: '',
  },
});

const bannerClass = computed(() => {
  if (props.status === 'verified') return 'banner-verified';
  return 'banner-unverified';
});

const icon = computed(() => (props.status === 'verified' ? '✅' : '⚠️'));

const title = computed(() => {
  if (props.status === 'verified') {
    return 'Doanh nghiệp đã được xác minh';
  }
  return 'Doanh nghiệp chưa được xác minh';
});

const message = computed(() => {
  if (props.status === 'verified') {
    return 'Tài khoản của bạn đã được Admin kiểm duyệt. Bạn có toàn quyền đăng tin tuyển dụng và xem hồ sơ ứng viên.';
  }
  return 'Tài khoản chưa được xác minh. Bạn có thể cập nhật thông tin công ty, nhưng việc tạo tin tuyển dụng và xem chi tiết CV ứng viên sẽ bị giới hạn cho đến khi Admin phê duyệt.';
});
</script>

<style scoped>
.verification-banner {
  display: flex;
  gap: 1rem;
  padding: 1rem 1.25rem;
  border-radius: 0.5rem;
  border-left: 4px solid;
  margin-bottom: 1.5rem;
}

.banner-verified {
  background-color: #f0fdf4;
  border-color: #22c55e;
  color: #166534;
}

.banner-unverified {
  background-color: #fffbeb;
  border-color: #f59e0b;
  color: #92400e;
}

.banner-icon {
  font-size: 1.5rem;
  line-height: 1;
}

.banner-content {
  flex: 1;
}

.banner-title {
  font-weight: 700;
  font-size: 0.95rem;
  margin-bottom: 0.25rem;
}

.banner-text {
  font-size: 0.85rem;
  line-height: 1.4;
}

.banner-note {
  margin-top: 0.5rem;
  font-size: 0.85rem;
  padding-top: 0.5rem;
  border-top: 1px dashed rgba(0, 0, 0, 0.1);
}
</style>
