# Dự án Nền tảng Tuyển dụng Thông minh (AI-Powered Recruitment Platform)

Hệ thống tuyển dụng hiện đại tích hợp phân tích CV tự động và so khớp ứng viên thông minh bằng AI, được xây dựng theo kiến trúc phân tán (decoupled/microservice) với 3 tầng độc lập: Vue 3 Single Page Application (SPA), Laravel 11 Core API và Python FastAPI AI Microservice.

---

## 1. Cấu trúc Kiến trúc (Architecture Overview)

* **`frontend/` (Cổng 5173):** Ứng dụng Web Client SPA xây dựng bằng **Vue 3 (Composition API) + Vite + Tailwind CSS + Pinia**. Đảm nhận giao diện responsive cho cả 3 phân hệ người dùng (Candidate, Company, Admin), xử lý upload multipart, stream binary PDF an toàn và reactive notifications.
* **`backend/` (Cổng 8000):** Trung tâm xử lý nghiệp vụ lõi (Business Core) xây dựng bằng **Laravel 11 (PHP 8.2+)**. Quản lý dữ liệu quan hệ MySQL, xác thực trạng thái Sanctum SPA, phân quyền RBAC, kiểm duyệt tin tuyển dụng, workflow đơn ứng tuyển & lịch phỏng vấn, và bảo vệ đĩa lưu trữ CV riêng tư (`private_cvs`).
* **`ai-service/` (Cổng 8001):** Microservice trí tuệ nhân tạo độc lập viết bằng **Python FastAPI + Sentence-Transformers (`all-MiniLM-L6-v2`) + PyMuPDF**. Chịu trách nhiệm trích xuất văn bản từ CV PDF, nhận diện kỹ năng theo danh mục chuẩn (Skill NER / Catalog Matching), và tính toán độ tương đồng ngữ nghĩa (Cosine Semantic Similarity + TF-IDF Baseline + Skill Coverage).
* **`database/`:** Bản vẽ thiết kế cơ sở dữ liệu quan hệ (ERD 23 bảng lõi) và kịch bản khởi tạo.
* **`docs/`:** Tài liệu kỹ thuật chi tiết: `api-contract.md` (68 endpoints), `decision-register.md` (14 Architecture Decision Records `D-01..D-14`), và `ai-evaluation.md`.

---

## 2. Chuẩn bị Dữ liệu & Khởi động 4 Dịch vụ

### Bước 2.1: Khởi tạo Cơ sở Dữ liệu Sạch & Nạp Dữ liệu Mẫu

Đảm bảo dịch vụ **MySQL Server** đang hoạt động (database: `recruitment_ai`), mở terminal tại thư mục gốc dự án và thực hiện:

```bash
cd backend
php artisan migrate:fresh --seed
```

> **Ghi chú:** Lệnh trên tự động tạo lập 23 bảng lõi, nạp danh mục tuyển dụng chuẩn (kỹ năng, ngành nghề, hình thức làm việc, cấp bậc) và khởi tạo sẵn tài khoản Quản trị viên hệ thống:
> * **Email Quản trị (`Admin`):** `admin@recruitment.local`
> * **Mật khẩu:** `Password123!@#`

---

### Bước 2.2: Khởi động Song song 3 Tầng Ứng dụng

Mở **3 cửa sổ Terminal** riêng biệt để chạy đồng thời các dịch vụ:

#### 🔹 Terminal 1 — Python AI Service (Cổng 8001)
```bash
cd ai-service
# Kích hoạt môi trường ảo (nếu chưa kích hoạt): .\venv\Scripts\activate
uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload
```
*(Chờ thông báo nạp mô hình Sentence-Transformers thành công và service sẵn sàng tại `http://127.0.0.1:8001`)*.

#### 🔹 Terminal 2 — Laravel Backend API (Cổng 8000)
```bash
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```
*(API Backend hoạt động tại `http://localhost:8000/api/v1`)*.

#### 🔹 Terminal 3 — Vue 3 Frontend (Cổng 5173)
```bash
cd frontend
npm run dev
```
*(Giao diện người dùng sẵn sàng tại `http://localhost:5173`)*.

> [!TIP]
> **Chuẩn bị trước khi demo:** Chuẩn bị sẵn **1 file CV định dạng `.pdf` có text layer** (file PDF xuất từ Word hoặc Canva, không dùng ảnh chụp/scan không có chữ) để thử nghiệm tính năng trích xuất AI.

---

## 3. Kịch bản Demo 5 Luồng Nghiệp vụ End-to-End (3 Vai trò)

