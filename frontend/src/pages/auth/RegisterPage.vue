<template>
  <div class="register-page">
    <div class="card">
      <h2 class="title">Đăng ký tài khoản</h2>
      <p class="subtitle">Bắt đầu kết nối việc làm và ứng viên thông minh cùng AI</p>

      <div v-if="errorMessage" class="error-banner" role="alert">
        {{ errorMessage }}
      </div>

      <form @submit.prevent="handleRegister" class="form">
        <div class="form-group">
          <label for="name">Họ và tên / Tên Doanh nghiệp</label>
          <input
            id="name"
            type="text"
            v-model="form.name"
            placeholder="Nguyễn Văn A hoặc Tên Công ty"
            required
            autocomplete="name"
          />
          <span v-if="fieldErrors.name" class="field-error">
            {{ fieldErrors.name[0] }}
          </span>
        </div>

        <div class="form-group">
          <label for="email">Địa chỉ Email</label>
          <input
            id="email"
            type="email"
            v-model="form.email"
            placeholder="example@domain.com"
            required
            autocomplete="email"
          />
          <span v-if="fieldErrors.email" class="field-error">
            {{ fieldErrors.email[0] }}
          </span>
        </div>

        <div class="form-group">
          <label for="password">Mật khẩu</label>
          <input
            id="password"
            type="password"
            v-model="form.password"
            placeholder="Tối thiểu 8 ký tự, gồm chữ hoa, số và ký tự đặc biệt"
            required
            autocomplete="new-password"
          />
          <span v-if="fieldErrors.password" class="field-error">
            {{ fieldErrors.password[0] }}
          </span>
        </div>

        <div class="form-group">
          <label for="role">Loại tài khoản</label>
          <!-- Tuyệt đối ẨN vai trò admin theo đặc tả an toàn hệ thống -->
          <select id="role" v-model="form.role" required>
            <option value="candidate">Ứng viên tìm việc</option>
            <option value="company">Nhà tuyển dụng / Doanh nghiệp</option>
          </select>
          <span v-if="fieldErrors.role" class="field-error">
            {{ fieldErrors.role[0] }}
          </span>
        </div>

        <button type="submit" class="btn-submit" :disabled="submitting">
          {{ submitting ? 'Đang xử lý...' : 'Đăng ký' }}
        </button>
      </form>

      <div class="footer-links">
        <span>Đã có tài khoản?</span>
        <router-link to="/login">Đăng nhập</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { authApi } from '@/services/authApi';

const router = useRouter();

const form = ref({
  name: '',
  email: '',
  password: '',
  role: 'candidate',
});
const submitting = ref(false);
const errorMessage = ref('');
const fieldErrors = ref({});

const handleRegister = async () => {
  submitting.value = true;
  errorMessage.value = '';
  fieldErrors.value = {};

  try {
    await authApi.register(form.value);

    // Chuyển về màn hình đăng nhập kèm thông báo
    router.push({
      path: '/login',
      query: { registered: 'true' },
    });
  } catch (error) {
    if (error.status === 422 && error.fields) {
      fieldErrors.value = error.fields;
    } else {
      errorMessage.value = error.message || 'Đăng ký không thành công. Vui lòng thử lại.';
    }
  } finally {
    submitting.value = false;
  }
};
</script>

<style scoped>
.register-page {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 2rem 1rem;
}

.card {
  width: 100%;
  max-width: 480px;
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

.form-group input,
.form-group select {
  padding: 0.625rem 0.875rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  outline: none;
  background-color: #ffffff;
  transition: border-color 0.15s;
}

.form-group input:focus,
.form-group select:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.field-error {
  color: #dc2626;
  font-size: 0.75rem;
  margin-top: 0.125rem;
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
