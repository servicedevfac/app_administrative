/**
 * GESTION+ : Logique Applicative & Interactivité Complète
 * Maquette interactive, intuitive et fidèle au standard SaaS B2B
 */

// État global de l'application (Connecté à MySQL & Données Réelles)
const AppState = {
  currentView: 'dashboard',
  currentRole: 'admin', // 'collaborator' ou 'admin'
  currentUser: null,
  selectedArticlesForRequest: {},
  currentStep: 1,
  activeDetailTab: 'info'
};

// Initialisation au chargement du DOM
document.addEventListener('DOMContentLoaded', () => {
  setupNavigation();
  setupRoleSwitcher();
  setupStepper();
  setupDetailTabs();
  setupTableFilters();
  setupModalsAndActions();
  setupSearchGlobal();
  setupAuthAndSettingsHandlers();

  // Restaurer l'utilisateur depuis la session
  const storedUser = sessionStorage.getItem('gestion_user');
  if (storedUser) {
    try {
      applyUser(JSON.parse(storedUser));
    } catch (e) {}
  } else {
    setUserRole(AppState.currentRole, false);
  }

  // Restaurer ou initialiser la vue via l'URL hash
  handleRouteHash();
  window.addEventListener('hashchange', handleRouteHash);

  // Vérifier la connexion avec le Backend Laravel & MySQL
  if (typeof BackendApi !== 'undefined') {
    BackendApi.checkHealth();
  }
});

/* ==========================================================================
   1. Gestion de la Navigation & du Hash Routing
   ========================================================================== */

function applyUser(user) {
  if (!user) return;
  AppState.currentUser = user;
  const role = (user.role === 'admin' || user.role === 'Administrateur') ? 'admin' : 'collaborator';
  AppState.currentRole = role;

  const nameEl = document.getElementById('topbarUserName');
  const roleEl = document.getElementById('topbarUserRole');
  const avatarEl = document.getElementById('topbarUserAvatar');
  const activeRoleText = document.getElementById('topbarActiveRoleText');

  const initials = (user.name || 'AD').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
  if (nameEl) nameEl.textContent = user.name;
  if (roleEl) roleEl.textContent = role === 'admin' ? 'Administrateur' : (user.service || 'Collaborateur');
  if (avatarEl) avatarEl.textContent = initials;
  if (activeRoleText) activeRoleText.textContent = `Profil : ${role === 'admin' ? 'Administrateur' : 'Collaborateur'}`;

  // Dashboards
  const collabDashboard = document.getElementById('collaboratorDashboardView');
  const adminDashboard = document.getElementById('adminDashboardView');
  if (collabDashboard && adminDashboard) {
    if (role === 'admin') {
      collabDashboard.style.display = 'none';
      adminDashboard.style.display = 'block';
    } else {
      collabDashboard.style.display = 'block';
      adminDashboard.style.display = 'none';
    }
  }

  // Greetings
  const collabNameEl = document.getElementById('collabGreetingName');
  if (collabNameEl) collabNameEl.textContent = user.name;
  const collabSubEl = document.getElementById('collabGreetingSub');
  if (collabSubEl) collabSubEl.textContent = `Espace Collaborateur • Département ${user.service || 'Général'}`;

  // Stepper demandeur name
  const reqDemandeurInput = document.getElementById('reqDemandeurName');
  if (reqDemandeurInput) {
    reqDemandeurInput.value = `${user.name} (${user.matricule || user.service || 'Demandeur'})`;
  }

  // Profile view
  const profName = document.getElementById('profileCardName');
  const profEmail = document.getElementById('profileCardEmail');
  const profInName = document.getElementById('profileInputName');
  const profInEmail = document.getElementById('profileInputEmail');
  const profRole = document.getElementById('profileCardRole');
  const profService = document.getElementById('profileCardService');
  const profMatricule = document.getElementById('profileCardMatricule');
  const profAvatar = document.getElementById('profileCardAvatar');
  if (profName) profName.textContent = user.name;
  if (profEmail) profEmail.textContent = user.email;
  if (profInName) profInName.value = user.name;
  if (profInEmail) profInEmail.value = user.email;
  if (profRole) profRole.textContent = role === 'admin' ? 'Administrateur / Gestionnaire' : 'Collaborateur';
  if (profService) profService.textContent = user.service ? `Service ${user.service}` : 'Direction Générale';
  if (profMatricule) profMatricule.textContent = user.matricule || `EMP-${user.id || 1}`;
  if (profAvatar && user.avatar) profAvatar.src = user.avatar;

  applyRoleVisibility(role);
}

function setupNavigation() {
  // Navigation Sidebar
  document.querySelectorAll('[data-view-target]').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const targetView = link.getAttribute('data-view-target');
      navigateToView(targetView);
    });
  });

}

function handleRouteHash() {
  const hash = window.location.hash.replace('#', '');
  if (hash) {
    navigateToView(hash, false);
  } else {
    navigateToView('dashboard', false);
  }
}

function navigateToView(viewId, updateHash = true) {
  if (viewId === 'logout') {
    sessionStorage.removeItem('gestion_user');
    AppState.currentUser = null;
    showToast('Vous avez été déconnecté avec succès.', 'info');
    navigateToView('login', updateHash);
    return;
  }

  const isAuthView = ['login', 'register'].includes(viewId);
  const appContainer = document.getElementById('appMainContainer');
  const authContainer = document.getElementById('authLayoutContainer');

  if (isAuthView) {
    if (appContainer) appContainer.style.display = 'none';
    if (authContainer) {
      authContainer.classList.add('active-auth');
      authContainer.style.display = 'flex';
    }

    // Masquer tous les écrans auth
    document.querySelectorAll('.auth-view-screen').forEach(el => {
      el.style.display = 'none';
    });
    // Afficher l'écran auth spécifique
    const targetAuth = document.getElementById(`authView-${viewId}`);
    if (targetAuth) {
      targetAuth.style.display = 'block';
    }
  } else {
    if (authContainer) {
      authContainer.classList.remove('active-auth');
      authContainer.style.display = 'none';
    }
    if (appContainer) appContainer.style.display = 'flex';

    // Restriction d'accès : interdire documents et vues admin aux collaborateurs
    if (AppState.currentRole !== 'admin') {
      const adminRestrictedViews = ['documents', 'document-new', 'users', 'stock-movements'];
      if (adminRestrictedViews.includes(viewId)) {
        showToast('Accès restreint : cette rubrique est réservée aux administrateurs.', 'warning');
        viewId = 'dashboard';
      }
    }

    // Masquer toutes les vues internes
    document.querySelectorAll('.view-section').forEach(view => {
      view.classList.remove('active-view');
    });

    // Activer la vue cible
    const targetElement = document.getElementById(`view-${viewId}`);
    if (targetElement) {
      targetElement.classList.add('active-view');
      AppState.currentView = viewId;
    } else {
      document.getElementById('view-dashboard')?.classList.add('active-view');
      AppState.currentView = 'dashboard';
    }

    // Mettre à jour les liens de sidebar
    document.querySelectorAll('.nav-item-link').forEach(link => {
      if (link.getAttribute('data-view-target') === viewId) {
        link.classList.add('active');
      } else {
        link.classList.remove('active');
      }
    });

    // Redessiner les graphiques si on arrive sur le tableau de bord
    if (viewId === 'dashboard') {
      setTimeout(() => {
        if (typeof initDashboardCharts === 'function') {
          initDashboardCharts();
        }
      }, 50);
    }

    // Re-synchroniser les accès par rôle pour la vue affichée
    applyRoleVisibility(AppState.currentRole);
  }

  // Mise à jour de l'URL
  if (updateHash) {
    window.location.hash = viewId;
  }

  // Remonter en haut de page
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ==========================================================================
   2. Switcher de Profils (Collaborateur vs Administrateur)
   ========================================================================== */
function setupRoleSwitcher() {
  const switchBtns = document.querySelectorAll('.role-pill-btn');
  switchBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const role = btn.getAttribute('data-role');
      setUserRole(role);
    });
  });
}

function setUserRole(role, notify = true) {
  AppState.currentRole = role;
  const user = AppState.currentUser || (role === 'admin' 
    ? { name: 'Administrateur', role: 'Administrateur', service: 'Direction' } 
    : { name: 'Collaborateur', role: 'Collaborateur', service: 'Ressources Humaines' });

  // Mettre à jour la Navbar
  const nameEl = document.getElementById('topbarUserName');
  const roleEl = document.getElementById('topbarUserRole');
  const avatarEl = document.getElementById('topbarUserAvatar');
  const activeRoleText = document.getElementById('topbarActiveRoleText');

  if (nameEl) nameEl.textContent = user.name;
  if (roleEl) roleEl.textContent = user.role;
  if (avatarEl) {
    const initials = (user.name || 'AD').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
    avatarEl.textContent = initials;
  }
  if (activeRoleText) {
    activeRoleText.textContent = `Profil : ${user.role}`;
  }

  // Basculer l'affichage du Dashboard spécifique (Collaborateur vs Superviseur)
  const collabDashboard = document.getElementById('collaboratorDashboardView');
  const adminDashboard = document.getElementById('adminDashboardView');
  if (collabDashboard && adminDashboard) {
    if (role === 'admin') {
      collabDashboard.style.display = 'none';
      adminDashboard.style.display = 'block';
      if (typeof initDashboardCharts === 'function') {
        setTimeout(() => initDashboardCharts(), 60);
      }
    } else {
      collabDashboard.style.display = 'block';
      adminDashboard.style.display = 'none';
    }
  }

  // Basculer l'affichage des actions réservées à l'administrateur
  applyRoleVisibility(role);

  if (notify) {
    showToast(`Profil actif : ${user.name} (${user.role})`, 'info');
  }

  // Synchronisation dynamique immédiate des données et KPIs selon le profil
  if (typeof BackendApi !== 'undefined' && BackendApi.refreshAll) {
    BackendApi.refreshAll({ silent: true });
  }
}

function updateCollaboratorLiveTracker() {
  const card = document.getElementById('activeRequestTrackerCard');
  if (!card) return;

  const requests = AppState.currentRequests || [];
  const myRequests = AppState.currentUser 
    ? requests.filter(r => r.user_id == AppState.currentUser.id) 
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

  if (latest.status === 'En attente') {
    if (b1) b1.className = 'request-progress-bar done';
    if (b2) b2.className = 'request-progress-bar active';
    if (badgeEl) {
      badgeEl.className = 'badge bg-warning-subtle text-warning border';
      badgeEl.textContent = 'En attente d\'approbation';
    }
  } else if (latest.status === 'Approuvée') {
    if (b1) b1.className = 'request-progress-bar done';
    if (b2) b2.className = 'request-progress-bar done';
    if (b3) b3.className = 'request-progress-bar done';
    if (b4) b4.className = 'request-progress-bar done';
    if (badgeEl) {
      badgeEl.className = 'badge bg-success-subtle text-success border';
      badgeEl.textContent = 'Approuvée • Matériel disponible';
    }
  } else if (latest.status === 'Restituée') {
    if (b1) b1.className = 'request-progress-bar done';
    if (b2) b2.className = 'request-progress-bar done';
    if (b3) b3.className = 'request-progress-bar done';
    if (b4) b4.className = 'request-progress-bar done';
    if (badgeEl) {
      badgeEl.className = 'badge bg-secondary-subtle text-secondary border';
      badgeEl.textContent = 'Restituée (Clôturée)';
    }
  } else if (latest.status === 'Rejetée') {
    if (b1) b1.className = 'request-progress-bar done';
    if (badgeEl) {
      badgeEl.className = 'badge bg-danger-subtle text-danger border';
      badgeEl.textContent = 'Non accordée (Rejetée)';
    }
  }
}

