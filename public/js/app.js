/**
 * GESTION+ : Logique Applicative Complète (Modules 1 à 10)
 * Gestion des utilisateurs, demandes, stocks, inventaires, patrimoine, parc auto,
 * dépenses, documents administratifs, reporting, audit, sauvegardes & paramétrage.
 */

const AppState = {
  currentView: 'dashboard',
  currentRole: 'collaborator', // 'collaborator' | 'admin' (accès opérationnel)
  isSuperAdmin: false,         // true uniquement pour role === 'admin'
  canManageOps: false,         // true pour admin, manager ou délégataire actif
  permissions: [],
  currentUser: null,
  selectedArticlesForRequest: {},
  editingRequestRef: null,
  selectedRequestRef: null,
  currentArticles: [],
  currentRequests: [],
  currentMovements: [],
  currentDocuments: [],
  currentUsers: [],
  currentAssignees: [],
  currentAssets: [],
  currentVehicles: [],
  currentExpenses: [],
  currentInventories: [],
  currentSavedReports: [],
  lastReportPayload: null,
  catalogRefs: {
    departments: [],
    locations: [],
    suppliers: [],
    request_categories: [],
    article_categories: [],
    units: [],
    settings: {}
  },
  currentStep: 1,
  activeDetailTab: 'info'
};

const PERMISSION_CATALOG = [
  { key: 'requests.create', label: 'Créer des demandes' },
  { key: 'requests.verify', label: 'Vérifier les demandes' },
  { key: 'requests.validate', label: 'Valider / Rejeter demandes' },
  { key: 'requests.assign', label: 'Affecter les demandes' },
  { key: 'requests.execute', label: 'Exécuter / Livrer demandes' },
  { key: 'stock.view', label: 'Consulter le stock' },
  { key: 'stock.manage', label: 'Gérer articles & mouvements' },
  { key: 'inventory.manage', label: 'Saisir inventaires physiques' },
  { key: 'inventory.validate', label: 'Valider écarts inventaire' },
  { key: 'assets.manage', label: 'Gérer le patrimoine' },
  { key: 'vehicles.manage', label: 'Gérer le parc automobile' },
  { key: 'expenses.manage', label: 'Enregistrer les dépenses' },
  { key: 'expenses.validate', label: 'Contrôler & valider dépenses' },
  { key: 'reports.view', label: 'Tableaux de bord & rapports' },
  { key: 'documents.manage', label: 'Gérer documents admin.' },
  { key: 'users.manage', label: 'Gérer utilisateurs & rôles' },
  { key: 'settings.manage', label: 'Paramétrage général' },
  { key: 'audit.view', label: 'Consulter journal d’audit' },
  { key: 'backups.manage', label: 'Sauvegarde & restauration' }
];

const DEFAULT_ROLE_PERMISSIONS = {
  Administrateur: PERMISSION_CATALOG.map(p => p.key),
  admin: PERMISSION_CATALOG.map(p => p.key),
  Responsable: [
    'requests.create', 'requests.verify', 'requests.validate', 'requests.assign', 'requests.execute',
    'stock.view', 'stock.manage', 'inventory.manage', 'inventory.validate',
    'assets.manage', 'vehicles.manage', 'expenses.manage', 'expenses.validate',
    'reports.view', 'documents.manage'
  ],
  manager: [
    'requests.create', 'requests.verify', 'requests.validate', 'requests.assign', 'requests.execute',
    'stock.view', 'stock.manage', 'inventory.manage', 'inventory.validate',
    'assets.manage', 'vehicles.manage', 'expenses.manage', 'expenses.validate',
    'reports.view', 'documents.manage'
  ],
  Collaborateur: ['requests.create', 'stock.view'],
  collaborator: ['requests.create', 'stock.view']
};

function hasAnyPermission(...permissions) {
  if (AppState.isSuperAdmin) return true;
  return permissions.some(permission => AppState.permissions.includes(permission));
}

const PENDING_REQUEST_STATUSES = [
  'Soumise',
  'En attente de validation'
];

const AUTHORIZED_REQUEST_STATUSES = ['Validée'];

function escHtml(value) {
  if (value === null || value === undefined) return '';
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function compactRequestReference(reference) {
  const value = String(reference || '');
  const sequence = value.match(/(\d{5})$/)?.[1];

  return sequence ? `#${sequence}` : value;
}

function formatCurrency(value, currencyLabel = null) {
  const unit = currencyLabel || (AppState.catalogRefs?.settings?.currency === 'EUR' ? '€' : (AppState.catalogRefs?.settings?.currency === 'USD' ? '$' : 'FCFA'));
  return `${Number(value || 0).toLocaleString('fr-FR')} ${unit}`;
}

function formatDateShort(value, fallback = '—') {
  if (!value) return fallback;
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return fallback;
  return parsed.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatDateTime(value, fallback = '—') {
  if (!value) return fallback;
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return fallback;
  return parsed.toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const writeActivity = {
  pending: 0,
  visibleSince: 0,
  hideTimer: null,
  minimumVisibleMs: 450,
  controls: new WeakMap()
};

function resolveWriteControl() {
  const activeElement = document.activeElement;
  if (activeElement?.matches?.('button, input[type="submit"]')) return activeElement;

  const activeForm = activeElement?.closest?.('form');
  return activeForm?.querySelector('button[type="submit"], input[type="submit"]') || null;
}

function writeActionLabel(method, control) {
  const text = `${control?.textContent || control?.value || ''}`.trim().toLocaleLowerCase('fr-FR');
  if (text.includes('connect')) return 'Connexion en cours…';
  if (text.includes('valid')) return 'Validation en cours…';
  if (text.includes('restaur')) return 'Restauration en cours…';
  if (text.includes('génér')) return 'Génération en cours…';
  if (text.includes('archiv')) return 'Archivage en cours…';
  if (text.includes('supprim') || method === 'DELETE') return 'Suppression en cours…';
  return 'Enregistrement en cours…';
}

function setWriteControlBusy(control, label) {
  if (!control) return;

  const currentState = writeActivity.controls.get(control);
  if (currentState) {
    currentState.pending += 1;
    return;
  }

  const state = {
    pending: 1,
    disabled: control.disabled,
    minWidth: control.style.minWidth,
    html: control instanceof HTMLButtonElement ? control.innerHTML : null,
    value: control instanceof HTMLInputElement ? control.value : null
  };
  writeActivity.controls.set(control, state);

  const width = control.getBoundingClientRect().width;
  if (width > 0) control.style.minWidth = `${Math.ceil(width)}px`;
  control.disabled = true;
  control.classList.add('is-submitting');
  control.setAttribute('aria-busy', 'true');

  if (control instanceof HTMLButtonElement) {
    control.innerHTML = `<span class="button-loading-spinner" aria-hidden="true"></span><span>${escHtml(label.replace(' en cours…', '…'))}</span>`;
  } else if (control instanceof HTMLInputElement) {
    control.value = label.replace(' en cours…', '…');
  }
}

function releaseWriteControl(control) {
  if (!control) return;
  const state = writeActivity.controls.get(control);
  if (!state) return;

  state.pending -= 1;
  if (state.pending > 0) return;

  control.disabled = state.disabled;
  control.style.minWidth = state.minWidth;
  control.classList.remove('is-submitting');
  control.removeAttribute('aria-busy');
  if (control instanceof HTMLButtonElement && state.html !== null) control.innerHTML = state.html;
  if (control instanceof HTMLInputElement && state.value !== null) control.value = state.value;
  writeActivity.controls.delete(control);
}

function beginWriteActivity(method) {
  const control = resolveWriteControl();
  const label = writeActionLabel(method, control);
  const indicator = document.getElementById('globalSavingIndicator');
  const indicatorLabel = document.getElementById('globalSavingIndicatorLabel');

  writeActivity.pending += 1;
  window.clearTimeout(writeActivity.hideTimer);
  if (writeActivity.pending === 1) writeActivity.visibleSince = Date.now();
  if (indicatorLabel) indicatorLabel.textContent = label;
  if (indicator) {
    indicator.classList.add('is-visible');
    indicator.setAttribute('aria-hidden', 'false');
  }
  setWriteControlBusy(control, label);

  return control;
}

function endWriteActivity(control) {
  releaseWriteControl(control);
  writeActivity.pending = Math.max(0, writeActivity.pending - 1);
  if (writeActivity.pending > 0) return;

  const elapsed = Date.now() - writeActivity.visibleSince;
  const remaining = Math.max(0, writeActivity.minimumVisibleMs - elapsed);
  writeActivity.hideTimer = window.setTimeout(() => {
    if (writeActivity.pending > 0) return;
    const indicator = document.getElementById('globalSavingIndicator');
    indicator?.classList.remove('is-visible');
    indicator?.setAttribute('aria-hidden', 'true');
  }, remaining);
}

function getAuthToken() {
  return sessionStorage.getItem('gestion_token') || localStorage.getItem('gestion_token') || '';
}

function getStoredUser() {
  const raw = sessionStorage.getItem('gestion_user') || localStorage.getItem('gestion_user');
  if (!raw) return null;
  try {
    return JSON.parse(raw);
  } catch (e) {
    return null;
  }
}

function setAuthSession(user, token) {
  if (token) {
    sessionStorage.setItem('gestion_token', token);
    localStorage.setItem('gestion_token', token);
  }
  if (user) {
    const raw = typeof user === 'string' ? user : JSON.stringify(user);
    sessionStorage.setItem('gestion_user', raw);
    localStorage.setItem('gestion_user', raw);
  }
}

function clearAuthSession() {
  sessionStorage.removeItem('gestion_token');
  sessionStorage.removeItem('gestion_user');
  localStorage.removeItem('gestion_token');
  localStorage.removeItem('gestion_user');
  AppState.currentUser = null;
}

window.addEventListener('storage', (event) => {
  if (event.key === 'gestion_token') {
    if (!event.newValue) {
      sessionStorage.removeItem('gestion_token');
      sessionStorage.removeItem('gestion_user');
      AppState.currentUser = null;
      if (AppState.currentView !== 'login' && AppState.currentView !== 'register') {
        showToast('Votre session a été fermée.', 'info');
        navigateToView('login', false);
      }
    } else {
      sessionStorage.setItem('gestion_token', event.newValue);
    }
  }
  if (event.key === 'gestion_user' && event.newValue) {
    sessionStorage.setItem('gestion_user', event.newValue);
    try {
      const parsed = JSON.parse(event.newValue);
      applyUser(parsed);
    } catch (e) {}
  }
});

const nativeFetch = window.fetch.bind(window);
window.fetch = (input, options = {}) => {
  const requestUrl = typeof input === 'string' ? input : input.url;
  const isApiRequest = requestUrl.startsWith('/api/') || requestUrl.startsWith(`${window.location.origin}/api/`);
  const token = getAuthToken();
  const method = `${options.method || (typeof input !== 'string' ? input.method : 'GET') || 'GET'}`.toUpperCase();
  const isWriteRequest = isApiRequest && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);
  const headers = new Headers(options.headers || (typeof input !== 'string' ? input.headers : undefined));

  if (isApiRequest && token) {
    headers.set('Authorization', `Bearer ${token}`);
    headers.set('X-Api-Token', token);
  }
  if (isApiRequest && !headers.has('Accept')) {
    headers.set('Accept', 'application/json');
  }

  if (!isApiRequest) return nativeFetch(input, options);

  const control = isWriteRequest ? beginWriteActivity(method) : null;
  return nativeFetch(input, { ...options, headers })
    .then(async (response) => {
      if (response.status === 401 && !requestUrl.includes('/api/auth/login')) {
        const hadSession = Boolean(getAuthToken());
        clearAuthSession();
        document.querySelectorAll('.modal.show').forEach((modalEl) => {
          bootstrap.Modal.getInstance(modalEl)?.hide();
        });
        if (hadSession && AppState.currentView !== 'login' && AppState.currentView !== 'register') {
          showToast('Votre session a expiré. Veuillez vous reconnecter.', 'warning');
          navigateToView('login', false);
        }
      }
      return response;
    })
    .finally(() => {
      if (isWriteRequest) endWriteActivity(control);
    });
};

window.downloadAuthenticatedFile = async (url, fallbackName = 'export.csv') => {
  try {
    const response = await fetch(url);
    if (!response.ok) throw new Error('Export refusé');
    const disposition = response.headers.get('Content-Disposition') || '';
    const fileNameMatch = disposition.match(/filename="?([^";]+)"?/i);
    const blob = await response.blob();
    const blobUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = blobUrl;
    link.download = fileNameMatch?.[1] || fallbackName;
    link.click();
    URL.revokeObjectURL(blobUrl);
  } catch (error) {
    showToast('Le téléchargement est indisponible ou vous n’avez pas les droits nécessaires.', 'danger');
  }
};

// Initialisation au chargement du DOM
document.addEventListener('DOMContentLoaded', () => {
  setupPasswordToggles();
  setupResponsiveNavigation();
  setupNavigation();
  setupRoleSwitcher();
  setupStepper();
  setupDetailTabs();
  setupTableFilters();
  setupModalsAndActions();
  setupSearchGlobal();
  setupAuthAndSettingsHandlers();
  setupOperationsCenter();

  const todayIso = new Date().toISOString().slice(0, 10);
  const fpDocDate = document.getElementById('fpDocDate');
  if (fpDocDate && !fpDocDate.value) fpDocDate.value = todayIso;
  const invDate = document.getElementById('inventoryDateInput');
  if (invDate && !invDate.value) invDate.value = todayIso;
  const delStart = document.getElementById('delegationStartDate');
  if (delStart && !delStart.value) delStart.value = todayIso;

  const storedUser = getStoredUser();
  const storedToken = getAuthToken();
  const hasAuthenticatedSession = Boolean(storedUser && storedToken);
  if (hasAuthenticatedSession) {
    applyUser(storedUser);
  } else {
    clearAuthSession();
    navigateToView('login', false);
  }

  if (hasAuthenticatedSession) {
    handleRouteHash();
  }
  window.addEventListener('hashchange', handleRouteHash);

  if (typeof BackendApi !== 'undefined') {
    BackendApi.checkHealth();
  }
});

/* ==========================================================================
   0. Bascule d'affichage des mots de passe (icône œil sur tous les champs)
   ========================================================================== */
function setupPasswordToggles() {
  document.addEventListener('click', (event) => {
    const toggleButton = event.target.closest('[data-password-toggle]');
    if (!toggleButton) return;

    const targetId = toggleButton.getAttribute('data-password-toggle');
    const passwordInput = targetId ? document.getElementById(targetId) : toggleButton.parentElement?.querySelector('input');
    if (!passwordInput) return;

    const shouldShowPassword = passwordInput.type === 'password';
    passwordInput.type = shouldShowPassword ? 'text' : 'password';

    const label = shouldShowPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe';
    const icon = toggleButton.querySelector('i');
    toggleButton.setAttribute('aria-label', label);
    toggleButton.setAttribute('aria-pressed', String(shouldShowPassword));
    toggleButton.setAttribute('title', label);
    if (icon) {
      icon.classList.toggle('bi-eye', !shouldShowPassword);
      icon.classList.toggle('bi-eye-slash', shouldShowPassword);
    }
  });
}

function setupResponsiveNavigation() {
  const toggleButton = document.getElementById('sidebarToggleButton');
  const backdrop = document.getElementById('sidebarBackdrop');
  const mobileBreakpoint = window.matchMedia('(max-width: 767px)');

  const setSidebarOpen = (isOpen) => {
    const shouldOpen = Boolean(isOpen && mobileBreakpoint.matches);
    document.body.classList.toggle('sidebar-mobile-open', shouldOpen);
    toggleButton?.setAttribute('aria-expanded', String(shouldOpen));
    toggleButton?.setAttribute('aria-label', shouldOpen ? 'Fermer le menu de navigation' : 'Ouvrir le menu de navigation');
  };

  toggleButton?.addEventListener('click', () => {
    setSidebarOpen(!document.body.classList.contains('sidebar-mobile-open'));
  });
  backdrop?.addEventListener('click', () => setSidebarOpen(false));

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setSidebarOpen(false);
  });

  document.addEventListener('click', (event) => {
    if (event.target.closest('.app-sidebar [data-view-target]')) setSidebarOpen(false);
  });

  const handleBreakpointChange = () => setSidebarOpen(false);
  if (typeof mobileBreakpoint.addEventListener === 'function') {
    mobileBreakpoint.addEventListener('change', handleBreakpointChange);
  } else {
    mobileBreakpoint.addListener(handleBreakpointChange);
  }
}

/* ==========================================================================
   1. Gestion de la Navigation, Session & Visibilité par Rôle
   ========================================================================== */
function applyUser(user) {
  if (!user) return;
  AppState.currentUser = user;

  const normalizedRole = String(user.role || '').toLowerCase();
  const isSuperAdmin = normalizedRole === 'admin' || normalizedRole === 'administrateur';
  const isManager = normalizedRole === 'manager' || normalizedRole === 'responsable';
  const hasDelegation = Boolean(user.has_active_delegation);
  const rolePermissions = DEFAULT_ROLE_PERMISSIONS[user.role] || DEFAULT_ROLE_PERMISSIONS[normalizedRole] || [];
  AppState.permissions = isSuperAdmin
    ? PERMISSION_CATALOG.map(permission => permission.key)
    : [...new Set(user.effective_permissions || user.permissions || rolePermissions)];
  const operationalPermissions = [
    'requests.verify', 'requests.validate', 'requests.assign', 'requests.execute',
    'stock.manage', 'inventory.manage', 'inventory.validate', 'assets.manage',
    'vehicles.manage', 'expenses.manage', 'expenses.validate', 'reports.view',
    'documents.manage'
  ];
  const canManageOps = isSuperAdmin || operationalPermissions.some(permission => AppState.permissions.includes(permission));

  AppState.isSuperAdmin = isSuperAdmin;
  AppState.canManageOps = canManageOps;
  AppState.currentRole = canManageOps ? 'admin' : 'collaborator';

  const roleDisplay = isSuperAdmin
    ? 'Administrateur'
    : (isManager ? 'Responsable' : (hasDelegation ? 'Collaborateur (Délégué)' : 'Collaborateur'));

  const initials = (user.name || 'GP').split(' ').filter(Boolean).map(n => n[0]).join('').substring(0, 2).toUpperCase();

  const nameEl = document.getElementById('topbarUserName');
  const roleEl = document.getElementById('topbarUserRole');
  const avatarEl = document.getElementById('topbarUserAvatar');
  const activeRoleText = document.getElementById('topbarActiveRoleText');
  const dropdownTitle = document.getElementById('topbarDropdownTitle');
  const dropdownService = document.getElementById('topbarDropdownService');

  if (nameEl) nameEl.textContent = user.name;
  if (roleEl) roleEl.textContent = isSuperAdmin || isManager ? roleDisplay : (user.service || roleDisplay);
  if (avatarEl) avatarEl.textContent = initials;
  if (activeRoleText) activeRoleText.textContent = `Profil : ${roleDisplay}`;
  if (dropdownTitle) dropdownTitle.textContent = `Compte ${roleDisplay}`;
  if (dropdownService) dropdownService.textContent = user.service || 'Direction Générale';

  const collabDashboard = document.getElementById('collaboratorDashboardView');
  const adminDashboard = document.getElementById('adminDashboardView');
  if (collabDashboard && adminDashboard) {
    collabDashboard.style.display = canManageOps ? 'none' : 'block';
    adminDashboard.style.display = canManageOps ? 'block' : 'none';
  }

  const collabNameEl = document.getElementById('collabGreetingName');
  if (collabNameEl) collabNameEl.textContent = user.name;
  const collabSubEl = document.getElementById('collabGreetingSub');
  if (collabSubEl) collabSubEl.textContent = `Espace Collaborateur • Service ${user.service || 'Général'}`;

  const reqDemandeurInput = document.getElementById('reqDemandeurName');
  if (reqDemandeurInput) {
    reqDemandeurInput.value = `${user.name} (${user.matricule || user.service || 'Demandeur'})`;
  }
  const reqServiceSelect = document.getElementById('selectRequestService');
  if (reqServiceSelect && user.service && [...reqServiceSelect.options].some(o => o.value === user.service)) {
    reqServiceSelect.value = user.service;
  }

  // Profil
  const profName = document.getElementById('profileCardName');
  const profEmail = document.getElementById('profileCardEmail');
  const profInName = document.getElementById('profileInputName');
  const profInEmail = document.getElementById('profileInputEmail');
  const profInPhone = document.getElementById('profileInputPhone');
  const profInOffice = document.getElementById('profileInputOffice');
  const profRole = document.getElementById('profileCardRole');
  const profService = document.getElementById('profileCardService');
  const profMatricule = document.getElementById('profileCardMatricule');
  const profAvatar = document.getElementById('profileCardAvatar');

  if (profName) profName.textContent = user.name;
  if (profEmail) profEmail.textContent = user.email;
  if (profInName) profInName.value = user.name || '';
  if (profInEmail) profInEmail.value = user.email || '';
  if (profInPhone) profInPhone.value = user.phone || '';
  if (profInOffice) profInOffice.value = user.office_location || '';
  if (profRole) profRole.textContent = roleDisplay;
  if (profService) profService.textContent = user.service ? `Service ${user.service}` : 'Direction Générale';
  if (profMatricule) profMatricule.textContent = user.matricule || 'Auto';
  if (profAvatar && user.avatar) profAvatar.src = user.avatar;

  applyRoleVisibility(AppState.currentRole);
}

function applyRoleVisibility(role = AppState.currentRole) {
  const isOps = role === 'admin';
  const isSuper = Boolean(AppState.isSuperAdmin);

  document.body.classList.remove('role-collaborator', 'role-admin');
  document.body.classList.add(`role-${role}`);

  document.querySelectorAll('.admin-only-feature').forEach(el => {
    el.style.setProperty('display', isOps ? '' : 'none', 'important');
  });

  document.querySelectorAll('.superadmin-only-feature').forEach(el => {
    el.style.setProperty('display', isSuper ? '' : 'none', 'important');
  });

  document.querySelectorAll('.collaborator-only-feature').forEach(el => {
    el.style.setProperty('display', !isOps ? '' : 'none', 'important');
  });

  document.querySelectorAll('[data-permission]').forEach(el => {
    const requiredPermissions = (el.dataset.permission || '').split(',').map(value => value.trim()).filter(Boolean);
    const isAllowed = requiredPermissions.length > 0 && hasAnyPermission(...requiredPermissions);
    el.style.setProperty('display', isAllowed ? '' : 'none', 'important');
  });

  const availableOperationTabs = [...document.querySelectorAll('[data-operations-tab]')]
    .filter(button => button.style.display !== 'none');
  if (availableOperationTabs.length > 0) {
    const activeTab = availableOperationTabs.find(button => button.classList.contains('active')) || availableOperationTabs[0];
    document.querySelectorAll('[data-operations-tab]').forEach(button => button.classList.toggle('active', button === activeTab));
    document.querySelectorAll('.operations-tab-pane').forEach(pane => {
      pane.style.display = pane.id === `operationsPane-${activeTab.dataset.operationsTab}` ? 'block' : 'none';
    });
  }
}

function setupNavigation() {
  document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-view-target]');
    if (!link) return;
    event.preventDefault();
    const targetView = link.getAttribute('data-view-target');
    if (targetView) navigateToView(targetView);
  });
}

function handleRouteHash() {
  const rawHash = window.location.hash.replace(/^#/, '');
  if (!rawHash) {
    navigateToView('dashboard', false);
    return;
  }
  if (rawHash.startsWith('request-detail/')) {
    const ref = decodeURIComponent(rawHash.split('/')[1] || '');
    if (ref) {
      openRequestDetail(ref);
      return;
    }
  }
  navigateToView(rawHash, false);
}

function navigateToView(viewId, updateHash = true) {
  if (viewId === 'logout') {
    if (getAuthToken()) {
      fetch('/api/auth/logout', { method: 'POST' }).catch(() => {});
    }
    clearAuthSession();
    AppState.isSuperAdmin = false;
    AppState.canManageOps = false;
    AppState.currentRole = 'collaborator';
    showToast('Vous avez été déconnecté avec succès.', 'info');
    navigateToView('login', updateHash);
    return;
  }

  const isAuthView = ['login', 'register'].includes(viewId);
  const appContainer = document.getElementById('appMainContainer');
  const authContainer = document.getElementById('authLayoutContainer');

  if (!isAuthView && !getAuthToken()) {
    viewId = 'login';
    return navigateToView('login', updateHash);
  }

  if (isAuthView) {
    if (appContainer) appContainer.style.display = 'none';
    if (authContainer) {
      authContainer.classList.add('active-auth');
      authContainer.style.display = 'flex';
    }
    document.querySelectorAll('.auth-view-screen').forEach(el => { el.style.display = 'none'; });
    const targetAuth = document.getElementById(`authView-${viewId}`);
    if (targetAuth) targetAuth.style.display = 'block';
  } else {
    if (authContainer) {
      authContainer.classList.remove('active-auth');
      authContainer.style.display = 'none';
    }
    if (appContainer) appContainer.style.display = 'flex';

    if (!AppState.canManageOps) {
      const opsRestrictedViews = ['operations', 'documents', 'document-new', 'users', 'stock-movements', 'settings'];
      if (opsRestrictedViews.includes(viewId)) {
        showToast('Accès restreint : cette rubrique est réservée aux responsables et administrateurs.', 'warning');
        viewId = 'dashboard';
      }
    } else if (!AppState.isSuperAdmin && ['users', 'settings'].includes(viewId)) {
      showToast('Cette rubrique d’administration générale est réservée à l’Administrateur.', 'warning');
      viewId = 'operations';
    }

    document.querySelectorAll('.view-section').forEach(view => view.classList.remove('active-view'));
    const targetElement = document.getElementById(`view-${viewId}`);
    if (targetElement) {
      targetElement.classList.add('active-view');
      AppState.currentView = viewId;
    } else {
      document.getElementById('view-dashboard')?.classList.add('active-view');
      AppState.currentView = 'dashboard';
      viewId = 'dashboard';
    }

    document.querySelectorAll('.nav-item-link').forEach(link => {
      link.classList.toggle('active', link.getAttribute('data-view-target') === viewId);
    });

    if (viewId === 'dashboard') {
      setTimeout(() => {
        if (typeof initDashboardCharts === 'function') initDashboardCharts();
      }, 50);
    } else if (viewId === 'profile' && typeof BackendApi !== 'undefined') {
      BackendApi.loadMyLoginHistory();
    } else if (viewId === 'settings' && AppState.isSuperAdmin && typeof BackendApi !== 'undefined') {
      BackendApi.loadAdminSettingsAndRefs();
      BackendApi.loadAuditLogs();
      BackendApi.loadBackups();
    }

    applyRoleVisibility(AppState.currentRole);
  }

  if (updateHash && window.location.hash !== `#${viewId}`) {
    window.location.hash = viewId;
  }

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function setupRoleSwitcher() {
  document.querySelectorAll('.role-pill-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const role = btn.getAttribute('data-role');
      if (role) setUserRole(role);
    });
  });
}

function setUserRole(role, notify = true) {
  if (!AppState.canManageOps && role === 'admin') {
    showToast('Votre compte ne dispose pas des droits de gestion.', 'warning');
    return;
  }
  AppState.currentRole = role;
  const collabDashboard = document.getElementById('collaboratorDashboardView');
  const adminDashboard = document.getElementById('adminDashboardView');
  if (collabDashboard && adminDashboard) {
    collabDashboard.style.display = role === 'admin' ? 'none' : 'block';
    adminDashboard.style.display = role === 'admin' ? 'block' : 'none';
  }
  applyRoleVisibility(role);
  if (notify && AppState.currentUser) {
    showToast(`Affichage basculé : ${AppState.currentUser.name}`, 'info');
  }
  if (typeof BackendApi !== 'undefined') {
    BackendApi.refreshAll({ silent: true });
  }
}

function updateCollaboratorLiveTracker() {
  const card = document.getElementById('activeRequestTrackerCard');
  if (!card) return;

  const requests = AppState.currentRequests || [];
  const myRequests = AppState.currentUser
    ? requests.filter(r => Number(r.user_id) === Number(AppState.currentUser.id))
    : requests;

  if (myRequests.length === 0) {
    card.style.display = 'none';
    return;
  }

  const latest = myRequests[0];
  const refEl = document.getElementById('trackerRequestRef');
  const badgeEl = document.getElementById('trackerRequestBadge');
  const b1 = document.getElementById('trackBar1');
  const b2 = document.getElementById('trackBar2');
  const b3 = document.getElementById('trackBar3');
  const b4 = document.getElementById('trackBar4');

  if (refEl) refEl.textContent = latest.reference;
  card.style.display = 'block';
  [b1, b2, b3, b4].forEach(b => { if (b) b.className = 'request-progress-bar'; });

  const st = latest.status;
  if (PENDING_REQUEST_STATUSES.includes(st)) {
    if (b1) b1.className = 'request-progress-bar done';
    if (b2) b2.className = 'request-progress-bar active';
    if (badgeEl) {
      badgeEl.className = 'badge bg-warning-subtle text-warning-emphasis border';
      badgeEl.textContent = st;
    }
  } else if (st === 'Validée') {
    [b1, b2, b3, b4].forEach(b => { if (b) b.className = 'request-progress-bar done'; });
    if (badgeEl) {
      badgeEl.className = 'badge bg-success-subtle text-success border';
      badgeEl.textContent = st;
    }
  } else if (st === 'Rejetée') {
    if (b1) b1.className = 'request-progress-bar done';
    if (badgeEl) {
      badgeEl.className = 'badge bg-danger-subtle text-danger border';
      badgeEl.textContent = st;
    }
  }
}

/* ==========================================================================
   2. Module 2 : Assistant Nouvelle Demande, Édition & Workflow Complet
   ========================================================================== */
window.preselectArticleForRequest = function (articleId) {
  const art = AppState.currentArticles.find(a => Number(a.id) === Number(articleId));
  resetNewRequestForm();
  if (art && art.quantity > 0) {
    AppState.selectedArticlesForRequest[art.code] = {
      id: art.code,
      dbId: art.id,
      name: art.name,
      category: art.category,
      stock: art.quantity,
      qty: 1
    };
    if (typeof BackendApi !== 'undefined') {
      BackendApi.renderSelectableArticles(AppState.currentArticles);
    }
    updateSelectedCounter();
    showToast(`"${art.name}" ajouté à votre sélection.`, 'info');
  }
  navigateToView('request-new');
};

window.startEditRequest = async function (reference) {
  let req = (AppState.currentRequests || []).find(r => r.reference === reference);
  if (!req) {
    try {
      const res = await fetch(`/api/requests/${encodeURIComponent(reference)}`);
      if (res.ok) {
        const json = await res.json();
        if (json.success && json.data) req = json.data;
      }
    } catch (e) {}
  }
  if (!req) {
    showToast('Demande introuvable pour modification.', 'warning');
    return;
  }

  const isOwner = AppState.currentUser && Number(req.user_id) === Number(AppState.currentUser.id);
  const isCollaborator = AppState.currentUser?.role === 'collaborator';
  if (!isOwner || !isCollaborator || !PENDING_REQUEST_STATUSES.includes(req.status)) {
    showToast('Seul le collaborateur demandeur peut modifier cette demande.', 'danger');
    return;
  }

  if (!AppState.currentArticles || AppState.currentArticles.length === 0) {
    if (typeof BackendApi !== 'undefined' && BackendApi.loadArticles) {
      await BackendApi.loadArticles();
    }
  }

  resetNewRequestForm();
  AppState.editingRequestRef = req.reference;

  const banner = document.getElementById('editingRequestBanner');
  const refText = document.getElementById('editingRequestRefText');
  const pageTitle = document.getElementById('requestNewPageTitle');
  const breadcrumb = document.getElementById('requestNewBreadcrumbText');
  const btnSubmit = document.getElementById('btnSubmitRequest');
  if (banner) banner.style.display = 'flex';
  if (refText) refText.textContent = req.reference;
  if (pageTitle) pageTitle.textContent = `Modifier ma demande ${req.reference}`;
  if (breadcrumb) breadcrumb.textContent = `Modification ${req.reference}`;
  if (btnSubmit) btnSubmit.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Enregistrer les modifications';

  const items = req.items || [];
  items.forEach(it => {
    const art = it.article || (AppState.currentArticles || []).find(a => Number(a.id) === Number(it.article_id));
    if (art) {
      const artKey = art.code || String(art.id);
      AppState.selectedArticlesForRequest[artKey] = {
        id: artKey,
        dbId: art.id,
        name: art.name,
        category: art.category,
        stock: Math.max(art.quantity || 1, it.quantity_requested || 1),
        qty: it.quantity_requested || 1
      };
    }
  });

  const nonStockCheck = document.getElementById('checkNonStockRequest');
  const nonStockWrap = document.getElementById('nonStockDescriptionWrapper');
  const nonStockDesc = document.getElementById('inputNonStockDescription');
  if (req.service_description || items.length === 0) {
    if (nonStockCheck) nonStockCheck.checked = true;
    if (nonStockWrap) nonStockWrap.style.display = 'block';
    if (nonStockDesc) nonStockDesc.value = req.service_description || req.motif || '';
  }

  if (typeof BackendApi !== 'undefined') {
    BackendApi.renderSelectableArticles(AppState.currentArticles);
  }
  updateSelectedCounter();

  const catSel = document.getElementById('selectRequestCategory');
  if (catSel && req.category) catSel.value = req.category;
  const typeSel = document.getElementById('selectRequestTypeOp');
  if (typeSel && req.type_operation) typeSel.value = req.type_operation;
  const srvSel = document.getElementById('selectRequestService');
  if (srvSel && req.service) srvSel.value = req.service;
  const motifIn = document.getElementById('inputRequestMotif');
  if (motifIn) motifIn.value = req.motif || '';
  const dateIn = document.getElementById('inputRequestDateNeeded');
  if (dateIn && req.date_needed) dateIn.value = String(req.date_needed).slice(0, 10);
  const urgSel = document.getElementById('selectRequestUrgency');
  if (urgSel && req.urgency) urgSel.value = req.urgency;
  const notesIn = document.getElementById('inputRequestNotes');
  if (notesIn) notesIn.value = req.notes || '';

  navigateToView('request-new');
};