### 📌 Luồng 1: Đăng ký & Phân quyền (Auth & RBAC)

1. **Đăng ký tài khoản mới:** Truy cập `http://localhost:5173/register` (`P-AUTH-01`), lần lượt đăng ký:
   * **1 tài khoản Ứng viên (`Candidate`)**
   * **1 tài khoản Doanh nghiệp (`Company`)**
2. **Kiểm tra Phân quyền (RBAC Protection):**
   * Đăng nhập với tư cách **Ứng viên** hoặc **Doanh nghiệp**, thử nhập trực tiếp URL Quản trị `/admin/dashboard` trên thanh địa chỉ.
   * Hệ thống lập tức kích hoạt Router Guard và xử lý API 403 Forbidden, chặn điều hướng trái phép và hiển thị thông báo lỗi.

---

### 📌 Luồng 2: Chuẩn bị Hồ sơ & Trích xuất CV bằng AI (Admin + Candidate)

1. **Đăng nhập Quản trị viên (`Admin`):**
   * Đăng nhập bằng tài khoản `admin@recruitment.local` / `Password123!@#`.
   * Truy cập `/admin/catalog/skills`, thêm một kỹ năng chuẩn mới (ví dụ: `FastAPI`) và thêm từ khóa viết tắt/đồng nghĩa (`alias`, ví dụ: `Fast API`).
2. **Đăng nhập Ứng viên (`Candidate`):**
   * Vào `/candidate/profile`: cập nhật hồ sơ cá nhân, thêm 1 bản ghi Học vấn và 1 bản ghi Kinh nghiệm làm việc.
   * Vào `/candidate/skills`: tìm kiếm kỹ năng theo từ khóa (hỗ trợ tìm cả alias vừa tạo) và lưu vào hồ sơ.
   * Vào `/candidate/cvs` (`P-CAN-02`): tải lên file CV PDF chuẩn bị sẵn. Backend chuyển file sang Python AI Service qua multipart binary stream, trích xuất text layer và tự động gán nhãn các kỹ năng tìm thấy.
   * Xem kết quả phân tích: Ứng viên kiểm tra kỹ năng trích xuất, bổ sung/chỉnh sửa nếu muốn và bấm **Xác nhận (`Confirm`)**. CV chuyển sang trạng thái đã xác nhận, mở khóa quyền sử dụng để ứng tuyển và gợi ý việc làm.

---

### 📌 Luồng 3: Xác minh Doanh nghiệp & Kiểm duyệt Tin Tuyển dụng (Company + Admin)

1. **Đăng nhập Nhà tuyển dụng (`Company`):**
   * Vào `/company/profile`: cập nhật thông tin công ty, địa chỉ, website.
   * Thử vào `/company/jobs/create` tạo tin tuyển dụng ngay: Hệ thống chặn lại với thông báo công ty chưa được xác minh danh tính (`unverified`).
2. **Quản trị viên (`Admin`) duyệt Doanh nghiệp:**
   * Chuyển sang tài khoản Admin, vào `/admin/companies` (`P-ADM-04`).
   * Xem hồ sơ công ty và bấm nút **Xác minh (`Verify`)** $\rightarrow$ Doanh nghiệp chuyển sang trạng thái `verified`.
3. **Doanh nghiệp đăng tin tuyển dụng:**
   * Quay lại tài khoản Doanh nghiệp, vào `/company/jobs/create`: tạo tin tuyển dụng chi tiết (tiêu đề, địa điểm, mức lương, hạn nộp, kỹ năng bắt buộc `required` và kỹ năng ưu tiên `preferred`).
   * Tin sau khi tạo ở trạng thái `pending` (chờ duyệt). Mở tab ẩn danh truy cập `/jobs` (trang tìm việc công khai) sẽ **chưa thấy** tin này xuất hiện.
4. **Quản trị viên duyệt tin:**
   * Tài khoản Admin vào `/admin/jobs` (`P-ADM-02`), duyệt tin tuyển dụng (`Approve`).
   * Tin chuyển sang trạng thái `approved` + `active` và lập tức hiển thị công khai tại `/jobs`.

---

### 📌 Luồng 4: Gợi ý Việc làm AI, Ứng tuyển & Xem CV Bảo mật (Candidate + Company)

