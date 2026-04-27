.PHONY: help install up down logs shell db-shell test test-unit test-integration test-functional \
        stan cs-check cs-fix rector-check rector-fix qa coverage migration bash clean

# ── Couleurs ──────────────────────────────────────────────────────────────────
GREEN  = \033[0;32m
YELLOW = \033[0;33m
RED    = \033[0;31m
NC     = \033[0m

help: ## Affiche cette aide
	@echo ""
	@echo "$(GREEN)GED Moderne — Commandes disponibles$(NC)"
	@echo "────────────────────────────────────────────────────────"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  $(YELLOW)%-25s$(NC) %s\n", $$1, $$2}'
	@echo ""

# ── Docker ────────────────────────────────────────────────────────────────────
up: ## Démarre tous les containers Docker
	docker compose up -d --build
	@echo "$(GREEN)✓ Stack démarrée sur http://localhost:8000$(NC)"
	@echo "  Mailpit : http://localhost:8025"
	@echo "  Meilisearch : http://localhost:7700"

down: ## Arrête tous les containers
	docker compose down

down-volumes: ## Arrête et supprime les volumes (ATTENTION: efface les données)
	docker compose down -v

logs: ## Affiche les logs en temps réel
	docker compose logs -f

logs-php: ## Affiche les logs PHP uniquement
	docker compose logs -f php

shell: ## Ouvre un shell dans le container PHP
	docker compose exec php bash

db-shell: ## Ouvre un shell MySQL
	docker compose exec db mysql -u ged_user -pged_password ged_moderne

redis-shell: ## Ouvre un shell Redis
	docker compose exec redis redis-cli

# ── Installation ──────────────────────────────────────────────────────────────
install: ## Installation complète (première fois)
	@echo "$(GREEN)Installation du projet...$(NC)"
	cp -n .env.example .env || true
	composer install
	php bin/console doctrine:database:create --if-not-exists
	php bin/console doctrine:migrations:migrate --no-interaction
	php bin/console lexik:jwt:generate-keypair --overwrite
	@echo "$(GREEN)✓ Installation terminée$(NC)"

install-docker: ## Installation via Docker
	docker compose run --rm php make install

jwt-keys: ## Génère les clés JWT
	php bin/console lexik:jwt:generate-keypair --overwrite
	@echo "$(GREEN)✓ Clés JWT générées$(NC)"

# ── Base de données ───────────────────────────────────────────────────────────
migration: ## Crée une nouvelle migration Doctrine
	php bin/console make:migration

migrate: ## Applique toutes les migrations
	php bin/console doctrine:migrations:migrate --no-interaction

migrate-test: ## Applique les migrations sur la base de test
	php bin/console doctrine:migrations:migrate --no-interaction --env=test

db-test-setup: ## Prépare la base SQLite de test (créée automatiquement au premier test)
	php bin/console cache:clear --env=test
	mkdir -p var var/storage/test
	@echo "$(GREEN)✓ Environnement de test prêt (SQLite : var/test.db)$(NC)"

db-reset: ## Recrée la base de données (ATTENTION: perte de données)
	php bin/console doctrine:database:drop --force --if-exists
	php bin/console doctrine:database:create
	php bin/console doctrine:migrations:migrate --no-interaction
	php bin/console doctrine:fixtures:load --no-interaction
	@echo "$(GREEN)✓ Base de données réinitialisée$(NC)"

fixtures: ## Charge les fixtures de développement
	php bin/console doctrine:fixtures:load --no-interaction

# ── Tests ─────────────────────────────────────────────────────────────────────
test: ## Lance tous les tests
	vendor/bin/pest --no-coverage

test-unit: ## Lance les tests unitaires uniquement
	vendor/bin/pest --testsuite=unit --colors=always --no-coverage

test-integration: ## Lance les tests d'intégration (SQLite)
	vendor/bin/pest --testsuite=integration --colors=always --no-coverage

test-functional: ## Lance les tests fonctionnels (HTTP)
	vendor/bin/pest --testsuite=functional --colors=always --no-coverage

test-characterization: ## Lance les tests de caractérisation du legacy
	vendor/bin/pest tests/Characterization --colors=always

coverage: ## Génère le rapport de couverture
	XDEBUG_MODE=coverage vendor/bin/pest --coverage --coverage-html=var/coverage/html --min=85
	@echo "$(GREEN)✓ Rapport disponible : var/coverage/html/index.html$(NC)"

mutation: ## Lance les tests de mutation (Infection)
	XDEBUG_MODE=coverage vendor/bin/infection \
		--min-msi=70 \
		--min-covered-msi=85 \
		--threads=4 \
		--show-mutations
	@echo "$(GREEN)✓ Rapport Infection disponible dans var/infection$(NC)"

# ── Qualité de code ───────────────────────────────────────────────────────────
stan: ## Lance PHPStan (level 9)
	vendor/bin/phpstan analyse --memory-limit=1G

stan-baseline: ## Génère la baseline PHPStan (à utiliser lors de la migration)
	vendor/bin/phpstan analyse --memory-limit=1G --generate-baseline=phpstan-baseline.neon
	@echo "$(YELLOW)⚠ Baseline générée. Réduire progressivement les erreurs.$(NC)"

cs-check: ## Vérifie le style de code (PER 3.0)
	vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Corrige le style de code automatiquement
	vendor/bin/php-cs-fixer fix
	@echo "$(GREEN)✓ Code reformaté$(NC)"

rector-check: ## Vérifie les upgrades Rector possibles
	vendor/bin/rector process --dry-run

rector-fix: ## Applique les upgrades Rector
	vendor/bin/rector process
	@echo "$(GREEN)✓ Code mis à jour par Rector$(NC)"

qa: ## Lance tous les checks qualité (cs + stan + tests)
	@echo "$(GREEN)── PHP-CS-Fixer ─────────────────────────────────$(NC)"
	@$(MAKE) cs-check
	@echo "$(GREEN)── PHPStan ──────────────────────────────────────$(NC)"
	@$(MAKE) stan
	@echo "$(GREEN)── Tests ────────────────────────────────────────$(NC)"
	@$(MAKE) test
	@echo "$(GREEN)✓ QA complète — tout est vert$(NC)"

# ── Analyse du legacy ─────────────────────────────────────────────────────────
legacy-stan: ## Analyse PHPStan sur le code legacy SeedDMS (baseline)
	vendor/bin/phpstan analyse \
		--memory-limit=1G \
		--level=3 \
		../modele/inc \
		../modele/controllers \
		../modele/out \
		2>&1 | tee var/legacy-phpstan-baseline.txt
	@echo "$(YELLOW)⚠ Rapport sauvegardé dans var/legacy-phpstan-baseline.txt$(NC)"

# ── Utilitaires ───────────────────────────────────────────────────────────────
cache-clear: ## Vide le cache Symfony
	php bin/console cache:clear

routes: ## Affiche toutes les routes
	php bin/console debug:router

services: ## Affiche les services du container DI
	php bin/console debug:container

clean: ## Supprime les caches et artefacts temporaires
	rm -rf var/cache var/log var/coverage
	@echo "$(GREEN)✓ Nettoyage terminé$(NC)"

bash: ## Alias pour shell
	$(MAKE) shell