function applyRoleVisibility(role = AppState.currentRole) {
  document.body.classList.remove('role-collaborator', 'role-admin');
  document.body.classList.add(`role-${role}`);

  const adminElements = document.querySelectorAll('.admin-only-feature');
  adminElements.forEach(el => {
    el.style.setProperty('display', role === 'admin' ? '' : 'none', 'important');
  });

  const collabElements = document.querySelectorAll('.collaborator-only-feature');
  collabElements.forEach(el => {
    el.style.setProperty('display', role === 'collaborator' ? '' : 'none', 'important');
  });
}

/* ==========================================================================
   3. Assistant Nouvelle Demande (Stepper 3 Étapes)
   ========================================================================== */
function setupStepper() {
  // Stepper buttons
  const btnNext1 = document.getElementById('btnStep1Next');
  const btnPrev2 = document.getElementById('btnStep2Prev');
  const btnNext2 = document.getElementById('btnStep2Next');
  const btnPrev3 = document.getElementById('btnStep3Prev');
  const btnSubmit = document.getElementById('btnSubmitRequest');

  if (btnNext1) {
    btnNext1.addEventListener('click', () => {
      // Vérifier si au moins 1 article est sélectionné
      const selectedCount = Object.keys(AppState.selectedArticlesForRequest).length;
      if (selectedCount === 0) {
        showToast('Veuillez cocher au moins un article pour votre demande.', 'warning');
        return;
      }
      goToStep(2);
    });
  }

  if (btnPrev2) btnPrev2.addEventListener('click', () => goToStep(1));
  if (btnNext2) {
    btnNext2.addEventListener('click', () => {
      const motif = document.getElementById('inputRequestMotif').value.trim();
      if (!motif) {
        showToast('Veuillez renseigner le motif de la demande.', 'warning');
        return;
      }
      buildStep3Recap();
      goToStep(3);
    });
  }

  if (btnPrev3) btnPrev3.addEventListener('click', () => goToStep(2));

  if (btnSubmit) {
    btnSubmit.addEventListener('click', async () => {
      const selectedKeys = Object.keys(AppState.selectedArticlesForRequest);
      if (selectedKeys.length === 0) {
        showToast('Veuillez sélectionner au moins un article.', 'warning');
        return;
      }
      const motif = document.getElementById('inputRequestMotif').value.trim() || 'Emprunt matériel interne';
      const typeOp = document.getElementById('selectRequestTypeOp')?.value || 'Emprunt temporaire';
      const dateNeeded = document.getElementById('inputRequestDateNeeded')?.value || new Date().toISOString().split('T')[0];
      const urgency = document.getElementById('selectRequestUrgency')?.value || 'Normale';

      const items = selectedKeys.map(k => ({
        article_id: AppState.selectedArticlesForRequest[k].dbId || 1,
        quantity: AppState.selectedArticlesForRequest[k].qty || 1
      }));

      try {
        const res = await fetch('/api/requests', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({
            user_id: AppState.currentUser?.id || 1,
            type_operation: typeOp,
            motif: motif,
            service: AppState.currentUser?.service || 'Général',
            date_needed: dateNeeded,
            urgency: urgency,
            items: items
          })
        });
        const result = await res.json();
        if (result.success) {
          showToast(`Demande ${result.data.reference} enregistrée avec succès dans MySQL !`, 'success');
          resetNewRequestForm();
          if (typeof BackendApi !== 'undefined') {
            await BackendApi.refreshAll({ showIndicator: true });
          }
          navigateToView('dashboard');
          return;
        }
      } catch (err) {
        console.warn('API submission failed, using local mode:', err);
      }

      // Fallback local
      const newRef = 'DEM-2025-00048';
      showToast(`Demande interne ${newRef} enregistrée et transmise au gestionnaire !`, 'success');
      resetNewRequestForm();
      navigateToView('dashboard');
    });
  }

  // Interactivité du panier d'articles
  document.querySelectorAll('.selectable-article-row').forEach(bindSelectableArticleRow);
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
  const articleStock = parseInt(row.getAttribute('data-article-stock'), 10);

  const updateRowState = () => {
    if (checkbox.checked) {
      row.classList.add('table-primary');
      const qty = parseInt(qtyInput.value, 10) || 1;
      AppState.selectedArticlesForRequest[articleId] = {
        id: articleId,
        dbId: articleDbId,
        name: articleName,
        category: articleCategory,
        stock: articleStock,
        qty: qty
      };
    } else {
      row.classList.remove('table-primary');
      delete AppState.selectedArticlesForRequest[articleId];
    }
    updateSelectedCounter();
  };

  if (checkbox) {
    checkbox.addEventListener('change', updateRowState);
  }

  if (btnMinus && qtyInput) {
    btnMinus.addEventListener('click', () => {
      let val = parseInt(qtyInput.value, 10) || 1;
      if (val > 1) {
        qtyInput.value = val - 1;
        if (checkbox.checked) updateRowState();
      }
    });
  }

  if (btnPlus && qtyInput) {
    btnPlus.addEventListener('click', () => {
      let val = parseInt(qtyInput.value, 10) || 1;
      if (val < articleStock) {
        qtyInput.value = val + 1;
        if (checkbox.checked) updateRowState();
      } else {
        showToast(`Quantité maximale disponible atteinte (${articleStock})`, 'warning');
      }
    });
  }

  if (qtyInput) {
    qtyInput.addEventListener('change', () => {
      let val = parseInt(qtyInput.value, 10) || 1;
      if (val < 1) val = 1;
      if (val > articleStock) val = articleStock;
      qtyInput.value = val;
      if (checkbox.checked) updateRowState();
    });
  }
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

  // Cacher tous les blocs d'étapes
  document.getElementById('stepContent1').style.display = stepNumber === 1 ? 'block' : 'none';
  document.getElementById('stepContent2').style.display = stepNumber === 2 ? 'block' : 'none';
  document.getElementById('stepContent3').style.display = stepNumber === 3 ? 'block' : 'none';

  // Mise à jour de l'en-tête du stepper
  for (let i = 1; i <= 3; i++) {
    const stepHeader = document.getElementById(`stepHeader${i}`);
    if (stepHeader) {
      stepHeader.classList.remove('active', 'completed');
      if (i === stepNumber) {
        stepHeader.classList.add('active');
      } else if (i < stepNumber) {
        stepHeader.classList.add('completed');
      }
    }
  }

  window.scrollTo({ top: 120, behavior: 'smooth' });
}

