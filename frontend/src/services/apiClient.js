const BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api/v1';

export const apiClient = {
  async request(method, endpoint, payload = null, options = {}) {
    const url = `${BASE_URL}${endpoint}`;
    
    // Setup headers; Authorization header và CSRF được gửi theo fetch config
    const headers = {
      'Accept': 'application/json',
      ...options.headers,
    };
    
    // Phân giải FormData (không set Content-Type) vs JSON
    let body = payload;
    if (payload && !(payload instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(payload);
    }

    const config = {
      method,
      headers,
      body,
      credentials: options.credentials || 'include',
      signal: options.signal,
    };

    try {
      const response = await fetch(url, config);
      const data = await response.json().catch(() => null);

      if (!response.ok) {
        // Chuẩn hóa ném lỗi dựa theo API Contract envelope: { error: { code, message, fields }, request_id }
        throw {
          status: response.status,
          ...(data?.error || { code: 'UNKNOWN', message: 'Đã xảy ra lỗi không xác định.' }),
        };
      }
      return data;
    } catch (error) {
      if (error.name === 'AbortError') {
        throw { status: 0, code: 'ABORTED', message: 'Yêu cầu bị hủy.' };
      }
      throw error;
    }
  },
};
