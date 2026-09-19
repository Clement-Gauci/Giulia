# Design — Authentification du dashboard (Giulia)

Date : 2026-09-09

> **Révision du 2026-09-19.** Les rôles ont été retirés. Le dashboard ne sert
> qu'à modifier le contenu du site : tous les comptes y sont équivalents, et il
> n'y a donc plus ni énumération `AccountRole`, ni colonne `role`, ni hiérarchie
> dans `security.yaml` — un unique `ROLE_ADMIN`. Les comptes prévus sont
> l'adresse principale de la pizzeria, un compte de secours, et le cas échéant
> celui de chaque gérant. Les passages ci-dessous marqués « (supprimé) »
> décrivent l'état livré le 2026-09-09.

## Objectif

Donner au dashboard d'administration une authentification **sans mot de passe** :
l'utilisateur saisit son adresse e-mail, reçoit un code à 6 chiffres, et le
saisit pour ouvrir une session de 30 jours. Livrer en même temps la structure des
comptes et une commande de création de compte.

Maquette de référence : `.claude/design-system/Connexion.dc.html` (Claude Design).
Elle fixe l'ergonomie **et** les règles de sécurité ; ce document les reprend.

## Contexte

- Chantier 2 sur 3 du dashboard (voir la note du 2026-09-09 dans
  `2026-07-21-makefile-deploiement-design.md` pour le socle PostgreSQL).
- Le chantier 1 (entités du contenu éditorial : pizzas, horaires, fermetures) n'est
  **pas** fait. L'authentification arrive avant, à la demande de Clément.
- Aucun `UserInterface` n'existe : `config/packages/security.yaml` est encore le
  fichier du squelette, avec un provider `users_in_memory` inutilisé.
- `APP_SECRET` est désormais renseigné (il signe le cookie « rester connecté »).

## Décisions

### Deux rôles, pas une hiérarchie de façade

**(supprimé — voir la révision du 2026-09-19.)** Deux rôles étaient prévus :
`manager` pour les gérants et `shop` pour le poste partagé de la pizzeria, avec
`ROLE_MANAGER` héritant de `ROLE_SHOP`. Aucun des deux ne portait de règle
métier : tous les comptes accèdent désormais au dashboard par `ROLE_ADMIN`.

### Adresse inconnue : message explicite

La maquette annonce « Cette adresse n'est pas enregistrée » et bloque après 3
tentatives. C'est une fuite d'information assumée : le back-office compte deux ou
trois accès, et savoir qu'on s'est trompé d'adresse vaut plus que la protection
contre l'énumération. Décision de Clément, prise en connaissance du compromis.

### Blocage persistant, pas en session

La maquette compte les tentatives dans l'état du composant, donc côté navigateur.
Reproduire ça côté serveur en session rendrait le blocage contournable en vidant
ses cookies. Les tentatives vont donc dans une table `login_attempt` (adresse,
IP, nature, horodatage) et le blocage se calcule en comptant les lignes d'une
fenêtre glissante.

Ce choix évite d'ajouter `symfony/rate-limiter` (dont le stockage par défaut est
un pool de cache, effacé à chaque déploiement), et donne en prime une piste
d'audit — utile pour un back-office. Contrepartie : la table doit être purgée ;
chaque écriture supprime les lignes de plus de 24 h.

### Codes à usage unique, stockés hachés

Un code vit dans `login_code`, rattaché à un compte, avec sa date d'expiration
(10 minutes), son compteur d'essais et sa date de consommation. Le code n'est
**jamais** stocké en clair : seulement son hachage. L'espace de recherche est
petit (10⁶), le hachage ne protège donc pas contre une attaque hors ligne — il
évite surtout qu'une fuite de la base livre des codes directement rejouables
pendant leurs 10 minutes de validité.

### Un authenticator maison pour l'étape du code

Premier réflexe : vérifier le code dans le contrôleur puis appeler
`Security::login()`, pour s'épargner un `AuthenticatorInterface`. **La lecture du
code de `SecurityBundle` invalide ce raccourci** : `Security::getAuthenticator()`
exige qu'au moins un authenticator (hors `remember_me`) soit déclaré sur le
pare-feu, et lève `LogicException` sinon. Il en faudrait donc un de toute façon,
ne serait-ce que pour la forme.

L'authenticator maison devient alors le chemin le plus court, et le plus
conforme : `CheckRememberMeConditionsListener` active tout seul le
`RememberMeBadge` quand la case « Rester connecté » est cochée, la protection
contre la fixation de session est appliquée, et rien n'est à recâbler à la main.

