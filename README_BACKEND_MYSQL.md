# 🚀 GESTION+ : Backend Laravel & Configuration MySQL

Ce projet dispose désormais d'un **backend complet prêt pour la production** propulsé par **PHP 8 / Laravel** et configuré pour se connecter à votre base de données **MySQL**.

---

## 📌 Architecture & Modules Mis en Place

Le backend prend en charge l'ensemble du cycle de vie opérationnel défini dans votre cahier des charges :

1. **Parc et Équipements de Stock (`articles`)** :
   - Gestion des codes articles, désignations, catégories (Informatique, Papeterie, Gadgets), unités, quantités et seuils d'alerte.
   - Calcul automatique des statuts : *En stock*, *Stock faible*, *Rupture*.
   
2. **Circuit des Demandes Internes d'Emprunt (`material_requests` & `request_items`)** :
   - Consultation des matériels disponibles par les collaborateurs.
   - Enregistrement des demandes avec motif, département et niveau d'urgence.
   - Séparation stricte des profils (Collaborateur vs Gestionnaire/Admin).

3. **Validation & Décompte Automatique (`MaterialRequestController@approve`)** :
   - Approbation par le gestionnaire.
   - Décompte instantané des quantités en stock.
   - Enregistrement d'un mouvement de sortie traçable dans `stock_movements`.
   - **Génération automatique du Document Récapitulatif Officiel (`Bon_de_Decharge_{REF}.pdf`)** archivé dans `administrative_documents`.

---

## 🛠️ Comment connecter votre base de données MySQL

Le serveur MySQL local est déjà détecté sur le port **3306**. Il vous suffit de renseigner votre mot de passe et d'importer les tables.

### Étape 1 : Configurer vos identifiants dans `.env`

Ouvrez le fichier [`.env`](file:///c:/Users/DELL%20LATITUDE%205400/Desktop/GestionMateriel/.env) à la racine du projet et adaptez les lignes suivantes avec vos identifiants MySQL (WampServer, XAMPP, Laragon ou MySQL autonome) :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestion_materiel
DB_USERNAME=root
DB_PASSWORD=VOTRE_MOT_DE_PASSE_ICI
```

> **Note :** Si vous utilisez Laragon ou WampServer sans mot de passe, laissez `DB_PASSWORD=` vide. Si vous avez un mot de passe (ex: `root` ou votre mot de passe personnalisé), écrivez-le après le signe `=`.

---

### Étape 2 : Initialiser la Base de Données

Vous avez **deux méthodes au choix** selon vos préférences :

#### 👉 Méthode A : Import du fichier SQL (Recommandée avec phpMyAdmin / Workbench)
Un script SQL complet avec structure et données de test est disponible dans le dossier `database` :
- Fichier : [`database/gestion_materiel.sql`](file:///c:/Users/DELL%20LATITUDE%205400/Desktop/GestionMateriel/database/gestion_materiel.sql)
- Ouvrez **phpMyAdmin** (ex: `http://localhost/phpmyadmin`), cliquez sur l'onglet **Importer**, sélectionnez `database/gestion_materiel.sql` et cliquez sur **Exécuter**.

#### 👉 Méthode B : Ligne de commande Artisan
Dans un terminal PowerShell à la racine du projet :
```powershell
php artisan migrate --seed
```
*Cette commande crée automatiquement les 6 tables et insère les comptes utilisateurs, le stock de démonstration et les demandes d'emprunt.*

---

## 🖥️ Démarrage du Serveur Backend

Le serveur tourne actuellement en arrière-plan. Si vous souhaitez le relancer vous-même à l'avenir :

```powershell
php artisan serve
```

L'application sera accessible sur :
- **Interface Web Complète :** [http://127.0.0.1:8000/](http://127.0.0.1:8000/)
- **Vérification API Santé & MySQL :** [http://127.0.0.1:8000/api/health](http://127.0.0.1:8000/api/health)

---

## 📡 Liste des Endpoints RESTful de l'API

| Méthode | Route | Rôle / Description |
|---|---|---|
| `GET` | `/api/health` | Vérifie l'état du serveur Laravel et la liaison MySQL |
| `GET` | `/api/dashboard` | Statistiques et indicateurs consolidés (KPIs) |
| `GET` | `/api/articles` | Liste filtrable des matériels et fournitures en stock |
| `POST` | `/api/articles` | Création d'un nouvel équipement (Admin / Gestionnaire) |
| `GET` | `/api/requests` | Liste des demandes d'emprunt |
| `POST` | `/api/requests` | Soumission d'une nouvelle demande interne d'emprunt |
| `PUT` | `/api/requests/{id}/approve` | **Validation gestionnaire + décompte stock + bon décharge** |
| `PUT` | `/api/requests/{id}/reject` | Refus motivé d'une demande avec notification |
| `GET` | `/api/movements` | Journal d'audit des entrées et sorties de stock |
| `POST` | `/api/movements` | Déclaration manuelle d'un mouvement de stock |
| `GET` | `/api/documents` | Registre des documents administratifs et bons archivés |

---

## 👤 Comptes de Test Pré-configurés

- **Gestionnaire / Administrateur :**
  - Email : `jean.ouedraogo@entreprise.ci`
  - Mot de passe : `Password2025!`
  - Rôle : *Gestionnaire de Stock & Administrateur SI*
- **Collaboratrice :**
  - Email : `aminata.kone@entreprise.ci`
  - Mot de passe : `Password2025!`
  - Rôle : *Collaboratrice (Ressources Humaines)*
