import { useAuthStore } from '@/stores/authStore';

export async function ensureAuth(to, from, next) {
  const authStore = useAuthStore();

  // Khởi tạo auth context lần đầu tiên nếu chưa load
  if (!authStore.initialized) {
    await authStore.loadMe();
  }

  // 1. Chặn người dùng chưa đăng nhập vào các route bảo vệ
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return next({
      path: '/login',
      query: { redirect: to.fullPath },
    });
  }

  // 2. Chặn người dùng đã đăng nhập quay lại các trang guest-only như /login, /register
  if (to.meta.guestOnly && authStore.isAuthenticated) {
    if (authStore.role === 'admin') return next('/admin/dashboard');
    if (authStore.role === 'company') return next('/company/jobs');
    return next('/jobs');
  }

  // 3. Kiểm tra vai trò RBAC
  if (to.meta.roles && Array.isArray(to.meta.roles)) {
    if (!authStore.role || !to.meta.roles.includes(authStore.role)) {
      return next('/403');
    }
  }

  next();
}
