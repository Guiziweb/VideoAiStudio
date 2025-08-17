# 🎬 VideoAI Studio

> Plateforme e-commerce moderne basée sur **Sylius 2.x** avec génération de vidéos par IA et système de wallet/tokens intégré.

[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-blue.svg)](https://www.php.net/)
[![Sylius 2.x](https://img.shields.io/badge/Sylius-2.x-green.svg)](https://sylius.com/)
[![Symfony 7](https://img.shields.io/badge/Symfony-7-purple.svg)](https://symfony.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

## 📚 Documentation

- **[Quick Start](docs/quick-start.md)** - Démarrage rapide en 5 minutes
- **[Architecture](docs/architecture.md)** - Vue d'ensemble technique et patterns
- **[Video Domain](docs/video-domain.md)** - Système de génération vidéo IA
- **[Wallet Domain](docs/wallet-domain.md)** - Système wallet/tokens avec graphiques
- **[Shared Domain](docs/shared-domain.md)** - Interactions cross-domain et extensions Sylius

## ⚡ Démarrage rapide

**Prérequis :** PHP 8.3+, Node.js 18+, Docker, Composer, Yarn

```bash
git clone https://github.com/yourusername/videoai-studio.git && cd videoai-studio
make init       # Installation complète (deps + backend + frontend)
make serve      # Serveur Symfony sur http://localhost:8000
```

**🎯 Commandes essentielles :**
```bash
make static     # Qualité code (PHPStan + ECS)
make ci         # Pipeline CI complète
make ecs        # Fix code style automatiquement
```

**➡️ Voir le [Guide de démarrage détaillé](docs/quick-start.md)**


## 🎯 Concept

**VideoAI Studio** combine la puissance de **Sylius e-commerce** avec l'IA générative pour créer une plateforme unique :

- **🎬 Génération vidéo IA** via prompts utilisateur
- **💳 Système wallet/tokens** avec graphiques d'évolution en temps réel
- **🛍️ E-commerce** pour achats de tokens avec argent réel
- **📊 Dashboard avancé** avec composants Live et analytics


## 📄 License

VideoAI Studio est un logiciel open source sous [license MIT](LICENSE).


