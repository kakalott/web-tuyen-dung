import { ref } from 'vue';

export function useFormErrors() {
  const errors = ref({});
  const generalError = ref('');

  const clearErrors = () => {
    errors.value = {};
    generalError.value = '';
  };

  const setFieldError = (field, message) => {
    errors.value[field] = message;
  };

  const getFieldError = (field) => {
    return errors.value[field] || '';
  };

  const fromApi422 = (fields, message = '') => {
    errors.value = {};
    if (fields && typeof fields === 'object') {
      Object.entries(fields).forEach(([k, v]) => {
        errors.value[k] = Array.isArray(v) ? v[0] : v;
      });
    }
    if (message) {
      generalError.value = message;
    }
  };

  const handleApiError = (err) => {
    if (err?.status === 422 && err.fields) {
      fromApi422(err.fields, err.message);
    } else {
      generalError.value = err?.message || 'Đã có lỗi xảy ra.';
    }
  };

  return {
    errors,
    generalError,
    clearErrors,
    setFieldError,
    getFieldError,
    fromApi422,
    handleApiError,
  };
}
