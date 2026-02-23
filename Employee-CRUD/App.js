// ─── API base URL — change this to match your server ─────────────────────────
const API_URL = 'api.php';

// ─── REST API calls ───────────────────────────────────────────────────────────

const API = {
  async getEmployees() {
    const res = await fetch(API_URL);
    return res.json();
  },

  async addEmployee(data) {
    const res = await fetch(API_URL, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(data),
    });
    return res.json();
  },

  async deleteEmployee(id) {
    const res = await fetch(`${API_URL}?id=${id}`, { method: 'DELETE' });
    return res.json();
  }
};

// ─── Frontend Validation ──────────────────────────────────────────────────────

const Validate = {
  employeeName(v) {
    if (!v.trim()) return 'Name is required';
    if (v.trim().length < 2) return 'Name is too short';
    return null;
  },
  gender(v) {
    return !v ? 'Gender is required' : null;
  },
  maritalStatus(v) {
    return !v ? 'Marital status is required' : null;
  },
  phoneNo(v) {
    if (!v) return 'Phone number is required';
    if (!/^\+?[\d\s\-()]{7,15}$/.test(v)) return 'Invalid phone number';
    return null;
  },
  email(v) {
    if (!v) return 'Email is required';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return 'Invalid email address';
    return null;
  },
  address(v) {
    return !v.trim() ? 'Address is required' : null;
  },
  dateOfBirth(v) {
    if (!v) return 'Date of birth is required';
    const age = (new Date() - new Date(v)) / (1000 * 60 * 60 * 24 * 365);
    if (age < 16) return 'Must be at least 16 years old';
    if (age > 80) return 'Invalid date of birth';
    return null;
  },
  nationality(v) {
    return !v ? 'Nationality is required' : null;
  },
  hireDate(v) {
    if (!v) return 'Hire date is required';
    if (new Date(v) > new Date()) return 'Cannot be in the future';
    return null;
  },
  department(v) {
    return !v ? 'Department is required' : null;
  },
};

function validateForm(data) {
  const errors = {};
  for (const [field, fn] of Object.entries(Validate)) {
    const err = fn(data[field] || '');
    if (err) errors[field] = err;
  }
  return errors;
}

// ─── Show / clear errors in the DOM ──────────────────────────────────────────

function showErrors(errors) {
  document.querySelectorAll('.error-msg').forEach(el => el.textContent = '');
  document.querySelectorAll('input, select').forEach(el => el.classList.remove('error'));

  for (const [field, msg] of Object.entries(errors)) {
    const errEl = document.getElementById(`err-${field}`);
    if (errEl) errEl.textContent = msg;
    const inputEl = document.getElementById(field);
    if (inputEl) inputEl.classList.add('error');
  }

  const first = document.querySelector('.error-msg:not(:empty)');
  if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// ─── Toast ────────────────────────────────────────────────────────────────────

function showToast(msg, type = 'success') {
  const toast = document.getElementById('toast');
  toast.textContent = msg;
  toast.className = `toast ${type} show`;
  setTimeout(() => toast.classList.remove('show'), 3500);
}

// ─── Format date for display ──────────────────────────────────────────────────

function formatDate(dateStr) {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('en-MY', {
    day: 'numeric', month: 'short', year: 'numeric'
  });
}