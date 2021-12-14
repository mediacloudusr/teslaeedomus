# Plugin Véhicule Tesla pour Eedomus

Suivez les paramètres de votre véhicule Tesla avec ce plugin pour [Eedomus](https://www.eedomus.com/).

Ce plugin a été développé par [mediacloud](https://forum.eedomus.com/ucp.php?i=pm&mode=compose&u=5280).

Voir la [discussion sur le forum](https://forum.eedomus.com/viewtopic.php?f=16&t=10515) for more information or for feedback.

La version actuelle est 1.7.1.

## Fonctionnalités

![tesla car overview](https://user-images.githubusercontent.com/94607717/145950751-715d7029-d674-4ff6-bfa9-946e81c59a48.png)

Ce plugin envoie les données suivantes à Eedomus :

- localisation du véhicule, latitude, longitude
- niveau de la batterie
- limite de recharge
- énergie ajoutée (kWh, coût)
- état de la trappe de la prise
- état de la recharge
- minutes de recharge restantes
- temps de recharge restant (h min)
- courant de recharge
- limite du courant de recharge
- tension de recharge
- puissance de recharge
- autonomie restante (théorique)
- autonomie restante (estimée)
- température extérieure
- température intérieure
- état du chauffage et climatisation
- état du chauffage du siège gauche et droit
- compteur kilométrique
- verrouillage du véhicule
- nom du véhicule
- levier de vitesse
- vitesse
- sentinelle

![tesla car actions](https://user-images.githubusercontent.com/94607717/145906384-73e170f4-7d2a-4093-a844-692092e90d8a.png)

Il expose les commandes suivantes (qui peuvent être utilisées dans les règles) :

- réveiller le véhicule
- verrouiller/déverrouiller le véhicule
- faire clignoter l'éclairage extérieur
- activer le klaxon
- démarrer/arrêter la recharge
- ouvrir/fermer la trappe de la prise de recharge
- régler la limite de recharge (50%, 60%, 70%, 75%, 80%, 85%, 90%, 95%, 100%)
- régler le courant de recharge (5A, 8A, 10A, 13A, 16A, 20A, 24A, 28A, 32A)
- démarrer/arrêter le chauffage et la climatisation
- régler le chauffage du siège gauche et droit
- activer/désactiver Sentinelle

Note : chaque commande réveille le véhicule si nécessaire.

## Installation et configuration

Installez le plugin depuis le store Eedomus.

Il est recommandé d'avoir votre voiture Tesla réveillé lorsque vous installez le plugin. Pour réveiller le véhicule, lancez l'application Tesla sur votre téléphone, ou ouvrez/fermez une portière.

### Pièce

Créer une pièce pour y affecter le véhicule Tesla.

### Code et authentification

Le plugin récupèrera automatique le jeton d'accès en se connectant au serveurs Tesla. Il sera renouvelé automatiquement toutes les 8 heures.

Le plugin a besoin d'un code pour récupérer le premier jeton. Pour obtenir le code :

- Cliquez sur le lien pour vous connecter avec votre compte Tesla

![auth url](https://user-images.githubusercontent.com/94607717/145906592-41b94333-5be6-4081-8184-0af5c1279c6d.png)

- Connectez-vous au site Tesla avec votre compte
- Une fois fait, une page "Page Not Found" sera affichée. C'est normal. Regardez l'URL et récupérer le paramètre **code** (texte après `code=` et jusqu'au `&`)

![auth url](https://user-images.githubusercontent.com/94607717/144481395-b52b58f2-90b6-42c3-9f9a-4202525e1cca.png)

- Le code est valide 2 minutes. Copiez le dans la paramètre correspondant du plugin.

![code paste](https://user-images.githubusercontent.com/94607717/145906603-0cacb740-ed61-488f-b2bc-ffdfa3669656.png)

### VIN

Par défaut, le plugin sélectionne le premier véhicule du compte. Vous pouvez sélectionner un autre véhicule de votre compte en fournissant son VIN.

### Création

Cliquez sur `Créer`.
Allez ensuite dans la pièce Tesla. Vous devriez voir les données quelques secondes après (si le véhicule est réveillé).

## Notes sur les intervalles de connexion et l'impact sur la batterie

Il y a des optimisations dans le plugin pour éviter de vider la batterie. Voici quelques détails :

- L'intervalle d'interrogation est de 1 à 3 minutes pour les compteurs mais il y a un cache de données de 15 minutes dans le script pour permettre à la voiture de se mettre en veille. Les données rapportées peuvent donc avoir 15 min de retard, y compris pour les données de géolocalisation.
- Lorsque la voiture est endormie, les données générales de la voiture et les données GPS sont récupérées toutes les 15 minutes mais les données seront vides (ou identiques) car la voiture est endormie. Il y a une exception pour l'**état de la voiture** qui utilise une API différente : l'état est toujours mis à jour toutes les 3 minutes.
- Lorsque la voiture est active (la climatisation est en marche, la charge est en cours, la voiture n'est pas garée ou la sentinelle est activée), le suivi est effectué toutes les 3 minutes. Si la voiture semble inactive pendant 10 minutes, la surveillance revient à toutes les 15 minutes pour que la voiture puisse s'endormir.

## Note sur le prix de l'électricité

Vous pouvez modifier le prix du kWh dans la configuration du compteur "Energie ajoutée (coût)". Mettez à jour la valeur dans l'expression XPATH.

![cost](https://user-images.githubusercontent.com/94607717/145906757-6c79004c-3f3a-4b1c-a600-6c80a0b37ce3.png)
