# 🏢 Plugin SCI - Recherche et Contact de Sociétés Civiles Immobilières

Un plugin WordPress complet pour rechercher, gérer et contacter des SCI (Sociétés Civiles Immobilières) via les APIs officielles INPI et La Poste.

## 📋 Table des matières

- [Fonctionnalités](#-fonctionnalités)
- [Prérequis](#-prérequis)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Utilisation](#-utilisation)
- [Shortcodes](#-shortcodes)
- [APIs utilisées](#-apis-utilisées)
- [Structure du plugin](#-structure-du-plugin)
- [Changelog](#-changelog)
- [Support](#-support)

## 🚀 Fonctionnalités

### 🔍 **Recherche de SCI**
- Recherche par code postal via l'API INPI
- Affichage des informations complètes (dénomination, dirigeant, SIREN, adresse)
- Liens Google Maps intégrés pour localiser les adresses
- Statut de contact pour éviter les doublons

### ⭐ **Gestion des favoris**
- Système de favoris avec stockage en base de données
- Synchronisation automatique entre localStorage et BDD
- Page dédiée pour gérer ses favoris

### 📬 **Campagnes de lettres**
- Création de campagnes personnalisées
- Génération automatique de PDFs
- Envoi via l'API La Poste (LRAR, LR, etc.)
- Suivi complet des envois avec UID de tracking

### 💳 **Système de paiement**
- Intégration WooCommerce pour le paiement sécurisé
- Checkout embarqué dans un popup
- Traitement automatique après paiement confirmé
- Fallback vers envoi direct si WooCommerce indisponible

### 🔐 **Sécurité et configuration**
- Gestion automatique des tokens INPI
- Configuration sécurisée des APIs
- Stockage chiffré des identifiants
- Interface d'administration complète

## 📋 Prérequis

### **Obligatoires**
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+

### **Recommandés**
- WooCommerce 6.0+ (pour le système de paiement)
- Advanced Custom Fields (ACF) (pour les champs utilisateur étendus)
- SSL activé (pour les APIs externes)

### **APIs requises**
- **Compte INPI** avec accès API
- **Compte La Poste** avec clé API pour l'envoi de lettres

## 🛠 Installation

### **1. Installation du plugin**
```bash
# Via l'admin WordPress
1. Télécharger le fichier ZIP du plugin
2. Aller dans Extensions > Ajouter
3. Téléverser le fichier ZIP
4. Activer le plugin

# Via FTP
1. Extraire le dossier dans /wp-content/plugins/
2. Activer depuis l'admin WordPress
```

### **2. Configuration des APIs**
1. Aller dans **SCI > Configuration**
2. Renseigner vos tokens API INPI et La Poste
3. Configurer les paramètres d'envoi La Poste
4. Tester la connexion

### **3. Configuration des identifiants INPI**
1. Aller dans **SCI > Identifiants INPI**
2. Saisir vos identifiants de connexion INPI
3. Le token sera généré automatiquement

## ⚙️ Configuration

### **🔑 APIs et tokens**

#### **INPI (Institut National de la Propriété Industrielle)**
- **URL API** : `https://registre-national-entreprises.inpi.fr/api/companies`
- **Authentification** : Bearer Token (généré automatiquement)
- **Utilisation** : Recherche des SCI par code postal

#### **La Poste**
- **URL API** : `https://api.servicepostal.com/lettres` (production)
- **URL Sandbox** : `https://sandbox-api.servicepostal.com/lettres`
- **Authentification** : Clé API
- **Utilisation** : Envoi de lettres recommandées

### **📮 Paramètres d'envoi La Poste**

| Paramètre | Options | Défaut | Description |
|-----------|---------|---------|-------------|
| **Type d'affranchissement** | lrar, lr, prioritaire, suivi, verte, etc. | `lrar` | Détermine le service postal |
| **Taille d'enveloppe** | auto, c4, c5, c6 | `auto` | Format de l'enveloppe |
| **Type d'enveloppe** | fenetre, imprime | `fenetre` | Enveloppe à fenêtre ou imprimée |
| **Couleur** | nb, couleur | `nb` | Impression noir/blanc ou couleur |
| **Recto-verso** | recto, rectoverso | `rectoverso` | Mode d'impression |

### **👤 Configuration utilisateur**

Le plugin récupère automatiquement les données expéditeur depuis :
1. **Champs ACF** (priorité) : `prenom_user`, `nom_user`, `adresse_user`, etc.
2. **WooCommerce** : `billing_first_name`, `billing_address_1`, etc.
3. **WordPress** : `first_name`, `last_name`, etc.

## 📖 Utilisation

### **1. Recherche de SCI**
1. Aller dans **SCI** dans l'admin WordPress
2. Sélectionner un code postal (configuré dans votre profil ACF)
3. Cliquer sur "🔍 Rechercher les SCI"
4. Consulter les résultats dans le tableau

### **2. Gestion des favoris**
- Cliquer sur l'⭐ pour ajouter/retirer des favoris
- Consulter vos favoris dans **SCI > Mes Favoris**

### **3. Création d'une campagne**
1. Sélectionner les SCI avec les checkboxes
2. Cliquer sur "📬 Créer une campagne"
3. Rédiger le titre et contenu de votre lettre
4. Vérifier le récapitulatif
5. Procéder au paiement (si WooCommerce activé)
6. Les lettres sont envoyées automatiquement

### **4. Suivi des campagnes**
- Consulter l'historique dans **SCI > Mes Campagnes**
- Voir le détail de chaque envoi avec les UID de tracking
- Consulter les logs d'API dans **SCI > Logs API**

## 📝 Shortcodes

### **[sci_panel]**
Affiche le panneau de recherche complet avec toutes les fonctionnalités.

```php
// Usage simple
[sci_panel]

// Avec paramètres (optionnel)
[sci_panel codes_postaux="75001,75002" theme="dark"]
```

### **[sci_favoris]**
Affiche la liste des SCI favorites de l'utilisateur connecté.

```php
[sci_favoris]
```

### **[sci_campaigns]**
Affiche l'historique des campagnes de l'utilisateur connecté.

```php
[sci_campaigns]
```

### **Configuration des URLs**
Dans **SCI > Configuration**, configurez les URLs de vos pages contenant les shortcodes pour les redirections automatiques.

## 🔧 APIs utilisées

### **🏛️ API INPI**
- **Endpoint** : `/api/companies`
- **Méthode** : GET
- **Paramètres** : `companyName=SCI&zipCodes[]=75001&pageSize=100`
- **Authentification** : Bearer Token (auto-généré)
- **Rate limiting** : Respecté automatiquement

### **📮 API La Poste**
- **Endpoint** : `/lettres`
- **Méthode** : POST
- **Format** : JSON avec PDF en base64
- **Authentification** : Clé API dans les headers
- **Services** : LRAR, LR, Prioritaire, Suivi, etc.

## 📁 Structure du plugin

```
my-istymo-sci-plugin/
├── 📄 my-istymo-sci-plugin.php     # Fichier principal
├── 📄 popup-lettre.php             # Gestion des popups
├── 📄 README.md                    # Documentation
├── 📄 CHANGELOG.md                 # Historique des versions
├── 📁 assets/
│   ├── 📁 css/
│   │   └── 📄 style.css            # Styles du plugin
│   └── 📁 js/
│       ├── 📄 favoris.js           # Gestion des favoris
│       ├── 📄 lettre.js            # Interface des lettres
│       └── 📄 payment.js           # Système de paiement
├── 📁 includes/
│   ├── 📄 campaign-manager.php     # Gestionnaire de campagnes
│   ├── 📄 config-manager.php       # Configuration sécurisée
│   ├── 📄 favoris-handler.php      # Gestion des favoris
│   ├── 📄 inpi-token-manager.php   # Tokens INPI automatiques
│   ├── 📄 shortcodes.php           # Shortcodes frontend
│   └── 📄 woocommerce-integration.php # Intégration WooCommerce
└── 📁 lib/
    └── 📁 tcpdf/                   # Bibliothèque PDF
```

## 🗃️ Base de données

### **Tables créées**
- `wp_sci_favoris` - Stockage des favoris utilisateur
- `wp_sci_campaigns` - Campagnes de lettres
- `wp_sci_campaign_letters` - Détail des envois
- `wp_sci_inpi_credentials` - Historique des tokens INPI

### **Métadonnées WooCommerce**
- `_sci_campaign_data` - Données de la campagne
- `_sci_campaign_status` - Statut du traitement
- `_sci_campaign_id` - ID de la campagne liée

## 🔒 Sécurité

### **Authentification**
- Tokens stockés de manière chiffrée
- Nonces WordPress pour toutes les requêtes AJAX
- Vérification des permissions utilisateur

### **Validation des données**
- Sanitisation de tous les inputs
- Validation des formats (SIREN, codes postaux, etc.)
- Protection contre les injections SQL

### **APIs externes**
- Gestion des erreurs et timeouts
- Retry automatique en cas d'échec
- Logs détaillés pour le debugging

## 📊 Logs et debugging

### **Fichiers de logs**
- **Emplacement** : `/wp-content/uploads/lettre-laposte/logs.txt`
- **Contenu** : Requêtes API, réponses, erreurs
- **Rotation** : Manuelle via l'interface admin

### **Consultation des logs**
1. Aller dans **SCI > Logs API**
2. Consulter les 100 dernières entrées
3. Analyser les codes de réponse et messages d'erreur

## 🔄 Changelog

### **Version 1.6 (Actuelle)**
- ✅ Intégration WooCommerce complète
- ✅ Système de paiement embarqué
- ✅ Gestion automatique des tokens INPI
- ✅ Shortcodes frontend
- ✅ Interface responsive améliorée
- ✅ Logs API détaillés

### **Version 1.5**
- ✅ Gestionnaire de campagnes
- ✅ Configuration sécurisée des APIs
- ✅ Système de favoris en BDD
- ✅ Génération PDF avec TCPDF

### **Version 1.4**
- ✅ Intégration API La Poste
- ✅ Interface de recherche SCI
- ✅ Gestion des favoris localStorage

## 🆘 Support et dépannage

### **Problèmes courants**

#### **❌ "Token INPI non configuré"**
**Solution** : Aller dans **SCI > Identifiants INPI** et configurer vos identifiants de connexion.

#### **❌ "Erreur API La Poste"**
**Solutions** :
1. Vérifier la clé API dans **SCI > Configuration**
2. Consulter les logs dans **SCI > Logs API**
3. Vérifier que l'URL API est correcte (sandbox vs production)

#### **❌ "Données expéditeur incomplètes"**
**Solution** : Compléter votre profil utilisateur avec les champs ACF ou WooCommerce requis.

#### **❌ "WooCommerce requis"**
**Solution** : Installer WooCommerce ou utiliser le mode envoi direct (sans paiement).

### **Debugging**
1. Activer `WP_DEBUG` dans `wp-config.php`
2. Consulter les logs dans **SCI > Logs API**
3. Vérifier les erreurs PHP dans `/wp-content/debug.log`

### **Performance**
- **Cache** : Les tokens INPI sont mis en cache 24h
- **Optimisation** : Pagination automatique des résultats
- **Timeout** : 30 secondes pour les requêtes API

## 👨‍💻 Développement

### **Hooks disponibles**
```php
// Avant envoi d'une lettre
do_action('sci_before_send_letter', $entry, $campaign_data);

// Après envoi réussi
do_action('sci_after_send_letter', $entry, $response);

// Erreur d'envoi
do_action('sci_send_letter_error', $entry, $error);
```

### **Filtres disponibles**
```php
// Modifier le contenu de la lettre
$content = apply_filters('sci_letter_content', $content, $entry);

// Modifier les paramètres La Poste
$params = apply_filters('sci_laposte_params', $params, $entry);
```

## 📞 Contact

- **Auteur** : Brio Guiseppe
- **Version** : 1.6
- **Licence** : GPL v2 ou ultérieure
- **Support** : Via l'interface d'administration WordPress

---

## 🎯 Roadmap

### **Version 1.7 (Prévue)**
- [ ] Interface de statistiques avancées
- [ ] Export des campagnes en CSV/Excel
- [ ] Templates de lettres prédéfinis
- [ ] Intégration avec d'autres APIs immobilières

### **Version 1.8 (Prévue)**
- [ ] Mode multi-utilisateur avec permissions
- [ ] API REST pour intégrations tierces
- [ ] Webhook pour notifications en temps réel
- [ ] Interface mobile dédiée

---

**🚀 Plugin SCI - Simplifiez vos démarches de prospection immobilière !**