1. **Ứng viên nhận Gợi ý Việc làm thông minh (AI Recommendations):**
   * Đăng nhập Ứng viên, vào `/candidate/recommendations` (`P-CAN-04`), chọn bản CV đã xác nhận.
   * Hệ thống gọi Python AI Service so khớp ngữ nghĩa đa tầng và hiển thị danh sách việc làm phù hợp kèm bảng phân rã điểm số chi tiết:
     * **Baseline Score (TF-IDF):** Độ tương đồng từ khóa cơ bản.
     * **Semantic Similarity (Vector Embedding):** Độ tương đồng ngữ nghĩa sâu từ mô hình AI.
     * **Skill Coverage:** Tỷ lệ bao phủ kỹ năng bắt buộc và kỹ năng ưu tiên.
     * Danh sách kỹ năng khớp (`Matched Skills`) và kỹ năng còn thiếu (`Missing Skills`).
2. **Ứng tuyển & Chống nộp trùng (409 Conflict):**
   * Nhấp vào tin tuyển dụng vừa duyệt, chọn CV đã xác nhận và bấm **Ứng tuyển ngay**.
   * Thử bấm ứng tuyển lại lần 2 vào cùng tin tuyển dụng để chứng minh cơ chế khóa nộp trùng đơn (`409 Conflict`).
3. **Nhà tuyển dụng quản lý ứng viên & Tải CV Bảo mật:**
   * Đăng nhập Doanh nghiệp, kiểm tra chuông thông báo nhận thông báo ứng viên mới nộp hồ sơ.
   * Vào danh sách ứng viên của tin tuyển dụng (`P-COM-04`), sử dụng bộ lọc sắp xếp **Điểm phù hợp AI giảm dần (`match_desc`)**.
   * Bấm vào chi tiết hồ sơ ứng viên và chọn **Xem / Tải CV**: Trình diễn luồng tải file PDF nhị phân bảo mật từ đĩa riêng tư `private_cvs` (không để lộ đường dẫn tĩnh của file).
   * Chuyển trạng thái đơn ứng tuyển từ `pending` $\rightarrow$ `reviewing` (Đang xem xét).

---

### 📌 Luồng 5: Lên lịch Phỏng vấn, Thông báo & Thống kê Thời gian thực (Full Workflow)

1. **Doanh nghiệp lên lịch Phỏng vấn:**
   * Tại chi tiết đơn ứng tuyển (`P-COM-05`), tạo một lịch phỏng vấn mới (thời gian, hình thức Online/Offline, ghi chú).
   * Đơn ứng tuyển tự động chuyển trạng thái sang `interview` và hiển thị trên dòng thời gian (Interview Timeline).
   * Doanh nghiệp đánh dấu hoàn thành phỏng vấn (`completed`), sau đó cập nhật kết quả đơn sang **Trúng tuyển (`passed`)**.
2. **Ứng viên theo dõi kết quả:**
   * Đăng nhập Ứng viên, kiểm tra biểu tượng chuông thông báo: nhận các thông báo về lịch phỏng vấn và kết quả đơn ứng tuyển.
   * Bấm nút **Đánh dấu tất cả đã đọc**.
3. **Quản trị viên theo dõi Dashboard thống kê:**
   * Đăng nhập Admin, truy cập `/admin/dashboard` (`P-ADM-01`).
   * Xem 4 thẻ số liệu tổng quan thời gian thực được truy vấn trực tiếp từ MySQL: Tổng số người dùng, Doanh nghiệp chờ xác minh, Tin tuyển dụng chờ duyệt, và Tổng số đơn ứng tuyển trên toàn hệ thống.

---

## 4. Bộ Lệnh Kiểm chứng Tự động (Automated Test Verification)

Toàn bộ hệ thống đã được kiểm thử tự động toàn diện và đạt **100% PASS** trước khi bàn giao:

### 1. Kiểm thử Python AI Microservice (4 files `pytest`)
```bash
cd ai-service
pytest -v
```
> **Kết quả:** `13 passed in 1.90s` (Bao phủ: `test_auth.py`, `test_extraction.py`, `test_matching.py`, `test_contracts.py`).

### 2. Kiểm thử Laravel Backend Core (17 test suites: 13 Feature + 4 Unit)
```bash
cd backend
php artisan test
```
> **Kết quả:** `62 passed (585 assertions)` (Bao phủ toàn bộ 11 nhóm nghiệp vụ: Auth, Candidate, CV, Company, Job, Application, Interview, Matching, Notification, AdminCatalog, Dashboard, Authorization IDOR, và FullSystemE2E).

### 3. Kiểm tra Biên dịch Frontend Production
```bash
cd frontend
npm run build
```
> **Kết quả:** Vite biên dịch thành công 125 modules sang `dist/` trong 1.41s, 0 lỗi cú pháp, 0 cảnh báo.
