import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/authStore';

const app = createApp(App);

const pinia = createPinia();
app.use(pinia);
app.use(router);

// Lắng nghe sự kiện 401 Unauthorized từ apiClient để đồng bộ đăng xuất & điều hướng
window.addEventListener('auth:unauthorized', () => {
  const authStore = useAuthStore();
  authStore.clear();
  const currentPath = router.currentRoute.value.fullPath;
  if (!currentPath.startsWith('/login') && !currentPath.startsWith('/register')) {
    router.push({ path: '/login', query: { redirect: currentPath } });
  }
});

app.mount('#app');
