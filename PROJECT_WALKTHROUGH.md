# Project Walkthrough

This file is the persistent context for future chats about this repository.

If a new chat starts, the first thing to do is read this file before making decisions.

## 1. Repository Purpose

This repository is a Symfony 6.4 integration project for a group project about a trading / wallet platform called `Nexora`.

The repository already contains multiple integrated modules:

- Wallets
- Wallet goals
- Assets
- Portfolios
- Orders
- P2P contracts
- Notifications
- User reputation

The user’s own module is `User`.

The work in this repository was done in phases:

1. understand the already integrated repository and workshop expectations
2. add a real database-backed `User` module with login
3. add admin user management
4. move input validation rules from browser-side HTML constraints to PHP-side validation
5. add self-registration for normal users
6. add dedicated search and filtering on the user management screen
7. add forgot-password with email PIN verification and Brevo SMTP delivery

## 2. Workshop Context From `out_project`

The folder [out_project](C:/Users/scyzo/OneDrive/Desktop/integration/out_project) contains the workshop resources the team is supposed to follow.

Verified relevant files:

- [RE_ Workshop Reverse Engineering version2.zip](C:/Users/scyzo/OneDrive/Desktop/integration/out_project/RE_%20Workshop%20Reverse%20Engineering%20version2.zip)
- [Workshop Reverse Engineering version 1 (2).zip](C:/Users/scyzo/OneDrive/Desktop/integration/out_project/Workshop%20Reverse%20Engineering%20version%201%20(2).zip)
- [workshop-intégration-templates.pdf](C:/Users/scyzo/OneDrive/Desktop/integration/out_project/workshop-int%C3%A9gration-templates.pdf)
- [Workshop_lesformulaire_CS_25_26.pdf](C:/Users/scyzo/OneDrive/Desktop/integration/out_project/Workshop_lesformulaire_CS_25_26.pdf)

### 2.1 Reverse Engineering Workshop

The reverse-engineering workshop content matched this methodology:

- start from an existing database
- generate or reverse-engineer Doctrine entities from that schema
- continue with repositories, controllers, templates, and migrations
- keep the implementation aligned with standard Symfony structure

This matches the repository: most current entities clearly come from an existing SQL model and were then customized manually.

### 2.2 Template / Form Workshops

The template/form workshops were not fully parsed line by line during the session, but the practical interpretation used in the work was:

- keep the Symfony MVC structure
- keep Twig templates consistent with the existing integrated UI
- keep form workflows Symfony-oriented
- move real input validation rules into PHP instead of relying only on browser-side HTML constraints

That last point became important later, when the user said the teacher required the `controles de saisie` to be handled in PHP rather than by HTML/browser validation.

## 3. Initial Project State Before User Module Work

The project is a Symfony app with Doctrine and Twig.

Important baseline files:

- [composer.json](C:/Users/scyzo/OneDrive/Desktop/integration/composer.json)
- [config/packages/doctrine.yaml](C:/Users/scyzo/OneDrive/Desktop/integration/config/packages/doctrine.yaml)
- [config/packages/security.yaml](C:/Users/scyzo/OneDrive/Desktop/integration/config/packages/security.yaml)
- [web.sql](C:/Users/scyzo/OneDrive/Desktop/integration/web.sql)

### 3.1 Authentication Before Changes

Before the `User` module changes, the app did not use real database-backed authentication.

It used a fake session gateway:

- choose `ADMIN` or `USER`
- if `USER`, choose a wallet from the gateway
- store `role` and `logged_in_wallet_id` in the session

This behavior originally lived in:

- [src/Controller/HomeController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/HomeController.php)
- [templates/home/gateway.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/home/gateway.html.twig)

### 3.2 Domain Model Before Changes

There was no real `User` entity.

Instead:

- wallet ownership was stored as plain text `owner` in `wallets`
- several tables used raw integer columns such as `user_id`, `creator_id`, `accepted_by`
- these were not Doctrine relations to a real `User`

