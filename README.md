# Blender Collection

Blender Collection est une application web développée avec **Symfony 7** et **PostgreSQL** qui a pour objectif de simplifier la gestion des add-ons Blender. Elle permet aux utilisateurs de créer des listes personnalisées d’add-ons à partir de l’API officielle de Blender, de regrouper ces extensions en collections et de télécharger le tout sous la forme d’un seul fichier bundle. L’application intègre également des fonctionnalités de collaboration, de gestion de profils, de rôles et d’administration.

---

## 📖 Sommaire

1. [Présentation du projet](#-présentation-du-projet)  
2. [Fonctionnalités principales](#-fonctionnalités-principales)  
3. [Architecture et enjeux techniques](#️-architecture-et-enjeux-techniques)  
4. [Stack technique](#️-stack-technique)  
5. [Installation](#-installation)  
6. [Utilisation](#-utilisation)  
7. [Gestion des rôles et administration](#-gestion-des-rôles-et-administration)   
8. [Licence](#-licence)

---

## 📝 Présentation du projet

Blender Collection est né d’un besoin simple : pouvoir centraliser et gérer des add-ons Blender sans avoir à les télécharger un par un. Grâce à l’API officielle de Blender et à un système de gestion des collections, l’application permet :
- d’organiser des add-ons en **listes personnalisées**,
- de les **télécharger tous ensemble** sous forme d’un bundle unique,
- de partager ses collections avec d’autres utilisateurs.

En complément, l’application intègre un **système de profils utilisateurs**, de **commentaires** et de **rôles administratifs** afin de créer une véritable plateforme collaborative.

---

## 🚀 Fonctionnalités principales

- Création et gestion de **profils utilisateurs** avec préférences personnalisées.  
- Création de **collections d’add-ons Blender** à partir de l’API officielle et de sources externes.  
- Téléchargement de tous les add-ons d’une collection en **un seul fichier ZIP**.  
- **Commentaires** et échanges autour des collections pour favoriser la collaboration.  
- Gestion de la **visibilité** des collections (publique, privée, restreinte).  
- **Tableau de bord administrateur** avec analytics et supervision des collections.  
- Interface utilisateur **responsive** grâce à Tailwind CSS.  

---

## ⚙️ Architecture et enjeux techniques

- **DevOps & qualité** : utilisation de Docker pour l’isolation, pipelines CI/CD (GitHub Actions) pour automatiser les tests et déploiements, tests unitaires avec PHPUnit.  
- **Gestion des ressources** : mise en cache des réponses API pour limiter les appels, workers asynchrones pour la gestion des fichiers volumineux.  
- **Système de profils & collections** : gestion des préférences utilisateurs (sessions, cookies, paramètres d’URL) et personnalisation de l’expérience.  
- **Collaboration et échanges** : possibilité de commenter les collections, d’échanger entre utilisateurs et de partager des liens personnalisés.  
- **Gestion des rôles** : modérateurs et administrateurs peuvent gérer les utilisateurs, bannir ou verrouiller des comptes, modifier la visibilité des collections.  
- **Tableau d’administration** : supervision des collections, suivi des analytics (popularité, activité des utilisateurs).  
- **Performance et optimisation** : utilisation de hard links pour éviter la duplication des fichiers et optimiser le stockage.  

---

## 🛠️ Stack technique

- **Backend** : Symfony 7 (PHP 8.3)  
- **Frontend** : Twig, Tailwind CSS  
- **Base de données** : PostgreSQL  
- **DevOps** : Docker, GitHub Actions (CI/CD)  
- **Tests** : PHPUnit  

---

## 📦 Installation

### Prérequis
- PHP 8.3+  
- Composer  
- Node.js + npm  
- Docker et Docker Compose  

### Étapes
```bash
# Cloner le projet
git clone https://github.com/tonprofil/blender-collection.git
cd blender-collection

# Installer les dépendances PHP
composer install

# Installer les dépendances front
npm install && npm run build

# Lancer les conteneurs Docker
docker-compose up -d

# Lancer les migrations
docker exec -it blender-php php bin/console doctrine:migrations:migrate
```

---

## 🖥️ Utilisation

1. Créez un compte utilisateur ou connectez-vous.  
2. Créez une **collection d’add-ons Blender**.  
3. Ajoutez des add-ons depuis l’API officielle ou d’autres sources.  
4. Téléchargez la collection complète sous forme de fichier ZIP.  
5. Partagez votre collection avec un lien ou discutez via les commentaires.  

---

## 🔐 Gestion des rôles et administration

- **Utilisateur standard** : peut créer et gérer ses propres collections.  
- **Modérateur** : peut commenter, surveiller et modérer les échanges.  
- **Administrateur** : peut bannir/verrouiller des utilisateurs, changer la visibilité des collections, gérer les analytics depuis le tableau d’administration.  


## 📄 Licence

Ce projet est distribué sous licence **Creative Commons Attribution-NonCommercial 4.0 International (CC BY-NC 4.0)**.  
Vous êtes libre de l’utiliser, le modifier et le partager à des fins **non commerciales**, à condition de créditer l’auteur.  

Copyright (c) 2025 Charles Lindecker  

Pour consulter une copie de cette licence, visitez :  
[https://creativecommons.org/licenses/by-nc/4.0/](https://creativecommons.org/licenses/by-nc/4.0/)

