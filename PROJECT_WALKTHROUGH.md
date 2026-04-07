# Project Walkthrough

This file is the persistent context for future chats about this repository.

If a new chat starts, the first thing to do is read this file before making decisions.

## 1. Repository Purpose

This repository is a Symfony 6.4 integration project for a group project about a trading / wallet platform called `Nexora`.

The project already contains multiple integrated modules:

- Wallets
- Wallet goals
- Assets
- Portfolios
- Orders
- P2P contracts
- Notifications
- User reputation

The user said their own module is `User`, and the goal was to understand the existing integration first, then build the `User` module so it fits the rest of the app.

## 2. What Was In `out_project`

The folder [out_project](C:/Users/scyzo/OneDrive/Desktop/integration/out_project) contains the workshop resources the team is supposed to follow.

### 2.1 Reverse Engineering Workshop

This part was directly verified from the zip contents:

- [RE_ Workshop Reverse Engineering version2.zip](C:/Users/scyzo/OneDrive/Desktop/integration/out_project/RE_%20Workshop%20Reverse%20Engineering%20version2.zip)
- [Workshop Reverse Engineering version 1 (2).zip](C:/Users/scyzo/OneDrive/Desktop/integration/out_project/Workshop%20Reverse%20Engineering%20version%201%20(2).zip)

What was understood from them:

- The expected methodology is to start from an existing database.
- Generate / reverse-engineer Doctrine entities from that database.
- Then continue with repositories, controllers, templates, and migrations in Symfony.
- The workshop includes a custom reverse-engineering script approach and then standard Doctrine migration usage.

This matches the project state: most current entities appear to have been built from the SQL model and then customized manually.

### 2.2 Other Workshops

There are also:

- [workshop-intégration-templates.pdf](C:/Users/scyzo/OneDrive/Desktop/integration/out_project/workshop-int%C3%A9gration-templates.pdf)
- [Workshop_lesformulaire_CS_25_26.pdf](C:/Users/scyzo/OneDrive/Desktop/integration/out_project/Workshop_lesformulaire_CS_25_26.pdf)

These were not fully extracted locally in a reliable way during the earlier session, but their purpose is clearly about:

- integrating templates
- working with forms

So the high-level interpretation is:

- follow Symfony structure
- use templates consistently
- use form-oriented workflows where appropriate
- stay aligned with the existing integrated UI

## 3. Initial Project State Before User Module Work

The project is a Symfony app with Doctrine and Twig.

Important files:

- [composer.json](C:/Users/scyzo/OneDrive/Desktop/integration/composer.json)
- [config/packages/doctrine.yaml](C:/Users/scyzo/OneDrive/Desktop/integration/config/packages/doctrine.yaml)
- [config/packages/security.yaml](C:/Users/scyzo/OneDrive/Desktop/integration/config/packages/security.yaml)
- [web.sql](C:/Users/scyzo/OneDrive/Desktop/integration/web.sql)

### 3.1 Authentication Before Changes

Before the `User` module changes, the app did not use real database-backed authentication.

It used a fake session-based gateway:

- pick `ADMIN` or `USER`
- if `USER`, choose a wallet from the gateway
- store `role` and `logged_in_wallet_id` in the session

This behavior originally lived in [HomeController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/HomeController.php) and [gateway.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/home/gateway.html.twig).

### 3.2 Domain Model Before Changes

There was no real `User` entity.

Instead:

- wallet ownership was stored as plain text `owner` in `wallets`
- several tables used raw integer columns like `user_id`, `creator_id`, `accepted_by`
- these were not real Doctrine relations to a `User` entity

Examples:

- [Portfolio.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Portfolio.php) uses `userId`
- [Order.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Order.php) uses `userId`
- [UserReputation.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/UserReputation.php) uses `userId`
- [P2pContract.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/P2pContract.php) uses `creatorId` and `acceptedBy`
- [Wallet.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Wallet.php) only had `owner`, not a `User` relation

### 3.3 SQL Snapshot Before Changes

[web.sql](C:/Users/scyzo/OneDrive/Desktop/integration/web.sql) showed:

- `wallets(owner, balance, created_at)` with no `user_id`
- `orders.user_id`
- `portfolio.user_id`
- `user_reputation.user_id`

This confirmed the project had a logical user concept, but not a normalized user module.

## 4. Main Architectural Decision We Took

Because the user asked for `only login` and wanted the new module integrated with the rest without rewriting everything, the least disruptive design was chosen:

- add a real `User` table and entity
- link `Wallet` to `User`
- use login to establish the active user session
- keep the rest of the modules working as they are for now
- do not yet refactor all raw `userId` integer fields into full Doctrine relations

This means:

- the real identity source is now the `users` table
- normal users access the app through the wallet linked to their account
- admin users can log in without a wallet

## 5. What Was Implemented

### 5.1 New User Entity

Added:

- [src/Entity/User.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/User.php)
- [src/Repository/UserRepository.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Repository/UserRepository.php)

Fields added in `User`:

- `id`
- `email`
- `fullName`
- `roles`
- `password`
- `createdAt`
- one-to-one relation to `Wallet`

This entity implements:

- `UserInterface`
- `PasswordAuthenticatedUserInterface`

Even though the login flow currently uses manual password verification and session storage, the entity is now aligned with Symfony security concepts.

### 5.2 Wallet Linked To User

Updated:

- [src/Entity/Wallet.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Wallet.php)

Added:

- one-to-one nullable `user` relation

This preserves the existing `owner` string while making wallet ownership connect to a real account.

### 5.3 Login Flow Replaced Old Gateway

Reworked:

- [src/Controller/HomeController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/HomeController.php)

