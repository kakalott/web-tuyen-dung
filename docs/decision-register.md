# Decision Register (Sổ ghi nhận quyết định kỹ thuật)

Tài liệu này ghi nhận các Architecture Decision Records (ADR) và các quyết định kỹ thuật đang chờ chốt hoặc đã được phê duyệt trong dự án.

## Quy trình ghi nhận
- **Status**: PROPOSED | ACCEPTED | REJECTED | DEPRECATED | SUPERSEDED
- Mỗi quyết định cần nêu rõ: Ngữ cảnh (Context), Quyết định (Decision), Hệ quả (Consequences).

---

### DR-001: Lựa chọn kiến trúc phân tách Frontend, Backend & AI Service
- **Status**: ACCEPTED
- **Context**: Cần xây dựng hệ thống tuyển dụng vừa đáp ứng nghiệp vụ quản trị phức tạp, vừa xử lý nặng về NLP/AI (trích xuất CV, tính độ tương đồng vector).
- **Decision**: 
  - Frontend: Vue 3 (Composition API) + Vite để tối ưu hiệu năng và trải nghiệm người dùng.
  - Backend: Laravel đóng vai trò trung tâm nghiệp vụ (Business core), xử lý auth, thanh toán, database transaction, bảo mật file CV.
  - AI Service: Python FastAPI độc lập để tận dụng hệ sinh thái Python AI/ML (HuggingFace, PyTorch, LangChain, v.v.).
- **Consequences**: Cần thiết lập giao tiếp bảo mật nội bộ (Internal Service Token / HMAC) giữa Laravel và FastAPI.

---

### DR-002: Lưu trữ và bảo mật tài liệu CV ứng viên
- **Status**: ACCEPTED
- **Context**: File CV chứa thông tin cá nhân nhạy cảm, không được phép truy cập công khai qua public URL.
- **Decision**: Lưu trữ qua private disk (`CV_PRIVATE_DISK`), backend Laravel kiểm soát phân quyền trước khi tạo temporary signed URL hoặc stream file.
- **Consequences**: Cần cấu hình disk riêng biệt và phân quyền chặt chẽ trên Backend.
