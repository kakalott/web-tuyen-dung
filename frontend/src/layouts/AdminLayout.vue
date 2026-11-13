<template>
  <div class="admin-layout">
    <header class="admin-header">
      <div class="header-content">
        <router-link to="/admin/dashboard" class="brand">Admin Center</router-link>
        <nav class="nav-links">
          <router-link to="/admin/dashboard">Dashboard</router-link>
          <router-link to="/admin/companies">Duyệt doanh nghiệp</router-link>
          <router-link to="/admin/jobs">Kiểm duyệt tin</router-link>
          <router-link to="/admin/catalog">Quản lý danh mục</router-link>
          <button @click="handleLogout" class="btn-logout">Đăng xuất</button>
        </nav>
      </div>
    </header>
    <main class="admin-main">
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
.admin-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
.admin-header {
  background: #0f172a;
  color: #f8fafc;
  padding: 1rem 2rem;
}
.brand {
  color: #38bdf8;
  font-weight: bold;
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
.nav-links a {
  color: #cbd5e1;
  text-decoration: none;
}
.nav-links a:hover {
  color: #ffffff;
}
.admin-main {
  flex: 1;
  padding: 2rem;
}
</style>
