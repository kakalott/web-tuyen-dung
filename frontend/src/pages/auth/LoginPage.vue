<template>
  <div class="login-page">
    <div class="card">
      <h2 class="title">Đăng nhập</h2>
      <p class="subtitle">Đăng nhập để truy cập tài khoản của bạn</p>

      <div v-if="errorMessage" class="error-banner" role="alert">
        {{ errorMessage }}
      </div>

      <form @submit.prevent="handleLogin" class="form">
        <div class="form-group">
          <label for="email">Email</label>
          <input
            id="email"
            type="email"
            v-model="form.email"
            placeholder="example@domain.com"
            required
            autocomplete="email"
          />
        </div>

        <div class="form-group">
          <label for="password">Mật khẩu</label>
          <input
            id="password"
            type="password"
            v-model="form.password"
            placeholder="••••••••"
            required
            autocomplete="current-password"
          />
        </div>

        <button type="submit" class="btn-submit" :disabled="submitting">
          {{ submitting ? 'Đang xử lý...' : 'Đăng nhập' }}
        </button>
      </form>

      <div class="footer-links">
        <span>Chưa có tài khoản?</span>
        <router-link to="/register">Đăng ký ngay</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { authApi } from '@/services/authApi';
import { useAuthStore } from '@/stores/authStore';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const form = ref({ email: '', password: '' });
const submitting = ref(false);
const errorMessage = ref('');

const handleLogin = async () => {
  submitting.value = true;
  errorMessage.value = '';

  try {
    // 1. Gọi API-AUTH-02 để xác thực
    await authApi.login(form.value);

    // 2. Gọi API-AUTH-04 qua Store để nạp thông tin user
    await authStore.loadMe();

    // 3. Nếu có redirect query param thì ưu tiên redirect
    const redirectUrl = route.query.redirect;
    if (redirectUrl && typeof redirectUrl === 'string') {
      return router.push(redirectUrl);
    }

    // 4. Điều hướng mặc định dựa trên role
    if (authStore.role === 'candidate') {
      router.push('/jobs');
    } else if (authStore.role === 'company') {
      router.push('/company/jobs');
    } else if (authStore.role === 'admin') {
      router.push('/admin/dashboard');
    } else {
      router.push('/jobs');
    }
  } catch (error) {
    // Hiển thị thông báo chung theo yêu cầu bảo mật thay vì chỉ ra sai email hay mật khẩu
    errorMessage.value = error.message || 'Thông tin đăng nhập không chính xác hoặc tài khoản đã bị khóa.';
  } finally {
    submitting.value = false;
  }
};
</script>

<style scoped>
.login-page {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 2rem 1rem;
}

.card {
  width: 100%;
  max-width: 440px;
  background: #ffffff;
  padding: 2.5rem;
  border-radius: 0.75rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 0.5rem;
  text-align: center;
}

.subtitle {
  font-size: 0.875rem;
  color: #64748b;
  margin-bottom: 1.5rem;
  text-align: center;
}

.error-banner {
  background-color: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
  padding: 0.75rem 1rem;
  border-radius: 0.375rem;
  margin-bottom: 1.25rem;
  font-size: 0.875rem;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.form-group label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #334155;
}

.form-group input {
  padding: 0.625rem 0.875rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  outline: none;
  transition: border-color 0.15s;
}

.form-group input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.btn-submit {
  background-color: #2563eb;
  color: #ffffff;
  font-weight: 600;
  padding: 0.75rem;
  border-radius: 0.375rem;
  border: none;
  cursor: pointer;
  font-size: 0.875rem;
  margin-top: 0.5rem;
  transition: background-color 0.15s;
}

.btn-submit:hover:not(:disabled) {
  background-color: #1d4ed8;
}

.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.footer-links {
  margin-top: 1.5rem;
  text-align: center;
  font-size: 0.875rem;
  color: #64748b;
  display: flex;
  justify-content: center;
  gap: 0.375rem;
}

.footer-links a {
  color: #2563eb;
  text-decoration: none;
  font-weight: 500;
}

.footer-links a:hover {
  text-decoration: underline;
}
</style>