Examples:

- [src/Entity/Portfolio.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Portfolio.php) uses `userId`
- [src/Entity/Order.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Order.php) uses `userId`
- [src/Entity/UserReputation.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/UserReputation.php) uses `userId`
- [src/Entity/P2pContract.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/P2pContract.php) uses `creatorId` and `acceptedBy`
- [src/Entity/Wallet.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Wallet.php) only had `owner`, no `User` relation

### 3.3 SQL Snapshot Before Changes

[web.sql](C:/Users/scyzo/OneDrive/Desktop/integration/web.sql) showed:

- `wallets(owner, balance, created_at)` with no `user_id`
- `orders.user_id`
- `portfolio.user_id`
- `user_reputation.user_id`

This confirmed that the app had a logical user concept in the data model, but not a normalized Symfony-level user module.

## 4. First Main Architectural Decision

The user initially asked for `only login` and wanted the new module integrated into the existing app without rewriting the whole codebase.

The least disruptive decision was:

- add a real `User` table and `User` entity
- link `Wallet` to `User`
- use login to establish the active session
- keep the rest of the modules working as they are for now
- do not refactor all raw `userId` integer fields into real Doctrine relations yet

This means:

- the real identity source is now the `users` table
- normal users access the app through the wallet linked to their account
- admins can log in without a wallet
- most non-wallet user references are still transitional legacy integer fields

## 5. Phase 1: Real User Entity + Login

### 5.1 New User Entity

Added:

- [src/Entity/User.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/User.php)
- [src/Repository/UserRepository.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Repository/UserRepository.php)

Fields in `User`:

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

It also contains:

- `getUserIdentifier()`
- `getRoles()`
- `hasRole()`

### 5.2 Wallet Linked To User

Updated:

- [src/Entity/Wallet.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Wallet.php)

Added:

- nullable one-to-one `user` relation

This preserved the old `owner` text field while adding a real account relation.

### 5.3 Login Flow Replaced Old Gateway

Reworked:

- [src/Controller/HomeController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/HomeController.php)

Behavior after this phase:

- `/` redirects to login if not authenticated
- `/login` supports GET and POST
- login checks `email + password` against the `users` table
- on success, the session stores:
  - `user_id`
  - `user_name`
  - `user_email`
  - `role`
  - `logged_in_wallet_id`
- `/logout` clears the session and redirects to login

Important rule:

- `ROLE_ADMIN` users can log in without a wallet
- normal users must have an attached wallet

### 5.4 Login UI

Added:

- [templates/security/login.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/security/login.html.twig)

Updated styling:

- [public/css/style.css](C:/Users/scyzo/OneDrive/Desktop/integration/public/css/style.css)

The login page matches the existing Nexora UI and shows seeded demo credentials.

### 5.5 Security Provider Updated

Updated:

- [config/packages/security.yaml](C:/Users/scyzo/OneDrive/Desktop/integration/config/packages/security.yaml)

Provider changed from in-memory to entity provider:

- class: `App\Entity\User`
- property: `email`

Important limitation:

This is still not a full Symfony authenticator-based security system.
It is a DB-backed login module integrated into the existing project style, with controller/session-driven access logic.

### 5.6 Redirects Updated Across Modules

Controllers that originally redirected to the old fake gateway were changed to redirect to the new login route:

- [src/Controller/AssetController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/AssetController.php)
- [src/Controller/OrderController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/OrderController.php)
- [src/Controller/P2pContractController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/P2pContractController.php)
- [src/Controller/PortfolioController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/PortfolioController.php)
- [src/Controller/UserReputationController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/UserReputationController.php)
- [src/Controller/WalletController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/WalletController.php)
- [src/Controller/WalletGoalController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/WalletGoalController.php)

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

- the `users` table
- seeded user rows
- `wallets.user_id`
- the migration version entry

## 6. Seeded Accounts

The migration seeded these accounts:

- Admin
  - email: `admin@nexora.tn`
  - password: `admin123`
