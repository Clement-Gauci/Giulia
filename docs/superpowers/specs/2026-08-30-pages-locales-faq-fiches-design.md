# Pages locales, FAQ & enrichissement des fiches pizza — Design

**Date :** 2026-08-30
**Statut :** en attente de relecture du contenu par le client
**Origine :** audit SEO local du 2026-08-30 (chapitre 03 « Les pages qui vous manquent »)

## 1. Contexte & objectif

L'audit SEO a établi que le site est techniquement sain mais **éditorialement pauvre** :
4 pages seulement, aucune ne cible Clisson (7 465 hab. à 2,3 km, le vrai bassin de
population face aux 4 462 hab. de Gorges), 15 fiches pizza partageant la même phrase
générique, et aucune FAQ.

Cette itération crée le contenu manquant :

1. **Deux pages locales** — Clisson, et le vignoble nantais (bourgs voisins regroupés).
2. **Une FAQ** avec balisage `FAQPage`.
3. **Un texte propre par pizza**, pour sortir les 15 fiches de la duplication.
4. **Le sitemap** étendu à toutes ces pages.

### Le risque à ne pas prendre

Google sanctionne les *doorway pages* : des pages « ville » quasi identiques créées pour
le seul référencement. La règle retenue est stricte — **si une page ne peut pas porter
300 mots véritablement spécifiques à son sujet, elle n'est pas créée.** C'est pourquoi le
périmètre est de 2 pages locales et non de 7.

### Ce que la carte PDF a appris (et qui change le contenu)

La lecture de `public/menu.pdf` a corrigé plusieurs hypothèses. **À intégrer aussi hors
de cette itération :**

