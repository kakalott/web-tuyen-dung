# Kế hoạch và Giao thức Đánh giá Mô hình AI (AI Evaluation Protocol)

Tài liệu này xác định các tiêu chuẩn, bộ dữ liệu kiểm thử và quy trình benchmark cho các dịch vụ AI trong hệ thống tuyển dụng.

## 1. Mục tiêu đánh giá

1. **CV Parsing & Information Extraction**:
   - Trích xuất chính xác các thực thể: Thông tin cá nhân, Học vấn, Kinh nghiệm làm việc, Kỹ năng, Chứng chỉ.
2. **Semantic Matching & Job-Candidate Scoring**:
   - Đo lường mức độ tương đồng giữa Job Description (JD) và CV dựa trên Vector Embedding và Cross-Encoder.

## 2. Tiêu chí và Thước đo (Metrics)

| Tác vụ | Metric | Ngưỡng mục tiêu (Threshold) |
|---|---|---|
| Trích xuất thực thể (NER) | Precision, Recall, F1-Score | F1-Score >= 85% |
| Phân loại kỹ năng (Skills classification) | Accuracy / F1-Score | >= 90% |
| Đối sánh JD - CV (Matching) | NDCG@10, Mean Reciprocal Rank (MRR) | NDCG@10 >= 0.80 |
| Độ trễ xử lý (Latency) | P95 Latency (PDF parsing + Matching) | < 3000ms / CV |

## 3. Giao thức thử nghiệm (Evaluation Protocol)

1. **Tập dữ liệu Ground Truth**: 
   - 200+ bộ CV mẫu (tiếng Việt và tiếng Anh) với định dạng đa dạng (PDF 1 cột, 2 cột, scan text).
   - Danh sách đánh giá mức độ phù hợp JD-CV do chuyên viên HR gán nhãn thủ công (Relevance scale: 1 - 5).
2. **Quy trình chạy Benchmark**:
   - Chạy kiểm thử tự động định kỳ sau mỗi lần cập nhật model hoặc thay đổi prompt/vectorizer.
   - Ghi nhận báo cáo tại `docs/benchmarks/`.