- User
  - email: `ayoub1@nexora.tn`
  - password: `user123`
- User
  - email: `test1@nexora.tn`
  - password: `user456`

These are shown in the login template.

## 7. Phase 2: Admin User Management

Later, the user asked that the admin should be able to:

- add users
- edit users
- remove users
- list all users

This was implemented as a custom back-office user management screen.

### 7.1 User CRUD Controller

Added:

- [src/Controller/UserController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/UserController.php)

Routes added:

- `GET /users` -> list users
- `POST /users/create` -> create user
- `POST /users/update/{id}` -> update user
- `POST /users/delete/{id}` -> delete user

Important business rules in that controller:

- only admins can access these routes
- email uniqueness is checked before create/update
- passwords are hashed with Symfony’s password hasher
- password minimum length is enforced in PHP
- a wallet can only be assigned if it is not already linked to another user
- when assigning a wallet, the wallet owner text is synchronized with the user full name
- the currently logged-in admin cannot delete their own account
- if the current logged-in user edits their own profile, session values are refreshed

### 7.2 User Management UI

Added:

- [templates/user/index.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/user/index.html.twig)

Added in sidebar:

- [templates/base.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/base.html.twig)

The `/users` page includes:

- user list
- stats cards
- add user modal
- edit user modal
- delete action
- wallet assignment / unassignment
- role selection

### 7.3 User Module Search / Filter / Sort

The user later asked whether the user module had `recherche et tri`.

Status after implementation:

- sorting already worked through the shared sortable table script in [templates/base.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/base.html.twig)
- dedicated user-module search/filter UI was then added to [templates/user/index.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/user/index.html.twig)

Dedicated features on `/users` now include:

- search by name or email
- filter by role: `Admin` / `User`
- filter by wallet state: `With Wallet` / `Without Wallet`

Implementation detail:

- rows use `data-*` attributes
- filtering is client-side JavaScript scoped to the user table

## 8. Phase 3: Input Validation Moved To PHP

The user said the teacher required `controles de saisie` to be done in PHP rather than in HTML/browser validation.

As a result, the implementation was adjusted to remove HTML/browser validation dependence and move the real validation rules into PHP.

### 8.1 Server-Side Validation Added To Entities

Validation constraints were added to:

- [src/Entity/Asset.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Asset.php)
- [src/Entity/Order.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Order.php)
- [src/Entity/P2pContract.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/P2pContract.php)
- [src/Entity/Portfolio.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Portfolio.php)
- [src/Entity/UserReputation.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/UserReputation.php)

Validation already existed earlier on:

- [src/Entity/User.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/User.php)
- [src/Entity/Wallet.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Wallet.php)
- [src/Entity/WalletGoal.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/WalletGoal.php)

### 8.2 Controllers Updated To Enforce PHP Validation

Controllers updated to use `ValidatorInterface` and enforce server-side checks:

- [src/Controller/AssetController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/AssetController.php)
- [src/Controller/OrderController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/OrderController.php)
- [src/Controller/P2pContractController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/P2pContractController.php)
- [src/Controller/PortfolioController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/PortfolioController.php)
- [src/Controller/UserReputationController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/UserReputationController.php)
- [src/Controller/WalletController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/WalletController.php)
- [src/Controller/WalletGoalController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/WalletGoalController.php)
- [src/Controller/UserController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/UserController.php)
- [src/Controller/HomeController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/HomeController.php)

Specific additions:

- login email and password validation in PHP
- user password length validation in PHP
- wallet goal deadline parsing guarded with try/catch
- order type / contract type / status normalized before validation

### 8.3 Browser-Side Validation Attributes Removed From Templates

The active Twig forms were cleaned to remove browser-enforced attributes such as:

- `required`
- `min`
- `max`
- `minlength`
- `type="email"`
- `type="number"`
- `type="date"`

Affected templates included:

- [templates/security/login.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/security/login.html.twig)
- [templates/user/index.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/user/index.html.twig)
- [templates/asset/index.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/asset/index.html.twig)
- [templates/wallet/index.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/wallet/index.html.twig)
- [templates/wallet_goal/index.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/wallet_goal/index.html.twig)
- [templates/portfolio/index.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/portfolio/index.html.twig)
- [templates/order/index.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/order/index.html.twig)
- [templates/p2p_contract/index.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/p2p_contract/index.html.twig)
- [templates/user_reputation/index.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/user_reputation/index.html.twig)

As a result, validation errors now come from Symfony/PHP flash messages rather than browser blocking.

## 9. Phase 4: User Self-Registration

The user later asked:

- create an account utilisateur with login
- the created account must automatically be `user`, not `admin`

This was implemented as a normal self-registration flow.

### 9.1 Registration Route And Logic

Updated:

- [src/Controller/HomeController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/HomeController.php)

Added route:

- `GET|POST /register`

Registration behavior:

- full name, email, password, and confirmation are submitted
- email format is checked in PHP
- duplicate email is refused
- password minimum length is enforced in PHP
- password confirmation must match
- created account always gets `ROLE_USER`
- a wallet is created automatically with balance `0.00`
- the wallet is linked to the created user immediately
- success redirects to login with a flash message

Important design reason:

The existing login flow requires non-admin users to have a linked wallet.
Therefore registration auto-creates a wallet so that a newly created user can actually log in immediately.

### 9.2 Registration UI

Added:

- [templates/security/register.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/security/register.html.twig)

Updated:

- [templates/security/login.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/security/login.html.twig)
- [public/css/style.css](C:/Users/scyzo/OneDrive/Desktop/integration/public/css/style.css)

UI behavior:

- `/login` now includes a link to `/register`
- `/register` includes a link back to `/login`
- the registration page visually matches the auth UI already used for login

## 10. Phase 5: Forgot Password With Email PIN

Later, the user asked to add a forgot-password flow from the login page:

- user clicks `Forgot password?`
- app sends a 6-digit PIN by email
- user enters the PIN
- user chooses a new password
- user logs in again with that new password

This was implemented as a controller/session-driven password reset flow consistent with the existing login architecture.

### 10.1 Persistence For Reset PIN

Updated:

- [src/Entity/User.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/User.php)

Added fields:

- `resetPinCode`
- `resetPinExpiresAt`
- `resetPinRequestedAt`

Added helper:

- `clearResetPin()`

Migration used:

- [migrations/Version20260414110000.php](C:/Users/scyzo/OneDrive/Desktop/integration/migrations/Version20260414110000.php)

This migration adds the reset-PIN columns on `users`.

### 10.2 Forgot Password Routes And Logic

Updated:

- [src/Controller/HomeController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/HomeController.php)

Added routes:

- `GET|POST /forgot-password`
- `GET|POST /verify-reset-pin`
- `GET|POST /reset-password`

Behavior:

- forgot-password validates the submitted email in PHP
- if the account exists, a 6-digit PIN is generated
- the PIN is hashed before storage
- the PIN expires after 15 minutes
- repeated requests are rate-limited to roughly one per minute per user
- if the email exists, the app sends the PIN by email and redirects to PIN verification
- if the PIN is valid, the session stores a short-lived reset authorization
- the reset-password screen then allows choosing a new password
- after successful reset, the password is hashed and saved
- the reset PIN data is cleared
- the user is redirected back to `/login`

Security design notes:

- the app does not expose whether a submitted email exists in the database
- the stored reset PIN is hashed, not stored in plain text
- reset authorization is temporary and kept in the session

### 10.3 Forgot Password UI

Updated:

- [templates/security/login.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/security/login.html.twig)
- [public/css/style.css](C:/Users/scyzo/OneDrive/Desktop/integration/public/css/style.css)

Added:

- [templates/security/forgot_password.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/security/forgot_password.html.twig)
- [templates/security/verify_reset_pin.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/security/verify_reset_pin.html.twig)
- [templates/security/reset_password.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/security/reset_password.html.twig)