function buildStep3Recap() {
  const recapTableBody = document.getElementById('step3RecapTableBody');
  const motifRecap = document.getElementById('step3MotifRecap');
  const serviceRecap = document.getElementById('step3ServiceRecap');
  const dateRecap = document.getElementById('step3DateRecap');

  if (motifRecap) motifRecap.textContent = document.getElementById('inputRequestMotif').value || '-';
  if (serviceRecap) serviceRecap.textContent = document.getElementById('selectRequestService').value || '-';
  if (dateRecap) dateRecap.textContent = document.getElementById('inputRequestDateNeeded').value || 'Dès que possible';

  if (recapTableBody) {
    recapTableBody.innerHTML = '';
    const articles = Object.values(AppState.selectedArticlesForRequest);
    articles.forEach(art => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="fw-semibold">${art.name}</td>
        <td><span class="badge bg-light text-dark">${art.category}</span></td>
        <td class="text-center fw-bold text-primary">${art.qty}</td>
        <td class="text-end text-muted">${art.stock} en stock</td>
      `;
      recapTableBody.appendChild(tr);
    });
  }
}

function resetNewRequestForm() {
  AppState.selectedArticlesForRequest = {};
  document.querySelectorAll('.article-select-checkbox').forEach(cb => { cb.checked = false; });
  document.querySelectorAll('.selectable-article-row').forEach(row => { row.classList.remove('table-primary'); });
  document.querySelectorAll('.qty-input').forEach(input => { input.value = 1; });
  document.getElementById('inputRequestMotif').value = '';
  document.getElementById('inputRequestNotes').value = '';
  updateSelectedCounter();
  goToStep(1);
}

/* ==========================================================================
   4. Détails de la Demande : Onglets & Circuit de Validation
   ========================================================================== */
function setupDetailTabs() {
  const tabBtns = document.querySelectorAll('.tab-nav-btn');
  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      tabBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const targetTab = btn.getAttribute('data-detail-tab');
      document.querySelectorAll('.detail-tab-pane').forEach(pane => {
        pane.style.display = 'none';
      });

      const paneTarget = document.getElementById(`tabPane-${targetTab}`);
      if (paneTarget) {
        paneTarget.style.display = 'block';
      }
    });
  });
}

function approveRequest(requestId, triggerBtn = null) {
  if (!requestId) return;
  // Contrôle strict des rôles : Seul le gestionnaire de stock / administrateur peut approuver
  if (AppState.currentRole !== 'admin') {
    showToast('Accès refusé : Seul le Gestionnaire de stock / Administrateur a l\'autorité d\'approuver les demandes.', 'danger');
    return;
  }

  // 1. Mettre à jour le badge de statut
  const badge = document.getElementById('requestDetailStatusBadge');
  if (badge) {
    badge.className = 'badge-status badge-approved';
    badge.innerHTML = '<span class="badge-status-dot"></span> Approuvée (Bon émis)';
  }

  // 2. Mettre à jour l'étape sur la timeline
  const stepCurrent = document.getElementById('timelineStepCurrent');
  if (stepCurrent) {
    stepCurrent.className = 'timeline-step done';
    const marker = stepCurrent.querySelector('.timeline-marker');
    if (marker) marker.innerHTML = '<i class="bi bi-check"></i>';
    const title = stepCurrent.querySelector('.timeline-title');
    if (title) { title.className = 'timeline-title'; title.textContent = 'Validation effectuée'; }
  }

  const stepApproval = document.getElementById('timelineStepApproved');
  if (stepApproval) {
    stepApproval.className = 'timeline-step done';
    const marker = stepApproval.querySelector('.timeline-marker');
    if (marker) marker.innerHTML = '<i class="bi bi-check-lg"></i>';
    const title = stepApproval.querySelector('.timeline-title');
    if (title) { title.className = 'timeline-title text-success fw-bold'; title.textContent = 'Approuvée & Bon émis'; }
    const timeEl = stepApproval.querySelector('.timeline-time');
    const approverName = (AppState.currentUser && AppState.currentUser.name) ? AppState.currentUser.name : 'le Gestionnaire';
    if (timeEl) timeEl.textContent = 'Aujourd\'hui à ' + getFormattedTime() + ' • Visé par ' + approverName + ' (Bon officiel émis)';
  }

  // 3. Mettre à jour le bouton déclencheur dans le tableau du superviseur
  if (triggerBtn) {
    const parentTd = triggerBtn.closest('td');
    if (parentTd) {
      parentTd.innerHTML = `
        <button class="btn btn-sm btn-outline-success py-0 px-2 me-1" onclick="openPdfModal('${requestId}')">
          <i class="bi bi-file-earmark-check me-1"></i> Bon PDF
        </button>
        <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="openRequestDetail('${requestId}')">Détails</button>
      `;
      const tr = parentTd.closest('tr');
      if (tr) {
        const statusTd = tr.querySelector('.badge-status');
        if (statusTd) {
          statusTd.className = 'badge-status badge-approved';
          statusTd.innerHTML = '<span class="badge-status-dot"></span> Approuvée';
        }
      }
    }
  }

  // 4. Mettre à jour l'état local dans AppState.currentRequests
  if (AppState.currentRequests) {
    const found = AppState.currentRequests.find(r => r.reference === requestId);
    if (found) found.status = 'Approuvée';
  }

  // 5. Notification Toast de confirmation
  showToast(`Demande ${requestId} approuvée ! Bon officiel généré avec succès.`, 'success');

  // 6. Synchronisation avec l'API avant d'ouvrir le bon officiel
  if (typeof BackendApi !== 'undefined' && BackendApi.approveRequest) {
    BackendApi.approveRequest(requestId).then((approved) => {
      if (approved) openPdfModal(requestId);
    });
  } else {
    openPdfModal(requestId);
  }
}

function cancelCollaboratorRequest(requestId) {
  if (!requestId) return;
  if (confirm(`Confirmez-vous l'annulation de votre demande ${requestId} ?`)) {
    const badge = document.getElementById('requestDetailStatusBadge');
    if (badge) {
      badge.className = 'badge-status badge-rejected';
      badge.innerHTML = '<span class="badge-status-dot"></span> Annulée';
    }
    const btnCancel = document.getElementById('btnCollaboratorCancel');
    if (btnCancel) btnCancel.style.display = 'none';
    if (AppState.currentRequests) {
      const found = AppState.currentRequests.find(r => r.reference === requestId);
      if (found) found.status = 'Rejetée';
    }
    showToast(`Votre demande ${requestId} a été annulée.`, 'info');
  }
}

function openRequestDetail(reference) {
  AppState.selectedRequestRef = reference;
  const req = (AppState.currentRequests || []).find(r => r.reference === reference);
  if (!req) {
    navigateToView('request-detail');
    return;
  }

  // Titre & Réf
  const refTitle = document.getElementById('detailRequestRef');
  if (refTitle) refTitle.textContent = `Demande n° ${req.reference}`;

  // Badge Statut
  const badge = document.getElementById('requestDetailStatusBadge');
  if (badge) {
    if (req.status === 'Approuvée') {
      badge.className = 'badge-status badge-approved';
      badge.innerHTML = '<span class="badge-status-dot"></span> Approuvée';
    } else if (req.status === 'Rejetée') {
      badge.className = 'badge-status badge-rejected';
      badge.innerHTML = '<span class="badge-status-dot"></span> Rejetée';
    } else {
      badge.className = 'badge-status badge-pending';
      badge.innerHTML = '<span class="badge-status-dot"></span> En attente';
    }
  }

  // Sous-titre & Demandeur
  const subtitle = document.getElementById('detailRequestSubtitle');
  const dName = req.user?.name || 'Demandeur';
  const dService = req.service || 'Service Général';
  const createdDate = req.created_at ? new Date(req.created_at).toLocaleDateString('fr-FR', {day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit'}) : 'Récemment';
  if (subtitle) {
    subtitle.innerHTML = `Créée le ${createdDate} par <strong id="detailDemandeurName">${dName}</strong> (${dService})`;
  }

  // Onglet Infos
  const motifEl = document.getElementById('detailRequestMotif');
  if (motifEl) motifEl.textContent = req.motif || '-';

  const notesEl = document.getElementById('detailRequestNotes');
  if (notesEl) notesEl.textContent = req.notes || 'Aucune note particulière.';

  const srvEl = document.getElementById('detailRequestService');
  if (srvEl) srvEl.textContent = dService;

  const createdEl = document.getElementById('detailRequestCreatedAt');
  if (createdEl) createdEl.textContent = createdDate;

  const updatedEl = document.getElementById('detailRequestUpdatedAt');
  if (updatedEl) {
    updatedEl.textContent = req.updated_at ? new Date(req.updated_at).toLocaleDateString('fr-FR', {day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit'}) : createdDate;
  }

  // Timeline
  const timeStep1 = document.getElementById('detailTimelineStep1Time');
  if (timeStep1) timeStep1.textContent = `${createdDate} • Par ${dName}`;

  const timeStep2 = document.getElementById('timelineStepCurrent');
  const timeStep3 = document.getElementById('timelineStepApproved');
  if (timeStep2 && timeStep3) {
    if (req.status === 'Approuvée') {
      timeStep2.className = 'timeline-step done';
      const m2 = timeStep2.querySelector('.timeline-marker');
      if (m2) m2.innerHTML = '<i class="bi bi-check"></i>';
      const t2 = timeStep2.querySelector('.timeline-title');
      if (t2) { t2.className = 'timeline-title'; t2.textContent = 'Validation effectuée'; }

      timeStep3.className = 'timeline-step done';
      const m3 = timeStep3.querySelector('.timeline-marker');
      if (m3) m3.innerHTML = '<i class="bi bi-check-lg"></i>';
      const t3 = timeStep3.querySelector('.timeline-title');
      if (t3) { t3.className = 'timeline-title text-success fw-bold'; t3.textContent = 'Approuvée & Bon émis'; }
      const time3 = timeStep3.querySelector('.timeline-time');
      if (time3) time3.textContent = 'Bon officiel prêt au téléchargement';
    } else if (req.status === 'Rejetée') {
      timeStep2.className = 'timeline-step done';
      const t2 = timeStep2.querySelector('.timeline-title');
      if (t2) { t2.className = 'timeline-title text-danger fw-bold'; t2.textContent = 'Demande rejetée'; }
      timeStep3.className = 'timeline-step';
    } else {
      timeStep2.className = 'timeline-step current';
      const m2 = timeStep2.querySelector('.timeline-marker');
      if (m2) m2.innerHTML = '<i class="bi bi-arrow-repeat"></i>';
      const t2 = timeStep2.querySelector('.timeline-title');
      if (t2) { t2.className = 'timeline-title text-warning fw-bold'; t2.textContent = "En attente d'approbation"; }

      timeStep3.className = 'timeline-step';
      const m3 = timeStep3.querySelector('.timeline-marker');
      if (m3) m3.innerHTML = '3';
      const t3 = timeStep3.querySelector('.timeline-title');
      if (t3) { t3.className = 'timeline-title'; t3.textContent = 'Approuvée & Bon émis'; }
      const time3 = timeStep3.querySelector('.timeline-time');
      if (time3) time3.textContent = 'En attente de signature du gestionnaire';
    }
  }

  // Articles tables
  const items = req.items || [];
  const tabArticlesBtn = document.getElementById('detailTabArticlesBtn');
  if (tabArticlesBtn) tabArticlesBtn.textContent = `Articles (${items.length})`;

  const previewBody = document.getElementById('detailPreviewArticlesBody');
  if (previewBody) {
    previewBody.innerHTML = '';
    if (items.length === 0) {
      previewBody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">Aucun article dans cette demande.</td></tr>';
    } else {
      items.forEach(it => {
        const art = it.article || {};
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><strong>${art.name || 'Article'}</strong></td>
          <td><span class="badge bg-light text-dark border">${art.category || 'Général'}</span></td>
          <td class="text-center fw-semibold">${it.quantity_requested} ${art.unit || 'unité(s)'}</td>
          <td class="text-end"><span class="badge-status badge-in-stock"><span class="badge-status-dot"></span> Pris en compte</span></td>
        `;
        previewBody.appendChild(tr);
      });
    }
  }

  const fullBody = document.getElementById('detailFullArticlesBody');
  if (fullBody) {
    fullBody.innerHTML = '';
    if (items.length === 0) {
      fullBody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Aucun article dans cette demande.</td></tr>';
    } else {
      items.forEach(it => {
        const art = it.article || {};
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="fw-semibold text-primary">${art.code || '-'}</td>
          <td>${art.name || 'Article'}</td>
          <td><span class="badge bg-light text-dark border">${art.category || 'Général'}</span></td>
          <td>${art.unit || 'unité(s)'}</td>
          <td class="text-center fw-bold">${it.quantity_requested}</td>
          <td class="text-center fw-bold text-success">${req.status === 'Approuvée' ? (it.quantity_validated || it.quantity_requested) : '-'}</td>
          <td><span class="badge-status badge-in-stock"><span class="badge-status-dot"></span> En stock</span></td>
        `;
        fullBody.appendChild(tr);
      });
    }
  }

  // Boutons actions
  const btnCancel = document.getElementById('btnCollaboratorCancel');
  if (btnCancel) {
    btnCancel.onclick = () => cancelCollaboratorRequest(req.reference);
    btnCancel.style.display = (req.status === 'En attente') ? 'inline-block' : 'none';
  }

  // Nom Bon PDF
  const pdfName = document.getElementById('detailBonPdfName');
  if (pdfName) pdfName.textContent = `Bon_de_Demande_${req.reference}.pdf`;

  navigateToView('request-detail');
}

async function openPdfModal(reference) {
  if (!reference) {
    showToast('Sélectionnez d’abord une demande.', 'warning');
    return false;
  }

  let req = (AppState.currentRequests || []).find(r => r.reference === reference);

  try {
    const response = await fetch(`/api/requests/${encodeURIComponent(reference)}`, {
      headers: { 'Accept': 'application/json' }
    });

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

  if (!['Approuvée', 'Restituée'].includes(req.status)) {
    showToast('Le bon est disponible uniquement après l’approbation de la demande.', 'warning');
    return false;
  }

  const refEl = document.getElementById('pdfModalRef');
  if (refEl) refEl.textContent = req.reference;

  const dateEl = document.getElementById('pdfModalDate');
  const documentDate = req.approved_at || req.created_at;
  const d = documentDate ? new Date(documentDate).toLocaleDateString('fr-FR', {day: '2-digit', month: 'long', year: 'numeric'}) : new Date().toLocaleDateString('fr-FR');
  if (dateEl) dateEl.textContent = `Émis le : ${d}`;

  const demandeurEl = document.getElementById('pdfModalDemandeur');
  if (demandeurEl) demandeurEl.textContent = req.user?.name || 'Demandeur';

  const srvEl = document.getElementById('pdfModalService');
  if (srvEl) srvEl.textContent = req.service || 'Direction Générale';

  const motifEl = document.getElementById('pdfModalMotif');
  if (motifEl) motifEl.textContent = req.motif || 'Dotation de matériel';

  const typeEl = document.getElementById('pdfModalType');
  if (typeEl) typeEl.textContent = req.type_operation || 'Dotation & Approvisionnement';

  const sigCollab = document.getElementById('pdfSignatureCollaborator');
  if (sigCollab) sigCollab.textContent = `Signé numériquement par ${req.user?.name || 'le demandeur'}`;

  const sigAdmin = document.getElementById('pdfSignatureAdmin');
  if (sigAdmin) sigAdmin.textContent = `Visé & Validé par ${req.approver?.name || 'le Gestionnaire de stock'}`;

  const tbody = document.getElementById('pdfModalArticlesBody');
  if (tbody) {
    tbody.innerHTML = '';
    const items = req.items || [];
    if (items.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">Aucun article spécifié.</td></tr>';
    } else {
      items.forEach(it => {
        const art = it.article || {};
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><strong>${art.code || '-'}</strong></td>
          <td>${art.name || 'Article'}</td>
          <td>${art.category || 'Fournitures'}</td>
          <td>${art.unit || 'unité'}</td>
          <td class="text-center">${it.quantity_requested}</td>
          <td class="text-center fw-bold text-success">${it.quantity_approved || it.quantity_requested}</td>
        `;
        tbody.appendChild(tr);
      });
    }
  }

  const printableDocument = document.getElementById('printablePdfDocument');
  if (printableDocument) printableDocument.dataset.reference = req.reference;

  const modalEl = document.getElementById('modalOfficialPdf');
  if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();

  return true;
}

function setupModalsAndActions() {
  // Bouton de rafraîchissement manuel dans la Topbar
  const btnTopRefresh = document.getElementById('topbarBtnRefresh');
  if (btnTopRefresh) {
    btnTopRefresh.addEventListener('click', () => {
      if (typeof BackendApi !== 'undefined') {
        BackendApi.refreshAll({ showIndicator: true, silent: false });
      }
    });
  }

  // Actions de validation, restitution et rejet de la demande
  const btnApprove = document.getElementById('btnActionApprove');
  const btnReject = document.getElementById('btnActionReject');
  const btnReturn = document.getElementById('btnActionReturn');

  document.querySelectorAll('.js-open-selected-bon').forEach(button => {
    button.addEventListener('click', () => openPdfModal(AppState.selectedRequestRef));
  });

  if (btnApprove) {
    btnApprove.addEventListener('click', () => {
      const activeRef = AppState.selectedRequestRef;
      if (activeRef) {
        approveRequest(activeRef);
      } else if (AppState.currentRequests && AppState.currentRequests.length > 0) {
        approveRequest(AppState.currentRequests[0].reference);
      } else {
        showToast('Aucune demande sélectionnée.', 'warning');
      }
    });
  }

  if (btnReturn) {
    btnReturn.addEventListener('click', async () => {
      if (AppState.currentRole !== 'admin') {
        showToast('Accès refusé : Seul le Gestionnaire peut enregistrer une restitution.', 'danger');
        return;
      }
      const activeRef = AppState.selectedRequestRef;
      if (!activeRef) {
        showToast('Aucune demande sélectionnée.', 'warning');
        return;
      }
      if (confirm(`Confirmez-vous la restitution physique du matériel pour la demande ${activeRef} ? Les stocks seront réintégrés.`)) {
        if (typeof BackendApi !== 'undefined') {
          await BackendApi.returnRequest(activeRef);
        }
      }
    });
  }

  if (btnReject) {
    btnReject.addEventListener('click', async () => {
      if (AppState.currentRole !== 'admin') {
        showToast('Accès refusé : Seul le Gestionnaire de stock / Administrateur peut rejeter une demande.', 'danger');
        return;
      }
      const activeRef = AppState.selectedRequestRef;
      if (!activeRef) {
        showToast('Aucune demande sélectionnée.', 'warning');
        return;
      }
      const reason = prompt('Motif du refus de la demande :', 'Quantités disproportionnées ou matériel indisponible.');
      if (reason && reason.trim()) {
        if (typeof BackendApi !== 'undefined') {
          await BackendApi.rejectRequest(activeRef, reason.trim());
        }
      }
    });
  }

  // Modal Nouvel Article (Formulaire d'ajout rapide - réservé Administrateur)
  const formNewArticle = document.getElementById('formNewArticle');
  if (formNewArticle) {
    formNewArticle.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (AppState.currentRole !== 'admin') {
        showToast('Action restreinte : seuls les administrateurs et gestionnaires de stock peuvent créer des articles.', 'danger');
        return;
      }
      const code = document.getElementById('artCode').value.trim();
      const name = document.getElementById('artName').value.trim();
      const cat = document.getElementById('artCategory').value;
      const unit = document.getElementById('artUnit').value;
      const qty = parseInt(document.getElementById('artQty').value, 10);
      const threshold = parseInt(document.getElementById('artThreshold').value, 10);
      const currentUserId = AppState.currentUser?.id;

      try {
        const res = await fetch('/api/articles', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ code, name, category: cat, unit, quantity: qty, threshold, created_by: currentUserId })
        });
        const result = await res.json();
        if (result.success) {
          showToast(`Article "${name}" créé et enregistré dans MySQL !`, 'success');
          const modalEl = document.getElementById('modalNewArticle');
          const modalInstance = bootstrap.Modal.getInstance(modalEl);
          if (modalInstance) modalInstance.hide();
          formNewArticle.reset();
          if (typeof BackendApi !== 'undefined') {
            await BackendApi.refreshAll({ showIndicator: true });
          }
          return;
        } else {
          showToast(result.message || 'Erreur lors de la création de l\'article.', 'danger');
          return;
        }
      } catch (err) {
        console.warn('API error:', err);
      }

      addNewArticleRow({ code, name, category: cat, unit, qty, threshold });
      const modalEl = document.getElementById('modalNewArticle');
      const modalInstance = bootstrap.Modal.getInstance(modalEl);
      if (modalInstance) modalInstance.hide();
      formNewArticle.reset();
      showToast(`Article "${name}" créé avec succès !`, 'success');
    });
  }

  // Modal d'ajustement manuel du stock
  const formNewMvt = document.getElementById('formNewMovement');
  if (formNewMvt) {
    formNewMvt.addEventListener('submit', async (e) => {
      e.preventDefault();
      const articleSelect = document.getElementById('mvtArticle');
      const articleId = parseInt(articleSelect.value, 10);
      const type = document.getElementById('mvtType').value;
      const qty = parseInt(document.getElementById('mvtQty').value, 10);
      const reason = document.getElementById('mvtReason').value.trim();
      const currentUserId = AppState.currentUser?.id;

      if (!articleId) {
        showToast('Veuillez sélectionner un article dans la liste.', 'warning');
        return;
      }

      try {
        const res = await fetch('/api/movements', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ article_id: articleId, type, quantity: qty, reason, user_id: currentUserId })
        });
        const result = await res.json();
        if (result.success) {
          showToast(`Ajustement enregistré (${type === 'Entrée' ? '+' : '-'}${qty}) !`, 'success');
          const modalEl = document.getElementById('modalNewMovement');
          const modalInstance = bootstrap.Modal.getInstance(modalEl);
          if (modalInstance) modalInstance.hide();
          formNewMvt.reset();
          if (typeof BackendApi !== 'undefined') {
            await BackendApi.refreshAll({ showIndicator: true });
          }
          return;
        } else {
          showToast(result.message || 'Erreur lors de l\'enregistrement du mouvement.', 'danger');
          return;
        }
      } catch (err) {
        console.warn('API error:', err);
        showToast('Impossible d’enregistrer l’ajustement. Vérifiez la connexion au serveur.', 'danger');
        return;
      }
    });
  }

  // Modal Nouveau Document Administratif
  const formNewDoc = document.getElementById('formNewDocument');
  if (formNewDoc) {
    formNewDoc.addEventListener('submit', async (e) => {
      e.preventDefault();
      const title = document.getElementById('docTitle').value.trim();
      const cat = document.getElementById('docCategory').value;
      const type = document.getElementById('docType').value;
      const service = document.getElementById('docService').value;

      try {
        const res = await fetch('/api/documents', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ title, category: cat, type, service, user_id: 1 })
        });
        const result = await res.json();
        if (result.success) {
          showToast(`Document "${title}" archivé dans MySQL !`, 'success');
          const modalEl = document.getElementById('modalNewDocument');
          const modalInstance = bootstrap.Modal.getInstance(modalEl);
          if (modalInstance) modalInstance.hide();
          formNewDoc.reset();
          if (typeof BackendApi !== 'undefined') {
            await BackendApi.loadDocuments();
          }
          return;
        }
      } catch (err) {
        console.warn('API error:', err);
      }

      addNewDocumentRow({ title, category: cat, type, service });
      const modalEl = document.getElementById('modalNewDocument');
      const modalInstance = bootstrap.Modal.getInstance(modalEl);
      if (modalInstance) modalInstance.hide();
      formNewDoc.reset();
      showToast(`Document "${title}" archivé avec succès !`, 'success');
    });
  }

  // Formulaire pleine page Nouveau Document (View document-new)
  const fullPageNewDoc = document.getElementById('formFullPageNewDoc');
  if (fullPageNewDoc) {
    fullPageNewDoc.addEventListener('submit', (e) => {
      e.preventDefault();
      const title = document.getElementById('fpDocTitle').value;
      const cat = document.getElementById('fpDocCategory').value;
      const type = document.getElementById('fpDocType').value;
      const service = document.getElementById('fpDocService').value;

      addNewDocumentRow({ title, category: cat, type, service });
      showToast(`Document "${title}" enregistré et indexé avec succès !`, 'success');
      navigateToView('documents');
    });
  }

  // Modal Nouvel Utilisateur
  const formNewUser = document.getElementById('formNewUser');
  if (formNewUser) {
    formNewUser.addEventListener('submit', async (e) => {
      e.preventDefault();
      const name = document.getElementById('usrName').value.trim();
      const email = document.getElementById('usrEmail').value.trim();
      const role = document.getElementById('usrRole').value;
      const service = document.getElementById('usrService').value;
      const password = document.getElementById('usrPassword')?.value || 'Password2025!';

      try {
        const res = await fetch('/api/users', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ name, email, role, service, password })
        });
        const result = await res.json();
        if (result.success) {
          showToast(`Utilisateur "${name}" créé et enregistré dans MySQL !`, 'success');
          const modalEl = document.getElementById('modalNewUser');
          const modalInstance = bootstrap.Modal.getInstance(modalEl);
          if (modalInstance) modalInstance.hide();
          formNewUser.reset();
          if (typeof BackendApi !== 'undefined') {
            await BackendApi.refreshAll({ showIndicator: true });
          }
          return;
        } else {
          showToast(result.message || 'Erreur lors de la création de l\'utilisateur.', 'danger');
          return;
        }
      } catch (err) {
        console.warn('API error create user:', err);
      }

      addNewUserRow({ name, email, role, service });
      const modalEl = document.getElementById('modalNewUser');
      const modalInstance = bootstrap.Modal.getInstance(modalEl);
      if (modalInstance) modalInstance.hide();
      formNewUser.reset();
      showToast(`Utilisateur "${name}" créé avec succès !`, 'success');
    });
  }

  // Impression du bon officiel uniquement, avec une mise en page A4 dédiée
  const btnPrintPdf = document.getElementById('btnPrintPdfDoc');
  if (btnPrintPdf) {
    btnPrintPdf.addEventListener('click', () => {
      const printableDocument = document.getElementById('printablePdfDocument');
      const reference = printableDocument?.dataset.reference;

      if (!printableDocument || !reference) {
        showToast('Aucun bon valide n’est chargé pour l’impression.', 'warning');
        return;
      }

      const previousTitle = document.title;
      document.title = `Bon_${reference}`;
      document.body.classList.add('printing-official-bon');

      const cleanupPrintState = () => {
        document.title = previousTitle;
        document.body.classList.remove('printing-official-bon');
      };

      window.addEventListener('afterprint', cleanupPrintState, { once: true });
      window.print();
      window.setTimeout(cleanupPrintState, 60000);
    });
  }
}

/* ==========================================================================
   5. Filtrage & Recherche Dynamique des Tableaux
   ========================================================================== */
function setupTableFilters() {
  // Filtre Articles en stock
  const searchStock = document.getElementById('searchStockInput');
  const filterCatStock = document.getElementById('filterStockCategory');
  const filterStatusStock = document.getElementById('filterStockStatus');

  const applyStockFilter = () => {
    const query = (searchStock?.value || '').toLowerCase();
    const cat = filterCatStock?.value || '';
    const status = filterStatusStock?.value || '';

    const rows = document.querySelectorAll('#articlesStockTableBody tr');
    rows.forEach(tr => {
      const text = tr.innerText.toLowerCase();
      const rowCat = tr.getAttribute('data-cat') || '';
      const rowStatus = tr.getAttribute('data-status') || '';

      const matchQuery = !query || text.includes(query);
      const matchCat = !cat || rowCat === cat;
      const matchStatus = !status || rowStatus === status;

      if (matchQuery && matchCat && matchStatus) {
        tr.style.display = '';
      } else {
        tr.style.display = 'none';
      }
    });
  };

  searchStock?.addEventListener('input', applyStockFilter);
  filterCatStock?.addEventListener('change', applyStockFilter);
  filterStatusStock?.addEventListener('change', applyStockFilter);

  // Filtre Mouvements de stock
  const filterMvtType = document.getElementById('filterMovementType');
  const searchMvt = document.getElementById('searchMovementInput');

  const applyMvtFilter = () => {
    const type = filterMvtType?.value || '';
    const query = (searchMvt?.value || '').toLowerCase();

    const rows = document.querySelectorAll('#stockMovementsTableBody tr');
    rows.forEach(tr => {
      const rowType = tr.getAttribute('data-type') || '';
      const text = tr.innerText.toLowerCase();

      const matchType = !type || rowType === type;
      const matchQuery = !query || text.includes(query);

      tr.style.display = matchType && matchQuery ? '' : 'none';
    });
  };

  filterMvtType?.addEventListener('change', applyMvtFilter);
  searchMvt?.addEventListener('input', applyMvtFilter);

  // Filtre Documents administratifs
  const searchDocs = document.getElementById('searchDocsInput');
  const filterDocCat = document.getElementById('filterDocCategory');
  const filterDocType = document.getElementById('filterDocType');
  const filterDocService = document.getElementById('filterDocService');

  const applyDocFilter = () => {
    const query = (searchDocs?.value || '').toLowerCase();
    const cat = filterDocCat?.value || '';
    const type = filterDocType?.value || '';
    const srv = filterDocService?.value || '';

    const rows = document.querySelectorAll('#documentsTableBody tr');
    rows.forEach(tr => {
      const text = tr.innerText.toLowerCase();
      const rowCat = tr.getAttribute('data-cat') || '';
      const rowType = tr.getAttribute('data-type') || '';
      const rowSrv = tr.getAttribute('data-service') || '';

      const matchQuery = !query || text.includes(query);
      const matchCat = !cat || rowCat === cat;
      const matchType = !type || rowType === type;
      const matchSrv = !srv || rowSrv === srv;

      tr.style.display = (matchQuery && matchCat && matchType && matchSrv) ? '' : 'none';
    });
  };

  searchDocs?.addEventListener('input', applyDocFilter);
  filterDocCat?.addEventListener('change', applyDocFilter);
  filterDocType?.addEventListener('change', applyDocFilter);
  filterDocService?.addEventListener('change', applyDocFilter);

  // Filtre Utilisateurs
  const searchUsers = document.getElementById('searchUsersInput');
  const filterUserRole = document.getElementById('filterUserRole');

  const applyUserFilter = () => {
    const query = (searchUsers?.value || '').toLowerCase();
    const role = filterUserRole?.value || '';

    const rows = document.querySelectorAll('#usersTableBody tr');
    rows.forEach(tr => {
      const text = tr.innerText.toLowerCase();
      const rowRole = tr.getAttribute('data-role') || '';

      const matchQuery = !query || text.includes(query);
      const matchRole = !role || rowRole === role;

      tr.style.display = matchQuery && matchRole ? '' : 'none';
    });
  };

  searchUsers?.addEventListener('input', applyUserFilter);
  filterUserRole?.addEventListener('change', applyUserFilter);
}

/* ==========================================================================
   6. Ajout Dynamique de Lignes (Stock, Mouvements, Docs, Users)
   ========================================================================== */
function addNewArticleRow(art) {
  const tbody = document.getElementById('articlesStockTableBody');
  if (!tbody) return;

  const isLow = art.qty <= art.threshold;
  const statusBadge = isLow 
    ? '<span class="badge-status badge-low-stock"><span class="badge-status-dot"></span> Stock faible</span>'
    : '<span class="badge-status badge-in-stock"><span class="badge-status-dot"></span> En stock</span>';

  const tr = document.createElement('tr');
  tr.setAttribute('data-cat', art.category);
  tr.setAttribute('data-status', isLow ? 'faible' : 'dispo');
  const isAdmin = AppState.currentRole === 'admin';
  tr.innerHTML = `
    <td class="fw-medium text-muted">${art.code}</td>
    <td>
      <div class="d-flex align-items-center gap-2">
        <div class="rounded p-1 bg-light text-primary"><i class="bi bi-box-seam"></i></div>
        <span class="fw-semibold text-dark">${art.name}</span>
      </div>
    </td>
    <td>${art.category}</td>
    <td>${art.unit}</td>
    <td class="fw-bold">${art.qty}</td>
    <td class="text-muted">${art.threshold}</td>
    <td>${statusBadge}</td>
    <td class="text-end">
      <button class="btn-gp-icon admin-only-feature" title="Modifier l'article" style="${isAdmin ? '' : 'display:none;'}"><i class="bi bi-three-dots"></i></button>
      <button class="btn btn-sm btn-outline-primary collaborator-only-feature py-0 px-2" style="${isAdmin ? 'display:none;' : ''}" onclick="showToast('${art.name.replace(/'/g, "\\'")} ajouté à votre demande !', 'info'); navigateToView('request-new');"><i class="bi bi-plus-lg me-1"></i> Demander</button>
    </td>
  `;
  tbody.prepend(tr);
}

function addNewDocumentRow(doc) {
  const tbody = document.getElementById('documentsTableBody');
  if (!tbody) return;

  let icon = '<i class="bi bi-file-earmark-pdf file-badge-icon file-icon-pdf"></i>';
  if (doc.type === 'Word') icon = '<i class="bi bi-file-earmark-word file-badge-icon file-icon-word"></i>';
  if (doc.type === 'Excel') icon = '<i class="bi bi-file-earmark-excel file-badge-icon file-icon-excel"></i>';

  const tr = document.createElement('tr');
  tr.setAttribute('data-cat', doc.category);
  tr.setAttribute('data-type', doc.type);
  tr.setAttribute('data-service', doc.service);
  tr.innerHTML = `
    <td>
      <div class="d-flex align-items-center gap-3">
        ${icon}
        <span class="fw-semibold text-dark">${doc.title}</span>
      </div>
    </td>
    <td>${doc.category}</td>
    <td><span class="badge bg-light text-secondary border">${doc.type}</span></td>
    <td>${doc.service}</td>
    <td>Aujourd'hui</td>
    <td class="text-end">
      <button class="btn-gp-icon" title="Télécharger"><i class="bi bi-download"></i></button>
      <button class="btn-gp-icon" title="Options"><i class="bi bi-three-dots"></i></button>
    </td>
  `;
  tbody.prepend(tr);
}

function addNewUserRow(usr) {
  const tbody = document.getElementById('usersTableBody');
  if (!tbody) return;

  const initials = usr.name.split(' ').map(n => n[0]).join('').substring(0, 2);
  const tr = document.createElement('tr');
  tr.setAttribute('data-role', usr.role);
  tr.innerHTML = `
    <td>
      <div class="d-flex align-items-center gap-2">
        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width:34px; height:34px; font-size:0.8rem;">
          ${initials}
        </div>
        <span class="fw-semibold text-dark">${usr.name}</span>
      </div>
    </td>
    <td class="text-muted">${usr.email}</td>
    <td><span class="badge bg-light text-dark border">${usr.role}</span></td>
    <td>${usr.service}</td>
    <td><span class="badge-status badge-in-stock"><span class="badge-status-dot"></span> Actif</span></td>
    <td class="text-end">
      <button class="btn-gp-icon" title="Modifier"><i class="bi bi-three-dots"></i></button>
    </td>
  `;
  tbody.prepend(tr);
}

/* ==========================================================================
   7. Recherche Globale Topbar
   ========================================================================== */
function setupSearchGlobal() {
  const globalInput = document.getElementById('topbarGlobalSearch');
  if (!globalInput) return;

  globalInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      const q = globalInput.value.trim().toLowerCase();
      if (!q) return;

      if (q.includes('art') || q.includes('papier') || q.includes('stylo') || q.includes('stock')) {
        navigateToView('stock-articles');
        const s = document.getElementById('searchStockInput');
        if (s) { s.value = q; s.dispatchEvent(new Event('input')); }
      } else if (q.includes('dem') || q.includes('demande') || q.includes('formation')) {
        navigateToView('request-detail');
      } else if (q.includes('doc') || q.includes('pdf') || q.includes('contrat') || q.includes('procédure')) {
        navigateToView('documents');
        const d = document.getElementById('searchDocsInput');
        if (d) { d.value = q; d.dispatchEvent(new Event('input')); }
      } else {
        showToast(`Recherche sur "${q}" effectuée.`, 'info');
      }
    }
  });
}

/* ==========================================================================
   8. Système de Notifications Toast
   ========================================================================== */
function showToast(message, type = 'info') {
  const container = document.getElementById('toastContainer');
  if (!container) return;

  const toastId = 'toast-' + Date.now();
  let bgClass = 'text-bg-dark';
  let icon = 'bi-info-circle-fill text-info';

  if (type === 'success') {
    bgClass = 'text-bg-success';
    icon = 'bi-check-circle-fill';
  } else if (type === 'warning') {
    bgClass = 'text-bg-warning text-dark';
    icon = 'bi-exclamation-triangle-fill';
  } else if (type === 'error') {
    bgClass = 'text-bg-danger';
    icon = 'bi-x-circle-fill';
  }

  const toastEl = document.createElement('div');
  toastEl.className = `toast align-items-center ${bgClass} border-0 shadow-lg mb-2`;
  toastEl.id = toastId;
  toastEl.setAttribute('role', 'alert');
  toastEl.setAttribute('aria-live', 'assertive');
  toastEl.setAttribute('aria-atomic', 'true');
  toastEl.innerHTML = `
    <div class="d-flex">
      <div class="toast-body d-flex align-items-center gap-2 py-2 px-3">
        <i class="bi ${icon} fs-5"></i>
        <span>${message}</span>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  `;

  container.appendChild(toastEl);
  const bsToast = new bootstrap.Toast(toastEl, { delay: 4000 });
  bsToast.show();

  toastEl.addEventListener('hidden.bs.toast', () => {
    toastEl.remove();
  });
}

function getFormattedTime() {
  const now = new Date();
  return now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

/* ==========================================================================
   9. Gestion de l'Authentification (Inscription, Connexion, Déconnexion) & Paramètres
   ========================================================================== */
function setupAuthAndSettingsHandlers() {
  // Inscription
  const formRegister = document.getElementById('formRegisterUser');
  if (formRegister) {
    formRegister.addEventListener('submit', async (e) => {
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
      const matricule = document.getElementById('regMatricule')?.value.trim() || '';
      const role = document.getElementById('regRole')?.value || 'Collaborateur';

      try {
        const res = await fetch('/api/auth/register', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ name, email, service, matricule, role, password: pwd })
        });
        const result = await res.json();
        if (result.success && result.user) {
          sessionStorage.setItem('gestion_user', JSON.stringify(result.user));
          applyUser(result.user);
          showToast(result.message || `Compte créé avec succès pour ${name} !`, 'success');
          navigateToView('dashboard');
          if (typeof BackendApi !== 'undefined') {
            await BackendApi.loadAllData();
          }
          return;
        } else {
          showToast(result.message || 'Erreur lors de la création du compte.', 'danger');
          return;
        }
      } catch (err) {
        console.warn('Register error:', err);
      }

      const fallbackUser = { name, email, service, matricule, role: role === 'Administrateur' ? 'admin' : 'collaborator' };
      sessionStorage.setItem('gestion_user', JSON.stringify(fallbackUser));
      applyUser(fallbackUser);
      showToast(`Compte créé avec succès pour ${name} !`, 'success');
      navigateToView('dashboard');
    });
  }

  // Connexion
  const formLogin = document.getElementById('formLoginUser');
  if (formLogin) {
    formLogin.addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = document.getElementById('loginEmail').value.trim();
      const password = document.getElementById('loginPassword').value;

      try {
        const res = await fetch('/api/auth/login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ email, password })
        });
        const result = await res.json();
        if (result.success && result.user) {
          sessionStorage.setItem('gestion_user', JSON.stringify(result.user));
          applyUser(result.user);
          showToast(`Bienvenue ${result.user.name} ! Bon retour sur votre espace.`, 'success');
          navigateToView('dashboard');
          if (typeof BackendApi !== 'undefined') {
            await BackendApi.loadAllData();
          }
          return;
        } else {
          showToast(result.message || 'Identifiants invalides (email ou mot de passe incorrect).', 'danger');
          return;
        }
      } catch (err) {
        console.warn('Login error:', err);
      }

      showToast('Impossible de contacter le serveur d\'authentification.', 'danger');
    });
  }

  // Paramètres : Gestion des onglets
  const settingsPills = document.querySelectorAll('.settings-pill-btn');
  settingsPills.forEach(btn => {
    btn.addEventListener('click', () => {
      settingsPills.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const targetPane = btn.getAttribute('data-settings-tab');
      document.querySelectorAll('.settings-tab-pane').forEach(pane => {
        pane.style.display = 'none';
      });

      const activePane = document.getElementById(`settingsPane-${targetPane}`);
      if (activePane) {
        activePane.style.display = 'block';
      }
    });
  });

  // Sauvegarde des paramètres
  const btnSaveSettings = document.getElementById('btnSaveSettings');
  if (btnSaveSettings) {
    btnSaveSettings.addEventListener('click', () => {
      showToast('Paramètres et seuils système enregistrés avec succès !', 'success');
    });
  }

  // Sauvegarde du profil
  const formProfile = document.getElementById('formUserProfile');
  if (formProfile) {
    formProfile.addEventListener('submit', (e) => {
      e.preventDefault();
      showToast('Vos informations de profil et préférences ont été mises à jour.', 'success');
    });
  }
}

/* ==========================================================================
   10. Pont Applicatif Backend Laravel & Base de Données MySQL
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
        this.isDbConnected = !!data.database_connected;
        this.updateStatusBadge(data);

        // Vérifier l'état des utilisateurs en base
        const usersRes = await fetch(`${this.baseUrl}/users`);
        if (usersRes.ok) {
          const uJson = await usersRes.json();
          if (uJson.count === 0) {
            sessionStorage.removeItem('gestion_user');
            AppState.currentUser = null;
            showToast('Aucun utilisateur en base de données. Créez votre compte Administrateur pour commencer.', 'info');
            navigateToView('register');
            return data;
          } else if (AppState.currentUser) {
            const exists = uJson.data.some(u => u.id === AppState.currentUser.id || u.email === AppState.currentUser.email);
            if (!exists) {
              sessionStorage.removeItem('gestion_user');
              AppState.currentUser = null;
              showToast('Ce compte n\'existe plus. Veuillez vous réinscrire ou vous connecter.', 'warning');
              navigateToView('register');
              return data;
            }
          }
        }

        if (this.isDbConnected) {
          await this.refreshAll({ showIndicator: false, silent: true });
          this.startAutoRefresh(20000);
        }
        return data;
      }
    } catch (e) {
      console.warn('Backend API non disponible en direct:', e.message);
      this.updateStatusBadge({ status: 'offline' });
    }
    return null;
  },

  updateStatusBadge(data) {
    let badge = document.getElementById('backendStatusBadge');
    if (!badge) {
      const topbarActions = document.querySelector('.topbar-actions');
      if (topbarActions) {
        badge = document.createElement('div');
        badge.id = 'backendStatusBadge';
        badge.className = 'd-none d-md-flex align-items-center gap-2 px-2 py-1 rounded-pill me-2';
        badge.style.fontSize = '0.78rem';
        badge.style.cursor = 'pointer';
        badge.setAttribute('title', 'Cliquez pour voir les détails de connexion MySQL');
        badge.addEventListener('click', () => {
          const modalEl = document.getElementById('modalDbStatus') || this.createDbModal();
          const modal = new bootstrap.Modal(modalEl);
          modal.show();
        });
        topbarActions.insertBefore(badge, topbarActions.firstChild);
      }
    }
    if (!badge) return;

    if (data.status === 'online') {
      if (data.database_connected) {
        badge.className = 'd-none d-md-flex align-items-center gap-2 px-3 py-1 rounded-pill me-2 bg-success-subtle text-success border border-success-subtle fw-semibold';
        badge.innerHTML = '<span class="badge-status-dot bg-success" style="width:8px;height:8px;border-radius:50%;display:inline-block;"></span> <span>MySQL Connecté (Données Réelles)</span>';
      } else {
        badge.className = 'd-none d-md-flex align-items-center gap-2 px-3 py-1 rounded-pill me-2 bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-semibold';
        badge.innerHTML = '<span class="badge-status-dot bg-warning" style="width:8px;height:8px;border-radius:50%;display:inline-block;"></span> <span>Laravel Actif • MySQL en attente (.env)</span>';
      }
    } else {
      badge.className = 'd-none d-md-flex align-items-center gap-2 px-3 py-1 rounded-pill me-2 bg-light text-muted border fw-semibold';
      badge.innerHTML = '<span class="badge-status-dot bg-secondary" style="width:8px;height:8px;border-radius:50%;display:inline-block;"></span> <span>Mode Autonome</span>';
    }
  },

  createDbModal() {
    let modal = document.getElementById('modalDbStatus');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'modalDbStatus';
    modal.setAttribute('tabindex', '-1');
    modal.innerHTML = `
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
          <div class="modal-header bg-dark text-white">
            <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-database-check me-2 text-warning"></i> Connexion Backend & MySQL</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-4">
            <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
              <i class="bi bi-check-circle-fill fs-5"></i>
              <div>Base de données <strong>MySQL (gestion_materiel)</strong> connectée avec succès ! Les données affichées sont 100% dynamiques et réelles.</div>
            </div>
            <h6 class="fw-bold mb-2">Paramètres de connexion actifs :</h6>
            <div class="bg-light p-3 rounded border font-monospace small mb-3">
              DB_CONNECTION=mysql<br>
              DB_HOST=127.0.0.1<br>
              DB_PORT=3306<br>
              DB_DATABASE=gestion_materiel<br>
              DB_USERNAME=root
            </div>
          </div>
          <div class="modal-footer bg-light py-2">
            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fermer</button>
            <button type="button" class="btn btn-sm btn-primary" onclick="BackendApi.loadAllData().then(() => showToast('Données synchronisées depuis MySQL !', 'success'))">Synchroniser maintenant</button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
    return modal;
  },

  async refreshAll(options = { showIndicator: false, silent: false }) {
    if (!this.isOnline || !this.isDbConnected) return;

    const spinIcon = document.getElementById('iconRefreshSpin');
    if (spinIcon) spinIcon.classList.add('spin-animation');

    try {
      await Promise.allSettled([
        this.loadArticles(),
        this.loadRequests(),
        this.loadMovements(),
        this.loadDashboard(),
        this.loadUsers(),
        this.loadDocuments()
      ]);

      if (typeof updateCollaboratorLiveTracker === 'function') {
        updateCollaboratorLiveTracker();
      }

      if (AppState.currentView === 'request-detail' && AppState.selectedRequestRef) {
        openRequestDetail(AppState.selectedRequestRef);
      }

      if (typeof applyRoleVisibility === 'function') {
        applyRoleVisibility(AppState.currentRole);
      }

      if (options.showIndicator && !options.silent) {
        showToast('Données et stocks synchronisés en direct avec MySQL !', 'success');
      }
    } catch (e) {
      console.warn('Erreur lors du rafraîchissement global:', e);
    } finally {
      if (spinIcon) {
        setTimeout(() => spinIcon.classList.remove('spin-animation'), 650);
      }
    }
  },

  async loadAllData() {
    return this.refreshAll({ showIndicator: false, silent: true });
  },

  startAutoRefresh(intervalMs = 20000) {
    if (this._autoRefreshTimer) clearInterval(this._autoRefreshTimer);
    this._autoRefreshTimer = setInterval(() => {
      if (!document.hidden && !document.querySelector('.modal.show')) {
        this.refreshAll({ silent: true, showIndicator: false });
      }
    }, intervalMs);
  },

  
  async loadUsers() {
    try {
      const res = await fetch(`${this.baseUrl}/users`);
      if (!res.ok) return;
      const json = await res.json();
      if (!json.success || !json.data) return;

      const users = json.data;
      const tbody = document.getElementById('usersTableBody');
      if (tbody) {
        tbody.innerHTML = '';
        if (users.length === 0) {
          tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-people me-2"></i>Aucun utilisateur enregistré. Cliquez sur "+ Nouvel utilisateur" pour créer un compte.</td></tr>';
          return;
        }

        users.forEach(u => {
          const initials = (u.name || 'U').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
          const roleLabel = u.role === 'admin' ? 'Administrateur' : 'Collaborateur';
          const roleBadgeClass = u.role === 'admin' ? 'bg-primary-subtle text-primary border' : 'bg-light text-dark border';

          const isCurrentSessionUser = AppState.currentUser && (
            AppState.currentUser.id === u.id || 
            AppState.currentUser.email === u.email
          );

          const actionHtml = isCurrentSessionUser
            ? '<span class="badge bg-primary-subtle text-primary border py-1 px-2" title="Votre compte connecté"><i class="bi bi-person-check me-1"></i>Connecté (Vous)</span>'
            : `<button class="btn-gp-icon" title="Supprimer l'utilisateur" onclick="BackendApi.deleteUser(${u.id}, '${u.name.replace(/'/g, "\\'")}', '${u.role}')"><i class="bi bi-trash text-danger"></i></button>`;

          const tr = document.createElement('tr');
          tr.setAttribute('data-role', roleLabel);
          tr.innerHTML = `
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width:34px; height:34px; font-size:0.8rem;">
                  ${initials}
                </div>
                <div>
                  <span class="fw-semibold text-dark">${u.name}</span>
                  ${isCurrentSessionUser ? ' <span class="badge bg-secondary-subtle text-secondary" style="font-size:0.68rem;">(Vous)</span>' : ''}
                </div>
              </div>
            </td>
            <td class="text-muted">${u.email}</td>
            <td><span class="badge ${roleBadgeClass}">${roleLabel}</span></td>
            <td>${u.service || '-'}</td>
            <td><span class="badge-status badge-in-stock"><span class="badge-status-dot"></span> Actif</span></td>
            <td class="text-end">
              ${actionHtml}
            </td>
          `;
          tbody.appendChild(tr);
        });
      }
    } catch (e) {
      console.warn('loadUsers error:', e);
    }
  },

  async deleteUser(userId, userName, userRole = '') {
    if (AppState.currentUser && (AppState.currentUser.id == userId || AppState.currentUser.email === userName)) {
      showToast('Action impossible : vous ne pouvez pas supprimer votre propre compte connecté !', 'warning');
      return;
    }

    if (!confirm(`Êtes-vous sûr de vouloir supprimer le compte de ${userName} ?`)) return;

    try {
      const currentUserId = AppState.currentUser?.id || '';
      const res = await fetch(`${this.baseUrl}/users/${userId}?current_user_id=${currentUserId}`, { 
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
          'X-User-Id': currentUserId
        }
      });
      const json = await res.json();
      if (json.success) {
        showToast(json.message || 'Utilisateur supprimé.', 'success');
        await this.loadUsers();
      } else {
        showToast(json.message || 'Impossible de supprimer cet utilisateur.', 'error');
      }
    } catch (e) {
      console.warn('deleteUser error:', e);
      showToast('Erreur de communication avec le serveur.', 'error');
    }
  },

  async loadArticles() {
    try {
      const res = await fetch(`${this.baseUrl}/articles`);
      if (!res.ok) return;
      const json = await res.json();
      if (!json.success || !json.data) return;

      const articles = json.data;
      const tbody = document.getElementById('articlesStockTableBody');
      if (tbody) {
        tbody.innerHTML = '';
        if (articles.length === 0) {
          tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted"><i class="bi bi-box me-2"></i>Aucun article en stock. Cliquez sur "+ Nouvel article" pour enregistrer un matériel.</td></tr>';
        } else {
          articles.forEach(art => {
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

          let icon = 'bi-box-seam';
          if (art.category === 'Informatique') icon = 'bi-laptop';
          else if (art.category === 'Papeterie') icon = 'bi-journal-text';
          else if (art.category === 'Gadgets') icon = 'bi-tag';

          const tr = document.createElement('tr');
          tr.setAttribute('data-cat', art.category);
          tr.setAttribute('data-status', statusKey);
          tr.innerHTML = `
            <td class="text-muted fw-medium">${art.code}</td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="p-1 rounded bg-light text-primary"><i class="bi ${icon}"></i></div>
                <span class="fw-semibold text-dark">${art.name}</span>
              </div>
            </td>
            <td>${art.category}</td>
            <td>${art.unit}</td>
            <td class="fw-bold ${qtyClass}">${art.quantity}</td>
            <td class="text-muted">${art.threshold}</td>
            <td>${badgeHtml}</td>
            <td class="text-end">
              <button class="btn-gp-icon admin-only-feature" title="Modifier l'article"><i class="bi bi-three-dots"></i></button>
              <button class="btn btn-sm btn-outline-primary collaborator-only-feature py-0 px-2" onclick="showToast('${art.name} sélectionné pour votre demande !', 'info'); navigateToView('request-new');"><i class="bi bi-plus-lg me-1"></i> Demander</button>
            </td>
          `;
          tbody.appendChild(tr);
        });
        }
      }

      // Mettre à jour les options de sélection pour le mouvement de stock
      const mvtSelect = document.getElementById('mvtArticle');
      if (mvtSelect) {
        mvtSelect.innerHTML = '<option value="">Sélectionner un article du stock...</option>';
        articles.forEach(art => {
          const opt = document.createElement('option');
          opt.value = art.id;
          opt.textContent = `${art.code} - ${art.name} (${art.quantity} en stock)`;
          mvtSelect.appendChild(opt);
        });
      }

      this.renderSelectableArticles(articles);

      // Mettre à jour le widget "Catalogue rapide" du dashboard Collaborateur
      const quickCatalog = document.getElementById('collabQuickCatalogList');
      if (quickCatalog) {
        quickCatalog.innerHTML = '';
        const availableArticles = articles.filter(a => a.quantity > 0).slice(0, 4);
        if (availableArticles.length === 0) {
          quickCatalog.innerHTML = '<div class="p-3 text-center text-muted small bg-light rounded"><i class="bi bi-box me-1"></i> Aucun article disponible dans le catalogue pour le moment.</div>';
        } else {
          availableArticles.forEach(art => {
            let icon = 'bi-box-seam';
            if (art.category === 'Informatique') icon = 'bi-laptop';
            else if (art.category === 'Papeterie') icon = 'bi-journal-text';
            else if (art.category === 'Gadgets') icon = 'bi-tag';

            const card = document.createElement('div');
            card.className = 'supply-quick-card';
            card.innerHTML = `
              <div class="d-flex align-items-center gap-3">
                <div class="p-2 rounded bg-light text-primary"><i class="bi ${icon} fs-5"></i></div>
                <div>
                  <div class="fw-semibold text-dark" style="font-size: 0.885rem;">${art.name}</div>
                  <small class="${art.quantity <= art.threshold ? 'text-warning' : 'text-success'} fw-medium">${art.quantity} disponibles (${art.unit})</small>
                </div>
              </div>
              <button class="btn btn-sm btn-outline-primary" onclick="showToast('${art.name.replace(/'/g, "\\'")} sélectionné pour votre demande !', 'info'); navigateToView('request-new');">
                <i class="bi bi-plus-lg"></i> Demander
              </button>
            `;
            quickCatalog.appendChild(card);
          });
        }
      }

      // Mettre à jour le widget "Stocks critiques à commander" du superviseur
      const criticalList = document.getElementById('supervisorCriticalStockList');
      const criticalBadge = document.getElementById('criticalStockBadge');
      if (criticalList) {
        criticalList.innerHTML = '';
        const criticalArticles = articles.filter(a => a.quantity <= a.threshold);
        if (criticalBadge) criticalBadge.textContent = `${criticalArticles.length} alerte${criticalArticles.length > 1 ? 's' : ''}`;
        if (criticalArticles.length === 0) {
          criticalList.innerHTML = '<div class="p-3 text-center text-muted small bg-light rounded"><i class="bi bi-check-circle text-success me-1"></i> Aucun stock critique pour le moment.</div>';
        } else {
          criticalArticles.slice(0, 3).forEach(art => {
            const div = document.createElement('div');
            div.className = 'p-2 rounded bg-light border d-flex align-items-center justify-content-between';
            div.innerHTML = `
              <div>
                <strong class="text-dark d-block" style="font-size: 0.85rem;">${art.name}</strong>
                <small class="text-danger fw-semibold">${art.quantity} ${art.unit} restant(s) (Seuil: ${art.threshold})</small>
              </div>
              <button class="btn btn-sm btn-outline-warning py-0 px-2" data-bs-toggle="modal" data-bs-target="#modalNewMovement">Réapprovisionner</button>
            `;
            criticalList.appendChild(div);
          });
        }
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

    articles.forEach(art => {
      let icon = 'bi-box-seam';
      if (art.category === 'Informatique') icon = 'bi-laptop';
      else if (art.category === 'Papeterie') icon = 'bi-journal-text';
      else if (art.category === 'Gadgets') icon = 'bi-tag';

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
        <td>
          <input type="checkbox" class="form-check-input article-select-checkbox" ${art.quantity === 0 ? 'disabled' : ''}>
        </td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <i class="bi ${icon} text-primary"></i>
            <span class="fw-semibold text-dark">${art.name}</span>
          </div>
        </td>
        <td>${art.category}</td>
        <td class="text-center">
          <span class="badge ${badgeBg}">${art.quantity} ${art.unit}</span>
        </td>
        <td>
          <div class="input-group input-group-sm">
            <button class="btn btn-outline-secondary qty-btn-minus" type="button">-</button>
            <input type="number" class="form-control text-center qty-input" value="1" min="1" max="${art.quantity}" ${art.quantity === 0 ? 'disabled' : ''}>
            <button class="btn btn-outline-secondary qty-btn-plus" type="button">+</button>
          </div>
        </td>
      `;
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
      const pending = requests.filter(r => r.status === 'En attente');
      const pendingCount = AppState.currentRole === 'collaborator' 
        ? requests.filter(r => r.status === 'En attente' && (!AppState.currentUser || r.user_id == AppState.currentUser.id)).length
        : pending.length;

      // Mettre à jour dynamiquement le compteur de demandes du profil
      const profCount = document.getElementById('profileCardDemandesCount');
      if (profCount) {
        const myCount = AppState.currentUser ? requests.filter(r => r.user_id == AppState.currentUser.id).length : requests.length;
        profCount.textContent = `${myCount} demande${myCount > 1 ? 's' : ''}`;
      }

      // Mettre à jour dynamiquement la notification devant "Demandes" et la cloche
      const sidebarBadge = document.getElementById('sidebarRequestsBadge');
      if (sidebarBadge) {
        sidebarBadge.textContent = pendingCount;
      }
      const notifBadge = document.getElementById('notifCountBadge');
      if (notifBadge) {
        notifBadge.textContent = pendingCount;
      }
      const dotAlert = document.getElementById('topbarNotifDot') || document.querySelector('.badge-dot-alert');
      if (dotAlert) {
        dotAlert.style.display = pendingCount > 0 ? 'block' : 'none';
      }

      // 1. Table superviseur (Demandes en attente)
      const supTbody = document.getElementById('supervisorPendingRequestsTableBody');
      if (supTbody) {
        supTbody.innerHTML = '';
        if (pending.length === 0) {
          supTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Aucune demande en attente de validation.</td></tr>';
        } else {
          pending.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td class="fw-semibold text-primary">${r.reference}</td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <img src="${r.user?.avatar || 'assets/avatars/aminata.svg'}" class="rounded-circle" width="24" height="24" alt="${r.user?.name || 'User'}">
                  <span class="fw-medium text-dark">${r.user?.name || 'Collaborateur'} (${r.service})</span>
                </div>
              </td>
              <td>${r.items?.length || 1} article(s) (${r.motif})</td>
              <td><span class="badge-status badge-pending"><span class="badge-status-dot"></span> En attente</span></td>
              <td class="text-end">
                <button class="btn btn-sm btn-success py-0 px-2 me-1" onclick="approveRequest('${r.reference}', this)"><i class="bi bi-check-lg"></i> Approuver & Émettre Bon</button>
                <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="openRequestDetail('${r.reference}')">Détails</button>
              </td>
            `;
            supTbody.appendChild(tr);
          });
        }
      }

      // 2. Table collaborateur (Mes demandes récentes)
      const collabTbody = document.getElementById('collaboratorRecentRequestsTableBody');
      if (collabTbody) {
        collabTbody.innerHTML = '';
        requests.slice(0, 5).forEach(r => {
          let badge = '<span class="badge-status badge-pending"><span class="badge-status-dot"></span> En attente</span>';
          if (r.status === 'Approuvée') badge = '<span class="badge-status badge-approved"><span class="badge-status-dot"></span> Approuvée</span>';
          else if (r.status === 'Rejetée') badge = '<span class="badge-status badge-rejected"><span class="badge-status-dot"></span> Rejetée</span>';

          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td class="fw-semibold text-primary">${r.reference}</td>
            <td>${r.created_at ? new Date(r.created_at).toLocaleDateString('fr-FR', {day:'2-digit', month:'short', year:'numeric'}) : 'Récemment'}</td>
            <td>${r.items?.length || 1} article(s) (${r.motif})</td>
            <td>${badge}</td>
            <td class="text-end">
              ${r.status === 'Approuvée' || r.status === 'Restituée' ? `<button class="btn btn-sm btn-outline-secondary py-0 px-2 me-1" onclick="openPdfModal('${r.reference}')">Bon PDF</button>` : ''}
              <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="openRequestDetail('${r.reference}')">Détails</button>
            </td>
          `;
          collabTbody.appendChild(tr);
        });
      }

      // 3. Table de la vue "Toutes les demandes"
      const allTbody = document.getElementById('allRequestsTableBody');
      if (allTbody) {
        allTbody.innerHTML = '';
        requests.forEach(r => {
          let badge = '<span class="badge-status badge-pending"><span class="badge-status-dot"></span> En attente</span>';
          if (r.status === 'Approuvée') badge = '<span class="badge-status badge-approved"><span class="badge-status-dot"></span> Approuvée</span>';
          else if (r.status === 'Rejetée') badge = '<span class="badge-status badge-rejected"><span class="badge-status-dot"></span> Rejetée</span>';

          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td class="fw-bold text-primary">${r.reference}</td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <img src="${r.user?.avatar || 'assets/avatars/aminata.svg'}" class="rounded-circle" width="26" height="26" alt="${r.user?.name || 'User'}">
                <span class="fw-medium text-dark">${r.user?.name || 'Collaborateur'}</span>
              </div>
            </td>
            <td>${r.service}</td>
            <td>${r.created_at ? new Date(r.created_at).toLocaleDateString('fr-FR') : 'Aujourd\'hui'}</td>
            <td>${r.items?.length || 1} article(s)</td>
            <td>${badge}</td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-primary" onclick="openRequestDetail('${r.reference}')">Consulter</button>
            </td>
          `;
          allTbody.appendChild(tr);
        });
      }

      document.querySelectorAll('[data-view-target]').forEach(link => {
        link.onclick = (e) => {
          e.preventDefault();
          navigateToView(link.getAttribute('data-view-target'));
        };
      });
    } catch (e) {
      console.warn('loadRequests error:', e);
    }
  },

  async loadMovements() {
    try {
      const res = await fetch(`${this.baseUrl}/movements`);
      if (!res.ok) return;
      const json = await res.json();
      if (!json.success || !json.data) return;

      const movements = json.data;
      const tbody = document.getElementById('stockMovementsTableBody');
      if (tbody) {
        tbody.innerHTML = '';
        movements.forEach(m => {
          const isEntree = m.type === 'Entrée';
          const sourceLabels = {
            initial_stock: 'Stock initial',
            manual_adjustment: 'Ajustement manuel',
            request_approval: `Demande ${m.material_request?.reference || ''}`.trim(),
            request_return: `Restitution ${m.material_request?.reference || ''}`.trim()
          };
          const sourceLabel = sourceLabels[m.source] || 'Ajustement manuel';
          const tr = document.createElement('tr');
          tr.setAttribute('data-type', m.type);
          tr.innerHTML = `
            <td class="text-muted small">${m.created_at ? new Date(m.created_at).toLocaleDateString('fr-FR') : 'Aujourd\'hui'}</td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <i class="bi ${isEntree ? 'bi-arrow-down-left text-success' : 'bi-arrow-up-right text-danger'} fs-5"></i>
                <span class="fw-semibold text-dark">${m.article?.name || 'Article en stock'}</span>
              </div>
            </td>
            <td><span class="badge ${isEntree ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'} border">${m.type}</span></td>
            <td class="fw-bold ${isEntree ? 'text-success' : 'text-danger'}">${isEntree ? '+' : '-'}${m.quantity}</td>
            <td><span class="badge bg-light text-secondary border">${sourceLabel}</span></td>
            <td>${m.user?.name || 'Administrateur'}</td>
            <td>${m.reason}</td>
          `;
          tbody.appendChild(tr);
        });
      }
    } catch (e) {
      console.warn('loadMovements error:', e);
    }
  },

  async loadDocuments() {
    // Ne pas charger ni exposer les documents si le profil est collaborateur
    if (AppState.currentRole !== 'admin') {
      return;
    }

    try {
      const res = await fetch(`${this.baseUrl}/documents`);
      if (!res.ok) return;
      const json = await res.json();
      if (!json.success || !json.data) return;

      const docs = json.data;
      const tbody = document.getElementById('documentsTableBody');
      if (tbody) {
        tbody.innerHTML = '';
        if (docs.length === 0) {
          tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-folder2-open me-2"></i>Aucun document administratif archivé.</td></tr>';
        } else {
          docs.forEach(doc => {
            const tr = document.createElement('tr');
            tr.setAttribute('data-cat', doc.category);
            tr.setAttribute('data-type', doc.type);
            tr.setAttribute('data-service', doc.service);
            tr.innerHTML = `
              <td>
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-file-earmark-pdf text-danger fs-5"></i>
                  <span class="fw-semibold text-dark">${doc.title}</span>
                </div>
              </td>
              <td>${doc.category}</td>
              <td><span class="badge bg-light text-secondary border">${doc.type}</span></td>
              <td>${doc.service}</td>
              <td class="text-muted small">${doc.created_at ? new Date(doc.created_at).toLocaleDateString('fr-FR') : 'Aujourd\'hui'}</td>
              <td class="text-end">
                <button class="btn-gp-icon" title="Consulter le document" onclick="${doc.material_request?.reference ? `openPdfModal('${doc.material_request.reference}')` : `showToast('Ce document ne possède pas d’aperçu imprimable.', 'info')`}"><i class="bi bi-download"></i></button>
                <button class="btn-gp-icon" title="Options"><i class="bi bi-three-dots"></i></button>
              </td>
            `;
            tbody.appendChild(tr);
          });
        }
      }

      // Mettre à jour le widget "Documents administratifs" du dashboard Collaborateur
      const quickDocs = document.getElementById('collabQuickDocumentsList');
      if (quickDocs) {
        quickDocs.innerHTML = '';
        if (docs.length === 0) {
          quickDocs.innerHTML = '<div class="p-3 text-center text-muted small bg-light rounded"><i class="bi bi-folder2-open me-1"></i> Aucun document administratif disponible pour le moment.</div>';
        } else {
          docs.slice(0, 3).forEach(doc => {
            const docLink = document.createElement('a');
            docLink.href = '#';
            docLink.className = 'p-2 rounded bg-light text-decoration-none text-dark d-flex align-items-center justify-content-between';
            docLink.innerHTML = `
              <span class="d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-pdf text-danger"></i>
                ${doc.title}
              </span>
              <i class="bi bi-download text-muted"></i>
            `;
            docLink.onclick = (e) => {
              e.preventDefault();
              showToast(`Téléchargement de : ${doc.title}...`, 'info');
            };
            quickDocs.appendChild(docLink);
          });
        }
      }
    } catch (e) {
      console.warn('loadDocuments error:', e);
    }
  },

  async loadDashboard() {
    try {
      const userParam = AppState.currentUser?.id ? `&user_id=${AppState.currentUser.id}` : '';
      const res = await fetch(`${this.baseUrl}/dashboard?role=${AppState.currentRole}${userParam}`);
      if (!res.ok) return;
      const json = await res.json();
      if (!json.success || !json.kpis) return;

      const kpis = json.kpis;
      const elTot = document.getElementById('kpiTotalArticles');
      const elLow = document.getElementById('kpiLowStock');
      const elPend = document.getElementById('kpiPendingRequests');
      const elAppr = document.getElementById('kpiApprovedRequests');

      if (elTot && kpis.total_articles !== undefined) elTot.textContent = kpis.total_articles;
      if (elLow && kpis.low_stock_count !== undefined) elLow.textContent = kpis.low_stock_count;
      if (elPend && kpis.pending_requests !== undefined) elPend.textContent = kpis.pending_requests;
      if (elAppr && kpis.approved_requests !== undefined) elAppr.textContent = kpis.approved_requests;

      const elCollabInProg = document.getElementById('kpiCollabInProgress');
      const elCollabApproved = document.getElementById('kpiCollabApproved');
      const elCollabReceived = document.getElementById('kpiCollabReceivedMonth');
      const elCollabLatestRef = document.getElementById('kpiCollabLatestRef');

      if (elCollabInProg && kpis.requests_in_progress !== undefined) {
        elCollabInProg.textContent = kpis.requests_in_progress;
        const collabBadge = document.getElementById('kpiCollabInProgressBadge');
        if (collabBadge) {
          const val = Number(kpis.requests_in_progress || 0);
          collabBadge.style.display = val > 0 ? 'inline-block' : 'none';
          collabBadge.textContent = `${val} en attente`;
        }
      }
      if (elCollabApproved && kpis.requests_approved !== undefined) elCollabApproved.textContent = kpis.requests_approved;
      if (elCollabReceived && kpis.articles_received_month !== undefined) elCollabReceived.textContent = kpis.articles_received_month;
      if (elCollabLatestRef && kpis.latest_request !== undefined) {
        elCollabLatestRef.textContent = kpis.latest_request || '-';
        const latestStatus = document.getElementById('kpiCollabLatestStatus');
        if (latestStatus) {
          latestStatus.style.display = (kpis.latest_request && kpis.latest_request !== '-') ? 'inline-block' : 'none';
        }
      }

      // Synchroniser le badge du menu Demandes et de notification
      const activePendingCount = AppState.currentRole === 'collaborator'
        ? Number(kpis.requests_in_progress || 0)
        : Number(kpis.pending_requests || 0);

      const sidebarBadge = document.getElementById('sidebarRequestsBadge');
      if (sidebarBadge) {
        sidebarBadge.textContent = activePendingCount;
      }
      const notifBadge = document.getElementById('notifCountBadge');
      if (notifBadge) {
        notifBadge.textContent = activePendingCount;
      }
      const dotAlert = document.getElementById('topbarNotifDot') || document.querySelector('.badge-dot-alert');
      if (dotAlert) {
        dotAlert.style.display = activePendingCount > 0 ? 'block' : 'none';
      }

      // Mettre à jour les graphiques Chart.js dynamiques
      if (typeof initDashboardCharts === 'function') {
        initDashboardCharts({
          categories: json.categories_distribution || [],
          totalArticles: Number(kpis.total_articles || 0)
        });
      }
    } catch (e) {
      console.warn('loadDashboard error:', e);
    }
  },

  async approveRequest(requestId, triggerBtn = null) {
    if (!this.isOnline || !this.isDbConnected) {
      showToast('La base de données est indisponible. Le bon ne peut pas être généré.', 'danger');
      return false;
    }
    try {
      const res = await fetch(`${this.baseUrl}/requests/${requestId}/approve`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ approved_by: AppState.currentUser?.id || null })
      });
      const data = await res.json();
      if (data.success) {
        showToast(`Demande ${requestId} approuvée ! Le stock a été décompté et le Bon archivé.`, 'success');
        await this.refreshAll({ showIndicator: true });
        return true;
      } else {
        showToast(data.message || 'Erreur lors de l\'approbation.', 'danger');
        return false;
      }
    } catch (e) {
      console.warn('API approve error:', e.message);
      showToast('Impossible d’approuver la demande. Vérifiez la connexion au serveur.', 'danger');
      return false;
    }
  },

  async rejectRequest(requestId, reason = '') {
    if (!this.isOnline || !this.isDbConnected) return;
    try {
      const res = await fetch(`${this.baseUrl}/requests/${requestId}/reject`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ reason: reason || 'Demande non validée par le gestionnaire' })
      });
      const data = await res.json();
      if (data.success) {
        showToast(`Demande ${requestId} rejetée. Le collaborateur a été notifié.`, 'info');
        await this.refreshAll({ showIndicator: true });
      } else {
        showToast(data.message || 'Erreur lors du rejet.', 'danger');
      }
    } catch (e) {
      console.warn('API reject error:', e.message);
    }
  },

  async returnRequest(requestId) {
    if (!this.isOnline || !this.isDbConnected) return;
    try {
      const res = await fetch(`${this.baseUrl}/requests/${requestId}/return`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        }
      });
      const data = await res.json();
      if (data.success) {
        showToast(`Demande ${requestId} marquée comme Restituée ! Le matériel a été réintégré en stock.`, 'success');
        await this.refreshAll({ showIndicator: true });
      } else {
        showToast(data.message || 'Erreur lors de la restitution.', 'danger');
      }
    } catch (e) {
      console.warn('API return error:', e.message);
    }
  }
};