| Découverte | Conséquence |
|---|---|
| Giulia est **« Pizzeria \| Trattoria »** et une **épicerie fine italienne** — charcuteries, fromages, plats cuisinés, épicerie. | Le site ne parle **que** de pizzas. C'est une part entière de l'activité rendue invisible. ⚠️ L'audit affirmait que le classement « épicerie fine » de Justacote était une erreur : **c'était faux, il est exact.** Correction apportée à l'audit. |
| La carte des vins est **100 % italienne**. | Les accords mets-vins se font avec vos vins, pas avec le muscadet. Le muscadet reste un ancrage **géographique** (les crus portent les noms des communes voisines), jamais commercial. |
| Vous servez du **Chianti et du Chianti Classico DOCG de Toscane**. | Angle en or pour la page Clisson : Clisson est la ville reconstruite « à la toscane ». |
| Vous servez du **Lambrusco de Ceci (Émilie-Romagne)**. | Accord régional juste avec le jambon de Parme et la mortadelle — mêmes terres que Villani (Modène). |
| Vos bières incluent **Les Papas Brasseurs**, brasserie locale. | Ancrage local authentique, à exploiter sur les pages locales. |
| La carte précise **« Après cuisson : »** pour le Parme, le saumon, la stracciatella, le miel, les olives. | Confirme la technique napolitaine — argument de qualité concret pour les fiches. |
| Le PDF fournit un **glossaire produits** (San Marzano DOP de Campanie, fior di latte, taggiasche d'Imperia, taleggio de Lombardie, spianata de Calabre au fenouil, stracciatella, guanciale). | Matière première des fiches, et c'est **votre** texte. |
| **Horaires boutique ≠ horaires pizzas** : ouverture 10h-14h30 / 17h-21h30, mais pizzas servies 11h30-14h et 18h-21h30 (22h ven./sam.). | Le site n'affiche que les horaires boutique. Un client peut venir à 10h30 et repartir sans pizza. **Question n°1 de la FAQ**, et à corriger sur le site. |
| L'équipe : **Estelle, Clément et Adrien**. | Matière pour la future page « qui nous sommes ». |

### L'angle éditorial

Trois ancrages vérifiés rendent ce contenu impossible à dupliquer par un concurrent — en
particulier par une chaîne comme Signorizza :

- **Clisson est surnommée « l'Italienne ».** Reconstruite sur le modèle toscan à partir de
  1798 par les frères Cacault puis le sculpteur Frédéric Lemot : tuiles canal, arcs en
  plein cintre, cyprès, domaine de la Garenne-Lemot. Une trattoria **napolitaine** qui sert
  du **Chianti de Toscane** à 2,3 km de la ville toscane de Loire-Atlantique : c'est écrit d'avance.
- **Les 7 crus communaux du Muscadet Sèvre et Maine portent les noms des communes cibles** —
  et **Gorges en est un** (codifié en 2011 avec Clisson et Le Pallet ; Monnières-Saint Fiacre
  et Mouzillon-Tillières en 2019). Fil conducteur de la page vignoble, en assumant le
  contraste : on est au cœur du muscadet, et on sert italien.
- **L'épicerie fine.** Personne d'autre sur la zone ne fait pizzeria + trattoria + épicerie
  italienne. C'est le vrai différenciateur, et il est aujourd'hui absent du site.

## 2. Décisions actées

| Sujet | Décision |
|---|---|
| Périmètre local | **2 pages** : Clisson (le bassin) + « le vignoble nantais » (Le Pallet, Monnières, Mouzillon, Saint-Lumine, Saint-Hilaire, Saint-Fiacre regroupés). Pas une page par commune. |
| URLs | `/pizzeria/clisson` et `/pizzeria/vignoble-nantais` — préfixe stable, mot-clé dans l'URL, aucune collision. |
| URL FAQ | `/questions-frequentes` (le mot « FAQ » reste dans le titre). |
| Navigation | **Footer uniquement**, bloc « On vous accueille aussi à ». Le header reste à 3 entrées. |
| Architecture | Deux nouveaux bounded contexts `Local` et `Faq`, calqués sur `Legal`. |
| Fiches pizza | Champ `story` optionnel dans `menu.yaml`, porté par `Pizza`. |
| Sitemap | `SitemapUrlProvider` reçoit les nouveaux repositories et publie les 3 pages. |
| Données structurées | `FAQPage` sur la FAQ. |
| Accords vins | **Uniquement des vins de votre carte.** Accords régionaux italiens traditionnels. |
| Contenu | Rédigé ici pour relecture avant implémentation. |

## 3. Architecture

### Contexte `Local`

```
src/Local/Domain/ServiceArea.php             (name, slug, title, description, intro, sections[])
src/Local/Domain/AreaRepositoryInterface.php (all(), findBySlug())
src/Local/Infrastructure/YamlAreaRepository.php
src/Local/UI/AreaController.php              #[Route('/pizzeria/{slug}', name: 'area_show')]
config/giulia/areas.yaml
templates/local/area.html.twig
```

`findBySlug()` renvoie `null` sur slug inconnu → `createNotFoundException`, comme
`menu_show`. Aucune route fourre-tout.

### Contexte `Faq`

```
src/Faq/Domain/Question.php                  (question, answer)
src/Faq/Domain/FaqRepositoryInterface.php
src/Faq/Infrastructure/YamlFaqRepository.php
src/Faq/UI/FaqController.php                 #[Route('/questions-frequentes', name: 'faq')]
config/giulia/faq.yaml
templates/faq/index.html.twig
```

JSON-LD `FAQPage` construit dans `StructuredDataBuilder::buildFaq()`, rendu par un bloc
Twig surchargé sur la seule page FAQ.

### Extension de `Menu`

`Pizza` gagne `?string $story` (`null` par défaut). `menu/show.html.twig` l'affiche sous
les ingrédients dans un `{% if %}` — les pizzas sans texte restent valides.

### Modifications de l'existant

| Fichier | Changement |
|---|---|
| `src/Seo/Application/SitemapUrlProvider.php` | Injecte `AreaRepositoryInterface` ; publie `/questions-frequentes` (0.6) + une entrée par zone (0.8). |
| `templates/components/_footer.html.twig` | Bloc « On vous accueille aussi à » + lien FAQ. |
| `config/services.yaml` | Câblage des deux repositories. |
| `config/giulia/menu.yaml` | Champ `story` sur les 15 pizzas. |

---

# 4. CONTENU À RELIRE

> **Comment relire :** tout ce qui est marqué **`⚠️`** est une affirmation que je n'ai pas
> pu confirmer et qui sera publiée telle quelle. Corrigez directement dans ce fichier.
> Le reste est soit sourcé (carte PDF, INSEE, INAO), soit de la rédaction libre.

## 4.1 Page Clisson — `/pizzeria/clisson`

**Title :** Pizzeria à Clisson — Giulia, pizzas napolitaines & épicerie italienne
**Meta description :** À 5 minutes de Clisson : pizzas napolitaines à emporter, trattoria et épicerie fine italienne. Pâte maturée 48 h, vins italiens, parking gratuit.
**H1 :** Votre pizzeria napolitaine à côté de Clisson

### Intro

> Clisson, on l'appelle « l'Italienne ». Au début du XIX<sup>e</sup> siècle, les frères
> Cacault et le sculpteur Frédéric Lemot ont relevé la ville en ruines sur le modèle
> toscan : tuiles canal, briques minces, arcs en plein cintre, cyprès au bord de la Sèvre.
> Deux siècles plus tard, le paysage est toujours là.
>
> Nous, on est à 2,3 km, à Gorges, et on fait de la vraie pizza napolitaine. Autant dire
> qu'on se sentait attendus. Chez Giulia, vous trouverez une pâte maturée 48 heures, des
> produits choisis chez de petits producteurs italiens, une trattoria et une épicerie fine
> — et, oui, du Chianti de Toscane pour accompagner tout ça.

### Section « Cinq minutes depuis le centre de Clisson »

> Nous sommes à **2,3 km du centre de Clisson**, route de Saint-Fiacre, à Gorges — environ
> **cinq minutes en voiture** depuis les Halles. ⚠️ Itinéraire exact à préciser.
>
> Le stationnement est **gratuit et juste devant** : vous vous garez, vous récupérez votre
> commande, vous repartez. Pas de tour du château à faire pour trouver une place un
> vendredi soir.

### Section « Une trattoria, pas seulement des pizzas »

> ⚠️ **SECTION NOUVELLE — à valider.** Votre carte annonce une trattoria et une épicerie
> fine italienne (charcuteries, fromages, plats cuisinés, épicerie), mais le site n'en dit
> rien nulle part. C'est votre vrai différenciateur face aux pizzerias du secteur.
>
> Chez nous, on ne fait pas que des pizzas. On a monté une épicerie fine italienne avec
> les produits qu'on aime : charcuteries, fromages, plats cuisinés, et une sélection
> d'épicerie qu'on ne trouve pas ailleurs dans le vignoble. Vous pouvez venir chercher vos
> pizzas et repartir avec de quoi tenir la semaine.
>
> ⚠️ À compléter : quels produits phares ? Peut-on composer un panier ? Y a-t-il des plats
> cuisinés à emporter, et lesquels ?

### Section « Comment ça marche »

> ⚠️ **Point important relevé sur votre carte :** la boutique ouvre à 10 h, mais les pizzas
> ne sont servies qu'à partir de 11 h 30 (et de 18 h le soir). Le site n'affiche que les
> horaires de la boutique — un client peut donc venir à 10 h 30 pour une pizza et repartir
> déçu. À dire explicitement ici, et à corriger sur la page d'accueil.
>
> La boutique est ouverte du mardi au samedi de 10 h à 14 h 30 et de 17 h à 21 h 30 (22 h
> les vendredis et samedis), et le dimanche de 18 h à 21 h 30. **Les pizzas, elles, sont
> préparées de 11 h 30 à 14 h et de 18 h à 21 h 30** — jusqu'à 22 h les vendredis et samedis.
>
> Vous commandez en ligne en click & collect, vous choisissez votre créneau, et vos pizzas
> vous attendent chaudes. Vous pouvez aussi appeler — mais aux heures de pointe, la
> commande en ligne vous garantit votre créneau.

### Section « Le vin qui va avec »

> Clisson s'est rêvée toscane. Ça tombe bien : notre carte l'est aussi, un peu. On sert du
> **Chianti Torre delle Grazie DOCG** et son grand frère le **Chianti Classico**, tous deux
> de Toscane — le rouge qu'on servirait à Florence sur une pizza aux champignons ou sur
> notre Tartufo.
>
> À côté, la carte descend toute la botte : Bardolino et Pinot Grigio de Vénétie,
> Montepulciano des Abruzzes, Primitivo des Pouilles, Nero d'Avola de Sicile, Lambrusco
> d'Émilie-Romagne, Moscato d'Asti du Piémont. Que des vins italiens, choisis pour aller
> avec ce qui sort du four.

### Section « Le Hellfest »

> ⚠️ **À CONFIRMER OU SUPPRIMER.** Si vous fermez pendant le festival, cette section fait
> plus de mal que de bien.
>
> Chaque été, Clisson accueille des dizaines de milliers de festivaliers, et la question
> revient toujours : où manger sans faire la queue ? On est à cinq minutes du site, avec du
> stationnement gratuit et des pizzas préparées à la commande.
>
> ⚠️ Ouvrez-vous pendant le Hellfest ? Horaires élargis ? Faut-il commander plus tôt ?

### Liens internes

Carte, pizza du moment (si active), horaires, click & collect, FAQ, page vignoble.

---

## 4.2 Page vignoble — `/pizzeria/vignoble-nantais`

**Title :** Pizza à emporter dans le vignoble nantais — Giulia, Gorges
**Meta description :** Pizzas napolitaines et épicerie italienne au cœur du vignoble nantais : Le Pallet, Monnières, Mouzillon, Saint-Lumine, Saint-Hilaire, Saint-Fiacre. Click & collect à Gorges.
**H1 :** Une pizzeria napolitaine au cœur du vignoble nantais

### Intro

> Il y a sept crus communaux dans le Muscadet Sèvre et Maine. Clisson, Le Pallet et Gorges
> ont ouvert la voie en 2011 ; Château-Thébaud, Goulaine, Monnières-Saint Fiacre et
> Mouzillon-Tillières ont suivi en 2019.
>
> Notre four est installé sur l'un d'eux. **Gorges** : du gabbro, de l'argile, des vins
> tendus et minéraux élevés longuement sur lie. On ne va pas prétendre le contraire — chez
> nous, la carte des vins est italienne du premier au dernier. Mais on aime bien l'idée
> d'être une maison napolitaine posée au milieu d'un des plus beaux terroirs de blanc de
> France. Le vignoble a le sien, on a le nôtre, et nos voisins font très bien le pont.
>
> Voici justement nos voisins, et le temps qu'il faut pour venir chercher ses pizzas.

### Les communes

> **Saint-Lumine-de-Clisson — 3,3 km**
> Notre plus proche voisin après Clisson. Moins de dix minutes de route.
>
> **Le Pallet — 4,7 km**
> Cru communal depuis 2011, et village natal de Pierre Abélard au XI<sup>e</sup> siècle.
> Une dizaine de minutes, et vos pizzas sont dans le coffre.
>
> **Mouzillon — 4,7 km**
> Cru communal Mouzillon-Tillières depuis 2019 : gabbro et argile, comme chez nous. On est
> d'ailleurs sur la route entre Mouzillon et Clisson — difficile de faire plus direct.
>
> **Saint-Hilaire-de-Clisson — 4,6 km**
> De l'autre côté de la Sèvre, une petite dizaine de minutes.
>
> **Monnières — 5,2 km · Saint-Fiacre-sur-Maine — 9,7 km**
> Réunies dans le cru Monnières-Saint Fiacre depuis 2019, sur des argiles de gneiss
> décomposé. Saint-Fiacre est notre voisin le plus éloigné de cette page — et comme on vous
> doit une pizza encore chaude, pensez à réserver votre créneau.

### Section « Ce qu'on a d'italien à vous proposer »

> ⚠️ À valider — même remarque qu'en 4.1, l'épicerie est absente du site.
>
> On est une pizzeria, une trattoria et une épicerie fine italienne. Charcuteries, fromages,
> plats cuisinés, produits d'épicerie choisis chez de petits producteurs : de quoi faire le
> détour même quand on ne vient pas pour une pizza. Côté boisson, tout est italien — sauf
> nos bières, où on a gardé **Les Papas Brasseurs**, brasseurs d'ici, à côté de la Peroni.

### Section « Click & collect »

> On ne livre pas. ⚠️ À confirmer.
>
> Tout part du même endroit, à Gorges : vous commandez en ligne, vous choisissez votre
> créneau, vous récupérez des pizzas préparées à la minute. Stationnement gratuit devant.
> Attention aux horaires : la boutique ouvre à 10 h, mais **les pizzas sortent du four à
> partir de 11 h 30**, et de 18 h le soir.

---

## 4.3 FAQ — `/questions-frequentes`

**Title :** Questions fréquentes — Giulia, pizzeria napolitaine à Gorges
**Meta description :** Horaires des pizzas, sans gluten, allergènes, délais, groupes, paiement : toutes les réponses sur Giulia, pizzeria napolitaine et épicerie italienne à Gorges, près de Clisson.
**H1 :** Vos questions, nos réponses

> **⚠️ SECTION À VÉRIFIER LIGNE À LIGNE.** J'ai rédigé des réponses plausibles comme base
> de travail. Chaque réponse fausse publiée deviendra un appel mécontent — et Google peut
> les afficher directement dans ses résultats.

| # | Question | Réponse proposée | Statut |
|---|---|---|---|
| 1 | À quelle heure peut-on avoir des pizzas ? | La boutique ouvre dès 10 h, mais les pizzas sont préparées de 11 h 30 à 14 h et de 18 h à 21 h 30 — jusqu'à 22 h les vendredis et samedis. | ✅ Carte PDF. **Question la plus utile de la FAQ** : l'écart boutique/pizzas est invisible sur le site. |
| 2 | Vous ne faites que des pizzas ? | Non : nous sommes aussi une trattoria et une épicerie fine italienne — charcuteries, fromages, plats cuisinés et produits d'épicerie choisis chez de petits producteurs. | ✅ Carte PDF, ⚠️ à préciser |
| 3 | Proposez-vous des pizzas sans gluten ? | *à compléter* | ⚠️ Inconnu. Si non, le dire évite des déplacements inutiles. |
| 4 | Avez-vous des pizzas végétariennes ? | Oui : Margherita, Quattro Stagioni, Quattro Formaggi et Miele, repérables au picto 🌱 sur la carte. | ✅ `menu.yaml` |
| 5 | Comment connaître les allergènes ? | *à compléter* | ⚠️ Le champ `allergens` est vide pour les 15 pizzas. Renvoyer vers un appel en attendant. |
| 6 | Quelle taille font vos pizzas ? | 33 cm de diamètre, pour une à deux personnes. | ✅ Déjà sur le site |
| 7 | Combien de temps entre la commande et le retrait ? | *à compléter* | ⚠️ Délai réel inconnu |
| 8 | Faut-il commander à l'avance ? | La commande en ligne garantit votre créneau, surtout les vendredis et samedis soir. Vous pouvez aussi appeler au 02 85 52 87 42. | ⚠️ À confirmer |
| 9 | Livrez-vous à domicile ? | Non, uniquement à emporter en click & collect, à Gorges. | ⚠️ À confirmer |
| 10 | Peut-on commander pour un groupe ? | Au-delà de 5 pizzas, appelez-nous directement : c'est plus simple pour caler le créneau. | ⚠️ À confirmer — le site mentionne « plus de 5 pizzas, appelez-nous » |
| 11 | Quels moyens de paiement acceptez-vous ? | *à compléter* | ⚠️ CB ? Sans contact ? Espèces ? |
| 12 | Acceptez-vous les tickets restaurant ? | *à compléter* | ⚠️ Inconnu |
| 13 | Servez-vous du vin ? | Uniquement des vins italiens : Chianti et Chianti Classico DOCG de Toscane, Bardolino et Pinot Grigio de Vénétie, Montepulciano des Abruzzes, Primitivo des Pouilles, Nero d'Avola de Sicile, Lambrusco d'Émilie-Romagne, Prosecco, Moscato d'Asti. Côté bière, la Peroni italienne et les bières locales des Papas Brasseurs. | ✅ Carte PDF |
| 14 | Y a-t-il un parking ? | Oui, gratuit et directement devant. | ✅ Déjà sur le site |
| 15 | C'est quoi le groupe WhatsApp ? | Un groupe anti-gaspi : quand il reste des pizzas en fin de service, on prévient les membres. | ⚠️ Fonctionnement exact et prix à confirmer |
| 16 | Où êtes-vous exactement ? | 1 rue de la Cité des Sports, route de Saint-Fiacre, 44190 Gorges — sur la route entre Mouzillon et Clisson, à 2,3 km du centre de Clisson. | ✅ |

---

## 4.4 Textes des fiches pizza

> Deux à trois phrases par pizza, en champ `story` dans `menu.yaml`. Chaque texte se
> termine par un accord avec **un vin de votre carte**.
>
> **Charcuteries : Villani** — maison familiale fondée en 1886 à Castelnuovo Rangone près
> de Modène ; sites spécialisés à Langhirano (jambon de Parme) et près de Bologne
> (mortadelle). ⚠️ Vous écrivez « Vilani », je trouve « **Villani** » : confirmez
> l'orthographe, et surtout **quels produits précis** viennent de chez eux. Je ne les cite
> nommément dans aucun texte tant que ce n'est pas confirmé.

### Base sauce tomate

**Margherita — 10,00 €**
> L'originale. Naples, 1889 : le pizzaiolo Raffaele Esposito compose pour la reine
> Marguerite une pizza aux couleurs du drapeau — tomate, mozzarella, basilic. Trois
> ingrédients, aucun endroit où se cacher : c'est la pizza qui dit tout d'une maison. La
> nôtre repose sur des San Marzano DOP de Campanie et une fior di latte. Un Chianti la
> tient sans la couvrir.

**Regina — 14,50 €**
> La grande classique des cartes italiennes, celle qu'on commande sans réfléchir. Les
> champignons cuisinés passent au four ; le prosciutto cotto, les olives taggiasche
> d'Imperia et le basilic arrivent après cuisson, pour garder leur fraîcheur. À boire avec
> un Chianti Torre delle Grazie.

**Giulia — 15,90 € · signature**
> Notre signature, celle qui porte le nom de la maison. Tout se joue après la cuisson : le
> jambon de Parme, la confiture de figues, les éclats de noisettes et le parmesan sont
> déposés à la sortie du four. Sucré, salé, croquant. Un Lambrusco Amabile d'Émilie-Romagne
> — la région du Parme — est l'accord traditionnel, et il fonctionne.
> ⚠️ Y a-t-il une histoire derrière cette recette ? C'est le texte le plus lu du site, il
> mérite votre version.

**Parma — 15,90 €**
> Tout est dans le jambon de Parme, posé à cru à la sortie du four pour qu'il ne cuise
> jamais. Les tomates cerises confites concentrent le sucre, la roquette apporte
> l'amertume, la crème de balsamique tranche. Là encore, le Lambrusco est chez lui.

**Diavola — 13,90 € · piquante**
> La pizza du diable, et elle porte bien son nom. La spianata piquante est une saucisse
> sèche calabraise parfumée au fenouil et relevée au piment — l'équivalent italien du
> chorizo — qui libère son gras à la cuisson. Les olives taggiasche calment le jeu, un peu.
> Un Primitivo des Pouilles ou un Nero d'Avola de Sicile tiennent la chaleur.

**Quattro Stagioni — 14,90 € · végétarienne**
> Notre lecture végétarienne des quatre saisons : aubergines, poivrons, artichauts et
> tomates séchées, chacun dans son quartier. La stracciatella — le cœur crémeux de la
> burrata — est ajoutée après cuisson et fait la liaison entre les quatre. Un Pinot Grigio
> de Vénétie garde l'ensemble léger.

**Calabrese — 16,90 € · piquante**
> La plus généreuse de la carte, et la plus méridionale. Spianata piquante, boulettes de
> bœuf cuisinées, scamorza fumée et oignons rouges confits : le fumé, le piquant et le
> sucré se répondent d'un bout à l'autre. Elle demande un vin qui ne recule pas — le
> Primitivo del Salento.
> ⚠️ **À trancher :** la Calabrese est classée sous « Base sauce tomate » sur la carte PDF
> comme dans `menu.yaml`, mais ne contient **pas** de San Marzano. Oubli de saisie, ou
> pizza sur base blanche mal rangée ? Ça change le texte et le classement.

### Base crème fraîche

**Quattro Formaggi — 14,90 € · végétarienne**
> Quatre fromages, quatre rôles : la fior di latte pour le fondant, la ricotta pour la
> douceur, le gorgonzola pour le caractère, le taleggio de Lombardie — proche d'un
> reblochon — pour la pointe rustique qui reste en bouche. Pas de tomate, pour ne pas
> brouiller le message. Un Moscato d'Asti sur le gorgonzola, si vous aimez les accords qui
> osent.

**Miele — 12,50 € · végétarienne**
> La plus simple de nos créations, et souvent la préférée de ceux qui l'essaient. Chèvre
> cendré, base crème, et le miel versé à la sortie du four. Deux ingrédients, un sucré-salé
> direct. Le Moscato d'Asti prolonge le miel ; le Prosecco fait l'inverse et le coupe.

**Salmone — 16,90 €**
> Le saumon fumé et la stracciatella sont déposés après cuisson : le poisson reste frais,
> le crémeux intact. L'huile d'olive citronnée réveille l'ensemble. Un Pinot Grigio delle
> Venezie, et on est au bord de l'Adriatique.

**Montagna Originale — 15,90 €**
> Une pizza de montagne, généreuse et réconfortante. Les pommes de terre cuisinées font le
> socle, le guanciale — cette joue de porc séchée du centre de l'Italie qui fait les vraies
> carbonara — apporte le gras et le sel, le taleggio fond par-dessus. Un Bardolino de
> Vénétie, léger et frais, l'empêche de peser.

### Base spéciale

**Pollo Rosso — 15,90 €**
> Le pesto rosso, aux tomates séchées, remplace la sauce tomate et donne à cette pizza sa
> couleur profonde. Le poulet cuisiné et la scamorza fumée s'appuient dessus, les oignons
> rouges confits ajoutent leur sucre. Un Bardolino Chiaretto rosé fait très bien l'affaire.

**Tartufo — 16,90 €**
> Pour les amateurs de truffe, sans détour : crème de truffe en base, copeaux de truffes
> ajoutés après cuisson avec le prosciutto cotto et le parmesan. Et puisque la truffe est
> une affaire toscane et ombrienne, on vous conseille le Chianti Classico DOCG.
> ⚠️ Truffe fraîche, en conserve, ou arôme ? À formuler honnêtement — les clients avertis
> y regardent, et une formulation trop flatteuse se retourne vite en commentaire.

**Camembert Rôti — 15,50 €**
> Un camembert entier, rôti au four, au centre de la pizza. Clin d'œil franco-italien
> assumé : la technique est napolitaine, le fromage est normand. Le jambon de Parme, le
> miel, la crème de balsamique et la roquette arrivent après cuisson. Un Bardolino
> Chiaretto, sur la fraîcheur.

**Pistacchio — 16,20 €**
> Le grand classique de Bologne : mortadelle et pistache. On le pousse jusqu'au bout — crème
> de pistaches en base, puis mortadelle, stracciatella, pesto de pistaches et éclats après
> cuisson. Et comme la mortadelle vient d'Émilie-Romagne, on lui sert son Lambrusco.

---

## 5. Récapitulatif des points à trancher

| # | Point | Où |
|---|---|---|
| 1 | Orthographe Vilani / **Villani**, et quels produits exactement | 4.4 |
| 2 | Calabrese : base tomate ou base blanche ? | 4.4 |
| 3 | Réponses 3, 5, 7, 11, 12 de la FAQ (sans gluten, allergènes, délai, paiement, tickets resto) | 4.3 |
| 4 | Ouverture pendant le Hellfest — sinon la section saute | 4.1 |
| 5 | Contenu de l'épicerie fine : produits phares, plats cuisinés, paniers | 4.1, 4.2 |
| 6 | Itinéraire exact depuis Clisson | 4.1 |
| 7 | Livraison : confirmé qu'il n'y en a pas ? | 4.2, 4.3 |
| 8 | Histoire de la pizza signature Giulia | 4.4 |
| 9 | Type de truffe sur la Tartufo | 4.4 |
| 10 | Allergènes — toujours vides dans `menu.yaml` | 4.3 |
| 11 | Fonctionnement exact du groupe WhatsApp anti-gaspi | 4.3 |

## 6. Tests

- **Fonctionnels** : les 3 pages répondent 200 et affichent leur H1 ; slug de zone inconnu
  → 404 ; les 3 pages figurent dans le sitemap ; le JSON-LD de la FAQ est un `FAQPage`
  valide comptant autant de `Question` que le YAML ; le footer expose les liens.
- **Unitaires** : `YamlAreaRepository` et `YamlFaqRepository` sur fixtures dédiées ;
  `Pizza::story()` optionnel.
- **Non-régression** : le sitemap conserve ses 19 URL ; les fiches sans `story` s'affichent.

TDD : test d'abord, échec observé, puis implémentation.

## 7. Hors périmètre — mais à programmer

Ces points sont sortis de l'analyse de la carte et méritent leur propre itération :

1. **L'épicerie fine et la trattoria sont absentes du site.** C'est votre différenciateur
   n°1 sur la zone et il est invisible. Sans doute la prochaine priorité après les photos.
2. **L'écart horaires boutique / horaires pizzas** n'est pas exposé sur la page d'accueil.
3. **La carte des vins n'est nulle part sur le site** — uniquement dans le PDF.
4. Photos, page « qui nous sommes » (Estelle, Clément et Adrien), enrichissement du JSON-LD
   `Restaurant`, correctifs techniques de l'audit (`/home`, DNS `www`, `lastmod`).
