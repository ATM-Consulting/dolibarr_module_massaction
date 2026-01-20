# Changelog for massaction

## Unreleased
- NEW : ST-5 - copy customer reference to supplier proposal when creating from propal/order (configurable) - *19/01/2026* - 1.8.0
- FIX : ST-5 - store supplier reference on RFQ using ref_supplier alias for compatibility - *20/01/2026* - 1.8.0

## Release 1.7
- FIX : ST-5 - add field Maximum response date on supplier proposal massaction - *20/12/2025* - 1.7.4
- FIX : ST-7 Add attachments when generating supplier RFQs - *19/12/2025* - 1.7.3
- NEW : ST-7 Add user attachments when generating supplier RFQs (upload, store on proposal, include in emails, remove before confirm) - *15/12/2025* - 1.7.2
- FIX : When converting a quote into a price request, VAT must be charged. - *05/11/2025* -1.7.1
- NEW : Add mass action to create a new supplier proposal from a customer proposal or order - *09/10/2025* - 1.7.0

## Release 1.6
- FIX : Compat V22 - *02/10/2025* - 1.6.4
- FIX : DA026579 => fix undeclared variable toShow  - *23/05/2025* - 1.6.3
- FIX : Using $this in non object context - *15/04/2025* - 1.6.2
- FIX : COMPAT V21 - *06/12/2024* - 1.6.1
- NEW : COMPAT V20 - *26/07/2024* - 1.6.0

## Release 1.5
- FIX : DA026002 Select only checkbox with checkforselect class to reset checked - *05/02/2024* - 1.5.2
- FIX : ICON siccors  in > 18.0  - *17/06/2024* - 1.5.1
    - remove question  in massaction confirm
    - set main handler in hook section
- NEW : Ajout d'un select pour actions en masse *22/05/2024* - 1.5.0
    - Editer marge et quantité
    - Supprimer
    - Couper
- NEW : Ajout de checkbox à droite des lignes propal pour actions en masse *22/05/2024* - 1.5.0
- NEW : Déplacement des fonctions du module Split dans MassAction *22/05/2024* - 1.5.0

## Release 1.4
- NEW : Compat V19 et php 8.2 *04/12/2023* - 1.4.0  
  Changed Dolibarr compatibility range to 15 min - 19 max  
  Change PHP compatibility range to 7.0 min - 8.2 max

## Release 1.3
- FIX : Family name due to compatibility v16 *2807/2022* - 1.3.2
- FIX : Module name and icon  *28/04/2022* - 1.3.1
- NEW: ajout massaction "Lier commercial" sur la liste des Tiers
  => donne la possibilité d'ajouter ou de remplacer le ou les commerciaux des Tiers sélectionnés *2021-12-20* - 1.3.0
- NEW: ajout massaction "Mailing : ajouter destinataires" sur la liste des Tiers, Contacts, Adhérents et Utilisateurs
  => donne la possibilité d'ajouter des destinataires à un mailing grâce à cette massaction *2021-12-20* - 1.2.0
- NEW: cleanup (delete unused files + functions) + compatibility with
  Dolibarr v13-v14 - *2021-08-03* - 1.1.0

## Release 1.0
- No changelog existed prior to this release
