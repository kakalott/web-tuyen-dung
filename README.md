# Dự án Nền tảng Tuyển dụng Thông minh (AI-Powered Recruitment Platform)

Hệ thống tuyển dụng hiện đại tích hợp phân tích CV và so khớp ứng viên thông minh bằng AI.

## Cấu trúc dự án (Architecture Overview)

Dự án được phân tách theo mô hình kiến trúc phân tán / microservice chuyên biệt:

- **`frontend/`**: Ứng dụng Web Client xây dựng bằng Vue 3 + Vite + Tailwind CSS / Pinia.
- **`backend/`**: Trung tâm xử lý nghiệp vụ, API RESTful và quản trị cơ sở dữ liệu xây dựng bằng Laravel (PHP).
- **`ai-service/`**: Dịch vụ AI microservice độc lập viết bằng Python FastAPI đảm nhận trích xuất thông tin CV (NER/LLM) và vector embedding / semantic matching.
- **`database/`**: Sơ đồ dữ liệu, tài liệu thiết kế cơ sở dữ liệu quan hệ (ERD).
- **`docs/`**: Tài liệu kỹ thuật, quy chuẩn phát triển, tài liệu API (API Contract), Decision Register và giao thức đánh giá AI.

## Bắt đầu nhanh (Quick Start)

Tham khảo hướng dẫn chi tiết trong các thư mục con và tài liệu kỹ thuật tại `docs/`.
