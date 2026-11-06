<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GESTION+ | Logiciel de Gestion des Stocks, Demandes & Documents</title>
  
  <!-- Polices Google : Plus Jakarta Sans & Inter (Rendu naturel et typographie d'entreprise) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Feuille de styles personnalisée GESTION+ -->
  <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}">
</head>
<body>

  <div id="globalSavingIndicator" class="global-saving-indicator" role="status" aria-live="polite" aria-hidden="true">
    <span class="saving-spinner" aria-hidden="true"></span>
    <span class="saving-indicator-copy">
      <strong id="globalSavingIndicatorLabel">Enregistrement en cours…</strong>
      <small>Veuillez patienter</small>
    </span>
    <span class="saving-progress" aria-hidden="true"></span>
  </div>

  <!-- ==========================================================================
       CONTAINER PRINCIPAL APPLICATIF (SIDEBAR + TOPBAR + VUES METIER)
       ========================================================================== -->
  <div class="app-container" id="appMainContainer">

    <!-- ========================================================================
         SIDEBAR NAVIGATION
         ======================================================================== -->
    <aside class="app-sidebar">
      <div class="sidebar-header">
        <a href="#dashboard" class="brand-logo" data-view-target="dashboard">
          <div class="brand-icon">
            <i class="bi bi-box-seam"></i>
          </div>
          <span class="brand-text">GESTION<span class="text-primary">+</span></span>
        </a>
      </div>

      <nav class="sidebar-menu">
        <div class="sidebar-heading">Pilotage & Opérations</div>
        
        <a class="nav-item-link active" data-view-target="dashboard" href="#dashboard">
          <i class="bi bi-grid-1x2"></i>
          <span>Tableau de bord</span>
        </a>

        <a class="nav-item-link" data-view-target="stock-articles" href="#stock-articles">
          <i class="bi bi-box"></i>
          <span>Stocks</span>
        </a>

        <a class="nav-item-link" data-view-target="requests-list" href="#requests-list">
          <i class="bi bi-file-earmark-text"></i>
          <span>Demandes</span>
          <span class="nav-badge bg-primary text-white" id="sidebarRequestsBadge">0</span>
        </a>

        <a class="nav-item-link admin-only-feature" data-permission="documents.manage" data-view-target="documents" href="#documents">
          <i class="bi bi-folder2-open"></i>
          <span>Documents</span>
        </a>

        <a class="nav-item-link admin-only-feature" data-permission="assets.manage,vehicles.manage,expenses.manage,expenses.validate,inventory.manage,inventory.validate,reports.view" data-view-target="operations" href="#operations">
          <i class="bi bi-buildings"></i>
          <span>Opérations & rapports</span>
        </a>

        <div class="sidebar-heading admin-only-feature">Administration</div>

        <a class="nav-item-link admin-only-feature superadmin-only-feature" data-view-target="users" href="#users">
          <i class="bi bi-people"></i>
          <span>Utilisateurs</span>
        </a>

        <a class="nav-item-link admin-only-feature superadmin-only-feature" data-view-target="settings" href="#settings">
          <i class="bi bi-gear"></i>
          <span>Paramètres</span>
        </a>
      </nav>

      <div class="sidebar-footer">
        <div class="d-flex align-items-center gap-2 p-2 rounded" style="background: rgba(255,255,255,0.05);">
          <i class="bi bi-shield-check text-success fs-5"></i>
          <div style="font-size: 0.72rem; line-height: 1.2;">
            <div class="text-white fw-bold">Gestion Interne</div>
            <div class="text-muted">Espace Collaboratif</div>
          </div>
        </div>
      </div>
    </aside>

    <!-- ========================================================================
         MAIN WRAPPER (TOPBAR + CONTENU)
         ======================================================================== -->
    <div class="main-wrapper">

      <!-- TOPBAR NAVIGATION -->
      <header class="app-topbar">
        <div class="topbar-search position-relative">
          <i class="bi bi-search"></i>
          <input type="text" id="topbarGlobalSearch" placeholder="Rechercher une demande, un article, un bien, un véhicule..." autocomplete="off">
          <div id="globalSearchResultsDropdown" class="dropdown-menu shadow border-0 p-2 w-100" style="display:none; max-height: 360px; overflow-y: auto; top: 44px; left: 0;"></div>
        </div>

        <div class="topbar-right topbar-actions">
          <!-- Rôle Actif Badge -->
          <div class="d-none d-xl-flex align-items-center gap-2 py-1 px-3 rounded-pill bg-light border" style="font-size: 0.82rem; font-weight: 600; color: var(--gp-slate-700);">
            <i class="bi bi-shield-check text-primary fs-6"></i>
            <span id="topbarActiveRoleText">Profil : Administrateur</span>
          </div>

          <!-- Notification Bell -->
          <div class="btn-icon-topbar" title="Notifications" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-bell"></i>
            <span class="badge-dot-alert" id="topbarNotifDot" style="display: none;"></span>
          </div>
          <!-- Menu dropdown notifications -->
          <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-2" id="notificationsDropdown" style="width: 350px; max-height: 440px; overflow:auto;">
            <li class="px-2 py-1 fw-bold text-dark border-bottom mb-2 d-flex justify-content-between align-items-center gap-2">
              <span>Notifications <span class="badge bg-secondary-subtle text-secondary ms-1" id="notifCountBadge">0</span></span>
              <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="btnMarkAllNotificationsRead" style="font-size: 0.75rem;">Tout marquer lu</button>
            </li>
            <li class="p-3 text-center text-muted small notification-empty-state">
              <i class="bi bi-bell-slash me-1"></i> Aucune nouvelle notification
            </li>
          </ul>

          <!-- User Profile Chip with Dropdown -->
          <div class="dropdown">
            <div class="user-profile-badge" data-bs-toggle="dropdown" aria-expanded="false" style="cursor:pointer;">
              <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" id="topbarUserAvatar" style="width:34px; height:34px; font-size:0.85rem;">
                AD
              </div>
              <div class="user-info-text d-none d-sm-flex">
                <span class="user-name" id="topbarUserName">Administrateur</span>
                <span class="user-role-label" id="topbarUserRole">Direction</span>
              </div>
              <i class="bi bi-chevron-down text-muted ms-1" style="font-size: 0.75rem;"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-2">
              <li class="px-3 py-2 border-bottom">
                <div class="fw-bold text-dark" id="topbarDropdownTitle">Profil utilisateur</div>
                <small class="text-muted" id="topbarDropdownService">Service interne</small>
              </li>
              <li><a class="dropdown-item py-2" href="#profile" data-view-target="profile"><i class="bi bi-person-circle me-2"></i> Mon profil</a></li>
              <li><a class="dropdown-item py-2" href="#requests-list" data-view-target="requests-list"><i class="bi bi-file-text me-2"></i> Demandes</a></li>
              <li class="superadmin-only-feature"><a class="dropdown-item py-2" href="#settings" data-view-target="settings"><i class="bi bi-gear me-2"></i> Paramètres système</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item py-2 text-danger" href="#logout" data-view-target="logout"><i class="bi bi-box-arrow-right me-2"></i> Déconnexion</a></li>
            </ul>
          </div>
        </div>
      </header>

      <!-- ======================================================================
           ZONE DE CONTENU DYNAMIQUE (VIEWS)
           ====================================================================== -->
      <main class="content-area">

        <!-- ====================================================================
             VUE 1 : TABLEAU DE BORD (DASHBOARD)
             ==================================================================== -->
        <section id="view-dashboard" class="view-section active-view">
          
          <!-- ==================================================================
               1. DASHBOARD COLLABORATEUR (AMINATA KONÉ)
               ================================================================== -->
          <div id="collaboratorDashboardView">
            <div class="page-header-row">
              <div>
                <h1 class="page-title">Bonjour <span id="collabGreetingName">Collaborateur</span> 👋</h1>
                <p class="page-subtitle" id="collabGreetingSub">Espace Collaborateur</p>
              </div>
              <div class="d-flex align-items-center gap-2">
                <button class="btn-gp-secondary" data-view-target="requests-list">
                  <i class="bi bi-list-check"></i> Mes demandes
                </button>
                <button class="btn-gp-primary" data-view-target="request-new">
                  <i class="bi bi-plus-lg"></i> Nouvelle demande
                </button>
              </div>
            </div>

            <!-- 4 Cartes KPIs Collaborateur -->
            <div class="row g-3 mb-4">
              <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                  <div>
                    <div class="stat-title">Mes demandes en cours</div>
                    <div class="stat-value-group">
                      <span class="stat-number text-warning" id="kpiCollabInProgress">0</span>
                      <span class="trend-badge trend-positive" id="kpiCollabInProgressBadge" style="display: none;">0 en attente</span>
                    </div>
                  </div>
                  <div class="stat-icon-wrapper stat-icon-amber">
                    <i class="bi bi-clock-history"></i>
                  </div>
                </div>
              </div>

              <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                  <div>
                    <div class="stat-title">Demandes approuvées</div>
                    <div class="stat-value-group">
                      <span class="stat-number text-success" id="kpiCollabApproved">0</span>
                    </div>
                  </div>
                  <div class="stat-icon-wrapper stat-icon-green">
                    <i class="bi bi-check-circle"></i>
                  </div>
                </div>
              </div>

              <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                  <div>
                    <div class="stat-title">Articles reçus (ce mois)</div>
                    <div class="stat-value-group">
                      <span class="stat-number text-primary" id="kpiCollabReceivedMonth">0</span>
                      <span class="badge bg-light text-muted border">Fournitures</span>
                    </div>
                  </div>
                  <div class="stat-icon-wrapper stat-icon-blue">
                    <i class="bi bi-box-seam"></i>
                  </div>
                </div>
              </div>

              <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                  <div>
                    <div class="stat-title">Dernière demande</div>
                    <div class="stat-value-group">
                      <span class="stat-number fs-5 text-dark fw-bold" id="kpiCollabLatestRef">-</span>
                    </div>
                    <span class="badge-status badge-pending mt-1" id="kpiCollabLatestStatus" style="display: none;"><span class="badge-status-dot"></span> Soumise</span>
                  </div>
                  <div class="stat-icon-wrapper stat-icon-orange">
                    <i class="bi bi-file-earmark-text"></i>
                  </div>
                </div>
              </div>
            </div>

            <div class="row g-4">
              <!-- Colonne Gauche Collaborateur : Mes demandes et suivi -->
              <div class="col-12 col-lg-7">
                <!-- Card Mes Demandes Récentes -->
                <div class="gp-card mb-4">
                  <div class="gp-card-header">
                    <h2 class="gp-card-title">Mes demandes récentes</h2>
                    <a href="#requests-list" class="btn btn-sm btn-link text-decoration-none fw-semibold" data-view-target="requests-list">Voir tout l'historique</a>
                  </div>
                  <div class="table-responsive">
                    <table class="gp-table">
                      <thead>
                        <tr>
                          <th>N° Réf</th>
                          <th>Date</th>
                          <th>Articles</th>
                          <th>Statut</th>
                          <th class="text-end">Action</th>
                        </tr>
                      </thead>
                      <tbody id="collaboratorRecentRequestsTableBody"><tr><td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-inbox me-2"></i>Aucune demande récente émise.</td></tr></tbody>
                    </table>
                  </div>
                </div>

                <!-- Card Suivi en direct de ma demande en cours -->
                <div class="gp-card mb-0" id="activeRequestTrackerCard" style="display: none;">
                  <div class="d-flex align-items-center justify-content-between mb-3">
                    <h3 class="gp-card-title">Avancement de ma demande active (<span id="trackerRequestRef">-</span>)</h3>
                    <span class="badge bg-primary-subtle text-primary border" id="trackerRequestBadge">En cours</span>
                  </div>
                  <div class="p-3 bg-light rounded border mb-3">
                    <div class="request-progress-tracker mb-2">
                      <div class="request-progress-bar done" id="trackBar1" title="Demande soumise"></div>
                      <div class="request-progress-bar" id="trackBar2" title="Validation logistique"></div>
                      <div class="request-progress-bar" id="trackBar3" title="Préparation magasin"></div>
                      <div class="request-progress-bar" id="trackBar4" title="Mise à disposition"></div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Colonne Droite Collaborateur : Fournitures fréquentes & Documents -->
              <div class="col-12 col-lg-5">
                <!-- Fournitures fréquentes -->
                <div class="gp-card mb-4">
                  <div class="d-flex align-items-center justify-content-between mb-3">
                    <h3 class="gp-card-title">Catalogue rapide</h3>
                    <a href="#stock-articles" class="btn btn-sm btn-link text-decoration-none fw-semibold" data-view-target="stock-articles">Catalogue complet</a>
                  </div>

                  <div class="d-flex flex-column gap-2" id="collabQuickCatalogList">
                    <div class="p-3 text-center text-muted small bg-light rounded">
                      <i class="bi bi-box me-1"></i> Aucun article disponible dans le catalogue pour le moment.
                    </div>
                  </div>
                </div>

                <!-- Guide & Bonnes Pratiques Collaborateur -->
                <div class="gp-card mb-0">
                  <div class="d-flex align-items-center justify-content-between mb-3">
                    <h3 class="gp-card-title">Guide des dotations</h3>
                    <span class="badge bg-primary-subtle text-primary border">Info service</span>
                  </div>
                  <div class="d-flex flex-column gap-3" style="font-size: 0.83rem;">
                    <div class="d-flex align-items-start gap-2 p-2 rounded bg-light border">
                      <i class="bi bi-1-circle-fill text-primary fs-6 mt-1"></i>
                      <div>
                        <strong class="text-dark d-block">1. Soumission de votre demande</strong>
                        <span class="text-muted">Sélectionnez vos articles dans le catalogue et indiquez votre motif professionnel.</span>
                      </div>
                    </div>
                    <div class="d-flex align-items-start gap-2 p-2 rounded bg-light border">
                      <i class="bi bi-2-circle-fill text-warning fs-6 mt-1"></i>
                      <div>
                        <strong class="text-dark d-block">2. Validation par le Magasinier</strong>
                        <span class="text-muted">L'administration vérifie la disponibilité et prépare vos équipements.</span>
                      </div>
                    </div>
                    <div class="d-flex align-items-start gap-2 p-2 rounded bg-light border">
                      <i class="bi bi-3-circle-fill text-success fs-6 mt-1"></i>
                      <div>
                        <strong class="text-dark d-block">3. Retrait & Restitution</strong>
                        <span class="text-muted">Récupérez le matériel au magasin central et veillez à son bon usage.</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ==================================================================
               2. DASHBOARD ADMINISTRATEUR (JEAN OUÉDRAOGO)
               ================================================================== -->
          <div id="adminDashboardView" style="display: none;">
            <div class="page-header-row">
              <div>
                <h1 class="page-title">Supervision Générale & Magasin</h1>
                <p class="page-subtitle">Vue globale des stocks, contrôle des réquisitions et alertes d'approvisionnement</p>
              </div>
              <div class="d-flex align-items-center gap-2">
                <button class="btn-gp-secondary" data-bs-toggle="modal" data-bs-target="#modalNewMovement">
                  <i class="bi bi-sliders"></i> Ajuster le stock
                </button>
                <button class="btn-gp-primary" data-bs-toggle="modal" data-bs-target="#modalNewArticle">
                  <i class="bi bi-plus-lg"></i> Nouvel article
                </button>
              </div>
            </div>

            <!-- 4 Cartes d'indicateurs Superviseur (KPI) -->
            <div class="row g-3 mb-4">
              <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                  <div>
                    <div class="stat-title">Articles en stock</div>
                    <div class="stat-value-group">
                      <span class="stat-number" id="kpiTotalArticles">0</span>
                    </div>
                  </div>
                  <div class="stat-icon-wrapper stat-icon-blue">
                    <i class="bi bi-box-seam"></i>
                  </div>
                </div>
              </div>

              <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                  <div>
                    <div class="stat-title">Stock critique / faible</div>
                    <div class="stat-value-group">
                      <span class="stat-number text-warning" id="kpiLowStock">0</span>
                    </div>
                  </div>
                  <div class="stat-icon-wrapper stat-icon-amber">
                    <i class="bi bi-exclamation-triangle"></i>
                  </div>
                </div>
              </div>

              <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                  <div>
                    <div class="stat-title">Demandes à valider</div>
                    <div class="stat-value-group">
                      <span class="stat-number text-primary" id="kpiPendingRequests">0</span>
                    </div>
                  </div>
                  <div class="stat-icon-wrapper stat-icon-orange">
                    <i class="bi bi-clock-history"></i>
                  </div>
                </div>
              </div>

              <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                  <div>
                    <div class="stat-title">Demandes validées / traitées</div>
                    <div class="stat-value-group">
                      <span class="stat-number text-success" id="kpiApprovedRequests">0</span>
                    </div>
                  </div>
                  <div class="stat-icon-wrapper stat-icon-green">
                    <i class="bi bi-check-circle"></i>
                  </div>
                </div>
              </div>
            </div>

            <!-- Bandeau secondaire d'indicateurs de pilotage (Module 7) -->
            <div class="row g-3 mb-4">
              <div class="col-6 col-md-3">
                <div class="p-3 bg-white rounded border d-flex justify-content-between align-items-center">
                  <div>
                    <div class="text-muted small">Valorisation du stock</div>
                    <div class="fw-bold text-dark fs-6" id="kpiStockTotalValue">0 FCFA</div>
                  </div>
                  <i class="bi bi-cash-coin text-primary fs-4"></i>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="p-3 bg-white rounded border d-flex justify-content-between align-items-center">
                  <div>
                    <div class="text-muted small">Articles en rupture</div>
                    <div class="fw-bold text-danger fs-6" id="kpiOutOfStockCount">0</div>
                  </div>
                  <i class="bi bi-x-octagon text-danger fs-4"></i>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="p-3 bg-white rounded border d-flex justify-content-between align-items-center">
                  <div>
                    <div class="text-muted small">Raccourci pilotage</div>
                    <a href="#operations" data-view-target="operations" class="fw-semibold text-primary small text-decoration-none">Centre d'opérations & Rapports →</a>
                  </div>
                  <i class="bi bi-graph-up-arrow text-success fs-4"></i>
                </div>
              </div>
            </div>

            <!-- 2 Cartes Graphiques : Évolution & Répartition -->
            <div class="row g-3 mb-4">
              <div class="col-12 col-xl-7">
                <div class="gp-card h-100 mb-0">
                  <div class="gp-card-header">
                    <div>
                      <h2 class="gp-card-title">Évolution globale des demandes</h2>
                      <small class="text-muted">Répartition mensuelle des décisions de validation</small>
                    </div>
                  </div>
                  <div style="height: 270px; width: 100%; position: relative; overflow: hidden;">
                    <canvas id="requestsEvolutionChart"></canvas>
                  </div>
                </div>
              </div>

              <div class="col-12 col-xl-5">
                <div class="gp-card h-100 mb-0">
                  <div class="gp-card-header">
                    <div>
                      <h2 class="gp-card-title">Répartition du stock global</h2>
                      <small class="text-muted">Ventilation par famille de fournitures</small>
                    </div>
                  </div>
                  <div class="row align-items-center g-3">
                    <div class="col-12 col-sm-5 d-flex justify-content-center">
                      <div style="height: 185px; width: 100%; max-width: 185px; position: relative; overflow: hidden;">
                        <canvas id="articlesDistributionChart"></canvas>
                      </div>
                    </div>
                    <div class="col-12 col-sm-7" style="min-width: 0;">
                      <ul class="list-unstyled mb-0 d-flex flex-column gap-2" id="categoriesLegendList" style="font-size: 0.81rem;">
                        <li class="p-3 text-center text-muted small bg-light rounded">
                          <i class="bi bi-pie-chart me-1"></i> Aucun article enregistré en stock.
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Section inférieure Superviseur : Demandes à valider & Alertes stocks -->
            <div class="row g-4">
              <div class="col-12 col-lg-8">
                <div class="gp-card mb-0">
                  <div class="gp-card-header">
                    <h2 class="gp-card-title">Demandes urgentes à valider</h2>
                    <a href="#requests-list" class="btn btn-sm btn-link text-decoration-none fw-semibold" data-view-target="requests-list">Voir tout</a>
                  </div>
                  <div class="table-responsive">
                    <table class="gp-table">
                      <thead>
                        <tr>
                          <th>N° Réf</th>
                          <th>Demandeur</th>
                          <th>Date</th>
                          <th>Articles</th>
                          <th>Statut</th>
                          <th class="text-end">Validation</th>
                        </tr>
                      </thead>
                      <tbody id="supervisorPendingRequestsTableBody"><tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-clipboard-check me-2"></i>Aucune demande en attente de validation.</td></tr></tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div class="col-12 col-lg-4">
                <div class="gp-card mb-0">
                  <div class="gp-card-header">
                    <h2 class="gp-card-title">Stocks critiques à commander</h2>
                    <span class="badge bg-warning-subtle text-warning" id="criticalStockBadge">0 alerte</span>
                  </div>
                  <div class="d-flex flex-column gap-3" id="supervisorCriticalStockList">
                    <div class="p-3 text-center text-muted small bg-light rounded">
                      <i class="bi bi-check-circle text-success me-1"></i> Aucun stock critique pour le moment.
                    </div>
                  </div>
                  <button class="btn-gp-secondary w-100 justify-content-center mt-2" data-view-target="stock-articles">
                      <i class="bi bi-box-seam me-1"></i> Gérer l'inventaire complet
                    </button>
                </div>
              </div>
            </div>
          </div>

        </section>

        <!-- Centre d'opérations : patrimoine, automobile, finances, inventaires et rapports -->
        <section id="view-operations" class="view-section">
          <div class="page-header-row">
            <div>
              <h1 class="page-title">Centre d’opérations & Reporting</h1>
              <p class="page-subtitle">Patrimoine durable, parc automobile, dépenses & justificatifs, inventaires physiques et rapports périodiques</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <button class="btn-gp-secondary" type="button" id="btnOpenReportPrintPreview" data-permission="reports.view"><i class="bi bi-file-earmark-pdf text-danger"></i> Exporter PDF / Imprimer</button>
              <button class="btn-gp-primary" type="button" id="btnExportFilteredReportCsv" data-permission="reports.view"><i class="bi bi-file-earmark-excel"></i> Exporter CSV</button>
            </div>
          </div>

          <div class="settings-nav-pills flex-wrap" id="operationsTabs">
            <button class="settings-pill-btn active" type="button" data-operations-tab="overview" data-permission="assets.manage,vehicles.manage,expenses.manage,inventory.manage,reports.view"><i class="bi bi-speedometer2"></i> Synthèse</button>
            <button class="settings-pill-btn" type="button" data-operations-tab="assets" data-permission="assets.manage"><i class="bi bi-pc-display"></i> Patrimoine & Équipements</button>
            <button class="settings-pill-btn" type="button" data-operations-tab="vehicles" data-permission="vehicles.manage"><i class="bi bi-car-front"></i> Parc automobile</button>
            <button class="settings-pill-btn" type="button" data-operations-tab="expenses" data-permission="expenses.manage,expenses.validate"><i class="bi bi-cash-stack"></i> Dépenses & Décaissements</button>
            <button class="settings-pill-btn" type="button" data-operations-tab="inventories" data-permission="inventory.manage,inventory.validate"><i class="bi bi-clipboard-check"></i> Inventaires physiques</button>
            <button class="settings-pill-btn" type="button" data-operations-tab="reports" data-permission="reports.view"><i class="bi bi-bar-chart"></i> Rapports & Statistiques</button>
          </div>

          <!-- ONGLET 1 : SYNTHÈSE OPÉRATIONNELLE -->
          <div id="operationsPane-overview" class="operations-tab-pane">
            <div class="row g-3 mb-4">
              <div class="col-6 col-xl-3">
                <div class="gp-card h-100 mb-0">
                  <div class="text-muted small">Patrimoine & Biens durables</div>
                  <div class="fs-2 fw-bold" id="opsAssetsTotal">0</div>
                  <div class="small text-primary" id="opsAssetsAssigned">0 affecté(s)</div>
                  <div class="small text-danger mt-1" id="opsAssetsIncidents">0 incident(s) ouvert(s)</div>
                </div>
              </div>
              <div class="col-6 col-xl-3">
                <div class="gp-card h-100 mb-0">
                  <div class="text-muted small">Parc automobile</div>
                  <div class="fs-2 fw-bold" id="opsVehiclesTotal">0</div>
                  <div class="small text-warning fw-semibold" id="opsVehicleAlerts">0 alerte(s) d'échéance / km</div>
                  <div class="small text-muted mt-1" id="opsVehiclesInService">0 en service</div>
                </div>
              </div>
              <div class="col-6 col-xl-3">
                <div class="gp-card h-100 mb-0">
                  <div class="text-muted small">Dépenses décaissées</div>
                  <div class="fs-4 fw-bold text-dark" id="opsDisbursed">0 FCFA</div>
                  <div class="small text-danger fw-semibold" id="opsUnjustified">0 FCFA à justifier</div>
                  <div class="small text-warning mt-1" id="opsExpenseOverruns">0 dépassement(s)</div>
                </div>
              </div>
              <div class="col-6 col-xl-3">
                <div class="gp-card h-100 mb-0">
                  <div class="text-muted small">Inventaires</div>
                  <div class="fs-2 fw-bold" id="opsInventoryOpen">0</div>
                  <div class="small text-warning" id="opsInventoryVariance">0 écart(s) d'inventaire</div>
                </div>
              </div>
            </div>
            <div class="gp-card">
              <h3 class="gp-card-title mb-3"><i class="bi bi-bell text-warning me-2"></i>Alertes opérationnelles & Échéances prioritaires</h3>
              <div id="operationsAlerts" class="text-muted">Aucune alerte active.</div>
            </div>
          </div>

          <!-- ONGLET 2 : MODULE 4 - PATRIMOINE ET ÉQUIPEMENTS -->
          <div id="operationsPane-assets" class="operations-tab-pane" style="display:none">
            <div class="gp-card mb-3">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h3 class="gp-card-title mb-0">Enregistrer un bien durable au patrimoine</h3>
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" class="btn-gp-secondary btn-sm" onclick="exportTableAsPdf('assets')">
                    <i class="bi bi-file-earmark-pdf text-danger"></i> Exporter PDF
                  </button>
                  <button type="button" class="btn-gp-secondary btn-sm" onclick="downloadAuthenticatedFile('/api/assets-export', 'etat-patrimoine.csv')">
                    <i class="bi bi-file-earmark-excel text-success"></i> Exporter CSV
                  </button>
                </div>
              </div>
              <form id="formNewAsset" class="row g-3">
                <div class="col-12 col-md-4">
                  <label class="form-label small fw-semibold">Désignation du bien <span class="text-danger">*</span></label>
                  <input name="name" class="form-control" placeholder="Ex: Ordinateur Portable Dell Latitude" required>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label small fw-semibold">Catégorie <span class="text-danger">*</span></label>
                  <select name="category" class="form-select" required>
                    <option value="Matériel informatique">Matériel informatique</option>
                    <option value="Mobilier de bureau">Mobilier de bureau</option>
                    <option value="Équipement réseau & télécom">Équipement réseau & télécom</option>
                    <option value="Climatisation & Énergie">Climatisation & Énergie</option>
                    <option value="Équipement audiovisuel">Équipement audiovisuel</option>
                    <option value="Équipement de cuisine / entretien">Équipement de cuisine / entretien</option>
                    <option value="Autre équipement durable">Autre équipement durable</option>
                  </select>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label small fw-semibold">N° de série constructeur</label>
                  <input name="serial_number" class="form-control" placeholder="S/N fabricant (optionnel)">
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                  <label class="form-label small fw-semibold">État physique</label>
                  <select name="condition" class="form-select">
                    <option value="Neuf">Neuf</option>
                    <option value="Bon état">Bon état</option>
                    <option value="Endommagé">Endommagé</option>
                    <option value="Hors service">Hors service</option>
                  </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                  <label class="form-label small fw-semibold">Date d’acquisition</label>
                  <input name="acquired_at" type="date" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                  <label class="form-label small fw-semibold">Coût d’acquisition</label>
                  <input name="acquisition_cost" type="number" min="0" step="0.01" class="form-control" placeholder="FCFA">
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                  <label class="form-label small fw-semibold">Service affecté</label>
                  <input name="department" class="form-control" placeholder="Ex: Informatique">
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                  <label class="form-label small fw-semibold">Site / Bureau</label>
                  <input name="location" class="form-control" placeholder="Ex: Siège - Bureau 204">
                </div>
                <div class="col-12 col-sm-6 col-lg-4 d-flex align-items-end">
                  <button class="btn-gp-primary w-100 justify-content-center" type="submit"><i class="bi bi-plus-lg"></i> Enregistrer</button>
                </div>
              </form>
            </div>

            <div class="gp-table-container">
              <div class="gp-table-toolbar">
                <div class="toolbar-filters">
                  <select id="filterAssetCondition" class="form-select-sm-custom">
                    <option value="">Tous les états</option>
                    <option value="Neuf">Neuf</option>
                    <option value="Bon état">Bon état</option>
                    <option value="Endommagé">Endommagé</option>
                    <option value="Hors service">Hors service</option>
                  </select>
                  <select id="filterAssetAvailability" class="form-select-sm-custom">
                    <option value="">Toute disponibilité</option>
                    <option value="Disponible">Disponible</option>
                    <option value="Affecté">Affecté</option>
                  </select>
                </div>
                <div class="table-search-box">
                  <i class="bi bi-search"></i>
                  <input type="text" id="searchAssetInput" class="form-input-sm-custom" placeholder="Rechercher n° inventaire, série, détenteur...">
                </div>
              </div>
              <div class="table-responsive">
                <table class="gp-table">
                  <thead>
                    <tr>
                      <th>N° Inventaire</th>
                      <th>Équipement & Série</th>
                      <th>Catégorie</th>
                      <th>Acquisition</th>
                      <th>État</th>
                      <th>Disponibilité</th>
                      <th>Affectation (Service / Détenteur)</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="assetsTableBody"></tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- ONGLET 3 : MODULE 5 - PARC AUTOMOBILE -->
          <div id="operationsPane-vehicles" class="operations-tab-pane" style="display:none">
            <div class="row g-3 mb-3">
              <div class="col-6 col-md-4 col-xxl-2"><div class="p-3 bg-white border rounded h-100"><div class="text-muted small">Total Parc</div><div class="fs-4 fw-bold" id="fleetKpiTotal">0</div></div></div>
              <div class="col-6 col-md-4 col-xxl-2"><div class="p-3 bg-white border rounded h-100"><div class="text-muted small">Disponibles</div><div class="fs-4 fw-bold text-success" id="fleetKpiAvailable">0</div></div></div>
              <div class="col-6 col-md-4 col-xxl-2"><div class="p-3 bg-white border rounded h-100"><div class="text-muted small">En service</div><div class="fs-4 fw-bold text-primary" id="fleetKpiInService">0</div></div></div>
              <div class="col-6 col-md-4 col-xxl-2"><div class="p-3 bg-white border rounded h-100"><div class="text-muted small">Maintenance / Immob.</div><div class="fs-4 fw-bold text-warning" id="fleetKpiMaintenance">0</div></div></div>
              <div class="col-6 col-md-4 col-xxl-2"><div class="p-3 bg-white border rounded h-100"><div class="text-muted small">Alertes échéance/km</div><div class="fs-4 fw-bold text-danger" id="fleetKpiAlerts">0</div></div></div>
              <div class="col-6 col-md-4 col-xxl-2"><div class="p-3 bg-white border rounded h-100"><div class="text-muted small">Coût entretiens</div><div class="fs-6 fw-bold text-dark" id="fleetKpiCost">0 FCFA</div></div></div>
            </div>

            <div class="gp-card mb-3">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h3 class="gp-card-title mb-0">Enregistrer un véhicule au parc automobile</h3>
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" class="btn-gp-secondary btn-sm" onclick="exportTableAsPdf('vehicles')">
                    <i class="bi bi-file-earmark-pdf text-danger"></i> Exporter PDF
                  </button>
                  <button type="button" class="btn-gp-secondary btn-sm" onclick="downloadAuthenticatedFile('/api/vehicles-export', 'parc-automobile.csv')">
                    <i class="bi bi-file-earmark-excel text-success"></i> Exporter CSV
                  </button>
                </div>
              </div>
              <form id="formNewVehicle" class="row g-3">
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label small fw-semibold">Immatriculation <span class="text-danger">*</span></label>
                  <input name="registration_number" class="form-control" placeholder="Ex: 1234 AB 01" required>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label small fw-semibold">Marque <span class="text-danger">*</span></label>
                  <input name="brand" class="form-control" placeholder="Toyota" required>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label small fw-semibold">Modèle <span class="text-danger">*</span></label>
                  <input name="model" class="form-control" placeholder="Hilux" required>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label small fw-semibold">Type</label>
                  <select name="type" class="form-select">
                    <option value="Berline">Berline</option>
                    <option value="SUV / 4x4">SUV / 4x4</option>
                    <option value="Pick-up">Pick-up</option>
                    <option value="Utilitaire / Fourgon">Utilitaire / Fourgon</option>
                    <option value="Minibus">Minibus</option>
                    <option value="Moto de liaison">Moto de liaison</option>
                  </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label small fw-semibold">N° Carte grise</label>
                  <input name="registration_card_number" class="form-control" placeholder="Optionnel">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label small fw-semibold">Kilométrage actuel</label>
                  <input name="mileage" type="number" min="0" value="0" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label small fw-semibold">État</label>
                  <select name="status" class="form-select">
                    <option value="Disponible">Disponible</option>
                    <option value="En service">En service</option>
                    <option value="Maintenance">Maintenance</option>
                    <option value="Immobilisé">Immobilisé</option>
                  </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label small fw-semibold">Service affecté</label>
                  <input name="department" class="form-control" placeholder="Ex: Direction">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label small fw-semibold">Conducteur</label>
                  <select name="driver_id" id="vehicleDriverSelect" class="form-select">
                    <option value="">Aucun conducteur</option>
                  </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label small fw-semibold">Échéance Assurance</label>
                  <input name="insurance_expires_at" type="date" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label small fw-semibold">Visite technique</label>
                  <input name="inspection_expires_at" type="date" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label small fw-semibold">Vignette</label>
                  <input name="tax_expires_at" type="date" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label small fw-semibold">Prochain entretien (Date)</label>
                  <input name="next_maintenance_at" type="date" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label small fw-semibold">Prochain entretien (Seuil Km)</label>
                  <input name="next_maintenance_mileage" type="number" min="0" class="form-control" placeholder="Ex: 60000">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label small fw-semibold">Observations</label>
                  <input name="notes" class="form-control" placeholder="Notes sur l'état ou l'équipement embarqué">
                </div>
                <div class="col-12 col-sm-6 col-lg-3 d-flex align-items-end">
                  <button class="btn-gp-primary w-100 justify-content-center" type="submit"><i class="bi bi-plus-lg"></i> Ajouter</button>
                </div>
              </form>
            </div>

            <div class="gp-table-container">
              <div class="table-responsive">
                <table class="gp-table">
                  <thead>
                    <tr>
                      <th>Immatriculation</th>
                      <th>Véhicule & Type</th>
                      <th>Kilométrage</th>
                      <th>État</th>
                      <th>Service & Conducteur</th>
                      <th>Échéances & Alertes</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="vehiclesTableBody"></tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- ONGLET 4 : MODULE 6 - DÉPENSES ET DÉCAISSEMENTS -->
          <div id="operationsPane-expenses" class="operations-tab-pane" style="display:none">
            <div class="row g-3 mb-3">
              <div class="col-6 col-md-4 col-xxl-2"><div class="p-3 bg-white border rounded h-100"><div class="text-muted small">Estimatif</div><div class="fw-bold" id="expKpiEstimated">0 FCFA</div></div></div>
              <div class="col-6 col-md-4 col-xxl-2"><div class="p-3 bg-white border rounded h-100"><div class="text-muted small">Autorisé</div><div class="fw-bold text-primary" id="expKpiAuthorized">0 FCFA</div></div></div>
              <div class="col-6 col-md-4 col-xxl-2"><div class="p-3 bg-white border rounded h-100"><div class="text-muted small">Engagé</div><div class="fw-bold" id="expKpiCommitted">0 FCFA</div></div></div>
              <div class="col-6 col-md-4 col-xxl-2"><div class="p-3 bg-white border rounded h-100"><div class="text-muted small">Décaissé</div><div class="fw-bold text-dark" id="expKpiDisbursed">0 FCFA</div></div></div>
              <div class="col-6 col-md-4 col-xxl-2"><div class="p-3 bg-white border rounded h-100"><div class="text-muted small">Justifié</div><div class="fw-bold text-success" id="expKpiJustified">0 FCFA</div></div></div>
              <div class="col-6 col-md-4 col-xxl-2"><div class="p-3 bg-white border rounded h-100"><div class="text-muted small">Reste à justifier</div><div class="fw-bold text-danger" id="expKpiUnjustified">0 FCFA</div></div></div>
            </div>

            <div class="gp-card mb-3">
              <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h3 class="gp-card-title mb-0" data-permission="expenses.manage">Enregistrer une dépense / décaissement</h3>
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" class="btn-gp-secondary btn-sm" onclick="exportTableAsPdf('expenses')">
                    <i class="bi bi-file-earmark-pdf text-danger"></i> Exporter PDF
                  </button>
                  <button type="button" class="btn-gp-secondary btn-sm" onclick="downloadAuthenticatedFile('/api/expenses-export', 'depenses-decaissements.csv')">
                    <i class="bi bi-file-earmark-excel text-success"></i> Exporter CSV
                  </button>
                </div>
              </div>
              <form id="formNewExpense" class="row g-3" data-permission="expenses.manage" enctype="multipart/form-data">
                <div class="col-12 col-md-6 col-lg-4">
                  <label class="form-label small fw-semibold">Demande rattachée</label>
                  <select name="material_request_id" id="expenseRequestSelect" class="form-select">
                    <option value="">Sélectionner une demande…</option>
                  </select>
                  <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" name="direct_authorization" value="1" id="checkDirectExpenseAuth">
                    <label class="form-check-label small text-muted" for="checkDirectExpenseAuth">Autorisation directe (sans demande préalable)</label>
                  </div>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                  <label class="form-label small fw-semibold">Fournisseur</label>
                  <select name="supplier_id" id="expenseSupplierSelect" class="form-select">
                    <option value="">Aucun / Bénéficiaire interne</option>
                  </select>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                  <label class="form-label small fw-semibold">Bénéficiaire du paiement</label>
                  <input name="beneficiary" class="form-control" placeholder="Nom de l'agent ou prestataire">
                </div>
                <div class="col-12 col-md-6 col-lg-2">
                  <label class="form-label small fw-semibold">Mode de paiement</label>
                  <select name="payment_method" class="form-select">
                    <option value="Espèces">Espèces / Caisse</option>
                    <option value="Virement bancaire">Virement bancaire</option>
                    <option value="Chèque">Chèque</option>
                    <option value="Mobile Money">Mobile Money</option>
                    <option value="Carte entreprise">Carte entreprise</option>
                  </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                  <label class="form-label small fw-semibold">Montant estimatif</label>
                  <input name="estimated_amount" type="number" min="0" step="0.01" class="form-control" placeholder="0">
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                  <label class="form-label small fw-semibold">Montant autorisé <span class="text-danger">*</span></label>
                  <input name="authorized_amount" type="number" min="0" step="0.01" class="form-control" required placeholder="0">
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                  <label class="form-label small fw-semibold">Montant engagé</label>
                  <input name="committed_amount" type="number" min="0" step="0.01" value="0" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                  <label class="form-label small fw-semibold">Montant décaissé</label>
                  <input name="disbursed_amount" type="number" min="0" step="0.01" value="0" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                  <label class="form-label small fw-semibold">Montant justifié</label>
                  <input name="justified_amount" type="number" min="0" step="0.01" value="0" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                  <label class="form-label small fw-semibold">Type justificatif</label>
                  <select name="attachment_type" class="form-select">
                    <option value="Facture">Facture</option>
                    <option value="Reçu">Reçu de paiement</option>
                    <option value="Bon de livraison">Bon de livraison</option>
                    <option value="Devis">Devis</option>
                  </select>
                </div>
                <div class="col-12 col-md-5">
                  <label class="form-label small fw-semibold">Pièces justificatives (Facture, Reçu, BL — optionnel)</label>
                  <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label small fw-semibold">Observations / Motif</label>
                  <input name="notes" class="form-control" placeholder="Détails sur l'engagement ou le règlement">
                </div>
                <div class="col-12 col-md-3 d-flex align-items-end">
                  <button class="btn-gp-primary w-100 justify-content-center" type="submit"><i class="bi bi-plus-lg"></i> Enregistrer</button>
                </div>
              </form>
            </div>

            <div class="gp-table-container">
              <div class="gp-table-toolbar">
                <div class="toolbar-filters">
                  <select id="filterExpenseScope" class="form-select-sm-custom">
                    <option value="">Toutes les dépenses</option>
                    <option value="unjustified">Dépenses non justifiées</option>
                    <option value="overrun">Dépassements à revalider</option>
                    <option value="Validé">Opérations validées</option>
                  </select>
                </div>
                <div class="table-search-box">
                  <i class="bi bi-search"></i>
                  <input type="text" id="searchExpenseInput" class="form-input-sm-custom" placeholder="Réf, demande, fournisseur, bénéficiaire...">
                </div>
              </div>
              <div class="table-responsive">
                <table class="gp-table">
                  <thead>
                    <tr>
                      <th>Référence</th>
                      <th>Rattachement</th>
                      <th>Fournisseur / Bénéficiaire</th>
                      <th>Autorisé</th>
                      <th>Engagé / Décaissé</th>
                      <th>Justifié / Reste</th>
                      <th>Solde</th>
                      <th>Statut</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="expensesTableBody"></tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- ONGLET 5 : MODULE 3 - INVENTAIRES PHYSIQUES -->
          <div id="operationsPane-inventories" class="operations-tab-pane" style="display:none">
            <div class="gp-card mb-3" data-permission="inventory.manage">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h3 class="gp-card-title mb-0">Réaliser un inventaire physique (Général ou Partiel)</h3>
                <div class="d-flex gap-2">
                  <button type="button" class="btn-gp-secondary btn-sm" id="btnLoadAllArticlesForInventory">
                    <i class="bi bi-list-check"></i> Charger tout le stock (Inventaire Général)
                  </button>
                </div>
              </div>

              <form id="formPhysicalInventory">
                <div class="row g-3 mb-3">
                  <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label small fw-semibold">Périmètre</label>
                    <select id="inventoryScopeSelect" class="form-select">
                      <option value="Partiel">Inventaire Partiel</option>
                      <option value="Général">Inventaire Général</option>
                    </select>
                  </div>
                  <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label small fw-semibold">Date du comptage</label>
                    <input type="date" id="inventoryDateInput" class="form-control" required>
                  </div>
                  <div class="col-12 col-md-8 col-lg-4">
                    <label class="form-label small fw-semibold">Ajouter un article au comptage</label>
                    <select id="inventoryArticleSelect" class="form-select">
                      <option value="">Choisir un article à ajouter…</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-4 col-lg-2 d-flex align-items-end">
                    <button type="button" class="btn-gp-secondary w-100 justify-content-center" id="btnAddInventoryLine">
                      <i class="bi bi-plus"></i> Ajouter ligne
                    </button>
                  </div>
                </div>

                <div class="table-responsive border rounded mb-3">
                  <table class="gp-table mb-0">
                    <thead>
                      <tr>
                        <th>Code & Article</th>
                        <th class="text-center">Qté théorique</th>
                        <th class="text-center" style="width:150px;">Qté physique</th>
                        <th class="text-center">Écart</th>
                        <th>Justification de l'écart (obligatoire si écart ≠ 0)</th>
                        <th class="text-end"></th>
                      </tr>
                    </thead>
                    <tbody id="inventoryCountingTableBody">
                      <tr><td colspan="6" class="text-center text-muted py-3">Sélectionnez un article ou cliquez sur « Charger tout le stock » pour saisir le comptage physique.</td></tr>
                    </tbody>
                  </table>
                </div>

                <div class="row g-3 align-items-end">
                  <div class="col-12 col-md-9">
                    <label class="form-label small fw-semibold">Notes d'inventaire (optionnel)</label>
                    <input type="text" id="inventoryGeneralNotes" class="form-control" placeholder="Observations de la commission d'inventaire...">
                  </div>
                  <div class="col-12 col-md-3">
                    <button class="btn-gp-primary w-100 justify-content-center" type="submit">
                      <i class="bi bi-save"></i> Enregistrer l'inventaire
                    </button>
                  </div>
                </div>
              </form>
            </div>

            <div class="gp-table-container">
              <div class="table-responsive">
                <table class="gp-table">
                  <thead>
                    <tr>
                      <th>Référence</th>
                      <th>Date</th>
                      <th>Périmètre</th>
                      <th>Articles contrôlés</th>
                      <th>Écarts</th>
                      <th>Statut</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="inventoriesTableBody"></tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- ONGLET 6 : MODULE 7 - RAPPORTS & STATISTIQUES -->
          <div id="operationsPane-reports" class="operations-tab-pane" style="display:none">
            <div class="gp-card mb-3">
              <h3 class="gp-card-title mb-3">Filtres d'analyse & Comparaison de périodes</h3>
              <form id="formReportFilters" class="row g-3 align-items-end">
                <div class="col-12 col-sm-6 col-lg-4 col-xl-2">
                  <label class="form-label small fw-semibold">Du</label>
                  <input name="from" id="reportFilterFrom" type="date" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-2">
                  <label class="form-label small fw-semibold">Au</label>
                  <input name="to" id="reportFilterTo" type="date" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-2">
                  <label class="form-label small fw-semibold">Service</label>
                  <select name="service" id="reportFilterService" class="form-select">
                    <option value="">Tous les services</option>
                  </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-2">
                  <label class="form-label small fw-semibold">Catégorie demande</label>
                  <select name="category" id="reportFilterCategory" class="form-select">
                    <option value="">Toutes catégories</option>
                  </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-2">
                  <label class="form-label small fw-semibold">Statut demande</label>
                  <select name="status" id="reportFilterStatus" class="form-select">
                    <option value="">Tous les statuts</option>
                    <option value="Soumise">Soumise</option>
                    <option value="En attente de validation">En attente de validation</option>
                    <option value="Rejetée">Rejetée</option>
                    <option value="Validée">Validée</option>
                  </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-2">
                  <button class="btn-gp-primary w-100 justify-content-center" type="submit"><i class="bi bi-funnel"></i> Actualiser</button>
                </div>
              </form>
            </div>

            <div id="reportSummary" class="row g-3 mb-4"></div>

            <!-- Formulaire de génération de rapport périodique (Hebdomadaire / Mensuel / Inventaire) -->
            <div class="gp-card mb-4">
              <h3 class="gp-card-title mb-3">Générer et archiver un rapport officiel</h3>
              <form id="formGenerateSavedReport" class="row g-3">
                <div class="col-md-3">
                  <label class="form-label small fw-semibold">Type de rapport <span class="text-danger">*</span></label>
                  <select name="type" id="savedReportType" class="form-select" required>
                    <option value="Hebdomadaire">Rapport hebdomadaire</option>
                    <option value="Mensuel">Rapport mensuel</option>
                    <option value="Inventaire">Rapport d'inventaire</option>
                  </select>
                </div>
                <div class="col-md-9">
                  <label class="form-label small fw-semibold">Intitulé du rapport</label>
                  <input name="title" id="savedReportTitle" class="form-control" placeholder="Ex : Rapport mensuel d'activité">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Difficultés rencontrées sur la période</label>
                  <textarea name="difficulties" id="savedReportDifficulties" class="form-control" rows="2" placeholder="Ruptures constatées, retards fournisseurs, pannes véhicules..."></textarea>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Actions à mener & Recommandations</label>
                  <textarea name="action_items" id="savedReportActions" class="form-control" rows="2" placeholder="Commandes prioritaires, relance des justificatifs en attente..."></textarea>
                </div>
                <div class="col-12 d-flex justify-content-end">
                  <button type="submit" class="btn-gp-primary"><i class="bi bi-archive"></i> Générer & Archiver le rapport</button>
                </div>
              </form>
            </div>

            <!-- Historique des rapports générés -->
            <div class="gp-card mb-0">
              <h3 class="gp-card-title mb-3">Historique des rapports archivés</h3>
              <div class="table-responsive">
                <table class="gp-table">
                  <thead>
                    <tr>
                      <th>Référence</th>
                      <th>Type</th>
                      <th>Intitulé</th>
                      <th>Période</th>
                      <th>Généré par</th>
                      <th>Date</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="savedReportsTableBody">
                    <tr><td colspan="7" class="text-center text-muted py-3">Aucun rapport archivé pour le moment.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </section>

        <!-- ====================================================================
             VUE 2 : ARTICLES EN STOCK (GESTION DE L'INVENTAIRE)
             ==================================================================== -->
        <section id="view-stock-articles" class="view-section">
          <div class="page-header-row">
            <div>
              <h1 class="page-title">Articles en stock</h1>
              <p class="page-subtitle">Consultez les quantités disponibles, seuils min/max, emplacements et états de réapprovisionnement</p>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2">
              <button class="btn-gp-secondary admin-only-feature" type="button" id="btnOpenReplenishmentModal" data-permission="stock.view">
                <i class="bi bi-cart-check text-warning"></i> État de réapprovisionnement
              </button>
              <button class="btn-gp-secondary admin-only-feature" type="button" data-permission="stock.view" onclick="exportTableAsPdf('articles')">
                <i class="bi bi-file-earmark-pdf text-danger"></i> Exporter PDF
              </button>
              <button class="btn-gp-secondary admin-only-feature" type="button" data-permission="stock.view" onclick="downloadAuthenticatedFile('/api/articles-export', 'etat-stock.csv')">
                <i class="bi bi-file-earmark-excel text-success"></i> Exporter CSV
              </button>
              <button class="btn-gp-secondary admin-only-feature" data-permission="stock.view" data-view-target="stock-movements">
                <i class="bi bi-clock-history"></i> Mouvements de stock
              </button>
              <button class="btn-gp-primary admin-only-feature" data-permission="stock.manage" data-bs-toggle="modal" data-bs-target="#modalNewArticle">
                <i class="bi bi-plus-lg"></i> Nouvel article
              </button>
              <button class="btn-gp-primary collaborator-only-feature" data-view-target="request-new">
                <i class="bi bi-plus-lg"></i> Nouvelle demande
              </button>
            </div>
          </div>

          <div class="gp-table-container">
            <div class="gp-table-toolbar">
              <div class="toolbar-filters">
                <select id="filterStockCategory" class="form-select-sm-custom">
                  <option value="">Toutes les catégories</option>
                  <option value="Papeterie">Papeterie</option>
                  <option value="Informatique">Informatique</option>
                  <option value="Fournitures">Fournitures</option>
                  <option value="Gadgets">Gadgets</option>
                </select>

                <select id="filterStockStatus" class="form-select-sm-custom">
                  <option value="">Tous les statuts</option>
                  <option value="dispo">En stock normal</option>
                  <option value="faible">Stock faible (sous seuil)</option>
                  <option value="rupture">En rupture (0)</option>
                </select>
              </div>

              <div class="table-search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchStockInput" class="form-input-sm-custom" placeholder="Rechercher code, désignation, emplacement, fournisseur...">
              </div>
            </div>

            <div class="table-responsive">
              <table class="gp-table">
                <thead>
                  <tr>
                    <th>Code</th>
                    <th>Désignation & Fournisseur</th>
                    <th>Catégorie</th>
                    <th>Emplacement</th>
                    <th>Unité</th>
                    <th>Coût unit.</th>
                    <th>Quantité</th>
                    <th>Seuils (Min/Max)</th>
                    <th>Statut</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody id="articlesStockTableBody"><tr><td colspan="10" class="text-center py-4 text-muted"><i class="bi bi-box me-2"></i>Aucun article en stock. Cliquez sur "+ Nouvel article" pour enregistrer un matériel.</td></tr></tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- ====================================================================
             VUE 3 : NOUVELLE DEMANDE (ASSISTANT STEPPER)
             ==================================================================== -->
        <section id="view-request-new" class="view-section">
          <div class="breadcrumb-nav">
            <a href="#requests-list" data-view-target="requests-list">Demandes</a>
            <span class="separator">/</span>
            <span class="current" id="requestNewBreadcrumbText">Nouvelle demande</span>
          </div>

          <div class="page-header-row mb-3">
            <div>
              <h1 class="page-title" id="requestNewPageTitle">Nouvelle demande interne</h1>
              <p class="page-subtitle">Sélectionnez des articles en stock ou décrivez votre besoin de prestation, véhicule ou maintenance.</p>
            </div>
            <div id="editingRequestBanner" style="display:none;">
              <span class="badge bg-warning-subtle text-warning-emphasis border px-3 py-2 me-2">
                <i class="bi bi-pencil-square me-1"></i> Modification de <strong id="editingRequestRefText">-</strong>
              </span>
              <button type="button" class="btn-gp-secondary btn-sm" id="btnCancelEditRequest">Annuler l'édition</button>
            </div>
          </div>

          <!-- Stepper Indicator -->
          <div class="stepper-header">
            <div class="step-item active" id="stepHeader1">
              <span class="step-number">1</span>
              <span>Besoin & Articles</span>
            </div>
            <div class="step-separator"></div>
            <div class="step-item" id="stepHeader2">
              <span class="step-number">2</span>
              <span>Catégorie & Justification</span>
            </div>
            <div class="step-separator"></div>
            <div class="step-item" id="stepHeader3">
              <span class="step-number">3</span>
              <span>Récapitulatif & Envoi</span>
            </div>
          </div>

          <!-- Étape 1 : Sélection des articles ou besoin hors stock -->
          <div id="stepContent1" class="gp-card">
            <div class="p-3 mb-3 rounded border bg-light d-flex flex-column gap-2">
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="checkNonStockRequest">
                <label class="form-check-label fw-semibold text-dark" for="checkNonStockRequest">
                  Demande sans article en stock (Prestation de service, intervention maintenance, véhicule ou achat spécifique)
                </label>
              </div>
              <div id="nonStockDescriptionWrapper" style="display:none;">
                <label class="form-label small fw-semibold mb-1">Description précise du besoin / de la prestation souhaitée</label>
                <textarea id="inputNonStockDescription" class="form-control form-control-sm" rows="2" placeholder="Ex: Révision climatiseur salle serveur, mise à disposition véhicule de liaison, impression supports..."></textarea>
              </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
              <h2 class="gp-card-title">Catalogue des articles disponibles <span class="badge bg-primary rounded-pill ms-2" id="selectedArticlesCountBadge" style="display:none;">0</span></h2>
              <div class="table-search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchNewReqArticle" class="form-input-sm-custom" placeholder="Filtrer le catalogue d'articles...">
              </div>
            </div>

            <div class="table-responsive mb-4">
              <table class="gp-table">
                <thead>
                  <tr>
                    <th style="width: 40px;"></th>
                    <th>Article</th>
                    <th>Catégorie</th>
                    <th class="text-center">Stock dispo</th>
                    <th class="text-center" style="width: 150px;">Quantité souhaitée</th>
                  </tr>
                </thead>
                <tbody id="selectableArticlesTableBody"><tr><td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-inbox me-2"></i>Aucun matériel disponible dans le catalogue pour le moment.</td></tr></tbody>
              </table>
            </div>

            <div class="d-flex justify-content-end">
              <button type="button" id="btnStep1Next" class="btn-gp-primary">
                Suivant <i class="bi bi-arrow-right"></i>
              </button>
            </div>
          </div>

          <!-- Étape 2 : Informations complémentaires -->
          <div id="stepContent2" class="gp-card" style="display: none;">
            <div class="mb-3">
              <h2 class="gp-card-title">Qualification et justification de la demande</h2>
              <small class="text-muted">Précisez la catégorie, l'urgence, le motif et joignez vos pièces justificatives éventuelles</small>
            </div>

            <div class="row g-3 mb-4">
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Catégorie de demande <span class="text-danger">*</span></label>
                <select id="selectRequestCategory" class="form-select">
                  <option value="Papeterie et fournitures de bureau">Papeterie et fournitures de bureau</option>
                  <option value="Matériel informatique et accessoires">Matériel informatique et accessoires</option>
                  <option value="Consommables informatiques">Consommables informatiques</option>
                  <option value="Fournitures et équipements de cuisine">Fournitures et équipements de cuisine</option>
                  <option value="Provisions et produits d'entretien">Provisions et produits d'entretien</option>
                  <option value="Mobilier et équipements de bureau">Mobilier et équipements de bureau</option>
                  <option value="Demandes liées aux véhicules">Demandes liées aux véhicules</option>
                  <option value="Maintenance et réparations">Maintenance et réparations</option>
                  <option value="Prestations de services">Prestations de services</option>
                  <option value="Autres besoins administratifs et logistiques" selected>Autres besoins administratifs et logistiques</option>
                </select>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Type d'opération interne <span class="text-danger">*</span></label>
                <select id="selectRequestTypeOp" class="form-select">
                  <option value="Dotation permanente" selected>Dotation / consommable / prestation — sans restitution</option>
                  <option value="Emprunt temporaire">Emprunt temporaire — matériel à restituer après utilisation</option>
                </select>
                <div class="form-text">
                  <strong>Sans restitution :</strong> article consommé, équipement attribué définitivement ou service demandé.
                  <strong>Emprunt temporaire :</strong> équipement prêté pour une durée limitée et rendu après utilisation.
                </div>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Service demandeur <span class="text-danger">*</span></label>
                <select id="selectRequestService" class="form-select">
                  <option value="Direction Générale">Direction Générale</option>
                  <option value="Administration & Logistique" selected>Administration & Logistique</option>
                  <option value="Ressources Humaines">Ressources Humaines</option>
                  <option value="Informatique & SI">Informatique & SI</option>
                  <option value="Finance & Comptabilité">Finance & Comptabilité</option>
                  <option value="Parc Automobile">Parc Automobile</option>
                </select>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Objet / Motif principal <span class="text-danger">*</span></label>
                <input type="text" id="inputRequestMotif" class="form-control" placeholder="Ex: Dotation mensuelle équipe, mission terrain, réparation...">
              </div>

              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold">Date souhaitée / échéance</label>
                <input type="date" id="inputRequestDateNeeded" class="form-control">
              </div>

              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold">Niveau d'urgence</label>
                <select id="selectRequestUrgency" class="form-select">
                  <option value="Planifiée">Planifiée (anticipation)</option>
                  <option value="Normale" selected>Normale (délai standard)</option>
                  <option value="Urgente">Urgente (prioritaire)</option>
                  <option value="Critique">Critique (blocage opérationnel)</option>
                </select>
              </div>

              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold">Demandeur</label>
                <input type="text" class="form-control bg-light" id="reqDemandeurName" placeholder="Nom du demandeur" readonly>
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold">Pièces jointes justificatives <span class="text-muted">(facultatives, sélection multiple autorisée)</span></label>
                <input type="file" id="inputRequestAttachment" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                <small class="text-muted">Devis, fiche technique, photo ou note interne (PDF, Word, Excel, JPG, PNG — 10 Mo max par fichier).</small>
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold">Justification détaillée & Contexte d'utilisation</label>
                <textarea id="inputRequestNotes" class="form-control" rows="3" placeholder="Expliquez la pertinence du besoin pour faciliter la vérification et la validation..."></textarea>
              </div>
            </div>

            <div class="d-flex justify-content-between">
              <button type="button" id="btnStep2Prev" class="btn-gp-secondary">
                <i class="bi bi-arrow-left"></i> Précédent
              </button>
              <button type="button" id="btnStep2Next" class="btn-gp-primary">
                Suivant <i class="bi bi-arrow-right"></i>
              </button>
            </div>
          </div>

          <!-- Étape 3 : Récapitulatif & Soumission -->
          <div id="stepContent3" class="gp-card" style="display: none;">
            <h2 class="gp-card-title mb-4">Récapitulatif avant enregistrement</h2>

            <div class="row g-3 mb-4">
              <div class="col-12 col-md-3">
                <div class="p-3 bg-light rounded h-100">
                  <small class="text-muted d-block">Catégorie :</small>
                  <strong id="step3CategoryRecap" class="text-dark small">-</strong>
                </div>
              </div>
              <div class="col-12 col-md-3">
                <div class="p-3 bg-light rounded h-100">
                  <small class="text-muted d-block">Motif :</small>
                  <strong id="step3MotifRecap" class="text-dark">-</strong>
                </div>
              </div>
              <div class="col-12 col-md-3">
                <div class="p-3 bg-light rounded h-100">
                  <small class="text-muted d-block">Service & Urgence :</small>
                  <strong id="step3ServiceRecap" class="text-dark">-</strong>
                </div>
              </div>
              <div class="col-12 col-md-3">
                <div class="p-3 bg-light rounded h-100">
                  <small class="text-muted d-block">Date souhaitée & Pièces :</small>
                  <strong id="step3DateRecap" class="text-dark">-</strong>
                </div>
              </div>
              <div class="col-12">
                <div class="p-3 bg-light rounded">
                  <small class="text-muted d-block">Justification & Détails :</small>
                  <span id="step3NotesRecap" class="text-secondary small">-</span>
                </div>
              </div>
            </div>

            <h3 class="fs-6 fw-bold text-dark mb-2">Articles / Prestations demandés :</h3>
            <div class="table-responsive mb-4">
              <table class="gp-table">
                <thead>
                  <tr>
                    <th>Désignation</th>
                    <th>Catégorie</th>
                    <th class="text-center">Quantité demandée</th>
                    <th class="text-end">Disponibilité stock</th>
                  </tr>
                </thead>
                <tbody id="step3RecapTableBody"></tbody>
              </table>
            </div>

            <div class="d-flex justify-content-between flex-wrap gap-2">
              <button type="button" id="btnStep3Prev" class="btn-gp-secondary">
                <i class="bi bi-arrow-left"></i> Précédent
              </button>
              <div class="d-flex gap-2">
                <button type="button" id="btnSubmitRequest" class="btn-gp-primary">
                  <i class="bi bi-send-check"></i> Soumettre la demande
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- ====================================================================
             VUE 4 : DÉTAILS DE LA DEMANDE
             ==================================================================== -->
        <section id="view-request-detail" class="view-section">
          <div class="breadcrumb-nav">
            <a href="#requests-list" data-view-target="requests-list">Demandes</a>
            <span class="separator">/</span>
            <span class="current">Détails de la demande</span>
          </div>

          <div class="page-header-row">
            <div>
              <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                <h1 class="page-title mb-0" id="detailRequestRef">Détails de la demande</h1>
                <span id="requestDetailStatusBadge" class="badge-status badge-pending"><span class="badge-status-dot"></span> Soumise</span>
                <span id="detailUrgencyBadge" class="badge bg-light text-dark border">Normale</span>
              </div>
              <p class="page-subtitle" id="detailRequestSubtitle">Consultez les informations et le circuit de validation de cette demande</p>
            </div>

            <!-- Actions Dropdown & Fast Actions -->
            <div class="d-flex align-items-center flex-wrap gap-2">
              <button class="btn-gp-secondary js-open-selected-bon" type="button" id="btnDetailOpenPdfBon">
                <i class="bi bi-file-earmark-pdf text-danger"></i> Bon officiel PDF
              </button>

              <!-- Actions du demandeur : modifier, confirmer la réception ou retirer la demande -->
              <button class="btn-gp-primary" type="button" id="btnRequesterEditResubmit" style="display: none;">
                <i class="bi bi-pencil-square me-1"></i> Modifier la demande
              </button>
              <button class="btn btn-success btn-sm fw-semibold px-3 py-2" type="button" id="btnRequesterConfirmReceipt" style="display: none;">
                <i class="bi bi-check2-circle me-1"></i> Confirmer la réception
              </button>
              <button class="btn btn-outline-danger btn-sm px-3 py-2" type="button" id="btnCollaboratorCancel" style="display: none;">
                <i class="bi bi-x-circle me-1"></i> Annuler la demande
              </button>

              <!-- Actions réservées au Responsable / Administrateur / Délégataire -->
              <div class="dropdown admin-only-feature" id="requestTreatmentDropdown" data-permission="requests.verify,requests.validate,requests.assign,requests.execute">
                <button class="btn-gp-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="bi bi-shield-check me-1"></i> Traiter la demande
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-2" style="min-width: 260px;">
                  <li><button class="dropdown-item py-2 text-info fw-semibold" id="btnActionVerify" data-permission="requests.verify"><i class="bi bi-search-heart me-2"></i> Vérifier disponibilité & pertinence</button></li>
                  <li><button class="dropdown-item py-2 text-success fw-semibold" id="btnActionApprove" data-permission="requests.validate"><i class="bi bi-check-circle me-2"></i> Valider & Autoriser (Bon PDF)</button></li>
                  <li><button class="dropdown-item py-2 text-warning fw-semibold" id="btnActionCorrection" data-permission="requests.validate"><i class="bi bi-chat-left-dots me-2"></i> Demander un complément</button></li>
                  <li><button class="dropdown-item py-2 text-primary fw-semibold" id="btnActionAssign" data-permission="requests.assign"><i class="bi bi-person-check me-2"></i> Affecter à un agent</button></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><button class="dropdown-item py-2 text-primary fw-semibold" id="btnActionDeliver" data-permission="requests.execute"><i class="bi bi-box-seam me-2"></i> Enregistrer la remise / livraison</button></li>
                  <li><button class="dropdown-item py-2 text-success fw-semibold" id="btnActionReceive" data-permission="requests.execute"><i class="bi bi-check2-square me-2"></i> Confirmer réception</button></li>
                  <li><button class="dropdown-item py-2 text-primary" id="btnActionReturn" data-permission="requests.execute"><i class="bi bi-arrow-return-left me-2"></i> Enregistrer restitution en stock</button></li>
                  <li><button class="dropdown-item py-2 text-secondary fw-semibold" id="btnActionClose" data-permission="requests.execute"><i class="bi bi-archive me-2"></i> Clôturer définitivement</button></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><button class="dropdown-item py-2 text-danger fw-semibold" id="btnActionReject" data-permission="requests.validate"><i class="bi bi-x-circle me-2"></i> Rejeter la demande (motif requis)</button></li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Alerte motif rejet / complément / annulation -->
          <div class="alert alert-warning border d-flex align-items-start gap-2 mb-3" id="detailDecisionReasonAlert" style="display:none;">
            <i class="bi bi-exclamation-circle-fill fs-5 mt-1"></i>
            <div>
              <strong id="detailDecisionReasonTitle">Observation du circuit :</strong>
              <div id="detailDecisionReasonBody" class="small">-</div>
            </div>
          </div>

          <!-- Onglets de détail -->
          <div class="custom-tabs-nav">
            <button class="tab-nav-btn active" data-detail-tab="info">Informations</button>
            <button class="tab-nav-btn" data-detail-tab="articles" id="detailTabArticlesBtn">Articles (0)</button>
            <button class="tab-nav-btn" data-detail-tab="history">Historique & Circuit</button>
            <button class="tab-nav-btn" data-detail-tab="documents" id="detailTabDocsBtn">Pièces & Documents</button>
          </div>

          <!-- Contenu de l'onglet : Informations -->
          <div id="tabPane-info" class="detail-tab-pane">
            <div class="row g-4">
              <div class="col-12 col-lg-7">
                <div class="gp-card">
                  <div class="row g-3 mb-3">
                    <div class="col-md-6">
                      <label class="text-muted small fw-semibold text-uppercase">Catégorie</label>
                      <div class="fw-semibold text-primary" id="detailRequestCategory">-</div>
                    </div>
                    <div class="col-md-6">
                      <label class="text-muted small fw-semibold text-uppercase">Agent affecté</label>
                      <div class="fw-semibold text-dark" id="detailRequestAssignee">Non affecté</div>
                    </div>
                  </div>

                  <div class="mb-3">
                    <label class="text-muted small fw-semibold text-uppercase">Motif de la demande</label>
                    <p class="fs-6 text-dark fw-medium mb-0" id="detailRequestMotif">-</p>
                  </div>

                  <div class="mb-4">
                    <label class="text-muted small fw-semibold text-uppercase">Justification / Informations complémentaires</label>
                    <p class="text-secondary mb-0" id="detailRequestNotes">Aucune note particulière.</p>
                  </div>

                  <div class="row g-3 pt-3 border-top">
                    <div class="col-6 col-sm-3">
                      <label class="text-muted small">Service</label>
                      <div class="fw-semibold text-dark" id="detailRequestService">-</div>
                    </div>
                    <div class="col-6 col-sm-3">
                      <label class="text-muted small">Échéance souhaitée</label>
                      <div class="fw-semibold text-dark" id="detailRequestDateNeeded">-</div>
                    </div>
                    <div class="col-6 col-sm-3">
                      <label class="text-muted small">Créée le</label>
                      <div class="fw-semibold text-dark" id="detailRequestCreatedAt">-</div>
                    </div>
                    <div class="col-6 col-sm-3">
                      <label class="text-muted small">Mise à jour</label>
                      <div class="fw-semibold text-dark" id="detailRequestUpdatedAt">-</div>
                    </div>
                  </div>
                </div>

                <!-- Aperçu rapide des articles demandés -->
                <div class="gp-card">
                  <h3 class="gp-card-title mb-3">Articles / Prestations demandés</h3>
                  <div class="table-responsive">
                    <table class="gp-table">
                      <thead>
                        <tr>
                          <th>Article</th>
                          <th>Catégorie</th>
                          <th class="text-center">Qté demandée</th>
                          <th class="text-end">Qté accordée</th>
                        </tr>
                      </thead>
                      <tbody id="detailPreviewArticlesBody">
                        <tr><td colspan="4" class="text-center py-3 text-muted">Aucun article en stock rattaché (demande de service / prestation).</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <!-- Colonne Droite : Circuit de Validation & Statut -->
              <div class="col-12 col-lg-5">
                <div class="gp-card">
                  <h3 class="gp-card-title mb-4">Circuit de traitement</h3>

                  <div class="audit-timeline">
                    <div class="timeline-step done">
                      <div class="timeline-marker"><i class="bi bi-check"></i></div>
                      <div class="timeline-title">Enregistrement & Soumission</div>
                      <div class="timeline-time" id="detailTimelineStep1Time">-</div>
                    </div>

                    <div class="timeline-step current" id="timelineStepCurrent">
                      <div class="timeline-marker"><i class="bi bi-arrow-repeat"></i></div>
                      <div class="timeline-title text-warning fw-bold">Vérification & Validation</div>
                      <div class="timeline-time">En cours d'examen</div>
                    </div>

                    <div class="timeline-step" id="timelineStepApproved">
                      <div class="timeline-marker">3</div>
                      <div class="timeline-title">Exécution, Réception & Clôture</div>
                      <div class="timeline-time">À traiter</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Contenu de l'onglet : Articles -->
          <div id="tabPane-articles" class="detail-tab-pane" style="display: none;">
            <div class="gp-card">
              <h3 class="gp-card-title mb-3">Détail complet des articles demandés</h3>
              <div class="table-responsive">
                <table class="gp-table">
                  <thead>
                    <tr>
                      <th>Code</th>
                      <th>Article</th>
                      <th>Catégorie</th>
                      <th>Unité</th>
                      <th class="text-center">Quantité demandée</th>
                      <th class="text-center">Quantité accordée</th>
                      <th>Stock actuel</th>
                    </tr>
                  </thead>
                  <tbody id="detailFullArticlesBody">
                    <tr><td colspan="7" class="text-center py-3 text-muted">Aucun article en stock dans cette demande.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Contenu de l'onglet : Historique -->
          <div id="tabPane-history" class="detail-tab-pane" style="display: none;">
            <div class="gp-card">
              <h3 class="gp-card-title mb-3">Journal complet des étapes de traitement</h3>
              <ul class="list-group list-group-flush" id="detailRequestHistory" style="font-size: 0.885rem;"></ul>
            </div>
          </div>

          <!-- Contenu de l'onglet : Documents & Pièces jointes -->
          <div id="tabPane-documents" class="detail-tab-pane" style="display: none;">
            <div class="gp-card mb-3">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <h3 class="gp-card-title mb-0">Bon officiel & Pièces justificatives rattachées</h3>
                <button class="btn-gp-secondary btn-sm js-open-selected-bon" type="button">
                  <i class="bi bi-file-earmark-pdf text-danger"></i> Aperçu / Imprimer le Bon de décharge
                </button>
              </div>

              <div id="detailRequestDocumentsList" class="d-flex flex-column gap-2 mb-4"></div>

              <form id="formAddRequestAttachment" class="row g-2 align-items-end pt-3 border-top" enctype="multipart/form-data">
                <div class="col-md-5">
                  <label class="form-label small fw-semibold">Intitulé de la pièce jointe</label>
                  <input type="text" id="reqNewAttachmentTitle" class="form-control form-control-sm" placeholder="Ex: Devis comparatif, Bon de livraison signé...">
                </div>
                <div class="col-md-5">
                  <label class="form-label small fw-semibold">Fichier à joindre <span class="text-danger">*</span></label>
                  <input type="file" id="reqNewAttachmentFile" class="form-control form-control-sm" required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                </div>
                <div class="col-md-2">
                  <button type="submit" class="btn-gp-primary btn-sm w-100 justify-content-center"><i class="bi bi-paperclip"></i> Joindre</button>
                </div>
              </form>
            </div>
          </div>
        </section>

        <!-- ====================================================================
             VUE 5 : NOUVEAU DOCUMENT ADMINISTRATIF (FORMULAIRE)
             ==================================================================== -->
        <section id="view-document-new" class="view-section">
          <div class="breadcrumb-nav">
            <a href="#documents" data-view-target="documents">Documents</a>
            <span class="separator">/</span>
            <span class="current">Nouveau document</span>
          </div>

          <div class="page-header-row mb-3">
            <div>
              <h1 class="page-title">Nouveau document administratif</h1>
              <p class="page-subtitle">Enregistrez et classez un document officiel dans l'archive centrale</p>
            </div>
          </div>

          <div class="gp-card">
            <form id="formFullPageNewDoc">
              <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                  <label class="form-label fw-semibold">Titre du document <span class="text-danger">*</span></label>
                  <input type="text" id="fpDocTitle" class="form-control" placeholder="Ex: Procédure d'achat interne" required>
                </div>

                <div class="col-12 col-md-6">
                  <label class="form-label fw-semibold">Catégorie <span class="text-danger">*</span></label>
                  <select id="fpDocCategory" class="form-select" required>
                    <option value="">Sélectionner une catégorie</option>
                    <option value="Procédures">Procédures</option>
                    <option value="Notes de service">Notes de service</option>
                    <option value="Règlements">Règlements</option>
                    <option value="Formulaires">Formulaires</option>
                    <option value="Plans">Plans</option>
                    <option value="Contrats & Conventions">Contrats & Conventions</option>
                    <option value="Documents internes">Documents internes</option>
                  </select>
                </div>

                <div class="col-12 col-md-4">
                  <label class="form-label fw-semibold">Type de document <span class="text-danger">*</span></label>
                  <select id="fpDocType" class="form-select" required>
                    <option value="">Sélectionner un type</option>
                    <option value="PDF">PDF</option>
                    <option value="Word">Word (.doc / .docx)</option>
                    <option value="Excel">Excel (.xls / .xlsx)</option>
                    <option value="Image">Image (.jpg / .png)</option>
                  </select>
                </div>

                <div class="col-12 col-md-4">
                  <label class="form-label fw-semibold">Service émetteur <span class="text-danger">*</span></label>
                  <select id="fpDocService" class="form-select" required>
                    <option value="Direction Générale">Direction Générale</option>
                    <option value="Administration & Logistique">Administration & Logistique</option>
                    <option value="Ressources Humaines">Ressources Humaines</option>
                    <option value="Informatique & SI">Informatique & SI</option>
                    <option value="Finance & Comptabilité">Finance & Comptabilité</option>
                  </select>
                </div>

                <div class="col-12 col-md-4">
                  <label class="form-label fw-semibold">Date du document <span class="text-danger">*</span></label>
                  <input type="date" id="fpDocDate" class="form-control" required>
                </div>

                <!-- Zone de dépôt Glisser-Déposer -->
                <div class="col-12">
                  <label class="form-label fw-semibold">Fichier <span class="text-danger">*</span></label>
                  <div class="dropzone-container" onclick="document.getElementById('fileUploadInput').click();">
                    <input type="file" id="fileUploadInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required style="display:none;" onchange="document.getElementById('selectedDocFileName').textContent = this.files[0] ? this.files[0].name : 'Aucun fichier sélectionné'; showToast('Fichier prêt : ' + (this.files[0] ? this.files[0].name : ''), 'info')">
                    <div class="dropzone-icon">
                      <i class="bi bi-cloud-arrow-up"></i>
                    </div>
                    <div class="fw-semibold text-dark fs-6 mb-1" id="selectedDocFileName">Cliquez ici pour sélectionner un fichier</div>
                    <div class="text-primary small mb-2">ou parcourir votre poste</div>
                    <div class="text-muted" style="font-size: 0.78rem;">Formats acceptés : PDF, Word, Excel, JPG, PNG (max 10 Mo)</div>
                  </div>
                </div>
              </div>

              <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn-gp-secondary" data-view-target="documents">Annuler</button>
                <button type="submit" class="btn-gp-primary">Enregistrer dans l'archive</button>
              </div>
            </form>
          </div>
        </section>

        <!-- ====================================================================
             VUE 6 : DOCUMENTS ADMINISTRATIFS (LISTE & ARCHIVAGE)
             ==================================================================== -->
        <section id="view-documents" class="view-section">
          <div class="page-header-row">
            <div>
              <h1 class="page-title">Documents administratifs</h1>
              <p class="page-subtitle">Archive centralisée des procédures, notes, bons de décharge et pièces justificatives</p>
            </div>
            <div class="d-flex align-items-center gap-2">
              <button class="btn-gp-primary" data-view-target="document-new">
                <i class="bi bi-plus-lg"></i> Nouveau document
              </button>
            </div>
          </div>

          <div class="gp-table-container">
            <div class="gp-table-toolbar">
              <div class="toolbar-filters">
                <select id="filterDocCategory" class="form-select-sm-custom">
                  <option value="">Toutes les catégories</option>
                  <option value="Procédures">Procédures</option>
                  <option value="Notes de service">Notes de service</option>
                  <option value="Règlements">Règlements</option>
                  <option value="Formulaires">Formulaires</option>
                  <option value="Plans">Plans</option>
                  <option value="Contrats & Conventions">Contrats & Conventions</option>
                  <option value="Documents internes">Documents internes</option>
                  <option value="Bons de sortie">Bons de sortie</option>
                  <option value="Pièces jointes">Pièces jointes</option>
                </select>

                <select id="filterDocType" class="form-select-sm-custom">
                  <option value="">Tous les types</option>
                  <option value="PDF">PDF</option>
                  <option value="Word">Word</option>
                  <option value="Excel">Excel</option>
                  <option value="Image">Image</option>
                </select>

                <select id="filterDocService" class="form-select-sm-custom">
                  <option value="">Tous les services</option>
                  <option value="Direction Générale">Direction Générale</option>
                  <option value="Administration & Logistique">Administration & Logistique</option>
                  <option value="Ressources Humaines">Ressources Humaines</option>
                  <option value="Informatique & SI">Informatique & SI</option>
                </select>
              </div>

              <div class="table-search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchDocsInput" class="form-input-sm-custom" placeholder="Rechercher réf, titre, service...">
              </div>
            </div>

            <div class="table-responsive">
              <table class="gp-table">
                <thead>
                  <tr>
                    <th>Référence</th>
                    <th>Titre</th>
                    <th>Catégorie</th>
                    <th>Type & Taille</th>
                    <th>Service</th>
                    <th>Date</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody id="documentsTableBody"><tr><td colspan="7" class="text-center py-4 text-muted"><i class="bi bi-folder2-open me-2"></i>Aucun document administratif archivé.</td></tr></tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- ====================================================================
             VUE 7 : MOUVEMENTS DE STOCK
             ==================================================================== -->
        <section id="view-stock-movements" class="view-section">
          <div class="page-header-row">
            <div>
              <h1 class="page-title">Mouvements de stock</h1>
              <p class="page-subtitle">Historique complet des entrées, sorties autorisées, transferts, retours, pertes et ajustements d'inventaire</p>
            </div>
            <div class="d-flex align-items-center gap-2">
              <button class="btn-gp-secondary admin-only-feature" data-bs-toggle="modal" data-bs-target="#modalNewMovement">
                <i class="bi bi-sliders"></i> Enregistrer un mouvement
              </button>
              <button class="btn-gp-secondary" type="button" onclick="exportTableAsPdf('movements')">
                <i class="bi bi-file-earmark-pdf text-danger"></i> Exporter PDF
              </button>
              <button class="btn-gp-secondary" type="button" onclick="downloadAuthenticatedFile('/api/movements-export', 'mouvements-stock.csv')">
                <i class="bi bi-file-earmark-excel text-success"></i> Exporter CSV
              </button>
            </div>
          </div>

          <div class="gp-table-container">
            <div class="gp-table-toolbar">
              <div class="toolbar-filters">
                <div class="d-flex align-items-center gap-1">
                  <span class="small text-muted">Du</span>
                  <input type="date" id="filterMvtFrom" class="form-input-sm-custom">
                  <span class="small text-muted">Au</span>
                  <input type="date" id="filterMvtTo" class="form-input-sm-custom">
                </div>

                <select id="filterMovementType" class="form-select-sm-custom">
                  <option value="">Tous les types</option>
                  <option value="Entrée">Entrée (Approvisionnement)</option>
                  <option value="Sortie">Sortie (Distribution)</option>
                  <option value="Transfert">Transfert (Emplacement / Service)</option>
                  <option value="Retour">Retour en stock</option>
                  <option value="Perte">Perte / Casse</option>
                  <option value="Ajustement">Ajustement d'inventaire</option>
                </select>
              </div>

              <div class="table-search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchMovementInput" class="form-input-sm-custom" placeholder="Rechercher réf, article, service, motif...">
              </div>
            </div>

            <div class="table-responsive">
              <table class="gp-table">
                <thead>
                  <tr>
                    <th>Réf & Date</th>
                    <th>Article</th>
                    <th>Type</th>
                    <th>Quantité</th>
                    <th>Origine / Trajet</th>
                    <th>Opérateur</th>
                    <th>Motif / Justificatif</th>
                  </tr>
                </thead>
                <tbody id="stockMovementsTableBody"><tr><td colspan="7" class="text-center py-4 text-muted"><i class="bi bi-clock-history me-2"></i>Aucun mouvement de stock enregistré.</td></tr></tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- ====================================================================
             VUE 8 : GESTION DES UTILISATEURS ET ACCÈS (MODULE 1)
             ==================================================================== -->
        <section id="view-users" class="view-section">
          <div class="page-header-row">
            <div>
              <h1 class="page-title">Utilisateurs, Rôles & Permissions</h1>
              <p class="page-subtitle">Gérez les comptes, permissions granulaires, réinitialisations de mot de passe et l'historique des connexions</p>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2">
              <button class="btn-gp-secondary" type="button" id="btnOpenLoginHistoryModal">
                <i class="bi bi-shield-lock"></i> Historique des connexions
              </button>
              <button class="btn-gp-primary" type="button" id="btnOpenCreateUserModal" data-bs-toggle="modal" data-bs-target="#modalNewUser">
                <i class="bi bi-plus-lg"></i> Nouvel utilisateur
              </button>
            </div>
          </div>

          <div class="gp-table-container">
            <div class="gp-table-toolbar">
              <div class="toolbar-filters">
                <select id="filterUserRole" class="form-select-sm-custom">
                  <option value="">Tous les profils</option>
                  <option value="Administrateur">Administrateur</option>
                  <option value="Responsable">Responsable</option>
                  <option value="Collaborateur">Collaborateur / Employé</option>
                </select>

                <select id="filterUserStatus" class="form-select-sm-custom">
                  <option value="">Tous les statuts</option>
                  <option value="Actif">Compte actif</option>
                  <option value="Désactivé">Compte désactivé</option>
                </select>
              </div>

              <div class="table-search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchUsersInput" class="form-input-sm-custom" placeholder="Rechercher nom, matricule, email, service...">
              </div>
            </div>

            <div class="table-responsive">
              <table class="gp-table">
                <thead>
                  <tr>
                    <th>Collaborateur & Matricule</th>
                    <th>Email & Contact</th>
                    <th>Profil & Permissions</th>
                    <th>Service</th>
                    <th>Statut</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody id="usersTableBody"><tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-people me-2"></i>Aucun utilisateur enregistré.</td></tr></tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- ====================================================================
             VUE 9 : LISTE COMPLÈTE DES DEMANDES INTERNES (MODULE 2)
             ==================================================================== -->
        <section id="view-requests-list" class="view-section">
          <div class="page-header-row">
            <div>
              <h1 class="page-title">Gestion des demandes internes</h1>
              <p class="page-subtitle">Suivi complet du cycle de vie, des délais de traitement et des circuits de validation</p>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2">
              <button class="btn-gp-secondary admin-only-feature" type="button" onclick="exportTableAsPdf('requests')">
                <i class="bi bi-file-earmark-pdf text-danger"></i> Exporter PDF
              </button>
              <button class="btn-gp-secondary admin-only-feature" type="button" id="btnExportRequestsCsv">
                <i class="bi bi-file-earmark-excel text-success"></i> Exporter CSV
              </button>
              <button class="btn-gp-primary" data-view-target="request-new">
                <i class="bi bi-plus-lg"></i> Nouvelle demande
              </button>
            </div>
          </div>

          <div class="gp-table-container">
            <div class="gp-table-toolbar">
              <div class="toolbar-filters">
                <select id="filterReqStatus" class="form-select-sm-custom">
                  <option value="">Tous les statuts</option>
                  <option value="Soumise">Soumise</option>
                  <option value="En attente de validation">En attente de validation</option>
                  <option value="Rejetée">Rejetée</option>
                  <option value="Validée">Validée</option>
                </select>

                <select id="filterReqCategory" class="form-select-sm-custom">
                  <option value="">Toutes les catégories</option>
                  <option value="Papeterie et fournitures de bureau">Papeterie et fournitures de bureau</option>
                  <option value="Matériel informatique et accessoires">Matériel informatique et accessoires</option>
                  <option value="Consommables informatiques">Consommables informatiques</option>
                  <option value="Fournitures et équipements de cuisine">Fournitures et équipements de cuisine</option>
                  <option value="Provisions et produits d'entretien">Provisions et produits d'entretien</option>
                  <option value="Mobilier et équipements de bureau">Mobilier et équipements de bureau</option>
                  <option value="Demandes liées aux véhicules">Demandes liées aux véhicules</option>
                  <option value="Maintenance et réparations">Maintenance et réparations</option>
                  <option value="Prestations de services">Prestations de services</option>
                  <option value="Autres besoins administratifs et logistiques">Autres besoins administratifs et logistiques</option>
                </select>

                <select id="filterReqUrgency" class="form-select-sm-custom">
                  <option value="">Toutes urgences</option>
                  <option value="Critique">Critique</option>
                  <option value="Urgente">Urgente</option>
                  <option value="Normale">Normale</option>
                  <option value="Planifiée">Planifiée</option>
                </select>
              </div>

              <div class="table-search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchRequestsInput" class="form-input-sm-custom" placeholder="Réf, demandeur, motif, service...">
              </div>
            </div>

            <div class="table-responsive">
              <table class="gp-table requests-table">
                <thead>
                  <tr>
                    <th>Réf.</th>
                    <th>Demandeur</th>
                    <th>Catégorie / motif</th>
                    <th>Priorité</th>
                    <th>Dates</th>
                    <th>Articles</th>
                    <th>Affecté à</th>
                    <th>Statut</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody id="allRequestsTableBody"><tr><td colspan="9" class="text-center py-4 text-muted"><i class="bi bi-file-earmark-text me-2"></i>Aucune demande enregistrée pour le moment.</td></tr></tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- ====================================================================
             VUE 10 : PARAMÈTRES, RÉFÉRENTIELS, AUDIT & SAUVEGARDES (MODULES 9 & 10)
             ==================================================================== -->
        <section id="view-settings" class="view-section">
          <div class="page-header-row">
            <div>
              <h1 class="page-title">Paramètres & Administration générale</h1>
              <p class="page-subtitle">Configuration de l'entreprise, référentiels métier, niveaux & délégations de validation, audit et sauvegardes</p>
            </div>
            <div>
              <button type="button" class="btn-gp-primary" id="btnSaveSettings">
                <i class="bi bi-floppy"></i> Enregistrer la configuration
              </button>
            </div>
          </div>

          <!-- Onglets de Paramètres -->
          <div class="settings-nav-pills flex-wrap">
            <button class="settings-pill-btn active" type="button" data-settings-tab="general">
              <i class="bi bi-building"></i> Général & Entreprise
            </button>
            <button class="settings-pill-btn" type="button" data-settings-tab="stocks">
              <i class="bi bi-box-seam"></i> Seuils & Règles Stock
            </button>
            <button class="settings-pill-btn" type="button" data-settings-tab="workflow">
              <i class="bi bi-diagram-3"></i> Validation, Notifications & Délégations
            </button>
            <button class="settings-pill-btn" type="button" data-settings-tab="referentials">
              <i class="bi bi-collection"></i> Référentiels métier
            </button>
            <button class="settings-pill-btn" type="button" data-settings-tab="audit">
              <i class="bi bi-journal-text"></i> Journal d'audit & Traçabilité
            </button>
            <button class="settings-pill-btn" type="button" data-settings-tab="security">
              <i class="bi bi-database-lock"></i> Sécurité & Sauvegardes
            </button>
          </div>

          <!-- Contenu Onglet 1 : Général -->
          <div id="settingsPane-general" class="settings-tab-pane">
            <div class="gp-card">
              <h3 class="gp-card-title mb-3">Identité et paramétrage général de l'entreprise</h3>
              <div class="row g-3 mb-2">
                <div class="col-12 col-md-6">
                  <label class="form-label fw-semibold">Raison sociale / Nom de l'organisation</label>
                  <input type="text" id="settingOrgName" class="form-control" value="ENTREPRISE CI - SIÈGE SOCIAL">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-semibold">Sigle officiel</label>
                  <input type="text" id="settingOrgAcronym" class="form-control" value="ENT-CI">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-semibold">Devise par défaut</label>
                  <select id="settingCurrency" class="form-select">
                    <option value="XOF" selected>FCFA (XOF)</option>
                    <option value="EUR">Euros (€)</option>
                    <option value="USD">Dollars ($)</option>
                  </select>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-semibold">Fuseau horaire</label>
                  <select id="settingTimezone" class="form-select">
                    <option value="Africa/Abidjan" selected>Abidjan (GMT+0)</option>
                    <option value="Africa/Dakar">Dakar (GMT+0)</option>
                    <option value="Europe/Paris">Paris (GMT+1)</option>
                  </select>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-semibold">Adresse du siège</label>
                  <input type="text" id="settingOrgAddress" class="form-control" value="Plateau, Immeuble Horizon, Abidjan - Côte d'Ivoire">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-semibold">Contact administratif (Tél / Email)</label>
                  <input type="text" id="settingOrgContact" class="form-control" value="+225 27 20 00 00 00 • contact@entreprise.ci">
                </div>
              </div>
            </div>
          </div>

          <!-- Contenu Onglet 2 : Stocks & Alertes -->
          <div id="settingsPane-stocks" class="settings-tab-pane" style="display: none;">
            <div class="gp-card">
              <h3 class="gp-card-title mb-3">Seuils de stock par défaut & Règles d'inventaire</h3>
              <div class="row g-3 mb-2">
                <div class="col-12 col-md-4">
                  <label class="form-label fw-semibold">Seuil minimal d'alerte par défaut</label>
                  <input type="number" id="settingDefaultStockThreshold" class="form-control" value="5" min="1">
                  <small class="text-muted">Déclenche une alerte de stock faible.</small>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-semibold">Seuil maximal par défaut</label>
                  <input type="number" id="settingDefaultMaxThreshold" class="form-control" value="100" min="1">
                  <small class="text-muted">Utilisé pour calculer les quantités à commander.</small>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-semibold">Fréquence d'inventaire physique</label>
                  <select id="settingInventoryFrequency" class="form-select">
                    <option value="monthly" selected>Mensuel</option>
                    <option value="quarterly">Trimestriel</option>
                    <option value="yearly">Annuel</option>
                  </select>
                </div>
                <div class="col-12 pt-2">
                  <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="switchAlertEmail" checked>
                    <label class="form-check-label fw-semibold" for="switchAlertEmail">
                      Notifier automatiquement les responsables en cas de rupture ou stock sous le seuil minimal
                    </label>
                  </div>
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="switchBlockZeroStock" checked>
                    <label class="form-check-label fw-semibold" for="switchBlockZeroStock">
                      Empêcher toute sortie de stock supérieure au disponible physique
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Contenu Onglet 3 : Workflow, Notifications & Délégations -->
          <div id="settingsPane-workflow" class="settings-tab-pane" style="display: none;">
            <div class="gp-card mb-4">
              <h3 class="gp-card-title mb-3">Niveaux de validation, Délais SLA & Paramétrage des notifications</h3>
              <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                  <label class="form-label fw-semibold">Niveaux de validation standard</label>
                  <select id="settingValidationLevels" class="form-select">
                    <option value="1">1 niveau (Validation directe Responsable / Admin)</option>
                    <option value="2" selected>2 niveaux (Vérification disponibilité + Validation hiérarchique)</option>
                    <option value="3">3 niveaux (Chef de service + Logistique + Direction)</option>
                  </select>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label fw-semibold">Délai maximal avant alerte de retard (SLA)</label>
                  <select id="settingRequestSlaHours" class="form-select">
                    <option value="24">24 heures</option>
                    <option value="48" selected>48 heures</option>
                    <option value="72">72 heures</option>
                    <option value="120">5 jours (120 heures)</option>
                  </select>
                </div>
                <div class="col-12 pt-2">
                  <h6 class="fw-bold text-dark mb-2">Canaux et événements de notification interne</h6>
                  <div class="row g-2">
                    <div class="col-md-6">
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="switchNotifyOnSubmit" checked>
                        <label class="form-check-label" for="switchNotifyOnSubmit">Nouvelle demande soumise ou re-soumise</label>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="switchNotifyRequester" checked>
                        <label class="form-check-label" for="switchNotifyRequester">Changement de statut d'une demande (Demandeur)</label>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="switchNotifyMaintenance" checked>
                        <label class="form-check-label" for="switchNotifyMaintenance">Alertes d'entretien et d'échéances du parc automobile</label>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="switchNotifyExpenseOverrun" checked>
                        <label class="form-check-label" for="switchNotifyExpenseOverrun">Dépassement de dépense nécessitant une revalidation</label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Délégations de validation -->
            <div class="gp-card mb-0">
              <h3 class="gp-card-title mb-2">Délégations temporaires de validation</h3>
              <p class="text-muted small mb-3">Autorisez un collaborateur ou adjoint à valider les opérations pendant l'absence d'un responsable.</p>
              <form id="formNewDelegation" class="row g-2 align-items-end mb-3">
                <div class="col-md-3">
                  <label class="form-label small fw-semibold">Délégant (Titulaire)</label>
                  <select id="delegationDelegatorSelect" class="form-select form-select-sm" required></select>
                </div>
                <div class="col-md-3">
                  <label class="form-label small fw-semibold">Délégataire (Remplaçant)</label>
                  <select id="delegationDelegateSelect" class="form-select form-select-sm" required></select>
                </div>
                <div class="col-md-2">
                  <label class="form-label small fw-semibold">Début</label>
                  <input type="date" id="delegationStartDate" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label small fw-semibold">Fin</label>
                  <input type="date" id="delegationEndDate" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-2">
                  <button type="submit" class="btn-gp-primary btn-sm w-100 justify-content-center"><i class="bi bi-plus-lg"></i> Déléguer</button>
                </div>
                <div class="col-12">
                  <input type="text" id="delegationReason" class="form-control form-control-sm" placeholder="Motif de la délégation (ex: Congés annuels, mission extérieure)...">
                </div>
              </form>
              <div class="table-responsive">
                <table class="gp-table">
                  <thead>
                    <tr>
                      <th>Titulaire</th>
                      <th>Délégataire</th>
                      <th>Période</th>
                      <th>Motif</th>
                      <th>Statut</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody id="delegationsTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-3">Aucune délégation enregistrée.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Contenu Onglet 4 : Référentiels Métier (Module 10) -->
          <div id="settingsPane-referentials" class="settings-tab-pane" style="display: none;">
            <div class="row g-4">
              <!-- Services -->
              <div class="col-12 col-lg-6">
                <div class="gp-card h-100 mb-0">
                  <h3 class="gp-card-title mb-3">Gestion des services & départements</h3>
                  <form class="d-flex gap-2 mb-3 js-ref-create-form" data-ref-endpoint="/api/operations/reference-data/departments">
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Nom du service (ex: Audit Interne)" required>
                    <button type="submit" class="btn-gp-primary btn-sm text-nowrap"><i class="bi bi-plus"></i> Ajouter</button>
                  </form>
                  <div id="refListDepartments" class="d-flex flex-column gap-1" style="max-height:220px; overflow:auto;"></div>
                </div>
              </div>

              <!-- Catégories de demandes -->
              <div class="col-12 col-lg-6">
                <div class="gp-card h-100 mb-0">
                  <h3 class="gp-card-title mb-3">Catégories de demandes internes</h3>
                  <form class="d-flex gap-2 mb-3 js-ref-create-form" data-ref-endpoint="/api/operations/reference-data/request-categories">
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Nouvelle catégorie de demande..." required>
                    <button type="submit" class="btn-gp-primary btn-sm text-nowrap"><i class="bi bi-plus"></i> Ajouter</button>
                  </form>
                  <div id="refListRequestCategories" class="d-flex flex-column gap-1" style="max-height:220px; overflow:auto;"></div>
                </div>
              </div>

              <!-- Catégories de stocks -->
              <div class="col-12 col-lg-6">
                <div class="gp-card h-100 mb-0">
                  <h3 class="gp-card-title mb-3">Catégories d'articles en stock</h3>
                  <form class="d-flex gap-2 mb-3 js-ref-create-form" data-ref-endpoint="/api/categories">
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Ex: Consommables réseau" required>
                    <button type="submit" class="btn-gp-primary btn-sm text-nowrap"><i class="bi bi-plus"></i> Ajouter</button>
                  </form>
                  <div id="refListStockCategories" class="d-flex flex-column gap-1" style="max-height:220px; overflow:auto;"></div>
                </div>
              </div>

              <!-- Unités de mesure -->
              <div class="col-12 col-lg-6">
                <div class="gp-card h-100 mb-0">
                  <h3 class="gp-card-title mb-3">Unités de mesure</h3>
                  <form class="d-flex gap-2 mb-3 js-ref-create-form" data-ref-endpoint="/api/units">
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Ex: Carton" required>
                    <input type="text" name="symbol" class="form-control form-control-sm" style="max-width:90px;" placeholder="Symb.">
                    <button type="submit" class="btn-gp-primary btn-sm text-nowrap"><i class="bi bi-plus"></i> Ajouter</button>
                  </form>
                  <div id="refListUnits" class="d-flex flex-column gap-1" style="max-height:220px; overflow:auto;"></div>
                </div>
              </div>

              <!-- Emplacements de stockage -->
              <div class="col-12 col-lg-6">
                <div class="gp-card h-100 mb-0">
                  <h3 class="gp-card-title mb-3">Emplacements de stockage</h3>
                  <form class="d-flex gap-2 mb-3 js-ref-create-form" data-ref-endpoint="/api/operations/reference-data/locations">
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Ex: Magasin Annexe - Rayon C1" required>
                    <button type="submit" class="btn-gp-primary btn-sm text-nowrap"><i class="bi bi-plus"></i> Ajouter</button>
                  </form>
                  <div id="refListLocations" class="d-flex flex-column gap-1" style="max-height:220px; overflow:auto;"></div>
                </div>
              </div>

              <!-- Fournisseurs & Prestataires -->
              <div class="col-12 col-lg-6">
                <div class="gp-card h-100 mb-0">
                  <h3 class="gp-card-title mb-3">Fournisseurs & Prestataires agréés</h3>
                  <form class="row g-2 mb-3 js-ref-create-form" data-ref-endpoint="/api/operations/reference-data/suppliers">
                    <div class="col-5"><input type="text" name="name" class="form-control form-control-sm" placeholder="Raison sociale fournisseur" required></div>
                    <div class="col-4"><input type="text" name="phone" class="form-control form-control-sm" placeholder="Téléphone"></div>
                    <div class="col-3"><button type="submit" class="btn-gp-primary btn-sm w-100 justify-content-center"><i class="bi bi-plus"></i> Ajouter</button></div>
                  </form>
                  <div id="refListSuppliers" class="d-flex flex-column gap-1" style="max-height:220px; overflow:auto;"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Contenu Onglet 5 : Journal d'Audit & Traçabilité (Module 9) -->
          <div id="settingsPane-audit" class="settings-tab-pane" style="display: none;">
            <div class="gp-card mb-0">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                  <h3 class="gp-card-title mb-0">Journal d'audit des opérations & Modifications</h3>
                  <small class="text-muted">Traçabilité horodatée de toutes les créations, validations, rejets, connexions et modifications.</small>
                </div>
                <button type="button" class="btn-gp-secondary btn-sm" id="btnRefreshAuditLogs"><i class="bi bi-arrow-clockwise"></i> Actualiser le journal</button>
              </div>

              <div class="row g-2 mb-3">
                <div class="col-md-3">
                  <select id="filterAuditModule" class="form-select form-select-sm">
                    <option value="">Tous les modules</option>
                    <option value="auth">Authentification & Connexions</option>
                    <option value="user">Utilisateurs & Permissions</option>
                    <option value="request">Demandes & Validations</option>
                    <option value="article">Articles en stock</option>
                    <option value="movement">Mouvements de stock</option>
                    <option value="inventory">Inventaires physiques</option>
                    <option value="asset">Patrimoine & Équipements</option>
                    <option value="vehicle">Parc automobile</option>
                    <option value="expense">Dépenses & Finances</option>
                    <option value="backup">Sauvegardes</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <input type="date" id="filterAuditFrom" class="form-control form-control-sm" title="Date début">
                </div>
                <div class="col-md-3">
                  <input type="date" id="filterAuditTo" class="form-control form-control-sm" title="Date fin">
                </div>
                <div class="col-md-3">
                  <input type="text" id="filterAuditSearch" class="form-control form-control-sm" placeholder="Rechercher action, auteur, réf...">
                </div>
              </div>

              <div class="table-responsive">
                <table class="gp-table">
                  <thead>
                    <tr>
                      <th>Date & Heure</th>
                      <th>Utilisateur & IP</th>
                      <th>Action</th>
                      <th>Description & Détails (Avant / Après)</th>
                    </tr>
                  </thead>
                  <tbody id="activityLogsTableBody">
                    <tr><td colspan="4" class="text-center text-muted py-4">Chargement du journal d'audit...</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Contenu Onglet 6 : Sécurité & Sauvegardes (Module 9) -->
          <div id="settingsPane-security" class="settings-tab-pane" style="display: none;">
            <div class="gp-card mb-4">
              <h3 class="gp-card-title mb-3">Politiques de sécurité des accès</h3>
              <div class="row g-3">
                <div class="col-12 col-md-6">
                  <label class="form-label fw-semibold">Délai d'inactivité avant déconnexion automatique</label>
                  <select id="settingSessionTimeout" class="form-select">
                    <option value="15">15 minutes</option>
                    <option value="30">30 minutes</option>
                    <option value="60" selected>60 minutes (Recommandé)</option>
                  </select>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label fw-semibold">Politique de renouvellement des mots de passe</label>
                  <select id="settingPasswordExpiry" class="form-select">
                    <option value="90" selected>Tous les 90 jours</option>
                    <option value="180">Tous les 180 jours</option>
                    <option value="never">Jamais</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="gp-card mb-0">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                  <h3 class="gp-card-title mb-0"><i class="bi bi-database-check text-primary me-2"></i>Sauvegarde et restauration des données</h3>
                  <small class="text-muted">Créez une sauvegarde complète des données de l'application, téléchargez-la ou restaurez un état antérieur.</small>
                </div>
                <div class="d-flex gap-2">
                  <button type="button" class="btn-gp-primary btn-sm" id="btnCreateBackupNow">
                    <i class="bi bi-cloud-download"></i> Créer une sauvegarde maintenant
                  </button>
                </div>
              </div>

              <form id="formRestoreUploadedBackup" class="p-3 bg-light border rounded mb-3 row g-2 align-items-end">
                <div class="col-md-9">
                  <label class="form-label small fw-semibold mb-1">Restaurer depuis un fichier de sauvegarde externe</label>
                  <input type="file" id="inputBackupRestoreFile" class="form-control form-control-sm" accept=".json,application/json">
                </div>
                <div class="col-md-3">
                  <button type="submit" class="btn-gp-secondary btn-sm w-100 justify-content-center text-danger border-danger-subtle">
                    <i class="bi bi-arrow-counterclockwise"></i> Restaurer ce fichier
                  </button>
                </div>
              </form>

              <div class="table-responsive">
                <table class="gp-table">
                  <thead>
                    <tr>
                      <th>Référence</th>
                      <th>Fichier</th>
                      <th>Taille</th>
                      <th>Enregistrements</th>
                      <th>Créée par</th>
                      <th>Date</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="backupsTableBody">
                    <tr><td colspan="7" class="text-center text-muted py-3">Aucune sauvegarde générée pour le moment.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </section>

        <!-- ====================================================================
             VUE 11 : PROFIL UTILISATEUR
             ==================================================================== -->
        <section id="view-profile" class="view-section">
          <div class="breadcrumb-nav">
            <a href="#dashboard" data-view-target="dashboard">Accueil</a>
            <span class="separator">/</span>
            <span class="current">Mon Profil</span>
          </div>

          <div class="page-header-row mb-3">
            <div>
              <h1 class="page-title">Mon profil & Sécurité</h1>
              <p class="page-subtitle">Gérez vos coordonnées professionnelles, votre mot de passe et consultez vos connexions</p>
            </div>
          </div>

          <div class="row g-4">
            <!-- Colonne Carte Récapitulative -->
            <div class="col-12 col-lg-4">
              <div class="gp-card text-center">
                <img src="assets/avatars/aminata.svg" alt="Avatar" class="profile-avatar-large mb-3 mx-auto" id="profileCardAvatar">
                <h3 class="fs-5 fw-bold text-dark mb-1" id="profileCardName">Mon Profil</h3>
                <span class="badge bg-primary-subtle text-primary border mb-3" id="profileCardRole">Collaborateur</span>
                <div class="text-muted small mb-4" id="profileCardService">Service Entreprise</div>

                <div class="text-start border-top pt-3" style="font-size: 0.85rem;">
                  <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Matricule :</span>
                    <strong class="text-dark" id="profileCardMatricule">-</strong>
                  </div>
                  <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Email :</span>
                    <strong class="text-dark" id="profileCardEmail">email@entreprise.ci</strong>
                  </div>
                  <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Statut :</span>
                    <span class="badge-status badge-in-stock"><span class="badge-status-dot"></span> Compte actif</span>
                  </div>
                  <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Demandes créées :</span>
                    <strong class="text-primary" id="profileCardDemandesCount">0 demande</strong>
                  </div>
                </div>
              </div>
            </div>

            <!-- Colonne Formulaire de mise à jour + Historique connexions -->
            <div class="col-12 col-lg-8">
              <div class="gp-card mb-4">
                <form id="formUserProfile">
                  <h3 class="gp-card-title mb-3">Informations professionnelles</h3>
                  <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                      <label class="form-label fw-semibold">Nom et prénom</label>
                      <input type="text" id="profileInputName" class="form-control" placeholder="Nom et prénom" required>
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label fw-semibold">Adresse e-mail professionnelle</label>
                      <input type="email" id="profileInputEmail" class="form-control" placeholder="email@entreprise.ci" required>
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label fw-semibold">Numéro de téléphone direct</label>
                      <input type="text" id="profileInputPhone" class="form-control" placeholder="+225 07 00 00 00 00">
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label fw-semibold">Bureau / Localisation interne</label>
                      <input type="text" id="profileInputOffice" class="form-control" placeholder="Ex: 2ème étage - Bureau 204">
                    </div>
                  </div>

                  <h3 class="gp-card-title mb-3 pt-3 border-top">Renouvellement du mot de passe</h3>
                  <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                      <label class="form-label fw-semibold">Mot de passe actuel</label>
                      <div class="input-group">
                        <input type="password" id="profileCurrentPassword" class="form-control" placeholder="Requis si changement">
                        <button type="button" class="input-group-text bg-white" data-password-toggle="profileCurrentPassword" aria-label="Afficher le mot de passe" aria-pressed="false" title="Afficher le mot de passe">
                          <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                      </div>
                    </div>
                    <div class="col-12 col-md-4">
                      <label class="form-label fw-semibold">Nouveau mot de passe</label>
                      <div class="input-group">
                        <input type="password" id="profileNewPassword" class="form-control" placeholder="••••••••••••">
                        <button type="button" class="input-group-text bg-white" data-password-toggle="profileNewPassword" aria-label="Afficher le mot de passe" aria-pressed="false" title="Afficher le mot de passe">
                          <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                      </div>
                    </div>
                    <div class="col-12 col-md-4">
                      <label class="form-label fw-semibold">Confirmer le nouveau</label>
                      <div class="input-group">
                        <input type="password" id="profileConfirmPassword" class="form-control" placeholder="••••••••••••">
                        <button type="button" class="input-group-text bg-white" data-password-toggle="profileConfirmPassword" aria-label="Afficher le mot de passe" aria-pressed="false" title="Afficher le mot de passe">
                          <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                      </div>
                    </div>
                  </div>

                  <div class="d-flex justify-content-end">
                    <button type="submit" class="btn-gp-primary">
                      <i class="bi bi-check-lg"></i> Mettre à jour mon profil
                    </button>
                  </div>
                </form>
              </div>

              <div class="gp-card mb-0">
                <h3 class="gp-card-title mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Historique récent de mes connexions</h3>
                <div class="table-responsive">
                  <table class="gp-table">
                    <thead>
                      <tr>
                        <th>Date & Heure</th>
                        <th>Événement</th>
                        <th>Adresse IP</th>
                      </tr>
                    </thead>
                    <tbody id="myLoginHistoryTableBody">
                      <tr><td colspan="3" class="text-center text-muted py-3">Aucune connexion enregistrée.</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </section>

      </main>
    </div>
  </div>

  <!-- ==========================================================================
       CONTAINER D'AUTHENTIFICATION (INSCRIPTION, CONNEXION, DÉCONNEXION)
       ========================================================================== -->
  <div id="authLayoutContainer" class="auth-layout-container">

    <!-- 1. FORMULAIRE D'INSCRIPTION (REGISTER) -->
    <div id="authView-register" class="auth-view-screen" style="display: none; width: 100%; max-width: 960px;">
      <div class="auth-card-wrapper">
        <!-- Colonne Branding Gauche -->
        <div class="auth-branding-col">
          <div>
            <div class="d-flex align-items-center gap-2 mb-4">
              <div class="brand-icon"><i class="bi bi-box-seam"></i></div>
              <span class="brand-text">GESTION<span class="text-primary">+</span></span>
            </div>
            <h2 class="fs-4 fw-bold text-white mb-2">Rejoignez l'espace collaborateur</h2>
            <p class="text-secondary small">Simplifiez la gestion de vos demandes internes, le suivi des stocks et l'archivage de vos documents administratifs.</p>

            <ul class="auth-feature-list">
              <li class="auth-feature-item">
                <i class="bi bi-check-circle-fill"></i>
                <span>Circuit de validation structuré et notifications en temps réel</span>
              </li>
              <li class="auth-feature-item">
                <i class="bi bi-check-circle-fill"></i>
                <span>Consultation en direct des stocks, seuils et disponibilités</span>
              </li>
              <li class="auth-feature-item">
                <i class="bi bi-check-circle-fill"></i>
                <span>Édition des bons de sortie et suivi des dotations</span>
              </li>
              <li class="auth-feature-item">
                <i class="bi bi-check-circle-fill"></i>
                <span>Archive documentaire centralisée</span>
              </li>
            </ul>
          </div>

          <div class="pt-3 border-top border-secondary small text-muted d-flex align-items-center gap-2">
            <i class="bi bi-shield-lock-fill text-success fs-5"></i>
            <span>Plateforme interne • Accès réservé au personnel autorisé</span>
          </div>
        </div>

        <!-- Colonne Formulaire Droite -->
        <div class="auth-form-col">
          <div class="auth-header">
            <h1 class="auth-title">Créer mon compte</h1>
            <p class="auth-subtitle">Renseignez vos informations professionnelles pour débuter</p>
          </div>

          <form id="formRegisterUser">
            <div class="row g-3 mb-3">
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold small">Nom et prénom <span class="text-danger">*</span></label>
                <input type="text" id="regNom" class="form-control form-control-sm" placeholder="Ex: Kouamé Patrick" required>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold small">Email d'entreprise <span class="text-danger">*</span></label>
                <input type="email" id="regEmail" class="form-control form-control-sm" placeholder="prenom.nom@entreprise.ci" required>
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold small">Département / Service <span class="text-danger">*</span></label>
                <select id="regService" class="form-select form-select-sm" required>
                  <option value="">Choisir un service...</option>
                  <option value="Administration & Logistique">Administration & Logistique</option>
                  <option value="Ressources Humaines">Ressources Humaines</option>
                  <option value="Informatique & SI">Informatique & SI</option>
                  <option value="Finance & Comptabilité">Finance & Comptabilité</option>
                  <option value="Parc Automobile">Parc Automobile</option>
                  <option value="Direction Générale">Direction Générale</option>
                </select>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold small">Mot de passe <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                  <input type="password" id="regPassword" class="form-control" placeholder="••••••••••••" required>
                  <button type="button" class="input-group-text bg-white" data-password-toggle="regPassword" aria-label="Afficher le mot de passe" aria-pressed="false" title="Afficher le mot de passe">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                  </button>
                </div>
                <div class="password-strength-bar">
                  <div class="password-strength-fill"></div>
                </div>
                <small class="text-muted" style="font-size: 0.72rem;">8 caractères minimum recommandés</small>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold small">Confirmation mot de passe <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                  <input type="password" id="regPasswordConfirm" class="form-control" placeholder="••••••••••••" required>
                  <button type="button" class="input-group-text bg-white" data-password-toggle="regPasswordConfirm" aria-label="Afficher le mot de passe" aria-pressed="false" title="Afficher le mot de passe">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                  </button>
                </div>
              </div>

              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="regCharte" required checked>
                  <label class="form-check-label text-muted" for="regCharte" style="font-size: 0.8rem;">
                    J'atteste avoir pris connaissance de la <strong>charte informatique interne</strong> et des règles de gestion.
                  </label>
                </div>
              </div>
            </div>

            <button type="submit" class="btn-gp-primary w-100 justify-content-center py-2 mb-3">
              <i class="bi bi-person-plus"></i> Finaliser l'inscription
            </button>

            <div class="text-center" style="font-size: 0.85rem;">
              <span class="text-muted">Vous avez déjà un compte ?</span>
              <a href="#login" class="text-primary fw-semibold ms-1 text-decoration-none" data-view-target="login">Se connecter</a>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- 2. PAGE DE CONNEXION (LOGIN) -->
    <div id="authView-login" class="auth-view-screen" style="display: none; width: 100%; max-width: 860px;">
      <div class="auth-card-wrapper">
        <div class="auth-branding-col">
          <div>
            <div class="d-flex align-items-center gap-2 mb-4">
              <div class="brand-icon"><i class="bi bi-box-seam"></i></div>
              <span class="brand-text">GESTION<span class="text-primary">+</span></span>
            </div>
            <h2 class="fs-4 fw-bold text-white mb-2">Espace collaborateur</h2>
            <p class="text-secondary small">Connectez-vous pour gérer vos demandes internes, consulter les stocks et accéder aux opérations de votre service.</p>
          </div>

          <div class="pt-3 border-top border-secondary small text-muted">
            Direction Générale & Administration • GESTION+
          </div>
        </div>

        <div class="auth-form-col">
          <div class="auth-header">
            <h1 class="auth-title">Connexion</h1>
            <p class="auth-subtitle">Accédez à votre espace professionnel</p>
          </div>

          <form id="formLoginUser">
            <div class="mb-3">
              <label class="form-label fw-semibold small">Adresse e-mail professionnelle</label>
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-envelope text-muted"></i></span>
                <input type="email" id="loginEmail" class="form-control" placeholder="nom.prenom@entreprise.ci" required>
              </div>
            </div>

            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label fw-semibold small mb-0">Mot de passe</label>
                <a href="#" class="text-primary small text-decoration-none" id="linkForgotPassword">Mot de passe oublié ?</a>
              </div>
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-lock text-muted"></i></span>
                <input type="password" id="loginPassword" class="form-control" placeholder="••••••••••••" required>
                <button type="button" class="input-group-text bg-white" data-password-toggle="loginPassword" aria-label="Afficher le mot de passe" aria-pressed="false" title="Afficher le mot de passe">
                  <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
              </div>
            </div>

            <div class="form-check mb-4">
              <input class="form-check-input" type="checkbox" id="loginRemember" checked>
              <label class="form-check-label text-muted small" for="loginRemember">
                Rester connecté
              </label>
            </div>

            <button type="submit" class="btn-gp-primary w-100 justify-content-center py-2 mb-3">
              <i class="bi bi-box-arrow-in-right"></i> Se connecter
            </button>

            <div class="text-center" style="font-size: 0.85rem;">
              <span class="text-muted">Pas encore de compte ?</span>
              <a href="#register" class="text-primary fw-semibold ms-1 text-decoration-none" data-view-target="register">Créer un compte</a>
            </div>
          </form>
        </div>
      </div>
    </div>

  </div>

  <!-- ==========================================================================
       MODAL : APERÇU ET EXPORT PDF DU BON DE DEMANDE INTERNE
       ========================================================================== -->
  <div class="modal fade" id="modalOfficialPdf" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-light">
          <h5 class="modal-title fs-6 fw-bold text-dark d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-pdf text-danger fs-5"></i>
            Bon de Demande & Décharge de Matériel Interne (Document Officiel)
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4 bg-secondary-subtle">
          <div class="official-pdf-preview" id="printablePdfDocument">
            <div class="pdf-header">
              <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                  <div class="bg-primary text-white p-2 rounded fw-bold fs-5">
                    <i class="bi bi-box-seam"></i>
                  </div>
                  <div>
                    <h3 class="fw-bold mb-0 text-dark" style="font-size: 1.25rem;" id="pdfOrgTitle">GESTION+ ENTERPRISE</h3>
                    <small class="text-muted">Système centralisé de gestion interne des demandes, stocks & décharges</small>
                  </div>
                </div>
                <div class="small text-secondary" id="pdfOrgAddressBlock">
                  Siège Social • Direction Générale • Service Logistique & Magasin Central<br>
                  Tél : +225 27 20 00 00 00 • contact@entreprise.ci
                </div>
              </div>
              <div class="text-end">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 fs-6 fw-bold" id="pdfStatusStamp">VALIDÉ & AUTORISÉ</span>
                <div class="fw-bold text-dark fs-5 mt-2" id="pdfModalRef">DEM-00000</div>
                <small class="text-muted" id="pdfModalDate">Émis le : -</small>
              </div>
            </div>

            <div class="row g-3 mb-4 p-3 bg-light rounded" style="font-size: 0.885rem;">
              <div class="col-6">
                <div class="text-muted small">Demandeur :</div>
                <strong class="text-dark" id="pdfModalDemandeur">Demandeur</strong>
                <div class="text-muted small mt-1">Département / Service :</div>
                <strong class="text-dark" id="pdfModalService">-</strong>
              </div>
              <div class="col-6">
                <div class="text-muted small">Catégorie & Opération :</div>
                <strong class="text-primary" id="pdfModalType">Dotation & Approvisionnement</strong>
                <div class="text-muted small mt-1">Motif professionnel :</div>
                <strong class="text-dark" id="pdfModalMotif">-</strong>
              </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">Désignation des articles / prestations accordés</h5>
              <span class="badge bg-light text-dark border">Propriété exclusive de l'entreprise</span>
            </div>
            <table class="table table-bordered table-sm mb-4" style="font-size: 0.85rem;">
              <thead class="table-light">
                <tr>
                  <th>Code</th>
                  <th>Désignation</th>
                  <th>Catégorie</th>
                  <th>Unité</th>
                  <th class="text-center">Qté demandée</th>
                  <th class="text-center">Qté accordée</th>
                </tr>
              </thead>
              <tbody id="pdfModalArticlesBody">
                <tr><td colspan="6" class="text-center py-3 text-muted">Sélectionnez une demande validée pour générer son Bon officiel.</td></tr>
              </tbody>
            </table>

            <div class="pdf-signature-box">
              <div class="signature-zone zone-collab">
                <div>
                  <div class="signature-header">
                    <i class="bi bi-person-check-fill text-primary"></i>
                    <span>Le Collaborateur Demandeur</span>
                  </div>
                  <p class="signature-desc">Atteste avoir reçu le matériel ou la prestation et s'engage à un usage strictement professionnel.</p>
                </div>
                <div class="signature-stamp stamp-collab">
                  <i class="bi bi-pen-fill"></i>
                  <span id="pdfSignatureCollaborator">Signé numériquement par le demandeur</span>
                </div>
              </div>
              <div class="signature-zone zone-admin">
                <div>
                  <div class="signature-header">
                    <i class="bi bi-shield-check text-success"></i>
                    <span>Le Responsable / Autorité de Validation</span>
                  </div>
                  <p class="signature-desc">Autorise l'exécution ou la sortie de stock correspondante conformément aux procédures internes.</p>
                </div>
                <div class="signature-stamp stamp-admin">
                  <i class="bi bi-patch-check-fill"></i>
                  <span id="pdfSignatureAdmin">Visé & Validé par le Responsable</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Fermer</button>
          <button type="button" class="btn-gp-primary" id="btnPrintPdfDoc">
            <i class="bi bi-printer"></i> Imprimer / Enregistrer en PDF
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL : APERÇU IMPRIMABLE A4 DES RAPPORTS PÉRIODIQUES -->
  <div class="modal fade" id="modalReportPreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-light">
          <h5 class="modal-title fs-6 fw-bold text-dark"><i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>Aperçu Officiel du Rapport d'Activité & Gestion</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4 bg-secondary-subtle">
          <div class="official-pdf-preview" id="printableReportDocument"></div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Fermer</button>
          <button type="button" class="btn-gp-primary" id="btnPrintReportDoc"><i class="bi bi-printer"></i> Imprimer / Exporter en PDF</button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL : AFFECTATION DEMANDE À UN AGENT -->
  <div class="modal fade" id="modalAssignRequest" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header"><h5 class="modal-title fs-6 fw-bold">Affecter la demande à un agent</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form id="formAssignRequest">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">Agent en charge du traitement <span class="text-danger">*</span></label>
              <select id="assignAgentSelect" class="form-select" required><option value="">Sélectionner un agent…</option></select>
            </div>
            <div>
              <label class="form-label fw-semibold">Consigne d'exécution (optionnel)</label>
              <textarea id="assignCommentInput" class="form-control" rows="2" placeholder="Instructions pour la livraison ou l'intervention..."></textarea>
            </div>
          </div>
          <div class="modal-footer bg-light"><button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn-gp-primary">Affecter</button></div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL : VALIDATION DE DEMANDE AVEC AJUSTEMENT DES QUANTITÉS -->
  <div class="modal fade" id="modalApproveRequest" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-success-subtle">
          <h5 class="modal-title fs-6 fw-bold text-success-emphasis"><i class="bi bi-check-circle-fill me-2"></i>Validation de la demande <span id="approveModalRefLabel"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="formApproveRequestModal">
          <div class="modal-body p-4">
            <p class="small text-muted mb-3">Vérifiez les quantités accordées avant de confirmer la validation.</p>
            <div class="table-responsive mb-3" id="approveModalItemsWrapper">
              <table class="table table-sm align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Article</th>
                    <th class="text-center">Stock actuel</th>
                    <th class="text-center">Qté demandée</th>
                    <th class="text-center" style="width:140px;">Qté accordée</th>
                  </tr>
                </thead>
                <tbody id="approveModalItemsBody"></tbody>
              </table>
            </div>
            <div>
              <label class="form-label fw-semibold small">Commentaire de validation (optionnel)</label>
              <textarea id="approveModalComment" class="form-control" rows="2" placeholder="Observation éventuelle pour le demandeur ou le magasin..."></textarea>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn-gp-primary bg-success border-success"><i class="bi bi-check-lg"></i> Confirmer la validation</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL : MOTIF OBLIGATOIRE (REJET / COMPLÉMENT / ANNULATION) -->
  <div class="modal fade" id="modalRequestReason" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header">
          <h5 class="modal-title fs-6 fw-bold" id="requestReasonModalTitle">Motif obligatoire</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="formRequestReasonModal">
          <div class="modal-body p-4">
            <input type="hidden" id="requestReasonActionType">
            <input type="hidden" id="requestReasonTargetRef">
            <label class="form-label fw-semibold" id="requestReasonLabel">Veuillez saisir le motif détaillé <span class="text-danger">*</span></label>
            <textarea id="requestReasonTextarea" class="form-control" rows="3" required placeholder="Saisissez une justification claire et précise..."></textarea>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Fermer</button>
            <button type="submit" class="btn-gp-primary" id="requestReasonSubmitBtn">Confirmer</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL : ÉTAT DE RÉAPPROVISIONNEMENT (MODULE 3) -->
  <div class="modal fade" id="modalReplenishment" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-warning-subtle">
          <h5 class="modal-title fs-6 fw-bold text-dark"><i class="bi bi-cart-check me-2"></i>État de réapprovisionnement — Articles à commander</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="small text-muted">Articles en rupture ou sous le seuil minimal.</div>
            <div class="fw-bold text-primary" id="replenishmentEstimatedTotal">Coût estimatif total : 0 FCFA</div>
          </div>
          <div class="table-responsive">
            <table class="gp-table">
              <thead>
                <tr>
                  <th>Code</th>
                  <th>Désignation</th>
                  <th>Fournisseur</th>
                  <th class="text-center">Qté dispo</th>
                  <th class="text-center">Seuil min / max</th>
                  <th class="text-center">Qté suggérée</th>
                  <th class="text-end">Coût estimé</th>
                </tr>
              </thead>
              <tbody id="replenishmentTableBody"></tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Fermer</button>
          <button type="button" class="btn-gp-secondary" onclick="exportTableAsPdf('replenishment')">
            <i class="bi bi-file-earmark-pdf text-danger"></i> Exporter PDF
          </button>
          <button type="button" class="btn-gp-primary" onclick="downloadAuthenticatedFile('/api/articles-replenishment-export', 'etat-reapprovisionnement.csv')">
            <i class="bi bi-file-earmark-excel"></i> Exporter CSV
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL : DÉTAIL D'INVENTAIRE PHYSIQUE -->
  <div class="modal fade" id="modalInventoryDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header">
          <h5 class="modal-title fs-6 fw-bold" id="inventoryDetailModalTitle">Détail de l'inventaire</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4" id="inventoryDetailModalBody"></div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Fermer</button>
          <button type="button" class="btn-gp-primary" id="btnModalValidateInventory" style="display:none;"><i class="bi bi-check2-all"></i> Valider & Appliquer les ajustements</button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL : GESTION COMPLÈTE D'UN BIEN DU PATRIMOINE (MODULE 4) -->
  <div class="modal fade" id="modalAssetAction" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header">
          <h5 class="modal-title fs-6 fw-bold" id="assetModalTitle">Gestion de l'équipement</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4" id="assetModalBody"></div>
      </div>
    </div>
  </div>

  <!-- MODAL : GESTION COMPLÈTE D'UN VÉHICULE (MODULE 5) -->
  <div class="modal fade" id="modalVehicleAction" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header">
          <h5 class="modal-title fs-6 fw-bold" id="vehicleModalTitle">Gestion du véhicule</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4" id="vehicleModalBody"></div>
      </div>
    </div>
  </div>

  <!-- MODAL : GESTION & TRAÇABILITÉ FINANCIÈRE D'UNE DÉPENSE (MODULE 6) -->
  <div class="modal fade" id="modalExpenseAction" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header">
          <h5 class="modal-title fs-6 fw-bold" id="expenseModalTitle">Suivi financier & Justificatifs</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4" id="expenseModalBody"></div>
      </div>
    </div>
  </div>

  <!-- ========================================================================== 
       MODAL : NOUVEL ARTICLE DE STOCK
       ========================================================================== -->
  <div class="modal fade" id="modalNewArticle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header">
          <h5 class="modal-title fs-6 fw-bold text-dark" id="articleModalTitle">Ajouter un nouvel article en stock</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="formNewArticle">
          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-6" id="artCodePreviewWrapper" style="display: none;">
                <label class="form-label fw-semibold">Code article</label>
                <div class="form-control bg-light text-muted small" id="artCodePreview">-</div>
              </div>
              <div class="col-12" id="artCategoryWrapper">
                <label class="form-label fw-semibold">Catégorie</label>
                <select id="artCategory" class="form-select" required>
                  <option value="Papeterie">Papeterie</option>
                  <option value="Informatique">Informatique</option>
                  <option value="Fournitures">Fournitures</option>
                  <option value="Gadgets">Gadgets</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Désignation de l'article</label>
                <input type="text" id="artName" class="form-control" placeholder="Ex: Cartouche Toner HP 85A" required>
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold">Unité de mesure</label>
                <select id="artUnit" class="form-select" required>
                  <option value="Pièce">Pièce</option>
                  <option value="Rame">Rame</option>
                  <option value="Boîte">Boîte</option>
                  <option value="Unité">Unité</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold" id="articleQuantityLabel">Quantité initiale</label>
                <input type="number" id="artQty" class="form-control" value="25" min="0" required>
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold">Seuil minimal d'alerte</label>
                <input type="number" id="artThreshold" class="form-control" value="5" min="0" required>
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold">Seuil maximum</label>
                <input type="number" id="artMaximumThreshold" class="form-control" min="1" placeholder="Ex: 100">
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold">Emplacement de stockage</label>
                <input type="text" id="artLocation" list="datalistLocations" class="form-control" placeholder="Ex: Magasin Central - Rayon B3">
                <datalist id="datalistLocations"></datalist>
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold">Coût unitaire (FCFA)</label>
                <input type="number" id="artUnitCost" class="form-control" min="0" step="0.01" placeholder="Ex: 2500">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Fournisseur principal</label>
                <input type="text" id="artSupplier" list="datalistSuppliers" class="form-control" placeholder="Nom du fournisseur">
                <datalist id="datalistSuppliers"></datalist>
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn-gp-primary" id="articleSubmitButton">Créer l'article</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ==========================================================================
       MODAL : ENREGISTREMENT DE MOUVEMENT DE STOCK (MODULE 3)
       ========================================================================== -->
  <div class="modal fade" id="modalNewMovement" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header">
          <h5 class="modal-title fs-6 fw-bold text-dark">Enregistrer un mouvement de stock</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="formNewMovement">
          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold">Article concerné <span class="text-danger">*</span></label>
                <select id="mvtArticle" class="form-select" required><option value="">Sélectionner un article du stock...</option></select>
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold">Type de mouvement <span class="text-danger">*</span></label>
                <select id="mvtType" class="form-select" required>
                  <option value="Entrée">Entrée (Approvisionnement)</option>
                  <option value="Sortie">Sortie (Distribution autorisée)</option>
                  <option value="Transfert">Transfert (Emplacement / Service)</option>
                  <option value="Retour">Retour en stock</option>
                  <option value="Perte">Perte / Casse</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold">Quantité <span class="text-danger">*</span></label>
                <input type="number" id="mvtQty" class="form-control" value="1" min="1" required>
              </div>

              <!-- Champs spécifiques au Transfert -->
              <div class="col-12" id="mvtTransferFieldsWrapper" style="display:none;">
                <div class="row g-2 p-2 bg-light border rounded">
                  <div class="col-6">
                    <label class="form-label small fw-semibold mb-1">Emplacement d'origine</label>
                    <input type="text" id="mvtFromLocation" list="datalistLocations" class="form-control form-control-sm" placeholder="Actuel">
                  </div>
                  <div class="col-6">
                    <label class="form-label small fw-semibold mb-1">Nouvel emplacement</label>
                    <input type="text" id="mvtToLocation" list="datalistLocations" class="form-control form-control-sm" placeholder="Destination">
                  </div>
                  <div class="col-6">
                    <label class="form-label small fw-semibold mb-1">Service source</label>
                    <input type="text" id="mvtFromService" class="form-control form-control-sm" placeholder="Service émetteur">
                  </div>
                  <div class="col-6">
                    <label class="form-label small fw-semibold mb-1">Service destinataire</label>
                    <input type="text" id="mvtToService" class="form-control form-control-sm" placeholder="Service receveur">
                  </div>
                </div>
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold">Demande autorisée liée</label>
                <select id="mvtRequestId" class="form-select"><option value="">Aucune (Entrée / Transfert / Urgence)</option></select>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Motif / Justificatif <span class="text-danger">*</span></label>
                <input type="text" id="mvtReason" class="form-control" placeholder="Ex: Bon de livraison fournisseur, casse constatée, dotation..." required>
              </div>
              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="mvtEmergency">
                  <label class="form-check-label small" for="mvtEmergency">Sortie d’urgence exceptionnelle</label>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn-gp-primary">Valider le mouvement</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ==========================================================================
       MODAL : CRÉATION / MODIFICATION D'UTILISATEUR & PERMISSIONS (MODULE 1)
       ========================================================================== -->
  <div class="modal fade" id="modalNewUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header">
          <h5 class="modal-title fs-6 fw-bold text-dark" id="userModalTitle">Ajouter un utilisateur</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="formNewUser">
          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Nom complet <span class="text-danger">*</span></label>
                <input type="text" id="usrName" class="form-control" placeholder="Ex: Koffi Serge" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Adresse email professionnelle <span class="text-danger">*</span></label>
                <input type="email" id="usrEmail" class="form-control" placeholder="koffi.serge@entreprise.ci" required>
              </div>
              <div class="col-md-6" id="usrPasswordGroup">
                <label class="form-label fw-semibold" id="usrPasswordLabel">Mot de passe initial <span class="text-danger">*</span></label>
                <div class="input-group">
                  <input type="password" id="usrPassword" class="form-control" placeholder="••••••••••••">
                  <button type="button" class="input-group-text bg-white" data-password-toggle="usrPassword" aria-label="Afficher le mot de passe" aria-pressed="false" title="Afficher le mot de passe">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                  </button>
                </div>
                <small class="text-muted" id="usrPasswordHint">Minimum 8 caractères.</small>
              </div>
              <div class="col-md-6" id="usrMatriculeWrapper" style="display: none;">
                <label class="form-label fw-semibold">Matricule</label>
                <div class="form-control bg-light text-muted" id="usrMatriculeDisplay">-</div>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Profil / Rôle <span class="text-danger">*</span></label>
                <select id="usrRole" class="form-select" required>
                  <option value="Collaborateur">Employé / Collaborateur</option>
                  <option value="Responsable">Responsable Administratif / Logistique</option>
                  <option value="Administrateur">Administrateur / Direction</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Département / Service <span class="text-danger">*</span></label>
                <select id="usrService" class="form-select" required>
                  <option value="Administration & Logistique">Administration & Logistique</option>
                  <option value="Ressources Humaines">Ressources Humaines</option>
                  <option value="Informatique & SI">Informatique & SI</option>
                  <option value="Finance & Comptabilité">Finance & Comptabilité</option>
                  <option value="Parc Automobile">Parc Automobile</option>
                  <option value="Direction Générale">Direction Générale</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Téléphone direct</label>
                <input type="text" id="usrPhone" class="form-control" placeholder="+225 07 00 00 00 00">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Bureau / Localisation</label>
                <input type="text" id="usrOffice" class="form-control" placeholder="Ex: Siège - Bureau 105">
              </div>

              <div class="col-12 pt-2 border-top">
                <div class="mb-2">
                  <label class="form-label fw-bold mb-0">Permissions autorisées</label>
                </div>
                <div class="row g-2" id="usrPermissionsGrid"></div>
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn-gp-primary" id="usrSubmitBtn">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL : RÉINITIALISATION DE MOT DE PASSE PAR L'ADMINISTRATEUR -->
  <div class="modal fade" id="modalResetUserPassword" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header">
          <h5 class="modal-title fs-6 fw-bold">Réinitialiser le mot de passe</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="formResetUserPassword">
          <div class="modal-body p-4">
            <input type="hidden" id="resetTargetUserId">
            <p class="small text-muted mb-3">Définissez un nouveau mot de passe pour <strong id="resetTargetUserName">-</strong>.</p>
            <label class="form-label fw-semibold">Nouveau mot de passe <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="password" id="resetTargetUserPassword" class="form-control" placeholder="••••••••••••" minlength="8" required>
              <button type="button" class="input-group-text bg-white" data-password-toggle="resetTargetUserPassword" aria-label="Afficher le mot de passe" aria-pressed="false" title="Afficher le mot de passe">
                <i class="bi bi-eye" aria-hidden="true"></i>
              </button>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn-gp-primary"><i class="bi bi-key"></i> Réinitialiser</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL : HISTORIQUE GLOBAL DES CONNEXIONS (MODULE 1 & 9) -->
  <div class="modal fade" id="modalLoginHistory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header">
          <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-shield-lock text-primary me-2"></i>Journal des connexions des utilisateurs</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="table-responsive">
            <table class="gp-table">
              <thead>
                <tr>
                  <th>Date & Heure</th>
                  <th>Utilisateur</th>
                  <th>Événement</th>
                  <th>Adresse IP</th>
                  <th>Détails</th>
                </tr>
              </thead>
              <tbody id="allLoginHistoryTableBody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL : MOT DE PASSE OUBLIÉ (ÉCRAN DE CONNEXION) -->
  <div class="modal fade" id="modalForgotPassword" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header">
          <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-envelope-exclamation text-primary me-2"></i>Demande de renouvellement de mot de passe</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="formForgotPassword">
          <div class="modal-body p-4">
            <p class="small text-muted mb-3">Indiquez votre adresse e-mail professionnelle. Une notification prioritaire sera transmise à l'administrateur pour réinitialiser votre accès.</p>
            <label class="form-label fw-semibold">Adresse e-mail professionnelle <span class="text-danger">*</span></label>
            <input type="email" id="forgotPasswordEmail" class="form-control" placeholder="nom.prenom@entreprise.ci" required>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn-gp-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn-gp-primary">Envoyer la demande</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Toast Container -->
  <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer" style="z-index: 9999;"></div>

  <!-- Bootstrap 5.3 JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Chart.js 4.4 CDN -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
  <!-- Scripts applicatifs -->
  <script src="{{ asset('js/charts.js') }}?v={{ filemtime(public_path('js/charts.js')) }}"></script>
  <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
