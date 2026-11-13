/**
 * Whitelist Điều hướng Thông báo (Notification Router Whitelist)
 * Database chỉ lưu related_type ('job', 'application', 'interview') và related_id.
 * Hàm này ánh xạ an toàn theo vai trò người dùng (userRole), ngăn chặn rò rỉ URL hoặc Open Redirect.
 */
export function resolveNotificationRoute(notification, userRole = 'candidate') {
  if (!notification) return null;
  const { related_type: type, related_id: id } = notification;
  if (!id) return null;

  if (type === 'job') {
    return userRole === 'company' ? `/company/jobs/${id}/edit` : `/jobs/${id}`;
  }
  if (type === 'application' || type === 'interview') {
    return userRole === 'company'
      ? `/company/applications/${id}`
      : `/candidate/applications`;
  }
  return null; // Bỏ qua nếu related_type nằm ngoài whitelist
}
