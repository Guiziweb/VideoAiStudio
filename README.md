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
git clone https://github.com/guiziweb/videoai-studio.git && cd videoai-studio
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
make messenger   # Lance le consumer des messages async
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
5. Valider le paiment de la commande en BO

### 2. Générer une vidéo
1. Aller dans "Video Generation"
2. Saisir un prompt : "A cat playing piano"
3. Voir le débit de tokens depuis votre wallet
4. Suivre le statut dans l'historique

### 3. Interface admin
1. Aller sur http://localhost:8000/admin
2. Login : `admin` / `root`
3. Consulter les commandes et wallets

## 🏗️ Architecture & Fonctionnalités

### 🎯 Domaines métier

#### 1. **Wallet** - Système de portefeuille numérique
- **Entité principale** : `Wallet` avec balance en tokens
- **Relation** : 1:1 avec Customer (création automatique)
- **Fonctionnalités** :
  - Crédit/débit de tokens avec validation de fonds
  - Paiement via wallet pour les générations vidéo
  - Système de commandes pour l'achat de token packs

#### 2. **Video** - Génération de vidéos IA
- **Entité principale** : `VideoGeneration` avec prompt utilisateur
- **Workflow d'états** : created → submitted → processing → completed/failed
- **Intégrations** : Providers externes (RunPod, Mock) via interface
- **Fonctionnalités** :
  - Soumission de prompts avec coût en tokens
  - Suivi asynchrone via Messenger
  - Stockage et diffusion des vidéos générées

#### Providers IA
- **Interface unifiée** : `VideoGenerationProviderInterface`
- **Providers** : RunPod, Mock (développement)
- **Extensibilité** : Ajout facile de nouveaux providers

### 🔄 Processus métier bout-en-bout

#### 1. **Achat de tokens**
```
Customer → Cart → Order → Payment → Wallet.credit() → Tokens disponibles
```

#### 2. **Génération de vidéo**
```
Prompt → Validation tokens → VideoGeneration.create()
   ↓
Workflow: created → submit → Provider.submitJob()
   ↓
État: submitted → Messenger.dispatch(CheckStatus)
   ↓
Polling async → Provider.getStatus() → processing/completed/failed
   ↓
Vidéo stockée → Notification utilisateur
```

#### 3. **États des workflows**

**Video Generation Workflow** :
- `created` : Génération créée, tokens débités
- `submitted` : Envoyée au provider IA
- `processing` : En cours de traitement
- `completed` : Vidéo prête, URL disponible
- `failed` : Échec, tokens remboursables

**Payment Workflow** (Sylius) :
- `cart` → `new` → `completed`/`failed`
- Intégration wallet pour paiement par tokens

### 🔧 Configuration clé

#### Services principaux
```yaml
# Video providers
App\IA\Provider\VideoGenerationProviderInterface: '@App\IA\Provider\MockProvider'

# Messenger async
transports:
  video_async: 'doctrine://default?queue_name=video_status'
```

#### Fixtures & données
- **Token Products** : 3 packs (Starter 10K, Pro 50K, Business 100K)
- **Channels** : Multi-devises (FR/US) 
- **Users** : Admin + shop user de test
