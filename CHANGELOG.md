# 📋 Changelog - Différences entre versions

## 🔍 **Différences identifiées entre Bolt et GitHub**

### **📝 1. Modifications dans `assets/js/lettre.js`**
- **Titre par défaut** : `"Contact SCI - Opportunité d'acquisition"` → `"Campagne 01"`
- **Texte d'aide** : Reformulation plus claire pour l'index `[NOM]`

### **💳 2. Modifications dans `assets/js/payment.js`**
- **Service description** : `"Envoi en lettre recommandée"` → `"Envoi en lettre recommandée avec accusé de réception"`
- **Suppression header checkout** : Retrait de la section "Paiement sécurisé WooCommerce"
- **🚫 NOUVELLE FONCTIONNALITÉ** : Désactivation du menu contextuel après paiement
- **⏰ Timeout modifié** : 15 secondes → 30 secondes pour réactivation automatique
- **🛡️ Sécurité renforcée** : Vérifications d'existence des éléments DOM

### **🔧 3. Modifications dans `includes/shortcodes.php`**
- **Optimisation chargement** : Suppression de la méthode 2 de vérification des posts
- **Versions CSS/JS** : Incrémentation `1.0.1` → `1.0.2`
- **Localisation améliorée** : Variable statique pour éviter les doublons
- **Cache busting** : Ajout de `Date.now()` pour forcer le rechargement
- **Gestion d'erreurs** : Callbacks `onload` et `onerror` pour les scripts

## ✅ **Nouvelles fonctionnalités ajoutées**

### **🚫 Désactivation du menu contextuel**
```javascript
// Nouvelles fonctions dans payment.js
- disableContextMenu()
- enableContextMenu()
- preventContextMenu()
- preventKeyboardShortcuts()
- preventDragDrop()
```

### **🔒 Sécurité renforcée**
- Désactivation F12, Ctrl+Shift+I, Ctrl+U, etc.
- Désactivation sélection de texte
- Désactivation glisser-déposer
- Réactivation automatique après 30 secondes

### **⚡ Performance améliorée**
- Éviter les doublons de localisation
- Cache busting intelligent
- Vérifications d'existence avant manipulation DOM
- Gestion d'erreurs robuste

## 🎯 **Recommandations de synchronisation**

### **🔄 Pour GitHub → Bolt**
Si vous voulez synchroniser GitHub vers Bolt :
1. Récupérer les améliorations de sécurité
2. Intégrer la désactivation du menu contextuel
3. Appliquer les optimisations de performance

### **🔄 Pour Bolt → GitHub**
Si vous voulez synchroniser Bolt vers GitHub :
1. Pousser les corrections de bugs
2. Mettre à jour les versions CSS/JS
3. Ajouter les nouvelles fonctionnalités de sécurité

## 📊 **Résumé des changements**

| Fichier | Changements | Impact |
|---------|-------------|---------|
| `lettre.js` | Textes et titre par défaut | Mineur |
| `payment.js` | Sécurité + menu contextuel | **Majeur** |
| `shortcodes.php` | Performance + cache busting | **Important** |

## 🚀 **Version recommandée**
La version **Bolt** est plus avancée avec :
- ✅ Sécurité renforcée
- ✅ Désactivation menu contextuel
- ✅ Gestion d'erreurs améliorée
- ✅ Performance optimisée