<template>
  <div class="candidate-layout">
    <header class="candidate-header">
      <div class="header-content">
        <router-link to="/jobs" class="brand">Candidate Portal</router-link>
        <nav class="nav-links">
          <router-link to="/jobs">Tìm việc làm</router-link>
          <router-link to="/candidate/profile">Hồ sơ của tôi</router-link>
          <router-link to="/candidate/applications">Đơn ứng tuyển</router-link>
          <button @click="handleLogout" class="btn-logout">Đăng xuất</button>
        </nav>
      </div>
    </header>
    <main class="candidate-main">
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
.candidate-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
.candidate-header {
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
.btn-logout {
  cursor: pointer;
}
.candidate-main {
  flex: 1;
  padding: 2rem;
}
</style>
