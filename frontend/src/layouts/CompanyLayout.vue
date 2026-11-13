<template>
  <div class="company-layout">
    <header class="company-header">
      <div class="header-content">
        <router-link to="/company/jobs" class="brand">Employer Portal</router-link>
        <nav class="nav-links">
          <router-link to="/company/jobs">Tin tuyển dụng</router-link>
          <router-link to="/company/profile">Hồ sơ doanh nghiệp</router-link>
          <button @click="handleLogout" class="btn-logout">Đăng xuất</button>
        </nav>
      </div>
    </header>
    <main class="company-main">
      <router-view />
    </main>
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
.company-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
.company-header {
  background: #ffffff;
  border-bottom: 1px solid #e2e8f0;
  padding: 1rem 2rem;
}
.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.nav-links {
  display: flex;
  gap: 1.25rem;
  align-items: center;
}
.company-main {
  flex: 1;
  padding: 2rem;
}
</style>
