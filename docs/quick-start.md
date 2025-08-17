# ⚡ Guide de démarrage

## 🚀 Installation

### Prérequis
- **PHP 8.3+** avec extensions Symfony
- **Node.js 18+** avec Yarn
- **Docker** installé (pour MySQL et MailHog)
- **Git** et **Make**
- **Composer** installé globalement

### Installation
```bash
git clone https://github.com/yourusername/videoai-studio.git && cd videoai-studio
make init        # Installation complète (deps + backend + frontend)
make serve       # Serveur Symfony sur http://localhost:8000
```

### Commandes utiles
```bash
make fixtures    # Recharger les fixtures uniquement
make static      # Qualité code (PHPStan + ECS)
make ci          # Pipeline CI complète
make ecs         # Fix automatique du style de code
make up          # Démarrer Docker seulement
make down        # Arrêter Docker
make integration # Tests d'intégration
```

## 🌐 Accès

| Interface | URL | Login |
|-----------|-----|-------|
| **Shop** | http://localhost:8000 | user@aivideo.com / root |
| **Admin** | http://localhost:8000/admin | admin / root |
| **API Docs** | http://localhost:8000/api/v2/docs | JWT required |
| **MailHog** | http://localhost:8025 | - |

## 💡 Premier test

### 1. Acheter des tokens
1. Aller sur http://localhost:8000
2. Se connecter avec `user@aivideo.com` / `root`
3. Parcourir les packs disponibles (Starter 10k, Pro 50k, Business 100k tokens)
4. Ajouter un pack au panier et valider la commande

### 2. Générer une vidéo
1. Aller dans "Video Generation"
2. Saisir un prompt : "A cat playing piano"
3. Voir le débit de tokens depuis votre wallet
4. Suivre le statut dans l'historique

### 3. Interface admin
1. Aller sur http://localhost:8000/admin
2. Login : `admin` / `root`
3. Consulter les commandes et wallets
4. Voir les détails des transactions wallet

## 🔧 Commandes utiles

```bash
# Infrastructure
make init        # Installation complète (Docker + deps + DB + fixtures)
make install     # Installation Sylius + fixtures uniquement
make up          # Démarrer les services Docker
make down        # Arrêter les services
make clean       # Nettoyer complètement (supprime volumes)

# Développement  
make serve       # Démarrer serveur Symfony
make dev         # Assets en mode watch
make frontend    # Build assets production

# Base de données
make fixtures    # Recharger les fixtures

# Qualité code
make phpunit     # Tests PHPUnit
make phpstan     # Analyse statique
make ecs         # Corriger code style
make static      # PHPStan + ECS
make ci          # Pipeline complète
```



---

✨ **Bravo !** VideoAI Studio est prêt à l'emploi.