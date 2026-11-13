# API Contract - Quy ước chung của 68 API Endpoints

Tài liệu này tóm tắt chuẩn giao tiếp, định dạng phản hồi chuẩn, mã lỗi và các nhóm endpoint trong hệ thống.

## 1. Chuẩn giao thức & Định dạng chung

- **Base URL**: `/api/v1`
- **Format**: JSON (`Content-Type: application/json`, `Accept: application/json`)
- **Authentication**: Bearer Token (Laravel Sanctum / JWT)
- **Standard Response Structure**:
  ```json
  {
    "success": true,
    "data": {},
    "message": "Thông báo nếu có",
    "meta": {}
  }
  ```
- **Standard Error Structure**:
  ```json
  {
    "success": false,
    "error": {
      "code": "ERROR_CODE",
      "message": "Mô tả lỗi chi tiết",
      "details": []
    }
  }
  ```

---

## 2. Phân nhóm 68 Endpoints

1. **Authentication & Authorization (10 endpoints)**: Đăng ký, đăng nhập (Ứng viên / Nhà tuyển dụng / Admin), quên mật khẩu, làm mới token, xác thực 2FA.
2. **User & Profile Management (8 endpoints)**: Quản lý hồ sơ cá nhân, đổi mật khẩu, avatar, thiết lập thông báo.
3. **Candidate CV & Experience (8 endpoints)**: Tải lên CV, quản lý danh sách CV, xem CV chi tiết, cập nhật kỹ năng, học vấn, kinh nghiệm.
4. **Job Posting & Management (12 endpoints)**: Đăng tin tuyển dụng, sửa/xóa/đóng tin, duyệt tin (Admin), tìm kiếm & lọc tin tuyển dụng.
5. **Job Application & Tracking (8 endpoints)**: Nộp hồ sơ ứng tuyển, quản lý hồ sơ đã nộp, cập nhật trạng thái ứng viên (Nhận hồ sơ, Phỏng vấn, Từ chối, Nhận việc).
6. **AI Matching & CV Parsing (6 endpoints)**: Yêu cầu trích xuất CV tự động, tính điểm matching giữa Job và CV, gợi ý việc làm phù hợp cho ứng viên, gợi ý ứng viên cho tin tuyển dụng.
7. **Employer & Company Management (6 endpoints)**: Quản lý hồ sơ công ty, xác minh doanh nghiệp, quản lý thành viên đội ngũ tuyển dụng.
8. **Admin & System Operations (10 endpoints)**: Quản lý người dùng, báo cáo thống kê, cấu hình hệ thống, audit logs.