Découpage : l'étape 1 (demande de code) est un contrôleur ordinaire, hors
authentification. Seule l'étape 2 passe par l'authenticator, qui délègue à
`VerifyLoginCode`. Les échecs remontent en `AuthenticationException` portant
l'exception métier d'origine ; `onAuthenticationFailure` dépose ce qu'il faut en
session et redirige vers l'étape 2, dont le contrôleur reste le seul endroit qui
sait rendre un écran — y compris celui du blocage.

### Le domaine ignore Symfony Security

`Account` reste un objet immuable du domaine. L'implémentation de `UserInterface`
est un adaptateur `Infrastructure/Security/AccountUser`, construit depuis
`Account`. Même discipline que le reste du projet : le framework ne remonte pas
dans `Domain/`.

## Structure

```
src/Account/
  Domain/
    Account                       objet immuable : identité, rôle, état
    AccountRepositoryInterface    findByEmail, save, emailExists
    LoginCode                     code émis : hachage, expiration, essais
    LoginCodeRepositoryInterface   save, findActiveFor, consume
    LoginAttempt                  tentative : adresse, IP, nature, instant
    LoginAttemptRepositoryInterface  record, countSince, purgeOlderThan
    AccountMailerInterface        notification de création
    LoginCodeMailerInterface      envoi du code
    AccountAlreadyExists          exception métier
    LoginBlocked                  exception métier (blocage en cours)
  Application/
    CreateAccount                 création + notification
    RequestLoginCode              émission et envoi d'un code
    VerifyLoginCode               vérification, consommation, comptage
  Infrastructure/
    Doctrine/AccountEntity, LoginCodeEntity, LoginAttemptEntity
    Doctrine/DoctrineAccountRepository, DoctrineLoginCodeRepository,
             DoctrineLoginAttemptRepository
    Security/AccountUser, AccountUserProvider
    SymfonyAccountMailer, SymfonyLoginCodeMailer
    RandomCodeGenerator
  UI/
    CreateAccountCommand          app:account:create
    LoginController               /admin/connexion
```

Les entités Doctrine sont distinctes des objets du domaine (décision du chantier
1) et portent le suffixe `Entity`. `config/packages/doctrine.yaml` passe en
`auto_mapping: false` avec un mapping explicite pour ce contexte ; le mapping
`src/Entity` du squelette disparaît, comme le dossier vide qu'il visait.

## Schéma

| Table | Colonnes |
|---|---|
| `account` | `id`, `email` unique, `name`, `active`, `created_at`, `last_login_at` null |
| `login_code` | `id`, `account_id` FK, `code_hash`, `expires_at`, `consumed_at` null, `tries`, `created_at` |
| `login_attempt` | `id`, `email` null, `ip`, `kind`, `created_at` — index sur (`email`, `created_at`) et (`ip`, `created_at`) |

`account.email` est normalisé en minuscules à l'entrée du domaine, ce qui rend
l'index unique suffisant (pas besoin de `citext`).

`login_attempt.kind` : `unknown_email`, `wrong_code`, `code_sent`.

### Piège des fuseaux, trouvé en écrivant les tests

Sur ce poste, **PHP tourne en UTC et PostgreSQL en Europe/Malta (+02)**. Les
instants sont donc stockés en `timestamptz` pour rester absolus. Mais ça ne
suffit pas : dans une requête DQL, Doctrine déduit le type d'un paramètre de la
valeur PHP et choisit `datetime`, ce qui envoie l'instant **sans son décalage**.
PostgreSQL le relit alors dans le fuseau de sa session et toute la fenêtre
glisse d'autant — un blocage de 15 minutes aurait duré 2 h 15, et le délai de
45 secondes aurait sauté.

Règle à tenir : **tout paramètre d'instant passe son type explicitement**
(`setParameter($nom, $valeur, 'datetimetz_immutable')`). Le test
« ce qui tombe hors fenêtre est ignoré » garde cette règle.

## Règles de sécurité (reprises de la maquette)

| Règle | Valeur |
|---|---|
| Durée de vie d'un code | 10 minutes |
| Délai entre deux envois | 45 secondes |
| Renvois maximum par code | 3 |
| Codes erronés avant blocage | 3 |
| Adresses inconnues avant blocage | 3 |
| Durée du blocage | 15 minutes |
| Durée de la session « rester connecté » | 30 jours |

Le blocage se calcule sur l'IP **et** sur l'adresse : trois codes erronés pour une
même adresse bloquent cette adresse, trois adresses inconnues depuis une même IP
bloquent cette IP. Un compte `active = false` se comporte comme une adresse
inconnue — aucune indication qu'il a existé.

Les deux limites d'envoi se lisent toutes deux sur les lignes `code_sent` de
l'adresse : le **délai** compare l'instant courant à la dernière ligne, le
**plafond** compte les lignes des 10 dernières minutes (la durée de vie d'un
code). Un plafond atteint n'est donc pas un blocage : il se relâche seul dès que
le code courant expire.