New behavior:

- `/` redirects to login if not authenticated
- `/login` supports GET and POST
- login checks `email + password` against the `users` table
- on success, it sets:
  - `user_id`
  - `user_name`
  - `user_email`
  - `role`
  - `logged_in_wallet_id`
- `/logout` clears the session and returns to login

Important rule:

- `ROLE_ADMIN` users can log in without a wallet
- normal users must have an attached wallet

### 5.4 Login UI Added

Added:

- [templates/security/login.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/security/login.html.twig)

Styling added in:

- [public/css/style.css](C:/Users/scyzo/OneDrive/Desktop/integration/public/css/style.css)

The login page matches the existing Nexora style and includes seeded demo credentials for testing.

### 5.5 Security Provider Updated

Updated:

- [config/packages/security.yaml](C:/Users/scyzo/OneDrive/Desktop/integration/config/packages/security.yaml)

Changed provider from in-memory to entity provider:

- class: `App\Entity\User`
- property: `email`

Important:

The app is still effectively session-driven in controller logic.
This is not yet a full Symfony authenticator-based security system.
It is a DB-backed login module integrated into the current project style.

### 5.6 Redirects Updated

Several controllers originally redirected unauthenticated users to the old fake gateway route.

Those redirects were changed to the new login route in:

- [AssetController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/AssetController.php)
- [OrderController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/OrderController.php)
- [P2pContractController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/P2pContractController.php)
- [PortfolioController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/PortfolioController.php)
- [UserReputationController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/UserReputationController.php)
- [WalletController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/WalletController.php)
- [WalletGoalController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/WalletGoalController.php)

### 5.7 Migration Added

Added:

- [migrations/Version20260407110000.php](C:/Users/scyzo/OneDrive/Desktop/integration/migrations/Version20260407110000.php)

This migration:

- creates `users`
- adds `wallets.user_id`
- adds the wallet/user relation
- seeds 3 accounts
- links 2 existing wallets to 2 users

### 5.8 SQL Snapshot Updated

Updated:

- [web.sql](C:/Users/scyzo/OneDrive/Desktop/integration/web.sql)

It now includes:

- `users` table
- seeded user data
- `wallets.user_id`
- migration version entry for the new migration

## 6. Seeded Accounts

The login module was seeded with these users:

- Admin
  - email: `admin@nexora.tn`
  - password: `admin123`
- User
  - email: `ayoub1@nexora.tn`
  - password: `user123`
- User
  - email: `test1@nexora.tn`
  - password: `user456`

These credentials are shown in the login template for easy testing.

## 7. What Was Verified

PHP syntax was checked with `php -l` for the edited PHP files:

- [src/Controller/HomeController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/HomeController.php)
- [src/Entity/User.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/User.php)
- [src/Entity/Wallet.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Wallet.php)
- [src/Repository/UserRepository.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Repository/UserRepository.php)
- [migrations/Version20260407110000.php](C:/Users/scyzo/OneDrive/Desktop/integration/migrations/Version20260407110000.php)

All passed syntax checks.

## 8. Important Limitation At The Time Of The Changes

The Symfony app was not fully runnable during the implementation session because `vendor/` was missing.

That means:

- Symfony console commands could not run
- full application runtime could not be verified end-to-end

Because of that, after the code changes, the user was told to do:

1. install Composer if missing
2. run `composer install`
3. run the new migration
4. test the login flow

## 9. What The User Needs To Do After These Changes

If starting from this repo state, the next setup steps are:

1. Install Composer if it is not installed.
2. In the project root, run:

```powershell
composer install
```

or if Composer is only available as the local phar:

```powershell
C:\xampp\php\php.exe composer.phar install
```

3. Run migrations:

```powershell
php bin/console doctrine:migrations:migrate
```

4. Start the application with the preferred local setup.

5. Test the seeded logins.

## 10. Current Project State After User Module Work

After the changes, the project is in this state:

- real `User` module exists
- login is DB-backed
- user session is based on an actual account
- normal users are linked to wallets
- admin can log in separately
- the rest of the modules still mostly use legacy integer fields for user references

So the system is now partially normalized:

- authentication and account identity are real
- domain relations outside wallet ownership are still transitional

## 11. Recommended Next Refactor

The next major step is to replace raw user integer columns with real Doctrine relations.

Priority targets:

1. [Portfolio.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Portfolio.php)
   Replace `userId` with `ManyToOne` or `OneToOne User`

2. [Order.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Order.php)
   Replace `userId` with `ManyToOne User`

3. [UserReputation.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/UserReputation.php)
   Replace `userId` with `OneToOne` or `ManyToOne User`

4. [P2pContract.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/P2pContract.php)
   Replace `creatorId` and `acceptedBy` with `User` relations

5. Update controllers and Twig templates accordingly

This was already identified as the next logical step after login-only user module completion.

## 12. Things To Avoid In Future Work

- Do not reintroduce the fake role gateway as the main auth mechanism.
- Do not create a second parallel user identity system.
- Do not break current wallet-based user views until all legacy `userId` fields are refactored properly.
- Do not assume Symfony authentication is fully modernized yet; it is only partially integrated.

## 13. How Future Chats Should Use This File

If a future chat starts, the context should be:

- this is a Symfony 6.4 group integration project
- workshops emphasized reverse engineering from DB and structured Symfony integration
- initial app had fake session auth and no real user entity
- a login-only `User` module has now been added
- `Wallet` is linked to `User`
- login is database-backed
- the next step is refactoring remaining user-related integer fields into real relations

Future implementation work should start from that assumption instead of re-analyzing the whole repository from zero.