UI flow:

- `/login` now includes `Forgot password?`
- `/forgot-password` requests the email
- `/verify-reset-pin` validates the code
- `/reset-password` saves the new password

### 10.4 Email Delivery Infrastructure

The implementation initially passed through a Messenger async queue, but the final setup was changed to direct sending because the user wanted immediate delivery without running a worker manually.

Final mail setup:

- [config/packages/mailer.yaml](C:/Users/scyzo/OneDrive/Desktop/integration/config/packages/mailer.yaml)
- [config/packages/messenger.yaml](C:/Users/scyzo/OneDrive/Desktop/integration/config/packages/messenger.yaml)
- [config/services.yaml](C:/Users/scyzo/OneDrive/Desktop/integration/config/services.yaml)

Final behavior:

- `framework.mailer.message_bus` is disabled for mail sending
- `Symfony\Component\Mailer\Messenger\SendEmailMessage` is no longer routed to `async`
- forgot-password emails are sent immediately during the request

### 10.5 Brevo SMTP Setup Chosen

The user considered Outlook, Gmail, Mailtrap, and Brevo. The final choice for real delivery was `Brevo`.

Practical reasons:

- easier transactional-email setup than the school Outlook mailbox
- real inbox delivery instead of sandbox-only testing
- more suitable for the forgot-password use case than Mailtrap

Local runtime configuration is expected in `.env.local` and currently includes:

- `MAILER_DSN` pointing to `smtp-relay.brevo.com`
- `MAILER_FROM_ADDRESS`
- `MAILER_FROM_NAME`

During the session, the sender address was aligned with the Brevo-verified sender already available in the account:

- `mariemhaneshi@gmail.com`

Display name used:

- `Nexora Support`

### 10.6 HTML Reset Email Template

Added:

- [templates/emails/reset_pin.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/emails/reset_pin.html.twig)

The reset email was improved from a plain text PIN email to a branded HTML email with:

- Nexora visual styling
- clear headline and support identity
- prominent 6-digit PIN block
- expiration reminder
- fallback plain text body still included in the message

### 10.7 Sender Visibility Limitation

One UX request was to avoid prominently showing the raw sender email.

Practical conclusion:

- the display name can show as `Nexora Support`
- however, if the actual verified sender is `mariemhaneshi@gmail.com`, many mail clients still reveal that address in sender details
- the only real long-term fix is to verify and use a better sender identity, ideally a custom domain sender such as `support@nexora.tn`

## 11. Technical Constraint Discussion For The User Module

At one point, the user asked whether the implemented `User` module respected teacher constraints such as:

- each module must contain at least two entities and one relation
- one shared database
- image URL format instead of blob
- no FOSUserBundle
- no AdminBundle
- both frontOffice and backOffice aspects

Practical conclusion reached:

- one shared DB: respected
- no FOSUserBundle: respected
- no AdminBundle: respected
- frontOffice/backOffice for user module: partially respected then improved
  - frontOffice: login and later registration
  - backOffice: admin user management
- relation: respected through `User` <-> `Wallet`
- strict “minimum two dedicated entities for the User module”: still potentially weak if the teacher interprets `Wallet` as belonging only to the wallet module, not the user module

So the main remaining academic risk is:

- the `User` module has one strong dedicated entity (`User`) and one relation (`Wallet`)
- but if the teacher strictly requires two entities created specifically for the user module itself, that is still not fully satisfied

Possible future fix if needed:

- add `UserProfile`, `UserPreference`, `UserAddress`, or another clearly user-owned entity

## 12. Recurrent Environment Issue: OneDrive Cache / Log Write Problems

During testing, a repeated runtime problem appeared:

- Symfony / Doctrine / Twig sometimes failed with errors saying `var/cache` or `var/log` was not writable
- the repository lives inside `OneDrive`
- Windows/OneDrive attributes and ACL behavior caused intermittent write issues for:
  - `var/cache/dev`
  - `var/cache/dev/doctrine/orm/Proxies`
  - `var/cache/dev/twig/...`
  - `var/log`

The practical quick fix used repeatedly was:

```powershell
attrib -R var /S /D
php bin\console cache:clear
```

Then run the project:

```powershell
php -S 127.0.0.1:8000 -t public
```

Important:

The durable fix is to move the project outside OneDrive, for example to a normal local folder such as `C:\projects\integration`.

## 13. What Was Verified During The Work

Different things were verified at different phases.

### 13.1 PHP Syntax

`php -l` was run successfully on many edited files including:

- [src/Controller/HomeController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/HomeController.php)
- [src/Controller/UserController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/UserController.php)
- [src/Controller/AssetController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/AssetController.php)
- [src/Controller/OrderController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/OrderController.php)
- [src/Controller/P2pContractController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/P2pContractController.php)
- [src/Controller/PortfolioController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/PortfolioController.php)
- [src/Controller/UserReputationController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/UserReputationController.php)
- [src/Controller/WalletGoalController.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Controller/WalletGoalController.php)
- [src/Entity/User.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/User.php)
- [src/Entity/Wallet.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Wallet.php)
- [src/Entity/Asset.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Asset.php)
- [src/Entity/Order.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Order.php)
- [src/Entity/P2pContract.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/P2pContract.php)
- [src/Entity/Portfolio.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Portfolio.php)
- [src/Entity/UserReputation.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/UserReputation.php)
- [migrations/Version20260407110000.php](C:/Users/scyzo/OneDrive/Desktop/integration/migrations/Version20260407110000.php)
- [migrations/Version20260414110000.php](C:/Users/scyzo/OneDrive/Desktop/integration/migrations/Version20260414110000.php)

### 13.2 Twig Syntax

Twig syntax was also verified successfully for important edited templates, including:

- [templates/security/login.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/security/login.html.twig)
- [templates/security/register.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/security/register.html.twig)
- [templates/user/index.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/user/index.html.twig)
- [templates/security/forgot_password.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/security/forgot_password.html.twig)
- [templates/security/verify_reset_pin.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/security/verify_reset_pin.html.twig)
- [templates/security/reset_password.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/security/reset_password.html.twig)
- [templates/emails/reset_pin.html.twig](C:/Users/scyzo/OneDrive/Desktop/integration/templates/emails/reset_pin.html.twig)

### 13.3 Route Verification

Important route existence was verified, including:

- `user_index` -> `/users`
- `app_register` -> `/register`
- `app_forgot_password` -> `/forgot-password`
- `app_verify_reset_pin` -> `/verify-reset-pin`
- `app_reset_password` -> `/reset-password`

## 14. Current Functional State Of The User Module

As of the latest changes, the `User` module supports:

- real DB-backed users
- login via `/login`
- logout via `/logout`
- self-registration via `/register`
- forgot-password via `/forgot-password`
- PIN verification via `/verify-reset-pin`
- password reset via `/reset-password`
- automatic `ROLE_USER` for self-registration
- automatic wallet creation for self-registered users
- admin login without wallet
- normal user login with linked wallet
- admin-only user list
- admin create / edit / delete user
- wallet assignment and unassignment from admin UI
- dedicated user search
- dedicated user role filter
- dedicated user wallet-state filter
- shared table sorting through the global table script
- branded HTML reset email through Brevo SMTP

## 15. Current Architectural Limitation

The app is still partially normalized.

Authentication and account identity are real now, but several business modules still use legacy integer user fields instead of real relations:

- [src/Entity/Portfolio.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Portfolio.php) -> `userId`
- [src/Entity/Order.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Order.php) -> `userId`
- [src/Entity/UserReputation.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/UserReputation.php) -> `userId`
- [src/Entity/P2pContract.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/P2pContract.php) -> `creatorId`, `acceptedBy`

So:

- `Wallet` <-> `User`: integrated
- authentication <-> `User`: integrated
- admin CRUD <-> `User`: integrated
- registration <-> `User`: integrated
- forgot-password <-> `User`: integrated
- most domain modules <-> `User`: not yet fully normalized

## 16. Recommended Next Refactor

The next major technical step is still to replace raw user integer columns with real Doctrine relations.

Priority targets:

1. [src/Entity/Portfolio.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Portfolio.php)
   Replace `userId` with a `User` relation

2. [src/Entity/Order.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/Order.php)
   Replace `userId` with a `User` relation

3. [src/Entity/UserReputation.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/UserReputation.php)
   Replace `userId` with a `User` relation

4. [src/Entity/P2pContract.php](C:/Users/scyzo/OneDrive/Desktop/integration/src/Entity/P2pContract.php)
   Replace `creatorId` and `acceptedBy` with `User` relations

5. Update controllers and Twig templates accordingly

If academic compliance requires two clearly user-owned entities, an additional dedicated user entity should also be added.

## 17. Current Setup / Run Instructions

From the project root:

1. install dependencies if needed

```powershell
php composer.phar install
```

or:

```powershell
C:\xampp\php\php.exe composer.phar install
```

2. run migrations

```powershell
php bin\console doctrine:migrations:migrate
```

or run the user migration directly if needed:

```powershell
php bin\console doctrine:migrations:execute DoctrineMigrations\\Version20260407110000 --up
```

For the forgot-password flow, the reset migration may also be relevant if it is not already applied:

```powershell
php bin\console doctrine:migrations:execute DoctrineMigrations\\Version20260414110000 --up
```

2.1 configure Brevo mail locally in `.env.local`

Expected keys:

- `MAILER_DSN`
- `MAILER_FROM_ADDRESS`
- `MAILER_FROM_NAME`

Current practical setup used during the session:

- Brevo SMTP relay
- verified sender: `mariemhaneshi@gmail.com`
- display name: `Nexora Support`

3. clear cache if OneDrive causes problems

```powershell
attrib -R var /S /D
php bin\console cache:clear
```

4. start the local server

```powershell
php -S 127.0.0.1:8000 -t public
```

5. test:

- login: `http://127.0.0.1:8000/login`
- register: `http://127.0.0.1:8000/register`
- admin user management: `http://127.0.0.1:8000/users`
- forgot password: `http://127.0.0.1:8000/forgot-password`

## 18. Things To Avoid In Future Work

- Do not reintroduce the fake gateway as the main auth mechanism.
- Do not create a second parallel user identity system.
- Do not break wallet-based user access while legacy integer references still exist elsewhere.
- Do not assume Symfony security is fully modernized; it is still controller/session-driven.
- Do not rely on browser-only HTML validation for core business input checks.
- Do not forget the OneDrive cache/log write issue when diagnosing random runtime failures.
- Do not switch forgot-password email delivery back to async Messenger unless a worker is also part of the documented run flow.
- Do not expect the sender email address to be fully hidden while using a free mailbox sender; use a verified custom-domain sender if sender identity matters.

## 19. How Future Chats Should Use This File

If a future chat starts, the working assumptions should be:

- this is a Symfony 6.4 group integration project
- workshops emphasized reverse engineering from an existing DB and then standard Symfony integration
- the repository originally had fake session auth and no real user entity
- a real DB-backed `User` module was added
- `Wallet` is linked to `User`
- login is DB-backed
- admin user CRUD now exists
- self-registration for normal users now exists
- registration auto-creates a wallet
- forgot-password with email PIN now exists
- forgot-password emails are sent directly through Brevo SMTP
- the current sender identity is still constrained by the Brevo-verified sender email in use
- user-module search/filtering now exists on `/users`
- major input validation was moved to PHP-side checks
- the next core technical refactor is replacing remaining legacy integer user fields with real Doctrine relations

Future implementation work should start from that state instead of re-analyzing the whole repository from zero.
