# GESTION+ : Logiciel de Gestion des Stocks, Demandes Internes & Documents Administratifs

Maquette web interactive haute fidélité, moderne et professionnelle, conçue dans un style SaaS d'entreprise épuré (sans artifices "IA", respectant les standards stricts d'ergonomie B2B).

---

## 🚀 Comment Lancer et Consulter la Maquette

### Méthode 1 : Navigateur Direct (Zéro Installation)
Double-cliquez simplement sur le fichier **`index.html`** dans le dossier :
`C:\Users\DELL LATITUDE 5400\Desktop\GestionMateriel\index.html`

### Méthode 2 : Serveur Local (Déjà actif)
Le serveur local est actuellement actif sur votre machine à l'adresse :
👉 **[http://localhost:3000](http://localhost:3000)**

Pour relancer le serveur à tout moment dans un terminal :
```bash
npm start
# ou
npx serve . -p 3000
```

---

## 📋 Périmètre Fonctionnel Couvert par la Maquette

Conformément à vos instructions et à votre cahier des charges, l'application intègre désormais l'ensemble des modules opérationnels ainsi que le cycle complet d'authentification :

### 1. Tableau de bord (Dashboard Général)
- **Indicateurs KPI temps réel :** Articles en stock (246, +12%), Stock faible (8 alertes), Demandes en attente (12), Demandes approuvées (28, +18%).
- **Graphique 1 (Évolution des demandes) :** Histogramme mensuel dynamique (Chart.js) avec statuts *Approuvées* (bleu), *En attente* (teal), et *Rejetées* (rouge doux).
- **Graphique 2 (Répartition des articles) :** Donut chart interactif avec indicateur centralisé (Papeterie 35%, Informatique 28%, Fournitures 20%, Gadgets 12%, Autres 5%).
- **Dernières demandes & Alertes stocks critiques :** Visualisation rapide des articles sous le seuil d'alerte et des demandes récentes.

### 2. Module Stocks : Articles Disponibles
- **Tableau de gestion des articles :** Code (ART-001...), Désignation, Catégorie, Unité de mesure (Rame, Pièce, Boîte), Quantité disponible, Seuil d'alerte, Statut avec pastilles colorées (*En stock*, *Stock faible*).
- **Filtres multicritères :** Filtrage instantané par catégorie, statut, et champ de recherche textuelle sans rechargement de page.
- **Modale d'ajout rapide :** `+ Nouvel article` avec saisie des paramètres métier.

### 3. Module Demandes : Assistant "Nouvelle Demande" (Stepper 3 Étapes)
- **Étape 1 (Sélection des articles) :** Panier interactif avec cases à cocher, recherche rapide, ajustement des quantités via compteurs `[-] [quantité] [+]`, et compteur d'articles sélectionnés.
- **Étape 2 (Informations) :** Motif de la demande, service demandeur, niveau de priorité, date souhaitée, et justifications.
- **Étape 3 (Validation & Soumission) :** Récapitulatif automatique et génération de la demande avec notification toast de confirmation.

### 4. Module Demandes : Détails & Circuit de Validation (`DEM-2025-00045`)
- **En-tête de référence :** Badge de statut en temps réel, demandeur et horodatage.
- **Onglets métiers :** *Informations*, *Articles (3)*, *Historique d'audit*, *Documents rattachés*.
- **Timeline de validation visuelle :** Traçabilité directe du circuit : *Demande soumise* -> *En attente d'approbation* -> *Approuvée & Bon émis* (Statut final).
- **Circuit d'action interactif :** Décisions directes du gestionnaire : *Approuver & Émettre le Bon* ou *Rejeter* (avec motif notifié au collaborateur).
- **Générateur / Aperçu PDF Officiel :** Modale de génération du **Bon de Demande Interne & Sortie de Matériel** prêt à imprimer avec signatures numériques et en-tête institutionnel.

### 5. Module Documents : Nouveau Document Administratif
- Formulaire d'enregistrement complet : Titre, Catégorie, Type (PDF, Word, Excel), Service émetteur, Référence.
- **Zone de dépôt (Drag & Drop) :** Espace de glisser-déposer de fichiers sécurisé avec prévisualisation.

### 6. Module Documents : Classement & Archivage Numérique
- Consultation, filtrage par catégorie (Procédures, Notes de service, Règlements, Plans, Organigramme) et type de fichier.
- Icônes de format différenciées (PDF rouge, Word bleu, Excel vert).
- Actions de téléchargement direct.

### 7. Module Stocks : Mouvements de Stock (Traçabilité)
- Historique exhaustif des flux d'entrées et de sorties d'articles.
- Filtres par période, type d'opération (*Entrée* / *Sortie*) et recherche par motif ou utilisateur.
- Modale d'enregistrement de mouvement d'inventaire.

### 8. Module Administration : Gestion des Utilisateurs
- Table complète des comptes : Nom, Prénom, Avatar, Email, Profil (*Administrateur*, *Responsable*, *Collaborateur*), Service, Statut (*Actif* / *Inactif*).
- Modale de création d'un nouvel utilisateur.

### 9. Module Paramètres Système (Nouveau)
- **4 onglets de configuration :**
  - *Général & Entreprise* : Raison sociale, sigle, devise par défaut (FCFA/EUR/USD), format des références (`DEM-YYYY-XXXXX`), fuseau horaire, adresse officielle.
  - *Stocks & Alertes* : Seuil d'alerte par défaut, notification automatique par e-mail au responsable, blocage des sorties en cas de rupture, fréquence d'inventaire.
  - *Circuit de validation* : Nombre de niveaux de validation hiérarchique, délai d'expiration des demandes, règles de rejet.
  - *Sécurité & Sauvegardes* : Délai d'inactivité avant déconnexion automatique, politique des mots de passe, état des sauvegardes de la base MySQL.

### 10. Module Profil Collaborateur (Nouveau)
- Consultation et mise à jour des coordonnées directes, bureau, téléphone, changement de mot de passe et préférences de notification.

### 11. Formulaire d'Inscription Collaborateur (`#register`) (Nouveau)
- Présentation corporate double-colonne avec rappel des engagements de service.
- Formulaire complet : Nom & Prénom, e-mail professionnel, département/service, matricule interne, mot de passe avec jauge visuelle de robustesse, confirmation, et validation obligatoire de la charte informatique interne.

### 12. Page de Connexion (`#login`) (Nouveau)
- Interface de connexion épurée et sécurisée avec accès direct 1-clic pour tester les deux profils (*Aminata* - Collaborateur et *Jean* - Admin/Validateur).

### 13. Page de Déconnexion Sécurisée (`#logout`) (Nouveau)
- Écran officiel de confirmation de clôture de session avec récapitulatif de sécurité (horodatage exact, réseau d'accès, chiffrement des données) et bouton direct pour se reconnecter.

---

## 🎨 Particularités du Design (Anti-Effet "IA")

Pour répondre spécifiquement à votre exigence (**"je veux un style jolie qui doit du tout pas ressembler a l'ia"**) :
1. **Pas de dégradés néon violet/rose artificiels :** Utilisation d'une palette corporate éprouvée (bleu institutionnel `#2563eb`, ardoise foncée `#0f172a`, fond `#f8fafc`, bordures nettes `#e2e8f0`).
2. **Typographie d'excellence :** Police *Plus Jakarta Sans* et *Inter*, standard de l'industrie du logiciel professionnel.
3. **Micro-interactions naturelles :** Ombres portées très légères, transitions de 150ms, retour haptique visuel sur chaque clic.
4. **Barre de démonstration inférieure (Prototype Switcher) :** 
   - Permet de basculer en un seul clic entre les 8 écrans de la maquette.
   - Intègre un commutateur de rôle : **Aminata (Collaboratrice)** ↔ **Jean (Administrateur / Validateur)** pour tester l'adaptation des droits et des boutons d'approbation.
