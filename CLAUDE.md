# Claude Configuration

## 🚀 Quick Commands

**➡️ Voir les commandes complètes dans @README.md**

## 🗄️ Accès Base de Données

### MySQL via Docker
```bash
# Lister les bases
docker exec -i $(docker ps -qf "name=mysql") mysql -uroot -e "SHOW DATABASES;"

# Accès direct à sylius_dev (pas de mot de passe)
docker exec -i $(docker ps -qf "name=mysql") mysql -uroot sylius_dev

# Requêtes directes
docker exec -i $(docker ps -qf "name=mysql") mysql -uroot sylius_dev -e "SELECT * FROM sylius_customer;"

# Exemple: Ajouter des tokens à un wallet
docker exec -i $(docker ps -qf "name=mysql") mysql -uroot sylius_dev -e "UPDATE wallet SET balance = balance + 100000 WHERE customer_id = (SELECT id FROM sylius_customer WHERE email = 'user@aivideo.com');"

# Vérifier le solde d'un wallet
docker exec -i $(docker ps -qf "name=mysql") mysql -uroot sylius_dev -e "SELECT c.email, w.balance FROM wallet w INNER JOIN sylius_customer c ON w.customer_id = c.id WHERE c.email = 'user@aivideo.com';"
```

**Note:** Base de données = `sylius_dev` (pas `sylius`), user = `root`, pas de mot de passe

