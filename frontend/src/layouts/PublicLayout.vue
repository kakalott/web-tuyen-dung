<template>
  <div class="public-layout">
    <header class="public-header">
      <div class="container header-container">
        <router-link to="/jobs" class="brand">
          <span class="brand-title">Hệ thống Tuyển dụng AI</span>
        </router-link>
        <nav class="public-nav">
          <router-link to="/jobs" class="nav-link">Việc làm</router-link>
          <template v-if="authStore.isAuthenticated">
            <span class="user-greeting">Xin chào, {{ authStore.user?.name }}</span>
            <button @click="handleLogout" class="btn-logout">Đăng xuất</button>
          </template>
          <template v-else>
            <router-link to="/login" class="nav-link">Đăng nhập</router-link>
            <router-link to="/register" class="nav-link btn-register">Đăng ký</router-link>
          </template>
        </nav>
      </div>
    </header>

    <main class="public-main">
      <router-view />
    </main>

    <footer class="public-footer">
      <div class="container footer-container">
        <p>&copy; 2026 Hệ thống Tuyển dụng AI Decoupled. Mọi quyền được bảo lưu.</p>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';

const router = useRouter();
const authStore = useAuthStore();

const handleLogout = async () => {
  await authStore.logout();
  router.push('/login');
};
</script>

<style scoped>
.public-layout {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background-color: #f8fafc;
  color: #1e293b;
  font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1rem;
}

.public-header {
  background-color: #ffffff;
  border-bottom: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.header-container {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 4rem;
}

.brand {
  text-decoration: none;
  font-size: 1.25rem;
  font-weight: 700;
  color: #2563eb;
}

.public-nav {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.nav-link {
  text-decoration: none;
  color: #475569;
  font-weight: 500;
  transition: color 0.15s;
}

.nav-link:hover {
  color: #2563eb;
}

.btn-register {
  background-color: #2563eb;
  color: #ffffff !important;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
}

.btn-logout {
  background: transparent;
  border: 1px solid #cbd5e1;
  color: #475569;
  padding: 0.375rem 0.75rem;
  border-radius: 0.375rem;
  cursor: pointer;
}

.btn-logout:hover {
  background-color: #f1f5f9;
}

.user-greeting {
  font-size: 0.875rem;
  color: #64748b;
}

.public-main {
  flex: 1;
  padding: 2rem 0;
}

.public-footer {
  background-color: #ffffff;
  border-top: 1px solid #e2e8f0;
  padding: 1.5rem 0;
  text-align: center;
  font-size: 0.875rem;
  color: #64748b;
}
</style>