function setupStepper() {
  const btnNext1 = document.getElementById('btnStep1Next');
  const btnPrev2 = document.getElementById('btnStep2Prev');
  const btnNext2 = document.getElementById('btnStep2Next');
  const btnPrev3 = document.getElementById('btnStep3Prev');
  const btnSubmit = document.getElementById('btnSubmitRequest');
  const checkNonStock = document.getElementById('checkNonStockRequest');
  const nonStockWrapper = document.getElementById('nonStockDescriptionWrapper');
  const searchCatalog = document.getElementById('searchNewReqArticle');
  const btnCancelEdit = document.getElementById('btnCancelEditRequest');

  checkNonStock?.addEventListener('change', () => {
    if (nonStockWrapper) {
      nonStockWrapper.style.display = checkNonStock.checked ? 'block' : 'none';
    }
  });

  btnCancelEdit?.addEventListener('click', () => {
    resetNewRequestForm();
    showToast('Mode modification annulé.', 'info');
  });

  searchCatalog?.addEventListener('input', () => {
    const q = searchCatalog.value.trim().toLowerCase();
    document.querySelectorAll('#selectableArticlesTableBody tr.selectable-article-row').forEach(tr => {
      tr.style.display = !q || tr.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
  });

  btnNext1?.addEventListener('click', () => {
    const selectedCount = Object.keys(AppState.selectedArticlesForRequest).length;
    const isNonStock = Boolean(checkNonStock?.checked);
    const nonStockDesc = document.getElementById('inputNonStockDescription')?.value.trim() || '';

    if (selectedCount === 0 && !isNonStock) {
      showToast('Sélectionnez au moins un article ou cochez « Demande sans article en stock ».', 'warning');
      return;
    }
    if (selectedCount === 0 && isNonStock && !nonStockDesc) {
      showToast('Veuillez décrire précisément la prestation ou le besoin souhaité.', 'warning');
      return;
    }
    if (isNonStock && selectedCount === 0) {
      const catSel = document.getElementById('selectRequestCategory');
      if (catSel && catSel.value === 'Papeterie et fournitures de bureau') {
        catSel.value = 'Prestations de services';
      }
      const motifIn = document.getElementById('inputRequestMotif');
      if (motifIn && !motifIn.value.trim()) {
        motifIn.value = nonStockDesc.slice(0, 120);
      }
    }
    goToStep(2);
  });

  btnPrev2?.addEventListener('click', () => goToStep(1));

  btnNext2?.addEventListener('click', () => {
    const motif = document.getElementById('inputRequestMotif')?.value.trim();
    if (!motif) {
      showToast('Veuillez renseigner l’objet / motif principal de la demande.', 'warning');
      return;
    }
    buildStep3Recap();
    goToStep(3);
  });

  btnPrev3?.addEventListener('click', () => goToStep(2));

  btnSubmit?.addEventListener('click', submitRequest);

  document.querySelectorAll('.selectable-article-row').forEach(bindSelectableArticleRow);
}

async function submitRequest() {
  const selectedKeys = Object.keys(AppState.selectedArticlesForRequest);
  const isNonStock = Boolean(document.getElementById('checkNonStockRequest')?.checked);
  const nonStockDesc = document.getElementById('inputNonStockDescription')?.value.trim() || '';

  if (selectedKeys.length === 0 && !isNonStock && !nonStockDesc) {
    showToast('Veuillez sélectionner au moins un article ou décrire le besoin hors stock.', 'warning');
    return;
  }

  const motif = document.getElementById('inputRequestMotif')?.value.trim();
  if (!motif) {
    showToast('Le motif de la demande est obligatoire.', 'warning');
    return;
  }

  const typeOp = document.getElementById('selectRequestTypeOp')?.value || 'Dotation permanente';
  const category = document.getElementById('selectRequestCategory')?.value || 'Autres besoins administratifs et logistiques';
  const service = document.getElementById('selectRequestService')?.value || AppState.currentUser?.service || 'Administration & Logistique';
  const dateNeeded = document.getElementById('inputRequestDateNeeded')?.value || new Date().toISOString().split('T')[0];
  const urgency = document.getElementById('selectRequestUrgency')?.value || 'Normale';
  const notes = document.getElementById('inputRequestNotes')?.value.trim() || '';
  const files = document.getElementById('inputRequestAttachment')?.files;

  const items = selectedKeys.map(k => ({
    article_id: AppState.selectedArticlesForRequest[k].dbId,
    quantity: Number(AppState.selectedArticlesForRequest[k].qty || 1)
  }));

  const btnSubmit = document.getElementById('btnSubmitRequest');
  if (btnSubmit) btnSubmit.disabled = true;

  try {
    if (AppState.editingRequestRef) {
      const editRef = AppState.editingRequestRef;
      const updateRes = await fetch(`/api/requests/${encodeURIComponent(editRef)}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          category,
          motif,
          service_description: isNonStock ? nonStockDesc : null,
          service,
          date_needed: dateNeeded,
          urgency,
          notes: notes || null,
          items
        })
      });
      const updateResult = await updateRes.json();
      if (!updateRes.ok || !updateResult.success) {
        const msg = Object.values(updateResult.errors || {}).flat()[0] || updateResult.message || 'Impossible de modifier la demande.';
        showToast(msg, 'danger');
        return;
      }

      if (files && files.length > 0) {
        for (const file of files) {
          const fd = new FormData();
          fd.append('file', file);
          await fetch(`/api/requests/${encodeURIComponent(editRef)}/attachments`, { method: 'POST', body: fd });
        }
      }

      showToast(`Demande ${editRef} mise à jour !`, 'success');
      resetNewRequestForm();
      await BackendApi.refreshAll({ showIndicator: true, silent: true });
      openRequestDetail(editRef);
      return;
    }

    const payload = new FormData();
    payload.append('type_operation', typeOp);
    payload.append('category', category);
    payload.append('motif', motif);
    if (isNonStock && nonStockDesc) {
      payload.append('service_description', nonStockDesc);
    }
    payload.append('service', service);
    payload.append('date_needed', dateNeeded);
    payload.append('urgency', urgency);
    if (notes) payload.append('notes', notes);
    items.forEach((item, index) => {
      payload.append(`items[${index}][article_id]`, item.article_id);
      payload.append(`items[${index}][quantity]`, item.quantity);
    });

    if (files && files.length > 0) {
      Array.from(files).forEach(file => payload.append('attachments[]', file));
    }

    const res = await fetch('/api/requests', { method: 'POST', body: payload });
    const result = await res.json();
    if (!res.ok || !result.success) {
      const msg = Object.values(result.errors || {}).flat()[0] || result.message || 'Enregistrement impossible.';
      showToast(msg, 'danger');
      return;
    }

    showToast(result.message || `Demande ${result.data.reference} enregistrée !`, 'success');
    resetNewRequestForm();
    await BackendApi.refreshAll({ showIndicator: true, silent: true });
    openRequestDetail(result.data.reference);
  } catch (err) {
    console.warn('Request save error:', err);
    showToast('Impossible d’enregistrer la demande pour le moment. Veuillez réessayer.', 'danger');
  } finally {
    if (btnSubmit) btnSubmit.disabled = false;
  }
}

function bindSelectableArticleRow(row) {
  const checkbox = row.querySelector('.article-select-checkbox');
  const qtyInput = row.querySelector('.qty-input');
  const btnMinus = row.querySelector('.qty-btn-minus');
  const btnPlus = row.querySelector('.qty-btn-plus');
  const articleId = row.getAttribute('data-article-id');
  const articleDbId = parseInt(row.getAttribute('data-article-db-id'), 10) || 1;
  const articleName = row.getAttribute('data-article-name');
  const articleCategory = row.getAttribute('data-article-category');
  const articleStock = parseInt(row.getAttribute('data-article-stock'), 10) || 0;

  if (AppState.selectedArticlesForRequest[articleId]) {
    if (checkbox && !checkbox.disabled) checkbox.checked = true;
    if (qtyInput) qtyInput.value = AppState.selectedArticlesForRequest[articleId].qty || 1;
    row.classList.add('table-primary');
  }

  const updateRowState = () => {
    if (checkbox?.checked) {
      row.classList.add('table-primary');
      const qty = Math.max(1, parseInt(qtyInput?.value, 10) || 1);
      AppState.selectedArticlesForRequest[articleId] = {
        id: articleId,
        dbId: articleDbId,
        name: articleName,
        category: articleCategory,
        stock: articleStock,
        qty
      };
    } else {
      row.classList.remove('table-primary');
      delete AppState.selectedArticlesForRequest[articleId];
    }
    updateSelectedCounter();
  };

  checkbox?.addEventListener('change', updateRowState);

  if (btnMinus && qtyInput) {
    btnMinus.addEventListener('click', () => {
      const val = parseInt(qtyInput.value, 10) || 1;
      if (val > 1) {
        qtyInput.value = val - 1;
        if (checkbox?.checked) updateRowState();
      }
    });
  }

  if (btnPlus && qtyInput) {
    btnPlus.addEventListener('click', () => {
      const val = parseInt(qtyInput.value, 10) || 1;
      if (articleStock === 0 || val < articleStock) {
        qtyInput.value = val + 1;
        if (checkbox && !checkbox.checked && !checkbox.disabled) checkbox.checked = true;
        updateRowState();
      } else {
        showToast(`Quantité disponible maximale en stock : ${articleStock}`, 'warning');
      }
    });
  }

  qtyInput?.addEventListener('change', () => {
    let val = parseInt(qtyInput.value, 10) || 1;
    if (val < 1) val = 1;
    if (articleStock > 0 && val > articleStock) val = articleStock;
    qtyInput.value = val;
    if (checkbox?.checked) updateRowState();
  });
}

function updateSelectedCounter() {
  const count = Object.keys(AppState.selectedArticlesForRequest).length;
  const badge = document.getElementById('selectedArticlesCountBadge');
  if (badge) {
    badge.textContent = count;
    badge.style.display = count > 0 ? 'inline-block' : 'none';
  }
}

function goToStep(stepNumber) {
  AppState.currentStep = stepNumber;
  ['stepContent1', 'stepContent2', 'stepContent3'].forEach((id, idx) => {
    const el = document.getElementById(id);
    if (el) el.style.display = idx + 1 === stepNumber ? 'block' : 'none';
  });

  for (let i = 1; i <= 3; i++) {
    const stepHeader = document.getElementById(`stepHeader${i}`);
    if (stepHeader) {
      stepHeader.classList.remove('active', 'completed');
      if (i === stepNumber) stepHeader.classList.add('active');
      else if (i < stepNumber) stepHeader.classList.add('completed');
    }
  }
  window.scrollTo({ top: 100, behavior: 'smooth' });
}

function buildStep3Recap() {
  const recapTableBody = document.getElementById('step3RecapTableBody');
  const catRecap = document.getElementById('step3CategoryRecap');
  const motifRecap = document.getElementById('step3MotifRecap');
  const serviceRecap = document.getElementById('step3ServiceRecap');
  const dateRecap = document.getElementById('step3DateRecap');
  const notesRecap = document.getElementById('step3NotesRecap');

  const category = document.getElementById('selectRequestCategory')?.value || '-';
  const motif = document.getElementById('inputRequestMotif')?.value || '-';
  const service = document.getElementById('selectRequestService')?.value || '-';
  const dateNeeded = document.getElementById('inputRequestDateNeeded')?.value || 'Dès que possible';
  const urgency = document.getElementById('selectRequestUrgency')?.value || 'Normale';
  const notes = document.getElementById('inputRequestNotes')?.value.trim() || '';
  const nonStockDesc = document.getElementById('checkNonStockRequest')?.checked
    ? (document.getElementById('inputNonStockDescription')?.value.trim() || '')
    : '';
  const filesCount = document.getElementById('inputRequestAttachment')?.files?.length || 0;

  if (catRecap) catRecap.textContent = category;
  if (motifRecap) motifRecap.textContent = motif;
  if (serviceRecap) serviceRecap.textContent = service;
  if (dateRecap) dateRecap.textContent = `${dateNeeded} • Urgence : ${urgency}`;
  if (notesRecap) {
    const parts = [];
    if (nonStockDesc) parts.push(`Besoin hors stock / prestation : ${nonStockDesc}`);
    if (notes) parts.push(`Justification : ${notes}`);
    if (filesCount > 0) parts.push(`${filesCount} pièce(s) jointe(s) sélectionnée(s)`);
    notesRecap.textContent = parts.length ? parts.join(' | ') : 'Aucune note complémentaire.';
  }

  if (recapTableBody) {
    recapTableBody.innerHTML = '';
    const articles = Object.values(AppState.selectedArticlesForRequest);
    if (articles.length === 0) {
      recapTableBody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">${escHtml(nonStockDesc || 'Prestation / besoin hors stock (aucun article magasin sélectionné)')}</td></tr>`;
    } else {
      articles.forEach(art => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="fw-semibold">${escHtml(art.name)}</td>
          <td><span class="badge bg-light text-dark border">${escHtml(art.category)}</span></td>
          <td class="text-center fw-bold text-primary">${art.qty}</td>
          <td class="text-end text-muted">${art.stock} en stock</td>
        `;
        recapTableBody.appendChild(tr);
      });
    }
  }
}

function resetNewRequestForm() {
  AppState.selectedArticlesForRequest = {};
  AppState.editingRequestRef = null;

  const banner = document.getElementById('editingRequestBanner');
  const pageTitle = document.getElementById('requestNewPageTitle');
  const breadcrumb = document.getElementById('requestNewBreadcrumbText');
  const btnSubmit = document.getElementById('btnSubmitRequest');
  if (banner) banner.style.display = 'none';
  if (pageTitle) pageTitle.textContent = 'Créer une nouvelle demande interne';
  if (breadcrumb) breadcrumb.textContent = 'Nouvelle demande';
  if (btnSubmit) btnSubmit.innerHTML = '<i class="bi bi-send-check me-1"></i> Soumettre la demande';

  const checkNonStock = document.getElementById('checkNonStockRequest');
  const nonStockWrapper = document.getElementById('nonStockDescriptionWrapper');
  const nonStockInput = document.getElementById('inputNonStockDescription');
  if (checkNonStock) checkNonStock.checked = false;
  if (nonStockWrapper) nonStockWrapper.style.display = 'none';
  if (nonStockInput) nonStockInput.value = '';

  document.querySelectorAll('.article-select-checkbox').forEach(cb => { cb.checked = false; });
  document.querySelectorAll('.selectable-article-row').forEach(row => { row.classList.remove('table-primary'); });
  document.querySelectorAll('.qty-input').forEach(input => { input.value = 1; });

  const motifEl = document.getElementById('inputRequestMotif');
  const notesEl = document.getElementById('inputRequestNotes');
  const attachEl = document.getElementById('inputRequestAttachment');
  if (motifEl) motifEl.value = '';
  if (notesEl) notesEl.value = '';
  if (attachEl) attachEl.value = '';

  updateSelectedCounter();
  goToStep(1);
}

function setupDetailTabs() {
  const tabBtns = document.querySelectorAll('.tab-nav-btn');
  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      tabBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const targetTab = btn.getAttribute('data-detail-tab');
      document.querySelectorAll('.detail-tab-pane').forEach(pane => { pane.style.display = 'none'; });
      const paneTarget = document.getElementById(`tabPane-${targetTab}`);
      if (paneTarget) paneTarget.style.display = 'block';
    });
  });
}

function requestStatusBadge(status) {
  const classes = {
    'Validée': 'badge-approved',
    'Soumise': 'badge-pending',
    'En attente de validation': 'badge-pending',
    'Rejetée': 'badge-rejected'
  };
  const badgeClass = classes[status] || 'badge-pending';
  return `<span class="badge-status ${badgeClass}"><span class="badge-status-dot"></span> ${escHtml(status || 'Soumise')}</span>`;
}

async function openRequestDetail(reference) {
  if (!reference) return;
  AppState.selectedRequestRef = reference;

  let req = (AppState.currentRequests || []).find(r => r.reference === reference);
  try {
    const res = await fetch(`/api/requests/${encodeURIComponent(reference)}`);
    if (res.ok) {
      const json = await res.json();
      if (json.success && json.data) {
        req = json.data;
        const idx = (AppState.currentRequests || []).findIndex(r => r.reference === reference);
        if (idx >= 0) AppState.currentRequests[idx] = req;
      }
    }
  } catch (e) {
    console.warn('openRequestDetail fetch error:', e);
  }

  if (!req) {
    showToast('Demande introuvable.', 'warning');
    return;
  }

  const refTitle = document.getElementById('detailRequestRef');
  if (refTitle) refTitle.textContent = `Demande n° ${req.reference}`;

  const badge = document.getElementById('requestDetailStatusBadge');
  if (badge) badge.outerHTML = requestStatusBadge(req.status).replace('<span ', '<span id="requestDetailStatusBadge" ');

  const urgBadge = document.getElementById('detailUrgencyBadge');
  if (urgBadge) {
    const urgClass = ['Urgente', 'Critique'].includes(req.urgency)
      ? 'bg-danger-subtle text-danger border-danger-subtle'
      : (req.urgency === 'Planifiée' ? 'bg-info-subtle text-info-emphasis border' : 'bg-light text-dark border');
    urgBadge.className = `badge ${urgClass}`;
    urgBadge.textContent = `Urgence : ${req.urgency || 'Normale'}`;
  }

  const dName = req.user?.name || 'Demandeur';
  const dService = req.service || 'Service Général';
  const createdDate = formatDateTime(req.created_at, 'Récemment');
  const subtitle = document.getElementById('detailRequestSubtitle');
  if (subtitle) {
    subtitle.innerHTML = `Créée le ${escHtml(createdDate)} par <strong id="detailDemandeurName">${escHtml(dName)}</strong> (${escHtml(dService)})`;
  }

  const setElText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  setElText('detailRequestCategory', req.category || 'Autres besoins administratifs et logistiques');
  setElText('detailRequestAssignee', req.assignee?.name ? `${req.assignee.name} (${req.assignee.service || 'Agent'})` : 'Non affectée');
  setElText('detailRequestMotif', req.motif || '-');
  setElText('detailRequestNotes', [req.service_description, req.notes].filter(Boolean).join(' — ') || 'Aucune note particulière.');
  setElText('detailRequestService', dService);
  setElText('detailRequestDateNeeded', formatDateShort(req.date_needed, 'Non précisée'));
  setElText('detailRequestCreatedAt', createdDate);
  setElText('detailRequestUpdatedAt', formatDateTime(req.updated_at, createdDate));

  // Timeline
  const timeStep1 = document.getElementById('detailTimelineStep1Time');
  if (timeStep1) timeStep1.textContent = `${createdDate} • Par ${dName}`;

  const timeStep2 = document.getElementById('timelineStepCurrent');
  const timeStep3 = document.getElementById('timelineStepApproved');
  if (timeStep2 && timeStep3) {
    const marker2 = timeStep2.querySelector('.timeline-marker');
    const title2 = timeStep2.querySelector('.timeline-title');
    const time2 = timeStep2.querySelector('.timeline-time');
    const marker3 = timeStep3.querySelector('.timeline-marker');
    const title3 = timeStep3.querySelector('.timeline-title');
    const time3 = timeStep3.querySelector('.timeline-time');

    if (AUTHORIZED_REQUEST_STATUSES.includes(req.status)) {
      timeStep2.className = 'timeline-step done';
      if (marker2) marker2.innerHTML = '<i class="bi bi-check"></i>';
      if (title2) { title2.className = 'timeline-title'; title2.textContent = 'Vérification & Validation effectuées'; }
      if (time2) time2.textContent = req.approver?.name ? `Validée par ${req.approver.name}` : 'Validée par la hiérarchie';

      const isDone = Boolean(req.executed_at || req.delivered_at || req.received_at || req.closed_at);
      timeStep3.className = isDone ? 'timeline-step done' : 'timeline-step current';
      if (marker3) marker3.innerHTML = isDone ? '<i class="bi bi-check-lg"></i>' : '<i class="bi bi-gear"></i>';
      if (title3) { title3.className = 'timeline-title text-success fw-bold'; title3.textContent = isDone ? 'Exécution enregistrée' : 'Suivi de l’exécution'; }
      if (time3) time3.textContent = req.assignee?.name ? `Suivi par ${req.assignee.name} • Bon officiel disponible` : 'Bon officiel disponible';
    } else if (req.status === 'Rejetée') {
      timeStep2.className = 'timeline-step done';
      if (marker2) marker2.innerHTML = '<i class="bi bi-x-lg"></i>';
      if (title2) { title2.className = 'timeline-title text-danger fw-bold'; title2.textContent = req.status; }
      if (time2) time2.textContent = req.rejection_reason || req.cancellation_reason || formatDateTime(req.closed_at || req.updated_at);
      timeStep3.className = 'timeline-step';
      if (marker3) marker3.innerHTML = '<i class="bi bi-dash"></i>';
      if (title3) { title3.className = 'timeline-title text-muted'; title3.textContent = 'Circuit clôturé'; }
      if (time3) time3.textContent = 'Aucune exécution ni sortie de stock';
    } else {
      timeStep2.className = 'timeline-step current';
      if (marker2) marker2.innerHTML = '<i class="bi bi-arrow-repeat"></i>';
      if (title2) { title2.className = 'timeline-title text-warning fw-bold'; title2.textContent = req.status; }
      if (time2) time2.textContent = req.verifier?.name ? `Vérifiée par ${req.verifier.name}` : 'En cours d’instruction';
      timeStep3.className = 'timeline-step';
      if (marker3) marker3.innerHTML = '3';
      if (title3) { title3.className = 'timeline-title'; title3.textContent = 'Validation, Exécution & Réception'; }
      if (time3) time3.textContent = 'En attente de décision';
    }
  }

  // Articles
  const items = req.items || [];
  const tabArticlesBtn = document.getElementById('detailTabArticlesBtn');
  if (tabArticlesBtn) tabArticlesBtn.textContent = `Articles (${items.length})`;

  const previewBody = document.getElementById('detailPreviewArticlesBody');
  if (previewBody) {
    if (items.length === 0) {
      previewBody.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-muted">${escHtml(req.service_description || 'Prestation / besoin hors stock magasin.')}</td></tr>`;
    } else {
      previewBody.innerHTML = items.map(it => {
        const art = it.article || {};
        return `
          <tr>
            <td><strong>${escHtml(art.name || 'Article')}</strong></td>
            <td><span class="badge bg-light text-dark border">${escHtml(art.category || 'Général')}</span></td>
            <td class="text-center fw-semibold">${it.quantity_requested} ${escHtml(art.unit || 'unité(s)')}</td>
            <td class="text-end">${AUTHORIZED_REQUEST_STATUSES.includes(req.status)
              ? `<span class="badge bg-success-subtle text-success border">${it.quantity_approved ?? it.quantity_requested} accordé(s)</span>`
              : `<span class="text-muted small">${art.quantity ?? 0} en stock</span>`}</td>
          </tr>`;
      }).join('');
    }
  }

  const fullBody = document.getElementById('detailFullArticlesBody');
  if (fullBody) {
    if (items.length === 0) {
      fullBody.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-muted">${escHtml(req.service_description || 'Aucun article magasin associé (demande de service / maintenance / véhicule).')}</td></tr>`;
    } else {
      fullBody.innerHTML = items.map(it => {
        const art = it.article || {};
        const stockOk = (art.quantity ?? 0) >= it.quantity_requested;
        return `
          <tr>
            <td class="fw-semibold text-primary">${escHtml(art.code || '-')}</td>
            <td>${escHtml(art.name || 'Article')}</td>
            <td><span class="badge bg-light text-dark border">${escHtml(art.category || 'Général')}</span></td>
            <td>${escHtml(art.unit || 'unité(s)')}</td>
            <td class="text-center fw-bold">${it.quantity_requested}</td>
            <td class="text-center fw-bold text-success">${AUTHORIZED_REQUEST_STATUSES.includes(req.status) ? (it.quantity_approved ?? it.quantity_requested) : '—'}</td>
            <td><span class="badge-status ${stockOk ? 'badge-in-stock' : 'badge-low-stock'}"><span class="badge-status-dot"></span> ${art.quantity ?? 0} dispo</span></td>
          </tr>`;
      }).join('');
    }
  }

  // Documents & pièces jointes
  const docs = req.documents || [];
  const tabDocsBtn = document.getElementById('detailTabDocsBtn');
  if (tabDocsBtn) tabDocsBtn.textContent = `Documents & Bon (${docs.length})`;

  const docsList = document.getElementById('detailRequestDocumentsList');
  if (docsList) {
    const canOpenBon = AUTHORIZED_REQUEST_STATUSES.includes(req.status);
    let html = '';
    if (canOpenBon) {
      html += `
        <div class="p-3 bg-success-subtle border border-success-subtle rounded d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="d-flex align-items-center gap-3">
            <i class="bi bi-file-earmark-check-fill text-success fs-3"></i>
            <div>
              <strong class="d-block text-dark">Bon Officiel_${escHtml(req.reference)}.pdf</strong>
              <small class="text-muted">Bon de sortie / exécution validé et signé numériquement</small>
            </div>
          </div>
          <button type="button" class="btn-gp-primary btn-sm" onclick="openPdfModal('${escHtml(req.reference)}')"><i class="bi bi-printer"></i> Aperçu & Imprimer PDF</button>
        </div>`;
    }
    if (docs.length === 0 && !canOpenBon) {
      html = '<div class="p-3 bg-light border rounded text-muted small">Aucun document ni pièce jointe rattaché pour le moment.</div>';
    } else {
      html += docs.map(doc => `
        <div class="p-2 px-3 bg-white border rounded d-flex justify-content-between align-items-center gap-2">
          <div>
            <strong class="text-dark small d-block">${escHtml(doc.title || doc.original_name)}</strong>
            <small class="text-muted">${escHtml(doc.reference || doc.type || 'Pièce')} • ${escHtml(doc.file_size || '')} • ${formatDateShort(doc.created_at)}</small>
          </div>
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="downloadDocument(${doc.id}, '${escHtml(doc.original_name || doc.title)}')"><i class="bi bi-download"></i> Télécharger</button>
        </div>`).join('');
    }
    docsList.innerHTML = html;
  }

  // Bouton Bon PDF en-tête
  const btnOpenPdfBon = document.getElementById('btnDetailOpenPdfBon');
  if (btnOpenPdfBon) {
    btnOpenPdfBon.style.display = AUTHORIZED_REQUEST_STATUSES.includes(req.status) ? 'inline-flex' : 'none';
  }

  // Boutons Demandeur
  const isOwner = AppState.currentUser && Number(req.user_id) === Number(AppState.currentUser.id);
  const btnEditResubmit = document.getElementById('btnRequesterEditResubmit');
  if (btnEditResubmit) {
    const canEdit = isOwner && AppState.currentUser?.role === 'collaborator' && PENDING_REQUEST_STATUSES.includes(req.status);
    btnEditResubmit.style.display = canEdit ? 'inline-flex' : 'none';
    btnEditResubmit.innerHTML = '<i class="bi bi-pencil-square me-1"></i> Modifier la demande';
    btnEditResubmit.onclick = () => window.startEditRequest(req.reference);
  }

  const btnConfirmReceipt = document.getElementById('btnRequesterConfirmReceipt');
  if (btnConfirmReceipt) {
    const canConfirm = (isOwner || AppState.canManageOps)
      && req.status === 'Validée'
      && !req.received_at;
    btnConfirmReceipt.style.display = canConfirm ? 'inline-flex' : 'none';
    btnConfirmReceipt.onclick = async () => {
      if (confirm(`Confirmez-vous la bonne réception du matériel / de la prestation pour ${req.reference} ?`)) {
        await BackendApi.transitionRequest(req.reference, 'receive');
      }
    };
  }

  const btnCancel = document.getElementById('btnCollaboratorCancel');
  if (btnCancel) {
    const cancellable = PENDING_REQUEST_STATUSES.includes(req.status) && (isOwner || AppState.isSuperAdmin);
    btnCancel.style.display = cancellable ? 'inline-flex' : 'none';
    btnCancel.onclick = () => openRequestReasonModal('cancel', req.reference);
  }

  // Actions Gestionnaire / Responsable
  const isPending = PENDING_REQUEST_STATUSES.includes(req.status);
  const isValidated = req.status === 'Validée';
  const hasReturned = (req.decisions || []).some(decision => decision.action === 'returned');
  const actionVisibility = {
    btnActionVerify: req.status === 'Soumise',
    btnActionApprove: isPending,
    btnActionCorrection: isPending,
    btnActionAssign: isValidated,
    btnActionDeliver: isValidated && !req.delivered_at,
    btnActionReceive: isValidated && !req.received_at,
    btnActionReturn: req.type_operation === 'Emprunt temporaire' && isValidated && !hasReturned,
    btnActionClose: isValidated && !req.closed_at,
    btnActionReject: isPending
  };

  Object.entries(actionVisibility).forEach(([id, visible]) => {
    const action = document.getElementById(id);
    if (action?.closest('li')) action.closest('li').style.display = visible ? '' : 'none';
  });

  const hasManagerAction = Object.values(actionVisibility).some(Boolean);
  const treatmentDropdown = document.getElementById('requestTreatmentDropdown');
  if (treatmentDropdown) {
    treatmentDropdown.style.setProperty('display', hasManagerAction && AppState.canManageOps ? '' : 'none', 'important');
  }

  // Historique complet
  const history = document.getElementById('detailRequestHistory');
  if (history) {
    const labels = {
      created: 'Demande créée',
      submitted: 'Demande soumise',
      resubmitted: 'Compléments fournis & Demande re-soumise',
      verified: 'Vérification effectuée',
      approved: 'Demande validée & Bon généré',
      assigned: 'Demande affectée à un agent',
      execution_step: 'Étape d’exécution enregistrée',
      executed: 'Demande exécutée / Matériel remis',
      delivered: 'Matériel livré',
      received: 'Réception confirmée',
      returned: 'Matériel restitué en stock',
      correction_requested: 'Demande de complément envoyée',
      rejected: 'Demande rejetée',
      cancelled: 'Demande annulée',
      closed: 'Demande clôturée'
    };
    const decisions = req.decisions || [];
    history.innerHTML = decisions.length
      ? decisions.map(d => `
        <li class="list-group-item d-flex justify-content-between align-items-start py-3 gap-3">
          <div>
            <span class="fw-semibold text-dark">${escHtml(labels[d.action] || d.action)}</span>
            <span class="badge bg-light text-secondary border ms-2">${escHtml(d.status_from ? `${d.status_from} → ${d.status_to}` : d.status_to)}</span>
            ${d.comment ? `<div class="text-dark small mt-1">${escHtml(d.comment)}</div>` : ''}
            <div class="text-muted small mt-1">Par ${escHtml(d.user?.name || 'Système')}</div>
          </div>
          <span class="text-muted small text-nowrap">${formatDateTime(d.created_at)}</span>
        </li>`).join('')
      : '<li class="list-group-item text-muted py-3">Aucun événement enregistré.</li>';
  }

  navigateToView('request-detail', false);
  window.location.hash = `request-detail/${encodeURIComponent(req.reference)}`;
}

window.approveRequest = function (reference) {
  openApproveRequestModal(reference);
};

function openApproveRequestModal(reference) {
  if (!AppState.canManageOps) {
    showToast('Seul un responsable, délégataire actif ou administrateur peut valider une demande.', 'danger');
    return;
  }
  const req = (AppState.currentRequests || []).find(r => r.reference === reference);
  if (!req) {
    BackendApi.approveRequest(reference);
    return;
  }

  AppState.selectedRequestRef = reference;
  const refLabel = document.getElementById('approveModalRefLabel');
  if (refLabel) refLabel.textContent = req.reference;
  const commentEl = document.getElementById('approveModalComment');
  if (commentEl) commentEl.value = '';

  const tbody = document.getElementById('approveModalItemsBody');
  const wrapper = document.getElementById('approveModalItemsWrapper');
  const items = req.items || [];
  if (tbody && wrapper) {
    if (items.length === 0) {
      wrapper.style.display = 'none';
      tbody.innerHTML = '';
    } else {
      wrapper.style.display = 'block';
      tbody.innerHTML = items.map(it => {
        const art = it.article || {};
        const maxStock = art.quantity ?? it.quantity_requested;
        const defaultGranted = Math.min(it.quantity_requested, Math.max(0, maxStock));
        return `
          <tr>
            <td><strong>${escHtml(art.code || '')}</strong> ${escHtml(art.name || 'Article')}</td>
            <td class="text-center"><span class="badge ${maxStock >= it.quantity_requested ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'}">${maxStock} ${escHtml(art.unit || '')}</span></td>
            <td class="text-center fw-semibold">${it.quantity_requested}</td>
            <td class="text-center">
              <input type="number" class="form-control form-control-sm text-center js-approve-item-qty" data-item-id="${it.id}" min="0" max="${maxStock}" value="${defaultGranted}" required>
            </td>
          </tr>`;
      }).join('');
    }
  }

  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalApproveRequest')).show();
}

function openRequestReasonModal(actionType, reference) {
  const modalEl = document.getElementById('modalRequestReason');
  if (!modalEl) return;
  document.getElementById('requestReasonActionType').value = actionType;
  document.getElementById('requestReasonTargetRef').value = reference;
  document.getElementById('requestReasonTextarea').value = '';

  const titleEl = document.getElementById('requestReasonModalTitle');
  const labelEl = document.getElementById('requestReasonLabel');
  const submitBtn = document.getElementById('requestReasonSubmitBtn');

  if (actionType === 'reject') {
    if (titleEl) titleEl.textContent = `Rejeter la demande ${reference}`;
    if (labelEl) labelEl.innerHTML = 'Motif du rejet (obligatoire) <span class="text-danger">*</span>';
    if (submitBtn) { submitBtn.className = 'btn-gp-primary bg-danger border-danger'; submitBtn.textContent = 'Confirmer le rejet'; }
  } else if (actionType === 'request-correction') {
    if (titleEl) titleEl.textContent = `Demander un complément (${reference})`;
    if (labelEl) labelEl.innerHTML = 'Précisions ou justificatifs attendus du demandeur <span class="text-danger">*</span>';
    if (submitBtn) { submitBtn.className = 'btn-gp-primary'; submitBtn.textContent = 'Envoyer la demande de complément'; }
  } else {
    if (titleEl) titleEl.textContent = `Annuler la demande ${reference}`;
    if (labelEl) labelEl.innerHTML = 'Motif de l’annulation (obligatoire) <span class="text-danger">*</span>';
    if (submitBtn) { submitBtn.className = 'btn-gp-primary bg-danger border-danger'; submitBtn.textContent = 'Confirmer l’annulation'; }
  }

  bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

async function openPdfModal(reference) {
  if (!reference) {
    showToast('Sélectionnez d’abord une demande.', 'warning');
    return false;
  }

  let req = (AppState.currentRequests || []).find(r => r.reference === reference);
  try {
    const response = await fetch(`/api/requests/${encodeURIComponent(reference)}`);
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) req = result.data;
    }
  } catch (error) {
    console.warn('Chargement du bon impossible depuis l’API :', error);
  }

  if (!req) {
    showToast('La demande sélectionnée est introuvable.', 'danger');
    return false;
  }

  if (!AUTHORIZED_REQUEST_STATUSES.includes(req.status)) {
    showToast('Le bon officiel est disponible uniquement après la validation de la demande.', 'warning');
    return false;
  }

  const orgName = AppState.catalogRefs?.settings?.company_name || 'GESTION+ — Direction Administrative & Logistique';
  const orgAddress = AppState.catalogRefs?.settings?.company_address || 'Abidjan, Côte d’Ivoire';
  const orgPhone = AppState.catalogRefs?.settings?.company_phone || '';

  const pdfOrgTitle = document.getElementById('pdfOrgTitle');
  if (pdfOrgTitle) pdfOrgTitle.innerHTML = `<i class="bi bi-layers-half"></i> ${escHtml(orgName)}`;
  const pdfOrgAddr = document.getElementById('pdfOrgAddressBlock');
  if (pdfOrgAddr) pdfOrgAddr.textContent = [orgAddress, orgPhone].filter(Boolean).join(' • ');
  const stamp = document.getElementById('pdfStatusStamp');
  if (stamp) stamp.textContent = req.status.toUpperCase();

  const setTxt = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
  setTxt('pdfModalRef', req.reference);
  setTxt('pdfModalDate', `Émis le : ${formatDateShort(req.approved_at || req.created_at)}`);
  setTxt('pdfModalDemandeur', req.user?.name || 'Demandeur');
  setTxt('pdfModalService', req.service || 'Direction Générale');
  setTxt('pdfModalMotif', req.motif || 'Dotation interne');
  setTxt('pdfModalType', `${req.category || req.type_operation || 'Demande interne'} (${req.type_operation || 'Dotation'})`);
  setTxt('pdfSignatureCollaborator', `Signé numériquement par ${req.user?.name || 'le demandeur'}${req.received_at ? ` (Réception confirmée le ${formatDateShort(req.received_at)})` : ''}`);
  setTxt('pdfSignatureAdmin', `Validé par ${req.approver?.name || 'le Responsable habilité'}`);

  const tbody = document.getElementById('pdfModalArticlesBody');
  if (tbody) {
    const items = req.items || [];
    if (items.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-dark">${escHtml(req.service_description || req.motif || 'Prestation / intervention validée')}</td></tr>`;
    } else {
      tbody.innerHTML = items.map(it => {
        const art = it.article || {};
        return `
          <tr>
            <td><strong>${escHtml(art.code || '-')}</strong></td>
            <td>${escHtml(art.name || 'Article')}</td>
            <td>${escHtml(art.category || 'Fournitures')}</td>
            <td>${escHtml(art.unit || 'unité')}</td>
            <td class="text-center">${it.quantity_requested}</td>
            <td class="text-center fw-bold text-success">${it.quantity_approved ?? it.quantity_requested}</td>
          </tr>`;
      }).join('');
    }
  }

  const printableDocument = document.getElementById('printablePdfDocument');
  if (printableDocument) printableDocument.dataset.reference = req.reference;

  const modalEl = document.getElementById('modalOfficialPdf');
  if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
  return true;
}

/* ==========================================================================
   3. Module 3 : Articles, Réapprovisionnement & Inventaires Physiques
   ========================================================================== */
function resetArticleFormMode() {
  const form = document.getElementById('formNewArticle');
  if (!form) return;
  form.reset();
  delete form.dataset.articleId;

  const codeWrapper = document.getElementById('artCodePreviewWrapper');
  const catWrapper = document.getElementById('artCategoryWrapper');
  const codePreview = document.getElementById('artCodePreview');
  const title = document.getElementById('articleModalTitle');
  const quantityLabel = document.getElementById('articleQuantityLabel');
  const submitButton = document.getElementById('articleSubmitButton');
  if (codeWrapper) codeWrapper.style.display = 'none';
  if (catWrapper) catWrapper.className = 'col-12';
  if (codePreview) codePreview.textContent = '-';
  if (title) title.textContent = 'Ajouter un nouvel article en stock';
  if (quantityLabel) quantityLabel.textContent = 'Quantité initiale';
  if (submitButton) submitButton.innerHTML = '<i class="bi bi-plus-circle me-1"></i> Créer l’article';
}

window.openEditArticle = async function (articleId) {
  if (!AppState.canManageOps) {
    showToast('Action restreinte aux responsables et administrateurs.', 'danger');
    return;
  }

  let article = AppState.currentArticles.find(item => Number(item.id) === Number(articleId));
  if (!article) {
    try {
      const response = await fetch(`/api/articles/${articleId}`);
      const result = await response.json();
      if (response.ok && result.success) article = result.data;
    } catch (error) {
      console.warn('Chargement de l’article impossible :', error);
    }
  }
  if (!article) {
    showToast('Impossible de charger cet article.', 'danger');
    return;
  }

  const form = document.getElementById('formNewArticle');
  if (!form) return;
  form.dataset.articleId = article.id;

  const codeWrapper = document.getElementById('artCodePreviewWrapper');
  const catWrapper = document.getElementById('artCategoryWrapper');
  const codePreview = document.getElementById('artCodePreview');
  if (codeWrapper) codeWrapper.style.display = '';
  if (catWrapper) catWrapper.className = 'col-6';
  if (codePreview) codePreview.textContent = article.code;
  document.getElementById('artCategory').value = article.category;
  document.getElementById('artName').value = article.name;
  document.getElementById('artUnit').value = article.unit;
  document.getElementById('artQty').value = article.quantity;
  document.getElementById('artThreshold').value = article.threshold;
  document.getElementById('artLocation').value = article.location || '';
  document.getElementById('artMaximumThreshold').value = article.maximum_threshold || '';
  document.getElementById('artUnitCost').value = article.unit_cost || '';
  document.getElementById('artSupplier').value = article.supplier || '';

  const title = document.getElementById('articleModalTitle');
  const quantityLabel = document.getElementById('articleQuantityLabel');
  const submitButton = document.getElementById('articleSubmitButton');
  if (title) title.textContent = `Modifier la fiche ${article.code}`;
  if (quantityLabel) quantityLabel.textContent = 'Quantité actuelle';
  if (submitButton) submitButton.innerHTML = '<i class="bi bi-check-circle me-1"></i> Enregistrer les modifications';

  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNewArticle')).show();
};

window.deleteArticle = async function (articleId, articleName) {
  if (!AppState.isSuperAdmin) {
    showToast('Seul l’administrateur peut supprimer une fiche article.', 'danger');
    return;
  }
  if (!confirm(`Supprimer l’article "${articleName}" du catalogue ?`)) return;
  try {
    const res = await fetch(`/api/articles/${articleId}`, { method: 'DELETE' });
    const data = await res.json();
    showToast(data.message || (res.ok ? 'Article supprimé.' : 'Suppression refusée.'), res.ok ? 'success' : 'danger');
    if (res.ok) await BackendApi.refreshAll({ showIndicator: true, silent: true });
  } catch (e) {
    showToast('Impossible de supprimer cet article pour le moment.', 'danger');
  }
};

async function openReplenishmentModal() {
  try {
    const res = await fetch('/api/articles-replenishment');
    const json = await res.json();
    if (!res.ok || !json.success) {
      showToast(json.message || 'Impossible de générer l’état de réapprovisionnement.', 'danger');
      return;
    }

    const totalEl = document.getElementById('replenishmentEstimatedTotal');
    if (totalEl) totalEl.textContent = `Coût estimatif total : ${formatCurrency(json.total_estimated_cost || 0)}`;

    const tbody = document.getElementById('replenishmentTableBody');
    if (tbody) {
      const items = json.data || [];
      tbody.innerHTML = items.length
        ? items.map(it => `
          <tr>
            <td class="fw-bold text-primary">${escHtml(it.code)}</td>
            <td>
              <strong>${escHtml(it.name)}</strong>
              <small class="d-block text-muted">${escHtml(it.category)} • ${escHtml(it.location || 'Magasin')}</small>
            </td>
            <td>${escHtml(it.supplier || '—')}</td>
            <td class="text-center"><span class="badge ${it.quantity <= 0 ? 'bg-danger' : 'bg-warning text-dark'}">${it.quantity} ${escHtml(it.unit)}</span></td>
            <td class="text-center">${it.threshold} / ${it.maximum_threshold}</td>
            <td class="text-center fw-bold text-primary">+${it.suggested_order_quantity} ${escHtml(it.unit)}</td>
            <td class="text-end fw-semibold">${formatCurrency(it.estimated_order_cost)}</td>
          </tr>`).join('')
        : '<tr><td colspan="7" class="text-center text-success py-4"><i class="bi bi-check-circle me-1"></i>Tous les articles sont au-dessus de leur seuil minimal. Aucun réapprovisionnement requis.</td></tr>';
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalReplenishment')).show();
  } catch (e) {
    showToast('Impossible de charger l’état de réapprovisionnement.', 'danger');
  }
}

function addInventoryCountingRow(article) {
  const tbody = document.getElementById('inventoryCountingTableBody');
  if (!tbody || !article) return;

  if (tbody.querySelector(`[data-inv-article-id="${article.id}"]`)) {
    showToast(`L’article ${article.code} figure déjà dans la grille de comptage.`, 'info');
    return;
  }

  if (tbody.querySelector('td[colspan]')) {
    tbody.innerHTML = '';
  }

  const tr = document.createElement('tr');
  tr.setAttribute('data-inv-article-id', article.id);
  tr.setAttribute('data-theoretical-qty', article.quantity);
  tr.innerHTML = `
    <td><strong>${escHtml(article.code)}</strong> — ${escHtml(article.name)} <small class="text-muted">(${escHtml(article.unit)})</small></td>
    <td class="text-center fw-semibold">${article.quantity}</td>
    <td class="text-center">
      <input type="number" class="form-control form-control-sm text-center js-inv-physical-qty" min="0" value="${article.quantity}" required>
    </td>
    <td class="text-center fw-bold js-inv-variance-cell text-muted">0</td>
    <td>
      <input type="text" class="form-control form-control-sm js-inv-justification" placeholder="Motif obligatoire si écart ≠ 0">
    </td>
    <td class="text-end">
      <button type="button" class="btn-gp-icon text-danger js-remove-inv-row" title="Retirer"><i class="bi bi-trash"></i></button>
    </td>
  `;

  const qtyInput = tr.querySelector('.js-inv-physical-qty');
  const varCell = tr.querySelector('.js-inv-variance-cell');
  const justifInput = tr.querySelector('.js-inv-justification');
  const updateVariance = () => {
    const phys = parseInt(qtyInput.value, 10) || 0;
    const diff = phys - Number(article.quantity);
    varCell.textContent = diff > 0 ? `+${diff}` : String(diff);
    varCell.className = `text-center fw-bold js-inv-variance-cell ${diff === 0 ? 'text-muted' : (diff > 0 ? 'text-success' : 'text-danger')}`;
    justifInput.required = diff !== 0;
  };
  qtyInput.addEventListener('input', updateVariance);
  tr.querySelector('.js-remove-inv-row').addEventListener('click', () => {
    tr.remove();
    if (!tbody.children.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Sélectionnez un article ou cliquez sur « Charger tout le stock » pour saisir le comptage physique.</td></tr>';
    }
  });

  tbody.appendChild(tr);
}

window.openInventoryDetailModal = function (inventoryId) {
  const inv = (AppState.currentInventories || []).find(i => Number(i.id) === Number(inventoryId));
  if (!inv) return;

  const titleEl = document.getElementById('inventoryDetailModalTitle');
  const bodyEl = document.getElementById('inventoryDetailModalBody');
  const btnValidate = document.getElementById('btnModalValidateInventory');
  if (titleEl) titleEl.textContent = `Inventaire ${inv.reference} (${inv.scope}) — ${inv.status}`;

  const items = inv.items || [];
  if (bodyEl) {
    bodyEl.innerHTML = `
      <div class="d-flex justify-content-between flex-wrap gap-2 mb-3 small text-muted">
        <span>Date : <strong>${formatDateShort(inv.inventory_date)}</strong></span>
        <span>Créé par : <strong>${escHtml(inv.creator?.name || 'Gestionnaire')}</strong></span>
        ${inv.validator?.name ? `<span>Validé par : <strong>${escHtml(inv.validator.name)}</strong></span>` : ''}
      </div>
      ${inv.notes ? `<div class="alert alert-light border py-2 small mb-3">${escHtml(inv.notes)}</div>` : ''}
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr><th>Article</th><th class="text-center">Théorique</th><th class="text-center">Physique</th><th class="text-center">Écart</th><th>Justification</th></tr>
          </thead>
          <tbody>
            ${items.map(it => {
              const v = Number(it.variance || 0);
              return `<tr>
                <td><strong>${escHtml(it.article?.code || '')}</strong> ${escHtml(it.article?.name || '')}</td>
                <td class="text-center">${it.theoretical_quantity}</td>
                <td class="text-center fw-bold">${it.physical_quantity}</td>
                <td class="text-center fw-bold ${v === 0 ? 'text-muted' : (v > 0 ? 'text-success' : 'text-danger')}">${v > 0 ? `+${v}` : v}</td>
                <td class="small">${escHtml(it.justification || '—')}</td>
              </tr>`;
            }).join('')}
          </tbody>
        </table>
      </div>
    `;
  }

  if (btnValidate) {
    if (inv.status === 'En attente de validation') {
      btnValidate.style.display = 'inline-flex';
      btnValidate.onclick = async () => {
        await window.validateInventory(inv.id);
        bootstrap.Modal.getInstance(document.getElementById('modalInventoryDetail'))?.hide();
      };
    } else {
      btnValidate.style.display = 'none';
    }
  }

  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalInventoryDetail')).show();
};

window.validateInventory = async inventoryId => {
  const response = await fetch(`/api/inventories/${inventoryId}/validate`, { method: 'PUT' });
  const data = await response.json();
  showToast(data.message || (response.ok ? 'Inventaire validé.' : 'Validation impossible.'), response.ok ? 'success' : 'danger');
  if (response.ok) await BackendApi.refreshAll({ showIndicator: true, silent: true });
};

/* ==========================================================================
   4. Module 4 : Gestion Complète d'un Bien du Patrimoine (Modal polyvalente)
   ========================================================================== */
window.openAssetModal = function (assetId, mode = 'history') {
  const asset = (AppState.currentAssets || []).find(a => Number(a.id) === Number(assetId));
  if (!asset) return;

  const titleEl = document.getElementById('assetModalTitle');
  const bodyEl = document.getElementById('assetModalBody');
  const modalEl = document.getElementById('modalAssetAction');
  if (!bodyEl || !modalEl) return;

  const userOptions = (AppState.currentAssignees || [])
    .map(u => `<option value="${u.id}" ${Number(asset.assigned_user_id) === Number(u.id) ? 'selected' : ''}>${escHtml(u.name)} (${escHtml(u.service || 'Agent')})</option>`)
    .join('');

  if (mode === 'edit') {
    if (titleEl) titleEl.textContent = `Modifier la fiche ${asset.inventory_number}`;
    bodyEl.innerHTML = `
      <form id="dynFormEditAsset" class="row g-3">
        <div class="col-md-6"><label class="form-label small fw-semibold">Désignation</label><input name="name" class="form-control" value="${escHtml(asset.name)}" required></div>
        <div class="col-md-6"><label class="form-label small fw-semibold">Catégorie</label><input name="category" class="form-control" value="${escHtml(asset.category)}" required></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">N° de série</label><input name="serial_number" class="form-control" value="${escHtml(asset.serial_number || '')}"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">État physique</label>
          <select name="condition" class="form-select">
            ${['Neuf', 'Bon état', 'Endommagé', 'Hors service'].map(c => `<option value="${c}" ${asset.condition === c ? 'selected' : ''}>${c}</option>`).join('')}
          </select>
        </div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Coût d’acquisition</label><input name="acquisition_cost" type="number" step="0.01" min="0" class="form-control" value="${asset.acquisition_cost || ''}"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Date d’acquisition</label><input name="acquired_at" type="date" class="form-control" value="${asset.acquired_at ? String(asset.acquired_at).slice(0, 10) : ''}"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Service</label><input name="department" class="form-control" value="${escHtml(asset.department || '')}"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Site / Bureau</label><input name="location" class="form-control" value="${escHtml(asset.location || '')}"></div>
        <div class="col-12"><label class="form-label small fw-semibold">Observations</label><textarea name="notes" class="form-control" rows="2">${escHtml(asset.notes || '')}</textarea></div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn-gp-primary">Enregistrer</button>
        </div>
      </form>`;
    document.getElementById('dynFormEditAsset').onsubmit = async (e) => {
      e.preventDefault();
      const payload = Object.fromEntries(new FormData(e.currentTarget).entries());
      const res = await fetch(`/api/assets/${asset.id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      const data = await res.json();
      showToast(data.message || 'Fiche mise à jour.', res.ok ? 'success' : 'danger');
      if (res.ok) { bootstrap.Modal.getInstance(modalEl)?.hide(); await BackendApi.loadOperations(); }
    };
  } else if (mode === 'assign') {
    if (titleEl) titleEl.textContent = `Remise / Affectation — ${asset.inventory_number}`;
    bodyEl.innerHTML = `
      <form id="dynFormAssignAsset" class="row g-3">
        <div class="col-md-6"><label class="form-label small fw-semibold">Employé détenteur</label><select name="user_id" class="form-select"><option value="">Affectation au service uniquement</option>${userOptions}</select></div>
        <div class="col-md-6"><label class="form-label small fw-semibold">Service / Département</label><input name="department" class="form-control" value="${escHtml(asset.department || '')}" placeholder="Ex: Informatique & SI"></div>
        <div class="col-md-6"><label class="form-label small fw-semibold">Site / Bureau</label><input name="location" class="form-control" value="${escHtml(asset.location || '')}" placeholder="Ex: Bureau 202"></div>
        <div class="col-md-6"><label class="form-label small fw-semibold">État à la remise</label>
          <select name="condition" class="form-select">
            ${['Neuf', 'Bon état', 'Endommagé'].map(c => `<option value="${c}" ${asset.condition === c ? 'selected' : ''}>${c}</option>`).join('')}
          </select>
        </div>
        <div class="col-12"><label class="form-label small fw-semibold">Notes de remise</label><textarea name="notes" class="form-control" rows="2" placeholder="Accessoires fournis, état constaté..."></textarea></div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn-gp-primary">Confirmer la remise</button>
        </div>
      </form>`;
    document.getElementById('dynFormAssignAsset').onsubmit = async (e) => {
      e.preventDefault();
      const payload = Object.fromEntries([...new FormData(e.currentTarget).entries()].filter(([, v]) => v !== ''));
      const res = await fetch(`/api/assets/${asset.id}/assign`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      const data = await res.json();
      showToast(data.message || 'Affectation enregistrée.', res.ok ? 'success' : 'danger');
      if (res.ok) { bootstrap.Modal.getInstance(modalEl)?.hide(); await BackendApi.loadOperations(); }
    };
  } else if (mode === 'return') {
    if (titleEl) titleEl.textContent = `Restitution d’équipement — ${asset.inventory_number}`;
    bodyEl.innerHTML = `
      <form id="dynFormReturnAsset" class="row g-3">
        <div class="col-md-6"><label class="form-label small fw-semibold">État constaté au retour</label>
          <select name="condition" class="form-select">
            ${['Neuf', 'Bon état', 'Endommagé', 'Hors service'].map(c => `<option value="${c}" ${asset.condition === c ? 'selected' : ''}>${c}</option>`).join('')}
          </select>
        </div>
        <div class="col-md-6"><label class="form-label small fw-semibold">Emplacement de rangement</label><input name="location" class="form-control" value="${escHtml(asset.location || 'Magasin Central')}"></div>
        <div class="col-12"><label class="form-label small fw-semibold">Observations de restitution</label><textarea name="notes" class="form-control" rows="2" placeholder="Motif du retour, état général..."></textarea></div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn-gp-primary">Enregistrer la restitution</button>
        </div>
      </form>`;
    document.getElementById('dynFormReturnAsset').onsubmit = async (e) => {
      e.preventDefault();
      const payload = Object.fromEntries(new FormData(e.currentTarget).entries());
      const res = await fetch(`/api/assets/${asset.id}/return`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      const data = await res.json();
      showToast(data.message || 'Restitution enregistrée.', res.ok ? 'success' : 'danger');
      if (res.ok) { bootstrap.Modal.getInstance(modalEl)?.hide(); await BackendApi.loadOperations(); }
    };
  } else if (mode === 'incident') {
    if (titleEl) titleEl.textContent = `Signaler un incident — ${asset.inventory_number}`;
    bodyEl.innerHTML = `
      <form id="dynFormAssetIncident" class="row g-3">
        <div class="col-md-4"><label class="form-label small fw-semibold">Gravité <span class="text-danger">*</span></label>
          <select name="severity" class="form-select" required>
            <option value="Mineur">Mineur</option>
            <option value="Majeur">Majeur (Bien endommagé)</option>
            <option value="Critique">Critique (Hors service)</option>
          </select>
        </div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Date du constat</label><input type="date" name="incident_date" class="form-control" value="${new Date().toISOString().slice(0, 10)}"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Référence incident</label><div class="form-control bg-light text-muted small">Auto (INC-...)</div></div>
        <div class="col-12"><label class="form-label small fw-semibold">Objet du dysfonctionnement <span class="text-danger">*</span></label><input name="title" class="form-control" required placeholder="Ex: Écran fissuré, panne d’alimentation..."></div>
        <div class="col-12"><label class="form-label small fw-semibold">Description circonstanciée <span class="text-danger">*</span></label><textarea name="description" class="form-control" rows="3" required></textarea></div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn-gp-primary bg-danger border-danger">Enregistrer l’incident</button>
        </div>
      </form>`;
    document.getElementById('dynFormAssetIncident').onsubmit = async (e) => {
      e.preventDefault();
      const payload = Object.fromEntries(new FormData(e.currentTarget).entries());
      const res = await fetch(`/api/assets/${asset.id}/incidents`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      const data = await res.json();
      showToast(data.message || 'Incident enregistré.', res.ok ? 'success' : 'danger');
      if (res.ok) { bootstrap.Modal.getInstance(modalEl)?.hide(); await BackendApi.loadOperations(); }
    };
  } else if (mode === 'maintenance') {
    if (titleEl) titleEl.textContent = `Maintenance / Réparation — ${asset.inventory_number}`;
    bodyEl.innerHTML = `
      <form id="dynFormAssetMaint" class="row g-3">
        <div class="col-md-4"><label class="form-label small fw-semibold">Type d’intervention</label>
          <select name="type" class="form-select"><option value="Maintenance préventive">Maintenance préventive</option><option value="Réparation">Réparation</option><option value="Remplacement pièce">Remplacement pièce</option></select>
        </div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Statut</label>
          <select name="status" class="form-select"><option value="Planifiée">Planifiée</option><option value="En cours">En cours</option><option value="Terminée">Terminée</option></select>
        </div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Coût (FCFA)</label><input type="number" name="cost" min="0" step="0.01" value="0" class="form-control"></div>
        <div class="col-md-6"><label class="form-label small fw-semibold">Prestataire / Technicien</label><input name="provider" list="datalistSuppliers" class="form-control" placeholder="Nom du prestataire"></div>
        <div class="col-md-6"><label class="form-label small fw-semibold">Date d’intervention</label><input type="date" name="scheduled_at" class="form-control" value="${new Date().toISOString().slice(0, 10)}"></div>
        <div class="col-12"><label class="form-label small fw-semibold">Travaux effectués / prévus <span class="text-danger">*</span></label><textarea name="description" class="form-control" rows="2" required></textarea></div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn-gp-primary">Enregistrer l’intervention</button>
        </div>
      </form>`;
    document.getElementById('dynFormAssetMaint').onsubmit = async (e) => {
      e.preventDefault();
      const payload = { ...Object.fromEntries(new FormData(e.currentTarget).entries()), resource_type: 'asset', resource_id: asset.id };
      const res = await fetch('/api/maintenance-records', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      const data = await res.json();
      showToast(data.message || 'Intervention enregistrée.', res.ok ? 'success' : 'danger');
      if (res.ok) { bootstrap.Modal.getInstance(modalEl)?.hide(); await BackendApi.loadOperations(); }
    };
  } else {
    // Historique complet (affectations, incidents, maintenances)
    if (titleEl) titleEl.textContent = `Fiche & Historique — ${asset.inventory_number} (${asset.name})`;
    const assignments = asset.assignments || [];
    const incidents = asset.incidents || [];
    const maintenances = asset.maintenance_records || [];
    bodyEl.innerHTML = `
      <div class="row g-2 mb-3 small">
        <div class="col-md-4"><div class="p-2 bg-light rounded border"><strong>Catégorie :</strong> ${escHtml(asset.category)}<br><strong>Série :</strong> ${escHtml(asset.serial_number || '—')}</div></div>
        <div class="col-md-4"><div class="p-2 bg-light rounded border"><strong>État :</strong> ${escHtml(asset.condition)}<br><strong>Disponibilité :</strong> ${escHtml(asset.availability)}</div></div>
        <div class="col-md-4"><div class="p-2 bg-light rounded border"><strong>Acquisition :</strong> ${formatDateShort(asset.acquired_at)}<br><strong>Coût :</strong> ${formatCurrency(asset.acquisition_cost)}</div></div>
      </div>

      <h6 class="fw-bold mb-2"><i class="bi bi-arrow-left-right text-primary me-1"></i>Historique des affectations</h6>
      <div class="table-responsive mb-3">
        <table class="table table-sm small">
          <thead class="table-light"><tr><th>Détenteur / Service</th><th>Site</th><th>Remis le</th><th>Restitué le</th><th>Notes</th></tr></thead>
          <tbody>
            ${assignments.length ? assignments.map(a => `<tr>
              <td>${escHtml(a.user?.name || a.department || '—')}</td>
              <td>${escHtml(a.location || '—')}</td>
              <td>${formatDateTime(a.assigned_at)}</td>
              <td>${a.returned_at ? formatDateTime(a.returned_at) : '<span class="badge bg-primary-subtle text-primary">En cours</span>'}</td>
              <td>${escHtml(a.notes || '—')}</td>
            </tr>`).join('') : '<tr><td colspan="5" class="text-muted text-center">Aucune affectation enregistrée.</td></tr>'}
          </tbody>
        </table>
      </div>

      <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-octagon text-danger me-1"></i>Incidents signalés (${incidents.length})</h6>
      <div class="table-responsive mb-3">
        <table class="table table-sm small align-middle">
          <thead class="table-light"><tr><th>Réf</th><th>Gravité & Objet</th><th>Date</th><th>Statut</th><th class="text-end">Action</th></tr></thead>
          <tbody>
            ${incidents.length ? incidents.map(inc => `<tr>
              <td class="fw-bold">${escHtml(inc.reference)}</td>
              <td><span class="badge bg-danger-subtle text-danger me-1">${escHtml(inc.severity)}</span> ${escHtml(inc.title)}<div class="text-muted">${escHtml(inc.description)}</div></td>
              <td>${formatDateShort(inc.incident_date)}</td>
              <td>${escHtml(inc.status)}</td>
              <td class="text-end">${inc.status !== 'Résolu' ? `<button class="btn btn-sm btn-outline-success py-0 px-2" onclick="resolveAssetIncidentPrompt(${inc.id})">Résoudre</button>` : escHtml(inc.resolution_notes || 'Résolu')}</td>
            </tr>`).join('') : '<tr><td colspan="5" class="text-muted text-center">Aucun incident signalé.</td></tr>'}
          </tbody>
        </table>
      </div>

      <h6 class="fw-bold mb-2"><i class="bi bi-tools text-secondary me-1"></i>Maintenances & Réparations (${maintenances.length})</h6>
      <div class="table-responsive">
        <table class="table table-sm small">
          <thead class="table-light"><tr><th>Type</th><th>Prestataire</th><th>Date</th><th>Coût</th><th>Statut</th></tr></thead>
          <tbody>
            ${maintenances.length ? maintenances.map(m => `<tr>
              <td>${escHtml(m.type)}<div class="text-muted">${escHtml(m.description)}</div></td>
              <td>${escHtml(m.provider || 'Interne')}</td>
              <td>${formatDateShort(m.scheduled_at || m.created_at)}</td>
              <td>${formatCurrency(m.cost)}</td>
              <td>${escHtml(m.status)}</td>
            </tr>`).join('') : '<tr><td colspan="5" class="text-muted text-center">Aucune maintenance enregistrée.</td></tr>'}
          </tbody>
        </table>
      </div>
    `;
  }

  bootstrap.Modal.getOrCreateInstance(modalEl).show();
};

window.resolveAssetIncidentPrompt = async function (incidentId) {
  const notes = prompt('Indiquez les actions correctives réalisées pour clôturer cet incident :', 'Réparation effectuée et équipement remis en bon état.');
  if (!notes || !notes.trim()) return;
  const res = await fetch(`/api/asset-incidents/${incidentId}/resolve`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ resolution_notes: notes.trim(), asset_condition: 'Bon état' })
  });
  const data = await res.json();
  showToast(data.message || 'Incident résolu.', res.ok ? 'success' : 'danger');
  if (res.ok) {
    bootstrap.Modal.getInstance(document.getElementById('modalAssetAction'))?.hide();
    await BackendApi.loadOperations();
  }
};

/* ==========================================================================
   5. Module 5 : Gestion Complète d'un Véhicule (Modal polyvalente)
   ========================================================================== */
window.openVehicleModal = function (vehicleId, mode = 'history') {
  const vehicle = (AppState.currentVehicles || []).find(v => Number(v.id) === Number(vehicleId));
  if (!vehicle) return;

  const titleEl = document.getElementById('vehicleModalTitle');
  const bodyEl = document.getElementById('vehicleModalBody');
  const modalEl = document.getElementById('modalVehicleAction');
  if (!bodyEl || !modalEl) return;

  const driverOptions = (AppState.currentAssignees || [])
    .map(u => `<option value="${u.id}" ${Number(vehicle.driver_id) === Number(u.id) ? 'selected' : ''}>${escHtml(u.name)} (${escHtml(u.service || '')})</option>`)
    .join('');

  if (mode === 'edit') {
    if (titleEl) titleEl.textContent = `Mise à jour véhicule & kilométrage — ${vehicle.registration_number}`;
    bodyEl.innerHTML = `
      <form id="dynFormEditVehicle" class="row g-3">
        <div class="col-md-4"><label class="form-label small fw-semibold">Immatriculation</label><input name="registration_number" class="form-control" value="${escHtml(vehicle.registration_number)}" required></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Marque</label><input name="brand" class="form-control" value="${escHtml(vehicle.brand)}" required></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Modèle</label><input name="model" class="form-control" value="${escHtml(vehicle.model)}" required></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Kilométrage actuel (km)</label><input type="number" name="mileage" min="0" class="form-control fw-bold" value="${vehicle.mileage || 0}" required></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">État</label>
          <select name="status" class="form-select">
            ${['Disponible', 'En service', 'Maintenance', 'Immobilisé'].map(s => `<option value="${s}" ${vehicle.status === s ? 'selected' : ''}>${s}</option>`).join('')}
          </select>
        </div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Conducteur</label><select name="driver_id" class="form-select"><option value="">Aucun conducteur</option>${driverOptions}</select></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Service affecté</label><input name="department" class="form-control" value="${escHtml(vehicle.department || '')}"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Assurance expire le</label><input type="date" name="insurance_expires_at" class="form-control" value="${vehicle.insurance_expires_at ? String(vehicle.insurance_expires_at).slice(0, 10) : ''}"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Visite technique expire le</label><input type="date" name="inspection_expires_at" class="form-control" value="${vehicle.inspection_expires_at ? String(vehicle.inspection_expires_at).slice(0, 10) : ''}"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Vignette expire le</label><input type="date" name="tax_expires_at" class="form-control" value="${vehicle.tax_expires_at ? String(vehicle.tax_expires_at).slice(0, 10) : ''}"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Prochain entretien (Date)</label><input type="date" name="next_maintenance_at" class="form-control" value="${vehicle.next_maintenance_at ? String(vehicle.next_maintenance_at).slice(0, 10) : ''}"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Prochain entretien (Seuil Km)</label><input type="number" name="next_maintenance_mileage" min="0" class="form-control" value="${vehicle.next_maintenance_mileage || ''}"></div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn-gp-primary">Enregistrer</button>
        </div>
      </form>`;
    document.getElementById('dynFormEditVehicle').onsubmit = async (e) => {
      e.preventDefault();
      const raw = Object.fromEntries(new FormData(e.currentTarget).entries());
      const payload = Object.fromEntries(Object.entries(raw).map(([k, v]) => [k, v === '' ? null : v]));
      const res = await fetch(`/api/vehicles/${vehicle.id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      const data = await res.json();
      showToast(data.message || 'Véhicule mis à jour.', res.ok ? 'success' : 'danger');
      if (res.ok) { bootstrap.Modal.getInstance(modalEl)?.hide(); await BackendApi.loadOperations(); }
    };
  } else if (mode === 'documents') {
    if (titleEl) titleEl.textContent = `Documents administratifs & Échéances — ${vehicle.registration_number}`;
    const docs = vehicle.documents || [];
    bodyEl.innerHTML = `
      <form id="dynFormVehicleDoc" class="row g-2 p-3 bg-light border rounded mb-3" enctype="multipart/form-data">
        <div class="col-md-4"><label class="form-label small fw-semibold">Type de document</label>
          <select name="document_type" class="form-select form-select-sm" required>
            <option value="Attestation d'assurance">Attestation d'assurance</option>
            <option value="Certificat de visite technique">Visite technique</option>
            <option value="Vignette automobile">Vignette automobile</option>
            <option value="Carte grise">Carte grise</option>
            <option value="Autre document">Autre document</option>
          </select>
        </div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Date d’expiration</label><input type="date" name="expires_at" class="form-control form-control-sm"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Fichier (PDF / Image)</label><input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"></div>
        <div class="col-md-9"><input name="notes" class="form-control form-control-sm" placeholder="Compagnie d’assurance, numéro de police..."></div>
        <div class="col-md-3"><button type="submit" class="btn-gp-primary btn-sm w-100 justify-content-center"><i class="bi bi-plus"></i> Enregistrer</button></div>
      </form>
      <div class="table-responsive">
        <table class="table table-sm small align-middle">
          <thead class="table-light"><tr><th>Réf</th><th>Type</th><th>Expiration</th><th>Notes</th><th class="text-end">Fichier</th></tr></thead>
          <tbody>
            ${docs.length ? docs.map(d => `<tr>
              <td class="fw-semibold">${escHtml(d.reference)}</td>
              <td>${escHtml(d.document_type)}</td>
              <td>${formatDateShort(d.expires_at)}</td>
              <td>${escHtml(d.notes || '—')}</td>
              <td class="text-end">${d.file_path ? `<button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="downloadAuthenticatedFile('/api/vehicle-documents/${d.id}/download', '${escHtml(d.original_name || d.reference)}')"><i class="bi bi-download"></i></button>` : '—'}</td>
            </tr>`).join('') : '<tr><td colspan="5" class="text-center text-muted py-3">Aucun document enregistré pour ce véhicule.</td></tr>'}
          </tbody>
        </table>
      </div>`;
    document.getElementById('dynFormVehicleDoc').onsubmit = async (e) => {
      e.preventDefault();
      const fd = new FormData(e.currentTarget);
      const res = await fetch(`/api/vehicles/${vehicle.id}/documents`, { method: 'POST', body: fd });
      const data = await res.json();
      showToast(data.message || 'Document enregistré.', res.ok ? 'success' : 'danger');
      if (res.ok) { bootstrap.Modal.getInstance(modalEl)?.hide(); await BackendApi.loadOperations(); }
    };
  } else if (mode === 'maintenance') {
    if (titleEl) titleEl.textContent = `Entretien / Réparation — ${vehicle.registration_number}`;
    bodyEl.innerHTML = `
      <form id="dynFormVehicleMaint" class="row g-3">
        <div class="col-md-4"><label class="form-label small fw-semibold">Nature de l’opération</label>
          <select name="type" class="form-select"><option value="Vidange & Entretien périodique">Vidange & Entretien périodique</option><option value="Réparation mécanique">Réparation mécanique</option><option value="Pneumatiques / Freinage">Pneumatiques / Freinage</option><option value="Carrosserie / Climatisation">Carrosserie / Climatisation</option></select>
        </div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Statut</label>
          <select name="status" class="form-select"><option value="Planifiée">Planifiée</option><option value="En cours">En cours (immobilise le véhicule)</option><option value="Terminée">Terminée</option></select>
        </div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Coût (FCFA)</label><input type="number" name="cost" min="0" step="0.01" value="0" class="form-control"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Prestataire / Garage</label><input name="provider" list="datalistSuppliers" class="form-control" placeholder="Garage agréé"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Kilométrage relevé</label><input type="number" name="mileage" min="0" class="form-control" value="${vehicle.mileage || 0}"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Date prévue / réalisée</label><input type="date" name="scheduled_at" class="form-control" value="${new Date().toISOString().slice(0, 10)}"></div>
        <div class="col-md-6"><label class="form-label small fw-semibold">Prochain entretien (Date)</label><input type="date" name="next_maintenance_at" class="form-control" value="${vehicle.next_maintenance_at ? String(vehicle.next_maintenance_at).slice(0, 10) : ''}"></div>
        <div class="col-md-6"><label class="form-label small fw-semibold">Prochain entretien (Seuil Km)</label><input type="number" name="next_maintenance_mileage" min="0" class="form-control" value="${vehicle.next_maintenance_mileage || ''}"></div>
        <div class="col-12"><label class="form-label small fw-semibold">Détail des opérations <span class="text-danger">*</span></label><textarea name="description" class="form-control" rows="2" required></textarea></div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn-gp-primary">Enregistrer</button>
        </div>
      </form>`;
    document.getElementById('dynFormVehicleMaint').onsubmit = async (e) => {
      e.preventDefault();
      const raw = Object.fromEntries([...new FormData(e.currentTarget).entries()].filter(([, v]) => v !== ''));
      const payload = { ...raw, resource_type: 'vehicle', resource_id: vehicle.id };
      const res = await fetch('/api/maintenance-records', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      const data = await res.json();
      showToast(data.message || 'Opération enregistrée.', res.ok ? 'success' : 'danger');
      if (res.ok) { bootstrap.Modal.getInstance(modalEl)?.hide(); await BackendApi.loadOperations(); }
    };
  } else {
    if (titleEl) titleEl.textContent = `Historique & Coûts — ${vehicle.registration_number} (${vehicle.brand} ${vehicle.model})`;
    const maintenances = vehicle.maintenance_records || [];
    const totalCost = maintenances.reduce((s, m) => s + Number(m.cost || 0), 0);
    bodyEl.innerHTML = `
      <div class="d-flex justify-content-between flex-wrap gap-2 p-3 bg-light border rounded mb-3 small">
        <span><strong>Kilométrage :</strong> ${Number(vehicle.mileage || 0).toLocaleString('fr-FR')} km</span>
        <span><strong>Carte grise :</strong> ${escHtml(vehicle.registration_card_number || '—')}</span>
        <span><strong>Cumul dépenses auto :</strong> <span class="text-primary fw-bold">${formatCurrency(totalCost)}</span></span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm small align-middle">
          <thead class="table-light"><tr><th>Date</th><th>Intervention</th><th>Prestataire</th><th>Km</th><th>Coût</th><th>Statut</th><th class="text-end">Action</th></tr></thead>
          <tbody>
            ${maintenances.length ? maintenances.map(m => `<tr>
              <td>${formatDateShort(m.completed_at || m.scheduled_at || m.created_at)}</td>
              <td><strong>${escHtml(m.type)}</strong><div class="text-muted">${escHtml(m.description)}</div></td>
              <td>${escHtml(m.provider || '—')}</td>
              <td>${m.mileage ? `${Number(m.mileage).toLocaleString('fr-FR')} km` : '—'}</td>
              <td class="fw-semibold">${formatCurrency(m.cost)}</td>
              <td>${escHtml(m.status)}</td>
              <td class="text-end">${m.status !== 'Terminée' ? `<button class="btn btn-sm btn-outline-success py-0 px-2" onclick="completeVehicleMaintenance(${m.id})">Terminer</button>` : '—'}</td>
            </tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted py-3">Aucun entretien ou réparation enregistré.</td></tr>'}
          </tbody>
        </table>
      </div>`;
  }

  bootstrap.Modal.getOrCreateInstance(modalEl).show();
};

window.completeVehicleMaintenance = async function (maintId) {
  const res = await fetch(`/api/maintenance-records/${maintId}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ status: 'Terminée' })
  });
  const data = await res.json();
  showToast(data.message || 'Intervention clôturée.', res.ok ? 'success' : 'danger');
  if (res.ok) {
    bootstrap.Modal.getInstance(document.getElementById('modalVehicleAction'))?.hide();
    await BackendApi.loadOperations();
  }
};

/* ==========================================================================
   6. Module 6 : Suivi Financier, Contrôle, Dépassements & Justificatifs
   ========================================================================== */
window.openExpenseModal = function (expenseId, mode = 'detail') {
  const exp = (AppState.currentExpenses || []).find(e => Number(e.id) === Number(expenseId));
  if (!exp) return;

  const titleEl = document.getElementById('expenseModalTitle');
  const bodyEl = document.getElementById('expenseModalBody');
  const modalEl = document.getElementById('modalExpenseAction');
  if (!bodyEl || !modalEl) return;

  if (mode === 'edit') {
    const isProtected = exp.status === 'Validé';
    if (titleEl) titleEl.textContent = `Suivi d’exécution / Décaissement — ${exp.reference}`;
    bodyEl.innerHTML = `
      ${isProtected ? '<div class="alert alert-warning py-2 small"><i class="bi bi-shield-lock me-1"></i>Cette dépense est déjà validée. Toute révision requiert un motif explicite et sera tracée dans l’historique financier.</div>' : ''}
      <form id="dynFormUpdateExpense" class="row g-3">
        <div class="col-md-4"><label class="form-label small fw-semibold">Montant autorisé</label><div class="form-control bg-light fw-bold">${formatCurrency(exp.authorized_amount)}</div></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Montant engagé</label><input type="number" step="0.01" min="0" name="committed_amount" class="form-control" value="${exp.committed_amount || 0}"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Montant décaissé</label><input type="number" step="0.01" min="0" name="disbursed_amount" class="form-control" value="${exp.disbursed_amount || 0}"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Montant justifié</label><input type="number" step="0.01" min="0" name="justified_amount" class="form-control" value="${exp.justified_amount || 0}"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Bénéficiaire</label><input name="beneficiary" class="form-control" value="${escHtml(exp.beneficiary || '')}"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Mode de paiement</label>
          <select name="payment_method" class="form-select">
            ${['Espèces', 'Virement bancaire', 'Chèque', 'Mobile Money', 'Carte entreprise'].map(m => `<option value="${m}" ${exp.payment_method === m ? 'selected' : ''}>${m}</option>`).join('')}
          </select>
        </div>
        <div class="col-12"><label class="form-label small fw-semibold">Motif de mise à jour / révision ${isProtected ? '<span class="text-danger">*</span>' : ''}</label>
          <input name="amendment_reason" class="form-control" ${isProtected ? 'required' : ''} placeholder="Ex: Réception facture définitive, décaissement solde fournisseur...">
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn-gp-primary">Enregistrer & Historiser</button>
        </div>
      </form>`;
    document.getElementById('dynFormUpdateExpense').onsubmit = async (e) => {
      e.preventDefault();
      const payload = Object.fromEntries(new FormData(e.currentTarget).entries());
      const res = await fetch(`/api/expenses/${exp.id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      const data = await res.json();
      showToast(data.message || 'Opération mise à jour.', res.ok ? 'success' : 'danger');
      if (res.ok) { bootstrap.Modal.getInstance(modalEl)?.hide(); await BackendApi.loadOperations(); }
    };
  } else if (mode === 'validate') {
    const suggestedAuth = Math.max(Number(exp.authorized_amount || 0), Number(exp.committed_amount || 0), Number(exp.disbursed_amount || 0));
    if (titleEl) titleEl.textContent = `Contrôle & Validation financière — ${exp.reference}`;
    bodyEl.innerHTML = `
      <form id="dynFormValidateExpense" class="row g-3">
        <div class="col-12">
          <p class="small text-muted mb-1">Engagé : <strong>${formatCurrency(exp.committed_amount)}</strong> • Décaissé : <strong>${formatCurrency(exp.disbursed_amount)}</strong> • Justifié : <strong>${formatCurrency(exp.justified_amount)}</strong></p>
        </div>
        <div class="col-md-6"><label class="form-label small fw-semibold">Plafond / Montant autorisé validé <span class="text-danger">*</span></label>
          <input type="number" step="0.01" min="0" name="authorized_amount" class="form-control fw-bold" value="${suggestedAuth}" required>
        </div>
        <div class="col-md-6"><label class="form-label small fw-semibold">Observation de contrôle</label>
          <input name="notes" class="form-control" placeholder="Visa conforme des pièces et montants">
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn-gp-primary bg-success border-success"><i class="bi bi-check-lg"></i> Valider la dépense</button>
        </div>
      </form>`;
    document.getElementById('dynFormValidateExpense').onsubmit = async (e) => {
      e.preventDefault();
      const payload = Object.fromEntries(new FormData(e.currentTarget).entries());
      const res = await fetch(`/api/expenses/${exp.id}/validate`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      const data = await res.json();
      showToast(data.message || 'Dépense validée.', res.ok ? 'success' : 'danger');
      if (res.ok) { bootstrap.Modal.getInstance(modalEl)?.hide(); await BackendApi.loadOperations(); }
    };
  } else {
    if (titleEl) titleEl.textContent = `Pièces justificatives & Historique financier — ${exp.reference}`;
    const attachments = exp.attachments || [];
    const audits = exp.audits || [];
    bodyEl.innerHTML = `
      <form id="dynFormExpenseAttach" class="row g-2 p-3 bg-light border rounded mb-3" enctype="multipart/form-data">
        <div class="col-md-3"><label class="form-label small fw-semibold">Nature</label>
          <select name="document_type" class="form-select form-select-sm"><option value="Facture">Facture</option><option value="Reçu">Reçu</option><option value="Bon de livraison">Bon de livraison</option><option value="Devis">Devis</option></select>
        </div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Fichier justificatif <span class="text-danger">*</span></label>
          <input type="file" name="file" class="form-control form-control-sm" required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
        </div>
        <div class="col-md-3"><label class="form-label small fw-semibold">Montant justifié cumulé</label>
          <input type="number" step="0.01" min="0" name="justified_amount" class="form-control form-control-sm" value="${exp.justified_amount || exp.disbursed_amount || 0}">
        </div>
        <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn-gp-primary btn-sm w-100 justify-content-center">Joindre</button></div>
      </form>

      <h6 class="fw-bold mb-2">Pièces justificatives rattachées (${attachments.length})</h6>
      <div class="table-responsive mb-3">
        <table class="table table-sm small align-middle">
          <thead class="table-light"><tr><th>Type</th><th>Document</th><th>Taille</th><th class="text-end">Télécharger</th></tr></thead>
          <tbody>
            ${attachments.length ? attachments.map(a => `<tr>
              <td><span class="badge bg-light text-dark border">${escHtml(a.document_type)}</span></td>
              <td>${escHtml(a.title || a.original_name)}</td>
              <td>${escHtml(a.file_size || '')}</td>
              <td class="text-end"><button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="downloadAuthenticatedFile('/api/expense-attachments/${a.id}/download', '${escHtml(a.original_name)}')"><i class="bi bi-download"></i></button></td>
            </tr>`).join('') : '<tr><td colspan="4" class="text-center text-muted">Aucun justificatif joint.</td></tr>'}
          </tbody>
        </table>
      </div>

      <h6 class="fw-bold mb-2">Historique financier avant / après (${audits.length})</h6>
      <div class="table-responsive">
        <table class="table table-sm small">
          <thead class="table-light"><tr><th>Date</th><th>Auteur</th><th>Action</th><th>Motif & Trace</th></tr></thead>
          <tbody>
            ${audits.length ? audits.map(au => `<tr>
              <td class="text-nowrap">${formatDateTime(au.created_at)}</td>
              <td>${escHtml(au.user?.name || 'Système')}</td>
              <td><span class="badge bg-secondary-subtle text-secondary">${escHtml(au.action)}</span></td>
              <td>${escHtml(au.reason || '—')}</td>
            </tr>`).join('') : '<tr><td colspan="4" class="text-center text-muted">Aucune trace.</td></tr>'}
          </tbody>
        </table>
      </div>`;
    document.getElementById('dynFormExpenseAttach').onsubmit = async (e) => {
      e.preventDefault();
      const fd = new FormData(e.currentTarget);
      const res = await fetch(`/api/expenses/${exp.id}/attachments`, { method: 'POST', body: fd });
      const data = await res.json();
      showToast(data.message || 'Justificatif ajouté.', res.ok ? 'success' : 'danger');
      if (res.ok) { bootstrap.Modal.getInstance(modalEl)?.hide(); await BackendApi.loadOperations(); }
    };
  }

  bootstrap.Modal.getOrCreateInstance(modalEl).show();
};

/* ==========================================================================
   7. Module 7 : Aperçu Officiel & Impression A4 des Rapports
   ========================================================================== */
function renderPrintableReportHtml(reportMeta, snapshot) {
  const orgName = AppState.catalogRefs?.settings?.company_name || 'GESTION+ — Direction Générale & Administration';
  const statusRows = Object.entries(snapshot.requests_by_status || {})
    .map(([st, cnt]) => `<tr><td>${escHtml(st)}</td><td class="text-end fw-bold">${cnt}</td></tr>`)
    .join('') || '<tr><td colspan="2" class="text-muted">Aucune demande sur la période</td></tr>';

  return `
    <div class="pdf-header-banner">
      <div>
        <div class="pdf-brand-title"><i class="bi bi-layers-half"></i> ${escHtml(orgName)}</div>
        <div class="text-muted small mt-1">${escHtml(reportMeta.title || 'Rapport consolidé d’activité et de gestion')}</div>
      </div>
      <div class="text-end">
        <span class="badge bg-primary px-3 py-1 mb-1">${escHtml(reportMeta.reference || 'RAPPORT')}</span>
        <div class="small text-muted">Période : ${escHtml(reportMeta.period_from)} au ${escHtml(reportMeta.period_to)}</div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-4"><div class="p-3 border rounded"><small class="text-muted d-block">Demandes période</small><strong class="fs-5">${snapshot.totals?.requests ?? 0}</strong><div class="small text-muted">Délai moyen : ${snapshot.average_processing_hours ?? 0} h</div></div></div>
      <div class="col-4"><div class="p-3 border rounded"><small class="text-muted d-block">Dépenses décaissées</small><strong class="fs-6">${formatCurrency(snapshot.expenses?.disbursed)}</strong><div class="small text-danger">À justifier : ${formatCurrency(snapshot.expenses?.unjustified)}</div></div></div>
      <div class="col-4"><div class="p-3 border rounded"><small class="text-muted d-block">Valorisation du stock</small><strong class="fs-6">${formatCurrency(snapshot.stock?.value)}</strong><div class="small text-muted">${snapshot.stock?.low ?? 0} alerte(s) • ${snapshot.assets?.total ?? 0} bien(s)</div></div></div>
    </div>

    <div class="row g-4 mb-4">
      <div class="col-6">
        <h6 class="fw-bold border-bottom pb-1">Répartition des demandes par statut</h6>
        <table class="table table-sm small mb-0"><tbody>${statusRows}</tbody></table>
      </div>
      <div class="col-6">
        <h6 class="fw-bold border-bottom pb-1">Patrimoine & Parc automobile</h6>
        <table class="table table-sm small mb-0">
          <tbody>
            <tr><td>Biens durables (Total / Affectés / Endommagés)</td><td class="text-end fw-bold">${snapshot.assets?.total ?? 0} / ${snapshot.assets?.assigned ?? 0} / ${snapshot.assets?.damaged ?? 0}</td></tr>
            <tr><td>Parc auto (Total / En service / Maintenance)</td><td class="text-end fw-bold">${snapshot.vehicles?.total ?? 0} / ${snapshot.vehicles?.in_service ?? 0} / ${snapshot.vehicles?.maintenance ?? 0}</td></tr>
            <tr><td>Alertes d’échéances / entretiens véhicules</td><td class="text-end fw-bold text-danger">${snapshot.vehicles?.alerts ?? 0}</td></tr>
            <tr><td>Mouvements de stock enregistrés</td><td class="text-end fw-bold">${snapshot.stock?.movements ?? 0}</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    ${(reportMeta.difficulties || reportMeta.action_items) ? `
      <div class="row g-3 mb-4">
        <div class="col-6"><div class="p-3 bg-light border rounded h-100"><strong class="small d-block mb-1">Difficultés rencontrées :</strong><div class="small">${escHtml(reportMeta.difficulties || 'RAS')}</div></div></div>
        <div class="col-6"><div class="p-3 bg-light border rounded h-100"><strong class="small d-block mb-1">Actions à mener :</strong><div class="small">${escHtml(reportMeta.action_items || 'RAS')}</div></div></div>
      </div>` : ''}

    <div class="small text-muted border-top pt-2 d-flex justify-content-between">
      <span>Document généré par GESTION+ le ${formatDateTime(reportMeta.created_at || new Date())}</span>
      <span>Visa Responsable Administratif / Direction Générale</span>
    </div>
  `;
}

function openReportPreviewModal(customReport = null) {
  const container = document.getElementById('printableReportDocument');
  if (!container) return;

  if (customReport && customReport.snapshot) {
    container.innerHTML = renderPrintableReportHtml(customReport, customReport.snapshot);
    container.dataset.reference = customReport.reference || 'Rapport';
  } else if (AppState.lastReportPayload) {
    const p = AppState.lastReportPayload;
    container.innerHTML = renderPrintableReportHtml({
      reference: 'SYNTHESE-EN-DIRECT',
      title: 'État synthétique des activités, stocks, dépenses et équipements',
      period_from: p.period?.from || '-',
      period_to: p.period?.to || '-'
    }, p.data || {});
    container.dataset.reference = 'Rapport_Gestion';
  } else {
    showToast('Chargez d’abord l’onglet Rapports.', 'info');
    return;
  }

  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalReportPreview')).show();
}

window.openSavedReportPreview = async function (reportId) {
  const local = (AppState.currentSavedReports || []).find(r => Number(r.id) === Number(reportId));
  if (local && local.snapshot) {
    openReportPreviewModal(local);
    return;
  }
  const res = await fetch(`/api/reports/saved/${reportId}`);
  const json = await res.json();
  if (res.ok && json.success) openReportPreviewModal(json.data);
};

window.exportTableAsPdf = async function(moduleType) {
  const container = document.getElementById('printableReportDocument');
  if (!container) return;

  const orgName = AppState.catalogRefs?.settings?.company_name || 'GESTION+ — Direction Générale & Administration';
  const nowStr = formatDateTime(new Date());
  let title = '';
  let refCode = '';
  let tableHtml = '';
  let summaryHtml = '';

  if (moduleType === 'articles') {
    if (!AppState.currentArticles || AppState.currentArticles.length === 0) {
      if (typeof BackendApi !== 'undefined' && BackendApi.loadArticles) await BackendApi.loadArticles();
    }
    const list = AppState.currentArticles || [];
    title = 'État Général du Stock et des Disponibilités';
    refCode = `STOCK-${new Date().toISOString().slice(0, 10)}`;
    const totalQty = list.reduce((sum, a) => sum + Number(a.stock || 0), 0);
    const totalVal = list.reduce((sum, a) => sum + (Number(a.stock || 0) * Number(a.unit_price || 0)), 0);
    const lowCount = list.filter(a => Number(a.stock || 0) <= Number(a.min_stock || 0)).length;

    summaryHtml = `
      <div class="row g-3 mb-4">
        <div class="col-4"><div class="p-3 border rounded"><small class="text-muted d-block">Total Articles</small><strong class="fs-5">${list.length} références</strong></div></div>
        <div class="col-4"><div class="p-3 border rounded"><small class="text-muted d-block">Quantité en Stock</small><strong class="fs-5">${totalQty} unités</strong><div class="small text-danger">${lowCount} alerte(s) stock faible</div></div></div>
        <div class="col-4"><div class="p-3 border rounded"><small class="text-muted d-block">Valorisation Globale</small><strong class="fs-5">${formatCurrency(totalVal)}</strong></div></div>
      </div>
    `;

    tableHtml = `
      <table class="table table-bordered table-sm small">
        <thead class="table-light">
          <tr>
            <th>Code</th>
            <th>Désignation</th>
            <th>Catégorie</th>
            <th>Emplacement</th>
            <th class="text-center">Stock</th>
            <th class="text-center">Seuil Min</th>
            <th class="text-end">Prix Unitaire</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          ${list.map(a => {
            const isLow = Number(a.stock || 0) <= Number(a.min_stock || 0);
            const isOut = Number(a.stock || 0) === 0;
            const statusLabel = isOut ? 'Rupture' : (isLow ? 'Stock faible' : 'En stock');
            return `<tr>
              <td class="fw-bold">${escHtml(a.code || '-')}</td>
              <td>${escHtml(a.name || '-')}</td>
              <td>${escHtml(a.category || '-')}</td>
              <td>${escHtml(a.location || '-')}</td>
              <td class="text-center fw-bold">${a.stock ?? 0}</td>
              <td class="text-center text-muted">${a.min_stock ?? 0}</td>
              <td class="text-end">${formatCurrency(a.unit_price ?? 0)}</td>
              <td><span class="badge ${isOut ? 'bg-danger' : (isLow ? 'bg-warning text-dark' : 'bg-success')}">${statusLabel}</span></td>
            </tr>`;
          }).join('') || '<tr><td colspan="8" class="text-center text-muted">Aucun article enregistré.</td></tr>'}
        </tbody>
      </table>
    `;
  } else if (moduleType === 'requests') {
    if (!AppState.currentRequests || AppState.currentRequests.length === 0) {
      if (typeof BackendApi !== 'undefined' && BackendApi.loadRequests) await BackendApi.loadRequests();
    }
    const list = AppState.currentRequests || [];
    title = 'Registre des Demandes Internes de Matériel';
    refCode = `DEMANDES-${new Date().toISOString().slice(0, 10)}`;
    const pending = list.filter(r => PENDING_REQUEST_STATUSES.includes(r.status)).length;
    const validated = list.filter(r => r.status === 'Validée').length;

    summaryHtml = `
      <div class="row g-3 mb-4">
        <div class="col-4"><div class="p-3 border rounded"><small class="text-muted d-block">Total Demandes</small><strong class="fs-5">${list.length}</strong></div></div>
        <div class="col-4"><div class="p-3 border rounded"><small class="text-muted d-block">En Cours / Traitement</small><strong class="fs-5 text-warning">${pending}</strong></div></div>
        <div class="col-4"><div class="p-3 border rounded"><small class="text-muted d-block">Validées</small><strong class="fs-5 text-success">${validated}</strong></div></div>
      </div>
    `;

    tableHtml = `
      <table class="table table-bordered table-sm small">
        <thead class="table-light">
          <tr>
            <th>Référence</th>
            <th>Demandeur</th>
            <th>Service</th>
            <th>Catégorie</th>
            <th>Urgence</th>
            <th>Statut</th>
            <th>Date Demande</th>
          </tr>
        </thead>
        <tbody>
          ${list.map(r => `<tr>
            <td class="fw-bold">${escHtml(r.reference || '-')}</td>
            <td>${escHtml(r.user?.name || r.demandeur_name || '-')}</td>
            <td>${escHtml(r.department || r.service || '-')}</td>
            <td>${escHtml(r.category || '-')}</td>
            <td><span class="badge ${r.urgency === 'Urgent' ? 'bg-danger' : 'bg-secondary'}">${escHtml(r.urgency || 'Normal')}</span></td>
            <td><span class="badge bg-primary">${escHtml(r.status || 'Soumise')}</span></td>
            <td>${formatDateShort(r.created_at || new Date())}</td>
          </tr>`).join('') || '<tr><td colspan="7" class="text-center text-muted">Aucune demande enregistrée.</td></tr>'}
        </tbody>
      </table>
    `;
  } else if (moduleType === 'movements') {
    if (!AppState.currentMovements || AppState.currentMovements.length === 0) {
      if (typeof BackendApi !== 'undefined' && BackendApi.loadMovements) await BackendApi.loadMovements();
    }
    const list = AppState.currentMovements || [];
    title = 'Historique des Mouvements de Stock';
    refCode = `MVT-${new Date().toISOString().slice(0, 10)}`;

    summaryHtml = `
      <div class="row g-3 mb-4">
        <div class="col-6"><div class="p-3 border rounded"><small class="text-muted d-block">Total Mouvements</small><strong class="fs-5">${list.length} opérations</strong></div></div>
        <div class="col-6"><div class="p-3 border rounded"><small class="text-muted d-block">Contrôle & Traçabilité</small><strong class="fs-5 text-success">100% validé & audité</strong></div></div>
      </div>
    `;

    tableHtml = `
      <table class="table table-bordered table-sm small">
        <thead class="table-light">
          <tr>
            <th>Date & Heure</th>
            <th>Type</th>
            <th>Article</th>
            <th class="text-center">Quantité</th>
            <th>Réf. Demande</th>
            <th>Motif</th>
            <th>Opérateur</th>
          </tr>
        </thead>
        <tbody>
          ${list.map(m => `<tr>
            <td>${formatDateTime(m.created_at || new Date())}</td>
            <td><span class="badge ${m.type === 'in' || m.type === 'Entrée' ? 'bg-success' : 'bg-danger'}">${escHtml(m.type || '-')}</span></td>
            <td class="fw-bold">${escHtml(m.article?.name || m.article_name || '-')}</td>
            <td class="text-center fw-bold">${m.quantity ?? 0}</td>
            <td>${escHtml(m.request_reference || m.material_request?.reference || '-')}</td>
            <td>${escHtml(m.reason || '-')}</td>
            <td>${escHtml(m.user?.name || '-')}</td>
          </tr>`).join('') || '<tr><td colspan="7" class="text-center text-muted">Aucun mouvement enregistré.</td></tr>'}
        </tbody>
      </table>
    `;
  } else if (moduleType === 'assets') {
    if (!AppState.currentAssets || AppState.currentAssets.length === 0) {
      if (typeof BackendApi !== 'undefined' && BackendApi.loadOperations) await BackendApi.loadOperations();
    }
    const list = AppState.currentAssets || [];
    title = 'État Général du Patrimoine & Équipements Durables';
    refCode = `PATRIMOINE-${new Date().toISOString().slice(0, 10)}`;
    const assigned = list.filter(a => a.availability === 'Affecté' || a.assigned_to).length;
    const totalVal = list.reduce((sum, a) => sum + Number(a.acquisition_cost || 0), 0);

    summaryHtml = `
      <div class="row g-3 mb-4">
        <div class="col-4"><div class="p-3 border rounded"><small class="text-muted d-block">Total Équipements</small><strong class="fs-5">${list.length} biens</strong></div></div>
        <div class="col-4"><div class="p-3 border rounded"><small class="text-muted d-block">Affectés en Service</small><strong class="fs-5 text-primary">${assigned}</strong></div></div>
        <div class="col-4"><div class="p-3 border rounded"><small class="text-muted d-block">Valeur d'Acquisition</small><strong class="fs-5">${formatCurrency(totalVal)}</strong></div></div>
      </div>
    `;

    tableHtml = `
      <table class="table table-bordered table-sm small">
        <thead class="table-light">
          <tr>
            <th>N° Inventaire</th>
            <th>Désignation</th>
            <th>Catégorie</th>
            <th>N° Série</th>
            <th>État</th>
            <th>Disponibilité</th>
            <th>Détenteur / Service</th>
            <th class="text-end">Coût Acquisition</th>
          </tr>
        </thead>
        <tbody>
          ${list.map(a => `<tr>
            <td class="fw-bold">${escHtml(a.inventory_number || '-')}</td>
            <td>${escHtml(a.name || '-')}</td>
            <td>${escHtml(a.category || '-')}</td>
            <td>${escHtml(a.serial_number || '-')}</td>
            <td><span class="badge ${a.condition === 'Neuf' ? 'bg-success' : (a.condition === 'Endommagé' || a.condition === 'Hors service' ? 'bg-danger' : 'bg-info text-dark')}">${escHtml(a.condition || 'Bon état')}</span></td>
            <td><span class="badge ${a.availability === 'Disponible' ? 'bg-success' : 'bg-primary'}">${escHtml(a.availability || '-')}</span></td>
            <td>${escHtml(a.assigned_user?.name || a.department || '-')}</td>
            <td class="text-end">${formatCurrency(a.acquisition_cost ?? 0)}</td>
          </tr>`).join('') || '<tr><td colspan="8" class="text-center text-muted">Aucun bien durable enregistré.</td></tr>'}
        </tbody>
      </table>
    `;
  } else if (moduleType === 'vehicles') {
    if (!AppState.currentVehicles || AppState.currentVehicles.length === 0) {
      if (typeof BackendApi !== 'undefined' && BackendApi.loadOperations) await BackendApi.loadOperations();
    }
    const list = AppState.currentVehicles || [];
    title = 'État du Parc Automobile & Suivi des Échéances';
    refCode = `PARCAUTO-${new Date().toISOString().slice(0, 10)}`;
    const inService = list.filter(v => v.status === 'En service' || v.status === 'Disponible').length;

    summaryHtml = `
      <div class="row g-3 mb-4">
        <div class="col-6"><div class="p-3 border rounded"><small class="text-muted d-block">Total Véhicules</small><strong class="fs-5">${list.length}</strong></div></div>
        <div class="col-6"><div class="p-3 border rounded"><small class="text-muted d-block">Opérationnels</small><strong class="fs-5 text-success">${inService}</strong></div></div>
      </div>
    `;

    tableHtml = `
      <table class="table table-bordered table-sm small">
        <thead class="table-light">
          <tr>
            <th>Immatriculation</th>
            <th>Marque & Modèle</th>
            <th>Type</th>
            <th class="text-center">Kilométrage</th>
            <th>État</th>
            <th>Conducteur / Service</th>
            <th>Échéance Assurance</th>
            <th>Visite Tech.</th>
          </tr>
        </thead>
        <tbody>
          ${list.map(v => `<tr>
            <td class="fw-bold">${escHtml(v.registration_number || '-')}</td>
            <td>${escHtml(v.brand || '')} ${escHtml(v.model || '')}</td>
            <td>${escHtml(v.vehicle_type || '-')}</td>
            <td class="text-center fw-bold">${v.mileage ? Number(v.mileage).toLocaleString('fr-FR') + ' km' : '-'}</td>
            <td><span class="badge ${v.status === 'En service' || v.status === 'Disponible' ? 'bg-success' : 'bg-warning text-dark'}">${escHtml(v.status || '-')}</span></td>
            <td>${escHtml(v.driver?.name || v.department || '-')}</td>
            <td>${formatDateShort(v.insurance_expires_at)}</td>
            <td>${formatDateShort(v.inspection_expires_at)}</td>
          </tr>`).join('') || '<tr><td colspan="8" class="text-center text-muted">Aucun véhicule enregistré.</td></tr>'}
        </tbody>
      </table>
    `;
  } else if (moduleType === 'expenses') {
    if (!AppState.currentExpenses || AppState.currentExpenses.length === 0) {
      if (typeof BackendApi !== 'undefined' && BackendApi.loadOperations) await BackendApi.loadOperations();
    }
    const list = AppState.currentExpenses || [];
    title = 'État des Dépenses, Décaissements & Justificatifs';
    refCode = `DEPENSES-${new Date().toISOString().slice(0, 10)}`;
    const disbursed = list.reduce((sum, e) => sum + Number(e.disbursed_amount || 0), 0);
    const justified = list.reduce((sum, e) => sum + Number(e.justified_amount || 0), 0);
    const unjustified = Math.max(0, disbursed - justified);

    summaryHtml = `
      <div class="row g-3 mb-4">
        <div class="col-4"><div class="p-3 border rounded"><small class="text-muted d-block">Total Décaissé</small><strong class="fs-5">${formatCurrency(disbursed)}</strong></div></div>
        <div class="col-4"><div class="p-3 border rounded"><small class="text-muted d-block">Total Justifié</small><strong class="fs-5 text-success">${formatCurrency(justified)}</strong></div></div>
        <div class="col-4"><div class="p-3 border rounded"><small class="text-muted d-block">Reste à Justifier</small><strong class="fs-5 text-danger">${formatCurrency(unjustified)}</strong></div></div>
      </div>
    `;

    tableHtml = `
      <table class="table table-bordered table-sm small">
        <thead class="table-light">
          <tr>
            <th>Réf. Dépense</th>
            <th>Demande Rattachée</th>
            <th>Bénéficiaire / Fournisseur</th>
            <th>Mode Paiement</th>
            <th class="text-end">Autorisé</th>
            <th class="text-end">Décaissé</th>
            <th class="text-end">Justifié</th>
            <th class="text-end">Solde</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          ${list.map(e => `<tr>
            <td class="fw-bold">${escHtml(e.expense_reference || '-')}</td>
            <td>${escHtml(e.material_request?.reference || 'Direct')}</td>
            <td>${escHtml(e.beneficiary || e.supplier?.name || '-')}</td>
            <td>${escHtml(e.payment_method || '-')}</td>
            <td class="text-end">${formatCurrency(e.authorized_amount ?? 0)}</td>
            <td class="text-end fw-bold">${formatCurrency(e.disbursed_amount ?? 0)}</td>
            <td class="text-end text-success">${formatCurrency(e.justified_amount ?? 0)}</td>
            <td class="text-end text-danger">${formatCurrency(e.balance ?? 0)}</td>
            <td>${e.is_overrun ? '<span class="badge bg-danger">Dépassement</span>' : '<span class="badge bg-success">Conforme</span>'}</td>
          </tr>`).join('') || '<tr><td colspan="9" class="text-center text-muted">Aucune dépense enregistrée.</td></tr>'}
        </tbody>
      </table>
    `;
  } else if (moduleType === 'replenishment') {
    if (!AppState.currentArticles || AppState.currentArticles.length === 0) {
      if (typeof BackendApi !== 'undefined' && BackendApi.loadArticles) await BackendApi.loadArticles();
    }
    const list = (AppState.currentArticles || []).filter(a => Number(a.stock || 0) <= Number(a.min_stock || 0));
    title = 'État de Réapprovisionnement des Stocks (Besoins d’Achat)';
    refCode = `REAPPRO-${new Date().toISOString().slice(0, 10)}`;
    const totalEstCost = list.reduce((sum, a) => {
      const needed = Math.max(1, (a.max_stock || a.min_stock * 2) - a.stock);
      return sum + (needed * Number(a.unit_price || 0));
    }, 0);

    summaryHtml = `
      <div class="row g-3 mb-4">
        <div class="col-6"><div class="p-3 border rounded"><small class="text-muted d-block">Articles sous Seuil Minimum</small><strong class="fs-5 text-danger">${list.length} référence(s)</strong></div></div>
        <div class="col-6"><div class="p-3 border rounded"><small class="text-muted d-block">Budget Estimatif Global</small><strong class="fs-5">${formatCurrency(totalEstCost)}</strong></div></div>
      </div>
    `;

    tableHtml = `
      <table class="table table-bordered table-sm small">
        <thead class="table-light">
          <tr>
            <th>Code</th>
            <th>Désignation</th>
            <th>Fournisseur</th>
            <th class="text-center">En Stock</th>
            <th class="text-center">Seuil Min / Max</th>
            <th class="text-center">Qté Suggérée</th>
            <th class="text-end">Prix Unitaire</th>
            <th class="text-end">Coût Estimé</th>
          </tr>
        </thead>
        <tbody>
          ${list.map(a => {
            const needed = Math.max(1, (a.max_stock || a.min_stock * 2) - a.stock);
            const cost = needed * Number(a.unit_price || 0);
            return `<tr>
              <td class="fw-bold">${escHtml(a.code || '-')}</td>
              <td>${escHtml(a.name || '-')}</td>
              <td>${escHtml(a.supplier?.name || a.supplier_name || '-')}</td>
              <td class="text-center fw-bold text-danger">${a.stock ?? 0}</td>
              <td class="text-center text-muted">${a.min_stock ?? 0} / ${a.max_stock ?? '-'}</td>
              <td class="text-center fw-bold text-primary">${needed}</td>
              <td class="text-end">${formatCurrency(a.unit_price ?? 0)}</td>
              <td class="text-end fw-bold">${formatCurrency(cost)}</td>
            </tr>`;
          }).join('') || '<tr><td colspan="8" class="text-center text-muted">Tous les stocks sont au-dessus des seuils de réapprovisionnement.</td></tr>'}
        </tbody>
      </table>
    `;
  }

  container.innerHTML = `
    <div class="pdf-header-banner">
      <div>
        <div class="pdf-brand-title"><i class="bi bi-layers-half"></i> ${escHtml(orgName)}</div>
        <div class="text-muted small mt-1">${escHtml(title)}</div>
      </div>
      <div class="text-end">
        <span class="badge bg-primary px-3 py-1 mb-1">${escHtml(refCode)}</span>
        <div class="small text-muted">Édité le : ${escHtml(nowStr)}</div>
      </div>
    </div>
    ${summaryHtml}
    <div class="table-responsive mb-4">
      ${tableHtml}
    </div>
    <div class="small text-muted border-top pt-2 d-flex justify-content-between">
      <span>Document officiel extrait de GESTION+ le ${escHtml(nowStr)}</span>
      <span>Visa Responsable Administratif / Direction Générale</span>
    </div>
  `;
  container.dataset.reference = refCode;

  const modalEl = document.getElementById('modalReportPreview');
  if (modalEl) {
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }
};

/* ==========================================================================
   8. Module 1, 8, 9 & 10 : Utilisateurs, Documents, Audit, Sauvegardes
   ========================================================================== */
function renderUserPermissionsGrid(selectedPermissions = null, lockPermissions = false) {
  const grid = document.getElementById('usrPermissionsGrid');
  if (!grid) return;
  const roleEl = document.getElementById('usrRole');
  const roleVal = roleEl?.value || 'Collaborateur';
  const isLocked = lockPermissions || Boolean(roleEl?.disabled);
  const activePerms = Array.isArray(selectedPermissions)
    ? selectedPermissions
    : (DEFAULT_ROLE_PERMISSIONS[roleVal] || DEFAULT_ROLE_PERMISSIONS.Collaborateur);

  grid.innerHTML = PERMISSION_CATALOG.map(perm => `
    <div class="col-6 col-md-4">
      <div class="form-check small">
        <input class="form-check-input js-usr-perm-check" type="checkbox" value="${perm.key}" id="perm_${perm.key.replace('.', '_')}" ${activePerms.includes(perm.key) ? 'checked' : ''} ${isLocked ? 'disabled' : ''}>
        <label class="form-check-label" for="perm_${perm.key.replace('.', '_')}">${escHtml(perm.label)}</label>
      </div>
    </div>
  `).join('');
}

function resetUserModalMode() {
  const form = document.getElementById('formNewUser');
  if (!form) return;
  form.reset();
  delete form.dataset.editUserId;

  const title = document.getElementById('userModalTitle');
  const pwdInput = document.getElementById('usrPassword');
  const pwdLabel = document.getElementById('usrPasswordLabel');
  const pwdHint = document.getElementById('usrPasswordHint');
  const matWrapper = document.getElementById('usrMatriculeWrapper');
  const matDisplay = document.getElementById('usrMatriculeDisplay');
  const submitBtn = document.getElementById('usrSubmitBtn');
  const roleSelect = document.getElementById('usrRole');

  if (title) title.textContent = 'Ajouter un compte utilisateur';
  if (pwdInput) { pwdInput.required = true; pwdInput.value = ''; }
  if (pwdLabel) pwdLabel.innerHTML = 'Mot de passe initial <span class="text-danger">*</span>';
  if (pwdHint) pwdHint.textContent = 'Minimum 8 caractères.';
  if (matWrapper) matWrapper.style.display = 'none';
  if (matDisplay) matDisplay.textContent = '-';
  if (submitBtn) submitBtn.textContent = 'Créer le compte';
  if (roleSelect) roleSelect.disabled = false;

  renderUserPermissionsGrid();
}

window.openEditUserModal = function (userId) {
  const u = (AppState.currentUsers || []).find(item => Number(item.id) === Number(userId));
  if (!u) return;

  const form = document.getElementById('formNewUser');
  if (!form) return;
  form.dataset.editUserId = u.id;

  document.getElementById('userModalTitle').textContent = `Modifier l’utilisateur — ${u.name}`;
  document.getElementById('usrName').value = u.name || '';
  document.getElementById('usrEmail').value = u.email || '';
  document.getElementById('usrPhone').value = u.phone || '';
  document.getElementById('usrOffice').value = u.office_location || '';
  const matWrapper = document.getElementById('usrMatriculeWrapper');
  if (matWrapper) matWrapper.style.display = '';
  document.getElementById('usrMatriculeDisplay').textContent = u.matricule || '-';

  const roleMap = { admin: 'Administrateur', manager: 'Responsable', collaborator: 'Collaborateur' };
  const roleSelect = document.getElementById('usrRole');
  if (roleSelect) {
    roleSelect.value = roleMap[u.role] || 'Collaborateur';
    roleSelect.disabled = u.role === 'admin';
  }
  const srvSel = document.getElementById('usrService');
  if (srvSel && u.service) {
    if (![...srvSel.options].some(o => o.value === u.service)) {
      srvSel.insertAdjacentHTML('beforeend', `<option value="${escHtml(u.service)}">${escHtml(u.service)}</option>`);
    }
    srvSel.value = u.service;
  }

  const pwdInput = document.getElementById('usrPassword');
  const pwdLabel = document.getElementById('usrPasswordLabel');
  const pwdHint = document.getElementById('usrPasswordHint');
  if (pwdInput) { pwdInput.required = false; pwdInput.value = ''; }
  if (pwdLabel) pwdLabel.textContent = 'Nouveau mot de passe (optionnel)';
  if (pwdHint) pwdHint.textContent = 'Laissez vide pour conserver le mot de passe actuel.';

  document.getElementById('usrSubmitBtn').textContent = 'Enregistrer les modifications';
  renderUserPermissionsGrid(u.effective_permissions || u.permissions || null, u.role === 'admin');

  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNewUser')).show();
};

window.openResetUserPasswordModal = function (userId, userName) {
  document.getElementById('resetTargetUserId').value = userId;
  document.getElementById('resetTargetUserName').textContent = userName;
  document.getElementById('resetTargetUserPassword').value = '';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalResetUserPassword')).show();
};

window.openValidateUserRoleModal = function (userId) {
  const user = (AppState.currentUsers || []).find(item => Number(item.id) === Number(userId));
  if (!user) return;
  if (user.role === 'admin') {
    showToast('Le rôle de l’administrateur ne peut pas être modifié.', 'warning');
    return;
  }

  const roleMap = { admin: 'Administrateur', manager: 'Responsable', collaborator: 'Collaborateur' };
  document.getElementById('validateRoleUserId').value = user.id;
  document.getElementById('validateRoleUserName').textContent = `${user.name} • ${user.service || 'Sans service'}`;
  document.getElementById('validateUserRoleSelect').value = roleMap[user.role] || 'Collaborateur';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalValidateUserRole')).show();
};

async function openAllLoginHistoryModal() {
  try {
    const res = await fetch('/api/login-history');
    const json = await res.json();
    const tbody = document.getElementById('allLoginHistoryTableBody');
    if (tbody) {
      const logs = json.data || [];
      tbody.innerHTML = logs.length
        ? logs.map(l => `<tr>
            <td class="text-nowrap small">${formatDateTime(l.created_at)}</td>
            <td><strong>${escHtml(l.user?.name || 'Inconnu')}</strong><small class="d-block text-muted">${escHtml(l.user?.email || '')}</small></td>
            <td><span class="badge ${l.action === 'auth.login' ? 'bg-success-subtle text-success' : (l.action === 'auth.logout' ? 'bg-secondary-subtle text-secondary' : 'bg-danger-subtle text-danger')}">${escHtml(l.action)}</span></td>
            <td class="font-monospace small">${escHtml(l.ip_address || '127.0.0.1')}</td>
            <td class="small text-muted">${escHtml(l.description || '')}</td>
          </tr>`).join('')
        : '<tr><td colspan="5" class="text-center text-muted py-3">Aucune connexion enregistrée.</td></tr>';
    }
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalLoginHistory')).show();
  } catch (e) {
    showToast('Impossible de charger l’historique des connexions.', 'danger');
  }
}

window.deleteDocument = async function (docId, docTitle) {
  if (!confirm(`Supprimer le document administratif "${docTitle}" ?`)) return;
  try {
    const res = await fetch(`/api/documents/${docId}`, { method: 'DELETE' });
    const data = await res.json();
    showToast(data.message || 'Document supprimé.', res.ok ? 'success' : 'danger');
    if (res.ok) await BackendApi.loadDocuments();
  } catch (e) {
    showToast('Impossible de supprimer ce document.', 'danger');
  }
};

function setupModalsAndActions() {
  document.getElementById('btnMarkAllNotificationsRead')?.addEventListener('click', async (e) => {
    e.stopPropagation();
    await fetch('/api/notifications/read-all', { method: 'PUT' });
    await BackendApi.loadNotifications();
    showToast('Notifications marquées comme lues.', 'info');
  });

  document.getElementById('btnOpenReplenishmentModal')?.addEventListener('click', openReplenishmentModal);
  document.getElementById('btnOpenLoginHistoryModal')?.addEventListener('click', openAllLoginHistoryModal);
  document.getElementById('btnOpenCreateUserModal')?.addEventListener('click', resetUserModalMode);
  document.getElementById('usrRole')?.addEventListener('change', () => renderUserPermissionsGrid());

  document.querySelectorAll('.js-open-selected-bon').forEach(button => {
    button.addEventListener('click', () => openPdfModal(AppState.selectedRequestRef));
  });

  // Actions détail de demande
  document.getElementById('btnActionVerify')?.addEventListener('click', async () => {
    if (AppState.selectedRequestRef) {
      await BackendApi.transitionRequest(AppState.selectedRequestRef, 'verify', { next_status: 'En attente de validation' });
    }
  });
  document.getElementById('btnActionApprove')?.addEventListener('click', () => {
    if (AppState.selectedRequestRef) openApproveRequestModal(AppState.selectedRequestRef);
  });
  document.getElementById('btnActionCorrection')?.addEventListener('click', () => {
    if (AppState.selectedRequestRef) openRequestReasonModal('request-correction', AppState.selectedRequestRef);
  });
  document.getElementById('btnActionReject')?.addEventListener('click', () => {
    if (AppState.selectedRequestRef) openRequestReasonModal('reject', AppState.selectedRequestRef);
  });
  document.getElementById('btnActionAssign')?.addEventListener('click', () => {
    if (!AppState.selectedRequestRef) return;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAssignRequest')).show();
  });
  document.getElementById('btnActionDeliver')?.addEventListener('click', async () => {
    if (AppState.selectedRequestRef && confirm(`Confirmer l’exécution / la remise pour ${AppState.selectedRequestRef} ?`)) {
      await BackendApi.transitionRequest(AppState.selectedRequestRef, 'deliver');
    }
  });
  document.getElementById('btnActionReceive')?.addEventListener('click', async () => {
    if (AppState.selectedRequestRef && confirm('Confirmer la réception de la demande ?')) {
      await BackendApi.transitionRequest(AppState.selectedRequestRef, 'receive');
    }
  });
  document.getElementById('btnActionReturn')?.addEventListener('click', async () => {
    if (AppState.selectedRequestRef && confirm(`Confirmer la restitution en stock du matériel pour ${AppState.selectedRequestRef} ?`)) {
      await BackendApi.returnRequest(AppState.selectedRequestRef);
    }
  });
  document.getElementById('btnActionClose')?.addEventListener('click', async () => {
    if (AppState.selectedRequestRef && confirm(`Clôturer définitivement la demande ${AppState.selectedRequestRef} ?`)) {
      await BackendApi.transitionRequest(AppState.selectedRequestRef, 'close');
    }
  });

  // Soumission modal validation demande avec quantités ajustables
  document.getElementById('formApproveRequestModal')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const ref = AppState.selectedRequestRef;
    if (!ref) return;
    const quantities = {};
    document.querySelectorAll('.js-approve-item-qty').forEach(inp => {
      quantities[inp.dataset.itemId] = parseInt(inp.value, 10) || 0;
    });
    const comment = document.getElementById('approveModalComment')?.value.trim() || null;
    const ok = await BackendApi.approveRequest(ref, { quantities, comment });
    if (ok) {
      bootstrap.Modal.getInstance(document.getElementById('modalApproveRequest'))?.hide();
      openPdfModal(ref);
    }
  });

  // Soumission modal motif obligatoire (rejet / complément / annulation)
  document.getElementById('formRequestReasonModal')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const action = document.getElementById('requestReasonActionType').value;
    const ref = document.getElementById('requestReasonTargetRef').value;
    const reason = document.getElementById('requestReasonTextarea').value.trim();
    if (!ref || !reason) return;
    const ok = await BackendApi.transitionRequest(ref, action, { reason });
    if (ok) bootstrap.Modal.getInstance(document.getElementById('modalRequestReason'))?.hide();
  });

  // Affectation agent
  document.getElementById('formAssignRequest')?.addEventListener('submit', async event => {
    event.preventDefault();
    const assignedTo = document.getElementById('assignAgentSelect').value;
    const comment = document.getElementById('assignCommentInput')?.value.trim() || null;
    if (!assignedTo || !AppState.selectedRequestRef) return;
    const success = await BackendApi.transitionRequest(AppState.selectedRequestRef, 'assign', { assigned_to: Number(assignedTo), comment });
    if (success) bootstrap.Modal.getInstance(document.getElementById('modalAssignRequest'))?.hide();
  });

  // Ajout de pièce jointe sur une demande existante
  document.getElementById('formAddRequestAttachment')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!AppState.selectedRequestRef) return;
    const fileInput = document.getElementById('reqNewAttachmentFile');
    const file = fileInput?.files?.[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    const res = await fetch(`/api/requests/${encodeURIComponent(AppState.selectedRequestRef)}/attachments`, { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.message || 'Pièce jointe ajoutée.', res.ok ? 'success' : 'danger');
    if (res.ok) {
      e.currentTarget.reset();
      await openRequestDetail(AppState.selectedRequestRef);
    }
  });

  // Export CSV demandes filtrées
  document.getElementById('btnExportRequestsCsv')?.addEventListener('click', () => {
    const params = new URLSearchParams();
    const st = document.getElementById('filterReqStatus')?.value || '';
    const cat = document.getElementById('filterReqCategory')?.value || '';
    const urg = document.getElementById('filterReqUrgency')?.value || '';
    if (st) params.set('status', st);
    if (cat) params.set('category', cat);
    if (urg) params.set('urgency', urg);
    downloadAuthenticatedFile(`/api/requests-export?${params.toString()}`, 'demandes.csv');
  });

  // Modal Nouvel Article
  const formNewArticle = document.getElementById('formNewArticle');
  if (formNewArticle) {
    document.querySelectorAll('[data-bs-target="#modalNewArticle"]').forEach(btn => btn.addEventListener('click', resetArticleFormMode));
    document.getElementById('modalNewArticle')?.addEventListener('hidden.bs.modal', resetArticleFormMode);

    formNewArticle.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!AppState.canManageOps) return;
      if (!getAuthToken()) {
        showToast('Votre session a expiré. Veuillez vous reconnecter.', 'warning');
        bootstrap.Modal.getInstance(document.getElementById('modalNewArticle'))?.hide();
        navigateToView('login', false);
        return;
      }
      const name = document.getElementById('artName').value.trim();
      const cat = document.getElementById('artCategory').value;
      const unit = document.getElementById('artUnit').value;
      const qty = parseInt(document.getElementById('artQty').value, 10);
      const threshold = Math.max(1, parseInt(document.getElementById('artThreshold').value, 10) || 1);
      const location = document.getElementById('artLocation').value.trim() || null;
      const maximumThreshold = document.getElementById('artMaximumThreshold').value || null;
      const unitCost = document.getElementById('artUnitCost').value || null;
      const supplier = document.getElementById('artSupplier').value.trim() || null;
      const articleId = formNewArticle.dataset.articleId;
      const isEditing = Boolean(articleId);

      const res = await fetch(isEditing ? `/api/articles/${articleId}` : '/api/articles', {
        method: isEditing ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, category: cat, unit, quantity: qty, threshold, maximum_threshold: maximumThreshold, unit_cost: unitCost, supplier, location })
      });
      const result = await res.json().catch(() => ({}));
      if (res.ok && result.success) {
        showToast(result.message || 'Article enregistré !', 'success');
        bootstrap.Modal.getInstance(document.getElementById('modalNewArticle'))?.hide();
        resetArticleFormMode();
        await BackendApi.refreshAll({ showIndicator: true, silent: true });
      } else if (res.status !== 401) {
        const validationMessage = Object.values(result.errors || {}).flat()[0];
        const errorMessage = validationMessage || (result.message === 'Unauthenticated.' ? 'Votre session a expiré. Veuillez vous reconnecter.' : result.message) || 'Erreur lors de l’enregistrement.';
        showToast(errorMessage, 'danger');
      }
    });
  }

  // Modal Mouvement de Stock (avec affichage conditionnel des champs Transfert)
  const mvtTypeSelect = document.getElementById('mvtType');
  const mvtTransferWrap = document.getElementById('mvtTransferFieldsWrapper');
  mvtTypeSelect?.addEventListener('change', () => {
    if (mvtTransferWrap) mvtTransferWrap.style.display = mvtTypeSelect.value === 'Transfert' ? 'block' : 'none';
  });

  document.getElementById('formNewMovement')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const articleId = parseInt(document.getElementById('mvtArticle').value, 10);
    const type = document.getElementById('mvtType').value;
    const qty = parseInt(document.getElementById('mvtQty').value, 10);
    const reason = document.getElementById('mvtReason').value.trim();
    const fromLocation = document.getElementById('mvtFromLocation')?.value.trim() || null;
    const toLocation = document.getElementById('mvtToLocation')?.value.trim() || null;
    const fromService = document.getElementById('mvtFromService')?.value.trim() || null;
    const toService = document.getElementById('mvtToService')?.value.trim() || null;
    const materialRequestId = document.getElementById('mvtRequestId')?.value || null;
    const isEmergency = Boolean(document.getElementById('mvtEmergency')?.checked);

    const res = await fetch('/api/movements', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        article_id: articleId,
        type,
        quantity: qty,
        reason,
        from_location: fromLocation,
        to_location: toLocation,
        from_service: fromService,
        to_service: toService,
        material_request_id: materialRequestId,
        is_emergency: isEmergency
      })
    });
    const result = await res.json();
    if (res.ok && result.success) {
      showToast(result.message || 'Mouvement enregistré avec succès !', 'success');
      bootstrap.Modal.getInstance(document.getElementById('modalNewMovement'))?.hide();
      e.currentTarget.reset();
      if (mvtTransferWrap) mvtTransferWrap.style.display = 'none';
      await BackendApi.refreshAll({ showIndicator: true, silent: true });
    } else {
      showToast(result.message || 'Impossible d’enregistrer ce mouvement.', 'danger');
    }
  });

  // Document administratif pleine page
  const fileUploadInput = document.getElementById('fileUploadInput');
  fileUploadInput?.addEventListener('change', () => {
    const lbl = document.getElementById('selectedDocFileName');
    if (lbl) lbl.textContent = fileUploadInput.files?.[0]?.name || '';
  });

  document.getElementById('formFullPageNewDoc')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const title = document.getElementById('fpDocTitle').value.trim();
    const cat = document.getElementById('fpDocCategory').value;
    const type = document.getElementById('fpDocType').value;
    const service = document.getElementById('fpDocService').value;
    const file = document.getElementById('fileUploadInput').files?.[0];
    if (!file) {
      showToast('Sélectionnez le fichier à archiver.', 'warning');
      return;
    }

    const formData = new FormData();
    formData.append('title', title);
    formData.append('category', cat);
    formData.append('type', type);
    formData.append('service', service);
    formData.append('file', file);

    const response = await fetch('/api/documents', { method: 'POST', body: formData });
    const result = await response.json();
    if (!response.ok || !result.success) {
      showToast(result.message || 'Le document n’a pas pu être archivé.', 'danger');
      return;
    }
    showToast(`Document "${title}" (${result.data?.reference || 'DOC'}) archivé avec succès !`, 'success');
    e.currentTarget.reset();
    await BackendApi.loadDocuments();
    navigateToView('documents');
  });

  window.downloadDocument = (documentId, fileName) => {
    downloadAuthenticatedFile(`/api/documents/${documentId}/download`, fileName || 'document');
  };

  // Création / Modification Utilisateur
  document.getElementById('formNewUser')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.currentTarget;
    const editId = form.dataset.editUserId;
    const name = document.getElementById('usrName').value.trim();
    const email = document.getElementById('usrEmail').value.trim();
    const role = document.getElementById('usrRole').value;
    const service = document.getElementById('usrService').value;
    const phone = document.getElementById('usrPhone').value.trim() || null;
    const officeLocation = document.getElementById('usrOffice').value.trim() || null;
    const password = document.getElementById('usrPassword')?.value || '';
    const permissions = [...document.querySelectorAll('.js-usr-perm-check:checked')].map(cb => cb.value);

    const payload = { name, email, role, service, phone, office_location: officeLocation, permissions };
    if (!editId || password) payload.password = password;

    const res = await fetch(editId ? `/api/users/${editId}` : '/api/users', {
      method: editId ? 'PUT' : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await res.json();
    if (res.ok && result.success) {
      showToast(result.message || 'Utilisateur enregistré !', 'success');
      bootstrap.Modal.getInstance(document.getElementById('modalNewUser'))?.hide();
      resetUserModalMode();
      await BackendApi.loadUsers();
    } else {
      const msg = Object.values(result.errors || {}).flat()[0] || result.message || 'Opération impossible.';
      showToast(msg, 'danger');
    }
  });

  document.getElementById('formValidateUserRole')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const userId = document.getElementById('validateRoleUserId').value;
    const role = document.getElementById('validateUserRoleSelect').value;
    const res = await fetch(`/api/users/${userId}/role`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ role })
    });
    const result = await res.json();
    const message = Object.values(result.errors || {}).flat()[0] || result.message || 'Validation du profil impossible.';
    showToast(message, res.ok ? 'success' : 'danger');
    if (!res.ok) return;

    bootstrap.Modal.getInstance(document.getElementById('modalValidateUserRole'))?.hide();
    await BackendApi.loadUsers();
  });

  // Réinitialisation mot de passe utilisateur par l'admin
  document.getElementById('formResetUserPassword')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const userId = document.getElementById('resetTargetUserId').value;
    const password = document.getElementById('resetTargetUserPassword').value;
    const res = await fetch(`/api/users/${userId}/reset-password`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ password })
    });
    const data = await res.json();
    showToast(data.message || 'Mot de passe réinitialisé.', res.ok ? 'success' : 'danger');
    if (res.ok) bootstrap.Modal.getInstance(document.getElementById('modalResetUserPassword'))?.hide();
  });

  // Impression A4 Bon Officiel
  document.getElementById('btnPrintPdfDoc')?.addEventListener('click', () => {
    const printableDocument = document.getElementById('printablePdfDocument');
    const reference = printableDocument?.dataset.reference;
    if (!printableDocument || !reference) return;
    const previousTitle = document.title;
    document.title = `Bon_${reference}`;
    document.body.classList.add('printing-official-bon');
    const cleanup = () => {
      document.title = previousTitle;
      document.body.classList.remove('printing-official-bon');
    };
    window.addEventListener('afterprint', cleanup, { once: true });
    window.print();
    setTimeout(cleanup, 60000);
  });

  // Impression A4 Rapport Officiel
  document.getElementById('btnPrintReportDoc')?.addEventListener('click', () => {
    const printableReport = document.getElementById('printableReportDocument');
    if (!printableReport) return;
    const previousTitle = document.title;
    document.title = printableReport.dataset.reference || 'Rapport_GESTION+';
    document.body.classList.add('printing-report');
    const cleanup = () => {
      document.title = previousTitle;
      document.body.classList.remove('printing-report');
    };
    window.addEventListener('afterprint', cleanup, { once: true });
    window.print();
    setTimeout(cleanup, 60000);
  });
}

/* ==========================================================================
   9. Filtres Dynamiques des Tableaux & Recherche Globale Topbar
   ========================================================================== */
function setupTableFilters() {
  // Stock articles
  const applyStockFilter = () => {
    const query = (document.getElementById('searchStockInput')?.value || '').toLowerCase();
    const cat = document.getElementById('filterStockCategory')?.value || '';
    const status = document.getElementById('filterStockStatus')?.value || '';
    document.querySelectorAll('#articlesStockTableBody tr[data-cat]').forEach(tr => {
      const matchQuery = !query || tr.innerText.toLowerCase().includes(query);
      const matchCat = !cat || tr.getAttribute('data-cat') === cat;
      const matchStatus = !status || tr.getAttribute('data-status') === status;
      tr.style.display = matchQuery && matchCat && matchStatus ? '' : 'none';
    });
  };
  document.getElementById('searchStockInput')?.addEventListener('input', applyStockFilter);
  document.getElementById('filterStockCategory')?.addEventListener('change', applyStockFilter);
  document.getElementById('filterStockStatus')?.addEventListener('change', applyStockFilter);

  // Mouvements de stock (type + dates + texte)
  const applyMvtFilter = () => {
    const type = document.getElementById('filterMovementType')?.value || '';
    const from = document.getElementById('filterMvtFrom')?.value || '';
    const to = document.getElementById('filterMvtTo')?.value || '';
    const query = (document.getElementById('searchMovementInput')?.value || '').toLowerCase();
    document.querySelectorAll('#stockMovementsTableBody tr[data-type]').forEach(tr => {
      const rowType = tr.getAttribute('data-type') || '';
      const rowDate = tr.getAttribute('data-date') || '';
      const matchType = !type || rowType === type;
      const matchFrom = !from || (rowDate && rowDate >= from);
      const matchTo = !to || (rowDate && rowDate <= to);
      const matchQuery = !query || tr.innerText.toLowerCase().includes(query);
      tr.style.display = matchType && matchFrom && matchTo && matchQuery ? '' : 'none';
    });
  };
  document.getElementById('filterMovementType')?.addEventListener('change', applyMvtFilter);
  document.getElementById('filterMvtFrom')?.addEventListener('change', applyMvtFilter);
  document.getElementById('filterMvtTo')?.addEventListener('change', applyMvtFilter);
  document.getElementById('searchMovementInput')?.addEventListener('input', applyMvtFilter);

  // Documents administratifs
  const applyDocFilter = () => {
    const query = (document.getElementById('searchDocsInput')?.value || '').toLowerCase();
    const cat = document.getElementById('filterDocCategory')?.value || '';
    const type = document.getElementById('filterDocType')?.value || '';
    const srv = document.getElementById('filterDocService')?.value || '';
    document.querySelectorAll('#documentsTableBody tr[data-cat]').forEach(tr => {
      const ok = (!query || tr.innerText.toLowerCase().includes(query))
        && (!cat || tr.getAttribute('data-cat') === cat)
        && (!type || tr.getAttribute('data-type') === type)
        && (!srv || tr.getAttribute('data-service') === srv);
      tr.style.display = ok ? '' : 'none';
    });
  };
  document.getElementById('searchDocsInput')?.addEventListener('input', applyDocFilter);
  document.getElementById('filterDocCategory')?.addEventListener('change', applyDocFilter);
  document.getElementById('filterDocType')?.addEventListener('change', applyDocFilter);
  document.getElementById('filterDocService')?.addEventListener('change', applyDocFilter);

  // Utilisateurs
  const applyUserFilter = () => {
    const query = (document.getElementById('searchUsersInput')?.value || '').toLowerCase();
    const role = document.getElementById('filterUserRole')?.value || '';
    const status = document.getElementById('filterUserStatus')?.value || '';
    document.querySelectorAll('#usersTableBody tr[data-role]').forEach(tr => {
      const ok = (!query || tr.innerText.toLowerCase().includes(query))
        && (!role || tr.getAttribute('data-role') === role)
        && (!status || tr.getAttribute('data-active') === status);
      tr.style.display = ok ? '' : 'none';
    });
  };
  document.getElementById('searchUsersInput')?.addEventListener('input', applyUserFilter);
  document.getElementById('filterUserRole')?.addEventListener('change', applyUserFilter);
  document.getElementById('filterUserStatus')?.addEventListener('change', applyUserFilter);

  // Toutes les demandes
  const applyRequestsFilter = () => {
    const status = document.getElementById('filterReqStatus')?.value || '';
    const cat = document.getElementById('filterReqCategory')?.value || '';
    const urg = document.getElementById('filterReqUrgency')?.value || '';
    const query = (document.getElementById('searchRequestsInput')?.value || '').toLowerCase();
    document.querySelectorAll('#allRequestsTableBody tr[data-status]').forEach(tr => {
      const rowStatus = tr.getAttribute('data-status') || '';
      const matchStatus = !status || rowStatus === status;
      const matchCat = !cat || tr.getAttribute('data-category') === cat;
      const matchUrg = !urg || tr.getAttribute('data-urgency') === urg;
      const matchQuery = !query || tr.innerText.toLowerCase().includes(query);
      tr.style.display = matchStatus && matchCat && matchUrg && matchQuery ? '' : 'none';
    });
  };
  document.getElementById('filterReqStatus')?.addEventListener('change', applyRequestsFilter);
  document.getElementById('filterReqCategory')?.addEventListener('change', applyRequestsFilter);
  document.getElementById('filterReqUrgency')?.addEventListener('change', applyRequestsFilter);
  document.getElementById('searchRequestsInput')?.addEventListener('input', applyRequestsFilter);

  // Patrimoine & Dépenses
  const applyAssetFilter = () => {
    const cond = document.getElementById('filterAssetCondition')?.value || '';
    const avail = document.getElementById('filterAssetAvailability')?.value || '';
    const q = (document.getElementById('searchAssetInput')?.value || '').toLowerCase();
    document.querySelectorAll('#assetsTableBody tr[data-condition]').forEach(tr => {
      const ok = (!cond || tr.getAttribute('data-condition') === cond)
        && (!avail || tr.getAttribute('data-availability') === avail)
        && (!q || tr.innerText.toLowerCase().includes(q));
      tr.style.display = ok ? '' : 'none';
    });
  };
  document.getElementById('filterAssetCondition')?.addEventListener('change', applyAssetFilter);
  document.getElementById('filterAssetAvailability')?.addEventListener('change', applyAssetFilter);
  document.getElementById('searchAssetInput')?.addEventListener('input', applyAssetFilter);

  const applyExpenseFilter = () => {
    const scope = document.getElementById('filterExpenseScope')?.value || '';
    const q = (document.getElementById('searchExpenseInput')?.value || '').toLowerCase();
    document.querySelectorAll('#expensesTableBody tr[data-status]').forEach(tr => {
      let matchScope = true;
      if (scope === 'unjustified') matchScope = tr.getAttribute('data-unjustified') === '1';
      else if (scope === 'overrun') matchScope = tr.getAttribute('data-overrun') === '1';
      else if (scope) matchScope = tr.getAttribute('data-status') === scope;
      const matchQ = !q || tr.innerText.toLowerCase().includes(q);
      tr.style.display = matchScope && matchQ ? '' : 'none';
    });
  };
  document.getElementById('filterExpenseScope')?.addEventListener('change', applyExpenseFilter);
  document.getElementById('searchExpenseInput')?.addEventListener('input', applyExpenseFilter);
}

function setupSearchGlobal() {
  const globalInput = document.getElementById('topbarGlobalSearch');
  const dropdown = document.getElementById('globalSearchResultsDropdown');
  if (!globalInput) return;

  const closeDropdown = () => { if (dropdown) dropdown.style.display = 'none'; };

  globalInput.addEventListener('input', () => {
    if (!dropdown) return;
    const q = globalInput.value.trim().toLowerCase();
    if (q.length < 2) {
      closeDropdown();
      return;
    }

    const matches = [];
    (AppState.currentRequests || []).forEach(r => {
      if (`${r.reference} ${r.motif} ${r.category} ${r.user?.name || ''}`.toLowerCase().includes(q)) {
        matches.push({ icon: 'bi-file-earmark-text text-primary', title: `${r.reference} — ${r.motif}`, sub: `Demande • ${r.status}`, action: () => openRequestDetail(r.reference) });
      }
    });
    (AppState.currentArticles || []).forEach(a => {
      if (`${a.code} ${a.name} ${a.category}`.toLowerCase().includes(q)) {
        matches.push({ icon: 'bi-box-seam text-success', title: `${a.code} — ${a.name}`, sub: `Stock : ${a.quantity} ${a.unit}`, action: () => navigateToView('stock-articles') });
      }
    });
    if (AppState.canManageOps) {
      (AppState.currentAssets || []).forEach(as => {
        if (`${as.inventory_number} ${as.name} ${as.serial_number || ''}`.toLowerCase().includes(q)) {
          matches.push({ icon: 'bi-pc-display text-info', title: `${as.inventory_number} — ${as.name}`, sub: `Patrimoine • ${as.condition}`, action: () => { navigateToView('operations'); openAssetModal(as.id, 'history'); } });
        }
      });
      (AppState.currentVehicles || []).forEach(v => {
        if (`${v.registration_number} ${v.brand} ${v.model}`.toLowerCase().includes(q)) {
          matches.push({ icon: 'bi-truck text-warning', title: `${v.registration_number} — ${v.brand} ${v.model}`, sub: `Parc auto • ${v.status}`, action: () => { navigateToView('operations'); openVehicleModal(v.id, 'history'); } });
        }
      });
    }

    if (!matches.length) {
      dropdown.innerHTML = '<div class="p-3 text-muted small text-center">Aucun résultat correspondant.</div>';
    } else {
      dropdown.innerHTML = matches.slice(0, 8).map((m, idx) => `
        <button type="button" class="btn text-start w-100 px-3 py-2 border-bottom d-flex align-items-center gap-2 js-global-hit" data-hit-idx="${idx}">
          <i class="bi ${m.icon} fs-5"></i>
          <div><div class="fw-semibold small text-dark">${escHtml(m.title)}</div><small class="text-muted">${escHtml(m.sub)}</small></div>
        </button>
      `).join('');
      dropdown.querySelectorAll('.js-global-hit').forEach(btn => {
        btn.addEventListener('click', () => {
          const hit = matches[Number(btn.dataset.hitIdx)];
          closeDropdown();
          globalInput.value = '';
          hit?.action();
        });
      });
    }
    dropdown.style.display = 'block';
  });

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.topbar-search-wrapper')) closeDropdown();
  });
}

function showToast(message, type = 'info') {
  const container = document.getElementById('toastContainer');
  if (!container) return;

  let bgClass = 'text-bg-dark';
  let icon = 'bi-info-circle-fill text-info';
  if (type === 'success') { bgClass = 'text-bg-success'; icon = 'bi-check-circle-fill'; }
  else if (type === 'warning') { bgClass = 'text-bg-warning text-dark'; icon = 'bi-exclamation-triangle-fill'; }
  else if (type === 'error' || type === 'danger') { bgClass = 'text-bg-danger'; icon = 'bi-x-circle-fill'; }

  const toastEl = document.createElement('div');
  toastEl.className = `toast align-items-center ${bgClass} border-0 shadow-lg mb-2`;
  toastEl.setAttribute('role', 'alert');
  toastEl.innerHTML = `
    <div class="d-flex">
      <div class="toast-body d-flex align-items-center gap-2 py-2 px-3">
        <i class="bi ${icon} fs-5"></i>
        <span>${escHtml(message)}</span>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  `;
  container.appendChild(toastEl);
  const bsToast = new bootstrap.Toast(toastEl, { delay: 4200 });
  bsToast.show();
  toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

/* ==========================================================================
   10. Authentification, Profil, Paramètres, Référentiels, Audit & Sauvegardes
   ========================================================================== */
function setupAuthAndSettingsHandlers() {
  // Mot de passe oublié
  document.getElementById('linkForgotPassword')?.addEventListener('click', (e) => {
    e.preventDefault();
    const loginEmail = document.getElementById('loginEmail')?.value.trim() || '';
    const forgotIn = document.getElementById('forgotPasswordEmail');
    if (forgotIn && loginEmail) forgotIn.value = loginEmail;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalForgotPassword')).show();
  });

  document.getElementById('formForgotPassword')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('forgotPasswordEmail')?.value.trim();
    if (!email) return;
    try {
      const res = await fetch('/api/auth/forgot-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email })
      });
      const data = await res.json();
      showToast(data.message || 'Demande transmise à l’administrateur.', res.ok ? 'success' : 'warning');
      if (res.ok) bootstrap.Modal.getInstance(document.getElementById('modalForgotPassword'))?.hide();
    } catch (err) {
      showToast('Impossible de traiter votre demande pour le moment. Veuillez réessayer.', 'danger');
    }
  });

  // Inscription
  document.getElementById('formRegisterUser')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const pwd = document.getElementById('regPassword').value;
    const pwdConfirm = document.getElementById('regPasswordConfirm').value;
    if (pwd !== pwdConfirm) {
      showToast('Les deux mots de passe ne correspondent pas.', 'danger');
      return;
    }

    const name = document.getElementById('regNom').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const service = document.getElementById('regService').value;

    try {
      const res = await fetch('/api/auth/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email, service, password: pwd })
      });
      const result = await res.json();
      if (res.ok && result.success && result.user && result.token) {
        setAuthSession(result.user, result.token);
        applyUser(result.user);
        showToast(result.message || `Compte créé avec succès pour ${name} !`, 'success');
        navigateToView('dashboard');
        await BackendApi.loadAllData();
      } else {
        const msg = Object.values(result.errors || {}).flat()[0] || result.message || 'Erreur lors de la création du compte.';
        showToast(msg, 'danger');
      }
    } catch (err) {
      showToast('Impossible de finaliser l’inscription pour le moment. Veuillez réessayer.', 'danger');
    }
  });

  // Connexion
  document.getElementById('formLoginUser')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;

    try {
      const res = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
      });
      const result = await res.json();
      if (res.ok && result.success && result.user && result.token) {
        setAuthSession(result.user, result.token);
        applyUser(result.user);
        showToast(`Bienvenue ${result.user.name} !`, 'success');
        navigateToView('dashboard');
        await BackendApi.loadAllData();
      } else {
        showToast(result.message || 'Identifiants invalides.', 'danger');
      }
    } catch (err) {
      showToast('Connexion impossible pour le moment. Vérifiez votre accès réseau et réessayez.', 'danger');
    }
  });

  // Onglets Paramètres (strictement scopés sur #view-settings)
  const settingsPills = document.querySelectorAll('#view-settings [data-settings-tab]');
  settingsPills.forEach(btn => {
    btn.addEventListener('click', () => {
      settingsPills.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const targetPane = btn.getAttribute('data-settings-tab');
      document.querySelectorAll('#view-settings .settings-tab-pane').forEach(pane => { pane.style.display = 'none'; });
      const activePane = document.getElementById(`settingsPane-${targetPane}`);
      if (activePane) activePane.style.display = 'block';
    });
  });

  // Enregistrement des paramètres système (Module 10)
  document.getElementById('btnSaveSettings')?.addEventListener('click', async () => {
    const settings = [
      { key: 'company_name', value: document.getElementById('settingOrgName')?.value || '', group: 'general' },
      { key: 'company_short_code', value: document.getElementById('settingOrgAcronym')?.value || '', group: 'general' },
      { key: 'currency', value: document.getElementById('settingCurrency')?.value || 'XOF', group: 'general' },
      { key: 'timezone', value: document.getElementById('settingTimezone')?.value || 'Africa/Abidjan', group: 'general' },
      { key: 'company_address', value: document.getElementById('settingOrgAddress')?.value || '', group: 'general' },
      { key: 'company_phone', value: document.getElementById('settingOrgContact')?.value || '', group: 'general' },
      { key: 'default_min_threshold', value: document.getElementById('settingDefaultStockThreshold')?.value || '5', group: 'stock' },
      { key: 'default_max_threshold', value: document.getElementById('settingDefaultMaxThreshold')?.value || '100', group: 'stock' },
      { key: 'inventory_frequency', value: document.getElementById('settingInventoryFrequency')?.value || 'monthly', group: 'stock' },
      { key: 'notify_on_low_stock', value: document.getElementById('switchAlertEmail')?.checked ? '1' : '0', group: 'notifications' },
      { key: 'block_zero_stock', value: document.getElementById('switchBlockZeroStock')?.checked ? '1' : '0', group: 'stock' },
      { key: 'validation_levels', value: document.getElementById('settingValidationLevels')?.value || '2', group: 'workflow' },
      { key: 'sla_processing_hours', value: document.getElementById('settingRequestSlaHours')?.value || '48', group: 'workflow' },
      { key: 'notify_on_request_submitted', value: document.getElementById('switchNotifyOnSubmit')?.checked ? '1' : '0', group: 'notifications' },
      { key: 'notify_on_request_decision', value: document.getElementById('switchNotifyRequester')?.checked ? '1' : '0', group: 'notifications' },
      { key: 'notify_on_vehicle_alert', value: document.getElementById('switchNotifyMaintenance')?.checked ? '1' : '0', group: 'notifications' },
      { key: 'notify_on_expense_overrun', value: document.getElementById('switchNotifyExpenseOverrun')?.checked ? '1' : '0', group: 'notifications' },
      { key: 'session_timeout_minutes', value: document.getElementById('settingSessionTimeout')?.value || '60', group: 'security' },
      { key: 'password_expiry_days', value: document.getElementById('settingPasswordExpiry')?.value || '90', group: 'security' }
    ];

    const res = await fetch('/api/operations/settings', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ settings })
    });
    const data = await res.json();
    showToast(data.message || 'Paramètres enregistrés.', res.ok ? 'success' : 'danger');
    if (res.ok) await BackendApi.loadCatalogReferences();
  });

  // Formulaires d'ajout aux 6 référentiels métier
  document.querySelectorAll('.js-ref-create-form').forEach(form => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const endpoint = form.getAttribute('data-ref-endpoint');
      if (!endpoint) return;
      const payload = Object.fromEntries([...new FormData(form).entries()].filter(([, v]) => v !== ''));
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      showToast(data.message || (res.ok ? 'Élément ajouté.' : 'Ajout impossible.'), res.ok ? 'success' : 'danger');
      if (res.ok) {
        form.reset();
        await BackendApi.loadAdminSettingsAndRefs();
        await BackendApi.loadCatalogReferences();
      }
    });
  });

  // Délégation de validation
  document.getElementById('formNewDelegation')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const delegatorId = document.getElementById('delegationDelegatorSelect').value;
    const delegateId = document.getElementById('delegationDelegateSelect').value;
    const startsAt = document.getElementById('delegationStartDate').value;
    const endsAt = document.getElementById('delegationEndDate').value;
    const notes = document.getElementById('delegationReason').value.trim();

    const res = await fetch('/api/operations/reference-data/delegations', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        delegator_id: Number(delegatorId),
        delegate_id: Number(delegateId),
        scope: 'Toutes les demandes',
        starts_at: startsAt,
        ends_at: endsAt || null,
        notes
      })
    });
    const data = await res.json();
    showToast(data.message || 'Délégation enregistrée.', res.ok ? 'success' : 'danger');
    if (res.ok) {
      e.currentTarget.reset();
      await BackendApi.loadAdminSettingsAndRefs();
    }
  });

  // Filtres Journal d'audit
  const reloadAudit = () => BackendApi.loadAuditLogs();
  document.getElementById('btnRefreshAuditLogs')?.addEventListener('click', reloadAudit);
  document.getElementById('filterAuditModule')?.addEventListener('change', reloadAudit);
  document.getElementById('filterAuditFrom')?.addEventListener('change', reloadAudit);
  document.getElementById('filterAuditTo')?.addEventListener('change', reloadAudit);
  document.getElementById('filterAuditSearch')?.addEventListener('input', reloadAudit);

  // Sauvegardes & Restauration (Module 9)
  document.getElementById('btnCreateBackupNow')?.addEventListener('click', async () => {
    const res = await fetch('/api/backups', { method: 'POST' });
    const data = await res.json();
    showToast(data.message || 'Sauvegarde générée.', res.ok ? 'success' : 'danger');
    if (res.ok) await BackendApi.loadBackups();
  });

  document.getElementById('formRestoreUploadedBackup')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const file = document.getElementById('inputBackupRestoreFile')?.files?.[0];
    if (!file) {
      showToast('Veuillez sélectionner un fichier de sauvegarde.', 'warning');
      return;
    }
    if (!confirm('Confirmez-vous la restauration de cette archive ? Les tables métier seront remplacées par le contenu de la sauvegarde.')) return;
    const fd = new FormData();
    fd.append('file', file);
    const res = await fetch('/api/backups/restore', { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.message || 'Restauration terminée.', res.ok ? 'success' : 'danger');
    if (res.ok) {
      e.currentTarget.reset();
      await BackendApi.refreshAll({ showIndicator: true, silent: true });
    }
  });

  // Profil utilisateur & changement de mot de passe
  document.getElementById('formUserProfile')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('profileInputName').value.trim();
    const email = document.getElementById('profileInputEmail').value.trim();
    const phone = document.getElementById('profileInputPhone')?.value.trim() || null;
    const officeLocation = document.getElementById('profileInputOffice')?.value.trim() || null;
    const currentPassword = document.getElementById('profileCurrentPassword')?.value || '';
    const newPassword = document.getElementById('profileNewPassword')?.value || '';
    const confirmPassword = document.getElementById('profileConfirmPassword')?.value || '';

    if (newPassword && newPassword !== confirmPassword) {
      showToast('La confirmation du nouveau mot de passe ne correspond pas.', 'danger');
      return;
    }

    const payload = { name, email, phone, office_location: officeLocation };
    if (newPassword) {
      payload.current_password = currentPassword;
      payload.password = newPassword;
      payload.password_confirmation = confirmPassword;
    }

    const res = await fetch('/api/auth/profile', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (res.ok && data.success) {
      setAuthSession(data.user, null);
      applyUser(data.user);
      if (document.getElementById('profileCurrentPassword')) document.getElementById('profileCurrentPassword').value = '';
      if (document.getElementById('profileNewPassword')) document.getElementById('profileNewPassword').value = '';
      if (document.getElementById('profileConfirmPassword')) document.getElementById('profileConfirmPassword').value = '';
      showToast(data.message || 'Profil mis à jour avec succès.', 'success');
    } else {
      const msg = Object.values(data.errors || {}).flat()[0] || data.message || 'Mise à jour impossible.';
      showToast(msg, 'danger');
    }
  });
}

window.restoreExistingBackup = async function (backupId, ref) {
  if (!confirm(`Restaurer la sauvegarde ${ref} ?`)) return;
  const res = await fetch('/api/backups/restore', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ backup_id: backupId })
  });
  const data = await res.json();
  showToast(data.message || 'Restauration effectuée.', res.ok ? 'success' : 'danger');
  if (res.ok) await BackendApi.refreshAll({ showIndicator: true, silent: true });
};

window.toggleReferenceItemActive = async function (endpoint, currentActive) {
  const res = await fetch(endpoint, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ is_active: !currentActive })
  });
  if (res.ok) {
    await BackendApi.loadAdminSettingsAndRefs();
    await BackendApi.loadCatalogReferences();
  }
};

function populateRelationSelectors(requests = [], articles = [], assignees = []) {
  const fillSelect = (id, rows, labelBuilder, placeholder) => {
    const select = document.getElementById(id);
    if (!select) return;
    const previousValue = select.value;
    select.innerHTML = `<option value="">${placeholder}</option>` + rows.map(row => `<option value="${row.id}">${escHtml(labelBuilder(row))}</option>`).join('');
    if ([...select.options].some(option => option.value === previousValue)) select.value = previousValue;
  };

  const selectableRequests = requests.filter(r => r.status === 'Validée');
  const authorizedRequests = requests.filter(r => AUTHORIZED_REQUEST_STATUSES.includes(r.status));
  fillSelect('expenseRequestSelect', selectableRequests, r => `${r.reference} — ${r.motif} (${r.service})`, 'Sélectionner une demande…');
  fillSelect('mvtRequestId', authorizedRequests, r => `${r.reference} — ${r.user?.name || r.service}`, 'Aucune (Entrée / Transfert / Urgence)');
  fillSelect('inventoryArticleSelect', articles, a => `${a.code} — ${a.name} (théorique : ${a.quantity})`, 'Choisir un article à ajouter…');
  fillSelect('assignAgentSelect', assignees, u => `${u.name} — ${u.service || 'Sans service'}`, 'Sélectionner un agent…');
  fillSelect('vehicleDriverSelect', assignees, u => `${u.name} (${u.service || 'Conducteur'})`, 'Aucun conducteur');
}

function applyCatalogReferencesToSelects(refs) {
  if (!refs) return;

  const fillNamedOptions = (selectId, items, keepEmptyFirst = null) => {
    const sel = document.getElementById(selectId);
    if (!sel || !items?.length) return;
    const prev = sel.value;
    const prefix = keepEmptyFirst !== null ? `<option value="">${keepEmptyFirst}</option>` : '';
    sel.innerHTML = prefix + items.map(it => `<option value="${escHtml(it.name)}">${escHtml(it.name)}</option>`).join('');
    if ([...sel.options].some(o => o.value === prev)) sel.value = prev;
  };

  if (refs.departments?.length) {
    fillNamedOptions('selectRequestService', refs.departments);
    fillNamedOptions('usrService', refs.departments);
    fillNamedOptions('regService', refs.departments);
    fillNamedOptions('fpDocService', refs.departments);
    fillNamedOptions('filterDocService', refs.departments, 'Tous les services');
    fillNamedOptions('reportFilterService', refs.departments, 'Tous les services');
  }

  if (refs.request_categories?.length) {
    fillNamedOptions('selectRequestCategory', refs.request_categories);
    fillNamedOptions('filterReqCategory', refs.request_categories, 'Toutes les catégories');
    fillNamedOptions('reportFilterCategory', refs.request_categories, 'Toutes catégories');
  }

  if (refs.article_categories?.length) {
    fillNamedOptions('artCategory', refs.article_categories);
    fillNamedOptions('filterStockCategory', refs.article_categories, 'Toutes catégories');
  }

  if (refs.units?.length) {
    const unitSel = document.getElementById('artUnit');
    if (unitSel) {
      const prev = unitSel.value;
      unitSel.innerHTML = refs.units.map(u => `<option value="${escHtml(u.name)}">${escHtml(u.name)}${u.symbol ? ` (${escHtml(u.symbol)})` : ''}</option>`).join('');
      if ([...unitSel.options].some(o => o.value === prev)) unitSel.value = prev;
    }
  }

  if (refs.locations?.length) {
    const dlLoc = document.getElementById('datalistLocations');
    if (dlLoc) dlLoc.innerHTML = refs.locations.map(l => `<option value="${escHtml(l.name)}"></option>`).join('');
  }

  if (refs.suppliers?.length) {
    const dlSup = document.getElementById('datalistSuppliers');
    if (dlSup) dlSup.innerHTML = refs.suppliers.map(s => `<option value="${escHtml(s.name)}"></option>`).join('');
    const expSup = document.getElementById('expenseSupplierSelect');
    if (expSup) {
      const prev = expSup.value;
      expSup.innerHTML = '<option value="">Aucun / Bénéficiaire interne</option>' + refs.suppliers.map(s => `<option value="${s.id}">${escHtml(s.name)}</option>`).join('');
      if ([...expSup.options].some(o => o.value === prev)) expSup.value = prev;
    }
  }
}

function setupOperationsCenter() {
  document.querySelectorAll('[data-operations-tab]').forEach(button => {
    button.addEventListener('click', () => {
      document.querySelectorAll('[data-operations-tab]').forEach(item => item.classList.remove('active'));
      document.querySelectorAll('.operations-tab-pane').forEach(pane => { pane.style.display = 'none'; });
      button.classList.add('active');
      const pane = document.getElementById(`operationsPane-${button.dataset.operationsTab}`);
      if (pane) pane.style.display = 'block';
    });
  });

  // Création Bien du patrimoine
  document.getElementById('formNewAsset')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const payload = Object.fromEntries([...new FormData(form).entries()].filter(([, v]) => v !== ''));
    const res = await fetch('/api/assets', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    const data = await res.json();
    showToast(data.message || (res.ok ? 'Bien enregistré.' : 'Enregistrement impossible.'), res.ok ? 'success' : 'danger');
    if (res.ok) { form.reset(); await BackendApi.loadOperations(); }
  });

  // Création Véhicule
  document.getElementById('formNewVehicle')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const payload = Object.fromEntries([...new FormData(form).entries()].filter(([, v]) => v !== ''));
    const res = await fetch('/api/vehicles', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    const data = await res.json();
    showToast(data.message || (res.ok ? 'Véhicule enregistré.' : 'Enregistrement impossible.'), res.ok ? 'success' : 'danger');
    if (res.ok) { form.reset(); await BackendApi.loadOperations(); }
  });

  // Création Dépense (FormData pour supporter les pièces justificatives multiples + autorisation directe auto)
  document.getElementById('formNewExpense')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const fd = new FormData(form);
    for (const [key, val] of [...fd.entries()]) {
      if (val === '' && key !== 'attachments[]') fd.delete(key);
    }
    const res = await fetch('/api/expenses', { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.message || (res.ok ? 'Dépense enregistrée.' : 'Enregistrement impossible.'), res.ok ? 'success' : 'danger');
    if (res.ok) { form.reset(); await BackendApi.loadOperations(); }
  });

  // Inventaire physique multi-articles (Général ou Partiel)
  document.getElementById('btnAddInventoryLine')?.addEventListener('click', () => {
    const artId = document.getElementById('inventoryArticleSelect')?.value;
    if (!artId) {
      showToast('Choisissez un article à ajouter au comptage.', 'warning');
      return;
    }
    const art = AppState.currentArticles.find(a => Number(a.id) === Number(artId));
    if (art) addInventoryCountingRow(art);
  });

  document.getElementById('btnLoadAllArticlesForInventory')?.addEventListener('click', () => {
    const scopeSel = document.getElementById('inventoryScopeSelect');
    if (scopeSel) scopeSel.value = 'Général';
    const tbody = document.getElementById('inventoryCountingTableBody');
    if (tbody) tbody.innerHTML = '';
    (AppState.currentArticles || []).forEach(art => addInventoryCountingRow(art));
    showToast(`${AppState.currentArticles.length} article(s) chargés pour l’inventaire général.`, 'info');
  });

  document.getElementById('formPhysicalInventory')?.addEventListener('submit', async event => {
    event.preventDefault();
    const rows = [...document.querySelectorAll('#inventoryCountingTableBody tr[data-inv-article-id]')];
    if (rows.length === 0) {
      showToast('Ajoutez au moins un article au comptage physique.', 'warning');
      return;
    }

    const items = [];
    for (const tr of rows) {
      const articleId = Number(tr.getAttribute('data-inv-article-id'));
      const theo = Number(tr.getAttribute('data-theoretical-qty'));
      const phys = Number(tr.querySelector('.js-inv-physical-qty')?.value || 0);
      const justif = tr.querySelector('.js-inv-justification')?.value.trim() || '';
      if (phys !== theo && !justif) {
        showToast('Veuillez renseigner une justification pour chaque ligne présentant un écart.', 'warning');
        return;
      }
      items.push({ article_id: articleId, physical_quantity: phys, justification: justif || null });
    }

    const scope = document.getElementById('inventoryScopeSelect')?.value || 'Partiel';
    const inventoryDate = document.getElementById('inventoryDateInput')?.value || new Date().toISOString().slice(0, 10);
    const notes = document.getElementById('inventoryGeneralNotes')?.value.trim() || null;

    const response = await fetch('/api/inventories', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ scope, inventory_date: inventoryDate, notes, items })
    });
    const data = await response.json();
    showToast(data.message || (response.ok ? 'Inventaire enregistré.' : 'Création impossible.'), response.ok ? 'success' : 'danger');
    if (response.ok) {
      document.getElementById('inventoryCountingTableBody').innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Sélectionnez un article ou cliquez sur « Charger tout le stock » pour saisir le comptage physique.</td></tr>';
      if (document.getElementById('inventoryGeneralNotes')) document.getElementById('inventoryGeneralNotes').value = '';
      await BackendApi.loadOperations();
    }
  });

  // Filtres et export Rapports (Module 7)
  document.getElementById('formReportFilters')?.addEventListener('submit', async event => {
    event.preventDefault();
    const query = new URLSearchParams([...new FormData(event.currentTarget).entries()].filter(([, v]) => v !== '')).toString();
    await BackendApi.loadReport(query);
  });

  document.getElementById('btnOpenReportPrintPreview')?.addEventListener('click', async () => {
    if (!AppState.lastReportPayload) await BackendApi.loadReport();
    openReportPreviewModal();
  });

  document.getElementById('btnExportFilteredReportCsv')?.addEventListener('click', () => {
    const form = document.getElementById('formReportFilters');
    const query = form ? new URLSearchParams([...new FormData(form).entries()].filter(([, v]) => v !== '')).toString() : '';
    downloadAuthenticatedFile(`/api/reports-export${query ? `?${query}` : ''}`, 'rapport-gestion.csv');
  });

  document.getElementById('formGenerateSavedReport')?.addEventListener('submit', async event => {
    event.preventDefault();
    const filterForm = document.getElementById('formReportFilters');
    const filterEntries = filterForm ? Object.fromEntries([...new FormData(filterForm).entries()].filter(([, v]) => v !== '')) : {};
    const genEntries = Object.fromEntries([...new FormData(event.currentTarget).entries()].filter(([, v]) => v !== ''));
    const res = await fetch('/api/reports/generate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...filterEntries, ...genEntries })
    });
    const data = await res.json();
    showToast(data.message || 'Rapport archivé.', res.ok ? 'success' : 'danger');
    if (res.ok) {
      event.currentTarget.reset();
      await BackendApi.loadReport();
      if (data.data) openReportPreviewModal(data.data);
    }
  });
}

/* ==========================================================================
   11. Pont Applicatif Backend Laravel & Base de Données MySQL (BackendApi)
   ========================================================================== */
const BackendApi = {
  baseUrl: '/api',
  isOnline: false,
  isDbConnected: false,

  async checkHealth() {
    try {
      const res = await fetch(`${this.baseUrl}/health`);
      if (res.ok) {
        const data = await res.json();
        this.isOnline = true;
        this.isDbConnected = Boolean(data.database_connected);
        this.updateStatusBadge(data);

        // Charger les référentiels publics (pour formulaire d'inscription notamment)
        await this.loadCatalogReferences(true);

        if (this.isDbConnected && data.has_users === false) {
          clearAuthSession();
          navigateToView('register', false);
          return data;
        }

        const token = getAuthToken();
        if (token) {
          const meRes = await fetch(`${this.baseUrl}/auth/me`);
          if (!meRes.ok) {
            clearAuthSession();
            showToast('Votre session a expiré ou a été révoquée. Veuillez vous reconnecter.', 'warning');
            navigateToView('login', false);
            return data;
          }
          const meJson = await meRes.json();
          if (meJson.success && meJson.user) {
            setAuthSession(meJson.user, null);
            applyUser(meJson.user);
          }

          if (this.isDbConnected) {
            await this.refreshAll({ showIndicator: false, silent: true });
            this.startAutoRefresh(25000);
          }
        }
        return data;
      }
    } catch (e) {
      console.warn('Backend API non disponible:', e.message);
      this.updateStatusBadge({ status: 'offline' });
    }
    return null;
  },

  updateStatusBadge() {
    document.getElementById('backendStatusBadge')?.remove();
    document.getElementById('modalDbStatus')?.remove();
  },

  async refreshAll(options = { showIndicator: false, silent: false }) {
    if (!this.isOnline || !this.isDbConnected || !getAuthToken()) return;

    try {
      const loads = [
        this.loadCatalogReferences(),
        this.loadArticles(),
        this.loadRequests(),
        this.loadDashboard(),
        this.loadNotifications()
      ];

      if (AppState.canManageOps) {
        if (hasAnyPermission('stock.view')) loads.push(this.loadMovements());
        if (hasAnyPermission('documents.manage')) loads.push(this.loadDocuments());
        if (hasAnyPermission('assets.manage', 'vehicles.manage', 'expenses.manage', 'expenses.validate', 'inventory.manage', 'inventory.validate', 'reports.view')) {
          loads.push(this.loadOperations());
        }
      }
      if (AppState.isSuperAdmin) {
        loads.push(this.loadUsers(), this.loadAdminSettingsAndRefs());
        if (AppState.currentView === 'settings') {
          loads.push(this.loadAuditLogs(), this.loadBackups());
        }
      }

      await Promise.allSettled(loads);
      updateCollaboratorLiveTracker();
      applyRoleVisibility(AppState.currentRole);

      if (options.showIndicator && !options.silent) {
        showToast('Affichage actualisé.', 'success');
      }
    } catch (e) {
      console.warn('refreshAll error:', e);
    }
  },

  async loadAllData() {
    return this.refreshAll({ showIndicator: false, silent: true });
  },

  startAutoRefresh(intervalMs = 25000) {
    if (this._autoRefreshTimer) clearInterval(this._autoRefreshTimer);
    this._autoRefreshTimer = setInterval(() => {
      if (!document.hidden && !document.querySelector('.modal.show') && getAuthToken()) {
        this.refreshAll({ silent: true, showIndicator: false });
      }
    }, intervalMs);
  },

  async loadCatalogReferences(usePublic = false) {
    try {
      const endpoint = usePublic || !getAuthToken()
        ? `${this.baseUrl}/public-references`
        : `${this.baseUrl}/catalog-references`;
      const res = await fetch(endpoint);
      if (!res.ok) return;
      const json = await res.json();
      if (json.success && json.data) {
        AppState.catalogRefs = json.data;
        applyCatalogReferencesToSelects(json.data);
      }
    } catch (e) {
      console.warn('loadCatalogReferences error:', e);
    }
  },

  async loadOperations() {
    if (!AppState.canManageOps) return;
    try {
      const canUseOverview = hasAnyPermission('assets.manage', 'vehicles.manage', 'expenses.manage', 'inventory.manage', 'reports.view');
      const canLoadAssignees = hasAnyPermission('requests.assign', 'assets.manage', 'vehicles.manage', 'expenses.manage');
      const [overviewRes, assetsRes, vehiclesRes, expensesRes, inventoriesRes, assigneesRes] = await Promise.all([
        canUseOverview ? fetch(`${this.baseUrl}/operations/overview`) : null,
        hasAnyPermission('assets.manage') ? fetch(`${this.baseUrl}/assets`) : null,
        hasAnyPermission('vehicles.manage') ? fetch(`${this.baseUrl}/vehicles`) : null,
        hasAnyPermission('expenses.manage', 'expenses.validate') ? fetch(`${this.baseUrl}/expenses`) : null,
        hasAnyPermission('inventory.manage', 'inventory.validate') ? fetch(`${this.baseUrl}/inventories`) : null,
        canLoadAssignees ? fetch(`${this.baseUrl}/operations/assignees`) : null
      ]);
      const responses = [overviewRes, assetsRes, vehiclesRes, expensesRes, inventoriesRes, assigneesRes];
      const payloads = await Promise.all(responses.map(async response => response?.ok ? response.json() : { data: [] }));
      const [overview, assets, vehicles, expenses, inventories, assignees] = payloads;

      const data = overview.data || {};
      const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };

      setText('opsAssetsTotal', data.assets?.total ?? 0);
      setText('opsAssetsAssigned', `${data.assets?.assigned ?? 0} affecté(s)`);
      setText('opsAssetsIncidents', `${data.assets?.open_incidents ?? 0} incident(s) ouvert(s)`);
      setText('opsVehiclesTotal', data.vehicles?.total ?? 0);
      setText('opsVehicleAlerts', `${data.vehicles?.alerts ?? 0} alerte(s) d’échéance / km`);
      setText('opsVehiclesInService', `${data.vehicles?.in_service ?? 0} en service`);
      setText('opsDisbursed', formatCurrency(data.expenses?.disbursed));
      setText('opsUnjustified', `${formatCurrency(data.expenses?.unjustified)} à justifier`);
      setText('opsExpenseOverruns', `${data.expenses?.reapproval ?? 0} dépassement(s)`);
      setText('opsInventoryOpen', data.inventory?.open ?? 0);
      setText('opsInventoryVariance', `${data.inventory?.variances ?? 0} écart(s) d’inventaire`);

      // Alertes synthèse
      const alerts = [];
      if (data.vehicles?.alerts) alerts.push(`${data.vehicles.alerts} véhicule(s) présentent une alerte d’échéance administrative ou de kilométrage d’entretien.`);
      if (data.expenses?.reapproval) alerts.push(`${data.expenses.reapproval} dépense(s) dépassent le plafond autorisé et nécessitent une revalidation.`);
      if ((data.expenses?.unjustified || 0) > 0) alerts.push(`${formatCurrency(data.expenses.unjustified)} de décaissements en attente de pièces justificatives.`);
      if (data.assets?.unavailable) alerts.push(`${data.assets.unavailable} équipement(s) du patrimoine signalé(s) endommagé(s) ou hors service.`);

      const alertBox = document.getElementById('operationsAlerts');
      if (alertBox) {
        alertBox.innerHTML = alerts.length
          ? alerts.map(a => `<div class="alert alert-warning py-2 mb-2"><i class="bi bi-exclamation-triangle me-2"></i>${escHtml(a)}</div>`).join('')
          : '<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-2"></i>Aucune alerte critique active sur le patrimoine, le parc automobile ou les dépenses.</div>';
      }

      AppState.currentAssets = assets.data || [];
      AppState.currentVehicles = vehicles.data || [];
      AppState.currentExpenses = expenses.data || [];
      AppState.currentInventories = inventories.data || [];
      AppState.currentAssignees = assignees.data || [];

      this.renderAssetsTable(AppState.currentAssets);
      this.renderVehiclesTable(AppState.currentVehicles, vehicles.kpis || data.vehicles || {});
      this.renderExpensesTable(AppState.currentExpenses, data.expenses || {});
      this.renderInventoriesTable(AppState.currentInventories);

      populateRelationSelectors(AppState.currentRequests || [], AppState.currentArticles || [], AppState.currentAssignees);
      if (hasAnyPermission('reports.view')) await this.loadReport();
    } catch (error) {
      console.warn('loadOperations error:', error);
    }
  },

  renderAssetsTable(assets) {
    const tbody = document.getElementById('assetsTableBody');
    if (!tbody) return;
    if (!assets.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Aucun bien durable enregistré au patrimoine.</td></tr>';
      return;
    }

    tbody.innerHTML = assets.map(asset => {
      const condClass = ['Endommagé', 'Hors service'].includes(asset.condition) ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success';
      const availClass = asset.availability === 'Affecté' ? 'bg-primary-subtle text-primary' : 'bg-light text-dark';
      return `
        <tr data-condition="${escHtml(asset.condition)}" data-availability="${escHtml(asset.availability)}">
          <td class="fw-bold text-primary font-monospace">${escHtml(asset.inventory_number)}</td>
          <td><strong>${escHtml(asset.name)}</strong>${asset.serial_number ? `<small class="d-block text-muted">S/N: ${escHtml(asset.serial_number)}</small>` : ''}</td>
          <td>${escHtml(asset.category)}</td>
          <td class="small">${formatDateShort(asset.acquired_at)}<div class="text-muted">${asset.acquisition_cost ? formatCurrency(asset.acquisition_cost) : '—'}</div></td>
          <td><span class="badge ${condClass} border">${escHtml(asset.condition)}</span></td>
          <td><span class="badge ${availClass} border">${escHtml(asset.availability)}</span></td>
          <td class="small">
            <strong>${escHtml(asset.assigned_user?.name || 'Non affecté')}</strong>
            <div class="text-muted">${escHtml([asset.department, asset.location].filter(Boolean).join(' • ') || '—')}</div>
          </td>
          <td class="text-end text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" title="Remettre / Affecter" onclick="openAssetModal(${asset.id}, 'assign')"><i class="bi bi-person-plus"></i></button>
            ${asset.availability === 'Affecté' ? `<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Restituer" onclick="openAssetModal(${asset.id}, 'return')"><i class="bi bi-box-arrow-in-left"></i></button>` : ''}
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" title="Signaler un incident" onclick="openAssetModal(${asset.id}, 'incident')"><i class="bi bi-exclamation-triangle"></i></button>
            <button type="button" class="btn btn-sm btn-outline-warning py-0 px-2" title="Maintenance" onclick="openAssetModal(${asset.id}, 'maintenance')"><i class="bi bi-tools"></i></button>
            <button type="button" class="btn btn-sm btn-outline-dark py-0 px-2" title="Modifier" onclick="openAssetModal(${asset.id}, 'edit')"><i class="bi bi-pencil"></i></button>
            <button type="button" class="btn btn-sm btn-light border py-0 px-2" title="Historique complet" onclick="openAssetModal(${asset.id}, 'history')"><i class="bi bi-clock-history"></i></button>
          </td>
        </tr>`;
    }).join('');
  },

  renderVehiclesTable(vehicles, kpis = {}) {
    const setTxt = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    setTxt('fleetKpiTotal', kpis.total ?? vehicles.length);
    setTxt('fleetKpiAvailable', kpis.available ?? 0);
    setTxt('fleetKpiInService', kpis.in_service ?? 0);
    setTxt('fleetKpiMaintenance', kpis.maintenance ?? kpis.unavailable ?? 0);
    setTxt('fleetKpiAlerts', kpis.alerts ?? 0);
    setTxt('fleetKpiCost', formatCurrency(kpis.total_cost ?? kpis.maintenance_cost ?? 0));

    const tbody = document.getElementById('vehiclesTableBody');
    if (!tbody) return;
    if (!vehicles.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Aucun véhicule enregistré au parc automobile.</td></tr>';
      return;
    }

    tbody.innerHTML = vehicles.map(v => {
      const statusBadge = v.status === 'Disponible'
        ? 'bg-success-subtle text-success'
        : (v.status === 'En service' ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning-emphasis');
      const alertsList = v.alerts_summary || [];
      return `
        <tr>
          <td class="fw-bold font-monospace">${escHtml(v.registration_number)}</td>
          <td><strong>${escHtml(v.brand)} ${escHtml(v.model)}</strong><small class="d-block text-muted">${escHtml(v.type || 'Véhicule')}</small></td>
          <td class="fw-semibold">${Number(v.mileage || 0).toLocaleString('fr-FR')} km</td>
          <td><span class="badge ${statusBadge} border">${escHtml(v.status)}</span></td>
          <td class="small"><strong>${escHtml(v.driver?.name || 'Sans conducteur')}</strong><div class="text-muted">${escHtml(v.department || 'Parc central')}</div></td>
          <td class="small">
            ${alertsList.length
              ? alertsList.map(al => `<span class="badge bg-danger-subtle text-danger border d-inline-block me-1 mb-1">${escHtml(al)}</span>`).join('')
              : `<span class="text-success"><i class="bi bi-check-circle me-1"></i>À jour</span>`}
            ${v.next_maintenance_at ? `<div class="text-muted">Entretien : ${formatDateShort(v.next_maintenance_at)}${v.next_maintenance_mileage ? ` / ${Number(v.next_maintenance_mileage).toLocaleString('fr-FR')} km` : ''}</div>` : ''}
          </td>
          <td class="text-end text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" title="Modifier / Kilométrage" onclick="openVehicleModal(${v.id}, 'edit')"><i class="bi bi-speedometer2"></i></button>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Documents & Échéances" onclick="openVehicleModal(${v.id}, 'documents')"><i class="bi bi-file-earmark-check"></i></button>
            <button type="button" class="btn btn-sm btn-outline-warning py-0 px-2" title="Entretien / Réparation" onclick="openVehicleModal(${v.id}, 'maintenance')"><i class="bi bi-wrench"></i></button>
            <button type="button" class="btn btn-sm btn-light border py-0 px-2" title="Historique & Coûts" onclick="openVehicleModal(${v.id}, 'history')"><i class="bi bi-clock-history"></i></button>
          </td>
        </tr>`;
    }).join('');
  },

  renderExpensesTable(expenses, kpis = {}) {
    const setTxt = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = formatCurrency(v); };
    setTxt('expKpiEstimated', kpis.estimated ?? 0);
    setTxt('expKpiAuthorized', kpis.authorized ?? 0);
    setTxt('expKpiCommitted', kpis.committed ?? 0);
    setTxt('expKpiDisbursed', kpis.disbursed ?? 0);
    setTxt('expKpiJustified', kpis.justified ?? 0);
    setTxt('expKpiUnjustified', kpis.unjustified ?? 0);

    const tbody = document.getElementById('expensesTableBody');
    if (!tbody) return;
    if (!expenses.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Aucune dépense enregistrée.</td></tr>';
      return;
    }

    tbody.innerHTML = expenses.map(exp => {
      const toJustify = Number(exp.amount_to_justify || 0);
      const isOverrun = Boolean(exp.requires_reapproval) || exp.status === 'Nouvelle validation requise';
      return `
        <tr data-status="${escHtml(exp.status)}" data-unjustified="${toJustify > 0 ? '1' : '0'}" data-overrun="${isOverrun ? '1' : '0'}">
          <td class="fw-bold text-primary font-monospace">${escHtml(exp.reference)}</td>
          <td class="small">
            ${exp.material_request?.reference
              ? `<a href="#request-detail/${encodeURIComponent(exp.material_request.reference)}" class="fw-semibold text-decoration-none">${escHtml(exp.material_request.reference)}</a>`
              : `<span class="badge bg-light text-dark border">${escHtml(exp.authorization_reference || 'Autorisation')}</span>`}
            <div class="text-muted">${escHtml(exp.payment_method || '')}</div>
          </td>
          <td class="small"><strong>${escHtml(exp.supplier?.name || exp.beneficiary || '—')}</strong>${exp.supplier && exp.beneficiary ? `<div class="text-muted">${escHtml(exp.beneficiary)}</div>` : ''}</td>
          <td class="fw-semibold">${formatCurrency(exp.authorized_amount)}</td>
          <td class="small">Eng: ${formatCurrency(exp.committed_amount)}<div class="fw-semibold text-dark">Déc: ${formatCurrency(exp.disbursed_amount)}</div></td>
          <td class="small">Just: ${formatCurrency(exp.justified_amount)}<div class="${toJustify > 0 ? 'text-danger fw-semibold' : 'text-success'}">Reste: ${formatCurrency(toJustify)}</div></td>
          <td class="fw-semibold">${formatCurrency(exp.remaining_amount)}</td>
          <td><span class="badge ${isOverrun ? 'bg-danger' : (exp.status === 'Validé' ? 'bg-success-subtle text-success border' : 'bg-warning-subtle text-warning-emphasis border')}">${escHtml(exp.status)}</span></td>
          <td class="text-end text-nowrap">
            ${hasAnyPermission('expenses.manage') ? `<button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" title="Suivi montants / Décaisser" onclick="openExpenseModal(${exp.id}, 'edit')"><i class="bi bi-pencil-square"></i></button>` : ''}
            ${hasAnyPermission('expenses.validate') ? `<button type="button" class="btn btn-sm btn-outline-success py-0 px-2" title="Contrôler & Valider" onclick="openExpenseModal(${exp.id}, 'validate')"><i class="bi bi-check2-circle"></i></button>` : ''}
            <button type="button" class="btn btn-sm btn-light border py-0 px-2" title="Justificatifs & Historique (${exp.attachments?.length || 0})" onclick="openExpenseModal(${exp.id}, 'detail')"><i class="bi bi-paperclip"></i> ${exp.attachments?.length || 0}</button>
          </td>
        </tr>`;
    }).join('');
  },

  renderInventoriesTable(inventories) {
    const tbody = document.getElementById('inventoriesTableBody');
    if (!tbody) return;
    if (!inventories.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Aucun inventaire physique enregistré.</td></tr>';
      return;
    }

    tbody.innerHTML = inventories.map(inv => {
      const variances = (inv.items || []).filter(item => Number(item.variance) !== 0).length;
      const isPending = inv.status === 'En attente de validation';
      return `
        <tr>
          <td class="fw-bold text-primary font-monospace">${escHtml(inv.reference)}</td>
          <td>${formatDateShort(inv.inventory_date)}</td>
          <td><span class="badge bg-light text-dark border">${escHtml(inv.scope)}</span></td>
          <td>${inv.items?.length || 0} article(s)</td>
          <td><span class="badge ${variances > 0 ? 'bg-warning-subtle text-warning-emphasis border' : 'bg-success-subtle text-success border'}">${variances} écart(s)</span></td>
          <td><span class="badge ${isPending ? 'bg-warning text-dark' : 'bg-success'}">${escHtml(inv.status)}</span></td>
          <td class="text-end text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 me-1" onclick="openInventoryDetailModal(${inv.id})">Détails</button>
            ${isPending && hasAnyPermission('inventory.validate') ? `<button type="button" class="btn btn-sm btn-success py-0 px-2" onclick="validateInventory(${inv.id})">Valider</button>` : ''}
          </td>
        </tr>`;
    }).join('');
  },

  async loadReport(query = '') {
    try {
      const response = await fetch(`${this.baseUrl}/reports${query ? `?${query}` : ''}`);
      if (!response.ok) return;
      const report = await response.json();
      AppState.lastReportPayload = report;
      AppState.currentSavedReports = report.saved_reports || [];

      const data = report.data || {};
      const cmp = data.comparison || {};
      const deltaBadge = (delta, inverse = false) => {
        const d = Number(delta || 0);
        if (d === 0) return '<span class="text-muted small">= vs période préc.</span>';
        const positiveGood = inverse ? d < 0 : d > 0;
        return `<span class="small ${positiveGood ? 'text-success' : 'text-warning'}">${d > 0 ? `+${d}` : d} vs période préc.</span>`;
      };

      const summary = document.getElementById('reportSummary');
      if (summary) {
        summary.innerHTML = `
          <div class="col-md-3">
            <div class="gp-card h-100 mb-0">
              <div class="text-muted small">Demandes de la période</div>
              <div class="fs-3 fw-bold">${data.totals?.requests ?? 0}</div>
              <div>${deltaBadge(cmp.requests?.delta)} • Délai moy: <strong>${data.average_processing_hours || 0} h</strong></div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="gp-card h-100 mb-0">
              <div class="text-muted small">Dépenses décaissées</div>
              <div class="fs-5 fw-bold">${formatCurrency(data.expenses?.disbursed)}</div>
              <div class="small text-danger">À justifier : ${formatCurrency(data.expenses?.unjustified)}</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="gp-card h-100 mb-0">
              <div class="text-muted small">Valorisation des stocks</div>
              <div class="fs-5 fw-bold">${formatCurrency(data.stock?.value)}</div>
              <div class="small text-muted">${data.stock?.low || 0} alerte(s) • ${data.stock?.movements || 0} mvt(s)</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="gp-card h-100 mb-0">
              <div class="text-muted small">Patrimoine & Parc auto</div>
              <div class="fs-5 fw-bold">${data.assets?.total || 0} biens • ${data.vehicles?.total || 0} véh.</div>
              <div class="small text-muted">${data.assets?.damaged || 0} endommagé(s) • ${data.vehicles?.alerts || 0} alerte(s) auto</div>
            </div>
          </div>`;
      }

      const savedTbody = document.getElementById('savedReportsTableBody');
      if (savedTbody) {
        const list = AppState.currentSavedReports;
        savedTbody.innerHTML = list.length
          ? list.map(sr => `
            <tr>
              <td class="fw-bold text-primary font-monospace">${escHtml(sr.reference)}</td>
              <td><span class="badge bg-light text-dark border">${escHtml(sr.type)}</span></td>
              <td class="fw-semibold">${escHtml(sr.title)}</td>
              <td class="small">${formatDateShort(sr.period_from)} → ${formatDateShort(sr.period_to)}</td>
              <td class="small">${escHtml(sr.generator?.name || 'Direction')}</td>
              <td class="small text-muted">${formatDateTime(sr.created_at)}</td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" onclick="openSavedReportPreview(${sr.id})"><i class="bi bi-printer"></i> Aperçu PDF</button>
              </td>
            </tr>`).join('')
          : '<tr><td colspan="7" class="text-center text-muted py-3">Aucun rapport archivé pour le moment.</td></tr>';
      }
    } catch (e) {
      console.warn('loadReport error:', e);
    }
  },

  async loadNotifications() {
    try {
      const response = await fetch(`${this.baseUrl}/notifications`);
      if (!response.ok) return;
      const result = await response.json();
      const notifications = result.data || [];
      const unreadCount = Number(result.unread_count ?? notifications.filter(n => !n.read_at).length);

      const countEl = document.getElementById('notifCountBadge');
      const dotEl = document.getElementById('topbarNotifDot');
      if (countEl) countEl.textContent = unreadCount;
      if (dotEl) dotEl.style.display = unreadCount > 0 ? 'block' : 'none';

      const dropdown = document.getElementById('notificationsDropdown');
      if (!dropdown) return;
      dropdown.querySelectorAll('.notification-item, .notification-empty-state').forEach(item => item.remove());
      if (!notifications.length) {
        dropdown.insertAdjacentHTML('beforeend', '<li class="p-3 text-center text-muted small notification-empty-state"><i class="bi bi-bell-slash me-1"></i> Aucune notification</li>');
        return;
      }
      notifications.slice(0, 12).forEach(notification => {
        const item = document.createElement('li');
        item.className = `notification-item p-2 border-bottom small ${notification.read_at ? 'text-muted' : 'bg-primary-subtle'}`;
        item.innerHTML = `
          <button type="button" class="btn text-start p-0 w-100">
            <strong class="d-block text-dark">${escHtml(notification.title)}</strong>
            <span>${escHtml(notification.message)}</span>
            <small class="d-block mt-1 text-muted">${formatDateTime(notification.created_at)}</small>
          </button>`;
        item.querySelector('button').addEventListener('click', async () => {
          if (!notification.read_at) {
            await fetch(`${this.baseUrl}/notifications/${notification.id}/read`, { method: 'PUT' });
          }
          await this.loadNotifications();
          if (notification.link && notification.link.startsWith('#')) {
            window.location.hash = notification.link.slice(1);
          }
        });
        dropdown.appendChild(item);
      });
    } catch (e) {
      console.warn('loadNotifications error:', e);
    }
  },

  async loadMyLoginHistory() {
    try {
      const res = await fetch(`${this.baseUrl}/auth/login-history`);
      if (!res.ok) return;
      const json = await res.json();
      const tbody = document.getElementById('myLoginHistoryTableBody');
      if (!tbody) return;
      const logs = json.data || [];
      tbody.innerHTML = logs.length
        ? logs.map(l => `<tr>
            <td class="small text-nowrap">${formatDateTime(l.created_at)}</td>
            <td><span class="badge ${l.action === 'auth.login' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'}">${l.action === 'auth.login' ? 'Connexion' : 'Déconnexion'}</span></td>
            <td class="font-monospace small">${escHtml(l.ip_address || '127.0.0.1')}</td>
            <td class="small text-muted">${escHtml(l.description || '')}</td>
          </tr>`).join('')
        : '<tr><td colspan="4" class="text-center text-muted py-3">Aucune connexion récente.</td></tr>';
    } catch (e) {
      console.warn('loadMyLoginHistory error:', e);
    }
  },

  async loadUsers() {
    if (!AppState.isSuperAdmin) return;
    try {
      const res = await fetch(`${this.baseUrl}/users`);
      if (!res.ok) return;
      const json = await res.json();
      if (!json.success || !json.data) return;

      const users = json.data;
      AppState.currentUsers = users;

      // Alimenter les listes de délégation dans Paramètres
      const del1 = document.getElementById('delegationDelegatorSelect');
      const del2 = document.getElementById('delegationDelegateSelect');
      if (del1 && del2) {
        const managers = users.filter(u => u.is_active && ['admin', 'manager'].includes(u.role));
        const activeUsers = users.filter(u => u.is_active);
        del1.innerHTML = managers.map(u => `<option value="${u.id}">${escHtml(u.name)} (${escHtml(u.service || u.role)})</option>`).join('');
        del2.innerHTML = activeUsers.map(u => `<option value="${u.id}">${escHtml(u.name)} (${escHtml(u.service || u.role)})</option>`).join('');
      }

      const tbody = document.getElementById('usersTableBody');
      if (!tbody) return;
      if (!users.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Aucun utilisateur enregistré.</td></tr>';
        return;
      }

      tbody.innerHTML = users.map(u => {
        const initials = (u.name || 'U').split(' ').filter(Boolean).map(n => n[0]).join('').substring(0, 2).toUpperCase();
        const roleLabel = u.role === 'admin' ? 'Administrateur' : (u.role === 'manager' ? 'Responsable' : 'Collaborateur');
        const roleBadgeClass = u.role === 'admin' ? 'bg-primary-subtle text-primary border' : (u.role === 'manager' ? 'bg-warning-subtle text-warning-emphasis border' : 'bg-light text-dark border');
        const isCurrentSessionUser = AppState.currentUser && Number(AppState.currentUser.id) === Number(u.id);
        const permCount = (u.effective_permissions || []).length;

        return `
          <tr data-role="${roleLabel}" data-active="${u.is_active ? 'active' : 'inactive'}">
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width:34px; height:34px; font-size:0.8rem;">${escHtml(initials)}</div>
                <div>
                  <span class="fw-semibold text-dark">${escHtml(u.name)}</span>
                  ${isCurrentSessionUser ? ' <span class="badge bg-secondary-subtle text-secondary" style="font-size:0.68rem;">(Vous)</span>' : ''}
                  <small class="d-block font-monospace text-muted">${escHtml(u.matricule || '')}</small>
                </div>
              </div>
            </td>
            <td class="small">
              <div>${escHtml(u.email)}</div>
              <small class="text-muted">${escHtml([u.phone, u.office_location].filter(Boolean).join(' • ') || '—')}</small>
            </td>
            <td>
              <span class="badge ${roleBadgeClass}">${roleLabel}</span>
              <small class="d-block text-muted mt-1">${permCount} permission(s)${u.has_active_delegation ? ' • <span class="text-primary fw-semibold">Délégué</span>' : ''}</small>
            </td>
            <td>${escHtml(u.service || '-')}</td>
            <td><span class="badge-status ${u.is_active ? 'badge-in-stock' : 'badge-rejected'}"><span class="badge-status-dot"></span> ${u.is_active ? 'Actif' : 'Désactivé'}</span></td>
            <td class="small text-muted">${formatDateTime(u.last_login_at, 'Jamais')}</td>
            <td class="text-end text-nowrap">
              ${u.role !== 'admin' ? `<button type="button" class="btn btn-sm btn-outline-primary" title="Valider ou modifier le profil et le rôle" onclick="openValidateUserRoleModal(${u.id})"><i class="bi bi-shield-check me-1"></i>${u.role === 'collaborator' ? 'Valider le profil' : 'Modifier le rôle'}</button>` : `<span class="badge bg-secondary-subtle text-secondary border me-1" title="Le rôle Administrateur ne peut pas être modifié"><i class="bi bi-lock-fill me-1"></i>Rôle verrouillé</span>`}
              <button type="button" class="btn-gp-icon" title="Modifier l’utilisateur & ses permissions" onclick="openEditUserModal(${u.id})"><i class="bi bi-pencil-square"></i></button>
              <button type="button" class="btn-gp-icon" title="Réinitialiser le mot de passe" onclick="openResetUserPasswordModal(${u.id}, '${escHtml(u.name)}')"><i class="bi bi-key"></i></button>
              ${!isCurrentSessionUser ? `
                <button type="button" class="btn-gp-icon" title="${u.is_active ? 'Désactiver immédiatement' : 'Réactiver'} le compte" onclick="BackendApi.toggleUserActive(${u.id}, ${!u.is_active})">
                  <i class="bi ${u.is_active ? 'bi-person-slash text-warning' : 'bi-person-check text-success'}"></i>
                </button>
                <button type="button" class="btn-gp-icon text-danger" title="Supprimer" onclick="BackendApi.deleteUser(${u.id}, '${escHtml(u.name)}')"><i class="bi bi-trash"></i></button>
              ` : ''}
            </td>
          </tr>`;
      }).join('');
    } catch (e) {
      console.warn('loadUsers error:', e);
    }
  },

  async loadAdminSettingsAndRefs() {
    if (!AppState.isSuperAdmin) return;
    try {
      const res = await fetch(`${this.baseUrl}/operations/reference-data`);
      if (!res.ok) return;
      const json = await res.json();
      const d = json.data || {};

      // Remplir les champs de paramètres généraux
      const settingsMap = {};
      (d.settings || []).forEach(s => { settingsMap[s.key] = s.value; });
      const setVal = (id, key) => { const el = document.getElementById(id); if (el && settingsMap[key] !== undefined) el.value = settingsMap[key]; };
      const setCheck = (id, key) => { const el = document.getElementById(id); if (el && settingsMap[key] !== undefined) el.checked = settingsMap[key] === '1' || settingsMap[key] === 'true'; };

      setVal('settingOrgName', 'company_name');
      setVal('settingOrgAcronym', 'company_short_code');
      setVal('settingCurrency', 'currency');
      setVal('settingTimezone', 'timezone');
      setVal('settingOrgAddress', 'company_address');
      setVal('settingOrgContact', 'company_phone');
      setVal('settingDefaultStockThreshold', 'default_min_threshold');
      setVal('settingDefaultMaxThreshold', 'default_max_threshold');
      setVal('settingInventoryFrequency', 'inventory_frequency');
      setCheck('switchAlertEmail', 'notify_on_low_stock');
      setCheck('switchBlockZeroStock', 'block_zero_stock');
      setVal('settingValidationLevels', 'validation_levels');
      setVal('settingRequestSlaHours', 'sla_processing_hours');
      setCheck('switchNotifyOnSubmit', 'notify_on_request_submitted');
      setCheck('switchNotifyRequester', 'notify_on_request_decision');
      setCheck('switchNotifyMaintenance', 'notify_on_vehicle_alert');
      setCheck('switchNotifyExpenseOverrun', 'notify_on_expense_overrun');
      setVal('settingSessionTimeout', 'session_timeout_minutes');
      setVal('settingPasswordExpiry', 'password_expiry_days');

      const renderRefList = (containerId, rows, updateBaseEndpoint, subBuilder = null) => {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = (rows || []).map(row => `
          <div class="d-flex justify-content-between align-items-center p-2 bg-light border rounded small">
            <div>
              <strong class="${row.is_active ? 'text-dark' : 'text-decoration-line-through text-muted'}">${escHtml(row.name)}</strong>
              ${subBuilder ? `<small class="text-muted ms-2">${escHtml(subBuilder(row))}</small>` : ''}
            </div>
            <button type="button" class="btn btn-sm ${row.is_active ? 'btn-outline-secondary' : 'btn-outline-success'} py-0 px-2" onclick="toggleReferenceItemActive('${updateBaseEndpoint}/${row.id}', ${Boolean(row.is_active)})">
              ${row.is_active ? 'Désactiver' : 'Activer'}
            </button>
          </div>
        `).join('') || '<div class="text-muted small py-2">Aucun élément.</div>';
      };

      renderRefList('refListDepartments', d.departments, '/api/operations/reference-data/departments');
      renderRefList('refListRequestCategories', d.request_categories, '/api/operations/reference-data/request-categories');
      renderRefList('refListStockCategories', d.article_categories, '/api/categories');
      renderRefList('refListUnits', d.units, '/api/units', u => u.symbol || '');
      renderRefList('refListLocations', d.locations, '/api/operations/reference-data/locations');
      renderRefList('refListSuppliers', d.suppliers, '/api/operations/reference-data/suppliers', s => s.phone || '');

      const delBody = document.getElementById('delegationsTableBody');
      if (delBody) {
        const dels = d.delegations || [];
        delBody.innerHTML = dels.length
          ? dels.map(dl => `
            <tr>
              <td class="fw-semibold">${escHtml(dl.delegator?.name || '—')}</td>
              <td class="fw-semibold text-primary">${escHtml(dl.delegate?.name || '—')}</td>
              <td class="small">${formatDateShort(dl.starts_at)} → ${formatDateShort(dl.ends_at, 'Illimitée')}</td>
              <td class="small">${escHtml(dl.notes || dl.scope)}</td>
              <td><span class="badge ${dl.is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'}">${dl.is_active ? 'Active' : 'Révoquée'}</span></td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="toggleReferenceItemActive('/api/operations/reference-data/delegations/${dl.id}', ${Boolean(dl.is_active)})">
                  ${dl.is_active ? 'Révoquer' : 'Réactiver'}
                </button>
              </td>
            </tr>`).join('')
          : '<tr><td colspan="6" class="text-center text-muted py-3">Aucune délégation enregistrée.</td></tr>';
      }
    } catch (e) {
      console.warn('loadAdminSettingsAndRefs error:', e);
    }
  },

  async loadAuditLogs() {
    if (!AppState.isSuperAdmin) return;
    try {
      const params = new URLSearchParams();
      const mod = document.getElementById('filterAuditModule')?.value || '';
      const from = document.getElementById('filterAuditFrom')?.value || '';
      const to = document.getElementById('filterAuditTo')?.value || '';
      const search = document.getElementById('filterAuditSearch')?.value.trim() || '';
      if (mod) params.set('module', mod);
      if (from) params.set('from', from);
      if (to) params.set('to', to);
      if (search) params.set('search', search);

      const res = await fetch(`${this.baseUrl}/activity-logs?${params.toString()}`);
      if (!res.ok) return;
      const json = await res.json();
      const tbody = document.getElementById('activityLogsTableBody');
      if (!tbody) return;
      const logs = json.data || [];
      const formatAuditObject = (obj) => {
        if (!obj || typeof obj !== 'object') return '';
        return Object.entries(obj)
          .filter(([, val]) => val !== null && val !== undefined && val !== '')
          .map(([key, val]) => `<span class="me-2"><strong>${escHtml(key.replace(/_/g, ' '))} :</strong> ${escHtml(typeof val === 'object' ? Object.values(val).join(', ') : String(val))}</span>`)
          .join('');
      };
      const formatAuditAction = (action) => {
        const raw = String(action || '');
        return raw.replace(/[._]/g, ' ').replace(/\b\w/g, ch => ch.toUpperCase());
      };
      tbody.innerHTML = logs.length
        ? logs.map(l => {
          const beforeHtml = formatAuditObject(l.properties?.before);
          const afterHtml = formatAuditObject(l.properties?.after);
          const props = (beforeHtml || afterHtml)
            ? `<details class="mt-1"><summary class="text-primary small" style="cursor:pointer;">Voir le détail des modifications</summary><div class="bg-light border rounded p-2 mt-1 small">${beforeHtml ? `<div class="text-muted mb-1"><em>Avant :</em> ${beforeHtml}</div>` : ''}${afterHtml ? `<div><em>Après :</em> ${afterHtml}</div>` : ''}</div></details>`
            : '';
          return `
            <tr>
              <td class="small text-nowrap">${formatDateTime(l.created_at)}</td>
              <td class="small"><strong>${escHtml(l.user?.name || 'Système')}</strong><div class="text-muted small">${escHtml(l.ip_address || '')}</div></td>
              <td><span class="badge bg-light text-dark border">${escHtml(formatAuditAction(l.action))}</span></td>
              <td class="small">${escHtml(l.description || '')}${props}</td>
            </tr>`;
        }).join('')
        : '<tr><td colspan="4" class="text-center text-muted py-4">Aucune entrée d’audit pour ces critères.</td></tr>';
    } catch (e) {
      console.warn('loadAuditLogs error:', e);
    }
  },

  async loadBackups() {
    if (!AppState.isSuperAdmin) return;
    try {
      const res = await fetch(`${this.baseUrl}/backups`);
      if (!res.ok) return;
      const json = await res.json();
      const tbody = document.getElementById('backupsTableBody');
      if (!tbody) return;
      const list = json.data || [];
      tbody.innerHTML = list.length
        ? list.map(b => `
          <tr>
            <td class="fw-bold text-primary">${escHtml(b.reference)}</td>
            <td class="small">${escHtml(b.filename)}</td>
            <td class="small">${Math.max(1, Math.round(Number(b.size_bytes || 0) / 1024))} Ko</td>
            <td class="small">${b.records_count} élément(s)</td>
            <td class="small">${escHtml(b.creator?.name || 'Admin')}</td>
            <td class="small text-nowrap">${formatDateTime(b.created_at)}</td>
            <td class="text-end text-nowrap">
              <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 me-1" onclick="downloadAuthenticatedFile('/api/backups/${b.id}/download', '${escHtml(b.filename)}')"><i class="bi bi-download"></i></button>
              <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="restoreExistingBackup(${b.id}, '${escHtml(b.reference)}')"><i class="bi bi-arrow-counterclockwise"></i> Restaurer</button>
            </td>
          </tr>`).join('')
        : '<tr><td colspan="7" class="text-center text-muted py-3">Aucune sauvegarde générée pour le moment.</td></tr>';
    } catch (e) {
      console.warn('loadBackups error:', e);
    }
  },

  async deleteUser(userId, userName) {
    if (AppState.currentUser && Number(AppState.currentUser.id) === Number(userId)) {
      showToast('Vous ne pouvez pas supprimer votre propre compte connecté.', 'warning');
      return;
    }
    if (!confirm(`Confirmez-vous la suppression du compte "${userName}" ?`)) return;
    try {
      const res = await fetch(`${this.baseUrl}/users/${userId}`, { method: 'DELETE' });
      const json = await res.json();
      showToast(json.message || 'Utilisateur supprimé.', res.ok && json.success ? 'success' : 'danger');
      if (res.ok && json.success) await this.loadUsers();
    } catch (e) {
      showToast('Impossible de supprimer ce compte pour le moment.', 'danger');
    }
  },

  async toggleUserActive(userId, isActive) {
    const response = await fetch(`${this.baseUrl}/users/${userId}/active`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ is_active: isActive })
    });
    const data = await response.json();
    showToast(data.message || 'Compte mis à jour.', response.ok ? 'success' : 'danger');
    if (response.ok) await this.loadUsers();
  },

  async loadArticles() {
    try {
      const res = await fetch(`${this.baseUrl}/articles`);
      if (!res.ok) return;
      const json = await res.json();
      if (!json.success || !json.data) return;

      const articles = json.data;
      AppState.currentArticles = articles;
      populateRelationSelectors(AppState.currentRequests || [], articles, AppState.currentAssignees || []);

      const tbody = document.getElementById('articlesStockTableBody');
      if (tbody) {
        if (!articles.length) {
          tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted"><i class="bi bi-box me-2"></i>Aucun article en stock.</td></tr>';
        } else {
          tbody.innerHTML = articles.map(art => {
            let statusKey = 'dispo';
            let badgeHtml = '<span class="badge-status badge-in-stock"><span class="badge-status-dot"></span> En stock</span>';
            let qtyClass = '';

            if (art.quantity === 0) {
              statusKey = 'rupture';
              badgeHtml = '<span class="badge-status badge-rejected"><span class="badge-status-dot"></span> Rupture</span>';
              qtyClass = 'text-danger';
            } else if (art.quantity <= art.threshold) {
              statusKey = 'faible';
              badgeHtml = '<span class="badge-status badge-low-stock"><span class="badge-status-dot"></span> Stock faible</span>';
              qtyClass = 'text-warning';
            }

            return `
              <tr data-cat="${escHtml(art.category)}" data-status="${statusKey}">
                <td class="text-muted fw-semibold font-monospace">${escHtml(art.code)}</td>
                <td>
                  <span class="fw-semibold text-dark">${escHtml(art.name)}</span>
                  ${art.supplier ? `<small class="d-block text-muted">Fournisseur : ${escHtml(art.supplier)}</small>` : ''}
                </td>
                <td>${escHtml(art.category)}</td>
                <td class="small text-muted">${escHtml(art.location || 'Magasin Central')}</td>
                <td>${escHtml(art.unit)}</td>
                <td class="small">${art.unit_cost ? formatCurrency(art.unit_cost) : '—'}</td>
                <td class="fw-bold ${qtyClass}">${art.quantity}</td>
                <td class="text-muted small">${art.threshold}${art.maximum_threshold ? ` / ${art.maximum_threshold}` : ''}</td>
                <td>${badgeHtml}</td>
                <td class="text-end text-nowrap">
                  <button class="btn-gp-icon admin-only-feature" type="button" title="Modifier la fiche" onclick="openEditArticle(${art.id})"><i class="bi bi-pencil-square"></i></button>
                  <button class="btn-gp-icon text-danger superadmin-only-feature" type="button" title="Supprimer l’article" onclick="deleteArticle(${art.id}, '${escHtml(art.name)}')"><i class="bi bi-trash"></i></button>
                  <button class="btn btn-sm btn-outline-primary collaborator-only-feature py-0 px-2" type="button" onclick="preselectArticleForRequest(${art.id})"><i class="bi bi-plus-lg me-1"></i> Demander</button>
                </td>
              </tr>`;
          }).join('');
        }
      }

      const mvtSelect = document.getElementById('mvtArticle');
      if (mvtSelect) {
        const prev = mvtSelect.value;
        mvtSelect.innerHTML = '<option value="">Sélectionner un article du stock...</option>' + articles.map(art => `<option value="${art.id}">${escHtml(art.code)} - ${escHtml(art.name)} (${art.quantity} en stock)</option>`).join('');
        if ([...mvtSelect.options].some(o => o.value === prev)) mvtSelect.value = prev;
      }

      this.renderSelectableArticles(articles);

      const quickCatalog = document.getElementById('collabQuickCatalogList');
      if (quickCatalog) {
        const availableArticles = articles.filter(a => a.quantity > 0).slice(0, 5);
        quickCatalog.innerHTML = availableArticles.length
          ? availableArticles.map(art => `
            <div class="supply-quick-card">
              <div class="d-flex align-items-center gap-3">
                <div class="p-2 rounded bg-light text-primary"><i class="bi bi-box-seam fs-5"></i></div>
                <div>
                  <div class="fw-semibold text-dark" style="font-size: 0.885rem;">${escHtml(art.name)}</div>
                  <small class="${art.quantity <= art.threshold ? 'text-warning' : 'text-success'} fw-medium">${art.quantity} disponibles (${escHtml(art.unit)})</small>
                </div>
              </div>
              <button class="btn btn-sm btn-outline-primary" type="button" onclick="preselectArticleForRequest(${art.id})"><i class="bi bi-plus-lg"></i> Demander</button>
            </div>`).join('')
          : '<div class="p-3 text-center text-muted small bg-light rounded">Aucun article disponible pour le moment.</div>';
      }

      const criticalList = document.getElementById('supervisorCriticalStockList');
      const criticalBadge = document.getElementById('criticalStockBadge');
      if (criticalList) {
        const criticalArticles = articles.filter(a => a.quantity <= a.threshold);
        if (criticalBadge) criticalBadge.textContent = `${criticalArticles.length} alerte${criticalArticles.length > 1 ? 's' : ''}`;
        criticalList.innerHTML = criticalArticles.length
          ? criticalArticles.slice(0, 4).map(art => `
            <div class="p-2 rounded bg-light border d-flex align-items-center justify-content-between">
              <div>
                <strong class="text-dark d-block" style="font-size: 0.85rem;">${escHtml(art.code)} — ${escHtml(art.name)}</strong>
                <small class="text-danger fw-semibold">${art.quantity} ${escHtml(art.unit)} restant(s) (Seuil: ${art.threshold})</small>
              </div>
              <button class="btn btn-sm btn-outline-warning py-0 px-2" data-bs-toggle="modal" data-bs-target="#modalNewMovement">Approvisionner</button>
            </div>`).join('')
          : '<div class="p-3 text-center text-muted small bg-light rounded"><i class="bi bi-check-circle text-success me-1"></i> Aucun stock critique.</div>';
      }

      applyRoleVisibility(AppState.currentRole);
    } catch (e) {
      console.warn('loadArticles error:', e);
    }
  },

  renderSelectableArticles(articles) {
    const tbody = document.getElementById('selectableArticlesTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!articles.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Aucun article au catalogue.</td></tr>';
      return;
    }

    articles.forEach(art => {
      let badgeBg = 'bg-success-subtle text-success';
      if (art.quantity === 0) badgeBg = 'bg-danger-subtle text-danger';
      else if (art.quantity <= art.threshold) badgeBg = 'bg-warning-subtle text-warning-emphasis';

      const tr = document.createElement('tr');
      tr.className = 'selectable-article-row';
      tr.setAttribute('data-article-id', art.code);
      tr.setAttribute('data-article-db-id', art.id);
      tr.setAttribute('data-article-name', art.name);
      tr.setAttribute('data-article-category', art.category);
      tr.setAttribute('data-article-stock', art.quantity);

      tr.innerHTML = `
        <td><input type="checkbox" class="form-check-input article-select-checkbox" ${art.quantity === 0 ? 'disabled' : ''}></td>
        <td><span class="fw-semibold text-dark">${escHtml(art.name)}</span> <small class="text-muted">(${escHtml(art.code)})</small></td>
        <td>${escHtml(art.category)}</td>
        <td class="text-center"><span class="badge ${badgeBg}">${art.quantity} ${escHtml(art.unit)}</span></td>
        <td>
          <div class="input-group input-group-sm">
            <button class="btn btn-outline-secondary qty-btn-minus" type="button">-</button>
            <input type="number" class="form-control text-center qty-input" value="1" min="1" max="${Math.max(1, art.quantity)}" ${art.quantity === 0 ? 'disabled' : ''}>
            <button class="btn btn-outline-secondary qty-btn-plus" type="button">+</button>
          </div>
        </td>`;
      tbody.appendChild(tr);
      bindSelectableArticleRow(tr);
    });
  },

  async loadRequests() {
    try {
      const res = await fetch(`${this.baseUrl}/requests`);
      if (!res.ok) return;
      const json = await res.json();
      if (!json.success || !json.data) return;

      const requests = json.data;
      AppState.currentRequests = requests;
      populateRelationSelectors(requests, AppState.currentArticles || [], AppState.currentAssignees || []);

      const pending = requests.filter(r => PENDING_REQUEST_STATUSES.includes(r.status));
      const pendingCount = AppState.canManageOps
        ? pending.length
        : requests.filter(r => PENDING_REQUEST_STATUSES.includes(r.status) && Number(r.user_id) === Number(AppState.currentUser?.id)).length;

      const profCount = document.getElementById('profileCardDemandesCount');
      if (profCount) {
        const myCount = AppState.currentUser ? requests.filter(r => Number(r.user_id) === Number(AppState.currentUser.id)).length : requests.length;
        profCount.textContent = `${myCount} demande${myCount > 1 ? 's' : ''}`;
      }

      const sidebarBadge = document.getElementById('sidebarRequestsBadge');
      if (sidebarBadge) sidebarBadge.textContent = pendingCount;

      // Table superviseur
      const supTbody = document.getElementById('supervisorPendingRequestsTableBody');
      if (supTbody) {
        supTbody.innerHTML = pending.length
          ? pending.slice(0, 8).map(r => `
            <tr>
              <td class="request-reference text-primary font-monospace" title="${escHtml(r.reference)}">${escHtml(compactRequestReference(r.reference))}</td>
              <td><span class="fw-medium text-dark">${escHtml(r.user?.name || 'Collaborateur')}</span> <small class="text-muted">(${escHtml(r.service)})</small></td>
              <td class="small"><strong>${escHtml(r.category || '')}</strong><div class="text-muted">${escHtml(r.motif)}</div></td>
              <td>${requestStatusBadge(r.status)}</td>
              <td class="text-end text-nowrap">
                <button class="btn btn-sm btn-success py-0 px-2 me-1" type="button" onclick="approveRequest('${escHtml(r.reference)}')"><i class="bi bi-check-lg"></i> Valider</button>
                <button class="btn btn-sm btn-outline-primary py-0 px-2" type="button" onclick="openRequestDetail('${escHtml(r.reference)}')">Instruire</button>
              </td>
            </tr>`).join('')
          : '<tr><td colspan="5" class="text-center text-muted py-3">Aucune demande en attente de validation.</td></tr>';
      }

      // Table collaborateur
      const collabTbody = document.getElementById('collaboratorRecentRequestsTableBody');
      if (collabTbody) {
        const myReqs = AppState.currentUser
          ? requests.filter(r => Number(r.user_id) === Number(AppState.currentUser.id))
          : requests;
        collabTbody.innerHTML = myReqs.length
          ? myReqs.slice(0, 6).map(r => `
            <tr>
              <td class="request-reference text-primary font-monospace" title="${escHtml(r.reference)}">${escHtml(compactRequestReference(r.reference))}</td>
              <td>${formatDateShort(r.created_at)}</td>
              <td class="small"><strong>${escHtml(r.motif)}</strong><div class="text-muted">${escHtml(r.category || '')}</div></td>
              <td>${requestStatusBadge(r.status)}</td>
              <td class="text-end text-nowrap">
                ${AUTHORIZED_REQUEST_STATUSES.includes(r.status) ? `<button class="btn btn-sm btn-outline-secondary py-0 px-2 me-1" type="button" onclick="openPdfModal('${escHtml(r.reference)}')">Bon PDF</button>` : ''}
                ${AppState.currentUser?.role === 'collaborator' && PENDING_REQUEST_STATUSES.includes(r.status) ? `<button class="btn btn-sm btn-outline-secondary py-0 px-2 me-1" type="button" onclick="startEditRequest('${escHtml(r.reference)}')"><i class="bi bi-pencil-square"></i> Modifier</button>` : ''}
                <button class="btn btn-sm btn-outline-primary py-0 px-2" type="button" onclick="openRequestDetail('${escHtml(r.reference)}')">Suivre</button>
              </td>
            </tr>`).join('')
          : '<tr><td colspan="5" class="text-center text-muted py-3">Vous n’avez encore soumis aucune demande.</td></tr>';
      }

      // Table complète Toutes les demandes
      const allTbody = document.getElementById('allRequestsTableBody');
      if (allTbody) {
        allTbody.innerHTML = requests.length
          ? requests.map(r => `
            <tr data-status="${escHtml(r.status)}" data-category="${escHtml(r.category || '')}" data-urgency="${escHtml(r.urgency || 'Normale')}">
              <td class="request-reference text-primary font-monospace" title="${escHtml(r.reference)}">${escHtml(compactRequestReference(r.reference))}</td>
              <td>
                <div class="fw-medium text-dark">${escHtml(r.user?.name || 'Collaborateur')}</div>
                <small class="text-muted">${escHtml(r.service || '')}</small>
              </td>
              <td class="small">
                <span class="badge bg-light text-dark border">${escHtml(r.category || 'Général')}</span>
                <div class="text-muted text-truncate" style="max-width:220px;">${escHtml(r.motif)}</div>
              </td>
              <td><span class="badge ${['Urgente', 'Critique'].includes(r.urgency) ? 'bg-danger-subtle text-danger' : 'bg-light text-secondary border'}">${escHtml(r.urgency || 'Normale')}</span></td>
              <td class="small">${formatDateShort(r.created_at)}<div class="text-muted">Éch: ${formatDateShort(r.date_needed)}</div></td>
              <td class="small">${r.items?.length ? `${r.items.length} réf.` : 'Prestation'}</td>
              <td class="small">${escHtml(r.assignee?.name || '—')}</td>
              <td>${requestStatusBadge(r.status)}</td>
              <td class="text-end text-nowrap">
                ${AppState.currentUser?.role === 'collaborator' && AppState.currentUser && Number(r.user_id) === Number(AppState.currentUser.id) && PENDING_REQUEST_STATUSES.includes(r.status) ? `<button class="btn btn-sm btn-outline-secondary py-0 px-2 me-1" type="button" onclick="startEditRequest('${escHtml(r.reference)}')"><i class="bi bi-pencil-square"></i> Modifier</button>` : ''}
                <button class="btn btn-sm btn-outline-primary py-0 px-2" type="button" onclick="openRequestDetail('${escHtml(r.reference)}')">Consulter</button>
              </td>
            </tr>`).join('')
          : '<tr><td colspan="9" class="text-center text-muted py-4">Aucune demande enregistrée.</td></tr>';
      }
    } catch (e) {
      console.warn('loadRequests error:', e);
    }
  },

  async loadMovements() {
    if (!AppState.canManageOps) return;
    try {
      const res = await fetch(`${this.baseUrl}/movements`);
      if (!res.ok) return;
      const json = await res.json();
      if (!json.success || !json.data) return;

      const movements = json.data;
      AppState.currentMovements = movements;
      const tbody = document.getElementById('stockMovementsTableBody');
      if (!tbody) return;

      if (!movements.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Aucun mouvement de stock enregistré.</td></tr>';
        return;
      }

      const sourceLabels = {
        initial_stock: 'Stock initial',
        manual_adjustment: 'Saisie gestionnaire',
        inventory_adjustment: 'Ajustement inventaire',
        request_approval: 'Validation demande',
        request_return: 'Restitution emprunt'
      };

      tbody.innerHTML = movements.map(m => {
        const isPositive = ['Entrée', 'Retour'].includes(m.type);
        const isTransfer = m.type === 'Transfert';
        const sign = isTransfer ? '↔ ' : (isPositive ? '+' : '-');
        const colorClass = isTransfer ? 'text-primary' : (isPositive ? 'text-success' : 'text-danger');
        const badgeClass = isTransfer ? 'bg-info-subtle text-info-emphasis' : (isPositive ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger');
        const dateIso = m.created_at ? String(m.created_at).slice(0, 10) : '';
        const routeInfo = [m.from_location || m.from_service, m.to_location || m.to_service].filter(Boolean).join(' → ');

        return `
          <tr data-type="${escHtml(m.type)}" data-date="${escHtml(dateIso)}">
            <td class="text-muted small text-nowrap">${formatDateTime(m.created_at)}</td>
            <td><strong>${escHtml(m.article?.code || '')}</strong> ${escHtml(m.article?.name || 'Article')}</td>
            <td><span class="badge ${badgeClass} border">${escHtml(m.type)}</span>${m.is_emergency ? ' <span class="badge bg-danger">Urgence</span>' : ''}</td>
            <td class="fw-bold ${colorClass}">${sign}${m.quantity}</td>
            <td class="small">
              <span class="badge bg-light text-secondary border">${escHtml(sourceLabels[m.source] || m.source || 'Mouvement')}</span>
              ${m.material_request?.reference ? `<div class="font-monospace text-primary">${escHtml(m.material_request.reference)}</div>` : ''}
            </td>
            <td class="small text-muted">${escHtml(routeInfo || '—')}</td>
            <td class="small">${escHtml(m.user?.name || 'Système')}</td>
            <td class="small">${escHtml(m.reason || m.justification || '—')}</td>
          </tr>`;
      }).join('');
    } catch (e) {
      console.warn('loadMovements error:', e);
    }
  },

  async loadDocuments() {
    if (!AppState.canManageOps) return;
    try {
      const res = await fetch(`${this.baseUrl}/documents`);
      if (!res.ok) return;
      const json = await res.json();
      if (!json.success || !json.data) return;

      const docs = json.data;
      AppState.currentDocuments = docs;
      const tbody = document.getElementById('documentsTableBody');
      if (!tbody) return;

      if (!docs.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Aucun document administratif archivé.</td></tr>';
        return;
      }

      tbody.innerHTML = docs.map(doc => {
        const iconMap = {
          PDF: 'bi-file-earmark-pdf text-danger',
          Word: 'bi-file-earmark-word text-primary',
          Excel: 'bi-file-earmark-excel text-success',
          Image: 'bi-file-earmark-image text-info'
        };
        const icon = iconMap[doc.type] || 'bi-file-earmark-text text-secondary';
        return `
          <tr data-cat="${escHtml(doc.category)}" data-type="${escHtml(doc.type)}" data-service="${escHtml(doc.service)}">
            <td class="font-monospace small fw-semibold text-primary">${escHtml(doc.reference || `DOC-${doc.id}`)}</td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <i class="bi ${icon} fs-5"></i>
                <div>
                  <span class="fw-semibold text-dark">${escHtml(doc.title)}</span>
                  ${doc.file_size ? `<small class="d-block text-muted">${escHtml(doc.file_size)}</small>` : ''}
                </div>
              </div>
            </td>
            <td>${escHtml(doc.category)}</td>
            <td><span class="badge bg-light text-secondary border">${escHtml(doc.type)}</span></td>
            <td>${escHtml(doc.service)}</td>
            <td class="text-muted small">${formatDateShort(doc.created_at)}</td>
            <td class="text-end text-nowrap">
              <button class="btn-gp-icon" type="button" title="Télécharger / Aperçu" onclick="${doc.material_request?.reference && String(doc.reference || '').startsWith('BON-') ? `openPdfModal('${escHtml(doc.material_request.reference)}')` : `downloadDocument(${doc.id}, '${escHtml(doc.original_name || doc.title)}')`}"><i class="bi bi-download"></i></button>
              <button class="btn-gp-icon text-danger" type="button" title="Supprimer" onclick="deleteDocument(${doc.id}, '${escHtml(doc.title)}')"><i class="bi bi-trash"></i></button>
            </td>
          </tr>`;
      }).join('');
    } catch (e) {
      console.warn('loadDocuments error:', e);
    }
  },

  async loadDashboard() {
    try {
      const res = await fetch(`${this.baseUrl}/dashboard`);
      if (!res.ok) return;
      const json = await res.json();
      if (!json.success || !json.kpis) return;

      const kpis = json.kpis;
      const setTxt = (id, v) => { const el = document.getElementById(id); if (el && v !== undefined) el.textContent = v; };

      setTxt('kpiTotalArticles', kpis.total_articles);
      setTxt('kpiLowStock', kpis.low_stock_count);
      setTxt('kpiPendingRequests', kpis.pending_requests);
      setTxt('kpiApprovedRequests', kpis.approved_requests);
      if (kpis.stock_value !== undefined) setTxt('kpiStockTotalValue', formatCurrency(kpis.stock_value));
      setTxt('kpiOutOfStockCount', kpis.out_of_stock_count);

      if (kpis.requests_in_progress !== undefined) {
        setTxt('kpiCollabInProgress', kpis.requests_in_progress);
        const collabBadge = document.getElementById('kpiCollabInProgressBadge');
        if (collabBadge) {
          const val = Number(kpis.requests_in_progress || 0);
          collabBadge.style.display = val > 0 ? 'inline-block' : 'none';
          collabBadge.textContent = `${val} en cours`;
        }
      }
      setTxt('kpiCollabApproved', kpis.requests_approved);
      setTxt('kpiCollabReceivedMonth', kpis.articles_received_month);
      if (kpis.latest_request !== undefined) {
        setTxt('kpiCollabLatestRef', kpis.latest_request || '-');
        const latestStatus = document.getElementById('kpiCollabLatestStatus');
        if (latestStatus) {
          latestStatus.style.display = kpis.latest_request && kpis.latest_request !== '-' ? 'inline-block' : 'none';
          latestStatus.textContent = kpis.latest_status || 'Enregistrée';
        }
      }

      if (typeof initDashboardCharts === 'function' && json.role === 'admin') {
        initDashboardCharts({
          categories: json.categories_distribution || [],
          totalArticles: Number(kpis.total_articles || 0),
          monthlyEvolution: json.monthly_evolution || null
        });
      }
    } catch (e) {
      console.warn('loadDashboard error:', e);
    }
  },

  async approveRequest(requestId, extraPayload = {}) {
    try {
      const res = await fetch(`${this.baseUrl}/requests/${encodeURIComponent(requestId)}/approve`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(extraPayload)
      });
      const data = await res.json();
      if (res.ok && data.success) {
        showToast(data.message || `Demande ${requestId} validée !`, 'success');
        await this.refreshAll({ showIndicator: true, silent: true });
        if (AppState.currentView === 'request-detail') await openRequestDetail(requestId);
        return true;
      }
      showToast(data.message || 'Erreur lors de la validation.', 'danger');
      return false;
    } catch (e) {
      showToast('Impossible de valider la demande.', 'danger');
      return false;
    }
  },

  async rejectRequest(requestId, reason = '') {
    return this.transitionRequest(requestId, 'reject', { reason });
  },

  async transitionRequest(requestId, action, payload = {}) {
    try {
      const res = await fetch(`${this.baseUrl}/requests/${encodeURIComponent(requestId)}/${action}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        const msg = Object.values(data.errors || {}).flat()[0] || data.message || 'Le changement de statut a échoué.';
        showToast(msg, 'danger');
        return false;
      }
      showToast(data.message || 'Statut de la demande mis à jour.', 'success');
      await this.refreshAll({ showIndicator: true, silent: true });
      await openRequestDetail(requestId);
      return true;
    } catch (e) {
      showToast('Impossible de mettre à jour la demande.', 'danger');
      return false;
    }
  },

  async returnRequest(requestId) {
    return this.transitionRequest(requestId, 'return');
  }
};