## Flux

1. `GET /admin/connexion` — étape 1, formulaire e-mail.
2. `POST /admin/connexion` — si l'adresse est bloquée ou l'IP bloquée, on rend
   l'écran de blocage. Si l'adresse est inconnue ou le compte inactif, on
   enregistre la tentative et on rend l'étape 1 avec le message et le nombre
   d'essais restants. Sinon on émet un code, on l'envoie, et on passe à l'étape 2
   (l'adresse est mémorisée en session, jamais en URL).
3. `POST /admin/connexion/code` — vérification. Code faux : on incrémente, on
   enregistre la tentative, on renvoie l'étape 2 avec les essais restants. Au
   troisième, écran de blocage. Code juste : consommation du code,
   `Security::login()`, `last_login_at` mis à jour, redirection vers le dashboard.
4. `POST /admin/connexion/renvoi` — soumis au délai de 45 s et au plafond de 3.

L'étape « succès » de la maquette n'est pas un écran serveur : la connexion
réussie redirige directement vers le dashboard. Cet artboard sert de repère
visuel, pas de page.

Un message d'erreur ne survit pas à l'écran qui l'affiche, mais **l'écran de
blocage, si** : sans cela, un simple rafraîchissement escamoterait le compte à
rebours alors que la pause court toujours. Contrepartie découverte à l'usage : il
faut pouvoir en sortir. « Changer d'adresse » et « Réessayer maintenant » vident
donc à la fois l'adresse en cours et le retour d'erreur.

## Interface

- `assets/styles/tokens.css` — les `@font-face` et le `:root` extraits de
  `app.css`, importés par `app.css` et par le nouveau `admin.css`. Aucun coloris
  n'est dupliqué : la maquette n'utilise que des valeurs déjà présentes dans les
  tokens, plus quelques teintes d'encarts (succès, erreur, information) qui les
  rejoignent.
- `templates/admin/base.html.twig` — layout du dashboard : ni en-tête, ni pied de
  page, ni bandeau cookies du site public, et `<meta name="robots" content="noindex">`.
- `templates/admin/login/` — un partiel par état : `_step_email`, `_step_code`,
  `_blocked`.
- `assets/controllers/login_code_controller.js` — les 6 cases : focus automatique,
  collage d'un code entier, retour arrière vers la case précédente, `Entrée` pour
  soumettre, compte à rebours des 45 s.

Le formulaire fonctionne sans JavaScript : les 6 cases dégradent en un champ
unique de 6 chiffres. Le dashboard n'a pas à être utilisable au clavier seul dans
un navigateur sans JS, mais un formulaire qui ne part pas du tout serait un piège.

## Gabarits d'e-mail

Deux envois, dont les gabarits sont dessinés par Clément dans Claude Design :

- `templates/emails/login_code.html.twig` — le code à 6 chiffres ;
- `templates/emails/account_created.html.twig` — l'information d'un nouvel accès.

Ils sont d'abord posés en version provisoire, avec les variables déjà figées pour
que le design s'y substitue sans toucher au code PHP :

| Gabarit | Variables |
|---|---|
| `login_code` | `code`, `account` (name, email), `expires_in_minutes`, `establishment` |
| `account_created` | `account` (name, email), `login_url`, `establishment` |

## Commande de création de compte

```
php bin/console app:account:create --email=… --name=…
```

- interactive quand un argument manque ;
- refuse une adresse déjà utilisée (code de sortie 1) ;
- `--sans-email` pour ne pas notifier — indispensable pour le premier compte,
  avant que les gabarits existent ;
- **si l'envoi échoue, le compte reste créé** : un accès ne doit pas dépendre de
  la remise SMTP. La commande le signale et sort en 0.

## Tests

- Domaine : règles de `Account`, `LoginCode` (expiration, essais).
- Application : `CreateAccount` (unicité, notification, échec d'envoi toléré),
  `RequestLoginCode` (délai, plafond de renvois), `VerifyLoginCode` (code faux,
  expiré, déjà consommé, blocage au troisième essai).
- Infrastructure : les trois repositories Doctrine, contre `giulia_dashboard_test`.
- Fonctionnel : les trois étapes de l'écran, le pare-feu `^/admin` qui redirige
  un visiteur anonyme, et la connexion complète d'un compte de test.

Les doubles de test remplacent le générateur de code (valeur fixe) et l'horloge
(`FrozenClock` existe déjà dans `tests/Opening/Support/`, à promouvoir en
`tests/Support/`).

## Hors périmètre

Les écrans du dashboard lui-même, la gestion des accès depuis l'interface (elle
passe par la commande pour l'instant), le pointage des horaires et les relevés de
température de la boutique, et tout le chantier 1 (contenu éditorial en base).
