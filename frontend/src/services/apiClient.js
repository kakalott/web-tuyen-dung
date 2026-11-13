const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1';
const ROOT_URL = API_BASE_URL.replace(/\/api\/v1\/?$/, '');

function getCookie(name) {
  const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
  return match ? decodeURIComponent(match[3]) : null;
}

async function ensureCsrfCookie() {
  if (!getCookie('XSRF-TOKEN')) {
    await fetch(`${ROOT_URL}/sanctum/csrf-cookie`, {
      method: 'GET',
      credentials: 'include',
      headers: { Accept: 'application/json' },
    });
  }
}

export const apiClient = {
  async request(method, endpoint, payload = null, options = {}) {
    const upperMethod = method.toUpperCase();
    const isMutation = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(upperMethod);

    // Bootstrap CSRF cookie trước các thao tác ghi dữ liệu
    if (isMutation) {
      await ensureCsrfCookie();
    }

    const headers = {
      Accept: 'application/json',
      ...options.headers,
    };

    const xsrfToken = getCookie('XSRF-TOKEN');
    if (xsrfToken) {
      headers['X-XSRF-TOKEN'] = xsrfToken;
    }

    let body = payload;
    if (payload && !(payload instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(payload);
    }

    const response = await fetch(`${API_BASE_URL}${endpoint}`, {
      method: upperMethod,
      headers,
      body,
      credentials: 'include', // Bắt buộc gửi kèm session cookie
      signal: options.signal,
    });

    const data = await response.json().catch(() => null);

    if (!response.ok) {
      throw {
        status: response.status,
        ...(data?.error || { code: 'UNKNOWN', message: 'Đã xảy ra lỗi không xác định.' }),
      };
    }

    return data;
  },

  async download(endpoint, fallbackFilename = 'document.pdf') {
    const response = await fetch(`${API_BASE_URL}${endpoint}`, {
      method: 'GET',
      headers: {
        Accept: 'application/pdf, application/json',
      },
      credentials: 'include', // Gửi kèm cookie xác thực Sanctum
    });

    // Nếu server trả lỗi (401, 403, 404, 502), response sẽ là JSON envelope
    if (!response.ok) {
      const errData = await response.json().catch(() => null);
      throw {
        status: response.status,
        ...(errData?.error || {
          code: 'DOWNLOAD_FAILED',
          message: 'Không thể tải tệp CV hoặc bạn không có quyền truy cập.',
        }),
      };
    }

    // Đọc dữ liệu nhị phân PDF dưới dạng Blob
    const blob = await response.blob();

    // Trích xuất filename từ Content-Disposition nếu có
    const disposition = response.headers.get('Content-Disposition') || '';
    const filenameMatch = disposition.match(/filename\*?=(?:UTF-8''|")?([^";\n]+)"?/i);
    const filename = filenameMatch
      ? decodeURIComponent(filenameMatch[1].trim())
      : fallbackFilename;

    // Kích hoạt tải xuống an toàn qua Blob URL tạm thời
    const blobUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = blobUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    // Giải phóng bộ nhớ trình duyệt sau 10 giây
    setTimeout(() => window.URL.revokeObjectURL(blobUrl), 10000);
    return blobUrl;
  },
};
